<?php
/**
 * SMTP Email Configuration - Using PHPMailer
 * Matches the requirements from check_mailer.php
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check for autoloader
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
}

// ==============================
// ⚙️ GMAIL CONFIGURATION
// ==============================
define('SMTP_HOST', 'smtp.gmail.com');
// Updated with your provided credentials
define('SMTP_USER', 'fyp.notificationsys@gmail.com'); 
define('SMTP_PASS', 'fkcv heot dqrh okwd'); 
define('SMTP_PORT', 587);
define('SYSTEM_NAME', 'FYP Management System');

/**
 * Generate random password (Helper function)
 */
function generateRandomPassword($length = 10) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

/**
 * Core Sending Function using PHPMailer
 */
function sendSimpleEmail($to, $subject, $htmlBody, $fromName = SYSTEM_NAME) {
    // Check if PHPMailer class exists
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        return ['success' => false, 'message' => 'PHPMailer not found. Run: composer require phpmailer/phpmailer'];
    }

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        // Recipients
        $mail->setFrom(SMTP_USER, $fromName);
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);

        $mail->send();
        return ['success' => true, 'message' => 'Email sent successfully via Gmail SMTP'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => "Message could not be sent. Mailer Error: {$mail->ErrorInfo}"];
    }
}

/**
 * Send student approval email
 */
function sendStudentApprovalEmail($email, $name, $password, $studentId) {
    $subject = "✅ Your FYP Portal Account Has Been Approved!";
    
    $htmlBody = "
    <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #ddd; border-radius: 10px; max-width: 600px; margin: 0 auto;'>
        <div style='background: linear-gradient(135deg, #28a745, #218838); color: white; padding: 20px; border-radius: 10px 10px 0 0; text-align: center;'>
            <h2 style='margin:0;'>Welcome to FYP Portal!</h2>
        </div>
        <div style='padding: 20px; background: #f9f9f9;'>
            <p>Dear <strong>{$name}</strong>,</p>
            <p>Congratulations! Your registration has been <strong>approved</strong>.</p>
            
            <div style='background: white; padding: 15px; border-left: 4px solid #28a745; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.05);'>
                <h3 style='margin-top:0; color: #28a745;'>📧 Your Login Credentials</h3>
                <p style='margin: 5px 0;'><strong>Student ID:</strong> {$studentId}</p>
                <p style='margin: 5px 0;'><strong>Email:</strong> {$email}</p>
                <p style='margin: 5px 0;'><strong>Password:</strong> <span style='background: #eee; padding: 2px 6px; border-radius: 4px; font-family: monospace;'>{$password}</span></p>
            </div>
            
            <p style='font-size: 0.9em; color: #666;'>⚠️ For security, please change your password after logging in.</p>
            
            <div style='text-align: center; margin-top: 20px;'>
                 <a href='http://localhost/fyp_management/fyp_main/Login.php' style='background: #0056b3; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Login Now</a>
            </div>
        </div>
        <div style='text-align: center; font-size: 12px; color: #999; margin-top: 20px; padding-top: 10px; border-top: 1px solid #ddd;'>
            <p>FYP Management System &copy; " . date('Y') . "</p>
        </div>
    </div>";
    
    return sendSimpleEmail($email, $subject, $htmlBody);
}

/**
 * Send lecturer credentials email
 */
function sendLecturerCredentialsEmail($email, $name, $password, $staffId) {
    $subject = "🎓 Your FYP Portal Supervisor Account";
    $htmlBody = "
    <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #ddd; border-radius: 10px; max-width: 600px; margin: 0 auto;'>
        <div style='background: linear-gradient(135deg, #0056b3, #004494); color: white; padding: 20px; border-radius: 10px 10px 0 0; text-align: center;'>
            <h2 style='margin:0;'>FYP Supervisor Account</h2>
        </div>
        <div style='padding: 20px; background: #f9f9f9;'>
            <p>Dear <strong>{$name}</strong>,</p>
            <p>Your supervisor account has been created successfully.</p>
            
            <div style='background: white; padding: 15px; border-left: 4px solid #0056b3; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.05);'>
                <h3 style='margin-top:0; color: #0056b3;'>🔑 Login Details</h3>
                <p style='margin: 5px 0;'><strong>Staff ID:</strong> {$staffId}</p>
                <p style='margin: 5px 0;'><strong>Username:</strong> {$email}</p>
                <p style='margin: 5px 0;'><strong>Password:</strong> <span style='background: #eee; padding: 2px 6px; border-radius: 4px; font-family: monospace;'>{$password}</span></p>
            </div>
            
            <div style='text-align: center; margin-top: 20px;'>
                 <a href='http://localhost/fyp_management/fyp_main/Login.php' style='background: #0056b3; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Login to Portal</a>
            </div>
        </div>
    </div>";
    
    return sendSimpleEmail($email, $subject, $htmlBody);
}

/**
 * Send student rejection email
 */
function sendStudentRejectionEmail($email, $name, $reason = '') {
    $subject = "❌ FYP Portal Registration Status";
    $htmlBody = "
    <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #ddd; border-radius: 10px; max-width: 600px; margin: 0 auto;'>
        <div style='background: #dc3545; color: white; padding: 20px; border-radius: 10px 10px 0 0; text-align: center;'>
            <h2 style='margin:0;'>Registration Status</h2>
        </div>
        <div style='padding: 20px; background: #f9f9f9;'>
            <p>Dear <strong>{$name}</strong>,</p>
            <p>We regret to inform you that your registration for the FYP Portal could not be approved at this time.</p>
            
            <div style='background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0; color: #856404;'>
                <strong>Reason:</strong> {$reason}
            </div>
            
            <p>Please contact the coordinator if you believe this is an error.</p>
        </div>
    </div>";
    
    return sendSimpleEmail($email, $subject, $htmlBody);
}
?>