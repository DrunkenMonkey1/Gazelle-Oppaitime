<?php

declare(strict_types=1);

//******************************************************************************//
//--------------- Take unfill request ------------------------------------------//

authorize();

$RequestID = $_POST['id'];
if (!is_number($RequestID)) {
    error(0);
}

$DB->query("
  SELECT
    r.CategoryID,
    r.UserID,
    r.FillerID,
    r.Title,
    u.Uploaded,
    u.BonusPoints,
    r.GroupID,
    t.UserID
  FROM requests AS r
    LEFT JOIN torrents AS t ON t.ID = TorrentID
    LEFT JOIN users_main AS u ON u.ID = FillerID
  WHERE r.ID = {$RequestID}");
[$CategoryID, $UserID, $FillerID, $Title, $Uploaded, $BonusPoints, $GroupID, $UploaderID] = $DB->next_record();

if (!$UploaderID) {
    // If the torrent was deleted and we don't know who the uploader was, just assume it was the filler
    $UploaderID = $FillerID;
}

if ((($LoggedUser['ID'] !== $UserID && $LoggedUser['ID'] !== $FillerID) && !check_perms('site_moderate_requests')) || '0' === $FillerID) {
    error(403);
}

// Unfill
$DB->query("
  UPDATE requests
  SET TorrentID = 0,
    FillerID = 0,
    TimeFilled = NULL,
    Visible = 1
  WHERE ID = {$RequestID}");

$CategoryName = $Categories[$CategoryID - 1];

$ArtistForm = Requests::get_artists($RequestID);
$ArtistName = Artists::display_artists($ArtistForm, false, true);
$FullName = $ArtistName . $Title;

$RequestVotes = Requests::get_votes_array($RequestID);

//Remove Filler portion of bounty
if ((int) ($RequestVotes['TotalBounty']*(1/4)) > $Uploaded) {
    // If we can't take it all out of upload, attempt to take the rest out of bonus points
    $DB->query("
    UPDATE users_main
    SET Uploaded = 0
    WHERE ID = {$FillerID}");
    if ((int) ($RequestVotes['TotalBounty']*(1/4)-$Uploaded) > $BonusPoints) {
        // If we can't take the rest as bonus points, turn the remaining bit to download
        $DB->query("
      UPDATE users_main
      SET BonusPoints = 0
      WHERE ID = {$FillerID}");
        $DB->query('
      UPDATE users_main
      SET Downloaded = Downloaded + ' . (int) ($RequestVotes['TotalBounty']*(1/4) - $Uploaded - $BonusPoints*1000) . "
      WHERE ID = {$FillerID}");
    } else {
        $DB->query('
      UPDATE users_main
      SET BonusPoints = BonusPoints - ' . (int) (($RequestVotes['TotalBounty']*(1/4) - $Uploaded)/1000) . "
      WHERE ID = {$FillerID}");
    }
} else {
    $DB->query('
    UPDATE users_main
    SET Uploaded = Uploaded - ' . (int) ($RequestVotes['TotalBounty']*(1/4)) . "
    WHERE ID = {$FillerID}");
}

$DB->query("
  SELECT
    Uploaded,
    BonusPoints
  FROM users_main
  WHERE ID = {$UploaderID}");
[$UploaderUploaded, $UploaderBonusPoints] = $DB->next_record();

//Remove Uploader portion of bounty
if ((int) ($RequestVotes['TotalBounty']*(3/4)) > $UploaderUploaded) {
    // If we can't take it all out of upload, attempt to take the rest out of bonus points
    $DB->query("
    UPDATE users_main
    SET Uploaded = 0
    WHERE ID = {$UploaderID}");
    if ((int) ($RequestVotes['TotalBounty']*(3/4) - $UploaderUploaded) > $UploaderBonusPoints) {
        // If we can't take the rest as bonus points, turn the remaining bit to download
        $DB->query("
      UPDATE users_main
      SET BonusPoints = 0
      WHERE ID = {$UploaderID}");
        $DB->query('
      UPDATE users_main
      SET Downloaded = Downloaded + ' . (int) ($RequestVotes['TotalBounty']*(3/4) - $UploaderUploaded - $UploaderBonusPoints*1000) . "
      WHERE ID = {$UploaderID}");
    } else {
        $DB->query('
      UPDATE users_main
      SET BonusPoints = BonusPoints - ' . (int) (($RequestVotes['TotalBounty']*(3/4) - $UploaderUploaded)/1000) . "
      WHERE ID = {$UploaderID}");
    }
} else {
    $DB->query('
    UPDATE users_main
    SET Uploaded = Uploaded - ' . (int) ($RequestVotes['TotalBounty']*(3/4)) . "
    WHERE ID = {$UploaderID}");
}
Misc::send_pm($FillerID, 0, 'A request you filled has been unfilled', 'The request "[url=' . site_url() . sprintf('requests.php?action=view&amp;id=%s]%s', $RequestID, $FullName) . '[/url]" was unfilled by [url=' . site_url() . 'user.php?id=' . $LoggedUser['ID'] . ']' . $LoggedUser['Username'] . '[/url] for the reason: [quote]' . $_POST['reason'] . "[/quote]\nIf you feel like this request was unjustly unfilled, please [url=" . site_url() . sprintf('reports.php?action=report&amp;type=request&amp;id=%s]report the request[/url] and explain why this request should not have been unfilled.', $RequestID));
if ($UploaderID != $FillerID) {
    Misc::send_pm($UploaderID, 0, 'A request filled with your torrent has been unfilled', 'The request "[url=' . site_url() . sprintf('requests.php?action=view&amp;id=%s]%s', $RequestID, $FullName) . '[/url]" was unfilled by [url=' . site_url() . 'user.php?id=' . $LoggedUser['ID'] . ']' . $LoggedUser['Username'] . '[/url] for the reason: [quote]' . $_POST['reason'] . "[/quote]\nIf you feel like this request was unjustly unfilled, please [url=" . site_url() . sprintf('reports.php?action=report&amp;type=request&amp;id=%s]report the request[/url] and explain why this request should not have been unfilled.', $RequestID));
}

$Cache->delete_value(sprintf('user_stats_%s', $FillerID));

if ($UserID !== $LoggedUser['ID']) {
    Misc::send_pm($UserID, 0, 'A request you created has been unfilled', 'The request "[url=' . site_url() . sprintf('requests.php?action=view&amp;id=%s]%s', $RequestID, $FullName) . '[/url]" was unfilled by [url=' . site_url() . 'user.php?id=' . $LoggedUser['ID'] . ']' . $LoggedUser['Username'] . "[/url] for the reason: [quote]" . $_POST['reason'] . '[/quote]');
}

Misc::write_log(sprintf('Request %s (%s), with a ', $RequestID, $FullName) . Format::get_size($RequestVotes['TotalBounty']) . ' bounty, was unfilled by user ' . $LoggedUser['ID'] . ' (' . $LoggedUser['Username'] . ') for the reason: ' . $_POST['reason']);

$Cache->delete_value(sprintf('request_%s', $RequestID));
$Cache->delete_value(sprintf('request_artists_%s', $RequestID));
if ($GroupID) {
    $Cache->delete_value(sprintf('requests_group_%s', $GroupID));
}

Requests::update_sphinx_requests($RequestID);

if (!empty($ArtistForm)) {
    foreach ($ArtistForm as $Artist) {
        $Cache->delete_value('artists_requests_' . $Artist['id']);
    }
}

$SphQL = new SphinxqlQuery();
$SphQL->raw_query("
    UPDATE requests, requests_delta
    SET torrentid = 0, fillerid = 0
    WHERE id = {$RequestID}", false);

header(sprintf('Location: requests.php?action=view&id=%s', $RequestID));
