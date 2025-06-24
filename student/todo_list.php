<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    $_SESSION['error'] = "You must be logged in as a student to view this page.";
    header("Location: ../login.php");
    exit();
}

// Get student information
$student_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    $_SESSION['error'] = "Student information not found.";
    header("Location: ../login.php");
    exit();
}

$active_page = 'todo';

$stmt = $pdo->prepare("
    SELECT c.id, c.name, c.color 
    FROM classes c
    JOIN class_enrollments ce ON c.id = ce.class_id
    WHERE ce.student_id = ?
");
$stmt->execute([$student_id]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$class_ids = [];
foreach ($classes as $class) {
    $class_ids[] = $class['id'];
}

// Array to hold ongoing tasks
$ongoing_tasks = array();

// Get ongoing tasks for these classes
if (!empty($class_ids)) {
    $placeholders = implode(',', array_fill(0, count($class_ids), '?'));
    
    // Force PHP to get a new timestamp
    date_default_timezone_set('Asia/Ho_Chi_Minh');
    $now = new DateTime('now');
    $current_date = $now->format('Y-m-d H:i:s');
    

    
    // Update query to use NOW() directly in SQL for better time accuracy
    $query = "SELECT t.*, c.name as class_name, c.color as class_color 
              FROM tasks t 
              JOIN classes c ON t.class_id = c.id 
              WHERE t.class_id IN ($placeholders)
              AND t.start_datetime <= NOW()
              AND t.end_datetime >= NOW()
              ORDER BY t.end_datetime ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($class_ids);
    $ongoing_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    

}

// Get submission details for the tasks
$task_ids = array_column($ongoing_tasks, 'id');
$submissions = [];
if (!empty($task_ids)) {
    $placeholders = implode(',', array_fill(0, count($task_ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM submissions WHERE student_id = ? AND task_id IN ($placeholders)");
    $stmt->execute(array_merge([$student_id], $task_ids));
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $submissions[$row['task_id']] = $row;
    }
}

$page_title = "To-do List";

// Add auto-refresh meta tag to ensure page stays updated
$auto_refresh = true;

// Calculate the todo_count for sidebar - only count tasks without submissions
$todo_count = 0;
foreach ($ongoing_tasks as $task) {
    if (!isset($submissions[$task['id']])) {
        $todo_count++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if ($auto_refresh): ?>
    <meta http-equiv="refresh" content="300"> <!-- Auto refresh every 5 minutes -->
    <?php endif; ?>
    <title><?php echo $page_title; ?> - Student Task Tracking</title>
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

        /* Task styling */
        .task-list-item {
            background-color: #fff;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            margin-bottom: 15px;
            position: relative;
            min-height: 170px;
            overflow: hidden;
        }
        
        .task-list-item.yellow-border {
            border-left: 4px solid #ffc107;
        }
        
        .task-list-item.green-border {
            border-left: 4px solid #28a745;
        }
        
        .badge.bg-ongoing {
            background-color: #ffc107 !important;
            color: #212529;
        }
        
        .badge.bg-not-started {
            background-color: #6c757d !important;
        }
        
        .badge.bg-in-progress {
            background-color: #0d6efd !important;
        }
        
        .badge.bg-completed {
            background-color: #198754 !important;
            color: #fff;
        }
        
        .badge.bg-warning {
            background-color: #ffc107 !important;
            color: #212529 !important;
            font-weight: 600 !important;
        }
        
        .task-list-item.completed-task {
            border-left: 4px solid #198754;
        }
        
        .task-content {
            padding: 15px;
            position: relative;
        }
        
        .task-file-section {
            padding: 10px 15px;
            background-color: #f8f9fa;
            border-top: 1px solid #e0e0e0;
            border-radius: 0 0 8px 4px;
        }
        
        .days-left {
            color: #ffc107;
            font-weight: 600;
        }
        
        .days-left.urgent {
            color: #dc3545;
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
        
        /* New Submission Container Styling */
        .submission-container {
            background-color: #f0f9f2;
            border: 1px solid #c8e6c9;
            border-radius: 6px;
            width: 280px;
            margin-bottom: 15px;
            text-align: left;
            overflow: hidden;
            font-size: 0.9rem;
            max-width: 100%;
        }
        
        .submission-header {
            background-color: white;
            color: #1e7e34;
            padding: 5px 12px;
            font-weight: bold;
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
        
        .submission-content .file-btn i {
            color: white;
            margin-right: 5px;
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
            cursor: pointer;
            text-decoration: none;
            border: 1px solid #dc3545;
        }
        
        .submission-content .unsubmit-btn:hover {
            background-color: #dc3545;
            color: white;
            border-color: #dc3545;
        }

        /* Disabled unsubmit button styling */
        .unsubmit-btn.disabled {
            color: #6c757d;
            border-color: #6c757d;
            background-color: #f8f9fa;
            opacity: 0.65;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        /* Tooltip styling for disabled elements */
        [data-tooltip] {
            position: relative;
        }
        
        [data-tooltip]:after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 130%;
            left: 50%;
            transform: translateX(-50%);
            padding: 4px 8px;
            background-color: rgba(0, 0, 0, 0.8);
            color: white;
            font-size: 12px;
            border-radius: 4px;
            white-space: nowrap;
            visibility: hidden;
            opacity: 0;
            transition: opacity 0.3s;
            z-index: 1000;
        }
        
        [data-tooltip]:hover:after {
            visibility: visible;
            opacity: 1;
        }

        /* Task Modal Styling */
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
        
        /* Submission action buttons */
        .submission-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-top: 10px;
        }
        
        .view-file-btn {
            background-color: #007bff;
        }
        
        .unsubmit-btn {
            background-color: #ffffff;
            color: #dc3545;
            border-color: #dc3545;
        }
        
        .unsubmit-btn:hover {
            background-color: #f8d7da;
            color: #dc3545;
            border-color: #dc3545;
        }

        .float-end {
            max-width: 280px;
            margin-left: 10px;
            height: fit-content;
            align-self: flex-start;
        }
        
        .task-content:after {
            content: "";
            display: table;
            clear: both;
        }

        @media (max-width: 767px) {
            .task-content {
                flex-direction: column-reverse;
            }
            
            .task-info {
                width: 100%;
                padding-right: 0;
            }
            
            .float-end {
                float: none !important;
                margin-left: 0;
                max-width: 100%;
                margin-bottom: 15px;
                width: 100%;
            }
            
            .submission-container {
                width: 100%;
            }
        }
    </style>
</head>
<body>
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
                    <h2>To-do List</h2>
                </div>
                
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

                <div class="container-fluid p-0">
                    <?php if (empty($ongoing_tasks)): ?>
                        <div class="empty-state">
                            <i class="bi bi-check-all"></i>
                            <h4>All caught up!</h4>
                            <p>You have no ongoing tasks currently. Check back later!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($ongoing_tasks as $task): 
                            // Calculate days remaining
                            $now = new DateTime();
                            $now->setTime(0, 0, 0);
                            $end_date = new DateTime($task['end_datetime']);
                            $start_date = new DateTime($task['start_datetime']);
                            $end_date_for_days = clone $end_date;
                            $end_date_for_days->setTime(0, 0, 0);
                            
                            $interval = $now->diff($end_date_for_days);
                            $days_remaining = $interval->days;
                            if ($interval->invert) {
                                $days_remaining = 0;
                            }
                            
                            $days_text = "";
                            $urgent_class = "";
                            
                            if ($days_remaining === 0) {
                                $days_text = 'Due today!';
                                $urgent_class = "urgent";
                            } elseif ($days_remaining === 1) {
                                $days_text = '1 day left';
                                $urgent_class = "urgent";
                            } else {
                                $days_text = $days_remaining . ' days left';
                            }
                        ?>
                            <div class="task-list-item yellow-border">
                                <div class="task-content">
                                    <div class="float-end">
                                        <div class="d-flex flex-column align-items-end">
                                            <span class="badge bg-ongoing mb-2">Ongoing</span>
                                            
                                            <?php if (isset($submissions[$task['id']])): 
                                                $sub = $submissions[$task['id']]; 
                                                $file_path = 'uploads/student_submissions/' . $student_id . '/' . $sub['filename'];
                                            ?>
                                                <div class="submission-container">
                                                    <div class="submission-header">Your Submission</div>
                                                    <div class="submission-content">
                                                        <div class="file-wrapper">
                                                            <a href="../<?php echo htmlspecialchars($file_path); ?>" 
                                                               target="_blank" 
                                                               class="btn file-btn">
                                                                <i class="bi bi-file-earmark-pdf"></i> 
                                                                <?php echo htmlspecialchars($sub['filename']); ?>
                                                            </a>
                                                        </div>
                                                        <a href="javascript:void(0)" 
                                                           class="btn unsubmit-btn" 
                                                           data-task-id="<?php echo $task['id']; ?>">
                                                            <i class="bi bi-x-circle"></i> Unsubmit
                                                        </a>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-sm btn-primary submit-task-btn" data-task-id="<?php echo $task['id']; ?>" data-bs-toggle="modal" data-bs-target="#taskSubmitModal" data-task-title="<?php echo htmlspecialchars($task['title']); ?>" data-task-class="<?php echo htmlspecialchars($task['class_name']); ?>" data-task-start="<?php echo $start_date->format('M j, Y - g:i A'); ?>" data-task-end="<?php echo $end_date->format('M j, Y - g:i A'); ?>" data-task-description="<?php echo htmlspecialchars($task['description'] ?? ''); ?>" data-task-pdf="<?php echo !empty($task['pdf_file']) ? '../' . $task['pdf_file'] : ''; ?>">
                                                    <i class="bi bi-upload"></i> Submit
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <h5><?php echo htmlspecialchars($task['title']); ?> <span class="text-muted">(Class: <?php echo htmlspecialchars($task['class_name']); ?>)</span></h5>
                                    
                                    <div class="mb-1">
                                        <i class="bi bi-calendar-event"></i> 
                                        Start: <?php echo $start_date->format('M j, Y - g:i A'); ?>
                                    </div>
                                    <div>
                                        <i class="bi bi-calendar-check"></i> 
                                        Due: <?php echo $end_date->format('M j, Y - g:i A'); ?>
                                        <span class="ms-2 days-left <?php echo $urgent_class; ?>">
                                            (<?php echo $days_text; ?>)
                                        </span>
                                    </div>
                                    
                                    <?php if (!empty($task['description'])): ?>
                                        <p class="mt-2"><?php echo htmlspecialchars($task['description']); ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($task['pdf_file'])): ?>
                                        <div class="mt-2">
                                            <i class="bi bi-file-pdf text-danger"></i>
                                            Task File
                                            <a href="../<?php echo $task['pdf_file']; ?>" target="_blank" class="btn btn-sm btn-primary ms-2">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                            <a href="../<?php echo $task['pdf_file']; ?>" download class="btn btn-sm btn-outline-primary ms-1">
                                                <i class="bi bi-download"></i> Download
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Task Submit Modal -->
    <div class="modal fade" id="taskSubmitModal" tabindex="-1" aria-labelledby="taskSubmitModalLabel" aria-hidden="true">
        <div class="modal-dialog" style="max-width: 550px;">
            <div class="modal-content">
                <div class="modal-header modal-task-header">
                    <h5 class="modal-title" id="taskSubmitModalLabel">Task Submission</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
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
                                                <i class="bi bi-eye"></i> Submit File
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const submitButtons = document.querySelectorAll('.submit-task-btn');
            submitButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const taskId = this.getAttribute('data-task-id');
                    const taskTitle = this.getAttribute('data-task-title');
                    const taskClass = this.getAttribute('data-task-class');
                    const taskStart = this.getAttribute('data-task-start');
                    const taskEnd = this.getAttribute('data-task-end');
                    const taskDescription = this.getAttribute('data-task-description');
                    const taskPdf = this.getAttribute('data-task-pdf');
                    
                    // Set modal content
                    document.getElementById('task-title').textContent = taskTitle;
                    document.getElementById('task-start').textContent = taskStart;
                    document.getElementById('task-end').textContent = taskEnd;
                    
                    // Handle description
                    const descriptionSection = document.getElementById('task-description-section');
                    if (taskDescription && taskDescription.trim() !== '') {
                        document.getElementById('task-description').textContent = taskDescription;
                        descriptionSection.style.display = 'block';
                    } else {
                        descriptionSection.style.display = 'none';
                    }
                    
                    document.getElementById('submission-task-id').value = taskId;
                    
                    // Handle PDF display
                    const pdfSection = document.getElementById('task-pdf-section');
                    if (taskPdf) {
                        pdfSection.style.display = 'block';
                        document.getElementById('view-pdf-link').href = taskPdf;
                        document.getElementById('download-pdf-link').href = taskPdf;
                    } else {
                        pdfSection.style.display = 'none';
                    }
                    
                    // Reset form elements
                    resetFormElements();
                    
                    // Check if there's existing submission
                    fetch('get_submission.php?task_id=' + taskId)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.submission) {
                                const filePath = 'uploads/student_submissions/' + <?php echo $student_id; ?> + '/' + data.submission.filename;
                                showSubmissionPreview(data.submission.filename, filePath);
                            } else {
                                hideSubmissionPreview();
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching submission data:', error);
                        });
                });
            });
            
            // Handle file upload
            const uploadArea = document.getElementById('upload-area');
            const fileInput = document.getElementById('submission-file');
            const fileSelected = document.getElementById('file-selected');
            const selectedFilename = document.getElementById('selected-filename');
            const removeFileBtn = document.getElementById('remove-file');
            
            uploadArea.addEventListener('click', function() {
                fileInput.click();
            });
            
            uploadArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('bg-light');
            });
            
            uploadArea.addEventListener('dragleave', function() {
                this.classList.remove('bg-light');
            });
            
            uploadArea.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('bg-light');
                if (e.dataTransfer.files.length) {
                    fileInput.files = e.dataTransfer.files;
                    handleFile();
                }
            });
            
            fileInput.addEventListener('change', handleFile);
            
            function handleFile() {
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
            }
            
            removeFileBtn.addEventListener('click', function() {
                fileInput.value = '';
                uploadArea.style.display = 'block';
                fileSelected.style.display = 'none';
            });
            
            // Function to reset form elements
            function resetFormElements() {
                fileInput.value = '';
                uploadArea.style.display = 'block';
                fileSelected.style.display = 'none';
            }
            
            // Handle unsubmit action
            const unsubmitButtons = document.querySelectorAll('.unsubmit-btn');
            unsubmitButtons.forEach(button => {
                button.addEventListener('click', function() {
                    if (confirm('Are you sure you want to remove your submission?')) {
                        const taskId = this.getAttribute('data-task-id');
                        window.location.href = 'unsubmit_task.php?task_id=' + taskId;
                    }
                });
            });

            function showSubmissionPreview(filename, filePath) {
                document.getElementById('submission-preview').style.display = 'block';
                document.getElementById('submission-filename').textContent = filename;
                document.getElementById('view-submission-link').href = filePath;
                document.getElementById('upload-area').style.display = 'none';
                document.getElementById('file-selected').style.display = 'none';
                document.getElementById('submit-button-container').style.display = 'none';
            }
            function hideSubmissionPreview() {
                document.getElementById('submission-preview').style.display = 'none';
                document.getElementById('upload-area').style.display = 'block';
                document.getElementById('submit-button-container').style.display = 'block';
                document.getElementById('file-selected').style.display = 'none';
            }
        });
    </script>
</body>
</html> 