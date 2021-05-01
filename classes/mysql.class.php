<?php

declare(strict_types=1);

//-----------------------------------------------------------------------------------
/////////////////////////////////////////////////////////////////////////////////////
/*//-- MySQL wrapper class ----------------------------------------------------------

This class provides an interface to mysqli. You should always use this class instead
of the mysql/mysqli functions, because this class provides debugging features and a
bunch of other cool stuff.

Everything returned by this class is automatically escaped for output. This can be
turned off by setting $Escape to false in next_record or to_array.

//--------- Basic usage -------------------------------------------------------------

* Creating the object.

require(SERVER_ROOT.'/classes/mysql.class.php');
$DB = NEW DB_MYSQL;
-----

* Making a query

$DB->query("
  SELECT *
  FROM table...");

  Is functionally equivalent to using mysqli_query("SELECT * FROM table...")
  Stores the result set in $this->QueryID
  Returns the result set, so you can save it for later (see set_query_id())
-----

* Getting data from a query

$array = $DB->next_record();
  Is functionally equivalent to using mysqli_fetch_array($ResultSet)
  You do not need to specify a result set - it uses $this-QueryID
-----

* Escaping a string

db_string($str);
  Is a wrapper for $DB->escape_str(), which is a wrapper for
  mysqli_real_escape_string(). The db_string() function exists so that you
  don't have to keep calling $DB->escape_str().

  USE THIS FUNCTION EVERY TIME YOU USE AN UNVALIDATED USER-SUPPLIED VALUE IN
  A DATABASE QUERY!


//--------- Advanced usage ---------------------------------------------------------

* The conventional way of retrieving a row from a result set is as follows:

list($All, $Columns, $That, $You, $Select) = $DB->next_record();
-----

* This is how you loop over the result set:

while (list($All, $Columns, $That, $You, $Select) = $DB->next_record()) {
  echo "Do stuff with $All of the ".$Columns.$That.$You.$Select;
}
-----

* There are also a couple more mysqli functions that have been wrapped. They are:

record_count()
  Wrapper to mysqli_num_rows()

affected_rows()
  Wrapper to mysqli_affected_rows()

inserted_id()
  Wrapper to mysqli_insert_id()

close
  Wrapper to mysqli_close()
-----

* And, of course, a few handy custom functions.

to_array($Key = false)
  Transforms an entire result set into an array (useful in situations where you
  can't order the rows properly in the query).

  If $Key is set, the function uses $Key as the index (good for looking up a
  field). Otherwise, it uses an iterator.

  For an example of this function in action, check out forum.php.

collect($Key)
  Loops over the result set, creating an array from one of the fields ($Key).
  For an example, see forum.php.

set_query_id($ResultSet)
  This class can only hold one result set at a time. Using set_query_id allows
  you to set the result set that the class is using to the result set in
  $ResultSet. This result set should have been obtained earlier by using
  $DB->query().

  Example:

  $FoodRS = $DB->query("
      SELECT *
      FROM food");
  $DB->query("
    SELECT *
    FROM drink");
  $Drinks = $DB->next_record();
  $DB->set_query_id($FoodRS);
  $Food = $DB->next_record();

  Of course, this example is contrived, but you get the point.


-------------------------------------------------------------------------------------
*///---------------------------------------------------------------------------------


if (!extension_loaded('mysqli')) {
    die('Mysqli Extension not loaded.');
}

//Handles escaping
function db_string($String, $DisableWildcards = false)
{
    global $DB;
    //Escape
    $String = $DB->escape_str($String);
    //Remove user input wildcards
    if ($DisableWildcards) {
        $String = str_replace(['%', '_'], ['\%', '\_'], $String);
    }
    
    return $String;
}

function db_array($Array, $DontEscape = [], $Quote = false)
{
    foreach ($Array as $Key => $Val) {
        if (!in_array($Key, $DontEscape, true)) {
            $Array[$Key] = $Quote ? '\'' . db_string(trim($Val)) . '\'' : db_string(trim($Val));
        }
    }
    
    return $Array;
}

//TODO: revisit access levels once Drone is replaced by ZeRobot
class DB_MYSQL
{
    public $PreppedQuery;
    /**
     * @var bool|\mysqli|null
     */
    public $LinkID = false;
    /**
     * @var mixed[]
     */
    public array $Queries = [];
    public float $Time = 0.0;
    protected bool|\mysqli_result $QueryID = false;
    protected bool|\mysqli_stmt $StatementID = false;
    protected bool $PreparedQuery = false;
    /**
     * @var mixed[]|bool|null
     */
    protected null|bool|array $Record = [];
    protected ?int $Row = null;
    protected int $Errno = 0;
    protected ?string $Error = '';
    
    public function __construct(
        protected $Database = SQLDB,
        protected $User = SQLLOGIN,
        protected $Pass = SQLPASS,
        protected $Server = SQLHOST,
        protected $Port = SQLPORT,
        protected $Socket = SQLSOCK
    ) {
    }
    
