<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['Administrator','Project Manager']);
$page_title = 'Edit Client';
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM clients WHERE client_id=?"); $stmt->execute([$id]);
$client = $stmt->fetch();
if (!$client) { set_flash('danger','Client not found.'); redirect('/ccms/clients/list.php'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name          = trim($_POST['name'] ?? '');
    $nic_number    = strtoupper(trim($_POST['nic_number'] ?? ''));
    $date_of_birth = trim($_POST['date_of_birth'] ?? '');
    $gender        = trim($_POST['gender'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $phone         = trim($_POST['phone'] ?? '');
    $address       = trim($_POST['address'] ?? '');

    if ($name === '') $errors[] = 'Client name is required.';

    // NIC Validation
    if ($nic_number === '') {
        $errors[] = 'NIC number is required.';
    } elseif (!validate_sri_lankan_nic($nic_number)) {
        $errors[] = 'Please enter a valid Sri Lankan NIC (9 digits + V/X or 12 digits).';
    } else {
        $dupNic = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE nic_number=? AND client_id != ?");
        $dupNic->execute([$nic_number, $id]);
        if ($dupNic->fetchColumn() > 0) {
            $errors[] = 'A client with this NIC number is already registered.';
        }
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

    // Email Validation
    if ($email === '') {
        $errors[] = 'Email address is required.';
    } elseif (!validate_email($email)) {
        $errors[] = 'Please enter a valid email address (e.g. client@example.com).';
    } else {
        $dupEmail = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE email=? AND client_id != ?");
        $dupEmail->execute([$email, $id]);
        if ($dupEmail->fetchColumn() > 0) {
            $errors[] = 'A client with this email address is already registered.';
        }
    }

    // Phone Validation
    if ($phone === '') {
        $errors[] = 'Phone number is required.';
    } elseif ($phoneErr = get_phone_validation_error($phone)) {
        $errors[] = $phoneErr;
    }
    if (!$errors) {
        $pdo->prepare("UPDATE clients SET name=?, nic_number=?, date_of_birth=?, gender=?, email=?, phone=?, address=? WHERE client_id=?")
            ->execute([$name,$nic_number,$date_of_birth,$gender,$email,$phone,$address,$id]);
        audit($pdo,'UPDATE','clients',$id);
        
        create_notification(
            $pdo,
            'Client Profile Updated',
            "Client '$name' profile updated successfully.",
            'success',
            '/ccms/clients/list.php'
        );

        redirect('/ccms/clients/list.php');
    }
}
require_once __DIR__ . '/../includes/page_start.php';
?>
<div class="card p-4" style="max-width:650px">
  <?php foreach ($errors as $e): ?><div class="alert alert-danger py-2"><?php echo htmlspecialchars($e); ?></div><?php endforeach; ?>
  <form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
    <input type="hidden" name="id" value="<?php echo $client['client_id']; ?>">
    <div class="mb-3">
      <label class="form-label required">Client Name</label>
      <input type="text" name="name" class="form-control" required value="<?php echo htmlspecialchars($_POST['name'] ?? $client['name']); ?>">
    </div>

    <div class="row g-2 mb-3">
      <div class="col-md-6">
        <label class="form-label required">NIC Number</label>
        <input type="text" name="nic_number" class="form-control" placeholder="e.g. 199512345678 or 123456789V" required maxlength="12" value="<?php echo htmlspecialchars($_POST['nic_number'] ?? $client['nic_number'] ?? ''); ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label required">Gender</label>
        <select name="gender" class="form-select" required>
          <option value="">-- Select Gender --</option>
          <?php foreach (['Male','Female','Other'] as $g): ?>
            <option value="<?php echo $g; ?>" <?php echo (($_POST['gender'] ?? $client['gender']) === $g) ? 'selected' : ''; ?>><?php echo $g; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label required">Date of Birth</label>
      <input type="date" name="date_of_birth" class="form-control" max="<?php echo date('Y-m-d'); ?>" required value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? $client['date_of_birth'] ?? ''); ?>">
    </div>

    <div class="row g-2 mb-3">
      <div class="col-md-6">
        <label class="form-label required">Email</label>
        <input type="email" name="email" class="form-control" placeholder="client@example.com" required value="<?php echo htmlspecialchars($_POST['email'] ?? $client['email']); ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label required">Phone</label>
        <input type="tel" name="phone" class="form-control" placeholder="e.g. 0712345678 or +94712345678" required maxlength="15" value="<?php echo htmlspecialchars($_POST['phone'] ?? $client['phone']); ?>">
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label">Address</label>
      <textarea name="address" class="form-control" rows="2"><?php echo htmlspecialchars($_POST['address'] ?? $client['address']); ?></textarea>
    </div>
    <button class="btn btn-success"><i class="bi bi-check-lg"></i> Save Changes</button>
    <a href="/ccms/clients/list.php" class="btn btn-outline-secondary">Cancel</a>
  </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
