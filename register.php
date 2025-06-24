<?php
session_start();
require_once 'config.php';

// If already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin/teacher_management.php");
    } elseif ($_SESSION['role'] === 'teacher') {
        header("Location: teacher/dashboard.php");
    } else {
        header("Location: student/dashboard.php");
    }
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];

    // Basic validation
    if (empty($role)) {
        $error = "Please select a role";
    } elseif (empty($username)) {
        $error = "Username is required";
    } elseif (empty($email)) {
        $error = "Email is required";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            $error = "Email already exists";
        } else {
            // Check if username already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->rowCount() > 0) {
                $error = "Username already exists";
            } else {
                // Create new user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
                try {
                    $stmt->execute([$username, $email, $hashed_password, $role]);
                    $success = "Registration successful! You can now login.";
                } catch(PDOException $e) {
                    $error = "Registration failed: " . $e->getMessage();
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Student Task Tracking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .auth-container {
            max-width: 500px;
            margin: 50px auto;
            padding: 30px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .auth-title {
            text-align: center;
            margin-bottom: 1.5rem;
            color: #333;
            font-weight: 600;
        }
        .auth-form .form-control, .auth-form .form-select {
            border-radius: 5px;
            padding: 12px 15px;
            border: 1px solid #ddd;
            transition: all 0.3s;
        }
        .auth-form .form-control:focus, .auth-form .form-select:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        .auth-form .form-label {
            font-weight: 500;
            color: #555;
        }
        .auth-form .btn-primary {
            padding: 12px;
            font-weight: 500;
            background-color: #007bff;
            border: none;
            transition: all 0.3s;
        }
        .auth-form .btn-primary:hover {
            background-color: #0056b3;
            transform: translateY(-2px);
        }
        .auth-footer {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.9rem;
            color: #6c757d;
        }
        .auth-footer a {
            color: #007bff;
            text-decoration: none;
            font-weight: 500;
        }
        .auth-footer a:hover {
            text-decoration: underline;
        }
        .app-logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        .app-logo i {
            font-size: 3rem;
            color: #007bff;
        }
        .app-name {
            display: block;
            font-size: 1.5rem;
            font-weight: 600;
            color: #333;
            margin-top: 0.5rem;
        }
        .role-select-container {
            margin-bottom: 1.5rem;
        }
        .role-option {
            display: inline-block;
            width: 48%;
            text-align: center;
            padding: 15px;
            margin: 0 1% 0 0;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .role-option:last-child {
            margin-right: 0;
        }
        .role-option:hover {
            border-color: #007bff;
        }
        .role-option.selected {
            border-color: #007bff;
            background-color: #f0f7ff;
        }
        .role-option i {
            font-size: 2rem;
            margin-bottom: 10px;
            color: #6c757d;
        }
        .role-option.selected i {
            color: #007bff;
        }
        .role-option-title {
            display: block;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="auth-container">
            <div class="app-logo">
                <i class="bi bi-check2-square"></i>
                <span class="app-name">Student Task Tracking</span>
            </div>
            <h2 class="auth-title">Create an Account</h2>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            <form method="POST" action="" class="auth-form">
                <div class="mb-3">
                    <label class="form-label">I am:</label>
                    <div class="d-flex justify-content-between role-select-container">
                        <div class="role-option" id="teacherRole" onclick="selectRole('teacher')">
                            <i class="bi bi-person-workspace"></i>
                            <span class="role-option-title">Teacher</span>
                        </div>
                        <div class="role-option" id="studentRole" onclick="selectRole('student')">
                            <i class="bi bi-mortarboard"></i>
                            <span class="role-option-title">Student</span>
                        </div>
                    </div>
                    <input type="hidden" id="role" name="role" value="">
                </div>
                <div class="mb-3">
                    <label for="username" class="form-label">Username *</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email address *</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password *</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm Password *</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Register</button>
            </form>
            <div class="auth-footer">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectRole(role) {
            document.getElementById('role').value = role;
            
            document.querySelectorAll('.role-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            if (role === 'teacher') {
                document.getElementById('teacherRole').classList.add('selected');
            } else {
                document.getElementById('studentRole').classList.add('selected');
            }
        }
    </script>
</body>
</html> 