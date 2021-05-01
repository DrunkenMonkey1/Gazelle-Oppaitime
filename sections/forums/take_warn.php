<?php

declare(strict_types=1);

if (!check_perms('users_warn')) {
    error(404);
}
Misc::assert_isset_request($_POST, ['reason', 'privatemessage', 'body', 'length', 'postid', 'userid']);

$Reason = $_POST['reason'];
$PrivateMessage = $_POST['privatemessage'];
$Body = $_POST['body'];
$WarningLength = $_POST['length'];
$PostID = (int)$_POST['postid'];
$UserID = (int)$_POST['userid'];
$Key = (int)$_POST['key'];
$SQLTime = sqltime();

$UserInfo = Users::user_info($UserID);
if ($UserInfo['Class'] > $LoggedUser['Class']) {
    error(403);
}

$URL = site_url() . sprintf('forums.php?action=viewthread&amp;postid=%s#post%s', $PostID, $PostID);
if ('verbal' !== $WarningLength) {
    $Time = (int)$WarningLength * (7 * 24 * 60 * 60);
    Tools::warn_user($UserID, $Time, sprintf('%s - %s', $URL, $Reason));
    $Subject = 'You have received a warning';
    $PrivateMessage = "You have received a {$WarningLength} week warning for [url={$URL}]this post[/url].\n\n" . $PrivateMessage;

    $WarnTime = time_plus($Time);
    $AdminComment = date('Y-m-d') . sprintf(' - Warned until %s by ', $WarnTime) . $LoggedUser['Username'] . " for {$URL}\nReason: {$Reason}\n\n";
} else {
    $Subject = 'You have received a verbal warning';
    $PrivateMessage = "You have received a verbal warning for [url={$URL}]this post[/url].\n\n" . $PrivateMessage;
    $AdminComment = date('Y-m-d') . ' - Verbally warned by ' . $LoggedUser['Username'] . " for {$URL}\nReason: {$Reason}\n\n";
    Tools::update_user_notes($UserID, $AdminComment);
}

$DB->query("
  INSERT INTO users_warnings_forums
    (UserID, Comment)
  VALUES
    ('{$UserID}', '" . db_string($AdminComment) . "')
  ON DUPLICATE KEY UPDATE
    Comment = CONCAT('" . db_string($AdminComment) . "', Comment)");
Misc::send_pm($UserID, $LoggedUser['ID'], $Subject, $PrivateMessage);

//edit the post
$DB->query("
  SELECT
    p.Body,
    p.AuthorID,
    p.TopicID,
    t.ForumID,
    CEIL(
      (
        SELECT COUNT(p2.ID)
        FROM forums_posts AS p2
        WHERE p2.TopicID = p.TopicID
          AND p2.ID <= '{$PostID}'
      ) / " . POSTS_PER_PAGE . "
    ) AS Page
  FROM forums_posts AS p
    JOIN forums_topics AS t ON p.TopicID = t.ID
    JOIN forums AS f ON t.ForumID = f.ID
  WHERE p.ID = '{$PostID}'");
[$OldBody, $AuthorID, $TopicID, $ForumID, $Page] = $DB->next_record();

// Perform the update
$DB->query("
  UPDATE forums_posts
  SET Body = '" . db_string($Body) . "',
    EditedUserID = '{$UserID}',
    EditedTime = '{$SQLTime}'
  WHERE ID = '{$PostID}'");

$CatalogueID = floor((POSTS_PER_PAGE * $Page - POSTS_PER_PAGE) / THREAD_CATALOGUE);
$Cache->begin_transaction(sprintf('thread_%s', $TopicID) . sprintf('_catalogue_%s', $CatalogueID));
if ($Cache->MemcacheDBArray[$Key]['ID'] != $PostID) {
    $Cache->cancel_transaction();
    $Cache->delete_value(sprintf('thread_%s', $TopicID) . sprintf('_catalogue_%s', $CatalogueID));
//just clear the cache for would be cache-screwer-uppers
} else {
    $Cache->update_row($Key, [
        'ID' => $Cache->MemcacheDBArray[$Key]['ID'],
        'AuthorID' => $Cache->MemcacheDBArray[$Key]['AuthorID'],
        'AddedTime' => $Cache->MemcacheDBArray[$Key]['AddedTime'],
        'Body' => $Body, //Don't url decode.
        'EditedUserID' => $LoggedUser['ID'],
        'EditedTime' => $SQLTime,
        'Username' => $LoggedUser['Username']]);
    $Cache->commit_transaction(3600 * 24 * 5);
}
$ThreadInfo = Forums::get_thread_info($TopicID);
if (null === $ThreadInfo) {
    error(404);
}
if ($ThreadInfo['StickyPostID'] == $PostID) {
    $ThreadInfo['StickyPost']['Body'] = $Body;
    $ThreadInfo['StickyPost']['EditedUserID'] = $LoggedUser['ID'];
    $ThreadInfo['StickyPost']['EditedTime'] = $SQLTime;
    $Cache->cache_value(sprintf('thread_%s', $TopicID) . '_info', $ThreadInfo, 0);
}

$DB->query("
  INSERT INTO comments_edits
    (Page, PostID, EditUser, EditTime, Body)
  VALUES
    ('forums', {$PostID}, {$UserID}, '{$SQLTime}', '" . db_string($OldBody) . "')");
$Cache->delete_value(sprintf('forums_edits_%s', $PostID));

header(sprintf('Location: forums.php?action=viewthread&postid=%s#post%s', $PostID, $PostID));
