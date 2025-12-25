<?php
// ====================================================
// Student_mainpage.php - Main Dashboard (Full Height Announcement Feed)
// ====================================================
include("connect.php");

// 1. 基础验证
$auth_user_id = $_GET['auth_user_id'] ?? null;
$current_page = $_GET['page'] ?? 'dashboard';

if ($current_page == 'book_session') $current_page = 'appointments';
if (!$auth_user_id) { header("location: login.php"); exit; }

// ====================================================
// 2. 逻辑处理 (POST 请求 - 预约)
// ====================================================
if ($current_page == 'appointments' && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['book_appointment'])) {
    $post_schedule_id = $_POST['schedule_id'];
    $post_supervisor_id = $_POST['supervisor_id'];
    $post_pairing_id = $_POST['pairing_id'];
    $post_stud_id = $_POST['student_id_str'];
    $status = "Pending";
    $current_date = date('Y-m-d H:i:s');
    
    $sql_book = "INSERT INTO appointment (fyp_studid, fyp_pairingid, fyp_scheduleid, fyp_supervisorid, fyp_status, fyp_datecreated) VALUES (?, ?, ?, ?, ?, ?)";
    if ($stmt = $conn->prepare($sql_book)) {
        $stmt->bind_param("siisss", $post_stud_id, $post_pairing_id, $post_schedule_id, $post_supervisor_id, $status, $current_date);
        if ($stmt->execute()) echo "<script>alert('Appointment booked successfully!'); window.location.href='?page=appointments&auth_user_id=" . urlencode($auth_user_id) . "';</script>";
        $stmt->close();
    }
}

// ====================================================
// 3. 数据查询 (GET)
// ====================================================

// 3.1 获取学生基本信息
$stud_data = [];
$user_name = 'Student';
if (isset($conn)) {
    $sql_user = "SELECT fyp_username FROM `USER` WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_user)) { 
        $stmt->bind_param("i", $auth_user_id); $stmt->execute(); 
        $res = $stmt->get_result(); if($row=$res->fetch_assoc()) $user_name=$row['fyp_username']; 
        $stmt->close(); 
    }
    $sql_stud = "SELECT * FROM STUDENT WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_stud)) { 
        $stmt->bind_param("i", $auth_user_id); $stmt->execute(); 
        $res=$stmt->get_result(); 
        if($res->num_rows > 0) { 
            $stud_data = $res->fetch_assoc(); 
            if(!empty($stud_data['fyp_studname'])) $user_name=$stud_data['fyp_studname']; 
        } else { 
            $stud_data=['fyp_studid'=>'', 'fyp_studname'=>$user_name, 'fyp_studfullid'=>'', 'fyp_projectid'=>null, 'fyp_profileimg'=>'']; 
        } 
        $stmt->close(); 
    }
}

// ----------------------------------------------------
// 3.2 获取 Dashboard 实时状态 & 关键过滤参数
// ----------------------------------------------------
$dashboard_data = [
    'project_status' => 'Not Registered', 
    'next_deadline' => 'TBA', 
    'supervisor_name' => 'Please apply for a project'
];

// 初始化过滤变量
$my_sv_id = null;         // 我的导师ID
$my_sv_name = "";         // 我的导师名字
$my_project_title = "";   // 我的项目标题

