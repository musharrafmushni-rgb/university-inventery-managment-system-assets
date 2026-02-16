<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$conn = getDBConnection();

// Get all categories
$query = "SELECT * FROM asset_categories ORDER BY category_name";
$result = $conn->query($query);
$categories = [];
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset Categories - <?php echo SYSTEM_NAME; ?></title>
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
            flex-wrap: wrap;
            gap: 20px;
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
        
        .btn-warning {
            background: var(--warning);
            color: white;
            padding: 5px 10px;
            font-size: 12px;
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
            padding: 5px 10px;
            font-size: 12px;
        }
        
        .btn-warning:hover {
            opacity: 0.9;
        }
        
        .btn-danger:hover {
            opacity: 0.9;
        }
        
        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            overflow: hidden;
            margin-bottom: 30px;
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
        
        .actions-cell {
            display: flex;
            gap: 5px;
        }
        
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #999;
        }
        
        .empty-state h3 {
            color: var(--secondary);
            margin-bottom: 10px;
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
            <h1>Asset Categories</h1>
            <div>
                <button class="btn btn-primary" onclick="location.href='add_category.php'">
                    ➕ Add New Category
                </button>
            </div>
        </div>
        
        <div class="card">
            <div class="card-body">
                <?php if (count($categories) > 0): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Category Name</th>
                                <th>Description</th>
                                <th>Assets Count</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($categories as $category): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($category['category_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($category['description'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php 
                                    $conn = getDBConnection();
                                    $count_result = $conn->query("SELECT COUNT(*) as count FROM assets WHERE category_id = " . intval($category['category_id']));
                                    $count_row = $count_result->fetch_assoc();
                                    echo $count_row['count'];
                                    $conn->close();
                                    ?>
                                </td>
                                <td class="actions-cell">
                                    <button class="btn btn-warning"
                                            onclick="location.href='edit_category.php?id=<?php echo $category['category_id']; ?>'">
                                        ✏️ Edit
                                    </button>
                                    <button class="btn btn-danger"
                                            onclick="deleteCategory(<?php echo $category['category_id']; ?>)">
                                        🗑️ Delete
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <h3>No Categories Found</h3>
                        <p>Create your first category to get started.</p>
                        <br>
                        <button class="btn btn-primary" onclick="location.href='add_category.php'">
                            ➕ Add New Category
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        function deleteCategory(categoryId) {
            if (confirm('Are you sure you want to delete this category? Assets in this category will not be deleted.')) {
                fetch(`api/delete_category.php?id=${categoryId}`, {
                    method: 'DELETE'
                })
                .then(response => {
                    if (response.ok) {
                        alert('Category deleted successfully!');
                        location.reload();
                    } else {
                        alert('Error deleting category');
                    }
                })
                .catch(error => {
                    alert('Error: ' + error);
                });
            }
        }
    </script>
</body>
</html>
