<?php
require '../config/db.php';
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    // Validation
    if (!$name) {
        echo json_encode(['success' => false, 'message' => 'Customer name required']);
        exit;
    }
    if (strlen($name) < 2 || strlen($name) > 255) {
        echo json_encode(['success' => false, 'message' => 'Customer name must be 2-255 characters']);
        exit;
    }
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO customers (name, email) VALUES (?, ?)");
        if ($stmt->execute([$name, $email])) {
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'name' => $name]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// GET: return all customers
try {
    $stmt = $pdo->query("SELECT id, name, email FROM customers ORDER BY name");
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($customers);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
}
