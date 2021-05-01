<?php

declare(strict_types=1);

class Format
{
    /**
     * Torrent Labels
     * Map a common display string to a CSS class
     * Indexes are lower case
     * Note the "tl_" prefix for "torrent label"
     *
     * There are five basic types:
     * * tl_free (leech status)
     * * tl_snatched
     * * tl_reported
     * * tl_approved
     * * tl_notice (default)
     *
     * @var array Strings
     */
    private static array $TorrentLabels = [
        'default' => 'tl_notice',
        'snatched' => 'tl_snatched',
        'seeding' => 'tl_seeding',
        'leeching' => 'tl_leeching',
        
        'freeleech' => 'tl_free',
        'neutral leech' => 'tl_free tl_neutral',
        'personal freeleech' => 'tl_free tl_personal',
        
        'reported' => 'tl_reported',
        'bad tags' => 'tl_reported tl_bad_tags',
        'bad folders' => 'tl_reported tl_bad_folders',
        'bad file names' => 'tl_reported tl_bad_file_names',
        
        'uncensored' => 'tl_notice'
    ];
    
    /**
     * Shorten a string
     *
     * @param      $Str      string to cut
     * @param      $Length   int cut at length
     * @param bool $Hard     force cut at length instead of at closest word
     * @param bool $ShowDots Show dots at the end
     *
     * @return string formatted string
     */
    public static function cut_string($Str, $Length, $Hard = false, $ShowDots = true): string
    {
        if (mb_strlen($Str, 'UTF-8') > $Length) {
            if (0 == $Hard) {
                // Not hard, cut at closest word
                $CutDesc = mb_substr($Str, 0, $Length, 'UTF-8');
                $DescArr = explode(' ', $CutDesc);
                if (count($DescArr) > 1) {
                    array_pop($DescArr);
                    $CutDesc = implode(' ', $DescArr);
                }
                if ($ShowDots) {
                    $CutDesc .= '...';
                }
            } else {
                $CutDesc = mb_substr($Str, 0, $Length, 'UTF-8');
                if ($ShowDots) {
                    $CutDesc .= '...';
                }
            }
            
            return $CutDesc;
        }
        
        return $Str;
    }
    
    /**
     * Calculates and formats a ratio.
     *
     * @param int  $Dividend AKA numerator
     * @param      $Divisor
     * @param bool $Color    if true, ratio will be coloured.
     *
     * @return string|bool formatted ratio HTML
     */
    public static function get_ratio_html($Dividend, $Divisor, bool $Color = true): string|bool
    {
        $Ratio = self::get_ratio($Dividend, $Divisor);
        
        if (false === $Ratio) {
            return '--';
        }
        if ('∞' === $Ratio) {
            return '<span class="tooltip r99" title="Infinite">∞</span>';
        }
        if ($Color) {
            $Ratio = sprintf(
                '<span class="tooltip %s" title="%s">%s</span>',
                self::get_ratio_color($Ratio),
                self::get_ratio($Dividend, $Divisor, 5),
                $Ratio
            );
        }
        
        return $Ratio;
    }
    
    /**
     * Returns ratio
     *
     * @param     $Dividend
     * @param     $Divisor
     * @param int $Decimal floor to n decimals (e.g. subtract .005 to floor to 2 decimals)
     *
     * @return bool|string
     */
    public static function get_ratio($Dividend, $Divisor, int $Decimal = 2): bool|string
    {
        if (0 == (int) $Divisor && 0 == (int) $Dividend) {
            return false;
        }
        if (0 == (int) $Divisor) {
            return '∞';
        }
        
        return number_format(max($Dividend / $Divisor - (0.5 / (10 ** $Decimal)), 0), $Decimal);
    }
    
    /**
     * Gets the CSS class corresponding to a ratio
     *
     * @param $Ratio ratio to get the css class for
     *
     * @return string the CSS class corresponding to the ratio range
     */
    public static function get_ratio_color($Ratio): string
    {
        if ($Ratio < 0.1) {
            return 'r00';
        }
        if ($Ratio < 0.2) {
            return 'r01';
        }
        if ($Ratio < 0.3) {
            return 'r02';
        }
        if ($Ratio < 0.4) {
            return 'r03';
        }
        if ($Ratio < 0.5) {
            return 'r04';
        }
        if ($Ratio < 0.6) {
            return 'r05';
        }
        if ($Ratio < 0.7) {
            return 'r06';
        }
        if ($Ratio < 0.8) {
            return 'r07';
        }
        if ($Ratio < 0.9) {
            return 'r08';
        }
        if ($Ratio < 1) {
            return 'r09';
        }
        if ($Ratio < 2) {
            return 'r10';
        }
        if ($Ratio < 5) {
            return 'r20';
        }
        
        return 'r50';
    }
    
