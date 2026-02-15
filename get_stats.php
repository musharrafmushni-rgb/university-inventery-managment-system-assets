<?php
require_once '../config.php';

if (!isLoggedIn()) {
    http_response_code(401);
    exit();
}

$conn = getDBConnection();

// Get statistics
$stats = [];

$result = $conn->query("SELECT COUNT(*) as count FROM assets");
$stats['total_assets'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM assets WHERE status = 'available'");
$stats['available_assets'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM assets WHERE status = 'in_use'");
$stats['in_use_assets'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM assets WHERE status = 'under_maintenance'");
$stats['maintenance_assets'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT SUM(purchase_cost) as total FROM assets");
$stats['total_value'] = $result->fetch_assoc()['total'] ?? 0;

$conn->close();

// Return stats as JSON
header('Content-Type: application/json');
echo json_encode($stats);
?>
