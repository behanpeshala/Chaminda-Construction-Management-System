<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['Administrator']);
$page_title = 'Create User';
$errors = [];
$roles = ['Administrator','Project Manager','Finance Officer','Procurement Staff','Site Staff','Client'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $full_name     = trim($_POST['full_name'] ?? '');
    $username      = trim($_POST['username'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $phone         = trim($_POST['phone'] ?? '');
    $nic_number    = strtoupper(trim($_POST['nic_number'] ?? ''));
    $date_of_birth = trim($_POST['date_of_birth'] ?? '');
    $gender        = trim($_POST['gender'] ?? '');
    $role          = $_POST['role'] ?? '';
    $password      = $_POST['password'] ?? '';

    if ($full_name === '') $errors[] = 'Full name is required.';
    if ($username === '')  $errors[] = 'Username is required.';
    if ($email === '') {
        $errors[] = 'Email address is required.';
    } elseif (!validate_email($email)) {
        $errors[] = 'Please enter a valid email address (e.g. name@example.com).';
    }

    // Phone Validation
    if ($phone === '') {
        $errors[] = 'Phone number is required.';
    } elseif ($phoneErr = get_phone_validation_error($phone)) {
        $errors[] = $phoneErr;
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

    if (!in_array($role, $roles, true)) $errors[] = 'Please select a valid role.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';

    if (!$errors) {
        $dup = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username=? OR email=? OR nic_number=?");
        $dup->execute([$username, $email, $nic_number]);
        if ($dup->fetchColumn() > 0) $errors[] = 'That username, email, or NIC number is already registered.';
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (full_name, username, email, phone, nic_number, date_of_birth, gender, password_hash, role) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$full_name, $username, $email, $phone, $nic_number, $date_of_birth, $gender, $hash, $role]);
        $newUserId = (int)$pdo->lastInsertId();
        audit($pdo, 'CREATE', 'users', $newUserId);
        
        create_notification(
            $pdo,
            'New User Account Created',
            "User account '$full_name' ($username) created with role $role. NIC: $nic_number.",
            'success',
            '/ccms/users/list.php'
        );

        redirect('/ccms/users/list.php');
    }
}

require_once __DIR__ . '/../includes/page_start.php';
?>
<div class="card p-4" style="max-width:650px">
  <?php foreach ($errors as $e): ?><div class="alert alert-danger py-2"><?php echo htmlspecialchars($e); ?></div><?php endforeach; ?>
  <form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
    <div class="mb-3">
      <label class="form-label required">Full Name</label>
      <input type="text" name="full_name" class="form-control" required value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
    </div>

    <div class="row g-2 mb-3">
      <div class="col-md-6">
        <label class="form-label required">Username</label>
        <input type="text" name="username" class="form-control" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label required">Email</label>
        <input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
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
        <input type="text" name="nic_number" class="form-control" placeholder="e.g. 199512345678 or 123456789V" required maxlength="12" value="<?php echo htmlspecialchars($_POST['nic_number'] ?? ''); ?>">
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
        <input type="date" name="date_of_birth" class="form-control" max="<?php echo date('Y-m-d'); ?>" required value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? ''); ?>">
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label required">Role</label>
      <select name="role" class="form-select" required>
        <option value="">-- Select role --</option>
        <?php foreach ($roles as $r): ?>
          <option value="<?php echo $r; ?>" <?php echo (($_POST['role'] ?? '') === $r) ? 'selected' : ''; ?>><?php echo $r; ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="mb-3">
      <label class="form-label required">Temporary Password</label>
      <input type="password" name="password" class="form-control" required minlength="8">
      <small class="text-muted">Minimum 8 characters. Passwords are stored hashed (bcrypt).</small>
    </div>
    <button class="btn btn-success"><i class="bi bi-check-lg"></i> Create User</button>
    <a href="/ccms/users/list.php" class="btn btn-outline-secondary">Cancel</a>
  </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
