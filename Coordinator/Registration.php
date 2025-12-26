<?php
session_start();
include("../db_connect.php");

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $email = trim($_POST['email']);
    $studfullid = trim($_POST['studfullid']);
    $studname = trim($_POST['studname']);
    
    // Generate student ID from full ID (e.g., TP055012 -> TP012)
    $studid = preg_replace('/^([A-Za-z]+)\d*(\d{3})$/', '$1$2', $studfullid);
    
    // Validate email ends with edu.my
    if (!preg_match('/\.edu\.my$/i', $email)) {
        $message = "Only student emails ending with <strong>.edu.my</strong> are allowed!";
        $message_type = 'error';
    }
    // Check if email already has pending registration
    else {
        $stmt = $conn->prepare("SELECT id FROM pending_registration WHERE email = ? AND status = 'pending'");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $message = "A registration with this email is already pending approval.";
            $message_type = 'error';
        } else {
            // Check if email exists in user table
            $stmt2 = $conn->prepare("SELECT fyp_userid FROM user WHERE fyp_username = ?");
            $stmt2->bind_param("s", $email);
            $stmt2->execute();
            $result2 = $stmt2->get_result();
            
            if ($result2->num_rows > 0) {
                $message = "An account with this email already exists.";
                $message_type = 'error';
            } else {
                // Check if student ID already exists
                $stmt3 = $conn->prepare("SELECT fyp_studid FROM student WHERE fyp_studfullid = ?");
                $stmt3->bind_param("s", $studfullid);
                $stmt3->execute();
                $result3 = $stmt3->get_result();
                
                if ($result3->num_rows > 0) {
                    $message = "A student with this ID already exists.";
                    $message_type = 'error';
                } else {
                    // Insert pending registration (no password - coordinator will generate)
                    $stmt4 = $conn->prepare("INSERT INTO pending_registration (email, studid, studfullid, studname, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
                    $stmt4->bind_param("ssss", $email, $studid, $studfullid, $studname);
                    
                    if ($stmt4->execute()) {
                        $message = "Registration submitted successfully!<br><br>
                                   <div style='background:rgba(59,130,246,0.1);padding:15px;border-radius:8px;border:1px solid rgba(59,130,246,0.3);'>
                                   <strong>What's next?</strong><br>
                                   1. Wait for coordinator approval<br>
                                   2. You will receive login credentials once approved<br>
                                   3. Login and reset your password from Profile
                                   </div>";
                        $message_type = 'success';
                    } else {
                        $message = "Error submitting registration. Please try again.";
                        $message_type = 'error';
                    }
                    $stmt4->close();
                }
                $stmt3->close();
            }
            $stmt2->close();
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Registration - FYP Portal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: linear-gradient(135deg, #0f0f1a 0%, #1a1a2e 50%, #16213e 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        
        .register-container { width: 100%; max-width: 480px; }
        
        .register-card { background: rgba(26, 26, 46, 0.9); border-radius: 24px; border: 1px solid rgba(139, 92, 246, 0.2); overflow: hidden; box-shadow: 0 25px 50px rgba(0,0,0,0.5); }
        
        .register-header { background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 50%, #3b82f6 100%); padding: 40px 30px; text-align: center; }
        .register-header h1 { color: white; font-size: 1.8rem; margin-bottom: 10px; }
        .register-header p { color: rgba(255,255,255,0.8); font-size: 0.95rem; }
        
        .register-body { padding: 40px 30px; }
        
        .form-group { margin-bottom: 24px; }
        .form-group label { display: block; color: #a78bfa; font-size: 0.9rem; margin-bottom: 8px; font-weight: 500; }
        .form-control { width: 100%; padding: 14px 16px; background: rgba(15, 15, 26, 0.6); border: 1px solid rgba(139, 92, 246, 0.2); border-radius: 12px; color: #fff; font-size: 1rem; transition: all 0.3s; }
        .form-control:focus { outline: none; border-color: #8b5cf6; box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1); }
        .form-control::placeholder { color: #64748b; }
        .form-control:read-only { background: rgba(15, 15, 26, 0.3); color: #94a3b8; }
        
        .input-icon { position: relative; }
        .input-icon i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #64748b; }
        .input-icon input { padding-left: 45px; }
        
        .email-hint { font-size: 0.8rem; color: #64748b; margin-top: 5px; }
        .email-hint i { color: #fb923c; }
        
        .btn { width: 100%; padding: 16px; border: none; border-radius: 12px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .btn-primary { background: linear-gradient(135deg, #8b5cf6, #6366f1); color: white; }
        .btn-primary:hover { box-shadow: 0 10px 30px rgba(139, 92, 246, 0.4); transform: translateY(-2px); }
        
        .alert { padding: 15px 20px; border-radius: 12px; margin-bottom: 20px; }
        .alert-success { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); color: #34d399; }
        .alert-error { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #f87171; }
        
        .login-link { text-align: center; margin-top: 25px; color: #94a3b8; }
        .login-link a { color: #a78bfa; text-decoration: none; font-weight: 600; }
        .login-link a:hover { text-decoration: underline; }
        
        .info-box { background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 12px; padding: 15px; margin-bottom: 25px; }
        .info-box h4 { color: #60a5fa; margin-bottom: 8px; font-size: 0.95rem; }
        .info-box p { color: #94a3b8; font-size: 0.85rem; line-height: 1.5; margin: 0; }
    </style>
</head>
<body>

<div class="register-container">
    <div class="register-card">
        <div class="register-header">
            <h1><i class="fas fa-user-graduate"></i> Student Registration</h1>
            <p>Request access to FYP Portal</p>
        </div>
        
        <div class="register-body">
            <?php if ($message): ?>
                <div class="alert alert-<?= $message_type; ?>">
                    <?= $message; ?>
                </div>
            <?php endif; ?>
            
            <div class="info-box">
                <h4><i class="fas fa-info-circle"></i> How it works</h4>
                <p>1. Submit your registration request<br>
                   2. Coordinator will review and approve<br>
                   3. You'll receive login credentials<br>
                   4. Login and update your profile</p>
            </div>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label>Student Email <span style="color:#f87171;">*</span></label>
                    <div class="input-icon">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" class="form-control" placeholder="yourname@student.edu.my" required>
                    </div>
                    <p class="email-hint"><i class="fas fa-info-circle"></i> Only emails ending with <strong>.edu.my</strong> are accepted</p>
                </div>
                
                <div class="form-group">
                    <label>Full Student ID <span style="color:#f87171;">*</span></label>
                    <div class="input-icon">
                        <i class="fas fa-id-badge"></i>
                        <input type="text" name="studfullid" class="form-control" placeholder="e.g. TP055012" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Full Name <span style="color:#f87171;">*</span></label>
                    <div class="input-icon">
                        <i class="fas fa-user"></i>
                        <input type="text" name="studname" class="form-control" placeholder="Enter your full name" required>
                    </div>
                </div>
                
                <button type="submit" name="register" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Submit Registration Request
                </button>
            </form>
            
            <p class="login-link">Already have an account? <a href="Login.php">Login here</a></p>
        </div>
    </div>
</div>

</body>
</html>