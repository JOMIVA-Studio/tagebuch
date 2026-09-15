<?php
if (!defined('APP_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}
$appName = app_config()['app_name'];
$admin = current_admin();
$currentScript = basename($_SERVER['SCRIPT_NAME']);
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
<header class="topbar admin-topbar">
  <div class="container topbar-inner">
    <a href="/admin/" class="logo">
      <span class="logo-ring"><span>A</span></span>
      <span class="logo-word">Administration</span>
    </a>
    <button class="nav-toggle" type="button" aria-label="Menu" onclick="document.getElementById('adminNav').classList.toggle('open')"></button>
    <nav class="main-nav" id="adminNav">
      <?php if ($admin): ?>
        <a href="/admin/index.php" class="<?= $currentScript === 'index.php' ? 'active' : '' ?>">Uebersicht</a>
        <a href="/admin/questions.php" class="<?= $currentScript === 'questions.php' ? 'active' : '' ?>">Fragen</a>
        <a href="/admin/templates.php" class="<?= $currentScript === 'templates.php' ? 'active' : '' ?>">Mail-Vorlagen</a>
        <a href="/admin/smtp.php" class="<?= $currentScript === 'smtp.php' ? 'active' : '' ?>">SMTP</a>
        <a href="/admin/reminders.php" class="<?= $currentScript === 'reminders.php' ? 'active' : '' ?>">Erinnerungen</a>
        <a href="/admin/password.php" class="<?= $currentScript === 'password.php' ? 'active' : '' ?>">Passwort</a>
        <a href="/admin/logout.php" class="btn btn-ghost btn-sm">Abmelden</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main>
  <div class="container" style="padding-block: 28px 20px;">
    <?php foreach (get_flashes() as $flash): ?>
      <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endforeach; ?>
