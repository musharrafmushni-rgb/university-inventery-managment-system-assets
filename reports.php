<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$conn = getDBConnection();

// Get current year and month
$current_year = date('Y');
$current_month = date('m');

// Get filter parameters
$report_type = isset($_GET['type']) ? sanitizeInput($_GET['type']) : 'overview';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$category_id = isset($_GET['category']) ? intval($_GET['category']) : 0;
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$department = isset($_GET['department']) ? sanitizeInput($_GET['department']) : '';

// Get categories for filter
$categories_result = $conn->query("SELECT * FROM asset_categories ORDER BY category_name");
$categories = [];
while ($row = $categories_result->fetch_assoc()) {
    $categories[] = $row;
}

// Get departments
$departments_result = $conn->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL ORDER BY department");
$departments = [];
while ($row = $departments_result->fetch_assoc()) {
    $departments[] = $row['department'];
}

// Get overall statistics
$stats = [];

// Total assets
$result = $conn->query("SELECT COUNT(*) as total FROM assets");
$stats['total_assets'] = $result->fetch_assoc()['total'];

// Total asset value
$result = $conn->query("SELECT SUM(purchase_cost) as total_value, SUM(current_value) as current_value FROM assets");
$row = $result->fetch_assoc();
$stats['total_purchase_value'] = $row['total_value'] ?? 0;
$stats['total_current_value'] = $row['current_value'] ?? 0;

// Assets by status
$result = $conn->query("SELECT status, COUNT(*) as count FROM assets GROUP BY status");
$stats['by_status'] = [];
while ($row = $result->fetch_assoc()) {
    $stats['by_status'][$row['status']] = $row['count'];
}

