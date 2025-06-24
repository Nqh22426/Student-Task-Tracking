<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

// Check if image was uploaded
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    $file = $_FILES['profile_image'];
    
    // Validate file type and size
    if (!in_array($file['type'], $allowed_types)) {
        $_SESSION['error'] = "Only JPEG, JPG and PNG images are allowed";
        header("Location: profile.php");
        exit();
    }
    
    if ($file['size'] > $max_size) {
        $_SESSION['error'] = "File size must be less than 5MB";
        header("Location: profile.php");
        exit();
    }
    
    // Generate unique filename
    $filename = uniqid() . '_' . time() . '_' . str_replace(' ', '_', $file['name']);
    $upload_dir = '../uploads/profile_images/';
    $upload_path = $upload_dir . $filename;
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Delete old profile image if exists
    $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $old_image = $stmt->fetchColumn();
    
    if ($old_image && file_exists($upload_dir . $old_image)) {
        unlink($upload_dir . $old_image);
    }
    
    // Upload new image
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        // Update database
        $stmt = $pdo->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
        $stmt->execute([$filename, $_SESSION['user_id']]);
        
        $_SESSION['success'] = "Profile image updated successfully";
    } else {
        $_SESSION['error'] = "Failed to upload image. Please try again.";
    }
} else {
    $_SESSION['error'] = "No image was uploaded or an error occurred";
}

// Redirect back to profile page
header("Location: profile.php");
exit(); 