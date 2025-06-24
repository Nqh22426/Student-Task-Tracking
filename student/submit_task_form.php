<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

// Check if task ID is provided
if (!isset($_GET['task_id']) || !is_numeric($_GET['task_id'])) {
    $_SESSION['error'] = "Invalid task ID";
    header("Location: dashboard.php");
    exit();
}

$task_id = (int)$_GET['task_id'];
$student_id = $_SESSION['user_id'];

// Get task information
$stmt = $pdo->prepare("
    SELECT t.*, c.name AS class_name, c.id AS class_id
    FROM tasks t
    JOIN classes c ON t.class_id = c.id
    JOIN class_enrollments ce ON c.id = ce.class_id
    WHERE t.id = ? AND ce.student_id = ?
");
$stmt->execute([$task_id, $student_id]);
$task = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$task) {
    $_SESSION['error'] = "Task not found or you don't have permission to access it";
    header("Location: dashboard.php");
    exit();
}

// Check if this task has a submission
$sub_query = $pdo->prepare("
    SELECT * FROM submissions 
    WHERE student_id = ? AND task_id = ? 
    ORDER BY submission_date DESC LIMIT 1
");
$sub_query->execute([$student_id, $task_id]);
$submission = $sub_query->fetch(PDO::FETCH_ASSOC);
$has_submission = ($submission !== false);

// Convert start and end times to DateTime objects
$start_date = new DateTime($task['start_datetime']);
$end_date = new DateTime($task['end_datetime']);
$now = new DateTime();

// Set page title
$page_title = "Submit Task: " . htmlspecialchars($task['title']);
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
        .submission-card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
            padding: 25px;
            margin-bottom: 30px;
        }
        .upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            background-color: #f8f9fa;
            margin-bottom: 20px;
            cursor: pointer;
        }
        .upload-area:hover {
            background-color: #e9ecef;
            border-color: #adb5bd;
        }
        .upload-icon {
            font-size: 48px;
            color: #6c757d;
            margin-bottom: 15px;
        }
        .file-selected {
            display: none;
            padding: 15px;
            background-color: #e8f5e9;
            border-radius: 4px;
            margin-top: 15px;
            border: 1px solid #c8e6c9;
        }
    </style>
