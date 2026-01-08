<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../PHPMailer/src/Exception.php';
require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';
require '../connect.php';

// Data from coordinator form
$stud_id = $_POST['stud_id'];
$subject = $_POST['subject'];
$message = $_POST['message'];

// 1️⃣ Get student email (NO LOGIN REQUIRED)
$sql = "SELECT fyp_email FROM student WHERE fyp_studid = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $stud_id);
$stmt->execute();
$stmt->bind_result($student_email);
$stmt->fetch();
$stmt->close();

if (!$student_email) {
    die("❌ Student email not found.");
}

// 2️⃣ Send email
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'fyp.notificationsys@gmail.com';
    $mail->Password = 'YOUR_APP_PASSWORD';
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    $mail->setFrom('fyp.notificationsys@gmail.com', 'FYP Coordinator');
    $mail->addAddress($student_email);

    $mail->Subject = $subject;
    $mail->Body = $message;

    $mail->send();

    // 3️⃣ Log notification
    $log = $conn->prepare(
        "INSERT INTO notifications (student_id, subject, message)
         VALUES (?, ?, ?)"
    );
    $log->bind_param("iss", $stud_id, $subject, $message);
    $log->execute();
    $log->close();

    echo "✅ Email sent to student successfully.";

} catch (Exception $e) {
    echo "❌ Email failed: " . $mail->ErrorInfo;
}

$conn->close();
?>