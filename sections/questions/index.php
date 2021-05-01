<?php

declare(strict_types=1);

enforce_login();

if (!isset($_REQUEST['action'])) {
    if (check_perms('users_mod')) {
        include SERVER_ROOT . '/sections/questions/questions.php';
    } else {
        include SERVER_ROOT . '/sections/questions/ask_question.php';
    }
} else {
    match ($_REQUEST['action']) {
        'take_ask_question' => include SERVER_ROOT . '/sections/questions/take_ask_question.php',
        'answer_question' => include SERVER_ROOT . '/sections/questions/answer_question.php',
        'take_answer_question' => include SERVER_ROOT . '/sections/questions/take_answer_question.php',
        'take_remove_question' => include SERVER_ROOT . '/sections/questions/take_remove_question.php',
        'take_remove_answer' => include SERVER_ROOT . '/sections/questions/take_remove_answer.php',
        'questions' => include SERVER_ROOT . '/sections/questions/questions.php',
        'answers' => include SERVER_ROOT . '/sections/questions/answers.php',
        'view_answers' => include SERVER_ROOT . '/sections/questions/view_answers.php',
        'popular_questions' => include SERVER_ROOT . '/sections/questions/popular_questions.php',
        'ajax_get_answers' => include SERVER_ROOT . '/sections/questions/ajax_get_answers.php',
        'take_ignore_question' => include SERVER_ROOT . '/sections/questions/take_ignore_question.php',
        'edit' => include SERVER_ROOT . '/sections/questions/edit.php',
        'take_edit_answer' => include SERVER_ROOT . '/sections/questions/take_edit_answer.php',
        default => error(404),
    };
}
