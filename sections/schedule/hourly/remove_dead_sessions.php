<?php

declare(strict_types=1);

//------------- Remove dead sessions ---------------------------------------//

$AgoMins = time_minus(60 * 30);
$AgoDays = time_minus(3600 * 24 * 30);

$SessionQuery = $DB->query("
    SELECT UserID, SessionID
    FROM users_sessions
    WHERE (LastUpdate < '{$AgoDays}' AND KeepLogged = '1')
      OR (LastUpdate < '{$AgoMins}' AND KeepLogged = '0')");

$DB->query("
  DELETE FROM users_sessions
  WHERE (LastUpdate < '{$AgoDays}' AND KeepLogged = '1')
    OR (LastUpdate < '{$AgoMins}' AND KeepLogged = '0')");

$DB->set_query_id($SessionQuery);

while ([$UserID, $SessionID] = $DB->next_record()) {
    $Cache->begin_transaction(sprintf('users_sessions_%s', $UserID));
    $Cache->delete_row($SessionID);
    $Cache->commit_transaction(0);
}
