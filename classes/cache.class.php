<?php

declare(strict_types=1);
/*************************************************************************|
 * |--------------- Caching class -------------------------------------------|
 * |*************************************************************************|
 *
 * This class is a wrapper for the Memcache class, and it's been written in
 * order to better handle the caching of full pages with bits of dynamic
 * content that are different for every user.
 *
 * As this inherits memcache, all of the default memcache methods work -
 * however, this class has page caching functions superior to those of
 * memcache.
 *
 * Also, Memcache::get and Memcache::set have been wrapped by
 * Cache::get_value and Cache::cache_value. get_value uses the same argument
 * as get, but cache_value only takes the key, the value, and the duration
 * (no zlib).
 *
 * // Unix sockets
 * memcached -d -m 5120 -s /var/run/memcached.sock -a 0777 -t16 -C -u root
 *
 * // TCP bind
 * memcached -d -m 8192 -l 10.10.0.1 -t8 -C
 *
 * |*************************************************************************/

if (!extension_loaded('memcache') && !extension_loaded('memcached')) {
    die('Memcache Extension not loaded.');
}

class Cache extends Memcached
{
    // Torrent Group cache version
    public const GROUP_VERSION = 5;
    
    /**
     * @var mixed[]
     */
    public array $CacheHits = [];
    /**
     * @var mixed[]
     */
    public array $MemcacheDBArray = [];
    public string|array $MemcacheDBKey = '';
    public int|float $Time = 0;
    public bool $CanClear = false;
    public bool $InternalCache = true;
    protected bool $InTransaction = false;
    /**
     * @var mixed[]
     */
    private array $PersistentKeys = [
        'ajax_requests_*',
        'query_lock_*',
        'stats_*',
        'top10tor_*',
        'users_snatched_*',
        
        // Cache-based features
        'global_notification',
        'notifications_one_reads_*',
    ];
    /**
     * @var mixed[]
     */
    private array $ClearedKeys = [];
    
    public function __construct(private $Servers)
    {
        parent::__construct();
        foreach ($Servers as $Server) {
            $this->addServer(str_replace('unix://', '', $Server['host']), $Server['port'], $Server['buckets']);
        }
    }
    
    //---------- Caching functions ----------//
    
    // Allows us to set an expiration on otherwise perminantly cache'd values
    // Useful for disabled users, locked threads, basically reducing ram usage
    public function expire_value($Key, $Duration = 2_592_000): void
    {
        $StartTime = microtime(true);
        $this->set($Key, $this->get($Key), $Duration);
        $this->Time += (microtime(true) - $StartTime) * 1000;
    }
    
    // Wrapper for Memcache::set, with the zlib option removed and default duration of 30 days
    
    public function replace_value($Key, $Value, $Duration = 2_592_000): void
    {
        $StartTime = microtime(true);
        $ReplaceParams = [$Key, $Value, $Duration];
        
        $this->replace(...$ReplaceParams);
        if ($this->InternalCache && array_key_exists($Key, $this->CacheHits)) {
            $this->CacheHits[$Key] = $Value;
        }
        $this->Time += (microtime(true) - $StartTime) * 1000;
    }
    
    // Wrapper for Memcache::add, with the zlib option removed and default duration of 30 days
    
    /**
     * @return bool|mixed
     */
    public function get_value($Key, $NoCache = false): mixed
    {
        if (!$this->InternalCache) {
            $NoCache = true;
        }
        $StartTime = microtime(true);
        if (empty($Key)) {
            trigger_error('Cache retrieval failed for empty key');
        }
        
        if (!empty($_GET['clearcache']) && $this->CanClear && !isset($this->ClearedKeys[$Key]) && !Misc::in_array_partial($Key,
                $this->PersistentKeys)) {
            if ('1' === $_GET['clearcache']) {
                // Because check_perms() isn't true until LoggedUser is pulled from the cache, we have to remove the entries loaded before the LoggedUser data
                // Because of this, not user cache data will require a secondary pageload following the clearcache to update
                if (count($this->CacheHits) > 0) {
                    foreach (array_keys($this->CacheHits) as $HitKey) {
                        if (!isset($this->ClearedKeys[$HitKey]) && !Misc::in_array_partial($HitKey,
                                $this->PersistentKeys)) {
                            $this->delete($HitKey);
                            unset($this->CacheHits[$HitKey]);
                            $this->ClearedKeys[$HitKey] = true;
                        }
                    }
                }
                $this->delete($Key);
                $this->Time += (microtime(true) - $StartTime) * 1000;
                
                return false;
            } elseif ($_GET['clearcache'] == $Key) {
                $this->delete($Key);
                $this->Time += (microtime(true) - $StartTime) * 1000;
                
                return false;
            } elseif ('*' === substr($_GET['clearcache'], -1)) {
                $Prefix = substr($_GET['clearcache'], 0, -1);
                if ('' === $Prefix || str_starts_with($Key, $Prefix)) {
                    $this->delete($Key);
                    $this->Time += (microtime(true) - $StartTime) * 1000;
                    
                    return false;
                }
            }
            $this->ClearedKeys[$Key] = true;
        }
        
        // For cases like the forums, if a key is already loaded, grab the existing pointer
        if (isset($this->CacheHits[$Key]) && !$NoCache) {
            $this->Time += (microtime(true) - $StartTime) * 1000;
            
            return $this->CacheHits[$Key];
        }
        
        $Return = $this->get($Key);
        if (false !== $Return) {
            $this->CacheHits[$Key] = $NoCache ? null : $Return;
        }
        $this->Time += (microtime(true) - $StartTime) * 1000;
        
        return $Return;
    }
    
