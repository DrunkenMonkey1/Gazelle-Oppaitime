<?php

declare(strict_types=1);

/*-- TODO ---------------------------//
Add the JavaScript validation into the display page using the class
//-----------------------------------*/

// Allow users to reset their password while logged in
if (!empty($LoggedUser['ID']) && 'recover' !== $_REQUEST['act']) {
    header('Location: index.php');
    die();
}

if (BLOCK_OPERA_MINI && isset($_SERVER['HTTP_X_OPERAMINI_PHONE'])) {
    error('Opera Mini is banned. Please use another browser.');
}

// Check if IP is banned
if (Tools::site_ban_ip($_SERVER['REMOTE_ADDR'])) {
    error('Your IP address has been banned.');
}

require_once SERVER_ROOT . '/classes/twofa.class.php';
require_once SERVER_ROOT . '/classes/u2f.class.php';
require_once SERVER_ROOT . '/classes/validate.class.php';

$Validate = new VALIDATE();
$TwoFA = new TwoFactorAuth(SITE_NAME);
$U2F = new u2f\U2F('https://' . SITE_DOMAIN);

if (array_key_exists('action', $_GET) && 'disabled' == $_GET['action']) {
    require __DIR__ . '/disabled.php';
    die();
}

if (isset($_REQUEST['act']) && 'recover' === $_REQUEST['act']) {
    // Recover password
    if (!empty($_REQUEST['key'])) {
        // User has entered a new password, use step 2
        /**
         * @var \DB_MYSQL $DB
         */
        $DB->query("
      SELECT
        m.ID,
        m.Email,
        m.ipcc,
        i.ResetExpires
      FROM users_main AS m
        INNER JOIN users_info AS i ON i.UserID = m.ID
      WHERE i.ResetKey = ?
        AND i.ResetKey != ''", $_REQUEST['key']);
        [$UserID, $Email, $Country, $Expires] = $DB->next_record();
        
        if (!apcu_exists('DBKEY')) {
            error('Database not fully decrypted. Please wait for staff to fix this and try again later');
        }
        
        $Email = Crypto::decrypt($Email);
        
        if ($UserID && strtotime($Expires) > time()) {
            // If the user has requested a password change, and his key has not expired
            $Validate->SetFields('password', '1', 'regex',
                'You entered an invalid password. Any password at least 6 characters long is accepted, but a strong password is 8 characters or longer, contains at least 1 lowercase and uppercase letter, contains at least a number or symbol',
                ['regex' => '/(?=^.{6,}$).*$/']);
            $Validate->SetFields('verifypassword', '1', 'compare', 'Your passwords did not match.',
                ['comparefield' => 'password']);
            
            if (!empty($_REQUEST['password'])) {
                // If the user has entered a password.
                // If the user has not entered a password, $PassWasReset is not set to 1, and the success message is not shown
                $Err = $Validate->ValidateForm($_REQUEST);
                if ('' === $Err) {
                    // Form validates without error, set new secret and password.
                    $salted = Users::make_sec_hash($_REQUEST['password']);
                    $DB->query("
            UPDATE
              users_main AS m,
              users_info AS i
            SET
              m.PassHash = ?,
              i.ResetKey = '',
              m.LastLogin = NOW(),
              i.ResetExpires = NULL
            WHERE m.ID = ?
              AND i.UserID = m.ID", $salted, $UserID);
                    $DB->query("
            INSERT INTO users_history_passwords
              (UserID, ChangerIP, ChangeTime)
            VALUES
              (?, ?, NOW())", $UserID, Crypto::encrypt($_SERVER['REMOTE_ADDR']));
                    $PassWasReset = true;
                    $LoggedUser['ID'] = $UserID; // Set $LoggedUser['ID'] for logout_all_sessions() to work
                    logout_all_sessions();
                }
            }
            
            // Either a form asking for them to enter the password
            // Or a success message if $PassWasReset is 1
            require __DIR__ . '/recover_step2.php';
        } else {
            // Either his key has expired, or he hasn't requested a pass change at all
            if (strtotime($Expires) < time() && $UserID) {
                // If his key has expired, clear all the reset information
                $DB->query("
          UPDATE users_info
          SET ResetKey = '',
            ResetExpires = NULL
          WHERE UserID = ?", $UserID);
                $_SESSION['reseterr'] = 'The link you were given has expired.'; // Error message to display on form
            }
            // Show him the first form (enter email address)
            header('Location: login.php?act=recover');
        }
    } // End step 2
    
    // User has not clicked the link in his email, use step 1
    else {
        $Validate->SetFields('email', '1', 'email', 'You entered an invalid email address.');
        
        if (!empty($_REQUEST['email'])) {
            // User has entered email and submitted form
            $Err = $Validate->ValidateForm($_REQUEST);
            if (!apcu_exists('DBKEY')) {
                $Err = 'Database not fully decrypted. Please wait for staff to fix this and try again.';
            }
            
            if (!$Err) {
                // Form validates correctly
                
                $DB->query("
          SELECT
            Email
          FROM users_main");
                while ([$EncEmail] = $DB->next_record()) {
                    if (strtolower($_REQUEST['email']) === strtolower(Crypto::decrypt($EncEmail))) {
                        break; // $EncEmail is now the encrypted form of the given email from the database
                    }
                }
                
                $DB->query("
          SELECT
            ID,
            Username,
            Email
          FROM users_main
          WHERE Email = ?", $EncEmail);
                [$UserID, $Username, $Email] = $DB->next_record();
                $Email = Crypto::decrypt($Email);
                
                if ($UserID) {
                    // Email exists in the database
                    // Set ResetKey, send out email, and set $Sent to 1 to show success page
                    Users::reset_password($UserID, $Username, $Email);
                    
                    $Sent = 1; // If $Sent is 1, recover_step1.php displays a success message
                    
                    //Log out all of the users current sessions
                    /**
                     * @var Cache $Cache
                     */
                    $Cache->delete_value(sprintf('user_info_%s', $UserID));
                    $Cache->delete_value(sprintf('user_info_heavy_%s', $UserID));
                    $Cache->delete_value(sprintf('user_stats_%s', $UserID));
                    $Cache->delete_value(sprintf('enabled_%s', $UserID));
                    
                    $DB->query("
            SELECT SessionID
            FROM users_sessions
            WHERE UserID = ?", $UserID);
                    while ([$SessionID] = $DB->next_record()) {
                        $Cache->delete_value(sprintf('session_%s', $UserID) . sprintf('_%s', $SessionID));
                    }
                    $DB->query("
            UPDATE users_sessions
            SET Active = 0
            WHERE UserID = ?
              AND Active = 1", $UserID);
                } else {
                    $Err = 'There is no user with that email address.';
                }
            }
        } elseif (!empty($_SESSION['reseterr'])) {
            // User has not entered email address, and there is an error set in session data
            // This is typically because their key has expired.
            // Stick the error into $Err so recover_step1.php can take care of it
            $Err = $_SESSION['reseterr'];
            unset($_SESSION['reseterr']);
        }
        
        // Either a form for the user's email address, or a success message
        require __DIR__ . '/recover_step1.php';
    }
} // End password recovery

elseif (isset($_REQUEST['act']) && 'newlocation' === $_REQUEST['act']) {
    if (isset($_REQUEST['key'])) {
        if ($ASNCache = $Cache->get_value('new_location_' . $_REQUEST['key'])) {
            $Cache->cache_value('new_location_' . $ASNCache['UserID'] . '_' . $ASNCache['ASN'], true);
            require __DIR__ . '/newlocation.php';
            die();
        }
        
        error(403);
    } else {
        error(403);
    }
} // End new location

// Normal login
else {
    $Validate->SetFields('username', true, 'regex', 'You did not enter a valid username.', ['regex' => USERNAME_REGEX]);
    $Validate->SetFields('password', '1', 'string', 'You entered an invalid password.',
        ['minlength' => '6', 'maxlength' => '307200']);
    
    [$Attempts, $Banned] = $Cache->get_value('login_attempts_' . db_string($_SERVER['REMOTE_ADDR']));
    
    // Function to log a user's login attempt
    function log_attempt(): void
    {
        global $Cache, $Attempts;
        $Attempts = ($Attempts ?? 0) + 1;
        $Cache->cache_value('login_attempts_' . db_string($_SERVER['REMOTE_ADDR']), [$Attempts, ($Attempts > 5)],
            60 * 60 * $Attempts);
        $AllAttempts = $Cache->get_value('login_attempts');
        $AllAttempts[$_SERVER['REMOTE_ADDR']] = time() + (60 * 60 * $Attempts);
        foreach ($AllAttempts as $IP => $Time) {
            if ($Time < time()) {
                unset($AllAttempts[$IP]);
            }
        }
        $Cache->cache_value('login_attempts', $AllAttempts, 0);
    }
    
    // If user has submitted form
    if (isset($_POST['username']) && !empty($_POST['username']) && isset($_POST['password']) && !empty($_POST['password'])) {
        if ($Banned) {
            header("Location: login.php");
            die();
        }
        $Err = $Validate->ValidateForm($_POST);
        
        if (!$Err) {
            // Passes preliminary validation (username and password "look right")
            $DB->query("
        SELECT
          ID,
          PermissionID,
          CustomPermissions,
          PassHash,
          TwoFactor,
          Enabled
        FROM users_main
        WHERE Username = ?
          AND Username != ''", $_POST['username']);
            [$UserID, $PermissionID, $CustomPermissions, $PassHash, $TwoFactor, $Enabled] = $DB->next_record(MYSQLI_NUM,
                [2]);
            if (!$Banned) {
                if ($UserID && Users::check_password($_POST['password'], $PassHash)) {
                    // Update hash if better algorithm available
                    if (password_needs_rehash($PassHash, PASSWORD_DEFAULT)) {
                        $make_sec_hash = Users::make_sec_hash($_POST['password']);
                        $DB->query("
              UPDATE users_main
              SET PassHash = ?
              WHERE Username = ?", $make_sec_hash, $_POST['username']);
                    }
                    $TwoFAVerifyCode = $TwoFA->verifyCode($TwoFactor, $_POST['twofa']);
                    
                    if (empty($TwoFactor) || $TwoFAVerifyCode) {
                        if (1 == $Enabled) {
                            // Check if the current login attempt is from a location previously logged in from
                            if (apcu_exists('DBKEY')) {
                                $DB->query("
                  SELECT IP
                  FROM users_history_ips
                  WHERE UserID = ?", $UserID);
                                $IPs = $DB->to_array(false, MYSQLI_NUM);
                                $QueryParts = [];
                                foreach ($IPs as $i => $IP) {
                                    $IPs[$i] = Crypto::decrypt($IP[0]);
                                }
                                $IPs = array_unique($IPs);
                                if ([] !== $IPs) { // Always allow first login
                                    foreach ($IPs as $IP) {
                                        $QueryParts[] = sprintf('(StartIP<=INET6_ATON(\'%s\') AND EndIP>=INET6_ATON(\'%s\'))',
                                            $IP, $IP);
                                    }
                                    $DB->query('SELECT ASN FROM geoip_asn WHERE ' . implode(' OR ', $QueryParts));
                                    $PastASNs = array_column($DB->to_array(false, MYSQLI_NUM), 0);
                                    $DB->query(sprintf('SELECT ASN FROM geoip_asn WHERE StartIP<=INET6_ATON(\'%s\') AND EndIP>=INET6_ATON(\'%s\')',
                                        $_SERVER['REMOTE_ADDR'], $_SERVER['REMOTE_ADDR']));
                                    [$CurrentASN] = $DB->next_record();
                                    
                                    // if FEATURE_ENFORCE_LOCATIONS is enabled, require users to confirm new logins
                                    if (!in_array($CurrentASN, $PastASNs, true) && FEATURE_ENFORCE_LOCATIONS) {
                                        $CacheGet_value = $Cache->get_value('new_location_' . $UserID . '_' . $CurrentASN);
                                        // Never logged in from this location before
                                        if (true !== $CacheGet_value) {
                                            $DB->query("
                        SELECT
                          UserName,
                          Email
                        FROM users_main
                        WHERE ID = ?", $UserID);
                                            [$Username, $Email] = $DB->next_record();
                                            Users::auth_location($UserID, $Username, $CurrentASN,
                                                Crypto::decrypt($Email));
                                            require __DIR__ . '/newlocation.php';
                                            die();
                                        }
                                    }
                                }
                            }
                            
                            $U2FRegs = [];
                            $DB->query("
                SELECT KeyHandle, PublicKey, Certificate, Counter, Valid
                FROM u2f
                WHERE UserID = ?", $UserID);
                            // Needs to be an array of objects, so we can't use to_array()
                            while ([$KeyHandle, $PublicKey, $Certificate, $Counter, $Valid] = $DB->next_record()) {
                                $U2FRegs[] = (object) [
                                    'keyHandle' => $KeyHandle,
                                    'publicKey' => $PublicKey,
                                    'certificate' => $Certificate,
                                    'counter' => $Counter,
                                    'valid' => $Valid
                                ];
                            }
                            
                            if (count($U2FRegs) > 0) {
                                // U2F is enabled for this account
                                if (isset($_POST['u2f-request']) && isset($_POST['u2f-response'])) {
                                    // Data from the U2F login page is present. Verify it.
                                    try {
                                        $U2FReg = $U2F->doAuthenticate(json_decode($_POST['u2f-request']), $U2FRegs,
                                            json_decode($_POST['u2f-response']));
                                        if ('1' != $U2FReg->valid) {
                                            throw new Exception('Token disabled.');
                                        }
                                        $DB->query(
                                            "UPDATE u2f
                                SET Counter = ?
                                WHERE KeyHandle = ?
                                AND UserID = ?",
                                            $U2FReg->counter,
                                            $U2FReg->keyHandle,
                                            $UserID
                                        );
                                    } catch (Exception $exception) {
                                        $U2FErr = 'U2F key invalid. Error: ' . ($exception->getMessage());
                                        if ('Token disabled.' == $exception->getMessage()) {
                                            $U2FErr = 'This token was disabled due to suspected cloning. Contact staff for assistance';
                                        }
                                        if ('Counter too low.' == $exception->getMessage()) {
                                            $BadHandle = json_decode($_POST['u2f-response'], true)['keyHandle'];
                                            $DB->query("UPDATE u2f
                                  SET Valid = '0'
                                  WHERE KeyHandle = ?
                                  AND UserID = ?", $BadHandle, $UserID);
                                            $U2FErr = 'U2F counter too low. This token has been disabled due to suspected cloning. Contact staff for assistance.';
                                        }
                                    }
                                } else {
                                    // Data from the U2F login page is not present. Go there
                                    require __DIR__ . '/u2f.php';
                                    die();
                                }
                            }
                            
                            if (0 == count($U2FRegs) || !isset($U2FErr)) {
                                $SessionID = Users::make_secret(64);
                                setcookie('session', $SessionID, (time() + 60 * 60 * 24 * 365), '/', '', true, true);
                                setcookie('userid', (string) $UserID, (time() + 60 * 60 * 24 * 365), '/', '', true,
                                    true);
                                
                                // Because we <3 our staff
                                $Permissions = Permissions::get_permissions($PermissionID);
                                $CustomPermissions = unserialize((string) $CustomPermissions);
                                if (isset($Permissions['Permissions']['site_disable_ip_history'])
                                    || isset($CustomPermissions['site_disable_ip_history'])
                                ) {
                                    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
                                }
                                
                                $DB->query("
                  INSERT INTO users_sessions
                    (UserID, SessionID, KeepLogged, Browser, OperatingSystem, IP, LastUpdate, FullUA)
                  VALUES
                    ('{$UserID}', '" . db_string($SessionID) . sprintf('\', \'1\', \'%s\', \'%s\', \'', $Browser,
                                        $OperatingSystem) . db_string(apcu_exists('DBKEY') ? Crypto::encrypt($_SERVER['REMOTE_ADDR']) : '0.0.0.0') . "', NOW(), '" . db_string($_SERVER['HTTP_USER_AGENT']) . "')");
                                
                                $Cache->begin_transaction(sprintf('users_sessions_%s', $UserID));
                                $Cache->insert_front($SessionID, [
                                    'SessionID' => $SessionID,
                                    'Browser' => $Browser,
                                    'OperatingSystem' => $OperatingSystem,
                                    'IP' => (apcu_exists('DBKEY') ? Crypto::encrypt($_SERVER['REMOTE_ADDR']) : '0.0.0.0'),
                                    'LastUpdate' => sqltime()
                                ]);
                                $Cache->commit_transaction(0);
                                
                                $Sql = "
                  UPDATE users_main
                  SET
                    LastLogin = NOW(),
                    LastAccess = NOW()
                  WHERE ID = '" . db_string($UserID) . "'";
                                
                                $DB->query($Sql);
                                
                                if (!empty($_COOKIE['redirect'])) {
                                    $URL = $_COOKIE['redirect'];
                                    setcookie('redirect', '', time() - 60 * 60 * 24, '/', '', false);
                                    header(sprintf('Location: %s', $URL));
                                    die();
                                } else {
                                    header('Location: index.php');
                                    die();
                                }
                            } else {
                                log_attempt();
                                $Err = $U2FErr;
                                setcookie('keeplogged', '', time() + 60 * 60 * 24 * 365, '/', '', false);
                            }
                        } else {
                            log_attempt();
                            if (2 === $Enabled) {
                                // Save the username in a cookie for the disabled page
                                setcookie('username', db_string($_POST['username']), time() + 60 * 60, '/', '', false);
                                header('Location: login.php?action=disabled');
                            } elseif (0 === $Enabled) {
                                $Err = 'Your account has not been confirmed.<br />Please check your email.';
                            }
                            setcookie('keeplogged', '', time() + 60 * 60 * 24 * 365, '/', '', false);
                        }
                    } else {
                        log_attempt();
                        $Err = 'Two-factor authentication failed.';
                        setcookie('keeplogged', '', time() + 60 * 60 * 24 * 365, '/', '', false);
                    }
                } else {
                    log_attempt();
                    $Err = 'Your username or password was incorrect.';
                    setcookie('keeplogged', '', time() + 60 * 60 * 24 * 365, '/', '', false);
                }
            } else {
                log_attempt();
                setcookie('keeplogged', '', time() + 60 * 60 * 24 * 365, '/', '', false);
            }
        } else {
            log_attempt();
            setcookie('keeplogged', '', time() + 60 * 60 * 24 * 365, '/', '', false);
        }
    }
    require __DIR__ . '/login.php';
}
