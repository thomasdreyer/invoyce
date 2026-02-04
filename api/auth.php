<?php
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require __DIR__ . '/../config/db.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

function json($data) {
    echo json_encode($data);
    exit;
}

function is_localhost() {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    return strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false;
}

if ($action === 'request_otp') {
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    if (!$email) json(['success' => false, 'message' => 'Invalid email']);

    // Test account shortcut (works on localhost and deployed servers for testing)
    if (strtolower($email) === 'test@invoyce.com') {
        $code = '123456';
        $expires = (new DateTime('+10 minutes'))->format('Y-m-d H:i:s');
        $stmt = $pdo->prepare('INSERT INTO otp_codes (email, code, expires_at) VALUES (?, ?, ?)');
        $stmt->execute([$email, $code, $expires]);
        $debugMsg = is_localhost() ? '(dev)' : '(test)';
        json(['success' => true, 'message' => 'OTP generated ' . $debugMsg, 'debug_code' => $code]);
    }

    // Production behavior: generate and email
    $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = (new DateTime('+10 minutes'))->format('Y-m-d H:i:s');
    $stmt = $pdo->prepare('INSERT INTO otp_codes (email, code, expires_at) VALUES (?, ?, ?)');
    $stmt->execute([$email, $code, $expiresAt]);

    $subject = 'Your Invoyce OTP Code';
    $message = "Your one-time password (OTP) is: $code\nIt will expire in 10 minutes.";
    $headers = "From: admin@dreyerventures\r\n" .
               "Reply-To: admin@dreyerventures\r\n" .
               "X-Mailer: PHP/" . phpversion();

    $mailSent = @mail($email, $subject, $message, $headers);

    if ($mailSent) {
        json(['success' => true, 'message' => 'OTP sent to email']);
    } else {
        json(['success' => false, 'message' => 'Failed to send OTP email. Check mail() availability on this server.']);
    }
}

if ($action === 'verify_otp') {
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $code = trim($_POST['code'] ?? '');
    if (!$email || !$code) json(['success' => false, 'message' => 'Missing email or code']);

    // Test account shortcut (works on localhost and deployed servers for testing)
    if (strtolower($email) === 'test@invoyce.com' && $code === '123456') {
        // Ensure user exists
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user) {
            $ins = $pdo->prepare('INSERT INTO users (email) VALUES (?)') ;
            $ins->execute([$email]);
            $userId = $pdo->lastInsertId();
        } else {
            $userId = $user['id'];
        }
        $debugMsg = is_localhost() ? 'Authenticated (dev)' : 'Authenticated (test)';
        json(['success' => true, 'message' => $debugMsg, 'user' => ['id' => (int)$userId, 'email' => $email]]);
    }

    // Check OTP table for valid code
    $stmt = $pdo->prepare('SELECT id FROM otp_codes WHERE email = ? AND code = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1');
    $stmt->execute([$email, $code]);
    $row = $stmt->fetch();
    if (!$row) json(['success' => false, 'message' => 'Invalid or expired code']);

    // Optionally delete used codes
    $del = $pdo->prepare('DELETE FROM otp_codes WHERE id = ?');
    $del->execute([$row['id']]);

    // Ensure user exists (auto-create)
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user) {
        $ins = $pdo->prepare('INSERT INTO users (email) VALUES (?)');
        $ins->execute([$email]);
        $userId = $pdo->lastInsertId();
    } else {
        $userId = $user['id'];
    }

    json(['success' => true, 'message' => 'Authenticated', 'user' => ['id' => (int)$userId, 'email' => $email]]);
}

json(['success' => false, 'message' => 'Invalid action']);
