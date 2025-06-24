<?php
session_start();
require_once '../config.php';

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

$class_id = $_GET['id'];
$student_id = $_SESSION['user_id'];

// Verify that the student is enrolled in this class
$stmt = $pdo->prepare("
    SELECT c.* 
    FROM classes c
    JOIN class_enrollments ce ON c.id = ce.class_id
    WHERE c.id = ? AND ce.student_id = ?
");
$stmt->execute([$class_id, $student_id]);
$class = $stmt->fetch();

if (!$class) {
    $_SESSION['error'] = "Class not found or you don't have permission to access it";
    header("Location: dashboard.php");
    exit();
}

// Get teacher information
$stmt = $pdo->prepare("SELECT u.username, u.profile_image FROM users u WHERE u.id = ?");
$stmt->execute([$class['teacher_id']]);
$teacher = $stmt->fetch();

// Get current month and year
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Validate month and year
if ($month < 1 || $month > 12) {
    $month = date('n');
}
if ($year < 2000 || $year > 2100) {
    $year = date('Y');
}

// Get number of days in the month
$num_days = cal_days_in_month(CAL_GREGORIAN, $month, $year);

// Get the first day of the month
$first_day_timestamp = mktime(0, 0, 0, $month, 1, $year);
$first_day_of_week = date('N', $first_day_timestamp);

// Previous and next month navigation
$prev_month = $month - 1;
$prev_year = $year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}

$next_month = $month + 1;
$next_year = $year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}

// Fetch tasks for this month and class
$month_start = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-01 00:00:00';
$month_end = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($num_days, 2, '0', STR_PAD_LEFT) . ' 23:59:59';

