<?php
session_start();
require_once '../config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Get search parameter if available
$search_email = isset($_GET['search_email']) ? trim($_GET['search_email']) : '';

// Prepare query to fetch teachers
$query = "SELECT * FROM users WHERE role = 'teacher'";
$params = [];

if (!empty($search_email)) {
    $query .= " AND email LIKE ?";
    $params[] = "%$search_email%";
}

$query .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$teachers = $stmt->fetchAll();

// Set page variables
$page_title = "Teacher Management";
$active_page = "teachers";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 48px 0 0;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            background-color: #343a40;
            width: 250px;
        }
        .sidebar-sticky {
            position: relative;
            top: 0;
            height: calc(100vh - 48px);
            padding-top: .5rem;
            overflow-x: hidden;
            overflow-y: auto;
        }
        .sidebar .nav-link {
            font-weight: 500;
            color: #e9ecef;
            padding: 0.8rem 1rem;
            transition: all 0.3s ease;
        }
        .sidebar .nav-link.active {
            color: #ffffff;
            background-color: #495057;
            border-left: 4px solid #007bff;
        }
        .sidebar .nav-link:hover {
            color: #007bff;
            background-color: #495057;
        }
        .sidebar .nav-link i {
            margin-right: 8px;
            width: 20px;
            text-align: center;
        }
        .navbar {
            position: fixed;
            top: 0;
            right: 0;
            left: 250px;
            z-index: 99;
            height: 48px;
            padding: 0 20px;
        }
        .navbar .container-fluid {
            padding: 0 20px;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .content-wrapper {
            margin-top: 48px;
            padding: 0 20px;
        }

        .admin-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .admin-table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        
        .admin-table th, 
        .admin-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .admin-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .admin-card {
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .admin-card .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 15px 20px;
        }
        
        .admin-card .card-body {
            padding: 20px;
        }
        
        .admin-search {
            max-width: 300px;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-sticky">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link active" href="teacher_management.php">
                        <i class="bi bi-people"></i>
                        Teacher Management
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Top navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <span class="navbar-brand">Admin Dashboard</span>
            <div class="navbar-nav ms-auto">
                <a class="nav-item nav-link" href="../logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main content -->
    <div class="main-content">
        <div class="content-wrapper">
            <div class="container-fluid">
                <div class="card admin-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Teacher List</h5>
                        <form class="d-flex" method="GET" action="">
                            <input type="text" name="search_email" class="form-control me-2 admin-search" placeholder="Search in email..." value="<?php echo htmlspecialchars($search_email); ?>">
                            <button type="submit" class="btn btn-primary">Search</button>
                            <?php if ($search_email): ?>
                                <a href="teacher_management.php" class="btn btn-secondary ms-2">Clear</a>
                            <?php endif; ?>
                        </form>
                    </div>
                    <div class="card-body">
                        <?php if (count($teachers) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped admin-table">
                                    <thead>
                                        <tr>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Registration Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($teachers as $teacher): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($teacher['username']); ?></td>
                                                <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                                                <td><?php echo date('Y-m-d H:i:s', strtotime($teacher['created_at'])); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-danger" onclick="deleteTeacher(<?php echo $teacher['id']; ?>)">
                                                        <i class="bi bi-trash"></i> Delete
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-center">No teachers registered yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function deleteTeacher(id) {
        if (confirm('Are you sure you want to delete this teacher?')) {
            fetch('delete_teacher.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    teacher_id: id
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message || 'Error deleting teacher');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error deleting teacher. Please try again.');
            });
        }
    }
    </script>
</body>
</html> 