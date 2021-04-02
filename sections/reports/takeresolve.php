<?php

authorize();

if (!check_perms('admin_reports') && !check_perms('project_team') && !check_perms('site_moderate_forums')) {
    error(403);
}

$ReportID = (int) $_POST['reportid'];

$DB->query("
  SELECT Type
  FROM reports
  WHERE ID = $ReportID");
[$Type] = $DB->next_record();
if (!check_perms('admin_reports')) {
    if (check_perms('site_moderate_forums')) {
        if (!in_array($Type, ['comment', 'post', 'thread'], true)) {
            error($Type);
        }
    } elseif (check_perms('project_team')) {
        if ('request_update' != $Type) {
            error(403);
        }
    }
}

$DB->query("
  UPDATE reports
  SET Status = 'Resolved',
    ResolvedTime = NOW(),
    ResolverID = ?
  WHERE ID = ?", $LoggedUser['ID'], $LoggedUser['ID']);

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
    send_irc("PRIVMSG $Channel :Report $ReportID resolved by " . preg_replace('/^(.{2})/', '$1·', $LoggedUser['Username']) . ' on site (' . (int)$Remaining . ' remaining).');
}

$Cache->delete_value('num_other_reports');

header('Location: reports.php');
