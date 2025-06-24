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
           s.filename as file_path, s.grade, s.ai_probability,
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
    .main-content {
        margin-left: 180px;
        padding: 20px;
        padding-top: 68px;
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
        background-color: #32CD32;
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
        color: #0d6efd;
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
        color: #0d6efd;
    }

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

    .table-responsive {
        width: 100%;
        overflow-x: auto;
    }

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
    
    /* Check AI Styling */
    #checkAiBtn {
        border-radius: 20px;
        font-weight: 500;
    }
    
    #checkAiBtn .bi-robot {
        color: #6f42c1;
    }
    
    /* AI Detection Column Styling */
    .ai-detection-column {
        text-align: center;
    }
    
    .ai-percentage {
        font-weight: 600;
        font-size: 0.9rem;
    }
    
    .ai-low {
        color: #28a745; /* Green for low AI probability */
    }
    
    .ai-medium {
        color: #f39c12; /* Yellow for medium AI probability */
    }
    
    .ai-high {
        color: #dc3545; /* Red for high AI probability */
    }
    
    .ai-loading {
        color: #6c757d;
        font-style: italic;
    }
    
    .ai-error {
        color: #dc3545;
        font-size: 0.8rem;
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
                            <div class="d-flex justify-content-end mb-3">
                                <button id="checkAiBtn" class="btn btn-light">
                                    <i class="bi bi-robot"></i> Check AI
                                </button>
                            </div>
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
                                            <tr data-submission-id="<?php echo $submission['submission_id']; ?>">
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
                                                <td class="ai-detection-column">
                                                    <?php if ($submission['ai_probability'] !== null): ?>
                                                        <?php 
                                                            // Convert probability to percentage
                                                            $aiPercentage = round($submission['ai_probability'] * 100, 1);
                                                            $aiClass = '';
                                                            if ($aiPercentage < 30) {
                                                                $aiClass = 'ai-low';
                                                            } elseif ($aiPercentage < 70) {
                                                                $aiClass = 'ai-medium';
                                                            } else {
                                                                $aiClass = 'ai-high';
                                                            }
                                                        ?>
                                                        <span class="ai-percentage <?php echo $aiClass; ?>"><?php echo $aiPercentage; ?>%</span>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not checked</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="grade-column">
                                                    <?php 
                                                        if ($submission['grade'] !== null) {
                                                            echo '<span class="grade-number">' . $submission['grade'] . '</span>';
                                                            
                                                            // Only show edit button when grades_sent = 0
                                                            if ($task['grades_sent'] == 0) {
                                                                echo '<button class="btn-link edit-grade-btn" data-submission-id="' . $submission['submission_id'] . '" title="Edit grade"><i class="bi bi-pencil"></i></button>';
                                                            }
                                                        } else {
                                                            // Only show add grade button when grades_sent = 0
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
        
        // Only show modal when clicking on edit or add grade button
        document.querySelectorAll('.edit-grade-btn, .add-grade-btn').forEach(el => {
            el.addEventListener('click', function(e) {
                currentSubmissionId = this.getAttribute('data-submission-id');
                document.getElementById('modalSubmissionId').value = currentSubmissionId;
                // If it's edit, fill in the grade in the input
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
                if (xhr1.readyState === 4) {
                    if (xhr1.status === 200) {
                        try {
                            const data = JSON.parse(xhr1.responseText);
                            if (data.success) {
                                const td = document.querySelector(`button[data-submission-id='${submissionId}']`).parentNode;
                                td.innerHTML = `<span class='grade-number'>${grade}</span><button class='btn-link edit-grade-btn' data-submission-id='${submissionId}' title='Edit grade'><i class='bi bi-pencil'></i></button>`;
                                // Reassign event to the edit grade button
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
                    } else {
                        alert('Server error updating grade');
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
                if (xhr2.readyState === 4) {
                    if (xhr2.status === 200) {
                        try {
                            const data = JSON.parse(xhr2.responseText);
                            if (data.success) {
                                const td = document.querySelector(`button[data-submission-id='${submissionId}']`).parentNode;
                                td.innerHTML = `<button class='add-grade-btn' data-submission-id='${submissionId}' title='Add grade' style='background:none;border:none;padding:0;outline:none;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;'><i class='bi bi-plus-circle' style='font-size:1.5rem;color:#0d6efd;'></i></button>`;
                                // Reassign event to the add grade button
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
                    } else {
                        alert('Server error removing grade');
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
        
        // Check AI Button functionality
        document.getElementById('checkAiBtn').addEventListener('click', function() {
            const btn = this;
            const originalText = btn.innerHTML;
            
            // Disable button and show loading state
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Checking...';
            
            // Update all AI detection cells to show loading
            const aiCells = document.querySelectorAll('.ai-detection-column');
            aiCells.forEach(cell => {
                const tr = cell.closest('tr');
                const submissionId = tr.getAttribute('data-submission-id');
                if (submissionId) {
                    cell.innerHTML = '<span class="ai-loading">Checking...</span>';
                }
            });
            
            // Make AJAX call to check AI for all submissions
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'check_ai_batch_python.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    // Re-enable button
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                    
                    if (xhr.status === 200) {
                        try {

                            console.log('Raw AI detection response:', xhr.responseText);
                            
                            const response = JSON.parse(xhr.responseText);
                            console.log('Parsed AI detection response:', response);
                            console.log('Response results:', response.results);
                            
                            if (response.success) {
                                // Update AI detection results
                                if (response.results && Array.isArray(response.results)) {
                                    response.results.forEach(result => {
                                        console.log('Processing result:', result);
                                        console.log('AI percentage value:', result.ai_percentage, 'Type:', typeof result.ai_percentage);
                                        
                                        const tr = document.querySelector(`tr[data-submission-id="${result.submission_id}"]`);
                                        if (tr) {
                                            const aiCell = tr.querySelector('.ai-detection-column');
                                            if (result.error) {
                                                aiCell.innerHTML = `<span class="ai-error" title="${result.error}">Error</span>`;
                                            } else if (result.ai_percentage !== null && result.ai_percentage !== undefined && !isNaN(result.ai_percentage)) {
                                                const percentage = Math.round(parseFloat(result.ai_percentage));
                                                console.log('Calculated percentage:', percentage);
                                                let aiClass = '';
                                                if (percentage < 30) {
                                                    aiClass = 'ai-low';
                                                } else if (percentage < 70) {
                                                    aiClass = 'ai-medium';
                                                } else {
                                                    aiClass = 'ai-high';
                                                }
                                                aiCell.innerHTML = `<span class="ai-percentage ${aiClass}">${percentage}%</span>`;
                                                console.log('Updated cell with:', percentage + '%');
                                            } else {
                                                console.log('AI percentage invalid:', result.ai_percentage);
                                                aiCell.innerHTML = '<span class="text-muted">Unable to detect</span>';
                                            }
                                        } else {
                                            console.log('Row not found for submission ID:', result.submission_id);
                                        }
                                    });
                                }
                                
                                // Show summary if available
                                if (response.summary) {
                                    console.log('AI Detection Summary:', response.summary);
                                }
                                
                            } else {
                                // Show error message in a more user-friendly way
                                const errorMessage = response.message || 'Unknown error occurred';
                                console.error('AI Detection Error:', errorMessage);
                                
                                // Show error in UI instead of alert
                                const errorDiv = document.createElement('div');
                                errorDiv.className = 'alert alert-danger alert-dismissible fade show';
                                errorDiv.innerHTML = `
                                    <strong>AI Detection Error:</strong> ${errorMessage}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                `;
                                
                                // Insert error at the top of the page
                                const container = document.querySelector('.container-fluid');
                                if (container) {
                                    container.insertBefore(errorDiv, container.firstChild);
                                }
                                
                                // Reset all loading cells back to "Not checked"
                                aiCells.forEach(cell => {
                                    if (cell.innerHTML.includes('Checking...')) {
                                        cell.innerHTML = '<span class="text-muted">Not checked</span>';
                                    }
                                });
                            }
                        } catch (e) {
                            console.error('JSON Parse Error:', e);
                            console.error('Raw Response:', xhr.responseText);
                            
                            // Try to extract error information from response
                            let errorMsg = 'Error processing AI detection results.';
                            if (xhr.responseText.includes('Fatal error') || xhr.responseText.includes('Parse error')) {
                                errorMsg = 'Server error occurred. Please check server logs.';
                            } else if (xhr.responseText.includes('Maximum execution time')) {
                                errorMsg = 'Request timed out. Please try again with fewer submissions.';
                            }
                            
                            alert(errorMsg + ' Check console for details.');
                            
                            // Reset all loading cells back to "Not checked"
                            aiCells.forEach(cell => {
                                if (cell.innerHTML.includes('Checking...')) {
                                    cell.innerHTML = '<span class="text-muted">Not checked</span>';
                                }
                            });
                        }
                    } else {
                        console.error('Server error during AI detection. Status:', xhr.status);
                        
                        // Show error in UI
                        const errorDiv = document.createElement('div');
                        errorDiv.className = 'alert alert-danger alert-dismissible fade show';
                        errorDiv.innerHTML = `
                            <strong>Server Error:</strong> AI detection failed. Please try again later. (HTTP ${xhr.status})
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        `;
                        
                        const container = document.querySelector('.container-fluid');
                        if (container) {
                            container.insertBefore(errorDiv, container.firstChild);
                        }
                        
                        // Reset all loading cells back to "Not checked"
                        aiCells.forEach(cell => {
                            if (cell.innerHTML.includes('Checking...')) {
                                cell.innerHTML = '<span class="text-muted">Not checked</span>';
                            }
                        });
                    }
                }
            };
            
            xhr.onerror = function() {
                btn.disabled = false;
                btn.innerHTML = originalText;
                
                console.error('Network error during AI detection');
                
                // Show error in UI
                const errorDiv = document.createElement('div');
                errorDiv.className = 'alert alert-danger alert-dismissible fade show';
                errorDiv.innerHTML = `
                    <strong>Network Error:</strong> Unable to connect to AI detection service. Please check your connection and try again.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                
                const container = document.querySelector('.container-fluid');
                if (container) {
                    container.insertBefore(errorDiv, container.firstChild);
                }
                
                aiCells.forEach(cell => {
                    if (cell.innerHTML.includes('Checking...')) {
                        cell.innerHTML = '<span class="text-muted">Not checked</span>';
                    }
                });
            };
            
            xhr.send('task_id=<?php echo $task_id; ?>');
        });
    });

    /**
     * Update AI result
     */
    function updateAIResult(submissionId, result) {
        const aiCell = $(`#ai-result-${submissionId}`);
        if (result.success) {
            let displayText = `${result.ai_percentage.toFixed(1)}%`;
            
            // Set color based on AI percentage
            let colorClass = 'ai-low';
            if (result.ai_percentage >= 70) {
                colorClass = 'ai-high';
            } else if (result.ai_percentage >= 30) {
                colorClass = 'ai-medium';
            }
            
            aiCell.html(`<span class="ai-percentage ${colorClass}">${displayText}</span>`);
        } else {
            aiCell.html(`<span class="text-danger">Error: ${result.message}</span>`);
        }
    }
</script>
</body>
</html> 