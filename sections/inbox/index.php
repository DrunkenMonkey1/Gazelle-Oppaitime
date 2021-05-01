<?php

declare(strict_types=1);

enforce_login();

$StaffIDs = $Cache->get_value('staff_ids');
if (!is_array($StaffIDs)) {
    $DB->query("
    SELECT m.ID, m.Username
    FROM users_main AS m
      JOIN permissions AS p ON p.ID=m.PermissionID
    WHERE p.DisplayStaff='1'");
    while ([$StaffID, $StaffName] = $DB->next_record()) {
        $StaffIDs[$StaffID] = $StaffName;
    }
    uasort($StaffIDs, 'strcasecmp');
    $Cache->cache_value('staff_ids', $StaffIDs);
}

if (!isset($_REQUEST['action'])) {
    $_REQUEST['action'] = '';
}
match ($_REQUEST['action']) {
    'takecompose' => require __DIR__ . '/takecompose.php',
    'takeedit' => require __DIR__ . '/takeedit.php',
    'compose' => require __DIR__ . '/compose.php',
    'viewconv' => require __DIR__ . '/conversation.php',
    'masschange' => require __DIR__ . '/massdelete_handle.php',
    'get_post' => require __DIR__ . '/get_post.php',
    'forward' => require __DIR__ . '/forward.php',
    default => require SERVER_ROOT . '/sections/inbox/inbox.php',
};
