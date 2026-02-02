<?php
require './config/db.php';

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
    try {
        // Validate required fields
        if (empty($_POST['customer_id'])) {
            throw new Exception('Customer is required');
        }
        if (empty($_POST['invoice_date'])) {
            throw new Exception('Invoice date is required');
        }
        if (empty($_POST['due_date'])) {
            throw new Exception('Due date is required');
        }
        if (empty($_POST['items']) || !is_array($_POST['items']) || count($_POST['items']) === 0) {
            throw new Exception('At least one line item is required');
        }
        
        // Validate each item has required fields
        foreach ($_POST['items'] as $index => $item) {
            if (empty($item['desc'])) {
                throw new Exception("Item #" . ($index + 1) . " description is required");
            }
            if (empty($item['qty']) || floatval($item['qty']) <= 0) {
                throw new Exception("Item #" . ($index + 1) . " quantity must be greater than 0");
            }
            if (!isset($item['price']) || floatval($item['price']) < 0) {
                throw new Exception("Item #" . ($index + 1) . " price is required");
            }
        }

        $customer_id = $_POST['customer_id'];
        $invoice_date = $_POST['invoice_date'];
        $due_date = $_POST['due_date'];
        $items = $_POST['items'];
        $invoice_discount_percent = floatval($_POST['invoice_discount_percent'] ?? 0);
        $invoice_discount_amount = floatval($_POST['invoice_discount_amount'] ?? 0);
        $status = $_POST['status'] ?? 'draft'; // 'draft' or 'finalized'

        $subtotal = 0;
        foreach ($items as $item) {
        $qty = floatval($item['qty']);
        $price = floatval($item['price']);
        $item_discount_percent = floatval($item['discount_percent'] ?? 0);
        $item_discount_amount = floatval($item['discount_amount'] ?? 0);
        
        $line_subtotal = $qty * $price;
        // Apply item discount (percent takes precedence if both are provided)
        if ($item_discount_percent > 0) {
            $line_discount = $line_subtotal * ($item_discount_percent / 100);
        } else {
            $line_discount = $item_discount_amount;
        }
        $subtotal += $line_subtotal - $line_discount;
    }
    
    $tax = $subtotal * 0.15;
    $total_after_tax = $subtotal + $tax;
    
    // Apply invoice-level discount (percent takes precedence if both are provided)
    if ($invoice_discount_percent > 0) {
        $invoice_discount = $total_after_tax * ($invoice_discount_percent / 100);
    } else {
        $invoice_discount = $invoice_discount_amount;
    }
    
    $total = $total_after_tax - $invoice_discount;

    // Check which columns exist in the database
    $columns = $pdo->query("SHOW COLUMNS FROM invoices")->fetchAll(PDO::FETCH_COLUMN);
    $has_discount_percent = in_array('discount_percent', $columns);
    $has_discount_amount = in_array('discount_amount', $columns);
    $has_share_token = in_array('share_token', $columns);
    $has_status = in_array('status', $columns);

    // Generate share token if column exists
    $share_token = $has_share_token ? bin2hex(random_bytes(32)) : null;

    // Build INSERT query based on available columns
    $fields = ['customer_id', 'invoice_date', 'due_date', 'subtotal', 'tax'];
    $values = [$customer_id, $invoice_date, $due_date, $subtotal, $tax];
    
    if ($has_discount_percent) {
        $fields[] = 'discount_percent';
        $values[] = $invoice_discount_percent;
    }
    if ($has_discount_amount) {
        $fields[] = 'discount_amount';
        $values[] = $invoice_discount_amount;
    }
    $fields[] = 'total';
    $values[] = $total;
    if ($has_status) {
        $fields[] = 'status';
        $values[] = $status;
    }
    if ($has_share_token && $share_token) {
        $fields[] = 'share_token';
        $values[] = $share_token;
    }

    $placeholders = str_repeat('?,', count($fields) - 1) . '?';
    $sql = "INSERT INTO invoices (" . implode(', ', $fields) . ") VALUES ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);

    $invoice_id = $pdo->lastInsertId();

    // Check which columns exist in invoice_items table
    $itemColumns = $pdo->query("SHOW COLUMNS FROM invoice_items")->fetchAll(PDO::FETCH_COLUMN);
    $has_item_discount_percent = in_array('discount_percent', $itemColumns);
    $has_item_discount_amount = in_array('discount_amount', $itemColumns);

    foreach ($items as $index => $item) {
        // Validate item data before processing
        if (empty($item['qty']) || !is_numeric($item['qty']) || floatval($item['qty']) <= 0) {
            throw new Exception("Item #" . ($index + 1) . " quantity is invalid or missing");
        }
        if (empty($item['price']) || !is_numeric($item['price']) || floatval($item['price']) < 0) {
            throw new Exception("Item #" . ($index + 1) . " price is invalid or missing");
        }
        if (empty($item['desc']) || trim($item['desc']) === '') {
            throw new Exception("Item #" . ($index + 1) . " description is required");
        }
        
        $qty = floatval($item['qty']);
        $price = floatval($item['price']);
        $item_discount_percent = floatval($item['discount_percent'] ?? 0);
        $item_discount_amount = floatval($item['discount_amount'] ?? 0);
        
        $line_subtotal = $qty * $price;
        // Apply item discount
        if ($item_discount_percent > 0) {
            $line_discount = $line_subtotal * ($item_discount_percent / 100);
        } else {
            $line_discount = $item_discount_amount;
        }
        $line_total = $line_subtotal - $line_discount;
        
        // Build INSERT query based on available columns
        $itemFields = ['invoice_id', 'description', 'quantity', 'unit_price'];
        $itemValues = [$invoice_id, $item['desc'], $item['qty'], $item['price']];
        
        if ($has_item_discount_percent) {
            $itemFields[] = 'discount_percent';
            $itemValues[] = $item_discount_percent;
        }
        if ($has_item_discount_amount) {
            $itemFields[] = 'discount_amount';
            $itemValues[] = $item_discount_amount;
        }
        $itemFields[] = 'line_total';
        $itemValues[] = $line_total;
        
        $itemPlaceholders = str_repeat('?,', count($itemFields) - 1) . '?';
        $itemSql = "INSERT INTO invoice_items (" . implode(', ', $itemFields) . ") VALUES ($itemPlaceholders)";
        $itemStmt = $pdo->prepare($itemSql);
        $itemStmt->execute($itemValues);
    }

    header("Location: view_invoice.php?id=$invoice_id");
    exit;
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
        error_log("Invoice creation PDO error: " . $e->getMessage());
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        error_log("Invoice creation error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Create Invoice</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<h1>Create Invoice</h1>

<?php if (isset($error_message)): ?>
  <div style="padding: 15px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 4px; margin-bottom: 20px;">
    <strong>Error:</strong> <?= htmlspecialchars($error_message) ?>
    <br><small>Please check your database schema. If you haven't run the migration, you may need to add the 'share_token' column to the invoices table.</small>
  </div>
<?php endif; ?>

<form method="post" id="invoiceForm">
  <label>Customer</label>
  <div style="display: flex; gap: 10px; align-items: flex-start;">
    <select name="customer_id" id="customerSelect" required style="flex: 1;">
      <?php foreach ($customers as $c): ?>
        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
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
  <input type="date" name="invoice_date" value="<?= date('Y-m-d') ?>" required>

  <label>Due Date</label>
  <input type="date" name="due_date" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>

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
    <tbody></tbody>
  </table>

  <button type="button" onclick="addRow()">+ Add Item</button>

  <div style="margin-top: 20px;">
    <h4>Invoice Discount</h4>
    <div style="display: flex; gap: 15px; align-items: flex-end;">
      <div>
        <label>Discount %</label>
        <input type="number" name="invoice_discount_percent" id="invoice_discount_percent" step="0.01" min="0" max="100" value="0" onchange="recalc()" style="width: 100px;">
      </div>
      <div>
        <label>Discount Amount (R)</label>
        <input type="number" name="invoice_discount_amount" id="invoice_discount_amount" step="0.01" min="0" value="0" onchange="recalc()" style="width: 100px;">
      </div>
      <div style="color: #666; font-size: 0.9em;">
        <em>Note: If both are provided, percentage takes precedence</em>
      </div>
    </div>
  </div>

  <div style="margin-top: 20px;">
    <p><strong>Subtotal:</strong> R<span id="subtotal">0.00</span></p>
    <p><strong>VAT (15%):</strong> R<span id="tax">0.00</span></p>
    <p id="invoiceDiscountRow" style="display: none;"><strong>Invoice Discount:</strong> -R<span id="invoice_discount">0.00</span></p>
    <p><strong>Total:</strong> R<span id="total">0.00</span></p>
  </div>

  <div style="margin-top: 20px;">
    <label>
      <input type="radio" name="status" value="draft" checked> Save as Draft
    </label>
    <label style="margin-left: 20px;">
      <input type="radio" name="status" value="finalized"> Finalize Invoice
    </label>
  </div>

  <div style="margin-top: 20px;">
    <button type="submit" name="save_action" value="save" onclick="return validateForm(event)">Save Invoice</button>
  </div>
</form>

<script src="assets/app.js"></script>
<script>
function validateForm(event) {
  if (event) event.preventDefault();
  const items = document.querySelectorAll('#itemsTable tbody tr');
  if (items.length === 0) {
    alert('Please add at least one line item before saving.');
    return false;
  }
  
  let hasError = false;
  items.forEach((row, index) => {
    const desc = row.querySelector('input[name$="[desc]"]');
    const qty = row.querySelector('input[name$="[qty]"]');
    const price = row.querySelector('input[name$="[price]"]');
    
    if (!desc || !desc.value.trim()) {
      alert(`Item #${index + 1}: Description is required`);
      hasError = true;
      return;
    }
    if (!qty || !qty.value || parseFloat(qty.value) <= 0) {
      alert(`Item #${index + 1}: Quantity must be greater than 0`);
      hasError = true;
      return;
    }
    if (!price || !price.value || parseFloat(price.value) < 0) {
      alert(`Item #${index + 1}: Price is required`);
      hasError = true;
      return;
    }
  });
  
  if (hasError) {
    return false;
  }
  
  return true;
}
</script>
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
  
  fetch('create_invoice.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // Add new option to select
      const select = document.getElementById('customerSelect');
      const option = document.createElement('option');
      option.value = data.id;
      option.textContent = data.name;
      option.selected = true;
      select.appendChild(option);
      
      messageDiv.textContent = 'Customer added successfully!';
      messageDiv.style.color = 'green';
      
      // Clear form and hide it after a moment
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
</script>
</body>
</html>
