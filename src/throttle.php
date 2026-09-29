<?php
declare(strict_types=1);

/**
 * Bremst wiederholte Fehlversuche (Login, Passwort vergessen) pro IP und Kennung:
 * nach 5 Fehlversuchen (bzw. 30 pro IP) innerhalb von 15 Minuten wird gesperrt.
 */
const THROTTLE_MAX_ATTEMPTS = 5;
const THROTTLE_MAX_ATTEMPTS_PER_IP = 30;
const THROTTLE_WINDOW_MINUTES = 15;

function throttle_too_many(string $scope, string $identifier): bool
{
    $window = THROTTLE_WINDOW_MINUTES;
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM login_attempts
         WHERE scope = :scope AND identifier = :identifier AND ip = :ip AND attempted_at > DATE_SUB(NOW(), INTERVAL $window MINUTE)"
    );
    $stmt->execute(['scope' => $scope, 'identifier' => throttle_normalize($identifier), 'ip' => throttle_ip()]);
    if ((int) $stmt->fetchColumn() >= THROTTLE_MAX_ATTEMPTS) {
        return true;
    }

    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM login_attempts
         WHERE scope = :scope AND ip = :ip AND attempted_at > DATE_SUB(NOW(), INTERVAL $window MINUTE)"
    );
    $stmt->execute(['scope' => $scope, 'ip' => throttle_ip()]);
    return (int) $stmt->fetchColumn() >= THROTTLE_MAX_ATTEMPTS_PER_IP;
}

function throttle_hit(string $scope, string $identifier): void
{
    $stmt = db()->prepare('INSERT INTO login_attempts (scope, identifier, ip) VALUES (:scope, :identifier, :ip)');
    $stmt->execute(['scope' => $scope, 'identifier' => throttle_normalize($identifier), 'ip' => throttle_ip()]);

    // Alte Einträge gelegentlich aufräumen
    if (random_int(1, 50) === 1) {
        db()->exec('DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
    }
}

function throttle_clear(string $scope, string $identifier): void
{
    $stmt = db()->prepare('DELETE FROM login_attempts WHERE scope = :scope AND identifier = :identifier AND ip = :ip');
    $stmt->execute(['scope' => $scope, 'identifier' => throttle_normalize($identifier), 'ip' => throttle_ip()]);
}

function throttle_message(): string
{
    return 'Zu viele Versuche. Bitte warte ' . THROTTLE_WINDOW_MINUTES . ' Minuten und versuche es dann erneut.';
}

function throttle_normalize(string $identifier): string
{
    return mb_substr(strtolower(trim($identifier)), 0, 190);
}

function throttle_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli');
}
