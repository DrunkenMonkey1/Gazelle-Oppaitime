<?php

declare(strict_types=1);

// Main feeds page
// The feeds don't use script_start.php, their code resides entirely in feeds.php in the document root
// Bear this in mind when you try to use script_start functions.

if (
  empty($_GET['feed'])
  || empty($_GET['authkey'])
  || empty($_GET['auth'])
  || empty($_GET['passkey'])
  || empty($_GET['user'])
  || !is_number($_GET['user'])
  || 32 !== strlen($_GET['authkey'])
  || 32 !== strlen($_GET['passkey'])
  || 32 !== strlen($_GET['auth'])
) {
    $Feed->open_feed();
    $Feed->channel('Blocked', 'RSS feed.');
    $Feed->close_feed();
    die();
}

$User = (int)$_GET['user'];

if (!$Enabled = $Cache->get_value(sprintf('enabled_%s', $User))) {
    require_once SERVER_ROOT . '/classes/mysql.class.php';
    $DB = new DB_MYSQL(); //Load the database wrapper
    $DB->query("
    SELECT Enabled
    FROM users_main
    WHERE ID = '{$User}'");
    [$Enabled] = $DB->next_record();
    $Cache->cache_value(sprintf('enabled_%s', $User), $Enabled, 0);
}

if (md5($User . RSS_HASH . $_GET['passkey']) !== $_GET['auth'] || 1 != $Enabled) {
    $Feed->open_feed();
    $Feed->channel('Blocked', 'RSS feed.');
    $Feed->close_feed();
    die();
}

require_once SERVER_ROOT . '/classes/text.class.php';
$Feed->open_feed();
switch ($_GET['feed']) {
  case 'feed_news':
    $Feed->channel('News', 'RSS feed for site news.');
    if (!$News = $Cache->get_value('news')) {
        require_once SERVER_ROOT . '/classes/mysql.class.php'; //Require the database wrapper
      $DB = new DB_MYSQL(); //Load the database wrapper
      $DB->query("
        SELECT
          ID,
          Title,
          Body,
          Time
        FROM news
        ORDER BY Time DESC
        LIMIT 10");
        $News = $DB->to_array(false, MYSQLI_NUM, false);
        $Cache->cache_value('news', $News, 1_209_600);
    }
    $Count = 0;
    foreach ($News as $NewsItem) {
        [$NewsID, $Title, $Body, $NewsTime] = $NewsItem;
        if (strtotime($NewsTime) >= time()) {
            continue;
        }
        echo $Feed->item($Title, Text::strip_bbcode($Body), sprintf('index.php#news%s', $NewsID), SITE_NAME . ' Staff', '', '', $NewsTime);
        if (++$Count > 4) {
            break;
        }
    }
    break;
  case 'feed_blog':
    $Feed->channel('Blog', 'RSS feed for site blog.');
    if (!$Blog = $Cache->get_value('blog')) {
        require_once SERVER_ROOT . '/classes/mysql.class.php'; //Require the database wrapper
      $DB = new DB_MYSQL(); //Load the database wrapper
      $DB->query("
        SELECT
          b.ID,
          um.Username,
          b.UserID,
          b.Title,
          b.Body,
          b.Time,
          b.ThreadID
        FROM blog AS b
          LEFT JOIN users_main AS um ON b.UserID = um.ID
        ORDER BY Time DESC
        LIMIT 20");
        $Blog = $DB->to_array();
        $Cache->cache_value('blog', $Blog, 1_209_600);
    }
    foreach ($Blog as $BlogItem) {
        [$BlogID, $Author, $AuthorID, $Title, $Body, $BlogTime, $ThreadID] = $BlogItem;
        if ($ThreadID) {
            echo $Feed->item($Title, Text::strip_bbcode($Body), sprintf('forums.php?action=viewthread&amp;threadid=%s', $ThreadID), SITE_NAME . ' Staff', '', '', $BlogTime);
        } else {
            echo $Feed->item($Title, Text::strip_bbcode($Body), sprintf('blog.php#blog%s', $BlogID), SITE_NAME . ' Staff', '', '', $BlogTime);
        }
    }
    break;
  case 'feed_changelog':
    $Feed->channel('Gazelle Change Log', "RSS feed for Gazelle's changelog.");
    if (!$Changelog = $Cache->get_value('changelog')) {
        require_once SERVER_ROOT . '/classes/mysql.class.php';
        require_once SERVER_ROOT . '/classes/misc.class.php';

        $DB = new DB_MYSQL();
        $DB->query("
        SELECT Message, Author, Date(Time)
        FROM changelog
        ORDER BY Time DESC
        LIMIT 20");
        $Changelog = $DB->to_array();
        $Cache->cache_value('changelog', $Changelog, 86400);
    }
    foreach ($Changelog as $Change) {
        [$Message, $Author, $Date] = $Change;
        echo $Feed->item(sprintf('%s by %s', $Date, $Author), $Message, 'tools.php?action=change_log', SITE_NAME . ' Staff', '', '', $Date);
    }
    break;
  case 'torrents_all':
    $Feed->channel('All Torrents', 'RSS feed for all new torrent uploads.');
    $Feed->retrieve('torrents_all', $_GET['authkey'], $_GET['passkey']);
    break;
  case 'torrents_movies':
    $Feed->channel('Movie Torrents', 'RSS feed for all new music torrents.');
    $Feed->retrieve('torrents_movies', $_GET['authkey'], $_GET['passkey']);
    break;
  case 'torrents_anime':
    $Feed->channel('Anime Torrents', 'RSS feed for all new application torrents.');
    $Feed->retrieve('torrents_anime', $_GET['authkey'], $_GET['passkey']);
    break;
  case 'torrents_manga':
    $Feed->channel('Manga Torrents', 'RSS feed for all new e-book torrents.');
    $Feed->retrieve('torrents_manga', $_GET['authkey'], $_GET['passkey']);
    break;
  case 'torrents_games':
    $Feed->channel('Game Torrents', 'RSS feed for all new audiobook torrents.');
    $Feed->retrieve('torrents_games', $_GET['authkey'], $_GET['passkey']);
    break;
  case 'torrents_other':
    $Feed->channel('Other Torrents', 'RSS feed for all new e-learning video torrents.');
    $Feed->retrieve('torrents_other', $_GET['authkey'], $_GET['passkey']);
    break;
  default:
    // Personalized torrents
    if (empty($_GET['name']) && 'torrents_notify_' == substr($_GET['feed'], 0, 16)) {
        // All personalized torrent notifications
        $Feed->channel('Personalized torrent notifications', 'RSS feed for personalized torrent notifications.');
        $Feed->retrieve($_GET['feed'], $_GET['authkey'], $_GET['passkey']);
    } elseif (!empty($_GET['name']) && 'torrents_notify_' == substr($_GET['feed'], 0, 16)) {
        // Specific personalized torrent notification channel
        $Feed->channel(display_str($_GET['name']), 'Personal RSS feed: ' . display_str($_GET['name']));
        $Feed->retrieve($_GET['feed'], $_GET['authkey'], $_GET['passkey']);
    } elseif (!empty($_GET['name']) && 'torrents_bookmarks_t_' == substr($_GET['feed'], 0, 21)) {
        // Bookmarks
        $Feed->channel('Bookmarked torrent notifications', 'RSS feed for bookmarked torrents.');
        $Feed->retrieve($_GET['feed'], $_GET['authkey'], $_GET['passkey']);
    } else {
        $Feed->channel('All Torrents', 'RSS feed for all new torrent uploads.');
        $Feed->retrieve('torrents_all', $_GET['authkey'], $_GET['passkey']);
    }
}
$Feed->close_feed();
