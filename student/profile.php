<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['user_id'];

// Fetch student information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Get number of enrolled classes
$stmt = $pdo->prepare("SELECT COUNT(*) as class_count FROM class_enrollments WHERE student_id = ?");
$stmt->execute([$student_id]);
$class_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Set active page
$active_page = 'profile';

// Get enrolled classes to calculate todo count
$stmt = $pdo->prepare("
    SELECT c.id
    FROM classes c
    JOIN class_enrollments ce ON c.id = ce.class_id
    WHERE ce.student_id = ?
");
$stmt->execute([$student_id]);
$class_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Calculate the todo_count for the sidebar badge
$todo_count = 0;
if(!empty($class_ids)) {
    $placeholders = implode(',', array_fill(0, count($class_ids), '?'));

    $query = "SELECT t.id FROM tasks t 
              WHERE t.class_id IN ($placeholders)
              AND t.start_datetime <= NOW()
              AND t.end_datetime >= NOW()";
    $stmt = $pdo->prepare($query);
    $stmt->execute($class_ids);
    $task_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get submissions for these tasks
    if (!empty($task_ids)) {
        $placeholders = implode(',', array_fill(0, count($task_ids), '?'));
        $stmt = $pdo->prepare("SELECT task_id FROM submissions WHERE student_id = ? AND task_id IN ($placeholders)");
        $stmt->execute(array_merge([$student_id], $task_ids));
        $submitted_task_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Calculate count of tasks without submissions
        $todo_count = count($task_ids) - count($submitted_task_ids);
    }
}

$page_title = "Student Profile";
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Student Task Tracking</title>
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
        
        /* Profile styles */
        .profile-card {
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .stats-card {
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }
        .profile-image-container {
            position: relative;
            width: 150px;
            height: 150px;
            margin: 0 auto 20px;
        }
        .profile-image {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            background-color: #007bff;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }
        .profile-image:hover {
            opacity: 0.9;
        }
        .profile-image-overlay {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #495057;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 2px solid white;
        }
        .profile-image-input {
            display: none;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-label {
            font-weight: 500;
        }
        .btn-update {
            width: 100%;
            padding: 10px;
            font-weight: 500;
        }
        .password-rules {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 0.5rem;
        }
        .password-rules ul {
            padding-left: 1.5rem;
            margin-bottom: 0;
        }
        .alert-container {
            position: fixed;
            top: 60px;
            right: 20px;
            z-index: 1000;
            max-width: 350px;
        }
    </style>
</head>
<body>
    <?php if(isset($_SESSION['success']) || isset($_SESSION['error'])): ?>
    <div class="alert-container">
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $_SESSION['success']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Sidebar -->
    <?php include_once '../includes/student_sidebar.php'; ?>

    <!-- Top navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <span class="navbar-brand">Student Dashboard</span>
            <div class="navbar-nav ms-auto">
                <?php if (!empty($student['profile_image'])): ?>
                    <a href="profile.php" class="nav-item nav-link p-0 me-3 d-flex align-items-center" style="height: 48px;">
                        <img src="../uploads/profile_images/<?php echo htmlspecialchars($student['profile_image']); ?>" class="rounded-circle" alt="Profile" width="32" height="32" style="border: 2px solid #ffffff;">
                    </a>
                <?php else: ?>
                    <a href="profile.php" class="nav-item nav-link p-0 me-3 d-flex align-items-center" style="height: 48px;">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 16px; border: 2px solid #ffffff;">
                            <?php echo strtoupper(substr($student['username'], 0, 1)); ?>
                        </div>
                    </a>
                <?php endif; ?>
                <a class="nav-item nav-link" href="../logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main content -->
    <div class="main-content">
        <div class="content-wrapper">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Student Profile</h2>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="card profile-card">
                            <div class="card-body">
                                <h4 class="card-title mb-4">Account Information</h4>
                                
                                <div class="profile-image-container">
                                    <?php if (!empty($student['profile_image'])): ?>
                                        <div class="profile-image" id="profileImageDisplay" style="background-image: url('../uploads/profile_images/<?php echo htmlspecialchars($student['profile_image']); ?>'); background-size: cover; background-color: transparent;">
                                        </div>
                                    <?php else: ?>
                                        <div class="profile-image" id="profileImageDisplay">
                                            <?php echo strtoupper(substr($student['username'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="profile-image-overlay" id="profileImageOverlay">
                                        <i class="bi bi-camera"></i>
                                    </div>
                                    <form id="profileImageForm" action="upload_profile_image.php" method="POST" enctype="multipart/form-data">
                                        <input type="file" name="profile_image" id="profileImageInput" class="profile-image-input" accept="image/jpeg, image/png, image/jpg">
                                    </form>
                                </div>
                                
                                <form action="update_profile.php" method="POST" class="profile-form">
                                    <div class="mb-3">
                                        <label for="username" class="form-label">Username</label>
                                        <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($student['username']); ?>" readonly>
                                    </div>
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($student['email']); ?>" readonly>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Joined Date</label>
                                        <input type="text" class="form-control" value="<?php echo date('d/m/Y', strtotime($student['created_at'])); ?>" readonly>
                                    </div>
                                    <button type="button" class="btn btn-primary w-100" onclick="togglePasswordForm()">
                                        <i class="bi bi-key me-1"></i> Change Password
                                    </button>

                                    <div id="passwordFormContainer" class="mt-4" style="display: none;">
                                        <hr>
                                        <h5 class="mb-3">Change Password</h5>
                                        <div class="mb-3">
                                            <label for="current_password" class="form-label">Current Password</label>
                                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="new_password" class="form-label">New Password</label>
                                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <button type="submit" class="btn btn-success" formaction="change_password.php">Save New Password</button>
                                            <button type="button" class="btn btn-secondary" onclick="togglePasswordForm()">Cancel</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card stats-card mb-4">
                            <div class="card-body">
                                <h4 class="card-title mb-4">Account Statistics</h4>
                                <div class="text-center">
                                    <div class="display-4"><?php echo $class_stats['class_count']; ?></div>
                                    <div class="text-muted">Classes Joined</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const profileImageDisplay = document.getElementById("profileImageDisplay");
            const profileImageOverlay = document.getElementById("profileImageOverlay");
            const profileImageInput = document.getElementById("profileImageInput");
            const profileImageForm = document.getElementById("profileImageForm");
            
            profileImageDisplay.addEventListener("click", function() {
                profileImageInput.click();
            });
            
            profileImageOverlay.addEventListener("click", function() {
                profileImageInput.click();
            });
            
            // Auto-submit form when file is selected
            profileImageInput.addEventListener("change", function() {
                if (this.files && this.files[0]) {
                    profileImageForm.submit();
                }
            });
        });

        function togglePasswordForm() {
            const formContainer = document.getElementById('passwordFormContainer');
            if (formContainer.style.display === 'none') {
                formContainer.style.display = 'block';
                formContainer.scrollIntoView({ behavior: 'smooth' });
            } else {
                formContainer.style.display = 'none';
            }
        }
    </script>
</body>
</html> 