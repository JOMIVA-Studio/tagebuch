<?php
require __DIR__ . '/../../src/admin_bootstrap.php';

logout_admin();
redirect('/admin/login.php');
