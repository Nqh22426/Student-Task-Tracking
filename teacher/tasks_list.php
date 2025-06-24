<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
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
$teacher_id = $_SESSION['user_id'];

// Fetch class details, ensuring it belongs to the current teacher
$stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ? AND teacher_id = ?");
$stmt->execute([$class_id, $teacher_id]);
$class = $stmt->fetch();

if (!$class) {
    $_SESSION['error'] = "Class not found or you don't have permission to access it";
    header("Location: dashboard.php");
    exit();
}

// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Build the SQL query based on search and filter
$sql = "SELECT * FROM tasks WHERE class_id = ?";
$params = [$class_id];

if (!empty($search)) {
    $sql .= " AND (title LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Add filter conditions
switch ($filter) {
    case 'upcoming':
        $sql .= " AND NOW() < start_datetime";
        break;
    case 'ongoing':
        $sql .= " AND NOW() BETWEEN start_datetime AND end_datetime";
        break;
    case 'completed':
        $sql .= " AND end_datetime < NOW()";
        break;
}

$sql .= " ORDER BY start_datetime ASC";

// Execute the query
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

// Current date for highlighting
$now = new DateTime();

// Set page variables
$page_title = htmlspecialchars($class['name']) . ' - Tasks List';
$navbar_title = 'Class: ' . htmlspecialchars($class['name']);
$active_page = 'tasks';

include_once '../includes/header.php';
?>

<style>
.main-content {
    margin-left: 180px;
    padding: 20px;
    padding-top: 68px;
}

.tasks-container {
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
    padding: 20px;
    margin-bottom: 30px;
}

.tasks-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.tasks-title {
    font-size: 22px;
    font-weight: 400;
    color: #3c4043;
    margin: 0;
}

.btn-add-task {
    background-color: #1a73e8;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    font-size: 14px;
    cursor: pointer;
    transition: background-color 0.2s;
}

.btn-add-task:hover {
    background-color: #1765cc;
}

.filter-card {
    background-color: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #e0e0e0;
    padding: 15px;
    margin-bottom: 25px;
}

.task-list-item {
    background-color: #fff;
    border-radius: 8px;
    border: 1px solid #e0e0e0;
    padding: 15px;
    margin-bottom: 15px;
    transition: box-shadow 0.2s, transform 0.2s;
}

.task-list-item:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.task-list-item h5 {
    color: #3c4043;
    margin-bottom: 12px;
    font-weight: 500;
}

.task-dates {
    color: #5f6368;
    font-size: 0.9rem;
}

.task-dates i {
    color: #5f6368;
    margin-right: 5px;
}

.task-actions {
    margin-top: 15px;
}

.task-actions .btn {
    padding: 5px 10px;
    font-size: 0.85rem;
}

/* Task status badges */
.task-list-item.upcoming {
    border-left: 4px solid #28a745; /* Green */
}

.task-list-item.ongoing {
    border-left: 4px solid #ffc107; /* Yellow */
}

.task-list-item.overdue {
    border-left: 4px solid #ea4335; /* Red */
}

.task-list-item.completed {
    border-left: 4px solid #dc3545; /* Red */
}

.badge.bg-upcoming {
    background-color: #28a745 !important; /* Green */
}

.badge.bg-ongoing {
    background-color: #ffc107 !important; /* Yellow */
    color: #212529 !important;
}

.badge.bg-overdue {
    background-color: #ea4335 !important; /* Red */
}

.badge.bg-completed {
    background-color: #dc3545 !important; /* Red */
}

/* Empty state styling */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    background-color: #f8f9fa;
    border-radius: 8px;
    margin-top: 20px;
}

.empty-state i {
    font-size: 48px;
    color: #dadce0;
    margin-bottom: 15px;
}

.empty-state h4 {
    color: #3c4043;
    margin-bottom: 10px;
}

.empty-state p {
    color: #5f6368;
    margin-bottom: 20px;
}

/* Task file styling */
.bi-file-pdf {
    color: #dc3545;
    font-size: 1.1rem;
    margin-right: 5px;
}

.mt-2 .small {
    margin-right: 10px;
    font-weight: 500;
}

.mt-2 .btn-sm {
    padding: 2px 8px;
    font-size: 0.8rem;
}

/* Modal styling */
.modal-header.task-modal-header {
    background-color: #1a73e8;
    color: white;
}

.modal-content {
    border-radius: 8px;
    border: none;
}

.btn-close.white-close {
    filter: brightness(0) invert(1);
}
</style>

<!-- Include sidebar and navbar -->
<?php include_once '../includes/teacher_sidebar.php'; ?>
<?php include_once '../includes/navbar.php'; ?>

