<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/.env.loader.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../lib/smtp_mailer.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'register') {
    register($conn);
} elseif ($action === 'login') {
    login($conn);
} elseif ($action === 'logout') {
    logout();
} elseif ($action === 'delete_self') {
    delete_self($conn);
} elseif ($action === 'change_password') {
    change_password($conn);
} elseif ($action === 'request_password_reset') {
    request_password_reset($conn);
} elseif ($action === 'reset_password') {
    reset_password($conn);
} elseif ($action === 'check_session') {
    check_session();
} elseif ($action === 'check_payment') {
    check_payment();
} elseif ($action === 'get_city_access') {
    get_city_access($conn);
} elseif ($action === 'request_city_access') {
    request_city_access($conn);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function register($conn) {
    $email = $conn->real_escape_string($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $name = $conn->real_escape_string($_POST['name'] ?? '');
    $city = $conn->real_escape_string($_POST['city'] ?? '');
    $phone = $conn->real_escape_string($_POST['phone'] ?? '');
    $honeypot = trim($_POST['website'] ?? '');

    if ($honeypot !== '') {
        echo json_encode(['success' => false, 'message' => 'Registration failed']);
        return;
    }
    
    // Validate input
    if (empty($email) || empty($password) || empty($name) || empty($city)) {
        echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
        return;
    }
    
    if (strlen($password) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
        return;
    }
    
    // Hash password
    $password_hash = password_hash($password, PASSWORD_BCRYPT);
    
    // Insert user with payment_status = 'pending'
    $sql = "INSERT INTO users (email, password_hash, name, city, phone, payment_status) VALUES (?, ?, ?, ?, ?, 'pending')";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }
    
    $stmt->bind_param("sssss", $email, $password_hash, $name, $city, $phone);
    
    try {
        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;
            // Default city access (approved)
            $city_access = $conn->prepare("INSERT IGNORE INTO user_city_access (user_id, city, status) VALUES (?, ?, 'approved')");
            if ($city_access) {
                $city_access->bind_param("is", $user_id, $city);
                $city_access->execute();
                $city_access->close();
            }
            echo json_encode(['success' => true, 'message' => 'Account created successfully!', 'user_id' => $user_id]);
        } else {
            if ($conn->errno === 1062) {
                echo json_encode(['success' => false, 'message' => 'Email already registered']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Registration failed']);
            }
        }
    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() === 1062) {
            echo json_encode(['success' => false, 'message' => 'Email already registered']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()]);
        }
    }
    
    $stmt->close();
}

function login($conn) {
    $email = $conn->real_escape_string($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Email and password required']);
        return;
    }
    
    $sql = "SELECT id, password_hash, name, city, payment_status FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        if (password_verify($password, $row['password_hash'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['user_email'] = $email;
            $_SESSION['user_name'] = $row['name'];
            $_SESSION['user_city'] = $row['city'];
            $_SESSION['payment_status'] = $row['payment_status'];
            
            // Update last login
            $update_sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("i", $row['id']);
            $update_stmt->execute();
            $update_stmt->close();
            
            if ($row['payment_status'] === 'verified') {
                // Ensure default city access exists
                $city_access = $conn->prepare("INSERT IGNORE INTO user_city_access (user_id, city, status) VALUES (?, ?, 'approved')");
                if ($city_access) {
                    $city_access->bind_param("is", $row['id'], $row['city']);
                    $city_access->execute();
                    $city_access->close();
                }
                echo json_encode(['success' => true, 'message' => 'Login successful', 'redirect' => '/my-listings.html']);
            } else {
                echo json_encode(['success' => true, 'message' => 'Login successful - please complete payment', 'redirect' => '/payment-checkout.html', 'needs_payment' => true]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Incorrect password']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Email not found']);
    }
    
    $stmt->close();
}

function logout() {
    session_destroy();
    echo json_encode(['success' => true, 'message' => 'Logged out']);
}

function is_local_upload($url) {
    return is_string($url) && strpos($url, '/uploads/') === 0;
}

function delete_local_upload($url) {
    if (!is_local_upload($url)) return;
    $root = dirname(__DIR__, 2);
    $path = $root . $url;
    if (is_file($path)) {
        @unlink($path);
    }
}


