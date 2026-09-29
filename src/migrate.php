<?php
declare(strict_types=1);

/**
 * Führt alle noch nicht angewendeten Dateien aus database/migrations/ aus.
 * Wird bei jedem Request aus bootstrap.php aufgerufen, damit die Datenbank
 * sich selbstständig aktualisiert - es sind nie manuelle SQL-Schritte nötig.
 */
function run_pending_migrations(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations (
            version    VARCHAR(191) PRIMARY KEY,
            applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $applied = $pdo->query('SELECT version FROM schema_migrations')
        ->fetchAll(PDO::FETCH_COLUMN);
    $applied = array_flip($applied);

    $migrationsDir = __DIR__ . '/../database/migrations';
    $files = glob($migrationsDir . '/*.sql') ?: [];
    sort($files, SORT_STRING);

    foreach ($files as $file) {
        $version = basename($file);
        if (isset($applied[$version])) {
            continue;
        }

        $sql = file_get_contents($file);
        if ($sql === false) {
            continue;
        }

        foreach (split_sql_statements($sql) as $statement) {
            $pdo->exec($statement);
        }

        $stmt = $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (:version)');
        $stmt->execute(['version' => $version]);
    }
}

/**
 * Sehr einfacher SQL-Statement-Splitter: reicht für unsere eigenen,
 * kontrollierten Migrationsdateien (kein Stored-Procedure-Syntax etc.).
 */
function split_sql_statements(string $sql): array
{
    $statements = [];
    foreach (explode(";\n", $sql) as $chunk) {
        $chunk = trim($chunk);
        if ($chunk === '') {
            continue;
        }
        $statements[] = $chunk;
    }
    return $statements;
}
