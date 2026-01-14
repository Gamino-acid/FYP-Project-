<?php
// ----------------------------------------------------
// Login Page - FYP Management System
// ----------------------------------------------------
include("connect.php"); 

$username = $password = "";
$login_err = "";

if ($_SERVER["REQUEST_METHOD"] == 'POST') {

    if (empty(trim($_POST["username"])) || empty(trim($_POST["password"]))) {
        $login_err = "Please enter username and password.";
    } else {
        $username = trim($_POST['username']);
        $password = $_POST['password']; 
    }

    if (empty($login_err)) {
        
        $sql = "SELECT fyp_userid, fyp_passwordhash, fyp_usertype FROM `user` WHERE fyp_username = ? LIMIT 1";
        
        if (isset($conn) && $stmt = $conn->prepare($sql)) {
            
            $stmt->bind_param("s", $param_username);
            $param_username = $username;
            
            if ($stmt->execute()) {
                
                $result = $stmt->get_result();
                
                if ($result->num_rows == 1) {
                    $user = $result->fetch_assoc();
                    
                    if ($password === $user['fyp_passwordhash']) { 
                        
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
        } else {
            $login_err = "System error. Please contact administrator.";
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
    <title>Login - FYP System</title>
    <link rel="icon" type="image/png" sizes="42x42" href="image/user.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap');
        :root {
            --primary: #0056b3;
            --primary-hover: #004494;
        }
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            background: linear-gradient(135deg, #eef2f7, #ffffff);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 40px;
            background-color: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .logo {
            font-size: 24px;
            font-weight: 600;
            color: var(--primary);
        }
        .main-wrapper {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .form-container {
            background: #fff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 420px;
            text-align: center;
        }
        h1 {
            font-size: 28px;
            margin-bottom: 10px;
            color: #333;
        }
        .intro-text {
            color: #666;
            margin-bottom: 30px;
        }
        .input-group {
            margin-bottom: 20px;
            text-align: left;
        }
        .input-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 14px;
        }
        .input-group input {
            width: 100%;
            box-sizing: border-box;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            font-family: inherit;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        .input-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0, 86, 179, 0.2);
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
        }
        .toggle-password:hover {
            color: #666;
        }
        .btn-submit {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 8px;
            background-color: var(--primary);
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.2s;
            margin-top: 10px;
        }
        .btn-submit:hover {
            background-color: var(--primary-hover);
            transform: translateY(-2px);
        }
        .form-links {
            margin-top: 20px;
            font-size: 14px;
        }
        .form-links a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }
        .form-links a:hover {
            text-decoration: underline;
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .divider {
            display: flex;
            align-items: center;
            margin: 25px 0;
        }
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #ddd;
        }
        .divider span {
            padding: 0 15px;
            color: #999;
            font-size: 13px;
        }
        .btn-register {
            width: 100%;
            padding: 14px;
            border: 2px solid var(--primary);
            border-radius: 8px;
            background-color: transparent;
            color: var(--primary);
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: block;
            box-sizing: border-box;
        }
        .btn-register:hover {
            background-color: var(--primary);
            color: white;
        }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="logo"><i class="fas fa-graduation-cap"></i> FYP Management</div>
    </header>

    <div class="main-wrapper">
        <div class="form-container">
            <h1>Welcome Back</h1>
            <p class="intro-text">Please login to continue</p>
            
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                
                <?php if (!empty($login_err)): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($login_err); ?>
                    </div>
                <?php endif; ?>

                <div class="input-group">
                    <label for="username">Email</label>
                    <input type="text" id="username" name="username" 
                           value="<?php echo htmlspecialchars($username); ?>" 
                           placeholder="Enter your email" required>
                </div>

                <div class="input-group">
                    <label for="password">Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" 
                               placeholder="Enter your password" required>
                        <i class="fa fa-eye-slash toggle-password" id="togglePassword"></i>
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-sign-in-alt"></i> LOGIN
                </button>

                <div class="form-links">
                    <a href="Recoverypage.php">Forgot Password?</a>
                </div>
            </form>

            <div class="divider">
                <span>New Student?</span>
            </div>
            
            <a href="Registration.php" class="btn-register">
                <i class="fas fa-user-plus"></i> Register Here
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