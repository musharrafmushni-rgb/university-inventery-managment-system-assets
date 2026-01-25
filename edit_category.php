<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$conn = getDBConnection();

// Get category ID from URL
$category_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($category_id == 0) {
    header('Location: categories.php');
    exit();
}

// Fetch category details
$stmt = $conn->prepare("SELECT * FROM asset_categories WHERE category_id = ?");
$stmt->bind_param("i", $category_id);
$stmt->execute();
$result = $stmt->get_result();
$category = $result->fetch_assoc();

if (!$category) {
    header('Location: categories.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $category_name = sanitizeInput($_POST['category_name']);
    $description = sanitizeInput($_POST['description']);
    
    if (empty($category_name)) {
        $error = "Category name is required!";
    } else {
        // Check if category name already exists (excluding current category)
        $check = $conn->prepare("SELECT * FROM asset_categories WHERE category_name = ? AND category_id != ?");
        $check->bind_param("si", $category_name, $category_id);
        $check->execute();
        $result = $check->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Category name already exists!";
        } else {
            $update_stmt = $conn->prepare("UPDATE asset_categories SET category_name = ?, description = ? WHERE category_id = ?");
            $update_stmt->bind_param("ssi", $category_name, $description, $category_id);
            
            if ($update_stmt->execute()) {
                $success = "Category updated successfully!";
                $category['category_name'] = $category_name;
                $category['description'] = $description;
            } else {
                $error = "Error updating category: " . $conn->error;
            }
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
    <title>Edit Category - <?php echo SYSTEM_NAME; ?></title>
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
            max-width: 600px;
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
            <li><a href="categories.php" class="active">📋 Categories</a></li>
            <li><a href="logout.php">🚪 Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="page-header">
            <h1>Edit Category</h1>
            <button class="btn" onclick="location.href='categories.php'">← Back</button>
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
                <div class="form-group">
                    <label class="required">Category Name</label>
                    <input type="text" name="category_name" class="form-control" 
                           value="<?php echo htmlspecialchars($category['category_name']); ?>"
                           required placeholder="e.g., Laptops, Furniture, etc.">
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" 
                              rows="4" placeholder="Add a description for this category..."><?php echo htmlspecialchars($category['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn" onclick="location.href='categories.php'">Cancel</button>
                    <button type="submit" class="btn btn-primary">💾 Update Category</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
