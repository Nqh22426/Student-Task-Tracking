<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

// Check if task ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid task ID";
    header("Location: dashboard.php");
    exit();
}

$task_id = $_GET['id'];
$student_id = $_SESSION['user_id'];

// Get task details with class information and verify student access
$stmt = $pdo->prepare("
    SELECT t.*, c.name AS class_name, c.id AS class_id, c.color AS class_color, c.teacher_id
    FROM tasks t
    JOIN classes c ON t.class_id = c.id
    JOIN class_enrollments ce ON c.id = ce.class_id
    WHERE t.id = ? AND ce.student_id = ?
");
$stmt->execute([$task_id, $student_id]);
$task = $stmt->fetch();

if (!$task) {
    $_SESSION['error'] = "Task not found or you do not have access to it";
    header("Location: dashboard.php");
    exit();
}

// Get teacher information
$stmt = $pdo->prepare("SELECT u.username, u.profile_image FROM users u WHERE u.id = ?");
$stmt->execute([$task['teacher_id']]);
$teacher = $stmt->fetch();

// Get student's progress on this task
$stmt = $pdo->prepare("
    SELECT * FROM student_progress 
    WHERE task_id = ? AND student_id = ?
");
$stmt->execute([$task_id, $student_id]);
$progress = $stmt->fetch();

// If no progress record exists, set default values
if (!$progress) {
    $progress = [
        'status' => 'not_started',
        'submission_text' => null,
        'submission_date' => null
    ];
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_task'])) {
    $submission_text = $_POST['submission_text'] ?? '';
    $status = (!empty($submission_text)) ? 'completed' : 'in_progress';
    
    try {
        $pdo->beginTransaction();
        
        // Check if a progress record already exists
        if ($progress['status']) {
            // Update existing record
            $stmt = $pdo->prepare("
                UPDATE student_progress 
                SET status = ?, submission_text = ?, submission_date = NOW()
                WHERE task_id = ? AND student_id = ?
            ");
            $stmt->execute([$status, $submission_text, $task_id, $student_id]);
        } else {
            // Create new record
            $stmt = $pdo->prepare("
                INSERT INTO student_progress (task_id, student_id, status, submission_text, submission_date)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$task_id, $student_id, $status, $submission_text]);
        }
        
        $pdo->commit();
        
        $_SESSION['success'] = "Task submission updated successfully";
        header("Location: task_details.php?id=$task_id");
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error updating task submission: " . $e->getMessage();
    }
}

$page_title = "Task: " . htmlspecialchars($task['title']);
$navbar_title = 'Class: ' . htmlspecialchars($task['class_name']);
$active_page = 'tasks';
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
        /* Task Details Page Styling */
        .task-details-card {
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .task-header {
            padding: 15px 20px;
            background-color: <?php echo $task['class_color']; ?>;
            color: white;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .task-header h5 {
            margin: 0;
            font-weight: 600;
        }

        .task-body {
            padding: 20px;
        }

        .task-info {
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .task-info .row {
            margin-bottom: 10px;
        }

        .info-label {
            font-weight: 600;
            color: #555;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
        }

        .status-not-started {
            background-color: #f8f9fa;
            color: #6c757d;
            border: 1px solid #dee2e6;
        }

        .status-in-progress {
            background-color: #e1f5fe;
            color: #0288d1;
            border: 1px solid #b3e5fc;
        }

        .status-completed {
            background-color: #e8f5e9;
            color: #388e3c;
            border: 1px solid #c8e6c9;
        }

        .time-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            margin-left: 10px;
        }

        .time-upcoming {
            background-color: #fff8e1;
            color: #ffa000;
            border: 1px solid #ffecb3;
        }

        .time-ongoing {
            background-color: #e8eaf6;
            color: #3f51b5;
            border: 1px solid #c5cae9;
        }

        .time-past {
            background-color: #fce4ec;
            color: #d81b60;
            border: 1px solid #f8bbd0;
        }

        .task-description {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #3498db;
        }

        .progress-section {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }

        .progress-section h5 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #333;
        }

        .progress-bar-container {
            height: 25px;
            background-color: #e9ecef;
            border-radius: 4px;
            margin-bottom: 15px;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            background-color: #3498db;
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        .update-form {
            margin-top: 15px;
        }

        .action-buttons {
            margin-top: 20px;
        }

        .btn-update {
            background-color: #3498db;
            border-color: #3498db;
        }

        .btn-update:hover {
            background-color: #2980b9;
            border-color: #2980b9;
        }

        .btn-back {
            background-color: #6c757d;
            border-color: #6c757d;
        }

        .btn-back:hover {
            background-color: #5a6268;
            border-color: #5a6268;
        }

        .main-content {
            margin-left: 180px;
            padding: 20px;
            padding-top: 68px;
        }
        
        .task-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .task-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
            margin-bottom: 10px;
        }
        
        .task-dates {
            display: flex;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .task-date-item {
            flex: 1;
            min-width: 200px;
            margin-bottom: 10px;
        }
        
        .task-date-label {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 5px;
        }
        
        .task-date-value {
            font-size: 16px;
            font-weight: 500;
        }
        
        .submission-form {
            margin-top: 20px;
        }
        
        .submission-text {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
            white-space: pre-wrap;
        }
        
        /* Teacher info */
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

        /* PDF display */
        .task-pdf {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            background-color: #f8f9fa;
            margin-bottom: 20px;
        }
        
        .pdf-container {
            padding: 0;
        }
        
        .task-pdf i.bi-file-pdf {
            font-size: 2rem;
        }
    </style>
</head>
<body>
    <?php include_once '../includes/student_sidebar.php'; ?>
    <?php include_once '../includes/navbar.php'; ?>

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
                
                <!-- Task header -->
                <div class="task-header">
                    <div class="task-info">
                        <div>
                            <h2><?php echo htmlspecialchars($task['title']); ?></h2>
                            <p class="mb-0">Class: <?php echo htmlspecialchars($task['class_name']); ?></p>
                        </div>
                        <div>
                            <a href="tasks_list.php?id=<?php echo $task['class_id']; ?>" class="btn btn-outline-light">
                                <i class="bi bi-arrow-left"></i> Back to Tasks
                            </a>
                        </div>
                    </div>
                </div>
                
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
                
                <!-- Task details card -->
                <div class="task-card">
                    <div class="task-status status-<?php echo str_replace('_', '-', $progress['status']); ?>">
                        <?php 
                            switch($progress['status']) {
                                case 'not_started':
                                    echo 'Not Started';
                                    break;
                                case 'in_progress':
                                    echo 'In Progress';
                                    break;
                                case 'completed':
                                    echo 'Completed';
                                    break;
                                default:
                                    echo 'Not Started';
                            }
                        ?>
                    </div>
                    
                    <div class="task-dates">
                        <div class="task-date-item">
                            <div class="task-date-label">Start Date</div>
                            <div class="task-date-value"><?php echo date('F j, Y - g:i A', strtotime($task['start_datetime'])); ?></div>
                        </div>
                        <div class="task-date-item">
                            <div class="task-date-label">Due Date</div>
                            <div class="task-date-value">
                                <?php echo date('F j, Y - g:i A', strtotime($task['end_datetime'])); ?>
                                
                                <?php 
                                // Check if task is overdue or due soon
                                $now = new DateTime();
                                $due_date = new DateTime($task['end_datetime']);
                                $interval = $now->diff($due_date);
                                $is_overdue = $due_date < $now && $progress['status'] != 'completed';
                                $is_due_soon = !$is_overdue && $interval->days <= 3 && $progress['status'] != 'completed';
                                
                                if ($is_overdue): 
                                ?>
                                    <span class="badge bg-danger ms-2">Overdue!</span>
                                <?php elseif ($is_due_soon): ?>
                                    <span class="badge bg-warning text-dark ms-2">Due Soon!</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (!empty($progress['submission_date'])): ?>
                        <div class="task-date-item">
                            <div class="task-date-label">Submission Date</div>
                            <div class="task-date-value"><?php echo date('F j, Y - g:i A', strtotime($progress['submission_date'])); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($task['description'])): ?>
                    <div class="task-description mb-4">
                        <h5>Description</h5>
                        <div class="p-3 bg-light rounded">
                            <?php echo nl2br(htmlspecialchars($task['description'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($task['pdf_file'])): ?>
                    <div class="task-pdf mb-4">
                        <h5>Task Document</h5>
                        <div class="pdf-container">
                            <div class="d-flex align-items-center p-3 bg-light rounded">
                                <i class="bi bi-file-pdf text-danger fs-3 me-3"></i>
                                <div>
                                    <h6 class="mb-0">Task PDF Document</h6>
                                    <p class="mb-0">
                                        <a href="<?php echo '../' . $task['pdf_file']; ?>" target="_blank" class="btn btn-sm btn-primary mt-2">
                                            <i class="bi bi-eye me-1"></i> View PDF
                                        </a>
                                        <a href="<?php echo '../' . $task['pdf_file']; ?>" download class="btn btn-sm btn-outline-primary mt-2">
                                            <i class="bi bi-download me-1"></i> Download
                                        </a>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($progress['status'] === 'completed' && !empty($progress['submission_text'])): ?>
                    <div class="task-submission">
                        <h5>Your Submission</h5>
                        <div class="submission-text">
                            <?php echo nl2br(htmlspecialchars($progress['submission_text'])); ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="submission-form">
                        <h5>Submit Your Work</h5>
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="submission_text" class="form-label">Your Answer/Work</label>
                                <textarea class="form-control" id="submission_text" name="submission_text" rows="6" placeholder="Enter your answer or work here..."><?php echo htmlspecialchars($progress['submission_text'] ?? ''); ?></textarea>
                            </div>
                            <button type="submit" name="submit_task" class="btn btn-primary">Submit Task</button>
                            
                            <?php if ($progress['status'] !== 'not_started'): ?>
                            <div class="mt-2 text-muted">
                                <small>You can update your submission until the teacher provides feedback.</small>
                            </div>
                            <?php endif; ?>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>