<?php
declare(strict_types=1);

// Fuer den Produktivbetrieb per Cronjob ausfuehren, z.B. taeglich um 08:00 Uhr:
//   0 8 * * *  php /pfad/zur/app/cron/send_inactivity_reminders.php
//
// Prueft Datenbank und Migrationen automatisch (siehe src/db.php) und
// verschickt die "inactivity_reminder"-Vorlage an alle laenger inaktiven,
// dafuer angemeldeten User.

require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/helpers.php';
require __DIR__ . '/../src/mailer.php';
require __DIR__ . '/../src/reminders.php';

date_default_timezone_set('Europe/Zurich');

db();

$result = send_inactivity_reminders();

echo sprintf(
    "[%s] Erinnerungen versendet: %d, fehlgeschlagen: %d\n",
    date('Y-m-d H:i:s'),
    $result['sent'],
    $result['failed']
);
