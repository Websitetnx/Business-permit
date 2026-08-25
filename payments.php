<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
$user = require_role('applicant');
$paymentWorkflowReady = true;
try {
    $statement = db()->prepare("SELECT a.id application_id, a.reference, a.status application_status, b.business_name, p.id payment_id, p.amount, p.status payment_status, p.submitted_at, p.paid_at FROM applications a JOIN businesses b ON b.id = a.business_id LEFT JOIN payments p ON p.application_id = a.id WHERE a.user_id = ? AND a.status IN ('Approved','Released') ORDER BY a.approved_at DESC, a.id DESC");
    $statement->execute([$user['id']]);
    $records = $statement->fetchAll();
} catch (PDOException) {
    $paymentWorkflowReady = false;
    $records = [];
}
render_app_header('Payments', 'payments');
?>
<div class="section-heading"><div><p class="eyebrow">Permit transactions</p><h2>Payments and receipts</h2><p class="muted">Submit assessed permit payments and monitor verification.</p></div></div>
<?php if (!$paymentWorkflowReady): ?><div class="form-alert form-alert-error">The administrator must import <strong>database/migrations/004_payment_workflow.sql</strong> before payments can be used.</div><?php endif; ?>
<article class="panel"><div class="panel-header"><div><p class="eyebrow">Approved applications</p><h3>Payment records</h3></div></div>
  <?php if (!$records): ?><div class="empty-state"><p>Payment becomes available after a permit application is approved.</p></div><?php endif; ?>
  <div class="payment-list"><?php foreach ($records as $record): ?><a href="<?= $record['payment_id'] && $record['payment_status'] === 'Paid' ? 'receipt.php?id=' . (int) $record['payment_id'] : 'payment.php?application_id=' . (int) $record['application_id'] ?>"><span class="payment-list-icon">₱</span><span><strong><?= e($record['business_name']) ?></strong><small><?= e($record['reference']) ?> · <?= $record['payment_id'] ? '₱' . e(number_format((float) $record['amount'], 2)) : 'Awaiting assessment' ?></small></span><em class="status <?= e(payment_status_class($record['payment_status'] ?? 'Pending')) ?>"><?= e($record['payment_status'] ?? 'Not assessed') ?></em></a><?php endforeach; ?></div>
</article>
<?php render_app_footer(); ?>
