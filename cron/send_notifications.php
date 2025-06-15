<?php
/**
 * This script sends all pending email notifications
 */

// Set the working directory to the project root
chdir(dirname(__DIR__));

require_once 'config.php';
require_once 'includes/notification_service.php';

// Log the execution
error_log("Notification sender started at " . date('Y-m-d H:i:s'));

try {
    // Create notification service instance
    $notificationService = new NotificationService($pdo);
    
    // Send all pending notifications
    $result = $notificationService->sendPendingNotifications();
    
    if ($result) {
        echo "Pending notifications sent successfully\n";
        error_log("Pending notifications sent successfully at " . date('Y-m-d H:i:s'));
    } else {
        echo "Error sending pending notifications\n";
        error_log("Error sending pending notifications at " . date('Y-m-d H:i:s'));
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    error_log("Notification sender error: " . $e->getMessage());
}

echo "Notification sender completed at " . date('Y-m-d H:i:s') . "\n";
?> 