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

// Get current month and year
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Validate month and year
if ($month < 1 || $month > 12) {
    $month = date('n');
}
if ($year < 2000 || $year > 2100) {
    $year = date('Y');
}

// Get number of days in the month
$num_days = cal_days_in_month(CAL_GREGORIAN, $month, $year);

// Get the first day of the month
$first_day_timestamp = mktime(0, 0, 0, $month, 1, $year);
$first_day_of_week = date('N', $first_day_timestamp);

// Previous and next month navigation
$prev_month = $month - 1;
$prev_year = $year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}

$next_month = $month + 1;
$next_year = $year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}

// Fetch tasks for this month and class
$month_start = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-01 00:00:00';
$month_end = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($num_days, 2, '0', STR_PAD_LEFT) . ' 23:59:59';

$stmt = $pdo->prepare(
    "SELECT * FROM tasks 
    WHERE class_id = ? 
    AND ((start_datetime BETWEEN ? AND ?) 
    OR (end_datetime BETWEEN ? AND ?)
    OR (start_datetime <= ? AND end_datetime >= ?))
    ORDER BY start_datetime"
);
$stmt->execute([$class_id, $month_start, $month_end, $month_start, $month_end, $month_start, $month_end]);
$tasks = $stmt->fetchAll();

// Organize tasks by day
$task_by_day = [];
foreach ($tasks as $task) {
    // For start date
    $start_day = date('j', strtotime($task['start_datetime']));
    $start_month = date('n', strtotime($task['start_datetime']));
    $start_year = date('Y', strtotime($task['start_datetime']));
    
    // For end date
    $end_day = date('j', strtotime($task['end_datetime']));
    $end_month = date('n', strtotime($task['end_datetime']));
    $end_year = date('Y', strtotime($task['end_datetime']));
    
    // Add task to start day
    if ($start_month == $month && $start_year == $year) {
        if (!isset($task_by_day[$start_day])) {
            $task_by_day[$start_day] = [];
        }
        $task_by_day[$start_day][] = [
            'id' => $task['id'],
            'title' => $task['title'],
            'type' => 'start',
            'datetime' => $task['start_datetime']
        ];
    }
    
    // Add task to end day
    if ($end_month == $month && $end_year == $year) {
        if (!isset($task_by_day[$end_day])) {
            $task_by_day[$end_day] = [];
        }
        $task_by_day[$end_day][] = [
            'id' => $task['id'],
            'title' => $task['title'],
            'type' => 'end',
            'datetime' => $task['end_datetime']
        ];
    }
}

// Set page variables
$page_title = htmlspecialchars($class['name']) . ' - Calendar';
$navbar_title = 'Class: ' . htmlspecialchars($class['name']);
$active_page = 'calendar';

// Include header
include_once '../includes/header.php';
?>

<style>
.main-content {
    margin-left: 180px;
    padding: 20px;
    padding-top: 68px;
}

.calendar-container {
    box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
    border-radius: 8px;
    background-color: #fff;
    margin-bottom: 30px;
    overflow: hidden;
}

.calendar-header {
    padding: 12px 15px;
    background-color: #fff;
    border-bottom: 1px solid #e0e0e0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.calendar-title {
    font-size: 22px;
    font-weight: 400;
    color: #3c4043;
    margin: 0;
}

.calendar-controls {
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-calendar {
    background-color: #f1f3f4;
    color: #3c4043;
    border: none;
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 14px;
    cursor: pointer;
    transition: background-color 0.2s;
}

.btn-calendar:hover {
    background-color: #e8eaed;
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

.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
}

.calendar-day-header {
    text-align: center;
    padding: 10px;
    font-size: 12px;
    font-weight: 500;
    color: #70757a;
    background-color: #f1f3f4;
    border-bottom: 1px solid #e0e0e0;
}

.calendar-day {
    min-height: 100px;
    border: 1px solid #e0e0e0;
    padding: 8px;
    position: relative;
    overflow: hidden;
}

.calendar-day:hover {
    background-color: #f8f9fa;
}

.calendar-day.empty {
    background-color: #f8f9fa;
}

.calendar-day.today {
    background-color: #e8f0fe;
}

.day-number {
    font-size: 14px;
    text-align: left;
    color: #70757a;
    font-weight: 500;
    position: absolute;
    top: 8px;
    left: 8px;
}

.today .day-number {
    color: #1a73e8;
    font-weight: 700;
    position: relative;
}

.today .day-number:after {
    content: "";
    position: absolute;
    width: 24px;
    height: 24px;
    background-color: #1a73e8;
    border-radius: 50%;
    left: -5px;
    top: -5px;
    z-index: -1;
    opacity: 0.2;
}

.task-container {
    max-height: 90px;
    overflow-y: auto;
    scrollbar-width: thin;
    margin-top: 30px;
}

.task-container::-webkit-scrollbar {
    width: 4px;
}

.task-container::-webkit-scrollbar-track {
    background: #f1f1f1;
}

.task-container::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
}

