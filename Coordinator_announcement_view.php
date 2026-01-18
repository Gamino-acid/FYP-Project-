<?php
include("connect.php");

$auth_user_id = $_GET['auth_user_id'] ?? null;
$current_page = 'announcements'; 

if (!$auth_user_id) { header("location: login.php"); exit; }

$user_name = "Coordinator"; 
$user_avatar = "image/user.png"; 
$my_staff_id = "";

if (isset($conn)) {
    $sql_coor = "SELECT * FROM coordinator WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_coor)) {
        $stmt->bind_param("i", $auth_user_id); $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $my_staff_id = !empty($row['fyp_staffid']) ? $row['fyp_staffid'] : $row['fyp_coordinatorid'];
            
            if (!empty($row['fyp_name'])) $user_name = $row['fyp_name'];
            if (!empty($row['fyp_profileimg'])) $user_avatar = $row['fyp_profileimg'];
        }
        $stmt->close();
    }
}

$swal_payload = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    if (isset($_POST['edit_announcement'])) {
        $anno_id = $_POST['announce_id'];
        $subject = $_POST['edit_subject'];
        $description = $_POST['edit_description'];
        
        $sql_upd = "UPDATE announcement SET fyp_subject = ?, fyp_description = ? WHERE fyp_annouceid = ?";
        if ($stmt = $conn->prepare($sql_upd)) {
            $stmt->bind_param("ssi", $subject, $description, $anno_id);
            if ($stmt->execute()) {
                $swal_payload = [
                    'icon' => 'success',
                    'title' => 'Updated!',
                    'text' => 'Announcement updated successfully.',
                    'redirect' => "Coordinator_announcement_view.php?auth_user_id=" . urlencode($auth_user_id)
                ];
            }
            $stmt->close();
        }
    }

    if (isset($_POST['toggle_status'])) {
        $anno_id = $_POST['announce_id'];
        $current_status = $_POST['current_status'];
        $new_status = ($current_status == 'Active') ? 'Archived' : 'Active';
        
        $sql_stat = "UPDATE announcement SET fyp_status = ? WHERE fyp_annouceid = ?";
        if ($stmt = $conn->prepare($sql_stat)) {
            $stmt->bind_param("si", $new_status, $anno_id);
            if ($stmt->execute()) {
                $action_text = ($new_status == 'Active') ? 'Activated' : 'Archived';
                $swal_payload = [
                    'icon' => 'success',
                    'title' => $action_text . '!',
                    'text' => "Announcement has been " . strtolower($action_text) . ".",
                    'redirect' => "Coordinator_announcement_view.php?auth_user_id=" . urlencode($auth_user_id)
                ];
            }
            $stmt->close();
        }
    }

    if (isset($_POST['delete_announcement'])) {
        $anno_id = $_POST['announce_id'];
        $sql_del = "DELETE FROM announcement WHERE fyp_annouceid = ?";
        if ($stmt = $conn->prepare($sql_del)) {
            $stmt->bind_param("i", $anno_id);
            if ($stmt->execute()) {
                $swal_payload = [
                    'icon' => 'success',
                    'title' => 'Deleted!',
                    'text' => 'Announcement has been permanently deleted.',
                    'redirect' => "Coordinator_announcement_view.php?auth_user_id=" . urlencode($auth_user_id)
                ];
            }
            $stmt->close();
        }
    }
}

$view_mode = $_GET['view'] ?? 'mine';
$filter_year = $_GET['filter_year'] ?? '';
$search_name = $_GET['search_name'] ?? '';

$announcements = [];
$years_available = [];
$year_sql = "SELECT DISTINCT YEAR(fyp_datecreated) as yr FROM announcement ORDER BY yr DESC";
$res_y = $conn->query($year_sql);
if($res_y) {
    while($row_y = $res_y->fetch_assoc()) $years_available[] = $row_y['yr'];
}

$sql_list = "SELECT a.*, 
                    COALESCE(s.fyp_name, c.fyp_name, a.fyp_staffid) as author_name 
             FROM announcement a
             LEFT JOIN supervisor s ON a.fyp_staffid = s.fyp_staffid
             LEFT JOIN coordinator c ON a.fyp_staffid = c.fyp_staffid
             WHERE 1=1 ";

$params = [];
$types = "";

