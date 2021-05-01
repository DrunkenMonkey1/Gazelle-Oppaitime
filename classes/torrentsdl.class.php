<?php

declare(strict_types=1);

/**
 * Class for functions related to the features involving torrent downloads
 */
class TorrentsDL
{
    public const ChunkSize = 100;
    public const MaxPathLength = 200;
    public ?array $SkippedFiles = null;
    private int $QueryRowNum = 0;
    private \Zip $Zip;
    /**
     * @var null
     */
    private $IDBoundaries;
    /**
     * @var mixed[]
     */
    private array $FailedFiles = [];
    private int $NumAdded = 0;
    private int $NumFound = 0;
    private int $Size = 0;
    private $User;
    private string $AnnounceURL;
    /**
     * @var mixed[]
     */
    private array $AnnounceList;
    
    /**
     * Create a Zip object and store the query results
     *
     * @param $QueryResult
     * @param $Title
     */
    public function __construct(private $QueryResult, private $Title)
    {
        G::$Cache->InternalCache = false; // The internal cache is almost completely useless for this
        Zip::unlimit();
        $this->User = G::$LoggedUser;
        $this->AnnounceURL = ANNOUNCE_URLS[0][0] . "/" . G::$LoggedUser['torrent_pass'] . "/announce";
        function add_passkey($Ann): array|string
        {
            return (is_array($Ann)) ? array_map('add_passkey',
                $Ann) : $Ann . "/" . G::$LoggedUser['torrent_pass'] . "/announce";
        }
        
        $this->AnnounceList = (1 == count(ANNOUNCE_URLS) && 1 == count(ANNOUNCE_URLS[0])) ? [] : array_map('add_passkey',
            ANNOUNCE_URLS);
        $this->Zip = new Zip(Misc::file_string($Title));
    }
    
    /**
     * Store the results from a DB query in smaller chunks to save memory
     *
     * @param string $Key the key to use in the result hash map
     *
     * @return bool|array with results and torrent group IDs or false if there are no results left
     */
    public function get_downloads(string $Key): bool|array
    {
        $GroupIDs = $Downloads = [];
        $OldQuery = G::$DB->get_query_id();
        G::$DB->set_query_id($this->QueryResult);
        if (!(null !== $this->IDBoundaries)) {
            $this->IDBoundaries = 'TorrentID' == $Key ? false : G::$DB->to_pair($Key, 'TorrentID', false);
        }
        $Found = 0;
        while ($Download = G::$DB->next_record(MYSQLI_ASSOC, false)) {
            if (!$this->IDBoundaries || $Download['TorrentID'] == $this->IDBoundaries[$Download[$Key]]) {
                $Found++;
                $Downloads[$Download[$Key]] = $Download;
                $GroupIDs[$Download['TorrentID']] = $Download['GroupID'];
                if ($Found >= self::ChunkSize) {
                    break;
                }
            }
        }
        $this->NumFound += $Found;
        G::$DB->set_query_id($OldQuery);
        if (empty($Downloads)) {
            return false;
        }
        
        return [$Downloads, $GroupIDs];
    }
    
    /**
     * Add a file to the zip archive
     *
     * @param string $TorrentData bencoded torrent without announce url (new format) or TORRENT object (old format)
     * @param array  $Info        file info stored as an array with at least the keys
     *                            Artist, Name, Year, Media, Format, Encoding and TorrentID
     * @param string $FolderName  folder name
     */
    public function add_file(string &$TorrentData, array $Info, string $FolderName = ''): void
    {
        $FolderName = Misc::file_string($FolderName);
        $MaxPathLength = $FolderName ? (self::MaxPathLength - strlen($FolderName) - 1) : self::MaxPathLength;
        $this->Size += $Info['Size'];
        $this->NumAdded++;
        $FileName = self::construct_file_name($Info['Artist'], $Info['Name'], $Info['Year'], $Info['Media'],
            $Info['Format'], $Info['Encoding'], $Info['TorrentID'], $MaxPathLength);
        $this->Zip->add_file(self::get_file($TorrentData, $this->AnnounceURL, $this->AnnounceList),
            ($FolderName ? "$FolderName/" : "") . $FileName);
        usleep(25000); // We don't want to send much faster than the client can receive
    }
    
