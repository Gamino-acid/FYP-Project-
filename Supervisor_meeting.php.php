<?php
// ====================================================
// supervisor_meeting.php - 导师会议管理 (发布时间 & 审批预约)
// ====================================================
include("connect.php");

// 1. 验证用户登录
$auth_user_id = $_GET['auth_user_id'] ?? null;
// 手动设置当前页面为 schedule 以高亮菜单
$current_page = 'schedule'; 

if (!$auth_user_id) { header("location: login.php"); exit; }

// 2. 获取导师信息
$user_name = "Supervisor"; 
$user_avatar = "image/user.png"; 
$sv_id = 0;
$sv_room = "Office"; // 默认地点

if (isset($conn)) {
    // 获取 Supervisor ID
    $sql_sv = "SELECT * FROM supervisor WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_sv)) {
        $stmt->bind_param("i", $auth_user_id); $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $sv_id = $row['fyp_supervisorid'];
            if (!empty($row['fyp_name'])) $user_name = $row['fyp_name'];
            if (!empty($row['fyp_profileimg'])) $user_avatar = $row['fyp_profileimg'];
            if (!empty($row['fyp_roomno'])) $sv_room = $row['fyp_roomno'];
        }
        $stmt->close();
    }
}

// ====================================================
// 3. 处理 POST 请求 (发布时间 / 处理预约)
// ====================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // A. 发布新时间段 (Add Slot)
    if (isset($_POST['add_slot'])) {
        $date = $_POST['date'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $location = !empty($_POST['location']) ? $_POST['location'] : $sv_room;
        
        // 获取星期几
        $day_of_week = date('l', strtotime($date));
        
        $sql_add = "INSERT INTO schedule_meeting (fyp_supervisorid, fyp_date, fyp_day, fyp_fromtime, fyp_totime, fyp_location, fyp_status) 
                    VALUES (?, ?, ?, ?, ?, ?, 'Available')";
        
        if ($stmt = $conn->prepare($sql_add)) {
            $stmt->bind_param("isssss", $sv_id, $date, $day_of_week, $start_time, $end_time, $location);
            if ($stmt->execute()) {
                echo "<script>alert('New slot added successfully!'); window.location.href='supervisor_meeting.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
            } else {
                echo "<script>alert('Error adding slot.');</script>";
            }
            $stmt->close();
        }
    }

    // B. 删除时间段 (Delete Slot - 仅限未被预订的)
    if (isset($_POST['delete_slot'])) {
        $sch_id = $_POST['schedule_id'];
        // 只能删除 Available 的，或者强制删除（这里假设只能删除 Available 以防误删已有预约）
        $conn->query("DELETE FROM schedule_meeting WHERE fyp_scheduleid = '$sch_id' AND fyp_status = 'Available'");
        echo "<script>window.location.href='supervisor_meeting.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
    }
    
    // C. 处理预约请求 (Approve / Reject / Cancel)
    if (isset($_POST['handle_appointment'])) {
        $app_id = $_POST['appointment_id'];
        $sch_id = $_POST['schedule_id'];
        $action = $_POST['action_type']; // 'approve', 'reject', 'cancel'
        
        if ($action == 'approve') {
            // 批准：更新预约状态为 Approved
            // 此时 schedule 状态已经是 Booked (学生申请时锁定的)，无需更改
            $conn->query("UPDATE appointment_meeting SET fyp_status = 'Approved' WHERE fyp_appointmentid = '$app_id'");
            echo "<script>alert('Appointment Approved!'); window.location.href='supervisor_meeting.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
        } 
        elseif ($action == 'reject' || $action == 'cancel') {
            // 拒绝/取消：
            // 1. 更新预约状态
            $new_status = ($action == 'reject') ? 'Rejected' : 'Cancelled';
            $conn->query("UPDATE appointment_meeting SET fyp_status = '$new_status' WHERE fyp_appointmentid = '$app_id'");
            
            // 2. [关键] 释放时间段，改回 Available，供他人预约
            $conn->query("UPDATE schedule_meeting SET fyp_status = 'Available' WHERE fyp_scheduleid = '$sch_id'");
            
            $msg = ($action == 'reject') ? 'Request Rejected.' : 'Appointment Cancelled.';
            echo "<script>alert('$msg'); window.location.href='supervisor_meeting.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
        }
    }
}

// ====================================================
// 4. 获取数据 (GET)
// ====================================================

