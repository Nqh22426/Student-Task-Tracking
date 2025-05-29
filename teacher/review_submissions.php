<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

// Check if task_id and class_id are provided
if (!isset($_GET['task_id']) || !is_numeric($_GET['task_id']) || 
    !isset($_GET['class_id']) || !is_numeric($_GET['class_id'])) {
    $_SESSION['error'] = "Invalid parameters";
    header("Location: dashboard.php");
    exit();
}

$task_id = $_GET['task_id'];
$class_id = $_GET['class_id'];
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

// Verify that the task belongs to this class
$stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ? AND class_id = ?");
$stmt->execute([$task_id, $class_id]);
$task = $stmt->fetch();

if (!$task) {
    $_SESSION['error'] = "Task not found or doesn't belong to this class";
    header("Location: student_submissions.php?id=" . $class_id);
    exit();
}

// Get class details
$class_name = $class['name'];
$class_code = $class['class_code'];

// Get task details
$task_title = $task['title'];
$task_end_datetime = $task['end_datetime'];

// Fetch student submissions for this task
$stmt = $pdo->prepare("
    SELECT s.id as submission_id, s.student_id, s.submission_date as submission_time, 
           s.filename as file_path, s.grade,
           u.username as student_name, u.id as student_code
    FROM submissions s
    JOIN users u ON s.student_id = u.id
    WHERE s.task_id = ?
    ORDER BY u.username ASC
");
$stmt->execute([$task_id]);
$submissions = $stmt->fetchAll();

// Get students who haven't submitted
$stmt = $pdo->prepare("
    SELECT u.id, u.username as name, u.id as student_code
    FROM users u
    JOIN class_enrollments ce ON u.id = ce.student_id
    WHERE ce.class_id = ? 
    AND u.role = 'student'
    AND u.id NOT IN (
        SELECT student_id FROM submissions WHERE task_id = ?
    )
    ORDER BY u.username ASC
");
$stmt->execute([$class_id, $task_id]);
$missing_submissions = $stmt->fetchAll();

// Set page variables
$page_title = "Review Submissions";
$navbar_title = "Review Submissions";
$active_page = 'submissions';

// Include header
include_once '../includes/header.php';
?>

<style>
    /* Additional styling for submissions page */
    .main-content {
        margin-left: 180px;
        padding: 20px;
        padding-top: 68px; /* Account for navbar height (48px) + extra padding */
    }
    .submissions-table {
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 3px 6px rgba(0,0,0,0.16);
        margin-bottom: 2rem;
    }
    .submissions-table .card-header {
        padding: 1rem;
        font-weight: 500;
    }
    .student-card {
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        margin-bottom: 1rem;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .student-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
    .student-info {
        padding: 1rem;
        display: flex;
        align-items: center;
    }
    .student-name {
        font-weight: 500;
        font-size: 1.1rem;
        margin-bottom: 0.2rem;
    }
    .student-id {
        color: #6c757d;
        font-size: 0.9rem;
    }
    .status-badge {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
        margin-left: 0.1rem;
        vertical-align: middle;
        display: inline-block;
        border-radius: 0.25rem;
        font-weight: 500;
        color: #fff;
    }
    .bg-success {
        background-color: #32CD32; /* Much brighter lime green */
    }
    .bg-danger {
        background-color: #dc3545;
    }
    .pdf-preview {
        width: 100%;
        height: auto;
        max-height: 150px;
        border: 1px solid #ddd;
        border-radius: 4px;
        object-fit: contain;
    }
    .back-button-container {
        margin-top: -20px;
    }
    .back-button {
        margin-bottom: 1rem;
    }
    /* New styles */
    .grade-column {
        text-align: center;
    }
    .grade-number {
        color: #0d6efd; /* Bootstrap primary blue */
        font-weight: 600;
        font-size: 1.1rem;
    }
    .edit-grade-btn {
        border: 1px solid #dee2e6;
        border-radius: 4px;
        margin-left: 5px;
        width: 28px;
        height: 28px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .add-grade-btn i {
        color: #0d6efd; /* Bootstrap primary blue */
    }
    /* Smaller submission time and status */
    .submission-time {
        font-size: 0.85rem;
    }
    .submission-time .submission-datetime {
        display: inline-block;
    }
    .submission-time .badge {
        font-size: 0.75rem;
        padding: 0.2rem 0.5rem;
        margin-left: 0.5rem;
        vertical-align: middle;
    }
    /* Table layout fixes */
    .table {
        width: 100%;
        table-layout: fixed;
        border-collapse: collapse;
    }
    .table th, 
    .table td {
        border-bottom: 1px solid #dee2e6;
        padding: 0.75rem;
        vertical-align: middle;
    }
    /* Remove custom table styling that might interfere */
    .table-responsive {
        width: 100%;
        overflow-x: auto;
    }
    /* Adjust file column */
    .file-column {
        padding-left: 0;
        padding-right: 0;
    }
    .file-btn {
        margin-left: 0;
        margin-right: 0;
        max-width: 220px !important;
    }
    .file-btn span {
        max-width: 170px !important;
    }
    
    /* Check AI Button Basic Styling */
    #checkAiBtn {
        border-radius: 20px;
        font-weight: 500;
    }
    
    #checkAiBtn .bi-robot {
        color: #6f42c1;
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
                    <h2 class="h4">Submissions: <?php echo htmlspecialchars($task_title); ?></h2>
                    <p class="text-muted">Review student submissions for this task</p>
                </div>
            </div>

            <div class="row">
                <div class="col-12 mb-2 back-button-container">
                    <a href="student_submissions.php?id=<?php echo $class_id; ?>" class="btn btn-outline-secondary back-button">
                        <i class="bi bi-arrow-left me-1"></i>Back
                    </a>
                </div>

                <!-- Submissions Section -->
                <?php if (count($submissions) > 0): ?>
                <div class="col-12 mt-2">
                    <div class="card submissions-table">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Submitted Work (<?php echo count($submissions); ?>)</h5>
                            <button type="button" class="btn btn-light btn-sm" id="checkAiBtn">
                                <i class="bi bi-robot me-1"></i>Check AI
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th width="22%">Name</th>
                                            <th width="22%">Submission Time</th>
                                            <th width="26%">File</th>
                                            <th width="15%">AI Detection</th>
                                            <th width="15%" class="grade-column">Grade</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($submissions as $submission): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($submission['student_name']); ?></td>
                                                <td class="submission-time">
                                                    <?php 
                                                        $submit_time = strtotime($submission['submission_time']);
                                                        $end_time = strtotime($task_end_datetime);
                                                        $ontime = $submit_time <= $end_time;
                                                    ?>
                                                    <span style="display: inline-block; margin-right: 5px;"><?php echo date('M j, Y g:i a', $submit_time); ?></span>
                                                    <?php if ($ontime): ?>
                                                        <span class="status-badge bg-success">Ontime</span>
                                                    <?php else: ?>
                                                        <span class="status-badge bg-danger">Overdue</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="file-column">
                                                    <?php if ($submission['file_path']): ?>
                                                        <a href="../uploads/student_submissions/<?php echo $submission['student_id']; ?>/<?php echo htmlspecialchars($submission['file_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary file-btn" style="max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: inline-block; vertical-align: middle; margin-left: -10px;" title="<?php echo htmlspecialchars($submission['file_path']); ?>">
                                                            <i class="bi bi-file-pdf"></i> <span style="vertical-align: middle; max-width: 170px; display: inline-block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars($submission['file_path']); ?></span>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">No file</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <!-- AI Detection column left empty -->
                                                </td>
                                                <td class="grade-column">
                                                    <?php 
                                                        if ($submission['grade'] !== null) {
                                                            echo '<span class="grade-number">' . $submission['grade'] . '</span>';
                                                            
                                                            // Chỉ hiển thị nút chỉnh sửa khi grades_sent = 0
                                                            if ($task['grades_sent'] == 0) {
                                                                echo '<button class="btn-link edit-grade-btn" data-submission-id="' . $submission['submission_id'] . '" title="Edit grade"><i class="bi bi-pencil"></i></button>';
                                                            }
                                                        } else {
                                                            // Chỉ hiển thị nút thêm điểm khi grades_sent = 0
                                                            if ($task['grades_sent'] == 0) {
                                                                echo '<button class="add-grade-btn" data-submission-id="' . $submission['submission_id'] . '" title="Add grade" style="background:none;border:none;padding:0;outline:none;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;"><i class="bi bi-plus-circle" style="font-size:1.5rem;"></i></button>';
                                                            } else {
                                                                echo '<span class="text-muted">Not graded</span>';
                                                            }
                                                        }
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        No submissions have been received for this task yet.
                    </div>
                </div>
                <?php endif; ?>

                <!-- Missing Submissions Section -->
                <?php if (count($missing_submissions) > 0): ?>
                <div class="col-12 mt-4">
                    <div class="card submissions-table">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0">Missing Submissions (<?php echo count($missing_submissions); ?>)</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($missing_submissions as $student): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($student['name']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Confirm & Send Grades Button Section -->
            <div class="row mt-4 mb-3">
                <div class="col-12 d-flex justify-content-end">
                    <?php if ($task['grades_sent'] == 1): ?>
                        <a href="undo_grades.php?task_id=<?php echo $task_id; ?>&class_id=<?php echo $class_id; ?>" 
                           class="btn btn-outline-secondary me-2" 
                           onclick="return confirm('Are you sure you want to unpublish grades? Students will no longer be able to see these grades until you send them again.');">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Undo
                        </a>
                        <button disabled class="btn btn-success">
                            <i class="bi bi-check-circle-fill me-2"></i>Grades Sent
                        </button>
                    <?php else: ?>
                        <button id="confirmSendGradesBtn" class="btn btn-primary">
                            <i class="bi bi-check-circle me-2"></i>Save & Send grade to students
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Grade Modal -->
<div class="modal fade" id="gradeModal" tabindex="-1" aria-labelledby="gradeModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="gradeModalLabel">Enter Grade</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="number" min="0" max="100" class="form-control" id="gradeInput" placeholder="Enter grade (0-100)">
        <input type="hidden" id="modalSubmissionId">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-danger" id="removeGradeBtn">Remove</button>
        <button type="button" class="btn btn-primary" id="confirmGradeBtn">Confirm</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        let gradeModal = new bootstrap.Modal(document.getElementById('gradeModal'));
        let currentSubmissionId = null;
        // Chỉ mở modal khi click vào nút edit hoặc nút cộng
        document.querySelectorAll('.edit-grade-btn, .add-grade-btn').forEach(el => {
            el.addEventListener('click', function(e) {
                currentSubmissionId = this.getAttribute('data-submission-id');
                document.getElementById('modalSubmissionId').value = currentSubmissionId;
                // Nếu là edit thì điền sẵn điểm vào input
                const badge = this.parentNode.querySelector('.badge.bg-success');
                if (badge) {
                    document.getElementById('gradeInput').value = badge.textContent.trim();
                } else {
                    document.getElementById('gradeInput').value = '';
                }
                gradeModal.show();
            });
        });
        // Confirm grade
        document.getElementById('confirmGradeBtn').addEventListener('click', function() {
            const grade = document.getElementById('gradeInput').value;
            const submissionId = document.getElementById('modalSubmissionId').value;
            if (grade === '' || isNaN(grade) || grade < 0 || grade > 100) {
                alert('Please enter a valid grade (0-100)');
                return;
            }
            const xhr1 = new XMLHttpRequest();
            xhr1.open('POST', 'update_grade.php', true);
            xhr1.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr1.onreadystatechange = function() {
                if (xhr1.readyState === 4 && xhr1.status === 200) {
                    try {
                        const data = JSON.parse(xhr1.responseText);
                if (data.success) {
                    // Cập nhật giao diện
                    const td = document.querySelector(`button[data-submission-id='${submissionId}']`).parentNode;
                    td.innerHTML = `<span class='grade-number'>${grade}</span><button class='btn-link edit-grade-btn' data-submission-id='${submissionId}' title='Edit grade'><i class='bi bi-pencil'></i></button>`;
                    // Gán lại sự kiện cho nút edit mới
                    td.querySelector('.edit-grade-btn').addEventListener('click', function() {
                        currentSubmissionId = this.getAttribute('data-submission-id');
                        document.getElementById('modalSubmissionId').value = currentSubmissionId;
                        document.getElementById('gradeInput').value = grade;
                        gradeModal.show();
                    });
                    gradeModal.hide();
                } else {
                    alert('Failed to update grade: ' + data.message);
                }
                    } catch (e) {
                        alert('Error updating grade');
                    }
                }
            };
            
            xhr1.send(`submission_id=${submissionId}&grade=${grade}`);
        });
        // Remove grade
        document.getElementById('removeGradeBtn').addEventListener('click', function() {
            const submissionId = document.getElementById('modalSubmissionId').value;
            const xhr2 = new XMLHttpRequest();
            xhr2.open('POST', 'update_grade.php', true);
            xhr2.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr2.onreadystatechange = function() {
                if (xhr2.readyState === 4 && xhr2.status === 200) {
                    try {
                        const data = JSON.parse(xhr2.responseText);
                if (data.success) {
                    // Cập nhật lại giao diện
                    const td = document.querySelector(`button[data-submission-id='${submissionId}']`).parentNode;
                    td.innerHTML = `<button class='add-grade-btn' data-submission-id='${submissionId}' title='Add grade' style='background:none;border:none;padding:0;outline:none;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;'><i class='bi bi-plus-circle' style='font-size:1.5rem;color:#0d6efd;'></i></button>`;
                    // Gán lại sự kiện cho nút cộng mới
                    td.querySelector('.add-grade-btn').addEventListener('click', function() {
                        currentSubmissionId = this.getAttribute('data-submission-id');
                        document.getElementById('modalSubmissionId').value = currentSubmissionId;
                        document.getElementById('gradeInput').value = '';
                        gradeModal.show();
                    });
                    gradeModal.hide();
                } else {
                    alert('Failed to remove grade: ' + data.message);
                }
                    } catch (e) {
                        alert('Error removing grade');
                    }
                }
            };
            
            xhr2.send(`submission_id=${submissionId}&grade=null`);
        });
        // Send grades to students
        document.getElementById('confirmSendGradesBtn').addEventListener('click', function() {
            if (confirm('Are you sure you want to send grades to all students?')) {
                const taskId = <?php echo $task_id; ?>;
                
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'send_grades.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        if (xhr.status === 200) {
                            try {
                                const data = JSON.parse(xhr.responseText);
                                if (data.success) {
                                    window.location.reload();
                                } else {
                                    alert('Failed to send grades: ' + data.message);
                                }
                            } catch (e) {
                                alert('Error parsing response. Please try again.');
                            }
                        } else {
                            alert('Error sending grades. Please try again.');
                        }
                    }
                };
                
                xhr.send('task_id=' + taskId);
            }
        });
        
        // Check AI Button - No action (empty button)
        document.getElementById('checkAiBtn').addEventListener('click', function() {
            // Button has no functionality
        });
    });
</script>
</body>
</html> 