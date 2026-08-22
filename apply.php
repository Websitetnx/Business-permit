<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
$user = require_role('applicant');
$errors = [];
$allowedBusinessTypes = ['Retail', 'Food and Beverage', 'Professional Services', 'Manufacturing', 'Other'];
$allowedOrganizations = ['Sole Proprietorship', 'Partnership', 'Corporation', 'Cooperative'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $businessName = trim((string) ($_POST['business_name'] ?? ''));
    $businessType = trim((string) ($_POST['business_type'] ?? ''));
    $organizationType = trim((string) ($_POST['organization_type'] ?? ''));
    $tin = trim((string) ($_POST['tin'] ?? ''));
    $contact = trim((string) ($_POST['contact'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $address = trim((string) ($_POST['address'] ?? ''));

    if (strlen($businessName) < 2 || strlen($businessName) > 190) $errors[] = 'Enter the registered business name.';
    if (!in_array($businessType, $allowedBusinessTypes, true)) $errors[] = 'Select a valid business type.';
    if (!in_array($organizationType, $allowedOrganizations, true)) $errors[] = 'Select a valid organization type.';
    if (!preg_match('/^\d{3}-?\d{3}-?\d{3}(?:-?\d{3})?$/', preg_replace('/\s+/', '', $tin))) $errors[] = 'Enter a valid 9 or 12-digit TIN.';
    if (strlen(preg_replace('/\D+/', '', $contact)) < 10) $errors[] = 'Enter a valid contact number.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid contact email.';
    if (strlen($address) < 8) $errors[] = 'Enter the complete business address.';
    if (!isset($_POST['declaration'])) $errors[] = 'Confirm the accuracy declaration before submitting.';

    if (!$errors) {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $businessStatement = $pdo->prepare('INSERT INTO businesses (user_id, business_name, business_type, organization_type, tin, contact, email, address) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $businessStatement->execute([$user['id'], $businessName, $businessType, $organizationType, $tin, $contact, $email, $address]);
            $businessId = (int) $pdo->lastInsertId();
            $reference = create_reference($pdo);
            $applicationStatement = $pdo->prepare("INSERT INTO applications (user_id, business_id, reference, application_type, status, stage) VALUES (?, ?, ?, 'New', 'For Review', 1)");
            $applicationStatement->execute([$user['id'], $businessId, $reference]);
            $applicationId = (int) $pdo->lastInsertId();
            $uploadErrors = store_application_documents($pdo, $applicationId);
            if ($uploadErrors) throw new RuntimeException(implode(' ', $uploadErrors));
            record_status($pdo, $applicationId, 'For Review', (int) $user['id'], 'New application submitted.');
            audit($pdo, (int) $user['id'], 'submit_application', 'application', $applicationId);
            $pdo->commit();
            flash('success', 'Application submitted. Your reference is ' . $reference . '.');
            redirect('application.php?id=' . $applicationId);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = $exception instanceof RuntimeException ? $exception->getMessage() : 'The application could not be submitted. Please try again.';
        }
    }
}

$definitions = document_definitions();
render_app_header('New Business Permit', 'apply');
?>
<div class="section-heading"><div><p class="eyebrow">New application</p><h2>Apply for a business permit</h2><p class="muted">Complete the business details and upload clear copies of the requirements.</p></div><span class="secure-badge">◆ Secure form</span></div>
<?php if ($errors): ?><div class="form-alert form-alert-error"><strong>Please correct the following:</strong><ul><?php foreach ($errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<ol class="stepper" aria-label="Application progress"><li class="active" data-step-indicator="1"><span>1</span><div><strong>Business details</strong><small>Basic information</small></div></li><li data-step-indicator="2"><span>2</span><div><strong>Requirements</strong><small>Upload documents</small></div></li><li data-step-indicator="3"><span>3</span><div><strong>Review</strong><small>Confirm and submit</small></div></li></ol>

<form id="applicationForm" method="post" enctype="multipart/form-data" novalidate>
  <?= csrf_field() ?>
  <div class="form-step active" data-form-step="1">
    <div class="panel form-panel"><div class="panel-header"><div><p class="eyebrow">Step 1 of 3</p><h3>Business information</h3></div></div>
      <div class="form-grid">
        <label class="field field-wide">Registered business name<input name="business_name" autocomplete="organization" required maxlength="190" value="<?= e($_POST['business_name'] ?? '') ?>"></label>
        <label class="field">Business type<select name="business_type" required><option value="">Select type</option><?php foreach ($allowedBusinessTypes as $option): ?><option <?= ($_POST['business_type'] ?? '') === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select></label>
        <label class="field">Organization type<select name="organization_type" required><option value="">Select organization</option><?php foreach ($allowedOrganizations as $option): ?><option <?= ($_POST['organization_type'] ?? '') === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select></label>
        <label class="field">TIN<input name="tin" required maxlength="15" value="<?= e($_POST['tin'] ?? '') ?>" placeholder="000-000-000-000"></label>
        <label class="field">Contact number<input name="contact" type="tel" required value="<?= e($_POST['contact'] ?? '') ?>" placeholder="09XX XXX XXXX"></label>
        <label class="field field-wide">Business address<input name="address" required value="<?= e($_POST['address'] ?? '') ?>" placeholder="Building, street, barangay, city"></label>
        <label class="field field-wide">Contact email<input name="email" type="email" required value="<?= e($_POST['email'] ?? $user['email']) ?>"></label>
      </div>
    </div>
    <div class="form-actions"><span></span><button class="button" type="button" data-next-step="2">Continue to requirements →</button></div>
  </div>

  <div class="form-step" data-form-step="2">
    <div class="panel form-panel"><div class="panel-header"><div><p class="eyebrow">Step 2 of 3</p><h3>Upload permit requirements</h3><p class="muted">Four files are standard requirements. The remaining files apply based on the business or location.</p></div></div>
      <?php foreach ([[true, 'Standard requirements'], [false, 'Conditional requirements']] as [$required, $heading]): ?>
        <div class="requirements-group <?= $required ? '' : 'conditional-group' ?>">
          <div class="requirements-heading"><div><h4><?= e($heading) ?></h4><p><?= $required ? 'These documents are required to submit.' : 'Upload these only when they apply to the business.' ?></p></div></div>
          <div class="upload-grid">
            <?php foreach ($definitions as $field => [$label, $isRequired, $tag]): if ($isRequired !== $required) continue; ?>
              <label class="upload-card"><span class="requirement-tag <?= $isRequired ? 'required-tag' : '' ?>"><?= e($tag) ?></span><span class="upload-icon">⇧</span><strong><?= e($label) ?></strong><small>PDF, JPG, or PNG · Maximum 5 MB</small><input name="<?= e($field) ?>" type="file" accept=".pdf,.jpg,.jpeg,.png" <?= $isRequired ? 'required' : '' ?>><em>Choose file</em><small class="error"></small></label>
            <?php endforeach; ?>
          </div>
          <?php if (!$required): ?><div class="conditional-note"><strong>Occupancy reminder:</strong> Upload the Occupancy Permit when available. Otherwise, upload the Affidavit of Undertaking in Absence of Occupancy.</div><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="form-actions"><button class="button button-secondary" type="button" data-prev-step="1">← Back</button><button class="button" type="button" data-next-step="3">Review application →</button></div>
  </div>

  <div class="form-step" data-form-step="3">
    <div class="panel form-panel"><div class="panel-header"><div><p class="eyebrow">Step 3 of 3</p><h3>Confirm and submit</h3><p class="muted">Review the information and selected filenames before sending them to the BPLO.</p></div></div><dl class="review-grid" id="applicationReview"></dl><label class="declaration"><input name="declaration" type="checkbox" required <?= isset($_POST['declaration']) ? 'checked' : '' ?>><span>I certify that the information and documents provided are complete and accurate.</span></label></div>
    <div class="form-actions"><button class="button button-secondary" type="button" data-prev-step="2">← Back</button><button class="button" type="submit">Submit application</button></div>
  </div>
</form>
<?php render_app_footer(); ?>
