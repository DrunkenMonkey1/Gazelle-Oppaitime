<?php declare(strict_types=1);
// Debug info for developers
ini_set('max_execution_time', '600');
define('MAX_TIME', 20000); //Maximum execution time in ms
define('MAX_ERRORS', 0); //Maxmimum errors, warnings, notices we will allow in a page
define('MAX_MEMORY', 80 * 1024 * 1024); //Maximum memory used per pageload
define('MAX_QUERIES', 30); //Maxmimum queries

class DEBUG
{
    /**
     * @var mixed[]
     */
    public array $Errors = [];
    /**
     * @var mixed[]
     */
    public array $Flags = [];
    /**
     * @var mixed[]
     */
    public array $Perf = [];
    /**
     * @var mixed[]
     */
    private array $LoggedVars = [];
    
    public function profile($Automatic = ''): bool
    {
        global $ScriptStartTime;
        $Reason = [];
        
        if (!empty($Automatic)) {
            $Reason[] = $Automatic;
        }
        
        $Micro = (microtime(true) - $ScriptStartTime) * 1000;
        if ($Micro > MAX_TIME && !defined('TIME_EXCEPTION')) {
            $Reason[] = number_format($Micro, 3) . ' ms';
        }
        
        $Errors = count($this->get_errors());
        if ($Errors > MAX_ERRORS && !defined('ERROR_EXCEPTION')) {
            $Reason[] = $Errors . ' PHP errors';
        }
        /*
        $Queries = count($this->get_queries());
        if ($Queries > MAX_QUERIES && !defined('QUERY_EXCEPTION')) {
          $Reason[] = $Queries.' Queries';
        }
        */
        $Ram = memory_get_usage(true);
        if ($Ram > MAX_MEMORY && !defined('MEMORY_EXCEPTION')) {
            $Reason[] = Format::get_size($Ram) . ' RAM used';
        }
        
        G::$DB->warnings(); // see comment in MYSQL::query
        /*$Queries = $this->get_queries();
        $DBWarningCount = 0;
        foreach ($Queries as $Query) {
          if (!empty($Query[2])) {
            $DBWarningCount += count($Query[2]);
          }
        }
        if ($DBWarningCount) {
          $Reason[] = $DBWarningCount . ' DB warning(s)';
        }*/
        
        $CacheStatus = G::$Cache->server_status();
        $cache_fail_reportedCache_value = G::$Cache->get_value('cache_fail_reported');
        if (in_array(0, $CacheStatus, true) && !$cache_fail_reportedCache_value) {
            // Limit to max one report every 15 minutes to avoid massive debug spam
            G::$Cache->cache_value('cache_fail_reported', true, 900);
            $Reason[] = "Cache server error";
        }
        
        if (isset($_REQUEST['profile'])) {
            $Reason[] = 'Requested by ' . G::$LoggedUser['Username'];
        }
        
        $this->Perf['Memory usage'] = (($Ram >> 10) / 1024) . ' MB';
        $this->Perf['Page process time'] = number_format($Micro / 1000, 3) . ' s';
        $this->Perf['CPU time'] = number_format($this->get_cpu_time() / 1_000_000, 3) . ' s';
        
        if (isset($Reason[0])) {
            $this->log_var($CacheStatus, 'Cache server status');
            $this->analysis(implode(', ', $Reason));
            
            return true;
        }
        
        return false;
    }
    
    /**
     * @return mixed[]
     */
    public function get_errors($Light = false): array
    {
        //Because the cache can't take some of these variables
        if ($Light) {
            foreach (array_keys($this->Errors) as $Key) {
                $this->Errors[$Key][3] = '';
            }
        }
        
        return $this->Errors;
    }
    
    public function get_cpu_time(): float|int|bool
    {
        if (!defined('PHP_WINDOWS_VERSION_MAJOR')) {
            global $CPUTimeStart;
            $RUsage = getrusage();
            
            return $RUsage['ru_utime.tv_sec'] * 1_000_000 + $RUsage['ru_utime.tv_usec'] - $CPUTimeStart;
        }
        
        return false;
    }
    
