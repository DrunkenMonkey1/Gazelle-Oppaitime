<?php

declare(strict_types=1);

class Artists
{
    /**
     * Convenience function for get_artists, when you just need one group.
     *
     * @return array - see get_artists
     */
    public static function get_artist(int $GroupID): array
    {
        $Results = Artists::get_artists([$GroupID]);
        
        return $Results[$GroupID];
    }
    
    /**
     * Given an array of GroupIDs, return their associated artists.
     *
     * @return array<int|string, mixed[]> array of the following form:
     * GroupID => {
     * [ArtistType] => {
     * id, name, aliasid
     * }
     * }
     * ArtistType is an int. It can be:
     * 1 => Main artist
     * 2 => Guest artist
     * 4 => Composer
     * 5 => Conductor
     * 6 => DJ
     */
    public static function get_artists(array $GroupIDs): array
    {
        $Results = [];
        $DBs = [];
        foreach ($GroupIDs as $GroupID) {
            if (!is_number($GroupID)) {
                continue;
            }
            $Artists = G::$Cache->get_value('groups_artists_' . $GroupID);
            if (is_array($Artists)) {
                $Results[$GroupID] = $Artists;
            } else {
                $DBs[] = $GroupID;
            }
        }
        if ([] !== $DBs) {
            $IDs = implode(',', $DBs);
            if (empty($IDs)) {
                $IDs = "null";
            }
            $QueryID = G::$DB->get_query_id();
            G::$DB->query("
        SELECT ta.GroupID,
          ta.ArtistID,
          ag.Name
        FROM torrents_artists AS ta
          JOIN artists_group AS ag ON ta.ArtistID = ag.ArtistID
        WHERE ta.GroupID IN ($IDs)
        ORDER BY ta.GroupID ASC,
          ag.Name ASC;");
            while ([$GroupID, $ArtistID, $ArtistName] = G::$DB->next_record(MYSQLI_BOTH, false)) {
                $Results[$GroupID][] = ['id' => $ArtistID, 'name' => $ArtistName];
                $New[$GroupID][] = ['id' => $ArtistID, 'name' => $ArtistName];
            }
            G::$DB->set_query_id($QueryID);
            foreach ($DBs as $GroupID) {
                if (isset($New[$GroupID])) {
                    G::$Cache->cache_value('groups_artists_' . $GroupID, $New[$GroupID]);
                } else {
                    G::$Cache->cache_value('groups_artists_' . $GroupID, []);
                }
            }
            $Missing = array_diff($GroupIDs, array_keys($Results));
            if (!empty($Missing)) {
                $Results += array_fill_keys($Missing, []);
            }
        }
        
        return $Results;
    }
    
    /**
     * Format an array of artists for display.
     * TODO: Revisit the logic of this, see if we can helper-function the copypasta.
     *
     * @param array Artists an array of the form output by get_artists
     * @param bool $MakeLink      if true, the artists will be links, if false, they will be text.
     * @param bool $IncludeHyphen if true, appends " - " to the end.
     * @param      $Escape        if true, output will be escaped. Think carefully before setting it false.
     *
     * @return string|void
     */
    public static function display_artists($Artists, bool $MakeLink = true, bool $IncludeHyphen = true, $Escape = true)
    {
        if (!empty($Artists)) {
            $link = '';
            
            switch (count($Artists)) {
                case 0:
                    break;
                case 3:
                    $link .= self::display_artist($Artists[2], $MakeLink, $Escape) . ", ";
                // no break
                case 2:
                    $link .= self::display_artist($Artists[1], $MakeLink, $Escape) . ", ";
                // no break
                case 1:
                    $link .= self::display_artist($Artists[0], $MakeLink, $Escape) . ($IncludeHyphen ? ' – ' : '');
                    break;
                default:
                    $link = "Various" . ($IncludeHyphen ? ' – ' : '');
            }
            
            return $link;
        }
    
        return '';
    }
    
    
    /**
     * Formats a single artist name.
     *
     * @param array $Artist   an array of the form ('id'=>ID, 'name'=>Name)
     * @param bool  $MakeLink If true, links to the artist page.
     * @param bool  $Escape   If false and $MakeLink is false, returns the unescaped, unadorned artist name.
     *
     * @return string|mixed|void Formatted artist name.
     */
    public static function display_artist(array $Artist, bool $MakeLink = true, bool $Escape = true)
    {
        if ($MakeLink && !$Escape) {
            error('Invalid parameters to Artists::display_artist()');
        } elseif ($MakeLink) {
            return '<a href="artist.php?id=' . $Artist['id'] . '" dir="ltr">' . display_str($Artist['name']) . '</a>';
        } elseif ($Escape) {
            return display_str($Artist['name']);
        } else {
            return $Artist['name'];
        }
    }
    
    /**
     * Deletes an artist and their requests, wiki, and tags.
     * Does NOT delete their torrents.
     */
    public static function delete_artist(int $ArtistID): void
    {
        $QueryID = G::$DB->get_query_id();
        G::$DB->query("
      SELECT Name
      FROM artists_group
      WHERE ArtistID = " . $ArtistID);
        [$Name] = G::$DB->next_record(MYSQLI_NUM, false);
        
        // Delete requests
        G::$DB->query("
      SELECT RequestID
      FROM requests_artists
      WHERE ArtistID = $ArtistID
        AND ArtistID != 0");
        $Requests = G::$DB->to_array();
        foreach ($Requests as $Request) {
            [$RequestID] = $Request;
            G::$DB->query('DELETE FROM requests WHERE ID=' . $RequestID);
            G::$DB->query('DELETE FROM requests_votes WHERE RequestID=' . $RequestID);
            G::$DB->query('DELETE FROM requests_tags WHERE RequestID=' . $RequestID);
            G::$DB->query('DELETE FROM requests_artists WHERE RequestID=' . $RequestID);
        }
        
        // Delete artist
        G::$DB->query('DELETE FROM artists_group WHERE ArtistID=' . $ArtistID);
        G::$Cache->decrement('stats_artist_count');
        
        // Delete wiki revisions
        G::$DB->query('DELETE FROM wiki_artists WHERE PageID=' . $ArtistID);
        
        // Delete tags
        G::$DB->query('DELETE FROM artists_tags WHERE ArtistID=' . $ArtistID);
        
        // Delete artist comments, subscriptions and quote notifications
        Comments::delete_page('artist', $ArtistID);
        
        G::$Cache->delete_value('artist_' . $ArtistID);
        G::$Cache->delete_value('artist_groups_' . $ArtistID);
        $Username = empty(G::$LoggedUser['Username']) ? 'System' : G::$LoggedUser['Username'];
        Misc::write_log("Artist $ArtistID ($Name) was deleted by $Username");
        G::$DB->set_query_id($QueryID);
    }
    
    
    /**
     * Remove LRM (left-right-marker) and trims, because people copypaste carelessly.
     * If we don't do this, we get seemingly duplicate artist names.
     * TODO: make stricter, e.g. on all whitespace characters or Unicode normalisation
     */
    public static function normalise_artist_name(string $ArtistName): string
    {
        // \u200e is &lrm;
        $ArtistName = trim($ArtistName);
        $ArtistName = preg_replace('/^(\xE2\x80\x8E)+/', '', $ArtistName);
        $ArtistName = preg_replace('/(\xE2\x80\x8E)+$/', '', $ArtistName);
        
        return trim(preg_replace('/ +/', ' ', $ArtistName));
    }
}
