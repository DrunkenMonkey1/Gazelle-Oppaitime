<?php

declare(strict_types=1);
/*************************************************************************|
 * |--------------- Cookie class --------------------------------------------|
 * |*************************************************************************|
 *
 * This class handles cookies.
 *
 * $Cookie->get(); is user provided and untrustworthy
 *
 * |*************************************************************************/
/*
interface COOKIE_INTERFACE {
  public function get($Key);
  public function set($Key, $Value, $Seconds, $LimitAccess);
  public function del($Key);

  public function flush();
}
*/

class COOKIE /*implements COOKIE_INTERFACE*/
{
    public const LIMIT_ACCESS = true; //If true, blocks JS cookie API access by default (can be overridden case by case)
    public const PREFIX = ''; //In some cases you may desire to prefix your cookies
    
    /**
     * @param $Key
     *
     * @return mixed
     */
    public function get($Key): mixed
    {
        return $_COOKIE[SELF::PREFIX . $Key] ?? false;
    }
    
    //Pass the 4th optional param as false to allow JS access to the cookie
    public function set($Key, $Value, $Seconds = 86400, $LimitAccess = SELF::LIMIT_ACCESS): void
    {
        setcookie(SELF::PREFIX . $Key, $Value, time() + $Seconds, '/', SITE_DOMAIN, '443' === $_SERVER['SERVER_PORT'],
            $LimitAccess, false);
    }
    
    public function flush(): void
    {
        $Cookies = array_keys($_COOKIE);
        foreach ($Cookies as $Cookie) {
            $this->del($Cookie);
        }
    }
    
    public function del($Key): void
    {
        setcookie(SELF::PREFIX . $Key, '',
            time() - 24 * 3600); //3600 vs 1 second to account for potential clock desyncs
    }
}
