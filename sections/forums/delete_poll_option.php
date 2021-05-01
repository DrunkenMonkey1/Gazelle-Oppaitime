<?php

declare(strict_types=1);

authorize();
if (!check_perms('site_moderate_forums')) {
    error(404);
}

$ThreadID = $_GET['threadid'];
$PollOption = $_GET['vote'];

if (is_number($ThreadID) && is_number($PollOption)) {
    $DB->query("
    SELECT ForumID
    FROM forums_topics
    WHERE ID = {$ThreadID}");
    [$ForumID] = $DB->next_record();
    if (!in_array($ForumID, FORUMS_TO_REVEAL_VOTERS, true)) {
        error(403);
    }

    $DB->query("
    SELECT Answers
    FROM forums_polls
    WHERE TopicID = {$ThreadID}");
    if (!$DB->has_results()) {
        error(404);
    }

    [$Answers] = $DB->next_record(MYSQLI_NUM, false);
    $Answers = unserialize($Answers);
    unset($Answers[$PollOption]);
    $Answers = serialize($Answers);

    $DB->query("
    UPDATE forums_polls
    SET Answers = '" . db_string($Answers) . "'
    WHERE TopicID = {$ThreadID}");
    $DB->query("
    DELETE FROM forums_polls_votes
    WHERE Vote = {$PollOption}
      AND TopicID = {$ThreadID}");

    $Cache->delete_value(sprintf('polls_%s', $ThreadID));
    header(sprintf('Location: forums.php?action=viewthread&threadid=%s', $ThreadID));
} else {
    error(404);
}
