<?php
session_start();
header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../middleware/auth.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Routes
if ($action === 'create') {
    create_listing($conn);
} elseif ($action === 'get_all') {
    get_all_listings($conn);
} elseif ($action === 'get_user_listings') {
    get_user_listings($conn);
} elseif ($action === 'get_single') {
    get_single_listing($conn);
} elseif ($action === 'update') {
    update_listing($conn);
} elseif ($action === 'delete') {
    delete_listing($conn);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

// CREATE new listing
function create_listing($conn) {
    if (!is_logged_in()) {
        echo json_encode(['success' => false, 'message' => 'Must be logged in']);
        return;
    }
    
    $user_id = get_user_id();
    $title = $conn->real_escape_string($_POST['title'] ?? '');
    $description = $conn->real_escape_string($_POST['description'] ?? '');
    $price = $_POST['price'] ?? NULL;
    $category = $conn->real_escape_string($_POST['category'] ?? '');
    $image_url = $conn->real_escape_string($_POST['image_url'] ?? '');
    $city = $conn->real_escape_string($_POST['city'] ?? '');
    
    // Validate
    if (empty($title) || empty($description) || empty($city)) {
        echo json_encode(['success' => false, 'message' => 'Title, description, and city required']);
        return;
    }
    
    if (strlen($title) > 150) {
        echo json_encode(['success' => false, 'message' => 'Title too long (max 150 chars)']);
        return;
    }
    
    $price = $price ? floatval($price) : NULL;
    
    // Create expiration (30 days from now)
    $expiration = date('Y-m-d H:i:s', strtotime('+30 days'));
    
    $sql = "INSERT INTO listings (user_id, title, description, price, category, image_url, city, expiration_date) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }
    
    $stmt->bind_param("issdssss", $user_id, $title, $description, $price, $category, $image_url, $city, $expiration);
    
    if ($stmt->execute()) {
        $listing_id = $stmt->insert_id;
        echo json_encode(['success' => true, 'message' => 'Listing created!', 'listing_id' => $listing_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create listing']);
    }
    
    $stmt->close();
}

// GET all active listings (for homepage)
function get_all_listings($conn) {
    $city = $conn->real_escape_string($_GET['city'] ?? '');
    $category = $conn->real_escape_string($_GET['category'] ?? '');
    
    $sql = "SELECT l.id, l.user_id, l.title, l.description, l.price, l.category, 
                   l.image_url, l.city, l.posted_date, u.name, u.reputation_score
            FROM listings l
            JOIN users u ON l.user_id = u.id
            WHERE l.status = 'active'";
    
    if (!empty($city)) {
        $sql .= " AND l.city = '$city'";
    }
    
    if (!empty($category)) {
        $sql .= " AND l.category = '$category'";
    }
    
    $sql .= " ORDER BY l.posted_date DESC LIMIT 100";
    
    $result = $conn->query($sql);
    
    if ($result) {
        $listings = [];
        while ($row = $result->fetch_assoc()) {
            $listings[] = $row;
        }
        echo json_encode(['success' => true, 'listings' => $listings]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

// GET user's own listings
function get_user_listings($conn) {
    if (!is_logged_in()) {
        echo json_encode(['success' => false, 'message' => 'Must be logged in']);
        return;
    }
    
    $user_id = get_user_id();
    
    $sql = "SELECT id, title, description, price, category, image_url, city, status, posted_date, views
            FROM listings
            WHERE user_id = ?
            ORDER BY posted_date DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $listings = [];
    while ($row = $result->fetch_assoc()) {
        $listings[] = $row;
    }
    
    echo json_encode(['success' => true, 'listings' => $listings]);
    $stmt->close();
}

// GET single listing details
function get_single_listing($conn) {
    $listing_id = intval($_GET['id'] ?? 0);
    
    if ($listing_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid listing ID']);
        return;
    }
    
    $sql = "SELECT l.id, l.user_id, l.title, l.description, l.price, l.category, 
                   l.image_url, l.city, l.posted_date, l.views, l.status, u.name, u.reputation_score
            FROM listings l
            JOIN users u ON l.user_id = u.id
            WHERE l.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $listing_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $listing = $result->fetch_assoc();
        // Increment views
        $update_sql = "UPDATE listings SET views = views + 1 WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $listing_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        echo json_encode(['success' => true, 'listing' => $listing]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Listing not found']);
    }
    
    $stmt->close();
}

// UPDATE listing
function update_listing($conn) {
    if (!is_logged_in()) {
        echo json_encode(['success' => false, 'message' => 'Must be logged in']);
        return;
    }
    
    $user_id = get_user_id();
    $listing_id = intval($_POST['id'] ?? 0);
    $title = $conn->real_escape_string($_POST['title'] ?? '');
    $description = $conn->real_escape_string($_POST['description'] ?? '');
    $price = $_POST['price'] ?? NULL;
    $category = $conn->real_escape_string($_POST['category'] ?? '');
    $image_url = $conn->real_escape_string($_POST['image_url'] ?? '');
    $status = $conn->real_escape_string($_POST['status'] ?? 'active');
    
    // Verify ownership
    $verify_sql = "SELECT user_id FROM listings WHERE id = ?";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("i", $listing_id);
    $verify_stmt->execute();
    $result = $verify_stmt->get_result();
    
    if ($result->num_rows === 0 || $result->fetch_assoc()['user_id'] !== $user_id) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        $verify_stmt->close();
        return;
    }
    $verify_stmt->close();
    
    $price = $price ? floatval($price) : NULL;
    
    $sql = "UPDATE listings SET title = ?, description = ?, price = ?, category = ?, image_url = ?, status = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssdsssi", $title, $description, $price, $category, $image_url, $status, $listing_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Listing updated']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update']);
    }
    
    $stmt->close();
}

// DELETE listing
function delete_listing($conn) {
    if (!is_logged_in()) {
        echo json_encode(['success' => false, 'message' => 'Must be logged in']);
        return;
    }
    
    $user_id = get_user_id();
    $listing_id = intval($_POST['id'] ?? 0);
    
    // Verify ownership
    $verify_sql = "SELECT user_id FROM listings WHERE id = ?";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("i", $listing_id);
    $verify_stmt->execute();
    $result = $verify_stmt->get_result();
    
    if ($result->num_rows === 0 || $result->fetch_assoc()['user_id'] !== $user_id) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        $verify_stmt->close();
        return;
    }
    $verify_stmt->close();
    
    $sql = "DELETE FROM listings WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $listing_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Listing deleted']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete']);
    }
    
    $stmt->close();
}
?>