    public function log_var($Var, $VarName = false): void
    {
        $BackTrace = debug_backtrace();
        $ID = Users::make_secret(5);
        if (!$VarName) {
            $VarName = $ID;
        }
        $File = ['path' => substr($BackTrace[0]['file'], strlen(SERVER_ROOT)), 'line' => $BackTrace[0]['line']];
        $this->LoggedVars[$ID] = [$VarName => ['bt' => $File, 'data' => $Var]];
    }
    
    public function analysis($Message, $Report = '', $Time = 43200): void
    {
        global $Document;
        if (empty($Report)) {
            $Report = $Message;
        }
        $Identifier = Users::make_secret(5);
        G::$Cache->cache_value(
            'analysis_' . $Identifier,
            [
                'url' => $_SERVER['REQUEST_URI'],
                'message' => $Report,
                'errors' => $this->get_errors(true),
                'queries' => $this->get_queries(),
                'flags' => $this->get_flags(),
                'includes' => $this->get_includes(),
                'cache' => $this->get_cache_keys(),
                'vars' => $this->get_logged_vars(),
                'perf' => $this->get_perf(),
                'ocelot' => $this->get_ocelot_requests()
            ],
            $Time
        );
        $RequestURI = empty($_SERVER['REQUEST_URI']) ? '' : substr($_SERVER['REQUEST_URI'], 1);
        send_irc('PRIVMSG ' . LAB_CHAN . " :{$Message} $Document " . site_url() . "tools.php?action=analysis&case=$Identifier " . site_url() . $RequestURI);
    }
    
    //This isn't in the constructor because $this is not available, and the function cannot be made static
    
    public function get_queries()
    {
        return G::$DB->Queries;
    }
    
    /**
     * @return mixed[]
     */
    public function get_flags(): array
    {
        return $this->Flags;
    }
    
    /**
     * @return mixed[]
     */
    public function get_includes(): array
    {
        return get_included_files();
    }
    
    /* Data wrappers */
    
    /**
     * @return int[]|string[]
     */
    public function get_cache_keys(): array
    {
        return array_keys(G::$Cache->CacheHits);
    }
    
    /**
     * @return mixed[]
     */
    public function get_logged_vars(): array
    {
        return $this->LoggedVars;
    }
    
    /**
     * @return array<string, string>|mixed[]
     */
    public function get_perf(): array
    {
        if (empty($this->Perf)) {
            global $ScriptStartTime;
            $PageTime = (microtime(true) - $ScriptStartTime);
            $CPUTime = $this->get_cpu_time();
            $Perf = [
                'Memory usage' => Format::get_size(memory_get_usage(true)),
                'Page process time' => number_format($PageTime, 3) . ' s'
            ];
            if ($CPUTime) {
                $Perf['CPU time'] = number_format($CPUTime / 1_000_000, 3) . ' s';
            }
            
            return $Perf;
        }
        
        return $this->Perf;
    }
    
    /**
     * @return mixed[]|void
     */
    public function get_ocelot_requests()
    {
        if (class_exists('Tracker')) {
            return Tracker::$Requests;
        }
    }
    
    public function set_flag($Event): void
    {
        global $ScriptStartTime;
        $this->Flags[] = [
            $Event,
            (microtime(true) - $ScriptStartTime) * 1000,
            memory_get_usage(true),
            $this->get_cpu_time()
        ];
    }
    
    public function handle_errors(): void
    {
        //error_reporting(E_ALL ^ E_STRICT | E_WARNING | E_DEPRECATED | E_ERROR | E_PARSE); //E_STRICT disabled
        error_reporting(E_WARNING | E_ERROR | E_PARSE);
        set_error_handler(fn ($Level, $Error, $File, $Line) => $this->php_error_handler($Level, $Error, $File, $Line));
    }
    
