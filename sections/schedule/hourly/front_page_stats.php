<?php

//------------- Front page stats ----------------------------------------//

//Love or hate, this makes things a hell of a lot faster

if (0 == $Hour % 2) {
    $DB->query("
    SELECT COUNT(uid) AS Snatches
    FROM xbt_snatched");
    [$SnatchStats] = $DB->next_record();
    $Cache->cache_value('stats_snatches', $SnatchStats, 0);
}

$DB->query("
  SELECT IF(remaining = 0, 'Seeding', 'Leeching') AS Type,
    COUNT(uid)
  FROM xbt_files_users
  WHERE active = 1
  GROUP BY Type");
$PeerCount = $DB->to_array(0, MYSQLI_NUM, false);
$SeederCount = isset($PeerCount['Seeding'][1]) ? $PeerCount['Seeding'][1] : 0;
$LeecherCount = isset($PeerCount['Leeching'][1]) ? $PeerCount['Leeching'][1] : 0;
$Cache->cache_value('stats_peers', [$LeecherCount, $SeederCount], 0);

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
