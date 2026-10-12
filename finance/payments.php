<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['Administrator','Finance Officer','Project Manager','Client','Procurement Staff']);
$page_title = 'Bank Payments Management';
$role = current_role();
$userId = current_user_id();

$canApprove = in_array($role, ['Administrator', 'Project Manager', 'Finance Officer'], true);

// Fetch projects for form dropdown
if ($role === 'Client') {
    $pStmt = $pdo->prepare("SELECT p.project_id, p.project_name FROM projects p 
                            JOIN clients c ON c.client_id=p.client_id 
                            WHERE (c.email = (SELECT email FROM users WHERE user_id=?) OR c.nic_number = (SELECT nic_number FROM users WHERE user_id=?))
                            ORDER BY p.project_name");
    $pStmt->execute([$userId, $userId]);
    $projects = $pStmt->fetchAll();
    if (!$projects) {
        $projects = $pdo->query("SELECT project_id, project_name FROM projects WHERE status != 'Completed' ORDER BY project_name")->fetchAll();
    }
} else {
    $projects = $pdo->query("SELECT project_id, project_name FROM projects ORDER BY project_name")->fetchAll();
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // 1. APPROVAL / REJECTION ACTION
    $postAction = $_POST['action'] ?? '';
    if (in_array($postAction, ['approve_payment', 'reject_payment'], true)) {
        if (!$canApprove) {
            set_flash('danger', 'Only Administrator, Project Manager, and Finance Officer can approve or reject payments.', 'Permission Denied');
            redirect('/ccms/finance/payments.php');
        }

        $payment_id = (int)($_POST['payment_id'] ?? 0);
        $payStmt = $pdo->prepare("SELECT pay.*, p.project_name FROM payments pay JOIN projects p ON p.project_id = pay.project_id WHERE pay.payment_id = ?");
        $payStmt->execute([$payment_id]);
        $targetPay = $payStmt->fetch();

        if (!$targetPay) {
            set_flash('danger', 'Payment record not found.', 'Error');
            redirect('/ccms/finance/payments.php');
        }

        $newStatus = ($postAction === 'approve_payment') ? 'Approved' : 'Rejected';
        $updateStmt = $pdo->prepare("UPDATE payments SET status = ? WHERE payment_id = ?");
        $updateStmt->execute([$newStatus, $payment_id]);

        audit($pdo, strtoupper($postAction), 'payments', $payment_id);

        // Send notification to the user who recorded the payment
        if (!empty($targetPay['recorded_by'])) {
            $notifTitle = ($newStatus === 'Approved') ? 'Payment Approved' : 'Payment Rejected';
            $notifMsg   = ($newStatus === 'Approved')
                ? "Your payment of LKR " . number_format($targetPay['amount'], 2) . " for project '" . $targetPay['project_name'] . "' has been approved."
                : "Your payment request of LKR " . number_format($targetPay['amount'], 2) . " for project '" . $targetPay['project_name'] . "' was rejected.";
            $notifType  = ($newStatus === 'Approved') ? 'success' : 'danger';

            $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, link, created_by) VALUES (?, ?, ?, ?, ?, ?)");
            $notifStmt->execute([
                $targetPay['recorded_by'],
                $notifTitle,
                $notifMsg,
                $notifType,
                '/ccms/finance/payments.php',
                $userId
            ]);
        }

        $flashMsg = ($newStatus === 'Approved')
            ? "Payment #PAY-" . str_pad($payment_id, 4, '0', STR_PAD_LEFT) . " has been approved successfully."
            : "Payment #PAY-" . str_pad($payment_id, 4, '0', STR_PAD_LEFT) . " has been rejected.";
        set_flash(($newStatus === 'Approved' ? 'success' : 'warning'), $flashMsg, 'Payment ' . $newStatus);
        redirect('/ccms/finance/payments.php');
    }

    // 2. RECORD / SUBMIT NEW PAYMENT
    $project_id      = (int)($_POST['project_id'] ?? 0);
    $amount          = $_POST['amount'] ?? 0;
    $type            = ($role === 'Client') ? 'Client Payment' : ($_POST['payment_type'] ?? 'Client Payment');
    $method          = $_POST['payment_method'] ?? 'Bank Transfer';
    $bank_name       = trim($_POST['bank_name'] ?? '');
    $account_number  = trim($_POST['account_number'] ?? '');
    $reference_number= trim($_POST['reference_number'] ?? '');
    $date            = $_POST['payment_date'] ?? date('Y-m-d');
    $notes           = trim($_POST['notes'] ?? '');
    $status          = ($role === 'Client') ? 'Pending' : 'Approved';

    if (!$project_id) $errors[] = 'Please select a project.';
    if (!is_numeric($amount) || $amount <= 0) $errors[] = 'Amount must be a positive value.';
    if (!$date) $errors[] = 'Payment date is required.';
    if (!in_array($type, ['Client Payment','Supplier Payment','Other'], true)) $errors[] = 'Please select a valid payment type.';
    if (!in_array($method, ['Bank Transfer','Bank Deposit','Cheque','Cash','Online Payment'], true)) $errors[] = 'Please select a valid payment method.';

    $slip_path = null;
    if (isset($_FILES['slip_file']) && $_FILES['slip_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['slip_file']['tmp_name'];
        $fileName    = $_FILES['slip_file']['name'];
        $fileSize    = $_FILES['slip_file']['size'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
        if (!in_array($fileExtension, $allowedExtensions, true)) {
            $errors[] = 'Invalid file type for bank slip. Allowed: JPG, PNG, WEBP, PDF.';
        } elseif ($fileSize > 5 * 1024 * 1024) {
            $errors[] = 'Bank slip file size exceeds 5MB limit.';
        } else {
            $uploadDir = __DIR__ . '/../uploads/slips/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $newFileName = 'slip_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExtension;
            $destPath = $uploadDir . $newFileName;
            if (move_uploaded_file($fileTmpPath, $destPath)) {
                $slip_path = '/ccms/uploads/slips/' . $newFileName;
            } else {
                $errors[] = 'Failed to save uploaded bank slip.';
            }
        }
    }

    if (!$errors) {
        $stmt = $pdo->prepare("INSERT INTO payments (project_id, amount, payment_method, bank_name, account_number, reference_number, slip_path, notes, payment_type, payment_date, status, recorded_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $project_id,
            $amount,
            $method,
            $bank_name !== '' ? $bank_name : null,
            $account_number !== '' ? $account_number : null,
            $reference_number !== '' ? $reference_number : null,
            $slip_path,
            $notes !== '' ? $notes : null,
            $type,
            $date,
            $status,
            $userId
        ]);
        
        $paymentId = (int)$pdo->lastInsertId();
        audit($pdo, 'CREATE', 'payments', $paymentId);

        // Fetch project name for detailed notification
        $projStmt = $pdo->prepare("SELECT project_name FROM projects WHERE project_id = ?");
        $projStmt->execute([$project_id]);
        $projectName = $projStmt->fetchColumn() ?: 'Project';

        if ($role === 'Client') {
            // Forward approval request to Administrator, Project Manager, and Finance Officer
            $approvers = $pdo->query("SELECT user_id FROM users WHERE role IN ('Administrator','Project Manager','Finance Officer') AND status='active'")->fetchAll(PDO::FETCH_COLUMN);
            $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, link, created_by) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($approvers as $apprId) {
                $notifStmt->execute([
                    $apprId,
                    'Client Payment Request',
                    "Client payment request of LKR " . number_format($amount, 2) . " for '$projectName' is pending your approval.",
                    'warning',
                    '/ccms/finance/payments.php',
                    $userId
                ]);
            }

            set_flash('info', "Your payment of LKR " . number_format($amount, 2) . " has been submitted. Status is Pending approval by management.", 'Payment Submitted');
        } else {
            $msg = "LKR " . number_format($amount, 2) . " ($type) recorded via $method for $projectName.";
            if ($bank_name) $msg .= " Bank: $bank_name.";
            create_notification(
                $pdo,
                'Bank Payment Recorded',
                $msg,
                'success',
                '/ccms/finance/payments.php'
            );
        }

        redirect('/ccms/finance/payments.php');
    }
}