    public function php_error_handler($Level, $Error, $File, $Line): bool
    {
        //Who added this, it's still something to pay attention to...
        if (false !== stripos('Undefined index', $Error)) {
            //return true;
        }
        
        $Steps = 1; //Steps to go up in backtrace, default one
        $Call = '';
        $Args = '';
        $Tracer = debug_backtrace();
        
        //This is in case something in this function goes wrong and we get stuck with an infinite loop
        if (isset($Tracer[$Steps]['function'], $Tracer[$Steps]['class']) && 'php_error_handler' == $Tracer[$Steps]['function'] && 'DEBUG' == $Tracer[$Steps]['class']) {
            return true;
        }
        
        //If this error was thrown, we return the function which threw it
        if (isset($Tracer[$Steps]['function']) && 'trigger_error' == $Tracer[$Steps]['function']) {
            $Steps++;
            $File = $Tracer[$Steps]['file'];
            $Line = $Tracer[$Steps]['line'];
        }
        
        //At this time ONLY Array strict typing is fully supported.
        //Allow us to abuse strict typing (IE: function test(Array))
        if (preg_match('/^Argument (\d+) passed to \S+ must be an (array), (array|string|integer|double|object) given, called in (\S+) on line (\d+) and defined$/',
            $Error, $Matches)) {
            $Error = 'Type hinting failed on arg ' . $Matches[1] . ', expected ' . $Matches[2] . ' but found ' . $Matches[3];
            $File = $Matches[4];
            $Line = $Matches[5];
        }
        
        //Lets not be repetative
        if (('include' == $Tracer[$Steps]['function'] || 'require' == $Tracer[$Steps]['function']) && isset($Tracer[$Steps]['args'][0]) && $Tracer[$Steps]['args'][0] == $File) {
            unset($Tracer[$Steps]['args']);
        }
        
        //Class
        if (isset($Tracer[$Steps]['class'])) {
            $Call .= $Tracer[$Steps]['class'] . '::';
        }
        
        //Function & args
        if (isset($Tracer[$Steps]['function'])) {
            $Call .= $Tracer[$Steps]['function'];
            if (isset($Tracer[$Steps]['args'][0])) {
                $Args = $this->format_args($Tracer[$Steps]['args']);
            }
        }
        
        //Shorten the path & we're done
        $File = str_replace(SERVER_ROOT, '', $File);
        $Error = str_replace(SERVER_ROOT, '', $Error);
        
        if (DEBUG_WARNINGS) {
            $this->Errors[] = [$Error, $File . ':' . $Line, $Call, $Args];
        }
        
        return true;
    }
    
    protected function format_args($Array): string
    {
        $LastKey = -1;
        $Return = [];
        foreach ($Array as $Key => $Val) {
            $Return[$Key] = '';
            if (!is_int($Key) || !is_int($LastKey) || $Key !== $LastKey + 1) {
                $Return[$Key] .= "'$Key' => ";
            }
            if (true === $Val) {
                $Return[$Key] .= 'true';
            } elseif (false === $Val) {
                $Return[$Key] .= 'false';
            } elseif (is_string($Val)) {
                $Return[$Key] .= "'$Val'";
            } elseif (is_int($Val)) {
                $Return[$Key] .= $Val;
            } elseif (is_object($Val)) {
                $Return[$Key] .= $Val::class;
            } elseif (is_array($Val)) {
                $Return[$Key] .= 'array(' . $this->format_args($Val) . ')';
            }
            $LastKey = $Key;
        }
        
        return implode(', ', $Return);
    }
    
    public function perf_table($Perf = false): void
    {
        if (!is_array($Perf)) {
            $Perf = $this->get_perf();
        }
        if (empty($Perf)) {
            return;
        } ?>
        <table class="layout">
            <tr>
                <td><strong><a href="#"
                               onclick="$(this).parents('.layout').next('#debug_perf').gtoggle(); return false;"
                               class="brackets">View</a> Performance Statistics:</strong></td>
            </tr>
        </table>
        <table id="debug_perf" class="debug_table hidden">
            <?php
            foreach ($Perf as $Stat => $Value) {
                ?>
                <tr class="valign_top">
                    <td class="debug_perf_stat"><?= $Stat ?></td>
                    <td class="debug_perf_data"><?= $Value ?></td>
                </tr>
                <?php
            } ?>
        </table>
        <?php
    }
    
    public function include_table($Includes = false): void
    {
        if (!is_array($Includes)) {
            $Includes = $this->get_includes();
        } ?>
        <table class="layout">
            <tr>
                <td><strong><a href="#"
                               onclick="$(this).parents('.layout').next('#debug_include').gtoggle(); return false;"
                               class="brackets">View</a> <?= number_format(count($Includes)) ?> Includes:</strong></td>
            </tr>
        </table>
        <table id="debug_include" class="debug_table hidden">
            <?php
            foreach ($Includes as $File) {
                ?>
                <tr class="valign_top">
                    <td><?= $File ?></td>
                </tr>
                <?php
            } ?>
        </table>
        <?php
    }
    
