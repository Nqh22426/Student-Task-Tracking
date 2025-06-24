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
    width: 180px;
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
    padding: 0.7rem 0.8rem;
    transition: all 0.3s ease;
    font-size: 0.9rem;
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

.sidebar .nav-link.submissions-link {
    font-size: 0.85rem;
    padding-right: 4px;
}
.main-content {
    margin-left: 180px;
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
    left: 180px;
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
                <a class="nav-link submissions-link <?php echo $active_page === 'submissions' ? 'active' : ''; ?>" href="student_submissions.php?id=<?php echo $class_id; ?>">
                    <i class="bi bi-file-earmark-text"></i>
                    Student Submissions
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $active_page === 'students' ? 'active' : ''; ?>" href="students.php?id=<?php echo $class_id; ?>">
                    <i class="bi bi-people"></i>
                    Students
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </div>
</nav> 