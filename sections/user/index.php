<?php

declare(strict_types=1);

//TODO
/*****************************************************************
 * Finish removing the take[action] pages and utilize the index correctly
 * Should the advanced search really only show if they match 3 perms?
 * Make sure all constants are defined in config.php and not in random files
 *****************************************************************/
/**
 * @var \Cache    $Cache
 * @var \DB_MYSQL $DB
 */
enforce_login();
include SERVER_ROOT . "/classes/validate.class.php";
$Val = new VALIDATE();

if (empty($_REQUEST['action'])) {
    $_REQUEST['action'] = '';
}

switch ($_REQUEST['action']) {
    case 'notify':
        include __DIR__ . '/notify_edit.php';
        break;
    case 'notify_handle':
        include __DIR__ . '/notify_handle.php';
        break;
    case 'notify_delete':
        authorize();
        if ($_GET['id'] && is_number($_GET['id'])) {
            $DB->query("DELETE FROM users_notify_filters WHERE ID='" . db_string($_GET['id']) . sprintf('\' AND UserID=\'%s\'',
                    $LoggedUser[ID]));
            $ArtistNotifications = $Cache->get_value('notify_artists_' . $LoggedUser['ID']);
            if (is_array($ArtistNotifications) && $ArtistNotifications['ID'] == $_GET['id']) {
                $Cache->delete_value('notify_artists_' . $LoggedUser['ID']);
            }
        }
        $Cache->delete_value('notify_filters_' . $LoggedUser['ID']);
        header('Location: user.php?action=notify');
        break;
    case 'search':// User search
        if (check_perms('admin_advanced_user_search') && check_perms('users_view_ips') && check_perms('users_view_email')) {
            include __DIR__ . '/advancedsearch.php';
        } else {
            include __DIR__ . '/search.php';
        }
        break;
    case 'edit':
        include __DIR__ . '/edit.php';
        break;
    case 'take_edit':
        include __DIR__ . '/take_edit.php';
        break;
    case '2fa':
        include __DIR__ . '/2fa.php';
        break;
    case 'invitetree':
        include __DIR__ . '/invitetree.php';
        break;
    case 'invite':
        include __DIR__ . '/invite.php';
        break;
    case 'take_invite':
        include __DIR__ . '/take_invite.php';
        break;
    case 'delete_invite':
        include __DIR__ . '/delete_invite.php';
        break;
    case 'dupes':
        include __DIR__ . '/manage_linked.php';
        break;
    case 'sessions':
        include __DIR__ . '/sessions.php';
        break;
    case 'connchecker':
        include __DIR__ . '/connchecker.php';
        break;
    case 'permissions':
        include __DIR__ . '/permissions.php';
        break;
    case 'similar':
        include __DIR__ . '/similar.php';
        break;
    case 'moderate':
        include __DIR__ . '/takemoderate.php';
        break;
    case 'hnr':
        include __DIR__ . '/hnr.php';
        break;
    case 'clearcache':
        if (!check_perms('admin_clear_cache') || !check_perms('users_override_paranoia')) {
            error(403);
        }
        $UserID = $_REQUEST['id'];
        $Cache->delete_value('user_info_' . $UserID);
        $Cache->delete_value('user_info_heavy_' . $UserID);
        $Cache->delete_value('subscriptions_user_new_' . $UserID);
        $Cache->delete_value('user_badges_' . $UserID);
        $Cache->delete_value('staff_pm_new_' . $UserID);
        $Cache->delete_value('inbox_new_' . $UserID);
        $Cache->delete_value('notifications_new_' . $UserID);
        $Cache->delete_value('collage_subs_user_new_' . $UserID);
        include SERVER_ROOT . '/sections/user/user.php';
        break;
    
    case 'take_donate':
        break;
    case 'take_update_rank':
        break;
    case 'points':
        include SERVER_ROOT . '/sections/user/points.php';
        break;
    default:
        if (isset($_REQUEST['id'])) {
            include SERVER_ROOT . '/sections/user/user.php';
        } else {
            header('Location: index.php');
        }
}
