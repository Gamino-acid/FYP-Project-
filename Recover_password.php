<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com; img-src 'self' data:;");

session_start();
include("connect.php");

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (file_exists('Email_config_SMTP.php')) {
    include("Email_config_SMTP.php");
} else {
    include("email_config.php");
}

function clean_input($data) {
    $data = trim($data);
    $data = strip_tags($data);
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

if (!function_exists('generateRandomPassword')) {
    function generateRandomPassword($length = 10) {
        return substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%'), 0, $length);
    }
}

$message = '';
$message_type = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security Warning: CSRF Token mismatch. Please refresh the page.");
    }

    $email_raw = clean_input($_POST['email']);
    $email = filter_var($email_raw, FILTER_VALIDATE_EMAIL);
    $student_id = clean_input($_POST['student_id']);

    if (!$email || empty($student_id)) {
        $message = "Please enter a valid Email and Student ID.";
        $message_type = 'error';
    } else {
        $sql = "SELECT u.fyp_userid, s.fyp_studname 
                FROM student s 
                JOIN user u ON s.fyp_userid = u.fyp_userid 
                WHERE s.fyp_studid = ? AND s.fyp_email = ? AND u.fyp_usertype = 'student' LIMIT 1";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ss", $student_id, $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $row = $result->fetch_assoc();
                $user_id = $row['fyp_userid'];
                $student_name = $row['fyp_studname'];
                
                $new_password = generateRandomPassword(10);
                
                $update_sql = "UPDATE user SET fyp_passwordhash = ? WHERE fyp_userid = ?";
                if ($update_stmt = $conn->prepare($update_sql)) {
                    $update_stmt->bind_param("si", $new_password, $user_id);
                    
                    if ($update_stmt->execute()) {
                        $subject = "🔒 Password Reset - FYP Portal";
                        $htmlBody = "
                        <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #ddd; border-radius: 10px; max-width: 600px; margin: 0 auto;'>
                            <div style='background: #0056b3; color: white; padding: 15px; border-radius: 10px 10px 0 0; text-align: center;'>
                                <h2 style='margin:0;'>Password Reset</h2>
                            </div>
                            <div style='padding: 20px; background: #f9f9f9;'>
                                <p>Dear <strong>{$student_name}</strong>,</p>
                                <p>We received a request to reset your password for the FYP Portal.</p>
                                <p>Your <strong>NEW</strong> login credentials are:</p>
                                
                                <div style='background: white; padding: 15px; border-left: 4px solid #0056b3; margin: 20px 0;'>
                                    <p style='margin: 5px 0;'><strong>Student ID:</strong> {$student_id}</p>
                                    <p style='margin: 5px 0;'><strong>Password:</strong> <span style='font-family: monospace; font-size: 1.2em; background: #eee; padding: 2px 6px;'>{$new_password}</span></p>
                                </div>
                                
                                <p>Please login immediately and change your password if possible.</p>
                                
                                <div style='text-align: center; margin-top: 20px;'>
                                     <a href='http://localhost/fyp_management/fyp_main/Login.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Login Now</a>
                                </div>
                            </div>
                        </div>";
                        
                        $emailResult = sendSimpleEmail($email, $subject, $htmlBody);
                        
                        if ($emailResult['success']) {
                            $message = "✅ Success! A new password has been sent to your email.";
                            $message_type = 'success';
                        } else {
                            $message = "⚠️ Password reset, but failed to send email. Contact Admin.";
                            $message_type = 'error';
                        }
                    } else {
                        $message = "Database error during password update.";
                        $message_type = 'error';
                    }
                    $update_stmt->close();
                }
            } else {
                $message = "❌ Verification Failed. Student ID and Email do not match our records.";
                $message_type = 'error';
            }
            $stmt->close();
        } else {
            $message = "Database connection error.";
            $message_type = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recover Password - FYP Portal</title>
    <!-- Updated Logo -->
    <link rel="icon" type="image/png" href="image/ladybug.png?v=<?php echo time(); ?>">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap');
        
        :root {
            --primary: #0056b3;
            --primary-hover: #004494;
            --bg-color: #f4f7fc;
            --card-bg: #ffffff;
            --text-color: #333;
        }

        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            background-color: var(--bg-color);
            background-image: radial-gradient(#e0e7ff 1px, transparent 1px);
            background-size: 20px 20px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .topbar {
            padding: 20px 40px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo-img {
            height: 50px;
            width: auto;
            object-fit: contain;
        }

        .system-title {
            font-size: 22px;
            font-weight: 600;
            color: var(--primary);
            letter-spacing: 0.5px;
        }

        .main-wrapper {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .recover-card {
            background: var(--card-bg);
            padding: 50px 40px;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            width: 100%;
            max-width: 450px;
            text-align: center;
        }

        .icon-circle {
            width: 70px;
            height: 70px;
            background: #e3f2fd;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto 20px;
        }

        .icon-circle i {
            font-size: 30px;
            color: #0056b3;
        }

        h2 { margin: 0 0 10px; color: #333; }
        p { color: #666; font-size: 14px; margin-bottom: 30px; }

        .input-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .input-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            font-size: 14px;
            color: #34495e;
        }

        .input-field {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            font-family: inherit;
            box-sizing: border-box;
            transition: all 0.3s;
            background: #fdfdfd;
        }

        .input-field:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(0, 86, 179, 0.1);
            background: #fff;
        }

        .btn-submit {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #0056b3, #004494);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(0, 86, 179, 0.3);
            margin-top: 10px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 86, 179, 0.4);
        }

        .back-link {
            display: inline-block;
            margin-top: 25px;
            color: #666;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }

        .back-link:hover { color: var(--primary); }
    </style>
</head>
<body>

    <div class="topbar">
        <!-- Updated Logo -->
        <img src="image/ladybug.png?v=<?php echo time(); ?>" alt="FYP Logo" class="logo-img" onerror="this.onerror=null; this.src='https://via.placeholder.com/150x50?text=FYP+Logo';">
        <div class="system-title">FYP Portal</div>
    </div>

    <div class="main-wrapper">
        <div class="recover-card">
            <div class="icon-circle">
                <i class="fas fa-key"></i>
            </div>
            <h2>Recover Password</h2>
            <p>Enter your details to receive a new password.</p>

            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <div class="input-group">
                    <label for="email">Registered Email</label>
                    <input type="email" id="email" name="email" class="input-field" placeholder="e.g. tp012345@mail.apu.edu.my" required>
                </div>

                <div class="input-group">
                    <label for="student_id">Student ID</label>
                    <input type="text" id="student_id" name="student_id" class="input-field" placeholder="e.g. TP012345" required>
                </div>

                <button type="submit" class="btn-submit">Reset Password</button>
            </form>

            <a href="Login.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Login
            </a>
        </div>
    </div>

    <script>
        <?php if ($message): ?>
        Swal.fire({
            icon: '<?php echo $message_type; ?>',
            title: '<?php echo $message_type == "success" ? "Done!" : "Error"; ?>',
            text: '<?php echo $message; ?>',
            confirmButtonColor: '#0056b3'
        });
        <?php endif; ?>
    </script>

</body>
</html>