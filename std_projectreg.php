<?php
// ====================================================
// std_projectreg.php - 学生申请项目 (优化版: Member 禁用 + 已申请禁用 + 搜索过滤)
// ====================================================
include("connect.php");

// 1. 验证用户登录
$auth_user_id = $_GET['auth_user_id'] ?? null;
if (!$auth_user_id) { header("location: login.php"); exit; }

// 2. 获取当前学生信息 (包括 fyp_group 状态)
$stud_data = [];
$my_stud_id = '';
$my_group_status = 'Individual'; // 默认为 Individual
$user_name = 'Student';
$is_leader = false; // 默认为 false
$my_group_id = 0; // 存储 Group ID

if (isset($conn)) {
    // 获取 USER 表名字
    $sql_user = "SELECT fyp_username FROM `USER` WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_user)) { 
        $stmt->bind_param("i", $auth_user_id); 
        $stmt->execute(); 
        $res=$stmt->get_result(); 
        if($row=$res->fetch_assoc()) $user_name=$row['fyp_username']; 
        $stmt->close(); 
    }
    
    // 获取 STUDENT 表详细信息
    $sql_stud = "SELECT * FROM STUDENT WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_stud)) { 
        $stmt->bind_param("i", $auth_user_id); 
        $stmt->execute(); 
        $res=$stmt->get_result(); 
        if($row=$res->fetch_assoc()){ 
            $stud_data=$row; 
            $my_stud_id = $row['fyp_studid'];
            if(!empty($row['fyp_studname'])) $user_name=$row['fyp_studname'];
            if(!empty($row['fyp_group'])) $my_group_status = $row['fyp_group']; // 获取当前状态
        } 
        $stmt->close(); 
    }

    // 【新增】如果状态是 Group，检查是否为 Leader，或者获取 Leader ID
    $target_applicant_id = $my_stud_id; // 默认检查自己

    if ($my_group_status == 'Group') {
        // 先查我是不是 Leader
        $chk_leader = "SELECT group_id FROM student_group WHERE leader_id = '$my_stud_id'";
        $res_l = $conn->query($chk_leader);
        if ($res_l && $res_l->num_rows > 0) {
            $is_leader = true;
            $grp = $res_l->fetch_assoc();
            $my_group_id = $grp['group_id'];
        } else {
            // 如果不是 Leader，那我是 Member，我得找出我的 Leader 是谁
            // 因为申请记录是挂在 Leader 头上的
            $find_leader_sql = "SELECT g.leader_id 
                                FROM group_request gr 
                                JOIN student_group g ON gr.group_id = g.group_id 
                                WHERE gr.invitee_id = '$my_stud_id' AND gr.request_status = 'Accepted' LIMIT 1";
            $res_fl = $conn->query($find_leader_sql);
            if ($row_fl = $res_fl->fetch_assoc()) {
                $target_applicant_id = $row_fl['leader_id']; // 这里的 ID 换成 Leader 的
            }
        }
    }
}

// ----------------------------------------------------
// 【全局检查】是否已经有 Pending 或 Approved 的申请
// ----------------------------------------------------
$has_active_application = false;
$active_app_status = '';

// 检查 project_request 表 (Pending/Approve)
$chk_app_sql = "SELECT fyp_requeststatus FROM project_request 
                WHERE fyp_studid = '$target_applicant_id' 
                AND (fyp_requeststatus = 'Pending' OR fyp_requeststatus = 'Approve') 
                LIMIT 1";
$res_app = $conn->query($chk_app_sql);
if ($res_app && $res_app->num_rows > 0) {
    $has_active_application = true;
    $row_app = $res_app->fetch_assoc();
    $active_app_status = $row_app['fyp_requeststatus'];
}

// ----------------------------------------------------
// 【拒绝检查】获取该学生（或团队）被拒绝过的 Project ID 列表
// ----------------------------------------------------
$rejected_project_ids = [];
$chk_rej_sql = "SELECT fyp_projectid FROM project_request 
                WHERE fyp_studid = '$target_applicant_id' 
                AND fyp_requeststatus = 'Reject'";
$res_rej = $conn->query($chk_rej_sql);
if ($res_rej) {
    while ($row_rej = $res_rej->fetch_assoc()) {
        $rejected_project_ids[] = $row_rej['fyp_projectid'];
    }
}

