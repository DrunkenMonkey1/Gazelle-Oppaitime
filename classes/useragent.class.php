<?php

declare(strict_types=1);

class UserAgent
{
    /**
     * @var mixed[]
     */
    private static array $Browsers = [
        //Less popular
        'Shiira' => 'Shiira',
        'Songbird' => 'Songbird',
        'SeaMonkey' => 'SeaMonkey',
        'OmniWeb' => 'OmniWeb',
        'Camino' => 'Camino',
        'Chimera' => 'Chimera',
        'Epiphany' => 'Epiphany',
        'Konqueror' => 'Konqueror',
        'Iceweasel' => 'Iceweasel',
        'Lynx' => 'Lynx',
        'Links' => 'Links',
        'libcurl' => 'cURL',
        'midori' => 'Midori',
        'Blackberry' => 'BlackBerry Browser',
        //Big names
        'Firefox' => 'Firefox',
        'OPR' => 'Opera Blink', # Opera 15+ (the version running Blink)
        'Opera' => 'Opera',
        'Chrome' => 'Chrome',
        'Safari' => 'Safari',
        //Put Chrome Frame above IE
        'chromeframe' => 'Chrome Frame',
        'x-clock' => 'Chrome Frame',
        'MSIE' => 'Internet Explorer',
        'Trident' => 'Internet Explorer',
        //Firefox versions
        'Shiretoko' => 'Firefox (Experimental)',
        'Minefield' => 'Firefox (Experimental)',
        'GranParadiso' => 'Firefox (Experimental)',
        'Namoroka' => 'Firefox (Experimental)',
        'AppleWebKit' => 'WebKit',
        'Mozilla' => 'Mozilla'
        //Weird shit
        /*
    'WWW-Mechanize' => 'Perl',
    'Wget'          => 'Wget',
    'BTWebClient'   => 'µTorrent',
    'Transmission'  => 'Transmission',
    'Java'          => 'Java',
    'RSS'           => 'RSS Downloader'
    */
    ];
    
    /**
     * @var mixed[]
     */
    private static array $OperatingSystems = [
        //Mobile
        'SymbianOS' => 'Symbian',
        'blackberry' => 'BlackBerry',
        'iphone' => 'iPhone',
        'ipod' => 'iPhone',
        'android' => 'Android',
        'palm' => 'Palm',
        'mot-razr' => 'Motorola Razr',
        //'tablet PC'    => 'Windows RT',
        //'ARM; Trident' => 'Windows RT',
        //Windows
        'Windows NT 10.0' => 'Windows 10',
        'Windows NT 6.4' => 'Windows 10',
        'Windows NT 6.3' => 'Windows 8.1',
        'Windows 8.1' => 'Windows 8.1',
        'Windows NT 6.2' => 'Windows 8',
        'Windows 8' => 'Windows 8',
        'Windows NT 6.1' => 'Windows 7',
        'Windows 7' => 'Windows 7',
        'Windows NT 6.0' => 'Windows Vista',
        'Windows Vista' => 'Windows Vista',
        'windows nt 5.2' => 'Windows 2003',
        'windows 2003' => 'Windows 2003',
        'windows nt 5.0' => 'Windows 2000',
        'windows 2000' => 'Windows 2000',
        'windows nt 5.1' => 'Windows XP',
        'windows xp' => 'Windows XP',
        'Win 9x 4.90' => 'Windows ME',
        'Windows Me' => 'Windows ME',
        'windows nt' => 'Windows NT',
        'winnt' => 'Windows NT',
        'windows 98' => 'Windows 98',
        'windows ce' => 'Windows CE',
        'win98' => 'Windows 98',
        'windows 95' => 'Windows 95',
        'windows 95' => 'Windows 95',
        'win95' => 'Windows 95',
        'win16' => 'Windows 3.1',
        //'windows'         => 'Windows',
        'cros' => 'Chrome OS',
        //OS X
        'os x' => 'Mac OS X',
        'macintosh' => 'Mac OS X',
        'darwin' => 'Mac OS X',
        //Less popular
        'ubuntu' => 'Ubuntu',
        'debian' => 'Debian',
        'fedora' => 'Fedora',
        'freebsd' => 'FreeBSD',
        'openbsd' => 'OpenBSD',
        'bsd' => 'BSD',
        'x11' => 'Linux',
        'gnu' => 'Linux',
        'linux' => 'Linux',
        'unix' => 'Unix',
        'Sun OS' => 'Sun',
        'Sun' => 'Sun',
        //Weird shit
        /*
    'WWW-Mechanize' => 'Perl',
    'Wget'          => 'Wget',
    'BTWebClient'   => 'µTorrent',
    'Transmission'  => 'Transmission',
    'Java'          => 'Java',
    'RSS'           => 'RSS Downloader',
    */
        //Catch-all
        'win' => 'Windows',
        'mac' => 'Mac OS X'
    ];
    
    /**
     * @param $UserAgentString
     *
     * @return string|mixed
     */
    public static function operating_system(&$UserAgentString)
    {
        if (empty($UserAgentString)) {
            return 'Hidden';
        }
        foreach (self::$OperatingSystems as $String => $OperatingSystem) {
            if (false !== stripos($UserAgentString, $String)) {
                return $OperatingSystem;
            }
        }
        
        return 'Unknown';
    }
    
    /**
     * @return string|mixed
     */
    public static function browser(&$UserAgentString)
    {
        if (empty($UserAgentString)) {
            return 'Hidden';
        }
        $Return = 'Unknown';
        foreach (self::$Browsers as $String => $Browser) {
            if (str_contains($UserAgentString, $String)) {
                $Return = $Browser;
                break;
            }
        }
        if (self::mobile($UserAgentString)) {
            $Return .= ' Mobile';
        }
        
        return $Return;
    }
    
    public static function mobile(&$UserAgentString): bool
    {
        if (str_contains($UserAgentString, 'iPad')) {
            return false;
        }
        
        // "Mobi" catches "Mobile" too
        return strpos($UserAgentString, 'Device') || strpos($UserAgentString, 'Mobi') || strpos($UserAgentString,
                'Mini') || strpos($UserAgentString, 'webOS');
    }
}
