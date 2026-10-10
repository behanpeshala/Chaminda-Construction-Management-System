<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['Administrator','Procurement Staff','Project Manager']);
$page_title = 'New Purchase Order';

$suppliers = $pdo->query("SELECT supplier_id, name FROM suppliers WHERE status='active' ORDER BY name")->fetchAll();
$materials = $pdo->query("SELECT material_id, name, unit, unit_price FROM materials WHERE status='active' ORDER BY name")->fetchAll();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $supplier_id = (int)($_POST['supplier_id'] ?? 0);
    $lineMaterials = $_POST['material_id'] ?? [];
    $lineQtys      = $_POST['quantity'] ?? [];
    $linePrices    = $_POST['unit_price'] ?? [];

    if (!$supplier_id) $errors[] = 'Please select a registered supplier.';

    // Build validated line items (must contain at least one material line)
    $items = [];
    foreach ($lineMaterials as $i => $mid) {
        $mid = (int)$mid;
        $qty = (int)($lineQtys[$i] ?? 0);
        $price = (float)($linePrices[$i] ?? 0);
        if ($mid && $qty > 0) {
            $items[] = ['material_id' => $mid, 'quantity' => $qty, 'unit_price' => $price];
        }
    }
    if (!$items) $errors[] = 'A purchase order must contain at least one material line.';

    if (!$errors) {
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO purchase_orders (supplier_id, created_by) VALUES (?,?)")
            ->execute([$supplier_id, current_user_id()]);
        $po_id = (int)$pdo->lastInsertId();
        $stmt = $pdo->prepare("INSERT INTO purchase_order_items (po_id, material_id, quantity, unit_price) VALUES (?,?,?,?)");
        foreach ($items as $it) {
            $stmt->execute([$po_id, $it['material_id'], $it['quantity'], $it['unit_price']]);
        }
        $pdo->commit();
        audit($pdo, 'CREATE', 'purchase_orders', $po_id);

        $sName = $pdo->query("SELECT name FROM suppliers WHERE supplier_id = $supplier_id")->fetchColumn() ?: 'Supplier';

        create_notification(
            $pdo,
            'New Purchase Order Created',
            "Purchase Order PO-" . str_pad($po_id,4,'0',STR_PAD_LEFT) . " created for supplier $sName.",
            'success',
            "/ccms/purchase_orders/view.php?id=$po_id"
        );

        redirect('/ccms/purchase_orders/view.php?id=' . $po_id);
    }
}
require_once __DIR__ . '/../includes/page_start.php';
?>
<div class="card p-4" style="max-width:780px">
  <?php foreach ($errors as $e): ?><div class="alert alert-danger py-2"><?php echo htmlspecialchars($e); ?></div><?php endforeach; ?>
  <form method="post" id="poForm">
    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
    <div class="mb-3">
      <label class="form-label required">Supplier</label>
      <select name="supplier_id" class="form-select" required>
        <option value="">-- Select registered supplier --</option>
        <?php foreach ($suppliers as $s): ?><option value="<?php echo $s['supplier_id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option><?php endforeach; ?>
      </select>
      <small class="text-muted">No supplier? <a href="/ccms/suppliers/create.php">Register one first</a>.</small>
    </div>

    <label class="form-label required">Material Lines</label>
    <div class="table-responsive mb-2">
      <table class="table table-sm" id="lineTable">
        <thead><tr><th>Material</th><th style="width:110px">Qty</th><th style="width:140px">Unit Price</th><th></th></tr></thead>
        <tbody id="lineBody">
          <tr>
            <td>
              <select name="material_id[]" class="form-select form-select-sm mat-select">
                <option value="">-- Select --</option>
                <?php foreach ($materials as $m): ?>
                  <option value="<?php echo $m['material_id']; ?>" data-price="<?php echo $m['unit_price']; ?>"><?php echo htmlspecialchars($m['name']); ?> (<?php echo htmlspecialchars($m['unit']); ?>)</option>
                <?php endforeach; ?>
              </select>
            </td>
            <td><input type="number" min="1" name="quantity[]" class="form-control form-control-sm"></td>
            <td><input type="number" step="0.01" min="0" name="unit_price[]" class="form-control form-control-sm price-input"></td>
            <td><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="bi bi-trash"></i></button></td>
          </tr>
        </tbody>
      </table>
    </div>
    <button type="button" id="addRow" class="btn btn-sm btn-outline-secondary mb-3"><i class="bi bi-plus-lg"></i> Add Line</button>

    <div>
      <button class="btn btn-success"><i class="bi bi-check-lg"></i> Create Purchase Order</button>
      <a href="/ccms/purchase_orders/list.php" class="btn btn-outline-secondary">Cancel</a>
    </div>
  </form>
</div>
<script>
document.getElementById('lineBody').addEventListener('change', function(e){
  if (e.target.classList.contains('mat-select')) {
    const price = e.target.selectedOptions[0].dataset.price || 0;
    e.target.closest('tr').querySelector('.price-input').value = price;
  }
});
document.getElementById('addRow').addEventListener('click', function(){
  const body = document.getElementById('lineBody');
  const clone = body.firstElementChild.cloneNode(true);
  clone.querySelectorAll('input').forEach(i => i.value = '');
  clone.querySelector('select').selectedIndex = 0;
  body.appendChild(clone);
});
document.getElementById('lineBody').addEventListener('click', function(e){
  const btn = e.target.closest('.remove-row');
  if (btn && document.querySelectorAll('#lineBody tr').length > 1) btn.closest('tr').remove();
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
