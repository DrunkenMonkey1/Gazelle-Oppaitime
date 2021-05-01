<?php

declare(strict_types=1);
/*****************************************************************
User history switch center


This page acts as a switch that includes the real user history pages (to keep
the root less cluttered).

enforce_login() is run here - the entire user history pages are off limits for
non members.
*****************************************************************/

//Include all the basic stuff...
enforce_login();

if ($_GET['action']) {
    match ($_GET['action']) {
        'ips' => include __DIR__ . '/ip_history.php',
        'tracker_ips' => include __DIR__ . '/ip_tracker_history.php',
        'passwords' => include __DIR__ . '/password_history.php',
        'email' => include __DIR__ . '/email_history.php',
        'email2' => include __DIR__ . '/email_history2.php',
        'useremail' => include __DIR__ . '/email_history_userview.php',
        'userip' => include __DIR__ . '/ip_history_userview.php',
        'passkeys' => include __DIR__ . '/passkey_history.php',
        'posts' => include __DIR__ . '/post_history.php',
        'subscriptions' => require __DIR__ . '/subscriptions.php',
        'thread_subscribe' => require __DIR__ . '/thread_subscribe.php',
        'comments_subscribe' => require __DIR__ . '/comments_subscribe.php',
        'catchup' => require __DIR__ . '/catchup.php',
        'collage_subscribe' => require __DIR__ . '/collage_subscribe.php',
        'subscribed_collages' => require __DIR__ . '/subscribed_collages.php',
        'catchup_collages' => require __DIR__ . '/catchup_collages.php',
        'token_history' => require __DIR__ . '/token_history.php',
        'quote_notifications' => require __DIR__ . '/quote_notifications.php',
        default => header('Location: index.php'),
    };
}

/* Database Information Regarding This Page

users_history_ips:
  id (auto_increment, index)
  userid (index)
  ip (stored using ip2long())
  timestamp

users_history_passwd:
  id (auto_increment, index)
  userid (index)
  changed_by (index)
  old_pass
  new_pass
  timestamp

users_history_email:
  id (auto_increment, index)
  userid (index)
  changed_by (index)
  old_email
  new_email
  timestamp

users_history_passkey:
  id (auto_increment, index)
  userid (index)
  changed_by (index)
  old_passkey
  new_passkey
  timestamp

users_history_stats:
  id (auto_increment, index)
  userid (index)
  uploaded
  downloaded
  ratio
  timestamp

*/
