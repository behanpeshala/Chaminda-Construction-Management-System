<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['Administrator']);
$page_title = 'Edit User';
$roles = ['Administrator','Project Manager','Finance Officer','Procurement Staff','Site Staff','Client'];

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id=?");
$stmt->execute([$id]);
$user = $stmt->fetch();
if (!$user) { set_flash('danger','User not found.'); redirect('/ccms/users/list.php'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $full_name     = trim($_POST['full_name'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $phone         = trim($_POST['phone'] ?? '');
    $nic_number    = strtoupper(trim($_POST['nic_number'] ?? ''));
    $date_of_birth = trim($_POST['date_of_birth'] ?? '');
    $gender        = trim($_POST['gender'] ?? '');
    $role          = $_POST['role'] ?? '';
    $newpass       = $_POST['password'] ?? '';

    if ($full_name === '') $errors[] = 'Full name is required.';
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
    } else {
        $dupNic = $pdo->prepare("SELECT COUNT(*) FROM users WHERE nic_number=? AND user_id != ?");
        $dupNic->execute([$nic_number, $id]);
        if ($dupNic->fetchColumn() > 0) $errors[] = 'That NIC number is already registered to another user.';
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
    if ($newpass !== '' && strlen($newpass) < 8) $errors[] = 'New password must be at least 8 characters.';

    if (!$errors) {
        $dupEmail = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email=? AND user_id != ?");
        $dupEmail->execute([$email, $id]);
        if ($dupEmail->fetchColumn() > 0) $errors[] = 'That email is already registered to another user.';
    }

    if (!$errors) {
        if ($newpass !== '') {
            $hash = password_hash($newpass, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE users SET full_name=?, email=?, phone=?, nic_number=?, date_of_birth=?, gender=?, role=?, password_hash=? WHERE user_id=?")
                ->execute([$full_name, $email, $phone, $nic_number, $date_of_birth, $gender, $role, $hash, $id]);
        } else {
            $pdo->prepare("UPDATE users SET full_name=?, email=?, phone=?, nic_number=?, date_of_birth=?, gender=?, role=? WHERE user_id=?")
                ->execute([$full_name, $email, $phone, $nic_number, $date_of_birth, $gender, $role, $id]);
        }
        audit($pdo, 'UPDATE', 'users', $id);

        create_notification(
            $pdo,
            'User Account Updated',
            "User profile '$full_name' ($user[username]) was updated.",
            'info',
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
    <input type="hidden" name="id" value="<?php echo $user['user_id']; ?>">
    <div class="mb-3">
      <label class="form-label required">Full Name</label>
      <input type="text" name="full_name" class="form-control" required value="<?php echo htmlspecialchars($_POST['full_name'] ?? $user['full_name']); ?>">
    </div>

    <div class="row g-2 mb-3">
      <div class="col-md-6">
        <label class="form-label">Username</label>
        <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
      </div>
      <div class="col-md-6">
        <label class="form-label required">Email</label>
        <input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($_POST['email'] ?? $user['email']); ?>">
      </div>
    </div>

    <div class="row g-2 mb-3">
      <div class="col-md-6">
        <label class="form-label required">Phone Number</label>
        <input type="tel" name="phone" class="form-control" required maxlength="15"
               value="<?php echo htmlspecialchars($_POST['phone'] ?? $user['phone'] ?? ''); ?>" placeholder="e.g. 0712345678 or +94712345678">
      </div>
      <div class="col-md-6">
        <label class="form-label required">NIC Number</label>
        <input type="text" name="nic_number" class="form-control" placeholder="e.g. 199512345678 or 123456789V" required maxlength="12" value="<?php echo htmlspecialchars($_POST['nic_number'] ?? $user['nic_number'] ?? ''); ?>">
      </div>
    </div>

    <div class="row g-2 mb-3">
      <div class="col-md-6">
        <label class="form-label required">Gender</label>
        <select name="gender" class="form-select" required>
          <option value="">-- Select Gender --</option>
          <?php foreach (['Male','Female','Other'] as $g): ?>
            <option value="<?php echo $g; ?>" <?php echo (($_POST['gender'] ?? $user['gender']) === $g) ? 'selected' : ''; ?>><?php echo $g; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label required">Date of Birth</label>
        <input type="date" name="date_of_birth" class="form-control" max="<?php echo date('Y-m-d'); ?>" required value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? $user['date_of_birth'] ?? ''); ?>">
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label required">Role</label>
      <select name="role" class="form-select" required>
        <?php foreach ($roles as $r): ?>
          <option value="<?php echo $r; ?>" <?php echo (($_POST['role'] ?? $user['role']) === $r) ? 'selected' : ''; ?>><?php echo $r; ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="mb-3">
      <label class="form-label">Reset Password (optional)</label>
      <input type="password" name="password" class="form-control" minlength="8" placeholder="Leave blank to keep current password">
    </div>
    <button class="btn btn-success"><i class="bi bi-check-lg"></i> Save Changes</button>
    <a href="/ccms/users/list.php" class="btn btn-outline-secondary">Cancel</a>
  </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
