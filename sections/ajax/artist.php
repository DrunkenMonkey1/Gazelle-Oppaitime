<?php

declare(strict_types=1);

//For sorting tags
function compare($X, $Y)
{
    return ($Y['count'] - $X['count']);
}

if (!empty($_GET['artistreleases'])) {
    $OnlyArtistReleases = true;
}

if ($_GET['id'] && $_GET['artistname']) {
    json_die("failure", "bad parameters");
}

$ArtistID = $_GET['id'];
if ($ArtistID && !is_number($ArtistID)) {
    json_die("failure");
}

if (empty($ArtistID) && !empty($_GET['artistname'])) {
    $Name = db_string(trim($_GET['artistname']));
    $DB->query("
      SELECT ArtistID
      FROM artists_alias
      WHERE Name LIKE '{$Name}'");
    if (!([$ArtistID] = $DB->next_record(MYSQLI_NUM, false))) {
        json_die("failure", 'Not Found');
    }
    // If we get here, we got the ID!
}

if (!empty($_GET['revisionid'])) { // if they're viewing an old revision
    $RevisionID = $_GET['revisionid'];
    if (!is_number($RevisionID)) {
        error(0);
    }
    $Data = $Cache->get_value(sprintf('artist_%s', $ArtistID) . sprintf('_revision_%s', $RevisionID));
} else { // viewing the live version
    $Data = $Cache->get_value(sprintf('artist_%s', $ArtistID));
    $RevisionID = false;
}
if ($Data) {
    [$Name, $Image, $Body] = current($Data);
} else {
    if ($RevisionID) {
        /*
          $sql = "
            SELECT
              a.Name,
              wiki.Image,
              wiki.body,
              a.VanityHouse
            FROM wiki_artists AS wiki
              LEFT JOIN artists_group AS a ON wiki.RevisionID = a.RevisionID
            WHERE wiki.RevisionID = '$RevisionID' ";
        */
        $sql = "
      SELECT
        a.Name,
        wiki.Image,
        wiki.body
      FROM wiki_artists AS wiki
        LEFT JOIN artists_group AS a ON wiki.RevisionID = a.RevisionID
      WHERE wiki.RevisionID = '{$RevisionID}' ";
    } else {
        /*
          $sql = "
            SELECT
              a.Name,
              wiki.Image,
              wiki.body,
              a.VanityHouse
            FROM artists_group AS a
              LEFT JOIN wiki_artists AS wiki ON wiki.RevisionID = a.RevisionID
            WHERE a.ArtistID = '$ArtistID' ";
        */
        $sql = "
      SELECT
        a.Name,
        wiki.Image,
        wiki.body
      FROM artists_group AS a
        LEFT JOIN wiki_artists AS wiki ON wiki.RevisionID = a.RevisionID
      WHERE a.ArtistID = '{$ArtistID}' ";
    }
    $sql .= " GROUP BY a.ArtistID";
    $DB->query($sql);
    
    if (!$DB->has_results()) {
        json_die("failure");
    }
    
    //  list($Name, $Image, $Body, $VanityHouseArtist) = $DB->next_record(MYSQLI_NUM, array(0));
    [$Name, $Image, $Body] = $DB->next_record(MYSQLI_NUM, [0]);
}

// Requests
$Requests = [];
if (empty($LoggedUser['DisableRequests'])) {
    $Requests = $Cache->get_value(sprintf('artists_requests_%s', $ArtistID));
    if (!is_array($Requests)) {
        $DB->query("
      SELECT
        r.ID,
        r.CategoryID,
        r.Title,
        r.Year,
        r.TimeAdded,
        COUNT(rv.UserID) AS Votes,
        SUM(rv.Bounty) AS Bounty
      FROM requests AS r
        LEFT JOIN requests_votes AS rv ON rv.RequestID = r.ID
        LEFT JOIN requests_artists AS ra ON r.ID = ra.RequestID
      WHERE ra.ArtistID = {$ArtistID}
        AND r.TorrentID = 0
      GROUP BY r.ID
      ORDER BY Votes DESC");
        
        $Requests = $DB->has_results() ? $DB->to_array('ID', MYSQLI_ASSOC, false) : [];
        $Cache->cache_value(sprintf('artists_requests_%s', $ArtistID), $Requests);
    }
}
$NumRequests = count($Requests);

if (($Importances = $Cache->get_value(sprintf('artist_groups_%s', $ArtistID))) === false) {
    /*
    $DB->query("
      SELECT
        DISTINCTROW ta.GroupID, ta.Importance, tg.VanityHouse, tg.Year
      FROM torrents_artists AS ta
        JOIN torrents_group AS tg ON tg.ID = ta.GroupID
      WHERE ta.ArtistID = '$ArtistID'
      ORDER BY tg.Year DESC, tg.Name DESC");
    */
    $DB->query("
    SELECT
      DISTINCTROW ta.GroupID, ta.Importance, tg.Year
    FROM torrents_artists AS ta
      JOIN torrents_group AS tg ON tg.ID = ta.GroupID
    WHERE ta.ArtistID = '{$ArtistID}'
    ORDER BY tg.Year DESC, tg.Name DESC");
    $GroupIDs = $DB->collect('GroupID');
    $Importances = $DB->to_array(false, MYSQLI_BOTH, false);
    $Cache->cache_value(sprintf('artist_groups_%s', $ArtistID), $Importances, 0);
} else {
    $GroupIDs = [];
    foreach ($Importances as $Group) {
        $GroupIDs[] = $Group['GroupID'];
    }
}
$TorrentList = count($GroupIDs) > 0 ? Torrents::get_groups($GroupIDs, true, true) : [];
$NumGroups = count($TorrentList);

//Get list of used release types
$UsedReleases = [];
foreach (array_keys($TorrentList) as $GroupID) {
    if ('2' == $Importances[$GroupID]['Importance']) {
        $TorrentList[$GroupID]['ReleaseType'] = 1024;
        $GuestAlbums = true;
    }
    if ('3' == $Importances[$GroupID]['Importance']) {
        $TorrentList[$GroupID]['ReleaseType'] = 1023;
        $RemixerAlbums = true;
    }
    if ('4' == $Importances[$GroupID]['Importance']) {
        $TorrentList[$GroupID]['ReleaseType'] = 1022;
        $ComposerAlbums = true;
    }
    if ('7' == $Importances[$GroupID]['Importance']) {
        $TorrentList[$GroupID]['ReleaseType'] = 1021;
        $ProducerAlbums = true;
    }
    if (!in_array($TorrentList[$GroupID]['ReleaseType'], $UsedReleases, true)) {
        $UsedReleases[] = $TorrentList[$GroupID]['ReleaseType'];
    }
}

if (!empty($GuestAlbums)) {
    $ReleaseTypes[1024] = 'Guest Appearance';
}
if (!empty($RemixerAlbums)) {
    $ReleaseTypes[1023] = 'Remixed By';
}
if (!empty($ComposerAlbums)) {
    $ReleaseTypes[1022] = 'Composition';
}
if (!empty($ProducerAlbums)) {
    $ReleaseTypes[1021] = 'Produced By';
}

reset($TorrentList);

$JsonTorrents = [];
$Tags = [];
$NumTorrents = $NumLeechers = $NumSnatches = 0;
$NumSeeders = $NumLeechers = $NumSnatches = 0;
foreach ($GroupIDs as $GroupID) {
    if (!isset($TorrentList[$GroupID])) {
        continue;
    }
    $Group = $TorrentList[$GroupID];
    extract(Torrents::array_group($Group));
    
    foreach ($Artists as &$Artist) {
        $Artist['id'] = (int) $Artist['id'];
        $Artist['aliasid'] = (int) $Artist['aliasid'];
    }
    
    foreach ($ExtendedArtists as &$ArtistGroup) {
        foreach ($ArtistGroup as &$Artist) {
            $Artist['id'] = (int) $Artist['id'];
            $Artist['aliasid'] = (int) $Artist['aliasid'];
        }
    }
    
    $Found = Misc::search_array($Artists, 'id', $ArtistID);
    if (isset($OnlyArtistReleases) && empty($Found)) {
        continue;
    }
    
    $GroupVanityHouse = $Importances[$GroupID]['VanityHouse'];
    
    $TagList = explode(' ', str_replace('_', '.', $TagList));
    
    // $Tags array is for the sidebar on the right
    foreach ($TagList as $Tag) {
        if (!isset($Tags[$Tag])) {
            $Tags[$Tag] = ['name' => $Tag, 'count' => 1];
        } else {
            ++$Tags[$Tag]['count'];
        }
    }
    $InnerTorrents = [];
    foreach ($Torrents as $Torrent) {
        ++$NumTorrents;
        $NumSeeders += $Torrent['Seeders'];
        $NumLeechers += $Torrent['Leechers'];
        $NumSnatches += $Torrent['Snatched'];
        
        $InnerTorrents[] = [
            'id' => (int) $Torrent['ID'],
            'groupId' => (int) $Torrent['GroupID'],
            'media' => $Torrent['Media'],
            'format' => $Torrent['Format'],
            'encoding' => $Torrent['Encoding'],
            'remasterYear' => (int) $Torrent['RemasterYear'],
            'remastered' => 1 == $Torrent['Remastered'],
            'remasterTitle' => $Torrent['RemasterTitle'],
            'remasterRecordLabel' => $Torrent['RemasterRecordLabel'],
            'scene' => 1 == $Torrent['Scene'],
            'hasLog' => 1 == $Torrent['HasLog'],
            'hasCue' => 1 == $Torrent['HasCue'],
            'logScore' => (int) $Torrent['LogScore'],
            'fileCount' => (int) $Torrent['FileCount'],
            'freeTorrent' => 1 == $Torrent['FreeTorrent'],
            'size' => (int) $Torrent['Size'],
            'leechers' => (int) $Torrent['Leechers'],
            'seeders' => (int) $Torrent['Seeders'],
            'snatched' => (int) $Torrent['Snatched'],
            'time' => $Torrent['Time'],
            'hasFile' => (int) $Torrent['HasFile']
        ];
    }
    $JsonTorrents[] = [
        'groupId' => (int) $GroupID,
        'groupName' => $GroupName,
        'groupYear' => (int) $GroupYear,
        'groupRecordLabel' => $GroupRecordLabel,
        'groupCatalogueNumber' => $GroupCatalogueNumber,
        'groupCategoryID' => $GroupCategoryID,
        'tags' => $TagList,
        'releaseType' => (int) $ReleaseType,
        'wikiImage' => $WikiImage,
        'groupVanityHouse' => 1 == $GroupVanityHouse,
        'hasBookmarked' => Bookmarks::has_bookmarked('torrent', $GroupID),
        'artists' => $Artists,
        'extendedArtists' => $ExtendedArtists,
        'torrent' => $InnerTorrents,
    
    ];
}

$JsonRequests = [];
foreach ($Requests as $RequestID => $Request) {
    $JsonRequests[] = [
        'requestId' => (int) $RequestID,
        'categoryId' => (int) $Request['CategoryID'],
        'title' => $Request['Title'],
        'year' => (int) $Request['Year'],
        'timeAdded' => $Request['TimeAdded'],
        'votes' => (int) $Request['Votes'],
        'bounty' => (int) $Request['Bounty']
    ];
}

//notifications disabled by default
$notificationsEnabled = false;
if (check_perms('site_torrents_notify')) {
    if (($Notify = $Cache->get_value('notify_artists_' . $LoggedUser['ID'])) === false) {
        $DB->query("
      SELECT ID, Artists
      FROM users_notify_filters
      WHERE UserID = '$LoggedUser[ID]'
        AND Label = 'Artist notifications'
      LIMIT 1");
        $Notify = $DB->next_record(MYSQLI_ASSOC, false);
        $Cache->cache_value('notify_artists_' . $LoggedUser['ID'], $Notify, 0);
    }
    $notificationsEnabled = false !== stripos($Notify['Artists'], sprintf('|%s|', $Name));
}

$Key = $RevisionID ? sprintf('artist_%s', $ArtistID) . sprintf('_revision_%s', $RevisionID) : sprintf('artist_%s',
    $ArtistID);

$Data = [[$Name, $Image, $Body]];

$Cache->cache_value($Key, $Data, 3600);

json_die("success", [
    'id' => (int) $ArtistID,
    'name' => $Name,
    'notificationsEnabled' => $notificationsEnabled,
    'hasBookmarked' => Bookmarks::has_bookmarked('artist', $ArtistID),
    'image' => $Image,
    'body' => Text::full_format($Body),
    'vanityHouse' => 1 == $VanityHouseArtist,
    'tags' => array_values($Tags),
    'statistics' => [
        'numGroups' => $NumGroups,
        'numTorrents' => $NumTorrents,
        'numSeeders' => $NumSeeders,
        'numLeechers' => $NumLeechers,
        'numSnatches' => $NumSnatches
    ],
    'torrentgroup' => $JsonTorrents,
    'requests' => $JsonRequests
]);
