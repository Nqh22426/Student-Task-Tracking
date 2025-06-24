<?php
session_start();
require_once '../config.php';

date_default_timezone_set('Asia/Ho_Chi_Minh');

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Get all tasks for this class
$taskQuery = "SELECT t.*, t.is_locked
FROM tasks t WHERE t.class_id = :class_id";
$params = ['class_id' => $class_id];

// Add search conditions if necessary
if (!empty($search)) {
    $taskQuery .= " AND (title LIKE :search OR description LIKE :search)";
    $params['search'] = "%$search%";
}

// Apply filter
switch ($filter) {
    case 'upcoming':
        $taskQuery .= " AND NOW() < start_datetime";
        break;
    case 'ongoing':
        $taskQuery .= " AND NOW() BETWEEN start_datetime AND end_datetime";
        break;
    case 'completed':
        $taskQuery .= " AND end_datetime < NOW()";
        break;
}

$taskQuery .= " ORDER BY start_datetime ASC";

// Execute the query
$stmt = $pdo->prepare($taskQuery);
$stmt->execute($params);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = htmlspecialchars($class['name']) . ' - Tasks List';
$active_page = 'tasks';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta http-equiv="Cache" content="no-cache">
    
    <title><?php echo $page_title; ?> - Student Task Tracking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Custom Modal Cleanup Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                const backdrops = document.querySelectorAll('.modal-backdrop');
                if (backdrops.length > 0) {
                    console.log('Found lingering backdrops on page load, cleaning up');
                    backdrops.forEach(el => el.remove());
                    document.body.classList.remove('modal-open');
                    document.body.style.overflow = '';
                    document.body.style.paddingRight = '';
                }
            }, 500);
        });
    </script>
    
    <style>
    /* Tasks List Styling */
    .main-content {
        margin-left: 180px;
        padding: 20px;
        padding-top: 68px;
    }

    .tasks-container {
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
        padding: 20px;
        margin-bottom: 30px;
        margin-top: 45px;
    }

    .alert {
        margin-top: 45px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        position: relative;
        z-index: 100;
    }

    /* This pushes down the tasks container when alerts are present */
    .alert + .tasks-container, 
    .alert + .alert + .tasks-container {
        margin-top: 10px;
    }

    .tasks-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .tasks-title {
        font-size: 22px;
        font-weight: 400;
        color: #3c4043;
        margin: 0;
    }

    .filter-card {
        background-color: #f8f9fa;
        border-radius: 8px;
        border: 1px solid #e0e0e0;
        padding: 15px;
        margin-bottom: 25px;
    }

    .task-list-item {
        background-color: #fff;
        border-radius: 8px;
        border: 1px solid #e0e0e0;
        padding: 15px;
        margin-bottom: 15px;
        transition: box-shadow 0.2s, transform 0.2s;
    }

    .task-list-item:hover {
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        transform: translateY(-2px);
    }

    /* Task status styling */
    .task-list-item.upcoming {
        border-left: 4px solid #28a745;
    }

    .task-list-item.ongoing {
        border-left: 4px solid #ffc107;
    }

    .task-list-item.completed {
        border-left: 4px solid #dc3545;
    }

    .badge.bg-upcoming {
        background-color: #28a745 !important;
    }

    .badge.bg-ongoing {
        background-color: #ffc107 !important;
        color: #212529 !important;
    }

    .badge.bg-completed {
        background-color: #dc3545 !important;
    }

    /* Days remaining styling */
    .days-left {
        display: none !important;
    }
    
    .days-left.urgent {
        display: none !important;
    }
    
    .task-list-item h5 {
        color: #3c4043;
        margin-bottom: 12px;
        font-weight: 500;
    }

    .task-dates {
        color: #5f6368;
        font-size: 0.9rem;
    }

    .task-dates i {
        color: #5f6368;
        margin-right: 5px;
    }

    .task-actions {
        margin-top: 15px;
    }

    .task-actions .btn {
        padding: 5px 10px;
        font-size: 0.85rem;
    }

    /* Badge styling */
    .badge.bg-warning {
        background-color: #ffc107 !important;
        color: #212529 !important;
    }

    /* Empty state styling */
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

    .bi-file-pdf {
        color: #dc3545;
        font-size: 1.1rem;
        margin-right: 5px;
    }

    .mt-2 .small {
        margin-right: 10px;
        font-weight: 500;
    }

    .mt-2 .btn-sm {
        padding: 2px 8px;
        font-size: 0.8rem;
    }
    
    .col-md-4.text-md-end {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
    }

    .col-md-8 {
        padding-bottom: 10px;
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
        position: relative;
        padding-bottom: 40px;
    }
    
    .file-wrapper {
        position: relative;
        margin-bottom: 5px;
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
    
    .submission-content .unsubmit-btn {
        display: inline-block;
        width: auto;
        color: #dc3545;
        border-color: #dc3545;
        background-color: white;
        padding: 4px 8px;
        font-size: 0.875rem;
        position: absolute;
        right: 10px;
        bottom: 10px;
        border-radius: 4px;
        transition: background-color 0.15s ease-in-out, color 0.15s ease-in-out !important;
    }

    .submission-content .unsubmit-btn:hover {
        background-color: #dc3545;
        color: white;
        border-color: #dc3545;
    }

    .submission-content .file-btn i {
        color: white;
        margin-right: 5px;
        font-size: 0.875rem;
    }

    * {
        transition: none !important;
    }
    
    /* Basic responsive fix for smaller screens */
    @media (max-width: 768px) {
        .main-content {
            margin-left: 0;
        }
        
        .days-left {
            margin-top: 5px;
            margin-left: 0;
            display: inline-flex;
            padding: 3px 8px;
            font-size: 0.85rem;
        }
        
        .d-flex.align-items-center {
            flex-wrap: wrap;
        }
    }

    .submit-btn {
        display: inline-block;
        padding: 8px 16px;
        background-color: #007bff;
        color: white;
        border-radius: 4px;
        font-size: 14px;
        font-weight: 500;
        text-align: center;
        text-decoration: none;
        border: none;
        cursor: pointer;
        transition: background-color 0.15s ease-in-out;
    }

    .submit-btn:hover {
        background-color: #0069d9;
        color: white;
        text-decoration: none;
    }

    .submit-btn i {
        margin-right: 5px;
    }
    
    /* Locked Submit button styling */
    .submit-btn.locked {
        background-color: #6c757d;
        cursor: not-allowed;
        position: relative;
    }
    
    .submit-btn.locked:hover {
        background-color: #6c757d;
    }
    
    .tooltip-container {
        position: relative;
        display: inline-block;
    }
    
    .tooltip-container .tooltip-text {
        visibility: hidden;
        width: 220px;
        background-color: #333;
        color: #fff;
        text-align: center;
        border-radius: 6px;
        padding: 5px;
        position: absolute;
        z-index: 1;
        bottom: 125%;
        left: 50%;
        transform: translateX(-50%);
        opacity: 0;
        transition: opacity 0.3s;
    }
    
    .tooltip-container .tooltip-text::after {
        content: "";
        position: absolute;
        top: 100%;
        left: 50%;
        margin-left: -5px;
        border-width: 5px;
        border-style: solid;
        border-color: #333 transparent transparent transparent;
    }
    
    .tooltip-container:hover .tooltip-text {
        visibility: visible;
        opacity: 1;
    }

    .submission-actions-container {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        width: 100%;
    }

    .submit-button-container {
        margin-top: 5px;
        min-height: 40px;
        display: flex;
        align-items: center;
        justify-content: flex-end;
    }

    .modal-task-header {
        background-color: #007bff;
        color: white;
        border-bottom: none;
        padding: 0.75rem 1rem;
    }
    
    .task-title-with-class {
        margin-bottom: 15px;
    }
    
    .task-title-with-class h3 {
        margin-bottom: 0;
        font-size: 1.5rem;
    }
    
    .task-title-with-class .class-name {
        color: #6c757d;
        font-size: 1rem;
        margin-left: 4px;
    }
    
    .task-info-section {
        margin-bottom: 15px;
    }
    
    .task-info-section h6 {
        font-weight: 600;
        margin-bottom: 4px;
        color: #495057;
        font-size: 0.9rem;
    }
    
    .task-info-section p {
        margin-bottom: 10px;
    }
    
    .task-file-preview {
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        padding: 10px;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
    }
    
    .submission-section {
        background-color: #f0f7ff;
        border-radius: 6px;
        padding: 15px;
        margin-top: 15px;
        border: 1px solid #cfe2ff;
    }
    
    .submission-section h5 {
        font-size: 1.1rem;
        margin-bottom: 10px;
    }
    
    .upload-area {
        border: 2px dashed #dee2e6;
        border-radius: 8px;
        padding: 20px;
        text-align: center;
        background-color: #f8f9fa;
        margin-bottom: 15px;
        transition: all 0.3s;
        cursor: pointer;
    }
    
    .upload-area:hover {
        border-color: #6c757d;
        background-color: #e9ecef;
    }
    
    .upload-icon {
        font-size: 2.5rem;
        color: #6c757d;
        margin-bottom: 10px;
    }
    
    .file-selected {
        display: none;
        padding: 10px;
        background-color: #e8f5e9;
        border-radius: 4px;
        margin-top: 10px;
        border: 1px solid #c8e6c9;
    }
    
    .file-selected i {
        color: #4caf50;
        margin-right: 5px;
    }
    
    .submission-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        margin-top: 10px;
    }
    
    .view-file-btn {
        background-color: #007bff;
    }

    .modal-backdrop {
        z-index: 1040 !important;
    }
    
    .modal-content {
        z-index: 1050 !important;
        box-shadow: 0 5px 15px rgba(0,0,0,.5);
    }
    
    .modal.fade .modal-dialog {
        transition: transform .3s ease-out !important;
        transform: translate(0,-50px) !important;
    }
    
    .modal.show .modal-dialog {
        transform: none !important;
    }
    
    .modal.fade {
        transition: opacity .15s linear !important;
    }
    
    .modal {
        background-color: rgba(0, 0, 0, 0.5);
    }
    
    .modal-backdrop.show {
        opacity: 0.5;
    }
    
    .modal-dialog {
        pointer-events: auto;
    }
    
    .manually-close-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 1045;
        cursor: pointer;
        display: none;
    }
    

    </style>
    
    <script>
        setTimeout(function() {
            console.log("Forcing page refresh to update task statuses");
            window.location.reload();
        }, 60000);
    </script>
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
            
            <div class="tasks-container">
                <div class="tasks-header">
                    <h2 class="tasks-title">Tasks List</h2>
                </div>
                
                <div class="filter-card">
                    <form method="GET" action="tasks_list.php" class="row g-3">
                        <input type="hidden" name="id" value="<?php echo $class_id; ?>">
                        <div class="col-md-6">
                            <input type="text" class="form-control" name="search" placeholder="Search tasks..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-4">
                            <select class="form-select" name="filter">
                                <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Tasks</option>
                                <option value="upcoming" <?php echo $filter === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                                <option value="ongoing" <?php echo $filter === 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                                <option value="completed" <?php echo $filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                        </div>
                    </form>
                </div>

                <?php if (empty($tasks)): ?>
                    <div class="empty-state">
                        <i class="bi bi-calendar-check"></i>
                        <h4>No tasks found</h4>
                        <p><?php echo !empty($search) ? 'No tasks match your search or filter criteria.' : 'No tasks have been assigned for this class yet.'; ?></p>
                    </div>
                <?php else: ?>
                    <?php foreach ($tasks as $task): 
                        $status_class = 'upcoming';
                        $badge_class = 'bg-upcoming';
                        $status_text = 'Upcoming';
                        
                        // Use direct timestamp comparison for more reliable results
                        $current_time = time();
                        $start_time = strtotime($task['start_datetime']);
                        $end_time = strtotime($task['end_datetime']);
                        
                        // Determine status based on current time relative to task times
                        if ($current_time < $start_time) {
                            // Before start time - Upcoming
                            $status_class = 'upcoming';
                            $badge_class = 'bg-upcoming';
                            $status_text = 'Upcoming';
                        } elseif ($current_time >= $start_time && $current_time <= $end_time) {
                            // Between start and end time - Ongoing
                            $status_class = 'ongoing';
                            $badge_class = 'bg-ongoing';
                            $status_text = 'Ongoing';
                        } else {
                            // After end time - Completed
                            $status_class = 'completed';
                            $badge_class = 'bg-completed';
                            $status_text = 'Completed';
                        }
                        
                        // Check if this task has a submission
                        $sub_query = $pdo->prepare("
                            SELECT * FROM submissions 
                            WHERE student_id = ? AND task_id = ? 
                            ORDER BY submission_date DESC LIMIT 1
                        ");
                        $sub_query->execute([$student_id, $task['id']]);
                        $submission = $sub_query->fetch(PDO::FETCH_ASSOC);
                        $has_submission = ($submission !== false);
                    ?>
                        <div class="task-list-item <?php echo $status_class; ?>"
                             data-start-datetime="<?php echo $task['start_datetime']; ?>" 
                             data-end-datetime="<?php echo $task['end_datetime']; ?>"
                             data-task-id="<?php echo $task['id']; ?>">
                            <div class="row">
                                <div class="col-md-8">
                                    <h5>
                                        <?php echo htmlspecialchars($task['title']); ?>
                                    </h5>
                                    <div class="task-dates mb-3">
                                        <div class="mb-1">
                                            <i class="bi bi-calendar-event"></i> 
                                            Start: <?php echo date('M j, Y - g:i A', $start_time); ?>
                                        </div>
                                        <div>
                                            <i class="bi bi-calendar-check"></i> 
                                            Due: <?php echo date('M j, Y - g:i A', $end_time); ?>
                                        </div>
                                    </div>
                                    <p class="text-secondary">
                                        <?php echo !empty($task['description']) ? htmlspecialchars($task['description']) : 'No description available.'; ?>
                                    </p>
                                    
                                    <?php if (!empty($task['pdf_file'])): ?>
                                    <div class="mt-2">
                                        <i class="bi bi-file-pdf"></i>
                                        <span class="small">Task File</span>
                                        <a href="../<?php echo $task['pdf_file']; ?>" target="_blank" class="btn btn-sm btn-primary ms-2">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                        <a href="../<?php echo $task['pdf_file']; ?>" download class="btn btn-sm btn-outline-primary ms-1">
                                            <i class="bi bi-download"></i> Download
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-4 text-md-end">
                                    <div class="submission-actions-container">
                                        <span class="badge <?php echo $badge_class; ?> mb-2">
                                            <?php echo $status_text; ?>
                                        </span>
                                        
                                        <?php if ($has_submission): 
                                            $file_path = '../uploads/student_submissions/' . $student_id . '/' . $submission['filename'];
                                        ?>
                                            <div class="submission-container">
                                                <div class="submission-header">Your Submission</div>
                                                <div class="submission-content">
                                                    <div class="file-wrapper">
                                                        <a href="<?php echo htmlspecialchars($file_path); ?>" 
                                                           target="_blank" 
                                                           class="btn btn-success file-btn">
                                                            <i class="bi bi-file-earmark-pdf"></i> 
                                                            <?php echo htmlspecialchars($submission['filename']); ?>
                                                        </a>
                                                    </div>
                                                    <?php if (!$task['is_locked']): ?>
                                                    <a href="unsubmit_task.php?task_id=<?php echo $task['id']; ?>&redirect=tasks_list.php?id=<?php echo $class_id; ?>" 
                                                       class="btn btn-sm btn-outline-danger unsubmit-btn"
                                                       onclick="return confirm('Are you sure you want to remove this submission?');">
                                                        <i class="bi bi-x-circle me-1"></i> Unsubmit
                                                    </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="submit-button-container">
                                                <?php if ($status_class === 'upcoming'): ?>
                                                    <button type="button" class="btn btn-secondary btn-sm" disabled title="Task not yet started">
                                                        <i class="bi bi-upload"></i> Submit
                                                    </button>
                                                <?php elseif ($task['is_locked']): ?>
                                                    <div class="tooltip-container">
                                                        <button type="button" class="btn btn-secondary btn-sm submit-btn locked" disabled>
                                                            <i class="bi bi-lock-fill"></i> Submit
                                                        </button>
                                                        <span class="tooltip-text">This task has been locked by your teacher. You cannot submit at this time.</span>
                                                    </div>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-primary btn-sm submit-task-btn" data-bs-toggle="modal" data-bs-target="#taskSubmitModal" data-task-id="<?php echo $task['id']; ?>" data-task-title="<?php echo htmlspecialchars($task['title']); ?>" data-task-class="<?php echo htmlspecialchars($class['name']); ?>" data-task-start="<?php echo date('M j, Y - g:i A', $start_time); ?>" data-task-end="<?php echo date('M j, Y - g:i A', $end_time); ?>" data-task-description="<?php echo htmlspecialchars($task['description'] ?? ''); ?>" data-task-pdf="<?php echo !empty($task['pdf_file']) ? '../' . $task['pdf_file'] : ''; ?>" data-task-locked="<?php echo $task['is_locked'] ? 'true' : 'false'; ?>">
                                                        <i class="bi bi-upload"></i> Submit
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Click anywhere outside modal to close it properly -->
    <div class="manually-close-modal" id="manuallyCloseModal"></div>

    <!-- Task Submit Modal -->
    <div class="modal fade" id="taskSubmitModal" tabindex="-1" aria-labelledby="taskSubmitModalLabel" aria-hidden="true" data-bs-backdrop="true" data-bs-keyboard="true">
        <div class="modal-dialog" style="max-width: 550px;">
            <div class="modal-content">
                <div class="modal-header modal-task-header">
                    <h5 class="modal-title" id="taskSubmitModalLabel">Task Submission</h5>
                    <button type="button" class="btn-close btn-close-white" id="closeModalBtn"></button>
                </div>
                <div class="modal-body">
                    <div class="task-info-section">
                        <div class="task-title-with-class">
                            <h3><span id="task-title"></span></h3>
                        </div>
                        
                        <div class="row g-2">
                            <div class="col-6">
                                <h6>Starts:</h6>
                                <p id="task-start" class="small"></p>
                            </div>
                            <div class="col-6">
                                <h6>Due:</h6>
                                <p id="task-end" class="small"></p>
                            </div>
                        </div>
                        
                        <div id="task-description-section">
                            <h6>Description:</h6>
                            <div class="p-2 bg-light rounded mb-2 small" id="task-description">
                            </div>
                        </div>
                        
                        <div id="task-pdf-section" style="display: none;">
                            <h6>Task Document:</h6>
                            <div class="task-file-preview">
                                <i class="bi bi-file-pdf text-danger me-2"></i>
                                <div>
                                    <span id="task-pdf-name" class="small d-block">Task PDF Document</span>
                                    <div class="mt-1">
                                        <a href="#" id="view-pdf-link" target="_blank" class="btn btn-sm btn-primary">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                        <a href="#" id="download-pdf-link" download class="btn btn-sm btn-outline-primary ms-1">
                                            <i class="bi bi-download"></i> Download
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="submission-section">
                        <h5>Your Submission</h5>
                        <form id="task-submission-form" action="submit_task.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="task_id" id="submission-task-id">
                            <input type="hidden" name="referer" value="tasks_list.php?id=<?php echo $class_id; ?>">
                            <input type="hidden" name="MAX_FILE_SIZE" value="31457280"> <!-- Increased to 30MB -->
                            
                            <div class="upload-area" id="upload-area">
                                <i class="bi bi-cloud-arrow-up upload-icon"></i>
                                <h5>Upload your PDF file</h5>
                                <p class="text-muted small">Click to select a file or drag and drop (Max 30MB)</p>
                                <input type="file" name="submission_file" id="submission-file" accept=".pdf" class="d-none">
                            </div>
                            
                            <div class="file-selected" id="file-selected">
                                <i class="bi bi-file-pdf"></i>
                                <span id="selected-filename">No file selected</span>
                                <button type="button" class="btn btn-sm btn-outline-danger float-end" id="remove-file">
                                    <i class="bi bi-x"></i> Remove
                                </button>
                            </div>
                            
                            <div id="submission-preview" style="display: none;" class="mb-3">
                                <label class="form-label small">Your Submitted File</label>
                                <div class="p-2 bg-light rounded d-flex align-items-center">
                                    <i class="bi bi-file-pdf text-danger me-2"></i>
                                    <div class="w-100">
                                        <span id="submission-filename" class="small d-block"></span>
                                        <div class="submission-actions">
                                            <a href="#" id="view-submission-link" target="_blank" class="btn btn-sm btn-primary view-file-btn">
                                                <i class="bi bi-eye"></i> View File
                                            </a>
                                            <button type="button" id="unsubmit-btn" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-x-circle"></i> Unsubmit
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid" id="submit-button-container">
                                <button type="submit" class="btn btn-primary">Submit Task</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Script to update task status in real-time -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log("Document loaded, initializing task status update");
        
        // Initialize modal control
        const taskModal = document.getElementById('taskSubmitModal');
        const closeBtn = document.getElementById('closeModalBtn');
        const manualClose = document.getElementById('manuallyCloseModal');
        let bsModal = null;
        
        // Initialize Bootstrap modal
        if (taskModal) {
            bsModal = new bootstrap.Modal(taskModal, {
                backdrop: true,
                keyboard: true,
                focus: true
            });
            
            // Add event listener to close when clicking outside
            taskModal.addEventListener('click', function(event) {
                // If click is outside the modal-content
                if (event.target === taskModal) {
                    bsModal.hide();
                }
            });
        }
        
        // Show modal function
        function showModal() {
            if (bsModal) {
                bsModal.show();
            }
        }
        
        // Hide modal function
        function hideModal() {
            if (bsModal) {
                bsModal.hide();
            }
            
            // Ensure cleanup after the modal animation finishes
            setTimeout(function() {
                document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
            }, 300);
        }
        
        // Set up event listeners
        if (closeBtn) {
            closeBtn.addEventListener('click', hideModal);
        }
        
        // Add event listener when modal is hidden
        if (taskModal) {
            taskModal.addEventListener('hidden.bs.modal', function() {
                // Clean up any lingering backdrops or styles
                document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
            });
        }
        
        // Add submit button handling
        document.querySelectorAll('.submit-task-btn').forEach(button => {
            button.addEventListener('click', function() {
                const taskId = this.getAttribute('data-task-id');
                const taskTitle = this.getAttribute('data-task-title');
                const taskStart = this.getAttribute('data-task-start');
                const taskEnd = this.getAttribute('data-task-end');
                const taskDescription = this.getAttribute('data-task-description');
                const taskPdf = this.getAttribute('data-task-pdf');
                const taskLocked = this.getAttribute('data-task-locked') === 'true';
                
                // Set modal content
                document.getElementById('task-title').textContent = taskTitle;
                document.getElementById('task-start').textContent = taskStart;
                document.getElementById('task-end').textContent = taskEnd;
                document.getElementById('submission-task-id').value = taskId;
                
                // Handle locked task
                if (taskLocked) {
                    const modalBody = document.querySelector('#taskSubmitModal .modal-body');
                    const submissionSection = modalBody.querySelector('.submission-section');
                    const uploadArea = document.getElementById('upload-area');
                    const submitButton = document.querySelector('#submit-button-container button[type="submit"]');
                    
                    // Add locked warning
                    if (!submissionSection.querySelector('.alert-warning')) {
                        const warningDiv = document.createElement('div');
                        warningDiv.className = 'alert alert-warning mb-3';
                        warningDiv.innerHTML = '<i class="bi bi-lock-fill me-2"></i> This task has been locked by your teacher. You cannot submit at this time.';
                        submissionSection.insertBefore(warningDiv, submissionSection.firstChild.nextSibling);
                    }
                    
                    // Hide upload area and disable submit button
                    if (uploadArea) uploadArea.style.display = 'none';
                    if (submitButton) {
                        submitButton.disabled = true;
                        submitButton.innerHTML = '<i class="bi bi-lock-fill me-2"></i> Submit Locked';
                        submitButton.classList.add('btn-secondary');
                        submitButton.classList.remove('btn-primary');
                    }
                } else {
                    // Ensure elements are in their default state
                    const modalBody = document.querySelector('#taskSubmitModal .modal-body');
                    const warningDiv = modalBody.querySelector('.alert-warning');
                    const uploadArea = document.getElementById('upload-area');
                    const submitButton = document.querySelector('#submit-button-container button[type="submit"]');
                    
                    // Remove any warning messages
                    if (warningDiv) warningDiv.remove();
                    
                    // Show upload area and enable submit button
                    if (uploadArea) uploadArea.style.display = 'block';
                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.innerHTML = 'Submit Task';
                        submitButton.classList.add('btn-primary');
                        submitButton.classList.remove('btn-secondary');
                    }
                }
                
                // Handle description
                const descriptionSection = document.getElementById('task-description-section');
                if (taskDescription && taskDescription.trim() !== '') {
                    document.getElementById('task-description').textContent = taskDescription;
                    descriptionSection.style.display = 'block';
                } else {
                    descriptionSection.style.display = 'none';
                }
                
                // Handle PDF display
                const pdfSection = document.getElementById('task-pdf-section');
                if (taskPdf) {
                    pdfSection.style.display = 'block';
                    document.getElementById('view-pdf-link').href = taskPdf;
                    document.getElementById('download-pdf-link').href = taskPdf;
                } else {
                    pdfSection.style.display = 'none';
                }
            });
        });
        
        // Handle file upload
        const uploadArea = document.getElementById('upload-area');
        const fileInput = document.getElementById('submission-file');
        const fileSelected = document.getElementById('file-selected');
        const selectedFilename = document.getElementById('selected-filename');
        const removeFileBtn = document.getElementById('remove-file');
        
        if (uploadArea && fileInput) {
            uploadArea.addEventListener('click', function() {
                fileInput.click();
            });
            
            fileInput.addEventListener('change', function() {
                if (fileInput.files.length > 0) {
                    const file = fileInput.files[0];
                    if (file.type === 'application/pdf') {
                        selectedFilename.textContent = file.name;
                        uploadArea.style.display = 'none';
                        fileSelected.style.display = 'block';
                    } else {
                        alert('Please upload a PDF file');
                        fileInput.value = '';
                    }
                }
            });
            
            if (removeFileBtn) {
                removeFileBtn.addEventListener('click', function() {
                    fileInput.value = '';
                    uploadArea.style.display = 'block';
                    fileSelected.style.display = 'none';
                });
            }
        }
    });
    </script>
    
    <!-- Real-time task lock/unlock listener -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Intercept XHR requests to detect lock status changes
        const originalXHROpen = XMLHttpRequest.prototype.open;
        XMLHttpRequest.prototype.open = function() {
            this.addEventListener('load', function() {
                if (this.responseURL && this.responseURL.includes('toggle_task_lock.php')) {
                    try {
                        const response = JSON.parse(this.responseText);
                        if (response.success && response.task_id && typeof response.is_locked !== 'undefined') {
                            console.log('Task lock status changed:', response);
                            updateTaskLockUI(response.task_id, response.is_locked);
                        }
                    } catch (e) {
                        console.error('Error parsing lock toggle response:', e);
                    }
                }
            });
            return originalXHROpen.apply(this, arguments);
        };
        
        // Function to update the UI when task lock status changes
        function updateTaskLockUI(taskId, isLocked) {
            // Find all task items with this ID
            const taskItems = document.querySelectorAll(`.task-list-item[data-task-id="${taskId}"]`);
            
            taskItems.forEach(taskItem => {
                // Get the submit button in this task item
                const submitBtn = taskItem.querySelector('.submit-task-btn');
                const submitBtnContainer = taskItem.querySelector('.submit-button-container');
                
                // Check for submission container
                const submissionContainer = taskItem.querySelector('.submission-container');
                if (submissionContainer) {
                    // Get or add the unsubmit button container
                    let unsubmitBtn = submissionContainer.querySelector('.unsubmit-btn');
                    const submissionContent = submissionContainer.querySelector('.submission-content');
                    
                    if (isLocked) {
                        // Hide the unsubmit button if task is locked
                        if (unsubmitBtn) {
                            unsubmitBtn.style.display = 'none';
                        }
                    } else {
                        // Show the unsubmit button if task is unlocked
                        if (unsubmitBtn) {
                            unsubmitBtn.style.display = 'inline-block';
                        } else {
                            // Create unsubmit button if it doesn't exist
                            const fileWrapper = submissionContent.querySelector('.file-wrapper');
                            if (fileWrapper && submissionContent) {
                                const taskIdVal = taskItem.getAttribute('data-task-id');
                                const classId = new URLSearchParams(window.location.search).get('id');
                                
                                unsubmitBtn = document.createElement('a');
                                unsubmitBtn.href = `unsubmit_task.php?task_id=${taskIdVal}&redirect=tasks_list.php?id=${classId}`;
                                unsubmitBtn.className = 'btn btn-sm btn-outline-danger unsubmit-btn';
                                unsubmitBtn.innerHTML = '<i class="bi bi-x-circle me-1"></i> Unsubmit';
                                unsubmitBtn.onclick = function() {
                                    return confirm('Are you sure you want to remove this submission?');
                                };
                                
                                submissionContent.appendChild(unsubmitBtn);
                            }
                        }
                    }
                }
                
                // If the task has no submission and is not upcoming
                if (submitBtn && !taskItem.classList.contains('upcoming')) {
                    if (isLocked) {
                        // Replace with locked button
                        const tooltipHtml = `
                            <div class="tooltip-container">
                                <button type="button" class="btn btn-secondary btn-sm submit-btn locked" disabled>
                                    <i class="bi bi-lock-fill"></i> Submit
                                </button>
                                <span class="tooltip-text">This task has been locked by your teacher. You cannot submit at this time.</span>
                            </div>
                        `;
                        submitBtnContainer.innerHTML = tooltipHtml;
                    } else {
                        // Restore the submit button
                        const btnHtml = `
                            <button type="button" class="btn btn-primary btn-sm submit-task-btn" data-bs-toggle="modal" 
                                data-bs-target="#taskSubmitModal" data-task-id="${taskId}" 
                                data-task-title="${submitBtn.getAttribute('data-task-title')}" 
                                data-task-class="${submitBtn.getAttribute('data-task-class')}" 
                                data-task-start="${submitBtn.getAttribute('data-task-start')}" 
                                data-task-end="${submitBtn.getAttribute('data-task-end')}" 
                                data-task-description="${submitBtn.getAttribute('data-task-description') || ''}" 
                                data-task-pdf="${submitBtn.getAttribute('data-task-pdf') || ''}" 
                                data-task-locked="false">
                                <i class="bi bi-upload"></i> Submit
                            </button>
                        `;
                        submitBtnContainer.innerHTML = btnHtml;
                        
                        const newBtn = submitBtnContainer.querySelector('.submit-task-btn');
                        if (newBtn) {
                            newBtn.addEventListener('click', handleSubmitButtonClick);
                        }
                    }
                }
                
                taskItem.setAttribute('data-task-locked', isLocked ? 'true' : 'false');
            });
            
            // If the task modal is currently open for this task, update it
            const modalTaskId = document.getElementById('submission-task-id')?.value;
            if (modalTaskId === taskId.toString()) {
                // Re-trigger the same logic as clicking the button
                const isLocked = document.querySelector(`[data-task-id="${taskId}"]`).getAttribute('data-task-locked') === 'true';
                updateModalForLockedTask(isLocked);
            }
        }
        
        // Helper function to update modal for locked tasks
        function updateModalForLockedTask(isLocked) {
            const modalBody = document.querySelector('#taskSubmitModal .modal-body');
            const submissionSection = modalBody.querySelector('.submission-section');
            const uploadArea = document.getElementById('upload-area');
            const submitButton = document.querySelector('#submit-button-container button[type="submit"]');
            
            if (isLocked) {
                // Add locked warning if not already present
                if (!submissionSection.querySelector('.alert-warning')) {
                    const warningDiv = document.createElement('div');
                    warningDiv.className = 'alert alert-warning mb-3';
                    warningDiv.innerHTML = '<i class="bi bi-lock-fill me-2"></i> This task has been locked by your teacher. You cannot submit at this time.';
                    submissionSection.insertBefore(warningDiv, submissionSection.firstChild.nextSibling);
                }
                
                // Hide upload area and disable submit button
                if (uploadArea) uploadArea.style.display = 'none';
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<i class="bi bi-lock-fill me-2"></i> Submit Locked';
                    submitButton.classList.add('btn-secondary');
                    submitButton.classList.remove('btn-primary');
                }
            } else {
                // Remove any warning messages
                const warningDiv = submissionSection.querySelector('.alert-warning');
                if (warningDiv) warningDiv.remove();
                
                // Show upload area and enable submit button
                if (uploadArea) uploadArea.style.display = 'block';
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.innerHTML = 'Submit Task';
                    submitButton.classList.add('btn-primary');
                    submitButton.classList.remove('btn-secondary');
                }
            }
        }
        
        // Extract the submit button click handler to reuse it
        function handleSubmitButtonClick() {
            const taskId = this.getAttribute('data-task-id');
            const taskTitle = this.getAttribute('data-task-title');
            const taskStart = this.getAttribute('data-task-start');
            const taskEnd = this.getAttribute('data-task-end');
            const taskDescription = this.getAttribute('data-task-description');
            const taskPdf = this.getAttribute('data-task-pdf');
            const taskLocked = this.getAttribute('data-task-locked') === 'true';
            
            // Set modal content
            document.getElementById('task-title').textContent = taskTitle;
            document.getElementById('task-start').textContent = taskStart;
            document.getElementById('task-end').textContent = taskEnd;
            document.getElementById('submission-task-id').value = taskId;
            
            // Handle locked task
            updateModalForLockedTask(taskLocked);
            
            // Handle description
            const descriptionSection = document.getElementById('task-description-section');
            if (taskDescription && taskDescription.trim() !== '') {
                document.getElementById('task-description').textContent = taskDescription;
                descriptionSection.style.display = 'block';
            } else {
                descriptionSection.style.display = 'none';
            }
            
            // Handle PDF display
            const pdfSection = document.getElementById('task-pdf-section');
            if (taskPdf) {
                pdfSection.style.display = 'block';
                document.getElementById('view-pdf-link').href = taskPdf;
                document.getElementById('download-pdf-link').href = taskPdf;
            } else {
                pdfSection.style.display = 'none';
            }
        }
        
        // Attach the handler to existing buttons
        document.querySelectorAll('.submit-task-btn').forEach(btn => {
            btn.removeEventListener('click', handleSubmitButtonClick);
            btn.addEventListener('click', handleSubmitButtonClick);
        });
    });
    </script>
</body>
</html> 