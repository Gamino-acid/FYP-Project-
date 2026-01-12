<?php
// ====================================================
// supervisor_projectreq.php - Request Management (Fixed)
// ====================================================
include("connect.php");

// 1. Auth Check
$auth_user_id = $_GET['auth_user_id'] ?? null;
// 手动设置当前页面为 project_requests 以高亮菜单
$current_page = 'project_requests'; 

if (!$auth_user_id) { header("location: login.php"); exit; }

// ----------------------------------------------------
// 2. 获取 Supervisor 资料 (获取 Staff ID)
// ----------------------------------------------------
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

// ----------------------------------------------------
// 3. 处理 Approve / Reject 逻辑
// ----------------------------------------------------
if (isset($_GET['action']) && isset($_GET['req_id']) && !empty($my_staff_id)) {
    $req_id = $_GET['req_id']; 
    $action = $_GET['action'];

    if ($action == 'Reject') {
        $conn->query("UPDATE project_request SET fyp_requeststatus = 'Reject' WHERE fyp_requestid = $req_id");
        echo "<script>alert('Request Rejected.'); window.location.href='Supervisor_projectreq.php?auth_user_id=$auth_user_id';</script>";
    } 
    elseif ($action == 'Approve') {
        // --- Quota Check (Active Load) ---
        $limit = 0; 
        $current_active = 0;
        
        // 1. 查配额上限
        $q_res = $conn->query("SELECT fyp_numofstudent FROM quota WHERE fyp_staffid = '$my_staff_id'");
        if ($q_row = $q_res->fetch_assoc()) $limit = intval($q_row['fyp_numofstudent']);

        // 2. 查当前活跃人数 (Active Load)
        // 排除 Hidden 的学生
        $c_sql = "SELECT COUNT(*) as cnt 
                  FROM fyp_registration 
                  WHERE fyp_staffid = '$my_staff_id' 
                  AND (fyp_archive_status = 'Active' OR fyp_archive_status IS NULL)";

        $c_res = $conn->query($c_sql);
        if ($c_row = $c_res->fetch_assoc()) {
            $current_active = intval($c_row['cnt']);
        }
        
        // 3. 比较
        if ($current_active >= $limit) {
            echo "<script>alert('Cannot Approve: Quota Limit Exceeded ($current_active/$limit)! Please archive old students in Student List first.'); window.location.href='Supervisor_projectreq.php?auth_user_id=$auth_user_id';</script>";
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

                echo "<script>alert('Approved! Project Taken.'); window.location.href='Supervisor_projectreq.php?auth_user_id=$auth_user_id';</script>";
            }
        }
    }
}

// ----------------------------------------------------
// 4. 获取 Request 列表
// ----------------------------------------------------
$requests = [];
$filter_status = $_GET['filter_status'] ?? 'Pending'; 
$sort_order = $_GET['sort_order'] ?? 'DESC';

