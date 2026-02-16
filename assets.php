<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$conn = getDBConnection();

// Handle search and filters
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$category = isset($_GET['category']) ? intval($_GET['category']) : 0;
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';

// Build query
$query = "SELECT a.*, c.category_name, u.full_name 
          FROM assets a 
          LEFT JOIN asset_categories c ON a.category_id = c.category_id
          LEFT JOIN users u ON a.assigned_to = u.user_id
          WHERE 1=1";
          
$params = [];
$types = '';

if (!empty($search)) {
    $query .= " AND (a.asset_name LIKE ? OR a.asset_code LIKE ? OR a.serial_number LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'sss';
}

if (!empty($category)) {
    $query .= " AND a.category_id = ?";
    $params[] = $category;
    $types .= 'i';
}

if (!empty($status)) {
    $query .= " AND a.status = ?";
    $params[] = $status;
    $types .= 's';
}

$query .= " ORDER BY a.asset_code";

// Prepare statement
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get categories for filter
$categories_result = $conn->query("SELECT * FROM asset_categories ORDER BY category_name");
$categories = [];
while ($row = $categories_result->fetch_assoc()) {
    $categories[] = $row;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assets Management - <?php echo SYSTEM_NAME; ?></title>
    <style>
        :root {
            --primary: #4a90e2;
            --secondary: #2c3e50;
            --success: #27ae60;
            --warning: #f39c12;
            --danger: #e74c3c;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
        }
        
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            height: 100vh;
            background: var(--secondary);
            color: white;
            padding: 20px;
            overflow-y: auto;
        }
        
        .sidebar-header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }
        
        .sidebar-header h2 {
            font-size: 18px;
        }
        
        .nav-menu {
            list-style: none;
        }
        
        .nav-menu li {
            margin-bottom: 10px;
        }
        
        .nav-menu a {
            color: white;
            text-decoration: none;
            display: block;
            padding: 12px 15px;
            border-radius: 8px;
            transition: background 0.3s;
        }
        
        .nav-menu a:hover,
        .nav-menu a.active {
            background: var(--primary);
        }
        
        .main-content {
            margin-left: 250px;
            padding: 30px;
        }
        
        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table thead {
            background: #f8f9fa;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--secondary);
        }
        
        .table td {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .table tbody tr:hover {
            background: #f9f9f9;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-available {
            background: #d4edda;
            color: #155724;
        }
        
        .status-in_use {
            background: #cce5ff;
            color: #004085;
        }
        
        .status-under_maintenance {
            background: #fff3cd;
            color: #856404;
        }
        
        /* Add to existing styles */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .page-header h1 {
            color: var(--secondary);
            font-size: 32px;
        }
        
        .search-filters {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--secondary);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-warning {
            background: var(--warning);
            color: white;
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }
        
        .actions-cell {
            display: flex;
            gap: 5px;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: var(--secondary);
        }
        
        .asset-code {
            font-family: monospace;
            background: #f0f0f0;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2><?php echo SYSTEM_NAME; ?></h2>
        </div>
        <ul class="nav-menu">
            <li><a href="dashboard.php">📊 Dashboard</a></li>
            <li><a href="assets.php" class="active">📦 Assets</a></li>
            <li><a href="logout.php">🚪 Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="page-header">
            <h1>Assets Management</h1>
            <div>
                <button class="btn btn-primary" onclick="location.href='add_asset.php'">
                    ➕ Add New Asset
                </button>
                <button class="btn btn-success" onclick="exportAssets()">
                    📥 Export to Excel
                </button>
            </div>
        </div>
        
        <!-- Search and Filters -->
        <div class="search-filters">
            <form method="GET" action="">
                <div class="filter-row">
                    <input type="text" name="search" class="form-control" 
                           placeholder="Search by name, code, or serial..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    
                    <select name="category" class="form-control">
                        <option value="">All Categories</option>
                        <?php foreach($categories as $cat): ?>
                        <option value="<?php echo $cat['category_id']; ?>"
                                <?php echo $category == $cat['category_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['category_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="available" <?php echo $status == 'available' ? 'selected' : ''; ?>>Available</option>
                        <option value="in_use" <?php echo $status == 'in_use' ? 'selected' : ''; ?>>In Use</option>
                        <option value="under_maintenance" <?php echo $status == 'under_maintenance' ? 'selected' : ''; ?>>Under Maintenance</option>
                    </select>
                </div>
                
                <div style="text-align: right;">
                    <button type="submit" class="btn btn-primary">🔍 Search</button>
                    <button type="button" class="btn" onclick="resetFilters()">🔄 Reset</button>
                </div>
            </form>
        </div>
        
        <!-- Assets Table -->
        <div class="card">
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Asset Code</th>
                            <th>Asset Name</th>
                            <th>Category</th>
                            <th>Serial Number</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Value</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($asset = $result->fetch_assoc()): ?>
                        <tr>
                            <td><span class="asset-code"><?php echo $asset['asset_code']; ?></span></td>
                            <td><?php echo htmlspecialchars($asset['asset_name']); ?></td>
                            <td><?php echo htmlspecialchars($asset['category_name']); ?></td>
                            <td><?php echo htmlspecialchars($asset['serial_number']); ?></td>
                            <td><?php echo htmlspecialchars($asset['location']); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $asset['status']; ?>">
                                    <?php echo str_replace('_', ' ', ucfirst($asset['status'])); ?>
                                </span>
                            </td>
                            <td>Rs. <?php echo number_format($asset['current_value'], 2); ?></td>
                            <td class="actions-cell">
                                <button class="btn btn-sm btn-primary" 
                                        onclick="viewAsset(<?php echo $asset['asset_id']; ?>)">
                                    👁️ View
                                </button>
                                <button class="btn btn-sm btn-warning"
                                        onclick="editAsset(<?php echo $asset['asset_id']; ?>)">
                                    ✏️ Edit
                                </button>
                                <button class="btn btn-sm btn-danger"
                                        onclick="deleteAsset(<?php echo $asset['asset_id']; ?>)">
                                    🗑️ Delete
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Asset View Modal -->
    <div id="assetModal" class="modal">
        <div class="modal-content">
            <div id="assetDetails"></div>
        </div>
    </div>
    
    <script>
        function viewAsset(assetId) {
            fetch(`api/get_asset.php?id=${assetId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('assetDetails').innerHTML = `
                        <h3>${data.asset_name}</h3>
                        <p><strong>Asset Code:</strong> ${data.asset_code}</p>
                        <p><strong>Category:</strong> ${data.category_name}</p>
                        <p><strong>Serial Number:</strong> ${data.serial_number}</p>
                        <p><strong>Purchase Date:</strong> ${data.purchase_date}</p>
                        <p><strong>Current Value:</strong> Rs. ${parseFloat(data.current_value).toFixed(2)}</p>
                        <p><strong>Location:</strong> ${data.location}</p>
                        <p><strong>Status:</strong> <span class="status-badge status-${data.status}">
                            ${data.status.replace('_', ' ')}
                        </span></p>
                    `;
                    document.getElementById('assetModal').style.display = 'block';
                });
        }
        
        function editAsset(assetId) {
            window.location.href = `edit_asset.php?id=${assetId}`;
        }
        
        function deleteAsset(assetId) {
            if (confirm('Are you sure you want to delete this asset?')) {
                fetch(`api/delete_asset.php?id=${assetId}`, {
                    method: 'DELETE'
                })
                .then(response => {
                    if (response.ok) {
                        alert('Asset deleted successfully!');
                        location.reload();
                    }
                });
            }
        }
        
        function resetFilters() {
            window.location.href = 'assets.php';
        }
        
        function exportAssets() {
            window.location.href = 'api/export_assets.php';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('assetModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>