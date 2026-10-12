<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['Administrator','Project Manager']);
$page_title = 'Project Registration';

$clients   = $pdo->query("SELECT client_id, name FROM clients WHERE status='active' ORDER BY name")->fetchAll();
$managers  = $pdo->query("SELECT user_id, full_name FROM users WHERE role='Project Manager' AND status='active' ORDER BY full_name")->fetchAll();
$employees = $pdo->query("SELECT employee_id, full_name, role_title FROM employees WHERE status='active' ORDER BY full_name")->fetchAll();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name    = trim($_POST['project_name'] ?? '');
    $client_id = (int)($_POST['client_id'] ?? 0);
    $type    = $_POST['project_type'] ?? '';
    $location = trim($_POST['location'] ?? '');
    $start   = $_POST['start_date'] ?? '';
    $end     = $_POST['end_date'] ?? '';
    $budget  = $_POST['estimated_budget'] ?? '';
    $pm      = (int)($_POST['project_manager_id'] ?? 0);
    $status  = $_POST['status'] ?? 'Planned';
    $assigned = $_POST['employee_ids'] ?? [];

    if ($name === '') $errors[] = 'Project name is required.';
    if (!$client_id) $errors[] = 'Please select a client.';
    if (!in_array($type, ['Residential','Commercial','Infrastructure'], true)) $errors[] = 'Please select a project type.';
    if (!$start || !$end) $errors[] = 'Start and expected end dates are required.';
    elseif (strtotime($end) <= strtotime($start)) $errors[] = 'Expected end date must be later than the start date.';
    if (!is_numeric($budget) || (float)$budget <= 0) $errors[] = 'Estimated budget must be a positive value.';
    if (!$pm) $errors[] = 'Please assign a project manager.';

    if (!$errors) {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO projects (project_name, client_id, project_type, location, start_date, end_date, estimated_budget, project_manager_id, status)
                                VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$name, $client_id, $type, $location, $start, $end, $budget, $pm, $status]);
        $project_id = (int)$pdo->lastInsertId();

        foreach ($assigned as $eid) {
            $pdo->prepare("INSERT IGNORE INTO project_employees (project_id, employee_id) VALUES (?,?)")
                ->execute([$project_id, (int)$eid]);
        }
        $pdo->prepare("INSERT INTO budgets (project_id, allocated_amount) VALUES (?,?)")
            ->execute([$project_id, $budget]);

        $pdo->commit();
        audit($pdo, 'CREATE', 'projects', $project_id);

        create_notification(
            $pdo,
            'New Project Created',
            "Project '$name' (Budget: LKR " . number_format($budget, 2) . ") registered successfully.",
            'success',
            "/ccms/projects/view.php?id=$project_id"
        );

        redirect('/ccms/projects/list.php');
    }
}
require_once __DIR__ . '/../includes/page_start.php';
?>
<div class="card p-4" style="max-width:760px">
  <?php foreach ($errors as $e): ?><div class="alert alert-danger py-2"><?php echo htmlspecialchars($e); ?></div><?php endforeach; ?>
  <form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

    <div class="row g-3">
      <div class="col-md-8">
        <label class="form-label required">Project Name</label>
        <input type="text" name="project_name" class="form-control" required value="<?php echo htmlspecialchars($_POST['project_name'] ?? ''); ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label required">Project Type</label>
        <select name="project_type" class="form-select" required>
          <option value="">-- Select --</option>
          <?php foreach (['Residential','Commercial','Infrastructure'] as $t): ?>
            <option value="<?php echo $t; ?>" <?php echo (($_POST['project_type'] ?? '')===$t)?'selected':''; ?>><?php echo $t; ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-6">
        <label class="form-label required">Client</label>
        <select name="client_id" class="form-select" required>
          <option value="">-- Select registered client --</option>
          <?php foreach ($clients as $c): ?>
            <option value="<?php echo $c['client_id']; ?>" <?php echo (($_POST['client_id'] ?? '')==$c['client_id'])?'selected':''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
          <?php endforeach; ?>
        </select>
        <small class="text-muted">No client? <a href="/ccms/clients/create.php">Register one first</a>.</small>
      </div>
      <div class="col-md-6">
        <label class="form-label">Location</label>
        <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>">
      </div>

      <div class="col-md-4">
        <label class="form-label required">Start Date</label>
        <input type="date" name="start_date" class="form-control" required value="<?php echo htmlspecialchars($_POST['start_date'] ?? ''); ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label required">Expected End Date</label>
        <input type="date" name="end_date" id="endDate" class="form-control" required value="<?php echo htmlspecialchars($_POST['end_date'] ?? ''); ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label required">Estimated Budget (LKR)</label>
        <input type="number" step="0.01" min="0.01" name="estimated_budget" class="form-control" required value="<?php echo htmlspecialchars($_POST['estimated_budget'] ?? ''); ?>">
      </div>

      <div class="col-md-6">
        <label class="form-label required">Project Manager</label>
        <select name="project_manager_id" class="form-select" required>
          <option value="">-- Select --</option>
          <?php foreach ($managers as $m): ?>
            <option value="<?php echo $m['user_id']; ?>" <?php echo (($_POST['project_manager_id'] ?? '')==$m['user_id'])?'selected':''; ?>><?php echo htmlspecialchars($m['full_name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          <?php foreach (['Planned','Ongoing','On Hold','Completed','Cancelled'] as $s): ?>
            <option value="<?php echo $s; ?>" <?php echo (($_POST['status'] ?? 'Planned')===$s)?'selected':''; ?>><?php echo $s; ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12">
        <label class="form-label">Assign Employees</label>
        <div class="border rounded p-2" style="max-height:180px;overflow-y:auto">
          <?php if (!$employees): ?><p class="text-muted small mb-0">No employees registered yet.</p><?php endif; ?>
          <?php foreach ($employees as $e): ?>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="employee_ids[]" value="<?php echo $e['employee_id']; ?>" id="emp<?php echo $e['employee_id']; ?>">
              <label class="form-check-label" for="emp<?php echo $e['employee_id']; ?>">
                <?php echo htmlspecialchars($e['full_name']); ?> <small class="text-muted"><?php echo htmlspecialchars($e['role_title']); ?></small>
              </label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="mt-4">
      <button class="btn btn-success"><i class="bi bi-check-lg"></i> Register Project</button>
      <a href="/ccms/projects/list.php" class="btn btn-outline-secondary">Cancel</a>
    </div>
  </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const form = document.querySelector('form');
  const startInput = document.querySelector('[name=start_date]');
  const endInput = document.querySelector('[name=end_date]');

  function updateEndDateConstraints() {
    if (startInput && startInput.value) {
      endInput.min = startInput.value;
    }
  }

  if (startInput) {
    startInput.addEventListener('change', updateEndDateConstraints);
    startInput.addEventListener('input', updateEndDateConstraints);
    updateEndDateConstraints();
  }

  form.addEventListener('submit', function(e){
    const start = startInput ? startInput.value : '';
    const end = endInput ? endInput.value : '';

    if (!end) {
      alert('Expected end date is required.');
      e.preventDefault();
      if (endInput) endInput.focus();
      return;
    }

    if (start && end <= start) {
      alert('Expected end date must be later than the start date.');
      e.preventDefault();
      if (endInput) endInput.focus();
      return;
    }
  });
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
