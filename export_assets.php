<?php
require_once '../config.php';

if (!isLoggedIn()) {
    http_response_code(401);
    exit();
}

$conn = getDBConnection();

// Get all assets
$query = "SELECT a.*, c.category_name FROM assets a 
          LEFT JOIN asset_categories c ON a.category_id = c.category_id
          ORDER BY a.asset_code";

$result = $conn->query($query);

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="assets_export_' . date('Y-m-d_H-i-s') . '.csv"');

// Create file output
$output = fopen('php://output', 'w');

// Write headers
fputcsv($output, ['Asset Code', 'Asset Name', 'Category', 'Serial Number', 'Model', 'Manufacturer', 
                   'Location', 'Status', 'Purchase Date', 'Purchase Cost', 'Current Value', 'Warranty Expiry']);

// Write data
while($asset = $result->fetch_assoc()) {
    fputcsv($output, [
        $asset['asset_code'],
        $asset['asset_name'],
        $asset['category_name'],
        $asset['serial_number'],
        $asset['model'],
        $asset['manufacturer'],
        $asset['location'],
        $asset['status'],
        $asset['purchase_date'],
        $asset['purchase_cost'],
        $asset['current_value'],
        $asset['warranty_expiry']
    ]);
}

fclose($output);
$conn->close();
?>
