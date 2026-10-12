<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['Administrator','Finance Officer','Project Manager','Client','Procurement Staff']);
$page_title = 'Project Budgets';

$role = current_role();
$userId = current_user_id();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if ($role === 'Client') {
        $errors[] = 'Clients are not authorized to modify project budgets.';
    } else {
        $project_id = (int)$_POST['project_id'];
        $amount = $_POST['allocated_amount'];
        if (!is_numeric($amount) || $amount <= 0) {
            $errors[] = 'Allocated amount must be a positive value.';
        } else {
            $exists = $pdo->prepare("SELECT budget_id FROM budgets WHERE project_id=?");
            $exists->execute([$project_id]);
            if ($exists->fetchColumn()) {
                $pdo->prepare("UPDATE budgets SET allocated_amount=? WHERE project_id=?")->execute([$amount,$project_id]);
            } else {
                $pdo->prepare("INSERT INTO budgets (project_id, allocated_amount) VALUES (?,?)")->execute([$project_id,$amount]);
            }
            audit($pdo,'BUDGET_SET','budgets',$project_id);

            $pName = $pdo->query("SELECT project_name FROM projects WHERE project_id = $project_id")->fetchColumn() ?: 'Project';
            create_notification(
                $pdo,
                'Project Budget Set',
                "Allocated budget for '$pName' set to LKR " . number_format($amount, 2) . ".",
                'success',
                '/ccms/finance/budgets.php'
            );

            redirect('/ccms/finance/budgets.php');
        }
    }
}

$search = trim($_GET['q'] ?? '');
if ($role === 'Client') {
    if ($search !== '') {
        $stmt = $pdo->prepare("SELECT p.project_id, p.project_name, p.estimated_budget, b.allocated_amount,
                                     COALESCE((SELECT SUM(amount) FROM expenses e WHERE e.project_id=p.project_id),0) AS spent
                              FROM projects p 
                              JOIN clients c ON c.client_id=p.client_id
                              LEFT JOIN budgets b ON b.project_id=p.project_id
                              WHERE (c.email = (SELECT email FROM users WHERE user_id=?) OR c.nic_number = (SELECT nic_number FROM users WHERE user_id=?))
                                AND p.project_name LIKE ?
                              ORDER BY p.created_at DESC");
        $term = "%$search%";
        $stmt->execute([$userId, $userId, $term]);
        $rows = $stmt->fetchAll();
    } else {
        $stmt = $pdo->prepare("SELECT p.project_id, p.project_name, p.estimated_budget, b.allocated_amount,
                                     COALESCE((SELECT SUM(amount) FROM expenses e WHERE e.project_id=p.project_id),0) AS spent
                              FROM projects p 
                              JOIN clients c ON c.client_id=p.client_id
                              LEFT JOIN budgets b ON b.project_id=p.project_id
                              WHERE (c.email = (SELECT email FROM users WHERE user_id=?) OR c.nic_number = (SELECT nic_number FROM users WHERE user_id=?))
                              ORDER BY p.created_at DESC");
        $stmt->execute([$userId, $userId]);
        $rows = $stmt->fetchAll();
    }
} else {
    if ($search !== '') {
        $stmt = $pdo->prepare("SELECT p.project_id, p.project_name, p.estimated_budget, b.allocated_amount,
                                     COALESCE((SELECT SUM(amount) FROM expenses e WHERE e.project_id=p.project_id),0) AS spent
                              FROM projects p LEFT JOIN budgets b ON b.project_id=p.project_id
                              WHERE p.project_name LIKE ?
                              ORDER BY p.created_at DESC");
        $term = "%$search%";
        $stmt->execute([$term]);
        $rows = $stmt->fetchAll();
    } else {
        $rows = $pdo->query("SELECT p.project_id, p.project_name, p.estimated_budget, b.allocated_amount,
                                     COALESCE((SELECT SUM(amount) FROM expenses e WHERE e.project_id=p.project_id),0) AS spent
                              FROM projects p LEFT JOIN budgets b ON b.project_id=p.project_id
                              ORDER BY p.created_at DESC")->fetchAll();
    }
}

require_once __DIR__ . '/../includes/page_start.php';
?>
<?php foreach ($errors as $e): ?><div class="alert alert-danger py-2"><?php echo htmlspecialchars($e); ?></div><?php endforeach; ?>
<?php if ($role === 'Client'): ?>
<div class="alert alert-info py-2 small mb-3">
  <i class="bi bi-info-circle-fill me-1"></i> As a Client, you have view-only access to project budgets. Budget allocations are managed by company administrators and finance officers.
</div>
<?php endif; ?>
<div class="card p-3">
  <form class="row g-2 mb-3" method="get">
    <div class="col-md-4">
      <input type="text" name="q" class="form-control" placeholder="Search project budgets by name" value="<?php echo htmlspecialchars($search); ?>" oninput="filterTable(this.value)">
    </div>
    <div class="col-auto"><button class="btn btn-outline-secondary"><i class="bi bi-search"></i> Search</button></div>
  </form>
  <div class="table-responsive">
  <table class="table table-hover align-middle">
    <thead>
      <tr>
        <th>Project</th>
        <th>Estimated Budget</th>
        <th>Allocated Budget</th>
        <th>Spent</th>
        <th>Remaining</th>
        <th><?php echo $role === 'Client' ? 'Status' : 'Set / Update'; ?></th>
      </tr>
    </thead>
    <tbody>
    <?php if (!$rows): ?>
      <tr><td colspan="6" class="text-center text-muted py-4">No project budgets found.</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): $alloc = (float)($r['allocated_amount'] ?? 0); $remaining = $alloc - $r['spent']; ?>
      <tr class="<?php echo $remaining < 0 ? 'table-danger' : ''; ?>">
        <td class="fw-semibold text-light"><?php echo htmlspecialchars($r['project_name']); ?></td>
        <td><?php echo number_format($r['estimated_budget'],2); ?></td>
        <td class="text-info"><?php echo number_format($alloc,2); ?></td>
        <td class="text-warning"><?php echo number_format($r['spent'],2); ?></td>
        <td class="<?php echo $remaining < 0 ? 'text-danger fw-bold' : 'text-emerald'; ?>"><?php echo number_format($remaining,2); ?></td>
        <td>
          <?php if ($role === 'Client'): ?>
            <span class="badge bg-secondary"><i class="bi bi-lock-fill me-1"></i>View Only</span>
          <?php else: ?>
            <form method="post" class="d-flex gap-1">
              <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
              <input type="hidden" name="project_id" value="<?php echo $r['project_id']; ?>">
              <input type="number" step="0.01" min="0.01" name="allocated_amount" class="form-control form-control-sm" style="width:140px" value="<?php echo $alloc; ?>" required>
              <button class="btn btn-sm btn-outline-success" title="Save Budget"><i class="bi bi-check-lg"></i></button>
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
