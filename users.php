<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Check if user is admin
if ($_SESSION['role'] != 'admin') {
    header('Location: dashboard.php');
    exit();
}

$conn = getDBConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = sanitizeInput($_POST['action']);
        
        switch ($action) {
            case 'add_user':
                $username = sanitizeInput($_POST['username']);
                $password = $_POST['password'];
                $confirm_password = $_POST['confirm_password'];
                $email = sanitizeInput($_POST['email']);
                $full_name = sanitizeInput($_POST['full_name']);
                $role = sanitizeInput($_POST['role']);
                $department = sanitizeInput($_POST['department']);
                
                // Check if passwords match
                if ($password !== $confirm_password) {
                    $error = "Passwords do not match!";
                    break;
                }
                
                // Check if username exists
                $check_stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
                $check_stmt->bind_param("s", $username);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    $error = "Username already exists!";
                    break;
                }
                
                // Check if email exists
                $check_stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
                $check_stmt->bind_param("s", $email);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    $error = "Email already exists!";
                    break;
                }
                
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert new user
                $stmt = $conn->prepare("INSERT INTO users (username, password, email, full_name, role, department) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssss", $username, $hashed_password, $email, $full_name, $role, $department);
                
                if ($stmt->execute()) {
                    $success = "User added successfully!";
                } else {
                    $error = "Error adding user: " . $conn->error;
                }
                break;
                
            case 'edit_user':
                $user_id = intval($_POST['user_id']);
                $username = sanitizeInput($_POST['username']);
                $email = sanitizeInput($_POST['email']);
                $full_name = sanitizeInput($_POST['full_name']);
                $role = sanitizeInput($_POST['role']);
                $department = sanitizeInput($_POST['department']);
                $password = $_POST['password'];
                
                // Check if username exists for other users
                $check_stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
                $check_stmt->bind_param("si", $username, $user_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    $error = "Username already exists!";
                    break;
                }
                
                // Check if email exists for other users
                $check_stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
                $check_stmt->bind_param("si", $email, $user_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    $error = "Email already exists!";
                    break;
                }
                
                // Update user
                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET username = ?, password = ?, email = ?, full_name = ?, role = ?, department = ? WHERE user_id = ?");
                    $stmt->bind_param("ssssssi", $username, $hashed_password, $email, $full_name, $role, $department, $user_id);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, full_name = ?, role = ?, department = ? WHERE user_id = ?");
                    $stmt->bind_param("sssssi", $username, $email, $full_name, $role, $department, $user_id);
                }
                
                if ($stmt->execute()) {
                    $success = "User updated successfully!";
                } else {
                    $error = "Error updating user: " . $conn->error;
                }
                break;
                
            case 'delete_user':
                $user_id = intval($_POST['user_id']);
                
                // Check if user is trying to delete themselves
                if ($user_id == $_SESSION['user_id']) {
                    $error = "You cannot delete your own account!";
                    break;
                }
                
                // Check if user has any assets assigned
                $check_stmt = $conn->prepare("SELECT COUNT(*) as asset_count FROM assets WHERE assigned_to = ?");
                $check_stmt->bind_param("i", $user_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                $asset_count = $check_result->fetch_assoc()['asset_count'];
                
                if ($asset_count > 0) {
                    $error = "Cannot delete user. User has $asset_count assets assigned. Reassign assets first.";
                    break;
                }
                
                // Delete user
                $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                
                if ($stmt->execute()) {
                    $success = "User deleted successfully!";
                } else {
                    $error = "Error deleting user: " . $conn->error;
                }
                break;
        }
    }
}

// Get filter parameters
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$role = isset($_GET['role']) ? sanitizeInput($_GET['role']) : '';
$department = isset($_GET['department']) ? sanitizeInput($_GET['department']) : '';

// Build query
$query = "SELECT * FROM users WHERE 1=1";
$params = [];
$types = '';

