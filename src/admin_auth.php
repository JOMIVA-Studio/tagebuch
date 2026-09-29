<?php
declare(strict_types=1);

/**
 * Auth für den Admin-Bereich. Völlig separate Session (eigener Cookie-
 * Name "tagebuch_admin_sess") und eigene Tabelle "admins" - unabhängig
 * vom normalen User-Login in auth.php. Ein Admin ist kein User und
 * umgekehrt.
 */
function admin_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('tagebuch_admin_sess');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/admin/',
        'secure'   => request_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function current_admin(): ?array
{
    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    static $admin = null;
    if ($admin === null) {
        $stmt = db()->prepare('SELECT * FROM admins WHERE id = :id');
        $stmt->execute(['id' => $_SESSION['admin_id']]);
        $admin = $stmt->fetch() ?: null;
        if ($admin === null) {
            unset($_SESSION['admin_id']);
        }
    }
    return $admin;
}

function require_admin(): array
{
    $admin = current_admin();
    if ($admin === null) {
        redirect('/admin/login.php');
    }
    // Solange das Standard-/Initialpasswort aktiv ist, nur die Passwort-Seite zulassen.
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (!empty($admin['must_change_password']) && $path !== '/admin/password.php') {
        flash('info', 'Bitte vergib zuerst ein neues, persönliches Passwort.');
        redirect('/admin/password.php');
    }
    return $admin;
}

function login_admin(int $adminId): void
{
    session_regenerate_id(true);
    $_SESSION['admin_id'] = $adminId;
}

function logout_admin(): void
{
    unset($_SESSION['admin_id']);
    session_regenerate_id(true);
}
