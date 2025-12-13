<?php
// ====================================================
// std_request_status.php - 组队与邀请管理 (基于 student_group 表)
// ====================================================
include("connect.php");

// 1. 验证用户登录
$auth_user_id = $_GET['auth_user_id'] ?? null;
if (!$auth_user_id) { header("location: login.php"); exit; }

// 2. 获取当前登录学生信息
$stud_data = [];
$current_stud_id = '';
$current_stud_name = 'Student';
$current_stud_group_status = 'Individual';

if (isset($conn)) {
    // 获取 USER 表名字
    $sql_user = "SELECT fyp_username FROM `USER` WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_user)) { 
        $stmt->bind_param("i", $auth_user_id); $stmt->execute(); 
        $res = $stmt->get_result(); 
        if ($row = $res->fetch_assoc()) $current_stud_name = $row['fyp_username']; 
        $stmt->close(); 
    }
    
    // 获取 STUDENT 表详细信息
    $sql_stud = "SELECT * FROM STUDENT WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_stud)) { 
        $stmt->bind_param("i", $auth_user_id); $stmt->execute(); 
        $res = $stmt->get_result(); 
        if ($row = $res->fetch_assoc()) { 
            $stud_data = $row; 
            $current_stud_id = $row['fyp_studid'];
            if (!empty($row['fyp_studname'])) $current_stud_name = $row['fyp_studname'];
            if (!empty($row['fyp_group'])) $current_stud_group_status = $row['fyp_group'];
        }
        $stmt->close(); 
    }
}

// ----------------------------------------------------
// 3. 判断当前用户是否属于某个小组 (作为 Leader 或 Member)
// ----------------------------------------------------
$my_group = null; // 存储我的小组信息 (student_group 表的数据)
$my_role = '';    // 'Leader' 或 'Member'

if ($current_stud_id) {
    // 3a. 检查我是否是 Leader (查询 student_group 表)
    $sql_leader = "SELECT * FROM student_group WHERE leader_id = ?";
    if ($stmt = $conn->prepare($sql_leader)) {
        $stmt->bind_param("s", $current_stud_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $my_group = $row;
            $my_role = 'Leader';
        }
    }

    // 3b. 如果不是 Leader，检查我是否是 Member (查询 group_request 表 Accepted 状态)
    if (!$my_group) {
        // 关联 student_group 表以获取组名
        $sql_member = "SELECT g.*, gr.request_id 
                       FROM group_request gr 
                       JOIN student_group g ON gr.group_id = g.group_id 
                       WHERE gr.invitee_id = ? AND gr.request_status = 'Accepted'";
        if ($stmt = $conn->prepare($sql_member)) {
            $stmt->bind_param("s", $current_stud_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $my_group = $row;
                $my_role = 'Member';
            }
        }
    }
}

// ----------------------------------------------------
// 4. AJAX 搜索功能
// ----------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] == 'search_students') {
    header('Content-Type: application/json');
    $keyword = $_GET['keyword'] ?? '';
    
    if (strlen($keyword) < 1) { echo json_encode([]); exit; }
    $keyword = "%" . $keyword . "%";
    
    // 搜索逻辑：
    // 1. 名字匹配
    // 2. ID 不是自己
    // 3. 状态是 'Individual'
    // 4. 且该学生不是任何小组的 Leader (防止邀请了已经建组的队长)
    $sql = "SELECT s.fyp_studid, s.fyp_studname, s.fyp_studfullid 
            FROM STUDENT s
            LEFT JOIN student_group g ON s.fyp_studid = g.leader_id
            WHERE s.fyp_studname LIKE ? 
            AND s.fyp_userid != ? 
            AND s.fyp_group = 'Individual'
            AND g.group_id IS NULL  -- 确保他不是其他组的队长
            LIMIT 5";
            
    $result = [];
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("si", $keyword, $auth_user_id);
        $stmt->execute(); 
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $result[] = [
                'studid' => $row['fyp_studid'], 
                'name' => $row['fyp_studname'],
                'fullid' => $row['fyp_studfullid']
            ];
        }
    }
    echo json_encode($result); exit;
}

// ----------------------------------------------------
// 5. 处理 POST/GET 请求
// ----------------------------------------------------