</head>
<body>
    <?php include_once '../includes/student_sidebar.php'; ?>
    
    <!-- Custom Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <span class="navbar-brand"><?php echo htmlspecialchars($task['class_name']); ?></span>
            <div class="navbar-nav ms-auto">
                <a href="tasks_list.php?id=<?php echo $task['class_id']; ?>" class="nav-item nav-link">
                    <i class="bi bi-arrow-left"></i> Back to Tasks List
                </a>
            </div>
        </div>
    </nav>

    <!-- Main content -->
    <div class="main-content">
        <div class="content-wrapper">
            <div class="container-fluid">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <div class="submission-card">
                    <h2><?php echo htmlspecialchars($task['title']); ?></h2>
                    
                    <div class="row mb-4 mt-3">
                        <div class="col-md-6">
                            <h5>Start Date</h5>
                            <p><i class="bi bi-calendar-event"></i> <?php echo $start_date->format('M j, Y - g:i A'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <h5>Due Date</h5>
                            <p><i class="bi bi-calendar-check"></i> <?php echo $end_date->format('M j, Y - g:i A'); ?></p>
                        </div>
                    </div>
                    
                    <?php if (!empty($task['description'])): ?>
                        <div class="mb-4">
                            <h5>Description</h5>
                            <div class="p-3 bg-light rounded">
                                <p><?php echo nl2br(htmlspecialchars($task['description'])); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($task['pdf_file'])): ?>
                        <div class="mb-4">
                            <h5>Task Document</h5>
                            <div class="p-3 bg-light rounded d-flex align-items-center">
                                <i class="bi bi-file-pdf text-danger me-3" style="font-size: 1.5rem;"></i>
                                <div>
                                    <div class="mb-2">Task PDF Document</div>
                                    <a href="../<?php echo $task['pdf_file']; ?>" target="_blank" class="btn btn-sm btn-primary me-2">
                                        <i class="bi bi-eye"></i> View Document
                                    </a>
                                    <a href="../<?php echo $task['pdf_file']; ?>" download class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-download"></i> Download
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="submission-section mt-4">
                        <h4 class="mb-3">Your Submission</h4>
                        
                        <?php if ($has_submission): 
                            $file_path = '../uploads/student_submissions/' . $student_id . '/' . $submission['filename'];
                        ?>
                            <div class="p-3 bg-success bg-opacity-10 rounded border border-success mb-3">
                                <h5 class="text-success mb-3"><i class="bi bi-check-circle"></i> You have submitted this task</h5>
                                <div class="d-flex align-items-center mb-3">
                                    <i class="bi bi-file-pdf text-danger me-3" style="font-size: 1.5rem;"></i>
                                    <div>
                                        <p class="mb-1"><?php echo htmlspecialchars($submission['filename']); ?></p>
                                        <small class="text-muted">Submitted: <?php echo date('F j, Y, g:i a', strtotime($submission['submission_date'])); ?></small>
                                    </div>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <a href="<?php echo htmlspecialchars($file_path); ?>" target="_blank" class="btn btn-primary">
                                        <i class="bi bi-eye"></i> View Submission
                                    </a>
                                    <a href="unsubmit_task.php?task_id=<?php echo $task_id; ?>" class="btn btn-outline-danger"
                                       onclick="return confirm('Are you sure you want to remove your submission? You will need to submit again.');">
                                        <i class="bi bi-x-circle"></i> Unsubmit Task
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <form action="submit_task.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="task_id" value="<?php echo $task_id; ?>">
                                <input type="hidden" name="MAX_FILE_SIZE" value="31457280"> <!-- 30MB -->
                                <input type="hidden" name="referer" value="submit_task_form.php?task_id=<?php echo $task_id; ?>">
                                
                                <div class="upload-area" id="upload-area">
                                    <i class="bi bi-cloud-arrow-up upload-icon"></i>
                                    <h4>Upload your PDF file</h4>
                                    <p class="text-muted">Click to select a file or drag and drop</p>
                                    <p class="small text-muted">(Maximum file size: 30MB)</p>
                                    <input type="file" name="submission_file" id="submission-file" accept=".pdf" class="d-none">
                                </div>
                                
                                <div class="file-selected" id="file-selected">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-file-pdf text-danger me-2"></i>
                                        <span id="selected-filename" class="me-auto">No file selected</span>
                                        <button type="button" class="btn btn-sm btn-outline-danger" id="remove-file">
                                            <i class="bi bi-x"></i> Remove
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-success btn-lg" id="submit-button">Submit Task</button>
                                    <a href="tasks_list.php?id=<?php echo $task['class_id']; ?>" class="btn btn-outline-secondary">Cancel</a>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const uploadArea = document.getElementById('upload-area');
            const fileInput = document.getElementById('submission-file');
            const fileSelected = document.getElementById('file-selected');
            const selectedFilename = document.getElementById('selected-filename');
            const removeFileBtn = document.getElementById('remove-file');
            const submitButton = document.getElementById('submit-button');
            
            if (uploadArea) {
                // Click to select file
                uploadArea.addEventListener('click', function() {
                    fileInput.click();
                });
                
                // Drag and drop functionality
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
                        handleFileSelection();
                    }
                });
                
                // Handle file selection
                fileInput.addEventListener('change', handleFileSelection);
                
                function handleFileSelection() {
                    if (fileInput.files.length > 0) {
                        const file = fileInput.files[0];
                        if (file.type === 'application/pdf') {
                            selectedFilename.textContent = file.name;
                            uploadArea.style.display = 'none';
                            fileSelected.style.display = 'block';
                            submitButton.disabled = false;
                        } else {
                            alert('Please upload a PDF file');
                            fileInput.value = '';
                            submitButton.disabled = true;
                        }
                    }
                }
                
                // Remove selected file
                removeFileBtn.addEventListener('click', function() {
                    fileInput.value = '';
                    uploadArea.style.display = 'block';
                    fileSelected.style.display = 'none';
                    submitButton.disabled = true;
                });
                
                // Initially disable submit button if no file is selected
                if (!fileInput.files.length) {
                    submitButton.disabled = true;
                }
            }
        });
    </script>
</body>
</html> 