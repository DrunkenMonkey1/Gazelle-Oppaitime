<?php

declare(strict_types=1);

/**
 * If we're running a 32bit PHP version, we use small objects to store ints.
 * Overhead from the function calls is small enough to not worry about
 */
class Int64
{
    public function __construct(private $Num)
    {
    }
    
    public static function make($Val): int|\Int64
    {
        return PHP_INT_SIZE === 4 ? new Int64($Val) : (int) $Val;
    }
    
    public static function get($Val)
    {
        return PHP_INT_SIZE === 4 ? $Val->Num : $Val;
    }
    
    public static function is_int($Val): bool
    {
        return is_int($Val) || (is_object($Val) && $Val instanceof \Int64);
    }
}

/**
 * The encode class is simple and straightforward. The only thing to
 * note is that empty dictionaries are represented by boolean trues
 */
class Bencode
{
    public array $Dec;
    public $Enc = null;
    /**
     * @var mixed[]
     */
    private array $DefaultKeys = [ // Get rid of everything except these keys to save some space
        'created by',
        'creation date',
        'encoding',
        'info',
        'comment'
    ];
    private $Data = null;
    
    /**
     * Encode an arbitrary array (usually one that's just been decoded)
     *
     * @param array|bool $Arg  the thing to encode
     * @param mixed      $Keys string or array with keys in the input array to encode or true to encode everything
     *
     * @return bool|string string representing the content of the input array
     */
    public function encode(array|bool $Arg = false, $Keys = false): bool|string
    {
        if (false === $Arg) {
            $Data =& $this->Dec;
        } else {
            $Data =& $Arg;
        }
        if (true === $Keys) {
            $this->Data = $Data;
        } elseif (false === $Keys) {
            $this->Data = array_intersect_key($Data, array_flip($this->DefaultKeys));
        } elseif (is_array($Keys)) {
            $this->Data = array_intersect_key($Data, array_flip($Keys));
        } else {
            $this->Data = $Data[$Keys] ?? false;
        }
        if (!$this->Data) {
            return false;
        }
        $this->Enc = $this->_benc();
        
        return $this->Enc;
    }
    
    /**
     * Internal encoding function that does the actual job
     *
     * @return bencoded string
     */
    private function _benc(): string
    {
        if (!is_array($this->Data)) {
            if (Int64::is_int($this->Data)) { // Integer
                return 'i' . Int64::get($this->Data) . 'e';
            }
            if (true === $this->Data) { // Empty dictionary
                return 'de';
            }
            
            return strlen($this->Data) . ':' . $this->Data; // String
        }
        if (empty($this->Data) || Int64::is_int(key($this->Data))) {
            $IsDict = false;
        } else {
            $IsDict = true;
            ksort($this->Data); // Dictionaries must be sorted
        }
        $Ret = $IsDict ? 'd' : 'l';
        foreach ($this->Data as $Key => $Value) {
            if ($IsDict) {
                $Ret .= strlen($Key) . ':' . $Key;
            }
            $this->Data = $Value;
            $Ret .= $this->_benc();
        }
        
        return $Ret . 'e';
    }
}
