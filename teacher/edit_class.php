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
    $_SESSION['error'] = "Class not found or you don't have permission to edit it";
    header("Location: dashboard.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['class_name'] ?? '');
    $description = trim($_POST['class_description'] ?? '');
    $color = $_POST['class_color'] ?? $class['color'];
    
    // Validate class name
    if (empty($name)) {
        $_SESSION['error'] = "Class name is required";
        header("Location: edit_class.php?id=" . $class_id);
        exit();
    }
    
    try {
        // Update the class
        $stmt = $pdo->prepare("UPDATE classes SET name = ?, description = ?, color = ? WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$name, $description, $color, $class_id, $teacher_id]);
        
        $_SESSION['success'] = "Class updated successfully";
        header("Location: dashboard.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating class: " . $e->getMessage();
        header("Location: edit_class.php?id=" . $class_id);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Class - Student Task Tracking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        /* Sidebar styles */
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

        /* Edit Class Styles */
        .edit-class-card {
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .edit-class-card .card-header {
            color: white;
            border-radius: 8px 8px 0 0;
            padding: 15px 20px;
        }
        .edit-class-form .form-group {
            margin-bottom: 1.5rem;
        }
        .edit-class-form .form-label {
            font-weight: 500;
        }
        .card-header-badge {
            background-color: rgba(255,255,255,0.3);
            color: white;
            font-size: 0.9rem;
            padding: 5px 10px;
            border-radius: 4px;
        }
        .delete-section {
            background-color: rgba(220, 53, 69, 0.05);
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .delete-section .warning-text {
            color: #dc3545;
            font-weight: 500;
        }
        .delete-section .delete-button {
            margin-top: 15px;
        }
        .color-option {
            display: inline-block;
            width: 30px;
            height: 30px;
            margin-right: 10px;
            border-radius: 50%;
            cursor: pointer;
            border: 3px solid transparent;
        }
        .color-option.active {
            border-color: #000;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-sticky">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="bi bi-grid"></i>
                        Your Classes
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="profile.php">
                        <i class="bi bi-person"></i>
                        Profile
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Top navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <span class="navbar-brand">Teacher Dashboard</span>
            <div class="navbar-nav ms-auto">
                <a class="nav-item nav-link" href="../logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main content -->
    <div class="main-content">
        <div class="content-wrapper">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Edit Class</h2>
                    <a href="dashboard.php" class="btn btn-secondary">Back to Classes</a>
                </div>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php 
                            echo htmlspecialchars($_SESSION['error']); 
                            unset($_SESSION['error']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card edit-class-card mb-4">
                    <div class="card-header d-flex justify-content-between" style="background-color: <?php echo htmlspecialchars($class['color']); ?>;">
                        <h5 class="mb-0">Class Information</h5>
                        <div>
                            <span class="badge card-header-badge">Join Code: <?php echo htmlspecialchars($class['class_code']); ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <form action="edit_class.php?id=<?php echo $class_id; ?>" method="POST" class="edit-class-form">
                            <div class="mb-3">
                                <label for="className" class="form-label">Class Name *</label>
                                <input type="text" class="form-control" id="className" name="class_name" value="<?php echo htmlspecialchars($class['name']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="classDesc" class="form-label">Description</label>
                                <textarea class="form-control" id="classDesc" name="class_description" rows="3"><?php echo htmlspecialchars($class['description']); ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Color</label>
                                <div>
                                    <span class="color-option <?php echo $class['color'] === '#4285F4' ? 'active' : ''; ?>" data-color="#4285F4" style="background-color: #4285F4;" onclick="selectColor(this)"></span>
                                    <span class="color-option <?php echo $class['color'] === '#EA4335' ? 'active' : ''; ?>" data-color="#EA4335" style="background-color: #EA4335;" onclick="selectColor(this)"></span>
                                    <span class="color-option <?php echo $class['color'] === '#FBBC05' ? 'active' : ''; ?>" data-color="#FBBC05" style="background-color: #FBBC05;" onclick="selectColor(this)"></span>
                                    <span class="color-option <?php echo $class['color'] === '#34A853' ? 'active' : ''; ?>" data-color="#34A853" style="background-color: #34A853;" onclick="selectColor(this)"></span>
                                    <span class="color-option <?php echo $class['color'] === '#8E24AA' ? 'active' : ''; ?>" data-color="#8E24AA" style="background-color: #8E24AA;" onclick="selectColor(this)"></span>
                                    <span class="color-option <?php echo $class['color'] === '#F4511E' ? 'active' : ''; ?>" data-color="#F4511E" style="background-color: #F4511E;" onclick="selectColor(this)"></span>
                                    <input type="hidden" name="class_color" id="classColor" value="<?php echo htmlspecialchars($class['color']); ?>">
                                </div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <button type="submit" class="btn btn-primary">Update Class</button>
                                <button type="button" class="btn btn-danger" onclick="showDeleteWarning()">
                                    <i class="bi bi-trash"></i> Delete Class
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Delete Warning Modal -->
                <div class="modal fade" id="deleteWarningModal" tabindex="-1" aria-labelledby="deleteWarningModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header bg-danger text-white">
                                <h5 class="modal-title" id="deleteWarningModalLabel">Warning: Delete Class</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p class="warning-text fw-bold">Warning: This action cannot be undone!</p>
                                <p>Deleting this class will:</p>
                                <ul>
                                    <li>Remove all tasks associated with this class</li>
                                    <li>Remove all student enrollments in this class</li>
                                    <li>Delete all submissions and grades for tasks in this class</li>
                                </ul>
                                <p>Are you sure you want to permanently delete this class?</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <a href="delete_class.php?id=<?php echo $class_id; ?>" class="btn btn-danger">
                                    Yes, Delete This Class
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectColor(element) {
            // Remove active class from all color options
            document.querySelectorAll(".color-option").forEach(option => {
                option.classList.remove("active");
            });
            
            // Add active class to selected color
            element.classList.add("active");
            
            // Update hidden input value
            document.getElementById("classColor").value = element.getAttribute("data-color");
            
            // Update card header background color
            document.querySelector(".card-header").style.backgroundColor = element.getAttribute("data-color");
        }

        function showDeleteWarning() {
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteWarningModal'));
            deleteModal.show();
        }
    </script>
</body>
</html> 