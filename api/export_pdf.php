<?php
require '../config/db.php';

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    die('Invalid invoice ID');
}

$stmt = $pdo->prepare("
  SELECT invoices.*, customers.name AS customer_name, customers.email
  FROM invoices
  JOIN customers ON invoices.customer_id = customers.id
  WHERE invoices.id = ?
");
$stmt->execute([$id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    die('Invoice not found');
}

$itemStmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
$itemStmt->execute([$id]);
$items = $itemStmt->fetchAll();

// Generate PDF using HTML output (can be printed to PDF by browser)
// For production, consider using TCPDF, FPDF, or DomPDF library
?>
<!DOCTYPE html>
<html>
<head>
  <title>Invoice #<?= $invoice['id'] ?> - PDF</title>
  <style>
    @media print {
      body { margin: 0; padding: 20px; }
      .no-print { display: none; }
    }
    body {
      font-family: Arial, sans-serif;
      max-width: 800px;
      margin: 0 auto;
      padding: 20px;
      color: #333;
    }
    .invoice-header {
      text-align: center;
      margin-bottom: 30px;
      border-bottom: 2px solid #333;
      padding-bottom: 20px;
    }
    .invoice-info {
      display: flex;
      justify-content: space-between;
      margin-bottom: 30px;
    }
    .info-block {
      flex: 1;
    }
    .info-block h3 {
      margin-top: 0;
      border-bottom: 1px solid #ddd;
      padding-bottom: 5px;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 20px;
    }
    th, td {
      padding: 8px;
      text-align: left;
      border-bottom: 1px solid #ddd;
    }
    th {
      background-color: #f5f5f5;
      font-weight: bold;
    }
    tfoot td {
      border-top: 2px solid #333;
      padding-top: 10px;
    }
    .total-row {
      font-weight: bold;
      font-size: 1.2em;
      background-color: #f9f9f9;
    }
    .no-print {
      text-align: center;
      margin: 20px 0;
    }
    .no-print button {
      padding: 10px 20px;
      font-size: 16px;
      background-color: #007bff;
      color: white;
      border: none;
      border-radius: 4px;
      cursor: pointer;
    }
    .no-print button:hover {
      background-color: #0056b3;
    }
  </style>
</head>
<body>
<div class="no-print">
  <button onclick="window.print()">Print / Save as PDF</button>
  <p style="margin-top: 10px; color: #666;">Click the button above to print or save as PDF</p>
</div>

<div class="invoice-header">
  <h1>INVOICE #<?= $invoice['id'] ?></h1>
  <p style="color: #666; margin-top: 5px;">Status: <?= strtoupper($invoice['status']) ?></p>
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
    <h3>Invoice Details</h3>
    <p><strong>Invoice Date:</strong> <?= date('F d, Y', strtotime($invoice['invoice_date'])) ?></p>
    <p><strong>Due Date:</strong> <?= date('F d, Y', strtotime($invoice['due_date'])) ?></p>
  </div>
</div>

<table>
  <thead>
    <tr>
      <th>Description</th>
      <th style="text-align: center;">Qty</th>
      <th style="text-align: right;">Unit Price</th>
      <th style="text-align: right;">Discount</th>
      <th style="text-align: right;">Total</th>
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
        <td style="text-align: center;"><?= $item['quantity'] ?></td>
        <td style="text-align: right;">R<?= number_format($item['unit_price'], 2) ?></td>
        <td style="text-align: right;">
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
        <td style="text-align: right;">R<?= number_format($item['line_total'], 2) ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr>
      <td colspan="4" style="text-align: right;"><strong>Subtotal:</strong></td>
      <td style="text-align: right;"><strong>R<?= number_format($invoice['subtotal'], 2) ?></strong></td>
    </tr>
    <tr>
      <td colspan="4" style="text-align: right;"><strong>VAT (15%):</strong></td>
      <td style="text-align: right;"><strong>R<?= number_format($invoice['tax'], 2) ?></strong></td>
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
        <td style="text-align: right;"><strong>-R<?= number_format($invoice_discount, 2) ?></strong></td>
      </tr>
    <?php endif; ?>
    <tr class="total-row">
      <td colspan="4" style="text-align: right;"><strong>TOTAL:</strong></td>
      <td style="text-align: right;"><strong>R<?= number_format($invoice['total'], 2) ?></strong></td>
    </tr>
  </tfoot>
</table>

<div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; color: #666; font-size: 0.9em;">
  <p>Thank you for your business!</p>
</div>
</body>
</html>
