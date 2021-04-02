<?php

//------------- Remove expired warnings ---------------------------------//

$DB->query("
  SELECT UserID
  FROM users_info
  WHERE Warned < '$sqltime'");
while ([$UserID] = $DB->next_record()) {
    $Cache->begin_transaction("user_info_$UserID");
    $Cache->update_row(false, ['Warned' => null]);
    $Cache->commit_transaction(2592000);
}

$DB->query("
  UPDATE users_info
  SET Warned = NULL
  WHERE Warned < '$sqltime'");
