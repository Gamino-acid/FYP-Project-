<?php
session_start();
require_once '../db_connect.php';
date_default_timezone_set('Asia/Kuala_Lumpur');

$view = 'login';
$login_err = '';
$reset_msg = '';
$reset_err = '';

if (isset($_GET['view'])) {
    $allowed_views = ['login', 'forgot', 'reset'];
    if (in_array($_GET['view'], $allowed_views)) {
        $view = $_GET['view'];
    }
}

function checkToken($conn, $token_hash) {
    $current_time = date("Y-m-d H:i:s");
    $sql = "SELECT fyp_userid FROM user WHERE reset_token_hash = ? AND reset_token_expires_at > ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ss", $token_hash, $current_time);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 1) {
            $stmt->close();
            return true;
        }
        $stmt->close();
    }
    return false;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (isset($_POST['login'])) {
        $view = 'login';
        $username = trim($_POST['username']);
        $password = $_POST['password']; 
        
        if (empty($username) || empty($password)) {
            $login_err = "Please enter both username and password.";
        } else {
            $sql = "SELECT fyp_userid, fyp_username, fyp_passwordhash, fyp_usertype FROM user WHERE fyp_username = ? LIMIT 1";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows == 1) {
                    $user = $result->fetch_assoc();
                    if (password_verify($password, $user['fyp_passwordhash']) || $password === $user['fyp_passwordhash']) {
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user['fyp_userid'];
                        $_SESSION['username'] = $user['fyp_username'];
                        $_SESSION['user_role'] = strtolower(trim($user['fyp_usertype']));
                        
                        $user_type = strtolower(trim($user['fyp_usertype']));
                        
                        if ($user_type === 'student') {
                            // check student profile
                            $chk = $conn->prepare("SELECT fyp_studid FROM student WHERE fyp_userid = ?");
                            $chk->bind_param("i", $user['fyp_userid']);
                            $chk->execute();
                            $chk->store_result();

                            if ($chk->num_rows === 0) {
                                // student exists in USER but not registered
                                header("Location: ../Registration.php");
                                exit;
                            }

                            header("Location: ../Student_mainpage.php");
                            exit;
                        } else if ($user_type === 'coordinator') {
                            // COORDINATORss goes to Coordinator_mainpage.php (same folder)
                            header("Location: Coordinator_mainpage.php");
                            exit;
                        } else if ($user_type === 'lecturer') {
                            // LECTURER goes to Supervisor_mainpage.php (parent folder)
                            header("Location: ../Supervisor_mainpage.php");
                            exit;
                        } else {
                            $login_err = "Login failed: Unknown user type ($user_type).";
                        }
                    } else {
                        $login_err = "Invalid password.";
                    }
                } else {
                    $login_err = "No account found.";
                }
                $stmt->close();
            }
        }

    } elseif (isset($_POST['reset_password'])) {
        $view = 'forgot';
        $email = trim($_POST['email']);
        
        if (empty($email)) {
            $reset_err = "Please enter your email.";
        } else {
            $sql = "SELECT fyp_userid FROM user WHERE fyp_email = ? LIMIT 1";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows == 1) {
                    
                    $token = bin2hex(random_bytes(16));
                    $token_hash = hash("sha256", $token);
                    
                    $expiry = date("Y-m-d H:i:s", time() + (60 * 60 * 24)); 
                    
                    $update_sql = "UPDATE user SET reset_token_hash = ?, reset_token_expires_at = ? WHERE fyp_email = ?";
                    if ($upd = $conn->prepare($update_sql)) {
                        $upd->bind_param("sss", $token_hash, $expiry, $email);
                        $upd->execute();
                        
                        $link = "http://$_SERVER[HTTP_HOST]$_SERVER[PHP_SELF]?token=$token";
                        
                        $subject = "Reset Password";
                        $msg = "Click: $link";
                        $headers = "From: no-reply@fyp.com";
                        
                        if (@mail($email, $subject, $msg, $headers)) {
                            $reset_msg = "Check your email for the link.";
                            $view = 'login';
                        } else {
                            $reset_msg = "<b>LOCALHOST SUCCESS!</b><br>Click this link to reset:<br><a href='$link' style='color:#0056b3;font-weight:bold;text-decoration:underline;'>RESET PASSWORD LINK</a>";
                            $view = 'login';
                        }
                    }
                } else {
                    $reset_err = "Email not found.";
                }
            }
        }

    } elseif (isset($_POST['save_new_password'])) {
        $view = 'reset';
        $token = $_POST['token'];
        $p1 = $_POST['new_password'];
        $p2 = $_POST['confirm_password'];
        
        if ($p1 !== $p2) {
            $reset_err = "Passwords do not match.";
        } else {
            $token_hash = hash("sha256", $token);
            
            if (checkToken($conn, $token_hash)) {
                $sql = "SELECT fyp_userid FROM user WHERE reset_token_hash = ?";
                $uid = null;
                if ($stmt = $conn->prepare($sql)) {
                    $stmt->bind_param("s", $token_hash);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    $row = $res->fetch_assoc();
                    $uid = $row['fyp_userid'];
                    $stmt->close();
                }

                if ($uid) {
                    $new_hash = password_hash($p1, PASSWORD_DEFAULT);
                    $upd = $conn->prepare("UPDATE user SET fyp_passwordhash = ?, reset_token_hash = NULL, reset_token_expires_at = NULL WHERE fyp_userid = ?");
                    $upd->bind_param("si", $new_hash, $uid);
                    $upd->execute();
                    
                    $reset_msg = "Password changed! Please login.";
                    $view = 'login';
                }
            } else {
                $raw_check_sql = "SELECT fyp_userid FROM user WHERE reset_token_hash = ?"; 
                if ($stmt = $conn->prepare($raw_check_sql)) {
                    $stmt->bind_param("s", $token);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    if ($res->num_rows === 1) {
                        $row = $res->fetch_assoc();
                        $uid = $row['fyp_userid'];
                        
                        $new_hash = password_hash($p1, PASSWORD_DEFAULT);
                        $upd = $conn->prepare("UPDATE user SET fyp_passwordhash = ?, reset_token_hash = NULL, reset_token_expires_at = NULL WHERE fyp_userid = ?");
                        $upd->bind_param("si", $new_hash, $uid);
                        $upd->execute();
                        $reset_msg = "Password changed! Please login.";
                        $view = 'login';
                    } else {
                        $reset_err = "Invalid or expired token.";
                    }
                }
            }
        }
    }
}

