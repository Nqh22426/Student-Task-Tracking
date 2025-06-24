<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['user_id'];

// Get filter parameters
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : null;

// Build query to get tasks
$sql = "
    SELECT t.*, c.name as class_name, c.color as class_color,
           sp.status, sp.updated_at as progress_update
    FROM tasks t
    JOIN classes c ON t.class_id = c.id
    JOIN class_enrollments ce ON c.id = ce.class_id AND ce.student_id = ?
    LEFT JOIN student_progress sp ON t.id = sp.task_id AND sp.student_id = ?
";

$params = [$student_id, $student_id];

// Add class filter if provided
if ($class_id) {
    $sql .= " WHERE t.class_id = ?";
    $params[] = $class_id;
}

// Add search condition
if (!empty($search)) {
    $sql .= ($class_id ? " AND" : " WHERE");
    $sql .= " (t.title LIKE ? OR t.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Add status filter
if ($filter !== 'all') {
    $filterCondition = ($class_id || !empty($search)) ? " AND" : " WHERE";
    
    switch ($filter) {
        case 'not_started':
            $sql .= "$filterCondition (sp.status IS NULL OR sp.status = 'not_started')";
            break;
        case 'in_progress':
            $sql .= "$filterCondition sp.status = 'in_progress'";
            break;
        case 'completed':
            $sql .= "$filterCondition sp.status = 'completed'";
            break;
        case 'upcoming':
            $sql .= "$filterCondition t.start_datetime > NOW()";
            break;
        case 'ongoing':
            $sql .= "$filterCondition t.start_datetime <= NOW() AND t.end_datetime >= NOW()";
            break;
        case 'past':
            $sql .= "$filterCondition t.end_datetime < NOW()";
            break;
    }
}

// Add order by
$sql .= " ORDER BY t.end_datetime ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get enrolled classes for filter dropdown
$stmt = $pdo->prepare("
    SELECT c.id, c.name, c.color
    FROM classes c
    JOIN class_enrollments ce ON c.id = ce.class_id
    WHERE ce.student_id = ?
    ORDER BY c.name ASC
");
$stmt->execute([$student_id]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch student information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// If class_id is provided, get class info
$current_class = null;
if ($class_id) {
    $stmt = $pdo->prepare("
        SELECT c.*, u.username as teacher_name 
        FROM classes c
        JOIN users u ON c.teacher_id = u.id
        WHERE c.id = ?
    ");
    $stmt->execute([$class_id]);
    $current_class = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Page variables
$page_title = $current_class ? "Nhiệm vụ: " . $current_class['name'] : "Danh sách nhiệm vụ";
$active_page = "tasks";
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Task Tracking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        /* Sidebar styles */
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 48px 0 0;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            background-color: #343a40;
            width: 250px;
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
            padding: 0.8rem 1rem;
            transition: all 0.3s ease;
        }
        .sidebar .nav-link.active {
            color: #ffffff;
            background-color: #495057;
            border-left: 4px solid #007bff;
        }
        .sidebar .nav-link:hover {
            color: #007bff;
            background-color: #495057;
        }
        .sidebar .nav-link i {
            margin-right: 8px;
            width: 20px;
            text-align: center;
        }
        .navbar {
            position: fixed;
            top: 0;
            right: 0;
            left: 250px;
            z-index: 99;
            height: 48px;
            padding: 0 20px;
        }
        .navbar .container-fluid {
            padding: 0 20px;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .content-wrapper {
            margin-top: 48px;
            padding: 0 20px;
        }
        
        /* Task list styling */
        .task-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .filter-card {
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .task-item {
            margin-bottom: 15px;
            border-radius: 8px;
            transition: transform 0.2s, box-shadow 0.2s;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .task-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .task-header {
            padding: 12px 15px;
            color: white;
        }
        .task-body {
            padding: 15px;
            background-color: white;
        }
        .task-title {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 500;
        }
        .task-class {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
            margin-top: 8px;
        }
        .task-dates {
            margin-top: 10px;
            font-size: 0.9rem;
            color: #6c757d;
        }
        .task-dates i {
            width: 16px;
            text-align: center;
            margin-right: 5px;
        }
        .task-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-top: 10px;
        }
        .status-not-started {
            background-color: #f8f9fa;
            color: #6c757d;
        }
        .status-in-progress {
            background-color: #e8f5e9;
            color: #28a745;
        }
        .status-completed {
            background-color: #e3f2fd;
            color: #007bff;
        }
        .status-upcoming {
            background-color: #f0f4fa;
            color: #6c757d;
        }
        .status-ongoing {
            background-color: #fff3cd;
            color: #ffc107;
        }
        .status-past {
            background-color: #f8d7da;
            color: #dc3545;
        }
        .task-description {
            margin-top: 10px;
            font-size: 0.9rem;
            color: #6c757d;
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
        }
        .empty-state i {
            font-size: 48px;
            color: #dee2e6;
            margin-bottom: 15px;
        }
        .alert-container {
            position: fixed;
            top: 60px;
            right: 20px;
            z-index: 1000;
            max-width: 350px;
        }
        
        .task-link {
            text-decoration: none;
            color: inherit;
            display: block;
            transition: transform 0.2s;
        }

        .task-link:hover {
            transform: translateY(-5px);
        }

        .task-link .card {
            height: 100%;
            transition: box-shadow 0.3s;
        }

        .task-link:hover .card {
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }
        
        /* PDF display */
        .task-pdf-indicator {
            margin-top: 10px;
            display: flex;
            align-items: center;
            font-size: 0.9rem;
        }
        
        .task-pdf-indicator i {
            color: #dc3545;
            margin-right: 6px;
            font-size: 1.1rem;
        }
        
        .task-pdf-indicator span {
            margin-right: 10px;
            font-weight: 500;
        }
        
        .task-pdf-indicator .btn {
            padding: 2px 8px;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <?php if(isset($_SESSION['success']) || isset($_SESSION['error'])): ?>
    <div class="alert-container">
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $_SESSION['success']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-sticky">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="bi bi-grid"></i>
                        Your Classes
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="tasks.php">
                        <i class="bi bi-list-check"></i>
                        Task List
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="profile.php">
                        <i class="bi bi-person"></i>
                        Profile
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Top navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <span class="navbar-brand">Student Dashboard</span>
            <div class="navbar-nav ms-auto">
                <?php if (!empty($student['profile_image'])): ?>
                    <a href="profile.php" class="nav-item nav-link p-0 me-3 d-flex align-items-center" style="height: 48px;">
                        <img src="../uploads/profile_images/<?php echo htmlspecialchars($student['profile_image']); ?>" class="rounded-circle" alt="Profile" width="32" height="32" style="border: 2px solid #ffffff;">
                    </a>
                <?php else: ?>
                    <a href="profile.php" class="nav-item nav-link p-0 me-3 d-flex align-items-center" style="height: 48px;">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 16px; border: 2px solid #ffffff;">
                            <?php echo strtoupper(substr($student['username'], 0, 1)); ?>
                        </div>
                    </a>
                <?php endif; ?>
                <a class="nav-item nav-link" href="../logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main content -->
    <div class="main-content">
        <div class="content-wrapper">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><?php echo $current_class ? htmlspecialchars($current_class['name']) : 'Danh sách nhiệm vụ'; ?></h2>
                    <?php if ($current_class): ?>
                        <a href="tasks.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Back to all tasks
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Filters -->
                <div class="card filter-card">
                    <div class="card-body">
                        <form method="GET" action="tasks.php" class="row g-3">
                            <?php if ($class_id): ?>
                                <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
                            <?php else: ?>
                                <div class="col-md-3">
                                    <label for="class_id" class="form-label">Class</label>
                                    <select class="form-select" id="class_id" name="class_id">
                                        <option value="">All classes</option>
                                        <?php foreach ($classes as $class): ?>
                                            <option value="<?php echo $class['id']; ?>" <?php echo isset($_GET['class_id']) && $_GET['class_id'] == $class['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($class['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                            
                            <div class="col-md-3">
                                <label for="filter" class="form-label">Status</label>
                                <select class="form-select" id="filter" name="filter">
                                    <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All status</option>
                                    <option value="not_started" <?php echo $filter === 'not_started' ? 'selected' : ''; ?>>Not started</option>
                                    <option value="in_progress" <?php echo $filter === 'in_progress' ? 'selected' : ''; ?>>Đang thực hiện</option>
                                    <option value="completed" <?php echo $filter === 'completed' ? 'selected' : ''; ?>>Đã hoàn thành</option>
                                    <option value="upcoming" <?php echo $filter === 'upcoming' ? 'selected' : ''; ?>>Sắp tới</option>
                                    <option value="ongoing" <?php echo $filter === 'ongoing' ? 'selected' : ''; ?>>Đang diễn ra</option>
                                    <option value="past" <?php echo $filter === 'past' ? 'selected' : ''; ?>>Đã qua</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="search" class="form-label">Search</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="search" name="search" placeholder="Enter keyword..." value="<?php echo htmlspecialchars($search); ?>">
                                    <button class="btn btn-outline-secondary" type="button" onclick="clearSearch()">
                                        <i class="bi bi-x"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-filter"></i> Filter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Task List -->
                <?php if (empty($tasks)): ?>
                    <div class="empty-state">
                        <i class="bi bi-clipboard-x"></i>
                        <h4>No task found</h4>
                        <p class="text-muted">
                            <?php if (!empty($search) || $filter !== 'all' || $class_id): ?>
                                No task found.
                            <?php else: ?>
                                You don't have any task. Join a class to get tasks.
                            <?php endif; ?>
                        </p>
                        <?php if (!empty($search) || $filter !== 'all' || $class_id): ?>
                            <a href="tasks.php" class="btn btn-outline-primary">Xem tất cả nhiệm vụ</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div>
                        <?php foreach ($tasks as $task): 
                            $status_class = '';
                            $status_text = '';
                            
                            if (isset($task['status'])) {
                                switch ($task['status']) {
                                    case 'not_started':
                                        $status_class = 'status-not-started';
                                        $status_text = 'Chưa bắt đầu';
                                        break;
                                    case 'in_progress':
                                        $status_class = 'status-in-progress';
                                        $status_text = 'Đang thực hiện';
                                        break;
                                    case 'completed':
                                        $status_class = 'status-completed';
                                        $status_text = 'Đã hoàn thành';
                                        break;
                                    default:
                                        $status_class = 'status-not-started';
                                        $status_text = 'Chưa bắt đầu';
                                }
                            } else {
                                $status_class = 'status-not-started';
                                $status_text = 'Chưa bắt đầu';
                            }
                            
                            // Determine time status
                            $now = new DateTime();
                            $start_date = new DateTime($task['start_datetime']);
                            $end_date = new DateTime($task['end_datetime']);
                            
                            $time_status_class = '';
                            $time_status_text = '';
                            
                            if ($now < $start_date) {
                                $time_status_class = 'status-upcoming';
                                $time_status_text = 'Sắp tới';
                            } elseif ($now <= $end_date) {
                                $time_status_class = 'status-ongoing';
                                $time_status_text = 'Đang diễn ra';
                            } else {
                                $time_status_class = 'status-past';
                                $time_status_text = 'Đã qua';
                            }
                        ?>
                            <a href="task_details.php?id=<?php echo $task['id']; ?>" class="text-decoration-none text-dark">
                                <div class="task-item">
                                    <div class="task-header" style="background-color: <?php echo htmlspecialchars($task['class_color']); ?>">
                                        <h5 class="task-title"><?php echo htmlspecialchars($task['title']); ?></h5>
                                    </div>
                                    <div class="task-body">
                                        <span class="task-class" style="background-color: <?php echo htmlspecialchars($task['class_color']); ?>; color: white;">
                                            <?php echo htmlspecialchars($task['class_name']); ?>
                                        </span>
                                        
                                        <span class="task-status float-end <?php echo $status_class; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
                                        
                                        <div class="task-dates">
                                            <div><i class="bi bi-calendar-event"></i> Bắt đầu: <?php echo date('d/m/Y H:i', strtotime($task['start_datetime'])); ?></div>
                                            <div><i class="bi bi-calendar-check"></i> Kết thúc: <?php echo date('d/m/Y H:i', strtotime($task['end_datetime'])); ?></div>
                                        </div>
                                        
                                        <span class="task-status <?php echo $time_status_class; ?>">
                                            <?php echo $time_status_text; ?>
                                        </span>
                                        
                                        <?php if (!empty($task['description'])): ?>
                                            <div class="task-description">
                                                <?php
                                                    $desc = htmlspecialchars($task['description']);
                                                    echo strlen($desc) > 100 ? substr($desc, 0, 100) . '...' : $desc;
                                                ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($task['pdf_file'])): ?>
                                            <div class="task-pdf-indicator">
                                                <i class="bi bi-file-pdf"></i>
                                                <span>Task File</span>
                                                <a href="../<?php echo $task['pdf_file']; ?>" target="_blank" class="btn btn-sm btn-primary ms-2">
                                                    <i class="bi bi-eye"></i> View
                                                </a>
                                                <a href="../<?php echo $task['pdf_file']; ?>" download class="btn btn-sm btn-outline-primary ms-1">
                                                    <i class="bi bi-download"></i> Download
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function clearSearch() {
            document.getElementById('search').value = '';
        }
    </script>
</body>
</html> 