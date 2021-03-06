<?php

declare(strict_types=1);

class TorrentSearch
{
    public const TAGS_ANY = 0;
    public const TAGS_ALL = 1;
    public const SPH_BOOL_AND = ' ';
    public const SPH_BOOL_OR = ' | ';
    public static array $SortOrders = [
        'year' => 'year',
        'time' => 'id',
        'size' => 'size',
        'seeders' => 'seeders',
        'leechers' => 'leechers',
        'snatched' => 'snatched',
        'cataloguenumber' => 'cataloguenumber',
        'random' => 1
    ];
    
    // Map of sort mode => attribute name for ungrouped torrent page
    private static array $SortOrdersGrouped = [
        'year' => 'year',
        'time' => 'id',
        'size' => 'maxsize',
        'seeders' => 'sumseeders',
        'leechers' => 'sumleechers',
        'snatched' => 'sumsnatched',
        'cataloguenumber' => 'cataloguenumber',
        'random' => 1
    ];
    
    // Map of sort mode => attribute name for grouped torrent page
    private static array $AggregateExp = [
        'size' => 'MAX(size) AS maxsize',
        'seeders' => 'SUM(seeders) AS sumseeders',
        'leechers' => 'SUM(leechers) AS sumleechers',
        'snatched' => 'SUM(snatched) AS sumsnatched'
    ];
    
    // Map of sort mode => aggregate expression required for some grouped sort orders
    private static array $Attributes = [
        'filter_cat' => false,
        'releasetype' => 'ReleaseTypes',
        'freetorrent' => false,
        'censored' => false,
        'size_unit' => false,
        'year' => false
    ];
    
    // Map of attribute name => global variable name with list of values that can be used for filtering
    private static array $Fields = [
        'artistname' => 1,
        'audioformat' => 1,
        'cataloguenumber' => 1,
        'codec' => 1,
        'container' => 1,
        'description' => 1,
        'dlsiteid' => 1,
        'filelist' => 1,
        'groupname' => 1,
        'advgroupname' => 1,
        'language' => 1,
        'media' => 1,
        'resolution' => 1,
        'searchstr' => 1,
        'series' => 1,
        'studio' => 1,
        'subber' => 1,
        'subbing' => 1,
        'taglist' => 1
    ];
    
    // List of fields that can be used for fulltext searches
    private static array $TorrentFields = [
        'description' => 1,
        'encoding' => 1,
        'censored' => 1,
        'language' => 1,
        'filelist' => 1,
        'format' => 1,
        'media' => 1
    ];
    
    // List of torrent-specific fields that can be used for filtering
    private static array $FormsToFields = [
        'searchstr' => '(groupname,artistname,studio,series,dlsiteid,cataloguenumber,yearfulltext)',
        'advgroupname' => '(groupname)'
    ];
    
    // Some form field names don't match the ones in the index
    private static array $FieldOperators = [
        '' => self::SPH_BOOL_AND,
        'encoding' => self::SPH_BOOL_OR,
        'format' => self::SPH_BOOL_OR,
        'media' => self::SPH_BOOL_OR
    ];
    
    // Specify the operator type to use for fields. Empty key sets the default
    private static array $FieldSeparators = [
        '' => ' ',
        'encoding' => '|',
        'format' => '|',
        'media' => '|',
        'taglist' => ','
    ];
    
    // Specify the separator character to use for fields. Empty key sets the default
    public bool $GroupResults;
    
    // Primary SphinxqlQuery object used to get group IDs or torrent IDs for ungrouped searches
    private \SphinxqlQuery $SphQL;
    // Second SphinxqlQuery object used to get torrent IDs if torrent-specific fulltext filters are used
    private ?\SphinxqlQuery $SphQLTor = null;
    // Ordered result array or false if query resulted in an error
    /**
     * @var bool|mixed[]|null
     */
    private $SphResults;
    // Requested page
    private float|int $Page;
    // Number of results per page
    private int $PageSize;
    // Number of results
    private int $NumResults = 0;
    // Array with info from all matching torrent groups
    private array $Groups = [];
    // Whether any filters were used
    private bool $Filtered = false;
    // Whether the random sort order is selected
    private bool $Random = false;
    /*
     * Storage for fulltext search terms
     * ['Field name' => [
     *     'include' => [],
     *     'exclude' => [],
     *     'operator' => self::SPH_BOOL_AND | self::SPH_BOOL_OR
     * ]], ...
     */
    private array $Terms = [];
    // Unprocessed search terms for retrieval
    private array $RawTerms = [];
    // Storage for used torrent-specific attribute filters
    // ['Field name' => 'Search expression', ...]
    private array $UsedTorrentAttrs = [];
    // Storage for used torrent-specific fulltext fields
    // ['Field name' => 'Search expression', ...]
    private array $UsedTorrentFields = [];
    
