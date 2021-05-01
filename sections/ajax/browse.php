<?php

declare(strict_types=1);

include SERVER_ROOT . '/sections/torrents/functions.php';

$OrderWay = !empty($_GET['order_way']) && 'asc' == $_GET['order_way'] ? 'asc' : 'desc';

if (empty($_GET['order_by']) || !isset(TorrentSearch::$SortOrders[$_GET['order_by']])) {
    $OrderBy = 'time';
} else {
    $OrderBy = $_GET['order_by'];
}

$GroupResults = !isset($_GET['group_results']) || '0' != $_GET['group_results'];
$Page = empty($_GET['page']) ? 1 : (int)$_GET['page'];
$Search = new TorrentSearch($GroupResults, $OrderBy, $OrderWay, $Page, TORRENTS_PER_PAGE);
$Results = $Search->query($_GET);
$Groups = $Search->get_groups();
$NumResults = $Search->record_count();

if (false === $Results) {
    json_die('error', 'Search returned an error. Make sure all parameters are valid and of the expected types.');
}

if (0 == $NumResults) {
    json_die("success", [
        'results' => []
    ]);
}

$Bookmarks = Bookmarks::all_bookmarks('torrent');

$JsonGroups = [];
foreach ($Results as $Key => $GroupID) {
    $GroupInfo = $Groups[$GroupID];
    if (empty($GroupInfo['Torrents'])) {
        continue;
    }
    $CategoryID = $GroupInfo['CategoryID'];
    $Artists = $GroupInfo['Artists'];
    $GroupCatalogueNumber = $GroupInfo['CatalogueNumber'];
    $GroupName = $GroupInfo['Name'];
    if ($GroupResults) {
        $Torrents = $GroupInfo['Torrents'];
        $GroupTime = $TotalLeechers = $TotalSeeders = $TotalSnatched = 0;
        $MaxSize = $TotalLeechers = $TotalSeeders = $TotalSnatched = 0;
        foreach ($Torrents as $T) {
            $GroupTime = max($GroupTime, strtotime($T['Time']));
            $MaxSize = max($MaxSize, $T['Size']);
            $TotalLeechers += $T['Leechers'];
            $TotalSeeders += $T['Seeders'];
            $TotalSnatched += $T['Snatched'];
        }
    } else {
        $TorrentID = $Key;
        $Torrents = [$TorrentID => $GroupInfo['Torrents'][$TorrentID]];
    }

    $TagList = explode(' ', str_replace('_', '.', $GroupInfo['TagList']));
    $JsonArtists = [];
    $DisplayName = '';
    if (!empty($Artists)) {
        $DisplayName = Artists::display_artists($Artists, false, false, false);
        foreach ($Artists as $Artist) {
            $JsonArtists[] = [
                'id' => (int)$Artist['id'],
                'name' => $Artist['name']
            ];
        }
    }

    $JsonTorrents = [];
    foreach ($Torrents as $TorrentID => $Data) {
        // All of the individual torrents in the group

        $JsonTorrents[] = [
            'torrentId' =>       (int)$TorrentID,
            'artists' =>              $JsonArtists,
            'media' =>                $Data['Media'],
            'container' =>            $Data['Container'],
            'codec' =>                $Data['Codec'],
            'resolution' =>           $Data['Resolution'],
            'audio' =>                $Data['AudioFormat'],
            'lang' =>                 $Data['Language'],
            'subbing' =>              $Data['Subbing'],
            'subber' =>               $Data['Subber'],
            'censored' =>             $Data['Censored'],
            'archive' =>              $Data['Archive'],
            'fileCount' =>       (int)$Data['FileCount'],
            'time' =>                 $Data['Time'],
            'size' =>            (int)$Data['Size'],
            'snatches' =>        (int)$Data['Snatched'],
            'seeders' =>         (int)$Data['Seeders'],
            'leechers' =>        (int)$Data['Leechers'],
            'isFreeleech' =>          '1' == $Data['FreeTorrent'],
            'isNeutralLeech' =>       '2' == $Data['FreeTorrent'],
            'isPersonalFreeleech' =>  $Data['PersonalFL'],
            'canUseToken' =>          Torrents::can_use_token($Data),
            'hasSnatched' =>          $Data['IsSnatched']
        ];
    }

    $JsonGroups[] = [
        'groupId' =>       (int)$GroupID,
        'groupName' =>          $GroupName,
        'artist' =>             $DisplayName,
        'cover' =>              $GroupInfo['WikiImage'],
        'tags' =>               $TagList,
        'bookmarked' =>    (in_array($GroupID, $Bookmarks, true)),
        'groupYear' =>     (int)$GroupInfo['Year'],
        'groupTime' =>     (int)$GroupTime,
        'catalogue' =>          $GroupInfo['CatalogueNumber'],
        'studio' =>             $GroupInfo['Studio'],
        'series' =>             $GroupInfo['Series'],
        'dlsite' =>             $GroupInfo['DLSiteID'],
        'maxSize' =>       (int)$MaxSize,
        'totalSnatched' => (int)$TotalSnatched,
        'totalSeeders' =>  (int)$TotalSeeders,
        'totalLeechers' => (int)$TotalLeechers,
        'torrents' =>           $JsonTorrents
    ];
}

json_print('success', [
    'currentPage' => (int) $Page,
    'pages' => ceil($NumResults / TORRENTS_PER_PAGE),
    'results' => $JsonGroups
]);
