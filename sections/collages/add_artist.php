<?php

declare(strict_types=1);

//NumTorrents is actually the number of things in the collage, the name just isn't generic.

authorize();

include SERVER_ROOT . '/classes/validate.class.php';
$Val = new VALIDATE();

function add_artist($CollageID, $ArtistID): void
{
    global $Cache, $LoggedUser, $DB;

    $DB->query("
    SELECT MAX(Sort)
    FROM collages_artists
    WHERE CollageID = '{$CollageID}'");
    [$Sort] = $DB->next_record();
    $Sort += 10;

    $DB->query("
    SELECT ArtistID
    FROM collages_artists
    WHERE CollageID = '{$CollageID}'
      AND ArtistID = '{$ArtistID}'");
    if (!$DB->has_results()) {
        $DB->query("
      INSERT IGNORE INTO collages_artists
        (CollageID, ArtistID, UserID, Sort, AddedOn)
      VALUES
        ('{$CollageID}', '{$ArtistID}', '$LoggedUser[ID]', '{$Sort}', '" . sqltime() . "')");

        $DB->query("
      UPDATE collages
      SET NumTorrents = NumTorrents + 1, Updated = '" . sqltime() . "'
      WHERE ID = '{$CollageID}'");

        $Cache->delete_value(sprintf('collage_%s', $CollageID));
        $Cache->delete_value(sprintf('artists_collages_%s', $ArtistID));
        $Cache->delete_value(sprintf('artists_collages_personal_%s', $ArtistID));

        $DB->query("
      SELECT UserID
      FROM users_collage_subs
      WHERE CollageID = {$CollageID}");
        while ([$CacheUserID] = $DB->next_record()) {
            $Cache->delete_value(sprintf('collage_subs_user_new_%s', $CacheUserID));
        }
    }
}

$CollageID = $_POST['collageid'];
if (!is_number($CollageID)) {
    error(404);
}
$DB->query("
  SELECT UserID, CategoryID, Locked, NumTorrents, MaxGroups, MaxGroupsPerUser
  FROM collages
  WHERE ID = '{$CollageID}'");
[$UserID, $CategoryID, $Locked, $NumTorrents, $MaxGroups, $MaxGroupsPerUser] = $DB->next_record();

if (!check_perms('site_collages_delete')) {
    if ($Locked) {
        $Err = 'This collage is locked';
    }
    if (0 == $CategoryID && $UserID != $LoggedUser['ID']) {
        $Err = "You cannot edit someone else's personal collage.";
    }
    if ($MaxGroups > 0 && $NumTorrents >= $MaxGroups) {
        $Err = 'This collage already holds its maximum allowed number of artists.';
    }

    if (isset($Err)) {
        error($Err);
    }
}

if ($MaxGroupsPerUser > 0) {
    $DB->query("
    SELECT COUNT(*)
    FROM collages_artists
    WHERE CollageID = '{$CollageID}'
      AND UserID = '$LoggedUser[ID]'");
    [$GroupsForUser] = $DB->next_record();
    if (!check_perms('site_collages_delete') && $GroupsForUser >= $MaxGroupsPerUser) {
        error(403);
    }
}

if ('add_artist' == $_REQUEST['action']) {
    $Val->SetFields('url', '1', 'regex', 'The URL must be a link to a artist on the site.', ['regex' => '/^' . ARTIST_REGEX . '/i']);
    $Err = $Val->ValidateForm($_POST);

    if ($Err) {
        error($Err);
    }

    $URL = $_POST['url'];

    // Get artist ID
    preg_match('/^' . ARTIST_REGEX . '/i', $URL, $Matches);
    $ArtistID = $Matches[4];
    if (!$ArtistID || 0 === (int)$ArtistID) {
        error(404);
    }

    $DB->query("
    SELECT ArtistID
    FROM artists_group
    WHERE ArtistID = '{$ArtistID}'");
    [$ArtistID] = $DB->next_record();
    if (!$ArtistID) {
        error('The artist was not found in the database.');
    }

    add_artist($CollageID, $ArtistID);
} else {
    $URLs = explode("\n", $_REQUEST['urls']);
    $ArtistIDs = [];
    $Err = '';
    foreach ($URLs as $Key => &$URL) {
        $URL = trim($URL);
        if ('' == $URL) {
            unset($URLs[$Key]);
        }
    }
    unset($URL);

    if (!check_perms('site_collages_delete')) {
        if ($MaxGroups > 0 && ($NumTorrents + count($URLs) > $MaxGroups)) {
            $Err = sprintf('This collage can only hold %s artists.', $MaxGroups);
        }
        if ($MaxGroupsPerUser > 0 && ($GroupsForUser + count($URLs) > $MaxGroupsPerUser)) {
            $Err = sprintf('You may only have %s artists in this collage.', $MaxGroupsPerUser);
        }
    }

    foreach ($URLs as $URL) {
        $Matches = [];
        if (preg_match('/^' . ARTIST_REGEX . '/i', $URL, $Matches)) {
            $ArtistIDs[] = $Matches[4];
            $ArtistID = $Matches[4];
        } else {
            $Err = sprintf('One of the entered URLs (%s) does not correspond to an artist on the site.', $URL);
            break;
        }

        $DB->query("
      SELECT ArtistID
      FROM artists_group
      WHERE ArtistID = '{$ArtistID}'");
        if (!$DB->has_results()) {
            $Err = sprintf('One of the entered URLs (%s) does not correspond to an artist on the site.', $URL);
            break;
        }
    }

    if ('' !== $Err) {
        error($Err);
    }

    foreach ($ArtistIDs as $ArtistID) {
        add_artist($CollageID, $ArtistID);
    }
}
header(sprintf('Location: collages.php?id=%s', $CollageID));
