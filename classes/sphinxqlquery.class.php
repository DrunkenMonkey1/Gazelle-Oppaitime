<?php

class SphinxqlQuery
{
    private \Sphinxql $Sphinxql;
    
    private ?array $Errors = null;
    private ?array $Expressions = null;
    private ?array $Filters = null;
    private ?string $GroupBy = null;
    private ?string $Indexes = null;
    private string|array|null $Limits;
    private ?array $Options = null;
    private ?string $QueryString = null;
    private ?string $Select = null;
    private ?array $SortBy = null;
    private ?string $SortGroupBy = null;
    
    /**
     * Initialize Sphinxql object
     *
     * @param string $Server server address or hostname
     * @param int    $Port   listening port
     * @param string $Socket Unix socket address, overrides $Server:$Port
     */
    public function __construct($Server = SPHINXQL_HOST, $Port = SPHINXQL_PORT, $Socket = SPHINXQL_SOCK)
    {
        $this->Sphinxql = Sphinxql::init_connection($Server, $Port, $Socket);
        $this->reset();
    }
    
    /**
     * Reset all query options and conditions
     */
    public function reset(): void
    {
        $this->Errors = [];
        $this->Expressions = [];
        $this->Filters = [];
        $this->GroupBy = '';
        $this->Indexes = '';
        $this->Limits = [];
        $this->Options = ['ranker' => 'none'];
        $this->QueryString = '';
        $this->Select = '*';
        $this->SortBy = [];
        $this->SortGroupBy = '';
    }
    
    /**
     * Specify what data the Sphinx query is supposed to return
     *
     * @param string $Fields Attributes and expressions
     *
     * @return \SphinxqlQuery Sphinxql query object
     */
    public function select(string $Fields): static
    {
        $this->Select = $Fields;
        
        return $this;
    }
    
    /**
     * Specify the indexes to use in the search
     *
     * @param string $Indexes comma separated list of indexes
     *
     * @return \SphinxqlQuery Sphinxql query object
     */
    public function from(string $Indexes): static
    {
        $this->Indexes = $Indexes;
        
        return $this;
    }
    
    /**
     * Add attribute filter. Calling multiple filter functions results in boolean AND between each condition.
     *
     * @param string $Attribute attribute which the filter will apply to
     * @param mixed  $Values    scalar or array of numerical values. Array uses boolean OR in query condition
     * @param bool   $Exclude   whether to exclude or include matching documents. Default mode is to include matches
     *
     * @return \SphinxqlQuery Sphinxql query object
     */
    public function where(string $Attribute, $Values, bool $Exclude = false): static
    {
        if (empty($Attribute) || !isset($Values)) {
            $this->error("Attribute name and filter value are required.");
            
            return $this;
        }
        $Filters = [];
        if (is_array($Values)) {
            foreach ($Values as $Value) {
                if (!is_number($Value)) {
                    $this->error("Filters only support numeric values.");
                    
                    return $this;
                }
            }
            if ($Exclude) {
                $Filters[] = "$Attribute NOT IN (" . implode(",", $Values) . ")";
            } else {
                $Filters[] = "$Attribute IN (" . implode(",", $Values) . ")";
            }
        } else {
            if (!is_number($Values)) {
                $this->error("Filters only support numeric values.");
                
                return $this;
            }
            $Filters[] = $Exclude ? "$Attribute != $Values" : "$Attribute = $Values";
        }
        $this->Filters[] = implode(" AND ", $Filters);
        
        return $this;
    }
    
    /**
     * Store error messages
     *
     * @param $Msg
     */
    private function error($Msg): void
    {
        $this->Errors[] = $Msg;
    }
    
    /**
     * Add attribute less-than filter. Calling multiple filter functions results in boolean AND between each condition.
     *
     * @param string $Attribute attribute which the filter will apply to
     * @param array  $Value     upper limit for matches
     * @param bool   $Inclusive whether to use <= or <
     *
     * @return \SphinxqlQuery Sphinxql query object
     */
    public function where_lt(string $Attribute, array $Value, bool $Inclusive = false): static
    {
        if (empty($Attribute) || !isset($Value) || !is_number($Value)) {
            $this->error("Attribute name is required and only numeric filters are supported.");
            
            return $this;
        }
        $this->Filters[] = $Inclusive ? "$Attribute <= $Value" : "$Attribute < $Value";
        
        return $this;
    }
    