    /**
     * Initialize and configure a TorrentSearch object
     *
     * @param bool   $GroupResults whether results should be grouped by group id
     * @param string $OrderBy      attribute to use for sorting the results
     * @param string $OrderWay     Whether to use ascending or descending order
     * @param int    $Page         Page number to display
     * @param int    $PageSize     Number of results per page
     */
    public function __construct(bool $GroupResults, string $OrderBy, string $OrderWay, int $Page, $PageSize)
    {
        if (($GroupResults && !isset(self::$SortOrdersGrouped[$OrderBy]))
            || (!$GroupResults && !isset(self::$SortOrders[$OrderBy]))
            || !in_array($OrderWay, ['asc', 'desc'])
        ) {
            global $Debug;
            $ErrMsg = "TorrentSearch constructor arguments:\n" . print_r(func_get_args(), true);
            $Debug->analysis('Bad arguments in TorrentSearch constructor', $ErrMsg, 3600 * 24);
            error('-1');
        }
        if (!is_number($Page) || $Page < 1) {
            $Page = 1;
        }
        $this->Page = check_perms('site_search_many') ? $Page : min($Page, SPHINX_MAX_MATCHES / $PageSize);
        $ResultLimit = $PageSize;
        $this->PageSize = $PageSize;
        $this->GroupResults = $GroupResults;
        $this->SphQL = new SphinxqlQuery();
        $this->SphQL->where_match('_all', 'fake', false);
        if ('random' === $OrderBy) {
            $this->SphQL->select('id, groupid')
                ->order_by('RAND()', '');
            $this->Random = true;
            $this->Page = 1;
            if ($GroupResults) {
                // Get more results because ORDER BY RAND() can't be used in GROUP BY queries
                $ResultLimit *= 5;
            }
        } elseif ($GroupResults) {
            $Select = 'groupid';
            if (isset(self::$AggregateExp[$OrderBy])) {
                $Select .= ', ' . self::$AggregateExp[$OrderBy];
            }
            $this->SphQL->select($Select)
                ->group_by('groupid')
                ->order_group_by(self::$SortOrdersGrouped[$OrderBy], $OrderWay)
                ->order_by(self::$SortOrdersGrouped[$OrderBy], $OrderWay);
        } else {
            $this->SphQL->select('id, groupid')
                ->order_by(self::$SortOrders[$OrderBy], $OrderWay);
        }
        $Offset = ($this->Page - 1) * $ResultLimit;
        $MinMax = G::$Cache->get_value('sphinx_min_max_matches');
        $MaxMatches = max($Offset + $ResultLimit, $MinMax ? $MinMax : 2000);
        $this->SphQL->from('torrents, delta')
            ->limit($Offset, $ResultLimit, $MaxMatches);
    }
    
    /**
     * Process search terms and run the main query
     *
     * @param array $Terms Array containing all search terms (e.g. $_GET)
     *
     * @return array List of matching group IDs with torrent ID as key for ungrouped results
     */
    public function query(array $Terms = [])
    {
        $this->process_search_terms($Terms);
        $this->build_query();
        $this->run_query();
        $this->process_results();
        return $this->SphResults;
    }
    
    /**
     * Look at each search term and figure out what to do with it
     *
     * @param array $Terms Array with search terms from query()
     */
    private function process_search_terms(array $Terms): void
    {
        foreach ($Terms as $Key => $Term) {
            if (isset(self::$Fields[$Key])) {
                $this->process_field($Key, $Term);
            } elseif (isset(self::$Attributes[$Key])) {
                $this->process_attribute($Key, $Term);
            }
            $this->RawTerms[$Key] = $Term;
        }
        $this->post_process();
    }
    
