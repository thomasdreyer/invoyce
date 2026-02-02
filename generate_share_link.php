<?php
require './config/db.php';

$id = $_GET['id'] ?? null;

if (!$id) {
    header("Location: index.php");
    exit;
}

// Generate share token
$share_token = bin2hex(random_bytes(32));

// Update invoice with share token
$stmt = $pdo->prepare("UPDATE invoices SET share_token = ? WHERE id = ?");
$stmt->execute([$share_token, $id]);

header("Location: view_invoice.php?id=$id");
exit;