    public function prepare_query(bool $Query, &...$BindVars): \mysqli_stmt|bool
    {
        $this->connect();
        
        $this->StatementID = mysqli_prepare($this->LinkID, $Query);
        if (!empty($BindVars)) {
            $Types = '';
            $TypeMap = ['string' => 's', 'double' => 'd', 'integer' => 'i', 'boolean' => 'i'];
            foreach ($BindVars as $BindVar) {
                $Types .= $TypeMap[gettype($BindVar)] ?? 'b';
            }
            mysqli_stmt_bind_param($this->StatementID, $Types, ...$BindVars);
        }
        $this->PreparedQuery = $Query;
        
        return $this->StatementID;
    }
    
    public function connect(): void
    {
        if (!$this->LinkID) {
            $this->LinkID = mysqli_connect($this->Server, $this->User, $this->Pass, $this->Database, $this->Port,
                $this->Socket); // defined in config.php
            if (!$this->LinkID) {
                $this->Errno = mysqli_connect_errno();
                $this->Error = mysqli_connect_error();
                $this->halt('Connection failed (host:' . $this->Server . ':' . $this->Port . ')');
            }
        }
        mysqli_set_charset($this->LinkID, "utf8mb4");
    }
    
    public function halt($Msg): void
    {
        global $Debug, $argv;
        $DBError = 'MySQL: ' . strval($Msg) . ' SQL error: ' . strval($this->Errno) . ' (' . strval($this->Error) . ')';
        if (1194 == $this->Errno) {
            send_irc('PRIVMSG ' . ADMIN_CHAN . ' :' . $this->Error);
        }
        $Debug->analysis('!dev DB Error', $DBError, 3600 * 24);
        if (DEBUG_MODE || check_perms('site_debug') || isset($argv[1])) {
            echo '<pre>' . display_str($DBError) . '</pre>';
            if (DEBUG_MODE || check_perms('site_debug')) {
                print_r($this->Queries);
            }
            die();
        }
        
        error('-1');
    }
    
    public function exec_prepared_query(): void
    {
        $QueryStartTime = microtime(true);
        mysqli_stmt_execute($this->StatementID);
        $this->QueryID = mysqli_stmt_get_result($this->StatementID);
        $QueryRunTime = (microtime(true) - $QueryStartTime) * 1000;
        $this->Queries[] = [$this->PreppedQuery, $QueryRunTime, null];
        $this->Time += $QueryRunTime;
    }
    
    public function query($Query, &...$BindVars): bool|\mysqli_result
    {
    
        global $Debug;
        /*
         * If there was a previous query, we store the warnings. We cannot do
         * this immediately after mysqli_query because mysqli_insert_id will
         * break otherwise due to mysqli_get_warnings sending a SHOW WARNINGS;
         * query. When sending a query, however, we're sure that we won't call
         * mysqli_insert_id (or any similar function, for that matter) later on,
         * so we can safely get the warnings without breaking things.
         * Note that this means that we have to call $this->warnings manually
         * for the last query!
         */
        if ($this->QueryID) {
            $this->warnings();
        }
        $QueryStartTime = microtime(true);
        $this->connect();
        
        // In the event of a MySQL deadlock, we sleep allowing MySQL time to unlock, then attempt again for a maximum of 5 tries
        for ($i = 1; $i < 6; $i++) {
            $this->StatementID = mysqli_prepare($this->LinkID, $Query);
            if (!empty($BindVars)) {
                $Types = '';
                $TypeMap = ['string' => 's', 'double' => 'd', 'integer' => 'i', 'boolean' => 'i'];
                foreach ($BindVars as $BindVar) {
                    $Types .= $TypeMap[gettype($BindVar)] ?? 'b';
                }
                mysqli_stmt_bind_param($this->StatementID, $Types, ...$BindVars);
            }
            if (false !== $this->StatementID) {
                mysqli_stmt_execute($this->StatementID);
                $this->QueryID = mysqli_stmt_get_result($this->StatementID);
            }
            
            // in DEBUG_MODE, return the full trace on a SQL error (super useful
            // for debugging). do not attempt to retry to query
            if (DEBUG_MODE && !$this->QueryID) {
                echo '<pre>' . mysqli_error($this->LinkID) . '<br><br>';
                debug_print_backtrace();
                echo '</pre>';
                die();
            }
            
            if (!in_array(mysqli_errno($this->LinkID), [1213, 1205], true)) {
                break;
            }
            $Debug->analysis('Non-Fatal Deadlock:', $Query, 3600 * 24);
            trigger_error("Database deadlock, attempt $i");
            
            sleep($i * rand(2, 5)); // Wait longer as attempts increase
        }
        
        $QueryEndTime = microtime(true);
        $this->Queries[] = [$Query, ($QueryEndTime - $QueryStartTime) * 1000, null];
        $this->Time += ($QueryEndTime - $QueryStartTime) * 1000;
        
        if (!$this->QueryID && !$this->StatementID) {
            $this->Errno = mysqli_errno($this->LinkID);
            $this->Error = mysqli_error($this->LinkID);
            $this->halt("Invalid Query: $Query");
        }
        
        $this->Row = 0;
        
        return $this->QueryID;
    }
    
