<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
$user = require_role('applicant');
$reference = strtoupper(trim((string) ($_GET['reference'] ?? '')));
$result = null;
$searched = $reference !== '';
if ($searched) {
    $statement = db()->prepare('SELECT a.id, a.reference, a.application_type, a.status, a.stage, a.submitted_at, b.business_name FROM applications a JOIN businesses b ON b.id = a.business_id WHERE a.reference = ? AND a.user_id = ? LIMIT 1');
    $statement->execute([$reference, $user['id']]);
    $result = $statement->fetch();
}
render_app_header('Track Application', 'track');
?>
<div class="section-heading"><div><p class="eyebrow">Application tracker</p><h2>Know exactly where your permit stands</h2><p class="muted">Enter the reference issued after submission.</p></div></div>
<div class="panel lookup-panel"><form method="get" class="lookup-form"><label class="field"><span>Application reference</span><input name="reference" required value="<?= e($reference) ?>" placeholder="BPL-2026-12345"></label><button class="button" type="submit">Track status</button></form></div>
<?php if ($searched && !$result): ?><div class="panel result-card empty-state"><strong>No application found in your account.</strong><p>Check the reference and try again.</p></div><?php endif; ?>
<?php if ($result): ?><article class="panel result-card"><div class="record-summary"><div><p class="eyebrow"><?= e($result['reference']) ?></p><h3><?= e($result['business_name']) ?></h3><p class="muted"><?= e($result['application_type']) ?> application · Submitted <?= e(date('M j, Y', strtotime($result['submitted_at']))) ?></p></div><span class="status <?= e(status_class($result['status'])) ?>"><?= e($result['status']) ?></span></div><ol class="timeline"><?php foreach (['Submitted', 'Validation', 'Assessment', 'Permit release'] as $stage => $label): $step = $stage + 1; ?><li class="<?= $step < (int) $result['stage'] ? 'done' : ($step === (int) $result['stage'] ? 'current' : '') ?>"><span><?= $step <= (int) $result['stage'] ? '✓' : $step ?></span><?= e($label) ?></li><?php endforeach; ?></ol><div class="form-actions"><span></span><a class="button button-secondary" href="application.php?id=<?= (int) $result['id'] ?>">View complete record</a></div></article><?php endif; ?>
<?php render_app_footer(); ?>
