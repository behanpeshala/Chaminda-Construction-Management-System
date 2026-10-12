<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$role = current_role();
$page_title = 'Reports';

require_once __DIR__ . '/../includes/page_start.php';
?>
<div class="row g-3">

  <?php if (in_array($role, ['Administrator','Project Manager','Site Staff','Client'])): ?>
  <div class="col-md-6 col-lg-3">
    <div class="card p-3 h-100">
      <h6><i class="bi bi-kanban"></i> Project Progress</h6>
      <p class="small text-muted">Status and completion percentage for all projects.</p>
      <a href="/ccms/reports/export_pdf.php?type=projects" class="btn btn-sm btn-success"><i class="bi bi-file-earmark-pdf"></i> Export PDF Invoice</a>
    </div>
  </div>
  <?php endif; ?>

  <?php if (in_array($role, ['Administrator','Site Staff','Project Manager'])): ?>
  <div class="col-md-6 col-lg-3">
    <div class="card p-3 h-100">
      <h6><i class="bi bi-clipboard-data"></i> Inventory</h6>
      <p class="small text-muted">Current stock levels for every material.</p>
      <a href="/ccms/reports/export_pdf.php?type=inventory" class="btn btn-sm btn-success"><i class="bi bi-file-earmark-pdf"></i> Export PDF Invoice</a>
    </div>
  </div>
  <?php endif; ?>

  <?php if (in_array($role, ['Administrator','Finance Officer','Project Manager','Client','Procurement Staff'])): ?>
  <div class="col-md-6 col-lg-3">
    <div class="card p-3 h-100">
      <h6><i class="bi bi-receipt"></i> Financial</h6>
      <p class="small text-muted">Budget vs. actual expenditure per project.</p>
      <a href="/ccms/reports/export_pdf.php?type=financial" class="btn btn-sm btn-success"><i class="bi bi-file-earmark-pdf"></i> Export PDF Invoice</a>
    </div>
  </div>
  <?php endif; ?>

  <?php if (in_array($role, ['Administrator','Procurement Staff'])): ?>
  <div class="col-md-6 col-lg-3">
    <div class="card p-3 h-100">
      <h6><i class="bi bi-truck-front"></i> Supplier / Purchase Orders</h6>
      <p class="small text-muted">All purchase orders and their status.</p>
      <a href="/ccms/reports/export_pdf.php?type=purchase_orders" class="btn btn-sm btn-success"><i class="bi bi-file-earmark-pdf"></i> Export PDF Invoice</a>
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
