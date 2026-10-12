<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['Administrator','Finance Officer','Project Manager','Client','Procurement Staff']);
$page_title = 'Expenses';

$projects = $pdo->query("SELECT project_id, project_name FROM projects ORDER BY project_name")->fetchAll();
$categories = ['Materials','Labour','Equipment','Transport','Permits & Fees','Miscellaneous'];

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $project_id = (int)$_POST['project_id'];
    $category = $_POST['category'] ?? '';
    $amount = $_POST['amount'] ?? 0;
    $description = trim($_POST['description'] ?? '');
    $justification = trim($_POST['overrun_justification'] ?? '');

    if (!$project_id) $errors[] = 'Please select a project.';
    if (!is_numeric($amount) || $amount <= 0) $errors[] = 'Amount must be a positive value.';

    if (!$errors) {
        $b = $pdo->prepare("SELECT allocated_amount FROM budgets WHERE project_id=?"); $b->execute([$project_id]);
        $budget = (float)($b->fetchColumn() ?: 0);
        $s = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE project_id=?"); $s->execute([$project_id]);
        $spentSoFar = (float)$s->fetchColumn();

        $willOverrun = ($spentSoFar + (float)$amount) > $budget && $budget > 0;
        if ($willOverrun && $justification === '') {
            $errors[] = 'This expense would exceed the allocated budget. Please provide a justification to confirm the overrun.';
        }
    }

    if (!$errors) {
        $pdo->prepare("INSERT INTO expenses (project_id, category, amount, description, overrun_justification, recorded_by) VALUES (?,?,?,?,?,?)")
            ->execute([$project_id,$category,$amount,$description,$justification ?: null, current_user_id()]);
        audit($pdo,'CREATE','expenses',(int)$pdo->lastInsertId());

        $pName = $pdo->query("SELECT project_name FROM projects WHERE project_id = $project_id")->fetchColumn() ?: 'Project';
        create_notification(
            $pdo,
            'Expense Recorded',
            "Expense of LKR " . number_format($amount, 2) . " ($category) recorded for '$pName'.",
            'success',
            '/ccms/finance/expenses.php'
        );

        redirect('/ccms/finance/expenses.php');
    }
}

$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $stmt = $pdo->prepare("SELECT e.*, p.project_name, u.full_name FROM expenses e
                          JOIN projects p ON p.project_id=e.project_id
                          JOIN users u ON u.user_id=e.recorded_by
                          WHERE p.project_name LIKE ? OR e.category LIKE ? OR e.description LIKE ? OR u.full_name LIKE ?
                          ORDER BY e.created_at DESC LIMIT 100");
    $term = "%$search%";
    $stmt->execute([$term, $term, $term, $term]);
    $expenses = $stmt->fetchAll();
} else {
    $expenses = $pdo->query("SELECT e.*, p.project_name, u.full_name FROM expenses e
                              JOIN projects p ON p.project_id=e.project_id
                              JOIN users u ON u.user_id=e.recorded_by
                              ORDER BY e.created_at DESC LIMIT 100")->fetchAll();
}

require_once __DIR__ . '/../includes/page_start.php';
?>
<div class="row g-3">
  <div class="col-lg-4">
    <div class="card p-3">
      <h6>Record Expense</h6>
      <?php foreach ($errors as $e): ?><div class="alert alert-danger py-2 small"><?php echo htmlspecialchars($e); ?></div><?php endforeach; ?>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
        <div class="mb-2">
          <label class="form-label small required">Project</label>
          <select name="project_id" class="form-select form-select-sm" required>
            <option value="">-- Select --</option>
            <?php foreach ($projects as $p): ?><option value="<?php echo $p['project_id']; ?>"><?php echo htmlspecialchars($p['project_name']); ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label small required">Category</label>
          <select name="category" class="form-select form-select-sm">
            <?php foreach ($categories as $c): ?><option value="<?php echo $c; ?>"><?php echo $c; ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label small required">Amount (LKR)</label>
          <input type="number" step="0.01" min="0.01" name="amount" class="form-control form-control-sm" required>
        </div>
        <div class="mb-2">
          <label class="form-label small">Description</label>
          <input type="text" name="description" class="form-control form-control-sm">
        </div>
        <div class="mb-2">
          <label class="form-label small">Overrun Justification <span class="text-muted">(only if exceeding budget)</span></label>
          <input type="text" name="overrun_justification" class="form-control form-control-sm">
        </div>
        <button class="btn btn-sm btn-success w-100"><i class="bi bi-check-lg"></i> Record Expense</button>
      </form>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card p-3">
      <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <h6 class="mb-0">Recent Expenses</h6>
        <form class="row g-2 mb-0" method="get">
          <div class="col-auto">
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Search by project, category..." value="<?php echo htmlspecialchars($search); ?>" oninput="filterTable(this.value, '.col-lg-8 table')">
          </div>
          <div class="col-auto"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i> Search</button></div>
        </form>
      </div>
      <div class="table-responsive">
      <table class="table table-sm table-hover align-middle">
        <thead><tr><th>Date</th><th>Project</th><th>Category</th><th>Amount</th><th>By</th></tr></thead>
        <tbody>
        <?php if (!$expenses): ?><tr><td colspan="5" class="text-center text-muted py-4">No expenses recorded yet.</td></tr><?php endif; ?>
        <?php foreach ($expenses as $e): ?>
          <tr>
            <td><?php echo $e['created_at']; ?></td>
            <td><?php echo htmlspecialchars($e['project_name']); ?></td>
            <td><?php echo htmlspecialchars($e['category']); ?></td>
            <td><?php echo number_format($e['amount'],2); ?>
              <?php if ($e['overrun_justification']): ?><i class="bi bi-exclamation-triangle text-warning" title="<?php echo htmlspecialchars($e['overrun_justification']); ?>"></i><?php endif; ?>
            </td>
            <td><?php echo htmlspecialchars($e['full_name']); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
