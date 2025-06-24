<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
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
$student_id = $_SESSION['user_id'];

// Verify that the student is enrolled in this class
$stmt = $pdo->prepare("
    SELECT c.* 
    FROM classes c
    JOIN class_enrollments ce ON c.id = ce.class_id
    WHERE c.id = ? AND ce.student_id = ?
");
$stmt->execute([$class_id, $student_id]);
$class = $stmt->fetch();

if (!$class) {
    $_SESSION['error'] = "Class not found or you don't have permission to access it";
    header("Location: dashboard.php");
    exit();
}

// Get notifications for this student and class
$stmt = $pdo->prepare("
    SELECT n.*, t.title as task_title
    FROM notifications n
    LEFT JOIN tasks t ON n.task_id = t.id
    WHERE n.recipient_id = ? AND n.class_id = ?
    ORDER BY n.created_at DESC
    LIMIT 50
");
$stmt->execute([$student_id, $class_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Mark all notifications as read when user visits this page
try {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE recipient_id = ? AND class_id = ? AND is_read = 0");
    $stmt->execute([$student_id, $class_id]);
} catch (PDOException $e) {
}

// Set page variables
$page_title = "Notifications - " . htmlspecialchars($class['name']);
$active_page = 'notifications';

include_once '../includes/header.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

<style>
.main-content {
    margin-left: 180px;
    padding: 20px;
    padding-top: 220px;
}

.navbar {
    z-index: 1030;
    position: fixed !important;
    top: 0;
    left: 0;
    right: 0;
}

.main-content .container-fluid {
    position: relative;
    z-index: 1;
}

.main-content .mb-4 {
    margin-top: 20px;
    padding-top: 20px;
}

.notification-card {
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    margin-bottom: 15px;
    transition: all 0.3s ease;
}

.notification-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.notification-header {
    padding: 15px 20px;
    border-bottom: 1px solid #f0f0f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.notification-body {
    padding: 20px;
}

.notification-type {
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    padding: 4px 8px;
    border-radius: 12px;
    display: inline-block;
}

.type-task_created {
    background-color: #e3f2fd;
    color: #1976d2;
}

.type-task_updated {
    background-color: #fff3e0;
    color: #f57c00;
}

.type-task_deadline {
    background-color: #ffebee;
    color: #d32f2f;
}

.type-grade_sent {
    background-color: #e8f5e8;
    color: #388e3c;
}

.notification-date {
    font-size: 14px;
    color: #666;
}

.notification-status {
    font-size: 12px;
    padding: 2px 6px;
    border-radius: 8px;
    font-weight: 500;
}

.status-sent {
    background-color: #e8f5e8;
    color: #2e7d32;
}

.status-pending {
    background-color: #fff3e0;
    color: #f57c00;
}

.status-failed {
    background-color: #ffebee;
    color: #d32f2f;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #666;
}

.empty-state i {
    font-size: 64px;
    margin-bottom: 20px;
    color: #ddd;
}

.delete-notification {
    border: none !important;
    background: transparent !important;
    color: #666 !important;
    padding: 6px 8px !important;
    border-radius: 4px !important;
    transition: all 0.2s ease;
    min-width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px !important;
    cursor: pointer;
}

.delete-notification:hover {
    background: #dc3545 !important;
    color: white !important;
    transform: scale(1.05);
}

.delete-notification:focus {
    box-shadow: none !important;
    outline: none;
}

.delete-notification:active {
    transform: scale(0.95);
}

.delete-notification i {
    font-size: 14px !important;
    line-height: 1 !important;
    display: inline-block !important;
}

.delete-notification::before {
    font-family: "bootstrap-icons" !important;
}

.empty-notifications-btn {
    background: transparent;
    border: none;
    color: #dc3545;
    font-size: 14px;
    cursor: pointer;
    padding: 8px 12px;
    border-radius: 4px;
    transition: all 0.2s ease;
    text-decoration: none;
    margin-top: 8px;
}

.empty-notifications-btn:hover {
    background: #dc3545;
    color: white;
    text-decoration: none;
}

.empty-notifications-btn:focus {
    outline: none;
    box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
}
</style>

<!-- Include sidebar -->
<?php include_once '../includes/student_sidebar.php'; ?>

<!-- Custom Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <span class="navbar-brand"><?php echo htmlspecialchars($class['name']); ?></span>
        <div class="navbar-nav ms-auto">
            <a href="dashboard.php" class="nav-item nav-link">
                <i class="bi bi-arrow-left"></i> Back to Your Classes
            </a>
        </div>
    </div>
</nav>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="h3 mb-0">Notifications</h1>
                <?php if (!empty($notifications)): ?>
                    <button class="empty-notifications-btn" id="emptyNotificationsBtn">
                        Empty Notifications
                    </button>
                <?php endif; ?>
            </div>
        </div>
            
            <!-- Notifications List -->
            <?php if (empty($notifications)): ?>
                <div class="empty-state">
                    <i class="bi bi-bell-slash"></i>
                    <h4>No Notifications</h4>
                    <p>You don't have any notifications for this class yet.</p>
                </div>
            <?php else: ?>
                <div class="row">
                    <div class="col-12">
                        <?php foreach ($notifications as $notification): ?>
                            <div class="notification-card">
                                <div class="notification-header">
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="notification-type type-<?php echo $notification['type']; ?>">
                                            <?php 
                                            switch($notification['type']) {
                                                case 'task_created':
                                                    echo '<i class="bi bi-plus-circle me-1"></i>New Task';
                                                    break;
                                                case 'task_updated':
                                                    echo '<i class="bi bi-pencil-square me-1"></i>Task Updated';
                                                    break;
                                                case 'task_deadline':
                                                    echo '<i class="bi bi-clock me-1"></i>Deadline';
                                                    break;
                                                case 'grade_sent':
                                                    echo '<i class="bi bi-award me-1"></i>Grade';
                                                    break;
                                            }
                                            ?>
                                        </span>
                                        <?php if ($notification['task_title']): ?>
                                            <h5 class="mb-0"><?php echo htmlspecialchars($notification['task_title']); ?></h5>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="notification-date">
                                            <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                                        </span>
                                        <button class="delete-notification" 
                                                data-notification-id="<?php echo $notification['id']; ?>" 
                                                title="Delete notification">
                                            🗑️
                                        </button>
                                    </div>
                                </div>
                                <div class="notification-body">
                                    <?php 
                                    // Notification message
                                    switch($notification['type']) {
                                        case 'task_created':
                                            echo '<div class="alert alert-info alert-sm mb-2">';
                                            echo 'Your teacher has created a new task for the class.';
                                            echo '</div>';
                                            break;
                                        case 'task_updated':
                                            echo '<div class="alert alert-warning alert-sm mb-2">';
                                            echo 'Your teacher has updated the task details.';
                                            echo '</div>';
                                            break;
                                        case 'task_deadline':
                                            echo '<div class="alert alert-danger alert-sm mb-2">';
                                            echo 'Task deadline is approaching. Please submit your work on time.';
                                            echo '</div>';
                                            break;
                                        case 'grade_sent':
                                            echo '<div class="alert alert-success alert-sm mb-2">';
                                            echo 'Your teacher has graded your submission.';
                                            echo '</div>';
                                            break;
                                    }
                                    ?>
                                    
                                    <?php if ($notification['status'] === 'pending'): ?>
                                        <div class="alert alert-warning alert-sm mb-0">
                                            <i class="bi bi-clock me-2"></i>
                                            Email is pending to be sent
                                        </div>
                                    <?php elseif ($notification['status'] === 'failed'): ?>
                                        <div class="alert alert-danger alert-sm mb-0">
                                            <i class="bi bi-exclamation-triangle me-2"></i>
                                            Failed to send email
                                            <?php if ($notification['error_message']): ?>
                                                <br><small><?php echo htmlspecialchars($notification['error_message']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle delete notification
    document.querySelectorAll('.delete-notification').forEach(function(button) {
        button.addEventListener('click', function() {
            const notificationId = this.getAttribute('data-notification-id');
            const notificationCard = this.closest('.notification-card');
            
            // Send AJAX request to delete notification immediately
            fetch('delete_notification.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    notification_id: notificationId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove notification card
                    notificationCard.style.transition = 'all 0.3s ease';
                    notificationCard.style.opacity = '0';
                    notificationCard.style.transform = 'translateX(100%)';
                    
                    setTimeout(() => {
                        notificationCard.remove();
                        
                        // Check if no notifications left
                        const remainingNotifications = document.querySelectorAll('.notification-card');
                        if (remainingNotifications.length === 0) {
                            location.reload();
                        }
                    }, 300);
                } else {
                    console.error('Error deleting notification:', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        });
    });
    
    // Handle empty all notifications
    document.getElementById('emptyNotificationsBtn').addEventListener('click', function() {
        const notificationCards = document.querySelectorAll('.notification-card');
        
        if (notificationCards.length === 0) {
            return;
        }
        
        // Show confirmation dialog
        if (!confirm('Are you sure you want to delete all notifications? Please make sure you have read all important notifications.')) {
            return;
        }
        
        // Get all notification IDs
        const notificationIds = Array.from(notificationCards).map(card => {
            const deleteBtn = card.querySelector('.delete-notification');
            return deleteBtn.getAttribute('data-notification-id');
        });
        
        // Send AJAX request to delete all notifications
        fetch('empty_all_notifications.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                notification_ids: notificationIds
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove all notification cards
                notificationCards.forEach((card, index) => {
                    setTimeout(() => {
                        card.style.transition = 'all 0.3s ease';
                        card.style.opacity = '0';
                        card.style.transform = 'translateX(100%)';
                        
                        setTimeout(() => {
                            card.remove();
                            
                            // Reload page after last notification is removed
                            if (index === notificationCards.length - 1) {
                                setTimeout(() => {
                                    location.reload();
                                }, 300);
                            }
                        }, 300);
                    }, index * 100);
                });
            } else {
                console.error('Error emptying notifications:', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    });
});
</script>
</body>
</html>