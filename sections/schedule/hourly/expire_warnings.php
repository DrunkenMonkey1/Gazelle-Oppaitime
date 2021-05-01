<?php

declare(strict_types=1);

//------------- Remove expired warnings ---------------------------------//

$DB->query("
  SELECT UserID
  FROM users_info
  WHERE Warned < '{$sqltime}'");
while ([$UserID] = $DB->next_record()) {
    $Cache->begin_transaction(sprintf('user_info_%s', $UserID));
    $Cache->update_row(false, ['Warned' => null]);
    $Cache->commit_transaction(2_592_000);
}

$DB->query("
  UPDATE users_info
  SET Warned = NULL
  WHERE Warned < '{$sqltime}'");
