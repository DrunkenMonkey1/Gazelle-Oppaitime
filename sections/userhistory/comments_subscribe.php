<?php

declare(strict_types=1);

// perform the back end of subscribing to topics
authorize();

if (!in_array($_GET['page'], ['artist', 'collages', 'requests', 'torrents'], true) || !is_number($_GET['pageid'])) {
    error(0);
}

Subscriptions::subscribe_comments($_GET['page'], $_GET['pageid']);
