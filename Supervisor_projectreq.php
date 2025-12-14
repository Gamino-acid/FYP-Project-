<?php
// ====================================================
// supervisor_projectreq.php - Request Management (With Group Details & Status Update)
// ====================================================
include("connect.php");

// 1. Auth Check
$auth_user_id = $_GET['auth_user_id'] ?? null;
if (!$auth_user_id) { header("location: login.php"); exit; }

// 2. Get Supervisor Data
$sv_data = [];
$user_name = 'Supervisor';
$sv_id = null; 

if (isset($conn)) {
    $sql_user = "SELECT fyp_username FROM `USER` WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_user)) { $stmt->bind_param("i", $auth_user_id); $stmt->execute(); $res=$stmt->get_result(); if($row=$res->fetch_assoc()) $user_name=$row['fyp_username']; $stmt->close(); }
    
    $sql_sv = "SELECT * FROM supervisor WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_sv)) { 
        $stmt->bind_param("i", $auth_user_id); 
        $stmt->execute(); 
        $res=$stmt->get_result(); 
        if($res->num_rows > 0) { 
            $sv_data = $res->fetch_assoc(); 
            $sv_id = $sv_data['fyp_supervisorid'];
            if(!empty($sv_data['fyp_name'])) $user_name=$sv_data['fyp_name']; 
        }
        $stmt->close(); 
    }
}

// 3. Logic Processing (Approve/Reject)
if (isset($_GET['action']) && isset($_GET['req_id']) && $sv_id) {
    $req_id = $_GET['req_id'];
    $action = $_GET['action']; // 'Approve' or 'Reject'

    if ($action == 'Reject') {
        // Simple Reject
        $conn->query("UPDATE project_request SET fyp_requeststatus = 'Reject' WHERE fyp_requestid = $req_id");
        echo "<script>alert('Request Rejected.'); window.location.href='supervisor_projectreq.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
    } 
    elseif ($action == 'Approve') {
        // Quota Check
        $limit = 0;
        $current_count = 0;
        
        $q_sql = "SELECT fyp_numofstudent FROM quota WHERE fyp_supervisorid = '$sv_id' LIMIT 1";
        $q_res = $conn->query($q_sql);
        if ($q_row = $q_res->fetch_assoc()) {
            $limit = intval($q_row['fyp_numofstudent']);
        }

        $c_sql = "SELECT COUNT(*) as cnt FROM project_request WHERE fyp_supervisorid = '$sv_id' AND fyp_requeststatus = 'Approve'";
        $c_res = $conn->query($c_sql);
        if ($c_row = $c_res->fetch_assoc()) {
            $current_count = intval($c_row['cnt']);
        }

        if ($current_count >= $limit) {
            echo "<script>alert('Cannot Approve: Quota Limit Exceeded! (Current: $current_count / Max: $limit)'); window.location.href='supervisor_projectreq.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
        } else {
            // ==========================================================
            // [CORE LOGIC] Register Leader AND Team Members
            // ==========================================================
            
            // 1. 获取 Request 详情
            $req_sql = "SELECT fyp_studid, fyp_projectid FROM project_request WHERE fyp_requestid = $req_id LIMIT 1";
            $req_res = $conn->query($req_sql);
            
            if ($req_row = $req_res->fetch_assoc()) {
                $target_stud_id = $req_row['fyp_studid']; // 这是 Leader ID
                $target_proj_id = $req_row['fyp_projectid'];
                $date_now = date('Y-m-d H:i:s');

                // 2. 准备要注册的学生列表 (默认为队长一人)
                $students_to_register = [$target_stud_id];

                // 检查是否为 Group Leader，如果是，找出所有队员
                $g_chk = $conn->query("SELECT group_id FROM student_group WHERE leader_id = '$target_stud_id' LIMIT 1");
                if ($grp = $g_chk->fetch_assoc()) {
                    $gid = $grp['group_id'];
                    // 找出 Accepted 的队员
                    $m_res = $conn->query("SELECT invitee_id FROM group_request WHERE group_id = '$gid' AND request_status = 'Accepted'");
                    while ($m = $m_res->fetch_assoc()) {
                        $students_to_register[] = $m['invitee_id'];
                    }
                }

                // 3. 循环注册每一位学生到 FYP_REGISTRATION 表
                $success_count = 0;
                $ins_reg = $conn->prepare("INSERT INTO fyp_registration (fyp_studid, fyp_projectid, fyp_supervisorid, fyp_datecreated) VALUES (?, ?, ?, ?)");
                
                foreach ($students_to_register as $sid) {
                    // 简单查重，防止重复插入
                    $dup_chk = $conn->query("SELECT fyp_regid FROM fyp_registration WHERE fyp_studid = '$sid'");
                    if ($dup_chk->num_rows == 0) {
                        $ins_reg->bind_param("siis", $sid, $target_proj_id, $sv_id, $date_now);
                        if ($ins_reg->execute()) {
                            $success_count++;
                        }
                    }
                }
                $ins_reg->close();

                // 4. 更新 Request 状态为 Approve
                $conn->query("UPDATE project_request SET fyp_requeststatus = 'Approve' WHERE fyp_requestid = $req_id");
                
                // 5. 【关键更新】更新 Project 状态为 Taken
                // 这样一来，在学生端的 std_projectreg.php 中，这个项目就会变成 "Taken" 状态，按钮变灰不可选
                $conn->query("UPDATE project SET fyp_projectstatus = 'Taken' WHERE fyp_projectid = $target_proj_id");

                echo "<script>alert('Request Approved! Project is now TAKEN. $success_count student(s) registered.'); window.location.href='supervisor_projectreq.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
            } else {
                echo "<script>alert('Request data not found.');</script>";
            }
        }
    }
}

