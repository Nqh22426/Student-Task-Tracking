<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];

// Check if class_id parameter exists
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "No class specified";
    header("Location: dashboard.php");
    exit();
}

$class_id = $_GET['id'];

// Verify that the class belongs to the current teacher
$stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ? AND teacher_id = ?");
$stmt->execute([$class_id, $teacher_id]);
$class = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$class) {
    $_SESSION['error'] = "You don't have permission to delete this class";
    header("Location: dashboard.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_delete'])) {
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Delete all enrollments for this class
        $stmt = $pdo->prepare("DELETE FROM class_enrollments WHERE class_id = ?");
        $stmt->execute([$class_id]);
        
        // Delete all tasks for this class
        $stmt = $pdo->prepare("DELETE FROM tasks WHERE class_id = ?");
        $stmt->execute([$class_id]);
        
        // Finally, delete the class
        $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ?");
        $stmt->execute([$class_id]);
        
        $pdo->commit();
        
        $_SESSION['success'] = "Class deleted successfully";
        header("Location: dashboard.php");
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error deleting class: " . $e->getMessage();
        header("Location: dashboard.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Class - Student Task Tracking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        /* Sidebar styles */
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
        
        /* Delete Class Styles */
        .delete-class-card {
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
            margin-top: 2rem;
        }
        .delete-class-card .card-header {
            background-color: #dc3545;
            color: white;
            border-radius: 8px 8px 0 0;
            padding: 15px 20px;
        }
        .delete-warning {
            color: #dc3545;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-sticky">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="bi bi-grid"></i>
                        Your Classes
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="profile.php">
                        <i class="bi bi-person"></i>
                        Profile
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Top navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <span class="navbar-brand">Teacher Dashboard</span>
            <div class="navbar-nav ms-auto">
                <a class="nav-item nav-link" href="../logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main content -->
    <div class="main-content">
        <div class="content-wrapper">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Delete Class</h2>
                    <a href="dashboard.php" class="btn btn-secondary">Back to Classes</a>
                </div>

                <div class="card delete-class-card">
                    <div class="card-header">
                        <h5 class="mb-0">Warning: You are about to delete "<?php echo htmlspecialchars($class['name']); ?>"</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-danger">
                            <p><strong>Caution:</strong> This action cannot be undone!</p>
                            <p>Deleting this class will:</p>
                            <ul>
                                <li>Remove all tasks associated with this class</li>
                                <li>Remove all student enrollments in this class</li>
                            </ul>
                        </div>
                        
                        <form action="delete_class.php?id=<?php echo $class_id; ?>" method="POST">
                            <p class="delete-warning mb-4">Are you sure you want to permanently delete this class?</p>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" name="confirm_delete" class="btn btn-danger">Yes, Delete This Class</button>
                                <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 