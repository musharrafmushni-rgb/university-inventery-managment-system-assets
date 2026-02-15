<?php
require_once '../config.php';

if (!isLoggedIn()) {
    http_response_code(401);
    exit();
}

$maintenance_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($maintenance_id == 0) {
    http_response_code(400);
    exit();
}

$conn = getDBConnection();

// Delete maintenance record
$stmt = $conn->prepare("DELETE FROM maintenance WHERE maintenance_id = ?");
$stmt->bind_param("i", $maintenance_id);

if ($stmt->execute()) {
    http_response_code(200);
} else {
    http_response_code(500);
}

$conn->close();
?>
