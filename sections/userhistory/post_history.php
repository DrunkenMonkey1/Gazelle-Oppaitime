<?php declare(strict_types=1);
/*
User post history page
*/

if (!empty($LoggedUser['DisableForums'])) {
    error(403);
}

$UserID = empty($_GET['userid']) ? $LoggedUser['ID'] : $_GET['userid'];
if (!is_number($UserID)) {
    error(0);
}

$PerPage = $LoggedUser['PostsPerPage'] ?? POSTS_PER_PAGE;

[$Page, $Limit] = Format::page_limit($PerPage);

$UserInfo = Users::user_info($UserID);
extract(array_intersect_key($UserInfo, array_flip(['Username', 'Enabled', 'Title', 'Avatar', 'Donor', 'Warned'])));

View::show_header(sprintf('Post history for %s', $Username), 'subscriptions,comments,bbcode');

$ViewingOwn = ($UserID == $LoggedUser['ID']);
$ShowUnread = ($ViewingOwn && (!isset($_GET['showunread']) || (bool) $_GET['showunread']));
$ShowGrouped = ($ViewingOwn && (!isset($_GET['group']) || (bool) $_GET['group']));
if ($ShowGrouped) {
    $sql = '
    SELECT
      SQL_CALC_FOUND_ROWS
      MAX(p.ID) AS ID
    FROM forums_posts AS p
      LEFT JOIN forums_topics AS t ON t.ID = p.TopicID';
    if ($ShowUnread) {
        $sql .= '
      LEFT JOIN forums_last_read_topics AS l ON l.TopicID = t.ID AND l.UserID = ' . $LoggedUser['ID'];
    }
    $sql .= "
      LEFT JOIN forums AS f ON f.ID = t.ForumID
    WHERE p.AuthorID = {$UserID}
      AND " . Forums::user_forums_sql();
    if ($ShowUnread) {
        $sql .= '
      AND ((t.IsLocked = \'0\' OR t.IsSticky = \'1\')
      AND (l.PostID < t.LastPostID OR l.PostID IS NULL))';
    }
    $sql .= "
    GROUP BY MIN(t.ID)
    ORDER BY p.ID DESC
    LIMIT {$Limit}";
    $PostIDs = $DB->query($sql);
    $DB->query('SELECT FOUND_ROWS()');
    [$Results] = $DB->next_record();
    
    if ($Results > $PerPage * ($Page - 1)) {
        $DB->set_query_id($PostIDs);
        $PostIDs = $DB->collect('ID');
        $sql = "
      SELECT
        p.ID,
        p.AddedTime,
        p.Body,
        p.EditedUserID,
        p.EditedTime,
        ed.Username,
        p.TopicID,
        t.Title,
        t.LastPostID,
        l.PostID AS LastRead,
        t.IsLocked,
        t.IsSticky
      FROM forums_posts AS p
        LEFT JOIN users_main AS um ON um.ID = p.AuthorID
        LEFT JOIN users_info AS ui ON ui.UserID = p.AuthorID
        LEFT JOIN users_main AS ed ON ed.ID = p.EditedUserID
        JOIN forums_topics AS t ON t.ID = p.TopicID
        JOIN forums AS f ON f.ID = t.ForumID
        LEFT JOIN forums_last_read_topics AS l ON l.UserID = {$UserID}
            AND l.TopicID = t.ID
      WHERE p.ID IN (" . implode(',', $PostIDs) . ')
      ORDER BY p.ID DESC';
        $Posts = $DB->query($sql);
    }
} else {
    $sql = '
    SELECT
      SQL_CALC_FOUND_ROWS';
    $sql .= '
      p.ID,
      p.AddedTime,
      p.Body,
      p.EditedUserID,
      p.EditedTime,
      ed.Username,
      p.TopicID,
      t.Title,
      t.LastPostID,';
    $sql .= ($UserID == $LoggedUser['ID']) ? '
      l.PostID AS LastRead,' : '
      true AS LastRead,';
    $sql .= "
      t.IsLocked,
      t.IsSticky
    FROM forums_posts AS p
      LEFT JOIN users_main AS um ON um.ID = p.AuthorID
      LEFT JOIN users_info AS ui ON ui.UserID = p.AuthorID
      LEFT JOIN users_main AS ed ON ed.ID = p.EditedUserID
      JOIN forums_topics AS t ON t.ID = p.TopicID
      JOIN forums AS f ON f.ID = t.ForumID
      LEFT JOIN forums_last_read_topics AS l ON l.UserID = {$UserID} AND l.TopicID = t.ID
    WHERE p.AuthorID = {$UserID}
      AND " . Forums::user_forums_sql();
    
    if ($ShowUnread) {
        $sql .= '
      AND ( (t.IsLocked = \'0\' OR t.IsSticky = \'1\')
          AND (l.PostID < t.LastPostID OR l.PostID IS NULL)
        ) ';
    }
    
    $sql .= '
    ORDER BY p.ID DESC';
    
    if ($ShowGrouped) {
        $sql .= '
      ) AS sub
    GROUP BY TopicID
    ORDER BY ID DESC';
    }
    
    $sql .= sprintf(' LIMIT %s', $Limit);
    $Posts = $DB->query($sql);
    
    $DB->query('SELECT FOUND_ROWS()');
    [$Results] = $DB->next_record();
    
    $DB->set_query_id($Posts);
}

?>
<div class="thin">
    <div class="header">
        <h2>
            <?php
            if ($ShowGrouped) {
                echo 'Grouped ' . ($ShowUnread ? 'unread ' : '') . sprintf('post history for <a href="user.php?id=%s">%s</a>',
                        $UserID, $Username);
            } elseif ($ShowUnread) {
                echo sprintf('Unread post history for <a href="user.php?id=%s">%s</a>', $UserID, $Username);
            } else {
                echo sprintf('Post history for <a href="user.php?id=%s">%s</a>', $UserID, $Username);
            }
            ?>
        </h2>
        <div class="linkbox">
            <br/><br/>
            <?php
            if ($ViewingOwn) {
                $UserSubscriptions = Subscriptions::get_subscriptions();
                
                if (!$ShowUnread) {
                    if ($ShowGrouped) { ?>
                        <a href="userhistory.php?action=posts&amp;userid=<?= $UserID ?>&amp;showunread=0&amp;group=0"
                           class="brackets">Show all posts</a>&nbsp;&nbsp;&nbsp;
                    <?php } else { ?>
                        <a href="userhistory.php?action=posts&amp;userid=<?= $UserID ?>&amp;showunread=0&amp;group=1"
                           class="brackets">Show all posts (grouped)</a>&nbsp;&nbsp;&nbsp;
                    <?php } ?>
                    <a href="userhistory.php?action=posts&amp;userid=<?= $UserID ?>&amp;showunread=1&amp;group=1"
                       class="brackets">Only display posts with unread replies (grouped)</a>&nbsp;&nbsp;&nbsp;
                    <?php
                } else { ?>
                    <a href="userhistory.php?action=posts&amp;userid=<?= $UserID ?>&amp;showunread=0&amp;group=0"
                       class="brackets">Show all posts</a>&nbsp;&nbsp;&nbsp;
                    <?php if (!$ShowGrouped) { ?>
                        <a href="userhistory.php?action=posts&amp;userid=<?= $UserID ?>&amp;showunread=1&amp;group=1"
                           class="brackets">Only display posts with unread replies (grouped)</a>&nbsp;&nbsp;&nbsp;
                    <?php } else { ?>
                        <a href="userhistory.php?action=posts&amp;userid=<?= $UserID ?>&amp;showunread=1&amp;group=0"
                           class="brackets">Only display posts with unread replies</a>&nbsp;&nbsp;&nbsp;
                    <?php }
                } ?>
                <a href="userhistory.php?action=subscriptions" class="brackets">Go to subscriptions</a>
                <?php
            } else {
                ?>
                <a href="forums.php?action=search&amp;type=body&amp;user=<?= $Username ?>" class="brackets">Search</a>
                <?php
            }
            ?>
        </div>
    </div>
    <?php
    if (empty($Results)) {
        ?>
        <div class="center">
            No topics<?= $ShowUnread ? ' with unread posts' : '' ?>
        </div>
        <?php
    } else {
        ?>
        <div class="linkbox">
            <?php
            $Pages = Format::get_pages($Page, $Results, $PerPage, 11);
            echo $Pages; ?>
        </div>
        <?php
        $QueryID = $DB->get_query_id();
        while ([
            $PostID,
            $AddedTime,
            $Body,
            $EditedUserID,
            $EditedTime,
            $EditedUsername,
            $TopicID,
            $ThreadTitle,
            $LastPostID,
            $LastRead,
            $Locked,
            $Sticky
        ] = $DB->next_record()) {
            ?>
            <table class="box forum_post vertical_margin<?= Users::has_avatars_enabled() ? '' : ' noavatar' ?>"
                   id="post<?= $PostID ?>">
                <colgroup>
                    <?php if (Users::has_avatars_enabled()) { ?>
                        <col class="col_avatar"/>
                    <?php } ?>
                    <col class="col_post_body"/>
                </colgroup>
                <tr class="colhead_dark">
                    <td colspan="<?= Users::has_avatars_enabled() ? 2 : 1 ?>">
        <span class="float_left">
          <?= time_diff($AddedTime) ?>
          in <a href="forums.php?action=viewthread&amp;threadid=<?= $TopicID ?>&amp;postid=<?= $PostID ?>#post<?= $PostID ?>"
                class="tooltip"
                title="<?= display_str($ThreadTitle ?? '') ?>"><?= Format::cut_string($ThreadTitle ?? '', 75) ?></a>
<?php
if ($ViewingOwn) {
if ((!$Locked || $Sticky) && (!$LastRead || $LastRead < $LastPostID)) { ?>
    <span class="new">(New!)</span>
    <?php
} ?>
        </span>
                        <?php if (!empty($LastRead)) { ?>
                            <span class="float_left tooltip last_read" title="Jump to last read">
          <a href="forums.php?action=viewthread&amp;threadid=<?= $TopicID ?>&amp;postid=<?= $LastRead ?>#post<?= $LastRead ?>"></a>
        </span>
                        <?php }
                        } else {
                            ?>
                            </span>
                            <?php
                        } ?>
                        <span id="bar<?= $PostID ?>" class="float_right">
<?php if ($ViewingOwn && !in_array($TopicID, $UserSubscriptions, true)) { ?>
    <a href="#"
       onclick="Subscribe(<?= $TopicID ?>); $('.subscribelink<?= $TopicID ?>').remove(); return false;"
       class="brackets subscribelink<?= $TopicID ?>">Subscribe</a>
    &nbsp;
<?php } ?>
          <a href="#">&uarr;</a>
        </span>
                    </td>
                </tr>
                <?php
                if (!$ShowGrouped) {
                    ?>
                    <tr>
                        <?php if (Users::has_avatars_enabled()) { ?>
                            <td class="avatar" valign="top">
                                <?= Users::show_avatar($Avatar, $UserID, $Username, $HeavyInfo['DisableAvatars']) ?>
                            </td>
                        <?php } ?>
                        <td class="body" valign="top">
                            <div id="content<?= $PostID ?>">
                                <?= Text::full_format($Body) ?>
                                <?php if ($EditedUserID) { ?>
                                    <br/>
                                    <br/>
                                    <?php if (check_perms('site_moderate_forums')) { ?>
                                        <a href="#content<?= $PostID ?>"
                                           onclick="LoadEdit(<?= $PostID ?>, 1);">&laquo;</a>
                                    <?php } ?>
                                    Last edited by
                                    <?= Users::format_username($EditedUserID, false, false,
                                        false) ?> <?= time_diff($EditedTime, 2, true, true) ?>
                                <?php } ?>
                            </div>
                        </td>
                    </tr>
                    <?php
                }
                $DB->set_query_id($QueryID); ?>
            </table>
            <?php
        } ?>
        <div class="linkbox">
            <?= $Pages ?>
        </div>
        <?php
    } ?>
</div>
<?php View::show_footer(); ?>