    /**
     * Add attribute greater-than filter. Calling multiple filter functions results in boolean AND between each
     * condition.
     *
     * @param string $Attribute attribute which the filter will apply to
     * @param array  $Value     lower limit for matches
     * @param bool   $Inclusive whether to use >= or >
     *
     * @return \SphinxqlQuery Sphinxql query object
     */
    public function where_gt(string $Attribute, array $Value, bool $Inclusive = false): static
    {
        if (empty($Attribute) || !isset($Value) || !is_number($Value)) {
            $this->error("Attribute name is required and only numeric filters are supported.");
            
            return $this;
        }
        $this->Filters[] = $Inclusive ? "$Attribute >= $Value" : "$Attribute > $Value";
        
        return $this;
    }
    
    /**
     * Add attribute range filter. Calling multiple filter functions results in boolean AND between each condition.
     *
     * @param string $Attribute attribute which the filter will apply to
     * @param array  $Values    pair of numerical values that defines the filter range
     *
     * @return \SphinxqlQuery Sphinxql query object
     */
    public function where_between(string $Attribute, array $Values): static
    {
        if (empty($Attribute) || empty($Values) || 2 != count($Values) || !is_number($Values[0]) || !is_number($Values[1])) {
            $this->error("Filter range requires array of two numerical boundaries as values.");
            
            return $this;
        }
        $this->Filters[] = "$Attribute BETWEEN $Values[0] AND $Values[1]";
        
        return $this;
    }
    
    /**
     * Add fulltext query expression. Calling multiple filter functions results in boolean AND between each condition.
     * Query expression is escaped automatically
     *
     * @param string $Expr  query expression
     * @param string $Field field to match $Expr against. Default is *, which means all available fields
     * @param bool   $Escape
     *
     * @return \SphinxqlQuery Sphinxql query object
     */
    public function where_match(string $Expr, string $Field = '*', $Escape = true): static
    {
        if (empty($Expr)) {
            return $this;
        }
        if (false !== $Field) {
            $Field = "@$Field ";
        }
        $this->Expressions[] = true === $Escape ? "$Field" . Sphinxql::sph_escape_string($Expr) : $Field . $Expr;
        
        return $this;
    }
    
    /**
     * Specify the order of the matches. Calling this function multiple times sets secondary priorities
     *
     * @param bool|string $Attribute attribute to use for sorting.
     *                               Passing an empty attribute value will clear the current sort settings
     * @param bool        $Mode      sort method to apply to the selected attribute
     *
     * @return \SphinxqlQuery Sphinxql query object
     */
    public function order_by(bool|string $Attribute = false, bool|string $Mode = false): static
    {
        if (empty($Attribute)) {
            $this->SortBy = [];
        } else {
            $this->SortBy[] = "$Attribute $Mode";
        }
        
        return $this;
    }
    
    /**
     * Specify how the results are grouped
     *
     * @param string|bool $Attribute group matches with the same $Attribute value.
     *                               Passing an empty attribute value will clear the current group settings
     *
     * @return \SphinxqlQuery Sphinxql query object
     */
    public function group_by(string|bool $Attribute = false): static
    {
        $this->GroupBy = empty($Attribute) ? '' : $Attribute;
        
        return $this;
    }
    
    /**
     * Specify the order of the results within groups
     *
     * @param string|bool $Attribute attribute to use for sorting.
     *                               Passing an empty attribute will clear the current group sort settings
     * @param bool        $Mode      sort method to apply to the selected attribute
     *
     * @return \SphinxqlQuery Sphinxql query object
     */
    public function order_group_by(string|bool $Attribute = false, bool|string $Mode = false): static
    {
        $this->SortGroupBy = empty($Attribute) ? '' : "$Attribute $Mode";
        
        return $this;
    }
    
    /**
     * Specify the offset and amount of matches to return
     *
     * @param int $Offset     number of matches to discard
     * @param int $Limit      number of matches to return
     * @param int $MaxMatches number of results to store in the Sphinx server's memory. Must be >= ($Offset+$Limit)
     *
     * @return \SphinxqlQuery Sphinxql query object
     */
    public function limit(int $Offset, int $Limit, int $MaxMatches = SPHINX_MAX_MATCHES): static
    {
        $this->Limits = "$Offset, $Limit";
        $this->set('max_matches', $MaxMatches);
        
        return $this;
    }
    
    /**
     * Tweak the settings to use for the query. Sanity checking shouldn't be needed as Sphinx already does it
     *
     * @param string $Name  setting name
     * @param mixed  $Value value
     *
     * @return \SphinxqlQuery Sphinxql query object
     */
    public function set(string $Name, $Value): static
    {
        $this->Options[$Name] = $Value;
        
        return $this;
    }
    
