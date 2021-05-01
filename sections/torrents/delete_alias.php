<?php

declare(strict_types=1);

$ArtistID = db_string($_GET['artistid']);
$GroupID = db_string($_GET['groupid']);

if (!is_number($ArtistID) || !is_number($GroupID)) {
    error(404);
}
if (!check_perms('torrents_edit')) {
    error(403);
}

// Remove artist from this group.
$DB->query("
  DELETE FROM torrents_artists
  WHERE GroupID = '{$GroupID}'
    AND ArtistID = '{$ArtistID}'");

$DB->query("
  SELECT Name
  FROM artists_group
  WHERE ArtistID = {$ArtistID}");
[$ArtistName] = $DB->next_record(MYSQLI_NUM, false);

$DB->query("
  SELECT Name
  FROM torrents_group
  WHERE ID = {$GroupID}");
if (!$DB->has_results()) {
    error(404);
}
[$GroupName] = $DB->next_record(MYSQLI_NUM, false);

// Get a count of how many groups or requests use this artist ID
$DB->query("
  SELECT ag.ArtistID
  FROM artists_group AS ag
    LEFT JOIN requests_artists AS ra ON ag.ArtistID = ra.ArtistID
  WHERE ra.ArtistID IS NOT NULL
    AND ag.ArtistID = {$ArtistID}");
$ReqCount = $DB->record_count();
$DB->query("
  SELECT ag.ArtistID
  FROM artists_group AS ag
    LEFT JOIN torrents_artists AS ta ON ag.ArtistID = ta.ArtistID
  WHERE ta.ArtistID IS NOT NULL
    AND ag.ArtistID = {$ArtistID}");
$GroupCount = $DB->record_count();
if (($ReqCount + $GroupCount) == 0) {
    // The only group to use this artist
    Artists::delete_artist($ArtistID);
}

$Cache->delete_value(sprintf('torrents_details_%s', $GroupID)); // Delete torrent group cache
$Cache->delete_value(sprintf('groups_artists_%s', $GroupID)); // Delete group artist cache
Misc::write_log(sprintf('Artist %s (%s) was removed from the group %s (%s) by user ', $ArtistID, $ArtistName, $GroupID, $GroupName) . $LoggedUser['ID'] . ' (' . $LoggedUser['Username'] . ')');
Torrents::write_group_log($GroupID, 0, $LoggedUser['ID'], sprintf('removed artist %s', $ArtistName), 0);

Torrents::update_hash($GroupID);
$Cache->delete_value(sprintf('artist_groups_%s', $ArtistID));

header('Location: ' . $_SERVER['HTTP_REFERER']);
