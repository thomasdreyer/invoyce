<?php
require './config/db.php';

$token = $_GET['token'] ?? null;

if (!$token) {
    die('Invalid share link');
}

$stmt = $pdo->prepare("
  SELECT invoices.*, customers.name AS customer_name, customers.email
  FROM invoices
  JOIN customers ON invoices.customer_id = customers.id
  WHERE invoices.share_token = ?
");
$stmt->execute([$token]);
$invoice = $stmt->fetch();

if (!$invoice) {
    die('Invoice not found or link is invalid');
}

$itemStmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
$itemStmt->execute([$invoice['id']]);
$items = $itemStmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
  <title>Invoice #<?= $invoice['id'] ?></title>
  <link rel="stylesheet" href="assets/style.css">
  <style>
    body {
      max-width: 800px;
      margin: 0 auto;
      padding: 20px;
    }
    .invoice-header {
      text-align: center;
      margin-bottom: 30px;
    }
    .invoice-info {
      display: flex;
      justify-content: space-between;
      margin-bottom: 30px;
    }
    .info-block {
      flex: 1;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 20px;
    }
    th, td {
      padding: 10px;
      text-align: left;
      border-bottom: 1px solid #ddd;
    }
    th {
      background-color: #f5f5f5;
      font-weight: bold;
    }
    .total-row {
      font-weight: bold;
      font-size: 1.1em;
    }
  </style>
</head>
<body>
<div class="invoice-header">
  <h1>INVOICE #<?= $invoice['id'] ?></h1>
  <p style="color: #666;">Status: <?= strtoupper($invoice['status']) ?></p>
</div>

<div class="invoice-info">
  <div class="info-block">
    <h3>Bill To:</h3>
    <p><strong><?= htmlspecialchars($invoice['customer_name']) ?></strong></p>
    <?php if ($invoice['email']): ?>
      <p><?= htmlspecialchars($invoice['email']) ?></p>
    <?php endif; ?>
  </div>
  <div class="info-block" style="text-align: right;">
    <p><strong>Invoice Date:</strong> <?= date('F d, Y', strtotime($invoice['invoice_date'])) ?></p>
    <p><strong>Due Date:</strong> <?= date('F d, Y', strtotime($invoice['due_date'])) ?></p>
  </div>
</div>

<table>
  <thead>
    <tr>
      <th>Description</th>
      <th>Qty</th>
      <th>Unit Price</th>
      <th>Discount</th>
      <th>Total</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($items as $item): 
      $line_subtotal = $item['quantity'] * $item['unit_price'];
      $item_discount = 0;
      if ($item['discount_percent'] > 0) {
        $item_discount = $line_subtotal * ($item['discount_percent'] / 100);
      } elseif ($item['discount_amount'] > 0) {
        $item_discount = $item['discount_amount'];
      }
    ?>
      <tr>
        <td><?= htmlspecialchars($item['description']) ?></td>
        <td><?= $item['quantity'] ?></td>
        <td>R<?= number_format($item['unit_price'], 2) ?></td>
        <td>
          <?php if ($item_discount > 0): ?>
            <?php if ($item['discount_percent'] > 0): ?>
              <?= number_format($item['discount_percent'], 2) ?>%
            <?php else: ?>
              R<?= number_format($item_discount, 2) ?>
            <?php endif; ?>
          <?php else: ?>
            -
          <?php endif; ?>
        </td>
        <td>R<?= number_format($item['line_total'], 2) ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr>
      <td colspan="4" style="text-align: right;"><strong>Subtotal:</strong></td>
      <td><strong>R<?= number_format($invoice['subtotal'], 2) ?></strong></td>
    </tr>
    <tr>
      <td colspan="4" style="text-align: right;"><strong>VAT (15%):</strong></td>
      <td><strong>R<?= number_format($invoice['tax'], 2) ?></strong></td>
    </tr>
    <?php 
    $invoice_discount = 0;
    if ($invoice['discount_percent'] > 0) {
      $total_before_discount = $invoice['subtotal'] + $invoice['tax'];
      $invoice_discount = $total_before_discount * ($invoice['discount_percent'] / 100);
    } elseif ($invoice['discount_amount'] > 0) {
      $invoice_discount = $invoice['discount_amount'];
    }
    if ($invoice_discount > 0): ?>
      <tr>
        <td colspan="4" style="text-align: right;">
          <strong>Invoice Discount 
          <?php if ($invoice['discount_percent'] > 0): ?>
            (<?= number_format($invoice['discount_percent'], 2) ?>%)
          <?php endif; ?>:</strong>
        </td>
        <td><strong>-R<?= number_format($invoice_discount, 2) ?></strong></td>
      </tr>
    <?php endif; ?>
    <tr class="total-row">
      <td colspan="4" style="text-align: right;"><strong>TOTAL:</strong></td>
      <td><strong>R<?= number_format($invoice['total'], 2) ?></strong></td>
    </tr>
  </tfoot>
</table>

<div style="margin-top: 40px; text-align: center; color: #666; font-size: 0.9em;">
  <p>This is a shared invoice. For questions, please contact the invoice issuer.</p>
</div>
</body>
</html>
