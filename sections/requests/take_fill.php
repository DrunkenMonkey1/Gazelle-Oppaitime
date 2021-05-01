<?php

declare(strict_types=1);

//******************************************************************************//
//--------------- Fill a request -----------------------------------------------//

$RequestID = $_REQUEST['requestid'];
if (!is_number($RequestID)) {
    error(0);
}

authorize();

//VALIDATION
if (!empty($_GET['torrentid']) && is_number($_GET['torrentid'])) {
    $TorrentID = $_GET['torrentid'];
} else {
    if (empty($_POST['link'])) {
        error('You forgot to supply a link to the filling torrent');
    } else {
        $Link = $_POST['link'];
        if (!preg_match('/' . TORRENT_REGEX . '/i', $Link, $Matches)) {
            error("Your link didn't seem to be a valid torrent link");
        } else {
            $TorrentID = $Matches[4];
        }
    }
    if (!$TorrentID || !is_number($TorrentID)) {
        error(404);
    }
}

//Torrent exists, check it's applicable
$DB->query("
  SELECT
    t.UserID,
    t.Time,
    tg.CategoryID,
    tg.CatalogueNumber,
    tg.DLSiteID
  FROM torrents AS t
    LEFT JOIN torrents_group AS tg ON t.GroupID = tg.ID
  WHERE t.ID = {$TorrentID}
  LIMIT 1");

if (!$DB->has_results()) {
    error(404);
}
[$UploaderID, $UploadTime, $TorrentCategoryID, $TorrentCatalogueNumber, $TorrentDLSiteID] = $DB->next_record();

$FillerID = $LoggedUser['ID'];
$FillerUsername = $LoggedUser['Username'];

if (!empty($_POST['user']) && check_perms('site_moderate_requests')) {
    $FillerUsername = $_POST['user'];
    $DB->query("
    SELECT ID
    FROM users_main
    WHERE Username LIKE '" . db_string($FillerUsername) . "'");
    if (!$DB->has_results()) {
        $Err = 'No such user to fill for!';
    } else {
        [$FillerID] = $DB->next_record();
    }
}

if (time_ago($UploadTime) < 3600 && $UploaderID !== $FillerID && !check_perms('site_moderate_requests')) {
    $Err = "There is a one hour grace period for new uploads to allow the torrent's uploader to fill the request.";
}


$DB->query("
  SELECT
    Title,
    UserID,
    TorrentID,
    CategoryID,
    CatalogueNumber,
    DLSiteID
  FROM requests
  WHERE ID = {$RequestID}");
[$Title, $RequesterID, $OldTorrentID, $RequestCategoryID, $RequestCatalogueNumber, $RequestDLSiteID] = $DB->next_record();


if (!empty($OldTorrentID)) {
    $Err = 'This request has already been filled.';
}
if ('0' !== $RequestCategoryID && $TorrentCategoryID !== $RequestCategoryID) {
    $Err = 'This torrent is of a different category than the request. If the request is actually miscategorized, please contact staff.';
}

$CategoryName = $Categories[$RequestCategoryID - 1];

if ($RequestCatalogueNumber && str_replace('-', '', strtolower($TorrentCatalogueNumber)) !== str_replace('-', '', strtolower($RequestCatalogueNumber))) {
    $Err = sprintf('This request requires the catalogue number %s', $RequestCatalogueNumber);
}

if ($RequestDLSiteID && strtolower($TorrentDLSiteID) !== strtolower($RequestDLSiteID)) {
    $Err = sprintf('This request requires DLSite ID %s', $RequestDLSiteID);
}

// Fill request
if (!empty($Err)) {
    error($Err);
}

//We're all good! Fill!
$DB->query("
  UPDATE requests
  SET FillerID = {$FillerID},
    TorrentID = {$TorrentID},
    TimeFilled = NOW()
  WHERE ID = {$RequestID}");

$ArtistForm = Requests::get_artists($RequestID);
$ArtistName = Artists::display_artists($ArtistForm, false, true);
$FullName = $ArtistName . $Title;

$DB->query("
  SELECT UserID
  FROM requests_votes
  WHERE RequestID = {$RequestID}");
$UserIDs = $DB->to_array();
foreach ($UserIDs as $User) {
    [$VoterID] = $User;
    Misc::send_pm($VoterID, 0, sprintf('The request "%s" has been filled', $FullName), 'One of your requests&#8202;&mdash;&#8202;[url=' . site_url() . sprintf('requests.php?action=view&amp;id=%s]%s', $RequestID, $FullName) . '[/url]&#8202;&mdash;&#8202;has been filled. You can view it here: [url]' . site_url() . sprintf('torrents.php?torrentid=%s', $TorrentID) . '[/url]');
}
if ($UploaderID != $FillerID) {
    Misc::send_pm($UploaderID, 0, sprintf('The request "%s" has been filled with your torrent', $FullName), 'The request&#8202;&mdash;&#8202;[url=' . site_url() . sprintf('requests.php?action=view&amp;id=%s]%s', $RequestID, $FullName) . '[/url]&#8202;&mdash;&#8202;has been filled with a torrent you uploaded. You automatically received ' . Format::get_size($RequestVotes['TotalBounty']*(3/4)) . ' of the total bounty. You can view the torrent you uploaded here: [url]' . site_url() . sprintf('torrents.php?torrentid=%s', $TorrentID) . '[/url]');
}

$RequestVotes = Requests::get_votes_array($RequestID);
Misc::write_log(sprintf('Request %s (%s) was filled by user %s (%s) with the torrent %s for a ', $RequestID, $FullName, $FillerID, $FillerUsername, $TorrentID) . Format::get_size($RequestVotes['TotalBounty']) . ' bounty.');

// Give bounty
$DB->query("
  UPDATE users_main
  SET Uploaded = (Uploaded + " . (int) ($RequestVotes['TotalBounty']*(1/4)) . ")
  WHERE ID = {$FillerID}");

$DB->query("
  UPDATE users_main
  SET Uploaded = (Uploaded + " . (int) ($RequestVotes['TotalBounty']*(3/4)) . ")
  WHERE ID = {$UploaderID}");


$Cache->delete_value(sprintf('user_stats_%s', $FillerID));
$Cache->delete_value(sprintf('request_%s', $RequestID));
if (isset($GroupID)) {
    $Cache->delete_value(sprintf('requests_group_%s', $GroupID));
}



$DB->query("
  SELECT ArtistID
  FROM requests_artists
  WHERE RequestID = {$RequestID}");
$ArtistIDs = $DB->to_array();
foreach ($ArtistIDs as $ArtistID) {
    $Cache->delete_value("artists_requests_" . $ArtistID[0]);
}

Requests::update_sphinx_requests($RequestID);
$SphQL = new SphinxqlQuery();
$SphQL->raw_query(sprintf('UPDATE requests, requests_delta SET torrentid = %s, fillerid = %s WHERE id = %s', $TorrentID, $FillerID, $RequestID), false);

header(sprintf('Location: requests.php?action=view&id=%s', $RequestID));