// ----------------------------------------------------
// 3. 处理申请提交 (POST)
// ----------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register_project_confirm'])) {
    
    // 如果已经有申请了，后端再次拦截
    if ($has_active_application) {
        echo "<script>alert('You already have an active project application ($active_app_status).'); window.history.back();</script>"; exit;
    }

    $proj_id = $_POST['project_id'];
    $date_now = date('Y-m-d H:i:s');

    // 如果该项目之前被拒绝过，拦截
    if (in_array($proj_id, $rejected_project_ids)) {
        echo "<script>alert('You cannot re-apply to a project that has already rejected you.'); window.history.back();</script>"; exit;
    }

    // A. 再次获取项目详情
    $chk_proj_sql = "SELECT fyp_projecttype, fyp_supervisorid, fyp_projectstatus FROM PROJECT WHERE fyp_projectid = ?";
    $stmt_p = $conn->prepare($chk_proj_sql);
    $stmt_p->bind_param("i", $proj_id);
    $stmt_p->execute();
    $res_p = $stmt_p->get_result();
    $proj_info = $res_p->fetch_assoc();
    $stmt_p->close();

    if (!$proj_info) {
        echo "<script>alert('Project not found.'); window.history.back();</script>"; exit;
    }
    
    $target_sv_id = $proj_info['fyp_supervisorid'];
    $proj_type = $proj_info['fyp_projecttype'];
    $proj_status = $proj_info['fyp_projectstatus'];

    // B. 验证逻辑
    if ($proj_status == 'Taken') {
        echo "<script>alert('Sorry, this project is already TAKEN.'); window.history.back();</script>"; exit;
    }

    if ($my_group_status != $proj_type) {
        $msg = ($my_group_status == 'Individual') 
            ? "You are currently an 'Individual' student. You cannot apply for a 'Group' project." 
            : "You are currently in a 'Group'. You cannot apply for an 'Individual' project.";
        echo "<script>alert(\"$msg\"); window.history.back();</script>"; exit;
    }

    if ($proj_type == 'Group' && !$is_leader) {
        echo "<script>alert('Only the Team Leader can apply for a Group Project.'); window.history.back();</script>"; exit;
    }
    
    if ($proj_type == 'Group' && $is_leader) {
        $count_mem_sql = "SELECT count(*) as member_count FROM group_request WHERE group_id = '$my_group_id' AND request_status = 'Accepted'";
        $cnt_res = $conn->query($count_mem_sql);
        $cnt_row = $cnt_res->fetch_assoc();
        $member_count = $cnt_row['member_count'];
        $total_size = 1 + $member_count;

        if ($total_size < 3) {
            echo "<script>alert('Application Failed: Your team must have exactly 3 members (Accepted) to apply. Current size: " . $total_size . "'); window.history.back();</script>"; exit;
        }
    }

    // C. 插入数据库
    $sql_ins = "INSERT INTO project_request (fyp_studid, fyp_supervisorid, fyp_projectid, fyp_requeststatus, fyp_datecreated) VALUES (?, ?, ?, 'Pending', ?)";
    if ($stmt = $conn->prepare($sql_ins)) {
        $stmt->bind_param("ssis", $my_stud_id, $target_sv_id, $proj_id, $date_now);
        if ($stmt->execute()) {
            echo "<script>alert('Application Submitted Successfully!'); window.location.href='std_projectreg.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
        } else {
            echo "<script>alert('Error submitting request: " . $stmt->error . "');</script>";
        }
        $stmt->close();
    }
}

// ----------------------------------------------------
// 4. 获取项目列表 (含搜索和过滤功能)
// ----------------------------------------------------
$available_projects = [];

// 获取 GET 参数
$filter_status = $_GET['filter_status'] ?? '';
$search_title = $_GET['search_title'] ?? '';
$search_sv = $_GET['search_sv'] ?? '';

// 构建 SQL
$sql = "SELECT p.*, s.fyp_name as sv_name, s.fyp_email as sv_email, s.fyp_contactno as sv_phone 
        FROM PROJECT p 
        LEFT JOIN supervisor s ON p.fyp_supervisorid = s.fyp_supervisorid 
        WHERE 1=1"; 

// 动态添加过滤条件
if (!empty($filter_status)) {
    $sql .= " AND p.fyp_projectstatus = '" . $conn->real_escape_string($filter_status) . "'";
}

