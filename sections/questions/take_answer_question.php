<?php

declare(strict_types=1);

authorize();

if (!check_perms("users_mod")) {
    error(403);
}

$ID = $_POST['id'];
$Answer = db_string($_POST['answer']);
$Date = sqltime();
$UserID = $LoggedUser['ID'];

if (!is_number($ID) || empty($Answer)) {
    error(404);
}

$DB->query("
  SELECT 1
  FROM staff_answers
  WHERE QuestionID = '{$ID}'
    AND UserID = '$LoggedUser[ID]'");

if (!$DB->has_results()) {
    $DB->query("
    INSERT INTO staff_answers
      (QuestionID, UserID, Answer, Date)
    VALUES
      ('{$ID}', '{$UserID}', '{$Answer}', '{$Date}')");
    $DB->query("
    SELECT UserID
    FROM user_questions
    WHERE ID = '{$ID}'");
    [$ToID] = $DB->next_record();
    Misc::send_pm($ToID, 0, "Your question has been answered", "One of your questions has been answered! View the response [url=" . site_url() . sprintf('questions.php?action=view_answers&userid=%s#question%s]here[/url].', $UserID, $ID));
} else {
    error("You have already answered this question");
}
header("Location: questions.php");