if (!empty($stud_data['fyp_studid'])) {
    $my_id = $stud_data['fyp_studid'];

    // 1. 检查 fyp_registration (优先：已注册项目)
    $sql_reg = "SELECT r.*, s.fyp_name, s.fyp_supervisorid, p.fyp_projecttitle
                FROM fyp_registration r 
                JOIN supervisor s ON r.fyp_supervisorid = s.fyp_supervisorid 
                JOIN project p ON r.fyp_projectid = p.fyp_projectid
                WHERE r.fyp_studid = ? LIMIT 1";
    
    $is_registered = false;
    if ($stmt = $conn->prepare($sql_reg)) {
        $stmt->bind_param("s", $my_id);
        $stmt->execute();
        $res_reg = $stmt->get_result();
        if ($row_reg = $res_reg->fetch_assoc()) {
            $is_registered = true;
            $dashboard_data['project_status'] = 'Active (Registered)';
            $dashboard_data['supervisor_name'] = $row_reg['fyp_name'];
            $dashboard_data['next_deadline'] = 'Check Schedule'; 
            
            $my_sv_id = $row_reg['fyp_supervisorid'];
            $my_project_title = trim($row_reg['fyp_projecttitle']);
        }
        $stmt->close();
    }

    // 2. 如果没注册，检查申请状态 (Pending/Reject)
    if (!$is_registered) {
        $applicant_id = $my_id;
        $sql_mem = "SELECT g.leader_id FROM group_request gr 
                    JOIN student_group g ON gr.group_id = g.group_id 
                    WHERE gr.invitee_id = ? AND gr.request_status = 'Accepted'";
        if ($stmt_m = $conn->prepare($sql_mem)) {
            $stmt_m->bind_param("s", $my_id);
            $stmt_m->execute();
            $res_m = $stmt_m->get_result();
            if ($row_m = $res_m->fetch_assoc()) {
                $applicant_id = $row_m['leader_id'];
            }
            $stmt_m->close();
        }

        $sql_req = "SELECT pr.fyp_requeststatus, s.fyp_name, s.fyp_supervisorid, p.fyp_projecttitle, p.fyp_contactpersonname
                    FROM project_request pr 
                    LEFT JOIN supervisor s ON pr.fyp_supervisorid = s.fyp_supervisorid 
                    LEFT JOIN project p ON pr.fyp_projectid = p.fyp_projectid
                    WHERE pr.fyp_studid = ? 
                    ORDER BY pr.fyp_datecreated DESC LIMIT 1";
        
        if ($stmt_r = $conn->prepare($sql_req)) {
            $stmt_r->bind_param("s", $applicant_id);
            $stmt_r->execute();
            $res_r = $stmt_r->get_result();
            if ($row_r = $res_r->fetch_assoc()) {
                $status = $row_r['fyp_requeststatus'];
                $current_sv_name = !empty($row_r['fyp_name']) ? $row_r['fyp_name'] : $row_r['fyp_contactpersonname'];
                
                if ($status == 'Pending') {
                    $dashboard_data['project_status'] = 'Pending Approval';
                    $dashboard_data['supervisor_name'] = $current_sv_name . " (Pending)";
                    
                    $my_sv_id = $row_r['fyp_supervisorid'];
                    $my_sv_name = $current_sv_name;
                    $my_project_title = trim($row_r['fyp_projecttitle']);

                } elseif ($status == 'Reject') {
                    $dashboard_data['project_status'] = 'Application Rejected';
                    $dashboard_data['supervisor_name'] = 'Please apply again';
                    $my_sv_id = null;
                    $my_project_title = "";
                }
            }
            $stmt_r->close();
        }
    }
}

// ----------------------------------------------------
// 3.3 获取并过滤公告 (Dashboard Only)
// ----------------------------------------------------
$announcements = [];
if ($current_page == 'dashboard') {
    $sql_ann = "SELECT a.*, s.fyp_name as sender_name, s.fyp_supervisorid as sv_real_id, s.fyp_profileimg
                FROM announcement a 
                LEFT JOIN supervisor s ON a.fyp_supervisorid = s.fyp_supervisorid 
                ORDER BY a.fyp_datecreated DESC";
    $res = $conn->query($sql_ann);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            
            $display_name = "Supervisor"; 
            if (!empty($row['sender_name'])) {
                $display_name = $row['sender_name'];
            } elseif (!empty($row['fyp_supervisorid']) && !is_numeric($row['fyp_supervisorid'])) {
                $display_name = $row['fyp_supervisorid'];
            }
            $row['final_display_name'] = $display_name;
            
            $should_show = false;
            $receiver = trim($row['fyp_receiver']); 
            
            $sender_sv_id = $row['sv_real_id'] ? $row['sv_real_id'] : $row['fyp_supervisorid'];
            $sender_sv_name = $display_name;

            if (strcasecmp($receiver, 'All Students') == 0) {
                $should_show = true;
            } 
            elseif (strcasecmp($receiver, 'My Supervisees') == 0) {
                if ($my_sv_id && $sender_sv_id == $my_sv_id && $my_sv_id != 0) {
                    $should_show = true;
                }
                elseif ($my_sv_name && strcasecmp($sender_sv_name, $my_sv_name) == 0) {
                    $should_show = true;
                }
            } 
            elseif (strpos($receiver, 'Project: ') === 0) {
                $target_project = trim(substr($receiver, 9)); 
                if ($my_project_title && strcasecmp($target_project, $my_project_title) == 0) {
                    $should_show = true;
                }
            }

            if ($should_show) {
                $announcements[] = $row;
            }
        }
    }
}

