<?php
session_start();
require_once '../config.php';

// Check login and role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['user_id'];

// Check if file is uploaded
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
    $max_size = 2 * 1024 * 1024; // 2MB
    
    // Check file type
    if (!in_array($_FILES['profile_image']['type'], $allowed_types)) {
        $_SESSION['error'] = "Only JPG, JPEG and PNG files are allowed";
        header("Location: profile.php");
        exit();
    }
    
    // Check file size
    if ($_FILES['profile_image']['size'] > $max_size) {
        $_SESSION['error'] = "File size cannot exceed 2MB";
        header("Location: profile.php");
        exit();
    }
    
    // Create uploads directory if it doesn't exist
    $upload_dir = '../uploads/profile_images/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Create new filename to avoid duplicates
    $file_extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
    $new_filename = 'student_' . $student_id . '_' . time() . '.' . $file_extension;
    $upload_path = $upload_dir . $new_filename;
    
    // Move temporary file to storage location
    if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
        // Update filename in database
        try {
            // Delete old image if it exists
            $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
            $stmt->execute([$student_id]);
            $old_image = $stmt->fetchColumn();
            
            if (!empty($old_image) && file_exists($upload_dir . $old_image)) {
                unlink($upload_dir . $old_image);
            }
            
            // Update new image
            $stmt = $pdo->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
            $stmt->execute([$new_filename, $student_id]);
            
            $_SESSION['success'] = "Profile image updated successfully";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error updating profile image: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "An error occurred while uploading the image";
    }
} else {
    $_SESSION['error'] = "No file was uploaded or the file is invalid";
}

header("Location: profile.php");
exit();
?> 