if ($view_mode == 'mine') {
    $sql_list .= " AND a.fyp_staffid = ? ";
    $params[] = $my_staff_id;
    $types .= "s";
} else {
    if (!empty($filter_year)) {
        $sql_list .= " AND YEAR(a.fyp_datecreated) = ? ";
        $params[] = $filter_year;
        $types .= "s";
    }

    if (!empty($search_name)) {
        $sql_list .= " AND (s.fyp_name LIKE ? OR c.fyp_name LIKE ?) ";
        $search_term = "%" . $search_name . "%";
        $params[] = $search_term;
        $params[] = $search_term;
        $types .= "ss";
    }
}

$sql_list .= " ORDER BY a.fyp_datecreated DESC";

if ($stmt = $conn->prepare($sql_list)) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        if (!isset($row['fyp_status'])) $row['fyp_status'] = 'Active'; 
        $row['is_mine'] = ($row['fyp_staffid'] == $my_staff_id);
        $announcements[] = $row;
    }
    $stmt->close();
}

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
        'name' => 'Project Manage', 
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
    'data_mgmt' => ['name' => 'Data Management', 'icon' => 'fa-database', 'link' => 'Coordinator_data_io.php'],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Announcements</title>
    <link rel="icon" type="image/png" href="image/ladybug.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap');
        
        :root {
            --primary-color: #0056b3;
            --primary-hover: #004494;
            --bg-color: #f4f6f9;
            --card-bg: #ffffff;
            --text-color: #333;
            --text-secondary: #666;
            --sidebar-bg: #004085; 
            --sidebar-hover: #003366;
            --sidebar-text: #e0e0e0;
            --card-shadow: 0 4px 6px rgba(0,0,0,0.05);
            --border-color: #e0e0e0;
            --slot-bg: #f8f9fa;
        }

        .dark-mode {
            --primary-color: #4da3ff;
            --primary-hover: #0069d9;
            --bg-color: #121212;
            --card-bg: #1e1e1e;
            --text-color: #e0e0e0;
            --text-secondary: #a0a0a0;
            --sidebar-bg: #0d1117;
            --sidebar-hover: #161b22;
            --sidebar-text: #c9d1d9;
            --card-shadow: 0 4px 6px rgba(0,0,0,0.3);
            --border-color: #333;
            --slot-bg: #2d2d2d;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            background-color: var(--bg-color);
            color: var(--text-color);
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
            transition: background-color 0.3s, color 0.3s;
        }

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
            padding-left: 70px !important;
            font-size: 13px;
            height: 40px;
        }

        .menu-item > a {
            cursor: pointer;
        }
        
        .main-content-wrapper {
            margin-left: 60px;
            flex: 1;
            padding: 20px;
            width: calc(100% - 60px);
            transition: margin-left .05s linear;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            background: var(--card-bg);
            padding: 20px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            transition: background 0.3s;
        }
        
        .welcome-text h1 {
            margin: 0;
            font-size: 24px;
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .welcome-text p {
            margin: 5px 0 0;
            color: var(--text-secondary);
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
            color: var(--text-secondary);
            background: var(--slot-bg);
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

        .theme-toggle {
            cursor: pointer; padding: 8px; border-radius: 50%;
            background: var(--slot-bg); border: 1px solid var(--border-color);
            color: var(--text-color); display: flex; align-items: center;
            justify-content: center; width: 35px; height: 35px; margin-right: 15px;
        }
        .theme-toggle img { width: 20px; height: 20px; object-fit: contain; }

        .control-bar { 
            background: var(--card-bg); 
            padding: 15px 25px; 
            border-radius: 12px; 
            box-shadow: var(--card-shadow); 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            flex-wrap: wrap; 
            gap: 15px; 
            margin-bottom: 20px; 
        }
        
        .view-tabs { 
            display: flex; 
            background: var(--slot-bg); 
            padding: 5px; 
            border-radius: 8px; 
        }
        
        .view-tab { 
            padding: 8px 20px; 
            text-decoration: none; 
            color: var(--text-secondary); 
            font-size: 14px; 
            font-weight: 500; 
            border-radius: 6px; 
            transition: 0.2s; 
        }
        
        .view-tab.active { 
            background: var(--card-bg); 
            color: var(--primary-color); 
            box-shadow: 0 2px 5px rgba(0,0,0,0.05); 
            font-weight: 600; 
        }
        
        .filter-form { display: flex; gap: 10px; align-items: center; }
        
        .filter-select, .search-input { 
            padding: 8px 12px; 
            border: 1px solid var(--border-color); 
            background: var(--slot-bg);
            color: var(--text-color);
            border-radius: 6px; 
            font-size: 13px; 
            outline: none; 
        }
        
        .btn-filter { 
            padding: 8px 15px; 
            background: var(--primary-color); 
            color: white; 
            border: none; 
            border-radius: 6px; 
            cursor: pointer; 
            font-size: 13px; 
        }

        .anno-card { 
            background: var(--card-bg); 
            border-radius: 12px; 
            box-shadow: var(--card-shadow); 
            padding: 25px; 
            margin-bottom: 15px; 
            border-left: 5px solid; 
            position: relative; 
            transition: transform 0.2s; 
        }
        
        .anno-card:hover { transform: translateY(-2px); }
        .anno-active { border-left-color: #28a745; }
        .anno-archived { border-left-color: #6c757d; background-color: var(--slot-bg); }
        
        .anno-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; }
        .anno-subject { font-size: 18px; font-weight: 600; color: var(--text-color); }
        
        .anno-badges { display: flex; gap: 8px; align-items: center; }
        .anno-status { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        
        .st-Active { background: rgba(40, 167, 69, 0.2); color: #28a745; }
        .st-Archived { background: rgba(108, 117, 125, 0.2); color: #6c757d; }
        
        .author-badge { 
            font-size: 11px; 
            padding: 4px 10px; 
            background: rgba(0, 86, 179, 0.1); 
            color: var(--primary-color); 
            border-radius: 4px; 
            font-weight: 500; 
            display: flex; 
            align-items: center; 
            gap: 5px; 
        }
        .author-badge.is-me { background: rgba(255, 193, 7, 0.15); color: #856404; border: 1px solid rgba(255, 193, 7, 0.3); }

        .anno-meta { 
            font-size: 13px; 
            color: var(--text-secondary); 
            margin-bottom: 15px; 
            display: flex; 
            gap: 20px; 
            border-bottom: 1px solid var(--border-color); 
            padding-bottom: 10px; 
        }
        
        .anno-desc { 
            font-size: 14px; 
            color: var(--text-color); 
            line-height: 1.6; 
            white-space: pre-wrap; 
            margin-bottom: 15px; 
        }
        
        .anno-actions { display: flex; gap: 10px; justify-content: flex-end; }
        .btn-action { 
            border: none; 
            padding: 7px 15px; 
            border-radius: 4px; 
            cursor: pointer; 
            font-size: 12px; 
            font-weight: 500; 
            display: flex; 
            align-items: center; 
            gap: 5px; 
            transition: 0.2s; 
        }
        .btn-edit { background: #007bff; color: white; } .btn-edit:hover { background: #0056b3; }
        .btn-archive { background: #ffc107; color: #333; } .btn-archive:hover { background: #e0a800; }
        .btn-activate { background: #28a745; color: white; } .btn-activate:hover { background: #1e7e34; }
        .btn-delete { background: #dc3545; color: white; } .btn-delete:hover { background: #c82333; }

        .modal-overlay { 
            display: none; 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            background: rgba(0,0,0,0.5); 
            z-index: 1000; 
            justify-content: center; 
            align-items: center; 
        }
        .modal-overlay.show { display: flex; }
        
        .modal-box { 
            background: var(--card-bg); 
            padding: 25px; 
            border-radius: 12px; 
            width: 90%; 
            max-width: 600px; 
            color: var(--text-color);
        }
        
        .modal-title { 
            font-size: 20px; 
            font-weight: 600; 
            margin-bottom: 20px; 
            border-bottom: 1px solid var(--border-color); 
            padding-bottom: 10px; 
        }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; color: var(--text-secondary); }
        .form-control { 
            width: 100%; 
            padding: 10px; 
            border: 1px solid var(--border-color); 
            border-radius: 6px; 
            box-sizing: border-box; 
            background: var(--slot-bg);
            color: var(--text-color);
        }
        textarea.form-control { resize: vertical; min-height: 120px; }
        
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
        .btn-save { background: var(--primary-color); color: white; padding: 10px 25px; border: none; border-radius: 6px; cursor: pointer; }
        .btn-close { background: #6c757d; color: white; padding: 10px 25px; border: none; border-radius: 6px; cursor: pointer; }

        @media (max-width: 900px) { 
            .main-content-wrapper { 
                margin-left: 0; 
                width: 100%; 
            }
            .page-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            .control-bar { flex-direction: column; align-items: stretch; } 
            .filter-form { flex-direction: column; } 
        }
    </style>
</head>
<body>

    <nav class="main-menu">
        <ul>
            <?php foreach ($menu_items as $key => $item): ?>
                <?php 
                    $isActive = ($key == $current_page);
                    $hasActiveChild = false;
                    if (isset($item['sub_items'])) {
                        foreach ($item['sub_items'] as $sub_key => $sub) {
                            if ($sub_key == 'view_announcements') { $hasActiveChild = true; break; }
                        }
                    }
                    $linkUrl = isset($item['link']) ? $item['link'] : "#";
                    if ($linkUrl !== "#" && strpos($linkUrl, '.php') !== false) {
                         $separator = (strpos($linkUrl, '?') !== false) ? '&' : '?';
                         $linkUrl .= $separator . "auth_user_id=" . urlencode($auth_user_id);
                    }
                    $hasSubmenu = isset($item['sub_items']);
                ?>
                <li class="menu-item <?php echo ($hasActiveChild || $isActive) ? 'open active' : ''; ?>">
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
                                if ($subLinkUrl !== "#" && strpos($subLinkUrl, '.php') !== false) {
                                    $separator = (strpos($subLinkUrl, '?') !== false) ? '&' : '?';
                                    $subLinkUrl .= $separator . "auth_user_id=" . urlencode($auth_user_id);
                                }
                            ?>
                                <li><a href="<?php echo $subLinkUrl; ?>" class="<?php echo ($sub_key == 'view_announcements') ? 'active' : ''; ?>"><i class="fa <?php echo $sub_item['icon']; ?> nav-icon"></i><span class="nav-text"><?php echo $sub_item['name']; ?></span></a></li>
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

    <div class="main-content-wrapper">
        <div class="page-header">
            <div class="welcome-text">
                <h1>Manage Announcements</h1>
                <p>Welcome back, <?php echo htmlspecialchars($user_name); ?></p>
            </div>
            
            <div class="logo-section">
                <img src="image/ladybug.png" alt="Logo" class="logo-img">
                <span class="system-title">FYP Portal</span>
            </div>

            <div class="user-section">
                <button class="theme-toggle" onclick="toggleDarkMode()" title="Toggle Dark Mode">
                    <img id="theme-icon" src="image/moon-solid-full.svg" alt="Toggle Theme">
                </button>
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

        <div class="control-bar">
            <div class="view-tabs">
                <a href="?auth_user_id=<?php echo $auth_user_id; ?>&view=mine" class="view-tab <?php echo $view_mode == 'mine' ? 'active' : ''; ?>">
                    My Announcements
                </a>
                <a href="?auth_user_id=<?php echo $auth_user_id; ?>&view=all" class="view-tab <?php echo $view_mode == 'all' ? 'active' : ''; ?>">
                    Manage All
                </a>
            </div>

            <?php if ($view_mode == 'all'): ?>
                <form method="GET" class="filter-form">
                    <input type="hidden" name="auth_user_id" value="<?php echo $auth_user_id; ?>">
                    <input type="hidden" name="view" value="all">
                    
                    <select name="filter_year" class="filter-select">
                        <option value="">All Years</option>
                        <?php foreach($years_available as $yr): ?>
                            <option value="<?php echo $yr; ?>" <?php echo $filter_year == $yr ? 'selected' : ''; ?>>
                                <?php echo $yr; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <input type="text" name="search_name" placeholder="Search Supervisor..." class="search-input" value="<?php echo htmlspecialchars($search_name); ?>">
                    
                    <button type="submit" class="btn-filter"><i class="fa fa-search"></i> Filter</button>
                </form>
            <?php endif; ?>
        </div>

        <?php if (count($announcements) > 0): ?>
            <?php foreach ($announcements as $anno): 
                $status = $anno['fyp_status'];
                $isArchived = ($status == 'Archived');
                $cardClass = $isArchived ? 'anno-archived' : 'anno-active';
            ?>
                <div class="anno-card <?php echo $cardClass; ?>">
                    <div class="anno-top">
                        <div class="anno-subject"><?php echo htmlspecialchars($anno['fyp_subject']); ?></div>
                        <div class="anno-badges">
                            <span class="author-badge <?php echo $anno['is_mine'] ? 'is-me' : ''; ?>">
                                <i class="fa fa-user-tag"></i> 
                                <?php echo $anno['is_mine'] ? 'You' : htmlspecialchars($anno['author_name']); ?>
                            </span>
                            <span class="anno-status st-<?php echo $status; ?>"><?php echo $status; ?></span>
                        </div>
                    </div>
                    <div class="anno-meta">
                        <span><i class="fa fa-calendar"></i> Posted: <?php echo date('d M Y', strtotime($anno['fyp_datecreated'])); ?></span>
                        <span><i class="fa fa-bullseye"></i> To: <?php echo htmlspecialchars($anno['fyp_receiver']); ?></span>
                    </div>
                    <div class="anno-desc"><?php echo nl2br(htmlspecialchars($anno['fyp_description'])); ?></div>
                    
                    <div class="anno-actions">
                        <button type="button" class="btn-action btn-edit" onclick="openEditModal(
                            '<?php echo $anno['fyp_annouceid']; ?>', 
                            '<?php echo addslashes(htmlspecialchars($anno['fyp_subject'])); ?>', 
                            '<?php echo addslashes(htmlspecialchars($anno['fyp_description'])); ?>'
                        )">
                            <i class="fa fa-edit"></i> Edit
                        </button>

                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="announce_id" value="<?php echo $anno['fyp_annouceid']; ?>">
                            <input type="hidden" name="current_status" value="<?php echo $status; ?>">
                            <?php if ($isArchived): ?>
                                <button type="submit" name="toggle_status" class="btn-action btn-activate">
                                    <i class="fa fa-box-open"></i> Set Active
                                </button>
                            <?php else: ?>
                                <button type="submit" name="toggle_status" class="btn-action btn-archive">
                                    <i class="fa fa-archive"></i> Archive
                                </button>
                            <?php endif; ?>
                        </form>

                        <form id="del_form_<?php echo $anno['fyp_annouceid']; ?>" method="POST" style="display:inline;">
                            <input type="hidden" name="announce_id" value="<?php echo $anno['fyp_annouceid']; ?>">
                            <input type="hidden" name="delete_announcement" value="1">
                            <button type="button" class="btn-action btn-delete" onclick="confirmDelete('<?php echo $anno['fyp_annouceid']; ?>')">
                                <i class="fa fa-trash"></i> Delete
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align:center; padding:40px; color:var(--text-secondary);">
                <i class="fa fa-inbox" style="font-size:40px; margin-bottom:10px;"></i><br>
                No announcements found matching your criteria.
            </div>
        <?php endif; ?>

    </div>

    <div id="editModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-title">Edit Announcement</div>
            <form method="POST">
                <input type="hidden" name="announce_id" id="edit_id">
                <div class="form-group">
                    <label>Subject</label>
                    <input type="text" name="edit_subject" id="edit_subject" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="edit_description" id="edit_description" class="form-control" required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="edit_announcement" class="btn-save">Save Changes</button>
                    <button type="button" class="btn-close" onclick="closeEditModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleSubmenu(element) {
            const menuItem = element.parentElement;
            const isOpen = menuItem.classList.contains('open');
            
            document.querySelectorAll('.menu-item').forEach(item => {
                if (item !== menuItem) {
                    item.classList.remove('open');
                }
            });
            
            if (isOpen) {
                menuItem.classList.remove('open');
            } else {
                menuItem.classList.add('open');
            }
        }

        function toggleDarkMode() {
            document.body.classList.toggle('dark-mode');
            const isDark = document.body.classList.contains('dark-mode');
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
            
            const iconImg = document.getElementById('theme-icon');
            if (isDark) {
                iconImg.src = 'image/sun-solid-full.svg'; 
            } else {
                iconImg.src = 'image/moon-solid-full.svg'; 
            }
        }

        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            document.body.classList.add('dark-mode');
            const iconImg = document.getElementById('theme-icon');
            if(iconImg) {
                iconImg.src = 'image/sun-solid-full.svg'; 
            }
        }

        const editModal = document.getElementById('editModal');
        function openEditModal(id, subject, description) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_subject').value = subject;
            document.getElementById('edit_description').value = description.replace(/<br\s*\/?>/mg, "\n");
            editModal.classList.add('show');
        }
        function closeEditModal() { editModal.classList.remove('show'); }
        window.onclick = function(e) { if (e.target.classList.contains('modal-overlay')) closeEditModal(); }

        function confirmDelete(id) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('del_form_' + id).submit();
                }
            })
        }

        <?php if (isset($swal_payload)): ?>
            Swal.fire({
                icon: '<?php echo $swal_payload['icon']; ?>',
                title: '<?php echo $swal_payload['title']; ?>',
                text: '<?php echo $swal_payload['text']; ?>',
                showConfirmButton: false,
                timer: 2000
            }).then(() => {
                window.location.href = '<?php echo $swal_payload['redirect']; ?>';
            });
        <?php endif; ?>
    </script>
</body>
</html>