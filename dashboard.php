<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$page_title = 'Dashboard';

$role = current_role();
$userId = current_user_id();

// KPI helper queries
function count_rows(PDO $pdo, string $sql, array $params = []) {
    try { $s = $pdo->prepare($sql); $s->execute($params); return (int)$s->fetchColumn(); }
    catch (Exception $e) { return 0; }
}
function sum_col(PDO $pdo, string $sql, array $params = []) {
    try { $s = $pdo->prepare($sql); $s->execute($params); return (float)$s->fetchColumn(); }
    catch (Exception $e) { return 0.0; }
}

$totalProjects   = count_rows($pdo, "SELECT COUNT(*) FROM projects");
$ongoingProjects = count_rows($pdo, "SELECT COUNT(*) FROM projects WHERE status='Ongoing'");
$totalClients    = count_rows($pdo, "SELECT COUNT(*) FROM clients WHERE status='active'");
$totalEmployees  = count_rows($pdo, "SELECT COUNT(*) FROM employees WHERE status='active'");
$lowStockCount   = count_rows($pdo, "SELECT COUNT(*) FROM inventory i JOIN materials m ON m.material_id=i.material_id WHERE i.quantity_on_hand <= m.reorder_level");
$pendingRequests = count_rows($pdo, "SELECT COUNT(*) FROM material_requests WHERE status='Pending'");
$pendingPOs      = count_rows($pdo, "SELECT COUNT(*) FROM purchase_orders WHERE status IN ('Pending','Approved')");
$totalBudget     = sum_col($pdo, "SELECT COALESCE(SUM(allocated_amount),0) FROM budgets");
$totalExpense    = sum_col($pdo, "SELECT COALESCE(SUM(amount),0) FROM expenses");
$totalPayments   = sum_col($pdo, "SELECT COALESCE(SUM(amount),0) FROM payments WHERE status IN ('Approved', 'Verified')");

try {
    $projByStatus = $pdo->query("SELECT status, COUNT(*) c FROM projects GROUP BY status")->fetchAll();
} catch (Exception $e) { $projByStatus = []; }

try {
    $expByCategory = $pdo->query("SELECT category, SUM(amount) total FROM expenses GROUP BY category")->fetchAll();
} catch (Exception $e) { $expByCategory = []; }

