<?php
// ====================================================
// supervisor_projectreq.php - Request Management (AJAX Version)
// ====================================================
include("connect.php");

// 1. AJAX Handler (Process Requests without Reload)
if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    $req_id = $_POST['req_id'];
    $action = $_POST['action'];
    $auth_user_id = $_POST['auth_user_id'];
    
    // Get Staff ID for the current user
    $my_staff_id = "";
    $stmt = $conn->prepare("SELECT fyp_staffid FROM supervisor WHERE fyp_userid = ?");
    $stmt->bind_param("i", $auth_user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $my_staff_id = $row['fyp_staffid'];
    }
    $stmt->close();

    if (empty($my_staff_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Staff ID not found.']);
        exit;
    }

    if ($action == 'Reject') {
        $conn->query("UPDATE project_request SET fyp_requeststatus = 'Reject' WHERE fyp_requestid = $req_id");
        echo json_encode(['status' => 'success', 'message' => 'Request Rejected.', 'new_status' => 'Reject']);
        exit;
    } 
    elseif ($action == 'Approve') {
        // --- Quota Check ---
        $limit = 0; 
        $current_active = 0;
        
        $q_res = $conn->query("SELECT fyp_numofstudent FROM quota WHERE fyp_staffid = '$my_staff_id'");
        if ($q_row = $q_res->fetch_assoc()) $limit = intval($q_row['fyp_numofstudent']);

        $c_sql = "SELECT COUNT(*) as cnt 
                  FROM fyp_registration 
                  WHERE fyp_staffid = '$my_staff_id' 
                  AND (fyp_archive_status = 'Active' OR fyp_archive_status IS NULL)";

        $c_res = $conn->query($c_sql);
        if ($c_row = $c_res->fetch_assoc()) {
            $current_active = intval($c_row['cnt']);
        }
        
        if ($current_active >= $limit) {
            echo json_encode(['status' => 'error', 'message' => "Cannot Approve: Quota Limit Exceeded ($current_active/$limit)! Please archive old students first."]);
            exit;
        } else {
            // Register Logic
            $req_sql = "SELECT fyp_studid, fyp_projectid FROM project_request WHERE fyp_requestid = $req_id LIMIT 1";
            $req_res = $conn->query($req_sql);
            
            if ($req_row = $req_res->fetch_assoc()) {
                $sid = $req_row['fyp_studid']; 
                $pid = $req_row['fyp_projectid'];
                
                $students_to_register = [$sid];
                // Check if Group
                $grp = $conn->query("SELECT group_id FROM student_group WHERE leader_id = '$sid'")->fetch_assoc();
                if ($grp) {
                    $gid = $grp['group_id'];
                    $m_res = $conn->query("SELECT invitee_id FROM group_request WHERE group_id = '$gid' AND request_status = 'Accepted'");
                    while ($m = $m_res->fetch_assoc()) { $students_to_register[] = $m['invitee_id']; }
                }

                foreach ($students_to_register as $student_id) {
                    $conn->query("INSERT INTO fyp_registration (fyp_studid, fyp_projectid, fyp_staffid, fyp_datecreated, fyp_archive_status) VALUES ('$student_id', '$pid', '$my_staff_id', NOW(), 'Active')");
                }

                $conn->query("UPDATE project_request SET fyp_requeststatus = 'Approve' WHERE fyp_requestid = $req_id");
                $conn->query("UPDATE project SET fyp_projectstatus = 'Taken' WHERE fyp_projectid = $pid");

                echo json_encode(['status' => 'success', 'message' => 'Approved! Project Taken.', 'new_status' => 'Approve']);
                exit;
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Request not found.']);
                exit;
            }
        }
    }
    exit;
}

// --- Standard Page Load ---
$auth_user_id = $_GET['auth_user_id'] ?? null;
$current_page = 'project_requests'; 

if (!$auth_user_id) { header("location: login.php"); exit; }

// 获取 Supervisor 资料
$user_name = "Supervisor"; 
$user_avatar = "image/user.png"; 
$my_staff_id = ""; 

