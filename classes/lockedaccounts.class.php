<?php

declare(strict_types=1);

/**
 * Class to manage locked accounts
 */
class LockedAccounts
{
    /**
     * Lock an account
     *
     * @param int    $UserID         The ID of the user to lock
     * @param int    $Type           The lock type, should be a constant value
     * @param string $Message        The message to write to user notes
     * @param string $Reason         The reason for the lock
     * @param int    $LockedByUserID The ID of the staff member that locked $UserID's account. 0 for system
     */
    public static function lock_account(
        int $UserID,
        int $Type,
        string $Message,
        string $Reason,
        int $LockedByUserID
    ): void {
        if (0 == $LockedByUserID) {
            $Username = "System";
        } else {
            G::$DB->query("SELECT Username FROM users_main WHERE ID = '" . $LockedByUserID . "'");
            [$Username] = G::$DB->next_record();
        }
        
        G::$DB->query("
                INSERT INTO locked_accounts (UserID, Type)
                VALUES ('" . $UserID . "', " . $Type . ")");
        Tools::update_user_notes($UserID,
            sqltime() . " - " . db_string($Message) . " by $Username\nReason: " . db_string($Reason) . "\n\n");
        G::$Cache->delete_value('user_info_' . $UserID);
    }
    
    /**
     * Unlock an account
     *
     * @param int    $UserID           The ID of the user to unlock
     * @param int    $Type             The lock type, should be a constant value. Used for database verification
     *                                 to avoid deleting the wrong lock type
     * @param string $Reason           The reason for unlock
     * @param int    $UnlockedByUserID The ID of the staff member unlocking $UserID's account. 0 for system
     */
    public static function unlock_account(int $UserID, int $Type, $Message, string $Reason, int $UnlockedByUserID): void
    {
        if (0 == $UnlockedByUserID) {
            $Username = "System";
        } else {
            G::$DB->query("SELECT Username FROM users_main WHERE ID = '" . $UnlockedByUserID . "'");
            [$Username] = G::$DB->next_record();
        }
        
        G::$DB->query("DELETE FROM locked_accounts WHERE UserID = '$UserID' AND Type = '" . $Type . "'");
        
        if (1 == G::$DB->affected_rows()) {
            G::$Cache->delete_value("user_info_" . $UserID);
            Tools::update_user_notes($UserID,
                sqltime() . " - " . db_string($Message) . " by $Username\nReason: " . db_string($Reason) . "\n\n");
        }
    }
}
