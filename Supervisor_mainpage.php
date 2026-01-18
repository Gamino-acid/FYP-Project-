<?php
// ====================================================
// Supervisor_mainpage.php - Dropdown Sidebar Version
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
$sv_id = 0; 
$my_staff_id = "";

if (isset($conn)) {
    $sql_user = "SELECT fyp_username FROM `USER` WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_user)) {
        $stmt->bind_param("i", $auth_user_id); $stmt->execute();
        $res = $stmt->get_result(); if ($row = $res->fetch_assoc()) $user_name = $row['fyp_username'];
        $stmt->close();
    }
    
    $sql_sv = "SELECT * FROM supervisor WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_sv)) {
        $stmt->bind_param("i", $auth_user_id); $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $sv_data = $res->fetch_assoc();
            $sv_id = $sv_data['fyp_supervisorid']; 
            $my_staff_id = $sv_data['fyp_staffid']; 
            
            if (!empty($sv_data['fyp_name'])) $user_name = $sv_data['fyp_name'];
            if (!empty($sv_data['fyp_profileimg'])) $user_avatar = $sv_data['fyp_profileimg'];
        }
        $stmt->close();
    }
}

// 3. DASHBOARD 统计逻辑
$stats = [
    'total_students' => 0,
    'pending_req' => 0,
    'quota_used' => 0,
    'quota_limit' => 3 
];
$active_projects_list = [];

