<?php require_once __DIR__ . '/includes/auth.php'; ?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password - CCMS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="/ccms/assets/css/style.css" rel="stylesheet">
</head>
<body class="ccms-auth-wrapper px-3 py-4">
  <div class="card ccms-auth-card my-auto">
    <div class="card-body p-4 p-md-5 text-center">
      <i class="bi bi-shield-lock" style="font-size:2rem;color:var(--ccms-primary)"></i>
      <h5 class="mt-2">Password Reset</h5>
      <p class="text-muted small">
        Automated email/SMS password reset is out of scope for CCMS Version 1.0.
        Please contact your System Administrator, who can reset your password
        from <strong>Users &rarr; Edit User</strong>.
      </p>
      <a href="/ccms/login.php" class="btn btn-outline-secondary btn-sm">&larr; Back to Login</a>
    </div>
  </div>
</body>
</html>