    public function increment_value($Key, $Value = 1): void
    {
        $StartTime = microtime(true);
        $NewVal = $this->increment($Key, $Value);
        if (isset($this->CacheHits[$Key])) {
            $this->CacheHits[$Key] = $NewVal;
        }
        $this->Time += (microtime(true) - $StartTime) * 1000;
    }
    
    public function decrement_value($Key, $Value = 1): void
    {
        $StartTime = microtime(true);
        $NewVal = $this->decrement($Key, $Value);
        if (isset($this->CacheHits[$Key])) {
            $this->CacheHits[$Key] = $NewVal;
        }
        $this->Time += (microtime(true) - $StartTime) * 1000;
    }
    
    // Wrapper for Memcache::delete. For a reason, see above.
    
    public function cancel_transaction(): void
    {
        $this->InTransaction = false;
        $this->MemcacheDBKey = [];
        $this->MemcacheDBKey = '';
    }
    
    /**
     * @return bool|void
     */
    public function update_row($Row, $Values)
    {
        if (!$this->InTransaction) {
            return false;
        }
        $UpdateArray = false === $Row ? $this->MemcacheDBArray : $this->MemcacheDBArray[$Row];
        foreach ($Values as $Key => $Value) {
            if (!array_key_exists($Key, $UpdateArray)) {
                trigger_error('Bad transaction key (' . $Key . ') for cache ' . $this->MemcacheDBKey);
            }
            if ('+1' === $Value) {
                if (!is_number($UpdateArray[$Key])) {
                    trigger_error('Tried to increment non-number (' . $Key . ') for cache ' . $this->MemcacheDBKey);
                }
                ++$UpdateArray[$Key]; // Increment value
            } elseif ('-1' === $Value) {
                if (!is_number($UpdateArray[$Key])) {
                    trigger_error('Tried to decrement non-number (' . $Key . ') for cache ' . $this->MemcacheDBKey);
                }
                --$UpdateArray[$Key]; // Decrement value
            } else {
                $UpdateArray[$Key] = $Value; // Otherwise, just alter value
            }
        }
        if (false === $Row) {
            $this->MemcacheDBArray = $UpdateArray;
        } else {
            $this->MemcacheDBArray[$Row] = $UpdateArray;
        }
    }
    
    /**
     * @return bool|void
     */
    public function increment_row($Row, $Values)
    {
        if (!$this->InTransaction) {
            return false;
        }
        $UpdateArray = false === $Row ? $this->MemcacheDBArray : $this->MemcacheDBArray[$Row];
        foreach ($Values as $Key => $Value) {
            if (!array_key_exists($Key, $UpdateArray)) {
                trigger_error("Bad transaction key ($Key) for cache " . $this->MemcacheDBKey);
            }
            if (!is_number($Value)) {
                trigger_error("Tried to increment with non-number ($Key) for cache " . $this->MemcacheDBKey);
            }
            $UpdateArray[$Key] += $Value; // Increment value
        }
        if (false === $Row) {
            $this->MemcacheDBArray = $UpdateArray;
        } else {
            $this->MemcacheDBArray[$Row] = $UpdateArray;
        }
    }
    
    //---------- memcachedb functions ----------//
    
    /**
     * @return bool|void
     */
    public function insert_front($Key, $Value)
    {
        if (!$this->InTransaction) {
            return false;
        }
        if ('' === $Key) {
            array_unshift($this->MemcacheDBArray, $Value);
        } else {
            $this->MemcacheDBArray = [$Key => $Value] + $this->MemcacheDBArray;
        }
    }
    
    /**
     * @return bool|void
     */
    public function insert_back($Key, $Value)
    {
        if (!$this->InTransaction) {
            return false;
        }
        if ('' === $Key) {
            $this->MemcacheDBArray[] = $Value;
        } else {
            $this->MemcacheDBArray += [$Key => $Value];
        }
    }
    
