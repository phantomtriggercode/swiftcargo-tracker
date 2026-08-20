<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (admin_logged_in()) {
    redirect('/admin/dashboard.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if (attempt_admin_login($username, $password)) {
        redirect('/admin/dashboard.php');
    }
    $error = 'Invalid username or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Staff Login | <?= h(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="login-page">
  <div class="form-card">
    <a href="/index.php" class="logo" style="justify-content:center;margin-bottom:22px;">
      <span class="mark">SC</span>
      <span><span class="word-swift">Swift</span><span class="word-cargo">Cargo</span></span>
    </a>
    <h3 style="text-align:center;margin:0 0 20px;">Staff Login</h3>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required autofocus>
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
      </div>
      <button type="submit" class="btn btn-primary btn-block">Log In</button>
    </form>
    <p style="text-align:center;color:var(--muted);font-size:12.5px;margin-top:16px;">
      Default demo login: <code>admin</code> / <code>ChangeMe123!</code>
    </p>
  </div>
</div>
</body>
</html>
