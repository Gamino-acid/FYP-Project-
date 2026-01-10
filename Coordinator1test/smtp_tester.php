<?php
// ENABLE DEBUGGING
error_reporting(E_ALL);
ini_set('display_errors', 1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// =================================================================
// ⚙️ CREDENTIALS TO TEST
// =================================================================
$TEST_EMAIL    = 'chin.foog.sin@student.mmu.edu.my'; 
$TEST_PASSWORD = 'qkokhgiezphbajcr'; 
// =================================================================

echo "<style>body{font-family:sans-serif; padding:20px; background:#222; color:#fff;}</style>";
echo "<h2>🔌 SMTP Connection Test</h2>";

// 1. LOAD PHPMAILER
$phpmailer_found = false;
$possible_paths = ['PHPMailer/PHPMailer.php', 'src/PHPMailer.php'];
foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        $dir = dirname($path);
        require $dir . '/Exception.php';
        require $dir . '/PHPMailer.php';
        require $dir . '/SMTP.php';
        $phpmailer_found = true;
        break;
    }
}

if (!$phpmailer_found) die("<h3 style='color:red'>❌ PHPMailer not found. Check folders.</h3>");

// 2. ATTEMPT CONNECTION
$mail = new PHPMailer(true);
try {
    echo "<p>Attempting to connect to Google as <b>$TEST_EMAIL</b>...</p>";
    
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = $TEST_EMAIL;
    $mail->Password   = $TEST_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // We verify the connection without sending an email
    if($mail->smtpConnect()) {
        echo "<div style='background:green; padding:20px; border-radius:5px;'>
                <h1>✅ CONNECTION SUCCESSFUL!</h1>
                <p>Your Username and Password are CORRECT.</p>
                <p>The MMU server allows SMTP connections.</p>
              </div>";
        $mail->smtpClose();
    } else {
        throw new Exception("Connect failed.");
    }

} catch (Exception $e) {
    echo "<div style='background:darkred; padding:20px; border-radius:5px;'>
            <h1>❌ CONNECTION FAILED</h1>
            <p><b>Error:</b> " . $mail->ErrorInfo . "</p>
            <hr>
            <h3>⚠️ DIAGNOSIS:</h3>
            <p>If the password is correct, <b>MMU has likely blocked SMTP access for student accounts.</b></p>
            <p><b>SOLUTION:</b> You MUST switch back to using a personal Gmail (like <code>fyp.notificationsystem@gmail.com</code>) as the Sender.</p>
          </div>";
}
?>