<?php
// ====================================================
// supervisor_meeting.php - 最终版 (移除调试 + 增加 Slot 编辑功能)
// ====================================================
include("connect.php");

// 1. 验证用户登录
$auth_user_id = $_GET['auth_user_id'] ?? null;
$current_page = 'schedule'; 

if (!$auth_user_id) { header("location: login.php"); exit; }

// 2. 获取导师信息
$user_name = "Supervisor"; 
$user_avatar = "image/user.png"; 
$sv_id = ""; // Staff ID (String)
$sv_room = "Office"; 

if (isset($conn)) {
    $sql_sv = "SELECT * FROM supervisor WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_sv)) {
        $stmt->bind_param("i", $auth_user_id); $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $row = $res->fetch_assoc();
            
            // 优先获取 fyp_staffid
            if (!empty($row['fyp_staffid'])) {
                $sv_id = $row['fyp_staffid'];
            } elseif (!empty($row['fyp_supervisorid'])) {
                $sv_id = $row['fyp_supervisorid'];
            }
            
            if (!empty($row['fyp_name'])) $user_name = $row['fyp_name'];
            if (!empty($row['fyp_profileimg'])) $user_avatar = $row['fyp_profileimg'];
            if (!empty($row['fyp_roomno'])) $sv_room = $row['fyp_roomno'];
        }
        $stmt->close();
    }
}