    /**
     * Combine a bunch of torrent info into a standardized file name
     *
     * @params most input variables are self-explanatory
     * @param           $Artist
     * @param           $Album
     * @param           $Year
     * @param           $Media
     * @param           $Format
     * @param           $Encoding
     * @param int|false $TorrentID if given, append "-TorrentID" to torrent name
     * @param int       $MaxLength maximum file name length
     *
     * @return \file|string name with at most $MaxLength characters
     */
    public static function construct_file_name(
        $Artist,
        $Album,
        $Year,
        $Media,
        $Format,
        $Encoding,
        int|bool $TorrentID = false,
        int $MaxLength = self::MaxPathLength
    ): file|string {
        $MaxLength -= 8; // ".torrent"
        if (false !== $TorrentID) {
            $MaxLength -= (strlen($TorrentID) + 1);
        }
        $TorrentArtist = Misc::file_string($Artist);
        $TorrentName = Misc::file_string($Album);
        if ($Year > 0) {
            $TorrentName .= " - $Year";
        }
        $TorrentInfo = [];
        if ('' != $Media) {
            $TorrentInfo[] = $Media;
        }
        if ('' != $Format) {
            $TorrentInfo[] = $Format;
        }
        if ('' != $Encoding) {
            $TorrentInfo[] = $Encoding;
        }
        $TorrentInfo = empty($TorrentInfo) ? '' : ' (' . Misc::file_string(implode(' - ', $TorrentInfo)) . ')';
        
        if (!$TorrentName) {
            $TorrentName = 'No Name';
        } elseif (mb_strlen($TorrentArtist . $TorrentName . $TorrentInfo, 'UTF-8') <= $MaxLength) {
            $TorrentName = $TorrentArtist . $TorrentName;
        }
        
        $TorrentName = Format::cut_string($TorrentName . $TorrentInfo, $MaxLength, true, false);
        if (false !== $TorrentID) {
            $TorrentName .= "-$TorrentID";
        }
        
        return "$TorrentName.torrent";
    }
    
    /**
     * Convert a stored torrent into a binary file that can be loaded in a torrent client
     *
     * @param mixed $TorrentData bencoded torrent without announce URL (new format) or TORRENT object (old format)
     *
     * @return string|mixed string
     */
    public static function get_file(&$TorrentData, $AnnounceURL, $AnnounceList = [])
    {
        if (Misc::is_new_torrent($TorrentData)) {
            $Bencode = BencodeTorrent::add_announce_url($TorrentData, $AnnounceURL);
            if (!empty($AnnounceList)) {
                $Bencode = BencodeTorrent::add_announce_list($Bencode, $AnnounceList);
            }
            
            return $Bencode;
        }
        $Tor = new TORRENT(unserialize(base64_decode($TorrentData, true)), true);
        $Tor->set_announce_url($AnnounceURL);
        unset($Tor->Val['announce-list']);
        if (!empty($AnnounceList)) {
            $Tor->set_announce_list($AnnounceList);
        }
        unset($Tor->Val['url-list']);
        unset($Tor->Val['libtorrent_resume']);
        
        return $Tor->enc();
    }
    
    /**
     * Add a file to the list of files that could not be downloaded
     *
     * @param array $Info file info stored as an array with at least the keys Artist, Name and Year
     */
    public function fail_file(array $Info): void
    {
        $this->FailedFiles[] = $Info['Artist'] . $Info['Name'] . " $Info[Year]";
    }
    
    /**
     * Add a file to the list of files that did not match the user's format or quality requirements
     *
     * @param array $Info file info stored as an array with at least the keys Artist, Name and Year
     */
    public function skip_file(array $Info): void
    {
        $this->SkippedFiles[] = $Info['Artist'] . $Info['Name'] . " $Info[Year]";
    }
    
    /**
     * Add a summary to the archive and include a list of files that could not be added. Close the zip archive
     *
     * @param bool $FilterStats whether to include filter stats in the report
     */
    public function finalize(bool $FilterStats = true): void
    {
        $this->Zip->add_file($this->summary($FilterStats), "Summary.txt");
        if (!empty($this->FailedFiles)) {
            $this->Zip->add_file($this->errors(), "Errors.txt");
        }
        $this->Zip->close_stream();
    }
    
    /**
     * Produce a summary text over the collector results
     *
     * @param bool $FilterStats whether to include filter stats in the report
     *
     * @return summary text
     */
    public function summary(bool $FilterStats): string
    {
        global $ScriptStartTime;
        $Time = number_format(1000 * (microtime(true) - $ScriptStartTime), 2) . " ms";
        $Used = Format::get_size(memory_get_usage(true));
        $Date = date("M d Y, H:i");
        $NumSkipped = count($this->SkippedFiles);
        
        return "Collector Download Summary for $this->Title - " . SITE_NAME . "\r\n"
            . "\r\n"
            . "User:    {$this->User[Username]}\r\n"
            . "Passkey: {$this->User[torrent_pass]}\r\n"
            . "\r\n"
            . "Time:    $Time\r\n"
            . "Used:    $Used\r\n"
            . "Date:    $Date\r\n"
            . "\r\n"
            . ($FilterStats
                ? "Torrent groups analyzed: $this->NumFound\r\n"
                . "Torrent groups filtered: $NumSkipped\r\n"
                : "")
            . "Torrents downloaded:   $this->NumAdded\r\n"
            . "\r\n"
            . "Total size of torrents (ratio hit): " . Format::get_size($this->Size) . "\r\n"
            . (0 !== $NumSkipped
                ? "\r\n"
                . "Albums unavailable within your criteria (consider making a request for your desired format):\r\n"
                . implode("\r\n", $this->SkippedFiles) . "\r\n"
                : "");
    }
    
    /**
     * Compile a list of files that could not be added to the archive
     *
     * @return list of files
     */
    public function errors(): string
    {
        return "A server error occurred. Please try again at a later time.\r\n"
            . "\r\n"
            . "The following torrents could not be downloaded:\r\n"
            . implode("\r\n", $this->FailedFiles) . "\r\n";
    }
}
