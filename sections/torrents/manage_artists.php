<?php

declare(strict_types=1);

if (empty($_POST['importance']) || empty($_POST['artists']) || empty($_POST['groupid']) || !is_number($_POST['importance']) || !is_number($_POST['groupid'])) {
    error(0);
}
if (!check_perms('torrents_edit')) {
    error(403);
}
authorize();

$GroupID = $_POST['groupid'];
$Artists = explode(',', $_POST['artists']);
$CleanArtists = [];
$ArtistIDs = [];
$ArtistsString = '0';

foreach ($Artists as $i => $Artist) {
    [$Importance, $ArtistID] = explode(';', $Artist);
    if (is_number($ArtistID) && is_number($Importance)) {
        $CleanArtists[] = [$Importance, $ArtistID];
        $ArtistIDs[] = $ArtistID;
    }
}

if ([] !== $CleanArtists) {
    $ArtistsString = implode(',', $ArtistIDs);
    if ('delete' == $_POST['manager_action']) {
        $DB->query("
      SELECT Name
      FROM torrents_group
      WHERE ID = '" . $_POST['groupid'] . "'");
        [$GroupName] = $DB->next_record();
        $DB->query("
      SELECT ArtistID, Name
      FROM artists_group
      WHERE ArtistID IN ({$ArtistsString})");
        $ArtistNames = $DB->to_array('ArtistID', MYSQLI_ASSOC, false);
        foreach ($CleanArtists as $Artist) {
            [$Importance, $ArtistID] = $Artist;
            Misc::write_log('Artist (' . $ArtistTypes[$Importance] . sprintf(') %s (', $ArtistID) . $ArtistNames[$ArtistID]['Name'] . ") was removed from the group " . $_POST['groupid'] . sprintf(' (%s) by user ', $GroupName) . $LoggedUser['ID'] . ' (' . $LoggedUser['Username'] . ')');
            Torrents::write_group_log($GroupID, 0, $LoggedUser['ID'], "Removed artist " . $ArtistNames[$ArtistID]['Name'] . " (" . $ArtistTypes[$Importance] . ")", 0);
            $DB->query("
        DELETE FROM torrents_artists
        WHERE GroupID = '{$GroupID}'
          AND ArtistID = '{$ArtistID}'
          AND Importance = '{$Importance}'");
            $Cache->delete_value(sprintf('artist_groups_%s', $ArtistID));
        }
        $DB->query("
      SELECT ArtistID
        FROM requests_artists
        WHERE ArtistID IN ({$ArtistsString})
      UNION
      SELECT ArtistID
        FROM torrents_artists
        WHERE ArtistID IN ({$ArtistsString})");
        $Items = $DB->collect('ArtistID');
        $EmptyArtists = array_diff($ArtistIDs, $Items);
        foreach ($EmptyArtists as $ArtistID) {
            Artists::delete_artist($ArtistID);
        }
    } else {
        $DB->query("
      UPDATE IGNORE torrents_artists
      SET Importance = '" . $_POST['importance'] . "'
      WHERE GroupID = '{$GroupID}'
        AND ArtistID IN ({$ArtistsString})");
    }
    $Cache->delete_value(sprintf('groups_artists_%s', $GroupID));
    Torrents::update_hash($GroupID);
    header(sprintf('Location: torrents.php?id=%s', $GroupID));
}
