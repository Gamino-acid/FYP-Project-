<?php
// ====================================================
// supervisor_profile.php - Final V2 (Max 16 Chars Limit)
// ====================================================

include("connect.php");

// 1. 基础验证
$auth_user_id = $_GET['auth_user_id'] ?? null;
$current_page = 'profile'; 

if (!$auth_user_id) { header("location: login.php"); exit; }

// ====================================================
// 2. 逻辑处理 (POST)
// ====================================================

// A. 更新基本资料 (Update Profile Info)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    
    $contact = $_POST['contact'];
    $room = $_POST['room_no'];
    $spec = $_POST['specialization'];
    $area = $_POST['area_interest'];

    // 更新 Supervisor 表
    $sql_update = "UPDATE supervisor SET 
                   fyp_contactno = ?, 
                   fyp_roomno = ?, 
                   fyp_specialization = ?, 
                   fyp_areaofinterest = ? 
                   WHERE fyp_userid = ?";
                   
    if ($stmt = $conn->prepare($sql_update)) {
        $stmt->bind_param("ssssi", $contact, $room, $spec, $area, $auth_user_id);
        if ($stmt->execute()) {
            $msg_type = "success";
            $msg_text = "Profile details updated successfully!";
        } else {
            $msg_type = "error";
            $msg_text = "Error updating profile.";
        }
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
        }
    }
    
    echo "<script>alert('$msg_text'); window.location.href='supervisor_profile.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
}

// B. 修改密码 (Test Mode: 纯文本存储 + 长度限制)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    $current_pwd = $_POST['current_password'];
    $new_pwd = $_POST['new_password'];
    $confirm_pwd = $_POST['confirm_password'];

    // 1. 验证一致性
    if ($new_pwd !== $confirm_pwd) {
        echo "<script>alert('Error: New password and Confirmation do not match.'); window.history.back();</script>";
        exit;
    }

    // 2. 验证长度 (PHP 端双重验证)
    if (strlen($new_pwd) < 8 || strlen($new_pwd) > 16) {
        echo "<script>alert('Error: Password must be between 8 and 16 characters.'); window.history.back();</script>";
        exit;
    }

    // 3. 验证复杂度 (8-16位 + 大写 + 数字 + 特殊字符)
    if (!preg_match('/^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,16}$/', $new_pwd)) {
        echo "<script>alert('Error: Password requirement not met (8-16 chars, 1 Upper, 1 Num, 1 Symbol).'); window.history.back();</script>";
        exit;
    }

    // 4. 验证旧密码 & 更新
    $sql_check = "SELECT fyp_passwordhash FROM `USER` WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_check)) {
        $stmt->bind_param("i", $auth_user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $db_pwd = $row['fyp_passwordhash']; 
            $is_correct = false;

            // 混合验证
            if ($current_pwd === $db_pwd) {
                $is_correct = true; 
            } 
            elseif (password_verify($current_pwd, $db_pwd)) {
                $is_correct = true; 
            }

            if ($is_correct) {
                // 【测试模式】直接存纯文本
                $final_password_to_store = $new_pwd; 
                
                $sql_upd_pwd = "UPDATE `USER` SET fyp_passwordhash = ? WHERE fyp_userid = ?";
                if ($stmt_up = $conn->prepare($sql_upd_pwd)) {
                    $stmt_up->bind_param("si", $final_password_to_store, $auth_user_id);
                    $stmt_up->execute();
                    echo "<script>alert('Password updated successfully!'); window.location.href='supervisor_profile.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
                }
            } else {
                echo "<script>alert('Error: Current password is incorrect.'); window.history.back();</script>";
            }
        }
        $stmt->close();
    }
}

// 3. 获取导师数据 (GET)
$sv_data = [];
$user_name = 'Supervisor';
$user_avatar = 'image/user.png';

