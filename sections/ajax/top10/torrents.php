<?php

declare(strict_types=1);

if (isset($_GET['details'])) {
    if (in_array($_GET['details'], ['day', 'week', 'overall', 'snatched', 'data', 'seeded'], true)) {
        $Details = $_GET['details'];
    } else {
        print json_encode(['status' => 'failure'], JSON_THROW_ON_ERROR);
        die();
    }
} else {
    $Details = 'all';
}

// defaults to 10 (duh)
$Limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
$Limit = in_array($Limit, [10, 100, 250], true) ? $Limit : 10;

$WhereSum = (empty($Where)) ? '' : md5($Where);
$BaseQuery = '
  SELECT
    t.ID,
    g.ID,
    g.Name,
    g.CategoryID,
    g.WikiImage,
    g.TagList,
    t.Media,
    g.Year,
    t.Snatched,
    t.Seeders,
    t.Leechers,
    ((t.Size * t.Snatched) + (t.Size * 0.5 * t.Leechers)) AS Data,
    t.Size
  FROM torrents AS t
    LEFT JOIN torrents_group AS g ON g.ID = t.GroupID';

$OuterResults = [];

if ('all' == $Details || 'day' == $Details) {
    if (!$TopTorrentsActiveLastDay = $Cache->get_value('top10tor_day_' . $Limit . $WhereSum)) {
        $DayAgo = time_minus(86400);
        $Query = $BaseQuery . ' WHERE t.Seeders>0 AND ';
        if (!empty($Where)) {
            $Query .= $Where . ' AND ';
        }
        $Query .= "
      t.Time>'{$DayAgo}'
      ORDER BY (t.Seeders + t.Leechers) DESC
      LIMIT {$Limit};";
        $DB->query($Query);
        $TopTorrentsActiveLastDay = $DB->to_array(false, MYSQLI_NUM);
        $Cache->cache_value('top10tor_day_' . $Limit . $WhereSum, $TopTorrentsActiveLastDay, 3600 * 2);
    }
    $OuterResults[] = generate_torrent_json('Most Active Torrents Uploaded in the Past Day', 'day', $TopTorrentsActiveLastDay, $Limit);
}
if ('all' == $Details || 'week' == $Details) {
    if (!$TopTorrentsActiveLastWeek = $Cache->get_value('top10tor_week_' . $Limit . $WhereSum)) {
        $WeekAgo = time_minus(604800);
        $Query = $BaseQuery . ' WHERE ';
        if (!empty($Where)) {
            $Query .= $Where . ' AND ';
        }
        $Query .= "
      t.Time>'{$WeekAgo}'
      ORDER BY (t.Seeders + t.Leechers) DESC
      LIMIT {$Limit};";
        $DB->query($Query);
        $TopTorrentsActiveLastWeek = $DB->to_array(false, MYSQLI_NUM);
        $Cache->cache_value('top10tor_week_' . $Limit . $WhereSum, $TopTorrentsActiveLastWeek, 3600*6);
    }
    $OuterResults[] = generate_torrent_json('Most Active Torrents Uploaded in the Past Week', 'week', $TopTorrentsActiveLastWeek, $Limit);
}

if ('all' == $Details || 'overall' == $Details) {
    if (!$TopTorrentsActiveAllTime = $Cache->get_value(sprintf('top10tor_overall_%s%s', $Limit, $WhereSum))) {
        $Query = $BaseQuery;
        if (!empty($Where)) {
            $Query .= sprintf(' WHERE %s', $Where);
        }
        $Query .= "
      ORDER BY (t.Seeders + t.Leechers) DESC
      LIMIT {$Limit};";
        $DB->query($Query);
        $TopTorrentsActiveAllTime = $DB->to_array(false, MYSQLI_NUM);
        $Cache->cache_value(sprintf('top10tor_overall_%s%s', $Limit, $WhereSum), $TopTorrentsActiveAllTime, 3600 * 6);
    }
    $OuterResults[] = generate_torrent_json('Most Active Torrents of All Time', 'overall', $TopTorrentsActiveAllTime, $Limit);
}

