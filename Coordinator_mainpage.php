<?php
// ====================================================
// Coordinator_mainpage.php - Student-Style UI
// ====================================================

include("connect.php");

// 1. Basic Verification
$auth_user_id = $_GET['auth_user_id'] ?? null;
$current_page = $_GET['page'] ?? 'dashboard';

// Security Check
if (!$auth_user_id) { header("location: login.php"); exit; }

// 2. Data Query (Coordinator Info)
$coor_data = [];
$user_name = "Coordinator"; 
$user_avatar = "image/user.png"; 
$my_staff_id = ""; 

if (isset($conn)) {
    // Get USER table info
    $sql_user = "SELECT fyp_username FROM `USER` WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_user)) {
        $stmt->bind_param("i", $auth_user_id); $stmt->execute();
        $res = $stmt->get_result(); if ($row = $res->fetch_assoc()) $user_name = $row['fyp_username'];
        $stmt->close();
    }
    
    // Get COORDINATOR details
    $sql_coor = "SELECT * FROM coordinator WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_coor)) {
        $stmt->bind_param("i", $auth_user_id); $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $coor_data = $res->fetch_assoc();
            $my_staff_id = $coor_data['fyp_staffid']; 
            
            if (!empty($coor_data['fyp_name'])) $user_name = $coor_data['fyp_name'];
            if (!empty($coor_data['fyp_profileimg'])) $user_avatar = $coor_data['fyp_profileimg'];
        }
        $stmt->close();
    }
}

// 3. DASHBOARD STATS LOGIC
$stats = [
    'total_students' => 0,
    'pending_req' => 0,
    'active_projects' => 0
];
$active_projects_list = [];

if (!empty($my_staff_id)) {
    // A. Pending Requests (Personal)
    $p_sql = "SELECT COUNT(*) as cnt 
              FROM project_request pr
              JOIN project p ON pr.fyp_projectid = p.fyp_projectid
              WHERE p.fyp_staffid = '$my_staff_id' 
              AND pr.fyp_requeststatus = 'Pending'";
    $p_res = $conn->query($p_sql);
    if ($p_res && $p_row = $p_res->fetch_assoc()) {
        $stats['pending_req'] = $p_row['cnt'];
    }

    // B. Active Projects & Students (Personal Supervision)
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
            $stats['active_projects']++;
            $stats['total_students'] += $row['stud_count']; 
            $active_projects_list[] = $row; 
        }
    }
}

