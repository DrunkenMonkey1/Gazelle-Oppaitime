<?php

$DB->query("
    SELECT
      sq.UserID, um.Username, count(1) AS Answered
    FROM staff_answers AS sq
      LEFT JOIN users_main AS um ON um.ID = sq.UserID
    GROUP BY sq.UserID
    ORDER BY um.Username ASC");
$Staff = $DB->to_array();

$DB->query("
  SELECT COUNT(1)
  FROM user_questions");
[$TotalQuestions] = $DB->next_record();

View::show_header("Ask the Staff");
?>
<div class="thin">
  <div class="header">
    <h2>Staff Answers</h2>
  </div>
  <div class="linkbox">
<?php  if (check_perms("users_mod")) { ?>
    <a class="brackets" href="questions.php">View questions</a>
<?php  } else { ?>
    <a class="brackets" href="questions.php">Ask question</a>
<?php  } ?>
    <a class="brackets" href="questions.php?action=popular_questions">Popular questions</a>
  </div>
<?php  foreach ($Staff as $User) { ?>
    <h2>
      <a href="questions.php?action=view_answers&amp;userid=<?=$User['UserID']?>"><?=$User['Username']?></a>
      - (<?=$User['Answered']?> / <?=$TotalQuestions?>)
    </h2>
<?php  } ?>
</div>
<?php
View::show_footer();