if (!empty($search)) {
    $query .= " AND (username LIKE ? OR full_name LIKE ? OR email LIKE ? OR department LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'ssss';
}

if (!empty($role)) {
    $query .= " AND role = ?";
    $params[] = $role;
    $types .= 's';
}

if (!empty($department)) {
    $query .= " AND department = ?";
    $params[] = $department;
    $types .= 's';
}

$query .= " ORDER BY created_at DESC";

// Prepare statement
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get unique departments for filter
$dept_result = $conn->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != '' ORDER BY department");
$departments = [];
while ($row = $dept_result->fetch_assoc()) {
    $departments[] = $row['department'];
}

// Get user statistics
$stats_result = $conn->query("SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_count,
    SUM(CASE WHEN role = 'staff' THEN 1 ELSE 0 END) as staff_count,
    SUM(CASE WHEN role = 'faculty' THEN 1 ELSE 0 END) as faculty_count,
    SUM(CASE WHEN role = 'technician' THEN 1 ELSE 0 END) as technician_count
    FROM users");
$stats = $stats_result->fetch_assoc();

// Get recent users
$recent_users_result = $conn->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");
$recent_users = [];
while ($row = $recent_users_result->fetch_assoc()) {
    $recent_users[] = $row;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - <?php echo SYSTEM_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border-top: 4px solid var(--primary);
            transition: transform 0.3s;
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
            font-size: 32px;
            margin-bottom: 10px;
            opacity: 0.8;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: bold;
            color: var(--secondary);
            margin: 5px 0;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
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
            gap: 15px;
            margin-bottom: 15px;
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
        
        /* Users Table */
        .users-table {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .table-header {
            padding: 20px;
            background: var(--light);
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-header h3 {
            color: var(--secondary);
        }
        
        .table-body {
            padding: 0;
            overflow-x: auto;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th, .table td {
            padding: 15px;
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
        .role-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .role-admin {
            background: #4a6491;
            color: white;
        }
        
        .role-staff {
            background: #3498db;
            color: white;
        }
        
        .role-faculty {
            background: #9b59b6;
            color: white;
        }
        
        .role-technician {
            background: #f39c12;
            color: white;
        }
        
        /* Action Buttons */
        .actions-cell {
            display: flex;
            gap: 5px;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 6px;
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
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
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
        
        /* Form Groups */
        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--secondary);
        }
        
        .form-group .required::after {
            content: " *";
            color: var(--danger);
        }
        
        /* Notifications */
        .notification {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
        
        /* User Avatar in Table */
        .user-cell {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .table-avatar {
            width: 36px;
            height: 36px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
        }
        
        /* Password Toggle */
        .password-toggle {
            position: relative;
        }
        
        .password-toggle .toggle-icon {
            position: absolute;
            right: 15px;
            top: 40px;
            cursor: pointer;
            color: #666;
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
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .filter-grid {
                grid-template-columns: 1fr;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
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
            <a href="reports.php" class="nav-item">
                📈 Reports
            </a>
            <a href="users.php" class="nav-item active">
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
            <h1><i class="fas fa-users"></i> User Management</h1>
            <button class="btn btn-primary" onclick="openAddUserModal()">
                <i class="fas fa-user-plus"></i> Add New User
            </button>
        </div>
        
        <!-- Notifications -->
        <?php if(isset($success)): ?>
            <div class="notification success">
                <span><?php echo $success; ?></span>
                <button onclick="this.parentElement.style.display='none'">&times;</button>
            </div>
        <?php endif; ?>
        
        <?php if(isset($error)): ?>
            <div class="notification error">
                <span><?php echo $error; ?></span>
                <button onclick="this.parentElement.style.display='none'">&times;</button>
            </div>
        <?php endif; ?>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users text-primary"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-icon">
                    <i class="fas fa-user-shield text-success"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['admin_count']); ?></div>
                <div class="stat-label">Administrators</div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-icon">
                    <i class="fas fa-user-tie text-warning"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['faculty_count']); ?></div>
                <div class="stat-label">Faculty Members</div>
            </div>
            
            <div class="stat-card info">
                <div class="stat-icon">
                    <i class="fas fa-user-cog text-info"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['technician_count']); ?></div>
                <div class="stat-label">Technicians</div>
            </div>
        </div>
        
        <!-- Filter Section -->
        <div class="filter-section">
            <h3 style="margin-bottom: 20px;">Filter Users</h3>
            <form method="GET" action="">
                <div class="filter-grid">
                    <div>
                        <label>Search</label>
                        <input type="text" name="search" class="form-control" 
                               placeholder="Search by name, username, or email" 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div>
                        <label>Role</label>
                        <select name="role" class="form-control">
                            <option value="">All Roles</option>
                            <option value="admin" <?php echo $role == 'admin' ? 'selected' : ''; ?>>Administrator</option>
                            <option value="staff" <?php echo $role == 'staff' ? 'selected' : ''; ?>>Staff</option>
                            <option value="faculty" <?php echo $role == 'faculty' ? 'selected' : ''; ?>>Faculty</option>
                            <option value="technician" <?php echo $role == 'technician' ? 'selected' : ''; ?>>Technician</option>
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
                        <i class="fas fa-redo"></i> Reset
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Recent Users (Quick View) -->
        <div class="users-table">
            <div class="table-header">
                <h3><i class="fas fa-history"></i> Recently Added Users</h3>
                <span><?php echo count($recent_users); ?> users</span>
            </div>
            <div class="table-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Department</th>
                            <th>Joined Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($recent_users as $user): ?>
                        <tr>
                            <td>
                                <div class="user-cell">
                                    <div class="table-avatar" style="background: <?php echo getAvatarColor($user['user_id']); ?>">
                                        <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="role-badge role-<?php echo $user['role']; ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($user['department']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                            <td class="actions-cell">
                                <button class="btn btn-sm btn-primary" onclick="viewUser(<?php echo $user['user_id']; ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-warning" onclick="editUser(<?php echo $user['user_id']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if($user['user_id'] != $_SESSION['user_id']): ?>
                                <button class="btn btn-sm btn-danger" onclick="deleteUser(<?php echo $user['user_id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- All Users Table -->
        <div class="users-table">
            <div class="table-header">
                <h3><i class="fas fa-users-cog"></i> All Users (<?php echo $result->num_rows; ?>)</h3>
                <button class="btn btn-success" onclick="exportUsers()">
                    <i class="fas fa-file-export"></i> Export Users
                </button>
            </div>
            <div class="table-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Department</th>
                            <th>Joined Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($user = $result->fetch_assoc()): ?>
                        <tr>
                            <td>#<?php echo $user['user_id']; ?></td>
                            <td>
                                <div class="user-cell">
                                    <div class="table-avatar" style="background: <?php echo getAvatarColor($user['user_id']); ?>">
                                        <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="role-badge role-<?php echo $user['role']; ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($user['department']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                            <td class="actions-cell">
                                <button class="btn btn-sm btn-primary" onclick="viewUser(<?php echo $user['user_id']; ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-warning" onclick="editUser(<?php echo $user['user_id']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if($user['user_id'] != $_SESSION['user_id']): ?>
                                <button class="btn btn-sm btn-danger" onclick="deleteUser(<?php echo $user['user_id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Add User Modal -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New User</h3>
                <button class="close-modal" onclick="closeModal('addUserModal')">&times;</button>
            </div>
            <form id="addUserForm" method="POST" action="">
                <input type="hidden" name="action" value="add_user">
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="required">Full Name</label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Role</label>
                        <select name="role" class="form-control" required>
                            <option value="">Select Role</option>
                            <option value="admin">Administrator</option>
                            <option value="staff">Staff</option>
                            <option value="faculty">Faculty</option>
                            <option value="technician">Technician</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Department</label>
                        <input type="text" name="department" class="form-control">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group password-toggle">
                        <label class="required">Password</label>
                        <input type="password" name="password" id="newPassword" class="form-control" required>
                        <span class="toggle-icon" onclick="togglePassword('newPassword', this)">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                    
                    <div class="form-group password-toggle">
                        <label class="required">Confirm Password</label>
                        <input type="password" name="confirm_password" id="confirmPassword" class="form-control" required>
                        <span class="toggle-icon" onclick="togglePassword('confirmPassword', this)">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                </div>
                
                <div style="text-align: right; margin-top: 30px;">
                    <button type="button" class="btn" onclick="closeModal('addUserModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit User</h3>
                <button class="close-modal" onclick="closeModal('editUserModal')">&times;</button>
            </div>
            <form id="editUserForm" method="POST" action="">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="user_id" id="edit_user_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="required">Full Name</label>
                        <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Username</label>
                        <input type="text" name="username" id="edit_username" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Email</label>
                        <input type="email" name="email" id="edit_email" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Role</label>
                        <select name="role" id="edit_role" class="form-control" required>
                            <option value="">Select Role</option>
                            <option value="admin">Administrator</option>
                            <option value="staff">Staff</option>
                            <option value="faculty">Faculty</option>
                            <option value="technician">Technician</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Department</label>
                        <input type="text" name="department" id="edit_department" class="form-control">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Change Password (Leave blank to keep current)</label>
                    <div class="password-toggle">
                        <input type="password" name="password" id="edit_password" class="form-control">
                        <span class="toggle-icon" onclick="togglePassword('edit_password', this)">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                </div>
                
                <div style="text-align: right; margin-top: 30px;">
                    <button type="button" class="btn" onclick="closeModal('editUserModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update User</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- View User Modal -->
    <div id="viewUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>User Details</h3>
                <button class="close-modal" onclick="closeModal('viewUserModal')">&times;</button>
            </div>
            <div id="userDetails"></div>
        </div>
    </div>
    
    <script>
        // Helper function for avatar colors
        function getAvatarColor(id) {
            const colors = [
                '#4a6491', '#f39c12', '#27ae60', '#e74c3c', '#9b59b6',
                '#3498db', '#1abc9c', '#d35400', '#2c3e50', '#7f8c8d'
            ];
            return colors[id % colors.length];
        }
        
        // Modal Functions
        function openAddUserModal() {
            document.getElementById('addUserModal').style.display = 'block';
            document.getElementById('addUserForm').reset();
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Toggle Password Visibility
        function togglePassword(inputId, icon) {
            const input = document.getElementById(inputId);
            const iconElement = icon.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                iconElement.classList.remove('fa-eye');
                iconElement.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                iconElement.classList.remove('fa-eye-slash');
                iconElement.classList.add('fa-eye');
            }
        }
        
        // View User Details
        function viewUser(userId) {
            fetch(`api/get_user.php?id=${userId}`)
                .then(response => response.json())
                .then(data => {
                    const detailsDiv = document.getElementById('userDetails');
                    detailsDiv.innerHTML = `
                        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 30px; margin-bottom: 25px;">
                            <div style="text-align: center;">
                                <div style="width: 100px; height: 100px; background: ${getAvatarColor(data.user_id)}; 
                                     color: white; border-radius: 50%; display: flex; align-items: center; 
                                     justify-content: center; font-size: 36px; font-weight: bold; margin: 0 auto 15px;">
                                    ${data.full_name.charAt(0).toUpperCase()}
                                </div>
                                <h3 style="margin-bottom: 5px;">${data.full_name}</h3>
                                <span class="role-badge role-${data.role}">
                                    ${data.role.charAt(0).toUpperCase() + data.role.slice(1)}
                                </span>
                            </div>
                            
                            <div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                    <div>
                                        <p><strong>Username:</strong> ${data.username}</p>
                                        <p><strong>Email:</strong> ${data.email}</p>
                                        <p><strong>Department:</strong> ${data.department || 'Not specified'}</p>
                                    </div>
                                    <div>
                                        <p><strong>User ID:</strong> #${data.user_id}</p>
                                        <p><strong>Joined Date:</strong> ${new Date(data.created_at).toLocaleDateString('en-US', { 
                                            year: 'numeric', 
                                            month: 'long', 
                                            day: 'numeric' 
                                        })}</p>
                                        <p><strong>Account Status:</strong> <span style="color: green;">Active</span></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div style="margin-top: 20px;">
                            <h4 style="margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #eee;">User Statistics</h4>
                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                                    <p style="margin: 0; font-weight: bold; color: #4a6491;">Assets Assigned</p>
                                    <p style="margin: 5px 0 0; font-size: 24px; font-weight: bold;">${data.asset_count || 0}</p>
                                </div>
                                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                                    <p style="margin: 0; font-weight: bold; color: #27ae60;">Maintenance Records</p>
                                    <p style="margin: 5px 0 0; font-size: 24px; font-weight: bold;">${data.maintenance_count || 0}</p>
                                </div>
                            </div>
                        </div>
                        
                        <div style="margin-top: 30px; text-align: center;">
                            <button class="btn btn-warning" onclick="editUser(${data.user_id})">
                                <i class="fas fa-edit"></i> Edit User
                            </button>
                            ${data.user_id != <?php echo $_SESSION['user_id']; ?> ? `
                            <button class="btn btn-danger" onclick="deleteUser(${data.user_id})">
                                <i class="fas fa-trash"></i> Delete User
                            </button>
                            ` : ''}
                        </div>
                    `;
                    
                    document.getElementById('viewUserModal').style.display = 'block';
                })
                .catch(error => {
                    alert('Error loading user details: ' + error);
                });
        }
        
        // Edit User
        function editUser(userId) {
            fetch(`api/get_user.php?id=${userId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('edit_user_id').value = data.user_id;
                    document.getElementById('edit_full_name').value = data.full_name;
                    document.getElementById('edit_username').value = data.username;
                    document.getElementById('edit_email').value = data.email;
                    document.getElementById('edit_role').value = data.role;
                    document.getElementById('edit_department').value = data.department || '';
                    
                    document.getElementById('editUserModal').style.display = 'block';
                })
                .catch(error => {
                    alert('Error loading user data: ' + error);
                });
        }
        
        // Delete User
        function deleteUser(userId) {
            if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_user';
                form.appendChild(actionInput);
                
                const userIdInput = document.createElement('input');
                userIdInput.type = 'hidden';
                userIdInput.name = 'user_id';
                userIdInput.value = userId;
                form.appendChild(userIdInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Filter Functions
        function resetFilters() {
            window.location.href = 'users.php';
        }
        
        // Export Users
        function exportUsers() {
            const search = document.querySelector('input[name="search"]').value;
            const role = document.querySelector('select[name="role"]').value;
            const department = document.querySelector('select[name="department"]').value;
            
            let url = 'api/export_users.php?';
            if (search) url += `search=${encodeURIComponent(search)}&`;
            if (role) url += `role=${role}&`;
            if (department) url += `department=${encodeURIComponent(department)}`;
            
            window.open(url, '_blank');
        }
        
        // Form Validation
        document.getElementById('addUserForm')?.addEventListener('submit', function(e) {
            const password = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long!');
                return false;
            }
        });
        
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

<?php
// Helper function for avatar colors
function getAvatarColor($id) {
    $colors = [
        '#4a6491', '#f39c12', '#27ae60', '#e74c3c', '#9b59b6',
        '#3498db', '#1abc9c', '#d35400', '#2c3e50', '#7f8c8d'
    ];
    return $colors[$id % count($colors)];
}
?>