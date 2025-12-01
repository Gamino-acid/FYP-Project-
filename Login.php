<?php
// ----------------------------------------------------
// 第一部分：PHP 逻辑处理 (放在文件最顶部)
// ----------------------------------------------------

// 引入数据库连接
include("connect.php"); 

// 初始化变量
$username = $password = "";
$login_err = "";

// 处理表单提交
if ($_SERVER["REQUEST_METHOD"] == 'POST') {

    // 1. 检查输入是否为空
    if (empty(trim($_POST["username"])) || empty(trim($_POST["password"]))) {
        $login_err = "请输入用户名和密码。";
    } else {
        $username = trim($_POST['username']);
        $password = $_POST['password']; 
    }

    // 2. 验证凭证
    if (empty($login_err)) {
        
        // SQL 语句：查询用户ID和密码哈希
        $sql = "SELECT fyp_userid, fyp_passwordhash FROM USER WHERE fyp_username = ? LIMIT 1";
        
        // 【关键修复】这里使用 $conn (根据之前的调试结果)
        if (isset($conn) && $stmt = $conn->prepare($sql)) {
            
            // 绑定变量
            $stmt->bind_param("s", $param_username);
            $param_username = $username;
            
            // 执行查询
            if ($stmt->execute()) {
                
                $result = $stmt->get_result();
                
                if ($result->num_rows == 1) {
                    $user = $result->fetch_assoc();
                    
                    // 3. 密码验证：直接比较明文
                    // 如果一直登录失败，建议尝试: if ($password === trim($user['fyp_passwordhash']))
                    if ($password === $user['fyp_passwordhash']) { 
                        
                        // 验证通过，获取用户ID
                        $user_id = $user['fyp_userid'];

                        // 登录成功，跳转到主页
                        header("location: Coordinator_mainpage.php?auth_user_id=" . urlencode($user_id));
                        exit;
                        
                    } else {
                        $login_err = "密码不正确，请重试。";
                    }
                } else {
                    $login_err = "找不到该用户名的账户。";
                }
            } else {
                $login_err = "系统错误：无法执行查询。";
            }
            $stmt->close();
        } else {
            // 如果这里报错，说明 $conn 变量存在但 SQL 准备失败 (通常是表名 USER 写错或字段名拼写错误)
            $login_err = "系统错误：数据库查询准备失败。请检查表名和字段名。";
        }
    }
}

// 关闭连接
if (isset($conn)) {
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - System</title>
    <!-- 图标和样式库 -->
    <link rel="icon" type="image/png" sizes="42x42" href="image/ladybug.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap');
        :root {
            /* 蓝色主题配色 */
            --primary-color: #0056b3;
            --primary-hover: #004494;
            --secondary-color: #f4f4f9;
            --text-color: #333;
            --border-color: #ddd;
            --error-color: #d93025;
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
        .flag-icon {
            width: 24px;
            height: auto;
        }
        .icon-circle {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background-color: var(--secondary-color);
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            transition: background-color 0.3s;
        }
        .icon-circle:hover {
            background-color: #e0e0e0;
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
            max-width: 450px;
            text-align: center;
        }
        h1 {
            font-size: 28px;
            margin-bottom: 10px;
            color: var(--text-color);
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
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        .input-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 86, 179, 0.2);
        }
        .password-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        .password-wrapper input {
            padding-right: 40px;
        }
        .toggle-password {
            position: absolute;
            right: 15px;
            cursor: pointer;
            color: #999;
        }
        .btn-submit {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 8px;
            background-color: var(--primary-color);
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
            margin-top: 25px;
            font-size: 14px;
        }
        .form-links p {
            margin: 8px 0;
        }
        .form-links a, .privacy-policy a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        .form-links a:hover, .privacy-policy a:hover {
            text-decoration: underline;
        }
        .privacy-policy {
            margin-top: 20px;
            font-size: 12px;
            color: #888;
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
        }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="logo">System Login</div>
        <div class="topbar-right">
            <img src="image/Malaysia.png" alt="Flag icon" class="flag-icon" onerror="this.style.display='none'">
            <a href="#" class="icon-circle" title="Help">?</a>
        </div>
    </header>

    <div class="main-wrapper">
        <main class="content">
            <div class="form-container">
                <h1>Welcome Back</h1>
                <p class="intro-text">请输入您的登录凭证以继续。</p>
                
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    
                    <?php
                    // 显示 PHP 错误信息
                    if (!empty($login_err)) {
                        echo '<p class="error-message">' . htmlspecialchars($login_err) . '</p>';
                    }
                    ?>

                    <!-- 用户名输入框 -->
                    <div class="input-group">
                        <label for="username">用户名 / Username</label>
                        <input type="text" id="username" name="username" 
                               value="<?php echo htmlspecialchars($username); ?>" 
                               placeholder="Enter your username" required>
                    </div>

                    <!-- 密码输入框 -->
                    <div class="input-group">
                        <label for="password">密码 / Password</label>
                        <div class="password-wrapper">
                            <input type="password" id="password" name="password" 
                                   placeholder="Enter your password" required>
                            <i class="fa fa-eye-slash toggle-password" id="togglePassword"></i>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit">LOGIN</button>

                    <div class="form-links">
                        <p><a href="Recoverypage.php">Forgot Password?</a></p>
                        <p>Don't have an account? <a href="registration.php">Register here</a>.</p>
                    </div>
                </form>

                 <p class="privacy-policy">
                    By signing in, you agree to our <a href="#">Privacy Policy & Terms</a>.
                </p>
            </div>
        </main>
    </div>

    <script>
        // 密码显示/隐藏脚本
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
