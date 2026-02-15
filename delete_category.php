<?php
require_once '../config.php';

if (!isLoggedIn()) {
    http_response_code(401);
    exit();
}

$category_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($category_id == 0) {
    http_response_code(400);
    exit();
}

$conn = getDBConnection();

// Check if category has assets
$check = $conn->query("SELECT COUNT(*) as count FROM assets WHERE category_id = $category_id");
$result = $check->fetch_assoc();

if ($result['count'] > 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Cannot delete category with existing assets']);
    $conn->close();
    exit();
}

// Delete category
$stmt = $conn->prepare("DELETE FROM asset_categories WHERE category_id = ?");
$stmt->bind_param("i", $category_id);

if ($stmt->execute()) {
    http_response_code(200);
} else {
    http_response_code(500);
}

$conn->close();
?>
