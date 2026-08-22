<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if (!current_user()) {
    redirect('login.php');
}
redirect(current_user()['role'] === 'admin' ? 'admin/index.php' : 'dashboard.php');