    /**
     * Construct and send the query. Register the query in the global Sphinxql object
     *
     * @param bool GetMeta whether to fetch meta data for the executed query. Default is yes
     *
     * @return \SphinxqlResult|bool result object
     */
    public function query($GetMeta = true): \SphinxqlResult|bool
    {
       
        if (false === $this->Sphinxql->sph_connect()) {
            return false;
        }
        
        $QueryStartTime = microtime(true);
        $this->build_query();
        if (count($this->Errors) > 0) {
            $ErrorMsg = implode("\n", $this->Errors);
            $this->Sphinxql->error("Query builder found errors:\n$ErrorMsg");
    
            return new SphinxqlResult(null, null, 1, $ErrorMsg);
        }
        $QueryString = $this->QueryString;
        $Result = $this->send_query($GetMeta);
        $QueryProcessTime = (microtime(true) - $QueryStartTime) * 1000;
        Sphinxql::register_query($QueryString, $QueryProcessTime);
        
    
        return $Result;
    }
    
    /**
     * Combine the query conditions into a valid Sphinx query segment
     *
     * @return bool|void
     */
    private function build_query()
    {
        if (!$this->Indexes) {
            $this->error('Index name is required.');
            
            return false;
        }
        $this->QueryString = "SELECT $this->Select\nFROM $this->Indexes";
        if (!empty($this->Expressions)) {
            $this->Filters['expr'] = "MATCH('" . implode(' ', $this->Expressions) . "')";
        }
        if (!empty($this->Filters)) {
            $this->QueryString .= "\nWHERE " . implode("\n\tAND ", $this->Filters);
        }
        if (!empty($this->GroupBy)) {
            $this->QueryString .= "\nGROUP BY $this->GroupBy";
        }
        if (!empty($this->SortGroupBy)) {
            $this->QueryString .= "\nWITHIN GROUP ORDER BY $this->SortGroupBy";
        }
        if (!empty($this->SortBy)) {
            $this->QueryString .= "\nORDER BY " . implode(", ", $this->SortBy);
        }
        if (!empty($this->Limits)) {
            $this->QueryString .= "\nLIMIT $this->Limits";
        }
        if (!empty($this->Options)) {
            $Options = $this->build_options();
            $this->QueryString .= "\nOPTION $Options";
        }
    }
    
    /**
     * Combine the query options into a valid Sphinx query segment
     *
     * @return string of options
     */
    private function build_options(): string
    {
        $Options = [];
        foreach ($this->Options as $Option => $Value) {
            $Options[] = "$Option = $Value";
        }
        
        return implode(', ', $Options);
    }
    
    /**
     * Run a pre-processed query. Only used internally
     *
     * @param bool GetMeta whether to fetch meta data for the executed query
     *
     * @return bool|\SphinxqlResult result object
     */
    private function send_query($GetMeta): bool|\SphinxqlResult
    {
        if (!$this->QueryString) {
            return false;
        }
        
        if (false !== $this->Sphinxql->sph_connect()) {
            $Result = $this->Sphinxql->query($this->QueryString);
            if (false === $Result) {
                $Errno = $this->Sphinxql->errno;
                $Error = $this->Sphinxql->error;
                $this->Sphinxql->error("Query returned error $Errno ($Error).\n$this->QueryString");
                $Meta = null;
            } else {
                $Errno = 0;
                $Error = '';
                $Meta = $GetMeta ? $this->get_meta() : null;
            }
            
            return new SphinxqlResult($Result, $Meta, $Errno, $Error);
        }
        
        return false;
    }
    
    /**
     * Fetch and store meta data for the last executed query
     *
     * @return array data
     */
    private function get_meta(): array
    {
        return $this->raw_query("SHOW META", false)->to_pair(0, 1);
    }
    
    /**
     * Run a manually constructed query
     *
     * @param string|null $Query
     * @param bool GetMeta whether to fetch meta data for the executed query. Default is yes
     *
     * @return bool|\SphinxqlResult result object
     */
    public function raw_query(?string $Query, $GetMeta = true): bool|\SphinxqlResult
    {
        $this->QueryString = $Query;
        
        return $this->send_query($GetMeta);
    }
    
    /**
     * Copy attribute filters from another SphinxqlQuery object
     *
     * @param SphinxqlQuery $SphQLSource object to copy the filters from
     *
     * @return void SphinxqlQuery object
     */
    public function copy_attributes_from(\SphinxqlQuery $SphQLSource): void
    {
        $this->Filters = $SphQLSource->Filters;
    }
}
