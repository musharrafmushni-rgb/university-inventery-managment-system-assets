<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$conn = getDBConnection();

// Check if maintenance table exists, create if not
$table_check = $conn->query("SHOW TABLES LIKE 'maintenance'");
if ($table_check->num_rows == 0) {
    // Create the maintenance table
    $create_table_sql = "CREATE TABLE IF NOT EXISTS maintenance (
        maintenance_id INT AUTO_INCREMENT PRIMARY KEY,
        asset_id INT NOT NULL,
        maintenance_type VARCHAR(100) NOT NULL,
        maintenance_date DATE NOT NULL,
        cost DECIMAL(10, 2) DEFAULT 0,
        status VARCHAR(50) DEFAULT 'pending',
        assigned_to INT,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (asset_id) REFERENCES assets(asset_id) ON DELETE CASCADE,
        FOREIGN KEY (assigned_to) REFERENCES users(user_id) ON DELETE SET NULL,
        INDEX idx_asset (asset_id),
        INDEX idx_status (status),
        INDEX idx_date (maintenance_date)
    )";
    
    if (!$conn->query($create_table_sql)) {
        die("Error creating maintenance table: " . $conn->error);
    }
}

// Get maintenance statistics
$stats = [];
$result = $conn->query("SELECT COUNT(*) as total FROM maintenance");
$stats['total_maintenance'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as pending FROM maintenance WHERE status = 'pending'");
$stats['pending_maintenance'] = $result->fetch_assoc()['pending'];

$result = $conn->query("SELECT COUNT(*) as in_progress FROM maintenance WHERE status = 'in_progress'");
$stats['in_progress_maintenance'] = $result->fetch_assoc()['in_progress'];

$result = $conn->query("SELECT COUNT(*) as completed FROM maintenance WHERE status = 'completed'");
$stats['completed_maintenance'] = $result->fetch_assoc()['completed'];

$result = $conn->query("SELECT SUM(cost) as total_cost FROM maintenance");
$stats['total_maintenance_cost'] = $result->fetch_assoc()['total_cost'] ?? 0;

// Add upcoming and overdue (using pending for upcoming)
$stats['upcoming_maintenance'] = $stats['pending_maintenance'];
$stats['overdue_maintenance'] = 0; // No overdue logic in current schema

// Get recent maintenance records
$recent_maintenance = [];
$result = $conn->query("SELECT m.maintenance_id, m.asset_id, m.maintenance_type, m.maintenance_date, 
                              m.cost, m.status, m.assigned_to, 
                              a.asset_name, a.asset_code, c.category_name
                       FROM maintenance m 
                       LEFT JOIN assets a ON m.asset_id = a.asset_id 
                       LEFT JOIN asset_categories c ON a.category_id = c.category_id
                       ORDER BY m.maintenance_date DESC LIMIT 10");
while ($row = $result->fetch_assoc()) {
    $recent_maintenance[] = $row;
}

// Get maintenance by type
$maintenance_by_type = [];
$result = $conn->query("SELECT maintenance_type, COUNT(*) as count 
                       FROM maintenance 
                       GROUP BY maintenance_type");
while ($row = $result->fetch_assoc()) {
    $maintenance_by_type[] = $row;
}

// Handle filter requests
$filter_status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$filter_type = isset($_GET['type']) ? sanitizeInput($_GET['type']) : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Build filter query
$filter_conditions = [];
$filter_params = [];
$filter_types = '';

if ($filter_status) {
    $filter_conditions[] = "m.status = ?";
    $filter_params[] = $filter_status;
    $filter_types .= 's';
}

if ($filter_type) {
    $filter_conditions[] = "m.maintenance_type = ?";
    $filter_params[] = $filter_type;
    $filter_types .= 's';
}

if ($start_date) {
    $filter_conditions[] = "m.maintenance_date >= ?";
    $filter_params[] = $start_date;
    $filter_types .= 's';
}

if ($end_date) {
    $filter_conditions[] = "m.maintenance_date <= ?";
    $filter_params[] = $end_date;
    $filter_types .= 's';
}

$filter_query = !empty($filter_conditions) ? ' AND ' . implode(' AND ', $filter_conditions) : '';

// Get filtered maintenance records
$filtered_maintenance = [];
$filter_sql = "SELECT m.maintenance_id, m.asset_id, m.maintenance_type, m.maintenance_date, 
                      m.cost, m.status, m.assigned_to, m.description,
                      a.asset_name, a.asset_code, c.category_name
              FROM maintenance m 
              LEFT JOIN assets a ON m.asset_id = a.asset_id 
              LEFT JOIN asset_categories c ON a.category_id = c.category_id
              WHERE 1=1 $filter_query 
              ORDER BY m.maintenance_date DESC 
              LIMIT 50";

if (!empty($filter_conditions)) {
    $stmt = $conn->prepare($filter_sql);
    $stmt->bind_param($filter_types, ...$filter_params);
    $stmt->execute();
    $filter_result = $stmt->get_result();
} else {
    $filter_result = $conn->query($filter_sql);
}

while ($row = $filter_result->fetch_assoc()) {
    $filtered_maintenance[] = $row;
}

// Initialize empty array for assets needing maintenance (not used in current schema)
$assets_need_maintenance = [];

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Dashboard - <?php echo SYSTEM_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #4a6491;
            --secondary: #2c3e50;
            --success: #27ae60;
            --warning: #f39c12;
            --danger: #e74c3c;
            --info: #3498db;
            --light: #f8f9fa;
            --dark: #343a40;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: #f5f7fb;
            min-height: 100vh;
        }
        
        /* Sidebar - Same as dashboard */
        .sidebar {
            width: 250px;
            background: linear-gradient(180deg, var(--secondary) 0%, var(--primary) 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            padding: 20px;
        }
        
        .sidebar-header {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }
        
        .sidebar-header h2 {
            font-size: 20px;
            margin-bottom: 5px;
        }
        
        .sidebar-header p {
            font-size: 12px;
            opacity: 0.9;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 30px;
            padding: 15px;
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
        }
        
        .user-details h4 {
            font-size: 14px;
            margin-bottom: 3px;
        }
        
        .user-details span {
            font-size: 12px;
            opacity: 0.8;
        }
        
        .nav-menu {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0;
        }
        
        .nav-item {
            display: block;
            padding: 12px 15px;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s;
            margin-bottom: 5px;
        }
        
        .nav-item:hover,
        .nav-item.active {
            background: rgba(255,255,255,0.2);
        }
        
        /* Main Content */
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        
        /* Page Header */
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
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border-top: 4px solid var(--primary);
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.warning {
            border-top-color: var(--warning);
        }
        
        .stat-card.danger {
            border-top-color: var(--danger);
        }
        
        .stat-card.success {
            border-top-color: var(--success);
        }
        
        .stat-card.info {
            border-top-color: var(--info);
        }
        
        .stat-icon {
            font-size: 40px;
            margin-bottom: 15px;
            opacity: 0.8;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: var(--secondary);
            margin: 10px 0;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        
        /* Charts Container */
        .charts-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        @media (max-width: 1024px) {
            .charts-container {
                grid-template-columns: 1fr;
            }
        }
        
        .chart-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            padding: 20px;
        }
        
        .chart-card h3 {
            color: var(--secondary);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        /* Quick Actions */
        .quick-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
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
        
        /* Filter Section */
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .filter-grid {
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
        
        /* Tables */
        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .card-header {
            padding: 20px;
            background: var(--light);
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-header h3 {
            color: var(--secondary);
        }
        
        .card-body {
            padding: 20px;
            overflow-x: auto;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th, .table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .table th {
            background: var(--light);
            color: var(--secondary);
            font-weight: 600;
        }
        
        .table tr:hover {
            background: #f9f9f9;
        }
        
        /* Status Badges */
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-overdue {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-upcoming {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        
        .type-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .type-preventive {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .type-corrective {
            background: #f8d7da;
            color: #721c24;
        }
        
        .type-emergency {
            background: #f5c6cb;
            color: #721c24;
        }
        
        /* Countdown Indicator */
        .countdown-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 5px;
        }
        
        .countdown-critical {
            background-color: #dc3545;
            animation: pulse 1s infinite;
        }
        
        .countdown-warning {
            background-color: #ffc107;
        }
        
        .countdown-safe {
            background-color: #28a745;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        /* Action Buttons */
        .actions-cell {
            display: flex;
            gap: 5px;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }
        
        /* Modal */
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
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .modal-header h3 {
            color: var(--secondary);
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -250px;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .charts-container {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar (Same as Dashboard) -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2><?php echo SYSTEM_NAME; ?></h2>
            <p><?php echo UNIVERSITY_NAME; ?></p>
        </div>
        
        <div class="user-info">
            <div class="user-avatar">
                <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
            </div>
            <div class="user-details">
                <h4><?php echo $_SESSION['full_name']; ?></h4>
                <span><?php echo ucfirst($_SESSION['role']); ?></span>
            </div>
        </div>
        
        <div class="nav-menu">
            <a href="dashboard.php" class="nav-item">
                📊 Dashboard
            </a>
            <a href="assets.php" class="nav-item">
                🏷️ Assets
            </a>
            <a href="add_asset.php" class="nav-item">
                ➕ Add Asset
            </a>
            <a href="categories.php" class="nav-item">
                📂 Categories
            </a>
            <a href="maintenance.php" class="nav-item active">
                🔧 Maintenance
            </a>
            <a href="reports.php" class="nav-item">
                📊 Reports
            </a>
            <a href="users.php" class="nav-item">
                👥 Users
            </a>
            <a href="logout.php" class="nav-item">
                🚪 Logout
            </a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-tools"></i> Maintenance Dashboard</h1>
            <div class="quick-actions">
                <button class="btn btn-primary" onclick="openAddMaintenanceModal()">
                    <i class="fas fa-plus"></i> Add Maintenance Record
                </button>
                <button class="btn btn-success" onclick="generateMaintenanceReport()">
                    <i class="fas fa-file-export"></i> Generate Report
                </button>
                <button class="btn btn-warning" onclick="viewMaintenanceSchedule()">
                    <i class="fas fa-calendar-alt"></i> View Schedule
                </button>
            </div>
        </div>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-tools text-primary"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['total_maintenance']); ?></div>
                <div class="stat-label">Total Maintenance Records</div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle text-warning"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['upcoming_maintenance']); ?></div>
                <div class="stat-label">Upcoming Maintenance</div>
            </div>
            
            <div class="stat-card danger">
                <div class="stat-icon">
                    <i class="fas fa-clock text-danger"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['overdue_maintenance']); ?></div>
                <div class="stat-label">Overdue Maintenance</div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-icon">
                    <i class="fas fa-money-bill-wave text-success"></i>
                </div>
                <div class="stat-value">Rs. <?php echo number_format($stats['total_maintenance_cost'], 2); ?></div>
                <div class="stat-label">Total Maintenance Cost</div>
            </div>
        </div>
        
        <!-- Charts Section -->
        <div class="charts-container">
            <div class="chart-card">
                <h3>Maintenance by Type</h3>
                <canvas id="maintenanceTypeChart"></canvas>
            </div>
            
            <div class="chart-card">
                <h3>Monthly Maintenance Cost</h3>
                <canvas id="maintenanceCostChart"></canvas>
            </div>
        </div>
        
        <!-- Filter Section -->
        <div class="filter-section">
            <h3 style="margin-bottom: 20px;">Filter Maintenance Records</h3>
            <form method="GET" action="">
                <div class="filter-grid">
                    <div>
                        <label>Maintenance Type</label>
                        <select name="type" class="form-control">
                            <option value="">All Types</option>
                            <option value="preventive" <?php echo $filter_type == 'preventive' ? 'selected' : ''; ?>>Preventive</option>
                            <option value="corrective" <?php echo $filter_type == 'corrective' ? 'selected' : ''; ?>>Corrective</option>
                            <option value="emergency" <?php echo $filter_type == 'emergency' ? 'selected' : ''; ?>>Emergency</option>
                        </select>
                    </div>
                    
                    <div>
                        <label>Asset Status</label>
                        <select name="status" class="form-control">
                            <option value="">All Status</option>
                            <option value="available" <?php echo $filter_status == 'available' ? 'selected' : ''; ?>>Available</option>
                            <option value="in_use" <?php echo $filter_status == 'in_use' ? 'selected' : ''; ?>>In Use</option>
                            <option value="under_maintenance" <?php echo $filter_status == 'under_maintenance' ? 'selected' : ''; ?>>Under Maintenance</option>
                        </select>
                    </div>
                    
                    <div>
                        <label>Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                    </div>
                    
                    <div>
                        <label>End Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                    </div>
                </div>
                
                <div style="text-align: right; margin-top: 15px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <button type="button" class="btn" onclick="resetFilters()">
                        <i class="fas fa-redo"></i> Reset
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Upcoming Maintenance Section -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-calendar-check"></i> Recent Maintenance Records</h3>
                <button class="btn btn-sm btn-primary" onclick="addNewMaintenance()">
                    ➕ Add Maintenance
                </button>
            </div>
            <div class="card-body">
                <?php if(count($filtered_maintenance) > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Asset</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Cost</th>
                            <th>Performed By</th>
                            <th>Next Due</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($filtered_maintenance as $record): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($record['maintenance_date'])); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($record['asset_code'] ?? 'N/A'); ?></strong><br>
                                <small><?php echo htmlspecialchars($record['asset_name'] ?? 'Unknown'); ?></small>
                            </td>
                            <td>
                                <span class="type-badge type-<?php echo $record['maintenance_type']; ?>">
                                    <?php echo ucfirst($record['maintenance_type']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars(substr($record['description'] ?? '', 0, 50)) . (strlen($record['description'] ?? '') > 50 ? '...' : ''); ?></td>
                            <td>Rs. <?php echo number_format($record['cost'] ?? 0, 2); ?></td>
                            <td><?php echo htmlspecialchars($record['category_name'] ?? 'Uncategorized'); ?></td>
                            <td class="actions-cell">
                                <button class="btn btn-sm btn-primary" onclick="viewMaintenanceDetails(<?php echo $record['maintenance_id']; ?>)">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                <button class="btn btn-sm btn-warning" onclick="editMaintenanceRecord(<?php echo $record['maintenance_id']; ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteMaintenanceRecord(<?php echo $record['maintenance_id']; ?>)">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <p style="text-align: center; padding: 40px; color: #999;">No maintenance records found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Add Maintenance Modal -->
    <div id="addMaintenanceModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Maintenance Record</h3>
                <button class="close-modal" onclick="closeModal('addMaintenanceModal')">&times;</button>
            </div>
            <form id="addMaintenanceForm" onsubmit="submitMaintenanceForm(event)">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label>Select Asset *</label>
                        <select id="assetSelect" class="form-control" required>
                            <option value="">Select an asset</option>
                            <!-- Options will be populated by JavaScript -->
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Maintenance Type *</label>
                        <select id="maintenanceType" class="form-control" required>
                            <option value="preventive">Preventive Maintenance</option>
                            <option value="corrective">Corrective Maintenance</option>
                            <option value="emergency">Emergency Maintenance</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Maintenance Date *</label>
                        <input type="date" id="maintenanceDate" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Performed By *</label>
                        <input type="text" id="performedBy" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Cost (Rs.)</label>
                        <input type="number" step="0.01" id="maintenanceCost" class="form-control" value="0">
                    </div>
                    
                    <div class="form-group">
                        <label>Next Maintenance Date</label>
                        <input type="date" id="nextMaintenanceDate" class="form-control">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Description *</label>
                    <textarea id="maintenanceDescription" class="form-control" rows="3" required></textarea>
                </div>
                
                <div class="form-group">
                    <label>Notes</label>
                    <textarea id="maintenanceNotes" class="form-control" rows="2"></textarea>
                </div>
                
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn" onclick="closeModal('addMaintenanceModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Maintenance Record</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- View Maintenance Details Modal -->
    <div id="viewMaintenanceModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Maintenance Record Details</h3>
                <button class="close-modal" onclick="closeModal('viewMaintenanceModal')">&times;</button>
            </div>
            <div id="maintenanceDetails"></div>
        </div>
    </div>
    
    <script>
        // Initialize Charts
        document.addEventListener('DOMContentLoaded', function() {
            // Maintenance Type Chart
            const typeCtx = document.getElementById('maintenanceTypeChart').getContext('2d');
            const typeData = {
                labels: ['Preventive', 'Corrective', 'Emergency'],
                datasets: [{
                    data: [
                        <?php echo count(array_filter($maintenance_by_type, fn($x) => $x['maintenance_type'] == 'preventive')) ?: 0; ?>,
                        <?php echo count(array_filter($maintenance_by_type, fn($x) => $x['maintenance_type'] == 'corrective')) ?: 0; ?>,
                        <?php echo count(array_filter($maintenance_by_type, fn($x) => $x['maintenance_type'] == 'emergency')) ?: 0; ?>
                    ],
                    backgroundColor: [
                        '#d1ecf1',
                        '#f8d7da',
                        '#f5c6cb'
                    ],
                    borderColor: [
                        '#0c5460',
                        '#721c24',
                        '#721c24'
                    ],
                    borderWidth: 1
                }]
            };
            
            new Chart(typeCtx, {
                type: 'doughnut',
                data: typeData,
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
            
            // Maintenance Cost Chart (Sample data - in production, fetch from API)
            const costCtx = document.getElementById('maintenanceCostChart').getContext('2d');
            new Chart(costCtx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [{
                        label: 'Maintenance Cost (Rs.)',
                        data: [45000, 52000, 38000, 62000, 55000, 48000],
                        borderColor: '#4a6491',
                        backgroundColor: 'rgba(74, 100, 145, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'Rs. ' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
            
            // Load assets for maintenance form
            loadAssetsForMaintenance();
            
            // Set default dates
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('maintenanceDate').value = today;
            
            // Set next maintenance date to 3 months from now
            const nextDate = new Date();
            nextDate.setMonth(nextDate.getMonth() + 3);
            document.getElementById('nextMaintenanceDate').value = nextDate.toISOString().split('T')[0];
        });
        
        // Load assets that need maintenance
        function loadAssetsForMaintenance() {
            fetch('api/get_assets_for_maintenance.php')
                .then(response => response.json())
                .then(assets => {
                    const select = document.getElementById('assetSelect');
                    select.innerHTML = '<option value="">Select an asset</option>';
                    assets.forEach(asset => {
                        const option = document.createElement('option');
                        option.value = asset.asset_id;
                        option.textContent = `${asset.asset_code} - ${asset.asset_name} (${asset.location})`;
                        select.appendChild(option);
                    });
                });
        }
        
        // Modal Functions
        function openAddMaintenanceModal() {
            document.getElementById('addMaintenanceModal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function viewMaintenanceDetails(recordId) {
            fetch(`api/get_maintenance_details.php?id=${recordId}`)
                .then(response => response.json())
                .then(data => {
                    const detailsDiv = document.getElementById('maintenanceDetails');
                    detailsDiv.innerHTML = `
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                            <div>
                                <h4>Asset Information</h4>
                                <p><strong>Asset Code:</strong> ${data.asset_code}</p>
                                <p><strong>Asset Name:</strong> ${data.asset_name}</p>
                                <p><strong>Location:</strong> ${data.location}</p>
                            </div>
                            
                            <div>
                                <h4>Maintenance Details</h4>
                                <p><strong>Type:</strong> <span class="type-badge type-${data.maintenance_type}">
                                    ${data.maintenance_type.charAt(0).toUpperCase() + data.maintenance_type.slice(1)}
                                </span></p>
                                <p><strong>Date:</strong> ${new Date(data.maintenance_date).toLocaleDateString()}</p>
                                <p><strong>Cost:</strong> Rs. ${parseFloat(data.cost).toLocaleString('en-US', {minimumFractionDigits: 2})}</p>
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 20px;">
                            <h4>Description</h4>
                            <p>${data.description}</p>
                        </div>
                        
                        <div style="margin-bottom: 20px;">
                            <h4>Technician Information</h4>
                            <p><strong>Performed By:</strong> ${data.performed_by}</p>
                            ${data.next_maintenance_date ? 
                                `<p><strong>Next Maintenance Due:</strong> ${new Date(data.next_maintenance_date).toLocaleDateString()}</p>` : 
                                '<p><strong>Next Maintenance Due:</strong> Not scheduled</p>'}
                        </div>
                        
                        ${data.notes ? `
                            <div style="margin-bottom: 20px;">
                                <h4>Additional Notes</h4>
                                <p>${data.notes}</p>
                            </div>
                        ` : ''}
                    `;
                    
                    document.getElementById('viewMaintenanceModal').style.display = 'block';
                })
                .catch(error => {
                    alert('Error loading maintenance details: ' + error);
                });
        }
        
        // Submit Maintenance Form
        function submitMaintenanceForm(event) {
            event.preventDefault();
            alert('Please use the Add Maintenance page instead.');
            closeModal('addMaintenanceModal');
        }
        
        // Delete Maintenance Record
        function deleteMaintenanceRecord(recordId) {
            if (confirm('Are you sure you want to delete this maintenance record?')) {
                fetch(`api/delete_maintenance.php?id=${recordId}`, {
                    method: 'DELETE'
                })
                .then(response => {
                    if (response.ok) {
                        alert('Maintenance record deleted successfully!');
                        location.reload();
                    } else {
                        alert('Error deleting record');
                    }
                })
                .catch(error => {
                    alert('Error: ' + error);
                });
            }
        }
        
        function editMaintenanceRecord(recordId) {
            window.location.href = `edit_maintenance.php?id=${recordId}`;
        }
        
        function viewMaintenanceDetails(recordId) {
            alert('View details for record ' + recordId);
        }
        
        function addNewMaintenance() {
            window.location.href = 'add_maintenance.php';
        }
        
        function resetFilters() {
            window.location.href = 'maintenance.php';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>