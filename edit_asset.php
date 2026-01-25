<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$conn = getDBConnection();

// Get asset ID from URL
$asset_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($asset_id == 0) {
    header('Location: assets.php');
    exit();
}

// Fetch asset details
$stmt = $conn->prepare("SELECT * FROM assets WHERE asset_id = ?");
$stmt->bind_param("i", $asset_id);
$stmt->execute();
$result = $stmt->get_result();
$asset = $result->fetch_assoc();

if (!$asset) {
    header('Location: assets.php');
    exit();
}

// Get categories and users
$categories_result = $conn->query("SELECT * FROM asset_categories ORDER BY category_name");
$users_result = $conn->query("SELECT * FROM users ORDER BY full_name");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $asset_name = sanitizeInput($_POST['asset_name']);
    $category_id = intval($_POST['category']);
    $serial_number = sanitizeInput($_POST['serial_number']);
    $model = sanitizeInput($_POST['model']);
    $manufacturer = sanitizeInput($_POST['manufacturer']);
    $purchase_date = $_POST['purchase_date'];
    $purchase_cost = floatval($_POST['purchase_cost']);
    $current_value = floatval($_POST['current_value']);
    $location = sanitizeInput($_POST['location']);
    $status = sanitizeInput($_POST['status']);
    $assigned_to = !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : NULL;
    $warranty_expiry = $_POST['warranty_expiry'];
    $notes = sanitizeInput($_POST['notes']);
    
    $stmt = $conn->prepare("UPDATE assets SET asset_name = ?, category_id = ?, serial_number = ?, 
                          model = ?, manufacturer = ?, purchase_date = ?, purchase_cost = ?, 
                          current_value = ?, location = ?, status = ?, assigned_to = ?, 
                          warranty_expiry = ?, notes = ? WHERE asset_id = ?");
    
    $stmt->bind_param("sissssddssssi", $asset_name, $category_id, $serial_number,
                     $model, $manufacturer, $purchase_date, $purchase_cost, $current_value,
                     $location, $status, $assigned_to, $warranty_expiry, $notes, $asset_id);
    
    if ($stmt->execute()) {
        $success = "Asset updated successfully!";
        $asset['asset_name'] = $asset_name;
        $asset['category_id'] = $category_id;
        $asset['serial_number'] = $serial_number;
        $asset['model'] = $model;
        $asset['manufacturer'] = $manufacturer;
        $asset['purchase_date'] = $purchase_date;
        $asset['purchase_cost'] = $purchase_cost;
        $asset['current_value'] = $current_value;
        $asset['location'] = $location;
        $asset['status'] = $status;
        $asset['assigned_to'] = $assigned_to;
        $asset['warranty_expiry'] = $warranty_expiry;
        $asset['notes'] = $notes;
    } else {
        $error = "Error updating asset: " . $conn->error;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Asset - <?php echo SYSTEM_NAME; ?></title>
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
            <li><a href="assets.php" class="active">📦 Assets</a></li>
            <li><a href="categories.php">📋 Categories</a></li>
            <li><a href="maintenance.php">🔧 Maintenance</a></li>
            <li><a href="reports.php">📈 Reports</a></li>
            <li><a href="logout.php">🚪 Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="page-header">
            <h1>Edit Asset</h1>
            <button class="btn" onclick="location.href='assets.php'">← Back to Assets</button>
        </div>
        
        <?php if(isset($success)): ?>
            <div class="notification success">
                ✅ <?php echo $success; ?>
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
                        <label class="required">Asset Name</label>
                        <input type="text" name="asset_name" class="form-control" 
                               value="<?php echo htmlspecialchars($asset['asset_name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Category</label>
                        <select name="category" class="form-control" required>
                            <option value="">Select Category</option>
                            <?php while($cat = $categories_result->fetch_assoc()): ?>
                            <option value="<?php echo $cat['category_id']; ?>"
                                    <?php echo $cat['category_id'] == $asset['category_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Serial Number</label>
                        <input type="text" name="serial_number" class="form-control"
                               value="<?php echo htmlspecialchars($asset['serial_number']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Model</label>
                        <input type="text" name="model" class="form-control"
                               value="<?php echo htmlspecialchars($asset['model']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Manufacturer</label>
                        <input type="text" name="manufacturer" class="form-control"
                               value="<?php echo htmlspecialchars($asset['manufacturer']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Purchase Date</label>
                        <input type="date" name="purchase_date" class="form-control"
                               value="<?php echo $asset['purchase_date']; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Purchase Cost (Rs.)</label>
                        <input type="number" step="0.01" name="purchase_cost" class="form-control"
                               value="<?php echo $asset['purchase_cost']; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Current Value (Rs.)</label>
                        <input type="number" step="0.01" name="current_value" class="form-control"
                               value="<?php echo $asset['current_value']; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Location</label>
                        <input type="text" name="location" class="form-control"
                               value="<?php echo htmlspecialchars($asset['location']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Status</label>
                        <select name="status" class="form-control" required>
                            <option value="available" <?php echo $asset['status'] == 'available' ? 'selected' : ''; ?>>Available</option>
                            <option value="in_use" <?php echo $asset['status'] == 'in_use' ? 'selected' : ''; ?>>In Use</option>
                            <option value="under_maintenance" <?php echo $asset['status'] == 'under_maintenance' ? 'selected' : ''; ?>>Under Maintenance</option>
                            <option value="retired" <?php echo $asset['status'] == 'retired' ? 'selected' : ''; ?>>Retired</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Assigned To</label>
                        <select name="assigned_to" class="form-control">
                            <option value="">Not Assigned</option>
                            <?php while($user = $users_result->fetch_assoc()): ?>
                            <option value="<?php echo $user['user_id']; ?>"
                                    <?php echo $user['user_id'] == $asset['assigned_to'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo $user['role']; ?>)
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Warranty Expiry Date</label>
                        <input type="date" name="warranty_expiry" class="form-control"
                               value="<?php echo $asset['warranty_expiry']; ?>">
                    </div>
                    
                    <div class="form-group full-width">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="4"><?php echo htmlspecialchars($asset['notes']); ?></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn" onclick="location.href='assets.php'">Cancel</button>
                        <button type="submit" class="btn btn-primary">💾 Update Asset</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</body>
</html>

<?php
if(isset($conn) && $conn) {
    $conn->close();
}
?>
