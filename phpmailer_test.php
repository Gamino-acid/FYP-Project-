<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load PHPMailer classes
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

$mail = new PHPMailer(true);

try {
    // SMTP settings
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'fyp.notificationsys@gmail.com';
    $mail->Password = 'bybq zwux lrth xdkx ';
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    // Email content
    $mail->setFrom('fyp.notificationsys@gmail.com', 'FYP Management System');
    $mail->addAddress('chinsin220@gmail.com');

    $mail->Subject = 'PHPMailer Test';
    $mail->Body = 'PHPMailer setup is successful.';

    $mail->send();
    echo '✅ PHPMailer email sent successfully';

} catch (Exception $e) {
    echo '❌ PHPMailer Error: ' . $mail->ErrorInfo;
}