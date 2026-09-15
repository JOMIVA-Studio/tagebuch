<?php
declare(strict_types=1);

/**
 * Auth fuer normale App-User. Eigener Session-Cookie-Name, damit ein
 * gleichzeitig eingeloggter Admin (siehe admin_auth.php) im selben
 * Browser davon komplett unabhaengig ist.
 */
function user_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('tagebuch_user_sess');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $user = null;
    if ($user === null) {
        $stmt = db()->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => $_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
        if ($user === null) {
            unset($_SESSION['user_id']);
        }
    }
    return $user;
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        redirect('/login.php');
    }
    return $user;
}

function login_user(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    touch_user_activity($userId);
}

function logout_user(): void
{
    unset($_SESSION['user_id']);
    session_regenerate_id(true);
}

function touch_user_activity(int $userId): void
{
    $stmt = db()->prepare('UPDATE users SET last_activity_at = NOW() WHERE id = :id');
    $stmt->execute(['id' => $userId]);
}

/** Mindestens ein Ziel ist Pflicht: prueft, ob der User bereits eines hat. */
function user_has_goal(int $userId): bool
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM goals WHERE user_id = :id');
    $stmt->execute(['id' => $userId]);
    return (int) $stmt->fetchColumn() > 0;
}
