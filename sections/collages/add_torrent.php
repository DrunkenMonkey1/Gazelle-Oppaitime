<?php

declare(strict_types=1);

authorize();

include SERVER_ROOT . '/classes/validate.class.php';
$Val = new VALIDATE();

function add_torrent($CollageID, $GroupID): void
{
    global $Cache, $LoggedUser, $DB;

    $DB->query("
    SELECT MAX(Sort)
    FROM collages_torrents
    WHERE CollageID = '{$CollageID}'");
    [$Sort] = $DB->next_record();
    $Sort += 10;

    $DB->query("
    SELECT GroupID
    FROM collages_torrents
    WHERE CollageID = '{$CollageID}'
      AND GroupID = '{$GroupID}'");
    if (!$DB->has_results()) {
        $DB->query("
      INSERT IGNORE INTO collages_torrents
        (CollageID, GroupID, UserID, Sort, AddedOn)
      VALUES
        ('{$CollageID}', '{$GroupID}', '$LoggedUser[ID]', '{$Sort}', '" . sqltime() . "')");

        $DB->query("
      UPDATE collages
      SET NumTorrents = NumTorrents + 1, Updated = '" . sqltime() . "'
      WHERE ID = '{$CollageID}'");

        $Cache->delete_value(sprintf('collage_%s', $CollageID));
        $Cache->delete_value(sprintf('torrents_details_%s', $GroupID));
        $Cache->delete_value(sprintf('torrent_collages_%s', $GroupID));
        $Cache->delete_value(sprintf('torrent_collages_personal_%s', $GroupID));

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
        $Err = 'This collage already holds its maximum allowed number of torrents.';
    }

    if (isset($Err)) {
        error($Err);
    }
}

if ($MaxGroupsPerUser > 0) {
    $DB->query("
    SELECT COUNT(*)
    FROM collages_torrents
    WHERE CollageID = '{$CollageID}'
      AND UserID = '$LoggedUser[ID]'");
    [$GroupsForUser] = $DB->next_record();
    if (!check_perms('site_collages_delete') && $GroupsForUser >= $MaxGroupsPerUser) {
        error(403);
    }
}

if ('add_torrent' == $_REQUEST['action']) {
    $Val->SetFields('url', '1', 'regex', 'The URL must be a link to a torrent on the site.', ['regex' => '/^' . TORRENT_GROUP_REGEX . '/i']);
    $Err = $Val->ValidateForm($_POST);

    if ($Err) {
        error($Err);
    }

    $URL = $_POST['url'];

    // Get torrent ID
    preg_match('/^' . TORRENT_GROUP_REGEX . '/i', $URL, $Matches);
    $TorrentID = $Matches[4];
    if (!$TorrentID || 0 == (int)$TorrentID) {
        error(404);
    }

    $DB->query("
    SELECT ID
    FROM torrents_group
    WHERE ID = '{$TorrentID}'");
    [$GroupID] = $DB->next_record();
    if (!$GroupID) {
        error('The torrent was not found in the database.');
    }

    add_torrent($CollageID, $GroupID);
} else {
    $URLs = explode("\n", $_REQUEST['urls']);
    $GroupIDs = [];
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
            $Err = sprintf('This collage can only hold %s torrents.', $MaxGroups);
        }
        if ($MaxGroupsPerUser > 0 && ($GroupsForUser + count($URLs) > $MaxGroupsPerUser)) {
            $Err = sprintf('You may only have %s torrents in this collage.', $MaxGroupsPerUser);
        }
    }

    foreach ($URLs as $URL) {
        $Matches = [];
        if (preg_match('/^' . TORRENT_GROUP_REGEX . '/i', $URL, $Matches)) {
            $GroupIDs[] = $Matches[4];
            $GroupID = $Matches[4];
        } else {
            $Err = sprintf('One of the entered URLs (%s) does not correspond to a torrent group on the site.', $URL);
            break;
        }

        $DB->query("
      SELECT ID
      FROM torrents_group
      WHERE ID = '{$GroupID}'");
        if (!$DB->has_results()) {
            $Err = sprintf('One of the entered URLs (%s) does not correspond to a torrent group on the site.', $URL);
            break;
        }
    }

    if ('' !== $Err) {
        error($Err);
    }

    foreach ($GroupIDs as $GroupID) {
        add_torrent($CollageID, $GroupID);
    }
}
header('Location: collages.php?id=' . $CollageID);