    /**
     * @return bool|void
     */
    public function insert($Key, $Value)
    {
        if (!$this->InTransaction) {
            return false;
        }
        if ('' === $Key) {
            $this->MemcacheDBArray[] = $Value;
        } else {
            $this->MemcacheDBArray[$Key] = $Value;
        }
    }
    
    // Updates multiple rows in an array
    
    /**
     * @return bool|void
     */
    public function delete_row($Row)
    {
        if (!$this->InTransaction) {
            return false;
        }
        if (!isset($this->MemcacheDBArray[$Row])) {
            trigger_error("Tried to delete non-existent row ($Row) for cache " . $this->MemcacheDBKey);
        }
        unset($this->MemcacheDBArray[$Row]);
    }
    
    // Updates multiple values in a single row in an array
    // $Values must be an associative array with key:value pairs like in the array we're updating
    
    public function update($Key, $Rows, $Values, $Time = 2_592_000): void
    {
        if (!$this->InTransaction) {
            $this->begin_transaction($Key);
            $this->update_transaction($Rows, $Values);
            $this->commit_transaction($Time);
        } else {
            $this->update_transaction($Rows, $Values);
        }
    }
    
    // Increments multiple values in a single row in an array
    // $Values must be an associative array with key:value pairs like in the array we're updating
    
    public function begin_transaction(array|string $Key): bool
    {
        $Value = $this->get($Key);
        if (!is_array($Value)) {
            $this->InTransaction = false;
            $this->MemcacheDBKey = [];
            $this->MemcacheDBKey = '';
            
            return false;
        }
        $this->MemcacheDBArray = $Value;
        $this->MemcacheDBKey = $Key;
        $this->InTransaction = true;
        
        return true;
    }
    
    // Insert a value at the beginning of the array
    
    /**
     * @return bool|void
     */
    public function update_transaction($Rows, $Values)
    {
        if (!$this->InTransaction) {
            return false;
        }
        $Array = $this->MemcacheDBArray;
        if (is_array($Rows)) {
            $i = 0;
            $Keys = $Rows[0];
            $Property = $Rows[1];
            foreach ($Keys as $Row) {
                $Array[$Row][$Property] = $Values[$i];
                $i++;
            }
        } else {
            $Array[$Rows] = $Values;
        }
        $this->MemcacheDBArray = $Array;
    }
    
    // Insert a value at the end of the array
    
    /**
     * @return bool|void
     */
    public function commit_transaction($Time = 2_592_000)
    {
        if (!$this->InTransaction) {
            return false;
        }
        $this->cache_value($this->MemcacheDBKey, $this->MemcacheDBArray, $Time);
        $this->InTransaction = false;
    }
    
    public function cache_value($Key, $Value, $Duration = 2_592_000): void
    {
        $StartTime = microtime(true);
        if (empty($Key)) {
            trigger_error("Cache insert failed for empty key");
        }
        $SetParams = [$Key, $Value, (int) $Duration];
        if (!$this->set(...$SetParams)) {
            trigger_error("Cache insert failed for key $Key");
        }
        if ($this->InternalCache && array_key_exists($Key, $this->CacheHits)) {
            $this->CacheHits[$Key] = $Value;
        }
        $this->Time += (microtime(true) - $StartTime) * 1000;
    }
    
    /**
     * Tries to set a lock. Expiry time is one hour to avoid indefinite locks
     *
     * @param string $LockName name on the lock
     *
     * @return true   if lock was acquired
     */
    public function get_query_lock(string $LockName): bool
    {
        return $this->add_value('query_lock_' . $LockName, 1, 3600);
    }
    
    public function add_value($Key, $Value, $Duration = 2_592_000): bool
    {
        $StartTime = microtime(true);
        $Added = $this->add($Key, $Value, 0);
        $this->Time += (microtime(true) - $StartTime) * 1000;
        
        return $Added;
    }
    
    /**
     * Remove lock
     *
     * @param string $LockName name on the lock
     */
    public function clear_query_lock(string $LockName): void
    {
        $this->delete_value('query_lock_' . $LockName);
    }
    
    public function delete_value($Key): void
    {
        $StartTime = microtime(true);
        if (empty($Key)) {
            trigger_error('Cache deletion failed for empty key');
        }
        if (!$this->delete($Key)) {
            //trigger_error("Cache delete failed for key $Key");
        }
        unset($this->CacheHits[$Key]);
        $this->Time += (microtime(true) - $StartTime) * 1000;
    }
    
    /**
     * Get cache server status
     *
     * @return array (host => int status, ...)
     */
    public function server_status(): array
    {
        $Status = [];
        
        $MemcachedStats = $this->getStats();
        
        foreach ($this->Servers as $Server) {
            $Status["$Server[host]:$Server[port]"] = 'array' === gettype($MemcachedStats["$Server[host]:$Server[port]"]) ? 1 : 0;
        }
        
        return $Status;
    }
}
