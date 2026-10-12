<?php
$role = current_role();
$current_script = $_SERVER['PHP_SELF'];
function nav_active($dir_or_file, $current_script) {
    return (strpos($current_script, $dir_or_file) !== false) ? 'active' : '';
}
?>
<div class="ccms-sidebar" id="ccmsSidebar">
  <!-- Mobile Drawer Header -->
  <div class="d-flex d-lg-none align-items-center justify-content-between pb-3 mb-2 border-bottom border-secondary border-opacity-25 px-1">
    <div class="d-flex align-items-center gap-2">
      <img src="/ccms/assets/images/logo.png" alt="CCMS Logo" style="height: 28px; width: 28px; object-fit: contain; background: #ffffff; padding: 2px; border-radius: 6px; border: 1px solid #10B981;">
      <div>
        <div class="fw-bold text-light small" style="line-height:1.2;">CCMS Portal</div>
        <div class="text-secondary" style="font-size:0.7rem;"><?php echo htmlspecialchars($role); ?></div>
      </div>
    </div>
    <button type="button" class="btn btn-sm btn-outline-secondary rounded-circle d-flex align-items-center justify-content-center p-0" id="sidebarCloseBtn" style="width: 32px; height: 32px;" aria-label="Close menu">
      <i class="bi bi-x-lg text-light"></i>
    </button>
  </div>
  <ul class="nav flex-column">

    <li class="nav-item">
      <a class="nav-link <?php echo nav_active('dashboard.php',$current_script); ?>" href="/ccms/dashboard.php">
        <i class="bi bi-speedometer2"></i> Dashboard
      </a>
    </li>

    <?php if (in_array($role, ['Administrator'])): ?>
    <li class="nav-item">
      <a class="nav-link <?php echo nav_active('/users/',$current_script); ?>" href="/ccms/users/list.php"><i class="bi bi-person-badge"></i> Users</a>
    </li>
    <?php endif; ?>

    <?php if (in_array($role, ['Administrator','Project Manager'])): ?>
    <li class="nav-item">
      <a class="nav-link <?php echo nav_active('/clients/',$current_script); ?>" href="/ccms/clients/list.php"><i class="bi bi-person-lines-fill"></i> Clients</a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?php echo nav_active('/employees/',$current_script); ?>" href="/ccms/employees/list.php"><i class="bi bi-people"></i> Employees</a>
    </li>
    <?php endif; ?>

    <?php if (in_array($role, ['Administrator','Project Manager','Site Staff','Client','Procurement Staff','Finance Officer'])): ?>
    <li class="nav-item">
      <a class="nav-link <?php echo nav_active('/projects/',$current_script); ?>" href="/ccms/projects/list.php"><i class="bi bi-kanban"></i> Projects</a>
    </li>
    <?php endif; ?>

    <?php if (in_array($role, ['Administrator','Site Staff','Project Manager','Procurement Staff'])): ?>
    <li class="nav-item">
      <a class="nav-link <?php echo nav_active('/materials/',$current_script); ?>" href="/ccms/materials/list.php"><i class="bi bi-box-seam"></i> Materials</a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?php echo nav_active('/inventory/',$current_script); ?>" href="/ccms/inventory/list.php"><i class="bi bi-clipboard-data"></i> Inventory / Stock</a>
    </li>
    <?php endif; ?>

    <?php if (in_array($role, ['Administrator','Site Staff','Project Manager','Procurement Staff','Client'])): ?>
    <li class="nav-item">
      <a class="nav-link <?php echo nav_active('/material_requests/',$current_script); ?>" href="/ccms/material_requests/list.php"><i class="bi bi-truck"></i> Material Requests</a>
    </li>
    <?php endif; ?>

    <?php if (in_array($role, ['Administrator','Procurement Staff','Project Manager'])): ?>
    <li class="nav-item">
      <a class="nav-link <?php echo nav_active('/suppliers/',$current_script); ?>" href="/ccms/suppliers/list.php"><i class="bi bi-truck-front"></i> Suppliers</a>
    </li>
    <?php endif; ?>

    <?php if (in_array($role, ['Administrator','Procurement Staff','Project Manager','Site Staff','Finance Officer'])): ?>
    <li class="nav-item">
      <a class="nav-link <?php echo nav_active('/purchase_orders/',$current_script); ?>" href="/ccms/purchase_orders/list.php"><i class="bi bi-cart-check"></i> Purchase Orders</a>
    </li>
    <?php endif; ?>

    <?php if (in_array($role, ['Administrator','Finance Officer','Project Manager','Client','Procurement Staff'])): ?>
    <li class="nav-item">
      <a class="nav-link <?php echo nav_active('/finance/budgets.php',$current_script); ?>" href="/ccms/finance/budgets.php"><i class="bi bi-wallet2"></i> Budgets</a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?php echo nav_active('/finance/expenses.php',$current_script); ?>" href="/ccms/finance/expenses.php"><i class="bi bi-receipt"></i> Expenses</a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?php echo nav_active('/finance/payments.php',$current_script); ?>" href="/ccms/finance/payments.php"><i class="bi bi-credit-card"></i> Bank Payments</a>
    </li>
    <?php endif; ?>

    <li class="nav-item">
      <a class="nav-link <?php echo nav_active('/reports/',$current_script); ?>" href="/ccms/reports/index.php"><i class="bi bi-bar-chart-line"></i> Reports</a>
    </li>

  </ul>
</div>