    /**
     * Look at a fulltext search term and figure out if it needs special treatment
     *
     * @param string $Field Name of the search field
     * @param string $Term  Search expression for the field
     */
    private function process_field(string $Field, string $Term): void
    {
        $Term = trim($Term);
        if ('' === $Term) {
            return;
        }
        if ('searchstr' === $Field) {
            $this->search_basic($Term);
        } elseif ('filelist' === $Field) {
            $this->search_filelist($Term);
        } elseif ('taglist' === $Field) {
            $this->search_taglist($Term);
        } else {
            $this->add_field($Field, $Term);
        }
    }
    
    /**
     * Handle magic keywords in the basic torrent search
     *
     * @param string $Term Given search expression
     */
    private function search_basic(string $Term): void
    {
        global $Bitrates, $Formats, $Media;
        $SearchBitrates = array_map('strtolower', $Bitrates);
        $SearchBitrates[] = 'v0';
        $SearchBitrates[] = 'v1';
        $SearchBitrates[] = 'v2';
        $SearchBitrates[] = '24bit';
        $SearchFormats = array_map('strtolower', $Formats);
        $SearchMedia = array_map('strtolower', $Media);
        
        foreach (explode(' ', $Term) as $Word) {
            if (in_array($Word, $SearchBitrates)) {
                $this->add_word('encoding', $Word);
            } elseif (in_array($Word, $SearchFormats)) {
                $this->add_word('format', $Word);
            } elseif (in_array($Word, $SearchMedia)) {
                $this->add_word('media', $Word);
            } else {
                $this->add_word('searchstr', $Word);
            }
        }
    }
    
    /**
     * Add a keyword to the array of search terms
     *
     * @param string $Field Name of the search field
     * @param string $Word  Keyword
     */
    private function add_word(string $Field, string $Word): void
    {
        $Word = trim($Word);
        // Skip isolated hyphens to enable "Artist - Title" searches
        if ('' === $Word || '-' === $Word) {
            return;
        }
        if ('!' === $Word[0] && strlen($Word) >= 2 && !str_contains($Word, '!', 1)) {
            $this->Terms[$Field]['exclude'][] = $Word;
        } else {
            $this->Terms[$Field]['include'][] = $Word;
        }
    }
    
    /**
     * Use phrase boundary for file searches to make sure we don't count
     * partial hits from multiple files
     *
     * @param string $Term Given search expression
     */
    private function search_filelist(string $Term): void
    {
        $SearchString = '"' . Sphinxql::sph_escape_string($Term) . '"~20';
        $this->SphQL->where_match($SearchString, 'filelist', false);
        $this->UsedTorrentFields['filelist'] = $SearchString;
        $this->Filtered = true;
    }
    
    /**
     * Prepare tag searches before sending them to the normal treatment
     *
     * @param string $Term Given search expression
     */
    private function search_taglist(string $Term): void
    {
        $Term = strtr($Term, '.', '_');
        $this->add_field('taglist', $Term);
    }
    
    /**
     * Add a field filter that doesn't need special treatment
     *
     * @param string $Field Name of the search field
     * @param string $Term  Search expression for the field
     */
    private function add_field(string $Field, string $Term): void
    {
        $Separator = isset(self::$FieldSeparators[$Field]) ? self::$FieldSeparators[$Field] : self::$FieldSeparators[''];
        $Words = explode($Separator, $Term);
        foreach ($Words as $Word) {
            $this->add_word($Field, $Word);
        }
    }
    
