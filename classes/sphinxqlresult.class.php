<?php

declare(strict_types=1);

class SphinxqlResult
{
    /**
     * Create Sphinxql result object
     *
     * @param $Result
     * @param $Meta
     * @param $Errno
     * @param $Error
     */
    public function __construct(private $Result, private $Meta, public $Errno, public $Error)
    {
    }
    
    /**
     * Redirect to the Mysqli result object if a nonexistent method is called
     *
     * @param string $Name      method name
     * @param array  $Arguments arguments used in the function call
     *
     * @return whatever the parent function returns
     */
    public function __call(string $Name, array $Arguments)
    {
        return call_user_func_array([$this->Result, $Name], $Arguments);
    }
    
    /**
     * Did the query find anything?
     *
     * @return bool results were found
     */
    public function has_results(): bool
    {
        return $this->get_meta('total') > 0;
    }
    
    /**
     * Return specified portions of the current Sphinxql result object's meta data
     *
     * @param mixed $Keys scalar or array with keys to return. Default is false, which returns all meta data
     *
     * @return array|false with meta data
     */
    public function get_meta($Keys = false): array|false|string
    {
        if (null === $Keys || false === $Keys) {
            return false;
        }
        
            if (is_array($Keys)) {
                $Return = [];
                foreach ($Keys as $Key) {
                    if (!isset($this->Meta[$Key])) {
                        continue;
                    }
                    $Return[$Key] = $this->Meta[$Key];
                }
                
                return $Return;
            }
            
            return $this->Meta[$Keys] ?? false;
        
        
//        return $this->Meta;
    }
    
    /**
     * Collect and return the specified key of all results as a list
     *
     * @param string $Key key containing the desired data
     *
     * @return array  with the $Key value of all results
     */
    public function collect(string $Key): array
    {
        $Return = [];
        while ($Row = $this->fetch_array()) {
            $Return[] = $Row[$Key];
        }
        $this->data_seek(0);
        
        return $Return;
    }
    
    /**
     * Collect and return all available data for the matches optionally indexed by a specified key
     *
     * @param string $Key        key to use as indexing value
     * @param string $ResultType method to use when fetching data from the mysqli_result object. Default is MYSQLI_ASSOC
     *
     * @return array  with all available data for the matches
     */
    public function to_array(string $Key, int|string $ResultType = MYSQLI_ASSOC): array
    {
        $Return = [];
        while ($Row = $this->fetch_array($ResultType)) {
            if (false !== $Key) {
                $Return[$Row[$Key]] = $Row;
            } else {
                $Return[] = $Row;
            }
        }
        $this->data_seek(0);
        
        return $Return;
    }
    
    /**
     * Collect pairs of keys for all matches
     *
     * @param string $Key1 key to use as indexing value
     * @param string $Key2 key to use as value
     *
     * @return array  with $Key1 => $Key2 pairs for matches
     */
    public function to_pair(string $Key1, string $Key2): array
    {
        $Return = [];
        while ($Row = $this->fetch_array()) {
            $Return[$Row[$Key1]] = $Row[$Key2];
        }
        $this->data_seek(0);
        
        return $Return;
    }
    
    /**
     * Return specified portions of the current Mysqli result object's information
     *
     * @param mixed $Keys scalar or array with keys to return. Default is false, which returns all available information
     *
     * @return array with result information
     */
    public function get_result_info($Keys = false)
    {
        if (false !== $Keys) {
            if (is_array($Keys)) {
                $Return = [];
                foreach ($Keys as $Key) {
                    if (!isset($this->Result->$Key)) {
                        continue;
                    }
                    $Return[$Key] = $this->Result->$Key;
                }
                
                return $Return;
            }
            
            return $this->Result->$Keys ?? false;
        }
        
        return $this->Result;
    }
}