if (isset($conn)) {
    $stmt = $conn->prepare("SELECT fyp_staffid, fyp_name, fyp_profileimg FROM supervisor WHERE fyp_userid = ?");
    $stmt->bind_param("i", $auth_user_id); 
    $stmt->execute(); 
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) { 
        $my_staff_id = $row['fyp_staffid']; 
        $user_name = $row['fyp_name']; 
        if(!empty($row['fyp_profileimg'])) $user_avatar = $row['fyp_profileimg']; 
    }
    $stmt->close();
}

// 获取 Request 列表
$requests = [];
$filter_status = $_GET['filter_status'] ?? 'Pending'; 
$sort_order = $_GET['sort_order'] ?? 'DESC';

if (!empty($my_staff_id)) {
    $sql = "SELECT r.*, 
                   s.fyp_studname, 
                   s.fyp_studid, 
                   p.fyp_projecttitle,
                   g.group_name as group_name, g.group_id
            FROM project_request r 
            JOIN student s ON r.fyp_studid = s.fyp_studid 
            JOIN project p ON r.fyp_projectid = p.fyp_projectid 
            LEFT JOIN student_group g ON s.fyp_studid = g.leader_id
            WHERE r.fyp_staffid = '$my_staff_id'"; 

    if ($filter_status != 'All') {
        $sql .= " AND r.fyp_requeststatus = '" . $conn->real_escape_string($filter_status) . "'";
    }

    $sort_dir = ($sort_order == 'ASC') ? 'ASC' : 'DESC';
    $sql .= " ORDER BY r.fyp_datecreated $sort_dir";

    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $row['group_details'] = [];
            if (!empty($row['group_id'])) {
                $gid = $row['group_id'];
                $mem_sql = "SELECT s.fyp_studname, s.fyp_studid as fyp_studfullid 
                            FROM group_request gr 
                            JOIN student s ON gr.invitee_id = s.fyp_studid 
                            WHERE gr.group_id = '$gid' AND gr.request_status = 'Accepted'";
                $mem_res = $conn->query($mem_sql);
                $members = [];
                while ($m = $mem_res->fetch_assoc()) {
                    $members[] = $m;
                }
                $row['group_details'] = ['name' => $row['group_name'], 'members' => $members];
            }
            $requests[] = $row;
        }
    }
}

