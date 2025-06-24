<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['class_name'] ?? '');
    $description = trim($_POST['class_description'] ?? '');
    $color = $_POST['class_color'] ?? '#4285F4';
    
    // Validate class name
    if (empty($name)) {
        $_SESSION['error'] = "Class name is required";
        header("Location: create_class.php");
        exit();
    }
    
    // Generate a unique join code
    $class_code = generateJoinCode();
    
    try {
        // Create the class
        $stmt = $pdo->prepare("INSERT INTO classes (name, description, color, teacher_id, class_code, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$name, $description, $color, $teacher_id, $class_code]);
        
        $_SESSION['success'] = "Class created successfully";
        header("Location: dashboard.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error creating class: " . $e->getMessage();
        header("Location: create_class.php");
        exit();
    }
}

// Function to generate a random join code
function generateJoinCode($length = 6) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    
    // Ensure unique code
    do {
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[rand(0, strlen($characters) - 1)];
        }
        
        // Check if code already exists
        global $pdo;
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE class_code = ?");
        $stmt->execute([$code]);
        $exists = $stmt->fetchColumn() > 0;
    } while ($exists);
    
    return $code;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Class - Student Task Tracking</title>
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

        /* Create Class Styles */
        .create-class-card {
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .create-class-card .card-header {
            background-color: #4285F4;
            color: white;
            border-radius: 8px 8px 0 0;
            padding: 15px 20px;
        }
        .create-class-form .form-group {
            margin-bottom: 1.5rem;
        }
        .create-class-form .form-label {
            font-weight: 500;
        }
        .create-class-form input[type="radio"] {
            margin-right: 5px;
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
                    <h2>Create New Class</h2>
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

                <div class="card create-class-card">
                    <div class="card-header">
                        <h5 class="mb-0">Class Information</h5>
                    </div>
                    <div class="card-body">
                        <form action="create_class.php" method="POST" class="create-class-form">
                            <div class="mb-3">
                                <label for="className" class="form-label">Class Name *</label>
                                <input type="text" class="form-control" id="className" name="class_name" required>
                                <div class="form-text">For example: "Math 101", "Introduction to Science", etc.</div>
                            </div>
                            <div class="mb-3">
                                <label for="classDesc" class="form-label">Description</label>
                                <textarea class="form-control" id="classDesc" name="class_description" rows="3" placeholder="Provide a brief description of the class"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Color</label>
                                <div>
                                    <span class="color-option active" data-color="#4285F4" style="background-color: #4285F4;" onclick="selectColor(this)"></span>
                                    <span class="color-option" data-color="#EA4335" style="background-color: #EA4335;" onclick="selectColor(this)"></span>
                                    <span class="color-option" data-color="#FBBC05" style="background-color: #FBBC05;" onclick="selectColor(this)"></span>
                                    <span class="color-option" data-color="#34A853" style="background-color: #34A853;" onclick="selectColor(this)"></span>
                                    <span class="color-option" data-color="#8E24AA" style="background-color: #8E24AA;" onclick="selectColor(this)"></span>
                                    <span class="color-option" data-color="#F4511E" style="background-color: #F4511E;" onclick="selectColor(this)"></span>
                                    <input type="hidden" name="class_color" id="classColor" value="#4285F4">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Create Class</button>
                        </form>
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
    </script>
</body>
</html> 