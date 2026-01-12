<?php
// ----------------------------------------------------
// 第一部分：PHP 逻辑处理 (保留原 Registration.php 的逻辑)
// ----------------------------------------------------
session_start();

// 【注意】路径改为当前目录，适配你的旧框架结构
include("connect.php");

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $email = trim($_POST['email']);
    $studfullid = trim($_POST['studfullid']);
    $studname = trim($_POST['studname']);
    
    // 逻辑：从完整ID生成简短ID (例如: TP055012 -> TP012)
    $studid = preg_replace('/^([A-Za-z]+)\d*(\d{3})$/', '$1$2', $studfullid);
    
    // 1. 验证邮箱必须以 .edu.my 结尾
    if (!preg_match('/\.edu\.my$/i', $email)) {
        $message = "Only student emails ending with <strong>.edu.my</strong> are allowed!";
        $message_type = 'error';
    }
    // 2. 检查 pending 表是否已有申请
    else {
        // 注意：这里假设你的表名是 pending_registration (小写)
        $stmt = $conn->prepare("SELECT id FROM pending_registration WHERE email = ? AND status = 'pending'");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $message = "该邮箱已经提交过申请，正在等待审批。";
            $message_type = 'error';
        } else {
            // 3. 检查 user 表是否已存在账户
            $stmt2 = $conn->prepare("SELECT fyp_userid FROM user WHERE fyp_username = ?");
            $stmt2->bind_param("s", $email);
            $stmt2->execute();
            $result2 = $stmt2->get_result();
            
            if ($result2->num_rows > 0) {
                $message = "该邮箱已经注册过账户，请直接登录。";
                $message_type = 'error';
            } else {
                // 4. 检查 student 表是否已有该学号
                $stmt3 = $conn->prepare("SELECT fyp_studid FROM student WHERE fyp_studfullid = ?");
                $stmt3->bind_param("s", $studfullid);
                $stmt3->execute();
                $result3 = $stmt3->get_result();
                
                if ($result3->num_rows > 0) {
                    $message = "该学号 (Student ID) 已经存在。";
                    $message_type = 'error';
                } else {
                    // 5. 插入到 pending_registration 表 (Coordinator 审批后生成密码)
                    $stmt4 = $conn->prepare("INSERT INTO pending_registration (email, studid, studfullid, studname, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
                    $stmt4->bind_param("ssss", $email, $studid, $studfullid, $studname);
                    
                    if ($stmt4->execute()) {
                        // 成功提示信息
                        $message = "申请提交成功！<br>
                                   请等待 Coordinator 审批。<br>
                                   审批通过后，您将收到登录凭证。";
                        $message_type = 'success';
                    } else {
                        $message = "提交失败，请重试或联系管理员。";
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration - FYP System</title>
    <link rel="icon" type="image/png" sizes="42x42" href="image/user.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap');
        :root {
            --primary-color: #0056b3;
            --primary-hover: #004494;
            --secondary-color: #f4f4f9;
            --text-color: #333;
            --border-color: #ddd;
            --error-color: #d93025;
            --success-color: #155724;
            --success-bg: #d4edda;
            --gradient-start: #eef2f7;
            --gradient-end: #ffffff;
        }
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: var(--text-color);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
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
            color: var(--primary-color);
        }
        .topbar-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .flag-icon { width: 24px; height: auto; }
        .icon-circle {
            display: flex; align-items: center; justify-content: center;
            width: 28px; height: 28px; border-radius: 50%;
            background-color: var(--secondary-color); color: var(--primary-color);
            text-decoration: none; font-weight: 600; transition: background-color 0.3s;
        }
        .icon-circle:hover { background-color: #e0e0e0; }
        .main-wrapper {
            flex: 1; display: flex; justify-content: center; align-items: center; padding: 20px;
        }
        .form-container {
            background: #fff; padding: 40px; border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            width: 100%; max-width: 480px; text-align: center;
        }
        h1 { font-size: 28px; margin-bottom: 10px; color: var(--text-color); }
        .intro-text { color: #666; margin-bottom: 30px; }
        
        .input-group { margin-bottom: 20px; text-align: left; }
        .input-group label { display: block; font-weight: 500; margin-bottom: 8px; font-size: 14px; }
        .input-group input {
            width: 100%; box-sizing: border-box; padding: 12px 15px;
            border: 1px solid var(--border-color); border-radius: 8px;
            font-size: 16px; transition: border-color 0.3s;
        }
        .input-group input:focus {
            outline: none; border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 86, 179, 0.2);
        }
        
        .btn-submit {
            width: 100%; padding: 15px; border: none; border-radius: 8px;
            background-color: var(--primary-color); color: white;
            font-size: 16px; font-weight: 600; cursor: pointer;
            transition: background-color 0.3s, transform 0.2s; margin-top: 10px;
        }
        .btn-submit:hover { background-color: var(--primary-hover); transform: translateY(-2px); }
        
        .form-links { margin-top: 25px; font-size: 14px; }
        .form-links a { color: var(--primary-color); text-decoration: none; font-weight: 500; }
        .form-links a:hover { text-decoration: underline; }

        /* 消息提示框样式 */
        .error-message {
            background-color: #f8d7da; color: #721c24;
            border: 1px solid #f5c6cb; padding: 12px;
            border-radius: 8px; margin-bottom: 20px; font-size: 14px;
        }
        .success-message {
            background-color: var(--success-bg); color: var(--success-color);
            border: 1px solid #c3e6cb; padding: 15px;
            border-radius: 8px; margin-bottom: 20px; font-size: 14px; text-align: left;
        }
        
        /* 额外的信息提示框 */
        .info-box {
            background-color: #eef2f7; border: 1px solid #dfe6ed;
            padding: 15px; border-radius: 8px; margin-bottom: 25px;
            text-align: left; font-size: 13px; color: #555;
        }
        .info-box strong { color: var(--primary-color); display: block; margin-bottom: 5px; }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="logo">FYP Management</div>
        <div class="topbar-right">
            <img src="image/Malaysia.png" alt="Flag icon" class="flag-icon" onerror="this.style.display='none'">
            <a href="#" class="icon-circle" title="Help">?</a>
        </div>
    </header>

    <div class="main-wrapper">
        <main class="content">
            <div class="form-container">
                <h1>Student Registration</h1>
                <p class="intro-text">Request access to FYP Portal</p>
                
                <?php if ($message): ?>
                    <div class="<?php echo ($message_type === 'success') ? 'success-message' : 'error-message'; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <div class="info-box">
                    <strong><i class="fas fa-info-circle"></i> How it works</strong>
                    1. Submit your details below.<br>
                    2. Coordinator will approve your request.<br>
                    3. You will receive login credentials via email.
                </div>

                <form method="POST" action="">
                    
                    <div class="input-group">
                        <label for="email">Student Email <span style="color:red">*</span></label>
                        <input type="email" id="email" name="email" 
                               placeholder="yourname@student.edu.my" required>
                        <small style="color:#666; font-size:12px; margin-top:5px; display:block;">
                            Must end with <strong>.edu.my</strong>
                        </small>
                    </div>

                    <div class="input-group">
                        <label for="studfullid">Full Student ID <span style="color:red">*</span></label>
                        <input type="text" id="studfullid" name="studfullid" 
                               placeholder="e.g. TP055012" required>
                    </div>

                    <div class="input-group">
                        <label for="studname">Full Name <span style="color:red">*</span></label>
                        <input type="text" id="studname" name="studname" 
                               placeholder="Enter your full name" required>
                    </div>

                    <button type="submit" name="register" class="btn-submit">
                        SUBMIT REQUEST
                    </button>

                    <div class="form-links">
                        <p>Already have an account? <a href="Login.php">Login here</a></p>
                    </div>
                </form>

            </div>
        </main>
    </div>
</body>
</html>