<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['Administrator','Project Manager']);
$page_title = 'Employees';
$page_actions = '<a href="/ccms/employees/create.php" class="btn btn-success"><i class="bi bi-plus-lg"></i> New Employee</a>';

$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE full_name LIKE ? OR nic_number LIKE ? OR role_title LIKE ? OR phone LIKE ? OR email LIKE ? OR address LIKE ? ORDER BY created_at DESC");
    $term = "%$search%";
    $stmt->execute([$term, $term, $term, $term, $term, $term]);
    $employees = $stmt->fetchAll();
} else {
    $employees = $pdo->query("SELECT * FROM employees ORDER BY created_at DESC")->fetchAll();
}

require_once __DIR__ . '/../includes/page_start.php';
?>
<div class="card p-3">
  <form class="row g-2 mb-3" method="get">
    <div class="col-md-4">
      <input type="text" name="q" class="form-control" placeholder="Search by name, NIC, role, email, phone or address" value="<?php echo htmlspecialchars($search); ?>" oninput="filterTable(this.value)">
    </div>
    <div class="col-auto"><button class="btn btn-outline-secondary"><i class="bi bi-search"></i> Search</button></div>
  </form>
  <div class="table-responsive">
  <table class="table table-hover align-middle">
    <thead><tr><th>Name</th><th>NIC</th><th>Role</th><th>Phone</th><th>Email</th><th>Address</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    <?php if (!$employees): ?><tr><td colspan="8" class="text-center text-muted py-4">No employees found.</td></tr><?php endif; ?>
    <?php foreach ($employees as $e): ?>
      <tr>
        <td class="fw-semibold text-light"><?php echo htmlspecialchars($e['full_name']); ?></td>
        <td><code><?php echo htmlspecialchars($e['nic_number'] ?? '-'); ?></code></td>
        <td><?php echo htmlspecialchars($e['role_title'] ?? '-'); ?></td>
        <td><?php echo htmlspecialchars($e['phone'] ?? '-'); ?></td>
        <td><?php echo htmlspecialchars($e['email'] ?? '-'); ?></td>
        <td><?php echo htmlspecialchars($e['address'] ?? '-'); ?></td>
        <td><?php echo $e['status']==='active' ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>'; ?></td>
        <td>
          <a href="/ccms/employees/edit.php?id=<?php echo $e['employee_id']; ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
          <form action="/ccms/employees/toggle_status.php" method="post" class="d-inline" data-confirm="Change status of this employee?">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="id" value="<?php echo $e['employee_id']; ?>">
            <button class="btn btn-sm btn-outline-warning"><i class="bi bi-power"></i></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
