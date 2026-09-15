<?php
if (!defined('APP_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}
$appName = app_config()['app_name'];
$admin = current_admin();
$currentScript = basename($_SERVER['SCRIPT_NAME']);

$navItems = [
    ['href' => 'index.php',      'label' => 'Uebersicht',    'icon' => 'grid'],
    ['href' => 'questions.php',  'label' => 'Fragen',        'icon' => 'list'],
    ['href' => 'quotes.php',     'label' => 'Sprueche',      'icon' => 'quote'],
    ['href' => 'templates.php',  'label' => 'Mail-Vorlagen', 'icon' => 'mail', 'alsoActive' => 'template_edit.php'],
    ['href' => 'smtp.php',       'label' => 'SMTP',          'icon' => 'server'],
    ['href' => 'reminders.php',  'label' => 'Erinnerungen',  'icon' => 'bell'],
    ['href' => 'password.php',   'label' => 'Passwort',      'icon' => 'lock'],
];
?><!doctype html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle ?? 'Administration') ?> · <?= e($appName) ?> Admin</title>
  <link rel="icon" href="/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="app-shell">
  <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleSidebar(false)"></div>
  <aside class="sidebar admin-sidebar" id="sidebar">
    <a href="/admin/index.php" class="logo sidebar-logo" style="flex-direction: column; align-items: center; gap: 8px;">
      <img class="brand-logo brand-logo-md" src="/assets/img/logo-white.svg" alt="<?= e($appName) ?>">
      <span class="sidebar-admin-label">Administration</span>
    </a>

    <nav class="sidebar-nav">
      <?php foreach ($navItems as $item):
          $isActive = $currentScript === $item['href'] || (isset($item['alsoActive']) && $currentScript === $item['alsoActive']);
      ?>
        <a href="/admin/<?= $item['href'] ?>" class="sidebar-link <?= $isActive ? 'active' : '' ?>">
          <?= icon($item['icon']) ?><span><?= e($item['label']) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="sidebar-spacer"></div>

    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="sidebar-user-avatar"><?= e(mb_strtoupper(mb_substr($admin['username'], 0, 1))) ?></div>
        <div class="sidebar-user-info">
          <div class="sidebar-user-name"><?= e($admin['username']) ?></div>
          <div class="sidebar-user-email">Administration</div>
        </div>
      </div>
      <a href="/admin/logout.php" class="sidebar-link"><?= icon('logout') ?><span>Abmelden</span></a>
    </div>
  </aside>

  <div class="app-main">
    <div class="app-topbar">
      <button class="nav-toggle" type="button" aria-label="Menu" onclick="toggleSidebar(true)"></button>
      <a href="/admin/index.php" class="logo">
        <img class="brand-logo brand-logo-sm" src="/assets/img/logo-black.svg" alt="<?= e($appName) ?>">
      </a>
    </div>
    <main>
      <div class="container" style="padding-block: 28px 20px;">
        <?php foreach (get_flashes() as $flash): ?>
          <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endforeach; ?>
