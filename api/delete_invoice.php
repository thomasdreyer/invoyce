<?php
require '../config/db.php';

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: index.html?error=invalid_id");
    exit;
}

try {
    // Delete invoice items first (foreign key constraint)
    $stmt = $pdo->prepare("DELETE FROM invoice_items WHERE invoice_id = ?");
    $stmt->execute([$id]);

    // Then delete the invoice
    $stmt = $pdo->prepare("DELETE FROM invoices WHERE id = ?");
    $stmt->execute([$id]);

    header("Location: index.html?success=deleted");
} catch (Exception $e) {
    header("Location: index.html?error=" . urlencode($e->getMessage()));
}
exit;
