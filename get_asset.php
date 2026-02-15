<?php
require_once '../config.php';

if (!isLoggedIn()) {
    http_response_code(401);
    exit();
}

$asset_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($asset_id == 0) {
    http_response_code(400);
    exit();
}

$conn = getDBConnection();

// Get asset details
$stmt = $conn->prepare("SELECT a.*, c.category_name, u.full_name 
                       FROM assets a 
                       LEFT JOIN asset_categories c ON a.category_id = c.category_id
                       LEFT JOIN users u ON a.assigned_to = u.user_id
                       WHERE a.asset_id = ?");
$stmt->bind_param("i", $asset_id);
$stmt->execute();
$result = $stmt->get_result();
$asset = $result->fetch_assoc();

if (!$asset) {
    http_response_code(404);
    echo json_encode(['error' => 'Asset not found']);
    $conn->close();
    exit();
}

$conn->close();

// Return asset as JSON
header('Content-Type: application/json');
echo json_encode($asset);
?>
