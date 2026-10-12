<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['Administrator','Project Manager']);
$page_title = 'Client Web Inquiries';
$role = current_role();

// Handle status updates or deletes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'mark_replied' && $id > 0) {
        $pdo->prepare("UPDATE contact_messages SET status='Replied' WHERE id=?")->execute([$id]);
        set_flash('success', 'Message status updated to Replied.', 'Status Updated');
        redirect('/ccms/messages/index.php');
    } elseif ($action === 'mark_received' && $id > 0) {
        $pdo->prepare("UPDATE contact_messages SET status='Received' WHERE id=?")->execute([$id]);
        set_flash('info', 'Message status set to Received.', 'Status Updated');
        redirect('/ccms/messages/index.php');
    } elseif ($action === 'delete' && $id > 0 && $role === 'Administrator') {
        $pdo->prepare("DELETE FROM contact_messages WHERE id=?")->execute([$id]);
        set_flash('info', 'Inquiry message removed.', 'Deleted');
        redirect('/ccms/messages/index.php');
    }
}

$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $stmt = $pdo->prepare("SELECT * FROM contact_messages WHERE sender_name LIKE ? OR sender_email LIKE ? OR subject LIKE ? OR message LIKE ? ORDER BY created_at DESC");
    $term = "%$search%";
    $stmt->execute([$term, $term, $term, $term]);
    $messages = $stmt->fetchAll();
} else {
    $messages = $pdo->query("SELECT * FROM contact_messages ORDER BY created_at DESC")->fetchAll();
}

$totalCount = count($messages);
$pendingCount = 0;
foreach ($messages as $m) {
    if ($m['status'] === 'Received') $pendingCount++;
}

require_once __DIR__ . '/../includes/page_start.php';
?>

<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="ccms-stat-card bg-ccms-1">
      <div class="d-flex align-items-center justify-content-between">
        <span class="small fw-bold text-uppercase">Total Inquiries</span>
        <i class="bi bi-envelope-paper-fill fs-4"></i>
      </div>
      <div class="stat-value"><?php echo $totalCount; ?></div>
      <div class="small opacity-75 mt-1">All messages received from website</div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="ccms-stat-card bg-ccms-2">
      <div class="d-flex align-items-center justify-content-between">
        <span class="small fw-bold text-uppercase">New / Unhandled</span>
        <i class="bi bi-clock-history fs-4 text-warning"></i>
      </div>
      <div class="stat-value text-warning"><?php echo $pendingCount; ?></div>
      <div class="small opacity-75 mt-1">Awaiting staff response</div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="ccms-stat-card bg-ccms-3">
      <div class="d-flex align-items-center justify-content-between">
        <span class="small fw-bold text-uppercase">Direct Mailbox</span>
        <i class="bi bi-mailbox fs-4 text-cyan"></i>
      </div>
      <div class="stat-value text-cyan" style="font-size:1.1rem">chamindaconstructionn@gmail.com</div>
      <div class="small opacity-75 mt-1">Target recipient address</div>
    </div>
  </div>
</div>

<div class="card p-3">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <form class="row g-2 flex-grow-1 me-3" method="get">
      <div class="col-md-5">
        <input type="text" name="q" class="form-control" placeholder="Search by sender, email, subject or content" value="<?php echo htmlspecialchars($search); ?>">
      </div>
      <div class="col-auto">
        <button class="btn btn-outline-secondary"><i class="bi bi-search"></i> Search</button>
      </div>
    </form>
    <a href="/ccms/home.html#contact" target="_blank" class="btn btn-sm btn-outline-amber">
      <i class="bi bi-box-arrow-up-right me-1"></i> Visit Website Form
    </a>
  </div>

  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead>
        <tr>
          <th>Date &amp; Time</th>
          <th>Sender Name</th>
          <th>Email &amp; Phone</th>
          <th>Subject &amp; Message</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$messages): ?>
        <tr><td colspan="6" class="text-center text-muted py-4">No contact messages received yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($messages as $m): ?>
        <tr class="<?php echo $m['status'] === 'Received' ? 'table-warning table-opacity-10' : ''; ?>">
          <td class="font-monospace small text-nowrap"><?php echo htmlspecialchars($m['created_at']); ?></td>
          <td>
            <strong class="text-light"><?php echo htmlspecialchars($m['sender_name']); ?></strong>
          </td>
          <td>
            <div>
              <a href="mailto:<?php echo htmlspecialchars($m['sender_email']); ?>" class="text-info text-decoration-none small">
                <i class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($m['sender_email']); ?>
              </a>
            </div>
            <?php if ($m['sender_phone']): ?>
              <div class="small text-secondary mt-1">
                <a href="tel:<?php echo htmlspecialchars($m['sender_phone']); ?>" class="text-muted text-decoration-none">
                  <i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($m['sender_phone']); ?>
                </a>
              </div>
            <?php endif; ?>
          </td>
          <td>
            <div class="fw-semibold text-warning"><?php echo htmlspecialchars($m['subject']); ?></div>
            <div class="text-secondary small mt-1" style="max-width:400px; white-space:pre-wrap;"><?php echo htmlspecialchars($m['message']); ?></div>
          </td>
          <td>
            <?php if ($m['status'] === 'Received'): ?>
              <span class="badge bg-warning text-dark"><i class="bi bi-envelope-fill me-1"></i>Received</span>
            <?php else: ?>
              <span class="badge bg-success"><i class="bi bi-check-circle-fill me-1"></i>Replied</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="d-flex gap-1">
              <a href="mailto:<?php echo htmlspecialchars($m['sender_email']); ?>?subject=<?php echo rawurlencode('Re: ' . $m['subject']); ?>&body=<?php echo rawurlencode("Hi " . $m['sender_name'] . ",\n\nThank you for reaching out to Chaminda Construction regarding '" . $m['subject'] . "'.\n\n"); ?>" class="btn btn-xs btn-outline-cyan py-1 px-2" title="Reply via Email">
                <i class="bi bi-reply-fill me-1"></i>Reply
              </a>
              <?php if ($m['status'] === 'Received'): ?>
                <form method="post" class="d-inline">
                  <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                  <input type="hidden" name="action" value="mark_replied">
                  <input type="hidden" name="id" value="<?php echo $m['id']; ?>">
                  <button type="submit" class="btn btn-xs btn-outline-success py-1 px-2" title="Mark as Replied">
                    <i class="bi bi-check2"></i>
                  </button>
                </form>
              <?php else: ?>
                <form method="post" class="d-inline">
                  <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                  <input type="hidden" name="action" value="mark_received">
                  <input type="hidden" name="id" value="<?php echo $m['id']; ?>">
                  <button type="submit" class="btn btn-xs btn-outline-secondary py-1 px-2" title="Reopen / Mark as Received">
                    <i class="bi bi-arrow-counterclockwise"></i>
                  </button>
                </form>
              <?php endif; ?>
              <?php if ($role === 'Administrator'): ?>
                <form method="post" class="d-inline" onsubmit="return confirm('Delete this inquiry message?');">
                  <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?php echo $m['id']; ?>">
                  <button type="submit" class="btn btn-xs btn-outline-danger py-1 px-2" title="Delete Message">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
