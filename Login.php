<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com; img-src 'self' data:;");

session_start();
include("connect.php"); 

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$username = $password = "";
$login_err = "";

if ($_SERVER["REQUEST_METHOD"] == 'POST') {

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security Warning: CSRF Token mismatch. Please refresh the page and try again.");
    }

    if (empty(trim($_POST["username"])) || empty(trim($_POST["password"]))) {
        $login_err = "Please enter username and password.";
    } else {
        $username = trim($_POST['username']);
        $password = $_POST['password']; 
    }

    if (empty($login_err)) {
        $sql = "SELECT fyp_userid, fyp_passwordhash, fyp_usertype, fyp_status FROM `user` WHERE fyp_username = ? LIMIT 1";
        
        if (isset($conn) && $stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $param_username);
            $param_username = $username;
            
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                
                if ($result->num_rows == 1) {
                    $user = $result->fetch_assoc();
                    
                    if ($password === $user['fyp_passwordhash']) { 
                        
                        if ($user['fyp_status'] === 'Archived') {
                            $login_err = "Your account has been deactivated. Please contact the coordinator.";
                        } else {
                            session_regenerate_id(true);

                            $user_id = $user['fyp_userid'];
                            $user_type = $user['fyp_usertype'];

                            if ($user_type === 'student') {
                                header("location: Student_mainpage.php?auth_user_id=" . urlencode($user_id));
                            } else if ($user_type === 'lecturer') {
                                header("location: Supervisor_mainpage.php?auth_user_id=" . urlencode($user_id));
                            } else if ($user_type === 'coordinator') {
                                header("location: Coordinator_mainpage.php?auth_user_id=" . urlencode($user_id));
                            } else {
                                $login_err = "Login failed: Unknown user type.";
                            }
                            exit;
                        }
                        
                    } else {
                        $login_err = "Incorrect password.";
                    }
                } else {
                    $login_err = "No account found with that email.";
                }
            } else {
                $login_err = "System error. Please try again.";
            }
            $stmt->close();
        }
    }
}

if (isset($conn)) {
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - FYP Management System</title>
    <link rel="icon" type="image/png" href="image/ladybug.png?v=<?php echo time(); ?>">
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

        .login-card {
            background: var(--card-bg);
            padding: 50px 40px;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            width: 100%;
            max-width: 450px;
            text-align: center;
            transition: transform 0.3s ease;
        }

        .login-card h1 {
            font-size: 28px;
            color: #2c3e50;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .login-card p {
            color: #7f8c8d;
            margin-bottom: 35px;
            font-size: 15px;
        }

        .input-wrapper {
            position: relative;
            margin-bottom: 25px;
            text-align: left;
        }

        .input-wrapper label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #34495e;
            margin-bottom: 8px;
        }

        .input-field {
            width: 100%;
            padding: 14px 15px;
            padding-left: 45px; 
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

        .input-icon {
            position: absolute;
            left: 15px;
            top: 42px; 
            color: #95a5a6;
            font-size: 18px;
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 42px;
            color: #95a5a6;
            cursor: pointer;
            transition: color 0.3s;
        }

        .toggle-password:hover {
            color: var(--primary);
        }

        .btn-login {
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
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 86, 179, 0.4);
        }

        .help-section {
            margin-top: 25px;
            font-size: 14px;
            color: #666;
        }

        .help-section a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        .help-section a:hover {
            text-decoration: underline;
        }

        .divider {
            display: flex;
            align-items: center;
            margin: 30px 0;
            color: #bdc3c7;
        }

        .divider::before, .divider::after {
            content: "";
            flex: 1;
            border-bottom: 1px solid #e0e0e0;
        }

        .divider span {
            padding: 0 10px;
            font-size: 13px;
        }

        .btn-register {
            display: inline-block;
            width: 100%;
            padding: 14px;
            border: 2px solid var(--primary);
            border-radius: 8px;
            background: transparent;
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
            box-sizing: border-box;
            transition: all 0.3s;
        }

        .btn-register:hover {
            background: var(--primary);
            color: white;
        }

        .alert {
            background: #ffecec;
            color: #e74c3c;
            border: 1px solid #fadbd8;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
    </style>
</head>
<body>

    <div class="topbar">
        <img src="image/ladybug.png?v=<?php echo time(); ?>" alt="FYP Logo" class="logo-img" onerror="this.onerror=null; this.src='https://via.placeholder.com/150x50?text=FYP+Logo';">
        <div class="system-title">FYP Portal</div>
    </div>

    <div class="main-wrapper">
        <div class="login-card">
            <h1>Welcome Back</h1>
            <p>Please login to access your dashboard</p>

            <?php if (!empty($login_err)): ?>
                <div class="alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($login_err); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <div class="input-wrapper">
                    <label for="username">Email Address</label>
                    <i class="fas fa-envelope input-icon"></i>
                    <input type="text" id="username" name="username" class="input-field" 
                           value="<?php echo htmlspecialchars($username); ?>" 
                           placeholder="Enter your email" required>
                </div>

                <div class="input-wrapper">
                    <label for="password">Password</label>
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" id="password" name="password" class="input-field" 
                           placeholder="Enter your password" required>
                    <i class="far fa-eye-slash toggle-password" id="togglePassword"></i>
                </div>

                <button type="submit" class="btn-login">LOGIN</button>

                <div class="help-section">
                    Forgot your password? <a href="Recover_password.php">Recover here</a>
                </div>
            </form>

            <div class="divider">
                <span>OR</span>
            </div>

            <a href="Registration.php" class="btn-register">
                Register as Student
            </a>
        </div>
    </div>

    <script>
        const togglePassword = document.querySelector('#togglePassword');
        const passwordInput = document.querySelector('#password');

        togglePassword.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    </script>
</body>
</html>