// 菜单定义
$menu_items = [
    'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home', 'link' => 'Supervisor_mainpage.php?page=dashboard'],
    'profile'   => ['name' => 'My Profile', 'icon' => 'fa-user', 'link' => 'supervisor_profile.php'],
    'students'  => [
        'name' => 'My Students', 
        'icon' => 'fa-users',
        'sub_items' => [
            'project_requests' => ['name' => 'Project Requests', 'icon' => 'fa-envelope-open-text', 'link' => 'Supervisor_projectreq.php'],
            'student_list'     => ['name' => 'Student List', 'icon' => 'fa-list', 'link' => 'Supervisor_student_list.php'],
        ]
    ],
    'fyp_project' => [
        'name' => 'FYP Project',
        'icon' => 'fa-project-diagram',
        'sub_items' => [
            'propose_project' => ['name' => 'Propose Project', 'icon' => 'fa-plus-circle', 'link' => 'supervisor_purpose.php'],
            'my_projects'     => ['name' => 'My Projects', 'icon' => 'fa-folder-open', 'link' => 'Supervisor_mainpage.php?page=my_projects'],
            'propose_assignment' => ['name' => 'Propose Assignment', 'icon' => 'fa-tasks', 'link' => 'supervisor_assignment_purpose.php']
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
    <title>Project Requests</title>
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
            --bg-color: #f4f6f9;
        }

        body { font-family: 'Poppins', sans-serif; margin: 0; background-color: var(--bg-color); min-height: 100vh; display: flex; overflow-x: hidden; }

        /* Sidebar & Menu */
        .main-menu { background: var(--sidebar-bg); border-right: 1px solid rgba(255,255,255,0.1); position: fixed; top: 0; bottom: 0; height: 100%; left: 0; width: 60px; overflow-y: auto; overflow-x: hidden; transition: width .05s linear; z-index: 1000; box-shadow: 2px 0 5px rgba(0,0,0,0.1); }
        .main-menu:hover, nav.main-menu.expanded { width: 250px; }
        .main-menu > ul { margin: 7px 0; padding: 0; list-style: none; }
        .main-menu li { position: relative; display: block; width: 250px; }
        .main-menu li > a { position: relative; display: table; border-collapse: collapse; border-spacing: 0; color: var(--sidebar-text); font-size: 14px; text-decoration: none; transition: all .1s linear; width: 100%; }
        .main-menu .nav-icon { position: relative; display: table-cell; width: 60px; height: 46px; text-align: center; vertical-align: middle; font-size: 18px; }
        .main-menu .nav-text { position: relative; display: table-cell; vertical-align: middle; width: 190px; padding-left: 10px; white-space: nowrap; }
        .main-menu li:hover > a, nav.main-menu li.active > a, .menu-item.open > a { color: #fff; background-color: var(--sidebar-hover); border-left: 4px solid #fff; }
        .main-menu > ul.logout { position: absolute; left: 0; bottom: 0; width: 100%; }
        .dropdown-arrow { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); transition: transform 0.3s; font-size: 12px; }
        .menu-item.open .dropdown-arrow { transform: translateY(-50%) rotate(180deg); }
        .submenu { list-style: none; padding: 0; margin: 0; background-color: rgba(0,0,0,0.2); max-height: 0; overflow: hidden; transition: max-height 0.3s ease-out; }
        .menu-item.open .submenu { max-height: 500px; transition: max-height 0.5s ease-in; }
        .submenu li > a { padding-left: 70px !important; font-size: 13px; height: 40px; }

        /* Main Content */
        .main-content-wrapper { margin-left: 60px; flex: 1; padding: 20px; width: calc(100% - 60px); transition: margin-left .05s linear; }

        /* Header */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; background: white; padding: 20px; border-radius: 12px; box-shadow: var(--card-shadow); }
        .welcome-text h1 { margin: 0; font-size: 24px; color: var(--primary-color); font-weight: 600; }
        .welcome-text p { margin: 5px 0 0; color: #666; font-size: 14px; }
        .logo-section { display: flex; align-items: center; gap: 12px; }
        .logo-img { height: 40px; width: auto; background: white; padding: 2px; border-radius: 6px; }
        .system-title { font-size: 20px; font-weight: 600; color: var(--primary-color); letter-spacing: 0.5px; }

        /* Request Cards */
        .req-card { background: #fff; border-radius: 12px; padding: 25px; margin-bottom: 20px; box-shadow: var(--card-shadow); display: flex; justify-content: space-between; align-items: flex-start; transition: transform 0.2s; border-left: 5px solid transparent; }
        .req-card:hover { transform: translateY(-2px); box-shadow: 0 8px 15px rgba(0,0,0,0.1); }
        .req-card[data-status="Approve"] { border-left-color: #28a745; }
        .req-card[data-status="Reject"] { border-left-color: #dc3545; }
        .req-card[data-status="Pending"] { border-left-color: #ffc107; }

        .req-info h3 { margin: 0 0 5px 0; font-size: 18px; color: #333; font-weight: 600; }
        .req-info p { margin: 4px 0; color: #555; font-size: 14px; }
        .req-meta { margin-top: 15px; font-size: 13px; color: #888; display:flex; gap: 15px; align-items: center; }
        
        .req-status-badge { display: inline-block; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase; margin-bottom: 10px; }
        .st-Approve { background: #e6f9ed; color: #28a745; }
        .st-Reject { background: #fdeaea; color: #dc3545; }
        .st-Pending { background: #fff8e1; color: #b78a08; }

        .group-info-box { margin-top: 12px; background: #e3effd; padding: 12px 15px; border-radius: 8px; border-left: 3px solid #007bff; }
        .group-name-title { font-weight: 600; color: #0056b3; font-size: 14px; margin-bottom: 5px; }
        .group-mem-list { margin: 0; padding-left: 20px; font-size: 13px; color: #444; }

        .req-actions { display: flex; gap: 10px; flex-direction: column; align-items: flex-end; min-width: 140px; }
        .btn-act { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 8px; text-decoration: none; color: white; transition: opacity 0.2s; width: 100%; justify-content: center; }
        .btn-act:hover { opacity: 0.9; }
        .btn-approve { background-color: #28a745; box-shadow: 0 2px 4px rgba(40, 167, 69, 0.2); }
        .btn-reject { background-color: #dc3545; box-shadow: 0 2px 4px rgba(220, 53, 69, 0.2); }
        .btn-act.loading { opacity: 0.7; pointer-events: none; }

        .filter-card { background: #fff; padding: 20px; border-radius: 12px; margin-bottom: 25px; box-shadow: var(--card-shadow); display: flex; gap: 20px; align-items: flex-end; flex-wrap: wrap; }
        .filter-group { flex: 1; min-width: 200px; }
        .filter-group label { display: block; font-size: 13px; color: #555; margin-bottom: 8px; font-weight: 600; }
        .filter-select { width: 100%; padding: 10px 12px; border-radius: 6px; border: 1px solid #ddd; font-size: 14px; background: #fff; font-family: 'Poppins', sans-serif; }
        .btn-filter { padding: 10px 25px; border-radius: 6px; background: var(--primary-color); color: #fff; border: none; font-weight: 500; cursor: pointer; height: 42px; font-family: 'Poppins', sans-serif; }

        .empty-state { text-align: center; padding: 50px; color: #999; background: white; border-radius: 12px; box-shadow: var(--card-shadow); }
        .debug-bar { background: #333; color: #fff; padding: 10px 20px; border-radius: 8px; margin-bottom: 20px; font-family: monospace; font-size: 13px; display: flex; align-items: center; gap: 15px; flex-wrap: wrap; }
        @media (max-width: 900px) { .main-content-wrapper { margin-left: 0; width: 100%; } .req-card { flex-direction: column; gap: 15px; } .req-actions { width: 100%; flex-direction: row; } .filter-card { flex-direction: column; align-items: stretch; } }
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
                    <a href="<?php echo $hasSubmenu ? 'javascript:void(0)' : $linkUrl; ?>" class="<?php echo $isActive ? 'active' : ''; ?>" <?php if ($hasSubmenu): ?>onclick="toggleSubmenu(this)"<?php endif; ?>>
                        <i class="fa <?php echo $item['icon']; ?> nav-icon"></i><span class="nav-text"><?php echo $item['name']; ?></span><?php if ($hasSubmenu): ?><i class="fa fa-chevron-down dropdown-arrow"></i><?php endif; ?>
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
                                <li><a href="<?php echo $subLinkUrl; ?>" class="<?php echo ($sub_key == $current_page) ? 'active' : ''; ?>"><i class="fa <?php echo $sub_item['icon']; ?> nav-icon"></i><span class="nav-text"><?php echo $sub_item['name']; ?></span></a></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <ul class="logout"><li><a href="login.php"><i class="fa fa-power-off nav-icon"></i><span class="nav-text">Logout</span></a></li></ul>
    </nav>

    <div class="main-content-wrapper">
        <div class="page-header">
            <div class="welcome-text"><h1>Project Requests</h1><p>Manage incoming student supervision requests.</p></div>
            <div class="logo-section"><img src="image/ladybug.png" alt="Logo" class="logo-img"><span class="system-title">FYP Portal</span></div>
            <div style="display:flex; align-items:center; gap:10px;">
                <span style="font-size:13px; color:#666; background:#f0f0f0; padding:5px 10px; border-radius:20px;">Supervisor</span>
                <?php if(!empty($user_avatar) && $user_avatar != 'image/user.png'): ?>
                    <img src="<?php echo htmlspecialchars($user_avatar); ?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
                <?php else: ?>
                    <div style="width:40px;height:40px;border-radius:50%;background:#0056b3;color:white;display:flex;align-items:center;justify-content:center;font-weight:bold;"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quota Debugger -->
        <?php if (!empty($my_staff_id)): 
            $debug_limit = 0; $debug_active = 0;
            $q = $conn->query("SELECT fyp_numofstudent FROM quota WHERE fyp_staffid = '$my_staff_id'");
            if($r = $q->fetch_assoc()) $debug_limit = intval($r['fyp_numofstudent']);
            $c = $conn->query("SELECT COUNT(*) as cnt FROM fyp_registration WHERE fyp_staffid = '$my_staff_id' AND (fyp_archive_status = 'Active' OR fyp_archive_status IS NULL)");
            if($r = $c->fetch_assoc()) $debug_active = intval($r['cnt']);
        ?>
            <div class="debug-bar">
                <span><i class="fa fa-chart-pie"></i> <strong>Quota Status:</strong></span>
                <span>Active: <strong style="color: #4db8ff"><?php echo $debug_active; ?></strong></span>
                <span>Limit: <strong style="color: #69f0ae"><?php echo $debug_limit; ?></strong></span>
                <?php if ($debug_limit == 0): ?><span style="color:#ff8a80;"><i class="fa fa-exclamation-triangle"></i> Limit is 0</span>
                <?php elseif ($debug_active >= $debug_limit): ?><span style="color:#ff8a80; font-weight:bold;">FULL</span>
                <?php else: ?><span style="color:#69f0ae;">Available</span><?php endif; ?>
            </div>
        <?php endif; ?>

        <form method="GET" action="" class="filter-card">
            <input type="hidden" name="auth_user_id" value="<?php echo htmlspecialchars($auth_user_id); ?>">
            <div class="filter-group"><label>Status</label><select name="filter_status" class="filter-select"><option value="Pending" <?php if($filter_status == 'Pending') echo 'selected'; ?>>Pending (Default)</option><option value="Approve" <?php if($filter_status == 'Approve') echo 'selected'; ?>>Approved</option><option value="Reject" <?php if($filter_status == 'Reject') echo 'selected'; ?>>Rejected</option><option value="All" <?php if($filter_status == 'All') echo 'selected'; ?>>All Requests</option></select></div>
            <div class="filter-group"><label>Sort By Date</label><select name="sort_order" class="filter-select"><option value="DESC" <?php if($sort_order == 'DESC') echo 'selected'; ?>>Newest First</option><option value="ASC" <?php if($sort_order == 'ASC') echo 'selected'; ?>>Oldest First</option></select></div>
            <button type="submit" class="btn-filter"><i class="fa fa-filter"></i> Apply Filters</button>
        </form>

        <?php if (count($requests) > 0): ?>
            <?php foreach ($requests as $req): $status = $req['fyp_requeststatus']; if (empty($status)) $status = 'Pending'; ?>
                <div class="req-card" id="card-<?php echo $req['fyp_requestid']; ?>" data-status="<?php echo $status; ?>">
                    <div class="req-info" style="flex:1;">
                        <span id="badge-<?php echo $req['fyp_requestid']; ?>" class="req-status-badge st-<?php echo $status; ?>"><?php echo $status; ?></span>
                        <h3><?php echo htmlspecialchars($req['fyp_studname']); ?> <span style="font-weight:400; font-size:15px; color:#777;">(<?php echo htmlspecialchars($req['fyp_studid']); ?>)</span></h3>
                        <p><strong><i class="fa fa-book-reader" style="color:var(--primary-color); width:20px;"></i> Project:</strong> <?php echo htmlspecialchars($req['fyp_projecttitle']); ?></p>
                        <?php if (!empty($req['group_details'])): ?>
                            <div class="group-info-box"><div class="group-name-title"><i class="fa fa-users"></i> Team: <?php echo htmlspecialchars($req['group_details']['name']); ?></div><ul class="group-mem-list"><li><b>Leader:</b> <?php echo htmlspecialchars($req['fyp_studname']); ?></li><?php foreach($req['group_details']['members'] as $mem): ?><li><b>Member:</b> <?php echo htmlspecialchars($mem['fyp_studname']); ?> (<?php echo $mem['fyp_studfullid']; ?>)</li><?php endforeach; ?></ul></div>
                        <?php endif; ?>
                        <div class="req-meta"><span><i class="fa fa-calendar-alt"></i> Applied: <?php echo $req['fyp_datecreated']; ?></span></div>
                    </div>
                    <div class="req-actions" id="actions-<?php echo $req['fyp_requestid']; ?>">
                        <?php if ($status == 'Pending'): ?>
                            <button type="button" class="btn-act btn-approve" onclick="handleDecision('Approve', <?php echo $req['fyp_requestid']; ?>)"><i class="fa fa-check"></i> Accept</button>
                            <button type="button" class="btn-act btn-reject" onclick="handleDecision('Reject', <?php echo $req['fyp_requestid']; ?>)"><i class="fa fa-times"></i> Reject</button>
                        <?php else: ?>
                            <div style="text-align:right; color:#999; font-style:italic; margin-top:10px;"><i class="fa fa-check-circle"></i> Decision Recorded</div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state"><i class="fa fa-inbox" style="font-size: 48px; opacity: 0.3; margin-bottom:15px;"></i><p>No project requests found matching your criteria.</p></div>
        <?php endif; ?>
    </div>

    <script>
        function toggleSubmenu(element) {
            const menuItem = element.parentElement;
            const isOpen = menuItem.classList.contains('open');
            document.querySelectorAll('.menu-item').forEach(item => { if (item !== menuItem) item.classList.remove('open'); });
            if (isOpen) menuItem.classList.remove('open'); else menuItem.classList.add('open');
        }

        function handleDecision(action, reqId) {
            if (!confirm(action === 'Approve' ? 'Accept this student/team?' : 'Reject this request?')) return;

            // Find buttons to show loading state
            const actionContainer = document.getElementById('actions-' + reqId);
            const buttons = actionContainer.querySelectorAll('button');
            buttons.forEach(btn => {
                btn.classList.add('loading');
                btn.disabled = true;
                btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Processing...';
            });

            // Prepare Data
            const formData = new FormData();
            formData.append('ajax_action', 'true');
            formData.append('req_id', reqId);
            formData.append('action', action);
            formData.append('auth_user_id', '<?php echo $auth_user_id; ?>');

            // Fetch Request
            fetch('Supervisor_projectreq.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Update UI without Reload
                    const card = document.getElementById('card-' + reqId);
                    const badge = document.getElementById('badge-' + reqId);
                    
                    // Update Badge
                    badge.className = 'req-status-badge st-' + data.new_status;
                    badge.textContent = data.new_status;
                    
                    // Update Border Color
                    card.setAttribute('data-status', data.new_status);
                    
                    // Remove Actions and show text
                    actionContainer.innerHTML = '<div style="text-align:right; color:#999; font-style:italic; margin-top:10px;"><i class="fa fa-check-circle"></i> ' + (action === 'Approve' ? 'Approved' : 'Rejected') + ' Successfully</div>';
                    
                    // Optional: Show Alert
                    // alert(data.message);
                } else {
                    alert(data.message);
                    // Reset buttons on error
                    buttons.forEach(btn => {
                        btn.classList.remove('loading');
                        btn.disabled = false;
                        if(btn.classList.contains('btn-approve')) btn.innerHTML = '<i class="fa fa-check"></i> Accept';
                        else btn.innerHTML = '<i class="fa fa-times"></i> Reject';
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
                buttons.forEach(btn => {
                    btn.classList.remove('loading');
                    btn.disabled = false;
                    if(btn.classList.contains('btn-approve')) btn.innerHTML = '<i class="fa fa-check"></i> Accept';
                    else btn.innerHTML = '<i class="fa fa-times"></i> Reject';
                });
            });
        }
    </script>
</body> 
</html>