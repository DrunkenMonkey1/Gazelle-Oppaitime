<?php

declare(strict_types=1);

switch ($_GET['action']) {
  case 'notify_clear':
    $DB->query(sprintf('DELETE FROM users_notify_torrents WHERE UserID = \'%s\' AND UnRead = \'0\'', $LoggedUser[ID]));
    $Cache->delete_value('notifications_new_' . $LoggedUser['ID']);
    header('Location: torrents.php?action=notify');
    break;

  case 'notify_clear_item':
  case 'notify_clearitem':
    if (!isset($_GET['torrentid']) || !is_number($_GET['torrentid'])) {
        error(0);
    }
    $DB->query(sprintf('DELETE FROM users_notify_torrents WHERE UserID = \'%s\' AND TorrentID = \'%s\'', $LoggedUser[ID], $_GET[torrentid]));
    $Cache->delete_value('notifications_new_' . $LoggedUser['ID']);
    break;

  case 'notify_clear_items':
    if (!isset($_GET['torrentids'])) {
        error(0);
    }
    $TorrentIDs = explode(',', $_GET['torrentids']);
    foreach ($TorrentIDs as $TorrentID) {
        if (!is_number($TorrentID)) {
            error(0);
        }
    }
    $DB->query(sprintf('DELETE FROM users_notify_torrents WHERE UserID = %s AND TorrentID IN (%s)', $LoggedUser[ID], $_GET[torrentids]));
    $Cache->delete_value('notifications_new_' . $LoggedUser['ID']);
    break;

  case 'notify_clear_filter':
  case 'notify_cleargroup':
    if (!isset($_GET['filterid']) || !is_number($_GET['filterid'])) {
        error(0);
    }
    $DB->query(sprintf('DELETE FROM users_notify_torrents WHERE UserID = \'%s\' AND FilterID = \'%s\' AND UnRead = \'0\'', $LoggedUser[ID], $_GET[filterid]));
    $Cache->delete_value('notifications_new_' . $LoggedUser['ID']);
    header('Location: torrents.php?action=notify');
    break;

  case 'notify_catchup':
    $DB->query(sprintf('UPDATE users_notify_torrents SET UnRead = \'0\' WHERE UserID=%s', $LoggedUser[ID]));
    if ($DB->affected_rows()) {
        $Cache->delete_value('notifications_new_' . $LoggedUser['ID']);
    }
    header('Location: torrents.php?action=notify');
    break;

  case 'notify_catchup_filter':
    if (!isset($_GET['filterid']) || !is_number($_GET['filterid'])) {
        error(0);
    }
    $DB->query(sprintf('UPDATE users_notify_torrents SET UnRead=\'0\' WHERE UserID = %s AND FilterID = %s', $LoggedUser[ID], $_GET[filterid]));
    if ($DB->affected_rows()) {
        $Cache->delete_value('notifications_new_' . $LoggedUser['ID']);
    }
    header('Location: torrents.php?action=notify');
    break;
  default:
    error(0);
}
