<?php
session_start();
require_once '../config.php';

// check login and role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['user_id'];

// check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = trim($_POST['current_password'] ?? '');
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    
    // check input information
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $_SESSION['error'] = "Please fill in all the information";
        header("Location: profile.php");
        exit();
    }
    
    // check if new password and confirm password match
    if ($new_password !== $confirm_password) {
        $_SESSION['error'] = "New password and confirm password do not match";
        header("Location: profile.php");
        exit();
    }
    
    // check if new password meets the requirements
    if (strlen($new_password) < 8 || 
        !preg_match('/[A-Z]/', $new_password) || 
        !preg_match('/[a-z]/', $new_password) || 
        !preg_match('/[0-9]/', $new_password)) {
        $_SESSION['error'] = "New password must be at least 8 characters and include uppercase, lowercase, and numbers";
        header("Location: profile.php");
        exit();
    }
    
    // get current password from database
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$student_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $_SESSION['error'] = "User information not found";
        header("Location: profile.php");
        exit();
    }
    
    // check if current password is correct
    if (!password_verify($current_password, $user['password'])) {
        $_SESSION['error'] = "Current password is incorrect";
        header("Location: profile.php");
        exit();
    }
    
    // hash new password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    // update new password
    try {
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed_password, $student_id]);
        
        $_SESSION['success'] = "Password updated successfully";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating password: " . $e->getMessage();
    }
}

header("Location: profile.php");
exit();
?> 