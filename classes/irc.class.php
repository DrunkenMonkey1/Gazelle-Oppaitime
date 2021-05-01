<?php

declare(strict_types=1);

class IRC_DB extends DB_MYSQL
{
    public function halt($Msg): void
    {
        global $Bot;
        $Bot->send_to($Bot->get_channel(), 'The database is currently unavailable; try again later.');
    }
}

abstract class IRC_BOT
{
public int $Restart = 0;
    protected bool $Debug = false;
    /**
     * @var bool|resource
     */
    protected $Socket = false;
    protected bool $Data = false;
    protected bool $Whois = false;
    /**
     * @var mixed[]
     */
    protected array $Identified = [];
    /**
     * @var mixed[]
     */
    protected array $Channels = [];
    /**
     * @var mixed[]
     */
    protected array $Messages = [];
    protected bool $LastChan = false;
    /**
     * @var bool|resource
     */
    protected $ListenSocket = false;
    /**
     * @var bool|resource
     */
    protected $Listened = false;
    protected bool $Connecting = false;
    protected int $State = 1;

    public function __construct()
    {
        if (isset($_SERVER['HOME']) && is_dir($_SERVER['HOME']) && getcwd() != $_SERVER['HOME']) {
            chdir($_SERVER['HOME']);
        }
        ob_end_clean();
        restore_error_handler(); //Avoid PHP error logging
        set_time_limit(0);
    }

    public function connect(): void
    {
        $this->connect_irc();
        $this->connect_listener();
        $this->post_connect();
    }

    private function connect_irc($Reconnect = false): void
    {
        $this->Connecting = true;
        //Open a socket to the IRC server
        if (defined('BOT_PORT_SSL')) {
            $IrcAddress = 'tls://' . BOT_SERVER . ':' . BOT_PORT_SSL;
        } else {
            $IrcAddress = 'tcp://' . BOT_SERVER . ':' . BOT_PORT;
        }
        while (!$this->Socket = stream_socket_client($IrcAddress, $ErrNr, $ErrStr)) {
            sleep(15);
        }
        stream_set_blocking($this->Socket, 0);
        $this->Connecting = false;
        if ($Reconnect) {
            $this->post_connect();
        }
    }

    private function post_connect(): void
    {
        fwrite($this->Socket, "NICK " . BOT_NICK . "Init\n");
        fwrite($this->Socket, "USER " . BOT_NICK . " * * :IRC Bot\n");
        $this->listen();
    }
    
    protected function listen(): void
    {
        G::$Cache->InternalCache = false;
        stream_set_timeout($this->Socket, 10_000_000_000);
        while (1 == $this->State) {
            $NullSock = null;
            $Sockets = [$this->Socket, $this->ListenSocket];
            if (false === stream_select($Sockets, $NullSock, $NullSock, null)) {
                die();
            }
            foreach ($Sockets as $Socket) {
                if ($Socket === $this->Socket) {
                    $this->irc_events();
                } else {
                    $this->Listened = stream_socket_accept($Socket);
                    $this->listener_events();
                }
            }
            G::$DB->LinkID = false;
            G::$DB->Queries = [];
        }
    } // Die by default
    
    abstract protected function irc_events(): void;
    
    abstract protected function listener_events(): void;
    
    private function connect_listener(): void
    {
        //create a socket to listen on
        $ListenAddress = 'tcp://' . SOCKET_LISTEN_ADDRESS . ':' . SOCKET_LISTEN_PORT;
        if (!$this->ListenSocket = stream_socket_server($ListenAddress, $ErrNr, $ErrStr)) {
            die("Cannot create listen socket: $ErrStr");
        }
        stream_set_blocking($this->ListenSocket, false);
    }
    
    public function disconnect(): void
    {
        fclose($this->ListenSocket);
        $this->State = 0; //Drones dead
    }
    
    /**
     * @return mixed|bool|void
     */
    public function get_channel()
    {
        preg_match('/.+ PRIVMSG ([^:]+) :.+/', $this->Data, $Channel);
        if (preg_match('/#.+/', $Channel[1])) {
            return $Channel[1];
        } else {
            return false;
        }
    }
    
    public function get_nick()
    {
        preg_match('/:([^!:]+)!.+@[^\s]+ PRIVMSG [^:]+ :.+/', $this->Data, $Nick);
        
        return $Nick[1];
    }
    
    public function send_to($Channel, $Text): void
    {
        // split the message up into <= 460 character strings and send each individually
        // this is used to prevent messages from getting truncated
        $Text = wordwrap($Text, 460, "\n", true);
        $TextArray = explode("\n", $Text);
        foreach ($TextArray as $Text) {
            $this->send_raw("PRIVMSG $Channel :$Text");
        }
    }
    
    protected function send_raw($Text): void
    {
        if (!feof($this->Socket)) {
            fwrite($this->Socket, "$Text\n");
        } elseif (!$this->Connecting) {
            $this->Connecting = true;
            sleep(120);
            $this->connect_irc(true);
        }
    }
    
    abstract protected function connect_events(): void;
    
    abstract protected function channel_events(): void;
    
    abstract protected function query_events(): void;
    
    protected function get_message(): string
    {
        preg_match('/:.+ PRIVMSG [^:]+ :(.+)/', $this->Data, $Msg);
        
        return trim($Msg[1]);
    }
    
    protected function get_irc_host(): string
    {
        preg_match('/:[^!:]+!.+@([^\s]+) PRIVMSG [^:]+ :.+/', $this->Data, $Host);
        
        return trim($Host[1]);
    }
    
    protected function get_word($Select = 1): string
    {
        preg_match('/:.+ PRIVMSG [^:]+ :(.+)/', $this->Data, $Word);
        $Word = explode(' ', $Word[1]);
        
        return trim($Word[$Select]);
    }
    
    protected function get_action(): string
    {
        preg_match('/:.+ PRIVMSG [^:]+ :!(\S+)/', $this->Data, $Action);
        
        return strtoupper($Action[1]);
    }
    
    /*
    This function uses blacklisted_ip, which is no longer in RC2.
    You can probably find it in old RC1 code kicking aronud if you need it.
    protected function ip_check($IP, $Gline = false, $Channel = BOT_REPORT_CHAN) {
      if (blacklisted_ip($IP)) {
        $this->send_to($Channel, 'TOR IP Detected: '.$IP);
        if ($Gline) {
          $this->send_raw('GLINE *@'.$IP.' 90d :DNSBL Proxy');
        }
      }
      if (Tools::site_ban_ip($IP)) {
        $this->send_to($Channel, 'Site IP Ban Detected: '.$IP);
        if ($Gline) {
          $this->send_raw('GLINE *@'.$IP.' 90d :IP Ban');
        }
      }
    }*/
    
    protected function whois(bool $Nick): void
    {
        $this->Whois = $Nick;
        $this->send_raw("WHOIS $Nick");
    }
}
