<?php
session_start();
header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../middleware/auth.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

function get_allowed_cities($conn, $user_id) {
    $stmt = $conn->prepare("SELECT city FROM user_city_access WHERE user_id = ? AND status = 'approved'");
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

// Routes
if ($action === 'create') {
    create_listing($conn);
} elseif ($action === 'get_all') {
    get_all_listings($conn);
} elseif ($action === 'get_public_images') {
    get_public_images($conn);
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

    if (!is_payment_verified()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Payment verification required. Please complete your payment first.', 'redirect' => '/payment-checkout.html']);
        return;
    }

    $user_id = get_user_id();
    $title = $conn->real_escape_string($_POST['title'] ?? '');
    $description = $conn->real_escape_string($_POST['description'] ?? '');
    $price = $_POST['price'] ?? NULL;
    $category = $conn->real_escape_string($_POST['category'] ?? '');
    $image_url = $conn->real_escape_string($_POST['image_url'] ?? '');
    $city = $conn->real_escape_string($_POST['city'] ?? '');

    if (empty($title) || empty($description) || empty($city)) {
        echo json_encode(['success' => false, 'message' => 'Title, description, and city required']);
        return;
    }

    $allowed_cities = get_allowed_cities($conn, $user_id);
    if (empty($allowed_cities) || !in_array($city, $allowed_cities, true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'City not allowed for your account']);
        return;
    }

    if (strlen($title) > 150) {
        echo json_encode(['success' => false, 'message' => 'Title too long (max 150 chars)']);
        return;
    }

    $price = $price ? floatval($price) : NULL;
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

// GET all active listings for allowed cities (paid users only)
function get_all_listings($conn) {
    if (!is_payment_verified()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Payment verification required']);
        return;
    }

    $user_id = get_user_id();
    $allowed_cities = get_allowed_cities($conn, $user_id);
    if (empty($allowed_cities)) {
        echo json_encode(['success' => true, 'listings' => []]);
        return;
    }

    $placeholders = implode(',', array_fill(0, count($allowed_cities), '?'));
    $types = str_repeat('s', count($allowed_cities));

    $sql = "SELECT l.id, l.user_id, l.title, l.description, l.price, l.category, 
                   l.image_url, l.city, l.posted_date, u.name, u.reputation_score
            FROM listings l
            JOIN users u ON l.user_id = u.id
            WHERE l.status = 'active' AND l.city IN ($placeholders)
            ORDER BY l.posted_date DESC LIMIT 100";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$allowed_cities);
    $stmt->execute();
    $result = $stmt->get_result();

    $listings = [];
    while ($row = $result->fetch_assoc()) {
        $listings[] = $row;
    }

    echo json_encode(['success' => true, 'listings' => $listings]);
    $stmt->close();
}

// GET public images (all cities, no details)
function get_public_images($conn) {
    $sql = "SELECT id, image_url FROM listings WHERE status = 'active' AND image_url <> '' ORDER BY posted_date DESC LIMIT 100";
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
    if (!is_payment_verified()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Payment verification required']);
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
    if (!is_payment_verified()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Payment verification required']);
        return;
    }

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
        $allowed_cities = get_allowed_cities($conn, get_user_id());
        if (!in_array($listing['city'], $allowed_cities, true)) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            $stmt->close();
            return;
        }
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
    if (!is_payment_verified()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Payment verification required']);
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
    if (!is_payment_verified()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Payment verification required']);
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