    /**
     * Finds what page we're on and gives it to us, as well as the LIMIT clause for SQL
     * Takes in $_GET['page'] as an additional input
     *
     * @param $PerPage       integer  Results to show per page
     * @param $DefaultResult integer Optional, which result's page we want if no page is specified
     *                       If this parameter is not specified, we will default to page 1
     *
     * @return array(int, string) What page we are on, and what to use in the LIMIT section of a query
     *                    e.g. "SELECT [...] LIMIT $Limit;"
     */
    public static function page_limit($PerPage, $DefaultResult = 1): array
    {
        if (!isset($_GET['page'])) {
            $Page = ceil($DefaultResult / $PerPage);
            if (0 == $Page) {
                $Page = 1;
            }
            $Limit = $PerPage;
        } else {
            if (!is_number($_GET['page'])) {
                error(0);
            }
            $Page = $_GET['page'];
            if ($Page <= 0) {
                $Page = 1;
            }
            $Limit = $PerPage * $Page - $PerPage . ", $PerPage";
        }
        
        return [$Page, $Limit];
    }
    
    /**
     * @param     $Page
     * @param     $PerPage
     * @param int $CatalogueSize
     *
     * @return array
     */
    public static function catalogue_limit($Page, $PerPage, $CatalogueSize = 500): array
    {
        $CatalogueID = floor(($PerPage * $Page - $PerPage) / $CatalogueSize);
        $CatalogueLimit = ($CatalogueID * $CatalogueSize) . ", $CatalogueSize";
        
        return [$CatalogueID, $CatalogueLimit];
    }
    
    // A9 magic. Some other poor soul can write the phpdoc.
    // For data stored in memcached catalogues (giant arrays), e.g. forum threads
    
    /**
     * @return mixed[]
     */
    public static function catalogue_select($Catalogue, $Page, $PerPage, $CatalogueSize = 500): array
    {
        return array_slice($Catalogue, (($PerPage * $Page - $PerPage) % $CatalogueSize), $PerPage, true);
    }
    
