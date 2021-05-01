<?php

declare(strict_types=1);

if (!check_perms('site_torrents_notify')) {
    json_die("failure", 'Not Found');
}

define('NOTIFICATIONS_PER_PAGE', 50);
[$Page, $Limit] = Format::page_limit(NOTIFICATIONS_PER_PAGE);

$Results = $DB->query("
    SELECT
      SQL_CALC_FOUND_ROWS
      unt.TorrentID,
      unt.UnRead,
      unt.FilterID,
      unf.Label,
      t.GroupID
    FROM users_notify_torrents AS unt
      JOIN torrents AS t ON t.ID = unt.TorrentID
      LEFT JOIN users_notify_filters AS unf ON unf.ID = unt.FilterID
    WHERE unt.UserID = $LoggedUser[ID]" .
    ((!empty($_GET['filterid']) && is_number($_GET['filterid']))
        ? sprintf(' AND unf.ID = \'%s\'', $_GET[filterid])
        : '') . "
    ORDER BY TorrentID DESC
    LIMIT {$Limit}");
$GroupIDs = array_unique($DB->collect('GroupID'));

$DB->query('SELECT FOUND_ROWS()');
[$TorrentCount] = $DB->next_record();

if ([] !== $GroupIDs) {
    $TorrentGroups = Torrents::get_groups($GroupIDs);
    $DB->query("
    UPDATE users_notify_torrents
    SET UnRead = '0'
    WHERE UserID = $LoggedUser[ID]");
    $Cache->delete_value(sprintf('notifications_new_%s', $LoggedUser[ID]));
}

$DB->set_query_id($Results);

$JsonNotifications = [];
$NumNew = 0;

$FilterGroups = [];
while ($Result = $DB->next_record(MYSQLI_ASSOC)) {
    if (!$Result['FilterID']) {
        $Result['FilterID'] = 0;
    }
    if (!isset($FilterGroups[$Result['FilterID']])) {
        $FilterGroups[$Result['FilterID']] = [];
        $FilterGroups[$Result['FilterID']]['FilterLabel'] = ($Result['Label'] ? $Result['Label'] : false);
    }
    $FilterGroups[$Result['FilterID']][] = $Result;
}
unset($Result);

foreach ($FilterGroups as $FilterID => $FilterResults) {
    unset($FilterResults['FilterLabel']);
    foreach ($FilterResults as $Result) {
        $TorrentID = $Result['TorrentID'];
//    $GroupID = $Result['GroupID'];
        
        $GroupInfo = $TorrentGroups[$Result['GroupID']];
        extract(Torrents::array_group($GroupInfo)); // all group data
        $TorrentInfo = $GroupInfo['Torrents'][$TorrentID];
        
        if (1 == $Result['UnRead']) {
            ++$NumNew;
        }
        
        $JsonNotifications[] = [
            'torrentId' => (int) $TorrentID,
            'groupId' => (int) $GroupID,
            'groupName' => $GroupName,
            'groupCategoryId' => (int) $GroupCategoryID,
            'wikiImage' => $WikiImage,
            'torrentTags' => $TagList,
            'size' => (float) $TorrentInfo['Size'],
            'fileCount' => (int) $TorrentInfo['FileCount'],
            'format' => $TorrentInfo['Format'],
            'encoding' => $TorrentInfo['Encoding'],
            'media' => $TorrentInfo['Media'],
            'scene' => 1 == $TorrentInfo['Scene'],
            'groupYear' => (int) $GroupYear,
            'remasterYear' => (int) $TorrentInfo['RemasterYear'],
            'remasterTitle' => $TorrentInfo['RemasterTitle'],
            'snatched' => (int) $TorrentInfo['Snatched'],
            'seeders' => (int) $TorrentInfo['Seeders'],
            'leechers' => (int) $TorrentInfo['Leechers'],
            'notificationTime' => $TorrentInfo['Time'],
            'hasLog' => 1 == $TorrentInfo['HasLog'],
            'hasCue' => 1 == $TorrentInfo['HasCue'],
            'logScore' => (float) $TorrentInfo['LogScore'],
            'freeTorrent' => 1 == $TorrentInfo['FreeTorrent'],
            'logInDb' => 1 == $TorrentInfo['HasLog'],
            'unread' => 1 == $Result['UnRead']
        ];
    }
}

json_die("success", [
    'currentPages' => (int) $Page,
    'pages' => ceil($TorrentCount / NOTIFICATIONS_PER_PAGE),
    'numNew' => $NumNew,
    'results' => $JsonNotifications
]);