<!-- Main content -->
<div class="main-content">
    <div class="content-wrapper">
        <div class="container-fluid">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php 
                        echo htmlspecialchars($_SESSION['success']); 
                        unset($_SESSION['success']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php 
                        echo htmlspecialchars($_SESSION['error']); 
                        unset($_SESSION['error']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="tasks-container">
                <div class="tasks-header">
                    <h2 class="tasks-title">Tasks List</h2>
                    <button class="btn-add-task" data-bs-toggle="modal" data-bs-target="#addTaskModal">
                        <i class="bi bi-plus"></i> Add Task
                    </button>
                </div>

                <div class="filter-card">
                    <form method="GET" action="tasks_list.php" class="row g-3">
                        <input type="hidden" name="id" value="<?php echo $class_id; ?>">
                        <div class="col-md-6">
                            <input type="text" class="form-control" name="search" placeholder="Search tasks..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-4">
                            <select class="form-select" name="filter">
                                <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Tasks</option>
                                <option value="upcoming" <?php echo $filter === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                                <option value="ongoing" <?php echo $filter === 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                                <option value="completed" <?php echo $filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                        </div>
                    </form>
                </div>

                <?php if (empty($tasks)): ?>
                    <div class="empty-state">
                        <i class="bi bi-calendar-check"></i>
                        <h4>No tasks found</h4>
                        <p><?php echo empty($search) ? 'Create your first task to get started!' : 'Try with different search or filter terms.'; ?></p>
                        <?php if (empty($search)): ?>
                            <button class="btn-add-task" data-bs-toggle="modal" data-bs-target="#addTaskModal">
                                <i class="bi bi-plus"></i> Create Task
                            </button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($tasks as $task): 
                        // Convert start and end times to DateTime objects for comparison
                        $start_date = new DateTime($task['start_datetime']);
                        $end_date = new DateTime($task['end_datetime']);
                        
                        // Determine task status - default to ongoing
                        $status_class = 'ongoing';
                        $badge_class = 'bg-ongoing';
                        $status_text = 'Ongoing';
                        
                        // Compare current time with start and end times for correct status
                        if ($now < $start_date) {
                            $status_class = 'upcoming';
                            $badge_class = 'bg-upcoming';
                            $status_text = 'Upcoming';
                        } elseif ($now > $end_date) {
                            $status_class = 'completed';
                            $badge_class = 'bg-completed';
                            $status_text = 'Completed';
                        }
                    ?>
                        <div class="task-list-item <?php echo $status_class; ?>" 
                             data-start-datetime="<?php echo $task['start_datetime']; ?>" 
                             data-end-datetime="<?php echo $task['end_datetime']; ?>">
                            <div class="row">
                                <div class="col-md-8">
                                    <h5><?php echo htmlspecialchars($task['title']); ?></h5>
                                    <div class="task-dates mb-3">
                                        <div class="mb-1">
                                            <i class="bi bi-calendar-event"></i> 
                                            Start: <?php echo $start_date->format('M j, Y - g:i A'); ?>
                                        </div>
                                        <div>
                                            <i class="bi bi-calendar-check"></i> 
                                            Due: <?php echo $end_date->format('M j, Y - g:i A'); ?>
                                        </div>
                                    </div>
                                    <p class="text-secondary">
                                        <?php echo !empty($task['description']) ? htmlspecialchars($task['description']) : 'No description available.'; ?>
                                    </p>
                                    <?php if (!empty($task['pdf_file'])): ?>
                                    <div class="mt-2">
                                        <i class="bi bi-file-pdf"></i>
                                        <span class="small">Task File</span>
                                        <a href="../<?php echo $task['pdf_file']; ?>" target="_blank" class="btn btn-sm btn-primary ms-2">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                        <a href="../<?php echo $task['pdf_file']; ?>" download class="btn btn-sm btn-outline-primary ms-1">
                                            <i class="bi bi-download"></i> Download
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-4 text-md-end">
                                    <span class="badge <?php echo $badge_class; ?> mb-3">
                                        <?php echo $status_text; ?>
                                    </span>
                                    <div class="task-actions">
                                        <a href="edit_task.php?id=<?php echo $task['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil"></i> Edit
                                        </a>
                                        <a href="delete_task.php?id=<?php echo $task['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this task?')">
                                            <i class="bi bi-trash"></i> Delete
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Task Modal -->
<div class="modal fade" id="addTaskModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header task-modal-header">
                <h5 class="modal-title">Add New Task</h5>
                <button type="button" class="btn-close white-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="add_task.php" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
                    <div class="mb-3">
                        <label for="taskTitle" class="form-label">Task Title *</label>
                        <input type="text" class="form-control" id="taskTitle" name="task_title" required>
                    </div>
                    <div class="mb-3">
                        <label for="taskDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="taskDescription" name="task_description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="taskPdfFile" class="form-label">Attach PDF Document (Optional)</label>
                        <input type="file" class="form-control" id="taskPdfFile" name="task_pdf_file" accept=".pdf">
                        <div class="form-text">Upload a PDF file with task instructions or additional resources.</div>
                    </div>
                    <div class="mb-3">
                        <label for="taskStartDatetime" class="form-label">Start Date and Time *</label>
                        <input type="text" class="form-control" id="taskStartDatetime" name="task_start_datetime" required>
                    </div>
                    <div class="mb-3">
                        <label for="taskEndDatetime" class="form-label">End Date and Time (Deadline) *</label>
                        <input type="text" class="form-control" id="taskEndDatetime" name="task_end_datetime" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Include flatpickr for datetime picking -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Initialize datetime pickers with time always enabled
    if (typeof flatpickr !== 'undefined') {
        // Create datetime pickers with time always enabled
        const startPicker = flatpickr("#taskStartDatetime", {
            enableTime: true,
            dateFormat: "Y-m-d H:i",
            time_24hr: true,
            minuteIncrement: 5,
            defaultHour: 9,
            defaultMinute: 0
        });
        
        const endPicker = flatpickr("#taskEndDatetime", {
            enableTime: true,
            dateFormat: "Y-m-d H:i",
            time_24hr: true,
            minuteIncrement: 5,
            defaultHour: 17,
            defaultMinute: 0
        });

        // Store the pickers in window object for later access
        window.startPicker = startPicker;
        window.endPicker = endPicker;
        
        // When the Add Task button is clicked, pre-fill with current date and rounded time
        document.querySelector('button.btn-add-task').addEventListener('click', function() {
            // Get current date in YYYY-MM-DD format
            const now = new Date();
            const today = now.toISOString().split('T')[0];
            
            // Get current time
            const currentHour = now.getHours();
            const currentMinute = now.getMinutes();
            
            // Round minutes to nearest 5
            const roundedMinutes = Math.round(currentMinute / 5) * 5;
            
            // Set current date and time for start time
            window.startPicker.setDate(today + ` ${currentHour}:${roundedMinutes <= 9 ? '0' + roundedMinutes : roundedMinutes}`);
            
            // Set end time 1 hour after start time
            let endHour = currentHour + 1;
            if (endHour >= 24) endHour = 23;
            window.endPicker.setDate(today + ` ${endHour}:${roundedMinutes <= 9 ? '0' + roundedMinutes : roundedMinutes}`);
        });
    } else {
        console.warn("Flatpickr not loaded");
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Add real-time status update script -->
<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Function to update task status badges based on current time
        function updateTaskStatuses() {
            const now = new Date();
            console.log("Running status update at: " + now.toLocaleString());
            
            // Get all task items
            const taskItems = document.querySelectorAll('.task-list-item');
            
            taskItems.forEach(task => {
                // Skip if this task is marked as completed
                if (task.classList.contains('completed')) {
                    return;
                }
                
                // Get start and end dates from data attributes
                const startDateStr = task.getAttribute('data-start-datetime');
                const endDateStr = task.getAttribute('data-end-datetime');
                
                if (startDateStr && endDateStr) {
                    // Create Date objects for comparison
                    const startDate = new Date(startDateStr);
                    const endDate = new Date(endDateStr);
                    
                    console.log("Task dates - Start: " + startDate.toLocaleString() + ", End: " + endDate.toLocaleString());
                    
                    // Determine correct status based on current time
                    let statusClass = '';
                    let badgeClass = '';
                    let statusText = '';
                    
                    if (now < startDate) {
                        statusClass = 'upcoming';
                        badgeClass = 'bg-upcoming';
                        statusText = 'Upcoming';
                    } else if (now >= startDate && now <= endDate) {
                        statusClass = 'ongoing';
                        badgeClass = 'bg-ongoing';
                        statusText = 'Ongoing';
                    } else {
                        statusClass = 'completed';
                        badgeClass = 'bg-completed';
                        statusText = 'Completed';
                    }
                    
                    console.log("Setting task status to: " + statusText);
                    
                    // Update task item class
                    task.classList.remove('upcoming', 'ongoing', 'completed');
                    task.classList.add(statusClass);
                    
                    // Update badge
                    const badge = task.querySelector('.badge');
                    if (badge) {
                        badge.classList.remove('bg-upcoming', 'bg-ongoing', 'bg-completed');
                        badge.classList.add(badgeClass);
                        badge.textContent = statusText;
                    }
                } else {
                    console.log("Missing date attributes for task");
                }
            });
        }
        
        // Update immediately when page loads
        updateTaskStatuses();
        
        // Update every minute
        setInterval(updateTaskStatuses, 60000);
    });
</script>

<?php include_once '../includes/footer.php'; ?> 