function change_password($conn) {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        return;
    }

    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';

    if ($current === '' || $new === '') {
        echo json_encode(['success' => false, 'message' => 'Current and new password required']);
        return;
    }
    if (strlen($new) < 6) {
        echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters']);
        return;
    }

    $user_id = intval($_SESSION['user_id']);

    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'User not found']);
        return;
    }
    $row = $result->fetch_assoc();
    $stmt->close();

    if (!password_verify($current, $row['password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        return;
    }

    $new_hash = password_hash($new, PASSWORD_BCRYPT);
    $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }
    $stmt->bind_param("si", $new_hash, $user_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Password updated']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Unable to update password']);
    }
    $stmt->close();
}

function delete_self($conn) {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        return;
    }

    $password = $_POST['password'] ?? '';
    $confirm = trim($_POST['confirm'] ?? '');
    if ($confirm !== 'DELETE') {
        echo json_encode(['success' => false, 'message' => 'Please type DELETE to confirm']);
        return;
    }
    if ($password === '') {
        echo json_encode(['success' => false, 'message' => 'Password required']);
        return;
    }

    $user_id = intval($_SESSION['user_id']);

    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'User not found']);
        return;
    }
    $row = $result->fetch_assoc();
    $stmt->close();

    if (!password_verify($password, $row['password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Incorrect password']);
        return;
    }

    $images = [];
    $stmt = $conn->prepare("SELECT image_url FROM listings WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            if (!empty($r['image_url'])) {
                $images[] = $r['image_url'];
            }
        }
        $stmt->close();
    }

    $stmt = $conn->prepare("
        SELECT li.image_url
        FROM listing_images li
        JOIN listings l ON li.listing_id = l.id
        WHERE l.user_id = ?
    ");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            if (!empty($r['image_url'])) {
                $images[] = $r['image_url'];
            }
        }
        $stmt->close();
    }

    $ok = true;
    $stmt = $conn->prepare("DELETE FROM listings WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $ok = $stmt->execute();
        $stmt->close();
    } else {
        $ok = false;
    }

    $stmt = $conn->prepare("DELETE FROM user_city_access WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $ok = $ok && $stmt->execute();
        $stmt->close();
    } else {
        $ok = false;
    }

    $stmt = $conn->prepare("DELETE FROM payments WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $ok = $ok && $stmt->execute();
        $stmt->close();
    } else {
        $ok = false;
    }

    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $ok = $ok && $stmt->execute();
        $stmt->close();
    } else {
        $ok = false;
    }

    if (!$ok) {
        echo json_encode(['success' => false, 'message' => 'Failed to delete account']);
        return;
    }

    foreach ($images as $url) {
        delete_local_upload($url);
    }

    session_destroy();
    echo json_encode(['success' => true, 'message' => 'Account deleted']);
}

function check_session() {
    if (isset($_SESSION['user_id'])) {
        echo json_encode([
            'logged_in' => true,
            'user_id' => $_SESSION['user_id'],
            'name' => $_SESSION['user_name'],
            'city' => $_SESSION['user_city'],
            'payment_status' => $_SESSION['payment_status'] ?? 'pending'
        ]);
    } else {
        echo json_encode(['logged_in' => false]);
    }
}

function check_payment() {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['verified' => false, 'message' => 'Not logged in']);
        return;
    }
    
    echo json_encode([
        'verified' => ($_SESSION['payment_status'] ?? null) === 'verified',
        'payment_status' => $_SESSION['payment_status'] ?? 'pending',
        'user_id' => $_SESSION['user_id']
    ]);
}

function get_city_access($conn) {
    if (!is_logged_in()) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        return;
    }

    $user_id = get_user_id();
    $stmt = $conn->prepare("SELECT city, status, created_at FROM user_city_access WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $approved = [];
    $requested = [];
    while ($row = $result->fetch_assoc()) {
        if ($row['status'] === 'approved') {
            $approved[] = $row['city'];
        } else {
            $requested[] = $row['city'];
        }
    }

    echo json_encode([
        'success' => true,
        'approved' => $approved,
        'requested' => $requested,
        'total' => count($approved) + count($requested)
    ]);
    $stmt->close();
}

