<?php

declare(strict_types=1);

class Inbox
{
    /*
     * Get the link to a user's inbox.
     * This is what handles the ListUnreadPMsFirst setting
     *
     * @param string - whether the inbox or sentbox should be loaded
     * @return string - the URL to a user's inbox
     */
    public static function get_inbox_link($WhichBox = 'inbox'): string
    {
        $ListFirst = G::$LoggedUser['ListUnreadPMsFirst'] ?? false;
        
        if ('inbox' == $WhichBox) {
            $InboxURL = $ListFirst ? 'inbox.php?sort=unread' : 'inbox.php';
        } elseif ($ListFirst) {
            $InboxURL = 'inbox.php?action=sentbox&amp;sort=unread';
        } else {
            $InboxURL = 'inbox.php?action=sentbox';
        }
        
        return $InboxURL;
    }
}
