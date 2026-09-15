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
 */
$appName = app_config()['app_name'];
$loggedInUser = current_user();
$currentScript = basename($_SERVER['SCRIPT_NAME']);
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
<header class="topbar">
  <div class="container topbar-inner">
    <a href="/<?= $loggedInUser ? 'dashboard.php' : '' ?>" class="logo">
      <span class="logo-ring"><span>A</span></span>
      <span class="logo-word"><?= e($appName) ?></span>
    </a>

    <button class="nav-toggle" type="button" aria-label="Menu" onclick="document.getElementById('mainNav').classList.toggle('open')"></button>

    <nav class="main-nav" id="mainNav">
      <?php if ($loggedInUser): ?>
        <a href="/dashboard.php" class="<?= $currentScript === 'dashboard.php' ? 'active' : '' ?>">Kalender</a>
        <a href="/goals.php" class="<?= $currentScript === 'goals.php' || $currentScript === 'goal.php' ? 'active' : '' ?>">Ziele</a>
        <a href="/profile.php" class="<?= $currentScript === 'profile.php' ? 'active' : '' ?>">Profil</a>
        <a href="/logout.php" class="btn btn-ghost btn-sm">Abmelden</a>
      <?php else: ?>
        <a href="/login.php" class="<?= $currentScript === 'login.php' ? 'active' : '' ?>">Anmelden</a>
        <a href="/register.php" class="btn btn-primary btn-sm">Registrieren</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main>
<?php if (!empty($heroTone)): ?>
  <div class="page-hero tone-<?= e($heroTone) ?>">
    <div class="container">
      <?php if (!empty($heroBadge)): ?><span class="badge on-color"><?= e($heroBadge) ?></span><?php endif; ?>
      <h1><?= e($heroTitle ?? '') ?></h1>
      <?php if (!empty($heroSubtitle)): ?><p><?= e($heroSubtitle) ?></p><?php endif; ?>
    </div>
  </div>
<?php endif; ?>
  <div class="container" style="padding-bottom: 20px;">
    <?php foreach (get_flashes() as $flash): ?>
      <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endforeach; ?>
