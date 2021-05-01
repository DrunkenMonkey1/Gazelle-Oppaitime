<?php

declare(strict_types=1);

// TODO: Turn this into a class with nice functions like update_user, delete_torrent, etc.
class Tracker
{
    public const STATS_MAIN = 0;
    public const STATS_USER = 1;
    
    /**
     * @var mixed[]
     */
    public static array $Requests = [];
    
    /**
     * Send a GET request over a socket directly to the tracker
     * For example, Tracker::update_tracker('change_passkey', array('oldpasskey' => OLD_PASSKEY, 'newpasskey' =>
     * NEW_PASSKEY)) will send the request: GET
     * /tracker_32_char_secret_code/update?action=change_passkey&oldpasskey=OLD_PASSKEY&newpasskey=NEW_PASSKEY HTTP/1.1
     *
     * @param string $Action  The action to send
     * @param array  $Updates An associative array of key->value pairs to send to the tracker
     * @param bool   $ToIRC   Sends a message to the channel #tracker with the GET URL.
     *
     * @return bool
     */
    public static function update_tracker(string $Action, array $Updates, bool $ToIRC = false): bool
    {
        // Build request
        $Get = TRACKER_SECRET . "/update?action=$Action";
        foreach ($Updates as $Key => $Value) {
            $Get .= "&$Key=$Value";
        }
        
        $MaxAttempts = 3;
        // don't wait around if we're debugging
        if (DEBUG_MODE) {
            $MaxAttempts = 1;
        }
        
        $Err = false;
        if (false === self::send_request($Get, $MaxAttempts, $Err)) {
            send_irc("PRIVMSG " . BOT_DEBUG_CHAN . " :$MaxAttempts $Err $Get");
            $ocelot_error_reportedCache_value = G::$Cache->get_value('ocelot_error_reported');
            if (false === $ocelot_error_reportedCache_value) {
                send_irc('PRIVMSG ' . ADMIN_CHAN . " :Failed to update ocelot: $Err : $Get");
                G::$Cache->cache_value('ocelot_error_reported', true, 3600);
            }
            
            return false;
        }
        
        return true;
    }
    
    /**
     * Send a request to the tracker
     *
     * @param      $Get
     * @param int  $MaxAttempts Maximum number of failed attempts before giving up
     * @param bool $Err         Variable to use as storage for the error string if the request fails
     *
     * @return bool|string response message or false if the request failed
     */
    private static function send_request($Get, int $MaxAttempts = 1, &$Err = false): bool|string
    {
        $Header = "GET /$Get HTTP/1.1\r\nConnection: Close\r\n\r\n";
        $Attempts = 0;
        $Sleep = 0;
        $Success = false;
        $StartTime = microtime(true);
        while (!$Success && $Attempts++ < $MaxAttempts) {
            if ($Sleep) {
                sleep($Sleep);
            }
            
            // spend some time retrying if we're not in DEBUG_MODE
            if (!DEBUG_MODE) {
                $Sleep = 6;
            }
            
            // Send request
            $File = fsockopen(TRACKER_HOST, TRACKER_PORT, $ErrorNum, $ErrorString);
            if ($File) {
                if (!fwrite($File, $Header)) {
                    $Err = "Failed to fwrite()";
                    $Sleep = 3;
                    continue;
                }
            } else {
                $Err = "Failed to fsockopen() - $ErrorNum - $ErrorString";
                continue;
            }
            
            // Check for response.
            $Response = '';
            while (!feof($File)) {
                $Response .= fread($File, 1024);
            }
            $DataStart = strpos($Response, "\r\n\r\n") + 4;
            $DataEnd = strrpos($Response, "\n");
            $Data = $DataEnd > $DataStart ? substr($Response, $DataStart, $DataEnd - $DataStart) : "";
            $Status = substr($Response, $DataEnd + 1);
            if ("success" == $Status) {
                $Success = true;
            }
        }
        $Request = [
            'path' => substr($Get, strpos($Get, '/')),
            'response' => ($Success ? $Data : $Response),
            'status' => ($Success ? 'ok' : 'failed'),
            'time' => 1000 * (microtime(true) - $StartTime)
        ];
        self::$Requests[] = $Request;
        if ($Success) {
            return $Data;
        }
        
        return false;
    }
    
    /**
     * Get global peer stats from the tracker
     *
     * @return bool|array (0 => $Leeching, 1 => $Seeding) or false if request failed
     */
    public static function global_peer_count(): bool|array
    {
        $Stats = self::get_stats(self::STATS_MAIN);
        if (isset($Stats['leechers tracked'], $Stats['seeders tracked'])) {
            $Leechers = $Stats['leechers tracked'];
            $Seeders = $Stats['seeders tracked'];
        } else {
            return false;
        }
        
        return [$Leechers, $Seeders];
    }
    
    /**
     * Send a stats request to the tracker and process the results
     *
     * @param int  $Type   Stats type to get
     * @param bool $Params Parameters required by stats type
     *
     * @return bool|array with stats in named keys or false if the request failed
     */
    private static function get_stats(int $Type, bool $Params = false): bool|array
    {
        if (!defined('TRACKER_REPORTKEY')) {
            return false;
        }
        $Get = TRACKER_REPORTKEY . '/report?';
        if (self::STATS_MAIN === $Type) {
            $Get .= 'get=stats';
        } elseif (self::STATS_USER === $Type && !empty($Params['key'])) {
            $Get .= "get=user&key=$Params[key]";
        } else {
            return false;
        }
        $Response = self::send_request($Get);
        if (false === $Response) {
            return false;
        }
        $Stats = [];
        foreach (explode("\n", $Response) as $Stat) {
            [$Val, $Key] = explode(" ", $Stat, 2);
            $Stats[$Key] = $Val;
        }
        
        return $Stats;
    }
    
    /**
     * Get peer stats for a user from the tracker
     *
     * @param string $TorrentPass The user's pass key
     *
     * @return bool|array (0 => $Leeching, 1 => $Seeding) or false if the request failed
     */
    public static function user_peer_count(string $TorrentPass): bool|array
    {
        $Stats = self::get_stats(self::STATS_USER, ['key' => $TorrentPass]);
        if (false === $Stats) {
            return false;
        }
        if (isset($Stats['leeching']) && isset($Stats['seeding'])) {
            $Leeching = $Stats['leeching'];
            $Seeding = $Stats['seeding'];
        } else {
            // User doesn't exist, but don't tell anyone
            $Leeching = $Seeding = 0;
        }
        
        return [$Leeching, $Seeding];
    }
    
    /**
     * Get whatever info the tracker has to report
     *
     * @return mixed[] from get_stats()
     */
    public static function info(): bool
    {
        return self::get_stats(self::STATS_MAIN);
    }
}
