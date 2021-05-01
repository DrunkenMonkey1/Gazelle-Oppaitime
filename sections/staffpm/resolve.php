<?php

declare(strict_types=1);

if (($ID = (int)($_GET['id'])) !== 0) {
    // Check if conversation belongs to user
    $DB->query("
    SELECT UserID, AssignedToUser
    FROM staff_pm_conversations
    WHERE ID = {$ID}");
    [$UserID, $AssignedToUser] = $DB->next_record();

    if ($UserID == $LoggedUser['ID'] || $IsFLS || $AssignedToUser == $LoggedUser['ID']) {
        // Conversation belongs to user or user is staff, resolve it
        $DB->query("
      UPDATE staff_pm_conversations
      SET Status = 'Resolved', ResolverID = $LoggedUser[ID]
      WHERE ID = {$ID}");
        $Cache->delete_value(sprintf('staff_pm_new_%s', $LoggedUser[ID]));
        $Cache->delete_value(sprintf('num_staff_pms_%s', $LoggedUser[ID]));

        header('Location: staffpm.php');
    } else {
        // Conversation does not belong to user
        error(403);
    }
} else {
    // No ID
    header('Location: staffpm.php');
}
