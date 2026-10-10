<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['Administrator','Project Manager','Site Staff','Client','Procurement Staff','Finance Officer']);
$role = current_role();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT p.*, c.name AS client_name, c.email AS client_email, c.nic_number AS client_nic, u.full_name AS pm_name
                        FROM projects p JOIN clients c ON c.client_id=p.client_id
                        LEFT JOIN users u ON u.user_id=p.project_manager_id
                        WHERE p.project_id=?");
$stmt->execute([$id]);
$project = $stmt->fetch();
if (!$project) { set_flash('danger','Project not found.'); redirect('/ccms/projects/list.php'); }

// Clients may only view their own project
if ($role === 'Client') {
    $uStmt = $pdo->prepare("SELECT email, nic_number FROM users WHERE user_id=?"); 
    $uStmt->execute([current_user_id()]);
    $uData = $uStmt->fetch();
    $uEmail = $uData['email'] ?? '';
    $uNic = $uData['nic_number'] ?? '';

    if ($uEmail !== $project['client_email'] && ($uNic === '' || $uNic !== $project['client_nic'])) {
        http_response_code(403); die('Access denied: this is not your assigned project.');
    }
}

$page_title = $project['project_name'];
$page_actions = in_array($role, ['Administrator','Project Manager'])
  ? '<a href="/ccms/projects/edit.php?id=' . $id . '" class="btn btn-outline-primary"><i class="bi bi-pencil"></i> Edit Project</a>' : '';

// Progress update (Project Manager or Admin only; reducing progress requires justification)
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_progress'])) {
    if (!in_array($role, ['Administrator','Project Manager'])) {
        http_response_code(403); die('Only a Project Manager or Administrator can update progress.');
    }
    csrf_check();
    $newProgress = (int)$_POST['progress_percent'];
    $note = trim($_POST['note'] ?? '');
    $justification = trim($_POST['justification'] ?? '');

    if ($newProgress < 0 || $newProgress > 100) {
        $errors[] = 'Progress must be between 0 and 100.';
    } elseif ($newProgress < $project['progress_percent'] && $justification === '') {
        $errors[] = 'Reducing progress requires a recorded justification.';
    }

    if (!$errors) {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE projects SET progress_percent=? WHERE project_id=?")->execute([$newProgress, $id]);
        $pdo->prepare("INSERT INTO project_progress_log (project_id, progress_percent, note, justification, updated_by) VALUES (?,?,?,?,?)")
            ->execute([$id, $newProgress, $note, $justification ?: null, current_user_id()]);
        $pdo->commit();
        audit($pdo, 'PROGRESS_UPDATE', 'projects', $id);

        create_notification(
            $pdo,
            'Project Progress Updated',
            "Project '{$project['project_name']}' progress updated to {$newProgress}% by " . current_name() . ".",
            'success',
            "/ccms/projects/view.php?id=$id"
        );

        redirect('/ccms/projects/view.php?id='.$id);
    }
}

$assignedEmployees = $pdo->prepare("SELECT e.* FROM project_employees pe JOIN employees e ON e.employee_id=pe.employee_id WHERE pe.project_id=?");
$assignedEmployees->execute([$id]);
$assignedEmployees = $assignedEmployees->fetchAll();

$progressLog = $pdo->prepare("SELECT l.*, u.full_name FROM project_progress_log l JOIN users u ON u.user_id=l.updated_by WHERE l.project_id=? ORDER BY l.created_at DESC LIMIT 10");
$progressLog->execute([$id]);
$progressLog = $progressLog->fetchAll();

$budget = 0; $spent = 0;
try {
    $b = $pdo->prepare("SELECT allocated_amount FROM budgets WHERE project_id=?"); $b->execute([$id]);
    $budget = (float)($b->fetchColumn() ?: 0);
    $s = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE project_id=?"); $s->execute([$id]);
    $spent = (float)$s->fetchColumn();
} catch (Exception $e) {}