if (!empty($my_staff_id)) {
    $q_sql = "SELECT fyp_numofstudent FROM quota WHERE fyp_staffid = '$my_staff_id'";
    $q_res = $conn->query($q_sql);
    if ($q_res && $q_row = $q_res->fetch_assoc()) {
        $stats['quota_limit'] = intval($q_row['fyp_numofstudent']);
    }

    $p_sql = "SELECT COUNT(*) as cnt 
              FROM project_request pr
              JOIN project p ON pr.fyp_projectid = p.fyp_projectid
              WHERE p.fyp_staffid = '$my_staff_id' 
              AND pr.fyp_requeststatus = 'Pending'";
              
    $p_res = $conn->query($p_sql);
    if ($p_res && $p_row = $p_res->fetch_assoc()) {
        $stats['pending_req'] = $p_row['cnt'];
    }

    $act_sql = "SELECT r.fyp_projectid, 
                       p.fyp_projecttitle, 
                       p.fyp_projecttype, 
                       ay.fyp_acdyear, 
                       ay.fyp_intake,
                       GROUP_CONCAT(s.fyp_studname SEPARATOR ', ') as student_names,
                       COUNT(r.fyp_studid) as stud_count
                FROM fyp_registration r
                JOIN project p ON r.fyp_projectid = p.fyp_projectid
                JOIN student s ON r.fyp_studid = s.fyp_studid
                LEFT JOIN academic_year ay ON p.fyp_academicid = ay.fyp_academicid
                WHERE p.fyp_staffid = '$my_staff_id'
                AND (r.fyp_archive_status = 'Active' OR r.fyp_archive_status IS NULL)
                GROUP BY r.fyp_projectid";
    
    $act_res = $conn->query($act_sql);
    if ($act_res) {
        while ($row = $act_res->fetch_assoc()) {
            $stats['quota_used']++;
            $stats['total_students'] += $row['stud_count']; 
            $active_projects_list[] = $row; 
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
            'student_list'     => ['name' => 'Student List', 'icon' => 'fa-list', 'link' => 'Supervisor_student_list.php'],
        ]
    ],
    'fyp_project' => [
        'name' => 'FYP Project',
        'icon' => 'fa-project-diagram',
        'sub_items' => [
            'propose_project' => ['name' => 'Propose Project', 'icon' => 'fa-plus-circle', 'link' => 'supervisor_purpose.php'],
            'my_projects'     => ['name' => 'My Projects', 'icon' => 'fa-folder-open', 'link' => 'Supervisor_manage_project.php'],
            'propose_assignment' => ['name' => 'Propose Assignment', 'icon' => 'fa-tasks','link' => 'supervisor_assignment_purpose.php'],
        ]
    ],
    'grading' => [
        'name' => 'Assessment',
        'icon' => 'fa-marker',
        'sub_items' => [
            'grade_assignment' => ['name' => 'Grade Assignments', 'icon' => 'fa-check-square', 'link' => 'Supervisor_assignment_grade.php'],
            'grade_mod' => ['name' => 'Moderator Grading', 'icon' => 'fa-gavel', 'link' => 'Moderator_assignment_grade.php'],
        ]
    ],
    'announcement' => [
        'name' => 'Announcement',
        'icon' => 'fa-bullhorn',
        'sub_items' => [
            'post_announcement' => ['name' => 'Post Announcement', 'icon' => 'fa-pen-square', 'link' => 'supervisor_announcement.php'],
            'view_announcements' => ['name' => 'View History', 'icon' => 'fa-history', 'link' => 'Supervisor_announcement_view.php'],
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
    <title>Supervisor Dashboard</title>
    <link rel="icon" type="image/png" href="image/ladybug.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap');
        
        :root {
            --primary-color: #0056b3;
            --primary-hover: #004494;
            --secondary-color: #f8f9fa;
            --text-color: #333;
            --sidebar-bg: #004085; 
            --sidebar-hover: #003366;
            --sidebar-text: #e0e0e0;
            --card-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }

        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            background-color: #f4f6f9;
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
        }

        /* Sidebar with Dropdown */
        .main-menu {
            background: var(--sidebar-bg);
            border-right: 1px solid rgba(255,255,255,0.1);
            position: fixed;
            top: 0;
            bottom: 0;
            height: 100%;
            left: 0;
            width: 60px;
            overflow-y: auto;
            overflow-x: hidden;
            transition: width .05s linear;
            z-index: 1000;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }

        .main-menu:hover, nav.main-menu.expanded {
            width: 250px;
        }

        .main-menu > ul {
            margin: 7px 0;
            padding: 0;
            list-style: none;
        }

        .main-menu li {
            position: relative;
            display: block;
            width: 250px;
        }

        .main-menu li > a {
            position: relative;
            display: table;
            border-collapse: collapse;
            border-spacing: 0;
            color: var(--sidebar-text);
            font-size: 14px;
            text-decoration: none;
            transition: all .1s linear;
            width: 100%;
        }

        .main-menu .nav-icon {
            position: relative;
            display: table-cell;
            width: 60px;
            height: 46px; 
            text-align: center;
            vertical-align: middle;
            font-size: 18px;
        }

        .main-menu .nav-text {
            position: relative;
            display: table-cell;
            vertical-align: middle;
            width: 190px;
            padding-left: 10px;
            white-space: nowrap;
        }

        .main-menu li:hover > a, nav.main-menu li.active > a {
            color: #fff;
            background-color: var(--sidebar-hover);
            border-left: 4px solid #fff; 
        }

        .main-menu > ul.logout {
            position: absolute;
            left: 0;
            bottom: 0;
            width: 100%;
        }

        /* Dropdown Arrow */
        .dropdown-arrow {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            transition: transform 0.3s;
            font-size: 12px;
        }

        .menu-item.open .dropdown-arrow {
            transform: translateY(-50%) rotate(180deg);
        }

        /* Submenu Dropdown Styles */
        .submenu {
            list-style: none;
            padding: 0;
            margin: 0;
            background-color: rgba(0,0,0,0.2);
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }

        .menu-item.open .submenu {
            max-height: 500px;
            transition: max-height 0.5s ease-in;
        }

        .submenu li > a {
            padding-left: 70px !important;
            font-size: 13px;
            height: 40px;
        }

        .submenu .nav-icon {
            width: 50px;
            font-size: 14px;
        }

        .menu-item > a {
            cursor: pointer;
        }
        
        /* Main Content Wrapper */
        .main-content-wrapper {
            margin-left: 60px;
            flex: 1;
            padding: 20px;
            width: calc(100% - 60px);
            transition: margin-left .05s linear;
        }
        
        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
        }
        
        .welcome-text h1 {
            margin: 0;
            font-size: 24px;
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .welcome-text p {
            margin: 5px 0 0;
            color: #666;
            font-size: 14px;
        }

        /* Logo Section */
        .logo-section {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .logo-img {
            height: 40px;
            width: auto;
            background: white;
            padding: 2px;
            border-radius: 6px;
        }
        
        .system-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--primary-color);
            letter-spacing: 0.5px;
        }

        /* Info Cards Grid */
        .info-cards-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); 
            gap: 20px; 
            margin-bottom: 30px; 
        }
        
        .info-card { 
            background: #fff; 
            padding: 25px; 
            border-radius: 12px; 
            box-shadow: var(--card-shadow); 
            display: flex; 
            flex-direction: column; 
            transition: transform 0.2s; 
            border-left: 4px solid var(--primary-color); 
        }
        
        .info-card:hover { 
            transform: translateY(-3px); 
        }
        
        .info-card h3 { 
            margin: 0 0 15px 0; 
            color: var(--primary-color); 
            font-size: 16px; 
            font-weight: 600; 
            display: flex; 
            align-items: center; 
            gap: 10px; 
        }

        .info-card p {
            font-size: 15px;
            color: #555;
            margin: 0;
        }

        /* Section Header */
        .section-header { 
            font-size: 18px; 
            font-weight: 600; 
            margin-bottom: 15px; 
            color: #333; 
            border-bottom: 2px solid #eee; 
            padding-bottom: 10px; 
        }
        
        /* Empty State */
        .empty-state { 
            text-align: center; 
            padding: 40px; 
            color: #999; 
            background: white; 
            border-radius: 12px; 
            box-shadow: var(--card-shadow); 
        }
        
        /* Table Styles */
        .data-table { 
            width: 100%; 
            border-collapse: collapse; 
            background: #fff; 
            border-radius: 8px; 
            overflow: hidden; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.05); 
        }
        
        .data-table th { 
            background: #f8f9fa; 
            text-align: left; 
            padding: 12px 15px; 
            color: #555; 
            font-size: 13px; 
            font-weight: 600; 
            border-bottom: 2px solid #eee; 
        }
        
        .data-table td { 
            padding: 12px 15px; 
            border-bottom: 1px solid #f0f0f0; 
            font-size: 14px; 
            color: #333; 
        }
        
        .data-table tr:last-child td { 
            border-bottom: none; 
        }
        
        .type-badge { 
            padding: 3px 8px; 
            border-radius: 12px; 
            font-size: 11px; 
            font-weight: 600; 
            text-transform: uppercase; 
        }
        
        .type-Group { 
            background: #e3effd; 
            color: #0056b3; 
        }
        
        .type-Individual { 
            background: #f3f3f3; 
            color: #555; 
        }

        @media (max-width: 900px) { 
            .main-content-wrapper { 
                margin-left: 0; 
                width: 100%; 
            } 
        }
    </style>
