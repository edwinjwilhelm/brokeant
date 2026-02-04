<?php
session_start();
header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../config/.env.loader.php';
require_once '../middleware/auth.php';
require_once '../lib/smtp_mailer.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if ($action === '' && strpos($contentType, 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (is_array($input)) {
        $action = $input['action'] ?? '';
        $_POST = array_merge($_POST, $input);
    }
}

if ($action === 'check') {
    check_local_admin($conn);
} elseif ($action === 'request') {
    request_local_admin($conn);
} elseif ($action === 'get_queue') {
    get_moderation_queue($conn);
} elseif ($action === 'approve_listing') {
    approve_listing($conn);
} elseif ($action === 'reject_listing') {
    reject_listing($conn);
} elseif ($action === 'remove_image') {
    remove_listing_image($conn);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function get_local_admin_cities($conn, $user_id) {
    $stmt = $conn->prepare("SELECT city FROM local_admins WHERE user_id = ? AND status = 'active'");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $cities = [];
    while ($row = $result->fetch_assoc()) {
        $cities[] = $row['city'];
    }
    $stmt->close();
    return $cities;
}

function check_local_admin($conn) {
    if (!is_logged_in()) {
        echo json_encode(['logged_in' => false]);
        return;
    }
    $user_id = get_user_id();
    $cities = get_local_admin_cities($conn, $user_id);
    echo json_encode([
        'logged_in' => true,
        'is_local_admin' => !empty($cities),
        'cities' => $cities
    ]);
}

function request_local_admin($conn) {
    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Please log in first']);
        return;
    }
    $user_id = get_user_id();
    $admin_name = trim($_POST['admin_name'] ?? '');
    $admin_phone = trim($_POST['admin_phone'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $reason = trim($_POST['reason'] ?? '');

    if ($admin_name === '' || $admin_phone === '' || $city === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Name, phone, and city are required']);
        return;
    }

    $stmt = $conn->prepare("INSERT IGNORE INTO local_admin_requests (user_id, city, reason, admin_name, admin_phone, status) VALUES (?, ?, ?, ?, ?, 'requested')");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Unable to submit request']);
        return;
    }
    $stmt->bind_param("issss", $user_id, $city, $reason, $admin_name, $admin_phone);
    $ok = $stmt->execute();
    $stmt->close();

    $user_stmt = $conn->prepare("SELECT email, name FROM users WHERE id = ?");
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user = $user_stmt->get_result()->fetch_assoc();
    $user_stmt->close();

    $subject = "Local Admin request: {$city}";
    $body = "A new Local Admin request was submitted.\n\n"
          . "User: " . ($user['name'] ?? '') . "\n"
          . "Email: " . ($user['email'] ?? '') . "\n"
          . "Name: {$admin_name}\n"
          . "Phone: {$admin_phone}\n"
          . "City: {$city}\n"
          . "Reason: {$reason}\n";
    send_admin_alert($subject, $body);

    if ($ok) {
        echo json_encode(['success' => true, 'message' => 'Request submitted']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Request already exists']);
    }
}

function require_local_admin($conn) {
    if (!is_logged_in()) {
        return [];
    }
    $cities = get_local_admin_cities($conn, get_user_id());
    if (empty($cities)) {
        return [];
    }
    return $cities;
}

function get_moderation_queue($conn) {
    $cities = require_local_admin($conn);
    if (empty($cities)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Not authorized']);
        return;
    }

    $placeholders = implode(',', array_fill(0, count($cities), '?'));
    $types = str_repeat('s', count($cities));
    $sql = "SELECT l.id, l.title, l.city, l.image_url, l.status, l.posted_date, u.name AS seller_name, u.email AS seller_email
            FROM listings l
            JOIN users u ON l.user_id = u.id
            WHERE l.city IN ($placeholders) AND l.status IN ('pending','flagged')
            ORDER BY l.posted_date DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$cities);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();

    echo json_encode(['success' => true, 'rows' => $rows, 'cities' => $cities]);
}

function listing_in_allowed_city($conn, $listing_id, $cities) {
    $stmt = $conn->prepare("SELECT city FROM listings WHERE id = ?");
    $stmt->bind_param("i", $listing_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row || empty($row['city'])) {
        return false;
    }
    return in_array($row['city'], $cities, true);
}

function approve_listing($conn) {
    $cities = require_local_admin($conn);
    $listing_id = intval($_POST['listing_id'] ?? 0);
    if ($listing_id <= 0 || empty($cities) || !listing_in_allowed_city($conn, $listing_id, $cities)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Not authorized']);
        return;
    }

    $stmt = $conn->prepare("UPDATE listings SET status = 'active' WHERE id = ?");
    $stmt->bind_param("i", $listing_id);
    $ok = $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => $ok]);
}

function reject_listing($conn) {
    $cities = require_local_admin($conn);
    $listing_id = intval($_POST['listing_id'] ?? 0);
    if ($listing_id <= 0 || empty($cities) || !listing_in_allowed_city($conn, $listing_id, $cities)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Not authorized']);
        return;
    }

    $stmt = $conn->prepare("UPDATE listings SET status = 'removed' WHERE id = ?");
    $stmt->bind_param("i", $listing_id);
    $ok = $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => $ok]);
}

function is_local_upload($url) {
    return is_string($url) && strpos($url, '/uploads/') === 0;
}

function delete_local_upload($url) {
    if (!is_local_upload($url)) {
        return;
    }
    $root = dirname(__DIR__, 2);
    $path = $root . $url;
    if (is_file($path)) {
        @unlink($path);
    }
}

function delete_listing_images($conn, $listing_id) {
    $stmt = $conn->prepare("SELECT image_url FROM listing_images WHERE listing_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $listing_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $url = $row['image_url'] ?? '';
            if (is_local_upload($url)) {
                delete_local_upload($url);
            }
        }
        $stmt->close();
    }
    $del = $conn->prepare("DELETE FROM listing_images WHERE listing_id = ?");
    if ($del) {
        $del->bind_param("i", $listing_id);
        $del->execute();
        $del->close();
    }
}

function remove_listing_image($conn) {
    $cities = require_local_admin($conn);
    $listing_id = intval($_POST['listing_id'] ?? 0);
    if ($listing_id <= 0 || empty($cities) || !listing_in_allowed_city($conn, $listing_id, $cities)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Not authorized']);
        return;
    }

    $stmt = $conn->prepare("SELECT image_url FROM listings WHERE id = ?");
    $stmt->bind_param("i", $listing_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $current_image_url = $row['image_url'] ?? '';
    if (is_local_upload($current_image_url)) {
        delete_local_upload($current_image_url);
    }
    delete_listing_images($conn, $listing_id);

    $upd = $conn->prepare("UPDATE listings SET image_url = '' WHERE id = ?");
    $upd->bind_param("i", $listing_id);
    $ok = $upd->execute();
    $upd->close();

    echo json_encode(['success' => $ok]);
}
?>
