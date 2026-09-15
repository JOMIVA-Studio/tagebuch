<?php if (!defined('APP_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
  </div>
</main>
<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div>
        <a href="/" class="logo">
          <span class="logo-ring"><span>A</span></span>
          <span class="logo-word"><?= e(app_config()['app_name']) ?></span>
        </a>
        <p class="footer-meta mt-sm">Dein persoenliches Tagebuch mit Zielen und Erinnerungen.</p>
      </div>
    </div>
    <div class="footer-bottom">
      <span>&copy; <?= date('Y') ?> <?= e(app_config()['app_name']) ?></span>
      <span>Lokale Version</span>
    </div>
  </div>
</footer>
</body>
</html>
