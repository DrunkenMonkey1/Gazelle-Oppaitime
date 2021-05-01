<?php

declare(strict_types=1);

enforce_login();

// Get user level
$DB->query(
    "
  SELECT
    i.SupportFor,
    p.DisplayStaff
  FROM users_info AS i
    JOIN users_main AS m ON m.ID = i.UserID
    JOIN permissions AS p ON p.ID = m.PermissionID
  WHERE i.UserID = " . $LoggedUser['ID']
);
[$SupportFor, $DisplayStaff] = $DB->next_record();

if (!$IsFLS) {
    // Logged in user is not FLS or Staff
    error(403);
}

if (($ID = (int)$_GET['id']) !== 0) {
    $DB->query("
    SELECT Message
    FROM staff_pm_responses
    WHERE ID = {$ID}");
    [$Message] = $DB->next_record();
    if (1 == $_GET['plain']) {
        echo $Message;
    } else {
        echo Text::full_format($Message);
    }
} else {
    // No ID
    echo '-1';
}
