<?php
session_start();
header('Content-Type: application/json');

require_once '../config/database.php';

$action = $_POST['action'] ?? '';

if ($action === 'register') {
    register($conn);
} elseif ($action === 'login') {
    login($conn);
} elseif ($action === 'logout') {
    logout();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function register($conn) {
    $email = $conn->real_escape_string($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $name = $conn->real_escape_string($_POST['name'] ?? '');
    $city = $conn->real_escape_string($_POST['city'] ?? '');
    $phone = $conn->real_escape_string($_POST['phone'] ?? '');
    
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
    
    // Insert user
    $sql = "INSERT INTO users (email, password_hash, name, city, phone) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }
    
    $stmt->bind_param("sssss", $email, $password_hash, $name, $city, $phone);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Account created successfully!']);
    } else {
        if ($conn->errno === 1062) {
            echo json_encode(['success' => false, 'message' => 'Email already registered']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Registration failed']);
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
    
    $sql = "SELECT id, password_hash, name, city FROM users WHERE email = ?";
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
            $_SESSION['user_name'] = $row['name'];
            $_SESSION['user_city'] = $row['city'];
            
            // Update last login
            $update_sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("i", $row['id']);
            $update_stmt->execute();
            $update_stmt->close();
            
            echo json_encode(['success' => true, 'message' => 'Login successful']);
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
?>
