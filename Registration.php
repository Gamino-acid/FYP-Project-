<?php
// ----------------------------------------------------
// Student Registration Page - With First & Last Name
// ----------------------------------------------------
session_start();
include("connect.php");

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    
    // Validate inputs
    if (empty($first_name) || empty($last_name)) {
        $message = "Please enter your first name and last name.";
        $message_type = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $message_type = 'error';
    } else {
        // Check if email already exists in pending_registration
        $stmt = $conn->prepare("SELECT id FROM pending_registration WHERE email = ? AND status = 'pending'");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $message = "This email has already submitted a registration request.<br>Please wait for Coordinator approval.";
            $message_type = 'error';
        } else {
            $stmt->close();
            
            // Check if email already exists in user table (Already registered)
            $stmt2 = $conn->prepare("SELECT fyp_userid FROM user WHERE fyp_username = ?");
            $stmt2->bind_param("s", $email);
            $stmt2->execute();
            $result2 = $stmt2->get_result();
            
            if ($result2->num_rows > 0) {
                $message = "This email is already registered. Please <a href='Login.php'>login</a> instead.";
                $message_type = 'error';
            } else {
                // Insert into pending_registration table with first and last name
                // This matches the columns in your database screenshot
                $stmt3 = $conn->prepare("INSERT INTO pending_registration (first_name, last_name, email, status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
                $stmt3->bind_param("sss", $first_name, $last_name, $email);
                
                if ($stmt3->execute()) {
                    $full_name = htmlspecialchars($first_name . ' ' . $last_name);
                    $message = "✅ Registration request submitted successfully!<br><br>
                               <i class='fas fa-envelope' style='font-size:30px; color:#28a745;'></i><br><br>
                               <strong>Name:</strong> {$full_name}<br>
                               <strong>Email:</strong> {$email}<br><br>
                               Please wait for Coordinator approval.<br>
                               Your login password will be sent to this email.";
                    $message_type = 'success';
                } else {
                    $message = "Registration failed. Please try again.";
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
    <title>Student Registration - FYP System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap');
        :root { --primary: #0056b3; --primary-hover: #004494; }
        body { font-family: 'Poppins', sans-serif; margin: 0; background: linear-gradient(135deg, #eef2f7, #fff); min-height: 100vh; display: flex; flex-direction: column; }
        .topbar { padding: 15px 40px; background: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .logo { font-size: 24px; font-weight: 600; color: var(--primary); }
        .main-wrapper { flex: 1; display: flex; justify-content: center; align-items: center; padding: 20px; }
        .form-container { background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); width: 100%; max-width: 480px; text-align: center; }
        h1 { font-size: 26px; margin-bottom: 10px; color: #333; }
        .intro-text { color: #666; margin-bottom: 25px; font-size: 14px; }
        .input-group { margin-bottom: 20px; text-align: left; }
        .input-group label { display: block; font-weight: 500; margin-bottom: 8px; font-size: 14px; color: #333; }
        .input-group input { width: 100%; box-sizing: border-box; padding: 14px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 16px; font-family: inherit; transition: all 0.2s; }
        .input-group input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(0,86,179,0.2); }
        .name-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .btn-submit { width: 100%; padding: 15px; border: none; border-radius: 8px; background: var(--primary); color: #fff; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .btn-submit:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,86,179,0.3); }
        .form-links { margin-top: 20px; font-size: 14px; color: #666; }
        .form-links a { color: var(--primary); text-decoration: none; font-weight: 500; }
        .form-links a:hover { text-decoration: underline; }
        .error-message { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .success-message { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 20px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; line-height: 1.6; }
        .info-box { background: #e7f3ff; border: 1px solid #b6d4fe; padding: 15px; border-radius: 8px; margin-bottom: 25px; text-align: left; font-size: 13px; color: #084298; }
        .info-box strong { display: block; margin-bottom: 8px; font-size: 14px; }
        .info-box ol { margin: 0; padding-left: 18px; }
        .info-box li { margin-bottom: 5px; }
        small { color: #666; font-size: 12px; margin-top: 8px; display: block; }
        .required { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="logo"><i class="fas fa-graduation-cap"></i> FYP Management System</div>
    </header>

    <div class="main-wrapper">
        <div class="form-container">
            <h1><i class="fas fa-user-plus"></i> Student Registration</h1>
            <p class="intro-text">Request access to the FYP Portal</p>
            
            <?php if ($message): ?>
                <div class="<?php echo ($message_type === 'success') ? 'success-message' : 'error-message'; ?>">
                    <?php echo $message; ?>
                </div>
                <?php if ($message_type === 'success'): ?>
                    <a href="Login.php" class="btn-submit" style="display: block; text-decoration: none; margin-top: 15px;">
                        <i class="fas fa-arrow-left"></i> Back to Login
                    </a>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($message_type !== 'success'): ?>
            <div class="info-box">
                <strong><i class="fas fa-info-circle"></i> How Registration Works</strong>
                <ol>
                    <li>Fill in your name and email below</li>
                    <li>Coordinator will review your request</li>
                    <li>Once approved, login credentials will be sent to your email</li>
                    <li>Use the credentials to access the FYP Portal</li>
                </ol>
            </div>

            <form method="POST" autocomplete="off">
                <div class="name-row">
                    <div class="input-group">
                        <label for="first_name">First Name <span class="required">*</span></label>
                        <input type="text" id="first_name" name="first_name" placeholder="e.g., John" required
                               value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                    </div>
                    <div class="input-group">
                        <label for="last_name">Last Name <span class="required">*</span></label>
                        <input type="text" id="last_name" name="last_name" placeholder="e.g., Doe" required
                               value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                    </div>
                </div>

                <div class="input-group">
                    <label for="email">Email Address <span class="required">*</span></label>
                    <input type="email" id="email" name="email" placeholder="yourname@gmail.com" required
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    <small><i class="fas fa-envelope"></i> Enter your active email address to receive login credentials</small>
                </div>

                <button type="submit" name="register" class="btn-submit">
                    <i class="fas fa-paper-plane"></i> SUBMIT REGISTRATION REQUEST
                </button>

                <div class="form-links">
                    <p>Already have an account? <a href="Login.php"><i class="fas fa-sign-in-alt"></i> Login here</a></p>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>