<?php

declare(strict_types=1);
/**************************************************************************
Artists Switch Center

This page acts as a switch that includes the real artist pages (to keep
the root less cluttered).

enforce_login() is run here - the entire artist pages are off limits for
non members.
 ****************************************************************************/

// Width and height of similar artist map
define('WIDTH', 585);
define('HEIGHT', 400);

enforce_login();
if (!empty($_POST['action'])) {
    match ($_POST['action']) {
        'edit' => require SERVER_ROOT . '/sections/artist/takeedit.php',
        'download' => require SERVER_ROOT . '/sections/artist/download.php',
        'rename' => require SERVER_ROOT . '/sections/artist/rename.php',
        'add_similar' => require SERVER_ROOT . '/sections/artist/add_similar.php',
        'add_alias' => require SERVER_ROOT . '/sections/artist/add_alias.php',
        'change_artistid' => require SERVER_ROOT . '/sections/artist/change_artistid.php',
        'concert_thread' => include SERVER_ROOT . '/sections/artist/concert_thread.php',
        'take_concert_thread' => include SERVER_ROOT . '/sections/artist/take_concert_thread.php',
        default => error(0),
    };
} elseif (!empty($_GET['action'])) {
    match ($_GET['action']) {
        'autocomplete' => require __DIR__ . '/sections/artist/autocomplete.php',
        'edit' => require SERVER_ROOT . '/sections/artist/edit.php',
        'delete' => require SERVER_ROOT . '/sections/artist/delete.php',
        'revert' => require SERVER_ROOT . '/sections/artist/takeedit.php',
        'history' => require SERVER_ROOT . '/sections/artist/history.php',
        'vote_similar' => require SERVER_ROOT . '/sections/artist/vote_similar.php',
        'delete_similar' => require SERVER_ROOT . '/sections/artist/delete_similar.php',
        'similar' => require SERVER_ROOT . '/sections/artist/similar.php',
        'similar_bg' => require SERVER_ROOT . '/sections/artist/similar_bg.php',
        'notify' => require SERVER_ROOT . '/sections/artist/notify.php',
        'notifyremove' => require SERVER_ROOT . '/sections/artist/notifyremove.php',
        'delete_alias' => require SERVER_ROOT . '/sections/artist/delete_alias.php',
        'change_artistid' => require SERVER_ROOT . '/sections/artist/change_artistid.php',
        default => error(0),
    };
} elseif (!empty($_GET['id'])) {
    include SERVER_ROOT . '/sections/artist/artist.php';
} elseif (!empty($_GET['artistname'])) {
    $NameSearch = str_replace('\\', '\\\\', trim($_GET['artistname']));
    /*
        $DB->query("
          SELECT ArtistID, Name
          FROM artists_alias
          WHERE Name LIKE '" . db_string($NameSearch) . "'");
    */
    $DB->query("
      SELECT ArtistID, Name
      FROM artists_group
      WHERE Name LIKE '" . db_string($NameSearch) . "'");
    if (!$DB->has_results()) {
        if (isset($LoggedUser['SearchType']) && $LoggedUser['SearchType']) {
            header('Location: torrents.php?action=advanced&artistname=' . urlencode($_GET['artistname']));
        } else {
            header('Location: torrents.php?searchstr=' . urlencode($_GET['artistname']));
        }
        die();
    }
    [$FirstID, $Name] = $DB->next_record(MYSQLI_NUM, false);
    if (1 === $DB->record_count() || !strcasecmp($Name, $NameSearch)) {
        header(sprintf('Location: artist.php?id=%s', $FirstID));
        die();
    }
    while ([$ID, $Name] = $DB->next_record(MYSQLI_NUM, false)) {
        if (0 === strcasecmp($Name, $NameSearch)) {
            header(sprintf('Location: artist.php?id=%s', $ID));
            die();
        }
    }
    header(sprintf('Location: artist.php?id=%s', $FirstID));
    die();
} else {
    header('Location: torrents.php');
}
