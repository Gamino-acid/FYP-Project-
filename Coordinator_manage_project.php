<?php
include("connect.php");
session_start();

$auth_user_id = $_GET['auth_user_id'] ?? $_SESSION['user_id'] ?? null;
$current_page = 'project_list'; 

if (!$auth_user_id) {
    echo "<script>window.location.href='login.php';</script>";
    exit;
}

$user_name = "Coordinator"; 
$user_avatar = "image/user.png"; 
$my_staff_id = ""; 

if (isset($conn)) {
    $stmt = $conn->prepare("SELECT fyp_name, fyp_profileimg, fyp_staffid FROM coordinator WHERE fyp_userid = ?");
    $stmt->bind_param("i", $auth_user_id); 
    $stmt->execute(); 
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) { 
        $user_name = $row['fyp_name']; 
        $my_staff_id = $row['fyp_staffid'];
        if(!empty($row['fyp_profileimg'])) $user_avatar = $row['fyp_profileimg']; 
    }
    $stmt->close();
}

$filter_years = [];
$filter_intakes = [];

$year_sql = "SELECT DISTINCT fyp_acdyear FROM academic_year ORDER BY fyp_acdyear DESC";
$year_res = $conn->query($year_sql);
while($y = $year_res->fetch_assoc()) { if($y['fyp_acdyear']) $filter_years[] = $y['fyp_acdyear']; }

$intake_sql = "SELECT DISTINCT fyp_intake FROM academic_year ORDER BY fyp_intake ASC";
$intake_res = $conn->query($intake_sql);
while($i = $intake_res->fetch_assoc()) { if($i['fyp_intake']) $filter_intakes[] = $i['fyp_intake']; }

$search_keyword = $_GET['search'] ?? '';
$selected_year = $_GET['filter_year'] ?? '';
$selected_intake = $_GET['filter_intake'] ?? '';
$view_scope = $_GET['view_scope'] ?? 'my';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$swal_alert = null;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_project'])) {
    
    $target_pid = $_POST['project_id'];
    $new_title = $_POST['edit_title'];
    $new_cat = $_POST['edit_category'] ?? ''; 
    $new_desc = $_POST['edit_desc'];
    $new_req = $_POST['edit_req'];
    $new_archive = $_POST['edit_archive_status']; 

    $update_sql = "UPDATE project SET 
                   fyp_projecttitle = ?, 
                   fyp_projectcat = ?, 
                   fyp_description = ?, 
                   fyp_requirement = ?, 
                   fyp_archive_status = ? 
                   WHERE fyp_projectid = ?";
                    
    if ($up_stmt = $conn->prepare($update_sql)) {
        $up_stmt->bind_param("sssssi", $new_title, $new_cat, $new_desc, $new_req, $new_archive, $target_pid);
        if ($up_stmt->execute()) {
            $swal_alert = [
                'title' => 'Success!',
                'text' => 'Project details updated successfully.',
                'icon' => 'success'
            ];
        } else {
            $swal_alert = [
                'title' => 'Error!',
                'text' => 'Update failed: ' . $up_stmt->error,
                'icon' => 'error'
            ];
        }
        $up_stmt->close();
    }
}

$all_projects = [];
$total_pages = 1;

$base_sql = "FROM project p 
             LEFT JOIN academic_year ay ON p.fyp_academicid = ay.fyp_academicid
             LEFT JOIN supervisor s ON p.fyp_staffid = s.fyp_staffid
             LEFT JOIN coordinator c ON p.fyp_staffid = c.fyp_staffid
             WHERE 1=1 ";

$params = [];
$types = "";

if ($view_scope == 'my') {
    $base_sql .= " AND p.fyp_staffid = ? ";
    $params[] = $my_staff_id;
    $types .= "s";
}

if (!empty($search_keyword)) {
    $base_sql .= " AND (p.fyp_projecttitle LIKE ? OR s.fyp_name LIKE ? OR c.fyp_name LIKE ?)";
    $like_term = "%" . $search_keyword . "%";
    $params[] = $like_term;
    $params[] = $like_term;
    $params[] = $like_term;
    $types .= "sss";
}

if (!empty($selected_year)) {
    $base_sql .= " AND ay.fyp_acdyear = ?";
    $params[] = $selected_year;
    $types .= "s";
}

