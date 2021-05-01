<?php

declare(strict_types=1);

/**
 * Torrent class that contains some convenient functions related to torrent meta data
 */
class BencodeTorrent extends BencodeDecode
{
    /**
     * @var mixed[]
     */
    public array $Files = [];
    public int $Size = 0;
    private string $PathKey = 'path';
    
    /**
     * Add the announce URL to a torrent
     *
     * @param $Data
     * @param $Url
     *
     * @return string
     */
    public static function add_announce_url($Data, $Url): string
    {
        return 'd8:announce' . strlen($Url) . ':' . $Url . substr($Data, 1);
    }
    
    /**
     * Add list of announce URLs to a torrent
     */
    public static function add_announce_list($Data, $Urls): string
    {
        $r = 'd13:announce-listl';
        foreach ($Urls as $i => $Url) {
            $r .= 'l';
            $itemsCount = count($Urls[$i]);
            for ($j = 0; $j < $itemsCount; $j++) {
                $r .= strlen($Url[$j]) . ':' . $Url[$j];
            }
            $r .= 'e';
        }
        
        return $r . 'e' . substr($Data, 1);
    }
    
    /**
     * Find out the name of the torrent
     *
     * @return bool|mixed torrent name
     */
    public function get_name()
    {
        if (empty($this->Dec)) {
            return false;
        }
        if (isset($this->Dec['info']['name.utf-8'])) {
            return $this->Dec['info']['name.utf-8'];
        }
        
        return $this->Dec['info']['name'];
    }
    
    /**
     * Find out the total size of the torrent
     *
     * @return bool|mixed torrent size
     */
    public function get_size()
    {
        if (empty($this->Files)) {
            if (empty($this->Dec)) {
                return false;
            }
            $FileList = $this->file_list();
        }
        
        return $FileList[0];
    }
    
    /**
     * Create a list of the files in the torrent and their sizes as well as the total torrent size
     *
     * @return bool|array with a list of files and file sizes
     */
    public function file_list(): bool|array
    {
        if (empty($this->Dec)) {
            return false;
        }
        $InfoDict =& $this->Dec['info'];
        if (!isset($InfoDict['files'])) {
            // Single-file torrent
            $this->Size = (Int64::is_int($InfoDict['length'])
                ? Int64::get($InfoDict['length'])
                : $InfoDict['length']);
            $Name = ($InfoDict['name.utf-8'] ?? $InfoDict['name']);
            $this->Files[] = [$this->Size, $Name];
        } else {
            if (isset($InfoDict['path.utf-8']['files'][0])) {
                $this->PathKey = 'path.utf-8';
            }
            foreach ($InfoDict['files'] as $File) {
                $TmpPath = [];
                foreach ($File[$this->PathKey] as $SubPath) {
                    $TmpPath[] = $SubPath;
                }
                $CurSize = (Int64::is_int($File['length'])
                    ? Int64::get($File['length'])
                    : $File['length']);
                $this->Files[] = [$CurSize, implode('/', $TmpPath)];
                $this->Size += $CurSize;
            }
            uasort($this->Files, fn ($a, $b) => strnatcasecmp($a[1], $b[1]));
        }
        
        return [$this->Size, $this->Files];
    }
    
    /**
     * Add the "private" flag to the torrent
     *
     * @return bool|mixed if a change was required
     */
    public function make_private()
    {
        if (empty($this->Dec)) {
            return false;
        }
        if ($this->is_private()) {
            return false;
        }
        $this->Dec['info']['private'] = Int64::make(1);
        ksort($this->Dec['info']);
        
        return true;
    }
    
    /**
     * Checks if the "private" flag is present in the torrent
     *
     * @return true if the "private" flag is set
     */
    public function is_private(): bool
    {
        if (empty($this->Dec)) {
            return false;
        }
        
        return isset($this->Dec['info']['private']) && 1 == Int64::get($this->Dec['info']['private']);
    }
    
    /**
     * Add the "source" field to the torrent
     *
     * @return true if a change was required
     */
    public function make_sourced(): bool
    {
        $Sources = Users::get_upload_sources();
        if (empty($this->Dec)) {
            return false;
        }
        if (isset($this->Dec['info']['source']) && ($this->Dec['info']['source'] == $Sources[0] || $this->Dec['info']['source'] == $Sources[1])) {
            return false;
        }
        $this->Dec['info']['source'] = $Sources[0];
        ksort($this->Dec['info']);
        
        return true;
    }
    
    /**
     * Calculate the torrent's info hash
     *
     * @return bool|string hash in hexadecimal form
     */
    public function info_hash(): bool|string
    {
        if (empty($this->Dec) || !isset($this->Dec['info'])) {
            return false;
        }
        
        return sha1($this->encode(false, 'info'));
    }
}
