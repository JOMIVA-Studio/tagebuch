<?php
declare(strict_types=1);

function app_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }
    return $config;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(400);
        die('Ungueltiges oder abgelaufenes Formular (CSRF). Bitte Seite neu laden und erneut versuchen.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

/**
 * Schlanke Inline-SVG-Icons fuer die Sidebar-Navigation (Strich-Stil,
 * 20x20, currentColor) - keine externe Icon-Bibliothek noetig.
 */
function icon(string $name): string
{
    $paths = [
        'calendar' => '<rect x="3" y="4.5" width="14" height="13" rx="2"/><path d="M3 8.5h14M7 2.5v3M13 2.5v3"/><circle cx="7" cy="12" r="1"/><circle cx="10" cy="12" r="1"/><circle cx="13" cy="12" r="1"/>',
        'target'   => '<circle cx="10" cy="10" r="7"/><circle cx="10" cy="10" r="3.6"/><circle cx="10" cy="10" r="0.6" fill="currentColor"/>',
        'user'     => '<circle cx="10" cy="6.8" r="3.3"/><path d="M3.5 17c1-3.4 4-5 6.5-5s5.5 1.6 6.5 5"/>',
        'logout'   => '<path d="M8 17H4.5A1.5 1.5 0 0 1 3 15.5v-11A1.5 1.5 0 0 1 4.5 3H8"/><path d="M13 13.5 17 10l-4-3.5M17 10H8"/>',
        'grid'     => '<rect x="3" y="3" width="6" height="6" rx="1.2"/><rect x="11" y="3" width="6" height="6" rx="1.2"/><rect x="3" y="11" width="6" height="6" rx="1.2"/><rect x="11" y="11" width="6" height="6" rx="1.2"/>',
        'list'     => '<path d="M7.5 5h9M7.5 10h9M7.5 15h9"/><circle cx="3.5" cy="5" r="1"/><circle cx="3.5" cy="10" r="1"/><circle cx="3.5" cy="15" r="1"/>',
        'mail'     => '<rect x="3" y="4.5" width="14" height="11" rx="2"/><path d="M3.5 5.5 10 11l6.5-5.5"/>',
        'server'   => '<rect x="3" y="3.5" width="14" height="5" rx="1.5"/><rect x="3" y="11.5" width="14" height="5" rx="1.5"/><circle cx="6.2" cy="6" r="0.8" fill="currentColor"/><circle cx="6.2" cy="14" r="0.8" fill="currentColor"/>',
        'bell'     => '<path d="M5 14.5V9a5 5 0 0 1 10 0v5.5l1.3 1.8H3.7Z"/><path d="M8.2 17.3a1.8 1.8 0 0 0 3.6 0"/>',
        'lock'     => '<rect x="4.5" y="9" width="11" height="8" rx="1.8"/><path d="M6.7 9V6.3a3.3 3.3 0 0 1 6.6 0V9"/>',
        'home'     => '<path d="M3 9.5 10 3.5l7 6"/><path d="M5 8v8h10V8"/><path d="M8 16v-4.5h4V16"/>',
        'droplet'  => '<path d="M10 2.8s5.5 6.4 5.5 10.2a5.5 5.5 0 0 1-11 0C4.5 9.2 10 2.8 10 2.8Z"/>',
        'moon'     => '<path d="M16.5 12.6A6.8 6.8 0 0 1 7.4 3.5a7 7 0 1 0 9.1 9.1Z"/>',
        'quote'    => '<path d="M4 12V8.5A2.5 2.5 0 0 1 6.5 6H7"/><path d="M4 12h3v3.5A1.5 1.5 0 0 1 5.5 17H4"/><path d="M12 12V8.5A2.5 2.5 0 0 1 14.5 6H15"/><path d="M12 12h3v3.5a1.5 1.5 0 0 1-1.5 1.5H12"/>',
        'sun'      => '<circle cx="10" cy="10" r="3.6"/><path d="M10 2.5v2M10 15.5v2M17.5 10h-2M4.5 10h-2M15.3 4.7l-1.4 1.4M6.1 13.9l-1.4 1.4M15.3 15.3l-1.4-1.4M6.1 6.1 4.7 4.7"/>',
    ];
    $body = $paths[$name] ?? $paths['grid'];
    return '<svg class="nav-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $body . '</svg>';
}
