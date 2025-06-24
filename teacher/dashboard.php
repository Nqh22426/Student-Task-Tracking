<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];

// Fetch all classes belonging to this teacher
$stmt = $pdo->prepare("SELECT * FROM classes WHERE teacher_id = ? ORDER BY created_at DESC");
$stmt->execute([$teacher_id]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch teacher information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$teacher_id]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - Student Task Tracking</title>
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
        
        /* Class cards */
        .class-card {
            border-radius: 8px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .class-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .class-card .card-header {
            border-radius: 8px 8px 0 0;
            padding: 15px 20px;
        }
        .class-card .card-body {
            padding: 20px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        .class-card .card-footer {
            background-color: transparent;
            border-top: 1px solid rgba(0,0,0,0.125);
            padding: 15px 20px;
        }
        .class-actions {
            margin-top: auto;
        }
        .create-class-card {
            height: 100%;
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            transition: all 0.3s ease;
            background-color: #f8f9fa;
        }
        .create-class-card:hover {
            border-color: #007bff;
            background-color: #f1f8ff;
        }
        .create-class-icon {
            font-size: 48px;
            color: #6c757d;
            margin-bottom: 15px;
        }
        .create-class-card:hover .create-class-icon {
            color: #007bff;
        }
        .class-code {
            font-family: monospace;
            font-size: 1.1rem;
            background-color: #f8f9fa;
            padding: 0.5rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        .class-count {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .alert-container {
            position: fixed;
            top: 60px;
            right: 20px;
            z-index: 1000;
            max-width: 350px;
        }
    </style>
</head>
<body>
    <?php if(isset($_SESSION['success']) || isset($_SESSION['error'])): ?>
    <div class="alert-container">
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $_SESSION['success']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

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
                <?php if (!empty($teacher['profile_image'])): ?>
                    <a href="profile.php" class="nav-item nav-link p-0 me-3 d-flex align-items-center" style="height: 48px;">
                        <img src="../uploads/profile_images/<?php echo htmlspecialchars($teacher['profile_image']); ?>" class="rounded-circle" alt="Profile" width="32" height="32" style="border: 2px solid #ffffff;">
                    </a>
                <?php else: ?>
                    <a href="profile.php" class="nav-item nav-link p-0 me-3 d-flex align-items-center" style="height: 48px;">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 16px; border: 2px solid #ffffff;">
                            <?php echo strtoupper(substr($teacher['username'], 0, 1)); ?>
                        </div>
                    </a>
                <?php endif; ?>
                <a class="nav-item nav-link" href="../logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main content -->
    <div class="main-content">
        <div class="content-wrapper">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Your Classes</h2>
                    <a href="create_class.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-2"></i>Create New Class
                    </a>
                </div>
                
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                    <!-- Create new class card -->
                    <!-- The card is being removed as requested -->
                    
                    <!-- Existing classes -->
                    <?php foreach ($classes as $class): ?>
                        <div class="col">
                            <div class="card class-card">
                                <div class="card-header" style="background-color: <?php echo htmlspecialchars($class['color']); ?>; color: <?php echo getContrastColor($class['color']); ?>">
                                    <h5 class="card-title mb-0"><?php echo htmlspecialchars($class['name']); ?></h5>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($class['description'])): ?>
                                        <p class="card-text mb-3"><?php echo htmlspecialchars($class['description']); ?></p>
                                    <?php else: ?>
                                        <p class="card-text text-muted mb-3"><em>No description provided</em></p>
                                    <?php endif; ?>
                                    
                                    <div class="mb-3">
                                        <p class="mb-1"><strong>Join Code:</strong></p>
                                        <div class="class-code"><?php echo htmlspecialchars($class['class_code']); ?></div>
                                    </div>
                                    
                                    <?php
                                    // Count students enrolled in this class
                                    try {
                                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM class_enrollments WHERE class_id = ?");
                                        $stmt->execute([$class['id']]);
                                        $student_count = $stmt->fetchColumn();
                                    } catch (PDOException $e) {
                                        $student_count = 0;
                                    }
                                    
                                    // Count tasks in this class
                                    try {
                                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE class_id = ?");
                                        $stmt->execute([$class['id']]);
                                        $task_count = $stmt->fetchColumn();
                                    } catch (PDOException $e) {
                                        $task_count = 0;
                                    }
                                    ?>
                                    
                                    <div class="d-flex justify-content-between class-count mb-3">
                                        <span><i class="bi bi-people me-1"></i> <?php echo $student_count; ?> student<?php echo $student_count != 1 ? 's' : ''; ?></span>
                                        <span><i class="bi bi-list-check me-1"></i> <?php echo $task_count; ?> task<?php echo $task_count != 1 ? 's' : ''; ?></span>
                                    </div>
                                    
                                    <div class="class-actions">
                                        <a href="class_details.php?id=<?php echo $class['id']; ?>" class="btn btn-primary btn-sm me-1">
                                            <i class="bi bi-eye me-1"></i>View Class
                                        </a>
                                        <a href="edit_class.php?id=<?php echo $class['id']; ?>" class="btn btn-outline-secondary btn-sm me-1">
                                            <i class="bi bi-pencil me-1"></i>Edit
                                        </a>
                                        <a href="delete_class.php?id=<?php echo $class['id']; ?>" class="btn btn-outline-danger btn-sm">
                                            <i class="bi bi-trash me-1"></i>Delete
                                        </a>
                                    </div>
                                </div>
                                <div class="card-footer text-muted">
                                    <small>Created: <?php echo date('M j, Y', strtotime($class['created_at'])); ?></small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (count($classes) === 0): ?>
                        <div class="col-12 mt-3">
                            <div class="alert alert-info">
                                <p class="mb-0">You haven't created any classes yet. Create your first class to get started!</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-dismiss alerts after 5 seconds
        window.addEventListener('DOMContentLoaded', (event) => {
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });
    </script>
</body>
</html>

<?php
// Function to determine contrasting text color based on background
function getContrastColor($hexColor) {
    // Remove # if present
    $hex = ltrim($hexColor, '#');
    
    // Convert to RGB
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    
    // Calculate luminance
    $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
    
    // Return black or white
    return $luminance > 0.5 ? '#000000' : '#ffffff';
}
?> 