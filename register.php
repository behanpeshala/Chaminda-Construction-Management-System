<?php
require_once __DIR__ . '/includes/auth.php';

if (!empty($_SESSION['user_id'])) {
    redirect('/ccms/dashboard.php');
}

$errors = [];
// Admin role excluded from public registration
$allowed_roles = ['Project Manager', 'Finance Officer', 'Procurement Staff', 'Site Staff', 'Client'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $full_name        = trim($_POST['full_name'] ?? '');
    $username         = trim($_POST['username'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $phone            = trim($_POST['phone'] ?? '');
    $nic_number       = strtoupper(trim($_POST['nic_number'] ?? ''));
    $date_of_birth    = trim($_POST['date_of_birth'] ?? '');
    $gender           = trim($_POST['gender'] ?? '');
    $role             = $_POST['role'] ?? '';
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($full_name === '') $errors[] = 'Full name is required.';
    if ($username === '')  $errors[] = 'Username is required.';
    if ($email === '') {
        $errors[] = 'Email address is required.';
    } elseif (!validate_email($email)) {
        $errors[] = 'Please enter a valid email address (e.g. name@example.com).';
    }

    // Phone Validation (Sri Lankan standard / international)
    if ($phone === '') {
        $errors[] = 'Phone number is required.';
    } elseif (!validate_phone_number($phone)) {
        $errors[] = 'Please enter a valid phone number (e.g. 0712345678 or +94712345678).';
    }

    // NIC Validation
    if ($nic_number === '') {
        $errors[] = 'NIC number is required.';
    } elseif (!validate_sri_lankan_nic($nic_number)) {
        $errors[] = 'Please enter a valid Sri Lankan NIC (9 digits + V/X or 12 digits).';
    }

    // Date of Birth Validation
    if ($date_of_birth === '') {
        $errors[] = 'Date of birth is required.';
    } elseif ($date_of_birth > date('Y-m-d')) {
        $errors[] = 'Date of birth cannot be in the future.';
    }

    // Gender Validation
    if (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
        $errors[] = 'Please select a valid gender.';
    }

    // Role Validation (Must be in allowed_roles, strictly prohibiting Administrator)
    if (!in_array($role, $allowed_roles, true)) {
        $errors[] = 'Please select a valid role.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        $dup = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username=? OR email=? OR nic_number=?");
        $dup->execute([$username, $email, $nic_number]);
        if ($dup->fetchColumn() > 0) {
            $errors[] = 'That username, email, or NIC number is already registered.';
        }
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (full_name, username, email, phone, nic_number, date_of_birth, gender, password_hash, role, status) VALUES (?,?,?,?,?,?,?,?,?,'active')");
        $stmt->execute([$full_name, $username, $email, $phone, $nic_number, $date_of_birth, $gender, $hash, $role]);
        $new_user_id = (int)$pdo->lastInsertId();

        audit($pdo, 'REGISTER', 'users', $new_user_id);

        // Auto-sync Client record if registered as Client
        if ($role === 'Client') {
            try {
                $checkClient = $pdo->prepare("SELECT client_id FROM clients WHERE email=? OR nic_number=?");
                $checkClient->execute([$email, $nic_number]);
                if (!$checkClient->fetchColumn()) {
                    $pdo->prepare("INSERT INTO clients (name, nic_number, date_of_birth, gender, email, phone, address, status) VALUES (?,?,?,?,?,?,'Registered Online Client','active')")
                        ->execute([$full_name, $nic_number, $date_of_birth, $gender, $email, $phone]);
                }
            } catch (Exception $e) {}
        }

        create_notification(
            $pdo,
            'New Account Registered',
            "User '$full_name' ($username) registered as $role. NIC: $nic_number.",
            'info',
            '/ccms/users/list.php'
        );

        set_flash('success', 'Registration successful! You may now log in.');
        redirect('/ccms/login.php?registered=1');
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register - CCMS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="/ccms/assets/css/style.css" rel="stylesheet">
</head>
<body class="ccms-auth-wrapper py-4 px-3">
  <div class="card ccms-auth-card my-auto" style="max-width: 600px;">
    <div class="card-body p-4 p-md-5">
      <div class="text-center mb-4">
        <img src="/ccms/assets/images/logo.png" alt="CCMS Logo" style="height: 70px; width: auto; object-fit: contain; background: #ffffff; padding: 6px; border-radius: 12px; border: 1.5px solid #10B981; margin-bottom: 8px; box-shadow: 0 4px 15px rgba(16,185,129,0.2);">
        <h4 class="mt-1 mb-0">Create Account</h4>
        <small class="text-muted">Chaminda Construction Management System</small>
      </div>

      <?php foreach ($errors as $e): ?>
        <div class="alert alert-danger py-2 mb-2"><?php echo htmlspecialchars($e); ?></div>
      <?php endforeach; ?>

      <form method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

        <div class="mb-3">
          <label class="form-label required">Full Name</label>
          <input type="text" name="full_name" class="form-control" required autofocus
                 value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" placeholder="Enter your full name">
        </div>

        <div class="row g-2 mb-3">
          <div class="col-md-6">
            <label class="form-label required">Username</label>
            <input type="text" name="username" class="form-control" required
                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" placeholder="Choose username">
          </div>
          <div class="col-md-6">
            <label class="form-label required">Email</label>
            <input type="email" name="email" class="form-control" required
                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" placeholder="name@example.com">
          </div>
        </div>

        <div class="row g-2 mb-3">
          <div class="col-md-6">
            <label class="form-label required">Phone Number</label>
            <input type="tel" name="phone" class="form-control" required maxlength="15"
                   value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" placeholder="e.g. 0712345678 or +94712345678">
          </div>
          <div class="col-md-6">
            <label class="form-label required">NIC Number</label>
            <input type="text" name="nic_number" class="form-control" placeholder="e.g. 199512345678 or 123456789V"
                   required maxlength="12" value="<?php echo htmlspecialchars($_POST['nic_number'] ?? ''); ?>">
          </div>
        </div>

        <div class="row g-2 mb-3">
          <div class="col-md-6">
            <label class="form-label required">Gender</label>
            <select name="gender" class="form-select" required>
              <option value="">-- Select Gender --</option>
              <?php foreach (['Male','Female','Other'] as $g): ?>
                <option value="<?php echo $g; ?>" <?php echo (($_POST['gender'] ?? '') === $g) ? 'selected' : ''; ?>><?php echo $g; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label required">Date of Birth</label>
            <input type="date" name="date_of_birth" class="form-control" max="<?php echo date('Y-m-d'); ?>" required
                   value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? ''); ?>">
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label required">Role</label>
          <select name="role" class="form-select" required>
            <option value="">-- Select Role --</option>
            <?php foreach ($allowed_roles as $r): ?>
              <option value="<?php echo $r; ?>" <?php echo (($_POST['role'] ?? '') === $r) ? 'selected' : ''; ?>><?php echo $r; ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="row g-2 mb-3">
          <div class="col-md-6">
            <label class="form-label required">Password</label>
            <input type="password" name="password" id="regPassword" class="form-control" required minlength="8" placeholder="Min 8 characters">
          </div>
          <div class="col-md-6">
            <label class="form-label required">Confirm Password</label>
            <input type="password" name="confirm_password" id="regConfirmPassword" class="form-control" required minlength="8" placeholder="Re-enter password">
          </div>
        </div>

        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" id="showRegPass" data-toggle-password="#regPassword, #regConfirmPassword">
          <label class="form-check-label" for="showRegPass">Show passwords</label>
        </div>

        <button type="submit" class="btn btn-success w-100 py-2" style="background:var(--ccms-primary);border-color:var(--ccms-primary)">
          <i class="bi bi-person-plus-fill me-1"></i> Register Account
        </button>

        <div class="text-center mt-3 pt-2 border-top border-secondary border-opacity-25">
          <span class="text-muted small">Already have an account?</span>
          <a href="/ccms/login.php" class="ms-1 small text-primary fw-bold">Login here</a>
        </div>
      </form>
    </div>
  </div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/ccms/assets/js/script.js"></script>
</body>
</html>
