<?php
// ====================================================
// Coordinator_profile.php - 协调员个人资料 (包含专业领域和研究兴趣)
// ====================================================

include("connect.php");

// 1. 基础验证
$auth_user_id = $_GET['auth_user_id'] ?? null;
// 手动设置当前页面为 profile 以高亮菜单
$current_page = 'profile'; 

if (!$auth_user_id) { 
    echo "<script>window.location.href='login.php';</script>";
    exit; 
}

// 2. 逻辑处理 (POST) - 更新资料
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    
    // A. 更新所有个人信息 (包括新字段)
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $room_no = $_POST['room_no'];
    $contact = $_POST['contact'];
    $specialization = $_POST['specialization']; // 新增
    $area_interest = $_POST['area_interest'];   // 新增

    // SQL Update Statement
    // 注意：请确保数据库 coordinator 表已添加 fyp_specialization 和 fyp_areaofinterest 字段
    $sql_update = "UPDATE coordinator SET fyp_name = ?, fyp_email = ?, fyp_roomno = ?, fyp_contactno = ?, fyp_specialization = ?, fyp_areaofinterest = ? WHERE fyp_userid = ?";
    
    if ($stmt = $conn->prepare($sql_update)) {
        // 绑定参数: ssssss i (6个字符串, 1个整数)
        $stmt->bind_param("ssssssi", $full_name, $email, $room_no, $contact, $specialization, $area_interest, $auth_user_id);
        $stmt->execute();
        $stmt->close();
    }

    // B. 更新头像 (Base64)
    if (!empty($_FILES["profile_img"]["name"])) {
        $tmp_name = $_FILES["profile_img"]["tmp_name"];
        if (getimagesize($tmp_name) !== false) {
            $image_content = file_get_contents($tmp_name);
            $file_ext = pathinfo($_FILES["profile_img"]["name"], PATHINFO_EXTENSION);
            $base64_image = 'data:image/' . $file_ext . ';base64,' . base64_encode($image_content);
            
            $sql_img = "UPDATE coordinator SET fyp_profileimg = ? WHERE fyp_userid = ?";
            if ($stmt_img = $conn->prepare($sql_img)) {
                $stmt_img->bind_param("si", $base64_image, $auth_user_id);
                $stmt_img->execute();
                $stmt_img->close();
            }
        } else {
             echo "<script>alert('Invalid file format. Please upload an image.');</script>";
        }
    }
    // 刷新页面显示更新
    echo "<script>alert('Profile updated successfully!'); window.location.href='Coordinator_profile.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
}

// 3. 获取协调员数据 (GET)
$coor_data = [];
$user_name = 'Coordinator';
$user_avatar = 'image/user.png';

if (isset($conn)) {
    // 查 USER 表
    $sql_user = "SELECT fyp_username FROM `USER` WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_user)) { 
        $stmt->bind_param("i", $auth_user_id); 
        $stmt->execute(); 
        $res=$stmt->get_result(); 
        if($row=$res->fetch_assoc()) $user_name=$row['fyp_username']; 
        $stmt->close(); 
    }
    
    // 查 COORDINATOR 表
    $sql_coor = "SELECT * FROM coordinator WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_coor)) { 
        $stmt->bind_param("i", $auth_user_id); 
        $stmt->execute(); 
        $res=$stmt->get_result(); 
        if($res->num_rows > 0) { 
            $coor_data = $res->fetch_assoc(); 
            if(!empty($coor_data['fyp_name'])) $user_name=$coor_data['fyp_name'];
            if(!empty($coor_data['fyp_profileimg'])) $user_avatar=$coor_data['fyp_profileimg'];
        } else {
             // 默认空数据防止报错
             $coor_data = [
                 'fyp_coordinatorid'=>'', 'fyp_name'=>$user_name, 'fyp_staffid'=>'', 
                 'fyp_roomno'=>'', 'fyp_email'=>'', 'fyp_contactno'=>'',
                 'fyp_specialization'=>'', 'fyp_areaofinterest'=>'' // 防止未定义索引警告
             ];
        }
        $stmt->close(); 
    }
}

