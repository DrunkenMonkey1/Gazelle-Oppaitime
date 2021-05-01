<?php

declare(strict_types=1);

/*
 * This is the index page, it is pretty much reponsible only for the switch statement.
 */

enforce_login();

include SERVER_ROOT . '/sections/reportsv2/array.php';

if (isset($_REQUEST['action'])) {
    match ($_REQUEST['action']) {
        'report' => include SERVER_ROOT . '/sections/reportsv2/report.php',
        'takereport' => include SERVER_ROOT . '/sections/reportsv2/takereport.php',
        'takeresolve' => include SERVER_ROOT . '/sections/reportsv2/takeresolve.php',
        'take_pm' => include SERVER_ROOT . '/sections/reportsv2/take_pm.php',
        'search' => include SERVER_ROOT . '/sections/reportsv2/search.php',
        'new' => include SERVER_ROOT . '/sections/reportsv2/reports.php',
        'ajax_new_report' => include SERVER_ROOT . '/sections/reportsv2/ajax_new_report.php',
        'ajax_report' => include SERVER_ROOT . '/sections/reportsv2/ajax_report.php',
        'ajax_change_resolve' => include SERVER_ROOT . '/sections/reportsv2/ajax_change_resolve.php',
        'ajax_take_pm' => include SERVER_ROOT . '/sections/reportsv2/ajax_take_pm.php',
        'ajax_grab_report' => include SERVER_ROOT . '/sections/reportsv2/ajax_grab_report.php',
        'ajax_giveback_report' => include SERVER_ROOT . '/sections/reportsv2/ajax_giveback_report.php',
        'ajax_update_comment' => include SERVER_ROOT . '/sections/reportsv2/ajax_update_comment.php',
        'ajax_update_resolve' => include SERVER_ROOT . '/sections/reportsv2/ajax_update_resolve.php',
        'ajax_create_report' => include SERVER_ROOT . '/sections/reportsv2/ajax_create_report.php',
    };
} elseif (isset($_GET['view'])) {
    include SERVER_ROOT . '/sections/reportsv2/static.php';
} else {
    include SERVER_ROOT . '/sections/reportsv2/views.php';
}
