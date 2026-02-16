<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$conn = getDBConnection();

// Get assets and users for dropdowns
$assets_result = $conn->query("SELECT asset_id, asset_code, asset_name FROM assets WHERE status != 'retired' ORDER BY asset_code");
$users_result = $conn->query("SELECT user_id, full_name FROM users ORDER BY full_name");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $asset_id = intval($_POST['asset_id']);
    $maintenance_type = sanitizeInput($_POST['maintenance_type']);
    $maintenance_date = $_POST['maintenance_date'];
    $cost = floatval($_POST['cost']);
    $status = sanitizeInput($_POST['status']);
    $assigned_to = !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : NULL;
    $description = sanitizeInput($_POST['description']);
    
    if (empty($asset_id) || empty($maintenance_type) || empty($maintenance_date)) {
        $error = "Asset, maintenance type, and date are required!";
    } else {
        $stmt = $conn->prepare("INSERT INTO maintenance (asset_id, maintenance_type, maintenance_date, 
                              cost, status, assigned_to, description) 
                              VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param("issdsis", $asset_id, $maintenance_type, $maintenance_date, $cost, 
                         $status, $assigned_to, $description);
        
        if ($stmt->execute()) {
            $success = "Maintenance record added successfully!";
            $_POST = [];
        } else {
            $error = "Error adding record: " . $conn->error;
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Maintenance - <?php echo SYSTEM_NAME; ?></title>
    <style>
        :root {
            --primary: #4a90e2;
            --secondary: #2c3e50;
            --success: #27ae60;
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .page-header h1 {
            color: var(--secondary);
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
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: Arial, sans-serif;
            margin-bottom: 15px;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 5px rgba(74, 144, 226, 0.3);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--secondary);
        }
        
        .required::after {
            content: " *";
            color: var(--danger);
        }
        
        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            padding: 30px;
            max-width: 800px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .form-grid .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            padding-top: 20px;
            border-top: 2px solid #eee;
            margin-top: 30px;
        }
        
        .notification {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .notification.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .notification.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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
            <li><a href="maintenance.php" class="active">🔧 Maintenance</a></li>
            <li><a href="logout.php">🚪 Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="page-header">
            <h1>Add Maintenance Record</h1>
            <button class="btn" onclick="location.href='maintenance.php'">← Back</button>
        </div>
        
        <?php if(isset($success)): ?>
            <div class="notification success">
                ✅ <?php echo $success; ?>
                <br><br>
                <button class="btn btn-primary" onclick="location.href='maintenance.php'">View All Records</button>
            </div>
        <?php endif; ?>
        
        <?php if(isset($error)): ?>
            <div class="notification error">
                ❌ <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <form method="POST" action="">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="required">Asset</label>
                        <select name="asset_id" class="form-control" required>
                            <option value="">Select Asset</option>
                            <?php while($asset = $assets_result->fetch_assoc()): ?>
                            <option value="<?php echo $asset['asset_id']; ?>">
                                <?php echo htmlspecialchars($asset['asset_code'] . ' - ' . $asset['asset_name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Maintenance Type</label>
                        <select name="maintenance_type" class="form-control" required>
                            <option value="">Select Type</option>
                            <option value="Preventive">Preventive Maintenance</option>
                            <option value="Corrective">Corrective Maintenance</option>
                            <option value="Inspection">Inspection</option>
                            <option value="Repair">Repair</option>
                            <option value="Parts Replacement">Parts Replacement</option>
                            <option value="Software Update">Software Update</option>
                            <option value="Cleaning">Cleaning & Maintenance</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Maintenance Date</label>
                        <input type="date" name="maintenance_date" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Cost (Rs.)</label>
                        <input type="number" step="0.01" name="cost" class="form-control" value="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Status</label>
                        <select name="status" class="form-control" required>
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Assigned To</label>
                        <select name="assigned_to" class="form-control">
                            <option value="">Unassigned</option>
                            <?php while($user = $users_result->fetch_assoc()): ?>
                            <option value="<?php echo $user['user_id']; ?>">
                                <?php echo htmlspecialchars($user['full_name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group full-width">
                        <label>Description/Notes</label>
                        <textarea name="description" class="form-control" rows="4" placeholder="Add details about the maintenance work..."></textarea>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn" onclick="location.href='maintenance.php'">Cancel</button>
                    <button type="submit" class="btn btn-primary">💾 Save Record</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Set default date to today
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            document.querySelector('input[name="maintenance_date"]').value = today;
        });
    </script>
</body>
</html>
