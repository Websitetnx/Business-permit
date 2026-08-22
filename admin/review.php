<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/layout.php';
$user = require_role('admin');
$pdo = db();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'application_id', FILTER_VALIDATE_INT);
if (!$id) { http_response_code(404); exit('Application not found.'); }

$statement = $pdo->prepare('SELECT a.*, b.business_name, b.business_type, b.organization_type, b.tin, b.contact, b.email, b.address, u.name owner_name FROM applications a JOIN businesses b ON b.id = a.business_id JOIN users u ON u.id = a.user_id WHERE a.id = ?');
$statement->execute([$id]);
$application = $statement->fetch();
if (!$application) { http_response_code(404); exit('Application not found.'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $decision = (string) ($_POST['decision'] ?? '');
    $notes = trim((string) ($_POST['admin_notes'] ?? ''));
    $allowed = ['For Review', 'Needs Revision', 'Approved', 'Released', 'Rejected'];
    if (!in_array($decision, $allowed, true)) $errors[] = 'Select a valid decision.';
    if (in_array($decision, ['Needs Revision', 'Rejected'], true) && strlen($notes) < 5) $errors[] = 'Explain what the applicant must correct or why the application was rejected.';
    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $permitNumber = $application['permit_number'];
            if (in_array($decision, ['Approved', 'Released'], true) && !$permitNumber) $permitNumber = create_permit_number($pdo);
            $stage = application_stage($decision);
            $update = $pdo->prepare('UPDATE applications SET status = ?, stage = ?, admin_notes = ?, permit_number = ?, reviewed_at = NOW(), approved_at = CASE WHEN ? IN (\'Approved\', \'Released\') THEN COALESCE(approved_at, NOW()) ELSE approved_at END WHERE id = ?');
            $update->execute([$decision, $stage, $notes ?: null, $permitNumber ?: null, $decision, $id]);
            record_status($pdo, (int) $id, $decision, (int) $user['id'], $notes);
            $message = 'Application ' . $application['reference'] . ' was updated to ' . $decision . '.';
            $notice = $pdo->prepare('INSERT INTO notifications (user_id, application_id, message) VALUES (?, ?, ?)');
            $notice->execute([$application['user_id'], $id, $message]);
            audit($pdo, (int) $user['id'], 'update_status_' . strtolower(str_replace(' ', '_', $decision)), 'application', (int) $id);
            $pdo->commit();
            flash('success', 'The application status was updated to ' . $decision . '.');
            redirect('admin/review.php?id=' . $id);
        } catch (Throwable) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'The review decision could not be saved.';
        }
    }
}

$documents = $pdo->prepare('SELECT id, document_type, original_name, file_size FROM application_documents WHERE application_id = ? ORDER BY id');
$documents->execute([$id]);
$definitions = document_definitions();
render_app_header('Review Application', 'review');
?>
<div class="section-heading"><div><p class="eyebrow"><?= e($application['reference']) ?></p><h2><?= e($application['business_name']) ?></h2><p class="muted"><?= e($application['owner_name']) ?> · <?= e($application['application_type']) ?> application</p></div><span class="status <?= e(status_class($application['status'])) ?>"><?= e($application['status']) ?></span></div>
<?php if ($errors): ?><div class="form-alert form-alert-error"><ul><?php foreach ($errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="content-grid review-layout"><article class="panel"><div class="panel-header"><div><p class="eyebrow">Applicant record</p><h3>Business information</h3></div><a href="<?= e(url('application.php?id=' . $id)) ?>">Full timeline</a></div><div class="detail-list detail-list-padded"><div><small>Applicant</small><strong><?= e($application['owner_name']) ?></strong></div><div><small>TIN</small><strong><?= e($application['tin']) ?></strong></div><div><small>Business type</small><strong><?= e($application['business_type']) ?></strong></div><div><small>Organization</small><strong><?= e($application['organization_type']) ?></strong></div><div><small>Contact</small><strong><?= e($application['contact']) ?></strong></div><div><small>Email</small><strong><?= e($application['email']) ?></strong></div><div class="detail-wide"><small>Address</small><strong><?= e($application['address']) ?></strong></div></div></article>
<aside class="panel decision-panel"><div class="panel-header"><div><p class="eyebrow">LGU decision</p><h3>Update application</h3></div></div><form method="post" class="decision-form"><?= csrf_field() ?><input type="hidden" name="application_id" value="<?= (int) $id ?>"><label class="field">Decision<select name="decision" required><?php foreach (['For Review', 'Needs Revision', 'Approved', 'Released', 'Rejected'] as $option): ?><option <?= $application['status'] === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select></label><label class="field">BPLO notes<textarea name="admin_notes" rows="6" placeholder="Instructions or decision notes"><?= e($_POST['admin_notes'] ?? $application['admin_notes']) ?></textarea></label><button class="button" type="submit">Save decision</button></form></aside></div>
<article class="panel document-panel"><div class="panel-header"><div><p class="eyebrow">Document verification</p><h3>Submitted requirements</h3></div></div><div class="document-list"><?php foreach ($documents->fetchAll() as $document): $label = $definitions[$document['document_type']][0] ?? $document['document_type']; ?><a href="<?= e(url('document.php?id=' . $document['id'])) ?>" target="_blank" rel="noopener"><span class="document-icon">▧</span><span><strong><?= e($label) ?></strong><small><?= e($document['original_name']) ?> · <?= e(number_format((int) $document['file_size'] / 1024, 1)) ?> KB</small></span><em>Open →</em></a><?php endforeach; ?></div></article>
<div class="form-actions"><a class="button button-secondary" href="index.php#queue">← Back to review queue</a></div>
<?php render_app_footer(); ?>
