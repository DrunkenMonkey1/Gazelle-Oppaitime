<?php

declare(strict_types=1);
/*
 * This page simply assings a report to the person clicking on
 * the Claim / Claim all button.
 */
if (!check_perms('admin_reports')) {
    //error(403);
    echo '403';
    die();
}

if (!is_number($_GET['id'])) {
    die();
}

$DB->query("
  UPDATE reportsv2
  SET Status = 'InProgress',
    ResolverID = " . $LoggedUser['ID'] . "
  WHERE ID = " . $_GET['id']);

if (0 == $DB->affected_rows()) {
    echo '0';
} else {
    echo '1';
}
