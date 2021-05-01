<?php

declare(strict_types=1);

if (!check_perms('site_moderate_forums') || empty($_POST['id']) || empty($_POST['remove'])) {
    print
    json_encode(
        [
            'status' => 'failure'
        ]
    );
    die();
}
$ID = (int)$_POST['id'];
$DB->query(sprintf('UPDATE reports SET ClaimerID = \'0\' WHERE ID = \'%s\'', $ID));
print
  json_encode(
      [
          'status' => 'success',
      ]
  );
die();