// A. 创建小组 (Create Group)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_group'])) {
    $group_name = trim($_POST['group_name']);
    
    if (empty($group_name)) {
        echo "<script>alert('Please enter a group name.');</script>";
    } else {
        // 检查是否重名
        $chk = $conn->prepare("SELECT group_id FROM student_group WHERE group_name = ?");
        $chk->bind_param("s", $group_name);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
             echo "<script>alert('Group Name already taken. Please choose another.');</script>";
        } else {
            // 插入 student_group 表
            // 注意：此时 fyp_group 状态不改变，依然是 Individual，直到有人接受邀请
            $sql_ins = "INSERT INTO student_group (group_name, leader_id, created_at, status) VALUES (?, ?, NOW(), 'Recruiting')";
            if ($stmt = $conn->prepare($sql_ins)) {
                $stmt->bind_param("ss", $group_name, $current_stud_id);
                if ($stmt->execute()) {
                    echo "<script>alert('Group Created! You can now invite members.'); window.location.href='std_request_status.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
                } else {
                    echo "<script>alert('Error creating group: " . $stmt->error . "');</script>";
                }
            }
        }
    }
}

// B. 发起邀请 (Invite Teammate) - 仅限 Leader
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['invite_teammate'])) {
    if ($my_role != 'Leader') {
        echo "<script>alert('Only the team leader can invite members.');</script>";
    } else {
        $target_stud_id = $_POST['target_stud_id'];
        $my_group_id = $my_group['group_id'];

        // 1. 检查人数限制 (Leader + Accepted Members < 3)
        $sql_count = "SELECT count(*) as member_count FROM group_request WHERE group_id = ? AND request_status = 'Accepted'";
        $stmt_c = $conn->prepare($sql_count);
        $stmt_c->bind_param("i", $my_group_id);
        $stmt_c->execute();
        $cnt = $stmt_c->get_result()->fetch_assoc()['member_count'];
        
        // 队长(1) + 成员(cnt)
        if (($cnt + 1) >= 3) {
            echo "<script>alert('Your team is full (3 members). Cannot invite more.'); window.location.href='std_request_status.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
        } else {
            // 2. 检查是否重复邀请
            $chk = $conn->prepare("SELECT request_id FROM group_request WHERE group_id = ? AND invitee_id = ? AND request_status != 'Rejected'");
            $chk->bind_param("is", $my_group_id, $target_stud_id);
            $chk->execute();
            if ($chk->get_result()->num_rows == 0) {
                // 3. 插入 group_request
                $ins = $conn->prepare("INSERT INTO group_request (group_id, inviter_id, invitee_id, request_status) VALUES (?, ?, ?, 'Pending')");
                $ins->bind_param("iss", $my_group_id, $current_stud_id, $target_stud_id);
                if ($ins->execute()) {
                    echo "<script>alert('Invitation sent successfully!'); window.location.href='std_request_status.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
                }
            } else {
                echo "<script>alert('Already invited this student.'); window.location.href='std_request_status.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
            }
        }
    }
}

// C. 处理邀请回应 (Accept/Reject) - 由被邀请人操作
if (isset($_GET['action']) && isset($_GET['req_id'])) {
    $req_id = $_GET['req_id'];
    
    // 获取请求详情
    $sql_req = "SELECT gr.*, g.leader_id FROM group_request gr JOIN student_group g ON gr.group_id = g.group_id WHERE gr.request_id = ? AND gr.invitee_id = ?";
    $stmt_r = $conn->prepare($sql_req);
    $stmt_r->bind_param("is", $req_id, $current_stud_id);
    $stmt_r->execute();
    $res_r = $stmt_r->get_result();
    
    if ($row_req = $res_r->fetch_assoc()) {
        $group_id = $row_req['group_id'];
        $leader_id = $row_req['leader_id'];
        
        if ($_GET['action'] == 'AcceptInvite') {
            // 双重检查人数
            $sql_check = "SELECT count(*) as c FROM group_request WHERE group_id = ? AND request_status = 'Accepted'";
            $stmt_ck = $conn->prepare($sql_check);
            $stmt_ck->bind_param("i", $group_id);
            $stmt_ck->execute();
            $curr_mem = $stmt_ck->get_result()->fetch_assoc()['c'];
            
            if (($curr_mem + 1) >= 3) {
                 echo "<script>alert('Failed to join. This team is already full.'); window.location.href='std_request_status.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
            } else {
                // 1. 更新请求状态为 Accepted
                $conn->query("UPDATE group_request SET request_status = 'Accepted' WHERE request_id = $req_id");
                
                // 2. 【核心逻辑】状态变更
                // 只有在这里，队长和队员的状态才正式变为 'Group'
                $conn->query("UPDATE STUDENT SET fyp_group = 'Group' WHERE fyp_studid = '$current_stud_id'"); // 我 (被邀请人)
                $conn->query("UPDATE STUDENT SET fyp_group = 'Group' WHERE fyp_studid = '$leader_id'");     // 队长
                
                // 3. (可选) 如果满了(3人)，更新 student_group 表状态为 Full
                if (($curr_mem + 1 + 1) >= 3) { // +1我 +1队长
                    $conn->query("UPDATE student_group SET status = 'Full' WHERE group_id = $group_id");
                }

                echo "<script>alert('Invitation Accepted! You joined the team.'); window.location.href='std_request_status.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
            }
        }
        
        if ($_GET['action'] == 'RejectInvite') {
            $conn->query("UPDATE group_request SET request_status = 'Rejected' WHERE request_id = $req_id");
            echo "<script>alert('Invitation Rejected.'); window.location.href='std_request_status.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
        }
    }
}

