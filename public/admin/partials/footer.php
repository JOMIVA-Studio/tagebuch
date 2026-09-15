<?php if (!defined('APP_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
      </div>
    </main>
  </div>
</div>
<script>
function toggleSidebar(open) {
  document.getElementById('sidebar').classList.toggle('open', open);
  document.getElementById('sidebarBackdrop').classList.toggle('open', open);
}
</script>
</body>
</html>