.task-item {
    margin-bottom: 4px;
    padding: 3px 8px;
    font-size: 12px;
    border-radius: 4px;
    cursor: pointer;
    color: white;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    transition: transform 0.1s;
}

.task-item:hover {
    transform: translateY(-1px);
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}

.task-start {
    background-color: #4285f4;
    border-left: 3px solid #1967d2;
}

.task-end {
    background-color: #ea4335;
    border-left: 3px solid #c5221f;
}

.add-task-btn {
    position: absolute;
    top: 8px;
    right: 8px;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background-color: #1a73e8;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    cursor: pointer;
    opacity: 0;
    transition: opacity 0.2s;
    box-shadow: 0 1px 3px rgba(0,0,0,0.3);
}

.calendar-day:hover .add-task-btn {
    opacity: 1;
}

.add-task-btn:hover {
    background-color: #1967d2;
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

/* Weekend styling */
.calendar-day-header:nth-child(6),
.calendar-day-header:nth-child(7) {
    color: #ea4335;
}

/* Responsive styles */
@media (max-width: 768px) {
    .calendar-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .calendar-controls {
        margin-top: 10px;
        flex-wrap: wrap;
    }
    
    .calendar-grid {
        grid-template-columns: 1fr;
    }
    
    .calendar-day-header {
        display: none;
    }
    
    .calendar-day {
        border-bottom: 2px solid #e0e0e0;
        margin-bottom: 8px;
    }
    
    .day-number::before {
        content: attr(data-day-name);
        margin-right: 8px;
        font-weight: 400;
    }
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

            <div class="calendar-container">
                <div class="calendar-header">
                    <h2 class="calendar-title"><?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?></h2>
                    <div class="calendar-controls">
                        <a href="class_details.php?id=<?php echo $class_id; ?>&month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>" class="btn btn-calendar">
                            <i class="bi bi-chevron-left"></i> Previous
                        </a>
                        <a href="class_details.php?id=<?php echo $class_id; ?>" class="btn btn-calendar">Today</a>
                        <a href="class_details.php?id=<?php echo $class_id; ?>&month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>" class="btn btn-calendar">
                            Next <i class="bi bi-chevron-right"></i>
                        </a>
                        <button class="btn-add-task" data-bs-toggle="modal" data-bs-target="#addTaskModal">
                            <i class="bi bi-plus"></i> Add Task
                        </button>
                    </div>
                </div>
                
                <div class="calendar-grid">
                    <!-- Days of week header -->
                    <div class="calendar-day-header">Mon</div>
                    <div class="calendar-day-header">Tue</div>
                    <div class="calendar-day-header">Wed</div>
                    <div class="calendar-day-header">Thu</div>
                    <div class="calendar-day-header">Fri</div>
                    <div class="calendar-day-header">Sat</div>
                    <div class="calendar-day-header">Sun</div>
                    
                    <!-- Empty cells before the first day of the month -->
                    <?php for ($i = 1; $i < $first_day_of_week; $i++): ?>
                        <div class="calendar-day empty"></div>
                    <?php endfor; ?>
                    
                    <!-- Days of the month -->
                    <?php 
                    $current_day = date('j');
                    $current_month = date('n');
                    $current_year = date('Y');
                    
                    for ($day = 1; $day <= $num_days; $day++): 
                        $is_today = ($day == $current_day && $month == $current_month && $year == $current_year);
                        $day_class = $is_today ? 'calendar-day today' : 'calendar-day';
                        $date_ymd = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
                        
                        // Get day name for responsive view
                        $day_name = date('D', mktime(0, 0, 0, $month, $day, $year));
                    ?>
                        <div class="<?php echo $day_class; ?>" onclick="openAddTaskModal('<?php echo $date_ymd; ?>')">
                            <div class="day-number" data-day-name="<?php echo $day_name; ?>"><?php echo $day; ?></div>
                            
                            <!-- Tasks for this day -->
                            <div class="task-container">
                                <?php if (isset($task_by_day[$day])): ?>
                                    <?php foreach ($task_by_day[$day] as $task_item): ?>
                                        <div class="task-item task-<?php echo $task_item['type']; ?>" 
                                             onclick="event.stopPropagation(); openTaskDetails(<?php echo $task_item['id']; ?>)">
                                            <?php echo htmlspecialchars($task_item['title']); ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Add task button -->
                            <div class="add-task-btn" onclick="event.stopPropagation(); openAddTaskModal('<?php echo $date_ymd; ?>')">
                                <i class="bi bi-plus"></i>
                            </div>
                        </div>
                    <?php endfor; ?>
                    
                    <!-- Empty cells after the last day of the month -->
                    <?php 
                    $last_day_of_week = date('N', mktime(0, 0, 0, $month, $num_days, $year));
                    for ($i = $last_day_of_week + 1; $i <= 7; $i++): 
                    ?>
                        <div class="calendar-day empty"></div>
                    <?php endfor; ?>
                </div>
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
                        <label for="taskPdfFile" class="form-label">Upload PDF Document (Optional)</label>
                        <input type="file" class="form-control" id="taskPdfFile" name="task_pdf_file" accept=".pdf">
                        <div class="form-text">Upload a PDF file with task details or instructions.</div>
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

<!-- View Task Modal -->
<div class="modal fade" id="viewTaskModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header task-modal-header">
                <h5 class="modal-title">Task Details</h5>
                <button type="button" class="btn-close white-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="taskDetailsContent">
                    <div class="d-flex justify-content-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="#" id="editTaskBtn" class="btn btn-primary">Edit</a>
                <a href="#" id="deleteTaskBtn" class="btn btn-danger">Delete</a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Initialize flatpickr with time enabled by default
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
    } else {
        console.warn("Flatpickr not loaded");
    }
});

