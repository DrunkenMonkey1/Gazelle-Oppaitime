<?php

declare(strict_types=1);

class UserRank
{
    public const PREFIX = 'percentiles_'; // Prefix for memcache keys, to make life easier
    
    // Returns a 101 row array (101 percentiles - 0 - 100), with the minimum value for that percentile as the value for each row
    // BTW - ingenious

    public static function get_rank($TableName, $Value): int|bool
    {
        if (0 == $Value) {
            return 0;
        }
        
        $Table = G::$Cache->get_value(self::PREFIX . $TableName);
        if (!$Table) {
            //Cache lock!
            $Lock = G::$Cache->get_value(self::PREFIX . $TableName . '_lock');
            if ($Lock) {
                return false;
            }
            
            G::$Cache->cache_value(self::PREFIX . $TableName . '_lock', '1', 300);
            $Table = self::build_table(self::PREFIX . $TableName, self::table_query($TableName));
            G::$Cache->delete_value(self::PREFIX . $TableName . '_lock');
        }
        $LastPercentile = 0;
        foreach ($Table as $Row) {
            [$CurValue] = $Row;
            if ($CurValue >= $Value) {
                return $LastPercentile;
            }
            $LastPercentile++;
        }
        
        return 100; // 100th percentile
    }
    
    private static function build_table($MemKey, $Query): array
    {
        $QueryID = G::$DB->get_query_id();
        
        G::$DB->query("
      DROP TEMPORARY TABLE IF EXISTS temp_stats");
        
        G::$DB->query("
      CREATE TEMPORARY TABLE temp_stats (
        ID int(10) NOT NULL PRIMARY KEY AUTO_INCREMENT,
        Val bigint(20) NOT NULL
      );");
        
        G::$DB->query("
      INSERT INTO temp_stats (Val) " .
            $Query);
        
        G::$DB->query("
      SELECT COUNT(ID)
      FROM temp_stats");
        [$UserCount] = G::$DB->next_record();
        
        G::$DB->query("
      SELECT MIN(Val)
      FROM temp_stats
      GROUP BY CEIL(ID / (" . (int) $UserCount . " / 100));");
        
        $Table = G::$DB->to_array();
        
        G::$DB->set_query_id($QueryID);
        
        // Give a little variation to the cache length, so all the tables don't expire at the same time
        G::$Cache->cache_value($MemKey, $Table, 3600 * 24 * rand(800, 1000) * 0.001);
        
        return $Table;
    }
    
    private static function table_query($TableName): string
    {
        return match ($TableName) {
            'uploaded' => "
          SELECT Uploaded
          FROM users_main
          WHERE Enabled = '1'
            AND Uploaded > 0
          ORDER BY Uploaded;",
            'downloaded' => "
          SELECT Downloaded
          FROM users_main
          WHERE Enabled = '1'
            AND Downloaded > 0
          ORDER BY Downloaded;",
            'uploads' => "
          SELECT COUNT(t.ID) AS Uploads
          FROM users_main AS um
            JOIN torrents AS t ON t.UserID = um.ID
          WHERE um.Enabled = '1'
          GROUP BY um.ID
          ORDER BY Uploads;",
            'requests' => "
          SELECT COUNT(r.ID) AS Requests
          FROM users_main AS um
            JOIN requests AS r ON r.FillerID = um.ID
          WHERE um.Enabled = '1'
          GROUP BY um.ID
          ORDER BY Requests;",
            'posts' => "
          SELECT COUNT(p.ID) AS Posts
          FROM users_main AS um
            JOIN forums_posts AS p ON p.AuthorID = um.ID
          WHERE um.Enabled = '1'
          GROUP BY um.ID
          ORDER BY Posts;",
            'bounty' => "
          SELECT SUM(rv.Bounty) AS Bounty
          FROM users_main AS um
            JOIN requests_votes AS rv ON rv.UserID = um.ID
          WHERE um.Enabled = '1' " .
                "GROUP BY um.ID
          ORDER BY Bounty;",
            'artists' => "
          SELECT COUNT(ta.ArtistID) AS Artists
          FROM torrents_artists AS ta
            JOIN torrents_group AS tg ON tg.ID = ta.GroupID
            JOIN torrents AS t ON t.GroupID = tg.ID
          WHERE t.UserID != ta.UserID
          GROUP BY tg.ID
          ORDER BY Artists ASC",
        };
    }
    
    public static function overall_score(
        $Uploaded,
        $Downloaded,
        $Uploads,
        $Requests,
        $Posts,
        $Bounty,
        $Artists,
        $Ratio
    ): bool|float|int {
        // We can do this all in 1 line, but it's easier to read this way
        if ($Ratio > 1) {
            $Ratio = 1;
        }
        $TotalScore = 0;
        if (in_array(false, func_get_args(), true)) {
            return false;
        }
        $TotalScore += $Uploaded * 15;
        $TotalScore += $Downloaded * 8;
        $TotalScore += $Uploads * 25;
        $TotalScore += $Requests * 2;
        $TotalScore += $Posts;
        $TotalScore += $Bounty;
        $TotalScore += $Artists;
        $TotalScore /= (15 + 8 + 25 + 2 + 1 + 1 + 1);
        $TotalScore *= $Ratio;
        
        return $TotalScore;
    }
}
