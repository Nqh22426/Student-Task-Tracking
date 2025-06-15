<?php
/**
 * This script checks for tasks that are due within 24 hours and sends reminder emails
 */

// Set the working directory to the project root
chdir(dirname(__DIR__));

require_once 'config.php';
require_once 'includes/notification_service.php';

// Log the execution
error_log("Deadline reminder check started at " . date('Y-m-d H:i:s'));

try {
    // Create notification service instance
    $notificationService = new NotificationService($pdo);
    
    // Check for deadline reminders
    $result = $notificationService->createDeadlineReminders();
    
    if ($result) {
        echo "Deadline reminders checked and sent successfully\n";
        error_log("Deadline reminders processed successfully at " . date('Y-m-d H:i:s'));
    } else {
        echo "Error processing deadline reminders\n";
        error_log("Error processing deadline reminders at " . date('Y-m-d H:i:s'));
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    error_log("Deadline reminder error: " . $e->getMessage());
}

echo "Deadline reminder check completed at " . date('Y-m-d H:i:s') . "\n";
?> 