// ====================================================
// 3. 处理 POST 请求
// ====================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // A. 批量发布时间段 (Batch Add Slot)
    if (isset($_POST['add_slot'])) {
        if (empty($sv_id)) {
            echo "<script>alert('Error: Supervisor ID (Staff ID) not found.');</script>";
        } else {
            $start_date = $_POST['start_date'];
            $end_date = $_POST['end_date'];
            $days_selected = $_POST['days'] ?? []; 
            
            $start_time = $_POST['start_hour'] . ':' . $_POST['start_min'] . ':00';
            $end_time   = $_POST['end_hour'] . ':' . $_POST['end_min'] . ':00';
            $location   = !empty($_POST['location']) ? $_POST['location'] : $sv_room;
            
            if ($start_time >= $end_time) {
                echo "<script>alert('Error: End time must be later than Start time.');</script>";
            } elseif (empty($days_selected)) {
                echo "<script>alert('Please select at least one day.');</script>";
            } else {
                $sql_add = "INSERT INTO schedule_meeting (fyp_staffid, fyp_date, fyp_day, fyp_fromtime, fyp_totime, fyp_location, fyp_status) 
                            VALUES (?, ?, ?, ?, ?, ?, 'Available')";
                
                if ($stmt = $conn->prepare($sql_add)) {
                    $count_added = 0;
                    $current = strtotime($start_date);
                    $end = strtotime($end_date);
                    
                    while ($current <= $end) {
                        $current_date_str = date('Y-m-d', $current); 
                        $day_name = date('l', $current); 
                        
                        if (in_array($day_name, $days_selected)) {
                            // StaffID 是字符串，所以用 "s"
                            $stmt->bind_param("ssssss", $sv_id, $current_date_str, $day_name, $start_time, $end_time, $location);
                            if($stmt->execute()) {
                                $count_added++;
                            }
                        }
                        $current = strtotime('+1 day', $current);
                    }
                    
                    if ($count_added > 0) {
                        echo "<script>alert('Success! $count_added slots added.'); window.location.href='supervisor_meeting.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
                    } else {
                        echo "<script>alert('No slots added. Check date range.');</script>";
                    }
                    $stmt->close();
                } else {
                    echo "<script>alert('Database Error: " . addslashes($conn->error) . "');</script>";
                }
            }
        }
    }

    // B. 编辑时间段 (Edit Slot - Update)
    if (isset($_POST['edit_slot'])) {
        $sch_id = $_POST['schedule_id'];
        $new_date = $_POST['edit_date'];
        $new_day = date('l', strtotime($new_date)); // 重新计算星期几
        
        $new_start_time = $_POST['edit_start_hour'] . ':' . $_POST['edit_start_min'] . ':00';
        $new_end_time   = $_POST['edit_end_hour'] . ':' . $_POST['edit_end_min'] . ':00';
        $new_location   = $_POST['edit_location'];

        if ($new_start_time >= $new_end_time) {
            echo "<script>alert('Error: End time must be later than Start time.');</script>";
        } else {
            // 只允许更新 Available 的时间段，防止破坏已有预约
            $sql_edit = "UPDATE schedule_meeting 
                         SET fyp_date = ?, fyp_day = ?, fyp_fromtime = ?, fyp_totime = ?, fyp_location = ? 
                         WHERE fyp_scheduleid = ? AND fyp_status = 'Available'";
            
            if ($stmt = $conn->prepare($sql_edit)) {
                $stmt->bind_param("sssssi", $new_date, $new_day, $new_start_time, $new_end_time, $new_location, $sch_id);
                if ($stmt->execute()) {
                    echo "<script>alert('Slot updated successfully!'); window.location.href='supervisor_meeting.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
                } else {
                    echo "<script>alert('Error updating slot.');</script>";
                }
                $stmt->close();
            }
        }
    }

    // C. 删除时间段
    if (isset($_POST['delete_slot'])) {
        $sch_id = $_POST['schedule_id'];
        $conn->query("DELETE FROM schedule_meeting WHERE fyp_scheduleid = '$sch_id' AND fyp_status = 'Available'");
        echo "<script>window.location.href='supervisor_meeting.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
    }
    
    // D. 处理预约请求
    if (isset($_POST['handle_appointment'])) {
        $app_id = $_POST['appointment_id'];
        $sch_id = $_POST['schedule_id'];
        $action = $_POST['action_type']; 
        
        if ($action == 'approve') {
            $conn->query("UPDATE appointment_meeting SET fyp_status = 'Approved' WHERE fyp_appointmentid = '$app_id'");
            echo "<script>alert('Appointment Approved!'); window.location.href='supervisor_meeting.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
        } 
        elseif ($action == 'reject' || $action == 'cancel') {
            $new_status = ($action == 'reject') ? 'Rejected' : 'Cancelled';
            $conn->query("UPDATE appointment_meeting SET fyp_status = '$new_status' WHERE fyp_appointmentid = '$app_id'");
            $conn->query("UPDATE schedule_meeting SET fyp_status = 'Available' WHERE fyp_scheduleid = '$sch_id'");
            
            $msg = ($action == 'reject') ? 'Request Rejected.' : 'Appointment Cancelled.';
            echo "<script>alert('$msg'); window.location.href='supervisor_meeting.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
        }
    }
}

// ====================================================
// 4. 获取数据 (GET)
// ====================================================

$pending_requests = [];
$upcoming_meetings = [];
$my_slots = [];

if (!empty($sv_id)) {
    // A. Pending
    $sql_pen = "SELECT am.*, s.fyp_studname, s.fyp_studid, sm.fyp_date, sm.fyp_day, sm.fyp_fromtime, sm.fyp_totime 
                FROM appointment_meeting am
                JOIN student s ON am.fyp_studid = s.fyp_studid
                JOIN schedule_meeting sm ON am.fyp_scheduleid = sm.fyp_scheduleid
                WHERE am.fyp_staffid = '$sv_id' AND am.fyp_status = 'Pending'
                ORDER BY sm.fyp_date ASC";
    $res_pen = $conn->query($sql_pen);
    if ($res_pen) { while ($row = $res_pen->fetch_assoc()) $pending_requests[] = $row; }

    // B. Upcoming
    $sql_up = "SELECT am.*, s.fyp_studname, s.fyp_studid, sm.fyp_date, sm.fyp_day, sm.fyp_fromtime, sm.fyp_totime, sm.fyp_location
               FROM appointment_meeting am
               JOIN student s ON am.fyp_studid = s.fyp_studid
               JOIN schedule_meeting sm ON am.fyp_scheduleid = sm.fyp_scheduleid
               WHERE am.fyp_staffid = '$sv_id' AND am.fyp_status = 'Approved' AND sm.fyp_date >= CURDATE()
               ORDER BY sm.fyp_date ASC";
    $res_up = $conn->query($sql_up);
    if ($res_up) { while ($row = $res_up->fetch_assoc()) $upcoming_meetings[] = $row; }

    // C. Available Slots
    $sql_slots = "SELECT * FROM schedule_meeting 
                  WHERE fyp_staffid = '$sv_id' AND fyp_status = 'Available' 
                  ORDER BY fyp_date ASC";
    $res_slots = $conn->query($sql_slots);
    if ($res_slots) { while ($row = $res_slots->fetch_assoc()) $my_slots[] = $row; }
}