    /**
     * Process attribute filters and store them in case we need to post-process grouped results
     *
     * @param string $Attribute Name of the attribute to filter against
     * @param mixed  $Value     The filter's condition for a match
     */
    private function process_attribute(string $Attribute, $Value): void
    {
        if ('' === $Value) {
            return;
        }
        
        if ('year' === $Attribute) {
            $this->search_year($Value);
        } elseif ('size_unit' === $Attribute) {
            // for the record, size_unit must appear in the GET parameters after size_min and size_max for this to work. Sorry.
            if (is_numeric($this->RawTerms['size_min']) || is_numeric($this->RawTerms['size_max'])) {
                $this->SphQL->where_between('size', [
                    (int) (($this->RawTerms['size_min'] ?? 0) * (1024 ** $Value)),
                    (int) min(PHP_INT_MAX, ($this->RawTerms['size_max'] ?? INF) * (1024 ** $Value))
                ]);
            }
        } elseif ('freetorrent' === $Attribute) {
            if (3 == $Value) {
                $this->SphQL->where('freetorrent', 0, true);
                $this->UsedTorrentAttrs['freetorrent'] = 3;
            } elseif ($Value >= 0 && $Value < 3) {
                $this->SphQL->where('freetorrent', $Value);
                $this->UsedTorrentAttrs[$Attribute] = $Value;
            }
        } elseif ('filter_cat' === $Attribute) {
            if (!is_array($Value)) {
                $Value = array_fill_keys(explode('|', $Value), 1);
            }
            $CategoryFilter = [];
            foreach (array_keys($Value) as $Category) {
                if (is_number($Category)) {
                    $CategoryFilter[] = $Category;
                } else {
                    global $Categories;
                    $ValidValues = array_map('strtolower', $Categories);
                    if (($CategoryID = array_search(strtolower($Category), $ValidValues, true)) !== false) {
                        $CategoryFilter[] = $CategoryID + 1;
                    }
                }
            }
            $this->SphQL->where('categoryid', ($CategoryFilter ?? 0));
        } else {
            if (!is_number($Value) && false !== self::$Attributes[$Attribute]) {
                // Check if the submitted value can be converted to a valid one
                $ValidValuesVarname = self::$Attributes[$Attribute];
                global $$ValidValuesVarname;
                $ValidValues = array_map('strtolower', $$ValidValuesVarname);
                if (($Value = array_search(strtolower($Value), $ValidValues, true)) === false) {
                    // Force the query to return 0 results if value is still invalid
                    $Value = max(array_keys($ValidValues)) + 1;
                }
            }
            $this->SphQL->where($Attribute, $Value);
            $this->UsedTorrentAttrs[$Attribute] = $Value;
        }
        
        $this->Filtered = true;
    }
    
    /**
     * The year filter accepts a range. Figure out how to handle the filter value
     *
     * @param string $Term Filter condition. Can be an integer or a range with the format X-Y
     *
     * @return bool   True if parameters are valid
     */
    private function search_year(string $Term): bool
    {
        $Years = explode('-', $Term);
        if (1 === count($Years) && is_number($Years[0])) {
            // Exact year
            $this->SphQL->where('year', $Years[0]);
        } elseif (2 === count($Years)) {
            if (empty($Years[0]) && is_number($Years[1])) {
                // Range: 0 - 2005
                $this->SphQL->where_lt('year', $Years[1], true);
            } elseif (empty($Years[1]) && is_number($Years[0])) {
                // Range: 2005 - 2^32-1
                $this->SphQL->where_gt('year', $Years[0], true);
            } elseif (is_number($Years[0]) && is_number($Years[1])) {
                // Range: 2005 - 2009
                $this->SphQL->where_between('year', [min($Years), max($Years)]);
            } else {
                // Invalid input
                return false;
            }
        } else {
            // Invalid input
            return false;
        }
        
        return true;
    }
    
    /**
     * Some fields may require post-processing
     */
    private function post_process(): void
    {
        if (isset($this->Terms['taglist'])) {
            // Replace bad tags with tag aliases
            $this->Terms['taglist'] = Tags::remove_aliases($this->Terms['taglist']);
            if (isset($this->RawTerms['tags_type']) && self::TAGS_ANY === (int) $this->RawTerms['tags_type']) {
                $this->Terms['taglist']['operator'] = self::SPH_BOOL_OR;
            }
            $AllTags = isset($this->Terms['taglist']['include']) ? $this->Terms['taglist']['include'] : [];
            if (isset($this->Terms['taglist']['exclude'])) {
                $AllTags = array_merge($AllTags, $this->Terms['taglist']['exclude']);
            }
            $this->RawTerms['taglist'] = str_replace('_', '.', implode(', ', $AllTags));
        }
    }
    