// 4. 定义菜单 (Coordinator Specific Menu)
$menu_items = [
    'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home', 'link' => 'Coordinator_mainpage.php?page=dashboard'],
    'profile'   => ['name' => 'My Profile', 'icon' => 'fa-user', 'link' => 'Coordinator_profile.php'],
    
    // --- Coordinator 管理功能 ---
    'management' => [
        'name' => 'User Management',
        'icon' => 'fa-users-cog',
        'sub_items' => [
            // 两个链接都指向同一个管理页面，通过 tab 参数区分默认显示
            'manage_students' => ['name' => 'Student List', 'icon' => 'fa-user-graduate', 'link' => 'Coordinator_manage_users.php?tab=student'],
            'manage_supervisors' => ['name' => 'Supervisor List', 'icon' => 'fa-chalkboard-teacher', 'link' => 'Coordinator_manage_users.php?tab=supervisor'],
        ]
    ],
    'project_mgmt' => [
        'name' => 'Project Management',
        'icon' => 'fa-project-diagram',
        'sub_items' => [
            'pairing_list' => ['name' => 'Pairing List', 'icon' => 'fa-link', 'link' => 'Coordinator_mainpage.php?page=pairing_list'],
            'project_archive' => ['name' => 'Project Archive', 'icon' => 'fa-archive', 'link' => 'Coordinator_mainpage.php?page=project_archive'],
        ]
    ],
    // ----------------------------
    
    'announcement' => [
        'name' => 'Announcement',
        'icon' => 'fa-bullhorn',
        'sub_items' => [
            'post_announcement' => ['name' => 'Post Announcement', 'icon' => 'fa-pen-square', 'link' => 'Coordinator_announcement.php'], 
            'view_announcements' => ['name' => 'View History', 'icon' => 'fa-history', 'link' => 'Coordinator_mainpage.php?page=view_announcements'],
        ]
    ],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Coordinator</title>
    <link rel="icon" type="image/png" href="<?php echo $user_avatar; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* 复用 mainpage 的 CSS */
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap');
        :root { --primary-color: #0056b3; --primary-hover: #004494; --secondary-color: #f4f4f9; --text-color: #333; --border-color: #e0e0e0; --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); --gradient-start: #eef2f7; --gradient-end: #ffffff; --sidebar-width: 260px; }
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
        
        /* --- Sidebar Expanded Styles --- */
        .submenu { list-style: none; padding: 0; margin: 0; background-color: #fafafa; display: block; }
        .submenu .menu-link { padding-left: 58px; font-size: 14px; padding-top: 10px; padding-bottom: 10px; }
        /* ------------------------------- */

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
        .form-group input[type="text"], .form-group input[type="email"], .form-group input[type="number"], .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; font-size: 0.95em; font-family: inherit; }
        
        input[readonly], textarea[readonly] { background-color: #e9ecef; cursor: not-allowed; color: #6c757d; border-color: #ced4da; }
        
        .row-group { display: flex; gap: 20px; }
        .col-group { flex: 1; }
        
        .save-btn { margin-top: 25px; padding: 12px 25px; background-color: var(--primary-color); color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 1em; transition: background 0.3s; font-family: inherit; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; }
        .save-btn:hover { background-color: var(--primary-hover); }
        
        .student-id-display { font-size: 1.2em; font-weight: bold; color: #333; margin-top: 10px; text-align: center; }

        @media (max-width: 900px) { .layout-container { flex-direction: column; } .sidebar { width: 100%; min-height: auto; } .profile-container { flex-direction: column; } .left-column { width: 100%; border-right: none; border-bottom: 1px solid #eee; padding-bottom: 20px; margin-bottom: 20px; padding-right: 0; } }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="logo"><img src="image/ladybug.png" alt="Logo" style="width: 32px; margin-right: 10px;"> FYP System</div>
        <div class="topbar-right">
            <div class="user-profile-summary">
                <span class="user-name-display"><?php echo htmlspecialchars($user_name); ?></span>
                <span class="user-role-badge">Coordinator</span>
            </div>
            <div class="user-avatar-circle"><img src="<?php echo $user_avatar; ?>" alt="User Avatar"></div>
            <a href="login.php" class="logout-btn"><i class="fa fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>

    <div class="layout-container">
        <aside class="sidebar">
            <ul class="menu-list">
                <?php foreach ($menu_items as $key => $item): ?>
                    <?php 
                        $isActive = ($key == $current_page);
                        $hasActiveChild = false;
                        if (isset($item['sub_items'])) {
                            foreach ($item['sub_items'] as $sub_key => $sub) {
                                if ($sub_key == $current_page) { $hasActiveChild = true; break; }
                            }
                        }
                        
                        $linkUrl = isset($item['link']) ? $item['link'] : "#";
                        if ($linkUrl !== "#") {
                             $separator = (strpos($linkUrl, '?') !== false) ? '&' : '?';
                             $linkUrl .= $separator . "auth_user_id=" . urlencode($auth_user_id);
                        }
                    ?>
                    <li class="menu-item <?php echo $hasActiveChild ? 'has-active-child' : ''; ?>">
                        <a href="<?php echo $linkUrl; ?>" class="menu-link <?php echo $isActive ? 'active' : ''; ?>">
                            <span class="menu-icon"><i class="fa <?php echo $item['icon']; ?>"></i></span>
                            <?php echo $item['name']; ?>
                        </a>
                        <?php if (isset($item['sub_items'])): ?>
                            <ul class="submenu">
                                <?php foreach ($item['sub_items'] as $sub_key => $sub_item): 
                                    $subLinkUrl = isset($sub_item['link']) ? $sub_item['link'] : "#";
                                    if ($subLinkUrl !== "#") {
                                        $separator = (strpos($subLinkUrl, '?') !== false) ? '&' : '?';
                                        $subLinkUrl .= $separator . "auth_user_id=" . urlencode($auth_user_id);
                                    }
                                ?>
                                    <li><a href="<?php echo $subLinkUrl; ?>" class="menu-link <?php echo ($sub_key == $current_page) ? 'active' : ''; ?>">
                                        <span class="menu-icon"><i class="fa <?php echo $sub_item['icon']; ?>"></i></span> <?php echo $sub_item['name']; ?>
                                    </a></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </aside>

        <main class="main-content">
            <div class="welcome-card">
                <h1 class="page-title">My Profile</h1>
                <p style="color: #666; margin: 0;">Manage your personal information.</p>
            </div>

            <!-- PROFILE FORM -->
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="profile-container">
                    <div class="left-column">
                        <div class="profile-img-box">
                            <img src="<?php echo $user_avatar; ?>" alt="Profile">
                        </div>
                        <label style="font-size: 0.9em; font-weight: 500; margin-bottom: 5px;">Change Photo:</label>
                        <input type="file" name="profile_img" accept="image/*" style="font-size: 0.8em; max-width: 100%;">
                        <div class="student-id-display"><?php echo htmlspecialchars($coor_data['fyp_staffid']); ?></div>
                        <div style="font-size: 0.8em; color: #888; text-align: center;">(Staff ID)</div>
                    </div>

                    <div class="right-column">
                        <h3 class="form-section-title">Personal Details</h3>
                        <div class="form-group">
                            <label>Full Name:</label>
                            <input type="text" name="full_name" value="<?php echo htmlspecialchars($coor_data['fyp_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Email Address:</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($coor_data['fyp_email']); ?>" required>
                        </div>
                        
                        <div class="row-group">
                            <div class="col-group form-group">
                                <label>Room Number:</label>
                                <input type="text" name="room_no" value="<?php echo htmlspecialchars($coor_data['fyp_roomno']); ?>" required>
                            </div>
                            <div class="col-group form-group">
                                <label style="color: var(--primary-color); font-weight: 600;">Contact Number:</label>
                                <input type="text" name="contact" value="<?php echo htmlspecialchars($coor_data['fyp_contactno']); ?>" required style="border-color: var(--primary-color);">
                            </div>
                        </div>

                        <!-- NEW FIELDS -->
                        <div class="form-group">
                            <label>Specialization:</label>
                            <textarea name="specialization" class="form-control" rows="2" placeholder="e.g. Artificial Intelligence, Data Science"><?php echo htmlspecialchars($coor_data['fyp_specialization'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label>Area of Interest:</label>
                            <textarea name="area_interest" class="form-control" rows="2" placeholder="e.g. Machine Learning applications in healthcare"><?php echo htmlspecialchars($coor_data['fyp_areaofinterest'] ?? ''); ?></textarea>
                        </div>

                        <button type="submit" name="update_profile" class="save-btn"><i class="fa fa-save"></i> Save Changes</button>
                    </div>
                </div>
            </form>

        </main>
    </div>
</body>
</html>