require_once __DIR__ . '/../includes/page_start.php';
?>
<?php foreach ($errors as $e): ?><div class="alert alert-danger py-2"><?php echo htmlspecialchars($e); ?></div><?php endforeach; ?>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card p-3 mb-3">
      <div class="d-flex justify-content-between">
        <h6 class="mb-2">Project Details</h6>
        <span class="badge bg-secondary align-self-start"><?php echo htmlspecialchars($project['status']); ?></span>
      </div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <tr><th style="width:180px">Client</th><td><?php echo htmlspecialchars($project['client_name']); ?></td></tr>
          <tr><th>Type</th><td><?php echo htmlspecialchars($project['project_type']); ?></td></tr>
          <tr><th>Location</th><td><?php echo htmlspecialchars($project['location']); ?></td></tr>
          <tr><th>Start / End Date</th><td><?php echo $project['start_date']; ?> &rarr; <?php echo $project['end_date']; ?></td></tr>
          <tr><th>Estimated Budget</th><td>LKR <?php echo number_format($project['estimated_budget'],2); ?></td></tr>
          <tr><th>Project Manager</th><td><?php echo htmlspecialchars($project['pm_name'] ?? '—'); ?></td></tr>
        </table>
      </div>
    </div>

    <div class="card p-3 mb-3">
      <h6>Assigned Employees</h6>
      <?php if (!$assignedEmployees): ?><p class="text-muted small mb-0">No employees assigned yet.</p><?php endif; ?>
      <ul class="list-group list-group-flush">
        <?php foreach ($assignedEmployees as $e): ?>
          <li class="list-group-item px-0"><?php echo htmlspecialchars($e['full_name']); ?> <span class="text-muted small">(<?php echo htmlspecialchars($e['role_title']); ?>)</span></li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="card p-3">
      <h6>Budget vs Actual</h6>
      <div class="d-flex justify-content-between small mb-1">
        <span>Spent: LKR <?php echo number_format($spent,2); ?></span>
        <span>Budget: LKR <?php echo number_format($budget,2); ?></span>
      </div>
      <div class="progress">
        <div class="progress-bar <?php echo $spent > $budget ? 'bg-danger' : 'bg-info'; ?>" style="width:<?php echo $budget>0 ? min(100, round($spent/$budget*100)) : 0; ?>%"></div>
      </div>
      <?php if ($spent > $budget && $budget > 0): ?>
        <small class="text-danger">Budget overrun — see Finance &rarr; Expenses for justification records.</small>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card p-3 mb-3">
      <h6>Progress</h6>
      <div class="progress mb-2"><div class="progress-bar bg-success" style="width:<?php echo (int)$project['progress_percent']; ?>%"></div></div>
      <div class="text-center mb-3"><strong><?php echo (int)$project['progress_percent']; ?>%</strong> complete</div>

      <?php if (in_array($role, ['Administrator','Project Manager'])): ?>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
        <input type="hidden" name="update_progress" value="1">
        <div class="mb-2">
          <label class="form-label small">New Progress (%)</label>
          <input type="number" min="0" max="100" name="progress_percent" class="form-control form-control-sm" value="<?php echo (int)$project['progress_percent']; ?>" required>
        </div>
        <div class="mb-2">
          <label class="form-label small">Note</label>
          <input type="text" name="note" class="form-control form-control-sm" placeholder="e.g. Foundation completed">
        </div>
        <div class="mb-2">
          <label class="form-label small">Justification (required only if reducing progress)</label>
          <input type="text" name="justification" class="form-control form-control-sm">
        </div>
        <button class="btn btn-sm btn-success w-100"><i class="bi bi-arrow-repeat"></i> Update Progress</button>
      </form>
      <?php endif; ?>
    </div>

    <div class="card p-3">
      <h6>Progress History</h6>
      <?php if (!$progressLog): ?><p class="text-muted small mb-0">No updates recorded yet.</p><?php endif; ?>
      <ul class="list-group list-group-flush">
        <?php foreach ($progressLog as $l): ?>
          <li class="list-group-item px-0 small">
            <strong><?php echo (int)$l['progress_percent']; ?>%</strong> — <?php echo htmlspecialchars($l['note']); ?>
            <?php if ($l['justification']): ?><br><span class="text-danger">Justification: <?php echo htmlspecialchars($l['justification']); ?></span><?php endif; ?>
            <br><span class="text-muted"><?php echo htmlspecialchars($l['full_name']); ?> · <?php echo $l['created_at']; ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
