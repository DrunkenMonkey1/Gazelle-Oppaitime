<?php

declare(strict_types=1);

enforce_login(); // authorize() doesn't work if we're not logged in
authorize();
logout();