// Function to open the add task modal with the selected date
function openAddTaskModal(date) {
    const modal = new bootstrap.Modal(document.getElementById('addTaskModal'));
    modal.show();
    
    // Set the date in the form fields
    if (date) {
        setTimeout(() => {
            // Get current time
            const now = new Date();
            const currentHour = now.getHours();
            const currentMinute = now.getMinutes();
            
            // Round minutes to nearest 5
            const roundedMinutes = Math.round(currentMinute / 5) * 5;
            
            if (typeof flatpickr !== 'undefined' && window.startPicker && window.endPicker) {
                // Check if the date is today
                const today = new Date().toISOString().split('T')[0];
                
                if (date === today) {
                    // For today, use current time (rounded to nearest 5 minutes)
                    window.startPicker.setDate(date + ` ${currentHour}:${roundedMinutes <= 9 ? '0' + roundedMinutes : roundedMinutes}`);
                    
                    // Set end time 1 hour after start time
                    let endHour = currentHour + 1;
                    if (endHour >= 24) endHour = 23;
                    window.endPicker.setDate(date + ` ${endHour}:${roundedMinutes <= 9 ? '0' + roundedMinutes : roundedMinutes}`);
                } else {
                    // For future dates, use default business hours (9 AM - 5 PM)
                    window.startPicker.setDate(date + " 09:00");
                    window.endPicker.setDate(date + " 17:00");
                }
            } else {
                // Fallback for when flatpickr is not available
                const startDateInput = document.getElementById('taskStartDatetime');
                const endDateInput = document.getElementById('taskEndDatetime');
                
                // Check if the date is today
                const today = new Date().toISOString().split('T')[0];
                
                if (date === today) {
                    // For today, use current time
                    startDateInput.value = date + ` ${currentHour}:${roundedMinutes <= 9 ? '0' + roundedMinutes : roundedMinutes}`;
                    
                    // Set end time 1 hour after start time
                    let endHour = currentHour + 1;
                    if (endHour >= 24) endHour = 23;
                    endDateInput.value = date + ` ${endHour}:${roundedMinutes <= 9 ? '0' + roundedMinutes : roundedMinutes}`;
                } else {
                    // For future dates, use default business hours
                    startDateInput.value = date + " 09:00";
                    endDateInput.value = date + " 17:00";
                }
            }
        }, 300);
    } else {
        // When clicking the general "Add Task" button (not a specific date)
        // Clear any previous values and let the user select all date and time values
        if (typeof flatpickr !== 'undefined' && window.startPicker && window.endPicker) {
            window.startPicker.clear();
            window.endPicker.clear();
        } else {
            const startDateInput = document.getElementById('taskStartDatetime');
            const endDateInput = document.getElementById('taskEndDatetime');
            startDateInput.value = "";
            endDateInput.value = "";
        }
    }
}

