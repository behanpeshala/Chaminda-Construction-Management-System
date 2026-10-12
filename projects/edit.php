<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['Administrator','Project Manager']);
$page_title = 'Edit Project';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM projects WHERE project_id=?"); $stmt->execute([$id]);
$project = $stmt->fetch();
if (!$project) { set_flash('danger','Project not found.'); redirect('/ccms/projects/list.php'); }

$clients  = $pdo->query("SELECT client_id, name FROM clients ORDER BY name")->fetchAll();
$managers = $pdo->query("SELECT user_id, full_name FROM users WHERE role='Project Manager' AND status='active' ORDER BY full_name")->fetchAll();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim($_POST['project_name'] ?? '');
    $client_id = (int)($_POST['client_id'] ?? 0);
    $type = $_POST['project_type'] ?? '';
    $location = trim($_POST['location'] ?? '');
    $start = $_POST['start_date'] ?? '';
    $end   = $_POST['end_date'] ?? '';
    $budget = $_POST['estimated_budget'] ?? '';
    $pm = (int)($_POST['project_manager_id'] ?? 0);
    $status = $_POST['status'] ?? '';

    if ($name === '') $errors[] = 'Project name is required.';
    if (strtotime($end) <= strtotime($start)) $errors[] = 'Expected end date must be later than the start date.';
    if (!is_numeric($budget) || (float)$budget <= 0) $errors[] = 'Estimated budget must be a positive value.';

    if (!$errors) {
        $pdo->prepare("UPDATE projects SET project_name=?, client_id=?, project_type=?, location=?, start_date=?, end_date=?, estimated_budget=?, project_manager_id=?, status=? WHERE project_id=?")
            ->execute([$name,$client_id,$type,$location,$start,$end,$budget,$pm,$status,$id]);
        audit($pdo,'UPDATE','projects',$id);
        set_flash('success','Project updated.');
        redirect('/ccms/projects/view.php?id='.$id);
    }
}
require_once __DIR__ . '/../includes/page_start.php';
?>
<div class="card p-4" style="max-width:760px">
  <?php foreach ($errors as $e): ?><div class="alert alert-danger py-2"><?php echo htmlspecialchars($e); ?></div><?php endforeach; ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
    <input type="hidden" name="id" value="<?php echo $project['project_id']; ?>">
    <div class="row g-3">
      <div class="col-md-8">
        <label class="form-label required">Project Name</label>
        <input type="text" name="project_name" class="form-control" required value="<?php echo htmlspecialchars($project['project_name']); ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label required">Project Type</label>
        <select name="project_type" class="form-select">
          <?php foreach (['Residential','Commercial','Infrastructure'] as $t): ?>
            <option value="<?php echo $t; ?>" <?php echo $project['project_type']===$t?'selected':''; ?>><?php echo $t; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label required">Client</label>
        <select name="client_id" class="form-select">
          <?php foreach ($clients as $c): ?>
            <option value="<?php echo $c['client_id']; ?>" <?php echo $project['client_id']==$c['client_id']?'selected':''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label">Location</label>
        <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($project['location']); ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label required">Start Date</label>
        <input type="date" name="start_date" class="form-control" value="<?php echo $project['start_date']; ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label required">Expected End Date</label>
        <input type="date" name="end_date" class="form-control" value="<?php echo $project['end_date']; ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label required">Estimated Budget (LKR)</label>
        <input type="number" step="0.01" min="0.01" name="estimated_budget" class="form-control" required value="<?php echo $project['estimated_budget']; ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label required">Project Manager</label>
        <select name="project_manager_id" class="form-select">
          <?php foreach ($managers as $m): ?>
            <option value="<?php echo $m['user_id']; ?>" <?php echo $project['project_manager_id']==$m['user_id']?'selected':''; ?>><?php echo htmlspecialchars($m['full_name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          <?php foreach (['Planned','Ongoing','On Hold','Completed','Cancelled'] as $s): ?>
            <option value="<?php echo $s; ?>" <?php echo $project['status']===$s?'selected':''; ?>><?php echo $s; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="mt-4">
      <button class="btn btn-success"><i class="bi bi-check-lg"></i> Save Changes</button>
      <a href="/ccms/projects/view.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
  </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const form = document.querySelector('form');
  const startInput = document.querySelector('[name=start_date]');
  const endInput = document.querySelector('[name=end_date]');

  function updateEndDateConstraints() {
    if (startInput.value) {
      endInput.min = startInput.value;
    }
  }

  startInput.addEventListener('change', updateEndDateConstraints);
  startInput.addEventListener('input', updateEndDateConstraints);
  updateEndDateConstraints();

  form.addEventListener('submit', function(e){
    const start = startInput.value;
    const end = endInput.value;
    if (start && end && end <= start) {
      alert('Expected end date must be later than the start date.');
      e.preventDefault();
    }
  });
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
