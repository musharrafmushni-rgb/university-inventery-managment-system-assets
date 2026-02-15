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

// Delete asset
$stmt = $conn->prepare("DELETE FROM assets WHERE asset_id = ?");
$stmt->bind_param("i", $asset_id);

if ($stmt->execute()) {
    http_response_code(200);
} else {
    http_response_code(500);
}

$conn->close();
?>
