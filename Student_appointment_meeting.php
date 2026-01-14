<?php
// ====================================================
// student_appointment_meeting.php - 预约导师会议 (统一使用 fyp_staffid 版)
// ====================================================
include("connect.php");

// 1. 验证用户登录
$auth_user_id = $_GET['auth_user_id'] ?? null;
if (!$auth_user_id) { header("location: login.php"); exit; }

// 2. 获取当前学生信息
$stud_data = [];
$current_stud_id = '';
$user_name = 'Student';
// 默认头像，稍后会被数据库覆盖
$user_avatar = 'image/user.png'; 

if (isset($conn)) {
    // 获取 USER 表名字
    $sql_user = "SELECT fyp_username FROM `USER` WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_user)) { 
        $stmt->bind_param("i", $auth_user_id); $stmt->execute(); 
        $res = $stmt->get_result(); if ($row = $res->fetch_assoc()) $user_name = $row['fyp_username']; 
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
            if (!empty($row['fyp_studname'])) $user_name = $row['fyp_studname'];
            if (!empty($row['fyp_profileimg'])) $user_avatar = $row['fyp_profileimg'];
        } 
        $stmt->close(); 
    }
}

// ====================================================
// 3. 核心逻辑：获取我的 Supervisor (不通过 Pairing)
// ====================================================
$my_supervisor = null;
$sv_id = 0;

if ($current_stud_id) {
    // 从注册表中查找已批准的导师，使用 fyp_staffid
    $sql_reg = "SELECT fyp_staffid FROM fyp_registration WHERE fyp_studid = ? LIMIT 1";
    if ($stmt = $conn->prepare($sql_reg)) {
        $stmt->bind_param("s", $current_stud_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $sv_id = $row['fyp_staffid'];
        }
        $stmt->close();
    }
}

// 如果找到了导师 ID，获取导师详细资料
if ($sv_id > 0) {
    // 【修正点】：查询导师信息也统一使用 fyp_staffid
    $sql_sv = "SELECT * FROM supervisor WHERE fyp_staffid = ?";
    if ($stmt = $conn->prepare($sql_sv)) {
        $stmt->bind_param("i", $sv_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $my_supervisor = $res->fetch_assoc();
        }
        $stmt->close();
    }
}

// ====================================================
// 4. 获取可用时间段 (Available Slots)
// ====================================================
$available_slots = [];
if ($sv_id > 0) {
    // 【修正点】：查询 schedule_meeting 表，使用 fyp_staffid
    // 之前报错 Unknown column 'fyp_supervisorid' 就是因为这里
    $sql_sch = "SELECT * FROM schedule_meeting 
                WHERE fyp_staffid = ? 
                AND fyp_status = 'Available' 
                AND fyp_date >= CURDATE() 
                ORDER BY fyp_date ASC, fyp_fromtime ASC";
                
    if ($stmt = $conn->prepare($sql_sch)) {
        $stmt->bind_param("i", $sv_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $available_slots[] = $row;
        }
        $stmt->close();
    }
}
// 将 Slots 数据转为 JSON 供前端日历使用
$slots_json = json_encode($available_slots);

// ====================================================
// 5. 处理预约提交 (POST)
// ====================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_booking'])) {
    $target_schedule_id = $_POST['schedule_id'];
    $reason = $_POST['reason'] ?? ''; // 获取咨询原因
    
    // 【修正点】：插入 appointment_meeting 表，使用 fyp_staffid
    $sql_book = "INSERT INTO appointment_meeting (fyp_studid, fyp_scheduleid, fyp_staffid, fyp_status, fyp_reason, fyp_datecreated) 
                 VALUES (?, ?, ?, 'Pending', ?, NOW())";
                 
    if ($stmt = $conn->prepare($sql_book)) {
        $stmt->bind_param("siis", $current_stud_id, $target_schedule_id, $sv_id, $reason);
        if ($stmt->execute()) {
            // 预约后将 schedule 锁住 (变 Booked)
            $conn->query("UPDATE schedule_meeting SET fyp_status = 'Booked' WHERE fyp_scheduleid = '$target_schedule_id'");
            
            echo "<script>alert('Appointment Requested Successfully!'); window.location.href='student_appointment_meeting.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
        } else {
            echo "<script>alert('Error booking slot: " . $stmt->error . "');</script>";
        }
        $stmt->close();
    }
}