// ----------------------------------------------------
// 6. 获取页面展示数据
// ----------------------------------------------------

// A. 如果还没有组，显示收到的邀请 (Incoming Invitations)
// 注意：即使我是队长(但还没人接受)，我也算在"有组"的逻辑里(my_group不为空)，所以我不会看到这个界面，
// 但作为队长我不会收到别人的邀请，因为我在搜索里被过滤了。
// 这个逻辑主要是给还没有创建组，也没加入组的人看的。
$incoming_invites = [];
if (!$my_group) {
    // 关联 student_group 表以显示组名
    $sql_inc = "SELECT gr.*, g.group_name, s.fyp_studname as leader_name 
                FROM group_request gr 
                JOIN student_group g ON gr.group_id = g.group_id
                JOIN STUDENT s ON g.leader_id = s.fyp_studid
                WHERE gr.invitee_id = ? AND gr.request_status = 'Pending'";
    if ($stmt = $conn->prepare($sql_inc)) {
        $stmt->bind_param("s", $current_stud_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $incoming_invites[] = $row;
    }
}

// B. 如果有组 (Leader 或 Member)，获取小组成员列表
$team_members = [];
if ($my_group) {
    $group_id = $my_group['group_id'];
    
    // 1. 获取队长信息
    $sql_l = "SELECT fyp_studname, fyp_studid FROM STUDENT WHERE fyp_studid = ?";
    $stmt_l = $conn->prepare($sql_l);
    $stmt_l->bind_param("s", $my_group['leader_id']);
    $stmt_l->execute();
    $leader_info = $stmt_l->get_result()->fetch_assoc();
    if ($leader_info) {
        $team_members[] = [
            'role' => 'Leader', 
            'name' => $leader_info['fyp_studname'], 
            'id' => $leader_info['fyp_studid'], 
            'status' => 'Active'
        ];
    }
    
    // 2. 获取成员 (Accepted Requests)
    $sql_m = "SELECT gr.*, s.fyp_studname, s.fyp_studid 
              FROM group_request gr 
              JOIN STUDENT s ON gr.invitee_id = s.fyp_studid 
              WHERE gr.group_id = ? AND gr.request_status = 'Accepted'";
    $stmt_m = $conn->prepare($sql_m);
    $stmt_m->bind_param("i", $group_id);
    $stmt_m->execute();
    $res_m = $stmt_m->get_result();
    while ($row = $res_m->fetch_assoc()) {
        $team_members[] = [
            'role' => 'Member', 
            'name' => $row['fyp_studname'], 
            'id' => $row['fyp_studid'], 
            'status' => 'Active'
        ];
    }
}

// C. 获取我发出的邀请 (仅限 Leader 查看 Pending 状态)
$pending_requests = [];
if ($my_role == 'Leader') {
    $sql_p = "SELECT gr.*, s.fyp_studname 
              FROM group_request gr 
              JOIN STUDENT s ON gr.invitee_id = s.fyp_studid 
              WHERE gr.group_id = ? AND gr.request_status = 'Pending'";
    $stmt_p = $conn->prepare($sql_p);
    $stmt_p->bind_param("i", $my_group['group_id']);
    $stmt_p->execute();
    $res_p = $stmt_p->get_result();
    while ($row = $res_p->fetch_assoc()) $pending_requests[] = $row;
}

// 菜单定义
$current_page = 'team_invitations';
$menu_items = [
    'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home', 'link' => 'Student_mainpage.php?page=dashboard'],
    'profile' => ['name' => 'My Profile', 'icon' => 'fa-user', 'link' => 'std_profile.php'],
    'project_mgmt' => [
        'name' => 'Final Year Project',
        'icon' => 'fa-project-diagram',
        'sub_items' => [
            'group_setup' => ['name' => 'Project Registration', 'icon' => 'fa-users', 'link' => 'std_projectreg.php'], 
            'team_invitations' => ['name' => 'Request & Team Status', 'icon' => 'fa-tasks', 'link' => 'std_request_status.php'],
            'proposals' => ['name' => 'Proposal Submission', 'icon' => 'fa-file-alt', 'link' => 'Student_mainpage.php?page=proposals'],
        ]
    ],
    'grades' => ['name' => 'My Grades', 'icon' => 'fa-star', 'link' => 'Student_mainpage.php?page=grades'],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Management</title>
    <?php $favicon = !empty($stud_data['fyp_profileimg']) ? $stud_data['fyp_profileimg'] : "image/user.png"; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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
        .welcome-card { background: #fff; padding: 30px; border-radius: 12px; box-shadow: var(--card-shadow); border-left: 5px solid var(--primary-color); }
        .page-title { font-size: 24px; margin: 0 0 10px 0; color: var(--text-color); }
        .status-card { background: #fff; border-radius: 10px; padding: 25px; margin-bottom: 20px; box-shadow: 0 4px 10px rgba(0,0,0,0.03); border: 1px solid #eee; }
        .card-header { font-size: 18px; font-weight: 600; color: #333; margin-bottom: 15px; border-bottom: 1px solid #f0f0f0; padding-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
        .btn-action { background: var(--primary-color); color: white; border: none; padding: 8px 15px; border-radius: 6px; cursor: pointer; text-decoration: none; font-size: 14px; }
        .btn-reject { background: #dc3545; } .btn-accept { background: #28a745; }
        .form-input { padding: 10px; border: 1px solid #ddd; border-radius: 6px; width: 100%; box-sizing: border-box; }
        .team-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .team-table th { text-align: left; padding: 10px; background: #f8f9fa; color: #555; font-size: 13px; }
        .team-table td { padding: 10px; border-bottom: 1px solid #eee; font-size: 14px; }
        .search-results { position: absolute; background: white; border: 1px solid #ddd; width: 100%; z-index: 10; display: none; }
        .search-item { padding: 10px; cursor: pointer; border-bottom: 1px solid #eee; }
        .search-item:hover { background: #f5f5f5; }
        .group-badge { background: #e3effd; color: var(--primary-color); padding: 5px 12px; border-radius: 15px; font-weight: 600; }
        
        @media (max-width: 900px) { .layout-container { flex-direction: column; } .sidebar { width: 100%; min-height: auto; } }
    </style>
</head>
<body>

    <header class="topbar">
        <div class="logo">FYP System</div>
        <div class="topbar-right">
            <div class="user-profile-summary">
                <span class="user-name-display"><?php echo htmlspecialchars($current_stud_name); ?></span>
                <span class="user-role-badge">Student</span>
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
                        $isActive = ($key == $current_page);
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
                                    if (strpos($subLinkUrl, '.php') !== false) {
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
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <h1 class="page-title">Team Management</h1>
                        <p style="color: #666; margin: 0;">Create a group, invite members, or respond to invitations.</p>
                    </div>
                    <div>
                        <span style="color:#666;">My Status:</span>
                        <span class="group-badge"><?php echo $current_stud_group_status; ?></span>
                    </div>
                </div>
            </div>

            <!-- 逻辑分支：显示哪个视图 -->
            <?php if (!$my_group): ?>
                
                <!-- SCENE 1: 没有小组 (Free Agent) -->
                
                <!-- 1a. 创建小组表单 -->
                <div class="status-card">
                    <div class="card-header">
                        <span><i class="fa fa-plus-circle"></i> Create a New Team</span>
                    </div>
                    <p style="color:#666; font-size:14px; margin-bottom:15px;">To invite members, you must first create a team.</p>
                    <form method="POST" style="display:flex; gap:10px;">
                        <input type="text" name="group_name" class="form-input" placeholder="Enter Team Name (e.g., Tech Warriors)" required>
                        <button type="submit" name="create_group" class="btn-action">Create Team</button>
                    </form>
                </div>

                <!-- 1b. 收到的邀请 -->
                <div class="status-card">
                    <div class="card-header">
                        <span><i class="fa fa-envelope"></i> Incoming Invitations</span>
                    </div>
                    <?php if (count($incoming_invites) > 0): ?>
                        <table class="team-table">
                            <thead><tr><th>Team Name</th><th>Leader</th><th>Action</th></tr></thead>
                            <tbody>
                                <?php foreach ($incoming_invites as $inv): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($inv['group_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($inv['leader_name']); ?></td>
                                        <td>
                                            <a href="?auth_user_id=<?php echo $auth_user_id; ?>&action=AcceptInvite&req_id=<?php echo $inv['request_id']; ?>" class="btn-action btn-accept" onclick="return confirm('Join this team?')">Accept</a>
                                            <a href="?auth_user_id=<?php echo $auth_user_id; ?>&action=RejectInvite&req_id=<?php echo $inv['request_id']; ?>" class="btn-action btn-reject" onclick="return confirm('Decline invitation?')">Decline</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="color:#999; font-style:italic;">No invitations yet.</p>
                    <?php endif; ?>
                </div>

            <?php else: ?>

                <!-- SCENE 2: 已经在小组中 (Leader 或 Member) -->
                
                <div class="status-card" style="border-left: 5px solid #0056b3;">
                    <div class="card-header">
                        <span><i class="fa fa-users"></i> My Team: <?php echo htmlspecialchars($my_group['group_name']); ?></span>
                        <span class="group-badge"><?php echo $my_role; ?></span>
                    </div>

                    <!-- 2a. 成员列表 -->
                    <table class="team-table">
                        <thead><tr><th>Role</th><th>Name</th><th>Student ID</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($team_members as $mem): ?>
                                <tr>
                                    <td>
                                        <?php if($mem['role']=='Leader') echo '<i class="fa fa-crown" style="color:gold; margin-right:5px;"></i>'; ?>
                                        <?php echo $mem['role']; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($mem['name']); ?></td>
                                    <td><?php echo $mem['id']; ?></td>
                                    <td><span style="color:green; font-weight:600;">Active</span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- 2b. Leader 专属功能：邀请成员 -->
                <?php if ($my_role == 'Leader'): ?>
                    <div class="status-card">
                        <div class="card-header">
                            <span><i class="fa fa-user-plus"></i> Invite Members</span>
                            <span style="font-size:13px; color:#666;">Size: <?php echo count($team_members); ?>/3</span>
                        </div>
                        
                        <?php if (count($team_members) < 3): ?>
                            <div style="position:relative;">
                                <form method="POST">
                                    <input type="hidden" name="target_stud_id" id="targetStudId">
                                    <div style="display:flex; gap:10px;">
                                        <input type="text" id="studSearch" class="form-input" placeholder="Search student name..." autocomplete="off">
                                        <button type="submit" name="invite_teammate" id="btnInvite" class="btn-action" disabled>Send Invite</button>
                                    </div>
                                    <div id="searchRes" class="search-results"></div>
                                </form>
                            </div>
                        <?php else: ?>
                            <div style="background:#eee; padding:10px; border-radius:5px; text-align:center;">Team is Full.</div>
                        <?php endif; ?>

                        <!-- 显示已发送但未处理的邀请 -->
                        <?php if (count($pending_requests) > 0): ?>
                            <h4 style="margin-top:20px; border-bottom:1px solid #eee; padding-bottom:5px;">Pending Invitations</h4>
                            <ul style="list-style:none; padding:0;">
                                <?php foreach ($pending_requests as $pr): ?>
                                    <li style="padding:10px; border-bottom:1px solid #f0f0f0; display:flex; justify-content:space-between;">
                                        <span>Invited: <strong><?php echo htmlspecialchars($pr['fyp_studname']); ?></strong></span>
                                        <span style="color:orange;">Pending...</span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            <?php endif; ?>

        </main>
    </div>

    <script>
        // 搜索逻辑
        const searchInput = document.getElementById('studSearch');
        const searchBox = document.getElementById('searchRes');
        const targetInput = document.getElementById('targetStudId');
        const inviteBtn = document.getElementById('btnInvite');

        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const val = this.value.trim();
                if (val.length < 1) { searchBox.style.display = 'none'; return; }
                
                fetch(`std_request_status.php?action=search_students&keyword=${encodeURIComponent(val)}&auth_user_id=<?php echo $auth_user_id; ?>`)
                    .then(res => res.json())
                    .then(data => {
                        searchBox.innerHTML = '';
                        if (data.length > 0) {
                            searchBox.style.display = 'block';
                            data.forEach(stud => {
                                const div = document.createElement('div');
                                div.className = 'search-item';
                                div.innerHTML = `<strong>${stud.name}</strong> <small>(${stud.fullid || stud.studid})</small>`;
                                div.onclick = () => {
                                    searchInput.value = stud.name;
                                    targetInput.value = stud.studid;
                                    searchBox.style.display = 'none';
                                    inviteBtn.disabled = false;
                                };
                                searchBox.appendChild(div);
                            });
                        } else { searchBox.style.display = 'none'; }
                    });
            });
            // 点击外部关闭搜索框
            document.addEventListener('click', (e) => {
                if (e.target !== searchInput && e.target !== searchBox) searchBox.style.display = 'none';
            });
        }
    </script>
</body>
</html>