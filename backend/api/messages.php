<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/.env.loader.php';
require_once __DIR__ . '/../lib/smtp_mailer.php';

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$jsonBody = [];
if (stripos($contentType, 'application/json') !== false) {
    $rawBody = file_get_contents('php://input');
    $decoded = json_decode($rawBody, true);
    if (is_array($decoded)) {
        $jsonBody = $decoded;
    }
}

$action = $_GET['action'] ?? $_POST['action'] ?? ($jsonBody['action'] ?? '');

if ($action === 'info') {
    info_by_token($conn, $jsonBody);
} elseif ($action === 'reply') {
    reply_by_token($conn, $jsonBody);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function info_by_token($conn, $jsonBody) {
    $token = trim($_GET['token'] ?? $_POST['token'] ?? ($jsonBody['token'] ?? ''));
    if ($token === '') {
        echo json_encode(['success' => false, 'message' => 'Missing token']);
        return;
    }

    $stmt = $conn->prepare("
        SELECT lm.status, l.title, l.city
        FROM listing_messages lm
        JOIN listings l ON lm.listing_id = l.id
        WHERE lm.reply_token = ?
        LIMIT 1
    ");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Invalid or expired link']);
        return;
    }
    $row = $result->fetch_assoc();
    $stmt->close();

    echo json_encode([
        'success' => true,
        'status' => $row['status'],
        'title' => $row['title'],
        'city' => $row['city']
    ]);
}

function reply_by_token($conn, $jsonBody) {
    $token = trim($_POST['token'] ?? ($jsonBody['token'] ?? ''));
    $message = trim($_POST['message'] ?? ($jsonBody['message'] ?? ''));

    if ($token === '' || $message === '') {
        echo json_encode(['success' => false, 'message' => 'Message and token required']);
        return;
    }
    if (strlen($message) > 2000) {
        echo json_encode(['success' => false, 'message' => 'Message too long']);
        return;
    }

    $stmt = $conn->prepare("
        SELECT lm.id, lm.status, lm.buyer_email, lm.buyer_name, lm.seller_name, l.title, l.city
        FROM listing_messages lm
        JOIN listings l ON lm.listing_id = l.id
        WHERE lm.reply_token = ?
        LIMIT 1
    ");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Invalid or expired link']);
        return;
    }
    $row = $result->fetch_assoc();
    $stmt->close();

    if ($row['status'] === 'replied') {
        echo json_encode(['success' => false, 'message' => 'This message has already been replied to.']);
        return;
    }

    $buyer_email = $row['buyer_email'];
    if ($buyer_email === '') {
        echo json_encode(['success' => false, 'message' => 'Buyer email not available']);
        return;
    }

    $seller_name = $row['seller_name'] ?: 'Seller';
    $subject = "BrokeAnt reply: {$row['title']}";
    $body = "You have a reply about your advertisement.\n\n"
          . "Advertisement: {$row['title']}\n"
          . "City: {$row['city']}\n"
          . "Seller: {$seller_name}\n"
          . "Reply:\n{$message}\n\n"
          . "To respond, visit the advertisement and send a new message.\n";

    send_smtp_message($buyer_email, $subject, $body);

    $stmt = $conn->prepare("UPDATE listing_messages SET status = 'replied', reply_message = ?, replied_at = NOW() WHERE reply_token = ?");
    if ($stmt) {
        $stmt->bind_param("ss", $message, $token);
        $stmt->execute();
        $stmt->close();
    }

    echo json_encode(['success' => true, 'message' => 'Reply sent']);
}
