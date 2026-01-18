<?php
// ====================================================
// Coordinator_announcement.php - 修复版 V3
// (修复: 正确读取并存入 VARCHAR 类型的 staffid)
// ====================================================
include("connect.php");

// 1. 基础验证
$auth_user_id = $_GET['auth_user_id'] ?? null;
$current_page = 'post_announcement'; 

if (!$auth_user_id) { header("location: login.php"); exit; }

// 2. 获取 Coordinator 信息
$user_name = "Coordinator"; 
$user_avatar = "image/user.png"; 
$current_staff_id = ""; // 用于存储读取到的 VARCHAR ID

if (isset($conn)) {
    // A. 获取用户基本名
    $sql_user = "SELECT fyp_username FROM `USER` WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_user)) {
        $stmt->bind_param("i", $auth_user_id); $stmt->execute();
        $res = $stmt->get_result(); if ($row = $res->fetch_assoc()) $user_name = $row['fyp_username'];
        $stmt->close();
    }
    
    // B. 获取 COORDINATOR 详细资料 (包括 fyp_staffid)
    $sql_coor = "SELECT * FROM coordinator WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_coor)) {
        $stmt->bind_param("i", $auth_user_id); $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $row = $res->fetch_assoc();
            
            // 【修复点 1】: 优先读取 fyp_staffid (VARCHAR)
            if (!empty($row['fyp_staffid'])) {
                $current_staff_id = $row['fyp_staffid'];
            } elseif (!empty($row['fyp_coordinatorid'])) {
                $current_staff_id = $row['fyp_coordinatorid']; // 后备
            }

            if (!empty($row['fyp_name'])) $user_name = $row['fyp_name'];
            if (!empty($row['fyp_profileimg'])) $user_avatar = $row['fyp_profileimg'];
        }
        $stmt->close();
    }

    // C. 获取所有 Active Projects
    $my_active_targets = []; 
    $sql_targets = "SELECT p.fyp_projecttitle, p.fyp_projecttype, 
                           GROUP_CONCAT(s.fyp_studname SEPARATOR ', ') as students
                    FROM fyp_registration r
                    JOIN project p ON r.fyp_projectid = p.fyp_projectid
                    JOIN student s ON r.fyp_studid = s.fyp_studid
                    GROUP BY r.fyp_projectid";
    
    $res_t = $conn->query($sql_targets);
    if ($res_t) {
        while ($row_t = $res_t->fetch_assoc()) {
            $type_tag = ($row_t['fyp_projecttype'] == 'Group') ? '[Group]' : '[Indiv]';
            $label = $type_tag . " " . substr($row_t['fyp_projecttitle'], 0, 30) . "...";
            $value = "Project: " . $row_t['fyp_projecttitle']; 
            $my_active_targets[] = ['label' => $label, 'value' => $value];
        }
    }
}

// ====================================================
// 3. 处理公告提交 (POST)
// ====================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $subject = $_POST['subject'];
    $description = $_POST['description'];
    $receiver_type = $_POST['receiver_type'];
    $date_now = date('Y-m-d H:i:s');
    
    $final_receiver = "";
    if ($receiver_type == 'specific') {
        $final_receiver = $_POST['specific_target']; 
    } else {
        $final_receiver = $receiver_type; 
    }

    // 【修复点 2】: 重新获取正确的 Staff ID (VARCHAR) 以防丢失
    // 即使上面获取过一次，在POST请求中为了安全和准确，这里再次确认 ID
    $sender_identity = $auth_user_id; // 默认
    
    // 再次查询确保拿到 VARCHAR 类型的 staffid
    $id_query = "SELECT fyp_staffid FROM coordinator WHERE fyp_userid = '$auth_user_id' LIMIT 1";
    $id_res = $conn->query($id_query);
    if ($id_res && $id_row = $id_res->fetch_assoc()) {
        if(!empty($id_row['fyp_staffid'])) {
            $sender_identity = $id_row['fyp_staffid']; // 这里拿到了 "S1234" 这样的字符串
        }
    }

    // 插入数据库
    // 注意：bind_param 使用 "sssss"，最后一个 s 代表 sender_identity 是字符串
    $sql_insert = "INSERT INTO announcement (fyp_subject, fyp_description, fyp_receiver, fyp_datecreated, fyp_staffid) VALUES (?, ?, ?, ?, ?)";
    
    if ($stmt = $conn->prepare($sql_insert)) {
        // 绑定参数: Subject(s), Description(s), Receiver(s), Date(s), StaffID(s - VARCHAR)
        $stmt->bind_param("sssss", $subject, $description, $final_receiver, $date_now, $sender_identity);
        
        if ($stmt->execute()) {
            echo "<script>alert('Announcement posted successfully!'); window.location.href='Coordinator_mainpage.php?auth_user_id=" . $auth_user_id . "';</script>";
        } else {
            echo "<script>alert('SQL Execute Error: " . addslashes($stmt->error) . "');</script>";
        }
        $stmt->close();
    } else {
        echo "<script>alert('DB Prepare Error: " . addslashes($conn->error) . "');</script>";
    }
}