    public function class_table($Classes = false): void
    {
        if (!is_array($Classes)) {
            $Classes = $this->get_classes();
        } ?>
        <table class="layout">
            <tr>
                <td><strong><a href="#"
                               onclick="$(this).parents('.layout').next('#debug_classes').gtoggle(); return false;"
                               class="brackets">View</a> Classes:</strong></td>
            </tr>
        </table>
        <table id="debug_classes" class="debug_table hidden">
            <tr>
                <td>
        <pre>
<?php print_r($Classes);
echo "\n"; ?>
        </pre>
                </td>
            </tr>
        </table>
        <?php
    }
    
    public function get_classes()
    {
        foreach (get_declared_classes() as $Class) {
            $Classes[$Class]['Vars'] = get_class_vars($Class);
            $Classes[$Class]['Functions'] = get_class_methods($Class);
        }
        
        return $Classes;
    }
    
    public function extension_table(): void
    {
        ?>
        <table class="layout">
            <tr>
                <td><strong><a href="#"
                               onclick="$(this).parents('.layout').next('#debug_extensions').gtoggle(); return false;"
                               class="brackets">View</a> Extensions:</strong></td>
            </tr>
        </table>
        <table id="debug_extensions" class="debug_table hidden">
            <tr>
                <td>
        <pre>
<?php print_r($this->get_extensions());
echo "\n"; ?>
        </pre>
                </td>
            </tr>
        </table>
        <?php
    }
    
    public function get_extensions()
    {
        foreach (get_loaded_extensions() as $Extension) {
            $Extensions[$Extension]['Functions'] = get_extension_funcs($Extension);
        }
        
        return $Extensions;
    }
    
    public function flag_table($Flags = false): void
    {
        if (!is_array($Flags)) {
            $Flags = $this->get_flags();
        }
        if (empty($Flags)) {
            return;
        } ?>
        <table class="layout">
            <tr>
                <td><strong><a href="#"
                               onclick="$(this).parents('.layout').next('#debug_flags').gtoggle(); return false;"
                               class="brackets">View</a> Flags:</strong></td>
            </tr>
        </table>
        <table id="debug_flags" class="debug_table hidden">
            <tr class="valign_top">
                <td class="debug_flags_event"><strong>Event</strong></td>
                <td class="debug_flags_time"><strong>Page time</strong></td>
                <?php if (false !== $Flags[0][3]) { ?>
                    <td class="debug_flags_time"><strong>CPU time</strong></td>
                <?php } ?>
                <td class="debug_flags_memory"><strong>Memory</strong></td>
            </tr>
            <?php
            foreach ($Flags as $Flag) {
                [$Event, $MicroTime, $Memory, $CPUTime] = $Flag; ?>
                <tr class="valign_top">
                    <td><?= $Event ?></td>
                    <td><?= number_format($MicroTime, 3) ?> ms</td>
                    <?php if (false !== $CPUTime) { ?>
                        <td><?= number_format($CPUTime / 1000, 3) ?> ms</td>
                    <?php } ?>
                    <td><?= Format::get_size($Memory) ?></td>
                </tr>
                <?php
            } ?>
        </table>
        <?php
    }
    
    /* Output Formatting */
    
