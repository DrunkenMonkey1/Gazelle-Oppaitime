<?php

declare(strict_types=1);

/**
 * ImageTools Class
 * Thumbnail aide, mostly
 */
class ImageTools
{
    /**
     * Determine the image URL. This takes care of the image proxy and thumbnailing.
     *
     * @param string $Url
     * @param bool   $Thumb image proxy scale profile to use
     *
     * @return string
     */
    public static function process(?string $Url = '', string|bool $Thumb = false): string
    {
        if (is_null($Url)) {
            return '';
        }
        if (preg_match('/^https:\/\/(' . SITE_DOMAIN . '|' . IMAGE_DOMAIN . ')\//', $Url) || '/' == $Url[0]) {
            if (!str_contains($Url, '?')) {
                $Url .= '?';
            }
            
            return $Url;
        }
        
        return 'https://' . IMAGE_DOMAIN . ('' !== $Thumb ? "/$Thumb/" : '/') . '?h=' . rawurlencode(base64_encode(hash_hmac('sha256',
                $Url, IMAGE_PSK, true))) . '&i=' . urlencode($Url);
    }
    
    /**
     * Checks if a link's host is (not) good, otherwise displays an error.
     *
     * @param string $Url Link to an image
     * @param bool   $ShowError
     *
     * @return bool
     */
    public static function blacklisted(string $Url, $ShowError = true): bool
    {
        $Blacklist = ['tinypic.com'];
        foreach ($Blacklist as $Value) {
            if (false !== stripos($Url, $Value)) {
                if ($ShowError) {
                    error($Value . ' is not an allowed image host. Please use a different host.');
                }
                
                return true;
            }
        }
        
        return false;
    }
}
