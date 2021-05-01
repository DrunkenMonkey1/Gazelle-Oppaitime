<?php

declare(strict_types=1);

//------------- Expire invites ------------------------------------------//

$DB->query("
  SELECT InviterID
  FROM invites
  WHERE Expires < '{$sqltime}'");
$Users = $DB->to_array();
foreach ($Users as $UserID) {
    [$UserID] = $UserID;
    $DB->query("
    SELECT Invites, PermissionID
    FROM users_main
    WHERE ID = {$UserID}");
    [$Invites, $PermID] = $DB->next_record();
    if (($Invites < 2 && $Classes[$PermID]['Level'] <= $Classes[POWER]['Level']) || ($Invites < 4 && ELITE == $PermID)) {
        $DB->query("
      UPDATE users_main
      SET Invites = Invites + 1
      WHERE ID = {$UserID}");
        $Cache->begin_transaction(sprintf('user_info_heavy_%s', $UserID));
        $Cache->update_row(false, ['Invites' => '+1']);
        $Cache->commit_transaction(0);
    }
}
$DB->query("
  DELETE FROM invites
  WHERE Expires < '{$sqltime}'");
