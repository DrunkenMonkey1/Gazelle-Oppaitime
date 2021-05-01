<?php

declare(strict_types=1);

if (isset($_GET['details'])) {
    if (in_array($_GET['details'], ['ul', 'dl', 'numul', 'uls', 'dls'], true)) {
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

$BaseQuery = "
  SELECT
    u.ID,
    u.Username,
    ui.JoinDate,
    u.Uploaded,
    u.Downloaded,
    ABS(u.Uploaded-524288000) / (" . time() . " - UNIX_TIMESTAMP(ui.JoinDate)) AS UpSpeed,
    u.Downloaded / (" . time() . " - UNIX_TIMESTAMP(ui.JoinDate)) AS DownSpeed,
    COUNT(t.ID) AS NumUploads
  FROM users_main AS u
    JOIN users_info AS ui ON ui.UserID = u.ID
    LEFT JOIN torrents AS t ON t.UserID = u.ID
  WHERE u.Enabled = '1'
    AND Uploaded > '" . 5 * 1024 * 1024 * 1024 . "'
    AND Downloaded > '" . 5 * 1024 * 1024 * 1024 . "'
    AND (Paranoia IS NULL OR (Paranoia NOT LIKE '%\"uploaded\"%' AND Paranoia NOT LIKE '%\"downloaded\"%'))
  GROUP BY u.ID";

$OuterResults = [];

if ('all' == $Details || 'ul' == $Details) {
    if (!$TopUserUploads = $Cache->get_value(sprintf('topuser_ul_%s', $Limit))) {
        $DB->query("
      {$BaseQuery}
      ORDER BY u.Uploaded DESC
      LIMIT {$Limit};");
        $TopUserUploads = $DB->to_array();
        $Cache->cache_value(sprintf('topuser_ul_%s', $Limit), $TopUserUploads, 3600 * 12);
    }
    $OuterResults[] = generate_user_json('Uploaders', 'ul', $TopUserUploads, $Limit);
}

if ('all' == $Details || 'dl' == $Details) {
    if (!$TopUserDownloads = $Cache->get_value(sprintf('topuser_dl_%s', $Limit))) {
        $DB->query("
      {$BaseQuery}
      ORDER BY u.Downloaded DESC
      LIMIT {$Limit};");
        $TopUserDownloads = $DB->to_array();
        $Cache->cache_value(sprintf('topuser_dl_%s', $Limit), $TopUserDownloads, 3600 * 12);
    }
    $OuterResults[] = generate_user_json('Downloaders', 'dl', $TopUserDownloads, $Limit);
}

if ('all' == $Details || 'numul' == $Details) {
    if (!$TopUserNumUploads = $Cache->get_value(sprintf('topuser_numul_%s', $Limit))) {
        $DB->query("
      {$BaseQuery}
      ORDER BY NumUploads DESC
      LIMIT {$Limit};");
        $TopUserNumUploads = $DB->to_array();
        $Cache->cache_value(sprintf('topuser_numul_%s', $Limit), $TopUserNumUploads, 3600 * 12);
    }
    $OuterResults[] = generate_user_json('Torrents Uploaded', 'numul', $TopUserNumUploads, $Limit);
}

if ('all' == $Details || 'uls' == $Details) {
    if (!$TopUserUploadSpeed = $Cache->get_value(sprintf('topuser_ulspeed_%s', $Limit))) {
        $DB->query("
      {$BaseQuery}
      ORDER BY UpSpeed DESC
      LIMIT {$Limit};");
        $TopUserUploadSpeed = $DB->to_array();
        $Cache->cache_value(sprintf('topuser_ulspeed_%s', $Limit), $TopUserUploadSpeed, 3600 * 12);
    }
    $OuterResults[] = generate_user_json('Fastest Uploaders', 'uls', $TopUserUploadSpeed, $Limit);
}

if ('all' == $Details || 'dls' == $Details) {
    if (!$TopUserDownloadSpeed = $Cache->get_value(sprintf('topuser_dlspeed_%s', $Limit))) {
        $DB->query("
      {$BaseQuery}
      ORDER BY DownSpeed DESC
      LIMIT {$Limit};");
        $TopUserDownloadSpeed = $DB->to_array();
        $Cache->cache_value(sprintf('topuser_dlspeed_%s', $Limit), $TopUserDownloadSpeed, 3600 * 12);
    }
    $OuterResults[] = generate_user_json('Fastest Downloaders', 'dls', $TopUserDownloadSpeed, $Limit);
}

print
  json_encode(
      [
          'status' => 'success',
          'response' => $OuterResults
      ]
  );

function generate_user_json($Caption, $Tag, $Details, $Limit)
{
    $results = [];
    foreach ($Details as $Detail) {
        $results[] = [
            'id' => (int)$Detail['ID'],
            'username' => $Detail['Username'],
            'uploaded' => (float)$Detail['Uploaded'],
            'upSpeed' => (float)$Detail['UpSpeed'],
            'downloaded' => (float)$Detail['Downloaded'],
            'downSpeed' => (float)$Detail['DownSpeed'],
            'numUploads' => (int)$Detail['NumUploads'],
            'joinDate' => $Detail['JoinDate']
        ];
    }
    return [
        'caption' => $Caption,
        'tag' => $Tag,
        'limit' => (int)$Limit,
        'results' => $results
    ];
}