function request_city_access($conn) {
    if (!is_logged_in()) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        return;
    }

    if (!is_payment_verified()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Payment verification required']);
        return;
    }

    $city = $conn->real_escape_string($_POST['city'] ?? '');
    if (empty($city)) {
        echo json_encode(['success' => false, 'message' => 'City is required']);
        return;
    }

    $user_id = get_user_id();

    // Enforce max 6
    $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_city_access WHERE user_id = ?");
    $count_stmt->bind_param("i", $user_id);
    $count_stmt->execute();
    $count = $count_stmt->get_result()->fetch_assoc()['count'] ?? 0;
    $count_stmt->close();

    if ($count >= 6) {
        echo json_encode(['success' => false, 'message' => 'City limit reached (max 6)']);
        return;
    }

    // Avoid duplicates
    $check = $conn->prepare("SELECT id FROM user_city_access WHERE user_id = ? AND city = ?");
    $check->bind_param("is", $user_id, $city);
    $check->execute();
    $exists = $check->get_result()->num_rows > 0;
    $check->close();

    if ($exists) {
        echo json_encode(['success' => false, 'message' => 'City already requested or approved']);
        return;
    }

    $stmt = $conn->prepare("INSERT INTO user_city_access (user_id, city, status) VALUES (?, ?, 'requested')");
    $stmt->bind_param("is", $user_id, $city);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'City request submitted. Send $2 e-transfer to sales@brokeant.com with your email + city.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to submit request']);
    }
    $stmt->close();
}

function request_password_reset($conn) {
    $email = trim($_POST['email'] ?? '');
    $generic = ['success' => true, 'message' => 'If that email exists, a reset link has been sent.'];

    if ($email === '') {
        echo json_encode($generic);
        return;
    }

    $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ?");
    if (!$stmt) {
        echo json_encode($generic);
        return;
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        $stmt->close();
        echo json_encode($generic);
        return;
    }
    $user = $res->fetch_assoc();
    $stmt->close();

    $token = bin2hex(random_bytes(16));
    $token_hash = hash('sha256', $token);
    $expires = date('Y-m-d H:i:s', time() + 2 * 60 * 60);

    $stmt = $conn->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
    if ($stmt) {
        $uid = intval($user['id']);
        $stmt->bind_param("iss", $uid, $token_hash, $expires);
        $stmt->execute();
        $stmt->close();
    }

    $reset_link = "https://www.brokeant.com/reset-password.html?token={$token}";
    $subject = "BrokeAnt password reset";
    $body = "Hello " . ($user['name'] ?? 'there') . ",

"
          . "We received a request to reset your password.
"
          . "Reset link (valid for 2 hours):
{$reset_link}

"
          . "If you didn't request this, you can ignore this email.
";

    send_smtp_message($email, $subject, $body);

    echo json_encode($generic);
}

function reset_password($conn) {
    $token = trim($_POST['token'] ?? '');
    $new_password = $_POST['new_password'] ?? '';

    if ($token === '' || $new_password === '') {
        echo json_encode(['success' => false, 'message' => 'Token and new password required']);
        return;
    }
    if (strlen($new_password) < 6) {
        echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters']);
        return;
    }

    $token_hash = hash('sha256', $token);
    $stmt = $conn->prepare("SELECT id, user_id, expires_at, used_at FROM password_resets WHERE token_hash = ? LIMIT 1");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }
    $stmt->bind_param("s", $token_hash);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Invalid or expired link']);
        return;
    }
    $row = $res->fetch_assoc();
    $stmt->close();

    if (!empty($row['used_at'])) {
        echo json_encode(['success' => false, 'message' => 'This reset link has already been used']);
        return;
    }
    if (strtotime($row['expires_at']) < time()) {
        echo json_encode(['success' => false, 'message' => 'This reset link has expired']);
        return;
    }

    $user_id = intval($row['user_id']);
    $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
    $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }
    $stmt->bind_param("si", $new_hash, $user_id);
    if (!$stmt->execute()) {
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Unable to update password']);
        return;
    }
    $stmt->close();

    $stmt = $conn->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?");
    if ($stmt) {
        $rid = intval($row['id']);
        $stmt->bind_param("i", $rid);
        $stmt->execute();
        $stmt->close();
    }

    echo json_encode(['success' => true, 'message' => 'Password updated']);
}

?>
