<?php

declare(strict_types=1);

if ($Message = db_string($_POST['message'])) {
    if (isset($_POST['subject']) && $Subject = db_string($_POST['subject'])) {
        // New staff PM conversation
        assert_numbers($_POST, ['level'], 'Invalid recipient');
        $DB->query(
            "
      INSERT INTO staff_pm_conversations
        (Subject, Status, Level, UserID, Date)
      VALUES
        ('{$Subject}', 'Unanswered', $_POST[level], $LoggedUser[ID], NOW())"
        );

        // New message
        $ConvID = $DB->inserted_id();
        $DB->query(
            "
      INSERT INTO staff_pm_messages
        (UserID, SentDate, Message, ConvID)
      VALUES
        ($LoggedUser[ID], NOW(), '{$Message}', {$ConvID})"
        );

        header('Location: staffpm.php');
    } elseif (($ConvID = (int)$_POST['convid']) !== 0) {
        // Check if conversation belongs to user
        $DB->query("
      SELECT UserID, AssignedToUser, Level
      FROM staff_pm_conversations
      WHERE ID = {$ConvID}");
        [$UserID, $AssignedToUser, $Level] = $DB->next_record();

        $LevelCap = 1000;
        $Level = min($Level, $LevelCap);

        if ($UserID == $LoggedUser['ID'] || ($IsFLS && $LoggedUser['EffectiveClass'] >= $Level) || $UserID == $AssignedToUser) {
            // Response to existing conversation
            $DB->query(
                "
        INSERT INTO staff_pm_messages
          (UserID, SentDate, Message, ConvID)
        VALUES
          (" . $LoggedUser['ID'] . sprintf(', NOW(), \'%s\', %s)', $Message, $ConvID)
            );

            // Update conversation
            if ($IsFLS) {
                // FLS/Staff
                $DB->query("
          UPDATE staff_pm_conversations
          SET Date = NOW(),
            Unread = true,
            Status = 'Open'
          WHERE ID = {$ConvID}");
                $Cache->delete_value(sprintf('num_staff_pms_%s', $LoggedUser[ID]));
            } else {
                // User
                $DB->query("
          UPDATE staff_pm_conversations
          SET Date = NOW(),
            Unread = true,
            Status = 'Unanswered'
          WHERE ID = {$ConvID}");
            }

            // Clear cache for user
            $Cache->delete_value(sprintf('staff_pm_new_%s', $UserID));
            $Cache->delete_value(sprintf('staff_pm_new_%s', $LoggedUser[ID]));

            header(sprintf('Location: staffpm.php?action=viewconv&id=%s', $ConvID));
        } else {
            // User is trying to respond to conversation that does no belong to them
            error(403);
        }
    } else {
        // Message but no subject or conversation ID
        header(sprintf('Location: staffpm.php?action=viewconv&id=%s', $ConvID));
    }
} elseif (($ConvID = (int)$_POST['convid']) !== 0) {
    // No message, but conversation ID
    header(sprintf('Location: staffpm.php?action=viewconv&id=%s', $ConvID));
} else {
    // No message or conversation ID
    header('Location: staffpm.php');
}