// Advanced query to get tasks information
$stmt = $pdo->prepare("
    SELECT t.*, 
           CASE 
               WHEN EXISTS (SELECT 1 FROM submissions s WHERE s.task_id = t.id AND s.student_id = ?) THEN 1 
               ELSE 0 
           END as has_submission
    FROM tasks t
    WHERE t.class_id = ? 
    AND ((t.start_datetime BETWEEN ? AND ?) 
    OR (t.end_datetime BETWEEN ? AND ?)
    OR (t.start_datetime <= ? AND t.end_datetime >= ?))
    ORDER BY t.start_datetime
");
$stmt->execute([$student_id, $class_id, $month_start, $month_end, $month_start, $month_end, $month_start, $month_end]);
$tasks = $stmt->fetchAll();

// Organize tasks by day
$task_by_day = [];
foreach ($tasks as $task) {
    // For start date
    $start_day = date('j', strtotime($task['start_datetime']));
    $start_month = date('n', strtotime($task['start_datetime']));
    $start_year = date('Y', strtotime($task['start_datetime']));
    
    // For end date
    $end_day = date('j', strtotime($task['end_datetime']));
    $end_month = date('n', strtotime($task['end_datetime']));
    $end_year = date('Y', strtotime($task['end_datetime']));
    
    // Add task to start day
    if ($start_month == $month && $start_year == $year) {
        if (!isset($task_by_day[$start_day])) {
            $task_by_day[$start_day] = [];
        }
        $task_by_day[$start_day][] = [
            'id' => $task['id'],
            'title' => $task['title'],
            'type' => 'start',
            'datetime' => $task['start_datetime'],
            'grades_sent' => $task['grades_sent'],
            'has_submission' => $task['has_submission'],
        ];
    }
    
    // Add task to end day
    if ($end_month == $month && $end_year == $year) {
        if (!isset($task_by_day[$end_day])) {
            $task_by_day[$end_day] = [];
        }
        $task_by_day[$end_day][] = [
            'id' => $task['id'],
            'title' => $task['title'],
            'type' => 'end',
            'datetime' => $task['end_datetime'],
            'grades_sent' => $task['grades_sent'],
            'has_submission' => $task['has_submission'],
        ];
    }
}

// Set page variables
$page_title = htmlspecialchars($class['name']) . ' - Calendar';
$navbar_title = 'Class: ' . htmlspecialchars($class['name']);
$active_page = 'calendar';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Student Task Tracking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <!-- Add CSS styles inline -->
    <style>
    .main-content {
        margin-left: 180px;
        padding: 20px;
        padding-top: 68px;
    }

    .calendar-container {
        box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
        border-radius: 8px;
        background-color: #fff;
        margin-bottom: 30px;
        overflow: hidden;
    }

    .calendar-header {
        padding: 12px 15px;
        background-color: #fff;
        border-bottom: 1px solid #e0e0e0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .calendar-title {
        font-size: 22px;
        font-weight: 400;
        color: #3c4043;
        margin: 0;
    }

    .calendar-controls {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .btn-calendar {
        background-color: #f1f3f4;
        color: #3c4043;
        border: none;
        padding: 8px 12px;
        border-radius: 4px;
        font-size: 14px;
        cursor: pointer;
        transition: background-color 0.2s;
    }

    .btn-calendar:hover {
        background-color: #e8eaed;
    }

    .calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
    }

    .calendar-day-header {
        text-align: center;
        padding: 10px;
        font-size: 12px;
        font-weight: 500;
        color: #70757a;
        background-color: #f1f3f4;
        border-bottom: 1px solid #e0e0e0;
    }

    .calendar-day {
        min-height: 100px;
        border: 1px solid #e0e0e0;
        padding: 8px;
        position: relative;
        overflow: hidden;
    }

    .calendar-day:hover {
        background-color: #f8f9fa;
    }

    .calendar-day.empty {
        background-color: #f8f9fa;
    }

    .calendar-day.today {
        background-color: #e8f0fe;
    }

    .day-number {
        font-size: 14px;
        text-align: left;
        color: #70757a;
        font-weight: 500;
        position: absolute;
        top: 8px;
        left: 8px;
    }

    .today .day-number {
        color: #1a73e8;
        font-weight: 700;
        position: relative;
    }

    .today .day-number:after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        right: 0;
        height: 2px;
        background-color: #1a73e8;
        border-radius: 4px;
        display: none; /* Hide the underline */
    }

    .task-item {
        margin-bottom: 4px;
        padding: 3px 8px;
        font-size: 12px;
        border-radius: 4px;
        cursor: pointer;
        color: white;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        transition: transform 0.1s;
    }

    .task-item:hover {
        transform: translateY(-1px);
        box-shadow: 0 1px 3px rgba(0,0,0,0.2);
    }

    .task-start {
        background-color: #4285f4;
        border-left: 3px solid #1967d2;
    }

    .task-end {
        background-color: #ea4335;
        border-left: 3px solid #c5221f;
    }

    /* Modal styling */
    .modal-header.task-modal-header {
        background-color: #1a73e8;
        color: white;
    }

    .modal-content {
        border-radius: 8px;
        border: none;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    }

    .btn-close.white-close {
        filter: brightness(0) invert(1);
    }

    /* Submit button styling */
    .btn-submit-task {
        background-color: #1a73e8;
        border-color: #1a73e8;
        padding: 8px 16px;
        font-weight: 500;
    }

    .btn-submit-task:hover {
        background-color: #1765cc;
        border-color: #1765cc;
    }

    /* Task title styling */
    .task-title-header {
        color: #202124;
        font-weight: 500;
        border-bottom: 1px solid #e0e0e0;
        padding-bottom: 15px;
    }

    /* PDF display in task modal */
    .pdf-container {
        background-color: #f8f9fa;
        border-radius: 6px;
        padding: 12px;
        border: 1px solid #e9ecef;
        margin-top: 10px;
        display: flex;
        align-items: center;
    }

    .pdf-icon {
        color: #dc3545;
        font-size: 1.5rem;
        margin-right: 0.75rem;
    }

    .pdf-actions {
        display: flex;
        margin-left: auto;
    }

    .pdf-actions a {
        padding: 4px 10px;
        font-size: 13px;
    }

    .pdf-title {
        margin: 0;
        font-weight: 500;
    }

    /* Task status badges */
    .badge.bg-secondary {
        background-color: #6c757d !important;
    }

    .badge.bg-warning {
        background-color: #ffc107 !important;
        color: #212529 !important;
        font-weight: 600 !important;
    }

    .badge.bg-success {
        background-color: #28a745 !important;
    }

    .badge.bg-overdue {
        background-color: #ea4335 !important;
    }
    
    .badge.bg-completed {
        background-color: #dc3545 !important;
    }

    /* Weekend styling */
    .calendar-day-header:nth-child(6),
    .calendar-day-header:nth-child(7) {
        color: #ea4335;
    }

    /* File submission styles */
    .submission-section {
        background-color: #f0f7ff;
        border-radius: 6px;
        padding: 15px;
        margin-top: 15px;
        margin-bottom: 15px;
        border: 1px solid #cfe2ff;
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
    
    .bg-light-hover {
        background-color: #e9ecef;
        border-color: #007bff;
    }
    
    .file-selected {
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
    
    .cursor-pointer {
        cursor: pointer;
    }
    
    /* Submission display */
    .submission-container {
        background-color: #f0f9f2;
        border: 1px solid #c8e6c9;
        border-radius: 6px;
        margin-bottom: 15px;
        overflow: hidden;
    }
    
    .submission-header {
        background-color: #c8e6c9;
        color: #388e3c;
        padding: 8px 12px;
        font-weight: 600;
        font-size: 14px;
    }
    
    .submission-content {
        padding: 10px;
        position: relative;
        padding-bottom: 45px;
    }
    
    .file-btn {
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
        color: white;
        border-radius: 4px;
        transition: background-color 0.2s;
        text-decoration: none;
    }
    
    .file-btn:hover {
        background-color: #218838;
        border-color: #1e7e34;
        text-decoration: none;
        color: white;
    }
    
    .unsubmit-btn {
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
        transition: background-color 0.15s ease-in-out, color 0.15s ease-in-out;
    }
    
    .unsubmit-btn:hover {
        background-color: #dc3545;
        color: white;
        border-color: #dc3545;
    }

    /* Responsive styles */
    @media (max-width: 768px) {
        .calendar-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .calendar-controls {
            margin-top: 10px;
            flex-wrap: wrap;
        }
        
        .calendar-grid {
            grid-template-columns: 1fr;
        }
        
        .calendar-day-header {
            display: none;
        }
        
        .calendar-day {
            border-bottom: 2px solid #e0e0e0;
            margin-bottom: 8px;
        }
        
        .day-number::before {
            content: attr(data-day-name);
            margin-right: 8px;
            font-weight: 400;
        }
    }

    .task-container {
        margin-top: 26px;
    }

    /* Teacher info section */
    .teacher-info {
        display: flex;
        align-items: center;
        margin-bottom: 15px;
        font-size: 14px;
    }

    .teacher-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background-color: #1a73e8;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 500;
        font-size: 14px;
        margin-right: 10px;
        border: 2px solid #fff;
        box-shadow: 0 1px 3px rgba(0,0,0,0.2);
    }

    .teacher-name {
        margin: 0;
        font-weight: 500;
    }

    .text-pre-wrap {
        white-space: pre-wrap;
        background-color: #f8f9fa;
        padding: 12px;
        border-radius: 6px;
        border: 1px solid #e9ecef;
        margin-top: 10px;
        min-height: 100px;
    }

    .document-label, .description-label {
        margin-bottom: 10px;
        font-weight: 600;
        color: #333;
    }
    </style>
</head>
<body>
    <?php include_once '../includes/student_sidebar.php'; ?>
    
    <!-- Custom Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
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
        <div class="content-wrapper">
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
                
                <!-- Teacher info -->
                <div class="teacher-info">
                    <?php if (!empty($teacher['profile_image'])): ?>
                        <div class="teacher-avatar" style="background-image: url('../uploads/profile_images/<?php echo htmlspecialchars($teacher['profile_image']); ?>'); background-size: cover; background-color: transparent;"></div>
                    <?php else: ?>
                        <div class="teacher-avatar">
                            <?php echo strtoupper(substr($teacher['username'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    <p class="teacher-name">Teacher: <?php echo htmlspecialchars($teacher['username']); ?></p>
                </div>

                <div class="calendar-container">
                    <div class="calendar-header">
                        <h2 class="calendar-title"><?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?></h2>
                        <div class="calendar-controls">
                            <a href="class_details.php?id=<?php echo $class_id; ?>&month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>" class="btn btn-calendar">
                                <i class="bi bi-chevron-left"></i> Previous
                            </a>
                            <a href="class_details.php?id=<?php echo $class_id; ?>" class="btn btn-calendar">Today</a>
                            <a href="class_details.php?id=<?php echo $class_id; ?>&month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>" class="btn btn-calendar">
                                Next <i class="bi bi-chevron-right"></i>
                            </a>
                        </div>
                    </div>
                    
                    <div class="calendar-grid">
                        <!-- Days of week header -->
                        <div class="calendar-day-header">Mon</div>
                        <div class="calendar-day-header">Tue</div>
                        <div class="calendar-day-header">Wed</div>
                        <div class="calendar-day-header">Thu</div>
                        <div class="calendar-day-header">Fri</div>
                        <div class="calendar-day-header">Sat</div>
                        <div class="calendar-day-header">Sun</div>
                        
                        <!-- Empty cells before the first day of the month -->
                        <?php for ($i = 1; $i < $first_day_of_week; $i++): ?>
                            <div class="calendar-day empty"></div>
                        <?php endfor; ?>
                        
                        <!-- Days of the month -->
                        <?php 
                        $current_day = date('j');
                        $current_month = date('n');
                        $current_year = date('Y');
                        
                        for ($day = 1; $day <= $num_days; $day++): 
                            $is_today = ($day == $current_day && $month == $current_month && $year == $current_year);
                            $day_class = $is_today ? 'calendar-day today' : 'calendar-day';
                            $date_ymd = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
                            
                            // Get day name for responsive view
                            $day_name = date('D', mktime(0, 0, 0, $month, $day, $year));
                        ?>
                            <div class="<?php echo $day_class; ?>">
                                <div class="day-number" data-day-name="<?php echo $day_name; ?>"><?php echo $day; ?></div>
                                
                                <!-- Tasks for this day -->
                                <div class="task-container">
                                    <?php if (isset($task_by_day[$day])): ?>
                                        <?php foreach ($task_by_day[$day] as $task_item): ?>
                                            <div class="task-item task-<?php echo $task_item['type']; ?>" 
                                                onclick="openTaskDetails(<?php echo $task_item['id']; ?>)">
                                                <?php echo htmlspecialchars($task_item['title']); ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endfor; ?>
                        
                        <!-- Empty cells after the last day of the month -->
                        <?php 
                        $last_day_of_week = date('N', mktime(0, 0, 0, $month, $num_days, $year));
                        for ($i = $last_day_of_week + 1; $i <= 7; $i++): 
                        ?>
                            <div class="calendar-day empty"></div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View Task Modal -->
    <div class="modal fade" id="viewTaskModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header task-modal-header">
                    <h5 class="modal-title">Task Details</h5>
                    <button type="button" class="btn-close white-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div id="taskDetailsContent">
                        <div class="d-flex justify-content-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="task_ajax.js?v=<?php echo time(); ?>"></script>
    <script src="submission_fix.js?v=<?php echo time(); ?>"></script>
</body>
</html>