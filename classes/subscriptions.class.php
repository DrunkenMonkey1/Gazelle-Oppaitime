<?php

declare(strict_types=1);

class Subscriptions
{
    /**
     * Parse a post/comment body for quotes and notify all quoted users that have quote notifications enabled.
     */
    public static function quote_notify(string $Body, int $PostID, string $Page, int $PageID): void
    {
        $QueryID = G::$DB->get_query_id();
        /*
         * Explanation of the parameters PageID and Page: Page contains where
         * this quote comes from and can be forums, artist, collages, requests
         * or torrents. The PageID contains the additional value that is
         * necessary for the users_notify_quoted table. The PageIDs for the
         * different Page are: forums: TopicID artist: ArtistID collages:
         * CollageID requests: RequestID torrents: GroupID
         */
        $Matches = [];
        preg_match_all('/\[quote(?:=(.*)(?:\|.*)?)?]|\[\/quote]/iU', $Body, $Matches, PREG_SET_ORDER);
        
        if (count($Matches) > 0) {
            $Usernames = [];
            $Level = 0;
            foreach ($Matches as $M) {
                if ('[/quote]' != $M[0]) {
                    if (0 == $Level && isset($M[1]) && strlen($M[1]) > 0 && preg_match(USERNAME_REGEX, $M[1])) {
                        $Usernames[] = preg_replace('/(^[.,]*)|([.,]*$)/', '', $M[1]); // wut?
                    }
                    ++$Level;
                } else {
                    --$Level;
                }
            }
        }
        // remove any dupes in the array (the fast way)
        $Usernames = array_flip(array_flip($Usernames));
        
        G::$DB->query("
      SELECT m.ID
      FROM users_main AS m
        LEFT JOIN users_info AS i ON i.UserID = m.ID
      WHERE m.Username IN ('" . implode("', '", $Usernames) . "')
        AND i.NotifyOnQuote = '1'
        AND i.UserID != " . G::$LoggedUser['ID']);
        
        $Results = G::$DB->to_array();
        foreach ($Results as $Result) {
            $UserID = db_string($Result['ID']);
            $QuoterID = db_string(G::$LoggedUser['ID']);
            $Page = db_string($Page);
            $PageID = db_string($PageID);
            $PostID = db_string($PostID);
            
            G::$DB->query(
                "
        INSERT IGNORE INTO users_notify_quoted
          (UserID, QuoterID, Page, PageID, PostID, Date)
        VALUES
          (    ?,               ?,               ?,      ?,       ?,   NOW())",
                $Result['ID'],
                G::$LoggedUser['ID'],
                $Page,
                $PageID,
                $PostID
            );
            G::$Cache->delete_value("notify_quoted_$UserID");
            if ('forums' == $Page) {
                $URL = site_url() . "forums.php?action=viewthread&postid=$PostID";
            } else {
                $URL = site_url() . "comments.php?action=jump&postid=$PostID";
            }
        }
        G::$DB->set_query_id($QueryID);
    }
    
    /**
     * (Un)subscribe from a forum thread.
     * If UserID == 0, G::$LoggedUser[ID] is used
     */
    public static function subscribe(int $TopicID, int $UserID = 0): void
    {
        if (0 == $UserID) {
            $UserID = G::$LoggedUser['ID'];
        }
        $QueryID = G::$DB->get_query_id();
        $UserSubscriptions = self::get_subscriptions();
        $Key = self::has_subscribed($TopicID);
        if (false !== $Key) {
            G::$DB->query('
        DELETE FROM users_subscriptions
        WHERE UserID = ' . db_string($UserID) . '
          AND TopicID = ' . db_string($TopicID));
            unset($UserSubscriptions[$Key]);
        } else {
            G::$DB->query("
        INSERT IGNORE INTO users_subscriptions (UserID, TopicID)
        VALUES ($UserID, " . db_string($TopicID) . ")");
            $UserSubscriptions[] = $TopicID;
        }
        G::$Cache->replace_value("subscriptions_user_$UserID", $UserSubscriptions, 0);
        G::$Cache->delete_value("subscriptions_user_new_$UserID");
        G::$DB->set_query_id($QueryID);
    }
    
    /**
     * Read $UserID's subscriptions. If the cache key isn't set, it gets filled.
     * If UserID == 0, G::$LoggedUser[ID] is used
     *
     * @return array Array of TopicIDs
     */
    public static function get_subscriptions(int $UserID = 0): array
    {
        if (0 == $UserID) {
            $UserID = G::$LoggedUser['ID'];
        }
        $QueryID = G::$DB->get_query_id();
        $UserSubscriptions = G::$Cache->get_value("subscriptions_user_$UserID");
        if (false === $UserSubscriptions) {
            G::$DB->query('
        SELECT TopicID
        FROM users_subscriptions
        WHERE UserID = ' . db_string($UserID));
            $UserSubscriptions = G::$DB->collect(0);
            G::$Cache->cache_value("subscriptions_user_$UserID", $UserSubscriptions, 0);
        }
        G::$DB->set_query_id($QueryID);
        
        return $UserSubscriptions;
    }
    
    /**
     * Returns the key which holds this $TopicID in the subscription array.
     * Use type-aware comparison operators with this! (ie. if (self::has_subscribed($TopicID) !== false) { ... })
     */
    public static function has_subscribed(int $TopicID): bool|int
    {
        $UserSubscriptions = self::get_subscriptions();
        
        return array_search($TopicID, $UserSubscriptions, true);
    }
    
    /**
     * (Un)subscribe from comments.
     * If UserID == 0, G::$LoggedUser[ID] is used
     *
     * @param string $Page   'artist', 'collages', 'requests' or 'torrents'
     * @param int    $PageID ArtistID, CollageID, RequestID or GroupID
     */
    public static function subscribe_comments(string $Page, int $PageID, int $UserID = 0): void
    {
        if (0 == $UserID) {
            $UserID = G::$LoggedUser['ID'];
        }
        $QueryID = G::$DB->get_query_id();
        $UserCommentSubscriptions = self::get_comment_subscriptions();
        $Key = self::has_subscribed_comments($Page, $PageID);
        if (false !== $Key) {
            G::$DB->query("
        DELETE FROM users_subscriptions_comments
        WHERE UserID = " . db_string($UserID) . "
          AND Page = '" . db_string($Page) . "'
          AND PageID = " . db_string($PageID));
            unset($UserCommentSubscriptions[$Key]);
        } else {
            G::$DB->query("
        INSERT IGNORE INTO users_subscriptions_comments
          (UserID, Page, PageID)
        VALUES
          ($UserID, '" . db_string($Page) . "', " . db_string($PageID) . ")");
            $UserCommentSubscriptions[] = [$Page, $PageID];
        }
        G::$Cache->replace_value("subscriptions_comments_user_$UserID", $UserCommentSubscriptions, 0);
        G::$Cache->delete_value("subscriptions_comments_user_new_$UserID");
        G::$DB->set_query_id($QueryID);
    }
    
    /**
     * Same as self::get_subscriptions, but for comment subscriptions
     *
     * @return array Array of ($Page, $PageID)
     */
    public static function get_comment_subscriptions(int $UserID = 0): array
    {
        if (0 == $UserID) {
            $UserID = G::$LoggedUser['ID'];
        }
        $QueryID = G::$DB->get_query_id();
        $UserCommentSubscriptions = G::$Cache->get_value("subscriptions_comments_user_$UserID");
        if (false === $UserCommentSubscriptions) {
            G::$DB->query('
        SELECT Page, PageID
        FROM users_subscriptions_comments
        WHERE UserID = ' . db_string($UserID));
            $UserCommentSubscriptions = G::$DB->to_array(false, MYSQLI_NUM);
            G::$Cache->cache_value("subscriptions_comments_user_$UserID", $UserCommentSubscriptions, 0);
        }
        G::$DB->set_query_id($QueryID);
        
        return $UserCommentSubscriptions;
    }
    
    /**
     * Same as has_subscribed, but for comment subscriptions.
     *
     * @param string $Page 'artist', 'collages', 'requests' or 'torrents'
     */
    public static function has_subscribed_comments(string $Page,  $PageID): bool|int
    {
        $UserCommentSubscriptions = self::get_comment_subscriptions();
        
        return array_search([$Page, $PageID], $UserCommentSubscriptions, true);
    }
    
    /**
     * Returns whether or not the current user has new subscriptions. This handles both forum and comment subscriptions.
     *
     * @return int Number of unread subscribed threads/comments
     */
    public static function has_new_subscriptions(): int
    {
        $QueryID = G::$DB->get_query_id();
        
        $NewSubscriptions = G::$Cache->get_value('subscriptions_user_new_' . G::$LoggedUser['ID']);
        if (false === $NewSubscriptions) {
            // forum subscriptions
            G::$DB->query("
          SELECT COUNT(1)
          FROM users_subscriptions AS s
            LEFT JOIN forums_last_read_topics AS l ON l.UserID = s.UserID AND l.TopicID = s.TopicID
            JOIN forums_topics AS t ON t.ID = s.TopicID
            JOIN forums AS f ON f.ID = t.ForumID
          WHERE " . Forums::user_forums_sql() . "
            AND IF(t.IsLocked = '1' AND t.IsSticky = '0'" . ", t.LastPostID, IF(l.PostID IS NULL, 0, l.PostID)) < t.LastPostID
            AND s.UserID = " . G::$LoggedUser['ID']);
            [$NewForumSubscriptions] = G::$DB->next_record();
            
            // comment subscriptions
            G::$DB->query("
          SELECT COUNT(1)
          FROM users_subscriptions_comments AS s
            LEFT JOIN users_comments_last_read AS lr ON lr.UserID = s.UserID AND lr.Page = s.Page AND lr.PageID = s.PageID
            LEFT JOIN comments AS c ON c.ID = (SELECT MAX(ID) FROM comments WHERE Page = s.Page AND PageID = s.PageID)
            LEFT JOIN collages AS co ON s.Page = 'collages' AND co.ID = s.PageID
          WHERE s.UserID = " . G::$LoggedUser['ID'] . "
            AND (s.Page != 'collages' OR co.Deleted = '0')
            AND IF(lr.PostID IS NULL, 0, lr.PostID) < c.ID");
            [$NewCommentSubscriptions] = G::$DB->next_record();
            
            $NewSubscriptions = $NewForumSubscriptions . $NewCommentSubscriptions;
            G::$Cache->cache_value('subscriptions_user_new_' . G::$LoggedUser['ID'], $NewSubscriptions, 0);
        }
        G::$DB->set_query_id($QueryID);
        
        return (int) $NewSubscriptions;
    }
    
    /**
     * Returns whether or not the current user has new quote notifications.
     *
     * @return int Number of unread quote notifications
     */
    public static function has_new_quote_notifications(): int
    {
        $QuoteNotificationsCount = G::$Cache->get_value('notify_quoted_' . G::$LoggedUser['ID']);
        if (false === $QuoteNotificationsCount) {
            $sql = "
        SELECT COUNT(1)
        FROM users_notify_quoted AS q
          LEFT JOIN forums_topics AS t ON t.ID = q.PageID
          LEFT JOIN forums AS f ON f.ID = t.ForumID
          LEFT JOIN collages AS c ON q.Page = 'collages' AND c.ID = q.PageID
        WHERE q.UserID = " . G::$LoggedUser['ID'] . "
          AND q.UnRead
          AND (q.Page != 'forums' OR " . Forums::user_forums_sql() . ")
          AND (q.Page != 'collages' OR c.Deleted = '0')";
            $QueryID = G::$DB->get_query_id();
            G::$DB->query($sql);
            [$QuoteNotificationsCount] = G::$DB->next_record();
            G::$DB->set_query_id($QueryID);
            G::$Cache->cache_value('notify_quoted_' . G::$LoggedUser['ID'], $QuoteNotificationsCount, 0);
        }
        
        return (int) $QuoteNotificationsCount;
    }
    
    /**
     * Move all $Page subscriptions from $OldPageID to $NewPageID (for example when merging torrent groups).
     * Passing $NewPageID = null will delete the subscriptions.
     *
     * @param string   $Page      'forums', 'artist', 'collages', 'requests' or 'torrents'
     * @param int      $OldPageID TopicID, ArtistID, CollageID, RequestID or GroupID, respectively
     * @param int|null $NewPageID As $OldPageID, or null to delete the subscriptions
     */
    public static function move_subscriptions(string $Page, int $OldPageID, ?int $NewPageID): void
    {
        self::flush_subscriptions($Page, $OldPageID);
        $QueryID = G::$DB->get_query_id();
        if ('forums' == $Page) {
            if (null !== $NewPageID) {
                G::$DB->query("
          UPDATE IGNORE users_subscriptions
          SET TopicID = '$NewPageID'
          WHERE TopicID = '$OldPageID'");
                // explanation see below
                G::$DB->query("
          UPDATE IGNORE forums_last_read_topics
          SET TopicID = $NewPageID
          WHERE TopicID = $OldPageID");
                G::$DB->query("
          SELECT UserID, MIN(PostID)
          FROM forums_last_read_topics
          WHERE TopicID IN ($OldPageID, $NewPageID)
          GROUP BY UserID
          HAVING COUNT(1) = 2");
                $Results = G::$DB->to_array(false, MYSQLI_NUM);
                foreach ($Results as $Result) {
                    G::$DB->query("
            UPDATE forums_last_read_topics
            SET PostID = $Result[1]
            WHERE TopicID = $NewPageID
              AND UserID = $Result[0]");
                }
            }
            G::$DB->query("
        DELETE FROM users_subscriptions
        WHERE TopicID = '$OldPageID'");
            G::$DB->query("
        DELETE FROM forums_last_read_topics
        WHERE TopicID = $OldPageID");
        } else {
            if (null !== $NewPageID) {
                G::$DB->query("
          UPDATE IGNORE users_subscriptions_comments
          SET PageID = '$NewPageID'
          WHERE Page = '$Page'
            AND PageID = '$OldPageID'");
                // last read handling
                // 1) update all rows that have no key collisions (i.e. users that haven't previously read both pages or if there are only comments on one page)
                G::$DB->query("
          UPDATE IGNORE users_comments_last_read
          SET PageID = '$NewPageID'
          WHERE Page = '$Page'
            AND PageID = $OldPageID");
                // 2) get all last read records with key collisions (i.e. there are records for one user for both PageIDs)
                G::$DB->query("
          SELECT UserID, MIN(PostID)
          FROM users_comments_last_read
          WHERE Page = '$Page'
            AND PageID IN ($OldPageID, $NewPageID)
          GROUP BY UserID
          HAVING COUNT(1) = 2");
                $Results = G::$DB->to_array(false, MYSQLI_NUM);
                // 3) update rows for those people found in 2) to the earlier post
                foreach ($Results as $Result) {
                    G::$DB->query("
            UPDATE users_comments_last_read
            SET PostID = $Result[1]
            WHERE Page = '$Page'
              AND PageID = $NewPageID
              AND UserID = $Result[0]");
                }
            }
            G::$DB->query("
        DELETE FROM users_subscriptions_comments
        WHERE Page = '$Page'
          AND PageID = '$OldPageID'");
            G::$DB->query("
        DELETE FROM users_comments_last_read
        WHERE Page = '$Page'
          AND PageID = '$OldPageID'");
        }
        G::$DB->set_query_id($QueryID);
    }
    
    /**
     * Clear the subscription cache for all subscribers of a forum thread or artist/collage/request/torrent comments.
     *
     * @param type $Page   'forums', 'artist', 'collages', 'requests' or 'torrents'
     * @param type $PageID TopicID, ArtistID, CollageID, RequestID or GroupID, respectively
     */
    public static function flush_subscriptions($Page, $PageID): void
    {
        $QueryID = G::$DB->get_query_id();
        if ('forums' == $Page) {
            G::$DB->query("
        SELECT UserID
        FROM users_subscriptions
        WHERE TopicID = '$PageID'");
        } else {
            G::$DB->query("
        SELECT UserID
        FROM users_subscriptions_comments
        WHERE Page = '$Page'
          AND PageID = '$PageID'");
        }
        $Subscribers = G::$DB->collect('UserID');
        foreach ($Subscribers as $Subscriber) {
            G::$Cache->delete_value("subscriptions_user_new_$Subscriber");
        }
        G::$DB->set_query_id($QueryID);
    }
    
    /**
     * Clear the quote notification cache for all subscribers of a forum thread or artist/collage/request/torrent
     * comments.
     *
     * @param string $Page   'forums', 'artist', 'collages', 'requests' or 'torrents'
     * @param int    $PageID TopicID, ArtistID, CollageID, RequestID or GroupID, respectively
     */
    public static function flush_quote_notifications(string $Page, int $PageID): void
    {
        $QueryID = G::$DB->get_query_id();
        G::$DB->query("
      SELECT UserID
      FROM users_notify_quoted
      WHERE Page = '$Page'
        AND PageID = $PageID");
        $Subscribers = G::$DB->collect('UserID');
        foreach ($Subscribers as $Subscriber) {
            G::$Cache->delete_value("notify_quoted_$Subscriber");
        }
        G::$DB->set_query_id($QueryID);
    }
}
