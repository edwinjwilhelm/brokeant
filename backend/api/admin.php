<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/.env.loader.php';

// Cache JSON body (read once)
$rawBody = file_get_contents('php://input');
$jsonBody = [];
if ($rawBody !== false && $rawBody !== '') {
    $decoded = json_decode($rawBody, true);
    if (is_array($decoded)) {
        $jsonBody = $decoded;
    }
}

function getJsonBody() {
    global $jsonBody;
    return $jsonBody;
}


// Admin credentials (from .env)
define('ADMIN_EMAIL', getenv('ADMIN_EMAIL') ?: '');
define('ADMIN_PASSWORD', getenv('ADMIN_PASSWORD') ?: '');

$action = $_POST['action'] ?? $_GET['action'] ?? ($jsonBody['action'] ?? '');
$token = getAuthToken();

// Routes
if ($action === 'admin_login') {
    admin_login($conn);
} elseif ($action === 'get_stats' && verifyToken($token)) {
    get_stats($conn);
} elseif ($action === 'get_users' && verifyToken($token)) {
    get_users($conn);
} elseif ($action === 'get_user' && verifyToken($token)) {
    get_user($conn);
} elseif ($action === 'update_user' && verifyToken($token)) {
    update_user($conn);
} elseif ($action === 'mark_paid' && verifyToken($token)) {
    mark_paid($conn);
} elseif ($action === 'get_city_access' && verifyToken($token)) {
    get_city_access_admin($conn);
} elseif ($action === 'approve_city' && verifyToken($token)) {
    approve_city($conn);
} elseif ($action === 'revoke_city' && verifyToken($token)) {
    revoke_city($conn);
} elseif ($action === 'add_city_access' && verifyToken($token)) {
    add_city_access($conn);
} elseif ($action === 'delete_user' && verifyToken($token)) {
    delete_user($conn);
} elseif ($action === 'get_listings' && verifyToken($token)) {
    get_listings($conn);
} elseif ($action === 'approve_listing' && verifyToken($token)) {
    approve_listing($conn);
} elseif ($action === 'remove_listing_image' && verifyToken($token)) {
    remove_listing_image($conn);
} elseif ($action === 'delete_listing' && verifyToken($token)) {
    delete_listing($conn);
} elseif ($action === 'get_payments' && verifyToken($token)) {
    get_payments($conn);
} else {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
}

// Get auth token from header
function getAuthToken() {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $auth = null;
    foreach ($headers as $k => $v) {
        if (strcasecmp($k, 'Authorization') === 0) {
            $auth = $v;
            break;
        }
    }
    if (!$auth && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $auth = $_SERVER['HTTP_AUTHORIZATION'];
    }
    if (!$auth) return null;
    return trim(preg_replace('/^Bearer\s+/i', '', $auth));
}

// Verify token
function verifyToken($token) {
    if (!$token) return false;
    $expected = hash('sha256', ADMIN_EMAIL . ADMIN_PASSWORD);
    return hash_equals($expected, $token);
}

