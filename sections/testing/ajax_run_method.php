<?php

declare(strict_types=1);

authorize();
if (!check_perms('users_mod')) {
    error(404);
}

$Class = $_POST['class'];
$Method = $_POST['method'];
$Params = json_decode($_POST['params'], true);

if (!empty($Class) && !empty($Method) && Testing::has_testable_method($Class, $Method)) {
    if (count($Params) > 0) {
        $Results = call_user_func_array([$Class, $Method], array_values($Params));
    } else {
        $Results = call_user_func([$Class, $Method]);
    }
    TestingView::render_results($Results);
}
