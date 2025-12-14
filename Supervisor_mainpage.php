<?php
// ====================================================
// Supervisor_mainpage.php - 导师主页 (动态数据版)
// ====================================================

include("connect.php");

// 1. 基础验证
$auth_user_id = $_GET['auth_user_id'] ?? null;
$current_page = $_GET['page'] ?? 'dashboard';

// 安全检查
if (!$auth_user_id) { header("location: login.php"); exit; }

// 2. 数据查询 (获取导师资料)
$sv_data = [];
$user_name = "Supervisor"; 
$user_avatar = "image/user.png"; 
$sv_id = 0; // 初始化 Supervisor ID

if (isset($conn)) {
    // 获取 USER 表名字
    $sql_user = "SELECT fyp_username FROM `USER` WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_user)) {
        $stmt->bind_param("i", $auth_user_id); $stmt->execute();
        $res = $stmt->get_result(); if ($row = $res->fetch_assoc()) $user_name = $row['fyp_username'];
        $stmt->close();
    }
    
    // 获取 SUPERVISOR 详细资料
    $sql_sv = "SELECT * FROM supervisor WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_sv)) {
        $stmt->bind_param("i", $auth_user_id); $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $sv_data = $res->fetch_assoc();
            $sv_id = $sv_data['fyp_supervisorid']; // 获取关键 ID
            if (!empty($sv_data['fyp_name'])) $user_name = $sv_data['fyp_name'];
            if (!empty($sv_data['fyp_profileimg'])) $user_avatar = $sv_data['fyp_profileimg'];
        }
        $stmt->close();
    }
}

// ----------------------------------------------------
// 3. DASHBOARD 统计逻辑
// ----------------------------------------------------
$stats = [
    'total_students' => 0,
    'pending_req' => 0,
    'quota_used' => 0,
    'quota_limit' => 3 // 默认默认值
];
$active_projects_list = [];

if ($sv_id > 0) {
    // A. 获取 Quota 上限 (从 quota 表)
    $q_sql = "SELECT fyp_numofstudent FROM quota WHERE fyp_supervisorid = '$sv_id'";
    $q_res = $conn->query($q_sql);
    if ($q_res && $q_row = $q_res->fetch_assoc()) {
        $stats['quota_limit'] = intval($q_row['fyp_numofstudent']);
    }

    // B. 获取 Pending Requests 数量
    $p_sql = "SELECT COUNT(*) as cnt FROM project_request WHERE fyp_supervisorid = '$sv_id' AND fyp_requeststatus = 'Pending'";
    $p_res = $conn->query($p_sql);
    if ($p_res && $p_row = $p_res->fetch_assoc()) {
        $stats['pending_req'] = $p_row['cnt'];
    }

    // C. 获取 Active Projects (已注册的项目) & 计算 Used Quota & Total Students
    // 逻辑：Used Quota = 唯一 Project ID 的数量 (Group算1个, Individual算1个)
    // 逻辑：Total Students = 学生总人数
    
    // 联合查询：Project, Registration, Student
    $act_sql = "SELECT r.fyp_projectid, p.fyp_projecttitle, p.fyp_projecttype, 
                       GROUP_CONCAT(s.fyp_studname SEPARATOR ', ') as student_names,
                       COUNT(r.fyp_studid) as stud_count
                FROM fyp_registration r
                JOIN project p ON r.fyp_projectid = p.fyp_projectid
                JOIN student s ON r.fyp_studid = s.fyp_studid
                WHERE r.fyp_supervisorid = '$sv_id'
                GROUP BY r.fyp_projectid";
    
    $act_res = $conn->query($act_sql);
    if ($act_res) {
        while ($row = $act_res->fetch_assoc()) {
            $stats['quota_used']++; // 每有一个 active project，占用 1 个 quota
            $stats['total_students'] += $row['stud_count']; // 累加学生人数
            $active_projects_list[] = $row; // 存入列表用于展示
        }
    }
}

