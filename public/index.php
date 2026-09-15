<?php
require __DIR__ . '/../src/bootstrap.php';

redirect(current_user() ? '/dashboard.php' : '/login.php');
