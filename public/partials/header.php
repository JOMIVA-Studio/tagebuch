<?php
if (!defined('APP_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}
/**
 * Erwartet (optional), bevor diese Datei eingebunden wird:
 *   $pageTitle    string  Browser-Tab-Titel
 *   $heroTone     string  'green' | 'purple' | 'magenta'  -> zeigt eine farbige Kopfzeile
 *   $heroBadge    string  kleines Label ueber dem Titel
 *   $heroTitle    string  grosse Ueberschrift im Hero
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
    ['href' => 'dashboard.php', 'label' => 'Startseite', 'icon' => 'home'],
    ['href' => 'goals.php',     'label' => 'Ziele',      'icon' => 'target', 'alsoActive' => 'goal.php'],
    ['href' => 'tracker.php',   'label' => 'Tracker',    'icon' => 'droplet', 'alsoActive' => ['water.php', 'cycle.php']],
    ['href' => 'profile.php',   'label' => 'Profil',     'icon' => 'user'],
];
?><!doctype html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle ?? $appName) ?> · <?= e($appName) ?></title>
  <link rel="icon" href="/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<?php if ($usesSidebarShell): ?>
<div class="app-shell">
  <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleSidebar(false)"></div>
  <aside class="sidebar" id="sidebar">
    <a href="/dashboard.php" class="logo sidebar-logo">
      <img class="brand-logo brand-logo-md" src="/assets/img/logo-white.svg" alt="<?= e($appName) ?>">
    </a>

    <nav class="sidebar-nav">
      <?php foreach ($navItems as $item):
          $alsoActive = (array) ($item['alsoActive'] ?? []);
          $isActive = $currentScript === $item['href'] || in_array($currentScript, $alsoActive, true);
      ?>
        <a href="/<?= $item['href'] ?>" class="sidebar-link <?= $isActive ? 'active' : '' ?>">
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
      <button class="nav-toggle" type="button" aria-label="Menu" onclick="toggleSidebar(true)"></button>
      <a href="/dashboard.php" class="logo">
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
