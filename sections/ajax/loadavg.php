<?php

declare(strict_types=1);

authorize(true);

print
    json_encode([
        'status' => 'success',
        'response' => [
            'loadAverage' => sys_getloadavg()
        ]
    ], JSON_THROW_ON_ERROR);
