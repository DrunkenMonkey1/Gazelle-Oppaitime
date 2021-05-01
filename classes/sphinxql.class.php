<?php

declare(strict_types=1);

if (!extension_loaded('mysqli')) {
    error('Mysqli Extension not loaded.');
}

class Sphinxql extends mysqli
{
    /**
     * @var mixed[]
     */
    public static array $Queries = [];
    public static float $Time = 0.0;
    /**
     * @var mixed[]
     */
    private static array $Connections = [];
    private $Ident;
    private bool $Connected = false;
    
    /**
     * Initialize Sphinxql object
     *
     * @param $Server
     * @param $Port
     * @param $Socket
     */
    public function __construct(private $Server, private $Port, private $Socket)
    {
        $this->Ident = self::get_ident($Server, $Port, $Socket);
    }
    
    /**
     * Create server ident based on connection information
     *
     * @param string $Server server address or hostname
     * @param int    $Port   listening port
     * @param string $Socket Unix socket address, overrides $Server:$Port
     *
     * @return string|mixed|void string
     */
    private static function get_ident(string $Server, int $Port, string $Socket): mixed
    {
        if ('' !== $Socket) {
            return $Socket;
        }
        
        return "$Server:$Port";
    }
    
    /**
     * Create Sphinxql object or return existing one
     *
     * @param string $Server server address or hostname
     * @param int    $Port   listening port
     * @param string $Socket Unix socket address, overrides $Server:$Port
     *
     * @return Sphinxql object
     */
    public static function init_connection(string $Server, int $Port, string $Socket): \Sphinxql
    {
        $Ident = self::get_ident($Server, $Port, $Socket);
        if (!isset(self::$Connections[$Ident])) {
            self::$Connections[$Ident] = new Sphinxql($Server, $Port, $Socket);
        }
        
        return self::$Connections[$Ident];
    }
    
    /**
     * Escape special characters before sending them to the Sphinx server.
     * Two escapes needed because the first one is eaten up by the mysql driver.
     *
     * @param string $String string to escape
     *
     * @return escaped string
     */
    public static function sph_escape_string(string $String): string
    {
        return strtr(
            strtolower($String),
            [
                '(' => '\\\\(',
                ')' => '\\\\)',
                '|' => '\\\\|',
                '-' => '\\\\-',
                '@' => '\\\\@',
                '~' => '\\\\~',
                '&' => '\\\\&',
                '\'' => '\\\'',
                '<' => '\\\\<',
                '!' => '\\\\!',
                '"' => '\\\\"',
                '/' => '\\\\/',
                '*' => '\\\\*',
                '$' => '\\\\$',
                '^' => '\\\\^',
                '\\' => '\\\\\\\\'
            ]
        );
    }
    
    /**
     * Register sent queries globally for later retrieval by debug functions
     *
     * @param string $QueryString      query text
     * @param param  $QueryProcessTime time building and processing the query
     */
    public static function register_query(string $QueryString, $QueryProcessTime): void
    {
        self::$Queries[] = [$QueryString, $QueryProcessTime];
        self::$Time += $QueryProcessTime;
    }
    
    /**
     * Connect the Sphinxql object to the Sphinx server
     */
    public function sph_connect(): bool
    {
        if ($this->Connected || $this->connect_errno) {
            return true;
        }
        global $Debug;
        $Debug->set_flag("Connecting to Sphinx server $this->Ident");
        for ($Attempt = 0; $Attempt < 3; $Attempt++) {
            parent::__construct($this->Server, '', '', '', $this->Port, $this->Socket);
            if (0 === $this->connect_errno) {
                $this->Connected = true;
                break;
            }
            sleep(1);
        }
        if (0 !== $this->connect_errno) {
            $Errno = $this->connect_errno;
            $Error = $this->connect_error;
            $this->error("Connection failed. (" . strval($Errno) . ": " . strval($Error) . ")");
            $Debug->set_flag("Could not connect to Sphinx server $this->Ident. (" . strval($Errno) . ": " . strval($Error) . ")");
            
            return false;
        }
        $Debug->set_flag("Connected to Sphinx server $this->Ident");
        return true;
    }
    
    /**
     * Print a message to privileged users and optionally halt page processing
     *
     * @param string $Msg  message to display
     * @param bool   $Halt halt page processing. Default is to continue processing the page
     *
     * @return void object
     */
    public function error(string $Msg, bool $Halt = false): void
    {
        global $Debug;
        $ErrorMsg = 'SphinxQL (' . $this->Ident . '): ' . strval($Msg);
        $Debug->analysis('SphinxQL Error', $ErrorMsg, 3600 * 24);
        if ($Halt && (DEBUG_MODE || check_perms('site_debug'))) {
            echo '<pre>' . display_str($ErrorMsg) . '</pre>';
            die();
        }
        
        if ($Halt) {
            error('-1');
        }
    }
}
