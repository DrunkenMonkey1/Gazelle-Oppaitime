<?php

declare(strict_types=1);

class Forums
{
    /**
     * Get information on a thread.
     *
     * @param int     $ThreadID       the thread ID.
     * @param bool    $Return         indicates whether thread info should be returned.
     * @param Boolean $SelectiveCache cache thread info.
     *
     * @return array   holding thread information.
     */
    public static function get_thread_info(int $ThreadID, bool $Return = true, bool $SelectiveCache = false)
    {
        if ((!$ThreadInfo = G::$Cache->get_value('thread_' . $ThreadID . '_info')) || !isset($ThreadInfo['Ranking'])) {
            $QueryID = G::$DB->get_query_id();
            G::$DB->query("
        SELECT
          t.Title,
          t.ForumID,
          t.IsLocked,
          t.IsSticky,
          COUNT(fp.id) AS Posts,
          t.LastPostAuthorID,
          ISNULL(p.TopicID) AS NoPoll,
          t.StickyPostID,
          t.AuthorID as OP,
          t.Ranking
        FROM forums_topics AS t
          JOIN forums_posts AS fp ON fp.TopicID = t.ID
          LEFT JOIN forums_polls AS p ON p.TopicID = t.ID
        WHERE t.ID = ?
        GROUP BY fp.TopicID", $ThreadID);
            if (!G::$DB->has_results()) {
                G::$DB->set_query_id($QueryID);
                
                return null;
            }
            $ThreadInfo = G::$DB->next_record(MYSQLI_ASSOC, false);
            if ($ThreadInfo['StickyPostID']) {
                $ThreadInfo['Posts']--;
                G::$DB->query(
                    "SELECT
            p.ID,
            p.AuthorID,
            p.AddedTime,
            p.Body,
            p.EditedUserID,
            p.EditedTime,
            ed.Username
            FROM forums_posts AS p
              LEFT JOIN users_main AS ed ON ed.ID = p.EditedUserID
            WHERE p.TopicID = ?
              AND p.ID = ?",
                    $ThreadID,
                    $ThreadInfo['StickyPostID']
                );
                [$ThreadInfo['StickyPost']] = G::$DB->to_array(false, MYSQLI_ASSOC);
            }
            G::$DB->set_query_id($QueryID);
            if (!$SelectiveCache || !$ThreadInfo['IsLocked'] || $ThreadInfo['IsSticky']) {
                G::$Cache->cache_value('thread_' . $ThreadID . '_info', $ThreadInfo, 0);
            }
        }
        if ($Return) {
            return $ThreadInfo;
        }
    }
    
    /**
     * Checks whether user has permissions on a forum.
     *
     * @param int    $ForumID the forum ID.
     * @param string $Perm    the permissision to check, defaults to 'Read'
     *
     * @return bool   true if user has permission
     */
    public static function check_forumperm(int $ForumID, string $Perm = 'Read'): bool
    {
        if (isset(G::$LoggedUser['CustomForums'][$ForumID]) && 1 == G::$LoggedUser['CustomForums'][$ForumID]) {
            return true;
        }
        if (DONOR_FORUM == $ForumID && Donations::has_donor_forum(G::$LoggedUser['ID'])) {
            return true;
        }
        $Forums = self::get_forums();
        if ($Forums[$ForumID]['MinClass' . $Perm] > G::$LoggedUser['Class'] && (!isset(G::$LoggedUser['CustomForums'][$ForumID]) || 0 == G::$LoggedUser['CustomForums'][$ForumID])) {
            return false;
        }
        
        return !(isset(G::$LoggedUser['CustomForums'][$ForumID]) && 0 == G::$LoggedUser['CustomForums'][$ForumID]);
    }
    
    /**
     * Get the forums
     *
     * @return array ForumID => (various information about the forum)
     */
    public static function get_forums(): array
    {
        if (!$Forums = G::$Cache->get_value('forums_list')) {
            $QueryID = G::$DB->get_query_id();
            G::$DB->query("
        SELECT
          f.ID,
          f.CategoryID,
          f.Name,
          f.Description,
          f.MinClassRead AS MinClassRead,
          f.MinClassWrite AS MinClassWrite,
          f.MinClassCreate AS MinClassCreate,
          f.NumTopics,
          f.NumPosts,
          f.LastPostID,
          f.LastPostAuthorID,
          f.LastPostTopicID,
          f.LastPostTime,
          0 AS SpecificRules,
          t.Title,
          t.IsLocked AS Locked,
          t.IsSticky AS Sticky
        FROM forums AS f
          JOIN forums_categories AS fc ON fc.ID = f.CategoryID
          LEFT JOIN forums_topics AS t ON t.ID = f.LastPostTopicID
        GROUP BY f.ID
        ORDER BY fc.Sort, fc.Name, f.CategoryID, f.Sort");
            $Forums = G::$DB->to_array('ID', MYSQLI_ASSOC, false);
            
            G::$DB->query("
        SELECT ForumID, ThreadID
        FROM forums_specific_rules");
            $SpecificRules = [];
            while ([$ForumID, $ThreadID] = G::$DB->next_record(MYSQLI_NUM, false)) {
                $SpecificRules[$ForumID][] = $ThreadID;
            }
            G::$DB->set_query_id($QueryID);
            foreach ($Forums as $ForumID => &$Forum) {
                $Forum['SpecificRules'] = isset($SpecificRules[$ForumID]) ? $SpecificRules[$ForumID] : [];
            }
            G::$Cache->cache_value('forums_list', $Forums, 0);
        }
        
        return $Forums;
    }
    
    /**
     * Gets basic info on a forum.
     *
     * @param int $ForumID the forum ID.
     *
     * @return bool|mixed
     */
    public static function get_forum_info(int $ForumID)
    {
        $Forum = G::$Cache->get_value("ForumInfo_$ForumID");
        if (!$Forum) {
            $QueryID = G::$DB->get_query_id();
            G::$DB->query("
        SELECT
          Name,
          MinClassRead,
          MinClassWrite,
          MinClassCreate,
          COUNT(forums_topics.ID) AS Topics
        FROM forums
          LEFT JOIN forums_topics ON forums_topics.ForumID = forums.ID
        WHERE forums.ID = ?
        GROUP BY ForumID", $ForumID);
            if (!G::$DB->has_results()) {
                return false;
            }
            // Makes an array, with $Forum['Name'], etc.
            $Forum = G::$DB->next_record(MYSQLI_ASSOC);
            
            G::$DB->set_query_id($QueryID);
            
            G::$Cache->cache_value("ForumInfo_$ForumID", $Forum, 86400);
        }
        
        return $Forum;
    }
    
    /**
     * Get the forum categories
     *
     * @return array ForumCategoryID => Name
     */
    public static function get_forum_categories(): array
    {
        $ForumCats = G::$Cache->get_value('forums_categories');
        if (false === $ForumCats) {
            $QueryID = G::$DB->get_query_id();
            G::$DB->query("
        SELECT ID, Name
        FROM forums_categories");
            $ForumCats = [];
            while ([$ID, $Name] = G::$DB->next_record()) {
                $ForumCats[$ID] = $Name;
            }
            G::$DB->set_query_id($QueryID);
            G::$Cache->cache_value('forums_categories', $ForumCats, 0);
        }
        
        return $ForumCats;
    }
    
    /**
     * Get the last read posts for the current user
     *
     * @param array $Forums Array of forums as returned by self::get_forums()
     *
     * @return array TopicID => array(TopicID, PostID, Page) where PostID is the ID of the last read post and Page is
     *               the page on which that post is
     */
    public static function get_last_read(array $Forums): array
    {
        $PerPage = G::$LoggedUser['PostsPerPage'] ?? POSTS_PER_PAGE;
        $TopicIDs = [];
        foreach ($Forums as $Forum) {
            if (!empty($Forum['LastPostTopicID'])) {
                $TopicIDs[] = $Forum['LastPostTopicID'];
            }
        }
        if (!empty($TopicIDs)) {
            $QueryID = G::$DB->get_query_id();
            G::$DB->query("
        SELECT
          l.TopicID,
          l.PostID,
          CEIL(
            (
              SELECT
                COUNT(p.ID)
              FROM forums_posts AS p
              WHERE p.TopicID = l.TopicID
                AND p.ID <= l.PostID
            ) / ?
          ) AS Page
        FROM forums_last_read_topics AS l
        WHERE l.TopicID IN(" . implode(',', $TopicIDs) . ") AND
          l.UserID = ?", $PerPage, G::$LoggedUser['ID']);
            $LastRead = G::$DB->to_array('TopicID', MYSQLI_ASSOC);
            G::$DB->set_query_id($QueryID);
        } else {
            $LastRead = [];
        }
        
        return $LastRead;
    }
    
    /**
     * Add a note to a topic.
     */
    public static function add_topic_note(int $TopicID, string $Note, ?int $UserID = null): bool
    {
        if (null === $UserID) {
            $UserID = G::$LoggedUser['ID'];
        }
        $QueryID = G::$DB->get_query_id();
        G::$DB->query("
      INSERT INTO forums_topic_notes
        (TopicID, AuthorID, AddedTime, Body)
      VALUES
        (?, ?, NOW(), ?)", $TopicID, $UserID, $Note);
        G::$DB->set_query_id($QueryID);
        
        return (bool) G::$DB->affected_rows();
    }
    
    /**
     * Determine if a thread is unread
     *
     * @param array  $LastRead    An array as returned by self::get_last_read
     * @param int    $LastTopicID TopicID of the thread where the most recent post was made
     * @param string $LastTime    Datetime of the last post
     *
     * @return bool
     */
    public static function is_unread($Locked, $Sticky, $LastPostID, array $LastRead, int $LastTopicID, $LastTime)
    {
        return (!$Locked || $Sticky) && 0 !== $LastPostID && ((empty($LastRead[$LastTopicID]) || $LastRead[$LastTopicID]['PostID'] < $LastPostID) && strtotime($LastTime) > G::$LoggedUser['CatchupTime']);
    }
    
    /**
     * Create the part of WHERE in the sql queries used to filter forums for a
     * specific user (MinClassRead, restricted and permitted forums).
     */
    public static function user_forums_sql(): string
    {
        // I couldn't come up with a good name, please rename this if you can. -- Y
        $RestrictedForums = self::get_restricted_forums();
        $PermittedForums = self::get_permitted_forums();
        if (Donations::has_donor_forum(G::$LoggedUser['ID']) && !in_array(DONOR_FORUM, $PermittedForums, true)) {
            $PermittedForums[] = DONOR_FORUM;
        }
        $SQL = "((f.MinClassRead <= '" . G::$LoggedUser['Class'] . "'";
        if ([] !== $RestrictedForums) {
            $SQL .= " AND f.ID NOT IN ('" . implode("', '", $RestrictedForums) . "')";
        }
        $SQL .= ')';
        if ([] !== $PermittedForums) {
            $SQL .= " OR f.ID IN ('" . implode("', '", $PermittedForums) . "')";
        }
        
        return $SQL . ')';
    }
    
    /**
     * Get all forums that the current user does not have access to ("Restricted forums" in the profile)
     *
     * @return array Array of ForumIDs
     */
    public static function get_restricted_forums()
    {
        if (isset(G::$LoggedUser['CustomForums'])) {
            return (array) array_keys(G::$LoggedUser['CustomForums'], 0, true);
        }
        
        return [];
    }
    
    /**
     * Get all forums that the current user has special access to ("Extra forums" in the profile)
     *
     * @return array Array of ForumIDs
     */
    public static function get_permitted_forums()
    {
        if (isset(G::$LoggedUser['CustomForums'])) {
            return (array) array_keys(G::$LoggedUser['CustomForums'], 1, true);
        }
        
        return [];
    }
}
