<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
date_default_timezone_set('Europe/Zurich');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/mailer.php';

define('APP_BOOTSTRAPPED', true);

admin_session_start();

db();
