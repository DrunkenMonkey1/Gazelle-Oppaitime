<?php

if (!check_perms('users_mod')) {
    json_error(403);
}

if (!FEATURE_EMAIL_REENABLE) {
    json_error("This feature is currently disabled.");
}

$Type = $_GET['type'];

if ("resolve" == $Type) {
    $IDs = $_GET['ids'];
    $Comment = db_string($_GET['comment']);
    $Status = db_string($_GET['status']);

    // Error check and set things up
    if ("Approve" == $Status || "Approve Selected" == $Status) {
        $Status = AutoEnable::APPROVED;
    } elseif ("Reject" == $Status || "Reject Selected" == $Status) {
        $Status = AutoEnable::DENIED;
    } elseif ("Discard" == $Status || "Discard Selected" == $Status) {
        $Status = AutoEnable::DISCARDED;
    } else {
        json_error("Invalid resolution option");
    }

    if (is_array($IDs) && 0 == count($IDs)) {
        json_error("You must select at least one reuqest to use this option");
    } elseif (!is_array($IDs) && !is_number($IDs)) {
        json_error("You must select at least 1 request");
    }

    // Handle request
    AutoEnable::handle_requests($IDs, $Status, $Comment);
} elseif ("unresolve" == $Type) {
    $ID = (int) $_GET['id'];
    AutoEnable::unresolve_request($ID);
} else {
    json_error("Invalid type");
}

echo json_encode(["status" => "success"]);

function json_error($Message)
{
    echo json_encode(["status" => $Message]);
    die();
}