// 3. Budget vs Actual Spent per Project
try {
    $budgetVsExpense = $pdo->query("SELECT p.project_name, 
                                           COALESCE(b.allocated_amount, 0) AS budget, 
                                           COALESCE(SUM(e.amount), 0) AS spent 
                                    FROM projects p 
                                    LEFT JOIN budgets b ON b.project_id = p.project_id 
                                    LEFT JOIN expenses e ON e.project_id = p.project_id 
                                    GROUP BY p.project_id, p.project_name, b.allocated_amount 
                                    ORDER BY p.created_at DESC LIMIT 6")->fetchAll();
} catch (Exception $e) { $budgetVsExpense = []; }

// 4. Monthly Expense Trend
try {
    $monthlyExpenses = $pdo->query("SELECT DATE_FORMAT(created_at, '%b %Y') AS m_label, 
                                           SUM(amount) AS m_total 
                                    FROM expenses 
                                    GROUP BY YEAR(created_at), MONTH(created_at), m_label 
                                    ORDER BY MIN(created_at) ASC LIMIT 6")->fetchAll();
} catch (Exception $e) { $monthlyExpenses = []; }

try {
    $myProjects = [];
    if ($role === 'Client') {
        $stmt = $pdo->prepare("SELECT p.*, c.name AS client_name FROM projects p JOIN clients c ON c.client_id=p.client_id
                                WHERE (c.email = (SELECT email FROM users WHERE user_id=?) OR c.nic_number = (SELECT nic_number FROM users WHERE user_id=?))
                                ORDER BY p.created_at DESC");
        $stmt->execute([$userId, $userId]);
        $myProjects = $stmt->fetchAll();
    } elseif ($role === 'Project Manager') {
        $stmt = $pdo->prepare("SELECT p.*, c.name AS client_name FROM projects p JOIN clients c ON c.client_id=p.client_id WHERE p.project_manager_id = ? ORDER BY p.created_at DESC LIMIT 5");
        $stmt->execute([$userId]);
        $myProjects = $stmt->fetchAll();
    } else {
        $myProjects = $pdo->query("SELECT p.*, c.name AS client_name FROM projects p JOIN clients c ON c.client_id=p.client_id ORDER BY p.created_at DESC LIMIT 5")->fetchAll();
    }
} catch (Exception $e) { $myProjects = []; }

require_once __DIR__ . '/includes/page_start.php';
?>

<!-- Welcome Banner -->
<div class="card p-3 mb-4 bg-gradient-dark border-emerald">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
      <h5 class="fw-bold mb-1 text-emerald"><i class="bi bi-person-badge-fill me-2"></i>Welcome, <?php echo htmlspecialchars(current_name()); ?>!</h5>
      <p class="text-secondary small mb-0">Role: <span class="badge bg-warning text-dark font-monospace"><?php echo htmlspecialchars($role); ?></span> — CCMS Enterprise Portal</p>
    </div>
    <div>
      <span class="badge bg-dark border border-secondary font-monospace"><i class="bi bi-calendar-event me-1"></i><?php echo date('Y-m-d (l)'); ?></span>
    </div>
  </div>
</div>

<!-- Role Specific Quick Actions Header -->
<div class="card p-3 mb-4">
  <h6 class="fw-bold text-cyan mb-3"><i class="bi bi-lightning-charge-fill me-2"></i>Quick Actions & Services</h6>
  <div class="d-flex flex-wrap gap-2">
    
    <?php if ($role === 'Client'): ?>
      <a href="/ccms/finance/payments.php" class="btn btn-sm btn-success"><i class="bi bi-bank2 me-1"></i> Make Bank Payment</a>
      <a href="/ccms/finance/budgets.php" class="btn btn-sm btn-outline-info"><i class="bi bi-wallet2 me-1"></i> Project Budgets</a>
      <a href="/ccms/finance/expenses.php" class="btn btn-sm btn-outline-cyan"><i class="bi bi-receipt me-1"></i> Project Expenses</a>
      <a href="/ccms/material_requests/create.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-truck me-1"></i> Request Material / Inquiry</a>
      <a href="/ccms/projects/list.php" class="btn btn-sm btn-outline-info"><i class="bi bi-kanban me-1"></i> View My Projects</a>
      <a href="/ccms/reports/index.php" class="btn btn-sm btn-outline-warning"><i class="bi bi-bar-chart me-1"></i> View Progress Reports</a>
    
    <?php elseif ($role === 'Site Staff'): ?>
      <a href="/ccms/material_requests/create.php" class="btn btn-sm btn-success"><i class="bi bi-send me-1"></i> Request Materials for Site</a>
      <a href="/ccms/inventory/move.php" class="btn btn-sm btn-outline-cyan"><i class="bi bi-arrow-left-right me-1"></i> Record Stock Movement</a>
      <a href="/ccms/projects/list.php" class="btn btn-sm btn-outline-info"><i class="bi bi-kanban me-1"></i> Assigned Projects</a>
      <a href="/ccms/materials/list.php" class="btn btn-sm btn-outline-warning"><i class="bi bi-box-seam me-1"></i> Materials Catalogue</a>

    <?php elseif ($role === 'Procurement Staff'): ?>
      <a href="/ccms/purchase_orders/create.php" class="btn btn-sm btn-success"><i class="bi bi-cart-plus me-1"></i> Create Purchase Order</a>
      <a href="/ccms/finance/payments.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-bank2 me-1"></i> Bank Payments</a>
      <a href="/ccms/finance/expenses.php" class="btn btn-sm btn-outline-cyan"><i class="bi bi-receipt me-1"></i> Expenses</a>
      <a href="/ccms/finance/budgets.php" class="btn btn-sm btn-outline-info"><i class="bi bi-wallet2 me-1"></i> Budgets</a>
      <a href="/ccms/suppliers/create.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-truck-front me-1"></i> Register New Supplier</a>
      <a href="/ccms/material_requests/list.php" class="btn btn-sm btn-outline-info"><i class="bi bi-truck me-1"></i> View Approved Requests</a>
      <a href="/ccms/purchase_orders/list.php" class="btn btn-sm btn-outline-warning"><i class="bi bi-journal-check me-1"></i> View Purchase Orders</a>

    <?php elseif ($role === 'Finance Officer'): ?>
      <a href="/ccms/finance/payments.php" class="btn btn-sm btn-success"><i class="bi bi-bank2 me-1"></i> Record Bank Payment</a>
      <a href="/ccms/finance/expenses.php" class="btn btn-sm btn-outline-cyan"><i class="bi bi-receipt me-1"></i> Record Expense</a>
      <a href="/ccms/finance/budgets.php" class="btn btn-sm btn-outline-info"><i class="bi bi-wallet2 me-1"></i> Set Project Budgets</a>
      <a href="/ccms/reports/index.php" class="btn btn-sm btn-outline-warning"><i class="bi bi-file-earmark-pdf me-1"></i> Financial Reports</a>

    <?php elseif ($role === 'Project Manager'): ?>
      <a href="/ccms/projects/create.php" class="btn btn-sm btn-success"><i class="bi bi-plus-circle me-1"></i> Register New Project</a>
      <a href="/ccms/finance/budgets.php" class="btn btn-sm btn-outline-info"><i class="bi bi-wallet2 me-1"></i> Budgets</a>
      <a href="/ccms/finance/expenses.php" class="btn btn-sm btn-outline-cyan"><i class="bi bi-receipt me-1"></i> Expenses</a>
      <a href="/ccms/finance/payments.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-bank2 me-1"></i> Bank Payments</a>
      <a href="/ccms/clients/create.php" class="btn btn-sm btn-outline-cyan"><i class="bi bi-person-plus me-1"></i> Register Client</a>
      <a href="/ccms/employees/create.php" class="btn btn-sm btn-outline-info"><i class="bi bi-person-badge me-1"></i> Add Employee</a>
      <a href="/ccms/material_requests/list.php" class="btn btn-sm btn-outline-warning"><i class="bi bi-check-square me-1"></i> Approve Material Requests</a>

    <?php else: ?> <!-- Administrator -->
      <a href="/ccms/projects/create.php" class="btn btn-sm btn-success"><i class="bi bi-plus-lg me-1"></i> New Project</a>
      <a href="/ccms/users/create.php" class="btn btn-sm btn-outline-cyan"><i class="bi bi-person-plus me-1"></i> New User</a>
      <a href="/ccms/finance/payments.php" class="btn btn-sm btn-outline-info"><i class="bi bi-bank2 me-1"></i> Bank Payments</a>
      <a href="/ccms/purchase_orders/create.php" class="btn btn-sm btn-outline-warning"><i class="bi bi-cart-plus me-1"></i> New Purchase Order</a>
      <a href="/ccms/reports/index.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-bar-chart me-1"></i> Reports</a>
    <?php endif; ?>
  </div>
</div>

<!-- KPI Summary Cards -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="ccms-stat-card bg-ccms-1">
      <div class="d-flex justify-content-between align-items-center">
        <span class="small fw-bold text-uppercase">Projects</span>
        <i class="bi bi-kanban fs-4"></i>
      </div>
      <div class="stat-value"><?php echo $totalProjects; ?></div>
      <div class="small opacity-75 mt-1"><?php echo $ongoingProjects; ?> ongoing</div>
    </div>
  </div>

  <div class="col-6 col-md-3">
    <div class="ccms-stat-card bg-ccms-2">
      <div class="d-flex justify-content-between align-items-center">
        <span class="small fw-bold text-uppercase">Material Requests</span>
        <i class="bi bi-truck fs-4"></i>
      </div>
      <div class="stat-value"><?php echo $pendingRequests; ?></div>
      <div class="small opacity-75 mt-1">Pending approval</div>
    </div>
  </div>

  <div class="col-6 col-md-3">
    <div class="ccms-stat-card bg-ccms-3">
      <div class="d-flex justify-content-between align-items-center">
        <span class="small fw-bold text-uppercase">Purchase Orders</span>
        <i class="bi bi-cart-check fs-4"></i>
      </div>
      <div class="stat-value"><?php echo $pendingPOs; ?></div>
      <div class="small opacity-75 mt-1">Open orders</div>
    </div>
  </div>

  <div class="col-6 col-md-3">
    <div class="ccms-stat-card bg-ccms-4">
      <div class="d-flex justify-content-between align-items-center">
        <span class="small fw-bold text-uppercase">Payments / Expenses</span>
        <i class="bi bi-bank fs-4"></i>
      </div>
      <div class="stat-value" style="font-size:1.1rem">LKR <?php echo number_format($totalPayments,0); ?></div>
      <div class="small opacity-75 mt-1">LKR <?php echo number_format($totalExpense,0); ?> spent</div>
    </div>
  </div>
</div>

<!-- Interactive Charts Grid (2x2 Matrix) -->
<div class="row g-3 mb-4">
  <!-- Chart 1: Project Status Breakdown -->
  <div class="col-md-6">
    <div class="card p-3 h-100 shadow-sm border-secondary border-opacity-25">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h6 class="fw-bold text-emerald mb-0"><i class="bi bi-pie-chart-fill me-2"></i>Projects Status Distribution</h6>
        <span class="badge bg-dark text-secondary border border-secondary" style="font-size:0.7rem">Live Realtime</span>
      </div>
      <div style="position: relative; height: 220px;">
        <canvas id="projStatusChart"></canvas>
      </div>
    </div>
  </div>

  <!-- Chart 2: Budget vs Actual Spent per Project -->
  <div class="col-md-6">
    <div class="card p-3 h-100 shadow-sm border-secondary border-opacity-25">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h6 class="fw-bold text-warning mb-0"><i class="bi bi-bar-chart-steps me-2"></i>Allocated Budget vs Actual Spent (LKR)</h6>
        <span class="badge bg-dark text-secondary border border-secondary" style="font-size:0.7rem">Project Comparison</span>
      </div>
      <div style="position: relative; height: 220px;">
        <canvas id="budgetVsExpenseChart"></canvas>
      </div>
    </div>
  </div>

  <!-- Chart 3: Expenses Breakdown by Category -->
  <div class="col-md-6">
    <div class="card p-3 h-100 shadow-sm border-secondary border-opacity-25">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h6 class="fw-bold text-cyan mb-0"><i class="bi bi-diagram-3-fill me-2"></i>Expenses Breakdown by Category</h6>
        <span class="badge bg-dark text-secondary border border-secondary" style="font-size:0.7rem">Cost Categories</span>
      </div>
      <div style="position: relative; height: 220px;">
        <canvas id="expCategoryChart"></canvas>
      </div>
    </div>
  </div>

  <!-- Chart 4: Monthly Expenditure Trend -->
  <div class="col-md-6">
    <div class="card p-3 h-100 shadow-sm border-secondary border-opacity-25">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h6 class="fw-bold text-info mb-0"><i class="bi bi-graph-up-arrow me-2"></i>Monthly Expenditure Trend</h6>
        <span class="badge bg-dark text-secondary border border-secondary" style="font-size:0.7rem">Financial Flow</span>
      </div>
      <div style="position: relative; height: 220px;">
        <canvas id="monthlyExpenseChart"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- Projects Table -->
<div class="card p-3 mt-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h6 class="fw-bold text-warning mb-0"><i class="bi bi-buildings-fill me-2"></i><?php echo $role === 'Client' ? 'Your Assigned Projects' : 'Active Projects List'; ?></h6>
    <a href="/ccms/projects/list.php" class="btn btn-xs btn-outline-info">View All Projects &rarr;</a>
  </div>

  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle">
      <thead><tr><th>Project Name</th><th>Client</th><th>Type</th><th>Status</th><th>Progress</th><th>Action</th></tr></thead>
      <tbody>
      <?php if (!$myProjects): ?>
        <tr><td colspan="6" class="text-muted text-center py-4">No active projects assigned to display.</td></tr>
      <?php else: foreach ($myProjects as $p): ?>
        <tr>
          <td class="fw-semibold text-light"><?php echo htmlspecialchars($p['project_name']); ?></td>
          <td><?php echo htmlspecialchars($p['client_name'] ?? '-'); ?></td>
          <td><?php echo htmlspecialchars($p['project_type']); ?></td>
          <td><span class="badge bg-secondary"><?php echo htmlspecialchars($p['status']); ?></span></td>
          <td style="min-width:140px">
            <div class="progress"><div class="progress-bar bg-success" style="width:<?php echo (int)$p['progress_percent']; ?>%"></div></div>
            <small class="font-monospace"><?php echo (int)$p['progress_percent']; ?>%</small>
          </td>
          <td>
            <a href="/ccms/projects/view.php?id=<?php echo $p['project_id']; ?>" class="btn btn-xs btn-outline-secondary"><i class="bi bi-eye"></i> View</a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
  if (typeof Chart !== 'undefined') {
    Chart.defaults.color = '#94a3b8';
    Chart.defaults.borderColor = 'rgba(51, 65, 85, 0.4)';
    Chart.defaults.font.family = "'Inter', sans-serif";
  }

  // 1. Projects Status Doughnut Chart
  new Chart(document.getElementById('projStatusChart'), {
    type: 'doughnut',
    data: {
      labels: <?php echo json_encode(array_column($projByStatus, 'status')); ?>,
      datasets: [{
        data: <?php echo json_encode(array_column($projByStatus, 'c')); ?>,
        backgroundColor: ['#10b981', '#38bdf8', '#f59e0b', '#6366f1', '#ef4444'],
        borderWidth: 2,
        borderColor: '#0f172a'
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'right', labels: { color: '#cbd5e1', font: { size: 11 } } }
      }
    }
  });

  // 2. Budget vs Actual Spent Grouped Bar Chart
  new Chart(document.getElementById('budgetVsExpenseChart'), {
    type: 'bar',
    data: {
      labels: <?php echo json_encode(array_column($budgetVsExpense, 'project_name')); ?>,
      datasets: [
        {
          label: 'Allocated Budget',
          data: <?php echo json_encode(array_column($budgetVsExpense, 'budget')); ?>,
          backgroundColor: 'rgba(16, 185, 129, 0.75)',
          borderColor: '#10b981',
          borderWidth: 1,
          borderRadius: 4
        },
        {
          label: 'Actual Spent',
          data: <?php echo json_encode(array_column($budgetVsExpense, 'spent')); ?>,
          backgroundColor: 'rgba(245, 158, 11, 0.85)',
          borderColor: '#f59e0b',
          borderWidth: 1,
          borderRadius: 4
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'top', labels: { color: '#cbd5e1', font: { size: 11 } } },
        tooltip: {
          callbacks: {
            label: function(context) {
              return context.dataset.label + ': LKR ' + Number(context.raw).toLocaleString();
            }
          }
        }
      },
      scales: {
        x: { ticks: { color: '#94a3b8', font: { size: 10 } }, grid: { display: false } },
        y: { 
          ticks: { 
            color: '#94a3b8',
            callback: function(val) { return 'LKR ' + (val >= 1000000 ? (val/1000000).toFixed(1) + 'M' : val.toLocaleString()); }
          },
          grid: { color: 'rgba(51, 65, 85, 0.4)' } 
        }
      }
    }
  });

  // 3. Expenses Category Bar Chart
  new Chart(document.getElementById('expCategoryChart'), {
    type: 'bar',
    data: {
      labels: <?php echo json_encode(array_column($expByCategory, 'category')); ?>,
      datasets: [{
        label: 'Spent (LKR)',
        data: <?php echo json_encode(array_column($expByCategory, 'total')); ?>,
        backgroundColor: ['#38bdf8', '#10b981', '#f59e0b', '#a855f7', '#ec4899', '#6366f1'],
        borderRadius: 4
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: function(context) {
              return 'Spent: LKR ' + Number(context.raw).toLocaleString();
            }
          }
        }
      },
      scales: {
        x: { ticks: { color: '#94a3b8', font: { size: 10 } }, grid: { display: false } },
        y: { 
          ticks: { 
            color: '#94a3b8',
            callback: function(val) { return 'LKR ' + (val >= 1000000 ? (val/1000000).toFixed(1) + 'M' : val.toLocaleString()); }
          },
          grid: { color: 'rgba(51, 65, 85, 0.4)' } 
        }
      }
    }
  });

  // 4. Monthly Expense Line Chart
  new Chart(document.getElementById('monthlyExpenseChart'), {
    type: 'line',
    data: {
      labels: <?php echo json_encode(array_column($monthlyExpenses, 'm_label')); ?>,
      datasets: [{
        label: 'Monthly Expenditure',
        data: <?php echo json_encode(array_column($monthlyExpenses, 'm_total')); ?>,
        borderColor: '#06b6d4',
        backgroundColor: 'rgba(6, 182, 212, 0.15)',
        fill: true,
        tension: 0.35,
        pointBackgroundColor: '#38bdf8',
        pointRadius: 4
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: function(context) {
              return 'Monthly Spent: LKR ' + Number(context.raw).toLocaleString();
            }
          }
        }
      },
      scales: {
        x: { ticks: { color: '#94a3b8', font: { size: 10 } }, grid: { display: false } },
        y: { 
          ticks: { 
            color: '#94a3b8',
            callback: function(val) { return 'LKR ' + (val >= 1000000 ? (val/1000000).toFixed(1) + 'M' : val.toLocaleString()); }
          },
          grid: { color: 'rgba(51, 65, 85, 0.4)' } 
        }
      }
    }
  });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