</head>
<body>

    <!-- Sidebar Navigation with Dropdown -->
    <nav class="main-menu">
        <ul>
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
                    
                    $hasSubmenu = isset($item['sub_items']);
                ?>
                <li class="menu-item <?php echo $hasActiveChild ? 'open' : ''; ?>">
                    <a href="<?php echo $hasSubmenu ? 'javascript:void(0)' : $linkUrl; ?>" 
                       class="<?php echo $isActive ? 'active' : ''; ?>"
                       <?php if ($hasSubmenu): ?>onclick="toggleSubmenu(this)"<?php endif; ?>>
                        <i class="fa <?php echo $item['icon']; ?> nav-icon"></i>
                        <span class="nav-text"><?php echo $item['name']; ?></span>
                        <?php if ($hasSubmenu): ?>
                            <i class="fa fa-chevron-down dropdown-arrow"></i>
                        <?php endif; ?>
                    </a>
                    
                    <?php if ($hasSubmenu): ?>
                        <ul class="submenu">
                            <?php foreach ($item['sub_items'] as $sub_key => $sub_item): 
                                $subLinkUrl = isset($sub_item['link']) ? $sub_item['link'] : "#";
                                if ($subLinkUrl !== "#") {
                                    $separator = (strpos($subLinkUrl, '?') !== false) ? '&' : '?';
                                    $subLinkUrl .= $separator . "auth_user_id=" . urlencode($auth_user_id);
                                }
                            ?>
                                <li>
                                    <a href="<?php echo $subLinkUrl; ?>" class="<?php echo ($sub_key == $current_page) ? 'active' : ''; ?>">
                                        <i class="fa <?php echo $sub_item['icon']; ?> nav-icon"></i>
                                        <span class="nav-text"><?php echo $sub_item['name']; ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <ul class="logout">
            <li>
                <a href="login.php">
                    <i class="fa fa-power-off nav-icon"></i>
                    <span class="nav-text">Logout</span>
                </a>
            </li>  
        </ul>
    </nav>

    <!-- Main Content Area -->
    <div class="main-content-wrapper">
        
        <!-- Page Header -->
        <div class="page-header">
            <div class="welcome-text">
                <h1>Dashboard</h1>
                <p>Welcome back, <?php echo htmlspecialchars($user_name); ?></p>
            </div>
            
            <div class="logo-section">
                <img src="image/ladybug.png" alt="Logo" class="logo-img">
                <span class="system-title">FYP Portal</span>
            </div>

            <div style="display:flex; align-items:center; gap:10px;">
                <span style="font-size:13px; color:#666; background:#f0f0f0; padding:5px 10px; border-radius:20px;">Lecturer</span>
                <?php if(!empty($user_avatar) && $user_avatar != 'image/user.png'): ?>
                    <img src="<?php echo htmlspecialchars($user_avatar); ?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
                <?php else: ?>
                    <div style="width:40px;height:40px;border-radius:50%;background:#0056b3;color:white;display:flex;align-items:center;justify-content:center;font-weight:bold;">
                        <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Dashboard Content -->
        <?php if ($current_page == 'dashboard'): ?>
            <div class="info-cards-grid">
                <div class="info-card">
                    <h3><i class="fa fa-users"></i> Total Students</h3>
                    <p><?php echo $stats['total_students']; ?> Students</p>
                </div>
                
                <div class="info-card" style="border-left-color: #d93025;">
                    <h3 style="color: #d93025;"><i class="fa fa-clock"></i> Pending Requests</h3>
                    <p><?php echo $stats['pending_req']; ?> Requests</p>
                </div>
                
                <div class="info-card" style="border-left-color: #28a745;">
                    <h3 style="color: #28a745;"><i class="fa fa-clipboard-check"></i> Project Quota</h3>
                    <p><?php echo $stats['quota_used']; ?> / <?php echo $stats['quota_limit']; ?> Groups</p>
                </div>
            </div>

            <h3 class="section-header" style="margin-top: 20px;">Active Supervision</h3>
            
            <?php if (count($active_projects_list) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Project Title</th>
                            <th>Type</th>
                            <th>Academic Year</th> 
                            <th>Students</th>
                            <th>Size</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($active_projects_list as $proj): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($proj['fyp_projecttitle']); ?></strong></td>
                                <td><span class="type-badge type-<?php echo $proj['fyp_projecttype']; ?>"><?php echo $proj['fyp_projecttype']; ?></span></td>
                                
                                <td>
                                    <?php echo htmlspecialchars($proj['fyp_acdyear']); ?> 
                                    <span style="font-size:12px; color:#888;">(<?php echo htmlspecialchars($proj['fyp_intake']); ?>)</span>
                                </td>
                                
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
            <div class="empty-state">
                <i class="fa fa-wrench" style="font-size: 48px; color: #ddd; margin-bottom: 20px;"></i>
                <p><strong><?php echo ucfirst($current_page); ?></strong> module content appears here.</p>
            </div>
        <?php endif; ?>

    </div>

    <script>
        function toggleSubmenu(element) {
            const menuItem = element.parentElement;
            const isOpen = menuItem.classList.contains('open');
            
            // Close all other submenus
            document.querySelectorAll('.menu-item').forEach(item => {
                if (item !== menuItem) {
                    item.classList.remove('open');
                }
            });
            
            // Toggle current submenu
            if (isOpen) {
                menuItem.classList.remove('open');
            } else {
                menuItem.classList.add('open');
            }
        }
    </script>

</body>
</html>