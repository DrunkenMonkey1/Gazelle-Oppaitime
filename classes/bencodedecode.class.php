<?php

declare(strict_types=1);

/**
 * The decode class is simple and straightforward. The only thing to
 * note is that empty dictionaries are represented by boolean trues
 */
class BencodeDecode extends Bencode
{
    public const SnipLength = 40;
    /**
     * @var mixed[]
     */
    public array $Dec = [];
    public bool $ExitOnError = true;
    /**
     * @var string|bool|null
     */
    private null|bool|string $Data;
    private ?int $Length = null;
    private int $Pos = 0;
    
    /**
     * Decode prepararations
     *
     * @param bool $Arg    bencoded string or path to bencoded file to decode
     * @param bool $IsPath needs to be true if $Arg is a path
     * @param bool $Strict
     */
    public function __construct($Arg = false, $IsPath = false, $Strict = true)
    {
        if (!$Strict) {
            $this->ExitOnError = false;
        }
        if (false === $Arg) {
            if (empty($this->Enc)) {
                return false;
            }
        } else {
            if ($IsPath) {
                return $this->bdec_file($Arg);
            }
            $this->Data = $Arg;
        }
        
        return $this->decode();
    }
    
    /**
     * Decodes a bencoded file
     *
     * @param bool $Path path to bencoded file to decode
     *
     * @return bool|\decoded data with a suitable structure
     */
    public function bdec_file($Path = false)
    {
        if (empty($Path)) {
            return false;
        }
        if (!$this->Data = @file_get_contents($Path, (bool) FILE_BINARY)) {
            return $this->error("Error: file '$Path' could not be opened.\n");
        }
        
        return $this->decode();
    }
    
    /**
     * Display an error and halt the operation unless the $ExitOnError property is false
     *
     * @param string|bool $ErrMsg the error message to display
     *
     * @return bool
     */
    private function error(string|bool $ErrMsg = false): bool
    {
        static $ErrorPos;
        if ($this->Pos === $ErrorPos) {
            // The recursive nature of the class requires this to avoid duplicate error messages
            return false;
        }
        if ($this->ExitOnError) {
            if (false === $ErrMsg) {
                printf(
                    "Malformed string. Invalid character at pos 0x%X: %s\n",
                    $this->Pos,
                    str_replace(["\r", "\n"], ['', ' '],
                        htmlentities(substr($this->Data, $this->Pos, self::SnipLength)))
                );
            } else {
                echo $ErrMsg;
            }
            exit();
        }
        $ErrorPos = $this->Pos;
        
        return false;
    }
    
    /**
     * Decodes a string with bencoded data
     *
     * @param mixed $Arg bencoded data or false to decode the content of $this->Data
     *
     * @return bool|\decoded|array data with a suitable structure
     */
    public function decode($Arg = false)
    {
        if (false !== $Arg) {
            $this->Data = $Arg;
        } elseif (!$this->Data) {
            $this->Data = $this->Enc;
        }
        if (!$this->Data) {
            return false;
        }
        $this->Length = strlen($this->Data);
        $this->Pos = 0;
        $this->Dec = $this->_bdec();
        if ($this->Pos < $this->Length) {
            // Not really necessary, but if the torrent is invalid, it's better to warn than to silently truncate it
            return $this->error();
        }
        
        return $this->Dec;
    }
    
    /**
     * Internal decoding function that does the actual job
     *
     * @return decoded data with a suitable structure
     */
    private function _bdec()
    {
        switch ($this->Data[$this->Pos]) {
            case 'i':
                $this->Pos++;
                $Value = substr($this->Data, $this->Pos, strpos($this->Data, 'e', $this->Pos) - $this->Pos);
                if (!ctype_digit($Value) && !('-' == $Value[0] && ctype_digit(substr($Value, 1)))) {
                    return $this->error();
                }
                $this->Pos += strlen($Value) + 1;
                
                return Int64::make($Value);
            
            case 'l':
                $Value = [];
                $this->Pos++;
                while ('e' != $this->Data[$this->Pos]) {
                    if ($this->Pos >= $this->Length) {
                        return $this->error();
                    }
                    $Value[] = $this->_bdec();
                }
                $this->Pos++;
                
                return $Value;
            
            case 'd':
                $Value = [];
                $this->Pos++;
                while ('e' != $this->Data[$this->Pos]) {
                    $Length = substr($this->Data, $this->Pos, strpos($this->Data, ':', $this->Pos) - $this->Pos);
                    if (!ctype_digit($Length)) {
                        return $this->error();
                    }
                    $this->Pos += strlen($Length) + $Length + 1;
                    $Key = substr($this->Data, $this->Pos - $Length, (int) $Length);
                    if ($this->Pos >= $this->Length) {
                        return $this->error();
                    }
                    $Value[$Key] = $this->_bdec();
                }
                $this->Pos++;
                
                // Use boolean true to keep track of empty dictionaries
                return empty($Value) ? true : $Value;
            
            default:
                $Length = substr($this->Data, $this->Pos, strpos($this->Data, ':', $this->Pos) - $this->Pos);
                if (!ctype_digit($Length)) {
                    return $this->error(); // Even if the string is likely to be decoded correctly without this check, it's malformed
                }
                $this->Pos += strlen($Length) + $Length + 1;
                
                return substr($this->Data, $this->Pos - $Length, (int) $Length);
        }
    }
    
    /**
     * Convert everything to the correct data types and optionally escape strings
     *
     * @param bool  $Escape whether to escape the textual data
     * @param mixed $Data   decoded data or false to use the $Dec property
     *
     * @return mixed|mixed[]|\decoded[] data with more useful data types
     */
    public function dump(bool $Escape = true, $Data = false)
    {
        if (false === $Data) {
            $Data = $this->Dec;
        }
        if (Int64::is_int($Data)) {
            return Int64::get($Data);
        }
        if (is_bool($Data)) {
            return [];
        }
        if (is_array($Data)) {
            $Output = [];
            foreach ($Data as $Key => $Val) {
                $Output[$Key] = $this->dump($Escape, $Val);
            }
            
            return $Output;
        }
        
        return $Escape ? htmlentities($Data) : $Data;
    }
}
