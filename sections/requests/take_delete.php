<?php

declare(strict_types=1);

//******************************************************************************//
//--------------- Delete request -----------------------------------------------//

authorize();

$RequestID = $_POST['id'];
if (!is_number($RequestID)) {
    error(0);
}

$DB->query("
  SELECT
    UserID,
    Title,
    CategoryID,
    GroupID
  FROM requests
  WHERE ID = {$RequestID}");
[$UserID, $Title, $CategoryID, $GroupID] = $DB->next_record();

if ($LoggedUser['ID'] != $UserID && !check_perms('site_moderate_requests')) {
    error(403);
}

$CategoryName = $Categories[$CategoryID - 1];

//Do we need to get artists?
if ('Music' != $CategoryName) {
    $ArtistForm = Requests::get_artists($RequestID);
    $ArtistName = Artists::display_artists($ArtistForm, false, true);
    $FullName = $ArtistName . $Title;
} else {
    $FullName = $Title;
}



// Delete request, votes and tags
$DB->query(sprintf('DELETE FROM requests WHERE ID = \'%s\'', $RequestID));
$DB->query(sprintf('DELETE FROM requests_votes WHERE RequestID = \'%s\'', $RequestID));
$DB->query(sprintf('DELETE FROM requests_tags WHERE RequestID = \'%s\'', $RequestID));
Comments::delete_page('requests', $RequestID);

$DB->query("
  SELECT ArtistID
  FROM requests_artists
  WHERE RequestID = {$RequestID}");
$RequestArtists = $DB->to_array();
foreach ($RequestArtists as $RequestArtist) {
    $Cache->delete_value(sprintf('artists_requests_%s', $RequestArtist));
}
$DB->query("
  DELETE FROM requests_artists
  WHERE RequestID = '{$RequestID}'");
$Cache->delete_value(sprintf('request_artists_%s', $RequestID));

G::$DB->query("
  REPLACE INTO sphinx_requests_delta
    (ID)
  VALUES
    ({$RequestID})");

if ($UserID != $LoggedUser['ID']) {
    Misc::send_pm($UserID, 0, 'A request you created has been deleted', sprintf('The request "%s" was deleted by [url=', $FullName) . site_url() . 'user.php?id=' . $LoggedUser['ID'] . ']' . $LoggedUser['Username'] . '[/url] for the reason: [quote]' . $_POST['reason'] . '[/quote]');
}

Misc::write_log(sprintf('Request %s (%s) was deleted by user ', $RequestID, $FullName) . $LoggedUser['ID'] . ' (' . $LoggedUser['Username'] . ') for the reason: ' . $_POST['reason']);

$Cache->delete_value(sprintf('request_%s', $RequestID));
$Cache->delete_value(sprintf('request_votes_%s', $RequestID));
if ($GroupID) {
    $Cache->delete_value(sprintf('requests_group_%s', $GroupID));
}

header('Location: requests.php');