    public function constant_table($Constants = false): void
    {
        if (!is_array($Constants)) {
            $Constants = $this->get_constants();
        } ?>
        <table class="layout">
            <tr>
                <td><strong><a href="#"
                               onclick="$(this).parents('.layout').next('#debug_constants').gtoggle(); return false;"
                               class="brackets">View</a> Constants:</strong></td>
            </tr>
        </table>
        <table id="debug_constants" class="debug_table hidden">
            <tr>
                <td class="debug_data debug_constants_data">
        <pre>
<?= display_str(print_r($Constants, true)) ?>
        </pre>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * @return mixed[]
     */
    public function get_constants(): array
    {
        return get_defined_constants(true);
    }
    
    public function ocelot_table($OcelotRequests = false): void
    {
        if (!is_array($OcelotRequests)) {
            $OcelotRequests = $this->get_ocelot_requests();
        }
        if (empty($OcelotRequests)) {
            return;
        } ?>
        <table class="layout">
            <tr>
                <td><strong><a data-toggle-target="#debug_ocelot"
                               class="brackets">View</a> <?= number_format(count($OcelotRequests)) ?> Ocelot
                        requests:</strong></td>
            </tr>
        </table>
        <table id="debug_ocelot" class="debug_table hidden">
            <?php foreach ($OcelotRequests as $i => $Request) { ?>
                <tr>
                    <td class="debug_data debug_ocelot_data">
                        <a data-toggle-target="#debug_ocelot_<?= $i ?>"><?= display_str($Request['path']) ?></a>
                        <pre id="debug_ocelot_<?= $i ?>" class="hidden"><?= display_str($Request['response']) ?></pre>
                    </td>
                    <td class="debug_info" style="width: 100px;">
                        <?= display_str($Request['status']) ?>
                    </td>
                    <td class="debug_info debug_timing" style="width: 100px;">
                        <?= number_format($Request['time'], 5) ?> ms
                    </td>
                </tr>
            <?php } ?>
        </table>
        <?php
    }
    
    public function cache_table($CacheKeys = false): void
    {
        $Header = 'Cache Keys';
        if (!is_array($CacheKeys)) {
            $CacheKeys = $this->get_cache_keys();
            $Header .= ' (' . number_format($this->get_cache_time(), 5) . ' ms)';
        }
        if (empty($CacheKeys)) {
            return;
        }
        $Header = ' ' . number_format(count($CacheKeys)) . " $Header:"; ?>
        <table class="layout">
            <tr>
                <td><strong><a href="#"
                               onclick="$(this).parents('.layout').next('#debug_cache').gtoggle(); return false;"
                               class="brackets">View</a><?= $Header ?></strong></td>
            </tr>
        </table>
        <table id="debug_cache" class="debug_table hidden">
            <?php foreach ($CacheKeys as $Key) { ?>
                <tr>
                    <td class="label nobr debug_info debug_cache_key">
                        <a href="#"
                           onclick="$('#debug_cache_<?= $Key ?>').gtoggle(); return false;"><?= display_str($Key) ?></a>
                        <a href="tools.php?action=clear_cache&amp;key=<?= $Key ?>&amp;type=clear"
                           target="_blank"
                           class="brackets tooltip"
                           title="Clear this cache key">Clear</a>
                    </td>
                    <td class="debug_data debug_cache_data">
        <pre id="debug_cache_<?= $Key ?>" class="hidden">
<?= display_str(print_r(G::$Cache->get_value($Key, true), true)) ?>
        </pre>
                    </td>
                </tr>
            <?php } ?>
        </table>
        <?php
    }
    
    public function get_cache_time()
    {
        return G::$Cache->Time;
    }
    
    public function error_table($Errors = false): void
    {
        if (!is_array($Errors)) {
            $Errors = $this->get_errors();
        }
        if (empty($Errors)) {
            return;
        } ?>
        <table class="layout">
            <tr>
                <td><strong><a href="#"
                               onclick="$(this).parents('.layout').next('#debug_error').gtoggle(); return false;"
                               class="brackets">View</a> <?= number_format(count($Errors)) ?> Errors:</strong></td>
            </tr>
        </table>
        <table id="debug_error" class="debug_table hidden">
            <?php
            foreach ($Errors as $Error) {
                [$Error, $Location, $Call, $Args] = $Error; ?>
                <tr class="valign_top">
                    <td class="debug_info debug_error_call">
                        <?= display_str($Call) ?>(<?= display_str($Args) ?>)
                    </td>
                    <td class="debug_data debug_error_data">
                        <?= display_str($Error) ?>
                    </td>
                    <td>
                        <?= display_str($Location) ?>
                    </td>
                </tr>
                <?php
            } ?>
        </table>
        <?php
    }
    
    public function query_table($Queries = false): void
    {
        $Header = 'Queries';
        if (!is_array($Queries)) {
            $Queries = $this->get_queries();
            $Header .= ' (' . number_format($this->get_query_time(), 5) . ' ms)';
        }
        if (empty($Queries)) {
            return;
        }
        $Header = ' ' . number_format(count($Queries)) . " $Header:"; ?>
        <table class="layout">
            <tr>
                <td><strong><a href="#"
                               onclick="$(this).parents('.layout').next('#debug_database').gtoggle(); return false;"
                               class="brackets">View</a><?= $Header ?></strong></td>
            </tr>
        </table>
        <table id="debug_database" class="debug_table hidden">
            <?php
            foreach ($Queries as $Query) {
                $SQL = $Query[0] ?? null;
                $Time = $Query[1] ?? null;
                $Warnings = $Query[2] ?? null;
                if (null !== $Warnings) {
                    $Warnings = implode('<br />', $Warnings);
                } ?>
                <tr class="valign_top">
                    <td class="debug_data debug_query_data">
                        <div><?= str_replace("\t", '&nbsp;&nbsp;', nl2br(display_str(trim($SQL)))) ?></div>
                    </td>
                    <td class="debug_info debug_query_time" style="width: 130px;"><?= number_format($Time, 5) ?> ms</td>
                    <td class="debug_info debug_query_warnings"><?= $Warnings ?></td>
                </tr>
                <?php
            } ?>
        </table>
        <?php
    }
    
    public function get_query_time()
    {
        return G::$DB->Time;
    }
    
    public function sphinx_table($Queries = false): void
    {
        $Header = 'Searches';
        if (!is_array($Queries)) {
            $Queries = $this->get_sphinxql_queries();
            $Header .= ' (' . number_format($this->get_sphinxql_time(), 5) . ' ms)';
        }
        if (empty($Queries)) {
            return;
        }
        $Header = ' ' . number_format(count($Queries)) . " $Header:"; ?>
        <table class="layout">
            <tr>
                <td><strong><a href="#"
                               onclick="$(this).parents('.layout').next('#debug_sphinx').gtoggle(); return false;"
                               class="brackets">View</a><?= $Header ?></strong></td>
            </tr>
        </table>
        <table id="debug_sphinx" class="debug_table hidden">
            <?php
            foreach ($Queries as $Query) {
                [$Params, $Time] = $Query; ?>
                <tr class="valign_top">
                    <td class="debug_data debug_sphinx_data">
                        <pre><?= str_replace("\t", '  ', $Params) ?></pre>
                    </td>
                    <td class="debug_info debug_sphinx_time" style="width: 130px;"><?= number_format($Time, 5) ?>ms
                    </td>
                </tr>
                <?php
            } ?>
        </table>
        <?php
    }
    
    /**
     * @return mixed[]|void
     */
    public function get_sphinxql_queries()
    {
        if (class_exists('Sphinxql')) {
            return Sphinxql::$Queries;
        }
    }
    
    /**
     * @return float|void
     */
    public function get_sphinxql_time()
    {
        if (class_exists('Sphinxql')) {
            return Sphinxql::$Time;
        }
    }
    
    public function vars_table($Vars = false): void
    {
        $Header = 'Logged Variables';
        if (empty($Vars)) {
            if (empty($this->LoggedVars)) {
                return;
            }
            $Vars = $this->LoggedVars;
        }
        $Header = ' ' . number_format(count($Vars)) . " $Header:"; ?>
        <table class="layout">
            <tr>
                <td><strong><a href="#"
                               onclick="$(this).parents('.layout').next('#debug_loggedvars').gtoggle(); return false;"
                               class="brackets">View</a><?= $Header ?></strong></td>
            </tr>
        </table>
        <table id="debug_loggedvars" class="debug_table hidden">
            <?php
            foreach ($Vars as $ID => $Var) {
                $Key = key($Var);
                $Data = current($Var);
                if (false !== is_null($Data['data'])) {
                    $Size = count($Data['data']);
                }
                ?>
                <tr>
                    <td class="debug_info debug_loggedvars_name">
                        <a href="#"
                           onclick="$('#debug_loggedvars_<?= $ID ?>').gtoggle(); return false;"><?= display_str($Key) ?></a>
                        (<?= $Size . (1 == $Size ? ' element' : ' elements') ?>)
                        <div><?= $Data['bt']['path'] . ':' . $Data['bt']['line']; ?></div>
                    </td>
                    <td class="debug_data debug_loggedvars_data">
        <pre id="debug_loggedvars_<?= $ID ?>" class="hidden">
<?= display_str(print_r($Data['data'], true)) ?>
        </pre>
                    </td>
                </tr>
                <?php
            } ?>
        </table>
        <?php
    }
}
