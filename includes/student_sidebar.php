<style>

.sidebar {
    position: fixed;
    top: 0;
    bottom: 0;
    left: 0;
    z-index: 100;
    padding: 48px 0 0;
    box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
    background-color: #343a40;
    width: <?php echo isset($class_id) && isset($class) ? '180px' : '250px'; ?>;
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
    padding: <?php echo isset($class_id) && isset($class) ? '0.7rem 0.8rem' : '0.8rem 1rem'; ?>;
    transition: all 0.3s ease;
    font-size: <?php echo isset($class_id) && isset($class) ? '0.9rem' : '1rem'; ?>;
}
.sidebar .nav-link.active {
    color: #ffffff;
    background-color: #495057;
    border-left: 4px solid #1a73e8;
}
.sidebar .nav-link:hover {
    color: #1a73e8;
    background-color: #495057;
}
.sidebar .nav-link i {
    margin-right: 8px;
    width: 18px;
    text-align: center;
}
.main-content {
    margin-left: <?php echo isset($class_id) && isset($class) ? '180px' : '250px'; ?>;
    padding: 20px;
}
.content-wrapper {
    margin-top: 48px;
    padding: 0 20px;
}
.navbar {
    position: fixed;
    top: 0;
    right: 0;
    left: <?php echo isset($class_id) && isset($class) ? '180px' : '250px'; ?>;
    z-index: 99;
    height: 48px;
    padding: 0 20px;
}

/* For smaller screens */
@media (max-width: 767.98px) {
    .sidebar {
        width: 100%;
        height: auto;
        position: relative;
        padding-top: 0;
    }
    .main-content {
        margin-left: 0;
    }
    .navbar {
        left: 0;
    }
}

.sidebar .badge {
    font-size: 0.65rem;
    font-weight: 600;
    padding: 0.25em 0.5em;
    vertical-align: middle;
}

/* Red dot for notifications */
.notification-dot {
    width: 8px;
    height: 8px;
    background-color: #dc3545;
    border-radius: 50%;
    display: inline-block;
    margin-left: 8px;
    vertical-align: middle;
}
</style>

<nav class="sidebar">
    <div class="sidebar-sticky">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo $active_page === 'dashboard' ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="bi bi-grid"></i>
                    Your Classes
                </a>
            </li>
            <?php if(!isset($class_id) || !isset($class)): ?>
            <?php
            // Get count of ongoing tasks for the badge
            // Only calculate if not already set by the page
            if (!isset($todo_count)) {
                $todo_count = 0;
                if(isset($student_id)) {
                    try {
                        // Get enrolled classes first
                        $stmt = $pdo->prepare("SELECT c.id FROM classes c JOIN class_enrollments ce ON c.id = ce.class_id WHERE ce.student_id = ?");
                        $stmt->execute([$student_id]);
                        $enrolled_classes = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        
                        if(!empty($enrolled_classes)) {
                            $placeholders = implode(',', array_fill(0, count($enrolled_classes), '?'));
                            // Get all ongoing tasks
                            $query = "SELECT t.id FROM tasks t 
                                    WHERE t.class_id IN ($placeholders)
                                    AND t.start_datetime <= NOW()
                                    AND t.end_datetime >= NOW()";
                            $stmt = $pdo->prepare($query);
                            $stmt->execute($enrolled_classes);
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
                    } catch (PDOException $e) {
                        $todo_count = 0;
                    }
                }
            }
            ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $active_page === 'todo' ? 'active' : ''; ?>" href="todo_list.php">
                    <i class="bi bi-check2-square"></i>
                    To-do List
                    <?php if($todo_count > 0): ?>
                    <span class="badge  bg-danger ms-1"><?php echo $todo_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endif; ?>
            <?php if(isset($class_id) && isset($class)): ?>
            <li class="nav-item mt-3">
                <a class="nav-link <?php echo $active_page === 'calendar' ? 'active' : ''; ?>" href="class_details.php?id=<?php echo $class_id; ?>">
                    <i class="bi bi-calendar3"></i>
                    Calendar
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $active_page === 'tasks' ? 'active' : ''; ?>" href="tasks_list.php?id=<?php echo $class_id; ?>">
                    <i class="bi bi-list-check"></i>
                    Tasks List
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $active_page === 'grade' ? 'active' : ''; ?>" href="grade.php?id=<?php echo $class_id; ?>">
                    <i class="bi bi-award"></i>
                    Your Grade
                </a>
            </li>
            <?php
            // Check if there are unread notifications for this student and class
            $has_unread_notifications = false;
            if(isset($student_id) && isset($class_id)) {
                try {
                    $stmt = $pdo->prepare("SELECT EXISTS(SELECT 1 FROM notifications WHERE recipient_id = ? AND class_id = ? AND status = 'sent' AND is_read = 0)");
                    $stmt->execute([$student_id, $class_id]);
                    $has_unread_notifications = (bool)$stmt->fetchColumn();
                } catch (PDOException $e) {
                    $has_unread_notifications = false;
                }
            }
            ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $active_page === 'notifications' ? 'active' : ''; ?>" href="notifications.php?id=<?php echo $class_id; ?>">
                    <i class="bi bi-bell"></i>
                    Notifications
                    <?php if($has_unread_notifications && $active_page !== 'notifications'): ?>
                    <span class="notification-dot"></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php else: ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $active_page === 'profile' ? 'active' : ''; ?>" href="profile.php">
                    <i class="bi bi-person"></i>
                    Profile
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </div>
</nav>