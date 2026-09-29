<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/environment.php';
init_app_environment();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/throttle.php';
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/mailer.php';

define('APP_BOOTSTRAPPED', true);

admin_session_start();

db();
