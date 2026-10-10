<?php
require_once __DIR__ . '/includes/auth.php';

if (!empty($_SESSION['user_id'])) {
    redirect('/ccms/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $identifier = trim($_POST['identifier'] ?? '');
    $password   = $_POST['password'] ?? '';

    if ($identifier === '' || $password === '') {
        $error = 'Please enter your username/email and password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();

        if ($user && $user['status'] !== 'active') {
            $error = 'Invalid username or password.'; // do not reveal deactivated status
        } elseif ($user && $user['locked_until'] && strtotime($user['locked_until']) > time()) {
            $mins = ceil((strtotime($user['locked_until']) - time()) / 60);
            $error = "Account temporarily locked due to multiple failed attempts. Try again in {$mins} minute(s).";
        } elseif ($user && password_verify($password, $user['password_hash'])) {
            // success: reset failed attempts, start session
            $pdo->prepare("UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE user_id = ?")
                ->execute([$user['user_id']]);

            session_regenerate_id(true);
            $_SESSION['user_id']       = $user['user_id'];
            $_SESSION['full_name']     = $user['full_name'];
            $_SESSION['role']          = $user['role'];
            $_SESSION['last_activity'] = time();

            audit($pdo, 'LOGIN', 'users', $user['user_id']);
            redirect('/ccms/dashboard.php');
        } else {
            // failed attempt -- generic message either way (username enumeration protection)
            $error = 'Invalid username or password.';
            if ($user) {
                $attempts = $user['failed_attempts'] + 1;
                $locked_until = null;
                if ($attempts >= MAX_LOGIN_ATTEMPTS) {
                    $locked_until = date('Y-m-d H:i:s', strtotime('+' . LOCKOUT_MINUTES . ' minutes'));
                    $error = 'Too many failed attempts. Your account has been temporarily locked for ' . LOCKOUT_MINUTES . ' minutes.';
                }
                $pdo->prepare("UPDATE users SET failed_attempts = ?, locked_until = ? WHERE user_id = ?")
                    ->execute([$attempts, $locked_until, $user['user_id']]);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - CCMS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="/ccms/assets/css/style.css" rel="stylesheet">
</head>
<body class="ccms-auth-wrapper px-3 py-4">
  <div class="card ccms-auth-card my-auto <?php echo $error ? 'shake-animation' : ''; ?>">
    <div class="card-body p-4 p-md-5">
      <div class="text-center mb-4">
        <img src="/ccms/assets/images/logo.png" alt="CCMS Logo" style="height: 70px; width: auto; object-fit: contain; background: #ffffff; padding: 6px; border-radius: 12px; border: 1.5px solid #10B981; margin-bottom: 8px; box-shadow: 0 4px 15px rgba(16,185,129,0.2);">
        <h4 class="mt-1 mb-0">CCMS</h4>
        <small class="text-muted">Chaminda Construction Company</small>
      </div>

      <?php if (!empty($_GET['registered'])): ?>
        <div class="alert alert-success py-2"><i class="bi bi-check-circle-fill me-1"></i> Registration successful! Please log in with your credentials.</div>
      <?php endif; ?>
      <?php if (!empty($_GET['timeout'])): ?>
        <div class="alert alert-warning py-2"><i class="bi bi-clock-history me-1"></i> You were logged out due to inactivity.</div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-danger alert-permanent d-flex align-items-center gap-3 py-3 px-3 shadow mb-3 border border-danger border-opacity-75 rounded-3 text-start" role="alert" id="loginErrorAlert">
          <i class="bi bi-exclamation-octagon-fill fs-3 text-danger flex-shrink-0"></i>
          <div class="flex-grow-1">
            <div class="fw-bold text-danger">Authentication Failed</div>
            <div class="small text-light text-opacity-90 mt-1"><?php echo htmlspecialchars($error); ?></div>
          </div>
        </div>
      <?php endif; ?>

      <form method="post" id="loginForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
        <div class="mb-3">
          <label class="form-label required">Username / Email</label>
          <input type="text" name="identifier" id="loginIdentifier" class="form-control <?php echo $error ? 'is-invalid' : ''; ?>" required autofocus
                 value="<?php echo htmlspecialchars($_POST['identifier'] ?? ''); ?>" placeholder="Enter your username or email">
        </div>
        <div class="mb-2">
          <label class="form-label required">Password</label>
          <input type="password" name="password" id="loginPassword" class="form-control <?php echo $error ? 'is-invalid' : ''; ?>" required placeholder="Enter your password">
          <?php if ($error): ?>
            <div class="invalid-feedback d-block mt-2">
              <i class="bi bi-x-circle me-1"></i> <?php echo htmlspecialchars($error); ?>
            </div>
          <?php endif; ?>
        </div>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" id="showPass" data-toggle-password="#loginPassword">
          <label class="form-check-label" for="showPass">Show password</label>
        </div>
        <button type="submit" class="btn btn-success w-100" style="background:var(--ccms-primary);border-color:var(--ccms-primary)">
          <i class="bi bi-box-arrow-in-right"></i> Login
        </button>
        <div class="d-flex justify-content-between align-items-center mt-3">
          <a href="/ccms/forgot_password.php" class="small text-muted">Forgot Password?</a>
        </div>
        <div class="text-center mt-3 pt-2 border-top border-secondary border-opacity-25">
          <span class="text-muted small">Don't have an account?</span>
          <a href="/ccms/register.php" class="btn btn-outline-success btn-sm w-100 mt-2" style="border-color:var(--ccms-primary);color:var(--ccms-primary)">
            <i class="bi bi-person-plus"></i> Create New Account (Register)
          </a>
        </div>
      </form>
    </div>
  </div>

<!-- Floating Toast Container for Auth Pages -->
<div id="toastContainer" class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1090; margin-top: 20px;"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<?php if (!empty($_SESSION['flash'])): ?>
<script>
  window.SESSION_TOAST = <?php echo json_encode($_SESSION['flash']); ?>;
</script>
<?php unset($_SESSION['flash']); ?>
<?php endif; ?>

<script src="/ccms/assets/js/script.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  <?php if ($error): ?>
    if (typeof showToast === 'function') {
      showToast('Authentication Failed', <?php echo json_encode($error); ?>, 'danger');
    }
  <?php endif; ?>

  var loginForm = document.getElementById('loginForm');
  var identInput = document.getElementById('loginIdentifier');
  var passInput = document.getElementById('loginPassword');

  if (loginForm && identInput && passInput) {
    loginForm.addEventListener('submit', function (e) {
      var valid = true;
      if (!identInput.value.trim()) {
        identInput.classList.add('is-invalid');
        valid = false;
      }
      if (!passInput.value) {
        passInput.classList.add('is-invalid');
        valid = false;
      }
      if (!valid) {
        e.preventDefault();
        var card = document.querySelector('.ccms-auth-card');
        if (card) {
          card.classList.remove('shake-animation');
          void card.offsetWidth; // trigger reflow
          card.classList.add('shake-animation');
        }
        if (typeof showToast === 'function') {
          showToast('Validation Error', 'Please enter your username/email and password.', 'warning');
        }
      }
    });

    [identInput, passInput].forEach(function (input) {
      input.addEventListener('input', function () {
        this.classList.remove('is-invalid');
      });
    });
  }
});
</script>
</body>
</html>
