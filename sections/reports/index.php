<?php

declare(strict_types=1);

enforce_login();

if (empty($_REQUEST['action'])) {
    $_REQUEST['action'] = '';
}

match ($_REQUEST['action']) {
    'report' => include __DIR__ . '/report.php',
    'takereport' => include __DIR__ . '/takereport.php',
    'takeresolve' => include __DIR__ . '/takeresolve.php',
    'stats' => include SERVER_ROOT . '/sections/reports/stats.php',
    'compose' => include SERVER_ROOT . '/sections/reports/compose.php',
    'takecompose' => include SERVER_ROOT . '/sections/reports/takecompose.php',
    'add_notes' => include SERVER_ROOT . '/sections/reports/ajax_add_notes.php',
    'claim' => include SERVER_ROOT . '/sections/reports/ajax_claim_report.php',
    'unclaim' => include SERVER_ROOT . '/sections/reports/ajax_unclaim_report.php',
    'resolve' => include SERVER_ROOT . '/sections/reports/ajax_resolve_report.php',
    default => include SERVER_ROOT . '/sections/reports/reports.php',
};