if (!empty($my_staff_id)) {
    // 【修复点】将 g.name 改为 g.group_name (或者是 g.fyp_groupname)
    // 如果报错 Unknown column 'g.group_name'，请尝试改为 g.fyp_groupname
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
            // 获取组员信息
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

// 5. 菜单定义
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
        
        .submenu { list-style: none; padding: 0; margin: 0; background-color: #fafafa; display: block; }
        .submenu .menu-link { padding-left: 58px; font-size: 14px; padding-top: 10px; padding-bottom: 10px; }

        .main-content { flex: 1; display: flex; flex-direction: column; gap: 20px; }
        .welcome-card { background: #fff; padding: 30px; border-radius: 12px; box-shadow: var(--card-shadow); border-left: 5px solid var(--primary-color); }
        .page-title { font-size: 24px; margin: 0 0 10px 0; color: var(--text-color); }
        
        .req-card { background: #fff; border: 1px solid #eee; border-radius: 10px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.02); display: flex; justify-content: space-between; align-items: flex-start; transition: transform 0.2s; }
        .req-card:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .req-info h3 { margin: 0 0 5px 0; font-size: 18px; color: #333; }
        .req-info p { margin: 2px 0; color: #666; font-size: 14px; }
        .req-meta { margin-top: 10px; font-size: 12px; color: #999; }
        .req-status { display: inline-block; padding: 4px 12px; border-radius: 15px; font-size: 12px; font-weight: 600; text-transform: uppercase; margin-bottom: 10px; }
        .status-approve { background: #d4edda; color: #155724; }
        .status-reject { background: #f8d7da; color: #721c24; }
        .status-pending { background: #fff3cd; color: #856404; }
        .req-actions { display: flex; gap: 10px; flex-direction: column; align-items: flex-end; }
        .btn-act { padding: 8px 20px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 8px; text-decoration: none; color: white; transition: opacity 0.2s; }
        .btn-act:hover { opacity: 0.9; }
        .btn-approve { background-color: #28a745; }
        .btn-reject { background-color: #dc3545; }
        .empty-state { text-align: center; padding: 50px; color: #999; }
        
        .group-info-box { margin-top:10px; background:#f0f7ff; padding:10px 15px; border-radius:8px; border-left:4px solid #007bff; }
        .group-name-title { font-weight:600; color:#0056b3; font-size:14px; margin-bottom:5px; }
        .group-mem-list { margin:0; padding-left:20px; font-size:13px; color:#555; }
        
        .filter-card { background: #fff; padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #e0e0e0; display: flex; gap: 15px; align-items: flex-end; }
        .filter-group { flex: 1; }
        .filter-group label { display: block; font-size: 12px; color: #666; margin-bottom: 5px; font-weight: 600; }
        .filter-select { width: 100%; padding: 8px; border-radius: 5px; border: 1px solid #ccc; font-size: 14px; }
        .btn-filter { padding: 8px 20px; border-radius: 5px; background: var(--primary-color); color: #fff; border: none; font-weight: 600; cursor: pointer; height: 35px; }
        
        @media (max-width: 900px) { .layout-container { flex-direction: column; } .sidebar { width: 100%; min-height: auto; } .req-card { flex-direction: column; gap: 15px; } .req-actions { width: 100%; flex-direction: row; } .btn-act { flex: 1; justify-content: center; } .filter-card { flex-direction: column; align-items: stretch; } }
    </style>
</head>
<body>

<?php if (!empty($my_staff_id)): 
    // 调试数据获取
    $debug_limit = 0; $debug_active = 0;
    
    // 1. Quota
    $q = $conn->query("SELECT fyp_numofstudent FROM quota WHERE fyp_staffid = '$my_staff_id'");
    if($r = $q->fetch_assoc()) $debug_limit = intval($r['fyp_numofstudent']);
    
    // 2. Active Count (Exclude Hidden)
    $c = $conn->query("SELECT COUNT(*) as cnt FROM fyp_registration WHERE fyp_staffid = '$my_staff_id' AND (fyp_archive_status = 'Active' OR fyp_archive_status IS NULL)");
    if($r = $c->fetch_assoc()) $debug_active = intval($r['cnt']);
?>
    <div style='background:#333; color:#fff; padding:15px; text-align:center; font-family:monospace; font-size:16px;'>
        🔍 <strong>Quota Debugger (Active Only)</strong> | 
        Staff ID: <span style='color:yellow'><?php echo $my_staff_id; ?></span> | 
        Active Students: <span style='color:cyan'><?php echo $debug_active; ?></span> | 
        Max Limit: <span style='color:lime'><?php echo $debug_limit; ?></span> |
        
        <?php if ($debug_limit == 0): ?>
            <span style='color:red; margin-left:10px;'>⚠ 注意: Limit 为 0</span>
        <?php elseif ($debug_active >= $debug_limit): ?>
            <span style='color:red; margin-left:10px;'>⛔ 已满员 (FULL)</span>
        <?php else: ?>
            <span style='color:green; margin-left:10px;'>✅ 还有空位</span>
        <?php endif; ?>
    </div>
<?php endif; ?>
<header class="topbar">
        <div class="logo"> FYP System</div>
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
                        $isActive = ($key == 'students'); // Highlight students menu
                        
                        $linkUrl = isset($item['link']) ? $item['link'] : "#";
                        if (strpos($linkUrl, '.php') !== false) {
                             $separator = (strpos($linkUrl, '?') !== false) ? '&' : '?';
                             $linkUrl .= $separator . "auth_user_id=" . urlencode($auth_user_id);
                        } elseif (strpos($linkUrl, '?') === 0) {
                             $linkUrl .= "&auth_user_id=" . urlencode($auth_user_id);
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
                                    $subIsActive = ($sub_key == 'project_requests');
                                    $subLinkUrl = isset($sub_item['link']) ? $sub_item['link'] : "#";
                                    if (strpos($subLinkUrl, '.php') !== false) {
                                        $separator = (strpos($subLinkUrl, '?') !== false) ? '&' : '?';
                                        $subLinkUrl .= $separator . "auth_user_id=" . urlencode($auth_user_id);
                                    } elseif (strpos($subLinkUrl, '?') === 0) {
                                        $subLinkUrl .= "&auth_user_id=" . urlencode($auth_user_id);
                                    }
                                ?>
                                    <li><a href="<?php echo $subLinkUrl; ?>" class="menu-link <?php echo $subIsActive ? 'active' : ''; ?>">
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
                <h1 class="page-title">Project Requests</h1>
                <p style="color: #666; margin: 0;">Manage student project supervision requests.</p>
            </div>

            <form method="GET" action="" class="filter-card">
                <input type="hidden" name="auth_user_id" value="<?php echo htmlspecialchars($auth_user_id); ?>">
                
                <div class="filter-group">
                    <label>Status</label>
                    <select name="filter_status" class="filter-select">
                        <option value="Pending" <?php if($filter_status == 'Pending') echo 'selected'; ?>>Pending (Default)</option>
                        <option value="Approve" <?php if($filter_status == 'Approve') echo 'selected'; ?>>Approved</option>
                        <option value="Reject" <?php if($filter_status == 'Reject') echo 'selected'; ?>>Rejected</option>
                        <option value="All" <?php if($filter_status == 'All') echo 'selected'; ?>>All Requests</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Sort By Date</label>
                    <select name="sort_order" class="filter-select">
                        <option value="DESC" <?php if($sort_order == 'DESC') echo 'selected'; ?>>Newest First</option>
                        <option value="ASC" <?php if($sort_order == 'ASC') echo 'selected'; ?>>Oldest First</option>
                    </select>
                </div>

                <button type="submit" class="btn-filter"><i class="fa fa-filter"></i> Apply</button>
            </form>

            <?php if (count($requests) > 0): ?>
                <?php foreach ($requests as $req): 
                    $status = $req['fyp_requeststatus'];
                    if (empty($status)) $status = 'Pending';
                    $badgeClass = 'status-pending';
                    if ($status == 'Approve') $badgeClass = 'status-approve';
                    if ($status == 'Reject') $badgeClass = 'status-reject';
                ?>
                    <div class="req-card">
                        <div class="req-info" style="flex:1;">
                            <span class="req-status <?php echo $badgeClass; ?>"><?php echo $status; ?></span>
                            <h3><?php echo htmlspecialchars($req['fyp_studname']); ?> <small style="font-size:14px; color:#999;">(<?php echo htmlspecialchars($req['fyp_studid']); ?>)</small></h3>
                            <p><strong>Project:</strong> <?php echo htmlspecialchars($req['fyp_projecttitle']); ?></p>
                            
                            <?php if (!empty($req['group_details'])): ?>
                                <div class="group-info-box">
                                    <div class="group-name-title"><i class="fa fa-users"></i> Team: <?php echo htmlspecialchars($req['group_details']['name']); ?></div>
                                    <ul class="group-mem-list">
                                        <li><b>Leader:</b> <?php echo htmlspecialchars($req['fyp_studname']); ?></li>
                                        <?php foreach($req['group_details']['members'] as $mem): ?>
                                            <li><b>Member:</b> <?php echo htmlspecialchars($mem['fyp_studname']); ?> (<?php echo $mem['fyp_studfullid']; ?>)</li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <p class="req-meta"><i class="fa fa-calendar-alt"></i> Applied on: <?php echo $req['fyp_datecreated']; ?></p>
                        </div>
                        
                        <div class="req-actions">
                            <?php if ($status == 'Pending'): ?>
                                <a href="?auth_user_id=<?php echo $auth_user_id; ?>&action=Approve&req_id=<?php echo $req['fyp_requestid']; ?>" class="btn-act btn-approve" onclick="return confirm('Accept this student/team?')">
                                    <i class="fa fa-check"></i> Accept
                                </a>
                                <a href="?auth_user_id=<?php echo $auth_user_id; ?>&action=Reject&req_id=<?php echo $req['fyp_requestid']; ?>" class="btn-act btn-reject" onclick="return confirm('Reject this request?')">
                                    <i class="fa fa-times"></i> Reject
                                </a>
                            <?php else: ?>
                                <div style="font-size:13px; color:#999; font-style:italic;">Action Taken</div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fa fa-inbox" style="font-size: 48px; opacity: 0.3; margin-bottom:15px;"></i>
                    <p>No project requests found.</p>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>