// 4. 菜单定义
$menu_items = [
    'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home', 'link' => 'Coordinator_mainpage.php?page=dashboard'],
    'profile'   => ['name' => 'My Profile', 'icon' => 'fa-user', 'link' => 'Coordinator_profile.php'],
    'management' => [
        'name' => 'User Management',
        'icon' => 'fa-users-cog',
        'sub_items' => [
            'manage_students' => ['name' => 'Student List', 'icon' => 'fa-user-graduate', 'link' => 'Coordinator_mainpage.php?page=manage_students'],
            'manage_supervisors' => ['name' => 'Supervisor List', 'icon' => 'fa-chalkboard-teacher', 'link' => 'Coordinator_mainpage.php?page=manage_supervisors'],
        ]
    ],
    'project_mgmt' => [
        'name' => 'Project Management',
        'icon' => 'fa-project-diagram',
        'sub_items' => [
            'project_list' => ['name' => 'All Projects & Groups', 'icon' => 'fa-list-alt', 'link' => 'Coordinator_project_list.php'],
            'pairing_list' => ['name' => 'Pairing List', 'icon' => 'fa-link', 'link' => 'Coordinator_mainpage.php?page=pairing_list'],
        ]
    ],
    'announcement' => [
        'name' => 'Announcement',
        'icon' => 'fa-bullhorn',
        'sub_items' => [
            'post_announcement' => ['name' => 'Post Announcement', 'icon' => 'fa-pen-square', 'link' => 'Coordinator_announcement.php'], 
            'view_announcements' => ['name' => 'View History', 'icon' => 'fa-history', 'link' => 'Coordinator_mainpage.php?page=view_announcements'],
        ]
    ],
    'schedule' => ['name' => 'My Schedule', 'icon' => 'fa-calendar-alt', 'link' => 'Coordinator_meeting.php'], 
    'data_io' => ['name' => 'Data Management', 'icon' => 'fa-database', 'link' => 'Coordinator_mainpage.php?page=data_io'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post Announcement</title>
    <link rel="icon" type="image/png" href="<?php echo $user_avatar; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* CSS 样式 */
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
        .submenu { list-style: none; padding: 0; margin: 0; background-color: #fafafa; display: none; }
        .menu-item.has-active-child .submenu, .menu-item:hover .submenu { display: block; }
        .submenu .menu-link { padding-left: 58px; font-size: 14px; padding-top: 10px; padding-bottom: 10px; }

        .main-content { flex: 1; display: flex; flex-direction: column; gap: 20px; }
        
        /* 页面特定样式 */
        .form-card { background: #fff; padding: 30px; border-radius: 12px; box-shadow: var(--card-shadow); }
        .page-header { border-bottom: 2px solid #eee; padding-bottom: 15px; margin-bottom: 25px; }
        .page-header h2 { margin: 0; color: var(--primary-color); }
        .page-header p { color: #666; font-size: 14px; margin-top: 5px; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #444; }
        .form-control { width: 100%; padding: 10px 15px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; box-sizing: border-box; transition: border 0.3s; font-family: inherit; }
        .form-control:focus { border-color: var(--primary-color); outline: none; }
        textarea.form-control { resize: vertical; min-height: 150px; }
        
        .btn-submit { background-color: var(--primary-color); color: white; border: none; padding: 12px 30px; border-radius: 6px; font-weight: 600; cursor: pointer; transition: background 0.2s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-submit:hover { background-color: var(--primary-hover); }
        
        .specific-target-container { display: none; margin-top: 10px; padding: 15px; background: #f8f9fa; border-radius: 6px; border: 1px dashed #ddd; }
        
        @media (max-width: 900px) { .layout-container { flex-direction: column; } .sidebar { width: 100%; min-height: auto; } }
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
            <div class="form-card">
                <div class="page-header">
                    <h2><i class="fa fa-bullhorn"></i> Post New Announcement (Coordinator)</h2>
                    <p>Share important information with students and supervisors.</p>
                </div>
                
                <form action="" method="POST">
                    
                    <div class="form-group">
                        <label for="subject">Subject / Title <span style="color:red">*</span></label>
                        <input type="text" id="subject" name="subject" class="form-control" placeholder="Enter announcement subject..." required>
                    </div>

                    <div class="form-group">
                        <label for="receiver_type">Receiver <span style="color:red">*</span></label>
                        <select id="receiver_type" name="receiver_type" class="form-control" onchange="toggleSpecificTarget(this.value)">
                            <option value="All Students">All Students (System Wide)</option>
                            <option value="All Supervisors">All Supervisors</option>
                            <option value="specific">Specific Project Group (Any)</option>
                        </select>
                        
                        <div id="specific_target_container" class="specific-target-container">
                            <label for="specific_target" style="font-size:13px; color:#555;">Select Project / Team:</label>
                            <select id="specific_target" name="specific_target" class="form-control">
                                <?php if(empty($my_active_targets)): ?>
                                    <option value="" disabled>No active projects found.</option>
                                <?php else: ?>
                                    <?php foreach ($my_active_targets as $tgt): ?>
                                        <option value="<?php echo htmlspecialchars($tgt['value']); ?>">
                                            <?php echo htmlspecialchars($tgt['label']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description">Content / Message <span style="color:red">*</span></label>
                        <textarea id="description" name="description" class="form-control" placeholder="Type your message here..." required></textarea>
                    </div>

                    <button type="submit" class="btn-submit"><i class="fa fa-paper-plane"></i> Publish Announcement</button>
                </form>
            </div>
        </main>
    </div>

    <script>
        function toggleSpecificTarget(val) {
            const container = document.getElementById('specific_target_container');
            if (val === 'specific') {
                container.style.display = 'block';
            } else {
                container.style.display = 'none';
            }
        }
    </script>
</body>
</html>