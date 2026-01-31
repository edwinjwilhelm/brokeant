<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

if ($action === 'list') {
    list_cities($conn);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

function list_cities($conn) {
    $result = $conn->query("SELECT name FROM cities WHERE active = 1 ORDER BY name ASC");
    if (!$result) {
        echo json_encode(['success' => false, 'error' => 'Database error']);
        return;
    }

    $cities = [];
    while ($row = $result->fetch_assoc()) {
        $cities[] = ['name' => $row['name']];
    }

    echo json_encode(['success' => true, 'cities' => $cities]);
}
?>
