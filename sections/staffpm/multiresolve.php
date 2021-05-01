<?php

declare(strict_types=1);

if ($IDs = $_POST['id']) {
    $Queries = [];
    foreach ($IDs as &$ID) {
        $ID = (int)$ID;

        // Check if conversation belongs to user
        $DB->query("
      SELECT UserID, AssignedToUser
      FROM staff_pm_conversations
      WHERE ID = {$ID}");
        [$UserID, $AssignedToUser] = $DB->next_record();

        if ($UserID == $LoggedUser['ID'] || '1' == $DisplayStaff || $UserID == $AssignedToUser) {
            // Conversation belongs to user or user is staff, queue query
            $Queries[] = "
        UPDATE staff_pm_conversations
        SET Status = 'Resolved', ResolverID = " . $LoggedUser['ID'] . "
        WHERE ID = {$ID}";
        } else {
            // Trying to run disallowed query
            error(403);
        }
    }

    // Run queries
    foreach ($Queries as $Query) {
        $DB->query($Query);
    }
    // Clear cache for user
    $Cache->delete_value(sprintf('staff_pm_new_%s', $LoggedUser[ID]));
    $Cache->delete_value(sprintf('num_staff_pms_%s', $LoggedUser[ID]));

    // Done! Return to inbox
    header("Location: staffpm.php");
} else {
    // No ID
    header("Location: staffpm.php");
}
