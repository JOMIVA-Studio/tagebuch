<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/environment.php';
init_app_environment();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/throttle.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/mailer.php';

define('APP_BOOTSTRAPPED', true);

user_session_start();

// Stellt sicher, dass die Datenbank existiert und alle Migrationen
// angewendet sind, bevor irgendeine Seite Daten liest oder schreibt.
db();
