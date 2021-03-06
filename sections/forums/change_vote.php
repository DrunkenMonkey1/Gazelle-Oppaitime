<?php

declare(strict_types=1);

authorize();
$ThreadID = $_GET['threadid'];
$NewVote = $_GET['vote'];

if (is_number($ThreadID) && is_number($NewVote)) {
    if (!check_perms('site_moderate_forums')) {
        $DB->query("
      SELECT ForumID
      FROM forums_topics
      WHERE ID = {$ThreadID}");
        [$ForumID] = $DB->next_record();
        if (!in_array($ForumID, FORUMS_TO_REVEAL_VOTERS, true)) {
            error(403);
        }
    }

    $DB->query("
    UPDATE forums_polls_votes
    SET Vote = {$NewVote}
    WHERE TopicID = {$ThreadID}
      AND UserID = " . $LoggedUser['ID']);
    $Cache->delete_value('polls_' . $ThreadID);
    header("Location: forums.php?action=viewthread&threadid=" . $ThreadID);
} else {
    error(404);
}
