<?php
// ====================================================
// Coordinator_meeting.php - 日程管理 (Coordinator 版)
// ====================================================
include("connect.php");
$auth_user_id = $_GET['auth_user_id'] ?? null;
// 手动设置当前页面为 schedule 以高亮菜单
$current_page = 'schedule'; 

if (!$auth_user_id) { header("location: login.php"); exit; }

// --- 获取 Coordinator 信息 ---
$user_name = "Coordinator"; $user_avatar = "image/user.png"; $sv_id = 0; $sv_room = "Office";
if ($stmt = $conn->prepare("SELECT * FROM supervisor WHERE fyp_userid = ?")) {
    $stmt->bind_param("i", $auth_user_id); $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc()) {
        $sv_id = $row['fyp_supervisorid']; $user_name = $row['fyp_name'];
        if($row['fyp_profileimg']) $user_avatar = $row['fyp_profileimg'];
        if($row['fyp_roomno']) $sv_room = $row['fyp_roomno'];
    }
    $stmt->close();
}

// --- 处理表单 ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 发布时间槽
    if (isset($_POST['add_slot'])) {
        $d = $_POST['date']; $st = $_POST['start_time']; $et = $_POST['end_time']; $loc = $_POST['location'] ?: $sv_room;
        $day = date('l', strtotime($d));
        $stmt = $conn->prepare("INSERT INTO schedule_meeting (fyp_supervisorid, fyp_date, fyp_day, fyp_fromtime, fyp_totime, fyp_location, fyp_status) VALUES (?, ?, ?, ?, ?, ?, 'Available')");
        $stmt->bind_param("isssss", $sv_id, $d, $day, $st, $et, $loc);
        $stmt->execute();
        echo "<script>alert('Slot added!'); window.location.href='Coordinator_meeting.php?auth_user_id=$auth_user_id';</script>";
    }
    // 处理预约
    if (isset($_POST['handle_appointment'])) {
        $aid = $_POST['appointment_id']; $sid = $_POST['schedule_id']; $act = $_POST['action_type'];
        if ($act == 'approve') {
            $conn->query("UPDATE appointment_meeting SET fyp_status = 'Approved' WHERE fyp_appointmentid = '$aid'");
        } else {
            $new_st = ($act == 'reject') ? 'Rejected' : 'Cancelled';
            $conn->query("UPDATE appointment_meeting SET fyp_status = '$new_st' WHERE fyp_appointmentid = '$aid'");
            $conn->query("UPDATE schedule_meeting SET fyp_status = 'Available' WHERE fyp_scheduleid = '$sid'"); // 释放时间
        }
        echo "<script>alert('Done.'); window.location.href='Coordinator_meeting.php?auth_user_id=$auth_user_id';</script>";
    }
    // 删除空闲时间
    if (isset($_POST['delete_slot'])) {
        $sid = $_POST['schedule_id'];
        $conn->query("DELETE FROM schedule_meeting WHERE fyp_scheduleid = '$sid' AND fyp_status = 'Available'");
        echo "<script>window.location.href='Coordinator_meeting.php?auth_user_id=$auth_user_id';</script>";
    }
}

// --- 获取数据 ---
$my_slots = []; $pending_req = []; $upcoming = [];
if($sv_id) {
    // Available Slots
    $res = $conn->query("SELECT * FROM schedule_meeting WHERE fyp_supervisorid = '$sv_id' AND fyp_status = 'Available' AND fyp_date >= CURDATE() ORDER BY fyp_date ASC");
    while($r = $res->fetch_assoc()) $my_slots[] = $r;
    // Pending
    $res = $conn->query("SELECT am.*, s.fyp_studname, sm.fyp_date, sm.fyp_fromtime, sm.fyp_totime FROM appointment_meeting am JOIN student s ON am.fyp_studid = s.fyp_studid JOIN schedule_meeting sm ON am.fyp_scheduleid = sm.fyp_scheduleid WHERE am.fyp_supervisorid = '$sv_id' AND am.fyp_status = 'Pending' ORDER BY sm.fyp_date ASC");
    while($r = $res->fetch_assoc()) $pending_req[] = $r;
    // Upcoming
    $res = $conn->query("SELECT am.*, s.fyp_studname, sm.fyp_date, sm.fyp_fromtime, sm.fyp_totime, sm.fyp_location FROM appointment_meeting am JOIN student s ON am.fyp_studid = s.fyp_studid JOIN schedule_meeting sm ON am.fyp_scheduleid = sm.fyp_scheduleid WHERE am.fyp_supervisorid = '$sv_id' AND am.fyp_status = 'Approved' AND sm.fyp_date >= CURDATE() ORDER BY sm.fyp_date ASC");
    while($r = $res->fetch_assoc()) $upcoming[] = $r;
}