    /**
     * @param        $StartPage
     * @param        $TotalRecords
     * @param        $ItemsPerPage
     * @param int    $ShowPages
     * @param string $Anchor
     *
     * @return string|void
     */
    public static function get_pages($StartPage, $TotalRecords, $ItemsPerPage, $ShowPages = 11, $Anchor = '')
    {
        global $Document, $Method, $Mobile;
        $Location = "$Document.php";
        $StartPage = ceil($StartPage);
        $TotalPages = 0;
        if ($TotalRecords > 0) {
            $StartPage = min($StartPage, ceil($TotalRecords / $ItemsPerPage));
            
            $ShowPages--;
            $TotalPages = ceil($TotalRecords / $ItemsPerPage);
            
            if ($TotalPages > $ShowPages) {
                $StartPosition = $StartPage - round($ShowPages / 2);
                
                if ($StartPosition <= 0) {
                    $StartPosition = 1;
                } elseif ($StartPosition >= ($TotalPages - $ShowPages)) {
                    $StartPosition = $TotalPages - $ShowPages;
                }
                
                $StopPage = $ShowPages + $StartPosition;
            } else {
                $StopPage = $TotalPages;
                $StartPosition = 1;
            }
            
            $StartPosition = max($StartPosition, 1);
            
            $QueryString = self::get_url(['page', 'post']);
            if ('' != $QueryString) {
                $QueryString = "&amp;$QueryString";
            }
            
            $Pages = '';
            
            if ($StartPage > 1) {
                $Pages .= "<a href=\"$Location?page=1$QueryString$Anchor\"><strong>&lt;&lt; First</strong></a> ";
                $Pages .= "<a href=\"$Location?page=" . ($StartPage - 1) . $QueryString . $Anchor . '" class="pager_prev"><strong>&lt; Prev</strong></a> | ';
            }
            //End change
            
            if (!$Mobile) {
                for ($i = $StartPosition; $i <= $StopPage; $i++) {
                    if ($i != $StartPage) {
                        $Pages .= "<a href=\"$Location?page=$i$QueryString$Anchor\">";
                    }
                    $Pages .= '<strong>';
                    if ($i * $ItemsPerPage > $TotalRecords) {
                        $Pages .= ((($i - 1) * $ItemsPerPage) + 1) . "-$TotalRecords";
                    } else {
                        $Pages .= ((($i - 1) * $ItemsPerPage) + 1) . '-' . ($i * $ItemsPerPage);
                    }
                    
                    $Pages .= '</strong>';
                    if ($i != $StartPage) {
                        $Pages .= '</a>';
                    }
                    if ($i < $StopPage) {
                        $Pages .= ' | ';
                    }
                }
            } else {
                $Pages .= $StartPage;
            }
            
            if ($StartPage && $StartPage < $TotalPages) {
                $Pages .= " | <a href=\"$Location?page=" . ($StartPage + 1) . $QueryString . $Anchor . '" class="pager_next"><strong>Next &gt;</strong></a> ';
                $Pages .= "<a href=\"$Location?page=$TotalPages$QueryString$Anchor\"><strong> Last &gt;&gt;</strong></a>";
            }
        }
        if ($TotalPages > 1) {
            return $Pages;
        }
    }
    
    
    /* Get pages
     * Returns a page list, given certain information about the pages.
     *
     * @param int $StartPage: The current record the page you're on starts with.
     *    e.g. if you're on page 2 of a forum thread with 25 posts per page, $StartPage is 25.
     *    If you're on page 1, $StartPage is 0.
     * @param int $TotalRecords: The total number of records in the result set.
     *    e.g. if you're on a forum thread with 152 posts, $TotalRecords is 152.
     * @param int $ItemsPerPage: Self-explanatory. The number of records shown on each page
     *    e.g. if there are 25 posts per forum page, $ItemsPerPage is 25.
     * @param int $ShowPages: The number of page links that are shown.
     *    e.g. If there are 20 pages that exist, but $ShowPages is only 11, only 11 links will be shown.
     * @param string $Anchor A URL fragment to attach to the links.
     *    e.g. '#comment12'
     * @return A sanitized HTML page listing.
     */
    
    /**
     * Gets the query string of the current page, minus the parameters in $Exclude
     *
     * @param bool|array $Exclude Query string parameters to leave out, or blank to include all parameters.
     * @param bool       $Escape  Whether to return a string prepared for HTML output
     * @param bool       $Sort    Whether to sort the parameters by key
     *
     * @return mixed optionally HTML sanatized query string
     */
    public static function get_url(bool|array $Exclude = false, bool $Escape = true, bool $Sort = false): mixed
    {
        if (false !== $Exclude) {
            $Separator = $Escape ? '&amp;' : '&';
            $QueryItems = null;
            parse_str($_SERVER['QUERY_STRING'], $QueryItems);
            foreach ($Exclude as $Key) {
                unset($QueryItems[$Key]);
            }
            if ($Sort) {
                ksort($QueryItems);
            }
            
            return http_build_query($QueryItems, '', $Separator);
        }
        
        return $Escape ? display_str($_SERVER['QUERY_STRING']) : $_SERVER['QUERY_STRING'];
    }
    
