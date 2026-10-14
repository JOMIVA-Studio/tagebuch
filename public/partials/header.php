<?php
if (!defined('APP_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}
/**
 * Erwartet (optional), bevor diese Datei eingebunden wird:
 *   $pageTitle    string  Browser-Tab-Titel
 *   $heroTone     string  'green' | 'purple' | 'magenta'  -> zeigt eine farbige Kopfzeile
 *   $heroBadge    string  kleines Label über dem Titel
 *   $heroTitle    string  grosse Überschrift im Hero
 *   $heroSubtitle string  optionaler Untertext
 *
 * Eingeloggte User bekommen die Sidebar-Navigation (angelehnt an das
 * Justinmind-Referenz-Template), ausgeloggte Besucher (Login/Registrieren)
 * eine schlanke, zentrierte Seite ohne Sidebar.
 */
$appName = app_config()['app_name'];
$loggedInUser = current_user();
$currentScript = basename($_SERVER['SCRIPT_NAME']);
$usesSidebarShell = $loggedInUser !== null;

$navItems = [
    ['href' => 'diary.php', 'label' => 'Tagebuch', 'icon' => 'home', 'alsoActive' => 'day.php'],
    ['href' => 'goals.php',     'label' => 'Ziele',      'icon' => 'target', 'alsoActive' => ['goal.php', 'vision.php']],
    ['href' => 'tracker.php',   'label' => 'Tracker',    'icon' => 'droplet', 'alsoActive' => ['water.php', 'cycle.php', 'sleep.php']],
    ['href' => 'profile.php',   'label' => 'Profil',     'icon' => 'user'],
];
?><!doctype html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle ?? $appName) ?> · <?= e($appName) ?></title>
  <link rel="icon" href="/favicon.svg" type="image/svg+xml">
<?php $appIcon = app_icon_key($loggedInUser['app_icon'] ?? null); ?>
  <link rel="apple-touch-icon" href="/assets/icons/icon-<?= e($appIcon) ?>-180.png">
  <link rel="manifest" href="/manifest.php?i=<?= e($appIcon) ?>">
  <meta name="theme-color" content="<?= e(app_icon_choices()[$appIcon][1]) ?>">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="Tagebuch">
  <link rel="stylesheet" href="/assets/css/style.css?v=<?= substr(hash_file('sha256', __DIR__ . '/../assets/css/style.css'),0,12) ?>">
  <link rel="stylesheet" href="/assets/css/consistency.css?v=<?= substr(hash_file('sha256', __DIR__ . '/../assets/css/consistency.css'),0,12) ?>">
</head>
<body class="<?= in_array($currentScript, ['goals.php', 'goal.php', 'vision.php'], true) ? 'theme-goals' : (in_array($currentScript, ['tracker.php', 'water.php', 'cycle.php', 'sleep.php'], true) ? 'theme-tracker' : 'theme-main') ?><?= in_array($currentScript, ['dashboard.php', 'profile.php'], true) ? ' home-neutral' : '' ?><?= $currentScript === 'profile.php' ? ' profile-neutral' : '' ?>">
<?php if ($usesSidebarShell): ?>
<div class="app-shell">
  <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleSidebar(false)"></div>
  <aside class="sidebar" id="sidebar">
    <a href="/dashboard.php" class="logo sidebar-logo" aria-label="Startseite">
      <img class="brand-logo brand-logo-md" src="/assets/img/<?= in_array($currentScript, ['dashboard.php', 'profile.php'], true) ? 'logo-black.svg' : 'logo-white.svg' ?>" alt="<?= e($appName) ?>">
    </a>

    <nav class="sidebar-nav" aria-label="Hauptnavigation">
      <?php foreach ($navItems as $item):
          $alsoActive = (array) ($item['alsoActive'] ?? []);
          $isActive = $currentScript === $item['href'] || in_array($currentScript, $alsoActive, true);
      ?>
        <a href="/<?= $item['href'] ?>" class="sidebar-link <?= $isActive ? 'active' : '' ?>" <?= $isActive ? 'aria-current="page"' : '' ?>>
          <?= icon($item['icon']) ?><span><?= e($item['label']) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="sidebar-spacer"></div>

    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="sidebar-user-avatar"><?= e(mb_strtoupper(mb_substr($loggedInUser['name'], 0, 1))) ?></div>
        <div class="sidebar-user-info">
          <div class="sidebar-user-name"><?= e($loggedInUser['name']) ?></div>
          <div class="sidebar-user-email"><?= e($loggedInUser['email']) ?></div>
        </div>
      </div>
      <a href="/logout.php" class="sidebar-link"><?= icon('logout') ?><span>Abmelden</span></a>
    </div>
  </aside>

  <div class="app-main">
    <div class="app-topbar">
      <button class="nav-toggle" type="button" aria-label="Menü" onclick="toggleSidebar(true)"></button>
      <a href="/dashboard.php" class="logo" aria-label="Startseite">
        <img class="brand-logo brand-logo-sm" src="/assets/img/logo-black.svg" alt="<?= e($appName) ?>">
      </a>
    </div>
    <main>
<?php else: ?>
<main>
  <div class="container" style="max-width: 460px; padding-top: 40px; padding-bottom: 0; text-align: center;">
    <a href="/login.php" class="logo" style="justify-content: center; margin-bottom: 28px;">
      <img class="brand-logo brand-logo-lg" style="margin-inline: auto;" src="/assets/img/logo-black.svg" alt="<?= e($appName) ?>">
    </a>
  </div>
<?php endif; ?>
<?php if (!empty($heroTone)): ?>
  <div class="page-hero tone-<?= e($heroTone) ?>">
    <div class="container">
      <?php if (!empty($heroBadge)): ?><span class="badge on-color"><?= e($heroBadge) ?></span><?php endif; ?>
      <h1><?= e($heroTitle ?? '') ?></h1>
      <?php if (!empty($heroSubtitle)): ?><p><?= e($heroSubtitle) ?></p><?php endif; ?>
    </div>
  </div>
<?php endif; ?>
  <div class="container" style="padding-bottom: 20px; <?= $usesSidebarShell ? '' : 'max-width: 460px; padding-top: 0;' ?>">
    <?php foreach (get_flashes() as $flash): ?>
      <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endforeach; ?>