// 3.4 获取预约信息
$supervisor_data = null; $available_schedules = []; $my_appointments = []; $pairing_data = null;
if ($current_page == 'appointments' && !empty($stud_data['fyp_projectid'])) {
    $proj_id = $stud_data['fyp_projectid'];
    $res_pair = $conn->query("SELECT * FROM pairing WHERE fyp_projectid = $proj_id LIMIT 1");
    if ($res_pair->num_rows > 0) {
        $pairing_data = $res_pair->fetch_assoc();
        $sv_id = $pairing_data['fyp_supervisorid'];
        
        $res_sv = $conn->query("SELECT * FROM supervisor WHERE fyp_supervisorid = '$sv_id' LIMIT 1");
        if ($res_sv->num_rows > 0) $supervisor_data = $res_sv->fetch_assoc();
        
        $sql_sch = "SELECT s.* FROM schedule s 
                    WHERE s.fyp_supervisorid = '$sv_id' 
                    AND s.fyp_date >= CURDATE() 
                    AND s.fyp_scheduleid NOT IN (SELECT fyp_scheduleid FROM appointment WHERE fyp_status IN ('Pending', 'Approved')) 
                    ORDER BY s.fyp_date ASC";
        $res_sch = $conn->query($sql_sch);
        while ($row = $res_sch->fetch_assoc()) $available_schedules[] = $row;
    }
    $my_sid = $stud_data['fyp_studid'];
    $res_app = $conn->query("SELECT a.*, s.fyp_date, s.fyp_fromtime, s.fyp_totime, s.fyp_day FROM appointment a JOIN schedule s ON a.fyp_scheduleid = s.fyp_scheduleid WHERE a.fyp_studid = '$my_sid' ORDER BY s.fyp_date DESC");
    while ($row = $res_app->fetch_assoc()) $my_appointments[] = $row;
}

