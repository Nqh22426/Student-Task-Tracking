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

// Fetch class details, ensuring it belongs to the current teacher
$stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ? AND teacher_id = ?");
$stmt->execute([$class_id, $teacher_id]);
$class = $stmt->fetch();

if (!$class) {
    $_SESSION['error'] = "Class not found or you don't have permission to access it";
    header("Location: dashboard.php");
    exit();
}

// Handle student removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_student']) && isset($_POST['student_id'])) {
    $student_id = $_POST['student_id'];
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Delete the enrollment
        $stmt = $pdo->prepare("DELETE FROM class_enrollments WHERE class_id = ? AND student_id = ?");
        $stmt->execute([$class_id, $student_id]);
        
        // Commit transaction
        $pdo->commit();
        
        $_SESSION['success'] = "Student has been removed from the class";
        header("Location: students.php?id=$class_id");
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error removing student: " . $e->getMessage();
        header("Location: students.php?id=$class_id");
        exit();
    }
}

// Fetch students enrolled in this class
$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.email
    FROM class_enrollments ce
    JOIN users u ON ce.student_id = u.id 
    WHERE ce.class_id = ? 
    ORDER BY u.username ASC
");
$stmt->execute([$class_id]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);
$student_count = count($students);

// Fetch teacher information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$teacher_id]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

// Set page variables
$page_title = htmlspecialchars($class['name']) . ' - Students';
$navbar_title = 'Class: ' . htmlspecialchars($class['name']);
$active_page = 'students';
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
        }
        
        .students-wrapper {
            position: relative;
            top: 60px;
            margin-bottom: 80px;
        }
        
        .student-count {
            display: inline-block;
            padding: 4px 8px;
            background-color: #007bff;
            color: white;
            border-radius: 20px;
            font-size: 14px;
            margin-left: 10px;
        }
        
        .student-table {
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-top: 20px;
        }
        
        .student-table th {
            background-color: #f8f9fa;
            font-weight: 500;
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
            color: #dee2e6;
            margin-bottom: 15px;
        }
        
        .btn-remove {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            color: #dc3545;
            background-color: transparent;
            border: 1px solid #dc3545;
            border-radius: 0.25rem;
            transition: all 0.2s;
        }
        
        .btn-remove:hover {
            color: white;
            background-color: #dc3545;
        }
        
        .modal-confirm .modal-header {
            background-color: #dc3545;
            color: white;
            border-bottom: none;
        }
        
        .modal-confirm .btn-close {
            filter: brightness(0) invert(1);
        }
        
        .modal-confirm .modal-title {
            margin: 0;
        }
        
        .modal-confirm .modal-content {
            border-radius: 0.5rem;
            border: none;
        }
    </style>
</head>
<body>
    <?php include_once '../includes/teacher_sidebar.php'; ?>
    <?php include_once '../includes/navbar.php'; ?>

    <div class="main-content">
        <div class="students-wrapper">
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

            <h2>
                <?php echo htmlspecialchars($class['name']); ?> - Students
                <span class="student-count">
                    <i class="bi bi-people-fill me-1"></i> <?php echo $student_count; ?> student<?php echo $student_count != 1 ? 's' : ''; ?>
                </span>
            </h2>

            <?php if ($student_count > 0): ?>
                <div class="student-table">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width: 50px">#</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th style="width: 100px">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $index = 1; ?>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td><?php echo $index++; ?></td>
                                    <td><?php echo htmlspecialchars($student['username']); ?></td>
                                    <td><?php echo htmlspecialchars($student['email']); ?></td>
                                    <td>
                                        <button 
                                            type="button" 
                                            class="btn btn-remove" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#removeStudentModal" 
                                            data-student-id="<?php echo $student['id']; ?>"
                                            data-student-name="<?php echo htmlspecialchars($student['username']); ?>"
                                        >
                                            <i class="bi bi-x-circle"></i> Remove
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-people"></i>
                    <h4>No students enrolled yet</h4>
                    <p class="text-muted">Share your class code with students to have them join your class.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Remove Student Confirmation Modal -->
    <div class="modal fade" id="removeStudentModal" tabindex="-1" aria-labelledby="removeStudentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-confirm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="removeStudentModalLabel">Remove Student</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to remove <span id="studentName"></span> from this class?</p>
                    <p class="text-muted">This student will no longer see this class in their dashboard, and their progress in this class will be removed.</p>
                </div>
                <div class="modal-footer">
                    <form method="POST">
                        <input type="hidden" name="student_id" id="studentIdInput" value="">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="remove_student" class="btn btn-danger">Remove Student</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Set student data in the modal when triggered
        const removeStudentModal = document.getElementById('removeStudentModal');
        if (removeStudentModal) {
            removeStudentModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const studentId = button.getAttribute('data-student-id');
                const studentName = button.getAttribute('data-student-name');
                
                const studentNameSpan = document.getElementById('studentName');
                const studentIdInput = document.getElementById('studentIdInput');
                
                studentNameSpan.textContent = studentName;
                studentIdInput.value = studentId;
            });
        }
    </script>
</body>
</html> 