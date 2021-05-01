<?php

declare(strict_types=1);

enforce_login();

if (!isset($_REQUEST['action'])) {
    $_REQUEST['action'] = '';
}

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
// Logged in user is staff
$IsStaff = (1 == $DisplayStaff);
// Logged in user is Staff or FLS
$IsFLS = ($IsStaff || ($LoggedUser['ExtraClasses'] && $LoggedUser['ExtraClasses'][FLS_TEAM]));

switch ($_REQUEST['action']) {
  case 'viewconv':
    require __DIR__ . '/viewconv.php';
    break;
  case 'takepost':
    require __DIR__ . '/takepost.php';
    break;
  case 'resolve':
    require __DIR__ . '/resolve.php';
    break;
  case 'unresolve':
    require __DIR__ . '/unresolve.php';
    break;
  case 'multiresolve':
    require __DIR__ . '/multiresolve.php';
    break;
  case 'assign':
    require __DIR__ . '/assign.php';
    break;
  case 'make_donor':
    require __DIR__ . '/makedonor.php';
    break;
  case 'responses':
    require __DIR__ . '/common_responses.php';
    break;
  case 'get_response':
    require __DIR__ . '/ajax_get_response.php';
    break;
  case 'delete_response':
    require __DIR__ . '/ajax_delete_response.php';
    break;
  case 'edit_response':
    require __DIR__ . '/ajax_edit_response.php';
    break;
  case 'preview':
    require __DIR__ . '/ajax_preview_response.php';
    break;
  case 'get_post':
    require __DIR__ . '/get_post.php';
    break;
  case 'scoreboard':
    require __DIR__ . '/scoreboard.php';
    break;
  default:
    if ($IsStaff || $IsFLS) {
        require __DIR__ . '/staff_inbox.php';
    } else {
        require __DIR__ . '/user_inbox.php';
    }
    break;
}
