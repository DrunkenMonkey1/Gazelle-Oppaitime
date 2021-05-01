<?php

declare(strict_types=1);

class Bookmarks
{
    /**
     * Check if can bookmark
     */
    public static function can_bookmark(string $Type): bool
    {
        return in_array($Type, [
            'torrent',
            'artist',
            'collage',
            'request'
        ], true);
    }
    
    /**
     * Check if something is bookmarked
     *
     *                      type of bookmarks to check
     *                      bookmark's id
     */
    public static function has_bookmarked(string $Type, $ID): bool
    {
        if (is_null($ID)) {
            return false;
        }
        
        return in_array($ID, self::all_bookmarks($Type));
    }
    
    /**
     * Fetch all bookmarks of a certain type for a user.
     * If UserID is false than defaults to G::$LoggedUser['ID']
     *
     *                        type of bookmarks to fetch
     *                        userid whose bookmarks to get
     *
     * @param string    $Type
     * @param int|false $UserID
     *
     * @return array the bookmarks
     */
    public static function all_bookmarks(string $Type, int|bool $UserID = false): array
    {
        if (false === $UserID) {
            $UserID = G::$LoggedUser['ID'];
        }
        $CacheKey = 'bookmarks_' . $Type . '_' . $UserID;
        if (($Bookmarks = G::$Cache->get_value($CacheKey)) === false) {
            [$Table, $Col] = self::bookmark_schema($Type);
            $QueryID = G::$DB->get_query_id();
            G::$DB->query("
        SELECT $Col
        FROM $Table
        WHERE UserID = '$UserID'");
            $Bookmarks = G::$DB->collect($Col);
            G::$DB->set_query_id($QueryID);
            G::$Cache->cache_value($CacheKey, $Bookmarks, 0);
        }
        
        return $Bookmarks;
    }
    
    /**
     * Get the bookmark schema.
     * Recommended usage:
     * list($Table, $Col) = bookmark_schema('torrent');
     *
     * @param string $Type the type to get the schema for
     */
    public static function bookmark_schema(string $Type)
    {
        switch ($Type) {
            case 'torrent':
                return [
                    'bookmarks_torrents',
                    'GroupID'
                ];
                break;
            case 'artist':
                return [
                    'bookmarks_artists',
                    'ArtistID'
                ];
                break;
            case 'collage':
                return [
                    'bookmarks_collages',
                    'CollageID'
                ];
                break;
            case 'request':
                return [
                    'bookmarks_requests',
                    'RequestID'
                ];
                break;
            default:
                die('HAX');
        }
    }
}
