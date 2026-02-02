<?php
require './config/db.php';

$stmt = $pdo->query("
  SELECT invoices.*, customers.name AS customer_name
  FROM invoices
  JOIN customers ON invoices.customer_id = customers.id
  ORDER BY invoices.created_at DESC
");
$invoices = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
  <title>Invoices</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<h1>Invoices</h1>
<a href="create_invoice.php">+ New Invoice</a>

<table>
  <thead>
    <tr>
      <th>#</th>
      <th>Customer</th>
      <th>Date</th>
      <th>Total</th>
      <th>Status</th>
      <th></th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($invoices as $inv): ?>
    <tr>
      <td><?= $inv['id'] ?></td>
      <td><?= htmlspecialchars($inv['customer_name']) ?></td>
      <td><?= $inv['invoice_date'] ?></td>
      <td>R<?= number_format($inv['total'], 2) ?></td>
      <td><?= $inv['status'] ?></td>
      <td>
        <a href="view_invoice.php?id=<?= $inv['id'] ?>">View</a> |
        <a href="edit_invoice.php?id=<?= $inv['id'] ?>">Edit</a> |
        <a href="export_pdf.php?id=<?= $inv['id'] ?>" target="_blank">PDF</a> |
        <a href="delete_invoice.php?id=<?= $inv['id'] ?>" onclick="return confirm('Delete invoice?')">Delete</a>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</body>
</html>