if (!empty($search_title)) {
    $sql .= " AND p.fyp_projecttitle LIKE '%" . $conn->real_escape_string($search_title) . "%'";
}

if (!empty($search_sv)) {
    // 搜索 Supervisor 名字，同时也匹配 contactpersonname 作为 fallback
    $sv_term = $conn->real_escape_string($search_sv);
    $sql .= " AND (s.fyp_name LIKE '%$sv_term%' OR p.fyp_contactpersonname LIKE '%$sv_term%')";
}

$sql .= " ORDER BY p.fyp_datecreated DESC"; 

$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $available_projects[] = $row;
    }
}

// 5. 菜单定义
$current_page = 'group_setup';
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
    <title>Project Registration</title>
    <?php $favicon = !empty($stud_data['fyp_profileimg']) ? $stud_data['fyp_profileimg'] : "image/user.png"; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap');
        :root { --primary-color: #0056b3; --primary-hover: #004494; --secondary-color: #f4f4f9; --text-color: #333; --border-color: #e0e0e0; --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); --gradient-start: #eef2f7; --gradient-end: #ffffff; --sidebar-width: 260px; }
        body { font-family: 'Poppins', sans-serif; margin: 0; background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end)); color: var(--text-color); min-height: 100vh; display: flex; flex-direction: column; }
        
        .layout-container { display: flex; flex: 1; max-width: 1400px; margin: 0 auto; width: 100%; padding: 20px; box-sizing: border-box; gap: 20px; }
        .sidebar { width: var(--sidebar-width); background: #fff; border-radius: 12px; box-shadow: var(--card-shadow); padding: 20px 0; flex-shrink: 0; }
        .main-content { flex: 1; display: flex; flex-direction: column; gap: 20px; }
        
        /* Topbar & Sidebar Styles (Reuse) */
        .topbar { display: flex; justify-content: space-between; align-items: center; padding: 15px 40px; background-color: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.05); z-index: 100; position: sticky; top: 0; }
        .logo { font-size: 22px; font-weight: 600; color: var(--primary-color); display: flex; align-items: center; gap: 10px; }
        .topbar-right { display: flex; align-items: center; gap: 20px; }
        .user-name-display { font-weight: 600; font-size: 14px; }
        .user-avatar-circle { width: 40px; height: 40px; border-radius: 50%; overflow: hidden; border: 2px solid #e3effd; }
        .user-avatar-circle img { width: 100%; height: 100%; object-fit: cover; }
        .logout-btn { color: #d93025; text-decoration: none; font-size: 14px; font-weight: 500; }
        
        /* Menu Styles */
        .menu-list { list-style: none; padding: 0; margin: 0; }
        .menu-item { margin-bottom: 5px; }
        .menu-link { display: flex; align-items: center; padding: 12px 25px; text-decoration: none; color: #555; font-weight: 500; font-size: 15px; border-left: 4px solid transparent; transition: all 0.3s; }
        .menu-link:hover { background-color: var(--secondary-color); color: var(--primary-color); }
        .menu-link.active { background-color: #e3effd; color: var(--primary-color); border-left-color: var(--primary-color); }
        .menu-icon { width: 24px; margin-right: 10px; text-align: center; }
        .submenu { list-style: none; padding: 0; margin: 0; background-color: #fafafa; display: none; }
        .menu-item.has-active-child .submenu, .menu-item:hover .submenu { display: block; }
        .submenu .menu-link { padding-left: 58px; font-size: 14px; padding-top: 10px; padding-bottom: 10px; }

        /* Page Specific */
        .welcome-card { background: #fff; padding: 30px; border-radius: 12px; box-shadow: var(--card-shadow); border-left: 5px solid var(--primary-color); display: flex; justify-content: space-between; align-items: center; }
        .page-title { font-size: 24px; margin: 0 0 5px 0; color: var(--text-color); }
        .my-status-box { text-align: right; }
        .status-pill { background: #e3effd; color: var(--primary-color); padding: 5px 15px; border-radius: 20px; font-weight: 600; font-size: 13px; display: inline-block; margin-top: 5px; }

        /* Filter Bar Styles */
        .filter-card { background: #fff; padding: 20px; border-radius: 12px; box-shadow: var(--card-shadow); margin-bottom: 5px; display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end; }
        .filter-group { flex: 1; min-width: 150px; }
        .filter-group label { display: block; font-size: 12px; font-weight: 600; color: #666; margin-bottom: 5px; }
        .filter-input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-family: inherit; font-size: 14px; box-sizing: border-box; }
        .btn-filter { background-color: var(--primary-color); color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; height: 40px; display: inline-flex; align-items: center; gap: 5px; }
        .btn-filter:hover { background-color: var(--primary-hover); }
        .btn-reset { background-color: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; height: 40px; box-sizing: border-box; }
        .btn-reset:hover { background-color: #5a6268; }

        .project-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 25px; margin-top: 20px; }
        .project-card { background: #fff; border-radius: 12px; padding: 25px; box-shadow: var(--card-shadow); display: flex; flex-direction: column; transition: transform 0.3s; border-top: 4px solid #ddd; position: relative; }
        .project-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        
        /* Dynamic Border Colors based on Type */
        .card-Group { border-top-color: #7b1fa2; }
        .card-Individual { border-top-color: #0288d1; }

        .p-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; }
        .p-title { font-size: 18px; font-weight: 600; color: #333; line-height: 1.4; flex: 1; margin-right: 10px; }
        .p-status { font-size: 11px; padding: 3px 8px; border-radius: 4px; font-weight: 600; text-transform: uppercase; white-space: nowrap; }
        .st-Open { background: #e8f5e9; color: #2e7d32; }
        .st-Taken { background: #ffebee; color: #c62828; }

        .p-meta { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 15px; }
        .badge { font-size: 11px; padding: 4px 10px; border-radius: 12px; font-weight: 500; }
        .badge-cat { background: #fff3e0; color: #ef6c00; }
        .badge-type { background: #f3e5f5; color: #7b1fa2; }
        
        .p-desc { font-size: 13px; color: #666; margin-bottom: 20px; line-height: 1.6; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; height: 63px; }
        
        .sv-info { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; padding-top: 15px; border-top: 1px solid #f0f0f0; }
        .sv-avatar { width: 30px; height: 30px; background: #eee; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #888; font-size: 12px; }
        .sv-name { font-size: 13px; font-weight: 500; color: #444; }

        .btn-view { background: #fff; border: 1px solid var(--primary-color); color: var(--primary-color); padding: 10px; border-radius: 6px; width: 100%; cursor: pointer; font-weight: 500; transition: all 0.2s; }
        .btn-view:hover { background: var(--primary-color); color: #fff; }

        /* Disable style */
        .project-card.disabled { opacity: 0.6; }
        .project-card.disabled .btn-view { border-color: #ccc; color: #999; cursor: not-allowed; background: #f9f9f9; }
        .project-card.disabled:hover { transform: none; }

        /* Modal Styles */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-overlay.show { display: flex; }
        .modal-box { background: #fff; width: 90%; max-width: 700px; border-radius: 12px; padding: 30px; position: relative; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 50px rgba(0,0,0,0.2); }
        .close-modal { position: absolute; top: 20px; right: 20px; font-size: 24px; cursor: pointer; color: #999; }
        
        .m-section { margin-bottom: 20px; }
        .m-label { font-size: 12px; color: #888; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; margin-bottom: 5px; }
        .m-value { font-size: 15px; color: #333; line-height: 1.6; }
        .m-title { font-size: 22px; font-weight: 600; color: var(--primary-color); margin-bottom: 5px; }

        .sv-card-mini { background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px solid #eee; display: flex; gap: 15px; align-items: center; }
        
        .btn-confirm { background: var(--primary-color); color: white; border: none; padding: 12px 30px; border-radius: 6px; font-size: 16px; font-weight: 600; cursor: pointer; width: 100%; margin-top: 10px; }
        .btn-confirm:hover { background: var(--primary-hover); }
        
        /* Alert Box */
        .alert-banner { background-color: #fff3cd; color: #856404; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #ffeeba; display: flex; align-items: center; gap: 10px; }

        @media (max-width: 900px) { .layout-container { flex-direction: column; } .sidebar { width: 100%; } }
    </style>
</head>
<body>

    <header class="topbar">
        <div class="logo">FYP System</div>
        <div class="topbar-right">
            <span class="user-name-display"><?php echo htmlspecialchars($user_name); ?></span>
            <div class="user-avatar-circle"><img src="<?php echo $favicon; ?>" alt="User"></div>
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
                <div>
                    <h1 class="page-title">Available Projects</h1>
                    <p style="color: #666; margin: 0;">Browse and apply for Final Year Projects.</p>
                </div>
                <div class="my-status-box">
                    <div style="font-size:12px; color:#888;">Your Current Status</div>
                    <span class="status-pill"><?php echo $my_group_status; ?></span>
                    <?php if ($is_leader) echo '<span class="status-pill" style="background:#fff3cd; color:#856404; margin-left:5px;">Leader</span>'; ?>
                </div>
            </div>

            <?php if ($has_active_application): ?>
                <div class="alert-banner">
                    <i class="fa fa-info-circle" style="font-size: 18px;"></i>
                    <div>
                        <strong>Action Required:</strong> You (or your team) already have a project application status: 
                        <span style="font-weight:bold; text-transform:uppercase;"><?php echo $active_app_status; ?></span>.
                        <br>You cannot apply for another project until the current one is rejected or withdrawn.
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- FILTER BAR -->
            <form method="GET" action="" class="filter-card">
                <input type="hidden" name="auth_user_id" value="<?php echo htmlspecialchars($auth_user_id); ?>">
                
                <div class="filter-group">
                    <label>Status</label>
                    <select name="filter_status" class="filter-input">
                        <option value="">All</option>
                        <option value="Open" <?php if($filter_status == 'Open') echo 'selected'; ?>>Open</option>
                        <option value="Taken" <?php if($filter_status == 'Taken') echo 'selected'; ?>>Taken</option>
                    </select>
                </div>
                
                <div class="filter-group" style="flex: 2;">
                    <label>Search Title</label>
                    <input type="text" name="search_title" class="filter-input" placeholder="e.g. AI Chatbot" value="<?php echo htmlspecialchars($search_title); ?>">
                </div>
                
                <div class="filter-group" style="flex: 2;">
                    <label>Search Supervisor</label>
                    <input type="text" name="search_sv" class="filter-input" placeholder="e.g. Dr. Smith" value="<?php echo htmlspecialchars($search_sv); ?>">
                </div>
                
                <button type="submit" class="btn-filter"><i class="fa fa-search"></i> Filter</button>
                <a href="std_projectreg.php?auth_user_id=<?php echo urlencode($auth_user_id); ?>" class="btn-reset">Reset</a>
            </form>

            <div class="project-grid">
                <?php if (count($available_projects) > 0): ?>
                    <?php foreach ($available_projects as $proj): ?>
                        <?php 
                            // 逻辑检查
                            $isTaken = ($proj['fyp_projectstatus'] == 'Taken');
                            $isMismatch = ($my_group_status != $proj['fyp_projecttype']);
                            
                            // 1. Group Member Restriction
                            $isMemberRestrict = ($my_group_status == 'Group' && !$is_leader && $proj['fyp_projecttype'] == 'Group');

                            // 2. Global "Already Applied" Restriction
                            $isAlreadyApplied = $has_active_application;

                            // 3. Specific Project Rejection Restriction
                            $isRejected = in_array($proj['fyp_projectid'], $rejected_project_ids);

                            // Total Disabled Flag
                            $isDisabled = $isTaken || $isMismatch || $isMemberRestrict || $isAlreadyApplied || $isRejected;
                            
                            // Button Text Logic
                            $btnText = "View & Apply";
                            if ($isTaken) $btnText = "Taken";
                            else if ($isRejected) $btnText = "Application Rejected"; // 被拒绝显示
                            else if ($isAlreadyApplied) $btnText = "Already Applied"; 
                            else if ($isMismatch) $btnText = "Type Mismatch";
                            else if ($isMemberRestrict) $btnText = "Leader Only"; 
                        ?>
                        
                        <div class="project-card card-<?php echo $proj['fyp_projecttype']; ?> <?php echo $isDisabled ? 'disabled' : ''; ?>">
                            <div class="p-header">
                                <div class="p-title"><?php echo htmlspecialchars($proj['fyp_projecttitle']); ?></div>
                                <span class="p-status st-<?php echo $proj['fyp_projectstatus']; ?>"><?php echo $proj['fyp_projectstatus']; ?></span>
                            </div>
                            
                            <div class="p-meta">
                                <span class="badge badge-cat"><?php echo htmlspecialchars($proj['fyp_projectcat']); ?></span>
                                <span class="badge badge-type"><?php echo htmlspecialchars($proj['fyp_projecttype']); ?></span>
                            </div>

                            <div class="p-desc"><?php echo htmlspecialchars($proj['fyp_description']); ?></div>

                            <div class="sv-info">
                                <div class="sv-avatar"><i class="fa fa-user-tie"></i></div>
                                <div class="sv-name">
                                    SV: <?php echo htmlspecialchars(!empty($proj['sv_name']) ? $proj['sv_name'] : $proj['fyp_contactpersonname']); ?>
                                </div>
                            </div>

                            <!-- 如果 Disabled，onclick 设为空 或 移除 -->
                            <button type="button" class="btn-view" 
                                <?php if (!$isDisabled): ?>
                                    onclick="openModal(<?php echo htmlspecialchars(json_encode($proj)); ?>)" 
                                <?php endif; ?>
                                <?php echo $isDisabled ? 'disabled' : ''; ?>>
                                <?php echo $btnText; ?>
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align:center; padding:40px; color:#999; grid-column: 1/-1;">No projects match your criteria.</div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Modal -->
    <div id="detailModal" class="modal-overlay">
        <div class="modal-box">
            <span class="close-modal" onclick="closeModal()">&times;</span>
            
            <form method="POST">
                <input type="hidden" name="project_id" id="modalProjId">
                
                <div class="m-title" id="mTitle"></div>
                <div style="margin-bottom:20px;">
                    <span class="badge badge-cat" id="mCat"></span>
                    <span class="badge badge-type" id="mType"></span>
                    <span class="p-status" id="mStatus"></span>
                </div>

                <div class="m-section">
                    <div class="m-label">Description</div>
                    <div class="m-value" id="mDesc"></div>
                </div>

                <div class="m-section">
                    <div class="m-label">Technical Requirements</div>
                    <div class="m-value" id="mReq"></div>
                </div>

                <div class="m-section">
                    <div class="m-label">Course Requirement</div>
                    <div class="m-value" id="mCourse"></div>
                </div>

                <div class="m-section">
                    <div class="m-label">Supervisor Info</div>
                    <div class="sv-card-mini">
                        <div style="font-size:32px; color:#ccc;"><i class="fa fa-user-circle"></i></div>
                        <div>
                            <div style="font-weight:600; color:#333;" id="mSvName"></div>
                            <div style="font-size:13px; color:#666;">
                                <i class="fa fa-envelope"></i> <span id="mSvEmail"></span> &nbsp;|&nbsp; 
                                <i class="fa fa-phone"></i> <span id="mSvPhone"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" name="register_project_confirm" class="btn-confirm">
                    <i class="fa fa-paper-plane"></i> Apply for this Project
                </button>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('detailModal');

        function openModal(proj) {
            document.getElementById('modalProjId').value = proj.fyp_projectid;
            document.getElementById('mTitle').innerText = proj.fyp_projecttitle;
            document.getElementById('mCat').innerText = proj.fyp_projectcat;
            document.getElementById('mType').innerText = proj.fyp_projecttype;
            
            // Status Styling
            const stEl = document.getElementById('mStatus');
            stEl.innerText = proj.fyp_projectstatus;
            stEl.className = 'p-status st-' + proj.fyp_projectstatus;

            document.getElementById('mDesc').innerText = proj.fyp_description;
            document.getElementById('mReq').innerText = proj.fyp_requirement || 'None specified';
            document.getElementById('mCourse').innerText = proj.fyp_coursereq || 'Open to all';

            // SV Info
            // 优先使用 join 出来的 sv_name, 否则用 contactpersonname
            const svName = proj.sv_name || proj.fyp_contactpersonname || 'Unknown';
            const svEmail = proj.sv_email || proj.fyp_contactperson || 'N/A'; // contactperson field usually stores email in your prev code
            const svPhone = proj.sv_phone || 'N/A';

            document.getElementById('mSvName').innerText = svName;
            document.getElementById('mSvEmail').innerText = svEmail;
            document.getElementById('mSvPhone').innerText = svPhone;

            modal.classList.add('show');
        }

        function closeModal() {
            modal.classList.remove('show');
        }

        window.onclick = function(e) {
            if (e.target == modal) closeModal();
        }
    </script>
</body>
</html>