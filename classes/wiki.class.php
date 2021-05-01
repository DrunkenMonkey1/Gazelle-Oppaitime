<?php

declare(strict_types=1);

class Wiki
{
    /**
     * Flush the alias cache. Call this whenever you touch the wiki_aliases table.
     */
    public static function flush_aliases(): void
    {
        G::$Cache->delete_value('wiki_aliases');
    }
    
    /**
     * Get the ArticleID corresponding to an alias
     *
     * @return bool|int|void
     */
    public static function alias_to_id(string $Alias)
    {
        $Aliases = self::get_aliases();
        $Alias = self::normalize_alias($Alias);
        if (!isset($Aliases[$Alias])) {
            return false;
        }
        
        return (int) $Aliases[$Alias];
    }
    
    /**
     * Get all aliases in an associative array of Alias => ArticleID
     *
     * @return mixed[]
     */
    public static function get_aliases(): array
    {
        $Aliases = G::$Cache->get_value('wiki_aliases');
        if (!$Aliases) {
            $QueryID = G::$DB->get_query_id();
            G::$DB->query("
        SELECT Alias, ArticleID
        FROM wiki_aliases");
            $Aliases = G::$DB->to_pair('Alias', 'ArticleID');
            G::$DB->set_query_id($QueryID);
            G::$Cache->cache_value('wiki_aliases', $Aliases, 3600 * 24 * 14); // 2 weeks
        }
        
        return $Aliases;
    }
    
    /**
     * Normalize an alias
     *
     * @param string $str
     *
     * @return string
     */
    public static function normalize_alias(string $str): string
    {
        return trim(substr(preg_replace('/[^a-z0-9]/', '', strtolower(htmlentities($str))), 0, 50));
    }
    
    /**
     * Get an article; returns false on error if $Error = false
     */
    public static function get_article(int $ArticleID, bool $Error = true): array|bool
    {
        $Contents = G::$Cache->get_value('wiki_article_' . $ArticleID);
        if (!$Contents) {
            $QueryID = G::$DB->get_query_id();
            G::$DB->query("
        SELECT
          w.Revision,
          w.Title,
          w.Body,
          w.MinClassRead,
          w.MinClassEdit,
          w.Date,
          w.Author,
          u.Username,
          GROUP_CONCAT(a.Alias),
          GROUP_CONCAT(a.UserID)
        FROM wiki_articles AS w
          LEFT JOIN wiki_aliases AS a ON w.ID=a.ArticleID
          LEFT JOIN users_main AS u ON u.ID=w.Author
        WHERE w.ID='$ArticleID'
        GROUP BY w.ID");
            if (!G::$DB->has_results()) {
                if ($Error) {
                    error(404);
                } else {
                    return false;
                }
            }
            $Contents = G::$DB->to_array();
            G::$DB->set_query_id($QueryID);
            G::$Cache->cache_value('wiki_article_' . $ArticleID, $Contents, 3600 * 24 * 14); // 2 weeks
        }
        
        return $Contents;
    }
    
    /**
     * Flush an article's cache. Call this whenever you edited a wiki article or its aliases.
     */
    public static function flush_article(int $ArticleID): void
    {
        G::$Cache->delete_value('wiki_article_' . $ArticleID);
    }
}