// Fetch payment totals and list based on role
if ($role === 'Client') {
    $payStmt = $pdo->prepare("
        SELECT pay.*, p.project_name, u.full_name 
        FROM payments pay
        JOIN projects p ON p.project_id = pay.project_id
        JOIN users u ON u.user_id = pay.recorded_by
        LEFT JOIN clients c ON c.client_id = p.client_id
        WHERE pay.recorded_by = ? 
           OR c.email = (SELECT email FROM users WHERE user_id=?) 
           OR c.nic_number = (SELECT nic_number FROM users WHERE user_id=?)
        ORDER BY pay.created_at DESC 
        LIMIT 100
    ");
    $payStmt->execute([$userId, $userId, $userId]);
    $payments = $payStmt->fetchAll();

    $totalPayments = 0;
    $totalApproved = 0;
    $totalPending = 0;
    foreach ($payments as $item) {
        $amt = (float)$item['amount'];
        $totalPayments += $amt;
        if (in_array($item['status'], ['Approved', 'Verified'])) {
            $totalApproved += $amt;
        } elseif ($item['status'] === 'Pending') {
            $totalPending += $amt;
        }
    }
    $totalSupplier = 0;
} else {
    $totalPayments = $pdo->query("SELECT SUM(amount) FROM payments")->fetchColumn() ?: 0;
    $totalApproved = $pdo->query("SELECT SUM(amount) FROM payments WHERE status IN ('Approved', 'Verified')")->fetchColumn() ?: 0;
    $totalPending  = $pdo->query("SELECT SUM(amount) FROM payments WHERE status = 'Pending'")->fetchColumn() ?: 0;
    $totalSupplier = $pdo->query("SELECT SUM(amount) FROM payments WHERE payment_type = 'Supplier Payment'")->fetchColumn() ?: 0;

    $payments = $pdo->query("
        SELECT pay.*, p.project_name, u.full_name 
        FROM payments pay
        JOIN projects p ON p.project_id = pay.project_id
        JOIN users u ON u.user_id = pay.recorded_by
        ORDER BY pay.created_at DESC 
        LIMIT 100
    ")->fetchAll();
}

$pendingRequestsCount = 0;
foreach ($payments as $item) {
    if ($item['status'] === 'Pending') {
        $pendingRequestsCount++;
    }
}

require_once __DIR__ . '/../includes/page_start.php';
?>

<!-- Pending Requests Alert Banner for Approvers -->
<?php if ($canApprove && $pendingRequestsCount > 0): ?>
<div class="alert alert-warning border-warning d-flex align-items-center justify-content-between p-3 mb-4 shadow-sm">
  <div class="d-flex align-items-center gap-2">
    <i class="bi bi-exclamation-triangle-fill fs-4 text-warning"></i>
    <div>
      <strong class="d-block">Payment Approval Requests Awaiting Action (<?php echo $pendingRequestsCount; ?>)</strong>
      <span class="small opacity-75">There are client payment submissions waiting for approval. As an authorized role (<?php echo htmlspecialchars($role); ?>), you can approve or reject them below.</span>
    </div>
  </div>
  <a href="#paymentHistoryTable" class="btn btn-sm btn-warning text-dark fw-bold px-3">
    <i class="bi bi-arrow-down-circle me-1"></i> Review Requests
  </a>
</div>
<?php endif; ?>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="ccms-stat-card bg-ccms-1">
      <div class="d-flex align-items-center justify-content-between">
        <span class="small fw-bold text-uppercase"><?php echo $role === 'Client' ? 'Total Submitted' : 'Total Payments'; ?></span>
        <i class="bi bi-cash-stack fs-4"></i>
      </div>
      <div class="stat-value">LKR <?php echo number_format($totalPayments, 2); ?></div>
      <div class="small opacity-75 mt-1"><?php echo $role === 'Client' ? 'Your total payments' : 'All payment records'; ?></div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="ccms-stat-card bg-ccms-2">
      <div class="d-flex align-items-center justify-content-between">
        <span class="small fw-bold text-uppercase">Approved Payments</span>
        <i class="bi bi-check-circle-fill fs-4 text-emerald"></i>
      </div>
      <div class="stat-value text-emerald">LKR <?php echo number_format($totalApproved, 2); ?></div>
      <div class="small opacity-75 mt-1">Verified & Approved</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="ccms-stat-card bg-ccms-3">
      <div class="d-flex align-items-center justify-content-between">
        <span class="small fw-bold text-uppercase">Pending Approval</span>
        <i class="bi bi-hourglass-split fs-4 text-warning"></i>
      </div>
      <div class="stat-value text-warning">LKR <?php echo number_format($totalPending, 2); ?></div>
      <div class="small opacity-75 mt-1"><?php echo $pendingRequestsCount; ?> record(s) pending</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="ccms-stat-card bg-ccms-4">
      <div class="d-flex align-items-center justify-content-between">
        <span class="small fw-bold text-uppercase"><?php echo $role === 'Client' ? 'Records Count' : 'Supplier Payments'; ?></span>
        <i class="bi bi-<?php echo $role === 'Client' ? 'receipt' : 'truck'; ?> fs-4"></i>
      </div>
      <div class="stat-value"><?php echo $role === 'Client' ? count($payments) . ' Transactions' : 'LKR ' . number_format($totalSupplier, 2); ?></div>
      <div class="small opacity-75 mt-1"><?php echo $role === 'Client' ? 'Total transaction records' : 'Vendor disbursements'; ?></div>
    </div>
  </div>
</div>

<div class="row g-3">
  <!-- Record Payment Form -->
  <div class="col-lg-4">
    <div class="card p-3">
      <h6 class="fw-bold text-warning mb-3">
        <?php echo $role === 'Client' ? '<i class="bi bi-wallet2 me-2"></i>Make Client Payment' : '<i class="bi bi-bank2 me-2"></i>Record Bank Payment'; ?>
      </h6>
      
      <?php if ($role === 'Client'): ?>
        <div class="alert alert-info py-2 small mb-3">
          <i class="bi bi-info-circle-fill me-1"></i> Payments recorded here will be submitted with <strong>Pending</strong> status and forwarded to Administrator, Project Manager, and Finance Officer for verification.
        </div>
      <?php endif; ?>

      <?php foreach ($errors as $e): ?>
        <div class="alert alert-danger py-2 small mb-2"><i class="bi bi-exclamation-triangle-fill me-1"></i><?php echo htmlspecialchars($e); ?></div>
      <?php endforeach; ?>

      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
        
        <div class="mb-2">
          <label class="form-label small required">Project</label>
          <select name="project_id" class="form-select form-select-sm" required>
            <option value="">-- Select Project --</option>
            <?php foreach ($projects as $p): ?>
              <option value="<?php echo $p['project_id']; ?>" <?php echo isset($_POST['project_id']) && $_POST['project_id'] == $p['project_id'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($p['project_name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="row g-2 mb-2">
          <div class="col-6">
            <label class="form-label small required">Category</label>
            <?php if ($role === 'Client'): ?>
              <input type="hidden" name="payment_type" value="Client Payment">
              <input type="text" class="form-control form-control-sm" value="Client Payment" readonly disabled>
            <?php else: ?>
              <select name="payment_type" class="form-select form-select-sm" required>
                <option value="Client Payment">Client Payment</option>
                <option value="Supplier Payment">Supplier Payment</option>
                <option value="Other">Other</option>
              </select>
            <?php endif; ?>
          </div>
          <div class="col-6">
            <label class="form-label small required">Payment Method</label>
            <select name="payment_method" class="form-select form-select-sm" id="paymentMethodSelect" required>
              <option value="Bank Transfer">Bank Transfer</option>
              <option value="Bank Deposit">Bank Deposit</option>
              <option value="Cheque">Cheque</option>
              <option value="Online Payment">Online Payment</option>
              <option value="Cash">Cash</option>
            </select>
          </div>
        </div>

        <div class="mb-2" id="bankDetailsSection">
          <label class="form-label small">Bank Name</label>
          <select name="bank_name" class="form-select form-select-sm">
            <option value="">-- Select Bank --</option>
            <option value="Bank of Ceylon (BOC)">Bank of Ceylon (BOC)</option>
            <option value="Commercial Bank of Ceylon">Commercial Bank of Ceylon</option>
            <option value="Hatton National Bank (HNB)">Hatton National Bank (HNB)</option>
            <option value="People's Bank">People's Bank</option>
            <option value="Sampath Bank">Sampath Bank</option>
            <option value="Seylan Bank">Seylan Bank</option>
            <option value="Nations Trust Bank (NTB)">Nations Trust Bank (NTB)</option>
            <option value="National Development Bank (NDB)">National Development Bank (NDB)</option>
            <option value="DFCC Bank">DFCC Bank</option>
            <option value="Union Bank">Union Bank</option>
            <option value="Other Bank">Other Bank</option>
          </select>
        </div>

        <div class="row g-2 mb-2">
          <div class="col-6">
            <label class="form-label small">Bank Account No</label>
            <input type="text" name="account_number" class="form-control form-control-sm" placeholder="e.g. 8001234567" value="<?php echo htmlspecialchars($_POST['account_number'] ?? ''); ?>">
          </div>
          <div class="col-6">
            <label class="form-label small">Ref / Slip No</label>
            <input type="text" name="reference_number" class="form-control form-control-sm" placeholder="e.g. TRX982341" value="<?php echo htmlspecialchars($_POST['reference_number'] ?? ''); ?>">
          </div>
        </div>

        <div class="row g-2 mb-2">
          <div class="col-6">
            <label class="form-label small required">Amount (LKR)</label>
            <input type="number" step="0.01" min="0.01" name="amount" class="form-control form-control-sm" placeholder="0.00" required value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>">
          </div>
          <div class="col-6">
            <label class="form-label small required">Payment Date</label>
            <input type="date" name="payment_date" class="form-control form-control-sm" required value="<?php echo htmlspecialchars($_POST['payment_date'] ?? date('Y-m-d')); ?>">
          </div>
        </div>

        <div class="mb-2">
          <label class="form-label small">Upload Bank Deposit / Payment Slip</label>
          <input type="file" name="slip_file" class="form-control form-control-sm" accept="image/*,.pdf">
          <div class="form-text small opacity-75">Formats: JPG, PNG, WEBP, PDF (Max 5MB)</div>
        </div>

        <div class="mb-3">
          <label class="form-label small">Notes / Description</label>
          <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Optional reference notes..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
        </div>

        <button class="btn btn-sm btn-success w-100 py-2">
          <i class="bi bi-<?php echo $role === 'Client' ? 'send-check' : 'check-circle-fill'; ?> me-1"></i>
          <?php echo $role === 'Client' ? 'Submit Payment Request' : 'Record Bank Payment'; ?>
        </button>
      </form>
    </div>
  </div>

  <!-- Recent Payments List -->
  <div class="col-lg-8">
    <div class="card p-3">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h6 class="fw-bold text-cyan mb-0">
          <i class="bi bi-journal-text me-2"></i><?php echo $role === 'Client' ? 'Your Payment History' : 'Bank Payment History'; ?>
        </h6>
        <span class="badge bg-secondary font-monospace"><?php echo count($payments); ?> Records</span>
      </div>

      <div class="table-responsive" id="paymentHistoryTable">
        <table class="table table-sm table-hover align-middle">
          <thead>
            <tr>
              <th>Date</th>
              <th>Project</th>
              <th>Method / Type</th>
              <th>Bank & Ref No</th>
              <th>Amount (LKR)</th>
              <th>Slip / Receipt</th>
              <th>Status</th>
              <?php if ($canApprove): ?><th>Approval Action</th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
          <?php if (!$payments): ?>
            <tr><td colspan="<?php echo $canApprove ? 8 : 7; ?>" class="text-center text-muted py-4">No bank payments recorded yet.</td></tr>
          <?php endif; ?>
          <?php foreach ($payments as $p): ?>
            <tr class="<?php echo $p['status'] === 'Pending' ? 'table-warning table-opacity-10' : ''; ?>">
              <td class="font-monospace small"><?php echo $p['payment_date']; ?></td>
              <td>
                <span class="fw-semibold text-light"><?php echo htmlspecialchars($p['project_name']); ?></span>
                <div class="text-muted small" style="font-size:0.75rem">By: <?php echo htmlspecialchars($p['full_name']); ?></div>
              </td>
              <td>
                <span class="badge bg-info text-dark me-1"><?php echo htmlspecialchars($p['payment_method'] ?? 'Bank Transfer'); ?></span>
                <div class="small text-secondary" style="font-size:0.75rem"><?php echo htmlspecialchars($p['payment_type']); ?></div>
              </td>
              <td>
                <?php if ($p['bank_name']): ?>
                  <div class="small fw-semibold text-warning"><i class="bi bi-bank me-1"></i><?php echo htmlspecialchars($p['bank_name']); ?></div>
                <?php else: ?>
                  <span class="text-muted small">-</span>
                <?php endif; ?>
                <?php if ($p['reference_number']): ?>
                  <div class="font-monospace text-info" style="font-size:0.75rem">Ref: <?php echo htmlspecialchars($p['reference_number']); ?></div>
                <?php endif; ?>
              </td>
              <td class="fw-bold font-monospace text-emerald">
                <?php echo number_format($p['amount'], 2); ?>
              </td>
              <td>
                <div class="d-flex gap-1">
                  <?php if ($p['slip_path']): ?>
                    <button class="btn btn-xs btn-outline-warning p-1 py-0" onclick="viewSlip('<?php echo htmlspecialchars($p['slip_path']); ?>', '<?php echo htmlspecialchars($p['reference_number'] ?: $p['payment_id']); ?>')">
                      <i class="bi bi-file-earmark-image"></i> Slip
                    </button>
                  <?php endif; ?>
                  <button class="btn btn-xs btn-outline-cyan p-1 py-0" onclick="showReceipt(<?php echo htmlspecialchars(json_encode($p)); ?>)">
                    <i class="bi bi-receipt"></i> Receipt
                  </button>
                </div>
              </td>
              <td>
                <?php if ($p['status'] === 'Pending'): ?>
                  <span class="badge bg-warning text-dark"><i class="bi bi-clock-history me-1"></i>Pending</span>
                <?php elseif (in_array($p['status'], ['Approved', 'Verified'])): ?>
                  <span class="badge bg-success"><i class="bi bi-check-circle-fill me-1"></i>Approved</span>
                <?php elseif ($p['status'] === 'Rejected'): ?>
                  <span class="badge bg-danger"><i class="bi bi-x-circle-fill me-1"></i>Rejected</span>
                <?php else: ?>
                  <span class="badge bg-secondary"><?php echo htmlspecialchars($p['status']); ?></span>
                <?php endif; ?>
              </td>
              <?php if ($canApprove): ?>
              <td>
                <?php if ($p['status'] === 'Pending'): ?>
                  <div class="d-flex gap-1">
                    <form method="post" onsubmit="return confirm('Approve this payment of LKR <?php echo number_format($p['amount'], 2); ?> for <?php echo htmlspecialchars(addslashes($p['project_name'])); ?>?');" class="d-inline">
                      <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                      <input type="hidden" name="action" value="approve_payment">
                      <input type="hidden" name="payment_id" value="<?php echo $p['payment_id']; ?>">
                      <button type="submit" class="btn btn-xs btn-success py-1 px-2 text-nowrap">
                        <i class="bi bi-check-lg me-1"></i>Approve
                      </button>
                    </form>
                    <form method="post" onsubmit="return confirm('Reject this payment request?');" class="d-inline">
                      <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                      <input type="hidden" name="action" value="reject_payment">
                      <input type="hidden" name="payment_id" value="<?php echo $p['payment_id']; ?>">
                      <button type="submit" class="btn btn-xs btn-outline-danger py-1 px-2 text-nowrap">
                        <i class="bi bi-x-lg me-1"></i>Reject
                      </button>
                    </form>
                  </div>
                <?php else: ?>
                  <span class="small text-muted">-</span>
                <?php endif; ?>
              </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Bank Slip Preview Modal -->
<div class="modal fade" id="slipModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content bg-dark text-light border border-warning">
      <div class="modal-header border-secondary">
        <h6 class="modal-title fw-bold text-warning" id="slipModalTitle"><i class="bi bi-file-earmark-image me-2"></i>Bank Payment Deposit Slip</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center p-3" id="slipModalBody">
        <!-- Dynamic slip image or PDF preview -->
      </div>
    </div>
  </div>
</div>

<!-- Official Printable Bank Receipt Modal -->
<div class="modal fade" id="receiptModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark text-light border border-cyan">
      <div class="modal-header border-secondary">
        <h6 class="modal-title fw-bold text-cyan"><i class="bi bi-receipt me-2"></i>Official Bank Payment Receipt</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4" id="receiptPrintArea">
        <div class="receipt-box text-light">
          <div class="d-flex justify-content-between align-items-center border-bottom border-secondary pb-3 mb-3">
            <div>
              <h5 class="fw-bold text-emerald mb-0"><i class="bi bi-buildings-fill text-warning me-1"></i>CHAMINDA CONSTRUCTION</h5>
              <small class="text-muted">Management System - Bank Receipt</small>
            </div>
            <div class="text-end">
              <span class="badge bg-warning text-dark font-monospace" id="rcptId">#PAY-000</span>
              <div class="small text-muted mt-1" id="rcptDate">2026-10-05</div>
            </div>
          </div>

          <div class="row g-2 mb-3">
            <div class="col-6">
              <span class="text-muted small d-block">Project:</span>
              <strong class="text-light" id="rcptProject">-</strong>
            </div>
            <div class="col-6">
              <span class="text-muted small d-block">Category:</span>
              <strong class="text-info" id="rcptType">-</strong>
            </div>
          </div>

          <div class="row g-2 mb-3">
            <div class="col-6">
              <span class="text-muted small d-block">Payment Method:</span>
              <strong class="text-warning" id="rcptMethod">-</strong>
            </div>
            <div class="col-6">
              <span class="text-muted small d-block">Bank Name:</span>
              <strong class="text-light" id="rcptBank">-</strong>
            </div>
          </div>

          <div class="row g-2 mb-3">
            <div class="col-6">
              <span class="text-muted small d-block">Account No:</span>
              <span class="font-monospace text-light" id="rcptAcc">-</span>
            </div>
            <div class="col-6">
              <span class="text-muted small d-block">Transaction Ref:</span>
              <span class="font-monospace text-cyan" id="rcptRef">-</span>
            </div>
          </div>

          <div class="bg-black bg-opacity-40 p-3 rounded border border-success border-opacity-30 d-flex justify-content-between align-items-center my-3">
            <span class="fw-bold text-uppercase small">Total Amount Paid</span>
            <span class="fs-4 fw-bold text-emerald font-monospace" id="rcptAmount">LKR 0.00</span>
          </div>

          <div class="d-flex justify-content-between align-items-center pt-2 border-top border-secondary text-muted small">
            <span>Recorded By: <strong class="text-light" id="rcptUser">-</strong></span>
            <span id="rcptStatusWrapper"><span class="badge bg-success" id="rcptStatusBadge">Status: APPROVED</span></span>
          </div>
        </div>
      </div>
      <div class="modal-footer border-secondary">
        <button class="btn btn-sm btn-outline-light" data-bs-dismiss="modal">Close</button>
        <button class="btn btn-sm btn-cyan" onclick="window.print()"><i class="bi bi-printer me-1"></i> Print Receipt</button>
      </div>
    </div>
  </div>
</div>

<script>
function viewSlip(url, ref) {
  var modal = new bootstrap.Modal(document.getElementById('slipModal'));
  document.getElementById('slipModalTitle').innerHTML = '<i class="bi bi-file-earmark-image me-2"></i>Bank Slip - Ref: ' + ref;
  var body = document.getElementById('slipModalBody');
  
  if (url.toLowerCase().endsWith('.pdf')) {
    body.innerHTML = '<embed src="' + url + '" type="application/pdf" width="100%" height="500px" />';
  } else {
    body.innerHTML = '<img src="' + url + '" class="img-fluid rounded border border-secondary shadow" style="max-height: 500px;" alt="Bank Deposit Slip">';
  }
  modal.show();
}

function showReceipt(p) {
  var modal = new bootstrap.Modal(document.getElementById('receiptModal'));
  document.getElementById('rcptId').textContent = '#PAY-' + String(p.payment_id).padStart(4, '0');
  document.getElementById('rcptDate').textContent = p.payment_date;
  document.getElementById('rcptProject').textContent = p.project_name;
  document.getElementById('rcptType').textContent = p.payment_type;
  document.getElementById('rcptMethod').textContent = p.payment_method || 'Bank Transfer';
  document.getElementById('rcptBank').textContent = p.bank_name || 'N/A';
  document.getElementById('rcptAcc').textContent = p.account_number || 'N/A';
  document.getElementById('rcptRef').textContent = p.reference_number || 'N/A';
  document.getElementById('rcptAmount').textContent = 'LKR ' + parseFloat(p.amount).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
  document.getElementById('rcptUser').textContent = p.full_name;

  var statusBadge = document.getElementById('rcptStatusBadge');
  var st = (p.status === 'Verified' || p.status === 'Approved') ? 'Approved' : (p.status || 'Pending');
  statusBadge.textContent = 'Status: ' + st.toUpperCase();
  if (st === 'Approved') {
    statusBadge.className = 'badge bg-success';
  } else if (st === 'Pending') {
    statusBadge.className = 'badge bg-warning text-dark';
  } else {
    statusBadge.className = 'badge bg-danger';
  }

  modal.show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
