<?php

declare(strict_types=1);

authorize();

if (!check_perms('admin_reports') && !check_perms('project_team') && !check_perms('site_moderate_forums')) {
    ajax_error();
}

$ReportID = (int) $_POST['reportid'];

$DB->query("
  SELECT Type
  FROM reports
  WHERE ID = {$ReportID}");
[$Type] = $DB->next_record();
if (!check_perms('admin_reports')) {
    if (check_perms('site_moderate_forums')) {
        if (!in_array($Type, ['comment', 'post', 'thread'], true)) {
            ajax_error();
        }
    } elseif (check_perms('project_team')) {
        if ('request_update' != $Type) {
            ajax_error();
        }
    }
}

$DB->query("
  UPDATE reports
  SET Status = 'Resolved',
    ResolvedTime = NOW(),
    ResolverID = '" . $LoggedUser['ID'] . "'
  WHERE ID = '" . db_string($ReportID) . "'");

$Channels = [];

if ('request_update' == $Type) {
    $Channels[] = '#requestedits';
    $Cache->decrement('num_update_reports');
}

if (in_array($Type, ['comment', 'post', 'thread'], true)) {
    $Channels[] = '#forumreports';
    $Cache->decrement('num_forum_reports');
}

$DB->query("
  SELECT COUNT(ID)
  FROM reports
  WHERE Status = 'New'");
[$Remaining] = $DB->next_record();

foreach ($Channels as $Channel) {
    send_irc(sprintf('PRIVMSG %s :Report %s resolved by ', $Channel, $ReportID) . preg_replace('#^(.{2})#', '$1·', $LoggedUser['Username']) . ' on site (' . (int)$Remaining . ' remaining).');
}

$Cache->delete_value('num_other_reports');

ajax_success();

function ajax_error($Error = 'error'): void
{
    echo json_encode(['status' => $Error]);
    die();
}

function ajax_success(): void
{
    echo json_encode(['status' => 'success']);
    die();
}