    /**
     * Process search terms and store the parts in appropriate arrays until we know if
     * the NOT operator can be used
     */
    private function build_query(): void
    {
        foreach ($this->Terms as $Field => $Words) {
            $SearchString = '';
            if (isset(self::$FormsToFields[$Field])) {
                $Field = self::$FormsToFields[$Field];
            }
            $QueryParts = ['include' => [], 'exclude' => []];
            if (!empty($Words['include'])) {
                foreach ($Words['include'] as $Word) {
                    $QueryParts['include'][] = Sphinxql::sph_escape_string($Word);
                }
            }
            if (!empty($Words['exclude'])) {
                foreach ($Words['exclude'] as $Word) {
                    $QueryParts['exclude'][] = '!' . Sphinxql::sph_escape_string(substr($Word, 1));
                }
            }
            if (!empty($QueryParts)) {
                if (isset($Words['operator'])) {
                    // Is the operator already specified?
                    $Operator = $Words['operator'];
                } elseif (isset(self::$FieldOperators[$Field])) {
                    // Does this field have a non-standard operator?
                    $Operator = self::$FieldOperators[$Field];
                } else {
                    // Go for the default operator
                    $Operator = self::$FieldOperators[''];
                }
                if (!empty($QueryParts['include'])) {
                    if ('taglist' == $Field) {
                        foreach ($QueryParts['include'] as $key => $Tag) {
                            $QueryParts['include'][$key] = '( ' . $Tag . ' | ' . $Tag . ':* )';
                        }
                    }
                    $SearchString .= '( ' . implode($Operator, $QueryParts['include']) . ' ) ';
                }
                if (!empty($QueryParts['exclude'])) {
                    $SearchString .= implode(' ', $QueryParts['exclude']);
                }
                $this->SphQL->where_match($SearchString, $Field, false);
                if (isset(self::$TorrentFields[$Field])) {
                    $this->UsedTorrentFields[$Field] = $SearchString;
                }
                $this->Filtered = true;
            }
        }
    }
    
    /**
     * Internal function that runs the queries needed to get the desired results
     */
    private function run_query(): void
    {
        if (false !== $this->SphQL->query()) {
            $SphQLResult = $this->SphQL->query();
            if ($SphQLResult->Errno > 0) {
                $this->SphResults = false;
                
                return;
            }
            if ($this->Random && $this->GroupResults) {
                $TotalCount = $SphQLResult->get_meta('total_found');
                $this->SphResults = $SphQLResult->collect('groupid');
                $GroupIDs = array_keys($this->SphResults);
                $GroupCount = count($GroupIDs);
                while ($SphQLResult->get_meta('total') < $TotalCount && $GroupCount < $this->PageSize) {
                    // Make sure we get $PageSize results, or all of them if there are less than $PageSize hits
                    $this->SphQL->where('groupid', $GroupIDs, true);
                    $SphQLResult = $this->SphQL->query();
                    if (!$SphQLResult->has_results()) {
                        break;
                    }
                    $this->SphResults += $SphQLResult->collect('groupid');
                    $GroupIDs = array_keys($this->SphResults);
                    $GroupCount = count($GroupIDs);
                }
                if ($GroupCount > $this->PageSize) {
                    $this->SphResults = array_slice($this->SphResults, 0, $this->PageSize, true);
                }
                $this->NumResults = count($this->SphResults);
            } else {
                $this->NumResults = (int) $SphQLResult->get_meta('total_found');
                $this->SphResults = $this->GroupResults ? $SphQLResult->collect('groupid') : $SphQLResult->to_pair('id',
                    'groupid');
            }
        }
    }
    
    /**
     * Get torrent group info and remove any torrents that don't match
     */
    private function process_results(): void
    {
        if (null === $this->SphResults) {
            return;
        }
        if (0 == count($this->SphResults)) {
            return;
        }
        $this->Groups = Torrents::get_groups($this->SphResults);
        if ($this->need_torrent_ft()) {
            // Query Sphinx for torrent IDs if torrent-specific fulltext filters were used
            $this->filter_torrents_sph();
        } elseif ($this->GroupResults) {
            // Otherwise, let PHP discard unmatching torrents
            $this->filter_torrents_internal();
        }
        // Ungrouped searches don't need any additional filtering
    }
    
