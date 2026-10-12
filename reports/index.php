<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$role = current_role();
$page_title = 'Reports';

require_once __DIR__ . '/../includes/page_start.php';
?>
<div class="row g-3">

  <?php if (in_array($role, ['Administrator','Project Manager','Site Staff','Client'])): ?>
  <div class="col-md-6 col-lg-4">
    <div class="card p-3 h-100 border border-secondary shadow-sm">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <h6 class="fw-bold mb-0 text-success"><i class="bi bi-kanban me-2"></i>Project Progress</h6>
        <span class="badge bg-success bg-opacity-25 text-success">Projects</span>
      </div>
      <p class="small text-muted mb-3">Status, progress percentage, timeline and estimated budget for all projects.</p>
      <div class="d-flex gap-2 mt-auto">
        <a href="/ccms/reports/export_pdf.php?type=projects" class="btn btn-sm btn-success flex-grow-1"><i class="bi bi-file-earmark-pdf me-1"></i> Export PDF</a>
        <a href="/ccms/reports/export_csv.php?type=projects" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-spreadsheet me-1"></i> CSV</a>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php if (in_array($role, ['Administrator','Site Staff','Project Manager'])): ?>
  <div class="col-md-6 col-lg-4">
    <div class="card p-3 h-100 border border-secondary shadow-sm">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <h6 class="fw-bold mb-0 text-warning"><i class="bi bi-clipboard-data me-2"></i>Inventory & Stock</h6>
        <span class="badge bg-warning bg-opacity-25 text-warning">Inventory</span>
      </div>
      <p class="small text-muted mb-3">Current stock levels, reorder thresholds, unit prices and total stock valuation.</p>
      <div class="d-flex gap-2 mt-auto">
        <a href="/ccms/reports/export_pdf.php?type=inventory" class="btn btn-sm btn-success flex-grow-1"><i class="bi bi-file-earmark-pdf me-1"></i> Export PDF</a>
        <a href="/ccms/reports/export_csv.php?type=inventory" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-spreadsheet me-1"></i> CSV</a>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php if (in_array($role, ['Administrator','Finance Officer','Project Manager','Client','Procurement Staff'])): ?>
  <div class="col-md-6 col-lg-4">
    <div class="card p-3 h-100 border border-secondary shadow-sm">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <h6 class="fw-bold mb-0 text-info"><i class="bi bi-receipt me-2"></i>Financial & Budgets</h6>
        <span class="badge bg-info bg-opacity-25 text-info">Financial</span>
      </div>
      <p class="small text-muted mb-3">Allocated budgets, verified expenditures, recorded payments and net variances.</p>
      <div class="d-flex gap-2 mt-auto">
        <a href="/ccms/reports/export_pdf.php?type=financial" class="btn btn-sm btn-success flex-grow-1"><i class="bi bi-file-earmark-pdf me-1"></i> Export PDF</a>
        <a href="/ccms/reports/export_csv.php?type=financial" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-spreadsheet me-1"></i> CSV</a>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php if (in_array($role, ['Administrator','Procurement Staff'])): ?>
  <div class="col-md-6 col-lg-4">
    <div class="card p-3 h-100 border border-secondary shadow-sm">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <h6 class="fw-bold mb-0 text-danger"><i class="bi bi-truck-front me-2"></i>Supplier & POs</h6>
        <span class="badge bg-danger bg-opacity-25 text-danger">Procurement</span>
      </div>
      <p class="small text-muted mb-3">All purchase orders, vendor associations, status tracking and total valuations.</p>
      <div class="d-flex gap-2 mt-auto">
        <a href="/ccms/reports/export_pdf.php?type=purchase_orders" class="btn btn-sm btn-success flex-grow-1"><i class="bi bi-file-earmark-pdf me-1"></i> Export PDF</a>
        <a href="/ccms/reports/export_csv.php?type=purchase_orders" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-spreadsheet me-1"></i> CSV</a>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php if (in_array($role, ['Administrator','Project Manager'])): ?>
  <div class="col-md-6 col-lg-4">
    <div class="card p-3 h-100 border border-secondary shadow-sm">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <h6 class="fw-bold mb-0 text-cyan"><i class="bi bi-people-fill me-2"></i>System Users Directory</h6>
        <span class="badge bg-primary bg-opacity-25 text-primary">Users</span>
      </div>
      <p class="small text-muted mb-3">Complete report of all system accounts, user roles, phone, email, NIC and status.</p>
      <div class="d-flex gap-2 mt-auto">
        <a href="/ccms/reports/export_pdf.php?type=users" class="btn btn-sm btn-success flex-grow-1"><i class="bi bi-file-earmark-pdf me-1"></i> Export PDF</a>
        <a href="/ccms/reports/export_csv.php?type=users" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-spreadsheet me-1"></i> CSV</a>
      </div>
    </div>
  </div>
  <?php endif; ?>

</div>

<div class="card p-3 mt-3">
  <p class="small text-muted mb-0">
    <i class="bi bi-info-circle"></i> All system reports export directly as formal, professionally formatted **PDF Invoices & Statements**. Each document includes company branding, itemized tables, total summary boxes, and official signature sections ready for download or printing.
  </p>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
