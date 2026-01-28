<?php
session_start();
header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../config/.env.loader.php';

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
} elseif ($action === 'delete_user' && verifyToken($token)) {
    delete_user($conn);
} elseif ($action === 'get_listings' && verifyToken($token)) {
    get_listings($conn);
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
        $stmt = $conn->prepare("SELECT id, email, name, city, payment_status, created_at FROM users $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query("SELECT id, email, name, city, payment_status, created_at FROM users ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
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
        echo json_encode(['success' => true, 'message' => 'User updated']);
    } else {
        echo json_encode(['success' => false, 'error' => $stmt->error]);
    }
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
        SELECT l.id, l.title, l.price, l.city, l.created_at, u.name as user_name, u.email as user_email
        FROM listings l
        JOIN users u ON l.user_id = u.id
        ORDER BY l.created_at DESC
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
?>
