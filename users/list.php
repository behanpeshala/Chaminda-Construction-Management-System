<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['Administrator']);
$page_title = 'Users';
$page_actions = '<a href="/ccms/reports/export_pdf.php?type=users" class="btn btn-outline-info me-2"><i class="bi bi-file-earmark-pdf"></i> Export Users Report</a><a href="/ccms/users/create.php" class="btn btn-success"><i class="bi bi-plus-lg"></i> New User</a>';

$search = trim($_GET['q'] ?? '');
$sql = "SELECT * FROM users WHERE (full_name LIKE ? OR username LIKE ? OR email LIKE ? OR nic_number LIKE ?) ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$like = "%$search%";
$stmt->execute([$like, $like, $like, $like]);
$users = $stmt->fetchAll();

require_once __DIR__ . '/../includes/page_start.php';
?>
<div class="card p-3">
  <form class="row g-2 mb-3" method="get">
    <div class="col-md-4">
      <input type="text" name="q" class="form-control" placeholder="Search by name, username, email or NIC" value="<?php echo htmlspecialchars($search); ?>" oninput="filterTable(this.value)">
    </div>
    <div class="col-auto"><button class="btn btn-outline-secondary"><i class="bi bi-search"></i> Search</button></div>
  </form>

  <div class="table-responsive">
  <table class="table table-hover align-middle">
    <thead><tr><th>Name</th><th>Username</th><th>Email</th><th>NIC</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    <?php if (!$users): ?>
      <tr><td colspan="7" class="text-center text-muted py-4">No users found.</td></tr>
    <?php endif; ?>
    <?php foreach ($users as $u): ?>
      <tr>
        <td><?php echo htmlspecialchars($u['full_name']); ?></td>
        <td><?php echo htmlspecialchars($u['username']); ?></td>
        <td><?php echo htmlspecialchars($u['email']); ?></td>
        <td>
          <code><?php echo htmlspecialchars($u['nic_number'] ?? '—'); ?></code>
          <?php if (!empty($u['gender'])): ?>
            <br><small class="text-muted"><?php echo htmlspecialchars($u['gender']); ?><?php if (!empty($u['date_of_birth'])): ?> · <?php echo htmlspecialchars($u['date_of_birth']); ?><?php endif; ?></small>
          <?php endif; ?>
        </td>
        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($u['role']); ?></span></td>
        <td>
          <?php if ($u['status'] === 'active'): ?>
            <span class="badge bg-success">Active</span>
          <?php else: ?>
            <span class="badge bg-danger">Inactive</span>
          <?php endif; ?>
        </td>
        <td>
          <a href="/ccms/users/edit.php?id=<?php echo $u['user_id']; ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
          <?php if ($u['user_id'] != current_user_id()): ?>
          <form action="/ccms/users/toggle_status.php" method="post" class="d-inline" data-confirm="Change status of this user?">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="id" value="<?php echo $u['user_id']; ?>">
            <button class="btn btn-sm btn-outline-warning"><i class="bi bi-power"></i></button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
