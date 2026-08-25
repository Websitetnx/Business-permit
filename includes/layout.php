<?php
declare(strict_types=1);

function render_app_header(string $title, string $active): void
{
    $user = require_login();
    $isAdmin = $user['role'] === 'admin';
    $initials = implode('', array_map(static fn(string $part): string => strtoupper($part[0]), array_slice(preg_split('/\s+/', trim($user['name'])) ?: [], 0, 2)));
    $flashes = pull_flashes();
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="ERMIT web-based business permit management system.">
  <title><?= e($title) ?> | ERMIT</title>
  <link rel="stylesheet" href="<?= e(url('styles.css')) ?>">
  <link rel="icon" type="image/png" href="<?= e(url('assets/logo.png')) ?>">
</head>
<body>
  <div class="app-shell">
    <aside class="sidebar" id="sidebar" aria-label="Primary navigation">
      <a class="brand brand-logo-link" href="<?= e(url($isAdmin ? 'admin/index.php' : 'dashboard.php')) ?>">
        <img class="brand-logo" src="<?= e(url('assets/logo.png')) ?>" alt="ERMIT — Web-Based Business Permit Management System">
      </a>
      <nav>
        <p class="nav-label"><?= $isAdmin ? 'LGU administrator' : 'Applicant portal' ?></p>
        <?php if ($isAdmin): ?>
          <a class="<?= $active === 'admin-dashboard' ? 'active' : '' ?>" href="<?= e(url('admin/index.php')) ?>"><span>⌂</span> Overview</a>
          <a class="<?= $active === 'review' ? 'active' : '' ?>" href="<?= e(url('admin/index.php#queue')) ?>"><span>☷</span> Review queue</a>
          <a class="<?= $active === 'analytics' ? 'active' : '' ?>" href="<?= e(url('admin/analytics.php')) ?>"><span>⌁</span> AI analytics</a>
          <a class="<?= $active === 'payments' ? 'active' : '' ?>" href="<?= e(url('admin/payments.php')) ?>"><span>₱</span> Payments</a>
          <a class="<?= $active === 'users' ? 'active' : '' ?>" href="<?= e(url('admin/users.php')) ?>"><span>♙</span> User accounts</a>
        <?php else: ?>
          <a class="<?= $active === 'dashboard' ? 'active' : '' ?>" href="<?= e(url('dashboard.php')) ?>"><span>⌂</span> Dashboard</a>
          <a class="<?= $active === 'apply' ? 'active' : '' ?>" href="<?= e(url('apply.php')) ?>"><span>＋</span> New application</a>
          <a class="<?= $active === 'renew' ? 'active' : '' ?>" href="<?= e(url('renew.php')) ?>"><span>↻</span> Renew permit</a>
          <a class="<?= $active === 'track' ? 'active' : '' ?>" href="<?= e(url('track.php')) ?>"><span>◎</span> Track application</a>
          <a class="<?= $active === 'payments' ? 'active' : '' ?>" href="<?= e(url('payments.php')) ?>"><span>₱</span> Payments</a>
        <?php endif; ?>
        <a href="<?= e(url('logout.php')) ?>"><span>⇥</span> Sign out</a>
      </nav>
      <div class="sidebar-help"><span>?</span><div><strong>Need help?</strong><small>Contact your local<br>BPLO Help Desk</small></div></div>
    </aside>
    <main class="main-content">
      <header class="topbar">
        <button class="icon-button menu-button" id="menuButton" type="button" aria-label="Open navigation" aria-controls="sidebar">☰</button>
        <div><p class="eyebrow">City Government Services</p><h1><?= e($title) ?></h1></div>
        <div class="topbar-actions">
          <div class="avatar"><?= e($initials ?: 'U') ?></div>
          <div class="profile-copy"><strong><?= e($user['name']) ?></strong><small><?= $isAdmin ? 'Administrator' : 'Applicant' ?></small></div>
        </div>
      </header>
      <div id="toastRegion" class="toast-region" aria-live="polite">
        <?php foreach ($flashes as $message): ?>
          <div class="toast <?= $message['type'] === 'error' ? 'error-toast' : '' ?>"><?= e($message['message']) ?></div>
        <?php endforeach; ?>
      </div>
      <section class="page active">
    <?php
}

function render_app_footer(): void
{
    ?>
      </section>
    </main>
  </div>
  <script src="<?= e(url('app.js')) ?>"></script>
</body>
</html>
    <?php
}
