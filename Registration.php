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

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security Warning: CSRF Token mismatch. Please refresh the page.");
    }

    $first_name = clean_input($_POST['first_name']);
    $last_name = clean_input($_POST['last_name']);
    
    $email_raw = trim($_POST['email']);
    
    if (empty($first_name) || empty($last_name)) {
        $message = "Please enter your first name and last name.";
        $message_type = 'error';
    } elseif (!filter_var($email_raw, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $message_type = 'error';
    } else {
        $stmt = $conn->prepare("SELECT id FROM pending_registration WHERE email = ? AND status = 'pending'");
        $stmt->bind_param("s", $email_raw);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $message = "You have already submitted a request. Please wait for approval.";
            $message_type = 'warning';
        } else {
            $stmt->close();
            
            $stmt2 = $conn->prepare("SELECT fyp_userid FROM user WHERE fyp_username = ?");
            $stmt2->bind_param("s", $email_raw);
            $stmt2->execute();
            $result2 = $stmt2->get_result();
            
            if ($result2->num_rows > 0) {
                $message = "This email is already registered. Please login instead.";
                $message_type = 'error';
            } else {
                $stmt3 = $conn->prepare("INSERT INTO pending_registration (first_name, last_name, email, status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
                $stmt3->bind_param("sss", $first_name, $last_name, $email_raw);
                
                if ($stmt3->execute()) {
                    $message = "Registration request submitted! Check your email later for login credentials.";
                    $message_type = 'success';
                } else {
                    $message = "System error. Please try again later.";
                    $message_type = 'error';
                }
                $stmt3->close();
            }
            $stmt2->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - FYP Portal</title>
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

        .register-card {
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(5px);
            padding: 50px 40px;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 500px;
            text-align: center;
        }

        .register-card h1 {
            font-size: 26px;
            color: #2c3e50;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .register-card p {
            color: #7f8c8d;
            margin-bottom: 35px;
            font-size: 14px;
        }

        .input-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .input-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #34495e;
            margin-bottom: 8px;
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

        .row {
            display: flex;
            gap: 15px;
        }
        
        .col {
            flex: 1;
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

        .back-link:hover {
            color: var(--primary);
        }

        .info-box {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            text-align: left;
            font-size: 13px;
            color: #0d47a1;
        }
        
        .info-box i { margin-right: 5px; }
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
        <div class="register-card">
            <h1>Student Registration</h1>
            <p>Request access to the FYP Management System</p>

            <div class="info-box">
                <i class="fas fa-info-circle"></i> 
                <strong>Note:</strong> Your account will be reviewed by the coordinator. Login credentials will be sent to your email upon approval.
            </div>

            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <div class="row">
                    <div class="col">
                        <div class="input-group">
                            <label>First Name</label>
                            <input type="text" name="first_name" class="input-field" placeholder="John" required>
                        </div>
                    </div>
                    <div class="col">
                        <div class="input-group">
                            <label>Last Name</label>
                            <input type="text" name="last_name" class="input-field" placeholder="Doe" required>
                        </div>
                    </div>
                </div>

                <div class="input-group">
                    <label>Email Address</label>
                    <input type="email" name="email" class="input-field" placeholder="your.email@example.com" required>
                </div>

                <button type="submit" name="register" class="btn-submit">
                    <i class="fas fa-paper-plane" style="margin-right:8px;"></i> Submit Request
                </button>
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
            title: '<?php echo $message_type == "success" ? "Success!" : "Oops..."; ?>',
            text: '<?php echo $message; ?>',
            confirmButtonColor: '#0056b3'
        });
        <?php endif; ?>
    </script>

</body>
</html>