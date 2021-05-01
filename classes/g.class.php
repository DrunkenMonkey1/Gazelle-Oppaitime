<?php

declare(strict_types=1);

class G
{
    public static DB_MYSQL $DB;
    public static Cache $Cache;
    public static $LoggedUser;
    
    public static function initialize(): void
    {
        global $DB, $Cache, $LoggedUser;
        self::$DB = $DB;
        self::$Cache = $Cache;
        self::$LoggedUser =& $LoggedUser;
    }
}
