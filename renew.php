<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
$user = require_role('applicant');
$pdo = db();
$errors = [];
$record = null;
$permitNumber = strtoupper(trim((string) ($_GET['permit_number'] ?? $_POST['permit_number'] ?? '')));

function find_renewable_permit(PDO $pdo, int $userId, string $permitNumber): ?array
{
    $statement = $pdo->prepare("SELECT a.id source_application_id, a.permit_number, b.* FROM applications a JOIN businesses b ON b.id = a.business_id WHERE a.user_id = ? AND a.permit_number = ? AND a.status IN ('Approved','Released') ORDER BY a.approved_at DESC, a.id DESC LIMIT 1");
    $statement->execute([$userId, $permitNumber]);
    return $statement->fetch() ?: null;
}

if ($permitNumber !== '') $record = find_renewable_permit($pdo, (int) $user['id'], $permitNumber);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$record) $errors[] = 'No approved or released permit was found in your account.';
    $grossSales = str_replace([',', '₱', ' '], '', (string) ($_POST['gross_sales'] ?? ''));
    $contact = trim((string) ($_POST['contact'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $address = trim((string) ($_POST['address'] ?? ''));
    if (!is_numeric($grossSales) || (float) $grossSales < 0) $errors[] = 'Enter valid gross sales for the previous year.';
    if (strlen(preg_replace('/\D+/', '', $contact)) < 10) $errors[] = 'Enter a valid contact number.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid contact email.';
    if (strlen($address) < 8) $errors[] = 'Enter the complete business address.';
    if (!isset($_POST['declaration'])) $errors[] = 'Confirm the accuracy declaration before submitting.';
    if (!$errors && $record) {
        try {
            $pdo->beginTransaction();
            $update = $pdo->prepare('UPDATE businesses SET contact = ?, email = ?, address = ? WHERE id = ? AND user_id = ?');
            $update->execute([$contact, $email, $address, $record['id'], $user['id']]);
            $reference = create_reference($pdo);
            $insert = $pdo->prepare("INSERT INTO applications (user_id, business_id, reference, permit_number, application_type, status, stage, gross_sales) VALUES (?, ?, ?, ?, 'Renewal', 'For Review', 1, ?)");
            $insert->execute([$user['id'], $record['id'], $reference, $record['permit_number'], $grossSales]);
            $applicationId = (int) $pdo->lastInsertId();
            $uploadErrors = store_application_documents($pdo, $applicationId);
            if ($uploadErrors) throw new RuntimeException(implode(' ', $uploadErrors));
            record_status($pdo, $applicationId, 'For Review', (int) $user['id'], 'Permit renewal submitted.');
            audit($pdo, (int) $user['id'], 'submit_renewal', 'application', $applicationId);
            $pdo->commit();
            flash('success', 'Renewal submitted. Your reference is ' . $reference . '.');
            redirect('application.php?id=' . $applicationId);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = $exception instanceof RuntimeException ? $exception->getMessage() : 'The renewal could not be submitted.';
        }
    }
}

$definitions = document_definitions();
render_app_header('Renew Business Permit', 'renew');
?>
<div class="section-heading"><div><p class="eyebrow">Permit renewal</p><h2>Renew an existing permit</h2><p class="muted">Only approved or released permits connected to your account can be renewed.</p></div></div>
<?php if ($errors): ?><div class="form-alert form-alert-error"><ul><?php foreach ($errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="panel lookup-panel"><form method="get" class="lookup-form"><label class="field"><span>Business permit number</span><input name="permit_number" required value="<?= e($permitNumber) ?>" placeholder="BP-2026-12345"></label><button class="button" type="submit">Find permit</button></form></div>
<?php if ($permitNumber && !$record): ?><div class="panel result-card empty-state"><strong>No renewable permit found.</strong><p>Check the permit number or contact the BPLO Help Desk.</p></div><?php endif; ?>
<?php if ($record): ?>
<form method="post" enctype="multipart/form-data" class="renewal-application-form">
  <?= csrf_field() ?><input type="hidden" name="permit_number" value="<?= e($record['permit_number']) ?>">
  <article class="panel result-card"><div class="record-summary"><div><p class="eyebrow">Permit record found</p><h3><?= e($record['business_name']) ?></h3><p class="muted"><?= e($record['permit_number']) ?> · <?= e($record['address']) ?></p></div><span class="status approved">Active record</span></div>
    <div class="form-grid no-side-padding"><label class="field">Gross sales for previous year<input name="gross_sales" inputmode="decimal" required value="<?= e($_POST['gross_sales'] ?? '') ?>" placeholder="0.00"></label><label class="field">Contact number<input name="contact" required value="<?= e($_POST['contact'] ?? $record['contact']) ?>"></label><label class="field">Email<input type="email" name="email" required value="<?= e($_POST['email'] ?? $record['email']) ?>"></label><label class="field field-wide">Business address<input name="address" required value="<?= e($_POST['address'] ?? $record['address']) ?>"></label></div>
  </article>
  <article class="panel document-panel renewal-documents"><div class="panel-header"><div><p class="eyebrow">Renewal requirements</p><h3>Upload current documents</h3><p class="muted">Required documents and the occupancy alternative are validated again for this renewal.</p></div></div>
    <?php foreach ([[true, 'Standard requirements'], [false, 'Conditional requirements']] as [$required, $heading]): ?><div class="requirements-group <?= $required ? '' : 'conditional-group' ?>"><div class="requirements-heading"><div><h4><?= e($heading) ?></h4></div></div><div class="upload-grid"><?php foreach ($definitions as $field => [$label, $isRequired, $tag]): if ($isRequired !== $required) continue; ?><label class="upload-card"><span class="requirement-tag <?= $isRequired ? 'required-tag' : '' ?>"><?= e($tag) ?></span><span class="upload-icon">⇧</span><strong><?= e($label) ?></strong><small>PDF, JPG, or PNG · Maximum 5 MB</small><input name="<?= e($field) ?>" type="file" accept=".pdf,.jpg,.jpeg,.png" <?= $isRequired ? 'required' : '' ?>><em>Choose file</em><small class="error"></small></label><?php endforeach; ?></div><?php if (!$required): ?><div class="conditional-note"><strong>Occupancy reminder:</strong> Upload either the Occupancy Permit or the Affidavit of Undertaking alternative.</div><?php endif; ?></div><?php endforeach; ?>
    <label class="declaration"><input name="declaration" type="checkbox" required><span>I certify that the renewal information and documents are complete and accurate.</span></label>
  </article>
  <div class="form-actions"><span></span><button class="button" type="submit">Submit renewal</button></div>
</form>
<?php endif; ?>
<?php render_app_footer(); ?>
