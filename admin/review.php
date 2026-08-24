<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/layout.php';
$user = require_role('admin');
$pdo = db();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'application_id', FILTER_VALIDATE_INT);
if (!$id) { http_response_code(404); exit('Application not found.'); }

$statement = $pdo->prepare('SELECT a.*, b.business_name, b.business_type, b.organization_type, b.tin, b.contact, b.email, b.address, b.latitude, b.longitude, b.location_accuracy_m, b.location_captured_at, u.name owner_name FROM applications a JOIN businesses b ON b.id = a.business_id JOIN users u ON u.id = a.user_id WHERE a.id = ?');
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

$aiTablesReady = true;
try {
    $documents = $pdo->prepare('SELECT d.id, d.document_type, d.original_name, d.file_size, s.scan_status, s.detected_document_type, s.matches_expected_type, s.quality_score, s.confidence_score, s.extracted_fields, s.issues, s.summary, s.requires_human_review, s.model, s.error_message, s.scanned_at FROM application_documents d LEFT JOIN document_ai_scans s ON s.document_id = d.id WHERE d.application_id = ? ORDER BY d.id');
    $documents->execute([$id]);
} catch (PDOException) {
    $aiTablesReady = false;
    $documents = $pdo->prepare('SELECT id, document_type, original_name, file_size FROM application_documents WHERE application_id = ? ORDER BY id');
    $documents->execute([$id]);
}
$definitions = document_definitions();
render_app_header('Review Application', 'review');
?>
<div class="section-heading"><div><p class="eyebrow"><?= e($application['reference']) ?></p><h2><?= e($application['business_name']) ?></h2><p class="muted"><?= e($application['owner_name']) ?> · <?= e($application['application_type']) ?> application</p></div><span class="status <?= e(status_class($application['status'])) ?>"><?= e($application['status']) ?></span></div>
<?php if ($errors): ?><div class="form-alert form-alert-error"><ul><?php foreach ($errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="content-grid review-layout"><article class="panel"><div class="panel-header"><div><p class="eyebrow">Applicant record</p><h3>Business information</h3></div><a href="<?= e(url('application.php?id=' . $id)) ?>">Full timeline</a></div><div class="detail-list detail-list-padded"><div><small>Applicant</small><strong><?= e($application['owner_name']) ?></strong></div><div><small>TIN</small><strong><?= e($application['tin']) ?></strong></div><div><small>Business type</small><strong><?= e($application['business_type']) ?></strong></div><div><small>Organization</small><strong><?= e($application['organization_type']) ?></strong></div><div><small>Contact</small><strong><?= e($application['contact']) ?></strong></div><div><small>Email</small><strong><?= e($application['email']) ?></strong></div><div class="detail-wide"><small>Address</small><strong><?= e($application['address']) ?></strong></div><div class="detail-wide business-location"><small>Applicant-captured location</small><?php if ($application['latitude'] !== null && $application['longitude'] !== null): ?><strong><?= e(number_format((float) $application['latitude'], 7)) ?>, <?= e(number_format((float) $application['longitude'], 7)) ?></strong><span><?= $application['location_accuracy_m'] !== null ? 'Accuracy ±' . e(number_format((float) $application['location_accuracy_m'], 0)) . ' m · ' : '' ?><a href="<?= e(openstreetmap_url($application['latitude'], $application['longitude'])) ?>" target="_blank" rel="noopener">Verify on map ↗</a></span><?php else: ?><strong>Not provided</strong><span>Verify using the written address and submitted documents.</span><?php endif; ?></div></div></article>
<aside class="panel decision-panel"><div class="panel-header"><div><p class="eyebrow">LGU decision</p><h3>Update application</h3></div></div><form method="post" class="decision-form"><?= csrf_field() ?><input type="hidden" name="application_id" value="<?= (int) $id ?>"><label class="field">Decision<select name="decision" required><?php foreach (['For Review', 'Needs Revision', 'Approved', 'Released', 'Rejected'] as $option): ?><option <?= $application['status'] === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select></label><label class="field">BPLO notes<textarea name="admin_notes" rows="6" placeholder="Instructions or decision notes"><?= e($_POST['admin_notes'] ?? $application['admin_notes']) ?></textarea></label><button class="button" type="submit">Save decision</button></form></aside></div>
<article class="panel document-panel ai-document-panel">
  <div class="panel-header"><div><p class="eyebrow">AI-assisted verification</p><h3>Submitted requirements</h3><p class="muted">AI findings are advisory. Open and verify every original document before making a decision.</p></div><span class="ai-provider">OpenAI · <?= e(openai_settings()['model']) ?></span></div>
  <div class="ai-privacy-note"><strong>Privacy notice:</strong> Scanning sends the selected document to the configured OpenAI API project with response storage disabled. Confirm that your LGU is authorized to process it. Medical-result scanning is blocked unless explicitly enabled by the server administrator.</div>
  <?php if (!$aiTablesReady): ?><div class="form-alert form-alert-error ai-migration-alert">Import <strong>database/migrations/002_ai_features.sql</strong> to enable AI scanning.</div><?php endif; ?>
  <div class="ai-document-list">
    <?php foreach ($documents->fetchAll() as $document):
      $label = $definitions[$document['document_type']][0] ?? $document['document_type'];
      $issues = isset($document['issues']) ? (json_decode((string) $document['issues'], true) ?: []) : [];
      $fields = isset($document['extracted_fields']) ? (json_decode((string) $document['extracted_fields'], true) ?: []) : [];
      $sensitiveBlocked = $document['document_type'] === 'health_results_doc' && !openai_settings()['allow_sensitive_documents'];
    ?>
      <section class="ai-document-row">
        <div class="ai-document-main">
          <a class="ai-document-link" href="<?= e(url('document.php?id=' . $document['id'])) ?>" target="_blank" rel="noopener"><span class="document-icon">▧</span><span><strong><?= e($label) ?></strong><small><?= e($document['original_name']) ?> · <?= e(number_format((int) $document['file_size'] / 1024, 1)) ?> KB</small></span></a>
          <form method="post" action="scan-document.php"><?= csrf_field() ?><input type="hidden" name="document_id" value="<?= (int) $document['id'] ?>"><button class="button button-secondary ai-scan-button" type="submit" <?= (!$aiTablesReady || $sensitiveBlocked) ? 'disabled' : '' ?>><?= $sensitiveBlocked ? 'Sensitive file' : ((($document['scan_status'] ?? '') === 'Completed') ? 'Scan again' : 'AI scan') ?></button></form>
        </div>
        <?php if (($document['scan_status'] ?? '') === 'Completed'): ?>
          <div class="ai-scan-result">
            <div class="ai-score-row"><span class="ai-chip <?= (int) $document['matches_expected_type'] === 1 ? 'good' : 'warning' ?>"><?= (int) $document['matches_expected_type'] === 1 ? 'Type matched' : 'Check type' ?></span><span class="ai-chip">Quality <?= (int) $document['quality_score'] ?>%</span><span class="ai-chip">Confidence <?= (int) $document['confidence_score'] ?>%</span><?php if ((int) $document['requires_human_review'] === 1): ?><span class="ai-chip review">Human review required</span><?php endif; ?></div>
            <p><strong>Detected:</strong> <?= e($document['detected_document_type']) ?></p><p><?= e($document['summary']) ?></p>
            <?php if ($issues): ?><div class="ai-findings"><strong>Items to check</strong><ul><?php foreach ($issues as $issue): ?><li><?= e($issue) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
            <?php if ($fields): ?><details><summary>Extracted fields</summary><div class="ai-field-grid"><?php foreach ($fields as $field): ?><div><small><?= e($field['field'] ?? 'Field') ?></small><strong><?= e($field['value'] ?? '') ?></strong><em><?= (int) ($field['confidence'] ?? 0) ?>% confidence</em></div><?php endforeach; ?></div></details><?php endif; ?>
            <small class="ai-timestamp">Scanned <?= e(date('M j, Y g:i A', strtotime($document['scanned_at']))) ?> · <?= e($document['model']) ?></small>
          </div>
        <?php elseif (($document['scan_status'] ?? '') === 'Failed'): ?>
          <div class="ai-scan-error"><strong>Last scan failed.</strong> <?= e($document['error_message']) ?></div>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>
  </div>
</article>
<div class="form-actions"><a class="button button-secondary" href="index.php#queue">← Back to review queue</a></div>
<?php render_app_footer(); ?>
