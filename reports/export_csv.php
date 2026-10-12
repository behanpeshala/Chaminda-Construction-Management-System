<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$role = current_role();
$type = $_GET['type'] ?? '';

$allowed = [
    'projects'        => ['Administrator','Project Manager','Site Staff','Client'],
    'inventory'       => ['Administrator','Site Staff','Project Manager'],
    'financial'       => ['Administrator','Finance Officer'],
    'purchase_orders' => ['Administrator','Procurement Staff'],
];
if (!isset($allowed[$type]) || !in_array($role, $allowed[$type], true)) {
    http_response_code(403); die('Access denied for this report.');
}

function csv_out(string $filename, array $header, array $rows) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $header);
    foreach ($rows as $r) fputcsv($out, $r);
    fclose($out);
    exit;
}

switch ($type) {
    case 'projects':
        $rows = $pdo->query("SELECT p.project_name, c.name, p.project_type, p.status, p.progress_percent, p.start_date, p.end_date, p.estimated_budget
                              FROM projects p JOIN clients c ON c.client_id=p.client_id ORDER BY p.created_at DESC")->fetchAll(PDO::FETCH_NUM);
        csv_out('project_progress_report.csv',
            ['Project','Client','Type','Status','Progress %','Start Date','End Date','Estimated Budget (LKR)'], $rows);
        break;

    case 'inventory':
        $rows = $pdo->query("SELECT m.name, m.unit, COALESCE(i.quantity_on_hand,0), m.reorder_level, m.unit_price
                              FROM materials m LEFT JOIN inventory i ON i.material_id=m.material_id ORDER BY m.name")->fetchAll(PDO::FETCH_NUM);
        csv_out('inventory_report.csv',
            ['Material','Unit','Quantity on Hand','Reorder Level','Unit Price (LKR)'], $rows);
        break;

    case 'financial':
        $rows = $pdo->query("SELECT p.project_name, COALESCE(b.allocated_amount,0),
                                     COALESCE((SELECT SUM(amount) FROM expenses e WHERE e.project_id=p.project_id),0),
                                     COALESCE((SELECT SUM(amount) FROM payments pay WHERE pay.project_id=p.project_id AND pay.status IN ('Approved','Verified')),0)
                              FROM projects p LEFT JOIN budgets b ON b.project_id=p.project_id ORDER BY p.created_at DESC")->fetchAll(PDO::FETCH_NUM);
        csv_out('financial_report.csv',
            ['Project','Allocated Budget (LKR)','Total Expenses (LKR)','Total Payments (LKR)'], $rows);
        break;

    case 'purchase_orders':
        $rows = $pdo->query("SELECT CONCAT('PO-', LPAD(po.po_id,4,'0')), s.name, po.status, po.created_at,
                                     COALESCE((SELECT SUM(quantity*unit_price) FROM purchase_order_items WHERE po_id=po.po_id),0)
                              FROM purchase_orders po JOIN suppliers s ON s.supplier_id=po.supplier_id ORDER BY po.created_at DESC")->fetchAll(PDO::FETCH_NUM);
        csv_out('purchase_orders_report.csv',
            ['PO Number','Supplier','Status','Created At','Total (LKR)'], $rows);
        break;
}
