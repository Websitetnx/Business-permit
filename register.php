<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_guest();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['password_confirmation'] ?? '');
    if (strlen($name) < 2 || strlen($name) > 120) $errors[] = 'Enter your complete name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (strlen($password) < 8) $errors[] = 'Password must contain at least 8 characters.';
    if ($password !== $confirmation) $errors[] = 'Password confirmation does not match.';

    if (!$errors) {
        try {
            $pdo = db();
            $statement = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'applicant')");
            $statement->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            $id = (int) $pdo->lastInsertId();
            login_user(['id' => $id, 'name' => $name, 'email' => $email, 'role' => 'applicant']);
            audit($pdo, $id, 'register', 'user', $id);
            flash('success', 'Your applicant account was created successfully.');
            redirect('dashboard.php');
        } catch (PDOException $exception) {
            $errors[] = $exception->getCode() === '23000' ? 'An account already uses this email address.' : 'Account creation failed. Check the database setup.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Create account | PermitFlow</title><link rel="stylesheet" href="styles.css"></head>
<body class="auth-page">
  <main class="auth-shell">
    <section class="auth-visual"><a class="brand" href="index.php"><span class="brand-mark">P</span><span><strong>PermitFlow</strong><small>Business Permit Portal</small></span></a><div><p class="eyebrow light">Applicant registration</p><h1>Create one secure permit account.</h1><p>Your applications, renewals, documents, and status history remain connected to your account.</p></div><ul class="auth-points"><li>✓ Apply for a new permit</li><li>✓ Renew an approved permit</li><li>✓ Track LGU review updates</li></ul></section>
    <section class="auth-card">
      <div><p class="eyebrow">Get started</p><h2>Create applicant account</h2><p class="muted">Administrator roles cannot be selected during public registration.</p></div>
      <?php if ($errors): ?><div class="form-alert form-alert-error"><ul><?php foreach ($errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
      <form method="post" class="auth-form">
        <?= csrf_field() ?>
        <label class="field">Complete name<input name="name" autocomplete="name" required maxlength="120" value="<?= e($_POST['name'] ?? '') ?>"></label>
        <label class="field">Email address<input type="email" name="email" autocomplete="email" required value="<?= e($_POST['email'] ?? '') ?>"></label>
        <label class="field">Password<input type="password" name="password" autocomplete="new-password" minlength="8" required><small class="muted">Use at least 8 characters.</small></label>
        <label class="field">Confirm password<input type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required></label>
        <button class="button" type="submit">Create account</button>
      </form>
      <p class="auth-switch">Already registered? <a href="login.php">Sign in</a></p>
    </section>
  </main>
</body>
</html>
