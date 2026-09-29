<?php
declare(strict_types=1);

/**
 * Sucht User, die länger als der eingestellte Schwellenwert inaktiv sind,
 * Erinnerungs-Mails aktiviert haben und in den letzten 24h noch keine
 * Erinnerung bekommen haben, und verschickt die "inactivity_reminder"-Vorlage.
 * Wird sowohl vom manuellen Admin-Button als auch vom Cron-Skript genutzt.
 *
 * @return array{sent:int, failed:int, skipped:int}
 */
function send_inactivity_reminders(): array
{
    $pdo = db();

    $thresholdDays = (int) ($pdo->query('SELECT inactivity_threshold_days FROM app_settings WHERE id = 1')->fetchColumn() ?: 7);

    $stmt = $pdo->prepare(
        "SELECT * FROM users
         WHERE reminder_emails_enabled = 1
           AND (last_activity_at IS NULL OR last_activity_at < DATE_SUB(NOW(), INTERVAL :days DAY))
           AND (last_reminder_sent_at IS NULL OR last_reminder_sent_at < DATE_SUB(NOW(), INTERVAL 1 DAY))"
    );
    $stmt->execute(['days' => $thresholdDays]);
    $candidates = $stmt->fetchAll();

    $result = ['sent' => 0, 'failed' => 0, 'skipped' => 0];

    foreach ($candidates as $user) {
        try {
            send_templated_mail('inactivity_reminder', $user);
            $update = $pdo->prepare('UPDATE users SET last_reminder_sent_at = NOW() WHERE id = :id');
            $update->execute(['id' => $user['id']]);
            $result['sent']++;
        } catch (Throwable $e) {
            $result['failed']++;
        }
    }

    return $result;
}
