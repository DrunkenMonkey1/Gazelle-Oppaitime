<?php

declare(strict_types=1);

// Begin user stats
if (($UserCount = $Cache->get_value('stats_user_count')) === false) {
    $DB->query("
    SELECT COUNT(ID)
    FROM users_main
    WHERE Enabled = '1'");
    [$UserCount] = $DB->next_record();
    $Cache->cache_value('stats_user_count', $UserCount, 0); //inf cache
}

if (($UserStats = $Cache->get_value('stats_users')) === false) {
    $DB->query("
    SELECT COUNT(ID)
    FROM users_main
    WHERE Enabled = '1'
      AND LastAccess > '" . time_minus(3600 * 24) . "'");
    [$UserStats['Day']] = $DB->next_record();
    
    $DB->query("
    SELECT COUNT(ID)
    FROM users_main
    WHERE Enabled = '1'
      AND LastAccess > '" . time_minus(3600 * 24 * 7) . "'");
    [$UserStats['Week']] = $DB->next_record();
    
    $DB->query("
    SELECT COUNT(ID)
    FROM users_main
    WHERE Enabled = '1'
      AND LastAccess > '" . time_minus(3600 * 24 * 30) . "'");
    [$UserStats['Month']] = $DB->next_record();
    
    $Cache->cache_value('stats_users', $UserStats, 0);
}

// Begin torrent stats
if (($TorrentCount = $Cache->get_value('stats_torrent_count')) === false) {
    $DB->query("
    SELECT COUNT(ID)
    FROM torrents");
    [$TorrentCount] = $DB->next_record();
    $Cache->cache_value('stats_torrent_count', $TorrentCount, 604800); // staggered 1 week cache
}

if (($AlbumCount = $Cache->get_value('stats_album_count')) === false) {
    $DB->query("
    SELECT COUNT(ID)
    FROM torrents_group
    WHERE CategoryID = '1'");
    [$AlbumCount] = $DB->next_record();
    $Cache->cache_value('stats_album_count', $AlbumCount, 604830); // staggered 1 week cache
}

if (($ArtistCount = $Cache->get_value('stats_artist_count')) === false) {
    $DB->query("
    SELECT COUNT(ArtistID)
    FROM artists_group");
    [$ArtistCount] = $DB->next_record();
    $Cache->cache_value('stats_artist_count', $ArtistCount, 604860); // staggered 1 week cache
}

// Begin request stats
if (($RequestStats = $Cache->get_value('stats_requests')) === false) {
    $DB->query("
    SELECT COUNT(ID)
    FROM requests");
    [$RequestCount] = $DB->next_record();
    $DB->query("
    SELECT COUNT(ID)
    FROM requests
    WHERE FillerID > 0");
    [$FilledCount] = $DB->next_record();
    $Cache->cache_value('stats_requests', [$RequestCount, $FilledCount], 11280);
} else {
    [$RequestCount, $FilledCount] = $RequestStats;
}


// Begin swarm stats
if (($PeerStats = $Cache->get_value('stats_peers')) === false) {
    $peer_statsCache_query_lock = $Cache->get_query_lock('peer_stats');
    //Cache lock!
    if ($peer_statsCache_query_lock) {
        $DB->query("
      SELECT IF(remaining=0,'Seeding','Leeching') AS Type, COUNT(uid)
      FROM xbt_files_users
      WHERE active = 1
      GROUP BY Type");
        $PeerCount = $DB->to_array(0, MYSQLI_NUM, false);
        $LeecherCount = isset($PeerCount['Leeching']) ? $PeerCount['Leeching'][1] : 0;
        $SeederCount = isset($PeerCount['Seeding']) ? $PeerCount['Seeding'][1] : 0;
        $Cache->cache_value('stats_peers', [$LeecherCount, $SeederCount], 1_209_600); // 2 week cache
        $Cache->clear_query_lock('peer_stats');
    } else {
        $LeecherCount = 0;
        $SeederCount = 0;
    }
} else {
    [$LeecherCount, $SeederCount] = $PeerStats;
}

json_print("success", [
    'maxUsers' => USER_LIMIT,
    'enabledUsers' => (int) $UserCount,
    'usersActiveThisDay' => (int) $UserStats['Day'],
    'usersActiveThisWeek' => (int) $UserStats['Week'],
    'usersActiveThisMonth' => (int) $UserStats['Month'],
    
    'torrentCount' => (int) $TorrentCount,
    'groupCount' => (int) $AlbumCount,
    'artistCount' => (int) $ArtistCount,
    
    'requestCount' => (int) $RequestCount,
    'filledRequestCount' => (int) $FilledCount,
    
    'seederCount' => (int) $SeederCount,
    'leecherCount' => (int) $LeecherCount
]);
