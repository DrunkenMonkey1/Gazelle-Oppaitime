<?php declare(strict_types=1);

enforce_login();
authorize();

if (!isset($_POST['emails']) || !is_array($_POST['emails'])) {
    error("Stop that.");
}

if (!apcu_exists('DBKEY')) {
    error(403);
}

$EncEmails = $_POST['emails'];

$Reason = $_POST['reason'] ?? '';

foreach ($EncEmails as $EncEmail) {
    $DB->query("
    SELECT UserID
    FROM users_history_emails
    WHERE Email = '" . db_string($EncEmail) . "'");

    if (!$DB->has_results()) {
        error('Email not found');
    }

    [$UserID] = $DB->next_record();

    if (!check_perms('users_mod') && ($UserID != $LoggedUser['ID'])) {
        error(403);
    }

    $DB->query("
    SELECT Email
    FROM users_main
    WHERE ID = '{$UserID}'");

    if (!$DB->has_results()) {
        error(404);
    }

    [$Curr] = $DB->next_record();
    $Curr = Crypto::decrypt($Curr);

    if ($Curr == Crypto::decrypt($EncEmail)) {
        error("You can't delete your current email.");
    }
}

//Okay I think everything checks out.

$DB->query("
  INSERT INTO deletion_requests
    (UserID, Type, Value, Reason, Time)
  VALUES
    ('{$UserID}', 'Email', '" . db_string($EncEmails[0]) . "', '" . db_string($Reason) . "', NOW())");

$Cache->delete_value('num_deletion_requests');

View::show_header('Email Deletion Request');
?>

<div class="thin">
  <h2 id="general">Email Deletion Request</h2>
  <div class="box pad" style="padding: 10px 10px 10px 20px;">
    <p>Your request has been sent. Please wait for it to be acknowledged.</p>
    <p>After it's accepted or denied by staff, you will receive a PM response.</p>
    <p><a href="/index.php">Return</a></p>
  </div>
</div>
<?php View::show_footer(); ?>
