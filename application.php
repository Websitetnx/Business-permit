<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
$user = require_login();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(404);
    exit('Application not found.');
}

$sql = 'SELECT a.*, b.business_name, b.business_type, b.organization_type, b.tin, b.contact, b.email, b.address, u.name owner_name
        FROM applications a JOIN businesses b ON b.id = a.business_id JOIN users u ON u.id = a.user_id WHERE a.id = ?';
$params = [$id];
if ($user['role'] !== 'admin') {
    $sql .= ' AND a.user_id = ?';
    $params[] = $user['id'];
}
$statement = db()->prepare($sql);
$statement->execute($params);
$application = $statement->fetch();
if (!$application) {
    http_response_code(404);
    exit('Application not found.');
}

$documents = db()->prepare('SELECT id, document_type, original_name, mime_type, file_size, uploaded_at FROM application_documents WHERE application_id = ? ORDER BY uploaded_at');
$documents->execute([$id]);
$history = db()->prepare('SELECT h.status, h.notes, h.created_at, u.name changed_by_name FROM application_status_history h LEFT JOIN users u ON u.id = h.changed_by WHERE h.application_id = ? ORDER BY h.created_at DESC, h.id DESC');
$history->execute([$id]);
$definitions = document_definitions();

render_app_header('Application Details', $user['role'] === 'admin' ? 'review' : 'track');
?>
<div class="section-heading"><div><p class="eyebrow"><?= e($application['reference']) ?></p><h2><?= e($application['business_name']) ?></h2><p class="muted"><?= e($application['application_type']) ?> application · Submitted <?= e(date('F j, Y', strtotime($application['submitted_at']))) ?></p></div><span class="status <?= e(status_class($application['status'])) ?>"><?= e($application['status']) ?></span></div>
<ol class="timeline panel timeline-panel">
  <?php foreach (['Submitted', 'Validation', 'Assessment', 'Permit release'] as $stage => $label): $step = $stage + 1; ?>
    <li class="<?= $step < (int) $application['stage'] ? 'done' : ($step === (int) $application['stage'] ? 'current' : '') ?>"><span><?= $step <= (int) $application['stage'] ? '✓' : $step ?></span><?= e($label) ?></li>
  <?php endforeach; ?>
</ol>
<?php if ($application['admin_notes']): ?><div class="form-alert <?= $application['status'] === 'Needs Revision' ? 'form-alert-error' : '' ?>"><strong>BPLO note:</strong> <?= nl2br(e($application['admin_notes'])) ?></div><?php endif; ?>

<div class="content-grid application-detail-grid">
  <article class="panel"><div class="panel-header"><div><p class="eyebrow">Business record</p><h3>Application information</h3></div></div><div class="detail-list detail-list-padded">
    <div><small>Applicant</small><strong><?= e($application['owner_name']) ?></strong></div><div><small>Application type</small><strong><?= e($application['application_type']) ?></strong></div>
    <div><small>Business type</small><strong><?= e($application['business_type']) ?></strong></div><div><small>Organization</small><strong><?= e($application['organization_type']) ?></strong></div>
    <div><small>TIN</small><strong><?= e($application['tin']) ?></strong></div><div><small>Permit number</small><strong><?= e($application['permit_number'] ?: 'Assigned after approval') ?></strong></div>
    <div><small>Contact</small><strong><?= e($application['contact']) ?></strong></div><div><small>Email</small><strong><?= e($application['email']) ?></strong></div>
    <div class="detail-wide"><small>Address</small><strong><?= e($application['address']) ?></strong></div>
  </div></article>
  <aside class="panel"><div class="panel-header"><div><p class="eyebrow">Status log</p><h3>Processing history</h3></div></div><div class="history-list">
    <?php foreach ($history->fetchAll() as $entry): ?><div><span class="history-dot"></span><p><strong><?= e($entry['status']) ?></strong><small><?= e(date('M j, Y g:i A', strtotime($entry['created_at']))) ?><?= $entry['changed_by_name'] ? ' · ' . e($entry['changed_by_name']) : '' ?></small><?php if ($entry['notes']): ?><em><?= e($entry['notes']) ?></em><?php endif; ?></p></div><?php endforeach; ?>
  </div></aside>
</div>

<article class="panel document-panel"><div class="panel-header"><div><p class="eyebrow">Submitted requirements</p><h3>Uploaded documents</h3></div><span class="document-count"><?= $documents->rowCount() ?> files</span></div><div class="document-list">
  <?php foreach ($documents->fetchAll() as $document): $label = $definitions[$document['document_type']][0] ?? $document['document_type']; ?>
    <a href="<?= e(url('document.php?id=' . $document['id'])) ?>" target="_blank" rel="noopener"><span class="document-icon">▧</span><span><strong><?= e($label) ?></strong><small><?= e($document['original_name']) ?> · <?= e(number_format((int) $document['file_size'] / 1024, 1)) ?> KB</small></span><em>View →</em></a>
  <?php endforeach; ?>
</div></article>
<?php if ($user['role'] === 'admin'): ?><div class="form-actions"><a class="button button-secondary" href="admin/index.php#queue">← Review queue</a><a class="button" href="admin/review.php?id=<?= (int) $application['id'] ?>">Review decision →</a></div><?php endif; ?>
<?php render_app_footer(); ?>