if (isset($conn)) {
    $sql_user = "SELECT fyp_username FROM `USER` WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_user)) { 
        $stmt->bind_param("i", $auth_user_id); 
        $stmt->execute(); 
        $res=$stmt->get_result(); 
        if($row=$res->fetch_assoc()) $user_name=$row['fyp_username']; 
        $stmt->close(); 
    }
    
    $sql_sv = "SELECT * FROM supervisor WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_sv)) { 
        $stmt->bind_param("i", $auth_user_id); 
        $stmt->execute(); 
        $res=$stmt->get_result(); 
        if($res->num_rows > 0) { 
            $sv_data = $res->fetch_assoc(); 
            if(!empty($sv_data['fyp_name'])) $user_name=$sv_data['fyp_name'];
            if(!empty($sv_data['fyp_profileimg'])) $user_avatar=$sv_data['fyp_profileimg'];
        } else {
             $sv_data = ['fyp_supervisorid'=>'', 'fyp_name'=>$user_name, 'fyp_staffid'=>'', 'fyp_roomno'=>'', 'fyp_email'=>'', 'fyp_contactno'=>'', 'fyp_specialization'=>'', 'fyp_areaofinterest'=>''];
        }
        $stmt->close(); 
    }
}

// 4. 菜单定义
$menu_items = [
    'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home', 'link' => 'Supervisor_mainpage.php?page=dashboard'],
    'profile'   => ['name' => 'My Profile', 'icon' => 'fa-user', 'link' => 'supervisor_profile.php'],
    'students'  => [
        'name' => 'My Students', 
        'icon' => 'fa-users',
        'sub_items' => [
            'project_requests' => ['name' => 'Project Requests', 'icon' => 'fa-envelope-open-text', 'link' => 'supervisor_projectreq.php'],
            'student_list'     => ['name' => 'Student List', 'icon' => 'fa-list', 'link' => 'Supervisor_student_list.php'],
        ]
    ],
    'fyp_project' => [
        'name' => 'FYP Project',
        'icon' => 'fa-project-diagram',
        'sub_items' => [
            'propose_project' => ['name' => 'Propose Project', 'icon' => 'fa-plus-circle', 'link' => 'supervisor_purpose.php'],
            'my_projects'     => ['name' => 'My Projects', 'icon' => 'fa-folder-open', 'link' => 'Supervisor_mainpage.php?page=my_projects'],
            'propose_assignment' => ['name' => 'Propose Assignment', 'icon' => 'fa-tasks', 'link' => 'supervisor_assignment_purpose.php'],
            'all_project' => ['name' => 'All Project', 'icon' => 'fa-tasks', 'link' => 'supervisor_project_list.php']
        ]
    ],
    'grading' => [
        'name' => 'Assessment',
        'icon' => 'fa-marker',
        'sub_items' => [
            'grade_assignment' => ['name' => 'Grade Assignments', 'icon' => 'fa-check-square', 'link' => 'Supervisor_assignment_grade.php'],
        ]
    ],
    'announcement' => [
        'name' => 'Announcement',
        'icon' => 'fa-bullhorn',
        'sub_items' => [
            'post_announcement' => ['name' => 'Post Announcement', 'icon' => 'fa-pen-square', 'link' => 'supervisor_announcement.php'],
            'view_announcements' => ['name' => 'View History', 'icon' => 'fa-history', 'link' => 'Supervisor_mainpage.php?page=view_announcements'],
        ]
    ],
    'schedule'  => ['name' => 'My Schedule', 'icon' => 'fa-calendar-alt', 'link' => 'supervisor_meeting.php'],
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
        .submenu { list-style: none; padding: 0; margin: 0; background-color: #fafafa; display: block; }
        .submenu .menu-link { padding-left: 58px; font-size: 14px; padding-top: 10px; padding-bottom: 10px; }

        .main-content { flex: 1; display: flex; flex-direction: column; gap: 20px; }
        .welcome-card { background: #fff; padding: 30px; border-radius: 12px; box-shadow: var(--card-shadow); border-left: 5px solid var(--primary-color); }
        .page-title { font-size: 24px; margin: 0 0 10px 0; color: var(--text-color); }

        /* Profile Styles */
        .profile-container { display: flex; background: #fff; padding: 30px; border-radius: 12px; box-shadow: var(--card-shadow); gap: 30px; }
        .left-column { width: 250px; display: flex; flex-direction: column; align-items: center; border-right: 1px solid #eee; padding-right: 30px; }
        .right-column { flex: 1; }
        
        .profile-img-box { width: 160px; height: 160px; border-radius: 50%; background-color: #e0e0e0; overflow: hidden; margin-bottom: 15px; border: 4px solid #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.15); }
        .profile-img-box img { width: 100%; height: 100%; object-fit: cover; }
        
        .form-section-title { color: #555; border-bottom: 2px solid var(--primary-color); padding-bottom: 5px; margin-bottom: 20px; font-size: 1.1em; font-weight: 600; display: flex; justify-content: space-between; align-items: center; }
        
        .form-group { margin-bottom: 15px; position: relative; /* For password eye icon */ }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; color: #444; font-size: 0.9em; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; font-size: 0.95em; font-family: inherit; }
        .form-control:focus { border-color: var(--primary-color); outline: none; }
        
        /* Toggle Eye Icon Style */
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 38px;
            cursor: pointer;
            color: #999;
            z-index: 10;
        }
        .toggle-password:hover { color: #333; }

        input[readonly] { background-color: #e9ecef; cursor: not-allowed; color: #6c757d; border-color: #ced4da; }
        
        .row-group { display: flex; gap: 20px; }
        .col-group { flex: 1; }
        
        .action-buttons { margin-top: 25px; display: flex; gap: 15px; align-items: center; }
        
        .btn-save { padding: 12px 25px; background-color: var(--primary-color); color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 1em; transition: background 0.3s; font-family: inherit; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; }
        .btn-save:hover { background-color: var(--primary-hover); }
        
        .btn-reset-pwd { padding: 12px 25px; background-color: #ffc107; color: #333; border: none; border-radius: 6px; cursor: pointer; font-size: 1em; transition: background 0.3s; font-family: inherit; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; }
        .btn-reset-pwd:hover { background-color: #e0a800; }
        
        .student-id-display { font-size: 1.2em; font-weight: bold; color: #333; margin-top: 10px; text-align: center; }

        /* Modal Styles */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; opacity: 0; transition: opacity 0.3s ease; }
        .modal-overlay.show { display: flex; opacity: 1; }
        
        .modal-box { background: #fff; width: 90%; max-width: 500px; padding: 30px; border-radius: 12px; box-shadow: 0 15px 40px rgba(0,0,0,0.2); transform: scale(0.9); transition: transform 0.3s ease; border-left: 5px solid #ffc107; }
        .modal-overlay.show .modal-box { transform: scale(1); }
        
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .modal-title { font-size: 1.2em; font-weight: 600; color: #333; margin: 0; }
        .close-modal { font-size: 24px; cursor: pointer; color: #999; }
        .close-modal:hover { color: #333; }

        .pwd-req-list { list-style: none; padding: 0; margin: 10px 0 20px 0; font-size: 0.85em; color: #666; display: flex; flex-wrap: wrap; gap: 10px; }
        .pwd-req-item { display: flex; align-items: center; gap: 5px; background: #f8f9fa; padding: 5px 10px; border-radius: 15px; border: 1px solid #eee; transition: all 0.3s; }
        .pwd-req-item.valid { background: #d4edda; color: #155724; border-color: #c3e6cb; }
        .pwd-req-item.invalid { background: #fff3cd; color: #856404; border-color: #ffeeba; }
        .pwd-req-item i { font-size: 10px; }
        
        .btn-cancel { padding: 12px 20px; background-color: #f1f1f1; color: #555; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; margin-left: 10px; }
        .btn-cancel:hover { background-color: #e0e0e0; }
        
        .modal-footer { margin-top: 20px; display: flex; justify-content: flex-end; }

        @media (max-width: 900px) { .layout-container { flex-direction: column; } .sidebar { width: 100%; min-height: auto; } .profile-container { flex-direction: column; } .left-column { width: 100%; border-right: none; border-bottom: 1px solid #eee; padding-bottom: 20px; margin-bottom: 20px; padding-right: 0; } .row-group { flex-direction: column; gap: 0; } .action-buttons { flex-direction: column; align-items: stretch; } }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="logo">FYP System</div>
        <div class="topbar-right">
            <div class="user-profile-summary">
                <span class="user-name-display"><?php echo htmlspecialchars($user_name); ?></span>
                <span class="user-role-badge">Supervisor</span>
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
                        $linkUrl = isset($item['link']) ? $item['link'] : "#";
                        if (strpos($linkUrl, '.php') !== false) {
                             $separator = (strpos($linkUrl, '?') !== false) ? '&' : '?';
                             $linkUrl .= $separator . "auth_user_id=" . urlencode($auth_user_id);
                        }
                    ?>
                    <li class="menu-item <?php echo $isActive ? 'has-active-child' : ''; ?>">
                        <a href="<?php echo $linkUrl; ?>" class="menu-link <?php echo $isActive ? 'active' : ''; ?>">
                            <span class="menu-icon"><i class="fa <?php echo $item['icon']; ?>"></i></span>
                            <?php echo $item['name']; ?>
                        </a>
                        <?php if (isset($item['sub_items'])): ?>
                            <ul class="submenu">
                                <?php foreach ($item['sub_items'] as $sub_key => $sub_item): 
                                    $subLinkUrl = isset($sub_item['link']) ? $sub_item['link'] : "#";
                                    if (strpos($subLinkUrl, '.php') !== false) {
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
                <p style="color: #666; margin: 0;">Manage your personal information and account security.</p>
            </div>

            <form method="POST" action="" enctype="multipart/form-data">
                <div class="profile-container">
                    <div class="left-column">
                        <div class="profile-img-box">
                            <img src="<?php echo $user_avatar; ?>" alt="Profile">
                        </div>
                        <label style="font-size: 0.9em; font-weight: 500; margin-bottom: 5px;">Change Photo:</label>
                        <input type="file" name="profile_img" accept="image/*" style="font-size: 0.8em; max-width: 100%;">
                        <div class="student-id-display"><?php echo htmlspecialchars($sv_data['fyp_staffid']); ?></div>
                        <div style="font-size: 0.8em; color: #888; text-align: center;">(Staff ID)</div>
                    </div>

                    <div class="right-column">
                        <h3 class="form-section-title"><i class="fa fa-id-card"></i> Personal Details</h3>
                        <div class="form-group">
                            <label>Full Name:</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($sv_data['fyp_name']); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label>Email Address:</label>
                            <input type="email" class="form-control" value="<?php echo htmlspecialchars($sv_data['fyp_email']); ?>" readonly>
                        </div>
                        
                        <div class="row-group">
                            <div class="col-group form-group">
                                <label>Room Number:</label>
                                <input type="text" class="form-control" name="room_no" value="<?php echo htmlspecialchars($sv_data['fyp_roomno']); ?>" placeholder="e.g. B-02-01">
                            </div>
                            <div class="col-group form-group">
                                <label>Contact Number:</label>
                                <input type="text" class="form-control" name="contact" value="<?php echo htmlspecialchars($sv_data['fyp_contactno']); ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Specialization:</label>
                            <textarea name="specialization" class="form-control" rows="2" placeholder="e.g. Software Engineering, AI..."><?php echo htmlspecialchars($sv_data['fyp_specialization']); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label>Area of Interest:</label>
                            <textarea name="area_interest" class="form-control" rows="2" placeholder="e.g. Machine Learning, Web Development..."><?php echo htmlspecialchars($sv_data['fyp_areaofinterest']); ?></textarea>
                        </div>

                        <div class="action-buttons">
                            <button type="submit" name="update_profile" class="btn-save"><i class="fa fa-save"></i> Save Profile</button>
                            <button type="button" onclick="openPwdModal()" class="btn-reset-pwd"><i class="fa fa-key"></i> Change Password</button>
                        </div>
                    </div>
                </div>
            </form>
        </main>
    </div>

    <div id="pwdModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fa fa-lock"></i> Security Settings</h3>
                <span class="close-modal" onclick="closePwdModal()">&times;</span>
            </div>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label>Current Password <span style="color:red">*</span></label>
                    <input type="password" class="form-control" name="current_password" id="current_password" maxlength="16" required placeholder="Enter current password">
                    <i class="fa fa-eye toggle-password" onclick="togglePassword('current_password', this)"></i>
                </div>

                <div class="form-group">
                    <label>New Password <span style="color:red">*</span></label>
                    <input type="password" class="form-control" name="new_password" id="new_password" maxlength="16" required placeholder="Enter new password">
                    <i class="fa fa-eye toggle-password" onclick="togglePassword('new_password', this)"></i>
                </div>
                
                <div class="form-group">
                    <label>Confirm New Password <span style="color:red">*</span></label>
                    <input type="password" class="form-control" name="confirm_password" id="confirm_password" maxlength="16" required placeholder="Re-enter new password">
                    <i class="fa fa-eye toggle-password" onclick="togglePassword('confirm_password', this)"></i>
                </div>

                <ul class="pwd-req-list">
                    <li id="req_len" class="pwd-req-item"><i class="fa fa-circle"></i> 8-16 Characters</li>
                    <li id="req_upper" class="pwd-req-item"><i class="fa fa-circle"></i> 1 Uppercase</li>
                    <li id="req_num" class="pwd-req-item"><i class="fa fa-circle"></i> 1 Number</li>
                    <li id="req_spec" class="pwd-req-item"><i class="fa fa-circle"></i> 1 Special Char</li>
                    <li id="req_match" class="pwd-req-item"><i class="fa fa-circle"></i> Passwords Match</li>
                </ul>

                <div class="modal-footer">
                    <button type="submit" name="change_password" id="btn_change_pwd" class="btn-save" style="background-color: #ffc107; color: #333;" disabled>Update Password</button>
                    <button type="button" onclick="closePwdModal()" class="btn-cancel">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal Logic
        const modal = document.getElementById('pwdModal');
        function openPwdModal() {
            modal.classList.add('show');
        }
        function closePwdModal() {
            modal.classList.remove('show');
            // Optional: Clear fields when closing
            document.getElementById('current_password').value = '';
            document.getElementById('new_password').value = '';
            document.getElementById('confirm_password').value = '';
        }
        window.onclick = function(e) {
            if (e.target == modal) {
                closePwdModal();
            }
        }

        // Toggle Password Visibility
        function togglePassword(inputId, icon) {
            const input = document.getElementById(inputId);
            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
            } else {
                input.type = "password";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
            }
        }

        // Validation Logic
        const newPwdInput = document.getElementById('new_password');
        const confirmPwdInput = document.getElementById('confirm_password');
        const submitBtn = document.getElementById('btn_change_pwd');

        // Requirement Elements
        const reqLen = document.getElementById('req_len');
        const reqUpper = document.getElementById('req_upper');
        const reqNum = document.getElementById('req_num');
        const reqSpec = document.getElementById('req_spec');
        const reqMatch = document.getElementById('req_match');

        function validatePassword() {
            const val = newPwdInput.value;
            const confirmVal = confirmPwdInput.value;
            let isValid = true;

            // 1. Length Check
            if (val.length >= 8) {
                setValid(reqLen, true);
            } else {
                setValid(reqLen, false);
                isValid = false;
            }

            // 2. Uppercase Check
            if (/[A-Z]/.test(val)) {
                setValid(reqUpper, true);
            } else {
                setValid(reqUpper, false);
                isValid = false;
            }

            // 3. Number Check
            if (/\d/.test(val)) {
                setValid(reqNum, true);
            } else {
                setValid(reqNum, false);
                isValid = false;
            }

            // 4. Special Char Check
            if (/[\W_]/.test(val)) {
                setValid(reqSpec, true);
            } else {
                setValid(reqSpec, false);
                isValid = false;
            }

            // 5. Match Check
            if (val && val === confirmVal) {
                setValid(reqMatch, true);
            } else {
                setValid(reqMatch, false);
                isValid = false;
            }

            // Enable/Disable Button
            submitBtn.disabled = !isValid;
            submitBtn.style.opacity = isValid ? "1" : "0.6";
            submitBtn.style.cursor = isValid ? "pointer" : "not-allowed";
        }

        function setValid(element, isValid) {
            if (isValid) {
                element.classList.add('valid');
                element.classList.remove('invalid');
                element.querySelector('i').className = 'fa fa-check';
            } else {
                element.classList.remove('valid');
                element.classList.add('invalid');
                element.querySelector('i').className = 'fa fa-times';
            }
        }

        newPwdInput.addEventListener('input', validatePassword);
        confirmPwdInput.addEventListener('input', validatePassword);
    </script>
</body>
</html>