    /**
     * Format a size in bytes as a human readable string in KiB/MiB/...
     * Note: KiB, MiB, etc. are the IEC units, which are in base 2.
     * KB, MB are the SI units, which are in base 10.
     *
     * @param     $Size
     * @param int $Levels Number of decimal places. Defaults to 2, unless the size >= 1TB, in which case it defaults to
     *                    4.
     *
     * @return string formatted number.
     */
    public static function get_size($Size, int $Levels = 2): string
    {
        $Units = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB'];
        $Size = (double) $Size;
        $UnitsCount = count($Units);
        for ($Steps = 0; abs($Size) >= 1024 && $Steps < $UnitsCount; $Size /= 1024, $Steps++) {
        }
        if (1 == func_num_args() && $Steps >= 4) {
            $Levels++;
        }
        
        return number_format($Size, $Levels) . ' ' . $Units[$Steps];
    }
    
    
    /**
     * Format a number as a multiple of its highest power of 1000 (e.g. 10035 -> '10.04k')
     *
     * @param int $Number
     *
     * @return float|string formatted number.
     */
    public static function human_format(int $Number): float|string
    {
        $Steps = 0;
        while ($Number >= 1000) {
            $Steps++;
            $Number /= 1000;
        }
        
        return match ($Steps) {
            0 => round($Number),
            1 => round($Number, 2) . 'k',
            2 => round($Number, 2) . 'M',
            3 => round($Number, 2) . 'G',
            4 => round($Number, 2) . 'T',
            5 => round($Number, 2) . 'P',
            default => round($Number, 2) . 'E + ' . $Steps * 3,
        };
    }
    
    
    /**
     * Given a formatted string of a size, get the number of bytes it represents.
     *
     * @param string $Size formatted size string, e.g. 123.45k
     *
     * @return int|float of bytes it represents, e.g. (123.45 * 1024)
     */
    public static function get_bytes(string $Size): int|float
    {
        [$Value, $Unit] = sscanf($Size, "%f%s");
        $Unit = ltrim($Unit);
        if (empty($Unit)) {
            return $Value ? round($Value) : 0;
        }
        
        return match (strtolower($Unit[0])) {
            'k' => round($Value * 1024),
            'm' => round($Value * 1_048_576),
            'g' => round($Value * 1_073_741_824),
            't' => round($Value * 1_099_511_627_776),
            default => 0,
        };
    }
    
    
    /**
     * Reverse the effects of display_str - un-sanitize HTML.
     * Use sparingly.
     *
     * @param string $Str the string to unsanitize
     *
     * @return  string  unsanitized
     */
    // Use sparingly
    public static function undisplay_str(string $Str): string
    {
        return mb_convert_encoding($Str, 'UTF-8', 'HTML-ENTITIES');
    }
    
    
    /**
     * Echo data sent in a GET form field, useful for text areas.
     *
     * @param string $Index  the name of the form field
     * @param bool   $Return if set to true, value is returned instead of echoed.
     *
     * @return string|null value of field index if $Return == true
     */
    public static function form(string $Index, bool $Return = false)
    {
        if (!empty($_GET[$Index])) {
            if ($Return) {
                return display_str($_GET[$Index]);
            }
            
            echo display_str($_GET[$Index]);
        }
    }
    
    
    /**
     * Convenience function to echo out selected="selected" and checked="checked" so you don't have to.
     *
     * @param string $Name      the name of the option in the select (or field in $Array)
     * @param mixed  $Value     the value that the option must be for the option to be marked as selected or checked
     * @param string $Attribute The value returned/echoed is $Attribute="$Attribute" with a leading space
     * @param array  $Array     The array the option is in, defaults to GET.
     */
    public static function selected(string $Name, $Value, string $Attribute = 'selected', array $Array = []): void
    {
        if (empty($Array)) {
            $Array = $_GET;
        }
        if (isset($Array[$Name]) && '' !== $Array[$Name] && $Array[$Name] == $Value) {
            echo " $Attribute=\"$Attribute\"";
        }
    }
    
    /**
     * Return a CSS class name if certain conditions are met. Mainly useful to mark links as 'active'
     *
     * @param mixed       $Target       The variable to compare all values against
     * @param mixed       $Tests        The condition values. Type and dimension determines test type
     *                                  Scalar: $Tests must be equal to $Target for a match
     *                                  Vector: All elements in $Tests must correspond to equal values in $Target
     *                                  2-dimensional array: At least one array must be identical to $Target
     * @param string      $ClassName    CSS class name to return
     * @param bool        $AddAttribute Whether to include the "class" attribute in the output
     * @param string|bool $UserIDKey    Key in _REQUEST for a user ID parameter, which if given will be compared to
     *                                  G::$LoggedUser[ID]
     *
     * @return string name on match, otherwise an empty string
     */
    public static function add_class(
        mixed $Target,
        mixed $Tests,
        string $ClassName,
        bool $AddAttribute,
        string|bool $UserIDKey = false
    ): string {
        if ($UserIDKey && isset($_REQUEST[$UserIDKey]) && G::$LoggedUser['ID'] != $_REQUEST[$UserIDKey]) {
            return '';
        }
        $Pass = true;
        if (!is_array($Tests)) {
            // Scalars are nice and easy
            $Pass = $Tests === $Target;
        } elseif (!is_array($Tests[0])) {
            // Test all values in vectors
            foreach ($Tests as $Type => $Part) {
                if (!isset($Target[$Type]) || $Target[$Type] !== $Part) {
                    $Pass = false;
                    break;
                }
            }
        } else {
            // Loop to the end of the array or until we find a matching test
            foreach ($Tests as $Test) {
                $Pass = true;
                // If $Pass remains true after this test, it's a match
                foreach ($Test as $Type => $Part) {
                    if (!isset($Target[$Type]) || $Target[$Type] !== $Part) {
                        $Pass = false;
                        break;
                    }
                }
                if ($Pass) {
                    break;
                }
            }
        }
        if (!$Pass) {
            return '';
        }
        if ($AddAttribute) {
            return " class=\"$ClassName\"";
        }
        
        return " $ClassName";
    }
    
    
    /**
     * Detect the encoding of a string and transform it to UTF-8.
     *
     * @param string $Str
     *
     * @return string|void encoded version of $Str
     */
    public static function make_utf8(string $Str)
    {
        if ('' != $Str) {
            if (self::is_utf8($Str)) {
                $Encoding = 'UTF-8';
            }
            if (empty($Encoding)) {
                $Encoding = mb_detect_encoding($Str, 'UTF-8, ISO-8859-1', true);
            }
            if (empty($Encoding)) {
                $Encoding = 'ISO-8859-1';
            }
            if ('UTF-8' == $Encoding) {
                return $Str;
            }
            
            return @mb_convert_encoding($Str, 'UTF-8', $Encoding);
        }
    }
    
