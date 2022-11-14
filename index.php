<?php
/**
 * This is not a real max count, but rather a factor after which we try to limit the number
 * of messages we insert
 */
const MAX_COUNT=50;
const MAX_TITLE_LENGTH=80;

// See all error except warnings
error_reporting(E_ALL^E_WARNING^E_DEPRECATED);

require('vendor/autoload.php');

use Phediverse\MastodonRest\{Auth\AppRegisterClient, Auth\Scope, Resource\Application};

$config = include('config.php');
if(!array_key_exists('user', $config)) {
    die("There must be a \"user\" array in confg (see config.php.example)");
}
if(!array_key_exists('host', $config['application'])) {
    $config['application']['host'] = "https://".$config['user']['server'];
}
if(!array_key_exists('name', $config['application'])) {
    $config['application']['name']='mastodon-rss';
}
if(!array_key_exists('redirect_uris', $config['application'])) {
    $config['application']['redirect_uris']='urn:ietf:wg:oauth:2.0:oob';
}
if(!array_key_exists('scopes', $config['application'])) {
    $config['application']['scopes']='read';
}
if(!array_key_exists('instance', $config['application'])) {
    $host_parts = parse_url($config['application']['host']);
    $config['application']['instance'] = $host_parts['host'];
}

$app = \Phediverse\MastodonRest\Resource\Application::fromData($config['application']);
$authClient = \Phediverse\MastodonRest\Auth\AuthClient::forApplication($app);

$accessToken = $authClient->login($config['user']['email'], $config['user']['password']);

$client = \Phediverse\MastodonRest\Client::build($config['application']['instance'], $accessToken);

file_put_contents('config.php', '<?php return ' . var_export($config, true) . '; ?>');

$account = $client->getAccount();
// See https://docs.joinmastodon.org/methods/accounts/#retrieve-information
$followings = $client->getFollowings();
$feeds = array_map(function($account) {
    return $account->getProfileUrl().".rss";
}, $followings);

// These feeds will be used later, when getting the various elements in a pool

// And then, let's "borrow" (in other words copy/paste like the best StackOverflow developer in the world)
// A good example of feed merging using simplepie
// This code is a heavily modified version of some code borrowed from https://digitalfreelancing.eu/php-how-to-join-combine-merge-different-rss-feeds-into-one-using-your-own-server/


///////////////////////////////////////////////////////////////////////////////////////////////
$dom = new DOMDocument('1.0');
$dom->preserveWhiteSpace = false;
$dom->formatOutput = true;
$namespaces = array(
    "atom" => "http://www.w3.org/2005/Atom",
    "content" => "http://purl.org/rss/1.0/modules/content/",
    "dc" => "http://purl.org/dc/elements/1.1/",
    "content" => "http://purl.org/rss/1.0/modules/content/" ,
    "creativeCommons" => "http://backend.userland.com/creativeCommonsRssModule"
);

$rss = $dom->createElement("rss");
$rss->setAttribute("version", "2.0");
$dom->appendChild($rss);

$channel = $dom->createElement("channel"); $rss->appendChild($channel);
$titleText = "RSS Timeline of ".$account->getQualifiedAccountName();
$channel->appendChild($dom->createElement("title", $titleText));
$atomLink = $dom->createElementNS($namespaces["atom"], "link"); 
$atomLink->setAttribute("href", $account->getProfileUrl());
$atomLink->setAttribute("rel", "self");
$atomLink->setAttribute("type", "text/html");
$channel->appendChild($atomLink);
$channel->appendChild($dom->createElement("link", "https://".$account->getInstanceHostname()));
$description = $dom->createElement("description");
$description->appendChild($dom->createCDATASection(strip_tags($account->getBio())));
$channel->appendChild($description);
// $channel->appendChild($dom->createElement("language", "en-US"));
$channel->appendChild($dom->createElement("copyright", '2007-'.date("Y")));
$channel->appendChild($dom->createElementNS($namespaces["creativeCommons"], "license", "http://creativecommons.org/licenses/by-nc-sa/3.0/"));
$image = $dom->createElement("image"); $channel->appendChild($image);
$image->appendChild($dom->createElement("title", $titleText));
$image->appendChild($dom->createElement("link", $account->getProfileUrl()));
$image->appendChild($dom->createElement("url", $account->getAvatarUrl()));


use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

$http = new \GuzzleHttp\Client();
$requests = function () use ($feeds) {
    foreach ($feeds as $f) {
        yield new Request('GET', $f);
    }
};
// This array will contain all messages sorted by date
use chdemko\SortedCollection\TreeMap;
$messages = TreeMap::create();
$pool = new Pool($http, $requests(), [
    'concurrency' => 10,
    'fulfilled' => function (Response $response, $index) use ($messages, $followings) {
        $content = (string) $response->getBody();
        $dom = new DOMDocument();
        $dom->loadXML($content);
        // Read all items
        $author = $followings[$index]->getQualifiedAccountName();
        $items  =$dom->getElementsByTagName('item');
        foreach ($items as $i ) {
            // This element is mandatory, no?
            $pubDateList = $i->getElementsByTagName("pubDate");
            // Don't forget to restore author from the following list
            if(count($i->getElementsByTagName("author"))>0) {
                $authorElement = $i->getElementsByTagName("author")[0];
                $authorElement->nodeValue = $author;
            } else {
                $i->appendChild($dom->createElement("author", $author));
            }
            foreach($pubDateList as $pubDate) {
                $date = $pubDate->nodeValue;
                $timestamp = strtotime($date);
                $insert_message = true;
                if(count($messages)>MAX_COUNT) {
                    if($timestamp<$messages->firstKey) {
                        $insert_message = false;
                    }
                }
                if($insert_message) {
                    $messages->put(array($timestamp =>$i));
                }
            }
        }
    },
    'rejected' => function (RequestException $reason, $index) {
        echo "Rejected response";
    },
]);

// Initiate the transfers and create a promise
$promise = $pool->promise();

// Force the pool of requests to complete.
$promise->wait();

use chdemko\SortedCollection\ReversedMap;
$sortedMessages = ReversedMap::create($messages);
$messageIndex = 0;
foreach ($sortedMessages as $instant => $item) {
    // Create an output node from input one
    // I used to make a simple import node, but it resulted in far too ugly nodes, so now I use ... more radical ways

    $pubDate = $item->getElementsByTagName("pubDate")[0]->nodeValue;
    $guid = $item->getElementsByTagName("guid")[0]->nodeValue;
    $author = $item->getElementsByTagName("author")[0]->nodeValue;
    $link = $item->getElementsByTagName("link")[0]->nodeValue;
    $text = $item->getElementsByTagName("description")[0]->nodeValue;
    $title = strip_tags($text);
    $title = (strlen($title)>MAX_TITLE_LENGTH) ? substr($title, 0, MAX_TITLE_LENGTH-3)."..." : $title;
    $xml = $dom->createElement("item");
    $xml->appendChild(new DOMElement("title", $title));
    $xml->appendChild(new DOMElement("link", $link));
    $xml->appendChild(new DOMElement("guid", $guid));
    $xml->appendChild(new DOMElement("pubDate", $pubDate));
    $xml->appendChild(new DOMElement("author", $author));
    $description = $dom->createElement("description"); 
    $description->appendChild(new DOMCdataSection($text));
    // Now add the medias

    $xml->appendChild($description);

    $rss->appendChild($xml);

    $messageIndex++;
    if($messageIndex>MAX_COUNT)
        break;
}


header("Content-Type: application/rss+xml");
header("Content-type: text/xml; charset=utf-8");
?>
<?= $dom->saveXML() ?>