// Admin login
function admin_login($conn) {
    $json = getJsonBody();
    $email = $json['email'] ?? '';
    $password = $json['password'] ?? '';
    
    if ($email === ADMIN_EMAIL && $password === ADMIN_PASSWORD) {
        $token = hash('sha256', ADMIN_EMAIL . ADMIN_PASSWORD);
        echo json_encode([
            'success' => true,
            'token' => $token,
            'message' => 'Login successful'
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
    }
}

// Get statistics
function get_stats($conn) {
    $stats = [
        'total_users' => 0,
        'paid_users' => 0,
        'total_listings' => 0,
        'total_revenue' => 0
    ];
    
    // Count users
    $result = $conn->query("SELECT COUNT(*) as count FROM users");
    $stats['total_users'] = $result->fetch_assoc()['count'];
    
    // Count paid users
    $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE payment_status = 'verified'");
    $stats['paid_users'] = $result->fetch_assoc()['count'];
    
    // Count listings
    $result = $conn->query("SELECT COUNT(*) as count FROM listings");
    $stats['total_listings'] = $result->fetch_assoc()['count'];
    
    // Sum revenue
    $result = $conn->query("SELECT SUM(amount) as total FROM payments WHERE status = 'succeeded'");
    $row = $result->fetch_assoc();
    $stats['total_revenue'] = ($row['total'] / 100) ?? 0;
    
    echo json_encode(['success' => true, ...$stats]);
}

// Get all users
function get_users($conn) {
    $json = getJsonBody();
    $page = intval($json['page'] ?? $_POST['page'] ?? $_GET['page'] ?? 1);
    $limit = intval($json['limit'] ?? $_POST['limit'] ?? $_GET['limit'] ?? 20);
    $q = trim($json['q'] ?? $_POST['q'] ?? $_GET['q'] ?? '');

    if ($page < 1) $page = 1;
    if ($limit < 1) $limit = 20;
    if ($limit > 100) $limit = 100;

    $offset = ($page - 1) * $limit;

    $where = "";
    $params = [];
    $types = "";

    if ($q !== '') {
        $where = "WHERE email LIKE ? OR name LIKE ? OR city LIKE ?";
        $like = '%' . $q . '%';
        $params = [$like, $like, $like];
        $types = "sss";
    }

    if ($where) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users $where");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $total = $stmt->get_result()->fetch_assoc()['count'] ?? 0;
        $stmt->close();
    } else {
        $result = $conn->query("SELECT COUNT(*) as count FROM users");
        $total = $result->fetch_assoc()['count'] ?? 0;
    }

    if ($where) {
        $stmt = $conn->prepare("
            SELECT u.id, u.email, u.name, u.city, u.payment_status, u.created_at,
                   GROUP_CONCAT(uca.city ORDER BY uca.city SEPARATOR ', ') AS approved_cities
            FROM users u
            LEFT JOIN user_city_access uca ON uca.user_id = u.id AND uca.status = 'approved'
            $where
            GROUP BY u.id
            ORDER BY u.created_at DESC
            LIMIT $limit OFFSET $offset
        ");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query("
            SELECT u.id, u.email, u.name, u.city, u.payment_status, u.created_at,
                   GROUP_CONCAT(uca.city ORDER BY uca.city SEPARATOR ', ') AS approved_cities
            FROM users u
            LEFT JOIN user_city_access uca ON uca.user_id = u.id AND uca.status = 'approved'
            GROUP BY u.id
            ORDER BY u.created_at DESC
            LIMIT $limit OFFSET $offset
        ");
    }

    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }

    echo json_encode([
        'success' => true,
        'users' => $users,
        'total' => intval($total),
        'page' => $page,
        'limit' => $limit
    ]);
}


// Get single user
function get_user($conn) {
    $json = getJsonBody();
    $user_id = intval($json['user_id'] ?? 0);
    
    $stmt = $conn->prepare("SELECT id, email, name, city, phone, payment_status FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'user' => $result->fetch_assoc()]);
    } else {
        echo json_encode(['success' => false, 'error' => 'User not found']);
    }
}

// Update user
function update_user($conn) {
    $json = getJsonBody();
    $user_id = intval($json['user_id'] ?? 0);
    $name = $conn->real_escape_string($json['name'] ?? '');
    $city = $conn->real_escape_string($json['city'] ?? '');
    $payment_status = $conn->real_escape_string($json['payment_status'] ?? 'pending');
    
    $stmt = $conn->prepare("UPDATE users SET name = ?, city = ?, payment_status = ? WHERE id = ?");
    $stmt->bind_param("sssi", $name, $city, $payment_status, $user_id);
    
    if ($stmt->execute()) {
        if ($payment_status === 'verified') {
            ensure_city_access($conn, $user_id);
        }
        echo json_encode(['success' => true, 'message' => 'User updated']);
    } else {
        echo json_encode(['success' => false, 'error' => $stmt->error]);
    }
}

// Mark e-transfer as received (manual verification)
function mark_paid($conn) {
    $json = getJsonBody();
    $user_id = intval($json['user_id'] ?? 0);

    if ($user_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid user_id']);
        return;
    }

    $amount = 2.10;
    $currency = 'cad';
    $gateway = 'etransfer';
    $status = 'succeeded';
    $payment_method = 'etransfer';
    $transaction_id = 'etransfer';
    $payment_date = date('Y-m-d H:i:s');

    // Insert payment record only if none exists
    $stmt = $conn->prepare("SELECT id FROM payments WHERE user_id = ? AND status = 'succeeded' LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt = $conn->prepare("
            INSERT INTO payments (user_id, amount, currency, gateway, transaction_id, status, payment_method)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("idsssss", $user_id, $amount, $currency, $gateway, $transaction_id, $status, $payment_method);
        $stmt->execute();
    }

    // Update user payment status
    $stmt = $conn->prepare("
        UPDATE users
        SET payment_status = 'verified',
            payment_date = ?,
            payment_transaction_id = ?
        WHERE id = ?
    ");
    $stmt->bind_param("ssi", $payment_date, $transaction_id, $user_id);

    if ($stmt->execute()) {
        ensure_city_access($conn, $user_id);
        echo json_encode(['success' => true, 'message' => 'Marked as paid']);
    } else {
        echo json_encode(['success' => false, 'error' => $stmt->error]);
    }
}

function ensure_city_access($conn, $user_id) {
    $stmt = $conn->prepare("SELECT city FROM users WHERE id = ?");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $city = $result->fetch_assoc()['city'];
        if ($city) {
            $ins = $conn->prepare("INSERT IGNORE INTO user_city_access (user_id, city, status) VALUES (?, ?, 'approved')");
            if ($ins) {
                $ins->bind_param("is", $user_id, $city);
                $ins->execute();
                $ins->close();
            }
        }
    }
    $stmt->close();
}

// Delete user
function delete_user($conn) {
    $json = getJsonBody();
    $user_id = intval($json['user_id'] ?? 0);
    
    // Delete listings first
    $conn->query("DELETE FROM listings WHERE user_id = $user_id");
    
    // Delete payments
    $conn->query("DELETE FROM payments WHERE user_id = $user_id");
    
    // Delete user
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'User deleted']);
    } else {
        echo json_encode(['success' => false, 'error' => $stmt->error]);
    }
}

