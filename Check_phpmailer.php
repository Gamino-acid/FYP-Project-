<?php
/**
 * Check PHPMailer Installation
 */

echo "<h2>Checking PHPMailer Installation...</h2>";

// Check if vendor/autoload.php exists
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo "<p style='color:green;'>✅ vendor/autoload.php found</p>";
    require __DIR__ . '/vendor/autoload.php';
} else {
    echo "<p style='color:red;'>❌ vendor/autoload.php NOT found</p>";
    echo "<p>Run: <code>composer require phpmailer/phpmailer</code></p>";
    exit;
}

// Check if PHPMailer class exists
if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    echo "<p style='color:green;'>✅ PHPMailer class is available</p>";
    
    // Get PHPMailer version
    $reflector = new ReflectionClass('PHPMailer\PHPMailer\PHPMailer');
    $filename = $reflector->getFileName();
    echo "<p style='color:blue;'>📦 PHPMailer location: {$filename}</p>";
    
    // Try to create instance
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        echo "<p style='color:green;'>✅ PHPMailer instance created successfully</p>";
    } catch (Exception $e) {
        echo "<p style='color:red;'>❌ Error creating PHPMailer: {$e->getMessage()}</p>";
    }
    
} else {
    echo "<p style='color:red;'>❌ PHPMailer class NOT found</p>";
    echo "<p>Run: <code>composer require phpmailer/phpmailer</code></p>";
    exit;
}

echo "<hr>";
echo "<h3>✅ PHPMailer is ready to use!</h3>";
echo "<p><strong>Next steps:</strong></p>";
echo "<ol>";
echo "<li>Get Gmail App Password from: <a href='https://myaccount.google.com/apppasswords' target='_blank'>https://myaccount.google.com/apppasswords</a></li>";
echo "<li>Update Email_config.php with your Gmail and App Password</li>";
echo "<li>Test sending email with test_email.php</li>";
echo "</ol>";
?>