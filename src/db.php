<?php
declare(strict_types=1);

require_once __DIR__ . '/migrate.php';

/**
 * Liefert eine verbundene PDO-Instanz. Legt die Datenbank an, falls sie noch
 * nicht existiert, und wendet danach automatisch alle offenen Migrationen an
 * (siehe src/migrate.php) - dadurch aktualisiert sich das Schema bei jedem
 * Aufruf von selbst, ohne dass manuell SQL ausgeführt werden muss.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $config = require __DIR__ . '/config.php';
        $db = $config['db'];

        $pdo = connect_and_prepare_database($db);
        run_pending_migrations($pdo);
    }

    return $pdo;
}

function connect_and_prepare_database(array $db): PDO
{
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $dsnWithDb = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $db['host'],
        $db['port'],
        $db['name'],
        $db['charset']
    );

    try {
        return new PDO($dsnWithDb, $db['user'], $db['pass'], $options);
    } catch (PDOException $e) {
        // Fehlercode 1049 = Unknown database -> automatisch anlegen und erneut verbinden.
        if ($e->errorInfo[1] !== 1049) {
            throw $e;
        }

        $dsnServer = sprintf('mysql:host=%s;port=%d;charset=%s', $db['host'], $db['port'], $db['charset']);
        $serverPdo = new PDO($dsnServer, $db['user'], $db['pass'], $options);
        $serverPdo->exec(sprintf(
            'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET %s COLLATE utf8mb4_unicode_ci',
            str_replace('`', '', $db['name']),
            $db['charset']
        ));

        return new PDO($dsnWithDb, $db['user'], $db['pass'], $options);
    }
}
