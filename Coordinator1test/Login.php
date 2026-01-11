<?php
/**
 * Login Page with Google OAuth
 * =============================
 * Main login page supporting both traditional and Google login
 */

session_start();
require_once 'db_connect.php';  // Your database connection
require_once 'google_config.php';
require_once 'GoogleOAuth.php';

date_default_timezone_set('Asia/Kuala_Lumpur');

// Initialize variables
$username = $password = "";
$login_err = "";
$success_msg = "";

// Check for messages from Google callback
if (isset($_SESSION['login_error'])) {
    $login_err = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}
if (isset($_SESSION['login_success'])) {
    $success_msg = $_SESSION['login_success'];
    unset($_SESSION['login_success']);
}

// If already logged in, redirect
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['user_role'] ?? '';
    if ($role === 'student') {
        header("Location: Student_mainpage.php");
        exit;
    } elseif ($role === 'coordinator') {
        header("Location: Coordinator/Coordinator_mainpage.php");
        exit;
    } elseif ($role === 'lecturer') {
        header("Location: Supervisor_mainpage.php");
        exit;
    }
}

// Generate Google OAuth URL
$google = new GoogleOAuth(GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_REDIRECT_URI);
$google_login_url = $google->getAuthUrl();

// Handle traditional login form
if ($_SERVER["REQUEST_METHOD"] == 'POST' && isset($_POST['login'])) {
    
    if (empty(trim($_POST["username"])) || empty(trim($_POST["password"]))) {
        $login_err = "Please enter both username and password.";
    } else {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        
        // Query database
        $sql = "SELECT fyp_userid, fyp_username, fyp_passwordhash, fyp_usertype, fyp_email 
                FROM user WHERE fyp_username = ? OR fyp_email = ? LIMIT 1";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ss", $username, $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();
                
                // Verify password (supports both hashed and plain text for development)
                if (password_verify($password, $user['fyp_passwordhash']) || $password === $user['fyp_passwordhash']) {
                    
                    // Set session
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['fyp_userid'];
                    $_SESSION['username'] = $user['fyp_username'];
                    $_SESSION['email'] = $user['fyp_email'];
                    $_SESSION['user_role'] = strtolower(trim($user['fyp_usertype']));
                    
                    $user_type = strtolower(trim($user['fyp_usertype']));
                    
                    // Redirect based on role
                    if ($user_type === 'student') {
                        // Check if student profile exists
                        $chk = $conn->prepare("SELECT fyp_studid FROM student WHERE fyp_userid = ?");
                        $chk->bind_param("i", $user['fyp_userid']);
                        $chk->execute();
                        $chk->store_result();
                        
                        if ($chk->num_rows === 0) {
                            header("Location: Registration.php");
                            exit;
                        }
                        header("Location: Student_mainpage.php");
                        exit;
                        
                    } elseif ($user_type === 'coordinator') {
                        header("Location: Coordinator/Coordinator_mainpage.php");
                        exit;
                        
                    } elseif ($user_type === 'lecturer') {
                        header("Location: Supervisor_mainpage.php");
                        exit;
                        
                    } else {
                        $login_err = "Unknown user type: $user_type";
                    }
                } else {
                    $login_err = "Invalid password.";
                }
            } else {
                $login_err = "No account found with that username/email.";
            }
            $stmt->close();
        } else {
            $login_err = "Database error. Please try again.";
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - FYP Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
        
        :root {
            --primary-color: #4285f4;
            --primary-hover: #357abd;
            --google-red: #ea4335;
            --google-blue: #4285f4;
            --google-green: #34a853;
            --google-yellow: #fbbc05;
            --text-color: #333;
            --text-light: #666;
            --border-color: #ddd;
            --bg-light: #f8f9fa;
            --error-bg: #fce4e4;
            --error-color: #cc0033;
            --success-bg: #d4edda;
            --success-color: #155724;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        /* Top Bar */
        .topbar {
            background: rgba(255,255,255,0.95);
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .logo {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .logo i {
            margin-right: 10px;
        }
        
        /* Main Container */
        .main-wrapper {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 20px;
        }
        
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 450px;
            padding: 40px;
            animation: slideUp 0.5s ease;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h1 {
            font-size: 28px;
            color: var(--text-color);
            margin-bottom: 10px;
        }
        
        .login-header p {
            color: var(--text-light);
            font-size: 14px;
        }
        
        /* Alert Messages */
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
        }
        
        .alert-error {
            background: var(--error-bg);
            color: var(--error-color);
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background: var(--success-bg);
            color: var(--success-color);
            border: 1px solid #c3e6cb;
        }
        
        /* Google Sign In Button */
        .google-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            width: 100%;
            padding: 14px 20px;
            background: white;
            border: 2px solid #ddd;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 500;
            color: var(--text-color);
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            margin-bottom: 25px;
        }
        
        .google-btn:hover {
            border-color: var(--google-blue);
            box-shadow: 0 4px 15px rgba(66, 133, 244, 0.3);
            transform: translateY(-2px);
        }
        
        .google-btn img {
            width: 24px;
            height: 24px;
        }
        
        .google-icon {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Divider */
        .divider {
            display: flex;
            align-items: center;
            margin: 25px 0;
            color: var(--text-light);
            font-size: 13px;
        }
        
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border-color);
        }
        
        .divider span {
            padding: 0 15px;
        }
        
        /* Form Inputs */
        .input-group {
            margin-bottom: 20px;
        }
        
        .input-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 14px;
            color: var(--text-color);
        }
        
        .input-group input {
            width: 100%;
            padding: 14px 15px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s ease;
        }
        
        .input-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(66, 133, 244, 0.1);
        }
        
        .password-wrapper {
            position: relative;
        }
        
        .password-wrapper input {
            padding-right: 45px;
        }
        
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #999;
            transition: color 0.3s;
        }
        
        .toggle-password:hover {
            color: var(--primary-color);
        }
        
        /* Submit Button */
        .btn-submit {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        
        /* Links */
        .form-links {
            text-align: center;
            margin-top: 25px;
            font-size: 14px;
        }
        
        .form-links a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        
        .form-links a:hover {
            text-decoration: underline;
        }
        
        .form-links p {
            margin: 10px 0;
            color: var(--text-light);
        }
        
        /* Role Info */
        .role-info {
            background: var(--bg-light);
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            font-size: 13px;
        }
        
        .role-info h4 {
            color: var(--text-color);
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .role-info ul {
            list-style: none;
            padding: 0;
        }
        
        .role-info li {
            padding: 5px 0;
            color: var(--text-light);
        }
        
        .role-info li i {
            width: 20px;
            margin-right: 8px;
        }
        
        .role-info .student-icon { color: #4285f4; }
        .role-info .lecturer-icon { color: #34a853; }
        .role-info .coordinator-icon { color: #ea4335; }
        
        /* Footer */
        footer {
            text-align: center;
            padding: 20px;
            color: rgba(255,255,255,0.8);
            font-size: 13px;
        }
    </style>
</head>
<body>

<header class="topbar">
    <div class="logo">
        <i class="fas fa-graduation-cap"></i>
        FYP Management System
    </div>
</header>

<div class="main-wrapper">
    <div class="login-container">
        <div class="login-header">
            <h1>Welcome Back</h1>
            <p>Sign in to continue to your dashboard</p>
        </div>
        
        <?php if ($login_err): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($login_err); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($success_msg): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?>
        </div>
        <?php endif; ?>
        
        <!-- Google Sign In Button -->
        <a href="<?php echo htmlspecialchars($google_login_url); ?>" class="google-btn">
            <div class="google-icon">
                <svg width="24" height="24" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
            </div>
            <span>Sign in with Google</span>
        </a>
        
        <div class="divider">
            <span>or sign in with email</span>
        </div>
        
        <!-- Traditional Login Form -->
        <form method="POST" action="">
            <div class="input-group">
                <label for="username">Email / Username</label>
                <input type="text" id="username" name="username" 
                       value="<?php echo htmlspecialchars($username); ?>" 
                       placeholder="Enter your email or username" required>
            </div>
            
            <div class="input-group">
                <label for="password">Password</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" 
                           placeholder="Enter your password" required>
                    <i class="fas fa-eye-slash toggle-password" id="togglePassword"></i>
                </div>
            </div>
            
            <button type="submit" name="login" class="btn-submit">
                <i class="fas fa-sign-in-alt"></i> Sign In
            </button>
        </form>
        
        <div class="form-links">
            <p><a href="forgot_password.php">Forgot Password?</a></p>
            <p>New student? <a href="Registration.php">Register here</a></p>
        </div>
        
        <div class="role-info">
            <h4><i class="fas fa-info-circle"></i> Login Information</h4>
            <ul>
                <li><i class="fas fa-user-graduate student-icon"></i> <strong>Students:</strong> Use your student email (@student.edu.my)</li>
                <li><i class="fas fa-chalkboard-teacher lecturer-icon"></i> <strong>Supervisors:</strong> Use your staff email (@staff.edu.my)</li>
                <li><i class="fas fa-user-shield coordinator-icon"></i> <strong>Coordinators:</strong> Use assigned coordinator account</li>
            </ul>
        </div>
    </div>
</div>

<footer>
    &copy; <?php echo date('Y'); ?> FYP Management System. All rights reserved.
</footer>

<script>
// Toggle password visibility
const togglePassword = document.querySelector('#togglePassword');
const passwordInput = document.querySelector('#password');

togglePassword.addEventListener('click', function() {
    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
    passwordInput.setAttribute('type', type);
    this.classList.toggle('fa-eye');
    this.classList.toggle('fa-eye-slash');
});
</script>

</body>
</html>