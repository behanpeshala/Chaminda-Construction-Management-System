<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['Administrator','Procurement Staff','Project Manager']);
$page_title = 'New Supplier';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim($_POST['name'] ?? '');
    $contact = trim($_POST['contact_person'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($name === '') $errors[] = 'Supplier name is required.';
    if ($contact === '') $errors[] = 'Contact person is required.';

    // Phone Validation
    if ($phone === '') {
        $errors[] = 'Phone number is required.';
    } elseif ($phoneErr = get_phone_validation_error($phone)) {
        $errors[] = $phoneErr;
    }

    // Email Validation
    if ($email === '') {
        $errors[] = 'Email address is required.';
    } elseif (!validate_email($email)) {
        $errors[] = 'Please enter a valid email address (e.g. supplier@example.com).';
    } else {
        $dup = $pdo->prepare("SELECT COUNT(*) FROM suppliers WHERE email=?");
        $dup->execute([$email]);
        if ($dup->fetchColumn() > 0) {
            $errors[] = 'A supplier with this email address is already registered.';
        }
    }

    if (!$errors) {
        $pdo->prepare("INSERT INTO suppliers (name, contact_person, phone, email, address) VALUES (?,?,?,?,?)")
            ->execute([$name,$contact,$phone,$email,$address]);
        
        $supId = (int)$pdo->lastInsertId();
        audit($pdo, 'CREATE', 'suppliers', $supId);

        create_notification(
            $pdo,
            'New Supplier Added',
            "Supplier '$name' registered successfully.",
            'success',
            '/ccms/suppliers/list.php'
        );

        redirect('/ccms/suppliers/list.php');
    }
}
require_once __DIR__ . '/../includes/page_start.php';
?>
<div class="card p-4" style="max-width:560px">
  <?php foreach ($errors as $e): ?><div class="alert alert-danger py-2"><?php echo htmlspecialchars($e); ?></div><?php endforeach; ?>
  <form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
    <div class="mb-3"><label class="form-label required">Supplier Name</label>
      <input type="text" name="name" class="form-control" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"></div>
    <div class="mb-3"><label class="form-label required">Contact Person</label>
      <input type="text" name="contact_person" class="form-control" required value="<?php echo htmlspecialchars($_POST['contact_person'] ?? ''); ?>"></div>
    <div class="mb-3"><label class="form-label required">Phone</label>
      <input type="tel" name="phone" class="form-control" placeholder="e.g. 0712345678 or +94712345678" required maxlength="15" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"></div>
    <div class="mb-3"><label class="form-label required">Email</label>
      <input type="email" name="email" class="form-control" placeholder="supplier@example.com" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"></div>
    <div class="mb-3"><label class="form-label">Address</label>
      <textarea name="address" class="form-control" rows="2"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea></div>
    <button class="btn btn-success"><i class="bi bi-check-lg"></i> Save Supplier</button>
    <a href="/ccms/suppliers/list.php" class="btn btn-outline-secondary">Cancel</a>
  </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
