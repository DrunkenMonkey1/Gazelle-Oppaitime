<h1>Error: 403</h1> Forbidden.
<?php
if ('/static/' !== substr($_SERVER['REQUEST_URI'], 0, 9)) {
    notify(STATUS_CHAN, '403');
}
