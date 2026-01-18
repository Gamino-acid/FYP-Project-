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

function clean_input($data) {
    $data = trim($data);
    $data = strip_tags($data);
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

$conn->query("CREATE TABLE IF NOT EXISTS password_reset_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL,
    status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    request_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INT DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL
)");

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
                $check_stmt = $conn->prepare("SELECT request_id FROM password_reset_requests WHERE student_id = ? AND status = 'Pending'");
                $check_stmt->bind_param("s", $student_id);
                $check_stmt->execute();
                
                if ($check_stmt->get_result()->num_rows > 0) {
                    $message = "⚠️ You already have a pending request. Please wait for the Coordinator to approve it.";
                    $message_type = 'warning';
                } else {
                    $insert_stmt = $conn->prepare("INSERT INTO password_reset_requests (student_id, email, status, request_date) VALUES (?, ?, 'Pending', NOW())");
                    $insert_stmt->bind_param("ss", $student_id, $email);
                    
                    if ($insert_stmt->execute()) {
                        $message = "✅ Request Sent! The Coordinator will review your request and you will receive an email upon approval.";
                        $message_type = 'success';
                    } else {
                        $message = "Database error. Please try again later.";
                        $message_type = 'error';
                    }
                    $insert_stmt->close();
                }
                $check_stmt->close();
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
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }

        .slideshow {
            position: fixed;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            z-index: -1;
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .slideshow li {
            width: 100%;
            height: 100%;
            position: absolute;
            top: 0;
            left: 0;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            opacity: 0;
            z-index: 0;
            animation: imageAnimation 36s linear infinite;
        }

        .slideshow li:nth-child(1) { background-image: url('image/School1.jpg'); animation-delay: 0s; }
        .slideshow li:nth-child(2) { background-image: url('image/School2.jpg'); animation-delay: 6s; }
        .slideshow li:nth-child(3) { background-image: url('image/Student1.jpg'); animation-delay: 12s; }
        .slideshow li:nth-child(4) { background-image: url('image/Student2.jpg'); animation-delay: 18s; }
        .slideshow li:nth-child(5) { background-image: url('image/Student3.jpg'); animation-delay: 24s; }
        .slideshow li:nth-child(6) { background-image: url('image/Student4.jpg'); animation-delay: 30s; }

        .slideshow::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            z-index: 1;
        }

        @keyframes imageAnimation {
            0% { opacity: 0; animation-timing-function: ease-in; }
            2% { opacity: 1; animation-timing-function: ease-out; }
            14% { opacity: 1; }
            16% { opacity: 0; }
            100% { opacity: 0; }
        }

        .topbar {
            padding: 20px 40px;
            display: flex;
            align-items: center;
            gap: 15px;
            background-color: var(--primary);
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            position: relative;
            z-index: 2;
            width: 100%;
            box-sizing: border-box;
        }

        .logo-img {
            height: 50px;
            width: auto;
            object-fit: contain;
            background: white;
            padding: 5px;
            border-radius: 8px;
        }

        .system-title {
            font-size: 22px;
            font-weight: 600;
            color: #ffffff;
            letter-spacing: 0.5px;
        }

        .main-wrapper {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            position: relative;
            z-index: 2;
        }

        .recover-card {
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(5px);
            padding: 50px 40px;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
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

    <ul class="slideshow">
        <li></li>
        <li></li>
        <li></li>
        <li></li>
        <li></li>
        <li></li>
    </ul>

    <div class="topbar">
        <img src="image/ladybug.png?v=<?php echo time(); ?>" alt="FYP Logo" class="logo-img" onerror="this.onerror=null; this.src='https://via.placeholder.com/150x50?text=FYP+Logo';">
        <div class="system-title">FYP Portal</div>
    </div>

    <div class="main-wrapper">
        <div class="recover-card">
            <div class="icon-circle">
                <i class="fas fa-paper-plane"></i>
            </div>
            <h2>Request Password Reset</h2>
            <p>Your request will be sent to the Coordinator for approval.</p>

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

                <button type="submit" class="btn-submit">Send Request</button>
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
            title: '<?php echo $message_type == "success" ? "Request Sent" : ($message_type == "warning" ? "Pending" : "Error"); ?>',
            text: '<?php echo $message; ?>',
            confirmButtonColor: '#0056b3'
        });
        <?php endif; ?>
    </script>

</body>
</html>