    /**
     * @return bool Whether any torrent-specific fulltext filters were used
     */
    public function need_torrent_ft(): bool
    {
        return $this->GroupResults && $this->NumResults > 0 && !empty($this->UsedTorrentFields);
    }
    
    /**
     * Build and run a query that gets torrent IDs from Sphinx when fulltext filters
     * were used to get primary results and they are grouped
     */
    private function filter_torrents_sph(): void
    {
        $AllTorrents = [];
        foreach ($this->Groups as $GroupID => $Group) {
            if (!empty($Group['Torrents'])) {
                $AllTorrents += array_fill_keys(array_keys($Group['Torrents']), $GroupID);
            }
        }
        $TorrentCount = count($AllTorrents);
        $this->SphQLTor = new SphinxqlQuery();
        $this->SphQLTor->where_match('_all', 'fake', false);
        $this->SphQLTor->select('id')->from('torrents, delta');
        foreach ($this->UsedTorrentFields as $Field => $Term) {
            $this->SphQLTor->where_match($Term, $Field, false);
        }
        $this->SphQLTor->copy_attributes_from($this->SphQL);
        $this->SphQLTor->where('id', array_keys($AllTorrents))->limit(0, $TorrentCount, $TorrentCount);
        $SphQLResultTor = $this->SphQLTor->query();
        $MatchingTorrentIDs = $SphQLResultTor->to_pair('id', 'id');
        foreach ($AllTorrents as $TorrentID => $GroupID) {
            if (!isset($MatchingTorrentIDs[$TorrentID])) {
                unset($this->Groups[$GroupID]['Torrents'][$TorrentID]);
            }
        }
    }
    
    /**
     * Non-Sphinx method of collecting IDs of torrents that match any
     * torrent-specific attribute filters that were used in the search query
     */
    private function filter_torrents_internal(): void
    {
        foreach ($this->Groups as $GroupID => $Group) {
            if (empty($Group['Torrents'])) {
                continue;
            }
            foreach ($Group['Torrents'] as $TorrentID => $Torrent) {
                if (!$this->filter_torrent_internal($Torrent)) {
                    unset($this->Groups[$GroupID]['Torrents'][$TorrentID]);
                }
            }
        }
    }
    
    /**
     * Post-processing to determine if a torrent is a real hit or if it was
     * returned because another torrent in the group matched. Only used if
     * there are no torrent-specific fulltext conditions
     *
     * @param array $Torrent Torrent array, probably from Torrents::get_groups()
     *
     * @return bool  True if it's a real hit
     */
    private function filter_torrent_internal(array $Torrent): bool
    {
        if (isset($this->UsedTorrentAttrs['freetorrent'])) {
            $FilterValue = $this->UsedTorrentAttrs['freetorrent'];
            if ('3' == $FilterValue && '0' == $Torrent['FreeTorrent']) {
                // Either FL or NL is ok
                return false;
            } elseif ('3' != $FilterValue && $FilterValue != (int) $Torrent['FreeTorrent']) {
                return false;
            }
        }
        
        return true;
    }
    
    public function insert_hidden_tags($tags): void
    {
        $this->SphQL->where_match($tags, 'taglist', false);
    }
    
    /**
     * @return array Torrent group information for the matches from Torrents::get_groups
     */
    public function get_groups(): array
    {
        return $this->Groups;
    }
    
    /**
     * @param string $Type Field or attribute name
     *
     * @return string Unprocessed search terms
     */
    public function get_terms(string $Type): string
    {
        return $this->RawTerms[$Type] ?? '';
    }
    
    /**
     * @return int Result count
     */
    public function record_count(): int
    {
        return $this->NumResults;
    }
    
    /**
     * @return bool Whether any filters were used
     */
    public function has_filters(): bool
    {
        return $this->Filtered;
    }
    
    public function testq(){
        return $this->SphQL->where_match('_all', 'fake', false);
    }
}
