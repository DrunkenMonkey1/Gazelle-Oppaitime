<?php declare(strict_types=1);
enforce_login();

if (!check_perms('site_top10')) {
    View::show_header(); ?>
<div class="content_basiccontainer">
  You do not have access to view this feature.
</div>
<?php
  View::show_footer();
    die();
}

include SERVER_ROOT . '/sections/torrents/functions.php'; //Has get_reports($TorrentID);
if (empty($_GET['type']) || 'torrents' == $_GET['type']) {
    include SERVER_ROOT . '/sections/top10/torrents.php';
} else {
    match ($_GET['type']) {
        'users' => include SERVER_ROOT . '/sections/top10/users.php',
        'tags' => include SERVER_ROOT . '/sections/top10/tags.php',
        'history' => include SERVER_ROOT . '/sections/top10/history.php',
        'donors' => include SERVER_ROOT . '/sections/top10/donors.php',
        default => error(404),
    };
}
?>
