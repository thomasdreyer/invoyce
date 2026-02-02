<?php
require './config/db.php';

$id = $_GET['id'] ?? null;

if (!$id) {
    header("Location: index.php");
    exit;
}

// Fetch invoice data
$stmt = $pdo->prepare("
  SELECT invoices.*, customers.name AS customer_name
  FROM invoices
  JOIN customers ON invoices.customer_id = customers.id
  WHERE invoices.id = ?
");
$stmt->execute([$id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    header("Location: index.php");
    exit;
}

// Fetch invoice items
$itemStmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
$itemStmt->execute([$id]);
$existing_items = $itemStmt->fetchAll();

// Ensure CASH customer exists
$cashCheck = $pdo->query("SELECT id FROM customers WHERE name = 'CASH'")->fetch();
if (!$cashCheck) {
    $pdo->exec("INSERT INTO customers (name, email) VALUES ('CASH', 'cash@invoice.app')");
}

// Handle AJAX request to add new customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_customer') {
    header('Content-Type: application/json');
    $name = trim($_POST['name']);
    $email = trim($_POST['email'] ?? '');
    
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Customer name is required']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO customers (name, email) VALUES (?, ?)");
        $stmt->execute([$name, $email]);
        $customer_id = $pdo->lastInsertId();
        echo json_encode(['success' => true, 'id' => $customer_id, 'name' => $name]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error adding customer: ' . $e->getMessage()]);
    }
    exit;
}

$customers = $pdo->query("SELECT * FROM customers ORDER BY name = 'CASH' DESC, name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $customer_id = $_POST['customer_id'];
    $invoice_date = $_POST['invoice_date'];
    $due_date = $_POST['due_date'];
    $items = $_POST['items'];
    $invoice_discount_percent = floatval($_POST['invoice_discount_percent'] ?? 0);
    $invoice_discount_amount = floatval($_POST['invoice_discount_amount'] ?? 0);
    $status = $_POST['status'] ?? 'finalized';

    $subtotal = 0;
    foreach ($items as $item) {
        $qty = floatval($item['qty']);
        $price = floatval($item['price']);
        $item_discount_percent = floatval($item['discount_percent'] ?? 0);
        $item_discount_amount = floatval($item['discount_amount'] ?? 0);
        
        $line_subtotal = $qty * $price;
        if ($item_discount_percent > 0) {
            $line_discount = $line_subtotal * ($item_discount_percent / 100);
        } else {
            $line_discount = $item_discount_amount;
        }
        $subtotal += $line_subtotal - $line_discount;
    }
    
    $tax = $subtotal * 0.15;
    $total_after_tax = $subtotal + $tax;
    
    if ($invoice_discount_percent > 0) {
        $invoice_discount = $total_after_tax * ($invoice_discount_percent / 100);
    } else {
        $invoice_discount = $invoice_discount_amount;
    }
    
    $total = $total_after_tax - $invoice_discount;

    // Update invoice
    $stmt = $pdo->prepare("
      UPDATE invoices 
      SET customer_id = ?, invoice_date = ?, due_date = ?, subtotal = ?, tax = ?, 
          discount_percent = ?, discount_amount = ?, total = ?, status = ?
      WHERE id = ?
    ");
    $stmt->execute([$customer_id, $invoice_date, $due_date, $subtotal, $tax, 
                    $invoice_discount_percent, $invoice_discount_amount, $total, $status, $id]);

    // Delete old items
    $pdo->prepare("DELETE FROM invoice_items WHERE invoice_id = ?")->execute([$id]);

    // Insert new items
    $itemStmt = $pdo->prepare("
      INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, discount_percent, discount_amount, line_total)
      VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($items as $item) {
        $qty = floatval($item['qty']);
        $price = floatval($item['price']);
        $item_discount_percent = floatval($item['discount_percent'] ?? 0);
        $item_discount_amount = floatval($item['discount_amount'] ?? 0);
        
        $line_subtotal = $qty * $price;
        if ($item_discount_percent > 0) {
            $line_discount = $line_subtotal * ($item_discount_percent / 100);
        } else {
            $line_discount = $item_discount_amount;
        }
        $line_total = $line_subtotal - $line_discount;
        
        $itemStmt->execute([
            $id,
            $item['desc'],
            $item['qty'],
            $item['price'],
            $item_discount_percent,
            $item_discount_amount,
            $line_total
        ]);
    }

    header("Location: view_invoice.php?id=$id");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Edit Invoice #<?= $invoice['id'] ?></title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<h1>Edit Invoice #<?= $invoice['id'] ?></h1>

<form method="post" id="invoiceForm">
  <label>Customer</label>
  <div style="display: flex; gap: 10px; align-items: flex-start;">
    <select name="customer_id" id="customerSelect" required style="flex: 1;">
      <?php foreach ($customers as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $c['id'] == $invoice['customer_id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($c['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <button type="button" onclick="toggleAddCustomer()" style="padding: 8px 15px;">+ Add Customer</button>
  </div>
  
  <div id="addCustomerForm" style="display: none; margin: 15px 0; padding: 15px; border: 1px solid #ddd; background: #f9f9f9;">
    <h4>Add New Customer</h4>
    <div style="display: flex; gap: 10px; align-items: flex-end;">
      <div style="flex: 1;">
        <label>Name *</label>
        <input type="text" id="newCustomerName" placeholder="Enter customer name" autocomplete="name" style="width: 100%; padding: 8px;">
      </div>
      <div style="flex: 1;">
        <label>Email</label>
        <input type="email" id="newCustomerEmail" placeholder="customer@example.com" autocomplete="email" style="width: 100%; padding: 8px;">
      </div>
      <div>
        <button type="button" onclick="addCustomer()">Add</button>
        <button type="button" onclick="toggleAddCustomer()">Cancel</button>
      </div>
    </div>
    <div id="customerMessage" style="margin-top: 10px; color: green;"></div>
  </div>

  <label>Invoice Date</label>
  <input type="date" name="invoice_date" value="<?= $invoice['invoice_date'] ?>" required>

  <label>Due Date</label>
  <input type="date" name="due_date" value="<?= $invoice['due_date'] ?>" required>

  <h3>Line Items</h3>
  <table id="itemsTable">
    <thead>
      <tr>
        <th>Description</th>
        <th>Qty</th>
        <th>Unit Price</th>
        <th>Discount %</th>
        <th>Discount Amount</th>
        <th>Total</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($existing_items as $item): ?>
        <tr>
          <td><input name="items[][desc]" value="<?= htmlspecialchars($item['description']) ?>" required></td>
          <td><input type="number" name="items[][qty]" value="<?= $item['quantity'] ?>" min="1" onchange="recalc()" required></td>
          <td><input type="number" name="items[][price]" step="0.01" value="<?= $item['unit_price'] ?>" onchange="recalc()" required></td>
          <td><input type="number" name="items[][discount_percent]" step="0.01" min="0" max="100" value="<?= $item['discount_percent'] ?>" onchange="recalc()" style="width: 80px;"></td>
          <td><input type="number" name="items[][discount_amount]" step="0.01" min="0" value="<?= $item['discount_amount'] ?>" onchange="recalc()" style="width: 80px;"></td>
          <td class="line-total"><?= number_format($item['line_total'], 2) ?></td>
          <td><button type="button" onclick="this.closest('tr').remove(); recalc();">✕</button></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <button type="button" onclick="addRow()">+ Add Item</button>

  <div style="margin-top: 20px;">
    <h4>Invoice Discount</h4>
    <div style="display: flex; gap: 15px; align-items: flex-end;">
      <div>
        <label>Discount %</label>
        <input type="number" name="invoice_discount_percent" id="invoice_discount_percent" step="0.01" min="0" max="100" value="<?= $invoice['discount_percent'] ?>" onchange="recalc()" style="width: 100px;">
      </div>
      <div>
        <label>Discount Amount (R)</label>
        <input type="number" name="invoice_discount_amount" id="invoice_discount_amount" step="0.01" min="0" value="<?= $invoice['discount_amount'] ?>" onchange="recalc()" style="width: 100px;">
      </div>
    </div>
  </div>

  <div style="margin-top: 20px;">
    <p><strong>Subtotal:</strong> R<span id="subtotal"><?= number_format($invoice['subtotal'], 2) ?></span></p>
    <p><strong>VAT (15%):</strong> R<span id="tax"><?= number_format($invoice['tax'], 2) ?></span></p>
    <p id="invoiceDiscountRow" style="display: <?= ($invoice['discount_percent'] > 0 || $invoice['discount_amount'] > 0) ? 'block' : 'none' ?>;"><strong>Invoice Discount:</strong> -R<span id="invoice_discount">0.00</span></p>
    <p><strong>Total:</strong> R<span id="total"><?= number_format($invoice['total'], 2) ?></span></p>
  </div>

  <div style="margin-top: 20px;">
    <label>
      <input type="radio" name="status" value="draft" <?= $invoice['status'] == 'draft' ? 'checked' : '' ?>> Save as Draft
    </label>
    <label style="margin-left: 20px;">
      <input type="radio" name="status" value="finalized" <?= $invoice['status'] == 'finalized' ? 'checked' : '' ?>> Finalize Invoice
    </label>
  </div>

  <div style="margin-top: 20px;">
    <button type="submit">Update Invoice</button>
    <a href="view_invoice.php?id=<?= $id ?>" style="margin-left: 10px;">Cancel</a>
  </div>
</form>

<script src="assets/app.js"></script>
<script>
function toggleAddCustomer() {
  const form = document.getElementById('addCustomerForm');
  form.style.display = form.style.display === 'none' ? 'block' : 'none';
  if (form.style.display === 'block') {
    document.getElementById('newCustomerName').focus();
  } else {
    document.getElementById('newCustomerName').value = '';
    document.getElementById('newCustomerEmail').value = '';
    document.getElementById('customerMessage').textContent = '';
  }
}

function addCustomer() {
  const name = document.getElementById('newCustomerName').value.trim();
  const email = document.getElementById('newCustomerEmail').value.trim();
  const messageDiv = document.getElementById('customerMessage');
  
  if (!name) {
    messageDiv.textContent = 'Please enter a customer name';
    messageDiv.style.color = 'red';
    return;
  }
  
  const formData = new FormData();
  formData.append('action', 'add_customer');
  formData.append('name', name);
  formData.append('email', email);
  
  fetch('edit_invoice.php?id=<?= $id ?>', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      const select = document.getElementById('customerSelect');
      const option = document.createElement('option');
      option.value = data.id;
      option.textContent = data.name;
      option.selected = true;
      select.appendChild(option);
      
      messageDiv.textContent = 'Customer added successfully!';
      messageDiv.style.color = 'green';
      
      setTimeout(() => {
        toggleAddCustomer();
      }, 1000);
    } else {
      messageDiv.textContent = data.message || 'Error adding customer';
      messageDiv.style.color = 'red';
    }
  })
  .catch(error => {
    messageDiv.textContent = 'Error: ' + error.message;
    messageDiv.style.color = 'red';
  });
}

// Initialize calculations on page load
document.addEventListener('DOMContentLoaded', function() {
  recalc();
});
</script>
</body>
</html>
