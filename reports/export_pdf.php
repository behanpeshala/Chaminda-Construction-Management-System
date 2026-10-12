<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$role = current_role();
$type = $_GET['type'] ?? '';
$id   = (int)($_GET['id'] ?? 0);

$allowed = [
    'projects'        => ['Administrator','Project Manager','Site Staff','Client'],
    'inventory'       => ['Administrator','Site Staff','Project Manager'],
    'financial'       => ['Administrator','Finance Officer','Project Manager','Client','Procurement Staff'],
    'purchase_orders' => ['Administrator','Procurement Staff'],
    'po_single'       => ['Administrator','Procurement Staff','Project Manager','Finance Officer'],
    'users'           => ['Administrator','Project Manager'],
];

if (!isset($allowed[$type]) || !in_array($role, $allowed[$type], true)) {
    http_response_code(403);
    die('Access denied for this report/invoice.');
}

$refNo = 'INV-' . strtoupper(substr($type, 0, 3)) . '-' . date('Ymd-His');
$title = '';
$headers = [];
$rows = [];
$summaryText = '';
$totalAmount = 0;
$hasAmount = false;

switch ($type) {
    case 'projects':
        $title = 'Project Progress & Financial Statement';
        $headers = ['Project Name', 'Client', 'Type', 'Status', 'Progress %', 'Start Date', 'Expected End Date', 'Estimated Budget (LKR)'];
        $stmt = $pdo->query("SELECT p.project_name, c.name AS client_name, p.project_type, p.status, p.progress_percent, p.start_date, p.end_date, p.estimated_budget
                              FROM projects p JOIN clients c ON c.client_id=p.client_id ORDER BY p.created_at DESC");
        while ($r = $stmt->fetch()) {
            $budget = (float)$r['estimated_budget'];
            $totalAmount += $budget;
            $rows[] = [
                $r['project_name'],
                $r['client_name'],
                $r['project_type'],
                $r['status'],
                $r['progress_percent'] . '%',
                $r['start_date'],
                $r['end_date'],
                number_format($budget, 2)
            ];
        }
        $hasAmount = true;
        $summaryText = 'Total Estimated Project Portfolio Budget';
        break;

    case 'inventory':
        $title = 'Inventory & Stock Valuation Statement';
        $headers = ['Material Name', 'Unit', 'Stock Qty', 'Reorder Level', 'Unit Price (LKR)', 'Stock Value (LKR)'];
        $stmt = $pdo->query("SELECT m.name, m.unit, COALESCE(i.quantity_on_hand,0) AS qty, m.reorder_level, m.unit_price
                              FROM materials m LEFT JOIN inventory i ON i.material_id=m.material_id ORDER BY m.name");
        while ($r = $stmt->fetch()) {
            $val = (float)$r['qty'] * (float)$r['unit_price'];
            $totalAmount += $val;
            $rows[] = [
                $r['name'],
                $r['unit'],
                number_format($r['qty']),
                number_format($r['reorder_level']),
                number_format($r['unit_price'], 2),
                number_format($val, 2)
            ];
        }
        $hasAmount = true;
        $summaryText = 'Total Inventory Stock Valuation';
        break;

    case 'financial':
        $title = 'Financial Budget vs. Expenditure Invoice Summary';
        $headers = ['Project Name', 'Allocated Budget (LKR)', 'Total Expenses (LKR)', 'Total Payments (LKR)', 'Net Variance (LKR)'];
        $stmt = $pdo->query("SELECT p.project_name, COALESCE(b.allocated_amount,0) AS budget,
                                     COALESCE((SELECT SUM(amount) FROM expenses e WHERE e.project_id=p.project_id),0) AS expenses,
                                     COALESCE((SELECT SUM(amount) FROM payments pay WHERE pay.project_id=p.project_id AND pay.status IN ('Approved','Verified')),0) AS payments
                              FROM projects p LEFT JOIN budgets b ON b.project_id=p.project_id ORDER BY p.created_at DESC");
        while ($r = $stmt->fetch()) {
            $b = (float)$r['budget'];
            $exp = (float)$r['expenses'];
            $var = $b - $exp;
            $totalAmount += $exp;
            $rows[] = [
                $r['project_name'],
                number_format($b, 2),
                number_format($exp, 2),
                number_format($r['payments'], 2),
                number_format($var, 2)
            ];
        }
        $hasAmount = true;
        $summaryText = 'Total Expenditure Incurred';
        break;

    case 'purchase_orders':
        $title = 'Purchase Orders & Supplier Invoice Summary';
        $headers = ['PO Reference', 'Supplier Name', 'Order Status', 'Created Date', 'Total Value (LKR)'];
        $stmt = $pdo->query("SELECT po.po_id, CONCAT('PO-', LPAD(po.po_id,4,'0')) AS po_num, s.name AS supplier_name, po.status, po.created_at,
                                     COALESCE((SELECT SUM(quantity*unit_price) FROM purchase_order_items WHERE po_id=po.po_id),0) AS total_val
                              FROM purchase_orders po JOIN suppliers s ON s.supplier_id=po.supplier_id ORDER BY po.created_at DESC");
        while ($r = $stmt->fetch()) {
            $val = (float)$r['total_val'];
            $totalAmount += $val;
            $rows[] = [
                $r['po_num'],
                $r['supplier_name'],
                $r['status'],
                $r['created_at'],
                number_format($val, 2)
            ];
        }
        $hasAmount = true;
        $summaryText = 'Total Purchase Order Procurement Value';
        break;

    case 'po_single':
        $stmt = $pdo->prepare("SELECT po.*, s.name AS supplier_name, s.contact_person, s.phone, s.email, s.address, u.full_name AS created_by_name
                                FROM purchase_orders po JOIN suppliers s ON s.supplier_id=po.supplier_id
                                JOIN users u ON u.user_id=po.created_by WHERE po.po_id=?");
        $stmt->execute([$id]);
        $po = $stmt->fetch();
        if (!$po) { die('Purchase Order not found.'); }

        $refNo = 'PO-INV-' . str_pad($po['po_id'], 4, '0', STR_PAD_LEFT);
        $title = 'OFFICIAL PURCHASE ORDER INVOICE';
        $headers = ['Item #', 'Material Description', 'Quantity', 'Unit Price (LKR)', 'Line Total (LKR)'];

        $itemsStmt = $pdo->prepare("SELECT poi.*, m.name, m.unit FROM purchase_order_items poi JOIN materials m ON m.material_id=poi.material_id WHERE poi.po_id=?");
        $itemsStmt->execute([$id]);
        $idx = 1;
        while ($it = $itemsStmt->fetch()) {
            $lineTotal = (float)$it['quantity'] * (float)$it['unit_price'];
            $totalAmount += $lineTotal;
            $rows[] = [
                $idx++,
                $it['name'] . ' (' . $it['unit'] . ')',
                number_format($it['quantity']),
                number_format($it['unit_price'], 2),
                number_format($lineTotal, 2)
            ];
        }
        $hasAmount = true;
        $summaryText = 'Total Invoice Amount';
        break;

    case 'users':
        $title = 'System Users & Account Directory Statement';
        $headers = ['User ID', 'Full Name', 'Username', 'Role', 'Email', 'Phone', 'NIC Number', 'Status', 'Registered Date'];
        $stmt = $pdo->query("SELECT user_id, full_name, username, role, email, phone, nic_number, status, created_at FROM users ORDER BY user_id ASC");
        while ($r = $stmt->fetch()) {
            $rows[] = [
                '#USR-' . str_pad($r['user_id'], 3, '0', STR_PAD_LEFT),
                $r['full_name'],
                $r['username'],
                $r['role'],
                $r['email'],
                $r['phone'] ?: 'N/A',
                $r['nic_number'] ?: 'N/A',
                strtoupper($r['status']),
                date('Y-m-d', strtotime($r['created_at']))
            ];
        }
        $hasAmount = false;
        $summaryText = 'Total Registered System Users';
        break;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($title); ?> - <?php echo $refNo; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<style>
  body {
    background-color: #0b0f19;
    color: #0f172a;
    font-family: 'Inter', sans-serif;
    padding: 20px 0;
  }
  .action-bar {
    max-width: <?php echo (count($headers) >= 8) ? "1080px" : "900px"; ?>;
    margin: 0 auto 20px auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  .invoice-card {
    background: #ffffff;
    max-width: <?php echo (count($headers) >= 8) ? "1080px" : "900px"; ?>;
    margin: 0 auto;
    border-radius: 12px;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
    padding: 40px;
    color: #1e293b;
    position: relative;
  }
  .company-logo {
    font-size: 1.6rem;
    font-weight: 800;
    color: #0f4c3a;
    letter-spacing: -0.02em;
  }
  .invoice-title-badge {
    font-size: 0.85rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    background: #f1f5f9;
    color: #475569;
    padding: 6px 14px;
    border-radius: 20px;
    display: inline-block;
  }
  .table-invoice {
    width: 100%;
    margin-top: 25px;
    border-collapse: collapse;
  }
  .table-invoice th {
    background: #0f4c3a;
    color: #ffffff;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 10px 14px;
    font-weight: 600;
  }
  .table-invoice td {
    padding: 12px 14px;
    font-size: 0.88rem;
    border-bottom: 1px solid #e2e8f0;
    color: #334155;
  }
  .table-invoice tbody tr:nth-child(even) {
    background-color: #f8fafc;
  }
  .totals-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 18px 24px;
    width: 340px;
    margin-left: auto;
    margin-top: 20px;
  }
  .totals-box .amount-val {
    font-family: 'JetBrains Mono', monospace;
    font-size: 1.25rem;
    font-weight: 700;
    color: #0f4c3a;
  }
  .signature-block {
    margin-top: 60px;
    display: flex;
    justify-content: space-between;
    text-align: center;
  }
  .signature-line {
    width: 200px;
    border-top: 1px dashed #94a3b8;
    padding-top: 8px;
    font-size: 0.8rem;
    color: #64748b;
    font-weight: 500;
  }

  @media print {
    body {
      background: #ffffff;
      padding: 0;
    }
    .action-bar {
      display: none !important;
    }
    .invoice-card {
      box-shadow: none;
      padding: 0;
      max-width: 100%;
    }
  }
</style>
</head>
<body>

<div class="action-bar">
  <a href="/ccms/reports/index.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left"></i> Back to Reports</a>
  <div class="d-flex gap-2">
    <button onclick="downloadPDF()" class="btn btn-success btn-sm"><i class="bi bi-download"></i> Download PDF Invoice</button>
    <button onclick="window.print()" class="btn btn-primary btn-sm"><i class="bi bi-printer"></i> Print / Save PDF</button>
  </div>
</div>

<div class="invoice-card" id="invoiceArea">
  <!-- Company Header -->
  <div class="d-flex justify-content-between align-items-start border-bottom pb-4 mb-4">
    <div>
      <div class="company-logo d-flex align-items-center gap-2">
        <i class="bi bi-buildings-fill text-success"></i>
        <span>CHAMINDA CONSTRUCTION</span>
      </div>
      <div class="small text-secondary mt-1">
        Chaminda Construction Company (Pvt) Ltd · Digital Site Management System<br>
        No. 45, Kandy Road, Colombo / Kandy, Sri Lanka · Tel: +94 11 234 5678<br>
        Email: info@chamindaconstruction.lk · Web: www.chamindaconstruction.lk
      </div>
    </div>
    <div class="text-end">
      <div class="invoice-title-badge mb-2">OFFICIAL STATEMENT</div>
      <h5 class="fw-bold mb-1" style="color:#0f4c3a; font-family:'JetBrains Mono', monospace"><?php echo htmlspecialchars($refNo); ?></h5>
      <div class="small text-muted">Issued: <?php echo date('F d, Y - H:i:s'); ?></div>
      <div class="small text-muted">Generated By: <?php echo htmlspecialchars(current_name()); ?> (<?php echo htmlspecialchars(current_role()); ?>)</div>
    </div>
  </div>

  <!-- Document Meta & Bill To Info -->
  <div class="row mb-4">
    <div class="col-8">
      <h6 class="fw-bold text-uppercase text-secondary small mb-1">Document Subject / Description</h6>
      <h5 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($title); ?></h5>
      <?php if (isset($po)): ?>
        <p class="small text-muted mb-0">
          <strong>Supplier:</strong> <?php echo htmlspecialchars($po['supplier_name']); ?> | 
          <strong>Contact:</strong> <?php echo htmlspecialchars($po['contact_person'] ?: 'N/A'); ?> | 
          <strong>Phone:</strong> <?php echo htmlspecialchars($po['phone'] ?: 'N/A'); ?><br>
          <strong>Address:</strong> <?php echo htmlspecialchars($po['address'] ?: 'N/A'); ?>
        </p>
      <?php else: ?>
        <p class="small text-muted mb-0">Official system generated report statement for internal compliance and record verification.</p>
      <?php endif; ?>
    </div>
    <div class="col-4 text-end">
      <div class="p-2 border rounded bg-light">
        <div class="small text-secondary">System Status</div>
        <div class="fw-bold text-success"><i class="bi bi-shield-check"></i> VERIFIED & AUTHENTIC</div>
      </div>
    </div>
  </div>

  <!-- Formatted Data Table -->
  <table class="table-invoice">
    <thead>
      <tr>
        <?php foreach ($headers as $h): ?>
          <th><?php echo htmlspecialchars($h); ?></th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $row): ?>
        <tr>
          <?php foreach ($row as $idx => $cell): ?>
            <td class="<?php echo ($hasAmount && $idx === count($row)-1) ? 'fw-bold text-end' : ''; ?>">
              <?php echo htmlspecialchars($cell); ?>
            </td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <!-- Totals Summary Box -->
  <?php if ($hasAmount): ?>
  <div class="totals-box">
    <div class="d-flex justify-content-between mb-2">
      <span class="small text-secondary">Subtotal (Net Amount):</span>
      <span class="fw-bold">LKR <?php echo number_format($totalAmount, 2); ?></span>
    </div>
    <div class="d-flex justify-content-between mb-2">
      <span class="small text-secondary">Taxes & Charges:</span>
      <span class="text-success small fw-bold">0.00 (Included)</span>
    </div>
    <div class="d-flex justify-content-between pt-2 border-top">
      <span class="fw-bold text-dark small text-uppercase"><?php echo htmlspecialchars($summaryText); ?>:</span>
      <span class="amount-val">LKR <?php echo number_format($totalAmount, 2); ?></span>
    </div>
  </div>
  <?php elseif ($summaryText !== ''): ?>
  <div class="totals-box">
    <div class="d-flex justify-content-between align-items-center">
      <span class="fw-bold text-dark small text-uppercase"><?php echo htmlspecialchars($summaryText); ?>:</span>
      <span class="amount-val text-success"><?php echo count($rows); ?> Records</span>
    </div>
  </div>
  <?php endif; ?>

  <!-- Authorization Signatures -->
  <div class="signature-block">
    <div>
      <div class="signature-line">Prepared By<br><strong><?php echo htmlspecialchars(current_name()); ?></strong></div>
    </div>
    <div>
      <div class="signature-line">Checked & Audited By<br><strong>Management Department</strong></div>
    </div>
    <div>
      <div class="signature-line">Authorized Signatory<br><strong>Chaminda Construction</strong></div>
    </div>
  </div>

  <!-- Footer Notice -->
  <div class="text-center text-muted small mt-5 pt-3 border-top" style="font-size:0.75rem">
    This is an officially generated document from Chaminda Construction Management System (CCMS).<br>
    All records contained herein are confidential and property of Chaminda Construction Company (Pvt) Ltd.
  </div>
</div>

<script>
function downloadPDF() {
  const element = document.getElementById('invoiceArea');
  const opt = {
    margin:       0.3,
    filename:     '<?php echo $refNo; ?>.pdf',
    image:        { type: 'jpeg', quality: 0.98 },
    html2canvas:  { scale: 2, useCORS: true },
    jsPDF:        { unit: 'in', format: 'letter', orientation: '<?php echo (count($headers) >= 8) ? "landscape" : "portrait"; ?>' }
  };
  html2pdf().set(opt).from(element).save();
}

// Check if download parameter passed
if (new URLSearchParams(window.location.search).get('download') === '1') {
  window.addEventListener('load', downloadPDF);
}
</script>
</body>
</html>