if (('all' == $Details || 'snatched' == $Details) && empty($Where)) {
    if (!$TopTorrentsSnatched = $Cache->get_value(sprintf('top10tor_snatched_%s%s', $Limit, $WhereSum))) {
        $Query = $BaseQuery;
        $Query .= "
      ORDER BY t.Snatched DESC
      LIMIT {$Limit};";
        $DB->query($Query);
        $TopTorrentsSnatched = $DB->to_array(false, MYSQLI_NUM);
        $Cache->cache_value(sprintf('top10tor_snatched_%s%s', $Limit, $WhereSum), $TopTorrentsSnatched, 3600 * 6);
    }
    $OuterResults[] = generate_torrent_json('Most Snatched Torrents', 'snatched', $TopTorrentsSnatched, $Limit);
}

if (('all' == $Details || 'data' == $Details) && empty($Where)) {
    if (!$TopTorrentsTransferred = $Cache->get_value(sprintf('top10tor_data_%s%s', $Limit, $WhereSum))) {
        $Query = $BaseQuery;
        $Query .= "
      ORDER BY Data DESC
      LIMIT {$Limit};";
        $DB->query($Query);
        $TopTorrentsTransferred = $DB->to_array(false, MYSQLI_NUM);
        $Cache->cache_value(sprintf('top10tor_data_%s%s', $Limit, $WhereSum), $TopTorrentsTransferred, 3600 * 6);
    }
    $OuterResults[] = generate_torrent_json('Most Data Transferred Torrents', 'data', $TopTorrentsTransferred, $Limit);
}

if (('all' == $Details || 'seeded' == $Details) && empty($Where)) {
    if (!$TopTorrentsSeeded = $Cache->get_value(sprintf('top10tor_seeded_%s%s', $Limit, $WhereSum))) {
        $Query = $BaseQuery . "
      ORDER BY t.Seeders DESC
      LIMIT {$Limit};";
        $DB->query($Query);
        $TopTorrentsSeeded = $DB->to_array(false, MYSQLI_NUM);
        $Cache->cache_value(sprintf('top10tor_seeded_%s%s', $Limit, $WhereSum), $TopTorrentsSeeded, 3600 * 6);
    }
    $OuterResults[] = generate_torrent_json('Best Seeded Torrents', 'seeded', $TopTorrentsSeeded, $Limit);
}

json_print("success", $OuterResults);


function generate_torrent_json($Caption, $Tag, $Details, $Limit)
{
    global $LoggedUser, $Categories;
    $results = [];
    foreach ($Details as $Detail) {
        [$TorrentID, $GroupID, $GroupName, $GroupCategoryID, $WikiImage, $TorrentTags,
      $Media, $GroupYear,
      $Snatched, $Seeders, $Leechers, $Data, $Size] = $Detail;

        $Artist = Artists::display_artists(Artists::get_artist($GroupID), false, false);

        $TagList = [];

        if ('' != $TorrentTags) {
            $TorrentTags = explode(' ', $TorrentTags);
            foreach ($TorrentTags as $TagKey => $TagName) {
                $TagName = str_replace('_', '.', $TagName);
                $TagList[] = $TagName;
            }
        }

        // Append to the existing array.
        $results[] = [
            'torrentId'     => (int)$TorrentID,
            'groupId'       => (int)$GroupID,
            'artist'        => $Artist,
            'groupName'     => $GroupName,
            'groupCategory' => (int)$GroupCategoryID,
            'groupYear'     => (int)$GroupYear,
            'media'         => $Media,
            'tags'          => $TagList,
            'snatched'      => (int)$Snatched,
            'seeders'       => (int)$Seeders,
            'leechers'      => (int)$Leechers,
            'data'          => (int)$Data,
            'size'          => (int)$Size,
            'wikiImage'     => $WikiImage,
        ];
    }

    return [
        'caption' => $Caption,
        'tag'     => $Tag,
        'limit'   => (int)$Limit,
        'results' => $results
    ];
}