// 菜单
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
    <title>Manage Schedule</title>
    <link rel="icon" type="image/png" href="<?php echo $user_avatar; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* 复用 mainpage CSS */
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap');
        :root { --primary-color: #0056b3; --primary-hover: #004494; --secondary-color: #f4f4f9; --text-color: #333; --border-color: #e0e0e0; --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); }
        body { font-family: 'Poppins', sans-serif; margin: 0; background: linear-gradient(135deg, #eef2f7, #ffffff); color: var(--text-color); min-height: 100vh; display: flex; flex-direction: column; }
        
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
        .sidebar { width: 260px; background: #fff; border-radius: 12px; box-shadow: var(--card-shadow); padding: 20px 0; flex-shrink: 0; min-height: calc(100vh - 120px); }
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

        .container-cards { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .card { background: #fff; padding: 25px; border-radius: 12px; box-shadow: var(--card-shadow); margin-bottom: 20px; }
        .card-header { font-size: 18px; font-weight: 600; color: #333; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        
        /* Forms & Inputs */
        .form-row { display: flex; gap: 15px; margin-bottom: 15px; }
        .form-group { flex: 1; }
        .form-group label { display: block; font-size: 13px; margin-bottom: 5px; color: #555; font-weight: 500; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; }
        
        .time-select-wrapper { display: flex; align-items: center; gap: 5px; }
        .time-select { padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-family: inherit; font-size: 14px; flex: 1; cursor: pointer; }
        .time-sep { font-weight: bold; color: #555; }

        .days-checkbox-group { display: flex; flex-wrap: wrap; gap: 10px; }
        .day-label { display: flex; align-items: center; font-size: 13px; cursor: pointer; background: #f8f9fa; padding: 5px 10px; border-radius: 20px; border: 1px solid #e0e0e0; transition: all 0.2s; }
        .day-label:hover { background: #e2e6ea; }
        .day-label input { margin-right: 5px; }
        .day-label input:checked + span { font-weight: 600; color: var(--primary-color); }

        .btn-add { background: var(--primary-color); color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; width: 100%; }
        .btn-add:hover { background: #004494; }

        /* Tables */
        .req-table { width: 100%; border-collapse: collapse; }
        .req-table th { text-align: left; padding: 10px; background: #f8f9fa; font-size: 13px; color: #666; font-weight: 600; }
        .req-table td { padding: 12px 10px; border-bottom: 1px solid #eee; font-size: 14px; color: #333; }
        .req-table tr:last-child td { border-bottom: none; }
        
        .badge { padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .bg-pending { background: #fff3cd; color: #856404; }
        .bg-approved { background: #d4edda; color: #155724; }
        .bg-available { background: #cce5ff; color: #004085; }
        
        .action-btns { display: flex; gap: 5px; }
        .btn-sm { padding: 5px 10px; border: none; border-radius: 4px; font-size: 12px; cursor: pointer; color: white; text-decoration: none; }
        .btn-accept { background: #28a745; }
        .btn-reject { background: #dc3545; }
        .btn-cancel { background: #6c757d; }
        
        .btn-edit { background: #007bff; color: white; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; border-radius: 4px; border:none; cursor: pointer; }
        .btn-delete { background: #dc3545; color: white; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; border-radius: 4px; border:none; cursor: pointer;}

        .empty-state { text-align: center; padding: 30px; color: #999; font-style: italic; }

        /* Modal Styles */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-overlay.show { display: flex; }
        .modal-box { background: #fff; padding: 25px; border-radius: 12px; width: 90%; max-width: 500px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
        .modal-title { font-size: 18px; font-weight: 600; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
        .btn-save { background: #28a745; color: white; padding: 8px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: 500; }
        .btn-close { background: #6c757d; color: white; padding: 8px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: 500; }

        @media (max-width: 1100px) { .container-cards { grid-template-columns: 1fr; } }
        @media (max-width: 900px) { .layout-container { flex-direction: column; } .sidebar { width: 100%; min-height: auto; } }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="logo"><img src="image/ladybug.png" alt="Logo" style="width: 32px; margin-right: 10px;"> FYP System</div>
        <div class="topbar-right">
            <div class="user-profile-summary">
                <span class="user-name-display"><?php echo htmlspecialchars($user_name); ?></span>
                <span class="user-role-badge">Lecturer</span>
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
                        $isActive = ($key == 'schedule'); 
                        $linkUrl = isset($item['link']) ? $item['link'] : "#";
                        if (strpos($linkUrl, '.php') !== false) {
                             $separator = (strpos($linkUrl, '?') !== false) ? '&' : '?';
                             $linkUrl .= $separator . "auth_user_id=" . urlencode($auth_user_id);
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
                                    $subLinkUrl = isset($sub_item['link']) ? $sub_item['link'] : "#";
                                    if (strpos($subLinkUrl, '.php') !== false) {
                                        $separator = (strpos($subLinkUrl, '?') !== false) ? '&' : '?';
                                        $subLinkUrl .= $separator . "auth_user_id=" . urlencode($auth_user_id);
                                    }
                                ?>
                                    <li><a href="<?php echo $subLinkUrl; ?>" class="menu-link <?php echo ($sub_key == 'schedule') ? 'active' : ''; ?>">
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
            <div class="container-cards">
                <div class="card">
                    <div class="card-header">Publish Availability (Batch)</div>
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Start Date</label>
                                <input type="date" name="start_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="form-group">
                                <label>End Date</label>
                                <input type="date" name="end_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 15px;">
                            <label>Repeat on Days:</label>
                            <div class="days-checkbox-group">
                                <label class="day-label"><input type="checkbox" name="days[]" value="Monday"> <span>Mon</span></label>
                                <label class="day-label"><input type="checkbox" name="days[]" value="Tuesday"> <span>Tue</span></label>
                                <label class="day-label"><input type="checkbox" name="days[]" value="Wednesday"> <span>Wed</span></label>
                                <label class="day-label"><input type="checkbox" name="days[]" value="Thursday"> <span>Thu</span></label>
                                <label class="day-label"><input type="checkbox" name="days[]" value="Friday"> <span>Fri</span></label>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Start Time</label>
                                <div class="time-select-wrapper">
                                    <select name="start_hour" class="time-select">
                                        <?php for($i=8; $i<=20; $i++): $h = str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                                            <option value="<?php echo $h; ?>"><?php echo $h; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <span class="time-sep">:</span>
                                    <select name="start_min" class="time-select">
                                        <option value="00">00</option>
                                        <option value="15">15</option>
                                        <option value="30">30</option>
                                        <option value="45">45</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>End Time</label>
                                <div class="time-select-wrapper">
                                    <select name="end_hour" class="time-select">
                                        <?php for($i=9; $i<=21; $i++): $h = str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                                            <option value="<?php echo $h; ?>"><?php echo $h; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <span class="time-sep">:</span>
                                    <select name="end_min" class="time-select">
                                        <option value="00">00</option>
                                        <option value="15">15</option>
                                        <option value="30">30</option>
                                        <option value="45">45</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 20px;">
                            <label>Location (Optional)</label>
                            <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($sv_room); ?>" placeholder="e.g. Online / Room 101">
                        </div>
                        <button type="submit" name="add_slot" class="btn-add"><i class="fa fa-plus-circle"></i> Add Slots (Batch)</button>
                    </form>
                </div>

                <div class="card">
                    <div class="card-header">
                        My Available Slots
                        <span style="font-size:11px; color:#999; margin-left:10px; font-weight:normal;">(Showing all dates)</span>
                    </div>
                    <?php if (count($my_slots) > 0): ?>
                        <div style="max-height: 400px; overflow-y: auto;">
                            <table class="req-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($my_slots as $slot): ?>
                                        <tr>
                                            <td>
                                                <div><?php echo $slot['fyp_date']; ?></div>
                                                <div style="font-size:12px; color:#888;"><?php echo $slot['fyp_day']; ?></div>
                                            </td>
                                            <td>
                                                <?php echo date('H:i', strtotime($slot['fyp_fromtime'])) . ' - ' . date('H:i', strtotime($slot['fyp_totime'])); ?>
                                                <div style="font-size:11px; color:#888;"><?php echo $slot['fyp_location']; ?></div>
                                            </td>
                                            <td>
                                                <div style="display:flex; gap:5px;">
                                                    <button type="button" class="btn-edit" title="Manage / Edit" 
                                                        onclick="openEditModal('<?php echo $slot['fyp_scheduleid']; ?>', '<?php echo $slot['fyp_date']; ?>', '<?php echo $slot['fyp_fromtime']; ?>', '<?php echo $slot['fyp_totime']; ?>', '<?php echo htmlspecialchars($slot['fyp_location']); ?>')">
                                                        <i class="fa fa-edit"></i>
                                                    </button>
                                                    
                                                    <form method="POST" onsubmit="return confirm('Delete this available slot?');">
                                                        <input type="hidden" name="schedule_id" value="<?php echo $slot['fyp_scheduleid']; ?>">
                                                        <button type="submit" name="delete_slot" class="btn-delete" title="Delete"><i class="fa fa-trash"></i></button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">No available slots posted yet.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header" style="border-left: 5px solid #d93025; padding-left:10px;">
                    Incoming Appointment Requests <span class="badge bg-pending" style="margin-left:10px;"><?php echo count($pending_requests); ?> Pending</span>
                </div>
                
                <?php if (count($pending_requests) > 0): ?>
                    <table class="req-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Requested Slot</th>
                                <th>Reason</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_requests as $req): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($req['fyp_studname']); ?></strong>
                                        <div style="font-size:12px; color:#666;"><?php echo htmlspecialchars($req['fyp_studid']); ?></div>
                                    </td>
                                    <td>
                                        <div><?php echo $req['fyp_date'] . " (" . $req['fyp_day'] . ")"; ?></div>
                                        <div style="font-weight:600; color:#0056b3;">
                                            <?php echo date('H:i', strtotime($req['fyp_fromtime'])) . ' - ' . date('H:i', strtotime($req['fyp_totime'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="max-width:200px; font-size:13px; color:#555;">
                                            <?php echo !empty($req['fyp_reason']) ? htmlspecialchars($req['fyp_reason']) : 'No reason provided'; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <form method="POST" style="display:flex; gap:5px;">
                                            <input type="hidden" name="appointment_id" value="<?php echo $req['fyp_appointmentid']; ?>">
                                            <input type="hidden" name="schedule_id" value="<?php echo $req['fyp_scheduleid']; ?>">
                                            
                                            <button type="submit" name="handle_appointment" value="approve" class="btn-sm btn-accept" onclick="this.form.action_type.value='approve'">
                                                <i class="fa fa-check"></i> Accept
                                            </button>
                                            <button type="submit" name="handle_appointment" value="reject" class="btn-sm btn-reject" onclick="this.form.action_type.value='reject'">
                                                <i class="fa fa-times"></i> Reject
                                            </button>
                                            <input type="hidden" name="action_type" value="">
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">No pending requests.</div>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="card-header" style="border-left: 5px solid #28a745; padding-left:10px;">
                    Upcoming Scheduled Meetings
                </div>
                
                <?php if (count($upcoming_meetings) > 0): ?>
                    <table class="req-table">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Student</th>
                                <th>Location</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcoming_meetings as $up): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight:600; color:#333;"><?php echo $up['fyp_date']; ?></div>
                                        <div><?php echo date('H:i', strtotime($up['fyp_fromtime'])) . ' - ' . date('H:i', strtotime($up['fyp_totime'])); ?></div>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($up['fyp_studname']); ?>
                                        <div style="font-size:12px; color:#666;"><?php echo htmlspecialchars($up['fyp_studid']); ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars($up['fyp_location']); ?></td>
                                    <td>
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to CANCEL this approved meeting?');">
                                            <input type="hidden" name="appointment_id" value="<?php echo $up['fyp_appointmentid']; ?>">
                                            <input type="hidden" name="schedule_id" value="<?php echo $up['fyp_scheduleid']; ?>">
                                            <input type="hidden" name="action_type" value="cancel">
                                            <button type="submit" name="handle_appointment" class="btn-sm btn-cancel">
                                                <i class="fa fa-ban"></i> Cancel
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">No upcoming meetings scheduled.</div>
                <?php endif; ?>
            </div>

        </main>
    </div>

    <div id="editSlotModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-title">Manage Slot</div>
            <form method="POST">
                <input type="hidden" name="schedule_id" id="edit_id">
                
                <div class="form-group" style="margin-bottom:15px;">
                    <label>Date</label>
                    <input type="date" name="edit_date" id="edit_date" class="form-control" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Start Time</label>
                        <div class="time-select-wrapper">
                            <select name="edit_start_hour" id="edit_start_hour" class="time-select">
                                <?php for($i=8; $i<=20; $i++): $h = str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                                    <option value="<?php echo $h; ?>"><?php echo $h; ?></option>
                                <?php endfor; ?>
                            </select>
                            <span class="time-sep">:</span>
                            <select name="edit_start_min" id="edit_start_min" class="time-select">
                                <option value="00">00</option>
                                <option value="15">15</option>
                                <option value="30">30</option>
                                <option value="45">45</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>End Time</label>
                        <div class="time-select-wrapper">
                            <select name="edit_end_hour" id="edit_end_hour" class="time-select">
                                <?php for($i=9; $i<=21; $i++): $h = str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                                    <option value="<?php echo $h; ?>"><?php echo $h; ?></option>
                                <?php endfor; ?>
                            </select>
                            <span class="time-sep">:</span>
                            <select name="edit_end_min" id="edit_end_min" class="time-select">
                                <option value="00">00</option>
                                <option value="15">15</option>
                                <option value="30">30</option>
                                <option value="45">45</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label>Location</label>
                    <input type="text" name="edit_location" id="edit_location" class="form-control" required>
                </div>

                <div class="modal-footer">
                    <button type="submit" name="edit_slot" class="btn-save">Save Changes</button>
                    <button type="button" class="btn-close" onclick="closeEditModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const editModal = document.getElementById('editSlotModal');

        function openEditModal(id, date, start_time, end_time, location) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_date').value = date;
            document.getElementById('edit_location').value = location;

            // Split time string (e.g. "14:00:00") into hour and min
            if(start_time) {
                const s = start_time.split(':');
                document.getElementById('edit_start_hour').value = s[0];
                document.getElementById('edit_start_min').value = s[1];
            }
            if(end_time) {
                const e = end_time.split(':');
                document.getElementById('edit_end_hour').value = e[0];
                document.getElementById('edit_end_min').value = e[1];
            }

            editModal.classList.add('show');
        }

        function closeEditModal() {
            editModal.classList.remove('show');
        }

        window.onclick = function(e) {
            if (e.target.classList.contains('modal-overlay')) {
                e.target.classList.remove('show');
            }
        }
    </script>
</body>
</html>