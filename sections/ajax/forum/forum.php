<?php


/**********|| Page to show individual forums || ********************************\

Things to expect in $_GET:
  ForumID: ID of the forum curently being browsed
  page: The page the user's on.
  page = 1 is the same as no page

********************************************************************************/

//---------- Things to sort out before it can start printing/generating content

// Check for lame SQL injection attempts
$ForumID = $_GET['forumid'];
if (!is_number($ForumID)) {
    print json_encode(['status' => 'failure']);
    die();
}

if (isset($_GET['pp'])) {
    $PerPage = intval($_GET['pp']);
} elseif (isset($LoggedUser['PostsPerPage'])) {
    $PerPage = $LoggedUser['PostsPerPage'];
} else {
    $PerPage = POSTS_PER_PAGE;
}

[$Page, $Limit] = Format::page_limit(TOPICS_PER_PAGE);

//---------- Get some data to start processing

// Caching anything beyond the first page of any given forum is just wasting ram
// users are more likely to search then to browse to page 2
if (1 == $Page) {
    [$Forum, , , $Stickies] = $Cache->get_value("forums_$ForumID");
}
if (!isset($Forum) || !is_array($Forum)) {
    $DB->query("
    SELECT
      ID,
      Title,
      AuthorID,
      IsLocked,
      IsSticky,
      NumPosts,
      LastPostID,
      LastPostTime,
      LastPostAuthorID
    FROM forums_topics
    WHERE ForumID = '$ForumID'
    ORDER BY IsSticky DESC, LastPostTime DESC
    LIMIT $Limit"); // Can be cached until someone makes a new post
    $Forum = $DB->to_array('ID', MYSQLI_ASSOC, false);
    if (1 == $Page) {
        $DB->query("
      SELECT COUNT(ID)
      FROM forums_topics
      WHERE ForumID = '$ForumID'
        AND IsSticky = '1'");
        [$Stickies] = $DB->next_record();
        $Cache->cache_value("forums_$ForumID", [$Forum, '', 0, $Stickies], 0);
    }
}

if (!isset($Forums[$ForumID])) {
    json_die("failure");
}
// Make sure they're allowed to look at the page
if (!check_perms('site_moderate_forums')) {
    if (isset($LoggedUser['CustomForums'][$ForumID]) && 0 === $LoggedUser['CustomForums'][$ForumID]) {
        json_die("failure", "insufficient permissions to view page");
    }
}
if (1 != $LoggedUser['CustomForums'][$ForumID] && $Forums[$ForumID]['MinClassRead'] > $LoggedUser['Class']) {
    json_die("failure", "insufficient permissions to view page");
}

$ForumName = display_str($Forums[$ForumID]['Name']);
$JsonSpecificRules = [];
foreach ($Forums[$ForumID]['SpecificRules'] as $ThreadIDs) {
    $Thread = Forums::get_thread_info($ThreadIDs);
    $JsonSpecificRules[] = [
        'threadId' => (int)$ThreadIDs,
        'thread' => display_str($Thread['Title'])
    ];
}

$Pages = Format::get_pages($Page, $Forums[$ForumID]['NumTopics'], TOPICS_PER_PAGE, 9);

if (0 === count($Forum)) {
    print
    json_encode(
        [
            'status' => 'success',
            'forumName' => $ForumName,
            'threads' => []
        ]
    );
} else {
    // forums_last_read_topics is a record of the last post a user read in a topic, and what page that was on
    $DB->query("
    SELECT
      l.TopicID,
      l.PostID,
      CEIL(
        (
          SELECT COUNT(p.ID)
          FROM forums_posts AS p
          WHERE p.TopicID = l.TopicID
            AND p.ID <= l.PostID
        ) / $PerPage
      ) AS Page
    FROM forums_last_read_topics AS l
    WHERE l.TopicID IN(" . implode(', ', array_keys($Forum)) . ')
      AND l.UserID = \'' . $LoggedUser['ID'] . '\'');

    // Turns the result set into a multi-dimensional array, with
    // forums_last_read_topics.TopicID as the key.
    // This is done here so we get the benefit of the caching, and we
    // don't have to make a database query for each topic on the page
    $LastRead = $DB->to_array('TopicID');

    $JsonTopics = [];
    foreach ($Forum as $Topic) {
        [$TopicID, $Title, $AuthorID, $Locked, $Sticky, $PostCount, $LastID, $LastTime, $LastAuthorID] = array_values($Topic);

        // handle read/unread posts - the reason we can't cache the whole page
        if ((!$Locked || $Sticky)
        && ((empty($LastRead[$TopicID]) || $LastRead[$TopicID]['PostID'] < $LastID)
          && strtotime($LastTime) > $LoggedUser['CatchupTime'])
    ) {
            $Read = 'unread';
        } else {
            $Read = 'read';
        }
        $UserInfo = Users::user_info($AuthorID);
        $AuthorName = $UserInfo['Username'];
        $UserInfo = Users::user_info($LastAuthorID);
        $LastAuthorName = $UserInfo['Username'];
        // Bug fix for no last time available
        if (!$LastTime) {
            $LastTime = '';
        }

        $JsonTopics[] = [
            'topicId' => (int)$TopicID,
            'title' => display_str($Title),
            'authorId' => (int)$AuthorID,
            'authorName' => $AuthorName,
            'locked' => 1 == $Locked,
            'sticky' => 1 == $Sticky,
            'postCount' => (int)$PostCount,
            'lastID' => (null == $LastID) ? 0 : (int)$LastID,
            'lastTime' => $LastTime,
            'lastAuthorId' => (null == $LastAuthorID) ? 0 : (int)$LastAuthorID,
            'lastAuthorName' => (null == $LastAuthorName) ? '' : $LastAuthorName,
            'lastReadPage' => (null == $LastRead[$TopicID]['Page']) ? 0 : (int)$LastRead[$TopicID]['Page'],
            'lastReadPostId' => (null == $LastRead[$TopicID]['PostID']) ? 0 : (int)$LastRead[$TopicID]['PostID'],
            'read' => 'read' == $Read
        ];
    }

    print
    json_encode(
        [
            'status' => 'success',
            'response' => [
                'forumName' => $ForumName,
                'specificRules' => $JsonSpecificRules,
                'currentPage' => (int)$Page,
                'pages' => ceil($Forums[$ForumID]['NumTopics'] / TOPICS_PER_PAGE),
                'threads' => $JsonTopics
            ]
        ]
    );
}
