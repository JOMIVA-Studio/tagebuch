<?php if (!defined('APP_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
    <p class="legal-links small text-muted text-center"><a href="/datenschutz.php">Datenschutz</a></p>
  </div>
<?php if ($usesSidebarShell): ?>
    </main>
  </div>
</div>
<script>
function toggleSidebar(open) {
  document.getElementById('sidebar').classList.toggle('open', open);
  document.getElementById('sidebarBackdrop').classList.toggle('open', open);
}
</script>
<?php else: ?>
</main>
<?php endif; ?>
</body>
</html>