// Assets by category
$result = $conn->query("SELECT c.category_name, COUNT(a.asset_id) as count 
                       FROM asset_categories c 
                       LEFT JOIN assets a ON c.category_id = a.category_id 
                       GROUP BY c.category_id, c.category_name 
                       ORDER BY count DESC");
$stats['by_category'] = [];
while ($row = $result->fetch_assoc()) {
    $stats['by_category'][] = $row;
}

// Maintenance statistics
$result = $conn->query("SELECT COUNT(*) as total_maintenance, SUM(cost) as total_cost FROM maintenance");
if ($result) {
    $row = $result->fetch_assoc();
    $stats['total_maintenance'] = $row['total_maintenance'] ?? 0;
    $stats['total_maintenance_cost'] = $row['total_cost'] ?? 0;
} else {
    $stats['total_maintenance'] = 0;
    $stats['total_maintenance_cost'] = 0;
}

// Recent acquisitions (last 30 days)
$result = $conn->query("SELECT COUNT(*) as recent FROM assets 
                       WHERE purchase_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
$stats['recent_acquisitions'] = $result->fetch_assoc()['recent'] ?? 0;

// Get monthly asset acquisitions for the current year
$monthly_data = [];
for ($i = 1; $i <= 12; $i++) {
    $month = str_pad($i, 2, '0', STR_PAD_LEFT);
    $result = $conn->query("SELECT COUNT(*) as count, SUM(purchase_cost) as value 
                           FROM assets 
                           WHERE YEAR(purchase_date) = $current_year 
                           AND MONTH(purchase_date) = $i");
    $row = $result->fetch_assoc();
    $monthly_data[] = [
        'month' => date('M', mktime(0, 0, 0, $i, 1)),
        'count' => $row['count'] ?? 0,
        'value' => $row['value'] ?? 0
    ];
}

// Get assets by location
$result = $conn->query("SELECT location, COUNT(*) as count, SUM(current_value) as value 
                       FROM assets 
                       WHERE location IS NOT NULL AND location != '' 
                       GROUP BY location 
                       ORDER BY count DESC LIMIT 10");
$location_data = [];
while ($row = $result->fetch_assoc()) {
    $location_data[] = $row;
}

// Get maintenance cost by month
$maintenance_monthly = [];
for ($i = 1; $i <= 12; $i++) {
    $month = str_pad($i, 2, '0', STR_PAD_LEFT);
    $result = $conn->query("SELECT SUM(cost) as cost 
                           FROM maintenance_records 
                           WHERE YEAR(maintenance_date) = $current_year 
                           AND MONTH(maintenance_date) = $i");
    $row = $result->fetch_assoc();
    $maintenance_monthly[] = [
        'month' => date('M', mktime(0, 0, 0, $i, 1)),
        'cost' => $row['cost'] ?? 0
    ];
}

// Get asset depreciation data
$result = $conn->query("SELECT 
                        SUM(purchase_cost) as total_purchase,
                        SUM(current_value) as total_current,
                        SUM(purchase_cost - current_value) as total_depreciation,
                        AVG((purchase_cost - current_value) / purchase_cost * 100) as avg_depreciation_rate
                        FROM assets 
                        WHERE purchase_cost > 0");
$depreciation_data = $result->fetch_assoc();

// Get detailed report based on filters
$detailed_report = [];
$report_query = "SELECT a.*, c.category_name, u.full_name, u.department 
                FROM assets a 
                LEFT JOIN asset_categories c ON a.category_id = c.category_id
                LEFT JOIN users u ON a.assigned_to = u.user_id
                WHERE 1=1";

$report_params = [];
$report_types = '';

if (!empty($start_date)) {
    $report_query .= " AND a.purchase_date >= ?";
    $report_params[] = $start_date;
    $report_types .= 's';
}

if (!empty($end_date)) {
    $report_query .= " AND a.purchase_date <= ?";
    $report_params[] = $end_date;
    $report_types .= 's';
}

if (!empty($category_id)) {
    $report_query .= " AND a.category_id = ?";
    $report_params[] = $category_id;
    $report_types .= 'i';
}

if (!empty($status)) {
    $report_query .= " AND a.status = ?";
    $report_params[] = $status;
    $report_types .= 's';
}

if (!empty($department)) {
    $report_query .= " AND u.department = ?";
    $report_params[] = $department;
    $report_types .= 's';
}

$report_query .= " ORDER BY a.purchase_date DESC";

if (!empty($report_params)) {
    $stmt = $conn->prepare($report_query);
    $stmt->bind_param($report_types, ...$report_params);
    $stmt->execute();
    $report_result = $stmt->get_result();
} else {
    $report_result = $conn->query($report_query);
}

while ($row = $report_result->fetch_assoc()) {
    $detailed_report[] = $row;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - <?php echo SYSTEM_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
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
        
        /* Sidebar */
        .sidebar {
            width: 250px;
            background: linear-gradient(180deg, var(--secondary) 0%, var(--primary) 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        
        .sidebar-header {
            padding: 25px 20px;
            background: rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .sidebar-header h2 {
            font-size: 18px;
            margin-bottom: 5px;
        }
        
        .user-info {
            padding: 20px;
            background: rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-weight: bold;
        }
        
        .nav-menu {
            padding: 20px 0;
        }
        
        .nav-item {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }
        
        .nav-item:hover, .nav-item.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: white;
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
        
        /* Report Type Selector */
        .report-type-selector {
            display: flex;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 30px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .report-type {
            padding: 15px 25px;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
            flex: 1;
            border-bottom: 3px solid transparent;
        }
        
        .report-type:hover {
            background: #f8f9fa;
        }
        
        .report-type.active {
            background: var(--primary);
            color: white;
            border-bottom-color: white;
        }
        
        .report-type i {
            display: block;
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        /* Filter Section */
        .filter-section {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(74, 100, 145, 0.4);
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
        
        /* Stats Cards */
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
            transition: transform 0.3s;
            border-top: 4px solid var(--primary);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.success {
            border-top-color: var(--success);
        }
        
        .stat-card.warning {
            border-top-color: var(--warning);
        }
        
        .stat-card.danger {
            border-top-color: var(--danger);
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
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        @media (max-width: 1200px) {
            .charts-container {
                grid-template-columns: 1fr;
            }
        }
        
        .chart-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            padding: 50px;
            height: 600px;
            display: flex;
            flex-direction: column;
        }
        
        .chart-card h3 {
            color: var(--secondary);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .chart-container {
            flex: 1;
            position: relative;
        }
        
        /* Detailed Report Table */
        .detailed-report {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .report-header {
            padding: 20px;
            background: var(--light);
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .report-header h3 {
            color: var(--secondary);
        }
        
        .report-body {
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
            position: sticky;
            top: 0;
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
        
        .status-available {
            background: #d4edda;
            color: #155724;
        }
        
        .status-in_use {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .status-under_maintenance {
            background: #fff3cd;
            color: #856404;
        }
        
        /* Export Options */
        .export-options {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .export-btn {
            padding: 10px 20px;
            border: 2px solid var(--primary);
            background: white;
            color: var(--primary);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .export-btn:hover {
            background: var(--primary);
            color: white;
        }
        
        /* Summary Cards */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            padding: 25px;
        }
        
        .summary-card h4 {
            color: var(--secondary);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px dashed #eee;
        }
        
        .summary-item:last-child {
            border-bottom: none;
        }
        
        /* Loading Overlay */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            color: white;
        }
        
        .loading-spinner {
            border: 5px solid #f3f3f3;
            border-top: 5px solid var(--primary);
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
            
            .chart-card {
                height: 300px;
            }
            
            .report-type-selector {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
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
            <a href="maintenance.php" class="nav-item">
                🔧 Maintenance
            </a>
            <a href="reports.php" class="nav-item active">
                📈 Reports & Analytics
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
            <h1><i class="fas fa-chart-line"></i> Reports & Analytics Dashboard</h1>
            <div class="export-options">
                <button class="btn btn-primary" onclick="generatePDF()">
                    <i class="fas fa-file-pdf"></i> Export as PDF
                </button>
                <button class="btn btn-success" onclick="exportToExcel()">
                    <i class="fas fa-file-excel"></i> Export to Excel
                </button>
                <button class="btn btn-warning" onclick="printReport()">
                    <i class="fas fa-print"></i> Print Report
                </button>
            </div>
        </div>
        
        <!-- Report Type Selector -->
        <div class="report-type-selector">
            <div class="report-type <?php echo $report_type == 'overview' ? 'active' : ''; ?>" 
                 onclick="changeReportType('overview')">
                <i class="fas fa-tachometer-alt"></i>
                <span>Overview</span>
            </div>
            <div class="report-type <?php echo $report_type == 'assets' ? 'active' : ''; ?>" 
                 onclick="changeReportType('assets')">
                <i class="fas fa-laptop"></i>
                <span>Assets</span>
            </div>
            <div class="report-type <?php echo $report_type == 'financial' ? 'active' : ''; ?>" 
                 onclick="changeReportType('financial')">
                <i class="fas fa-money-bill-wave"></i>
                <span>Financial</span>
            </div>
            <div class="report-type <?php echo $report_type == 'maintenance' ? 'active' : ''; ?>" 
                 onclick="changeReportType('maintenance')">
                <i class="fas fa-tools"></i>
                <span>Maintenance</span>
            </div>
            <div class="report-type <?php echo $report_type == 'depreciation' ? 'active' : ''; ?>" 
                 onclick="changeReportType('depreciation')">
                <i class="fas fa-chart-line"></i>
                <span>Depreciation</span>
            </div>
        </div>
        
        <!-- Filter Section -->
        <div class="filter-section">
            <h3 style="margin-bottom: 20px;">Filter Report Data</h3>
            <form method="GET" action="" id="reportFilterForm">
                <input type="hidden" name="type" id="reportType" value="<?php echo $report_type; ?>">
                
                <div class="filter-grid">
                    <div>
                        <label>Start Date</label>
                        <input type="date" name="start_date" class="form-control" 
                               value="<?php echo $start_date; ?>">
                    </div>
                    
                    <div>
                        <label>End Date</label>
                        <input type="date" name="end_date" class="form-control" 
                               value="<?php echo $end_date; ?>">
                    </div>
                    
                    <div>
                        <label>Asset Category</label>
                        <select name="category" class="form-control">
                            <option value="">All Categories</option>
                            <?php foreach($categories as $cat): ?>
                            <option value="<?php echo $cat['category_id']; ?>"
                                <?php echo $category_id == $cat['category_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label>Asset Status</label>
                        <select name="status" class="form-control">
                            <option value="">All Status</option>
                            <option value="available" <?php echo $status == 'available' ? 'selected' : ''; ?>>Available</option>
                            <option value="in_use" <?php echo $status == 'in_use' ? 'selected' : ''; ?>>In Use</option>
                            <option value="under_maintenance" <?php echo $status == 'under_maintenance' ? 'selected' : ''; ?>>Under Maintenance</option>
                        </select>
                    </div>
                    
                    <div>
                        <label>Department</label>
                        <select name="department" class="form-control">
                            <option value="">All Departments</option>
                            <?php foreach($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept); ?>"
                                <?php echo $department == $dept ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div style="text-align: right; margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <button type="button" class="btn" onclick="resetFilters()">
                        <i class="fas fa-redo"></i> Reset Filters
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Loading Overlay -->
        <div class="loading-overlay" id="loadingOverlay">
            <div class="loading-spinner"></div>
            <p>Generating Report...</p>
        </div>
        
        <!-- Report Content (Dynamic based on type) -->
        <div id="reportContent">
            <?php if($report_type == 'overview'): ?>
                <!-- Overview Report -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-laptop text-primary"></i>
                        </div>
                        <div class="stat-value"><?php echo number_format($stats['total_assets']); ?></div>
                        <div class="stat-label">Total Assets</div>
                    </div>
                    
                    <div class="stat-card success">
                        <div class="stat-icon">
                            <i class="fas fa-money-bill-wave text-success"></i>
                        </div>
                        <div class="stat-value">Rs. <?php echo number_format($stats['total_current_value'], 2); ?></div>
                        <div class="stat-label">Current Asset Value</div>
                    </div>
                    
                    <div class="stat-card warning">
                        <div class="stat-icon">
                            <i class="fas fa-tools text-warning"></i>
                        </div>
                        <div class="stat-value"><?php echo number_format($stats['total_maintenance']); ?></div>
                        <div class="stat-label">Maintenance Records</div>
                    </div>
                    
                    <div class="stat-card info">
                        <div class="stat-icon">
                            <i class="fas fa-chart-line text-info"></i>
                        </div>
                        <div class="stat-value">Rs. <?php echo number_format($stats['total_maintenance_cost'], 2); ?></div>
                        <div class="stat-label">Total Maintenance Cost</div>
                    </div>
                </div>
                
                <!-- Charts -->
                <div class="charts-container">
                    <div class="chart-card">
                        <h3>Asset Distribution by Category</h3>
                        <div class="chart-container">
                            <canvas id="categoryChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="chart-card">
                        <h3>Asset Status Distribution</h3>
                        <div class="chart-container">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="chart-card">
                        <h3>Monthly Asset Acquisitions (<?php echo $current_year; ?>)</h3>
                        <div class="chart-container">
                            <canvas id="monthlyChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="chart-card">
                        <h3>Top 10 Locations by Asset Count</h3>
                        <div class="chart-container">
                            <canvas id="locationChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Summary Cards -->
                <div class="summary-grid">
                    <div class="summary-card">
                        <h4>Asset Status Summary</h4>
                        <div class="summary-item">
                            <span>Available</span>
                            <strong><?php echo $stats['by_status']['available'] ?? 0; ?></strong>
                        </div>
                        <div class="summary-item">
                            <span>In Use</span>
                            <strong><?php echo $stats['by_status']['in_use'] ?? 0; ?></strong>
                        </div>
                        <div class="summary-item">
                            <span>Under Maintenance</span>
                            <strong><?php echo $stats['by_status']['under_maintenance'] ?? 0; ?></strong>
                        </div>
                        <div class="summary-item">
                            <span>Retired</span>
                            <strong><?php echo $stats['by_status']['retired'] ?? 0; ?></strong>
                        </div>
                    </div>
                    
                    <div class="summary-card">
                        <h4>Financial Summary</h4>
                        <div class="summary-item">
                            <span>Total Purchase Value</span>
                            <strong>Rs. <?php echo number_format($stats['total_purchase_value'], 2); ?></strong>
                        </div>
                        <div class="summary-item">
                            <span>Total Current Value</span>
                            <strong>Rs. <?php echo number_format($stats['total_current_value'], 2); ?></strong>
                        </div>
                        <div class="summary-item">
                            <span>Total Depreciation</span>
                            <strong>Rs. <?php echo number_format($stats['total_purchase_value'] - $stats['total_current_value'], 2); ?></strong>
                        </div>
                        <div class="summary-item">
                            <span>Avg. Depreciation Rate</span>
                            <strong><?php echo number_format((($stats['total_purchase_value'] - $stats['total_current_value']) / $stats['total_purchase_value']) * 100, 2); ?>%</strong>
                        </div>
                    </div>
                    
                    <div class="summary-card">
                        <h4>Maintenance Summary</h4>
                        <div class="summary-item">
                            <span>Total Maintenance Records</span>
                            <strong><?php echo number_format($stats['total_maintenance']); ?></strong>
                        </div>
                        <div class="summary-item">
                            <span>Total Maintenance Cost</span>
                            <strong>Rs. <?php echo number_format($stats['total_maintenance_cost'], 2); ?></strong>
                        </div>
                        <div class="summary-item">
                            <span>Avg. Cost per Maintenance</span>
                            <strong>Rs. <?php echo $stats['total_maintenance'] > 0 ? number_format($stats['total_maintenance_cost'] / $stats['total_maintenance'], 2) : '0.00'; ?></strong>
                        </div>
                        <div class="summary-item">
                            <span>Recent Acquisitions (30 days)</span>
                            <strong><?php echo number_format($stats['recent_acquisitions']); ?></strong>
                        </div>
                    </div>
                </div>
                
            <?php elseif($report_type == 'assets'): ?>
                <!-- Assets Report -->
                <div class="detailed-report">
                    <div class="report-header">
                        <h3>Assets Report (<?php echo count($detailed_report); ?> records)</h3>
                        <span>Date Range: <?php echo date('M d, Y', strtotime($start_date)); ?> to <?php echo date('M d, Y', strtotime($end_date)); ?></span>
                    </div>
                    <div class="report-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Asset Code</th>
                                    <th>Asset Name</th>
                                    <th>Category</th>
                                    <th>Serial Number</th>
                                    <th>Location</th>
                                    <th>Status</th>
                                    <th>Assigned To</th>
                                    <th>Purchase Date</th>
                                    <th>Purchase Cost</th>
                                    <th>Current Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($detailed_report as $asset): ?>
                                <tr>
                                    <td><strong><?php echo $asset['asset_code']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($asset['asset_name']); ?></td>
                                    <td><?php echo htmlspecialchars($asset['category_name']); ?></td>
                                    <td><?php echo htmlspecialchars($asset['serial_number']); ?></td>
                                    <td><?php echo htmlspecialchars($asset['location']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $asset['status']; ?>">
                                            <?php echo str_replace('_', ' ', ucfirst($asset['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($asset['full_name'] ?? 'Not Assigned'); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($asset['purchase_date'])); ?></td>
                                    <td>Rs. <?php echo number_format($asset['purchase_cost'], 2); ?></td>
                                    <td>Rs. <?php echo number_format($asset['current_value'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr style="background: #f8f9fa; font-weight: bold;">
                                    <td colspan="8" style="text-align: right;">Totals:</td>
                                    <td>Rs. <?php echo number_format(array_sum(array_column($detailed_report, 'purchase_cost')), 2); ?></td>
                                    <td>Rs. <?php echo number_format(array_sum(array_column($detailed_report, 'current_value')), 2); ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                
            <?php elseif($report_type == 'financial'): ?>
                <!-- Financial Report -->
                <div class="charts-container">
                    <div class="chart-card">
                        <h3>Asset Value by Category</h3>
                        <div class="chart-container">
                            <canvas id="valueByCategoryChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="chart-card">
                        <h3>Purchase vs Current Value Trend</h3>
                        <div class="chart-container">
                            <canvas id="valueTrendChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="detailed-report">
                    <div class="report-header">
                        <h3>Financial Summary Report</h3>
                        <span>Generated on: <?php echo date('F j, Y'); ?></span>
                    </div>
                    <div class="report-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Asset Count</th>
                                    <th>Total Purchase Value</th>
                                    <th>Total Current Value</th>
                                    <th>Total Depreciation</th>
                                    <th>Depreciation Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_purchase = 0;
                                $total_current = 0;
                                ?>
                                <?php foreach($stats['by_category'] as $cat): 
                                    // Calculate values for this category
                                    $cat_purchase = 0;
                                    $cat_current = 0;
                                    foreach($detailed_report as $asset) {
                                        if($asset['category_name'] == $cat['category_name']) {
                                            $cat_purchase += $asset['purchase_cost'];
                                            $cat_current += $asset['current_value'];
                                        }
                                    }
                                    $total_purchase += $cat_purchase;
                                    $total_current += $cat_current;
                                    $depreciation = $cat_purchase - $cat_current;
                                    $dep_rate = $cat_purchase > 0 ? ($depreciation / $cat_purchase) * 100 : 0;
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($cat['category_name']); ?></td>
                                    <td><?php echo $cat['count']; ?></td>
                                    <td>Rs. <?php echo number_format($cat_purchase, 2); ?></td>
                                    <td>Rs. <?php echo number_format($cat_current, 2); ?></td>
                                    <td>Rs. <?php echo number_format($depreciation, 2); ?></td>
                                    <td><?php echo number_format($dep_rate, 2); ?>%</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot style="background: #f8f9fa; font-weight: bold;">
                                <tr>
                                    <td>Total</td>
                                    <td><?php echo $stats['total_assets']; ?></td>
                                    <td>Rs. <?php echo number_format($total_purchase, 2); ?></td>
                                    <td>Rs. <?php echo number_format($total_current, 2); ?></td>
                                    <td>Rs. <?php echo number_format($total_purchase - $total_current, 2); ?></td>
                                    <td><?php echo $total_purchase > 0 ? number_format((($total_purchase - $total_current) / $total_purchase) * 100, 2) : '0.00'; ?>%</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                
            <?php elseif($report_type == 'maintenance'): ?>
                <!-- Maintenance Report -->
                <div class="charts-container">
                    <div class="chart-card">
                        <h3>Maintenance Cost Trend (<?php echo $current_year; ?>)</h3>
                        <div class="chart-container">
                            <canvas id="maintenanceCostChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="chart-card">
                        <h3>Maintenance by Asset Category</h3>
                        <div class="chart-container">
                            <canvas id="maintenanceByCategoryChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="detailed-report">
                    <div class="report-header">
                        <h3>Maintenance Analysis Report</h3>
                        <span>Period: <?php echo date('M d, Y', strtotime($start_date)); ?> to <?php echo date('M d, Y', strtotime($end_date)); ?></span>
                    </div>
                    <div class="report-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Asset Code</th>
                                    <th>Asset Name</th>
                                    <th>Last Maintenance</th>
                                    <th>Next Maintenance</th>
                                    <th>Days Until</th>
                                    <th>Maintenance Frequency</th>
                                    <th>Total Maintenance Cost</th>
                                    <th>Maintenance Count</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                // In a real system, you would fetch this data from database
                                // This is sample data for demonstration
                                $sample_maintenance_data = [
                                    ['VU-IT-001', 'Dell Laptop', '2024-01-15', '2024-04-15', 45, 'Quarterly', 15000, 3],
                                    ['VU-IT-002', 'Projector', '2024-02-10', '2024-05-10', 30, 'Quarterly', 8500, 2],
                                    ['VU-FURN-001', 'Office Desk', '2023-12-20', '2024-06-20', 120, 'Semi-Annual', 5000, 1],
                                ];
                                
                                foreach($sample_maintenance_data as $data):
                                    $days_until = (strtotime($data[3]) - time()) / (60 * 60 * 24);
                                ?>
                                <tr>
                                    <td><strong><?php echo $data[0]; ?></strong></td>
                                    <td><?php echo $data[1]; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($data[2])); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($data[3])); ?></td>
                                    <td>
                                        <?php if($days_until < 0): ?>
                                            <span class="status-badge status-overdue"><?php echo abs(floor($days_until)); ?> days overdue</span>
                                        <?php else: ?>
                                            <?php echo floor($days_until); ?> days
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $data[5]; ?></td>
                                    <td>Rs. <?php echo number_format($data[6], 2); ?></td>
                                    <td><?php echo $data[7]; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
            <?php elseif($report_type == 'depreciation'): ?>
                <!-- Depreciation Report -->
                <div class="charts-container">
                    <div class="chart-card">
                        <h3>Asset Depreciation Analysis</h3>
                        <div class="chart-container">
                            <canvas id="depreciationChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="chart-card">
                        <h3>Depreciation by Asset Age</h3>
                        <div class="chart-container">
                            <canvas id="ageDepreciationChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="detailed-report">
                    <div class="report-header">
                        <h3>Asset Depreciation Report</h3>
                        <span>As of: <?php echo date('F j, Y'); ?></span>
                    </div>
                    <div class="report-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Asset Code</th>
                                    <th>Asset Name</th>
                                    <th>Purchase Date</th>
                                    <th>Age (Years)</th>
                                    <th>Purchase Cost</th>
                                    <th>Current Value</th>
                                    <th>Depreciation Amount</th>
                                    <th>Depreciation Rate</th>
                                    <th>Annual Depreciation</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_depreciation = 0;
                                foreach($detailed_report as $asset):
                                    $purchase_date = new DateTime($asset['purchase_date']);
                                    $current_date = new DateTime();
                                    $age = $current_date->diff($purchase_date)->y;
                                    
                                    $depreciation = $asset['purchase_cost'] - $asset['current_value'];
                                    $dep_rate = $asset['purchase_cost'] > 0 ? ($depreciation / $asset['purchase_cost']) * 100 : 0;
                                    $annual_dep = $age > 0 ? $depreciation / $age : $depreciation;
                                    
                                    $total_depreciation += $depreciation;
                                ?>
                                <tr>
                                    <td><strong><?php echo $asset['asset_code']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($asset['asset_name']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($asset['purchase_date'])); ?></td>
                                    <td><?php echo $age; ?></td>
                                    <td>Rs. <?php echo number_format($asset['purchase_cost'], 2); ?></td>
                                    <td>Rs. <?php echo number_format($asset['current_value'], 2); ?></td>
                                    <td>Rs. <?php echo number_format($depreciation, 2); ?></td>
                                    <td><?php echo number_format($dep_rate, 2); ?>%</td>
                                    <td>Rs. <?php echo number_format($annual_dep, 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot style="background: #f8f9fa; font-weight: bold;">
                                <tr>
                                    <td colspan="5">Total Depreciation:</td>
                                    <td colspan="4">Rs. <?php echo number_format($total_depreciation, 2); ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Initialize Charts
        document.addEventListener('DOMContentLoaded', function() {
            // Category Distribution Chart
            const categoryCtx = document.getElementById('categoryChart')?.getContext('2d');
            if (categoryCtx) {
                const categoryLabels = <?php echo json_encode(array_column($stats['by_category'], 'category_name')); ?>;
                const categoryData = <?php echo json_encode(array_column($stats['by_category'], 'count')); ?>;
                
                new Chart(categoryCtx, {
                    type: 'pie',
                    data: {
                        labels: categoryLabels,
                        datasets: [{
                            data: categoryData,
                            backgroundColor: [
                                '#4a6491', '#f39c12', '#27ae60', '#e74c3c', '#9b59b6',
                                '#3498db', '#1abc9c', '#d35400', '#2c3e50', '#7f8c8d'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'right'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label || '';
                                        const value = context.raw || 0;
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = Math.round((value / total) * 100);
                                        return `${label}: ${value} (${percentage}%)`;
                                    }
                                }
                            }
                        }
                    }
                });
            }
            
            // Status Distribution Chart
            const statusCtx = document.getElementById('statusChart')?.getContext('2d');
            if (statusCtx) {
                const statusData = {
                    labels: ['Available', 'In Use', 'Under Maintenance', 'Retired', 'Lost'],
                    datasets: [{
                        data: [
                            <?php echo $stats['by_status']['available'] ?? 0; ?>,
                            <?php echo $stats['by_status']['in_use'] ?? 0; ?>,
                            <?php echo $stats['by_status']['under_maintenance'] ?? 0; ?>,
                            <?php echo $stats['by_status']['retired'] ?? 0; ?>,
                            <?php echo $stats['by_status']['lost'] ?? 0; ?>
                        ],
                        backgroundColor: [
                            '#27ae60', '#3498db', '#f39c12', '#95a5a6', '#e74c3c'
                        ]
                    }]
                };
                
                new Chart(statusCtx, {
                    type: 'doughnut',
                    data: statusData,
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            }
            
            // Monthly Acquisitions Chart
            const monthlyCtx = document.getElementById('monthlyChart')?.getContext('2d');
            if (monthlyCtx) {
                const monthlyLabels = <?php echo json_encode(array_column($monthly_data, 'month')); ?>;
                const monthlyCounts = <?php echo json_encode(array_column($monthly_data, 'count')); ?>;
                const monthlyValues = <?php echo json_encode(array_column($monthly_data, 'value')); ?>;
                
                new Chart(monthlyCtx, {
                    type: 'bar',
                    data: {
                        labels: monthlyLabels,
                        datasets: [
                            {
                                label: 'Number of Assets',
                                data: monthlyCounts,
                                backgroundColor: 'rgba(74, 100, 145, 0.7)',
                                borderColor: 'rgba(74, 100, 145, 1)',
                                borderWidth: 1,
                                yAxisID: 'y'
                            },
                            {
                                label: 'Purchase Value (Rs.)',
                                data: monthlyValues,
                                backgroundColor: 'rgba(243, 156, 18, 0.7)',
                                borderColor: 'rgba(243, 156, 18, 1)',
                                borderWidth: 1,
                                type: 'line',
                                yAxisID: 'y1'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                type: 'linear',
                                display: true,
                                position: 'left',
                                title: {
                                    display: true,
                                    text: 'Number of Assets'
                                }
                            },
                            y1: {
                                type: 'linear',
                                display: true,
                                position: 'right',
                                title: {
                                    display: true,
                                    text: 'Purchase Value (Rs.)'
                                },
                                grid: {
                                    drawOnChartArea: false
                                }
                            }
                        }
                    }
                });
            }
            
            // Location Chart
            const locationCtx = document.getElementById('locationChart')?.getContext('2d');
            if (locationCtx) {
                const locationLabels = <?php echo json_encode(array_column($location_data, 'location')); ?>;
                const locationCounts = <?php echo json_encode(array_column($location_data, 'count')); ?>;
                
                new Chart(locationCtx, {
                    type: 'horizontalBar',
                    data: {
                        labels: locationLabels,
                        datasets: [{
                            label: 'Number of Assets',
                            data: locationCounts,
                            backgroundColor: 'rgba(52, 152, 219, 0.7)',
                            borderColor: 'rgba(52, 152, 219, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        indexAxis: 'y',
                        scales: {
                            x: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
            
            // Maintenance Cost Chart
            const maintenanceCostCtx = document.getElementById('maintenanceCostChart')?.getContext('2d');
            if (maintenanceCostCtx) {
                const maintenanceLabels = <?php echo json_encode(array_column($maintenance_monthly, 'month')); ?>;
                const maintenanceCosts = <?php echo json_encode(array_column($maintenance_monthly, 'cost')); ?>;
                
                new Chart(maintenanceCostCtx, {
                    type: 'line',
                    data: {
                        labels: maintenanceLabels,
                        datasets: [{
                            label: 'Maintenance Cost (Rs.)',
                            data: maintenanceCosts,
                            borderColor: '#e74c3c',
                            backgroundColor: 'rgba(231, 76, 60, 0.1)',
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
            }
        });
        
        // Report Type Navigation
        function changeReportType(type) {
            document.getElementById('reportType').value = type;
            document.getElementById('reportFilterForm').submit();
        }
        
        // Filter Functions
        function resetFilters() {
            const today = new Date().toISOString().split('T')[0];
            const firstDay = today.substring(0, 8) + '01';
            
            document.querySelector('input[name="start_date"]').value = firstDay;
            document.querySelector('input[name="end_date"]').value = today;
            document.querySelector('select[name="category"]').value = '';
            document.querySelector('select[name="status"]').value = '';
            document.querySelector('select[name="department"]').value = '';
            
            document.getElementById('reportFilterForm').submit();
        }
        
        // Export Functions
        function showLoading() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        }
        
        function hideLoading() {
            document.getElementById('loadingOverlay').style.display = 'none';
        }
        
        function generatePDF() {
            showLoading();
            
            // In a real implementation, you would generate PDF server-side
            // This is a simplified client-side version
            setTimeout(() => {
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF('landscape');
                
                // Add university header
                doc.setFontSize(20);
                doc.setTextColor(74, 100, 145);
                doc.text('University of Vavuniya', 105, 20, { align: 'center' });
                
                doc.setFontSize(16);
                doc.text('Asset Inventory Report', 105, 30, { align: 'center' });
                
                doc.setFontSize(12);
                doc.setTextColor(0, 0, 0);
                doc.text(`Report Type: ${document.getElementById('reportType').value.toUpperCase()}`, 20, 45);
                doc.text(`Date Range: ${document.querySelector('input[name="start_date"]').value} to ${document.querySelector('input[name="end_date"]').value}`, 20, 55);
                doc.text(`Generated on: ${new Date().toLocaleDateString()}`, 20, 65);
                
                // Add summary table
                const headers = [['Metric', 'Value']];
                const data = [
                    ['Total Assets', '<?php echo number_format($stats['total_assets']); ?>'],
                    ['Total Purchase Value', `Rs. <?php echo number_format($stats['total_purchase_value'], 2); ?>`],
                    ['Total Current Value', `Rs. <?php echo number_format($stats['total_current_value'], 2); ?>`],
                    ['Total Depreciation', `Rs. <?php echo number_format($stats['total_purchase_value'] - $stats['total_current_value'], 2); ?>`],
                    ['Total Maintenance Records', '<?php echo number_format($stats['total_maintenance']); ?>'],
                    ['Total Maintenance Cost', `Rs. <?php echo number_format($stats['total_maintenance_cost'], 2); ?>`]
                ];
                
                doc.autoTable({
                    head: headers,
                    body: data,
                    startY: 75,
                    theme: 'striped',
                    headStyles: { fillColor: [74, 100, 145] }
                });
                
                // Save the PDF
                doc.save(`Asset_Report_${new Date().toISOString().split('T')[0]}.pdf`);
                hideLoading();
            }, 1500);
        }
        
        function exportToExcel() {
            showLoading();
            
            // Collect data for export
            const reportType = document.getElementById('reportType').value;
            const params = new URLSearchParams();
            
            // Add all filter parameters
            const formData = new FormData(document.getElementById('reportFilterForm'));
            for (const [key, value] of formData.entries()) {
                params.append(key, value);
            }
            
            // Redirect to export endpoint
            setTimeout(() => {
                window.open(`api/export_report.php?${params.toString()}`, '_blank');
                hideLoading();
            }, 1000);
        }
        
        function printReport() {
            showLoading();
            
            setTimeout(() => {
                const printContent = document.getElementById('reportContent').innerHTML;
                const originalContent = document.body.innerHTML;
                
                document.body.innerHTML = `
                    <div style="padding: 20px;">
                        <h1 style="text-align: center; color: #4a6491;">
                            University of Vavuniya - Asset Inventory Report
                        </h1>
                        <div style="text-align: center; margin-bottom: 30px;">
                            <p>Report Type: ${document.getElementById('reportType').value.toUpperCase()}</p>
                            <p>Date Range: ${document.querySelector('input[name="start_date"]').value} to ${document.querySelector('input[name="end_date"]').value}</p>
                            <p>Generated on: ${new Date().toLocaleDateString()}</p>
                        </div>
                        ${printContent}
                    </div>
                `;
                
                window.print();
                document.body.innerHTML = originalContent;
                hideLoading();
                location.reload();
            }, 500);
        }
        
        // Auto-refresh charts on window resize
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                // In a real implementation, you would re-render charts
                console.log('Window resized - charts would re-render');
            }, 250);
        });
    </script>
</body>
</html>