if (isset($_GET['token'])) {
    $raw_token = $_GET['token'];
    $token_hash = hash("sha256", $raw_token);
    
    if (checkToken($conn, $token_hash)) {
        $view = 'reset';
    } else {
        $sql = "SELECT fyp_userid FROM user WHERE reset_token_hash = ?"; 
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $raw_token);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows === 1) {
                $view = 'reset';
            } else {
                $reset_err = "Token expired or invalid.";
                $view = 'login';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FYP Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/Login.css?v=<?php echo time(); ?>">
    <style>
        .alert-success {
            background-color: #d4edda; color: #155724; padding: 15px;
            border: 1px solid #c3e6cb; border-radius: 4px; margin-bottom: 20px; 
            text-align: center;
        }
        .alert-error {
            background-color: #fce4e4; color: #cc0033; padding: 10px;
            border: 1px solid #fcc2c3; border-radius: 4px; margin-bottom: 20px; 
            text-align: center;
        }
        form { animation: fadeIn 0.5s; }
        @keyframes fadeIn { from { opacity:0; } to { opacity:1; } }
    </style>
</head>
<body>

<div class="login-wrapper">

    <?php if ($view === 'login'): ?>
    <form id="loginForm" method="post" action="">
        <h2 style="margin-top:0;">FYP Portal</h2>

        <?php if($login_err) echo "<div class='alert-error'>$login_err</div>"; ?>
        <?php if($reset_msg) echo "<div class='alert-success'>$reset_msg</div>"; ?>
        <?php if($reset_err) echo "<div class='alert-error'>$reset_err</div>"; ?>

        <div class="input-group">
            <label>Username / Student ID</label>
            <input type="text" name="username" placeholder="Enter Username" required>
        </div>

        <div class="input-group">
            <label>Password</label>
            <div class="password-wrapper" style="width: 100%;">
                <input type="password" id="password" name="password" placeholder="Enter password" required>
                <i class="fa fa-eye-slash toggle-password" onclick="togglePass()"></i>
            </div>
        </div>

        <button type="submit" name="login" class="login-button">Log In</button>
        <div class="form-links">
            <a href="Login.php?view=forgot">Forgot Password?</a>
        </div>
    </form>
    <?php endif; ?>

    <?php if ($view === 'forgot'): ?>
    <form id="forgotForm" method="post" action="">
        <h2 style="margin-top:0;">Reset Password</h2>
        <p style="text-align:center;color:#666;">Enter your email to reset password.</p>

        <?php if($reset_err) echo "<div class='alert-error'>$reset_err</div>"; ?>

        <div class="input-group">
            <label>Student Email</label>
            <input type="email" name="email" placeholder="Enter email" required>
        </div>

        <button type="submit" name="reset_password" class="login-button">Send Reset Link</button>
        <div class="form-links">
            <a href="Login.php?view=login">Back to Login</a>
        </div>
    </form>
    <?php endif; ?>

    <?php if ($view === 'reset'): ?>
    <form id="resetForm" method="post" action="">
        <h2 style="margin-top:0;">New Password</h2>

        <?php if($reset_err) echo "<div class='alert-error'>$reset_err</div>"; ?>
        
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token'] ?? $_POST['token'] ?? ''); ?>">

        <div class="input-group">
            <label>New Password</label>
            <input type="password" name="new_password" required>
        </div>
        <div class="input-group">
            <label>Confirm Password</label>
            <input type="password" name="confirm_password" required>
        </div>

        <button type="submit" name="save_new_password" class="login-button">Save Password</button>
    </form>
    <?php endif; ?>
<hr>
<a href="google_login.php" class="btn btn-danger w-100">
    Login with Google
</a>
<hr>
<p style="text-align:center; font-size:14px;">
    New student?
    <a href="Registration.php" style="color:#0056b3; font-weight:600;">
        Register for Final Year Project
    </a>
</p>
</div>

<script>
function togglePass() {
    var p = document.getElementById('password');
    var i = document.querySelector('.toggle-password');
    if (p.type === "password") {
        p.type = "text";
        i.classList.remove('fa-eye-slash');
        i.classList.add('fa-eye');
    } else {
        p.type = "password";
        i.classList.remove('fa-eye');
        i.classList.add('fa-eye-slash');
    }
}
</script>

</body>
</html>