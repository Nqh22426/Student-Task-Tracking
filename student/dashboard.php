<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['user_id'];

// Fetch all classes enrolled by this student
$stmt = $pdo->prepare("
    SELECT c.*, u.username as teacher_name, u.profile_image as teacher_profile_image, u.id as teacher_id
    FROM classes c
    JOIN class_enrollments ce ON c.id = ce.class_id
    JOIN users u ON c.teacher_id = u.id
    WHERE ce.student_id = ? 
    ORDER BY c.name ASC
");
$stmt->execute([$student_id]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Extract class IDs for todo count calculation
$class_ids = array_column($classes, 'id');

// Fetch student information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Set page variables
$page_title = "Student Dashboard - Student Task Tracking";
$active_page = 'dashboard';

// Calculate the todo_count for the sidebar badge
$todo_count = 0;
if(!empty($class_ids)) {
    $placeholders = implode(',', array_fill(0, count($class_ids), '?'));
    // Get all ongoing tasks
    $query = "SELECT t.id FROM tasks t 
              WHERE t.class_id IN ($placeholders)
              AND t.start_datetime <= NOW()
              AND t.end_datetime >= NOW()";
    $stmt = $pdo->prepare($query);
    $stmt->execute($class_ids);
    $task_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get submissions for these tasks
    if (!empty($task_ids)) {
        $placeholders = implode(',', array_fill(0, count($task_ids), '?'));
        $stmt = $pdo->prepare("SELECT task_id FROM submissions WHERE student_id = ? AND task_id IN ($placeholders)");
        $stmt->execute(array_merge([$student_id], $task_ids));
        $submitted_task_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Calculate count of tasks without submissions
        $todo_count = count($task_ids) - count($submitted_task_ids);
    }
}

include_once '../includes/header.php';

?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Task Tracking</title>
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
            overflow: hidden;
        }
        .class-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .class-card .card-header {
            border-radius: 0;
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
        .teacher-info {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        .teacher-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
            background-color: #007bff;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 500;
            font-size: 16px;
            border: 2px solid #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .leave-btn {
            color: #dc3545;
            border-color: #dc3545;
        }
        .leave-btn:hover {
            background-color: #dc3545;
            color: white;
        }
        .join-class-card {
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
        .join-class-card:hover {
            border-color: #007bff;
            background-color: #f1f8ff;
        }
        .join-class-icon {
            font-size: 48px;
            color: #6c757d;
            margin-bottom: 15px;
        }
        .join-class-card:hover .join-class-icon {
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
        
        /* Dashboard specific styles */
        .classes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .welcome-section {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #007bff;
        }
        
        .welcome-section h2 {
            color: #343a40;
            margin-bottom: 10px;
        }
        
        .empty-classes {
            text-align: center;
            padding: 40px;
            background-color: #f8f9fa;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .empty-classes i {
            font-size: 48px;
            color: #adb5bd;
            margin-bottom: 15px;
        }
        
        .empty-classes h4 {
            margin-bottom: 10px;
            color: #495057;
        }
        
        .empty-classes p {
            color: #6c757d;
            margin-bottom: 20px;
        }
        
        /* Join class modal */
        .join-class-modal .modal-header {
            background-color: #007bff;
            color: white;
        }
        
        .join-class-modal .modal-footer {
            border-top: none;
            padding-top: 0;
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
    <?php include_once '../includes/student_sidebar.php'; ?>

    <!-- Top navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <span class="navbar-brand">Student Dashboard</span>
            <div class="navbar-nav ms-auto">
                <?php if (!empty($student['profile_image'])): ?>
                    <a href="profile.php" class="nav-item nav-link p-0 me-3 d-flex align-items-center" style="height: 48px;">
                        <img src="../uploads/profile_images/<?php echo htmlspecialchars($student['profile_image']); ?>" class="rounded-circle" alt="Profile" width="32" height="32" style="border: 2px solid #ffffff;">
                    </a>
                <?php else: ?>
                    <a href="profile.php" class="nav-item nav-link p-0 me-3 d-flex align-items-center" style="height: 48px;">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 16px; border: 2px solid #ffffff;">
                            <?php echo strtoupper(substr($student['username'], 0, 1)); ?>
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
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#joinClassModal">
                        <i class="bi bi-plus-circle me-2"></i>Join Class
                    </button>
                </div>
                
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                    <!-- Existing classes -->
                    <?php if(count($classes) > 0): ?>
                        <?php foreach ($classes as $class): ?>
                            <div class="col">
                                <div class="card class-card">
                                    <div class="card-header" style="background-color: <?php echo htmlspecialchars($class['color']); ?>; color: white;">
                                        <h5 class="mb-0"><?php echo htmlspecialchars($class['name']); ?></h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="teacher-info">
                                            <?php if (!empty($class['teacher_profile_image'])): ?>
                                                <div class="teacher-avatar" style="background-image: url('../uploads/profile_images/<?php echo htmlspecialchars($class['teacher_profile_image']); ?>'); background-size: cover; background-color: transparent;"></div>
                                            <?php else: ?>
                                                <div class="teacher-avatar">
                                                    <?php echo strtoupper(substr($class['teacher_name'], 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <p class="mb-0"><?php echo htmlspecialchars($class['teacher_name']); ?></p>
                                            </div>
                                        </div>
                                        
                                        <?php
                                        // Count tasks for this class
                                        $stmt = $pdo->prepare("SELECT COUNT(*) as task_count FROM tasks WHERE class_id = ?");
                                        $stmt->execute([$class['id']]);
                                        $task_count = $stmt->fetch(PDO::FETCH_ASSOC)['task_count'];
                                        ?>
                                        
                                        <p class="class-count">
                                            <i class="bi bi-list-check"></i> <?php echo $task_count; ?> tasks
                                        </p>
                                        
                                        <div class="class-actions mt-3">
                                            <div class="row g-2">
                                                <div class="col-12 mb-2">
                                                    <a href="class_details.php?id=<?php echo $class['id']; ?>" class="btn btn-outline-primary w-100">
                                                        <i class="bi bi-eye"></i> View Details
                                                    </a>
                                                </div>
                                                <div class="col-12">
                                                    <form action="leave_class.php" method="post" onsubmit="return confirm('Are you sure you want to leave this class? This will delete all your progress for tasks in this class.');">
                                                        <input type="hidden" name="class_id" value="<?php echo $class['id']; ?>">
                                                        <button type="submit" class="btn btn-outline-danger w-100 leave-btn">
                                                            <i class="bi bi-box-arrow-right"></i> Leave
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                You are not enrolled in any classes. Use the "Join Class" button to start.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Join Class Modal -->
    <div class="modal fade" id="joinClassModal" tabindex="-1" aria-labelledby="joinClassModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="joinClassModalLabel">Join Class</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="join_class.php" method="post">
                        <div class="mb-3">
                            <label for="join_code" class="form-label">Join Code</label>
                            <input type="text" class="form-control" id="join_code" name="join_code" required>
                            <div class="form-text">Please enter the join code provided by your teacher.</div>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Join</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 