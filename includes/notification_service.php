<?php
/**
 * Handles creating and sending email notifications
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/email_config.php';

// Include PHPMailer
require_once __DIR__ . '/PHPMailer-6.8.1/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer-6.8.1/src/SMTP.php';
require_once __DIR__ . '/PHPMailer-6.8.1/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class NotificationService
{
    private $pdo;
    
    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }
    
    /**
     * Create notification when task is created
     */
    public function createTaskCreatedNotifications($task_id, $class_id, $teacher_id)
    {
        try {
            // Get task details
            $stmt = $this->pdo->prepare("SELECT * FROM tasks WHERE id = ?");
            $stmt->execute([$task_id]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$task) {
                return false;
            }
            
            // Get class details
            $stmt = $this->pdo->prepare("SELECT * FROM classes WHERE id = ?");
            $stmt->execute([$class_id]);
            $class = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get teacher details
            $stmt = $this->pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$teacher_id]);
            $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get all students in this class
            $stmt = $this->pdo->prepare("
                SELECT u.id, u.email, u.username 
                FROM users u 
                JOIN class_enrollments ce ON u.id = ce.student_id 
                WHERE ce.class_id = ? AND u.role = 'student'
            ");
            $stmt->execute([$class_id]);
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Create notifications for each student
            foreach ($students as $student) {
                $subject = "New Task: " . $task['title'] . " - " . $class['name'];
                $message = $this->generateTaskCreatedMessage($task, $class, $teacher, $student);
                
                $this->createNotification(
                    'task_created',
                    $student['id'],
                    $task_id,
                    $class_id,
                    $subject,
                    $message
                );
            }
            
            // Send notifications immediately
            $this->sendPendingNotifications();
            
            return true;
        } catch (Exception $e) {
            error_log("Error creating task notifications: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create notification when task is updated
     */
    public function createTaskUpdatedNotifications($task_id, $class_id, $teacher_id)
    {
        try {
            // Get task details
            $stmt = $this->pdo->prepare("SELECT * FROM tasks WHERE id = ?");
            $stmt->execute([$task_id]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$task) {
                return false;
            }
            
            // Get class details
            $stmt = $this->pdo->prepare("SELECT * FROM classes WHERE id = ?");
            $stmt->execute([$class_id]);
            $class = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get teacher details
            $stmt = $this->pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$teacher_id]);
            $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get all students in this class
            $stmt = $this->pdo->prepare("
                SELECT u.id, u.email, u.username 
                FROM users u 
                JOIN class_enrollments ce ON u.id = ce.student_id 
                WHERE ce.class_id = ? AND u.role = 'student'
            ");
            $stmt->execute([$class_id]);
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Create notifications for each student
            foreach ($students as $student) {
                $subject = "Task Updated: " . $task['title'] . " - " . $class['name'];
                $message = $this->generateTaskUpdatedMessage($task, $class, $teacher, $student);
                
                $this->createNotification(
                    'task_updated',
                    $student['id'],
                    $task_id,
                    $class_id,
                    $subject,
                    $message
                );
            }
            
            // Send notifications immediately
            $this->sendPendingNotifications();
            
            return true;
        } catch (Exception $e) {
            error_log("Error creating task update notifications: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create notification when grade is sent
     */
    public function createGradeSentNotification($student_id, $task_id, $class_id, $grade)
    {
        try {
            // Get task details
            $stmt = $this->pdo->prepare("SELECT * FROM tasks WHERE id = ?");
            $stmt->execute([$task_id]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get class details
            $stmt = $this->pdo->prepare("SELECT * FROM classes WHERE id = ?");
            $stmt->execute([$class_id]);
            $class = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get student details
            $stmt = $this->pdo->prepare("SELECT username, email FROM users WHERE id = ?");
            $stmt->execute([$student_id]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get teacher details
            $stmt = $this->pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$class['teacher_id']]);
            $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $subject = "Grade Received: " . $task['title'] . " - " . $class['name'];
            $message = $this->generateGradeSentMessage($task, $class, $teacher, $student, $grade);
            
            $this->createNotification(
                'grade_sent',
                $student_id,
                $task_id,
                $class_id,
                $subject,
                $message
            );
            
            // Send notification immediately
            $this->sendPendingNotifications();
            
            return true;
        } catch (Exception $e) {
            error_log("Error creating grade notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check for deadline reminders (tasks ending in 1 day)
     */
    public function createDeadlineReminders()
    {
        try {
            // Get tasks ending in 24 hours
            $stmt = $this->pdo->prepare("
                SELECT t.*, c.name as class_name, c.teacher_id
                FROM tasks t
                JOIN classes c ON t.class_id = c.id
                WHERE t.end_datetime BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
                AND t.end_datetime > NOW()
            ");
            $stmt->execute();
            $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($tasks as $task) {
                // Get students who haven't submitted or don't have grades
                $stmt = $this->pdo->prepare("
                    SELECT u.id, u.email, u.username
                    FROM users u
                    JOIN class_enrollments ce ON u.id = ce.student_id
                    LEFT JOIN submissions s ON s.student_id = u.id AND s.task_id = ?
                    WHERE ce.class_id = ? AND u.role = 'student'
                    AND (s.id IS NULL OR s.grade IS NULL)
                ");
                $stmt->execute([$task['id'], $task['class_id']]);
                $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Get teacher details
                $stmt = $this->pdo->prepare("SELECT username FROM users WHERE id = ?");
                $stmt->execute([$task['teacher_id']]);
                $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Create deadline reminder for each student
                foreach ($students as $student) {
                    // Check if we already sent a deadline reminder for this task
                    $stmt = $this->pdo->prepare("
                        SELECT id FROM notifications 
                        WHERE type = 'task_deadline' 
                        AND recipient_id = ? 
                        AND task_id = ? 
                        AND created_at >= DATE_SUB(NOW(), INTERVAL 2 DAY)
                    ");
                    $stmt->execute([$student['id'], $task['id']]);
                    
                    if ($stmt->rowCount() == 0) {
                        $subject = "Deadline Reminder: " . $task['title'] . " - " . $task['class_name'];
                        $message = $this->generateDeadlineReminderMessage($task, $teacher, $student);
                        
                        $this->createNotification(
                            'task_deadline',
                            $student['id'],
                            $task['id'],
                            $task['class_id'],
                            $subject,
                            $message
                        );
                    }
                }
            }
            
            // Send all deadline reminders
            $this->sendPendingNotifications();
            
            return true;
        } catch (Exception $e) {
            error_log("Error creating deadline reminders: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create a notification record in database
     */
    private function createNotification($type, $recipient_id, $task_id, $class_id, $subject, $message)
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO notifications (type, recipient_id, task_id, class_id, subject, message, status)
            VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ");
        return $stmt->execute([$type, $recipient_id, $task_id, $class_id, $subject, $message]);
    }
    
    /**
     * Send all pending notifications
     */
    public function sendPendingNotifications()
    {
        try {
            // Get pending notifications
            $stmt = $this->pdo->prepare("
                SELECT n.*, u.email, u.username
                FROM notifications n
                JOIN users u ON n.recipient_id = u.id
                WHERE n.status = 'pending'
                ORDER BY n.created_at ASC
                LIMIT 50
            ");
            $stmt->execute();
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($notifications as $notification) {
                $success = $this->sendEmailWithPHPMailer(
                    $notification['email'],
                    $notification['username'],
                    $notification['subject'],
                    $notification['message']
                );
                
                if ($success) {
                    // Update status to sent
                    $stmt = $this->pdo->prepare("
                        UPDATE notifications 
                        SET status = 'sent', sent_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$notification['id']]);
                } else {
                    // Update status to failed
                    $stmt = $this->pdo->prepare("
                        UPDATE notifications 
                        SET status = 'failed', error_message = 'Failed to send email via PHPMailer' 
                        WHERE id = ?
                    ");
                    $stmt->execute([$notification['id']]);
                }
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Error sending notifications: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send email using PHPMailer with Gmail SMTP
     */
    private function sendEmailWithPHPMailer($to_email, $to_name, $subject, $message)
    {
        try {
            $mail = new PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USERNAME;
            $mail->Password   = SMTP_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT;
            
            // Recipients
            $mail->setFrom(FROM_EMAIL, FROM_NAME);
            $mail->addAddress($to_email, $to_name);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $message;
            $mail->CharSet = 'UTF-8';
            
            $mail->send();
            return true;
            
        } catch (Exception $e) {
            error_log("PHPMailer Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate task created email message
     */
    private function generateTaskCreatedMessage($task, $class, $teacher, $student)
    {
        $start_date = date('d/m/Y H:i', strtotime($task['start_datetime']));
        $end_date = date('d/m/Y H:i', strtotime($task['end_datetime']));
        
        return "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #1a73e8; border-bottom: 2px solid #1a73e8; padding-bottom: 10px;'>
                    New Task Created
                </h2>
                
                <p>Dear <strong>{$student['username']}</strong>,</p>
                
                <p>A new task has been created in your class <strong>{$class['name']}</strong> by teacher <strong>{$teacher['username']}</strong>.</p>
                
                <div style='background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                    <h3 style='margin-top: 0; color: #1a73e8;'>{$task['title']}</h3>
                    <p><strong>Description:</strong> {$task['description']}</p>
                    <p><strong>Start Date:</strong> {$start_date}</p>
                    <p><strong>Due Date:</strong> {$end_date}</p>
                </div>
                
                <p>Please log in to your account to view the task details and submit your work before the deadline.</p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='http://localhost/datn/student/tasks_list.php?id={$class['id']}' 
                       style='background-color: #1a73e8; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                        View Task
                    </a>
                </div>
                
                <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                <p style='font-size: 12px; color: #666;'>
                    <strong style='color: #d32f2f; font-weight: 700;'>This is an automated message from Student Task Tracking Management System. Please do not reply to this email.</strong>
                </p>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Generate task updated email message
     */
    private function generateTaskUpdatedMessage($task, $class, $teacher, $student)
    {
        $start_date = date('d/m/Y H:i', strtotime($task['start_datetime']));
        $end_date = date('d/m/Y H:i', strtotime($task['end_datetime']));
        
        return "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #ff9800; border-bottom: 2px solid #ff9800; padding-bottom: 10px;'>
                    Task Updated
                </h2>
                
                <p>Dear <strong>{$student['username']}</strong>,</p>
                
                <p>A task in your class <strong>{$class['name']}</strong> has been updated by teacher <strong>{$teacher['username']}</strong>.</p>
                
                <div style='background-color: #fff3e0; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #ff9800;'>
                    <h3 style='margin-top: 0; color: #ff9800;'>{$task['title']}</h3>
                    <p><strong>Description:</strong> {$task['description']}</p>
                    <p><strong>Start Date:</strong> {$start_date}</p>
                    <p><strong>Due Date:</strong> {$end_date}</p>
                </div>
                
                <p>Please review the updated task details and make sure your submission meets the new requirements.</p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='http://localhost/datn/student/tasks_list.php?id={$class['id']}' 
                       style='background-color: #ff9800; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                        View Updated Task
                    </a>
                </div>
                
                <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                <p style='font-size: 12px; color: #666;'>
                    <strong style='color: #d32f2f; font-weight: 700;'>This is an automated message from Student Task Tracking Management System. Please do not reply to this email.</strong>
                </p>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Generate grade sent email message
     */
    private function generateGradeSentMessage($task, $class, $teacher, $student, $grade)
    {
        return "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #4caf50; border-bottom: 2px solid #4caf50; padding-bottom: 10px;'>
                    Grade Received
                </h2>
                
                <p>Dear <strong>{$student['username']}</strong>,</p>
                
                <p>You have received a grade for your submission in <strong>{$class['name']}</strong>.</p>
                
                <div style='background-color: #e8f5e8; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #4caf50;'>
                    <h3 style='margin-top: 0; color: #4caf50;'>{$task['title']}</h3>
                    <p><strong>Your Grade:</strong> <span style='font-size: 24px; font-weight: bold; color: #4caf50;'>{$grade}/100</span></p>
                    <p><strong>Graded by:</strong> {$teacher['username']}</p>
                </div>
                
                <p>Congratulations on completing this task! You can view your detailed grade and feedback in your student portal.</p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='http://localhost/datn/student/grade.php?id={$class['id']}' 
                       style='background-color: #4caf50; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                        View Grade Details
                    </a>
                </div>
                
                <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                <p style='font-size: 12px; color: #666;'>
                    <strong style='color: #d32f2f; font-weight: 700;'>This is an automated message from Student Task Tracking Management System. Please do not reply to this email.</strong>
                </p>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Generate deadline reminder email message
     */
    private function generateDeadlineReminderMessage($task, $teacher, $student)
    {
        $end_date = date('d/m/Y H:i', strtotime($task['end_datetime']));
        $hours_left = round((strtotime($task['end_datetime']) - time()) / 3600, 1);
        
        return "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #f44336; border-bottom: 2px solid #f44336; padding-bottom: 10px;'>
                    ⏰ Deadline Reminder
                </h2>
                
                <p>Dear <strong>{$student['username']}</strong>,</p>
                
                <p>This is a reminder that the deadline for the following task is approaching:</p>
                
                <div style='background-color: #ffebee; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #f44336;'>
                    <h3 style='margin-top: 0; color: #f44336;'>{$task['title']}</h3>
                    <p><strong>Class:</strong> {$task['class_name']}</p>
                    <p><strong>Due Date:</strong> {$end_date}</p>
                    <p><strong>Time Remaining:</strong> <span style='color: #f44336; font-weight: bold;'>{$hours_left} hours</span></p>
                </div>
                
                <p><strong>Don't forget to submit your work before the deadline!</strong></p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='http://localhost/datn/student/tasks_list.php?id={$task['class_id']}' 
                       style='background-color: #f44336; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                        Submit Now
                    </a>
                </div>
                
                <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                <p style='font-size: 12px; color: #666;'>
                    <strong style='color: #d32f2f; font-weight: 700;'>This is an automated message from Student Task Tracking Management System. Please do not reply to this email.</strong>
                </p>
            </div>
        </body>
        </html>
        ";
    }
}
?>