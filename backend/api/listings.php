<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/.env.loader.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../lib/smtp_mailer.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

function load_badwords() {
    static $words = null;
    if ($words !== null) {
        return $words;
    }

    $path = __DIR__ . '/../config/badwords.txt';
    if (!file_exists($path)) {
        $words = [];
        return $words;
    }

    $content = file_get_contents($path);
    if ($content === false) {
        $words = [];
        return $words;
    }

    $parts = preg_split('/\s+/u', $content);
    $clean = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p !== '') {
            $clean[$p] = true;
        }
    }
    $words = array_keys($clean);
    return $words;
}

function contains_profanity($text) {
    $words = load_badwords();
    if (!$words) {
        return false;
    }

    $lower = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
    foreach ($words as $word) {
        $w = function_exists('mb_strtolower') ? mb_strtolower($word, 'UTF-8') : strtolower($word);
        if ($w === '') {
            continue;
        }
        if (preg_match('/^[a-z0-9]+$/i', $w)) {
            if (preg_match('/\b' . preg_quote($w, '/') . '\b/u', $lower)) {
                return true;
            }
        } else {
            if (strpos($lower, $w) !== false) {
                return true;
            }
        }
    }
    return false;
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

function handle_image_upload($field) {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return ['url' => null, 'error' => null];
    }

    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['url' => null, 'error' => 'Image upload failed'];
    }

    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxSize) {
        return ['url' => null, 'error' => 'Image too large (max 5MB)'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
    if ($finfo) finfo_close($finfo);

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png'
    ];

    if (!isset($allowed[$mime])) {
        return ['url' => null, 'error' => 'Only JPG or PNG images are allowed'];
    }

    $uploadDir = dirname(__DIR__, 2) . '/uploads';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }

    $name = 'listing_' . get_user_id() . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $dest = $uploadDir . '/' . $name;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['url' => null, 'error' => 'Failed to save image'];
    }

    return ['url' => '/uploads/' . $name, 'error' => null];
}

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
} elseif ($action === 'get_city') {
    get_city_listings($conn);
} elseif ($action === 'get_user_listings') {
    get_user_listings($conn);
} elseif ($action === 'get_single') {
    get_single_listing($conn);
} elseif ($action === 'contact_seller') {
    contact_seller($conn);
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

    if (contains_profanity($title . ' ' . $description)) {
        echo json_encode(['success' => false, 'message' => 'Listing contains prohibited language. Please edit and try again.']);
        return;
    }

    $price = $price ? floatval($price) : NULL;
    $expiration = date('Y-m-d H:i:s', strtotime('+30 days'));

    // Prevent duplicates for same user (last 7 days)
    $dup_sql = "SELECT id FROM listings
                WHERE user_id = ?
                  AND status = 'active'
                  AND title = ?
                  AND description = ?
                  AND city = ?
                  AND (price <=> ?)
                  AND posted_date >= (NOW() - INTERVAL 7 DAY)
                LIMIT 1";
    $dup_stmt = $conn->prepare($dup_sql);
    if ($dup_stmt) {
        $dup_stmt->bind_param("isssd", $user_id, $title, $description, $city, $price);
        $dup_stmt->execute();
        $dup_result = $dup_stmt->get_result();
        if ($dup_result && $dup_result->num_rows > 0) {
            $dup_stmt->close();
            echo json_encode(['success' => false, 'message' => 'Duplicate listing detected (same title/description/price in last 7 days).']);
            return;
        }
        $dup_stmt->close();
    }

    $upload = handle_image_upload('image_file');
    if ($upload['error']) {
        echo json_encode(['success' => false, 'message' => $upload['error']]);
        return;
    }
    if ($upload['url']) {
        $image_url = $upload['url'];
    }

    $status = 'pending';
    $sql = "INSERT INTO listings (user_id, title, description, price, category, image_url, city, expiration_date, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }

    $stmt->bind_param("issdsssss", $user_id, $title, $description, $price, $category, $image_url, $city, $expiration, $status);

    if ($stmt->execute()) {
        $listing_id = $stmt->insert_id;
        // Email alert to admin (best-effort)
        $subject = 'New listing pending approval';
        $body = "A new listing is pending approval.

"
              . "ID: {$listing_id}
"
              . "User ID: {$user_id}
"
              . "Title: {$title}
"
              . "City: {$city}
"
              . "Price: " . ($price !== NULL ? $price : 'N/A') . "
";
        send_admin_alert($subject, $body);
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
    $city = trim($_GET['city'] ?? $_POST['city'] ?? '');

    if ($city !== '') {
        $stmt = $conn->prepare("SELECT id, image_url, city FROM listings WHERE status = 'active' AND image_url <> '' AND city = ? ORDER BY posted_date DESC LIMIT 100");
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Database error']);
            return;
        }
        $stmt->bind_param("s", $city);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query("SELECT id, image_url, city FROM listings WHERE status = 'active' AND image_url <> '' ORDER BY posted_date DESC LIMIT 100");
    }

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

// GET listings for a specific city (paid users only)
function get_city_listings($conn) {
    if (!is_payment_verified()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Payment verification required']);
        return;
    }

    $city = trim($_GET['city'] ?? $_POST['city'] ?? '');
    if ($city === '') {
        echo json_encode(['success' => true, 'listings' => []]);
        return;
    }

    $user_id = get_user_id();
    $allowed_cities = get_allowed_cities($conn, $user_id);
    if (empty($allowed_cities) || !in_array($city, $allowed_cities, true)) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        return;
    }

    $sql = "SELECT l.id, l.user_id, l.title, l.description, l.price, l.category,
                   l.image_url, l.city, l.posted_date, u.name, u.reputation_score
            FROM listings l
            JOIN users u ON l.user_id = u.id
            WHERE l.status = 'active' AND l.city = ?
            ORDER BY l.posted_date DESC LIMIT 100";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }

    $stmt->bind_param("s", $city);
    $stmt->execute();
    $result = $stmt->get_result();

    $listings = [];
    while ($row = $result->fetch_assoc()) {
        $listings[] = $row;
    }

    echo json_encode(['success' => true, 'listings' => $listings]);
    $stmt->close();
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

// Contact seller (email hidden)
function contact_seller($conn) {
    if (!is_logged_in()) {
        echo json_encode(['success' => false, 'message' => 'Must be logged in']);
        return;
    }
    if (!is_payment_verified()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Payment verification required']);
        return;
    }

    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    $listing_id = intval($json['listing_id'] ?? $_POST['listing_id'] ?? 0);
    $message = trim($json['message'] ?? $_POST['message'] ?? '');
    $share_email = filter_var($json['share_email'] ?? $_POST['share_email'] ?? false, FILTER_VALIDATE_BOOLEAN);

    if ($listing_id <= 0 || $message === '') {
        echo json_encode(['success' => false, 'message' => 'Listing and message required']);
        return;
    }
    if (strlen($message) > 2000) {
        echo json_encode(['success' => false, 'message' => 'Message too long']);
        return;
    }

    $sql = "SELECT l.id, l.title, l.city, l.user_id, u.email AS seller_email
            FROM listings l
            JOIN users u ON l.user_id = u.id
            WHERE l.id = ? AND l.status = 'active'";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }
    $stmt->bind_param("i", $listing_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Listing not found']);
        return;
    }
    $listing = $result->fetch_assoc();
    $stmt->close();

    $allowed_cities = get_allowed_cities($conn, get_user_id());
    if (!in_array($listing['city'], $allowed_cities, true)) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        return;
    }

    $buyer_name = get_user_name() ?: 'A verified buyer';
    $buyer_email = $_SESSION['user_email'] ?? '';
    $subject = "BrokeAnt buyer inquiry: {$listing['title']}";
    $body = "You have a new message about your listing.\n\n"
          . "Listing: {$listing['title']}\n"
          . "City: {$listing['city']}\n"
          . "Buyer: {$buyer_name}\n"
          . "Message:\n{$message}\n\n"
          . "Note: Buyer email is hidden by default.\n";
    if ($share_email && $buyer_email) {
        $body .= "\nBuyer email (shared): {$buyer_email}\n";
    }

    send_smtp_message($listing['seller_email'], $subject, $body);

    echo json_encode(['success' => true, 'message' => 'Message sent']);
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
    $verify_sql = "SELECT user_id, status, image_url FROM listings WHERE id = ?";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("i", $listing_id);
    $verify_stmt->execute();
    $result = $verify_stmt->get_result();

    $row = $result->fetch_assoc();
    if ($result->num_rows === 0 || $row['user_id'] !== $user_id) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        $verify_stmt->close();
        return;
    }
    $current_status = $row['status'] ?? 'active';
    $current_image_url = $row['image_url'] ?? '';
    $verify_stmt->close();

    $price = $price ? floatval($price) : NULL;

    if (contains_profanity($title . ' ' . $description)) {
        echo json_encode(['success' => false, 'message' => 'Listing contains prohibited language. Please edit and try again.']);
        return;
    }

    $uploaded = false;
    $upload = handle_image_upload('image_file');
    if ($upload['error']) {
        echo json_encode(['success' => false, 'message' => $upload['error']]);
        return;
    }
    if ($upload['url']) {
        $uploaded = true;
        if (is_local_upload($current_image_url)) {
            delete_local_upload($current_image_url);
        }
        $image_url = $upload['url'];
    }

    if (!$uploaded && $image_url !== $current_image_url && is_local_upload($current_image_url)) {
        delete_local_upload($current_image_url);
    }

    if ($status === 'sold' && is_local_upload($image_url)) {
        delete_local_upload($image_url);
        $image_url = '';
    }

    // Do not allow users to self-approve
    if ($current_status === 'pending' && $status !== 'pending') {
        $status = 'pending';
    }

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
    $verify_sql = "SELECT user_id, image_url FROM listings WHERE id = ?";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("i", $listing_id);
    $verify_stmt->execute();
    $result = $verify_stmt->get_result();
    
    $row = $result->fetch_assoc();

    if ($result->num_rows === 0 || $row['user_id'] !== $user_id) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        $verify_stmt->close();
        return;
    }
    $current_image_url = $row['image_url'] ?? '';
    if (is_local_upload($current_image_url)) {
        delete_local_upload($current_image_url);
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
