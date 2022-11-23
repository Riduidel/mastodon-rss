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

///////////////////////////////////////////////////////////////////////////////////////////////
?>
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:dc = "http://purl.org/dc/elements/1.1/" xmlns:content = "http://purl.org/rss/1.0/modules/content/" xmlns:creativeCommons = "http://backend.userland.com/creativeCommonsRssModule">
    <channel>
        <atom:link href="<?= $account->getProfileUrl() ?>" rel="self" type="application/rss+xml" />
        <lastBuildDate>Tue, 15 Nov 2022 18:37:29 +0100</lastBuildDate>
        <language>en</language>
        <title><?= "RSS Timeline of ".$account->getQualifiedAccountName() ?></title>
        <description><![CDATA[ <?= strip_tags($account->getBio()) ?> ]]></description>
        <link><?= "https://".$account->getInstanceHostname() ?></link>
                <ttl>960</ttl>
        <generator>mastodon-rss</generator>
        <category>Personal</category>
        <image>
            <title><?= "RSS Timeline of ".$account->getQualifiedAccountName() ?></title>
            <link><?= $account->getProfileUrl() ?></link>
            <url><?= $account->getAvatarUrl() ?></url>
        </image>
        <creativeCommons:license>http://creativecommons.org/licenses/by-nc-sa/3.0/</creativeCommons:license>
    </channel>


<?php

foreach ($client->getHomeTimeline() as $item) {
    $contentProvider = $item;
    do {
        $content = $contentProvider->getContent();
        if($contentProvider->getReblog()==null) {
            break;
        } else {
            $contentProvider = $contentProvider->getReblog();
        }
    } while(true);
    $title = strip_tags($content);
    $title = (strlen($title)>MAX_TITLE_LENGTH) ? substr($title, 0, MAX_TITLE_LENGTH-3)."..." : $title;
    ?>
    <item>
        <title><![CDATA[<?= $title ?>]]></title>
        <author><![CDATA[<?= $contentProvider->getAccount()->getDisplayName() ?>]]></author>
        <pubDate><?= $contentProvider->getEditedAt()==null ? $contentProvider->getEditedAt()->format(DateTimeInterface::ATOM) : $contentProvider->getCreatedAt()->format(DateTimeInterface::ATOM) ?></pubDate>
        <link><?= $contentProvider->getUri() ?></link>
        <guid isPermaLink='false'><?= $contentProvider->getId() ?></guid>
        <description><![CDATA[ 
            <div style='float:left;margin: 0 6px 6px 0;'>
            <?php $avatarProvider = $item; 
            do {
                ?>
	<a href='<?= $avatarProvider->getUri() ?>' title='<?= $avatarProvider->getAccount()->getDisplayName()?>' border=0 target='blank'>
		<img src='<?= $avatarProvider->getAccount()->getAvatarUrl() ?>' width=20 border=0 />
	</a>
                <?php
                $avatarProvider = $avatarProvider->getReblog();
            } while($avatarProvider!=null);
                ?>
            </div>
            <b><?= $contentProvider->getAccount()->getDisplayName() ?></b>
            <a href="<?= $contentProvider->getAccount()->getProfileUrl() ?>"
                title="<?= strip_tags($contentProvider->getAccount()->getBio()) ?>">
                <?= $contentProvider->getAccount()->getQualifiedAccountName() ?>
            </a><br/>
<?= $content ?>
        <?php 
        foreach ($contentProvider->getMedias() as $media) {
            if($media->getType()=="image") {
                if($media->getMeta()["original"]) {
                    $original = $media->getMeta()["original"];
                ?>
                <img src="<?= $media->getUrl() ?>" 
                    width="<?= $original->getWidth() ?>" 
                    height="<?= $original->getHeight() ?>" 
                    />
                <?php
                }
            } else {
                ?>
                <b>Unknown media</b>
                <pre> <?= json_encode($media->jsonSerialize()) ?></pre>
                <div>Please fill a bug at <a href="https://github.com/Riduidel/mastodon-rss/issues">https://github.com/Riduidel/mastodon-rss/issues</a> with preformatted code attached</div>
                <?php
            }
        }
        ?>
</div>
        ]]></description>

    </item>

    <?php
}

header("Content-Type: application/rss+xml");
header("Content-type: text/xml; charset=utf-8");
?>

</rss>