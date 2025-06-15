USE datn;

-- Create users table
CREATE TABLE IF NOT EXISTS users (
    id INT NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'teacher', 'student') NOT NULL,
    profile_image VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

-- Create classes table
CREATE TABLE IF NOT EXISTS classes (
    id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    teacher_id INT NOT NULL,
    color VARCHAR(30) DEFAULT '#4285F4',
    class_code VARCHAR(6) UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Create tasks table
CREATE TABLE IF NOT EXISTS tasks (
    id INT NOT NULL AUTO_INCREMENT,
    class_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NOT NULL,
    pdf_file VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_locked TINYINT(1) NOT NULL DEFAULT 0,
    grades_sent TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Create admin account with ID 0
INSERT INTO users (id, username, email, password, role) VALUES 
(0, 'admin', 'studenttasktracking@gmail.com', '$2y$10$xWSQ79sG0PddBiQkhkMkYOAuOxZtD0f2iD8pLlB.oG90jVRxj2leS', 'admin');
-- Default password for admin is "admin"

-- Create trigger to prevent deletion of admin
DELIMITER //
CREATE TRIGGER prevent_admin_deletion
BEFORE DELETE ON users
FOR EACH ROW
BEGIN
    IF OLD.role = 'admin' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Cannot delete admin account';
    END IF;
END;//
DELIMITER ;

-- Create trigger to prevent updating admin
DELIMITER //
CREATE TRIGGER prevent_admin_update
BEFORE UPDATE ON users
FOR EACH ROW
BEGIN
    IF OLD.role = 'admin' AND 
       (NEW.username != OLD.username OR 
        NEW.email != OLD.email OR 
        NEW.role != OLD.role) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Cannot modify admin account details';
    END IF;
END;//
DELIMITER ;

-- Create class enrollments table
CREATE TABLE IF NOT EXISTS class_enrollments (
    id INT NOT NULL AUTO_INCREMENT,
    class_id INT NOT NULL,
    student_id INT NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_enrollment (class_id, student_id)
) ENGINE=InnoDB;

-- Create submissions table
CREATE TABLE IF NOT EXISTS `submissions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT(11) NOT NULL,
    `task_id` INT(11) NOT NULL,
    `filename` VARCHAR(255) NOT NULL,
    `submission_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ai_probability` FLOAT DEFAULT NULL,
    `grade` FLOAT DEFAULT NULL,
    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create notifications table
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `type` ENUM('task_created', 'task_updated', 'task_deadline', 'grade_sent') NOT NULL,
    `recipient_id` INT(11) NOT NULL,
    `task_id` INT(11) NOT NULL,
    `class_id` INT(11) NOT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `status` ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `sent_at` TIMESTAMP NULL,
    `error_message` TEXT NULL,
    FOREIGN KEY (`recipient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
    INDEX `idx_status` (`status`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_recipient_type` (`recipient_id`, `type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;