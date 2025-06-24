<?php
session_start();

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}
// If not logged in, redirect to login page
else {
    header("Location: login.php");
    exit();
}
?> 