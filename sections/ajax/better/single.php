<?php

declare(strict_types=1);

if (($Results = $Cache->get_value('better_single_groupids')) === false) {
    /**
     * @var \DB_MYSQL $DB
     */
    $DB->query("
    SELECT
      t.ID AS TorrentID,
      t.GroupID AS GroupID
    FROM xbt_files_users AS x
      JOIN torrents AS t ON t.ID=x.fid
    WHERE t.Format='FLAC'
    GROUP BY x.fid
    HAVING COUNT(x.uid) = 1
    ORDER BY t.LogScore DESC, t.Time ASC
    LIMIT 30");
    
    $Results = $DB->to_pair('GroupID', 'TorrentID', false);
    $Cache->cache_value('better_single_groupids', $Results, 30 * 60);
}

$Groups = Torrents::get_groups(array_keys($Results));

$JsonResults = [];
foreach ($Results as $GroupID => $FlacID) {
    if (!isset($Groups[$GroupID])) {
        continue;
    }
    $Group = $Groups[$GroupID];
    extract(Torrents::array_group($Group));
    
    $JsonArtists = [];
    if (count($Artists) > 0) {
        foreach ($Artists as $Artist) {
            $JsonArtists[] = [
                'id' => (int) $Artist['id'],
                'name' => $Artist['name'],
                'aliasId' => (int) $Artist['aliasid']
            ];
        }
    }
    
    $JsonResults[] = [
        'torrentId' => (int) $FlacID,
        'groupId' => (int) $GroupID,
        'artist' => $JsonArtists,
        'groupName' => $GroupName,
        'groupYear' => (int) $GroupYear,
        'downloadUrl' => sprintf('torrents.php?action=download&id=%s&authkey=',
                $FlacID) . $LoggedUser['AuthKey'] . '&torrent_pass=' . $LoggedUser['torrent_pass']
    ];
}

print json_encode([
    'status' => 'success',
    'response' => $JsonResults
], JSON_THROW_ON_ERROR);
