<!-- Top navbar -->
<style>
.navbar-brand-container {
    display: flex;
    align-items: center;
}

.class-info {
    display: flex;
    flex-direction: column;
}

.class-title {
    font-size: 1rem;
    font-weight: 500;
    color: #ffffff;
}

.join-code {
    font-size: 0.75rem;
    opacity: 0.8;
    font-family: monospace;
    color: #e0e0e0;
}

.navbar-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #1a73e8;
    color: white;
    font-weight: 500;
    font-size: 14px;
    margin-right: 10px;
    border: 2px solid #ffffff;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}

.navbar-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.back-link {
    display: flex;
    align-items: center;
    color: #ffffff;
    text-decoration: none;
    font-weight: 500;
}

.back-link:hover {
    color: #e0e0e0;
}

.back-link i {
    margin-right: 5px;
}
</style>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <?php if (isset($class_id) && isset($class)): ?>
            <div class="navbar-brand-container">
                <div class="class-info">
                    <span class="class-title"><?php echo htmlspecialchars($class['name']); ?></span>
                    <span class="join-code">Join code: <?php echo htmlspecialchars($class['class_code']); ?></span>
                </div>
            </div>
        <?php else: ?>
            <span class="navbar-brand"><?php echo isset($navbar_title) ? htmlspecialchars($navbar_title) : 'Student Task Tracking'; ?></span>
        <?php endif; ?>
        <div class="navbar-nav ms-auto">
            <?php if(isset($_SESSION['user_id'])): ?>
                <?php if($_SESSION['role'] === 'teacher' && isset($class_id)): ?>
                    <a href="dashboard.php" class="nav-item nav-link back-link">
                        <i class="bi bi-arrow-left"></i> Back to Your Classes
                    </a>
                <?php else: ?>
                    <div class="d-flex align-items-center">
                        <?php 
                        // Get user information
                        $user_id = $_SESSION['user_id'];
                        $stmt = $pdo->prepare("SELECT username, profile_image FROM users WHERE id = ?");
                        $stmt->execute([$user_id]);
                        $user = $stmt->fetch();
                        ?>
                        <a href="<?php echo $_SESSION['role']; ?>/profile.php" class="nav-item nav-link d-flex align-items-center text-decoration-none">
                            <div class="navbar-avatar">
                                <?php if (!empty($user['profile_image'])): ?>
                                    <img src="../uploads/profile_images/<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile">
                                <?php else: ?>
                                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                <?php endif; ?>
                            </div>
                        </a>
                        <a class="nav-item nav-link" href="../logout.php">Logout</a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</nav> 