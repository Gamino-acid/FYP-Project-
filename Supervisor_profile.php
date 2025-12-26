<?php
// ====================================================
// supervisor_profile.php - 导师个人资料页面
// ====================================================

include("connect.php");

// 1. 基础验证
$auth_user_id = $_GET['auth_user_id'] ?? null;
if (!$auth_user_id) { header("location: login.php"); exit; }

// 2. 逻辑处理 (POST) - 更新资料
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    
    // 更新联系方式
    $contact = $_POST['contact'];
    $sql_update = "UPDATE supervisor SET fyp_contactno = ? WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_update)) {
        $stmt->bind_param("si", $contact, $auth_user_id);
        $stmt->execute();
        $stmt->close();
    }

    // 更新头像 (Base64)
    if (!empty($_FILES["profile_img"]["name"])) {
        $tmp_name = $_FILES["profile_img"]["tmp_name"];
        if (getimagesize($tmp_name) !== false) {
            $image_content = file_get_contents($tmp_name);
            $file_ext = pathinfo($_FILES["profile_img"]["name"], PATHINFO_EXTENSION);
            $base64_image = 'data:image/' . $file_ext . ';base64,' . base64_encode($image_content);
            
            $sql_img = "UPDATE supervisor SET fyp_profileimg = ? WHERE fyp_userid = ?";
            if ($stmt_img = $conn->prepare($sql_img)) {
                $stmt_img->bind_param("si", $base64_image, $auth_user_id);
                $stmt_img->execute();
                $stmt_img->close();
            }
        } else {
             echo "<script>alert('Invalid file format. Please upload an image.');</script>";
        }
    }
    echo "<script>alert('Profile updated successfully!'); window.location.href='supervisor_profile.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
}

// 3. 数据查询 (GET) - 获取导师信息
$sv_data = [];
$user_name = "Supervisor"; 
$user_avatar = "image/user.png"; 

if (isset($conn)) {
    // 查 USER 表 (备用)
    $sql_user = "SELECT fyp_username FROM `USER` WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_user)) {
        $stmt->bind_param("i", $auth_user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) $user_name = $row['fyp_username'];
        $stmt->close();
    }
    
    // 查 SUPERVISOR 表
    $sql_sv = "SELECT * FROM supervisor WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_sv)) {
        $stmt->bind_param("i", $auth_user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $sv_data = $res->fetch_assoc();
            if (!empty($sv_data['fyp_name'])) $user_name = $sv_data['fyp_name'];
            if (!empty($sv_data['fyp_profileimg'])) $user_avatar = $sv_data['fyp_profileimg'];
        } else {
            // 空数据防止报错
            $sv_data = [
                'fyp_supervisorid' => 'N/A', 'fyp_name' => $user_name, 'fyp_roomno' => '', 
                'fyp_programme' => '', 'fyp_email' => '', 'fyp_contactno' => '', 
                'fyp_specialization' => '', 'fyp_areaofinterest' => '', 'fyp_ismoderator' => 0,
                'fyp_profileimg' => ''
            ];
        }
        $stmt->close();
    }
}