// --- Menu ---
$menu_items = [
    'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home', 'link' => 'Coordinator_mainpage.php?page=dashboard'],
    'profile'   => ['name' => 'Profile', 'icon' => 'fa-user', 'link' => 'Coordinator_profile.php'], 
    'project_mgmt' => ['name' => 'Project Mgmt', 'icon' => 'fa-tasks', 'sub_items' => ['propose_project' => ['name' => 'Propose Project', 'icon' => 'fa-plus-circle', 'link' => 'Coordinator_purpose.php'], 'project_requests' => ['name' => 'Project Requests', 'icon' => 'fa-envelope-open-text', 'link' => 'Coordinator_projectreq.php'], 'allocation' => ['name' => 'Auto Allocation', 'icon' => 'fa-bullseye', 'link' => 'Coordinator_mainpage.php?page=allocation']]],
    'assessment' => ['name' => 'Assessment', 'icon' => 'fa-clipboard-check', 'sub_items' => ['propose_assignment' => ['name' => 'Create Assignment', 'icon' => 'fa-plus', 'link' => 'Coordinator_assignment_purpose.php'], 'grade_assignment' => ['name' => 'Grade Assignments', 'icon' => 'fa-check-square', 'link' => 'Coordinator_assignment_grade.php']]],
    'announcements' => ['name' => 'Announcements', 'icon' => 'fa-bullhorn', 'sub_items' => ['post_announcement' => ['name' => 'Post New', 'icon' => 'fa-pen', 'link' => 'Coordinator_announcement.php']]],
    'schedule' => ['name' => 'My Schedule', 'icon' => 'fa-calendar-alt', 'link' => 'Coordinator_meeting.php'], 
    'data_io' => ['name' => 'Data Management', 'icon' => 'fa-database', 'link' => 'Coordinator_mainpage.php?page=data_io'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule - Coordinator</title>
    <link rel="icon" type="image/png" href="<?php echo $user_avatar; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* 复用样式 */
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap');
        :root { --primary-color: #0056b3; --primary-hover: #004494; --secondary-color: #f4f4f9; --text-color: #333; --border-color: #e0e0e0; --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); --sidebar-width: 260px; }
        body { font-family: 'Poppins', sans-serif; margin: 0; background: #eef2f7; color: var(--text-color); min-height: 100vh; display: flex; flex-direction: column; }
        .topbar { display: flex; justify-content: space-between; align-items: center; padding: 15px 40px; background-color: #fff; position: sticky; top: 0; z-index:100; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .logo { font-size: 22px; font-weight: 600; color: var(--primary-color); display: flex; align-items: center; gap: 10px; }
        .topbar-right { display: flex; align-items: center; gap: 20px; }
        .user-name-display { font-weight: 600; font-size: 14px; }
        .user-avatar-circle { width: 40px; height: 40px; border-radius: 50%; overflow: hidden; border: 2px solid #e3effd; margin-left: 10px; }
        .user-avatar-circle img { width: 100%; height: 100%; object-fit: cover; }
        .logout-btn { color: #d93025; text-decoration: none; font-weight: 500; }
        .layout-container { display: flex; flex: 1; max-width: 1400px; margin: 0 auto; width: 100%; padding: 20px; gap: 20px; }
        .sidebar { width: var(--sidebar-width); background: #fff; border-radius: 12px; box-shadow: var(--card-shadow); padding: 20px 0; min-height: calc(100vh - 120px); }
        .menu-list { list-style: none; padding: 0; margin: 0; }
        .menu-link { display: flex; align-items: center; padding: 12px 25px; text-decoration: none; color: #555; transition: all 0.3s; border-left: 4px solid transparent; }
        .menu-link:hover, .menu-link.active { background-color: #e3effd; color: var(--primary-color); border-left-color: var(--primary-color); }
        .menu-icon { width: 24px; margin-right: 10px; text-align: center; }
        .submenu { list-style: none; padding: 0; margin: 0; background-color: #fafafa; display: block; }
        .submenu .menu-link { padding-left: 58px; font-size: 14px; padding-top: 10px; padding-bottom: 10px; }
        .main-content { flex: 1; display: flex; flex-direction: column; gap: 20px; }
        
        /* Schedule Specific */
        .grid-cards { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .s-card { background: #fff; padding: 20px; border-radius: 12px; box-shadow: var(--card-shadow); }
        .btn-add { width: 100%; padding: 10px; background: var(--primary-color); color: white; border: none; border-radius: 6px; cursor: pointer; }
        .slot-item { display: flex; justify-content: space-between; padding: 10px; border-bottom: 1px solid #eee; align-items: center; }
        .btn-sm { padding: 4px 10px; font-size: 12px; border-radius: 4px; border: none; color: white; cursor: pointer; }
        .btn-del { background: #dc3545; } .btn-ok { background: #28a745; }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="logo"><img src="image/ladybug.png" alt="Logo" style="width: 32px;"> FYP System</div>
        <div class="topbar-right">
            <div><span class="user-name-display"><?php echo htmlspecialchars($user_name); ?></span> <span style="font-size:11px; background:#e3effd; color:#0056b3; padding:2px 8px; border-radius:12px;">Coordinator</span></div>
            <div class="user-avatar-circle"><img src="<?php echo $user_avatar; ?>" alt="User"></div>
            <a href="login.php" class="logout-btn"><i class="fa fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>
    <div class="layout-container">
        <aside class="sidebar">
            <ul class="menu-list">
                <?php foreach ($menu_items as $key => $item): 
                    $isActive = ($key == $current_page); 
                    $linkUrl = (isset($item['link']) && $item['link'] !== "#") ? $item['link'] . (strpos($item['link'], '?') !== false ? '&' : '?') . "auth_user_id=" . urlencode($auth_user_id) : "#";
                ?>
                <li class="menu-item">
                    <a href="<?php echo $linkUrl; ?>" class="menu-link <?php echo $isActive ? 'active' : ''; ?>">
                        <span class="menu-icon"><i class="fa <?php echo $item['icon']; ?>"></i></span> <?php echo $item['name']; ?>
                    </a>
                    <?php if (isset($item['sub_items'])): ?>
                        <ul class="submenu">
                        <?php foreach ($item['sub_items'] as $sub_key => $sub_item): 
                             $subLinkUrl = (isset($sub_item['link']) && $sub_item['link'] !== "#") ? $sub_item['link'] . (strpos($sub_item['link'], '?') !== false ? '&' : '?') . "auth_user_id=" . urlencode($auth_user_id) : "#";
                        ?>
                            <li><a href="<?php echo $subLinkUrl; ?>" class="menu-link <?php echo ($sub_key == $current_page) ? 'active' : ''; ?>">
                                <i class="fa <?php echo $sub_item['icon']; ?>" style="margin-right:10px;"></i> <?php echo $sub_item['name']; ?>
                            </a></li>
                        <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </aside>
        <main class="main-content">
            <div class="grid-cards">
                <div class="s-card">
                    <h3>Publish Slots</h3>
                    <form method="POST">
                        <div style="margin-bottom:10px;"><label>Date</label><input type="date" name="date" style="width:100%; padding:8px;" required></div>
                        <div style="display:flex; gap:10px; margin-bottom:10px;">
                            <input type="time" name="start_time" style="flex:1; padding:8px;" required>
                            <input type="time" name="end_time" style="flex:1; padding:8px;" required>
                        </div>
                        <div style="margin-bottom:15px;"><label>Location</label><input type="text" name="location" value="<?php echo htmlspecialchars($sv_room); ?>" style="width:100%; padding:8px;"></div>
                        <button type="submit" name="add_slot" class="btn-add">Add</button>
                    </form>
                    <hr>
                    <h4>My Available Slots</h4>
                    <?php foreach($my_slots as $s): ?>
                        <div class="slot-item">
                            <span><?php echo $s['fyp_date'] . " (" . substr($s['fyp_fromtime'],0,5) . "-" . substr($s['fyp_totime'],0,5) . ")"; ?></span>
                            <form method="POST"><input type="hidden" name="schedule_id" value="<?php echo $s['fyp_scheduleid']; ?>"><button type="submit" name="delete_slot" class="btn-sm btn-del">Del</button></form>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div style="display:flex; flex-direction:column; gap:20px;">
                    <div class="s-card" style="border-left:4px solid #f0ad4e;">
                        <h3>Pending Requests</h3>
                        <?php foreach($pending_req as $p): ?>
                            <div class="slot-item">
                                <div>
                                    <strong><?php echo htmlspecialchars($p['fyp_studname']); ?></strong><br>
                                    <small><?php echo $p['fyp_date'] . " " . substr($p['fyp_fromtime'],0,5); ?></small>
                                </div>
                                <form method="POST">
                                    <input type="hidden" name="appointment_id" value="<?php echo $p['fyp_appointmentid']; ?>">
                                    <input type="hidden" name="schedule_id" value="<?php echo $p['fyp_scheduleid']; ?>">
                                    <input type="hidden" name="action_type" id="act_<?php echo $p['fyp_appointmentid']; ?>">
                                    <button type="submit" name="handle_appointment" onclick="document.getElementById('act_<?php echo $p['fyp_appointmentid']; ?>').value='approve'" class="btn-sm btn-ok">Acc</button>
                                    <button type="submit" name="handle_appointment" onclick="document.getElementById('act_<?php echo $p['fyp_appointmentid']; ?>').value='reject'" class="btn-sm btn-del">Rej</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                        <?php if(empty($pending_req)) echo "<small style='color:#999'>No pending requests.</small>"; ?>
                    </div>

                    <div class="s-card" style="border-left:4px solid #28a745;">
                        <h3>Upcoming Meetings</h3>
                        <?php foreach($upcoming as $u): ?>
                            <div class="slot-item">
                                <div>
                                    <strong><?php echo $u['fyp_date']; ?></strong> <?php echo substr($u['fyp_fromtime'],0,5); ?><br>
                                    Student: <?php echo htmlspecialchars($u['fyp_studname']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if(empty($upcoming)) echo "<small style='color:#999'>No upcoming meetings.</small>"; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>