    /**
     * This function determines whether the last query caused warning messages
     * and stores them in $this->Queries.
     */
    public function warnings(): void
    {
        $Warnings = [];
        if (!is_bool($this->LinkID) && mysqli_warning_count($this->LinkID)) {
            $e = mysqli_get_warnings($this->LinkID);
            do {
                if (1592 == $e->errno) {
                    // 1592: Unsafe statement written to the binary log using statement format since BINLOG_FORMAT = STATEMENT.
                    continue;
                }
                $Warnings[] = 'Code ' . $e->errno . ': ' . display_str($e->message);
            } while ($e->next());
        }
        $this->Queries[count($this->Queries) - 1][2] = $Warnings;
    }
    
    public function query_unb($Query): void
    {
        $this->connect();
        mysqli_real_query($this->LinkID, $Query);
    }
    
    /**
     * @return int|string|void
     */
    public function inserted_id()
    {
        if ($this->LinkID) {
            return mysqli_insert_id($this->LinkID);
        }
    }
    
    public function next_record($Type = MYSQLI_BOTH, $Escape = true)
    { // $Escape can be true, false, or an array of keys to not escape
        if ($this->LinkID && false !== $this->QueryID) {
            $this->Record = mysqli_fetch_array($this->QueryID, $Type);
            $this->Row++;
            if (!is_array($this->Record)) {
                $this->QueryID = false;
            } elseif (false !== $Escape) {
                $this->Record = Misc::display_array($this->Record, $Escape);
            }
            
            return $this->Record;
        }
    }
    
    /*
     * returns an integer with the number of rows found
     * returns a string if the number of rows found exceeds MAXINT
     */
    
    public function close(): void
    {
        if ($this->LinkID) {
            if (!mysqli_close($this->LinkID)) {
                $this->halt('Cannot close connection or connection did not open.');
            }
            $this->LinkID = false;
        }
    }
    
    /*
     * returns true if the query exists and there were records found
     * returns false if the query does not exist or if there were 0 records returned
     */
    
    public function has_results(): bool
    {
        return ($this->QueryID && 0 !== $this->record_count());
    }
    
    /**
     * @return int|string|void
     */
    public function record_count()
    {
        if ($this->QueryID) {
            return mysqli_num_rows($this->QueryID);
        }
    }
    
    /**
     * @return int|string|void
     */
    public function affected_rows()
    {
        if ($this->LinkID) {
            return mysqli_affected_rows($this->LinkID);
        }
    }
    
    // You should use db_string() instead.
    
    public function info(): string
    {
        return mysqli_get_host_info($this->LinkID);
    }
    
    // Creates an array from a result set
    // If $Key is set, use the $Key column in the result set as the array key
    // Otherwise, use an integer
    
    public function escape_str($Str): string
    {
        $this->connect();
        if (is_array($Str)) {
            trigger_error('Attempted to escape array.');
            
            return '';
        }
        
        return mysqli_real_escape_string($this->LinkID, (string) $Str);
    }
    
    //  Loops through the result set, collecting the $ValField column into an array with $KeyField as keys
    
    /**
     * @param bool $Key
     * @param int  $Type
     * @param bool $Escape
     *
     * @return array
     */
    public function to_array($Key = false, $Type = MYSQLI_BOTH, $Escape = true)
    {
        $Return = [];
        
        while ($Row = mysqli_fetch_array($this->QueryID, $Type)) {
            if (false !== $Escape && is_array($Row)) {
//                $Row = Misc::display_array($Row, $Escape);
            }
            if (false !== $Key) {
                $Return[$Row[$Key]] = $Row;
            } else {
                $Return[] = $Row;
            }
        }
        mysqli_data_seek($this->QueryID, 0);
        
        return $Return;
    }
    
    //  Loops through the result set, collecting the $Key column into an array
    
    /**
     * @return array<int|string, mixed>
     */
    public function to_pair($KeyField, $ValField, $Escape = true): array
    {
        $Return = [];
        while ($Row = mysqli_fetch_array($this->QueryID)) {
            if ($Escape) {
                $Key = display_str($Row[$KeyField]);
                $Val = display_str($Row[$ValField]);
            } else {
                $Key = $Row[$KeyField];
                $Val = $Row[$ValField];
            }
            $Return[$Key] = $Val;
        }
        mysqli_data_seek($this->QueryID, 0);
        
        return $Return;
    }
    
    /**
     * @return mixed[]
     */
    public function collect($Key, $Escape = true): array
    {
        $Return = [];
        while ($Row = mysqli_fetch_array($this->QueryID)) {
            $Return[] = $Escape ? display_str($Row[$Key]) : $Row[$Key];
        }
        mysqli_data_seek($this->QueryID, 0);
        
        return $Return;
    }
    
    public function get_query_id(): bool|\mysqli_result
    {
        return $this->QueryID;
    }
    
    public function set_query_id(bool|\mysqli_result &$ResultSet): void
    {
        $this->QueryID = $ResultSet;
        $this->Row = 0;
    }
    
    public function beginning(): void
    {
        mysqli_data_seek($this->QueryID, 0);
        $this->Row = 0;
    }
}
