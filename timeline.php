<?php
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
$followings = $client->getFollowings();
$feeds = array_map(function($account) {
    return $account->getProfileUrl().".rss";
}, $followings);

// And then, let's "borrow" (in other words copy/paste like the best StackOverflow developer in the world)
// A good example of feed merging using simplepie
// This code is courtesy of https://digitalfreelancing.eu/php-how-to-join-combine-merge-different-rss-feeds-into-one-using-your-own-server/

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
$channel->appendChild($dom->createElement("link", $account->getInstanceHostname()));
$channel->appendChild($dom->createElement("description", $account->getBio()));
// $channel->appendChild($dom->createElement("language", "en-US"));
$channel->appendChild($dom->createElement("copyright", '2007-'.date("Y")));
$channel->appendChild($dom->createElementNS($namespaces["creativeCommons"], "license", "http://creativecommons.org/licenses/by-nc-sa/3.0/"));
$image = $dom->createElement("image"); $channel->appendChild($image);
$image->appendChild($dom->createElement("title", $titleText));
$image->appendChild($dom->createElement("link", $account->getProfileUrl()));
$image->appendChild($dom->createElement("url", $account->getAvatarUrl()));


date_default_timezone_set('America/Chicago');
$feed = new SimplePie(); // Create a new instance of SimplePie
// Load the feeds
$feed->set_feed_url($feeds);
$feed->set_cache_duration (600); // Set the cache time
$success = $feed->init(); // Initialize SimplePie
$feed->handle_content_type(); // Take care of the character encoding

foreach($feed->get_items() as $item) {
    $xml = $dom->createElement("item");
    $xml->appendChild(new DOMElement("title", $item->get_title()));
    $xml->appendChild(new DOMElement("link", $item->get_permalink()));
    $xml->appendChild(new DOMElement("guid", $item->get_permalink()));
    $xml->appendChild(new DOMElement("pubDate", $item->get_date('D, d M Y H:i:s T')));
    $xml->appendChild(new DOMElement("author", $item->get_author()));
    $description = $dom->createElement("description"); $xml->appendChild($description);
    $description->appendChild(new DOMCdataSection($item->get_content()));

    $rss->appendChild($xml);
}

header("Content-Type: application/rss+xml");
header("Content-type: text/xml; charset=utf-8");
?>
<?= $dom->saveXML() ?>