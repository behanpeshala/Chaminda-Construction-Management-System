<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['Administrator','Project Manager']);
$page_title = 'Edit Employee';
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM employees WHERE employee_id=?"); 
$stmt->execute([$id]);
$emp = $stmt->fetch();
if (!$emp) { set_flash('danger','Employee not found.'); redirect('/ccms/employees/list.php'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name       = trim($_POST['full_name'] ?? '');
    $nic_number = strtoupper(trim($_POST['nic_number'] ?? ''));
    $role_title = trim($_POST['role_title'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $email      = trim($_POST['email'] ?? '');

    if ($name === '') $errors[] = 'Employee name is required.';
    
    // NIC Validation (Required)
    if ($nic_number === '') {
        $errors[] = 'NIC number is required.';
    } elseif (!validate_sri_lankan_nic($nic_number)) {
        $errors[] = 'Please enter a valid Sri Lankan NIC (9 digits + V/X or 12 digits).';
    } else {
        $dup = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE nic_number=? AND employee_id != ?");
        $dup->execute([$nic_number, $id]);
        if ($dup->fetchColumn() > 0) {
            $errors[] = 'That NIC number is already assigned to another employee.';
        }
    }

    if ($role_title === '') {
        $errors[] = 'Role / Title is required.';
    }

    // Phone Validation (Required)
    if ($phone === '') {
        $errors[] = 'Phone number is required.';
    } elseif ($phoneErr = get_phone_validation_error($phone)) {
        $errors[] = $phoneErr;
    }

    // Email Validation (Required)
    if ($email === '') {
        $errors[] = 'Email address is required.';
    } elseif (!validate_email($email)) {
        $errors[] = 'Please enter a valid email address (e.g. employee@example.com).';
    } else {
        $dupEmail = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE email=? AND employee_id != ?");
        $dupEmail->execute([$email, $id]);
        if ($dupEmail->fetchColumn() > 0) {
            $errors[] = 'That email address is already assigned to another employee.';
        }
    }

    if (!$errors) {
        $pdo->prepare("UPDATE employees SET full_name=?, nic_number=?, role_title=?, phone=?, email=? WHERE employee_id=?")
            ->execute([$name, $nic_number !== '' ? $nic_number : null, $role_title, $phone, $email, $id]);
        
        audit($pdo, 'UPDATE', 'employees', $id);

        create_notification(
            $pdo,
            'Employee Updated',
            "Employee '$name' profile was updated successfully.",
            'success',
            '/ccms/employees/list.php'
        );

        redirect('/ccms/employees/list.php');
    }
}
require_once __DIR__ . '/../includes/page_start.php';
?>
<div class="card p-4" style="max-width:560px">
  <?php foreach ($errors as $e): ?><div class="alert alert-danger py-2"><?php echo htmlspecialchars($e); ?></div><?php endforeach; ?>
  <form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
    <input type="hidden" name="id" value="<?php echo $emp['employee_id']; ?>">
    
    <div class="mb-3">
      <label class="form-label required">Full Name</label>
      <input type="text" name="full_name" class="form-control" required value="<?php echo htmlspecialchars($_POST['full_name'] ?? $emp['full_name']); ?>">
    </div>

    <div class="mb-3">
      <label class="form-label required">NIC Number</label>
      <input type="text" name="nic_number" class="form-control" placeholder="e.g. 199512345678 or 123456789V" required maxlength="12" value="<?php echo htmlspecialchars($_POST['nic_number'] ?? $emp['nic_number'] ?? ''); ?>">
    </div>

    <div class="mb-3">
      <label class="form-label required">Role / Title</label>
      <input type="text" name="role_title" class="form-control" required value="<?php echo htmlspecialchars($_POST['role_title'] ?? $emp['role_title']); ?>">
    </div>

    <div class="row g-2 mb-3">
      <div class="col-md-6">
        <label class="form-label required">Phone</label>
        <input type="tel" name="phone" class="form-control" placeholder="e.g. 0712345678 or +94712345678" required maxlength="15" value="<?php echo htmlspecialchars($_POST['phone'] ?? $emp['phone']); ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label required">Email</label>
        <input type="email" name="email" class="form-control" placeholder="employee@example.com" required value="<?php echo htmlspecialchars($_POST['email'] ?? $emp['email']); ?>">
      </div>
    </div>

    <button class="btn btn-success"><i class="bi bi-check-lg"></i> Save Changes</button>
    <a href="/ccms/employees/list.php" class="btn btn-outline-secondary">Cancel</a>
  </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
