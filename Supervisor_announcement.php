<?php
// ====================================================
// supervisor_announcement.php - 发布公告页面 (Targeted)
// ====================================================

include("connect.php");

// 1. 基础验证
$auth_user_id = $_GET['auth_user_id'] ?? null;
// 手动设置当前页面为 post_announcement 以高亮菜单
$current_page = 'post_announcement'; 

// 安全检查
if (!$auth_user_id) { 
    echo "<script>alert('Session Error. Please Login.'); window.location.href='login.php';</script>";
    exit; 
}

// 2. 获取导师信息 & 负责的项目列表 (Active Projects)
$user_name = "Supervisor"; 
$user_avatar = "image/user.png"; 
$sv_name_for_post = "Supervisor";
$sv_id = 0;
$my_active_targets = []; // 用于存储可选的发送对象

if (isset($conn)) {
    $sql_sv = "SELECT * FROM supervisor WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_sv)) {
        $stmt->bind_param("i", $auth_user_id); $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $sv_id = $row['fyp_supervisorid'];
            if (!empty($row['fyp_name'])) {
                $user_name = $row['fyp_name'];
                $sv_name_for_post = $row['fyp_name']; 
            }
            if (!empty($row['fyp_profileimg'])) $user_avatar = $row['fyp_profileimg'];
        }
        $stmt->close();
    }

    // 获取该导师负责的所有 Active Projects (用于特定发送)
    // 我们获取 Project ID, Title, Type 和 学生名单
    if ($sv_id > 0) {
        $sql_targets = "SELECT p.fyp_projecttitle, p.fyp_projecttype, 
                               GROUP_CONCAT(s.fyp_studname SEPARATOR ', ') as students
                        FROM fyp_registration r
                        JOIN project p ON r.fyp_projectid = p.fyp_projectid
                        JOIN student s ON r.fyp_studid = s.fyp_studid
                        WHERE r.fyp_supervisorid = ?
                        GROUP BY r.fyp_projectid";
        
        if ($stmt_t = $conn->prepare($sql_targets)) {
            $stmt_t->bind_param("i", $sv_id);
            $stmt_t->execute();
            $res_t = $stmt_t->get_result();
            while ($row_t = $res_t->fetch_assoc()) {
                // 格式化显示名称: "[Group] Project Title (Alice, Bob)" 或 "[Indi] Project Title (Charlie)"
                $type_tag = ($row_t['fyp_projecttype'] == 'Group') ? '[Group]' : '[Indiv]';
                $label = $type_tag . " " . substr($row_t['fyp_projecttitle'], 0, 30) . "... (" . $row_t['students'] . ")";
                
                // 这里的 value 我们存入特定的格式，方便之后识别
                // 我们直接存 "Target: {Project Title}" 或者简单的标识
                // 为了兼容现有结构，我们将 value 设为一段描述性文本，例如 "Project: AI Chatbot Team"
                $value = "Project: " . $row_t['fyp_projecttitle']; 
                $my_active_targets[] = ['label' => $label, 'value' => $value];
            }
            $stmt_t->close();
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
    
    // 确定最终的 receiver 字符串
    $final_receiver = "";
    if ($receiver_type == 'specific') {
        $final_receiver = $_POST['specific_target']; // 例如 "Project: AI System"
    } else {
        $final_receiver = $receiver_type; // "All Students" 或 "My Supervisees"
    }

    $sql_insert = "INSERT INTO announcement (fyp_subject, fyp_description, fyp_receiver, fyp_datecreated, fyp_supervisorid) VALUES (?, ?, ?, ?, ?)";
    
    if ($stmt = $conn->prepare($sql_insert)) {
        $stmt->bind_param("sssss", $subject, $description, $final_receiver, $date_now, $sv_name_for_post);
        if ($stmt->execute()) {
            echo "<script>alert('Announcement posted successfully to: $final_receiver'); window.location.href='Supervisor_mainpage.php?auth_user_id=" . $auth_user_id . "';</script>";
        } else {
            echo "<script>alert('Error posting announcement: " . $stmt->error . "');</script>";
        }
        $stmt->close();
    } else {
        echo "<script>alert('Database Error: " . $conn->error . "');</script>";
    }
}

// 4. 菜单定义 (保持一致)
$menu_items = [
    'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home', 'link' => 'Supervisor_mainpage.php?page=dashboard'],
    'profile'   => ['name' => 'My Profile', 'icon' => 'fa-user', 'link' => 'supervisor_profile.php'],
    'students'  => [
        'name' => 'My Students', 
        'icon' => 'fa-users',
        'sub_items' => [
            'project_requests' => ['name' => 'Project Requests', 'icon' => 'fa-envelope-open-text', 'link' => 'supervisor_projectreq.php'],
            'student_list'     => ['name' => 'Student List', 'icon' => 'fa-list', 'link' => 'Supervisor_mainpage.php?page=student_list'],
        ]
    ],
    'fyp_project' => [
        'name' => 'FYP Project',
        'icon' => 'fa-project-diagram',
        'sub_items' => [
            'propose_project' => ['name' => 'Propose Project', 'icon' => 'fa-plus-circle', 'link' => 'supervisor_purpose.php'],
            'my_projects'     => ['name' => 'My Projects', 'icon' => 'fa-folder-open', 'link' => 'Supervisor_mainpage.php?page=my_projects'],
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
    'schedule'  => ['name' => 'My Schedule', 'icon' => 'fa-calendar-alt', 'link' => 'Supervisor_mainpage.php?page=schedule'],
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
                <span class="user-role-badge">Lecturer</span>
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
                        $isActive = ($key == $current_page); // 这里检查主菜单
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
                    <h2><i class="fa fa-bullhorn"></i> Post New Announcement</h2>
                    <p>Share updates, deadlines, or important information with your students.</p>
                </div>
                
                <form action="" method="POST">
                    
                    <div class="form-group">
                        <label for="subject">Subject / Title <span style="color:red">*</span></label>
                        <input type="text" id="subject" name="subject" class="form-control" placeholder="Enter announcement subject..." required>
                    </div>

                    <div class="form-group">
                        <label for="receiver_type">Receiver <span style="color:red">*</span></label>
                        <select id="receiver_type" name="receiver_type" class="form-control" onchange="toggleSpecificTarget(this.value)">
                            <option value="All Students">All Students (Public)</option>
                            <option value="My Supervisees">All My Supervisees</option>
                            <option value="specific">Specific Project / Team</option>
                        </select>
                        
                        <!-- 二级联动菜单：Specific Target -->
                        <div id="specific_target_container" class="specific-target-container">
                            <label for="specific_target" style="font-size:13px; color:#555;">Select Project / Team:</label>
                            <select id="specific_target" name="specific_target" class="form-control">
                                <?php if(empty($my_active_targets)): ?>
                                    <option value="" disabled>No active projects found under your supervision.</option>
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