<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../config/db.php';
header('Content-Type: application/json');
try {
    $stmt = $pdo->query("
        SELECT i.*, c.name AS customer_name
        FROM invoices i
        JOIN customers c ON i.customer_id = c.id
        ORDER BY i.created_at DESC
    ");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
}
