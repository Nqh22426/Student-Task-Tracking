<?php
/**
 * Gmail SMTP settings
 */

// Gmail SMTP Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'hung.job246@gmail.com');
define('SMTP_PASSWORD', 'czsa ebaw xibv ivrb');

// Email Settings
define('FROM_EMAIL', 'hung.job246@gmail.com');
define('FROM_NAME', 'Student Task Tracking Management System');
define('EMAIL_CHARSET', 'UTF-8');
define('EMAIL_CONTENT_TYPE', 'text/html');

// Debug Settings (set to false in production)
define('SMTP_DEBUG', false);

// Validation
if (!defined('SMTP_HOST') || !defined('SMTP_USERNAME') || !defined('SMTP_PASSWORD')) {
    die('Email configuration is incomplete. Please check email_config.php');
}

// Test connection function
function testEmailConfig() {
    return [
        'host' => SMTP_HOST,
        'port' => SMTP_PORT,
        'username' => SMTP_USERNAME,
        'from_email' => FROM_EMAIL,
        'from_name' => FROM_NAME
    ];
}

// Email templates directory
define('EMAIL_TEMPLATES_DIR', __DIR__ . '/email_templates/');

// Create templates directory if it doesn't exist
if (!file_exists(EMAIL_TEMPLATES_DIR)) {
    mkdir(EMAIL_TEMPLATES_DIR, 0755, true);
}
?> 