if (!empty($selected_intake)) {
    $base_sql .= " AND ay.fyp_intake = ?";
    $params[] = $selected_intake;
    $types .= "s";
}

$count_sql = "SELECT COUNT(*) as total " . $base_sql;
if ($stmt = $conn->prepare($count_sql)) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $total_rows = $res->fetch_assoc()['total'];
    $total_pages = ceil($total_rows / $limit);
    $stmt->close();
}

$data_sql = "SELECT p.*, ay.fyp_acdyear, ay.fyp_intake,
             COALESCE(s.fyp_name, c.fyp_name, 'Unknown Owner') as owner_name,
             CASE 
                WHEN s.fyp_staffid IS NOT NULL THEN 'Supervisor'
                WHEN c.fyp_staffid IS NOT NULL THEN 'Coordinator'
                ELSE 'Unknown'
             END as owner_role " . $base_sql . " ORDER BY p.fyp_datecreated DESC LIMIT ?, ?";

$params[] = $offset;
$params[] = $limit;
$types .= "ii";

if ($stmt = $conn->prepare($data_sql)) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $all_projects[] = $row;
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
    <title>Manage Projects - Coordinator</title>
    <link rel="icon" type="image/png" href="image/ladybug.png?v=<?php echo time(); ?>">
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

        .content-card {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
        }
        
        /* Filter Bar Styles */
        .filter-bar { background: var(--slot-bg); padding: 20px; border-radius: 12px; margin-bottom: 25px; border: 1px solid var(--border-color); display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; }
        .filter-group { flex: 1; min-width: 150px; }
        .filter-group label { display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px; color: var(--text-secondary); }
        .filter-input, .filter-select { width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 14px; box-sizing: border-box; background: var(--card-bg); color: var(--text-color); }
        .btn-filter { background: var(--primary-color); color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 500; transition: background 0.2s; height: 42px; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-filter:hover { background: var(--primary-hover); }
        .btn-reset { background: var(--slot-bg); color: var(--text-color); border: 1px solid var(--border-color); padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 500; text-decoration: none; height: 42px; display: flex; align-items: center; justify-content: center; box-sizing: border-box; }
        .btn-reset:hover { background: var(--border-color); }

        /* Data Table Styles (Matching Dashboard) */
        .data-table { 
            width: 100%; 
            border-collapse: collapse; 
            background: var(--card-bg); 
            border-radius: 8px; 
            overflow: hidden; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.05); 
            transition: background 0.3s; 
            margin-top: 10px;
        }
        
        .data-table th { 
            background: var(--slot-bg); 
            text-align: left; 
            padding: 12px 15px; 
            color: var(--text-secondary); 
            font-size: 13px; 
            font-weight: 600; 
            border-bottom: 2px solid var(--border-color); 
        }
        
        .data-table td { 
            padding: 12px 15px; 
            border-bottom: 1px solid var(--border-color); 
            font-size: 14px; 
            color: var(--text-color); 
            vertical-align: middle;
        }
        
        .data-table tr:hover { background-color: var(--slot-bg); }
        .data-table tr:last-child td { border-bottom: none; }
        
        /* Badges */
        .badge { padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-block; }
        .badge-status-Taken { background: #e3effd; color: #0056b3; }
        .badge-status-Open { background: #d4edda; color: #155724; }
        .badge-vis-Active { background: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; }
        .badge-vis-Archived { background: #f8d7da; color: #842029; border: 1px solid #f5c2c7; }
        
        .role-badge-small { font-size: 10px; padding: 2px 6px; border-radius: 4px; margin-left: 5px; font-weight: normal; vertical-align: middle; }
        .role-Supervisor { background: #e0f7fa; color: #006064; border: 1px solid #b2ebf2; }
        .role-Coordinator { background: #f3e5f5; color: #4a148c; border: 1px solid #e1bee7; }

        .btn-edit { background: var(--card-bg); border: 1px solid #ffc107; color: #d39e00; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 500; transition: all 0.2s; display: inline-flex; align-items: center; gap: 5px; }
        .btn-edit:hover { background: #ffc107; color: #333; }
        
        .btn-new { background: var(--primary-color); color: white; text-decoration: none; padding: 8px 15px; border-radius: 6px; font-size: 14px; display: inline-flex; align-items: center; gap: 6px; }

        /* Modal Styles */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-box { background: var(--card-bg); width: 600px; max-width: 90%; max-height: 90vh; overflow-y: auto; border-radius: 12px; box-shadow: 0 15px 40px rgba(0,0,0,0.2); padding: 30px; }
        .form-control { width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 14px; box-sizing: border-box; background: var(--card-bg); color: var(--text-color); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; color: var(--text-color); }
        .modal-footer { margin-top: 25px; display: flex; justify-content: flex-end; gap: 10px; }
        .btn-save { background: var(--primary-color); color: white; border: none; padding: 10px 25px; border-radius: 6px; cursor: pointer; }
        .btn-close { background: var(--slot-bg); color: var(--text-color); border: 1px solid var(--border-color); padding: 10px 20px; border-radius: 6px; cursor: pointer; }
        .close-modal { float:right; cursor:pointer; font-size:24px; color:var(--text-secondary); }

        .pagination { display: flex; justify-content: center; gap: 5px; margin-top: 20px; }
        .page-link { padding: 8px 14px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-color); text-decoration: none; border-radius: 6px; transition: 0.2s; font-size: 14px; }
        .page-link:hover { background: var(--slot-bg); }
        .page-link.active { background: var(--primary-color); color: white; border-color: var(--primary-color); }
        
        .theme-toggle { cursor: pointer; padding: 8px; border-radius: 50%; background: var(--slot-bg); border: 1px solid var(--border-color); color: var(--text-color); display: flex; align-items: center; justify-content: center; width: 35px; height: 35px; margin-right: 15px; }
        .theme-toggle img { width: 20px; height: 20px; object-fit: contain; }

        @media (max-width: 900px) { .main-content-wrapper { margin-left: 0; width: 100%; } .filter-bar { flex-direction: column; align-items: stretch; } .page-header { flex-direction: column; gap: 15px; text-align: center; } }
    </style>
</head>
<body>

    <nav class="main-menu">
        <ul>
            <?php foreach ($menu_items as $key => $item): ?>
                <?php 
                    $isActive = ($key == 'project_mgmt'); 
                    $hasActiveChild = false;
                    if (isset($item['sub_items'])) {
                        foreach ($item['sub_items'] as $sub_key => $sub) {
                            if ($sub_key == $current_page) { $hasActiveChild = true; break; }
                        }
                    }
                    $linkUrl = isset($item['link']) ? $item['link'] : "#";
                    if (strpos($linkUrl, '.php') !== false) {
                         $separator = (strpos($linkUrl, '?') !== false) ? '&' : '?';
                         $linkUrl .= $separator . "auth_user_id=" . urlencode($auth_user_id);
                    }
                    $hasSubmenu = isset($item['sub_items']);
                ?>
                <li class="menu-item <?php echo ($hasActiveChild || ($isActive && $hasSubmenu)) ? 'open active' : ''; ?>">
                    <a href="<?php echo $linkUrl; ?>" class="<?php echo $isActive ? 'active' : ''; ?>" <?php if ($hasSubmenu): ?>onclick="toggleSubmenu(this)"<?php endif; ?>>
                        <i class="fa <?php echo $item['icon']; ?> nav-icon"></i>
                        <span class="nav-text"><?php echo $item['name']; ?></span>
                        <?php if ($hasSubmenu): ?>
                            <i class="fa fa-chevron-down dropdown-arrow"></i>
                        <?php endif; ?>
                    </a>
                    <?php if (isset($item['sub_items'])): ?>
                        <ul class="submenu">
                            <?php foreach ($item['sub_items'] as $sub_key => $sub_item): 
                                $subLinkUrl = isset($sub_item['link']) ? $sub_item['link'] : "#";
                                if (strpos($subLinkUrl, '.php') !== false) {
                                    $separator = (strpos($subLinkUrl, '?') !== false) ? '&' : '?';
                                    $subLinkUrl .= $separator . "auth_user_id=" . urlencode($auth_user_id);
                                }
                                $isSubActive = ($sub_key == 'project_list'); 
                            ?>
                                <li><a href="<?php echo $subLinkUrl; ?>" class="<?php echo $isSubActive ? 'active' : ''; ?>">
                                    <i class="fa <?php echo $sub_item['icon']; ?> nav-icon"></i><span class="nav-text"><?php echo $sub_item['name']; ?></span>
                                </a></li>
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
                <h1>Manage Projects</h1>
                <p>Administer all student projects.</p>
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

        <div class="content-card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <div>
                    <h2 style="margin:0; color:var(--text-color); font-size: 18px;"><i class="fa fa-tasks"></i> Project List</h2>
                </div>
                <a href="Coordinator_purpose.php?auth_user_id=<?php echo $auth_user_id; ?>" class="btn-new">
                    <i class="fa fa-plus-circle"></i> New Project
                </a>
            </div>

            <form method="GET" class="filter-bar">
                <input type="hidden" name="auth_user_id" value="<?php echo htmlspecialchars($auth_user_id); ?>">
                
                <div class="filter-group">
                    <label>Scope</label>
                    <select name="view_scope" class="filter-select" style="font-weight:600; color:var(--primary-color);" onchange="this.form.submit()">
                        <option value="my" <?php echo ($view_scope == 'my') ? 'selected' : ''; ?>>My Projects (Supervising)</option>
                        <option value="all" <?php echo ($view_scope == 'all') ? 'selected' : ''; ?>>All System Projects</option>
                    </select>
                </div>

                <div class="filter-group" style="flex: 2;">
                    <label>Search Keyword</label>
                    <input type="text" name="search" class="filter-input" placeholder="Project Title or Owner Name..." value="<?php echo htmlspecialchars($search_keyword); ?>">
                </div>

                <div class="filter-group">
                    <label>Academic Year</label>
                    <select name="filter_year" class="filter-select">
                        <option value="">All Years</option>
                        <?php foreach($filter_years as $yr): ?>
                            <option value="<?php echo $yr; ?>" <?php echo ($selected_year == $yr) ? 'selected' : ''; ?>>
                                <?php echo $yr; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Intake</label>
                    <select name="filter_intake" class="filter-select">
                        <option value="">All Intakes</option>
                        <?php foreach($filter_intakes as $intk): ?>
                            <option value="<?php echo $intk; ?>" <?php echo ($selected_intake == $intk) ? 'selected' : ''; ?>>
                                <?php echo $intk; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn-filter"><i class="fa fa-search"></i> Filter</button>
                <a href="Coordinator_manage_project.php?auth_user_id=<?php echo $auth_user_id; ?>&view_scope=my" class="btn-reset">Reset</a>
            </form>

            <?php if (count($all_projects) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Project Info</th>
                            <th>Owner (Staff)</th>
                            <th>Academic Info</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_projects as $proj): ?>
                            <tr>
                                <td>
                                    <strong style="color:var(--text-color); font-size:15px;"><?php echo htmlspecialchars($proj['fyp_projecttitle']); ?></strong><br>
                                    <small style="color:var(--text-secondary);">
                                        Type: <?php echo htmlspecialchars($proj['fyp_projecttype']); ?> | 
                                        Domain: <?php echo htmlspecialchars($proj['fyp_projectcat']); ?>
                                    </small>
                                </td>
                                <td>
                                    <div style="display:flex; align-items:center;">
                                        <span style="font-weight:600; color:var(--text-color);">
                                            <?php echo htmlspecialchars($proj['owner_name']); ?>
                                        </span>
                                        <span class="role-badge-small role-<?php echo $proj['owner_role']; ?>">
                                            <?php echo $proj['owner_role']; ?>
                                        </span>
                                    </div>
                                    <small style="color:var(--text-secondary); font-family:monospace;"><?php echo htmlspecialchars($proj['fyp_staffid']); ?></small>
                                </td>
                                <td>
                                    <div style="font-size:13px; font-weight:500; color:var(--text-color);">
                                        <?php echo htmlspecialchars($proj['fyp_acdyear'] ?? 'N/A'); ?>
                                    </div>
                                    <div style="font-size:12px; color:var(--text-secondary);">
                                        Intake: <?php echo htmlspecialchars($proj['fyp_intake'] ?? 'N/A'); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-status-<?php echo $proj['fyp_projectstatus']; ?>">
                                        <?php echo $proj['fyp_projectstatus']; ?>
                                    </span>
                                    <br>
                                    <span class="badge badge-vis-<?php echo $proj['fyp_archive_status']; ?>" style="margin-top:5px;">
                                        <?php echo $proj['fyp_archive_status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="btn-edit" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($proj)); ?>)">
                                        <i class="fa fa-sliders-h"></i> Manage
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php 
                        $query_params = $_GET;
                        unset($query_params['page']); 
                        $query_str = http_build_query($query_params);
                    ?>
                    
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo $query_str; ?>&page=<?php echo $page - 1; ?>" class="page-link">Previous</a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?<?php echo $query_str; ?>&page=<?php echo $i; ?>" class="page-link <?php echo ($i == $page) ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?<?php echo $query_str; ?>&page=<?php echo $page + 1; ?>" class="page-link">Next</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

            <?php else: ?>
                <div style="text-align:center; padding:50px; color:var(--text-secondary);">
                    <i class="fa fa-search" style="font-size:48px; opacity:0.3; margin-bottom:15px;"></i>
                    <p>No projects found matching your criteria.</p>
                    <?php if($view_scope == 'my'): ?>
                        <p style="font-size:13px; color:var(--text-secondary);">Tip: Switch Scope to "All System Projects" to see others.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="editModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header" style="border-bottom:1px solid var(--border-color); padding-bottom:10px; margin-bottom:15px; display:flex; justify-content:space-between; align-items:center;">
                <h3 style="margin:0; color:var(--text-color);"><i class="fa fa-user-shield"></i> Admin Edit Project</h3>
                <span class="close-modal" onclick="closeModal()">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="project_id" id="modal_project_id">
                <input type="hidden" name="update_project" value="1">

                <div class="form-group">
                    <label>Project Title</label>
                    <input type="text" name="edit_title" id="modal_title" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>Visibility Status (Archive to hide)</label>
                    <select name="edit_archive_status" id="modal_archive" class="form-control" style="font-weight:bold;">
                        <option value="Active">Active (Visible)</option>
                        <option value="Archived">Archived (Hidden)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Category / Domain</label>
                    <select name="edit_category" id="modal_cat" class="form-control">
                        <option value="Software Eng.">Software Engineering</option>
                        <option value="Networking">Networking</option>
                        <option value="AI">Artificial Intelligence</option>
                        <option value="Cybersecurity">Cybersecurity</option>
                        <option value="Data Science">Data Science</option>
                        <option value="IoT">IoT</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="edit_desc" id="modal_desc" class="form-control" style="height:100px;" required></textarea>
                </div>

                <div class="form-group">
                    <label>Requirements</label>
                    <textarea name="edit_req" id="modal_req" class="form-control" style="height:80px;"></textarea>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-close" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-save">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('editModal');

        function openEditModal(projectData) {
            document.getElementById('modal_project_id').value = projectData.fyp_projectid;
            document.getElementById('modal_title').value = projectData.fyp_projecttitle;
            document.getElementById('modal_cat').value = projectData.fyp_projectcat;
            document.getElementById('modal_desc').value = projectData.fyp_description;
            document.getElementById('modal_req').value = projectData.fyp_requirement;
            document.getElementById('modal_archive').value = projectData.fyp_archive_status;
            
            modal.style.display = 'flex';
        }

        function closeModal() {
            modal.style.display = 'none';
        }

        window.onclick = function(e) {
            if (e.target == modal) {
                closeModal();
            }
        }

        function toggleSubmenu(element) {
            const menuItem = element.parentElement;
            const isOpen = menuItem.classList.contains('open');
            document.querySelectorAll('.menu-item').forEach(item => { if (item !== menuItem) item.classList.remove('open'); });
            if (isOpen) menuItem.classList.remove('open'); else menuItem.classList.add('open');
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

        <?php if ($swal_alert): ?>
            Swal.fire({
                title: "<?php echo $swal_alert['title']; ?>",
                text: "<?php echo $swal_alert['text']; ?>",
                icon: "<?php echo $swal_alert['icon']; ?>",
                confirmButtonColor: '#0056b3'
            });
        <?php endif; ?>
    </script>
</body>
</html>