// 4. Fetch Requests Data (With Group Info)
$requests = [];
if ($sv_id) {
    // 同时也获取 project type
    $sql_req = "SELECT r.*, s.fyp_studname, s.fyp_studfullid, p.fyp_projecttitle, p.fyp_projecttype 
                FROM project_request r
                JOIN student s ON r.fyp_studid = s.fyp_studid
                JOIN project p ON r.fyp_projectid = p.fyp_projectid
                WHERE r.fyp_supervisorid = '$sv_id' 
                ORDER BY r.fyp_datecreated DESC";
    $res_req = $conn->query($sql_req);
    
    while($row = $res_req->fetch_assoc()){
        // 初始化 Group 数据
        $row['group_details'] = null; 
        
        // 如果是 Group Project，去查找该学生领导的队伍信息
        if ($row['fyp_projecttype'] == 'Group') {
            $leader_id = $row['fyp_studid'];
            $g_sql = "SELECT group_id, group_name FROM student_group WHERE leader_id = '$leader_id' LIMIT 1";
            $g_res = $conn->query($g_sql);
            
            if ($g_info = $g_res->fetch_assoc()) {
                // 找到队伍了，存入组名
                $row['group_details'] = [
                    'name' => $g_info['group_name'],
                    'members' => []
                ];
                
                // 查找队员
                $gid = $g_info['group_id'];
                $m_sql = "SELECT s.fyp_studname, s.fyp_studfullid 
                          FROM group_request gr 
                          JOIN student s ON gr.invitee_id = s.fyp_studid 
                          WHERE gr.group_id = '$gid' AND gr.request_status = 'Accepted'";
                $m_res = $conn->query($m_sql);
                while($mem = $m_res->fetch_assoc()){
                    $row['group_details']['members'][] = $mem;
                }
            }
        }
        $requests[] = $row;
    }
}

// Menu Items
$current_page = 'project_requests';
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
    'schedule'  => ['name' => 'My Schedule', 'icon' => 'fa-calendar-alt', 'link' => 'Supervisor_mainpage.php?page=schedule'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Requests</title>
    <?php $favicon = !empty($sv_data['fyp_profileimg']) ? $sv_data['fyp_profileimg'] : "image/user.png"; ?>
    <link rel="icon" type="image/png" href="<?php echo $favicon; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* CSS 保持不变 */
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
        
        @media (max-width: 900px) { .layout-container { flex-direction: column; } .sidebar { width: 100%; min-height: auto; } .req-card { flex-direction: column; gap: 15px; } .req-actions { width: 100%; flex-direction: row; } .btn-act { flex: 1; justify-content: center; } }
    </style>
</head>
<body>

    <header class="topbar">
        <div class="logo"> FYP System</div>
        <div class="topbar-right">
            <div class="user-profile-summary">
                <span class="user-name-display"><?php echo htmlspecialchars($user_name); ?></span>
                <span class="user-role-badge">Lecturer</span>
            </div>
            <div class="user-avatar-circle"><img src="<?php echo $favicon; ?>" alt="User Avatar"></div>
            <a href="login.php" class="logout-btn"><i class="fa fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>

    <div class="layout-container">
        <aside class="sidebar">
            <ul class="menu-list">
                <?php foreach ($menu_items as $key => $item): ?>
                    <?php 
                        $isActive = ($key == 'students');
                        $hasActiveChild = ($key == 'students');
                        
                        $linkUrl = isset($item['link']) ? $item['link'] : "#";
                        if (strpos($linkUrl, '.php') !== false) {
                             $separator = (strpos($linkUrl, '?') !== false) ? '&' : '?';
                             $linkUrl .= $separator . "auth_user_id=" . urlencode($auth_user_id);
                        } elseif (strpos($linkUrl, '?') === 0) {
                             $linkUrl .= "&auth_user_id=" . urlencode($auth_user_id);
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
                            <h3><?php echo htmlspecialchars($req['fyp_studname']); ?> <small style="font-size:14px; color:#999;">(<?php echo htmlspecialchars($req['fyp_studfullid']); ?>)</small></h3>
                            <p><strong>Project:</strong> <?php echo htmlspecialchars($req['fyp_projecttitle']); ?></p>
                            
                            <!-- Group Details Section -->
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