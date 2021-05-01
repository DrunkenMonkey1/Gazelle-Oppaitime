<?php

declare(strict_types=1);

authorize();

if (empty($_POST['id']) || !is_number($_POST['id']) || empty($_POST['type']) || ('request_update' !== $_POST['type'] && empty($_POST['reason']))) {
    error(404);
}

include SERVER_ROOT . '/sections/reports/array.php';

if (!array_key_exists($_POST['type'], $Types)) {
    error(403);
}
$Short = $_POST['type'];
$Type = $Types[$Short];
$ID = $_POST['id'];
if ('request_update' === $Short) {
    if (empty($_POST['year']) || !is_number($_POST['year'])) {
        error('Year must be specified.');
        header(sprintf('Location: reports.php?action=report&type=request_update&id=%s', $ID));
        die();
    }
    $Reason = '[b]Year[/b]: ' . $_POST['year'] . ".\n\n";
    // If the release type is somehow invalid, return "Not given"; otherwise, return the release type.
    $Reason .= '[b]Release type[/b]: ' . ((empty($_POST['releasetype']) || !is_number($_POST['releasetype']) || '0' === $_POST['releasetype']) ? 'Not given' : $ReleaseTypes[$_POST['releasetype']]) . ". \n\n";
    $Reason .= '[b]Additional comments[/b]: ' . $_POST['comment'];
} else {
    $Reason = $_POST['reason'];
}

switch ($Short) {
  case 'request':
  case 'request_update':
    $Link = sprintf('requests.php?action=view&id=%s', $ID);
    break;
  case 'user':
    $Link = sprintf('user.php?id=%s', $ID);
    break;
  case 'collage':
    $Link = sprintf('collages.php?id=%s', $ID);
    break;
  case 'thread':
    $Link = sprintf('forums.php?action=viewthread&threadid=%s', $ID);
    break;
  case 'post':
    $DB->query("
      SELECT
        p.ID,
        p.TopicID,
        (
          SELECT COUNT(p2.ID)
          FROM forums_posts AS p2
          WHERE p2.TopicID = p.TopicID
            AND p2.ID <= p.ID
        ) AS PostNum
      FROM forums_posts AS p
      WHERE p.ID = {$ID}");
    [$PostID, $TopicID, $PostNum] = $DB->next_record();
    $Link = sprintf('forums.php?action=viewthread&threadid=%s&post=%s#post%s', $TopicID, $PostNum, $PostID);
    break;
  case 'comment':
    $Link = sprintf('comments.php?action=jump&postid=%s', $ID);
    break;
}

$DB->query('
  INSERT INTO reports
    (UserID, ThingID, Type, ReportedTime, Reason)
  VALUES
    (' . db_string($LoggedUser['ID']) . sprintf(', %s, \'%s\', NOW(), \'', $ID, $Short) . db_string($Reason) . "')");
$ReportID = $DB->inserted_id();

$Channels = [];

if ('request_update' === $Short) {
    $Channels[] = '#requestedits';
    $Cache->increment('num_update_reports');
}
if (in_array($Short, ['comment', 'post', 'thread'], true)) {
    $Channels[] = '#forumreports';
}


foreach ($Channels as $Channel) {
    send_irc(sprintf('PRIVMSG %s :%s - ', $Channel, $ReportID) . $LoggedUser['Username'] . sprintf(' just reported a %s: ', $Short) . site_url() . sprintf('%s : ', $Link) . strtr($Reason, "\n", ' '));
}

$Cache->delete_value('num_other_reports');

header(sprintf('Location: %s', $Link));
