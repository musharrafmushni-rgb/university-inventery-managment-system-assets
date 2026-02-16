<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$conn = getDBConnection();

// Get categories and users
$categories = $conn->query("SELECT * FROM asset_categories ORDER BY category_name");
$users = $conn->query("SELECT * FROM users ORDER BY full_name");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $asset_code = 'VU-' . strtoupper(substr($_POST['category'], 0, 2)) . '-' . sprintf('%03d', rand(100, 999));
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
    
    $stmt = $conn->prepare("INSERT INTO assets (asset_code, asset_name, category_id, serial_number, 
                          model, manufacturer, purchase_date, purchase_cost, current_value, 
                          location, status, assigned_to, warranty_expiry, notes) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->bind_param("ssisssssdssiss", $asset_code, $asset_name, $category_id, $serial_number,
                     $model, $manufacturer, $purchase_date, $purchase_cost, $current_value,
                     $location, $status, $assigned_to, $warranty_expiry, $notes);
    
    if ($stmt->execute()) {
        $success = "Asset added successfully! Asset Code: $asset_code";
    } else {
        $error = "Error adding asset: " . $conn->error;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Asset - <?php echo SYSTEM_NAME; ?></title>
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
            background: #e0e0e0;
            color: #333;
        }
        
        .btn:hover {
            background: #d0d0d0;
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
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: Arial, sans-serif;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 5px rgba(74, 144, 226, 0.3);
        }
        
        .form-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-actions {
            grid-column: 1 / -1;
            text-align: right;
            padding-top: 20px;
            border-top: 2px solid #eee;
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
        
        .required::after {
            content: " *";
            color: var(--danger);
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
            <li><a href="add_asset.php" class="active">➕ Add Asset</a></li>
            <li><a href="logout.php">🚪 Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="page-header">
            <h1>Add New Asset</h1>
            <button class="btn" onclick="location.href='assets.php'">← Back to Assets</button>
        </div>
        
        <?php if(isset($success)): ?>
            <div class="notification success">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if(isset($error)): ?>
            <div class="notification error">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="POST" action="">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="required">Asset Name</label>
                        <input type="text" name="asset_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Category</label>
                        <select name="category" class="form-control" required>
                            <option value="">Select Category</option>
                            <?php while($cat = $categories->fetch_assoc()): ?>
                            <option value="<?php echo $cat['category_id']; ?>">
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Serial Number</label>
                        <input type="text" name="serial_number" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label>Model</label>
                        <input type="text" name="model" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label>Manufacturer</label>
                        <input type="text" name="manufacturer" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Purchase Date</label>
                        <input type="date" name="purchase_date" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Purchase Cost (Rs.)</label>
                        <input type="number" step="0.01" name="purchase_cost" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Current Value (Rs.)</label>
                        <input type="number" step="0.01" name="current_value" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Location</label>
                        <input type="text" name="location" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Status</label>
                        <select name="status" class="form-control" required>
                            <option value="available">Available</option>
                            <option value="in_use">In Use</option>
                            <option value="under_maintenance">Under Maintenance</option>
                            <option value="retired">Retired</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Assigned To</label>
                        <select name="assigned_to" class="form-control">
                            <option value="">Not Assigned</option>
                            <?php while($user = $users->fetch_assoc()): ?>
                            <option value="<?php echo $user['user_id']; ?>">
                                <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo $user['role']; ?>)
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Warranty Expiry Date</label>
                        <input type="date" name="warranty_expiry" class="form-control">
                    </div>
                    
                    <div class="form-group full-width">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control"></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="reset" class="btn">🔄 Reset</button>
                        <button type="submit" class="btn btn-primary">💾 Save Asset</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Auto-calculate current value based on purchase cost
        document.querySelector('input[name="purchase_cost"]').addEventListener('input', function() {
            const purchaseCost = parseFloat(this.value) || 0;
            const currentValueInput = document.querySelector('input[name="current_value"]');
            if (!currentValueInput.value) {
                currentValueInput.value = purchaseCost.toFixed(2);
            }
        });
        
        // Set default dates
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            document.querySelector('input[name="purchase_date"]').value = today;
            
            // Set warranty expiry to 1 year from now
            const nextYear = new Date();
            nextYear.setFullYear(nextYear.getFullYear() + 1);
            document.querySelector('input[name="warranty_expiry"]').value = 
                nextYear.toISOString().split('T')[0];
        });
    </script>
</body>
</html>