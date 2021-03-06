<?php

declare(strict_types=1);

if (!($IsFLS)) {
    // Logged in user is not FLS or Staff
    error(403);
}

if (($ConvID = (int)$_GET['convid']) !== 0) {
    // FLS, check level of conversation
    $DB->query("
    SELECT Level
    FROM staff_pm_conversations
    WHERE ID = {$ConvID}");
    [$Level] = $DB->next_record();

    if (0 == $Level) {
        // FLS conversation, assign to staff (moderator)
        if (!empty($_GET['to'])) {
            $Level = 0;
            switch ($_GET['to']) {
        case 'forum':
          $Level = 650;
          break;
        case 'staff':
          $Level = 700;
          break;
        default:
          error(404);
          break;
      }

            $DB->query("
        UPDATE staff_pm_conversations
        SET Status = 'Unanswered',
          Level = {$Level}
        WHERE ID = {$ConvID}");
            $Cache->delete_value(sprintf('num_staff_pms_%s', $LoggedUser[ID]));
            header('Location: staffpm.php');
        } else {
            error(404);
        }
    } else {
        // FLS trying to assign non-FLS conversation
        error(403);
    }
} elseif (($ConvID = (int)$_POST['convid']) !== 0) {
    // Staff (via AJAX), get current assign of conversation
    $DB->query("
    SELECT Level, AssignedToUser
    FROM staff_pm_conversations
    WHERE ID = {$ConvID}");
    [$Level, $AssignedToUser] = $DB->next_record();

    $LevelCap = 1000;

    if ($LoggedUser['EffectiveClass'] >= min($Level, $LevelCap) || $AssignedToUser == $LoggedUser['ID']) {
        // Staff member is allowed to assign conversation, assign
        [$LevelType, $NewLevel] = explode('_', db_string($_POST['assign']));

        if ('class' == $LevelType) {
            // Assign to class
            $DB->query("
        UPDATE staff_pm_conversations
        SET Status = 'Unanswered',
          Level = {$NewLevel},
          AssignedToUser = NULL
        WHERE ID = {$ConvID}");
            $Cache->delete_value(sprintf('num_staff_pms_%s', $LoggedUser[ID]));
        } else {
            $UserInfo = Users::user_info($NewLevel);
            $Level = $Classes[$UserInfo['PermissionID']]['Level'];
            if (!$Level) {
                error('Assign to user not found.');
            }

            // Assign to user
            $DB->query("
        UPDATE staff_pm_conversations
        SET Status = 'Unanswered',
          AssignedToUser = {$NewLevel},
          Level = {$Level}
        WHERE ID = {$ConvID}");
            $Cache->delete_value(sprintf('num_staff_pms_%s', $LoggedUser[ID]));
        }
        echo '1';
    } else {
        // Staff member is not allowed to assign conversation
        echo '-1';
    }
} else {
    // No ID
    header('Location: staffpm.php');
}