// 4. Menu Definitions
$menu_items = [
    'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home', 'link' => 'Coordinator_mainpage.php?page=dashboard'],
    'profile'   => ['name' => 'My Profile', 'icon' => 'fa-user', 'link' => 'Coordinator_profile.php'], 
    
     'management' => [
        'name' => 'User Management',
        'icon' => 'fa-users-cog',
        'sub_items' => [
            'manage_students' => ['name' => 'Student List', 'icon' => 'fa-user-graduate', 'link' => 'Coordinator_manage_users.php?tab=student'],
            'manage_supervisors' => ['name' => 'Supervisor List', 'icon' => 'fa-chalkboard-teacher', 'link' => 'Coordinator_manage_users.php?tab=supervisor'],
            'manage_quota' => ['name' => 'Supervisor Quota', 'icon' => 'fa-chalkboard-teacher', 'link' => 'Coordinator_manage_quota.php'],
        ]
    ],
    
    'project_mgmt' => [
        'name' => 'Project Mgmt', 
        'icon' => 'fa-tasks', 
        'sub_items' => [
            'propose_project' => ['name' => 'Propose Project', 'icon' => 'fa-plus-circle', 'link' => 'Coordinator_purpose.php'],
            'project_requests' => ['name' => 'Project Requests', 'icon' => 'fa-envelope-open-text', 'link' => 'Coordinator_projectreq.php'],
            'project_list' => ['name' => 'All Projects & Groups', 'icon' => 'fa-list-alt', 'link' => 'Coordinator_manage_project.php'],
        ]
    ],
    'assessment' => [
        'name' => 'Assessment', 
        'icon' => 'fa-clipboard-check', 
        'sub_items' => [
            'propose_assignment' => ['name' => 'Create Assignment', 'icon' => 'fa-plus', 'link' => 'Coordinator_assignment_purpose.php'],
            'grade_assignment' => ['name' => 'Grade Assignments', 'icon' => 'fa-check-square', 'link' => 'Coordinator_assignment_grade.php'], 
        ]
    ],
    'announcements' => [
        'name' => 'Announcements', 
        'icon' => 'fa-bullhorn', 
        'sub_items' => [
            'post_announcement' => ['name' => 'Post New', 'icon' => 'fa-pen', 'link' => 'Coordinator_announcement.php'], 
            'view_announcements' => ['name' => 'View History', 'icon' => 'fa-history', 'link' => 'Coordinator_announcement_view.php'],
        ]
    ],
    'schedule' => ['name' => 'My Schedule', 'icon' => 'fa-calendar-alt', 'link' => 'Coordinator_meeting.php'], 
    'allocation' => ['name' => 'Moderator Allocation', 'icon' => 'fa-people-arrows', 'link' => 'Coordinator_allocation.php'],
   
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coordinator Dashboard</title>
    <link rel="icon" type="image/png" href="image/ladybug.png?v=<?php echo time(); ?>">
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

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            background-color: #f4f6f9;
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
            color: var(--text-color);
        }

        /* Student-Style Sidebar */
        .main-menu {
            background: var(--sidebar-bg);
            border-right: 1px solid rgba(255,255,255,0.1);
            position: fixed;
            top: 0;
            bottom: 0;
            height: 100%;
            left: 0;
            width: 60px;
            overflow: hidden;
            transition: width .05s linear;
            z-index: 1000;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }

        .main-menu:hover, nav.main-menu.expanded {
            width: 250px;
            overflow: visible;
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
            padding: 0; /* Reset */
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

        /* Dropdown/Submenu Styling */
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
            padding-left: 0;
            height: 40px;
        }
        
        .submenu .nav-text {
            padding-left: 20px;
            font-size: 13px;
        }

        /* Main Content */
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

        .user-section {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-badge {
            font-size: 13px;
            color: #666;
            background: #f0f0f0;
            padding: 5px 10px;
            border-radius: 20px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .user-avatar-placeholder {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #0056b3;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
            transition: transform 0.2s;
            border-left: 4px solid transparent;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }

        .stat-icon {
            position: absolute;
            right: 20px;
            top: 20px;
            font-size: 40px;
            opacity: 0.1;
        }

        /* Data Table */
        .section-header {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .data-table-wrapper {
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            background: #f8f9fa;
            padding: 15px 20px;
            text-align: left;
            font-weight: 600;
            color: #555;
            font-size: 13px;
            border-bottom: 1px solid #eee;
        }

        .data-table td {
            padding: 15px 20px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
            color: #333;
        }

        .data-table tr:hover {
            background: #fcfcfc;
        }

        .badge {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-Group { background: #e3effd; color: #0056b3; }
        .badge-Individual { background: #f3f3f3; color: #555; }

        .empty-state {
            text-align: center;
            padding: 50px;
            color: #999;
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

    <!-- Sidebar -->
    <nav class="main-menu">
        <ul>
            <?php foreach ($menu_items as $key => $item): ?>
                <?php 
                    $isActive = ($key == $current_page);
                    $hasSubmenu = isset($item['sub_items']);
                    $activeClass = $isActive ? 'active' : '';
                    
                    $linkUrl = isset($item['link']) ? $item['link'] : "#";
                    if ($linkUrl !== "#" && strpos($linkUrl, '.php') !== false) {
                         $separator = (strpos($linkUrl, '?') !== false) ? '&' : '?';
                         $linkUrl .= $separator . "auth_user_id=" . urlencode($auth_user_id);
                    }
                    
                    // Check if child active (simple logic)
                    $childActive = false;
                    if ($hasSubmenu) {
                        // In a real app, check $_GET/$_SERVER against sub items
                    }
                ?>
                <li class="menu-item <?php echo ($isActive || $childActive) ? 'open' : ''; ?>">
                    <a href="<?php echo $hasSubmenu ? 'javascript:void(0)' : $linkUrl; ?>" 
                       class="<?php echo $activeClass; ?>"
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
                                $subLink = $sub_item['link'];
                                if (strpos($subLink, '.php') !== false) {
                                    $sep = (strpos($subLink, '?') !== false) ? '&' : '?';
                                    $subLink .= $sep . "auth_user_id=" . urlencode($auth_user_id);
                                }
                            ?>
                                <li>
                                    <a href="<?php echo $subLink; ?>">
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

    <!-- Main Content -->
    <div class="main-content-wrapper">
        <div class="page-header">
            <div class="welcome-text">
                <h1>Dashboard</h1>
                <p>Welcome, Coordinator <strong><?php echo htmlspecialchars($user_name); ?></strong>.</p>
            </div>
            
            <div class="logo-section">
                <img src="image/ladybug.png?v=<?php echo time(); ?>" alt="Logo" class="logo-img">
                <span class="system-title">FYP Portal</span>
            </div>

            <div class="user-section">
                <span class="user-badge">Coordinator</span>
                <?php if(!empty($user_avatar) && $user_avatar !== 'image/user.png'): ?>
                    <img src="<?php echo htmlspecialchars($user_avatar); ?>" class="user-avatar" alt="Avatar">
                <?php else: ?>
                    <div class="user-avatar-placeholder">
                        <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($current_page == 'dashboard'): ?>
            
            <div class="stats-grid">
                <div class="stat-card" style="border-left-color: #0056b3;">
                    <h3>My Students</h3>
                    <div class="stat-value"><?php echo $stats['total_students']; ?></div>
                    <i class="fa fa-users stat-icon" style="color: #0056b3;"></i>
                </div>
                <div class="stat-card" style="border-left-color: #ffc107;">
                    <h3>Pending Requests</h3>
                    <div class="stat-value"><?php echo $stats['pending_req']; ?></div>
                    <i class="fa fa-envelope-open-text stat-icon" style="color: #ffc107;"></i>
                </div>
                <div class="stat-card" style="border-left-color: #28a745;">
                    <h3>Active Projects</h3>
                    <div class="stat-value"><?php echo $stats['active_projects']; ?></div>
                    <i class="fa fa-project-diagram stat-icon" style="color: #28a745;"></i>
                </div>
            </div>

            <div class="section-header">
                <i class="fa fa-list-alt" style="color: var(--primary-color);"></i>
                My Active Supervision
            </div>

            <div class="data-table-wrapper">
                <?php if (count($active_projects_list) > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Project Title</th>
                                <th>Type</th>
                                <th>Academic Session</th>
                                <th>Students</th>
                                <th>Size</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($active_projects_list as $proj): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($proj['fyp_projecttitle']); ?></strong></td>
                                    <td><span class="badge badge-<?php echo $proj['fyp_projecttype']; ?>"><?php echo $proj['fyp_projecttype']; ?></span></td>
                                    <td>
                                        <?php echo htmlspecialchars($proj['fyp_acdyear']); ?> 
                                        <span style="color:#999; font-size:12px;">(<?php echo htmlspecialchars($proj['fyp_intake']); ?>)</span>
                                    </td>
                                    <td><?php echo htmlspecialchars($proj['student_names']); ?></td>
                                    <td><?php echo $proj['stud_count']; ?> Student(s)</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fa fa-folder-open" style="font-size: 48px; opacity:0.3; margin-bottom: 15px;"></i>
                        <p>No active projects currently under your personal supervision.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="empty-state" style="background: white; border-radius: 12px; box-shadow: var(--card-shadow);">
                <p>Content for: <?php echo htmlspecialchars($current_page); ?></p>
            </div>
        <?php endif; ?>

    </div>

    <script>
        function toggleSubmenu(element) {
            const parent = element.parentElement;
            parent.classList.toggle('open');
        }
    </script>
</body>
</html>