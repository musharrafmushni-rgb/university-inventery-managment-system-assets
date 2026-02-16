<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$conn = getDBConnection();

// Get statistics
$stats = [];
$result = $conn->query("SELECT COUNT(*) as total FROM assets");
$stats['total_assets'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as available FROM assets WHERE status = 'available'");
$stats['available_assets'] = $result->fetch_assoc()['available'];

$result = $conn->query("SELECT COUNT(*) as in_use FROM assets WHERE status = 'in_use'");
$stats['in_use_assets'] = $result->fetch_assoc()['in_use'];

$result = $conn->query("SELECT COUNT(*) as maintenance FROM assets WHERE status = 'under_maintenance'");
$stats['maintenance_assets'] = $result->fetch_assoc()['maintenance'];

$result = $conn->query("SELECT SUM(purchase_cost) as total_value FROM assets");
$stats['total_value'] = $result->fetch_assoc()['total_value'] ?? 0;

// Get recent assets
$recent_assets = [];
$result = $conn->query("SELECT a.*, c.category_name, u.full_name 
                        FROM assets a 
                        LEFT JOIN asset_categories c ON a.category_id = c.category_id
                        LEFT JOIN users u ON a.assigned_to = u.user_id
                        ORDER BY a.created_at DESC LIMIT 10");
while ($row = $result->fetch_assoc()) {
    $recent_assets[] = $row;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo SYSTEM_NAME; ?></title>
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
            display: flex;
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
            transition: all 0.3s;
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
        
        .sidebar-header p {
            font-size: 13px;
            opacity: 0.8;
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
        
        .user-details h4 {
            font-size: 16px;
        }
        
        .user-details span {
            font-size: 12px;
            opacity: 0.8;
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
            flex: 1;
            margin-left: 250px;
            padding: 20px;
        }
        
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .welcome-text h1 {
            color: var(--secondary);
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        .welcome-text p {
            color: #666;
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
            border-top: 4px solid var(--primary);
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
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
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
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -250px;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
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
            <a href="dashboard.php" class="nav-item active">
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
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="welcome-text">
                <h1>Welcome back, <?php echo $_SESSION['full_name']; ?>!</h1>
                <p><?php echo date('l, F j, Y'); ?></p>
            </div>
            <div class="quick-actions">
                <button class="btn btn-primary" onclick="location.href='add_asset.php'">
                    ➕ Add New Asset
                </button>
            </div>
        </div>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Assets</div>
                <div class="stat-value"><?php echo number_format($stats['total_assets']); ?></div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-label">Available Assets</div>
                <div class="stat-value"><?php echo number_format($stats['available_assets']); ?></div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-label">In Use Assets</div>
                <div class="stat-value"><?php echo number_format($stats['in_use_assets']); ?></div>
            </div>
            
            <div class="stat-card danger">
                <div class="stat-label">Under Maintenance</div>
                <div class="stat-value"><?php echo number_format($stats['maintenance_assets']); ?></div>
            </div>
            
            <div class="stat-card info">
                <div class="stat-label">Total Asset Value</div>
                <div class="stat-value">Rs. <?php echo number_format($stats['total_value'], 2); ?></div>
            </div>
        </div>
        
        <!-- Recent Assets -->
        <div class="card">
            <div class="card-header">
                <h3>Recently Added Assets</h3>
                <a href="assets.php" class="btn btn-text">View All →</a>
            </div>
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Asset Code</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Assigned To</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($recent_assets as $asset): ?>
                        <tr>
                            <td><?php echo $asset['asset_code']; ?></td>
                            <td><?php echo $asset['asset_name']; ?></td>
                            <td><?php echo $asset['category_name']; ?></td>
                            <td><?php echo $asset['location']; ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $asset['status']; ?>">
                                    <?php echo str_replace('_', ' ', ucfirst($asset['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo $asset['full_name'] ?? 'Not Assigned'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        // Simple JavaScript for interactivity
        document.addEventListener('DOMContentLoaded', function() {
            // Add click effect to buttons
            const buttons = document.querySelectorAll('button, .nav-item');
            buttons.forEach(button => {
                button.addEventListener('click', function(e) {
                    this.style.transform = 'scale(0.98)';
                    setTimeout(() => {
                        this.style.transform = '';
                    }, 150);
                });
            });
            
            // Auto-refresh stats every 30 seconds
            setInterval(() => {
                fetch('api/get_stats.php')
                    .then(response => response.json())
                    .then(data => {
                        // Update stats here
                        console.log('Stats updated');
                    });
            }, 30000);
        });
    </script>
</body>
</html>