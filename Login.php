<?php
ob_start();
session_start();
require_once 'db_connect.php';
date_default_timezone_set('Asia/Kuala_Lumpur');

$view = 'login';
$login_err = '';
$reset_msg = ''; 
$reset_err = '';

// --- VIEW HANDLING ---
if (isset($_GET['view']) && in_array($_GET['view'], ['login', 'forgot', 'reset'])) {
    $view = $_GET['view'];
}

// --- HELPER FUNCTION ---
function checkToken($conn, $token_hash) {
    $now = date("Y-m-d H:i:s");
    $sql = "SELECT fyp_userid FROM user WHERE reset_token_hash = ? AND reset_token_expires_at > ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ss", $token_hash, $now);
        $stmt->execute();
        $stmt->store_result();
        return $stmt->num_rows === 1;
    }
    return false;
}

// --- MAIN POST LOGIC ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 1. LOGIN
    if (isset($_POST['login'])) {
        $username = trim($_POST['username']);
        $password = $_POST['password'];

        if (empty($username) || empty($password)) {
            $login_err = "Please enter both username and password.";
        } else {
            // Fetch User
            $stmt = $conn->prepare("SELECT fyp_userid, fyp_username, fyp_passwordhash, fyp_usertype FROM user WHERE fyp_username = ? LIMIT 1");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                // Verify Password
                if (password_verify($password, $row['fyp_passwordhash']) || $password === $row['fyp_passwordhash']) {
                    
                    // SET SESSION
                    session_regenerate_id(true);
                    $_SESSION['user_id']   = $row['fyp_userid'];
                    $_SESSION['username']  = $row['fyp_username'];
                    $_SESSION['user_role'] = strtolower(trim($row['fyp_usertype']));

                    $role = $_SESSION['user_role'];

                    if ($role === 'student') {
                        // CHECK IF REGISTERED IN 'STUDENT' TABLE
                        $chk = $conn->prepare("SELECT fyp_studid FROM student WHERE fyp_userid = ?");
                        $chk->bind_param("i", $row['fyp_userid']);
                        $chk->execute();
                        
                        if ($chk->get_result()->num_rows === 0) {
                            // User exists, but Student Profile missing -> Go to Registration
                            header("Location: Registration.php");
                            exit;
                        } else {
                            // Student Profile exists -> Go to Main Page
                            header("Location: Student_mainpage.php");
                            exit;
                        }
                    } elseif ($role === 'lecturer' || $role === 'coordinator') {
                        header("Location: Supervisor_mainpage.php");
                        exit;
                    } else {
                        $login_err = "Unknown user role: $role";
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

    // 2. FORGOT PASSWORD
    elseif (isset($_POST['reset_password'])) {
        $view = 'forgot';
        $email = trim($_POST['email']);
        
        $stmt = $conn->prepare("SELECT fyp_userid FROM user WHERE fyp_email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows == 1) {
            $token = bin2hex(random_bytes(16));
            $hash  = hash("sha256", $token);
            $exp   = date("Y-m-d H:i:s", time() + 86400);

            $upd = $conn->prepare("UPDATE user SET reset_token_hash = ?, reset_token_expires_at = ? WHERE fyp_email = ?");
            $upd->bind_param("sss", $hash, $exp, $email);
            $upd->execute();

            $link = "http://$_SERVER[HTTP_HOST]$_SERVER[PHP_SELF]?token=$token";
            $reset_msg = "<b>LOCALHOST LINK:</b><br><a href='$link' style='color:#0056b3;font-weight:bold;'>RESET LINK</a>";
            $view = 'login';
        } else {
            $reset_err = "Email not found.";
        }
    }

    // 3. SAVE NEW PASSWORD
    elseif (isset($_POST['save_new_password'])) {
        $view = 'reset';
        $token = $_POST['token'];
        $p1 = $_POST['new_password'];
        $p2 = $_POST['confirm_password'];

        if ($p1 !== $p2) {
            $reset_err = "Passwords do not match.";
        } else {
            $hash_token = hash("sha256", $token);
            if (checkToken($conn, $hash_token)) {
                // Get User ID
                $stmt = $conn->prepare("SELECT fyp_userid FROM user WHERE reset_token_hash = ?");
                $stmt->bind_param("s", $hash_token);
                $stmt->execute();
                $uid = $stmt->get_result()->fetch_assoc()['fyp_userid'];

                // Update Password
                $new_pwd_hash = password_hash($p1, PASSWORD_DEFAULT);
                $upd = $conn->prepare("UPDATE user SET fyp_passwordhash = ?, reset_token_hash = NULL, reset_token_expires_at = NULL WHERE fyp_userid = ?");
                $upd->bind_param("si", $new_pwd_hash, $uid);
                $upd->execute();

                $reset_msg = "Password changed! Please login.";
                $view = 'login';
            } else {
                $reset_err = "Invalid or expired token.";
            }
        }
    }
}

// --- HANDLE GET TOKEN (Link Clicked) ---
if (isset($_GET['token'])) {
    if (checkToken($conn, hash("sha256", $_GET['token']))) {
        $view = 'reset';
    } else {
        $reset_err = "Token expired or invalid.";
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
    <style>
        body { font-family: sans-serif; background: #f4f6f9; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-wrapper { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); width: 350px; }
        .input-group { margin-bottom: 15px; }
        .input-group label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 0.9em; }
        .input-group input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .login-button { width: 100%; padding: 10px; background: #0d6efd; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 1em; }
        .login-button:hover { background: #0b5ed7; }
        .btn-danger { background: #dc3545; color: white; text-decoration: none; padding: 10px; display: block; text-align: center; border-radius: 4px; }
        .btn-danger:hover { background: #bb2d3b; }
        .alert-success { background-color: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 15px; text-align: center; }
        .alert-error { background-color: #fce4e4; color: #cc0033; padding: 10px; border-radius: 4px; margin-bottom: 15px; text-align: center; }
        .form-links { text-align: right; margin-top: 10px; font-size: 0.85em; }
        .form-links a { color: #666; text-decoration: none; }
        .password-wrapper { position: relative; }
        .toggle-password { position: absolute; right: 10px; top: 12px; cursor: pointer; color: #888; }
        hr { margin: 20px 0; border: 0; border-top: 1px solid #eee; }
    </style>
</head>
<body>

<div class="login-wrapper">

    <!-- LOGIN FORM -->
    <?php if ($view === 'login'): ?>
    <form id="loginForm" method="post" action="">
        <h2 style="text-align:center; margin-top:0;">FYP Portal</h2>

        <?php if($login_err) echo "<div class='alert-error'>$login_err</div>"; ?>
        <?php if($reset_msg) echo "<div class='alert-success'>$reset_msg</div>"; ?>
        <?php if($reset_err) echo "<div class='alert-error'>$reset_err</div>"; ?>

        <div class="input-group">
            <label>Username / Student ID</label>
            <input type="text" name="username" placeholder="Enter Username" required>
        </div>

        <div class="input-group">
            <label>Password</label>
            <div class="password-wrapper">
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

    <!-- FORGOT PASSWORD FORM -->
    <?php if ($view === 'forgot'): ?>
    <form id="forgotForm" method="post" action="">
        <h2 style="text-align:center; margin-top:0;">Reset Password</h2>
        <p style="text-align:center;color:#666;font-size:0.9em;">Enter your email to receive a reset link.</p>

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

    <!-- RESET PASSWORD FORM -->
    <?php if ($view === 'reset'): ?>
    <form id="resetForm" method="post" action="">
        <h2 style="text-align:center; margin-top:0;">New Password</h2>

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

    <!-- EXTERNAL LINKS -->
    <hr>
    <a href="google_login.php" class="btn btn-danger w-100">
        <i class="fab fa-google me-2"></i> Login with Google
    </a>
    <hr>
    <p style="text-align:center; font-size:14px; margin-bottom:0;">
        New student?
        <a href="Registration.php" style="color:#0056b3; font-weight:600;">
            Register for FYP
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
<?php ob_end_flush(); ?>
