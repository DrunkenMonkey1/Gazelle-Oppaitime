<?php

declare(strict_types=1);

authorize();

$UserID = $LoggedUser['ID'];
$ConvID = $_POST['convid'];
$ReceiverID = $_POST['receiverid'];
if (!is_number($ConvID) || !is_number($ReceiverID)) {
    error(404);
}
if (!check_perms('users_mod') && !isset($StaffIDs[$ReceiverID])) {
    error(403);
}
$DB->query("
  SELECT ConvID
  FROM pm_conversations_users
  WHERE UserID = '{$UserID}'
    AND InInbox = '1'
    AND (ForwardedTo = 0 OR ForwardedTo = UserID)
    AND ConvID = '{$ConvID}'");
if (!$DB->has_results()) {
    error(403);
}

$DB->query("
  SELECT ConvID
  FROM pm_conversations_users
  WHERE UserID = '{$ReceiverID}'
    AND (ForwardedTo = 0 OR ForwardedTo = UserID)
    AND InInbox = '1'
    AND ConvID = '{$ConvID}'");
if (!$DB->has_results()) {
    $DB->query("
    INSERT IGNORE INTO pm_conversations_users
      (UserID, ConvID, InInbox, InSentbox, ReceivedDate)
    VALUES ('{$ReceiverID}', '{$ConvID}', '1', '0', NOW())
    ON DUPLICATE KEY UPDATE
      ForwardedTo = 0,
      UnRead = 1");
    $DB->query("
    UPDATE pm_conversations_users
    SET ForwardedTo = '{$ReceiverID}'
    WHERE ConvID = '{$ConvID}'
      AND UserID = '{$UserID}'");
    $Cache->delete_value(sprintf('inbox_new_%s', $ReceiverID));
    header('Location: ' . Inbox::get_inbox_link());
} else {
    error(sprintf('%s already has this conversation in their inbox.', $StaffIDs[$ReceiverID]));
    header(sprintf('Location: inbox.php?action=viewconv&id=%s', $ConvID));
}
//View::show_footer();