// A. 获取 Pending Requests (待处理请求)
$pending_requests = [];
$sql_pen = "SELECT am.*, s.fyp_studname, s.fyp_studfullid, sm.fyp_date, sm.fyp_day, sm.fyp_fromtime, sm.fyp_totime 
            FROM appointment_meeting am
            JOIN student s ON am.fyp_studid = s.fyp_studid
            JOIN schedule_meeting sm ON am.fyp_scheduleid = sm.fyp_scheduleid
            WHERE am.fyp_supervisorid = '$sv_id' AND am.fyp_status = 'Pending'
            ORDER BY sm.fyp_date ASC";
$res_pen = $conn->query($sql_pen);
while ($row = $res_pen->fetch_assoc()) $pending_requests[] = $row;

// B. 获取 Upcoming Approved Meetings (即将到来的已批准会议)
$upcoming_meetings = [];
$sql_up = "SELECT am.*, s.fyp_studname, s.fyp_studfullid, sm.fyp_date, sm.fyp_day, sm.fyp_fromtime, sm.fyp_totime, sm.fyp_location
           FROM appointment_meeting am
           JOIN student s ON am.fyp_studid = s.fyp_studid
           JOIN schedule_meeting sm ON am.fyp_scheduleid = sm.fyp_scheduleid
           WHERE am.fyp_supervisorid = '$sv_id' AND am.fyp_status = 'Approved' AND sm.fyp_date >= CURDATE()
           ORDER BY sm.fyp_date ASC";
$res_up = $conn->query($sql_up);
while ($row = $res_up->fetch_assoc()) $upcoming_meetings[] = $row;

// C. 获取 My Available Slots (我发布的空闲时间)
$my_slots = [];
$sql_slots = "SELECT * FROM schedule_meeting 
              WHERE fyp_supervisorid = '$sv_id' AND fyp_status = 'Available' AND fyp_date >= CURDATE() 
              ORDER BY fyp_date ASC";
$res_slots = $conn->query($sql_slots);
while ($row = $res_slots->fetch_assoc()) $my_slots[] = $row;


// 5. 菜单定义 (保持一致)
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
    'announcement' => [
        'name' => 'Announcement',
        'icon' => 'fa-bullhorn',
        'sub_items' => [
            'post_announcement' => ['name' => 'Post Announcement', 'icon' => 'fa-pen-square', 'link' => 'supervisor_announcement.php'],
            'view_announcements' => ['name' => 'View History', 'icon' => 'fa-history', 'link' => 'Supervisor_mainpage.php?page=view_announcements'],
        ]
    ],
    // 当前页面
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

        /* Page Specific */
        .container-cards { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .card { background: #fff; padding: 25px; border-radius: 12px; box-shadow: var(--card-shadow); margin-bottom: 20px; }
        .card-header { font-size: 18px; font-weight: 600; color: #333; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        
        /* Add Slot Form */
        .form-row { display: flex; gap: 15px; margin-bottom: 15px; }
        .form-group { flex: 1; }
        .form-group label { display: block; font-size: 13px; margin-bottom: 5px; color: #555; font-weight: 500; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; }
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
        .btn-delete { background: #dc3545; color: white; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; border-radius: 4px; border:none; cursor: pointer;}

        .empty-state { text-align: center; padding: 30px; color: #999; font-style: italic; }

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
            <!-- Part 1: Add Schedule Form & Available Slots -->
            <div class="container-cards">
                <!-- Add New Slot -->
                <div class="card">
                    <div class="card-header">Publish Availability</div>
                    <form method="POST">
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label>Date</label>
                            <input type="date" name="date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Start Time</label>
                                <input type="time" name="start_time" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>End Time</label>
                                <input type="time" name="end_time" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label>Location (Optional)</label>
                            <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($sv_room); ?>" placeholder="e.g. Online / Room 101">
                        </div>
                        <button type="submit" name="add_slot" class="btn-add"><i class="fa fa-plus-circle"></i> Add Slot</button>
                    </form>
                </div>

                <!-- My Available Slots List -->
                <div class="card">
                    <div class="card-header">My Available Slots</div>
                    <?php if (count($my_slots) > 0): ?>
                        <div style="max-height: 300px; overflow-y: auto;">
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
                                                <form method="POST" onsubmit="return confirm('Delete this available slot?');">
                                                    <input type="hidden" name="schedule_id" value="<?php echo $slot['fyp_scheduleid']; ?>">
                                                    <button type="submit" name="delete_slot" class="btn-delete" title="Delete"><i class="fa fa-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">No available slots posted.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Part 2: Incoming Requests (Pending) -->
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
                                        <div style="font-size:12px; color:#666;"><?php echo htmlspecialchars($req['fyp_studfullid']); ?></div>
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

            <!-- Part 3: Approved / Upcoming Meetings -->
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
                                        <div style="font-size:12px; color:#666;"><?php echo htmlspecialchars($up['fyp_studfullid']); ?></div>
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
</body>
</html>