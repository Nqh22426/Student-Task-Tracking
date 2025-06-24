<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
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
$teacher_id = $_SESSION['user_id'];

// Get the task details and ensure it belongs to a class owned by the current teacher
try {
    $stmt = $pdo->prepare(
        "SELECT t.*, c.id as class_id, c.name as class_name 
        FROM tasks t
        JOIN classes c ON t.class_id = c.id
        WHERE t.id = ? AND c.teacher_id = ?"
    );
    $stmt->execute([$task_id, $teacher_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$task) {
        $_SESSION['error'] = "Task not found or you don't have permission to edit it";
        header("Location: dashboard.php");
        exit();
    }
    
    $class_id = $task['class_id'];
    
    // Process form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $title = trim($_POST['task_title'] ?? '');
        $description = trim($_POST['task_description'] ?? '');
        $end_datetime = $_POST['task_end_datetime'] ?? '';
        $remove_pdf = isset($_POST['remove_pdf']);
        
        // Validate required fields
        if (empty($title)) {
            $_SESSION['error'] = "Task title is required";
            header("Location: edit_task.php?id=" . $task_id);
            exit();
        }
        
        if (empty($end_datetime)) {
            $_SESSION['error'] = "End date/time is required";
            header("Location: edit_task.php?id=" . $task_id);
            exit();
        }
        
        // Validate datetime format and logic
        $end_timestamp = strtotime($end_datetime);
        $start_timestamp = strtotime($task['start_datetime']);
        
        if (!$end_timestamp) {
            $_SESSION['error'] = "Invalid date format for deadline";
            header("Location: edit_task.php?id=" . $task_id);
            exit();
        }
        
        if ($end_timestamp <= $start_timestamp) {
            $_SESSION['error'] = "Deadline must be after the start date/time";
            header("Location: edit_task.php?id=" . $task_id);
            exit();
        }

        // Handle PDF file
        $pdf_file_path = $task['pdf_file'];

        // Remove PDF if requested
        if ($remove_pdf && !empty($pdf_file_path)) {
            $full_path = '../' . $pdf_file_path;
            if (file_exists($full_path)) {
                unlink($full_path);
            }
            $pdf_file_path = null;
        }

        // Handle new PDF upload
        if (isset($_FILES['task_pdf_file']) && $_FILES['task_pdf_file']['error'] == 0) {
            // Check if the file is a PDF
            $file_ext = strtolower(pathinfo($_FILES['task_pdf_file']['name'], PATHINFO_EXTENSION));
            if ($file_ext != 'pdf') {
                $_SESSION['error'] = "Only PDF files are allowed";
                header("Location: edit_task.php?id=" . $task_id);
                exit();
            }

            // Create directory if it doesn't exist
            $upload_dir = '../uploads/task_pdfs/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            // Remove old file if exists
            if (!empty($task['pdf_file']) && !$remove_pdf) {
                $full_path = '../' . $task['pdf_file'];
                if (file_exists($full_path)) {
                    unlink($full_path);
                }
            }

            // Generate unique file name
            $new_file_name = 'task_' . time() . '_' . uniqid() . '.pdf';
            $upload_path = $upload_dir . $new_file_name;

            // Move uploaded file to our directory
            if (move_uploaded_file($_FILES['task_pdf_file']['tmp_name'], $upload_path)) {
                $pdf_file_path = 'uploads/task_pdfs/' . $new_file_name;
            } else {
                $_SESSION['error'] = "Error uploading PDF file";
                header("Location: edit_task.php?id=" . $task_id);
                exit();
            }
        }
        
        try {
            // Update the task
            $stmt = $pdo->prepare(
                "UPDATE tasks 
                SET title = ?, description = ?, pdf_file = ?, end_datetime = ? 
                WHERE id = ?"
            );
            $stmt->execute([
                $title,
                $description,
                $pdf_file_path,
                date('Y-m-d H:i:s', $end_timestamp),
                $task_id
            ]);
            
            // Send notification emails to students about task update
            require_once '../includes/notification_service.php';
            $notificationService = new NotificationService($pdo);
            $notificationService->createTaskUpdatedNotifications($task_id, $class_id, $_SESSION['user_id']);
            
            $_SESSION['success'] = "Task updated successfully ";
            
            // Redirect back to the class details page
            header("Location: class_details.php?id=" . $class_id);
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error updating task: " . $e->getMessage();
            header("Location: edit_task.php?id=" . $task_id);
            exit();
        }
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    header("Location: dashboard.php");
    exit();
}

// Set page variables
$page_title = "Edit Task";
$navbar_title = "Edit Task - " . htmlspecialchars($task['class_name']);
$active_page = '';

include_once '../includes/header.php';
?>

<style>
.edit-task-container {
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
    padding: 25px;
    margin-bottom: 30px;
}

.task-header {
    margin-bottom: 25px;
    border-bottom: 1px solid #e0e0e0;
    padding-bottom: 15px;
}

.start-datetime-display {
    background-color: #f8f9fa;
    padding: 12px 15px;
    border-radius: 4px;
    border-left: 4px solid #4285f4;
    margin-bottom: 20px;
}

.edit-deadline-section {
    background-color: #fff8e1;
    padding: 15px;
    border-radius: 4px;
    border-left: 4px solid #ffc107;
    margin-bottom: 20px;
}

.edit-deadline-section .form-label {
    font-weight: 500;
}

.main-content {
    margin-left: 180px;
    padding: 20px;
    padding-top: 68px;
}
</style>

<!-- Include sidebar and navbar -->
<?php include_once '../includes/teacher_sidebar.php'; ?>
<?php include_once '../includes/navbar.php'; ?>

<!-- Main content -->
<div class="main-content">
    <div class="content-wrapper">
        <div class="container-fluid">
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php 
                        echo htmlspecialchars($_SESSION['error']); 
                        unset($_SESSION['error']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="edit-task-container">
                <div class="task-header">
                    <h2 class="mb-2">Edit Task</h2>
                    <p class="text-muted">
                        You can edit the task title, description, and deadline. 
                        The original start date/time will remain unchanged.
                    </p>
                </div>

                <form action="edit_task.php?id=<?php echo $task_id; ?>" method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="taskTitle" class="form-label">Task Title *</label>
                        <input type="text" class="form-control" id="taskTitle" name="task_title" 
                               value="<?php echo htmlspecialchars($task['title']); ?>" required>
                    </div>
                    
                    <div class="mb-4">
                        <label for="taskDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="taskDescription" name="task_description" 
                                  rows="4"><?php echo htmlspecialchars($task['description']); ?></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label for="taskPdfFile" class="form-label">PDF Document</label>
                        <?php if (!empty($task['pdf_file'])): ?>
                            <div class="mb-2 d-flex align-items-center">
                                <div class="border rounded p-2 bg-light flex-grow-1 me-2">
                                    <i class="bi bi-file-pdf text-danger me-2"></i>
                                    <a href="<?php echo '../' . $task['pdf_file']; ?>" target="_blank">
                                        View Current PDF
                                    </a>
                                </div>
                                <div class="form-check ms-2">
                                    <input class="form-check-input" type="checkbox" name="remove_pdf" id="removePdf" value="1">
                                    <label class="form-check-label" for="removePdf">
                                        Remove
                                    </label>
                                </div>
                            </div>
                        <?php endif; ?>
                        <input type="file" class="form-control" id="taskPdfFile" name="task_pdf_file" accept=".pdf">
                        <div class="form-text">
                            <?php if (!empty($task['pdf_file'])): ?>
                                Upload a new PDF to replace the current one, or check "Remove" to delete the current PDF.
                            <?php else: ?>
                                Upload a PDF file with task instructions or additional resources.
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="start-datetime-display">
                        <div class="row">
                            <div class="col">
                                <label class="form-label fw-bold">Start Date/Time (cannot be changed)</label>
                                <div>
                                    <i class="bi bi-calendar-event me-2"></i>
                                    <?php 
                                        $start_date = new DateTime($task['start_datetime']);
                                        echo $start_date->format('F j, Y - g:i A'); 
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="edit-deadline-section">
                        <label for="taskEndDatetime" class="form-label">Deadline (End Date/Time) *</label>
                        <input type="text" class="form-control" id="taskEndDatetime" name="task_end_datetime" 
                               value="<?php echo $task['end_datetime']; ?>" required>
                        <small class="text-muted mt-1 d-block">
                            <i class="bi bi-info-circle me-1"></i> 
                            You can update the deadline for this task. It must be after the start date/time.
                        </small>
                    </div>
                    
                    <div class="d-flex justify-content-between mt-4">
                        <a href="class_details.php?id=<?php echo $class_id; ?>" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Include flatpickr for datetime picking -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Initialize flatpickr (datetime picker) for deadline field only
    if (typeof flatpickr !== 'undefined') {
        // Get the start datetime value
        const startDatetime = "<?php echo $task['start_datetime']; ?>";
        const startTimestamp = new Date(startDatetime).getTime();
        
        // Configure the end datetime picker with a minimum time constraint
        const endPicker = flatpickr("#taskEndDatetime", {
            enableTime: true,
            dateFormat: "Y-m-d H:i:s",
            time_24hr: true,
            minuteIncrement: 5,
            minDate: new Date(startTimestamp),
            defaultDate: "<?php echo $task['end_datetime']; ?>",
            onOpen: function() {
                this.config.minDate = new Date(startTimestamp);
                this.redraw();
            }
        });
    } else {
        console.warn("Flatpickr not loaded");
    }
});
</script>

<?php include_once '../includes/footer.php'; ?> 
