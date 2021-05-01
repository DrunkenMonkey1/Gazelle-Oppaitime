<?php

declare(strict_types=1);

authorize();

$ID = $_POST['id'];
$UserID = $_POST['userid'];
$Answer = db_string($_POST['edit']);

if (empty($Answer) || !is_number($ID) || $UserID != $LoggedUser['ID']) {
    error(403);
}

$DB->query("
  UPDATE staff_answers
  SET Answer = '{$Answer}'
  WHERE QuestionID = '{$ID}'
    AND UserID = '{$UserID}'");

header(sprintf('Location: questions.php?action=view_answers&userid=%s', $UserID));
