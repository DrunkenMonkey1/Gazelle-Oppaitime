<?php

declare(strict_types=1);

class Crypto
{
    /**
     * Encrypts input text for use in database
     *
     *
     * @param string $plaintext
     *
     * @return bool|string string or false if DB key not accessible
     */
    public static function encrypt(string $plaintext): bool|string
    {
        if (apcu_exists('DBKEY')) {
            $iv_size = openssl_cipher_iv_length('AES-128-CBC');
            $Secure = false;
            $iv = openssl_random_pseudo_bytes($iv_size, $Secure);
            if (false !== $iv) {
                return base64_encode($iv . openssl_encrypt($plaintext, 'AES-128-CBC', apcu_fetch('DBKEY'),
                        OPENSSL_RAW_DATA,
                        $iv));
            }
        }
        
        return false;
    }
    
    /**
     * Decrypts input text from database
     *
     *
     * @param string $ciphertext
     *
     * @return bool|string string string or false if DB key not accessible
     */
    public static function decrypt(?string $ciphertext): bool|string
    {
        if ( is_null($ciphertext)) {
            return false;
        }
        
        if (apcu_exists('DBKEY')) {
            $iv_size = openssl_cipher_iv_length('AES-128-CBC');
            $iv = substr(base64_decode($ciphertext, true), 0, $iv_size);
            $ciphertext = substr(base64_decode($ciphertext, true), $iv_size);
            
            return openssl_decrypt($ciphertext, 'AES-128-CBC', apcu_fetch('DBKEY'), OPENSSL_RAW_DATA, $iv);
        }
        
        return false;
    }
}
