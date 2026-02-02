<?php
require './config/db.php';

$id = $_GET['id'];

$stmt = $pdo->prepare("
  SELECT invoices.*, customers.name AS customer_name, customers.email
  FROM invoices
  JOIN customers ON invoices.customer_id = customers.id
  WHERE invoices.id = ?
");
$stmt->execute([$id]);
$invoice = $stmt->fetch();

$itemStmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
$itemStmt->execute([$id]);
$items = $itemStmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
  <title>Invoice #<?= $invoice['id'] ?></title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<h1>Invoice #<?= $invoice['id'] ?></h1>
<p><strong>Customer:</strong> <?= htmlspecialchars($invoice['customer_name']) ?></p>
<p><strong>Date:</strong> <?= $invoice['invoice_date'] ?></p>
<p><strong>Due:</strong> <?= $invoice['due_date'] ?></p>

<table>
  <thead>
    <tr>
      <th>Description</th>
      <th>Qty</th>
      <th>Unit</th>
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
              <?= number_format($item['discount_percent'], 2) ?>% (R<?= number_format($item_discount, 2) ?>)
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
    <tr><td colspan="4">Subtotal</td><td>R<?= number_format($invoice['subtotal'], 2) ?></td></tr>
    <tr><td colspan="4">VAT (15%)</td><td>R<?= number_format($invoice['tax'], 2) ?></td></tr>
    <?php 
    $invoice_discount = 0;
    if ($invoice['discount_percent'] > 0) {
      $total_before_discount = $invoice['subtotal'] + $invoice['tax'];
      $invoice_discount = $total_before_discount * ($invoice['discount_percent'] / 100);
    } elseif ($invoice['discount_amount'] > 0) {
      $invoice_discount = $invoice['discount_amount'];
    }
    if ($invoice_discount > 0): ?>
      <tr><td colspan="4">
        Invoice Discount 
        <?php if ($invoice['discount_percent'] > 0): ?>
          (<?= number_format($invoice['discount_percent'], 2) ?>%)
        <?php endif; ?>
      </td><td>-R<?= number_format($invoice_discount, 2) ?></td></tr>
    <?php endif; ?>
    <tr><td colspan="4"><strong>Total</strong></td><td><strong>R<?= number_format($invoice['total'], 2) ?></strong></td></tr>
  </tfoot>
</table>

<div style="margin-top: 30px; padding: 20px; background: #f9f9f9; border-radius: 5px;">
  <h3>Actions</h3>
  <div style="display: flex; gap: 10px; flex-wrap: wrap;">
    <a href="edit_invoice.php?id=<?= $invoice['id'] ?>" style="padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px;">Edit Invoice</a>
    <a href="export_pdf.php?id=<?= $invoice['id'] ?>" target="_blank" style="padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 4px;">Export as PDF</a>
    <?php if ($invoice['share_token']): ?>
      <button onclick="copyShareLink()" style="padding: 10px 20px; background: #17a2b8; color: white; border: none; border-radius: 4px; cursor: pointer;">Copy Share Link</button>
      <a href="share_invoice.php?token=<?= $invoice['share_token'] ?>" target="_blank" style="padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px;">View Shared Link</a>
    <?php else: ?>
      <button onclick="generateShareLink()" style="padding: 10px 20px; background: #17a2b8; color: white; border: none; border-radius: 4px; cursor: pointer;">Generate Share Link</button>
    <?php endif; ?>
    <a href="delete_invoice.php?id=<?= $invoice['id'] ?>" onclick="return confirm('Are you sure you want to delete this invoice?')" style="padding: 10px 20px; background: #dc3545; color: white; text-decoration: none; border-radius: 4px;">Delete Invoice</a>
  </div>
  <?php if ($invoice['share_token']): ?>
    <div id="shareLinkDiv" style="margin-top: 15px; padding: 10px; background: white; border: 1px solid #ddd; border-radius: 4px; display: none;">
      <p><strong>Share Link:</strong></p>
      <input type="text" id="shareLinkInput" value="<?= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/share_invoice.php?token=' . $invoice['share_token'] ?>" readonly style="width: 100%; padding: 5px; margin-top: 5px;">
      <p style="margin-top: 10px; color: green; font-size: 0.9em;" id="copyMessage"></p>
    </div>
  <?php endif; ?>
</div>

<a href="index.php" style="display: inline-block; margin-top: 20px;">← Back to Invoices</a>

<script>
function copyShareLink() {
  const shareLink = document.getElementById('shareLinkInput');
  shareLink.select();
  document.execCommand('copy');
  document.getElementById('copyMessage').textContent = 'Link copied to clipboard!';
  setTimeout(() => {
    document.getElementById('copyMessage').textContent = '';
  }, 3000);
}

function generateShareLink() {
  if (confirm('Generate a share link for this invoice?')) {
    window.location.href = 'generate_share_link.php?id=<?= $invoice['id'] ?>';
  }
}
</script>
</body>
</html>
