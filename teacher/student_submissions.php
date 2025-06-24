<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
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
$teacher_id = $_SESSION['user_id'];

// Verify that the teacher owns this class
$stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ? AND teacher_id = ?");
$stmt->execute([$class_id, $teacher_id]);
$class = $stmt->fetch();

if (!$class) {
    $_SESSION['error'] = "Class not found or you don't have permission to access it";
    header("Location: dashboard.php");
    exit();
}

// Fetch all tasks for this class
$stmt = $pdo->prepare("
    SELECT t.id, t.title, t.description, t.start_datetime, t.end_datetime, t.pdf_file, 
           t.is_locked, COUNT(s.id) as submission_count
    FROM tasks t
    LEFT JOIN submissions s ON t.id = s.task_id
    WHERE t.class_id = ? AND t.end_datetime < NOW()
    GROUP BY t.id
    ORDER BY t.end_datetime DESC
");
$stmt->execute([$class_id]);
$tasks = $stmt->fetchAll();

// Set page variables
$page_title = "Student Submissions";
$navbar_title = "Student Submissions";
$active_page = 'submissions';

include_once '../includes/header.php';
?>

<style>
    .main-content {
        margin-left: 180px;
        padding: 20px;
        padding-top: 68px;
    }
    .task-card {
        transition: transform 0.2s, box-shadow 0.2s;
        margin-bottom: 1.5rem;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        box-shadow: 0 3px 6px rgba(0,0,0,0.16);
        width: 85%;
        margin-left: 0;
        margin-right: auto;
    }
    .task-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 10px rgba(0,0,0,0.2);
    }
    .task-card .card-header {
        background-color: #f0f0f0;
        border-bottom: 1px solid #ddd;
        padding: 1rem;
        font-weight: 500;
    }
    .task-card .card-body {
        padding: 1.25rem;
    }
    .badge-submissions {
        font-size: 0.8rem;
        padding: 0.35rem 0.65rem;
        background-color: #0d6efd;
        font-weight: 600;
    }
    .submissions-info {
        font-size: 0.9rem;
        color: #6c757d;
    }
    .btn-outline-primary {
        border-color: #1a73e8;
        color: #1a73e8;
        border-width: 1px;
        font-weight: normal;
    }
    .btn-outline-primary:hover {
        background-color: #1a73e8;
        border-color: #1a73e8;
        color: white;
    }
    .status-completed {
        background-color: #dc3545;
        color: white;
        font-size: 0.85rem;
        padding: 0.35rem 0.6rem;
        font-weight: bold;
        box-shadow: 0 2px 4px rgba(220, 53, 69, 0.2);
        letter-spacing: normal;
        text-transform: none !important;
    }
    
    .toggle-lock {
        min-width: 100px;
        white-space: nowrap;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
        padding: 0.375rem 0.65rem;
    }
    
    .toggle-lock i {
        margin-right: 4px;
        font-size: 0.9rem;
    }
</style>

<!-- Include sidebar and navbar -->
<?php include_once '../includes/teacher_sidebar.php'; ?>
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

            <div class="row mb-4 align-items-center">
                <div class="col-md-6">
                    <h2 class="h4">Student Submissions</h2>
                </div>
            </div>

            <div class="row">
                <?php if (count($tasks) > 0): ?>
                    <?php foreach ($tasks as $task): ?>
                        <div class="col-md-12 col-lg-6">
                            <div class="card task-card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0"><?php echo htmlspecialchars($task['title']); ?></h5>
                                    <span class="badge status-completed">Completed</span>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="submissions-info">
                                            <i class="bi bi-file-earmark-text me-1"></i>
                                            Submissions: <strong><?php echo $task['submission_count']; ?></strong>
                                        </span>
                                        <span class="badge bg-primary badge-submissions">
                                            Due: <?php echo date('M j, Y', strtotime($task['end_datetime'])); ?>
                                        </span>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <a href="review_submissions.php?task_id=<?php echo $task['id']; ?>&class_id=<?php echo $class_id; ?>" class="btn btn-outline-primary flex-grow-1">
                                            <i class="bi bi-eye me-1"></i> View Submissions
                                        </a>
                                        <button type="button" class="btn btn-outline-<?php echo $task['is_locked'] ? 'success' : 'secondary'; ?> toggle-lock" data-task-id="<?php echo $task['id']; ?>" data-status="<?php echo $task['is_locked'] ? 'unlocked' : 'locked'; ?>">
                                            <i class="bi bi-<?php echo $task['is_locked'] ? 'lock' : 'unlock'; ?>-fill"></i> <span class="lock-text"><?php echo $task['is_locked'] ? 'Unlock Task' : 'Lock Task'; ?></span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            No completed tasks available yet. Tasks will appear here only after their deadlines have passed.
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Lock/Unlock toggle functionality
    document.addEventListener('DOMContentLoaded', function() {
        const lockButtons = document.querySelectorAll('.toggle-lock');
        
        lockButtons.forEach(button => {
            button.addEventListener('click', function() {
                const currentStatus = this.getAttribute('data-status');
                const lockText = this.querySelector('.lock-text');
                const lockIcon = this.querySelector('i');
                const taskId = this.getAttribute('data-task-id');
                
                // Disable button during processing
                this.disabled = true;
                
                // AJAX request to update task lock status
                fetch('toggle_task_lock.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `task_id=${taskId}&status=${currentStatus}`,
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Toggle button appearance
                        if (currentStatus === 'locked') {
                            // Change to unlocked state
                            this.setAttribute('data-status', 'unlocked');
                            lockText.textContent = 'Unlock Task';
                            lockIcon.classList.remove('bi-unlock-fill');
                            lockIcon.classList.add('bi-lock-fill');
                            this.classList.remove('btn-outline-secondary');
                            this.classList.add('btn-outline-success');
                        } else {
                            // Change to locked state
                            this.setAttribute('data-status', 'locked');
                            lockText.textContent = 'Lock Task';
                            lockIcon.classList.remove('bi-lock-fill');
                            lockIcon.classList.add('bi-unlock-fill');
                            this.classList.remove('btn-outline-success');
                            this.classList.add('btn-outline-secondary');
                        }
                    } else {
                        // Show error message
                        const alertHtml = `
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                Error: ${data.message}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        `;
                        document.querySelector('.container-fluid').insertAdjacentHTML('afterbegin', alertHtml);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                })
                .finally(() => {
                    // Re-enable button
                    this.disabled = false;
                });
            });
        });
    });
</script>
</body>
</html> 