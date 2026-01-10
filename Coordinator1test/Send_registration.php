<?php
// ENABLE DEBUGGING
error_reporting(E_ALL);
ini_set('display_errors', 1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// =================================================================
// ⚙️ CONFIGURATION: SENDER SETTINGS
// =================================================================

// 1. SENDER EMAIL: The Gmail account acting as the System Coordinator
$SENDER_EMAIL    = 'fyp.notificationsystem@gmail.com'; 

// 2. SENDER PASSWORD: Your newest App Password (spaces removed)
$SENDER_PASSWORD = 'kauzztfaxexnzaor';  

$SENDER_NAME     = 'MMU FYP Admission';
// =================================================================

echo "<style>body{font-family:sans-serif; padding:20px;}</style>";
echo "<h2>🚀 Importing Student & Sending Notification...</h2>";

// 1. SMART LOADER
$phpmailer_found = false;
$possible_paths = ['PHPMailer/PHPMailer.php', 'src/PHPMailer.php', 'PHPMailer-master/src/PHPMailer.php'];
foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        $dir = dirname($path);
        require $dir . '/Exception.php'; require $dir . '/PHPMailer.php'; require $dir . '/SMTP.php';
        $phpmailer_found = true; break;
    }
}
if (!$phpmailer_found) die("<h3 style='color:red'>❌ Error: PHPMailer files not found.</h3>");

// 2. Load Database
if (file_exists('connect.php')) include 'connect.php';
elseif (file_exists('../connect.php')) include '../connect.php';
else die("❌ Error: connect.php not found.");

if (isset($_POST['send_btn'])) {
    $student_id = $_POST['student_id'];
    
    // THIS IS THE RECEIVER (The email you typed in the form)
    $target_email = $_POST['test_email']; 

    // 3. GENERATE PASSWORD
    $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
    $pass = array(); 
    $alphaLength = strlen($alphabet) - 1; 
    for ($i = 0; $i < 8; $i++) { $pass[] = $alphabet[rand(0, $alphaLength)]; }
    $temp_password = implode($pass); 

    // 4. UPDATE DATABASE
    $update_sql = "UPDATE student SET fyp_password = ? WHERE fyp_studid = ?";
    $update_stmt = $conn->prepare($update_sql);

    if ($update_stmt === false) die("❌ DB Error: Missing password column.");
    $update_stmt->bind_param("si", $temp_password, $student_id);
    
    if ($update_stmt->execute()) {
        echo "<div style='color:green'>✅ Student Record Updated (Admission Accepted).</div>";
    } else {
        die("❌ DB Error: " . $conn->error);
    }

    // 5. Fetch Student Details
    $sql = "SELECT * FROM student WHERE fyp_studid = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $stud_name = $row['fyp_studname'];
        $stud_tp   = $row['fyp_studfullid']; 

        echo "<div>📄 Emailing: <b>$stud_name</b> via <b>$SENDER_EMAIL</b></div>";
        echo "<div>📨 Delivering to Receiver: <b>$target_email</b></div><hr>";

        // 6. Send Email
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $SENDER_EMAIL; 
            $mail->Password   = $SENDER_PASSWORD; 
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom($SENDER_EMAIL, $SENDER_NAME);
            
            // SEND TO THE STUDENT EMAIL YOU TYPED IN THE FORM
            $mail->addAddress($target_email, $stud_name); 

            $mail->isHTML(true);
            $mail->Subject = "Admission Accepted: Final Year Project ($stud_tp)";
            $mail->Body    = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; border: 1px solid #ddd; padding: 20px;'>
                <div style='background: #28a745; color: white; padding: 15px; text-align: center;'>
                    <h2 style='margin:0;'>Application Accepted</h2>
                </div>
                <div style='padding: 20px; color: #333;'>
                    <p>Dear <b>$stud_name</b>,</p>
                    <p>We are pleased to inform you that you have been <b>accepted</b> into the Final Year Project module.</p>
                    
                    <div style='background: #f8f9fa; border-left: 5px solid #28a745; padding: 15px; margin: 20px 0;'>
                        <h3 style='margin-top:0;'>Access Credentials</h3>
                        <p><b>Student ID:</b> $stud_tp</p>
                        <p><b>Temporary Password:</b> <strong style='font-size:18px;'>$temp_password</strong></p>
                    </div>

                    <p>Please log in to the portal to view the supervisor quota list.</p>
                    <br>
                    <p>Regards,<br><b>$SENDER_NAME</b><br>Multimedia University</p>
                </div>
            </div>
            ";

            $mail->send();
            
            echo "<br><div style='background:#d4edda; color:#155724; padding:20px; border-radius:8px; text-align:center; border:1px solid #c3e6cb;'>
                    <h1>✅ Notification Sent!</h1>
                    <p><b>Sender:</b> $SENDER_EMAIL</p>
                    <p><b>Receiver:</b> $target_email</p>
                    <a href='registration_form.php' style='display:inline-block; margin-top:10px; padding:10px 20px; background:#155724; color:white; text-decoration:none; border-radius:5px;'>Import Next Student</a>
                  </div>";

        } catch (Exception $e) {
            echo "<div style='background:#f8d7da; padding:20px; color:#721c24; border:1px solid red;'>
                    <h3>❌ AUTHENTICATION ERROR</h3>
                    <p>" . $mail->ErrorInfo . "</p>
                    <hr>
                    <p><b>Troubleshooting:</b></p>
                    <p>We tried to log in to <b>$SENDER_EMAIL</b> using password <b>$SENDER_PASSWORD</b>.</p>
                    <p>If this failed, it means this password was generated for a different email account (like your student email).</p>
                    <p>You MUST generate the App Password inside the <b>Google Account Settings for $SENDER_EMAIL</b>.</p>
                  </div>";
        }

    } else {
        echo "Student not found.";
    }
}
?>