// 4. 定义菜单
$menu_items = [
    'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home', 'link' => 'Supervisor_mainpage.php?page=dashboard'],
    'profile'   => ['name' => 'My Profile', 'icon' => 'fa-user', 'link' => 'supervisor_profile.php'],
    'students'  => [
        'name' => 'My Students', 
        'icon' => 'fa-users',
        'sub_items' => [
            'project_requests' => ['name' => 'Project Requests', 'icon' => 'fa-envelope-open-text', 'link' => 'supervisor_projectreq.php'],
            'student_list'     => ['name' => 'Student List', 'icon' => 'fa-list', 'link' => '?page=student_list'],
        ]
    ],
    'fyp_project' => [
        'name' => 'FYP Project',
        'icon' => 'fa-project-diagram',
        'sub_items' => [
            'propose_project' => ['name' => 'Propose Project', 'icon' => 'fa-plus-circle', 'link' => 'supervisor_purpose.php'],
            'my_projects'     => ['name' => 'My Projects', 'icon' => 'fa-folder-open', 'link' => '?page=my_projects'],
        ]
    ],
    'schedule'  => ['name' => 'My Schedule', 'icon' => 'fa-calendar-alt', 'link' => '?page=schedule'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supervisor Dashboard</title>
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
        .submenu { list-style: none; padding: 0; margin: 0; background-color: #fafafa; display: none; }
        .menu-item.has-active-child .submenu, .menu-item:hover .submenu { display: block; }
        .submenu .menu-link { padding-left: 58px; font-size: 14px; padding-top: 10px; padding-bottom: 10px; }
        .main-content { flex: 1; display: flex; flex-direction: column; gap: 20px; }
        .welcome-card { background: #fff; padding: 30px; border-radius: 12px; box-shadow: var(--card-shadow); border-left: 5px solid var(--primary-color); }
        .page-title { font-size: 24px; margin: 0 0 10px 0; color: var(--text-color); }
        .info-cards-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; }
        .info-card { background: #fff; padding: 25px; border-radius: 12px; box-shadow: var(--card-shadow); display: flex; flex-direction: column; transition: transform 0.2s; border-left: 4px solid var(--student-accent); }
        .info-card:hover { transform: translateY(-3px); }
        .info-card h3 { margin: 0 0 15px 0; color: var(--student-accent); font-size: 16px; font-weight: 600; }
        .section-header { font-size: 18px; font-weight: 600; margin-bottom: 15px; color: #333; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .empty-state { text-align: center; padding: 40px; color: #999; }
        
        /* Table Styles */
        .data-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .data-table th { background: #f8f9fa; text-align: left; padding: 12px 15px; color: #555; font-size: 13px; font-weight: 600; border-bottom: 2px solid #eee; }
        .data-table td { padding: 12px 15px; border-bottom: 1px solid #f0f0f0; font-size: 14px; color: #333; }
        .data-table tr:last-child td { border-bottom: none; }
        .type-badge { padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .type-Group { background: #e3effd; color: #0056b3; }
        .type-Individual { background: #f3f3f3; color: #555; }

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
                <h1 class="page-title">Dashboard</h1>
                <p style="color: #666; margin: 0;">Welcome to your Supervisor Dashboard.</p>
            </div>
            
            <?php if ($current_page == 'dashboard'): ?>
                <div class="info-cards-grid">
                    <!-- 1. Total Students Count -->
                    <div class="info-card">
                        <h3><i class="fa fa-users"></i> Total Students</h3>
                        <p><?php echo $stats['total_students']; ?> Students</p>
                    </div>
                    
                    <!-- 2. Pending Requests Count -->
                    <div class="info-card" style="border-left-color: #d93025;">
                        <h3><i class="fa fa-clock"></i> Pending Requests</h3>
                        <p><?php echo $stats['pending_req']; ?> Requests</p>
                    </div>
                    
                    <!-- 3. Quota Usage (Based on Groups/Projects) -->
                    <div class="info-card" style="border-left-color: #28a745;">
                        <h3><i class="fa fa-clipboard-check"></i> Project Quota</h3>
                        <p><?php echo $stats['quota_used']; ?> / <?php echo $stats['quota_limit']; ?> Groups</p>
                    </div>
                </div>

                <h3 class="section-header" style="margin-top: 40px;">Active Supervision</h3>
                
                <?php if (count($active_projects_list) > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Project Title</th>
                                <th>Type</th>
                                <th>Students</th>
                                <th>Size</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($active_projects_list as $proj): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($proj['fyp_projecttitle']); ?></strong></td>
                                    <td><span class="type-badge type-<?php echo $proj['fyp_projecttype']; ?>"><?php echo $proj['fyp_projecttype']; ?></span></td>
                                    <td><?php echo htmlspecialchars($proj['student_names']); ?></td>
                                    <td><?php echo $proj['stud_count']; ?> Student(s)</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fa fa-folder-open" style="font-size: 48px; opacity:0.3; margin-bottom: 10px;"></i>
                        <p>No active projects currently under your supervision.</p>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div style="background: #fff; padding: 30px; border-radius: 12px; text-align: center;">
                    <i class="fa fa-info-circle" style="font-size: 32px; color: #ddd;"></i>
                    <p>Page Content for: <?php echo htmlspecialchars($current_page); ?></p>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>