// Get all listings
function get_listings($conn) {
    $result = $conn->query("
        SELECT l.id, l.title, l.price, l.city, l.image_url, l.status, l.posted_date AS created_at, u.name as user_name, u.email as user_email
        FROM listings l
        JOIN users u ON l.user_id = u.id
        ORDER BY l.posted_date DESC
    ");
    
    $listings = [];
    while ($row = $result->fetch_assoc()) {
        $listings[] = $row;
    }
    
    echo json_encode(['success' => true, 'listings' => $listings]);
}

// Delete listing
function delete_listing($conn) {
    $json = getJsonBody();
    $listing_id = intval($json['listing_id'] ?? 0);
    
    $stmt = $conn->prepare("DELETE FROM listings WHERE id = ?");
    $stmt->bind_param("i", $listing_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Listing deleted']);
    } else {
        echo json_encode(['success' => false, 'error' => $stmt->error]);
    }
}

// Get payments
function get_payments($conn) {
    $result = $conn->query("
        SELECT p.id, p.amount, p.gateway, p.status, p.created_at, u.email as user_email
        FROM payments p
        LEFT JOIN users u ON p.user_id = u.id
        ORDER BY p.created_at DESC
    ");
    
    $payments = [];
    while ($row = $result->fetch_assoc()) {
        $payments[] = $row;
    }
    
    echo json_encode(['success' => true, 'payments' => $payments]);
}

// Approve listing
function approve_listing($conn) {
    $json = getJsonBody();
    $listing_id = intval($json['listing_id'] ?? 0);

    if ($listing_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid listing id']);
        return;
    }

    $stmt = $conn->prepare("UPDATE listings SET status = 'active' WHERE id = ?");
    $stmt->bind_param("i", $listing_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Listing approved']);
    } else {
        echo json_encode(['success' => false, 'error' => $stmt->error]);
    }
}

// Remove listing image
function remove_listing_image($conn) {
    $json = getJsonBody();
    $listing_id = intval($json['listing_id'] ?? 0);

    if ($listing_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid listing id']);
        return;
    }

    $stmt = $conn->prepare("UPDATE listings SET image_url = '' WHERE id = ?");
    $stmt->bind_param("i", $listing_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Image removed']);
    } else {
        echo json_encode(['success' => false, 'error' => $stmt->error]);
    }
}

// City access admin endpoints
function get_city_access_admin($conn) {
    $result = $conn->query("
        SELECT uca.id, uca.user_id, u.email, u.name, u.city as signup_city, uca.city, uca.status, uca.created_at
        FROM user_city_access uca
        JOIN users u ON uca.user_id = u.id
        ORDER BY uca.created_at DESC
    ");

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    echo json_encode(['success' => true, 'rows' => $rows]);
}

function ensure_city($conn, $city) {
    $stmt = $conn->prepare("INSERT IGNORE INTO cities (name, active) VALUES (?, 1)");
    if ($stmt) {
        $stmt->bind_param("s", $city);
        $stmt->execute();
        $stmt->close();
    }
}

function approve_city($conn) {
    $json = getJsonBody();
    $id = intval($json['id'] ?? 0);

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid id']);
        return;
    }

    $stmt = $conn->prepare("UPDATE user_city_access SET status = 'approved' WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $city_stmt = $conn->prepare("SELECT city FROM user_city_access WHERE id = ?");
        if ($city_stmt) {
            $city_stmt->bind_param("i", $id);
            $city_stmt->execute();
            $row = $city_stmt->get_result()->fetch_assoc();
            if ($row && !empty($row['city'])) {
                ensure_city($conn, $row['city']);
            }
            $city_stmt->close();
        }
        echo json_encode(['success' => true, 'message' => 'Approved']);
    } else {
        echo json_encode(['success' => false, 'error' => $stmt->error]);
    }
}

function revoke_city($conn) {
    $json = getJsonBody();
    $id = intval($json['id'] ?? 0);

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid id']);
        return;
    }

    $stmt = $conn->prepare("DELETE FROM user_city_access WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Revoked']);
    } else {
        echo json_encode(['success' => false, 'error' => $stmt->error]);
    }
}

function add_city_access($conn) {
    $json = getJsonBody();
    $user_id = intval($json['user_id'] ?? 0);
    $city = $conn->real_escape_string($json['city'] ?? '');

    if ($user_id <= 0 || empty($city)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid user_id or city']);
        return;
    }

    $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_city_access WHERE user_id = ?");
    $count_stmt->bind_param("i", $user_id);
    $count_stmt->execute();
    $count = $count_stmt->get_result()->fetch_assoc()['count'] ?? 0;
    $count_stmt->close();

    if ($count >= 6) {
        echo json_encode(['success' => false, 'error' => 'City limit reached (max 6)']);
        return;
    }

    $stmt = $conn->prepare("INSERT IGNORE INTO user_city_access (user_id, city, status) VALUES (?, ?, 'approved')");
    $stmt->bind_param("is", $user_id, $city);
    if ($stmt->execute()) {
        ensure_city($conn, $city);
        echo json_encode(['success' => true, 'message' => 'City added']);
    } else {
        echo json_encode(['success' => false, 'error' => $stmt->error]);
    }
}
?>
