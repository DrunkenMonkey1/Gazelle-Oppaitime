<?php

ini_set('memory_limit', -1);
//~~~~~~~~~~~ Main bookmarks page ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~//


function compare($X, $Y)
{
    return($Y['count'] - $X['count']);
}

if (!empty($_GET['userid'])) {
    if (!check_perms('users_override_paranoia')) {
        error(403);
    }
    $UserID = $_GET['userid'];
    if (!is_number($UserID)) {
        error(404);
    }
    $DB->query("
    SELECT Username
    FROM users_main
    WHERE ID = '$UserID'");
    [$Username] = $DB->next_record();
} else {
    $UserID = $LoggedUser['ID'];
}

$Sneaky = ($UserID != $LoggedUser['ID']);

$JsonBookmarks = [];

[$GroupIDs, $CollageDataList, $GroupList] = Users::get_bookmarks($UserID);
foreach ($GroupIDs as $GroupID) {
    if (!isset($GroupList[$GroupID])) {
        continue;
    }
    $Group = $GroupList[$GroupID];
    $JsonTorrents = [];
    foreach ($Group['Torrents'] as $Torrent) {
        $JsonTorrents[] = [
            'id' => (int)$Torrent['ID'],
            'groupId' => (int)$Torrent['GroupID'],
            'media' => $Torrent['Media'],
            'format' => $Torrent['Format'],
            'encoding' => $Torrent['Encoding'],
            'remasterYear' => (int)$Torrent['RemasterYear'],
            'remastered' => 1 == $Torrent['Remastered'],
            'remasterTitle' => $Torrent['RemasterTitle'],
            'remasterRecordLabel' => $Torrent['RemasterRecordLabel'],
            'remasterCatalogueNumber' => $Torrent['RemasterCatalogueNumber'],
            'scene' => 1 == $Torrent['Scene'],
            'hasLog' => 1 == $Torrent['HasLog'],
            'hasCue' => 1 == $Torrent['HasCue'],
            'logScore' => (float)$Torrent['LogScore'],
            'fileCount' => (int)$Torrent['FileCount'],
            'freeTorrent' => 1 == $Torrent['FreeTorrent'],
            'size' => (float)$Torrent['Size'],
            'leechers' => (int)$Torrent['Leechers'],
            'seeders' => (int)$Torrent['Seeders'],
            'snatched' => (int)$Torrent['Snatched'],
            'time' => $Torrent['Time'],
            'hasFile' => (int)$Torrent['HasFile']
        ];
    }
    $JsonBookmarks[] = [
        'id' => (int)$Group['ID'],
        'name' => $Group['Name'],
        'year' => (int)$Group['Year'],
        'recordLabel' => $Group['RecordLabel'],
        'catalogueNumber' => $Group['CatalogueNumber'],
        'tagList' => $Group['TagList'],
        'releaseType' => $Group['ReleaseType'],
        'vanityHouse' => 1 == $Group['VanityHouse'],
        'image' => $Group['WikiImage'],
        'torrents' => $JsonTorrents
    ];
}

print
  json_encode(
      [
          'status' => 'success',
          'response' => [
              'bookmarks' => $JsonBookmarks
          ]
      ]
  );