// 4. 定义菜单
$menu_items = [
    'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home', 'link' => 'Student_mainpage.php?page=dashboard'],
    'profile' => ['name' => 'My Profile', 'icon' => 'fa-user', 'link' => 'std_profile.php'], 
    'project_mgmt' => [
        'name' => 'Final Year Project',
        'icon' => 'fa-project-diagram',
        'sub_items' => [
            'group_setup' => ['name' => 'Project Registration', 'icon' => 'fa-users', 'link' => 'std_projectreg.php'], 
            'team_invitations' => ['name' => 'Request & Team Status', 'icon' => 'fa-tasks', 'link' => 'std_request_status.php'], 
            'assignment' => ['name' => 'Assignment', 'icon' => 'fa-file-alt', 'link' => 'student_assignment.php'],
            'doc_submission' => ['name' => 'Document Upload', 'icon' => 'fa-cloud-upload-alt', 'link' => '?page=doc_submission'],
        ]
    ],
   'appointments' => ['name' => 'Appointment', 'icon' => 'fa-calendar-check', 'sub_items' => ['book_session' => ['name' => 'Book Consultation', 'icon' => 'fa-comments', 'link' => 'student_appointment_meeting.php'], 'presentation' => ['name' => 'Final Presentation', 'icon' => 'fa-chalkboard-teacher', 'link' => '?page=presentation']]],
    'grades' => ['name' => 'My Grades', 'icon' => 'fa-star', 'link' => '?page=grades'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <?php $favicon = !empty($stud_data['fyp_profileimg']) ? $stud_data['fyp_profileimg'] : "image/user.png"; ?>
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
        .menu-link { display: flex; align-items: center; padding: 12px 25px; text-decoration: none; color: #555; font-weight: 500; font-size: 15px; transition: all 0.3s; border-left: 4px solid transparent; position: relative; }
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
        
        /* Announcement Styles - FULL HEIGHT FIX */
        .announcement-feed { 
            /* Removed fixed height and overflow to allow full page scrolling */
        } 
        
        .ann-card { background: #fff; border-radius: 8px; padding: 20px; margin-bottom: 15px; box-shadow: 0 2px 6px rgba(0,0,0,0.06); border-left: 5px solid #6264A7; transition: transform 0.2s; position: relative; }
        .ann-card:hover { transform: translateX(3px); }
        .ann-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #eee; }
        .ann-sender-info { display: flex; align-items: center; gap: 10px; }
        .ann-avatar { width: 40px; height: 40px; background-color: #6264A7; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 18px; overflow: hidden; }
        .ann-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .ann-sender-name { font-weight: 600; color: #333; font-size: 15px; }
        .ann-role { font-size: 12px; color: #888; background: #f0f0f0; padding: 2px 8px; border-radius: 10px; margin-left: 8px;}
        .ann-date { font-size: 12px; color: #999; display: flex; align-items: center; gap: 5px; }
        .ann-subject { font-size: 18px; font-weight: 600; color: #2c2c2c; margin-bottom: 8px; }
        .ann-body { color: #555; line-height: 1.6; font-size: 14px; white-space: pre-wrap; }

        /* Appointment Styles */
        .supervisor-profile-card { background: #fff; padding: 25px; border-radius: 12px; box-shadow: var(--card-shadow); display: flex; align-items: center; gap: 20px; margin-bottom: 30px; border-left: 5px solid #28a745; }
        .sv-avatar { width: 80px; height: 80px; border-radius: 50%; background: #e9ecef; display: flex; align-items: center; justify-content: center; font-size: 32px; color: #6c757d; }
        .sv-info h2 { margin: 0 0 5px 0; font-size: 20px; color: #333; }
        .sv-info p { margin: 3px 0; color: #666; font-size: 14px; }
        .section-header { font-size: 18px; font-weight: 600; margin-bottom: 15px; color: #333; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .slots-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; }
        .slot-card { background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; padding: 15px; text-align: center; transition: all 0.3s; cursor: pointer; position: relative; }
        .slot-card:hover { border-color: var(--primary-color); transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.08); }
        .slot-day { font-weight: 600; color: var(--primary-color); margin-bottom: 5px; }
        .slot-date { font-size: 13px; color: #777; margin-bottom: 10px; }
        .slot-time { font-size: 15px; font-weight: 500; color: #333; background: #f8f9fa; padding: 5px; border-radius: 4px; }
        .btn-book { margin-top: 10px; width: 100%; padding: 8px; background: var(--primary-color); color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; }
        .appointment-list { background: #fff; border-radius: 12px; padding: 20px; box-shadow: var(--card-shadow); }
        .app-item { display: flex; justify-content: space-between; align-items: center; padding: 15px; border-bottom: 1px solid #f0f0f0; }
        .app-item:last-child { border-bottom: none; }
        .app-date { display: flex; flex-direction: column; align-items: center; min-width: 60px; background: #f1f3f5; padding: 5px 10px; border-radius: 6px; }
        .app-month { font-size: 12px; font-weight: 600; color: #666; text-transform: uppercase; }
        .app-day { font-size: 20px; font-weight: 700; color: #333; }
        .app-details { flex: 1; margin-left: 20px; }
        .app-time { font-weight: 600; font-size: 15px; color: #333; }
        .app-status { font-size: 12px; padding: 3px 10px; border-radius: 20px; font-weight: 600; display: inline-block; margin-top: 5px; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; opacity: 0; transition: opacity 0.3s ease; }
        .modal-overlay.show { display: flex; opacity: 1; }
        .modal-box { background: #fff; padding: 35px; border-radius: 12px; width: 90%; max-width: 450px; text-align: center; box-shadow: 0 15px 35px rgba(0,0,0,0.2); transform: scale(0.9); transition: transform 0.3s ease; }
        .modal-overlay.show .modal-box { transform: scale(1); }
        .modal-icon { font-size: 40px; color: var(--primary-color); margin-bottom: 15px; }
        .modal-title { font-size: 20px; font-weight: 600; margin-bottom: 10px; color: #333; }
        .modal-text { color: #666; margin-bottom: 30px; font-size: 15px; line-height: 1.6; }
        .modal-actions { display: flex; gap: 15px; justify-content: center; }
        .btn-confirm { background: var(--primary-color); color: white; padding: 12px 25px; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; font-size: 15px; }
        .btn-cancel { background: #f1f1f1; color: #444; padding: 12px 25px; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; font-size: 15px; transition: background 0.2s; }
        .btn-cancel:hover { background: #e0e0e0; }
        .empty-state { text-align: center; padding: 40px; color: #999; }
        @media (max-width: 900px) { .layout-container { flex-direction: column; } .sidebar { width: 100%; min-height: auto; } .supervisor-profile-card { flex-direction: column; text-align: center; } }
    </style>
</head>
<body>

    <header class="topbar">
        <div class="logo">FYP System</div>
        <div class="topbar-right">
            <div class="user-profile-summary">
                <span class="user-name-display"><?php echo htmlspecialchars($user_name); ?></span>
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
                                    $subLinkUrl = isset($sub_item['link']) ? $sub_item['link'] : "#";
                                    if (strpos($subLinkUrl, '.php') !== false) {
                                        $separator = (strpos($subLinkUrl, '?') !== false) ? '&' : '?';
                                        $subLinkUrl .= $separator . "auth_user_id=" . urlencode($auth_user_id);
                                    } elseif (strpos($subLinkUrl, '?') === 0) {
                                        $subLinkUrl .= "&auth_user_id=" . urlencode($auth_user_id);
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
                <?php if ($current_page == 'appointments'): ?>
                    <h1 class="page-title">Make Appointment</h1>
                    <p style="color: #666; margin: 0;">Book a consultation slot with your supervisor.</p>
                <?php elseif ($current_page == 'presentation'): ?>
                    <h1 class="page-title">Final Presentation</h1>
                    <p style="color: #666; margin: 0;">Book your final presentation slot.</p>
                <?php else: ?>
                    <h1 class="page-title">Hello, <?php echo htmlspecialchars($user_name); ?>! 👋</h1>
                    <p style="color: #666; margin: 0;">Welcome to your Student Dashboard.</p>
                <?php endif; ?>
            </div>

            <?php if ($current_page == 'dashboard'): ?>
                <div class="info-cards-grid">
                    <div class="info-card">
                        <h3><i class="fa fa-tasks"></i> Project Status</h3>
                        <p><?php echo htmlspecialchars($dashboard_data['project_status']); ?></p>
                    </div>
                    <div class="info-card" style="border-left-color: #d93025;">
                        <h3 style="color: #d93025;"><i class="fa fa-clock"></i> Next Deadline</h3>
                        <p><?php echo htmlspecialchars($dashboard_data['next_deadline']); ?></p>
                    </div>
                    <div class="info-card" style="border-left-color: #28a745;">
                        <h3 style="color: #28a745;"><i class="fa fa-chalkboard-teacher"></i> Supervisor</h3>
                        <p><?php echo htmlspecialchars($dashboard_data['supervisor_name']); ?></p>
                    </div>
                </div>

                <h3 class="section-header" style="margin-top: 40px; margin-bottom: 20px;">Latest Announcements</h3>
                <div class="announcement-feed">
                    <?php if (count($announcements) > 0): ?>
                        <?php foreach ($announcements as $ann): ?>
                            <div class="ann-card">
                                <div class="ann-header">
                                    <div class="ann-sender-info">
                                        <div class="ann-avatar">
                                            <?php 
                                            // 检查是否有头像图片，如果有则显示图片，否则显示名字首字母
                                            if (!empty($ann['fyp_profileimg'])) {
                                                echo "<img src='" . $ann['fyp_profileimg'] . "' alt='SV'>";
                                            } else {
                                                echo strtoupper(substr($ann['final_display_name'] ?? 'S', 0, 1)); 
                                            }
                                            ?>
                                        </div>
                                        <div>
                                            <div class="ann-sender-name"><?php echo htmlspecialchars($ann['final_display_name']); ?> <span class="ann-role">Supervisor</span></div>
                                            <div style="font-size: 11px; color: #888;">To: <?php echo htmlspecialchars($ann['fyp_receiver']); ?></div>
                                        </div>
                                    </div>
                                    <div class="ann-date"><i class="fa fa-clock"></i> <?php $date = date_create($ann['fyp_datecreated']); echo date_format($date, "M d, Y h:i A"); ?></div>
                                </div>
                                <div class="ann-subject"><?php echo htmlspecialchars($ann['fyp_subject']); ?></div>
                                <div class="ann-body"><?php echo nl2br(htmlspecialchars($ann['fyp_description'])); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fa fa-bullhorn" style="font-size: 48px; margin-bottom: 20px; opacity: 0.3;"></i>
                            <p>Currently there are no announcements.</p>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($current_page == 'appointments'): ?>
                <?php if (empty($stud_data['fyp_projectid'])): ?>
                    <div class="empty-state">
                        <i class="fa fa-exclamation-circle" style="font-size: 48px; color: #f0ad4e; margin-bottom: 20px;"></i>
                        <p>You have not registered for a project yet.</p>
                        <p style="font-size: 14px; color: #888;">Please go to <strong>Final Year Project > Project Registration</strong> to select a project first.</p>
                        <a href="std_projectreg.php?auth_user_id=<?php echo urlencode($auth_user_id); ?>" class="save-btn" style="display:inline-block; margin-top:15px; text-decoration:none;">Go to Registration</a>
                    </div>
                <?php elseif (empty($supervisor_data)): ?>
                    <div class="empty-state">
                        <i class="fa fa-user-times" style="font-size: 48px; color: #d9534f; margin-bottom: 20px;"></i>
                        <p>Your project has not been assigned a supervisor yet.</p>
                        <p style="font-size: 14px; color: #888;">Please check the Pairing table or contact the coordinator.</p>
                    </div>
                <?php else: ?>
                    <div class="supervisor-profile-card">
                        <div class="sv-avatar">
                            <?php 
                                echo !empty($supervisor_data['fyp_profileimg']) ? "<img src='{$supervisor_data['fyp_profileimg']}' style='width:100%;height:100%;border-radius:50%;object-fit:cover;'>" : strtoupper(substr($supervisor_data['fyp_name'] ?? 'U', 0, 1));
                            ?>
                        </div>
                        <div class="sv-info">
                            <h2><?php echo htmlspecialchars($supervisor_data['fyp_name']); ?></h2>
                            <p><i class="fa fa-envelope" style="width:20px;"></i> <?php echo htmlspecialchars($supervisor_data['fyp_email']); ?></p>
                            <p><i class="fa fa-door-open" style="width:20px;"></i> Room: <?php echo htmlspecialchars($supervisor_data['fyp_roomno']); ?></p>
                            <p><i class="fa fa-phone" style="width:20px;"></i> <?php echo htmlspecialchars($supervisor_data['fyp_contactno']); ?></p>
                        </div>
                    </div>

                    <div class="section-header">Available Slots</div>
                    
                    <?php if (count($available_schedules) > 0): ?>
                        <div class="slots-grid">
                            <?php foreach ($available_schedules as $sch): ?>
                                <div class="slot-card" onclick="openBookModal('<?php echo $sch['fyp_scheduleid']; ?>', '<?php echo $sch['fyp_day'] . ', ' . $sch['fyp_date'] . ' (' . $sch['fyp_fromtime'] . ')'; ?>', '<?php echo $supervisor_data['fyp_supervisorid']; ?>', '<?php echo $pairing_data['fyp_pairingid']; ?>', '<?php echo $stud_data['fyp_studid']; ?>')">
                                    <div class="slot-day"><?php echo htmlspecialchars($sch['fyp_day']); ?></div>
                                    <div class="slot-date"><?php echo htmlspecialchars($sch['fyp_date']); ?></div>
                                    <div class="slot-time">
                                        <?php echo date('h:i A', strtotime($sch['fyp_fromtime'])) . ' - ' . date('h:i A', strtotime($sch['fyp_totime'])); ?>
                                    </div>
                                    <button class="btn-book">Book Slot</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="color: #777; font-style: italic; margin-bottom: 30px;">No available slots found for this supervisor at the moment.</p>
                    <?php endif; ?>

                    <div class="section-header" style="margin-top: 40px;">My Appointments</div>
                    
                    <?php if (count($my_appointments) > 0): ?>
                        <div class="appointment-list">
                            <?php foreach ($my_appointments as $myapp): ?>
                                <?php 
                                    $statusClass = 'status-pending';
                                    if(strtolower($myapp['fyp_status']) == 'approved') $statusClass = 'status-approved';
                                    if(strtolower($myapp['fyp_status']) == 'rejected') $statusClass = 'status-rejected';
                                    $dateObj = date_create($myapp['fyp_date']);
                                ?>
                                <div class="app-item">
                                    <div class="app-date">
                                        <span class="app-month"><?php echo date_format($dateObj, "M"); ?></span>
                                        <span class="app-day"><?php echo date_format($dateObj, "d"); ?></span>
                                    </div>
                                    <div class="app-details">
                                        <div class="app-time">
                                            <?php echo htmlspecialchars($myapp['fyp_day']); ?>, 
                                            <?php echo date('h:i A', strtotime($myapp['fyp_fromtime'])); ?> - <?php echo date('h:i A', strtotime($myapp['fyp_totime'])); ?>
                                        </div>
                                        <div class="app-status <?php echo $statusClass; ?>">
                                            <?php echo htmlspecialchars($myapp['fyp_status']); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="color: #777;">You have no booking history.</p>
                    <?php endif; ?>
                <?php endif; ?>

            <!-- NEW SECTION -->
            <?php elseif ($current_page == 'presentation'): ?>
                <div style="background: #fff; padding: 40px; border-radius: 12px; box-shadow: var(--card-shadow); text-align: center;">
                    <i class="fa fa-chalkboard-teacher" style="font-size: 48px; color: #6c757d; margin-bottom: 20px;"></i>
                    <h2 style="color: #333; margin-bottom: 10px;">Final Presentation Booking</h2>
                    <p style="color: #666;">The slot booking for Final Year Project presentation will open soon.</p>
                    <div style="margin-top: 20px; padding: 10px 20px; background: #e9ecef; display: inline-block; border-radius: 20px; font-size: 13px; color: #555;">
                        <i class="fa fa-info-circle"></i> Please wait for coordinator announcement.
                    </div>
                </div>
            <!-- END NEW SECTION -->

            <?php else: ?>
                <div style="background: #fff; padding: 30px; border-radius: 12px; box-shadow: var(--card-shadow); text-align: center;">
                    <i class="fa fa-wrench" style="font-size: 32px; color: #ddd; margin-bottom: 15px;"></i>
                    <p><strong><?php echo ucfirst($current_page); ?></strong> module is under construction.</p>
                </div>
            <?php endif; ?>

        </main>
    </div>

    <div id="confirmationModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-icon"><i class="fa fa-question-circle"></i></div>
            <div class="modal-title">Confirm Action</div>
            <p class="modal-text" id="modalText"></p>
            <form method="POST" class="modal-actions">
                <input type="hidden" name="schedule_id" id="modalScheduleId">
                <input type="hidden" name="supervisor_id" id="modalSupervisorId">
                <input type="hidden" name="pairing_id" id="modalPairingId">
                <input type="hidden" name="student_id_str" id="modalStudentIdStr">
                <button type="submit" name="book_appointment" id="modalSubmitBtn" class="btn-confirm">Yes, Confirm</button>
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        const confirmModal = document.getElementById('confirmationModal');
        const modalText = document.getElementById('modalText');
        function openBookModal(scheduleId, dateStr, supervisorId, pairingId, studentIdStr) {
            modalText.innerHTML = "Confirm booking for:<br><strong>" + dateStr + "</strong>?";
            document.getElementById('modalScheduleId').value = scheduleId;
            document.getElementById('modalSupervisorId').value = supervisorId;
            document.getElementById('modalPairingId').value = pairingId;
            document.getElementById('modalStudentIdStr').value = studentIdStr;
            confirmModal.classList.add('show');
        }
        function closeModal() {
            confirmModal.classList.remove('show');
        }
        window.onclick = function(e) {
            if (e.target.classList.contains('modal-overlay')) {
                e.target.classList.remove('show');
            }
        }
    </script>
</body>
</html>