// ====================================================
// 6. 获取我的预约历史 (My History)
// ====================================================
$my_history = [];
if ($current_stud_id) {
    // 这里的查询主要是基于 studid 和 scheduleid，不需要改动 staffid
    $sql_hist = "SELECT am.*, sm.fyp_date, sm.fyp_day, sm.fyp_fromtime, sm.fyp_totime, sm.fyp_location 
                 FROM appointment_meeting am
                 JOIN schedule_meeting sm ON am.fyp_scheduleid = sm.fyp_scheduleid
                 WHERE am.fyp_studid = ?
                 ORDER BY sm.fyp_date DESC";
    if ($stmt = $conn->prepare($sql_hist)) {
        $stmt->bind_param("s", $current_stud_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $my_history[] = $row;
        }
        $stmt->close();
    }
}

// 菜单定义
$current_page = 'book_session'; // 标记当前页面，用于高亮
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
            'doc_submission' => ['name' => 'Document Upload', 'icon' => 'fa-cloud-upload-alt', 'link' => 'Student_mainpage.php?page=doc_submission'],
        ]
    ],
    'appointments' => [
        'name' => 'Appointment', 
        'icon' => 'fa-calendar-check', 
        'sub_items' => [
            'book_session' => ['name' => 'Book Consultation', 'icon' => 'fa-comments', 'link' => 'student_appointment_meeting.php'],
            'presentation' => ['name' => 'Final Presentation', 'icon' => 'fa-chalkboard-teacher', 'link' => 'Student_mainpage.php?page=presentation']
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
    <title>Book Consultation</title>
    <link rel="icon" type="image/png" href="<?php echo $user_avatar; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* === 1. Base Styles === */
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap');
        :root { --primary-color: #0056b3; --primary-hover: #004494; --secondary-color: #f4f4f9; --text-color: #333; --border-color: #e0e0e0; --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); --gradient-start: #eef2f7; --gradient-end: #ffffff; --sidebar-width: 260px; --student-accent: #007bff; }
        body { font-family: 'Poppins', sans-serif; margin: 0; background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end)); color: var(--text-color); min-height: 100vh; display: flex; flex-direction: column; }
        
        /* Topbar & Logout */
        .topbar { display: flex; justify-content: space-between; align-items: center; padding: 15px 40px; background-color: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.05); z-index: 100; position: sticky; top: 0; }
        .logo { font-size: 22px; font-weight: 600; color: var(--primary-color); display: flex; align-items: center; gap: 10px; }
        .topbar-right { display: flex; align-items: center; gap: 20px; }
        .user-name-display { font-weight: 600; font-size: 14px; display: block; }
        .user-role-badge { font-size: 11px; background-color: #e3effd; color: var(--primary-color); padding: 2px 8px; border-radius: 12px; font-weight: 500; }
        .user-avatar-circle { width: 40px; height: 40px; border-radius: 50%; overflow: hidden; border: 2px solid #e3effd; margin-left: 10px; }
        .user-avatar-circle img { width: 100%; height: 100%; object-fit: cover; }
        .logout-btn { color: #d93025; text-decoration: none; font-size: 14px; font-weight: 500; padding: 8px 15px; border-radius: 6px; transition: background 0.2s; }
        .logout-btn:hover { background-color: #fff0f0; }
        
        /* Layout */
        .layout-container { display: flex; flex: 1; max-width: 1400px; margin: 0 auto; width: 100%; padding: 20px; box-sizing: border-box; gap: 20px; }
        .sidebar { width: var(--sidebar-width); background: #fff; border-radius: 12px; box-shadow: var(--card-shadow); padding: 20px 0; flex-shrink: 0; min-height: calc(100vh - 120px); }
        
        /* Sidebar Menu */
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

        /* === 2. Page Specific Styles (Meeting) === */
        
        /* Supervisor Card */
        .supervisor-profile-card { background: #fff; padding: 25px; border-radius: 12px; box-shadow: var(--card-shadow); display: flex; align-items: center; gap: 20px; border-left: 5px solid #28a745; margin-bottom: 10px; }
        .sv-avatar { width: 80px; height: 80px; border-radius: 50%; background: #e9ecef; display: flex; align-items: center; justify-content: center; font-size: 32px; color: #6c757d; overflow: hidden; border: 3px solid #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .sv-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .sv-info h2 { margin: 0 0 5px 0; color: #333; font-size: 20px; }
        .sv-info p { margin: 3px 0; color: #666; font-size: 14px; }
        
        /* Calendar UI */
        .calendar-wrapper { background: #fff; border-radius: 12px; box-shadow: var(--card-shadow); overflow: hidden; display: flex; flex-direction: column; }
        .calendar-header { display: flex; justify-content: space-between; align-items: center; padding: 20px; background: var(--primary-color); color: white; }
        .calendar-nav-btn { background: rgba(255,255,255,0.2); border: none; color: white; padding: 5px 12px; border-radius: 4px; cursor: pointer; font-size: 16px; }
        .calendar-nav-btn:hover { background: rgba(255,255,255,0.3); }
        .current-month { font-size: 18px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }
        
        .calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); padding: 20px; gap: 10px; }
        .cal-day-header { text-align: center; font-weight: 600; color: #888; font-size: 13px; padding-bottom: 10px; }
        
        .cal-day { 
            aspect-ratio: 1; border-radius: 8px; border: 1px solid #eee; display: flex; flex-direction: column; align-items: center; justify-content: flex-start; padding-top: 8px; cursor: pointer; position: relative; transition: all 0.2s;
        }
        .cal-day:hover { background-color: #f9f9f9; border-color: #ddd; }
        .cal-day.empty { border: none; background: transparent; cursor: default; }
        .cal-day.has-slots { background-color: #e3f2fd; border-color: #90caf9; color: #0d47a1; font-weight: 600; }
        .cal-day.has-slots:hover { background-color: #bbdefb; transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .cal-day.selected { background-color: var(--primary-color); color: white; border-color: var(--primary-color); transform: scale(1.05); z-index: 2; box-shadow: 0 4px 10px rgba(0,0,0,0.2); }
        
        .slot-indicator { font-size: 10px; margin-top: 5px; background: rgba(255,255,255,0.8); padding: 2px 5px; border-radius: 4px; color: #333; }
        .cal-day.has-slots .slot-indicator { background: rgba(33, 150, 243, 0.2); color: #0d47a1; }
        .cal-day.selected .slot-indicator { background: rgba(255,255,255,0.3); color: white; }

        /* Selected Slots Area */
        .selected-date-slots { margin-top: 20px; background: #fff; padding: 20px; border-radius: 12px; box-shadow: var(--card-shadow); display: none; border-top: 4px solid var(--primary-color); animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
        .slots-list-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 15px; margin-top: 15px; }
        .time-slot-btn { 
            background: #fff; border: 1px solid #ddd; padding: 15px; border-radius: 8px; text-align: center; cursor: pointer; transition: all 0.2s;
            display: flex; flex-direction: column; gap: 5px;
        }
        .time-slot-btn:hover { border-color: var(--primary-color); background: #f8f9fa; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .ts-time { font-size: 16px; font-weight: 600; color: #333; }
        .ts-loc { font-size: 12px; color: #777; }
        .ts-icon { font-size: 20px; color: var(--primary-color); margin-bottom: 5px; }

        /* History Table */
        .appointment-list { background: #fff; border-radius: 12px; padding: 20px; box-shadow: var(--card-shadow); margin-top: 30px; }
        .app-item { display: flex; justify-content: space-between; align-items: center; padding: 15px; border-bottom: 1px solid #f0f0f0; }
        .app-item:last-child { border-bottom: none; }
        .app-date { display: flex; flex-direction: column; align-items: center; min-width: 60px; background: #f1f3f5; padding: 5px 10px; border-radius: 6px; }
        .app-month { font-size: 12px; font-weight: 600; color: #666; text-transform: uppercase; }
        .app-day { font-size: 20px; font-weight: 700; color: #333; }
        .app-details { flex: 1; margin-left: 20px; }
        .app-time { font-weight: 600; font-size: 15px; color: #333; }
        .app-loc { font-size: 13px; color: #777; margin-top: 2px; }
        .app-status { font-size: 12px; padding: 3px 10px; border-radius: 20px; font-weight: 600; display: inline-block; margin-top: 5px; }
        
        /* Updated Status Colors */
        .status-Pending { background: #fff3cd; color: #856404; }
        .status-Approved { background: #d4edda; color: #155724; }
        .status-Rejected { background: #f8d7da; color: #721c24; }
        .status-Cancelled { background: #e2e3e5; color: #383d41; } /* Grey for cancelled */

        /* Modal */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; backdrop-filter: blur(2px); justify-content: center; align-items: center; opacity: 0; transition: opacity 0.3s ease; }
        .modal-overlay.show { display: flex; opacity: 1; }
        .modal-box { background: #fff; padding: 35px; border-radius: 12px; width: 90%; max-width: 450px; text-align: center; box-shadow: 0 15px 35px rgba(0,0,0,0.2); transform: scale(0.9); transition: transform 0.3s ease; }
        .modal-overlay.show .modal-box { transform: scale(1); }
        .modal-icon { font-size: 40px; color: var(--primary-color); margin-bottom: 15px; }
        .modal-title { font-size: 20px; font-weight: 600; margin-bottom: 10px; color: #333; }
        .modal-text { color: #666; margin-bottom: 20px; font-size: 15px; line-height: 1.6; }
        .modal-actions { display: flex; gap: 15px; justify-content: center; }
        .btn-confirm { background: var(--primary-color); color: white; padding: 12px 25px; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; font-size: 15px; }
        .btn-cancel { background: #f1f1f1; color: #444; padding: 12px 25px; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; font-size: 15px; transition: background 0.2s; }
        .btn-cancel:hover { background: #e0e0e0; }
        
        .reason-input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-family: inherit; margin-bottom: 20px; resize: vertical; min-height: 80px; box-sizing: border-box; }
        .empty-state { text-align: center; padding: 40px; color: #999; background: #fff; border-radius: 12px; border: 1px dashed #ddd; }

        @media (max-width: 900px) { .layout-container { flex-direction: column; } .sidebar { width: 100%; min-height: auto; } .sv-card { flex-direction: column; text-align: center; } }
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
            <div class="user-avatar-circle"><img src="<?php echo $user_avatar; ?>" alt="User Avatar"></div>
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
                        if ($linkUrl !== "#") {
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
                                    if ($subLinkUrl !== "#") {
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
                <h1 class="page-title">Book Consultation</h1>
                <p style="color:#666; margin:0;">Select a date from the calendar to see available slots.</p>
            </div>

            <?php if ($my_supervisor): ?>
                <div class="supervisor-profile-card">
                    <div class="sv-avatar">
                        <?php 
                            echo !empty($my_supervisor['fyp_profileimg']) ? "<img src='{$my_supervisor['fyp_profileimg']}'>" : strtoupper(substr($my_supervisor['fyp_name'] ?? 'U', 0, 1));
                        ?>
                    </div>
                    <div class="sv-info">
                        <h2><?php echo htmlspecialchars($my_supervisor['fyp_name']); ?></h2>
                        <p><i class="fa fa-envelope" style="width:20px;"></i> <?php echo htmlspecialchars($my_supervisor['fyp_email']); ?></p>
                        <p><i class="fa fa-door-open" style="width:20px;"></i> Room: <?php echo htmlspecialchars($my_supervisor['fyp_roomno']); ?></p>
                        <p><i class="fa fa-phone" style="width:20px;"></i> <?php echo htmlspecialchars($my_supervisor['fyp_contactno']); ?></p>
                    </div>
                </div>

                <div class="calendar-wrapper">
                    <div class="calendar-header">
                        <button type="button" class="calendar-nav-btn" onclick="prevMonth()"><i class="fa fa-chevron-left"></i></button>
                        <div class="current-month" id="calendarMonthYear"></div>
                        <button type="button" class="calendar-nav-btn" onclick="nextMonth()"><i class="fa fa-chevron-right"></i></button>
                    </div>
                    
                    <div class="calendar-grid" id="calendarDays">
                        </div>
                </div>

                <div id="selectedSlotsContainer" class="selected-date-slots">
                    <h3 style="margin:0; font-size:18px; color:#333; border-bottom:1px solid #eee; padding-bottom:10px;">
                        Available Times for <span id="selectedDateText" style="color:var(--primary-color);"></span>
                    </h3>
                    <div id="slotsList" class="slots-list-grid">
                        </div>
                </div>

                <div class="section-header" style="margin-top: 40px;">My Appointments</div>
                
                <?php if (count($my_history) > 0): ?>
                    <div class="appointment-list">
                        <?php foreach ($my_history as $myapp): ?>
                            <?php 
                                $statusClass = 'status-Pending';
                                if(strtolower($myapp['fyp_status']) == 'approved') $statusClass = 'status-Approved';
                                if(strtolower($myapp['fyp_status']) == 'rejected') $statusClass = 'status-Rejected';
                                if(strtolower($myapp['fyp_status']) == 'cancelled') $statusClass = 'status-Cancelled'; // 灰色
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
                                    <div class="app-loc">
                                        <i class="fa fa-map-marker-alt" style="font-size:12px;"></i> <?php echo !empty($myapp['fyp_location']) ? htmlspecialchars($myapp['fyp_location']) : 'Office'; ?>
                                        <?php if(!empty($myapp['fyp_reason'])): ?>
                                            <div style="font-size:12px; color:#888; margin-top:2px;">Topic: <?php echo htmlspecialchars($myapp['fyp_reason']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="app-status <?php echo $statusClass; ?>">
                                        <?php echo htmlspecialchars($myapp['fyp_status']); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state" style="padding: 20px;">
                        <p style="color:#777; font-style:italic; margin:0;">No booking history.</p>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="empty-state">
                    <i class="fa fa-user-slash" style="font-size:48px; color:#d9534f; margin-bottom:15px;"></i>
                    <p><strong>Supervisor Not Assigned</strong></p>
                    <p style="font-size:14px; color:#666;">You need to have an approved FYP project and supervisor before you can book appointments.</p>
                    <a href="std_projectreg.php?auth_user_id=<?php echo urlencode($auth_user_id); ?>" class="btn-book" style="display:inline-block; width:auto; padding:10px 25px; text-decoration:none; margin-top:15px;">Go to Registration</a>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <div id="confirmationModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-icon"><i class="fa fa-question-circle"></i></div>
            <div class="modal-title">Book Slot</div>
            <p class="modal-text" id="modalText"></p>
            
            <form method="POST" class="modal-actions" style="display:block;">
                <input type="hidden" name="schedule_id" id="modalScheduleId">
                
                <div style="text-align:left; margin-bottom:15px;">
                    <label style="font-size:13px; font-weight:600; color:#555;">Reason for Consultation:</label>
                    <textarea name="reason" class="reason-input" placeholder="E.g. Discussing Chapter 2 difficulties..." required></textarea>
                </div>

                <div style="display:flex; justify-content:center; gap:10px;">
                    <button type="submit" name="confirm_booking" id="modalSubmitBtn" class="btn-confirm">Yes, Book it</button>
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const confirmModal = document.getElementById('confirmationModal');
        const modalText = document.getElementById('modalText');
        const slotsJson = <?php echo $slots_json; ?>; // PHP Array to JS Object

        let currentDate = new Date();
        let currentMonth = currentDate.getMonth();
        let currentYear = currentDate.getFullYear();

        const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

        // Init Calendar
        renderCalendar(currentMonth, currentYear);

        function renderCalendar(month, year) {
            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const calendarDays = document.getElementById('calendarDays');
            const monthYearText = document.getElementById('calendarMonthYear');
            
            calendarDays.innerHTML = '';
            monthYearText.innerText = `${monthNames[month]} ${year}`;
            
            // Add Header Row (Sun-Sat)
            const daysShort = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            daysShort.forEach(day => {
                const header = document.createElement('div');
                header.className = 'cal-day-header';
                header.innerText = day;
                calendarDays.appendChild(header);
            });

            // Empty slots for previous month
            for (let i = 0; i < firstDay; i++) {
                const emptyCell = document.createElement('div');
                emptyCell.className = 'cal-day empty';
                calendarDays.appendChild(emptyCell);
            }

            // Days
            for (let i = 1; i <= daysInMonth; i++) {
                const dayCell = document.createElement('div');
                dayCell.className = 'cal-day';
                dayCell.innerText = i;
                
                // Construct date string YYYY-MM-DD
                const monthStr = (month + 1).toString().padStart(2, '0');
                const dayStr = i.toString().padStart(2, '0');
                const fullDate = `${year}-${monthStr}-${dayStr}`;
                
                // Check if slots exist for this date
                const slotsForDay = slotsJson.filter(s => s.fyp_date === fullDate);
                
                if (slotsForDay.length > 0) {
                    dayCell.classList.add('has-slots');
                    const indicator = document.createElement('div');
                    indicator.className = 'slot-indicator';
                    indicator.innerText = `${slotsForDay.length} Slots`;
                    dayCell.appendChild(indicator);
                    
                    dayCell.onclick = function() {
                        // Remove active class from others
                        document.querySelectorAll('.cal-day').forEach(d => d.classList.remove('selected'));
                        this.classList.add('selected');
                        showSlotsForDate(fullDate, slotsForDay);
                    };
                }

                calendarDays.appendChild(dayCell);
            }
        }

        function prevMonth() {
            currentMonth--;
            if (currentMonth < 0) {
                currentMonth = 11;
                currentYear--;
            }
            renderCalendar(currentMonth, currentYear);
        }

        function nextMonth() {
            currentMonth++;
            if (currentMonth > 11) {
                currentMonth = 0;
                currentYear++;
            }
            renderCalendar(currentMonth, currentYear);
        }

        function showSlotsForDate(dateStr, slots) {
            const container = document.getElementById('selectedSlotsContainer');
            const list = document.getElementById('slotsList');
            const dateText = document.getElementById('selectedDateText');
            
            dateText.innerText = dateStr;
            list.innerHTML = '';
            
            slots.forEach(slot => {
                const btn = document.createElement('div');
                btn.className = 'time-slot-btn';
                // Format Time HH:MM:SS to HH:MM AM/PM
                const formatTime = (timeStr) => {
                    const [hour, minute] = timeStr.split(':');
                    const h = parseInt(hour);
                    const ampm = h >= 12 ? 'PM' : 'AM';
                    const h12 = h % 12 || 12;
                    return `${h12}:${minute} ${ampm}`;
                };
                
                btn.innerHTML = `
                    <div class="ts-icon"><i class="fa fa-clock"></i></div>
                    <div class="ts-time">${formatTime(slot.fyp_fromtime)} - ${formatTime(slot.fyp_totime)}</div>
                    <div class="ts-loc">${slot.fyp_location || 'Office'}</div>
                `;
                btn.onclick = () => openBookModal(slot.fyp_scheduleid, `${slot.fyp_date} (${formatTime(slot.fyp_fromtime)})`);
                list.appendChild(btn);
            });
            
            container.style.display = 'block';
            // Scroll to slots
            container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        function openBookModal(scheduleId, dateStr) {
            modalText.innerHTML = "Confirm booking for:<br><strong>" + dateStr + "</strong>?";
            document.getElementById('modalScheduleId').value = scheduleId;
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