<?php
session_start();
require_once '../config.php';

// Set timezone
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

// Check if class ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid class ID";
    header("Location: dashboard.php");
    exit();
}

$class_id = (int)$_GET['id'];
$student_id = $_SESSION['user_id'];

// Verify that the student is enrolled in this class
$stmt = $pdo->prepare("
    SELECT c.* 
    FROM classes c
    JOIN class_enrollments ce ON c.id = ce.class_id
    WHERE c.id = ? AND ce.student_id = ?
");
$stmt->execute([$class_id, $student_id]);
$class = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$class) {
    $_SESSION['error'] = "Class not found or you don't have permission to access it";
    header("Location: dashboard.php");
    exit();
}

// Get teacher information
$stmt = $pdo->prepare("SELECT u.username, u.profile_image FROM users u WHERE u.id = ?");
$stmt->execute([$class['teacher_id']]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

// Get all graded tasks for this class
$stmt = $pdo->prepare("
    SELECT t.*, s.grade, s.filename as submission_file  
    FROM tasks t
    LEFT JOIN submissions s ON t.id = s.task_id AND s.student_id = ?
    WHERE t.class_id = ? 
    AND t.grades_sent = 1
    AND (s.grade IS NOT NULL OR 
         NOT EXISTS (SELECT 1 FROM submissions 
                    WHERE task_id = t.id AND student_id = ?))
    ORDER BY t.end_datetime DESC
");
$stmt->execute([$student_id, $class_id, $student_id]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set page variables
$page_title = htmlspecialchars($class['name']) . ' - Your Grades';
$active_page = 'grade';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Student Task Tracking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    
    <style>
    .main-content {
        margin-left: 180px;
        padding: 20px;
        padding-top: 68px;
    }

    .grades-container {
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
        padding: 20px;
        margin-bottom: 30px;
        margin-top: 45px;
    }

    .grades-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .grades-title {
        font-size: 22px;
        font-weight: 400;
        color: #3c4043;
        margin: 0;
    }

    .grade-list-item {
        background-color: #fff;
        border-radius: 8px;
        border: 1px solid #e0e0e0;
        padding: 15px;
        margin-bottom: 15px;
        transition: box-shadow 0.2s, transform 0.2s;
    }

    .grade-list-item:hover {
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        transform: translateY(-2px);
    }

    .grade-badge {
        font-size: 15px;
        font-weight: 600;
        padding: 5px 10px;
        border-radius: 50px;
        background-color: #f8f9fa;
        color: #212529;
        border: 2px solid;
    }

    .grade-badge.excellent {
        border-color: #198754;
        color: #198754;
    }

    .grade-badge.good {
        border-color: #0d6efd;
        color: #0d6efd;
    }

    .grade-badge.average {
        border-color: #fd7e14;
        color: #fd7e14;
    }

    .grade-badge.poor {
        border-color: #dc3545;
        color: #dc3545;
    }

    .grade-badge.not-graded {
        border-color: #6c757d;
        color: #6c757d;
    }

    .empty-state {
        text-align: center;
        padding: 40px 20px;
        background-color: #f8f9fa;
        border-radius: 8px;
        margin-top: 20px;
    }

    .empty-state i {
        font-size: 48px;
        color: #dadce0;
        margin-bottom: 15px;
    }

    .empty-state h4 {
        color: #3c4043;
        margin-bottom: 10px;
    }

    .empty-state p {
        color: #5f6368;
        margin-bottom: 20px;
    }

    .graded-date {
        font-size: 0.85rem;
        color: #6c757d;
        margin-top: 8px;
    }

    .submission-container {
        background-color: #f0f9f2;
        border: 1px solid #c8e6c9;
        border-radius: 6px;
        width: 280px;
        margin-bottom: 15px;
        text-align: left;
        overflow: hidden;
        align-self: flex-end;
    }
    
    .submission-header {
        background-color: white;
        color: #28a745;
        padding: 5px 12px;
        font-weight: 600;
        font-size: 14px;
        border-bottom: 1px solid #c8e6c9;
        text-align: right;
    }
    
    .submission-content {
        padding: 10px;
    }
    
    .submission-content .file-btn {
        background-color: #28a745;
        border-color: #28a745;
        display: block;
        width: 100%;
        text-align: left;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        padding: 6px 12px;
        font-size: 0.875rem;
        line-height: 1.5;
        color: white;
        border-radius: 4px;
        transition: background-color 0.2s;
        text-decoration: none;
    }
    
    .submission-content .file-btn:hover {
        background-color: #218838;
        border-color: #1e7e34;
        text-decoration: none;
    }
    
    .submission-content .file-btn i {
        color: white;
        margin-right: 5px;
        font-size: 0.875rem;
    }

    .col-md-4.text-md-end {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
    }

    /* Responsive fixes */
    @media (max-width: 768px) {
        .main-content {
            margin-left: 0;
        }
    }

    .submission-container.mt-3 {
        margin-top: 0.75rem !important;
    }
    </style>
</head>
<body>
    <?php include_once '../includes/student_sidebar.php'; ?>
    
    <!-- Custom Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container-fluid">
            <span class="navbar-brand"><?php echo htmlspecialchars($class['name']); ?></span>
            <div class="navbar-nav ms-auto">
                <a href="dashboard.php" class="nav-item nav-link">
                    <i class="bi bi-arrow-left"></i> Back to Your Classes
                </a>
            </div>
        </div>
    </nav>

    <!-- Main content -->
    <div class="main-content">
        <div class="container-fluid">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php 
                        echo htmlspecialchars($_SESSION['success']); 
                        unset($_SESSION['success']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php 
                        echo htmlspecialchars($_SESSION['error']); 
                        unset($_SESSION['error']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="grades-container">
                <div class="grades-header">
                    <h2 class="grades-title">Your Grades</h2>
                </div>

                <?php if (empty($tasks)): ?>
                    <div class="empty-state">
                        <i class="bi bi-award"></i>
                        <h4>No graded tasks yet</h4>
                        <p>Your grades will appear here once your teacher has graded and sent your submissions.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($tasks as $task): 
                        // Define grade styling
                        $grade_class = 'not-graded';
                        $grade_text = 'Not Graded';
                        
                        if ($task['grade'] !== null) {
                            $grade = floatval($task['grade']);
                            if ($grade >= 9) {
                                $grade_class = 'excellent';
                            } elseif ($grade >= 7) {
                                $grade_class = 'good';
                            } elseif ($grade >= 5) {
                                $grade_class = 'average';
                            } else {
                                $grade_class = 'poor';
                            }
                            $grade_text = number_format($grade, 1);
                        }
                    ?>
                        <div class="grade-list-item">
                            <div class="row">
                                <div class="col-md-8">
                                    <h5>
                                        <?php echo htmlspecialchars($task['title']); ?>
                                    </h5>
                                    <div class="task-dates mb-2">
                                        <div class="small text-muted mb-1">
                                            <i class="bi bi-calendar-event"></i> 
                                            Start: <?php echo date('M j, Y - g:i A', strtotime($task['start_datetime'])); ?>
                                        </div>
                                        <div class="small text-muted">
                                            <i class="bi bi-calendar-check"></i> 
                                            Due: <?php echo date('M j, Y - g:i A', strtotime($task['end_datetime'])); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 text-md-end">
                                    <!-- Center the grade within the column's width -->
                                    <div class="d-flex justify-content-center" style="width: 280px; margin-left: auto;">
                                        <div class="grade-badge <?php echo $grade_class; ?>">
                                            <?php echo $grade_text; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Thêm phần Your Submission -->
                                    <?php if (!empty($task['submission_file'])): ?>
                                    <div class="submission-container mt-3">
                                        <div class="submission-header">Your Submission</div>
                                        <div class="submission-content">
                                            <a href="../uploads/student_submissions/<?php echo $student_id; ?>/<?php echo htmlspecialchars($task['submission_file']); ?>" 
                                               target="_blank" 
                                               class="btn btn-success file-btn">
                                                <i class="bi bi-file-earmark-pdf"></i> 
                                                <?php echo htmlspecialchars($task['submission_file']); ?>
                                            </a>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 