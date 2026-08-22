<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
$user = require_role('applicant');
$pdo = db();

$countStatement = $pdo->prepare("SELECT COUNT(*) total,
    SUM(status = 'For Review') for_review,
    SUM(status IN ('Approved','Released')) approved,
    SUM(status = 'Needs Revision') needs_revision
    FROM applications WHERE user_id = ?");
$countStatement->execute([$user['id']]);
$counts = $countStatement->fetch() ?: [];

$recentStatement = $pdo->prepare('SELECT a.id, a.reference, a.permit_number, a.application_type, a.status, a.submitted_at, b.business_name FROM applications a JOIN businesses b ON b.id = a.business_id WHERE a.user_id = ? ORDER BY a.submitted_at DESC LIMIT 5');
$recentStatement->execute([$user['id']]);
$recent = $recentStatement->fetchAll();

render_app_header('Dashboard', 'dashboard');
?>
<div class="welcome-card">
  <div><p class="eyebrow light">Welcome back, <?= e($user['name']) ?></p><h2>Manage your permits without the long lines.</h2><p>Submit requirements, monitor progress, and receive updates in one secure place.</p><a class="button button-light" href="apply.php">Start new application <span>→</span></a></div>
  <div class="welcome-art" aria-hidden="true"><span>✓</span></div>
</div>

<div class="section-heading"><div><p class="eyebrow">At a glance</p><h2>Your permit activity</h2></div><p class="muted"><?= e(date('F j, Y')) ?></p></div>
<div class="stat-grid">
  <article class="stat-card"><span>▤</span><strong><?= (int) ($counts['total'] ?? 0) ?></strong><small>Total applications</small></article>
  <article class="stat-card"><span>◷</span><strong><?= (int) ($counts['for_review'] ?? 0) ?></strong><small>Under review</small></article>
  <article class="stat-card"><span>✓</span><strong><?= (int) ($counts['approved'] ?? 0) ?></strong><small>Approved permits</small></article>
  <article class="stat-card"><span>!</span><strong><?= (int) ($counts['needs_revision'] ?? 0) ?></strong><small>Needs attention</small></article>
</div>

<div class="content-grid">
  <article class="panel">
    <div class="panel-header"><div><p class="eyebrow">Applications</p><h3>Recent activity</h3></div><a href="track.php">Track by reference</a></div>
    <?php if (!$recent): ?><p class="empty-state">No applications yet. Start your first application when your documents are ready.</p><?php endif; ?>
    <?php foreach ($recent as $application): ?>
      <a class="application-item application-link" href="application.php?id=<?= (int) $application['id'] ?>">
        <div class="application-icon"><?= $application['application_type'] === 'Renewal' ? '↻' : '＋' ?></div>
        <div class="application-copy"><strong><?= e($application['business_name']) ?></strong><small><?= e($application['reference']) ?> · <?= e(date('M j, Y', strtotime($application['submitted_at']))) ?></small></div>
        <span class="status <?= e(status_class($application['status'])) ?>"><?= e($application['status']) ?></span>
      </a>
    <?php endforeach; ?>
  </article>
  <aside class="panel checklist-panel">
    <div class="panel-header"><div><p class="eyebrow">Before you apply</p><h3>Standard requirements</h3></div></div>
    <ul class="checklist"><li><span>✓</span> DTI / SEC / CDA registration</li><li><span>✓</span> BFP application form</li><li><span>✓</span> BFP questionnaire</li><li><span>✓</span> Signed consent form</li></ul>
    <p class="hint">You will also need an Occupancy Permit or the affidavit alternative. Accepted files: PDF, JPG, or PNG up to 5 MB.</p>
  </aside>
</div>
<?php render_app_footer(); ?>