// 4. 定义菜单
$current_page = 'profile';
$menu_items = [
    'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home', 'link' => 'Supervisor_mainpage.php?page=dashboard'],
    'profile'   => ['name' => 'My Profile', 'icon' => 'fa-user', 'link' => 'supervisor_profile.php'], // 指向自己
    'students'  => ['name' => 'My Students', 'icon' => 'fa-users', 'link' => 'Supervisor_mainpage.php?page=students'],
    'schedule'  => ['name' => 'My Schedule', 'icon' => 'fa-calendar-alt', 'link' => 'Supervisor_mainpage.php?page=schedule'],
    'requests'  => ['name' => 'Requests', 'icon' => 'fa-envelope-open-text', 'link' => 'Supervisor_mainpage.php?page=requests'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Supervisor</title>
    
    <link rel="icon" type="image/png" href="<?php echo $user_avatar; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap');
        :root { --primary-color: #0056b3; --primary-hover: #004494; --secondary-color: #f4f4f9; --text-color: #333; --border-color: #e0e0e0; --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); --gradient-start: #eef2f7; --gradient-end: #ffffff; --sidebar-width: 260px; --student-accent: #007bff; }
        body { font-family: 'Poppins', sans-serif; margin: 0; background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end)); color: var(--text-color); min-height: 100vh; display: flex; flex-direction: column; }
        
        .topbar { display: flex; justify-content: space-between; align-items: center; padding: 15px 40px; background-color: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.05); z-index: 100; position: sticky; top: 0; }
        .logo { font-size: 22px; font-weight: 600; color: var(--primary-color); display: flex; align-items: center; gap: 10px; }
        .topbar-right { display: flex; align-items: center; gap: 20px; }
        .user-name-display { font-weight: 600; font-size: 14px; display: block; }
        .user-role-badge { font-size: 11px; background-color: #e3effd; color: var(--primary-color); padding: 2px 8px; border-radius: 12px; font-weight: 500; }
        .user-avatar-circle { width: 40px; height: 40px; border-radius: 50%; overflow: hidden; border: 2px solid #e3effd; margin-left: 10px; }
        .user-avatar-circle img { width: 100%; height: 100%; object-fit: cover; }
        .logout-btn { color: #d93025; text-decoration: none; font-size: 14px; font-weight: 500; padding: 8px 15px; border-radius: 6px; transition: background 0.2s; }
        .logout-btn:hover { background-color: #fff0f0; }
        
        .layout-container { display: flex; flex: 1; max-width: 1400px; margin: 0 auto; width: 100%; padding: 20px; box-sizing: border-box; gap: 20px; }
        .sidebar { width: var(--sidebar-width); background: #fff; border-radius: 12px; box-shadow: var(--card-shadow); padding: 20px 0; flex-shrink: 0; min-height: calc(100vh - 120px); }
        .menu-list { list-style: none; padding: 0; margin: 0; }
        .menu-item { margin-bottom: 5px; }
        .menu-link { display: flex; align-items: center; padding: 12px 25px; text-decoration: none; color: #555; font-weight: 500; font-size: 15px; transition: all 0.3s; border-left: 4px solid transparent; }
        .menu-link:hover { background-color: var(--secondary-color); color: var(--primary-color); }
        .menu-link.active { background-color: #e3effd; color: var(--primary-color); border-left-color: var(--primary-color); }
        .menu-icon { width: 24px; margin-right: 10px; text-align: center; }
        
        .main-content { flex: 1; display: flex; flex-direction: column; gap: 20px; }
        .welcome-card { background: #fff; padding: 30px; border-radius: 12px; box-shadow: var(--card-shadow); border-left: 5px solid var(--primary-color); }
        .page-title { font-size: 24px; margin: 0 0 10px 0; color: var(--text-color); }
        
        /* Profile Styles */
        .profile-container { display: flex; background: #fff; padding: 30px; border-radius: 12px; box-shadow: var(--card-shadow); gap: 30px; }
        .left-column { width: 250px; display: flex; flex-direction: column; align-items: center; border-right: 1px solid #eee; padding-right: 30px; }
        .right-column { flex: 1; }
        .profile-img-box { width: 160px; height: 160px; border-radius: 50%; background-color: #e0e0e0; overflow: hidden; margin-bottom: 15px; border: 4px solid #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.15); }
        .profile-img-box img { width: 100%; height: 100%; object-fit: cover; }
        .form-section-title { color: #555; border-bottom: 2px solid var(--primary-color); padding-bottom: 5px; margin-bottom: 20px; font-size: 1.1em; font-weight: 600; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; color: #444; font-size: 0.9em; }
        .form-group input[type="text"], .form-group input[type="email"], .form-group input[type="number"], .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; font-size: 0.95em; font-family: inherit; }
        
        input[readonly], textarea[readonly], select[disabled] { background-color: #e9ecef; cursor: not-allowed; color: #6c757d; border-color: #ced4da; }
        
        .row-group { display: flex; gap: 20px; }
        .col-group { flex: 1; }
        .save-btn { margin-top: 25px; padding: 12px 25px; background-color: var(--primary-color); color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 1em; transition: background 0.3s; font-family: inherit; font-weight: 500; }
        .save-btn:hover { background-color: var(--primary-hover); }
        .sv-id-display { font-size: 1.1em; font-weight: bold; color: #333; margin-top: 10px; text-align: center; }

        @media (max-width: 900px) { .layout-container { flex-direction: column; } .sidebar { width: 100%; min-height: auto; } .profile-container { flex-direction: column; } .left-column { width: 100%; border-right: none; border-bottom: 1px solid #eee; padding-bottom: 20px; margin-bottom: 20px; padding-right: 0; } }
    </style>
</head>
<body>

    <header class="topbar">
        <div class="logo"><img src="image/ladybug.png" alt="Logo" style="width: 32px; margin-right: 10px;"> FYP System</div>
        <div class="topbar-right">
            <div class="user-profile-summary">
                <span class="user-name-display"><?php echo htmlspecialchars($user_name); ?></span>
                <span class="user-role-badge">Lecturer</span>
            </div>
            <div class="user-avatar-circle">
                <img src="<?php echo $user_avatar; ?>" alt="User Avatar">
            </div>
            <a href="login.php" class="logout-btn"><i class="fa fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>

    <div class="layout-container">
        
        <aside class="sidebar">
            <ul class="menu-list">
                <?php foreach ($menu_items as $key => $item): ?>
                    <?php 
                        $isActive = ($key == $current_page);
                        $linkUrl = isset($item['link']) ? $item['link'] : "#";
                        
                        // URL 拼接修复 (兼容 ? 和 &)
                        if ($linkUrl !== "#") {
                             $separator = (strpos($linkUrl, '?') !== false) ? '&' : '?';
                             $linkUrl .= $separator . "auth_user_id=" . urlencode($auth_user_id);
                        }
                    ?>
                    <li class="menu-item">
                        <a href="<?php echo $linkUrl; ?>" class="menu-link <?php echo $isActive ? 'active' : ''; ?>">
                            <span class="menu-icon"><i class="fa <?php echo $item['icon']; ?>"></i></span>
                            <?php echo $item['name']; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </aside>

        <main class="main-content">
            
            <div class="welcome-card">
                <h1 class="page-title">My Profile</h1>
                <p style="color: #666; margin: 0;">View and manage your details.</p>
            </div>

            <form method="POST" action="" enctype="multipart/form-data">
                <div class="profile-container">
                    
                    <div class="left-column">
                        <div class="profile-img-box">
                            <?php 
                            if (!empty($sv_data['fyp_profileimg'])) {
                                echo "<img src='{$sv_data['fyp_profileimg']}' alt='Profile'>";
                            } else {
                                echo "<div style='width:100%;height:100%;background:#f0f0f0;display:flex;align-items:center;justify-content:center;color:#999;'><i class='fa fa-user-tie' style='font-size:50px;'></i></div>";
                            }
                            ?>
                        </div>
                        <label style="font-size: 0.9em; font-weight: 500; margin-bottom: 5px;">Change Photo:</label>
                        <input type="file" name="profile_img" accept="image/*" style="font-size: 0.8em; max-width: 100%;">
                        
                        <div class="sv-id-display">ID: <?php echo htmlspecialchars($sv_data['fyp_supervisorid']); ?></div>
                        <?php if($sv_data['fyp_ismoderator'] == 1): ?>
                            <span style="background:#28a745; color:white; padding:2px 8px; border-radius:10px; font-size:12px; margin-top:5px; display:inline-block;">Moderator</span>
                        <?php endif; ?>
                    </div>

                    <div class="right-column">
                        <h3 class="form-section-title">Personal & Academic Details</h3>
                        
                        <div class="form-group">
                            <label>Full Name:</label>
                            <input type="text" value="<?php echo htmlspecialchars($sv_data['fyp_name']); ?>" readonly>
                        </div>

                        <div class="row-group">
                            <div class="col-group form-group">
                                <label>Email Address:</label>
                                <input type="text" value="<?php echo htmlspecialchars($sv_data['fyp_email']); ?>" readonly>
                            </div>
                            <div class="col-group form-group">
                                <label>Programme:</label>
                                <input type="text" value="<?php echo htmlspecialchars($sv_data['fyp_programme']); ?>" readonly>
                            </div>
                        </div>

                        <div class="row-group">
                            <div class="col-group form-group">
                                <label>Room Number:</label>
                                <input type="text" value="<?php echo htmlspecialchars($sv_data['fyp_roomno']); ?>" readonly>
                            </div>
                            <div class="col-group form-group">
                                <label style="color: var(--primary-color); font-weight: 600;">Contact Number (Editable):</label>
                                <input type="text" name="contact" value="<?php echo htmlspecialchars($sv_data['fyp_contactno']); ?>" required style="border-color: var(--primary-color);">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Specialization:</label>
                            <textarea readonly rows="2"><?php echo htmlspecialchars($sv_data['fyp_specialization']); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label>Area of Interest:</label>
                            <textarea readonly rows="2"><?php echo htmlspecialchars($sv_data['fyp_areaofinterest']); ?></textarea>
                        </div>

                        <button type="submit" name="update_profile" class="save-btn"><i class="fa fa-save"></i> Save Changes</button>
                    </div>
                </div>
            </form>

        </main>
    </div>
</body>
</html>