// Function to open task details
function openTaskDetails(taskId) {
    const modal = new bootstrap.Modal(document.getElementById('viewTaskModal'));
    modal.show();
    
    const taskDetailsContent = document.getElementById('taskDetailsContent');
    const editTaskBtn = document.getElementById('editTaskBtn');
    const deleteTaskBtn = document.getElementById('deleteTaskBtn');
    
    taskDetailsContent.innerHTML = `
        <div class="d-flex justify-content-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    `;
    
    // Fetch task details
    fetch(`get_task_details.php?id=${taskId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const task = data.task;
                
                // Format datetime for display
                const startDate = new Date(task.start_datetime);
                const endDate = new Date(task.end_datetime);
                const formattedStart = startDate.toLocaleString();
                const formattedEnd = endDate.toLocaleString();
                
                // Display task details
                taskDetailsContent.innerHTML = `
                    <h4 class="mb-3">${task.title}</h4>
                    <div class="row mb-4">
                        <div class="${task.pdf_file ? 'col-md-6' : 'col-12'}">
                            <h6 class="text-muted mb-2">Description:</h6>
                            <p>${task.description || "No description provided"}</p>
                        </div>
                        ${task.pdf_file ? `
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2">Document:</h6>
                            <div class="d-flex align-items-center">
                                <i class="bi bi-file-pdf text-danger fs-4 me-2"></i>
                                <div>
                                    <p class="mb-1">Task File</p>
                                    <a href="../${task.pdf_file}" target="_blank" class="btn btn-sm btn-primary">
                                        <i class="bi bi-eye me-1"></i> View
                                    </a>
                                    <a href="../${task.pdf_file}" download class="btn btn-sm btn-outline-primary ms-1">
                                        <i class="bi bi-download me-1"></i> Download
                                    </a>
                                </div>
                            </div>
                        </div>` : ''}
                    </div>
                    <div class="mb-3">
                        <div class="d-flex align-items-center mb-2">
                            <i class="bi bi-calendar-plus me-2" style="color: #4285f4;"></i>
                            <div>
                                <div class="text-muted small">Starts</div>
                                <strong>${formattedStart}</strong>
                            </div>
                        </div>
                        <div class="d-flex align-items-center">
                            <i class="bi bi-calendar-check me-2" style="color: #ea4335;"></i>
                            <div>
                                <div class="text-muted small">Due</div>
                                <strong>${formattedEnd}</strong>
                            </div>
                        </div>
                    </div>
                `;
                
                // Set up edit and delete links
                editTaskBtn.href = `edit_task.php?id=${task.id}`;
                deleteTaskBtn.href = `delete_task.php?id=${task.id}`;
                
                // Confirm before delete
                deleteTaskBtn.onclick = function(e) {
                    if (!confirm("Are you sure you want to delete this task?")) {
                        e.preventDefault();
                    }
                };
            } else {
                taskDetailsContent.innerHTML = `<div class="alert alert-danger">Error loading task details.</div>`;
            }
        })
        .catch(error => {
            console.error("Error:", error);
            taskDetailsContent.innerHTML = `<div class="alert alert-danger">Error loading task details. Please try again.</div>`;
        });
}
</script>

<!-- Include flatpickr for datetime picking -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<?php include_once '../includes/footer.php'; ?> 