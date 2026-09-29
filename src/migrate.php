<?php
declare(strict_types=1);

/**
 * Führt alle noch nicht angewendeten Dateien aus database/migrations/ aus.
 * Wird aus db() aufgerufen, damit sich die Datenbank selbstständig aktualisiert.
 *
 * Damit das nicht bei jedem Seitenaufruf Datenbank-Abfragen kostet, merkt sich
 * storage/cache/migrations.txt den zuletzt geprüften Stand. Nur wenn neue
 * Migrationsdateien dazukommen, wird die Datenbank geprüft – unter einer
 * MySQL-Sperre, damit zwei gleichzeitige Aufrufe nichts doppelt ausführen.
 */
function run_pending_migrations(PDO $pdo): void
{
    $migrationsDir = __DIR__ . '/../database/migrations';
    $files = glob($migrationsDir . '/*.sql') ?: [];
    sort($files, SORT_STRING);
    $versions = array_map('basename', $files);

    $cacheFile = __DIR__ . '/../storage/cache/migrations.txt';
    $fingerprint = implode("\n", $versions);
    if (is_file($cacheFile) && file_get_contents($cacheFile) === $fingerprint) {
        return;
    }

    $locked = (int) $pdo->query("SELECT GET_LOCK('tagebuch_migrations', 30)")->fetchColumn() === 1;
    if (!$locked) {
        throw new RuntimeException('Datenbank-Migrationen laufen gerade in einem anderen Aufruf. Bitte Seite neu laden.');
    }

    try {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                version    VARCHAR(191) PRIMARY KEY,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $applied = array_flip($pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN));

        foreach ($files as $file) {
            $version = basename($file);
            if (isset($applied[$version])) {
                continue;
            }

            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new RuntimeException("Migration $version konnte nicht gelesen werden.");
            }

            foreach (split_sql_statements($sql) as $statement) {
                $pdo->exec($statement);
            }

            $stmt = $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (:version)');
            $stmt->execute(['version' => $version]);
        }
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('tagebuch_migrations')");
    }

    @file_put_contents($cacheFile, $fingerprint, LOCK_EX);
}

/**
 * Zerlegt eine SQL-Datei in einzelne Statements. Semikolons innerhalb von
 * Strings ('…', "…", `…`) und Kommentaren (-- …, # …, / * … * /) werden ignoriert.
 */
function split_sql_statements(string $sql): array
{
    $statements = [];
    $current = '';
    $length = strlen($sql);
    $quote = null;

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $sql[$i + 1] ?? '';

        if ($quote !== null) {
            $current .= $char;
            if ($char === '\\' && $quote !== '`') {
                $current .= $next;
                $i++;
            } elseif ($char === $quote) {
                if ($next === $quote) {
                    $current .= $next;
                    $i++;
                } else {
                    $quote = null;
                }
            }
            continue;
        }

        if ($char === "'" || $char === '"' || $char === '`') {
            $quote = $char;
            $current .= $char;
            continue;
        }
        if (($char === '-' && $next === '-') || $char === '#') {
            $end = strpos($sql, "\n", $i);
            $i = $end === false ? $length : $end;
            $current .= "\n";
            continue;
        }
        if ($char === '/' && $next === '*') {
            $end = strpos($sql, '*/', $i + 2);
            $i = $end === false ? $length : $end + 1;
            continue;
        }
        if ($char === ';') {
            if (trim($current) !== '') {
                $statements[] = trim($current);
            }
            $current = '';
            continue;
        }
        $current .= $char;
    }

    if (trim($current) !== '') {
        $statements[] = trim($current);
    }
    return $statements;
}
