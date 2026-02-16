<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$conn = getDBConnection();

// Get asset cost data
$asset_query = "SELECT asset_id, asset_code, asset_name, purchase_cost, current_value, 
               (purchase_cost - current_value) as depreciation 
               FROM assets ORDER BY purchase_cost DESC";
$asset_result = $conn->query($asset_query);
$assets = [];
while ($row = $asset_result->fetch_assoc()) {
    $assets[] = $row;
}

// Calculate cost statistics
$total_purchase = 0;
$total_current = 0;
$total_depreciation = 0;

foreach($assets as $asset) {
    $total_purchase += $asset['purchase_cost'];
    $total_current += $asset['current_value'];
    $total_depreciation += $asset['depreciation'];
}

// Get maintenance cost by asset
$maint_query = "SELECT a.asset_id, a.asset_code, a.asset_name, 
               SUM(m.cost) as maintenance_cost, COUNT(m.maintenance_id) as maintenance_count
               FROM assets a 
               LEFT JOIN maintenance m ON a.asset_id = m.asset_id 
               GROUP BY a.asset_id, a.asset_code, a.asset_name
               ORDER BY maintenance_cost DESC";
$maint_result = $conn->query($maint_query);
$maintenance_costs = [];
$total_maintenance = 0;
while ($row = $maint_result->fetch_assoc()) {
    $maintenance_costs[] = $row;
    $total_maintenance += $row['maintenance_cost'] ?? 0;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cost Analysis Report - <?php echo SYSTEM_NAME; ?></title>
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
        
        .page-header {
            color: var(--secondary);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .page-header h1 {
            font-size: 32px;
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
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border-left: 5px solid var(--primary);
        }
        
        .summary-card.success { border-left-color: var(--success); }
        .summary-card.danger { border-left-color: var(--danger); }
        .summary-card.warning { border-left-color: var(--warning); }
        
        .card-label {
            color: #999;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 10px;
        }
        
        .card-value {
            font-size: 28px;
            font-weight: bold;
            color: var(--secondary);
        }
        
        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 30px;
            overflow-x: auto;
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
        
        @media print {
            .sidebar {
                display: none;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .btn {
                display: none;
            }
            
            .page-header {
                border-bottom: 2px solid #e0e0e0;
                padding-bottom: 20px;
            }
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
            <li><a href="assets.php">📦 Assets</a></li>
            <li><a href="categories.php">📋 Categories</a></li>
            <li><a href="maintenance.php">🔧 Maintenance</a></li>
            <li><a href="reports.php" class="active">📈 Reports</a></li>
            <li><a href="logout.php">🚪 Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="page-header">
            <div>
                <h1>💰 Cost Analysis Report</h1>
                <p style="color: #666; margin-top: 5px;">Generated on <?php echo date('F d, Y H:i A'); ?></p>
            </div>
            <div>
                <button class="btn btn-secondary" onclick="window.print()">🖨️ Print</button>
                <button class="btn btn-primary" onclick="location.href='reports.php'">← Back</button>
            </div>
        </div>
        
        <!-- Summary Cards -->
        <div class="summary-cards">
            <div class="summary-card success">
                <div class="card-label">Total Purchase Cost</div>
                <div class="card-value">Rs. <?php echo number_format($total_purchase, 0); ?></div>
            </div>
            
            <div class="summary-card">
                <div class="card-label">Total Current Value</div>
                <div class="card-value">Rs. <?php echo number_format($total_current, 0); ?></div>
            </div>
            
            <div class="summary-card danger">
                <div class="card-label">Total Depreciation</div>
                <div class="card-value">Rs. <?php echo number_format($total_depreciation, 0); ?></div>
            </div>
            
            <div class="summary-card warning">
                <div class="card-label">Total Maintenance Cost</div>
                <div class="card-value">Rs. <?php echo number_format($total_maintenance, 0); ?></div>
            </div>
        </div>
        
        <!-- Asset Costs Table -->
        <div class="card">
            <h2 style="margin-bottom: 20px; color: var(--secondary);">Asset Cost Breakdown</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Asset Code</th>
                        <th>Asset Name</th>
                        <th>Purchase Cost (Rs.)</th>
                        <th>Current Value (Rs.)</th>
                        <th>Depreciation (Rs.)</th>
                        <th>Depreciation %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($assets as $asset): 
                        $depr_percent = $asset['purchase_cost'] > 0 ? round(($asset['depreciation'] / $asset['purchase_cost']) * 100, 1) : 0;
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($asset['asset_code']); ?></strong></td>
                        <td><?php echo htmlspecialchars($asset['asset_name']); ?></td>
                        <td style="text-align: right;"><?php echo number_format($asset['purchase_cost'], 2); ?></td>
                        <td style="text-align: right;"><?php echo number_format($asset['current_value'], 2); ?></td>
                        <td style="text-align: right;"><?php echo number_format($asset['depreciation'], 2); ?></td>
                        <td style="text-align: right;"><?php echo $depr_percent; ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Maintenance Costs Table -->
        <div class="card">
            <h2 style="margin-bottom: 20px; color: var(--secondary);">Maintenance Costs by Asset</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Asset Code</th>
                        <th>Asset Name</th>
                        <th>Maintenance Count</th>
                        <th>Total Maintenance Cost (Rs.)</th>
                        <th>Average Cost per Maintenance (Rs.)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($maintenance_costs as $item): 
                        $avg_cost = $item['maintenance_count'] > 0 ? ($item['maintenance_cost'] ?? 0) / $item['maintenance_count'] : 0;
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($item['asset_code']); ?></strong></td>
                        <td><?php echo htmlspecialchars($item['asset_name']); ?></td>
                        <td><?php echo $item['maintenance_count']; ?></td>
                        <td style="text-align: right;"><?php echo number_format($item['maintenance_cost'] ?? 0, 2); ?></td>
                        <td style="text-align: right;"><?php echo number_format($avg_cost, 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