    /**
     * Magical function.
     *
     * @param string $Str function to detect encoding on.
     *
     * @return int|bool if the string is in UTF-8.
     */
    public static function is_utf8(string $Str): int|bool
    {
        return preg_match(
            '%^(?:
      [\x09\x0A\x0D\x20-\x7E]              // ASCII
      | [\xC2-\xDF][\x80-\xBF]             // non-overlong 2-byte
      | \xE0[\xA0-\xBF][\x80-\xBF]         // excluding overlongs
      | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}  // straight 3-byte
      | \xED[\x80-\x9F][\x80-\xBF]         // excluding surrogates
      | \xF0[\x90-\xBF][\x80-\xBF]{2}      // planes 1-3
      | [\xF1-\xF3][\x80-\xBF]{3}          // planes 4-15
      | \xF4[\x80-\x8F][\x80-\xBF]{2}      // plane 16
      )*$%xs',
            $Str
        );
    }
    
    /**
     * Creates a strong element that notes the torrent's state.
     * E.g.: snatched/freeleech/neutral leech/reported
     *
     * The CSS class is inferred using find_torrent_label_class($Text)
     *
     * @param string $Text  Display text
     * @param string $Class Custom CSS class
     *
     * @return string <strong> element
     */
    public static function torrent_label(string $Text, string $Class = ''): string
    {
        if (empty($Class)) {
            $Class = self::find_torrent_label_class($Text);
        }
        
        return sprintf(
            '<strong class="torrent_label tooltip %1$s" title="%2$s" style="white-space: nowrap;">%2$s</strong>',
            display_str($Class),
            display_str($Text)
        );
    }
    
    /**
     * Modified accessor for the $TorrentLabels array
     *
     * Converts $Text to lowercase and strips non-word characters
     *
     * @param string $Text Search string
     *
     * @return mixed|void CSS class(es)
     */
    public static function find_torrent_label_class(string $Text)
    {
        $Index = mb_eregi_replace('(?:[^\w\d\s]+)', '', strtolower($Text));
        
        return self::$TorrentLabels[$Index] ?? self::$TorrentLabels['default'];
    }
    
    /**
     * Formats a CSS class name from a Category ID
     *
     * @param int|string $CategoryID This number will be subtracted by one
     *
     * @global array     $Categories
     */
    public static function css_category( $CategoryID = 1): string
    {
        global $Categories;
    
        if (is_null($CategoryID)) {
            $CategoryID = 1;
        }
        return 'cats_' . strtolower(str_replace(
                ['-', ' '],
                '',
                $Categories[$CategoryID - 1]
            ));
    }
    
    /**
     * Formats a CSS class name from a Category ID
     *
     * @param int|string $CategoryID This number will be subtracted by one
     *
     * @return string
     * @global array     $Categories
     */
    public static function pretty_category($CategoryID = 1): string
    {
        global $Categories;
        if (is_null($CategoryID)) {
            $CategoryID = 1;
        }
        
        return ucwords(str_replace('-', ' ', $Categories[$CategoryID - 1]));
    }
}
