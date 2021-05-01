<?php

declare(strict_types=1);


// error out on invalid requests (before caching)
if (isset($_GET['details'])) {
    if (in_array($_GET['details'], ['ut', 'ur'], true)) {
        $Details = $_GET['details'];
    } else {
        print json_encode(['status' => 'failure']);
        die();
    }
} else {
    $Details = 'all';
}

// defaults to 10 (duh)
$Limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
$Limit = in_array($Limit, [10, 100, 250], true) ? $Limit : 10;
$OuterResults = [];

if ('all' == $Details || 'ut' == $Details) {
    if (!$TopUsedTags = $Cache->get_value(sprintf('topusedtag_%s', $Limit))) {
        $DB->query("
      SELECT
        t.ID,
        t.Name,
        COUNT(tt.GroupID) AS Uses
      FROM tags AS t
        JOIN torrents_tags AS tt ON tt.TagID = t.ID
      GROUP BY tt.TagID
      ORDER BY Uses DESC
      LIMIT {$Limit}");
        $TopUsedTags = $DB->to_array();
        $Cache->cache_value(sprintf('topusedtag_%s', $Limit), $TopUsedTags, 3600 * 12);
    }

    $OuterResults[] = generate_tag_json('Most Used Torrent Tags', 'ut', $TopUsedTags, $Limit);
}

if ('all' == $Details || 'ur' == $Details) {
    if (!$TopRequestTags = $Cache->get_value(sprintf('toprequesttag_%s', $Limit))) {
        $DB->query("
      SELECT
        t.ID,
        t.Name,
        COUNT(r.RequestID) AS Uses,
        '',''
      FROM tags AS t
        JOIN requests_tags AS r ON r.TagID = t.ID
      GROUP BY r.TagID
      ORDER BY Uses DESC
      LIMIT {$Limit}");
        $TopRequestTags = $DB->to_array();
        $Cache->cache_value(sprintf('toprequesttag_%s', $Limit), $TopRequestTags, 3600 * 12);
    }

    $OuterResults[] = generate_tag_json('Most Used Request Tags', 'ur', $TopRequestTags, $Limit);
}

print json_encode([
    'status' => 'success',
    'response' => $OuterResults
]);

function generate_tag_json($Caption, $Tag, $Details, $Limit)
{
    $results = [];
    foreach ($Details as $Detail) {
        $results[] = [
            'name' => $Detail['Name'],
            'uses' => (int)$Detail['Uses']
        ];
    }

    return [
        'caption' => $Caption,
        'tag' => $Tag,
        'limit' => (int)$Limit,
        'results' => $results
    ];
}
