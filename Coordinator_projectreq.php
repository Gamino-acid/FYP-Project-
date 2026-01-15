<?php
// ====================================================
// Coordinator_projectreq.php - 管理项目申请
// ====================================================
include("connect.php");
$auth_user_id = $_GET['auth_user_id'] ?? null;
$current_page = 'project_requests';
if (!$auth_user_id) { header("location: login.php"); exit; }

// --- 批准/拒绝逻辑 (代码逻辑与 Supervisor 相同) ---
if (isset($_GET['action']) && isset($_GET['req_id'])) {
    $req_id = $_GET['req_id']; $action = $_GET['action'];
    if ($action == 'Reject') {
        $conn->query("UPDATE project_request SET fyp_requeststatus = 'Reject' WHERE fyp_requestid = $req_id");
        echo "<script>alert('Rejected.'); window.location.href='Coordinator_projectreq.php?auth_user_id=$auth_user_id';</script>";
    } elseif ($action == 'Approve') {
        // 这里简化了逻辑，完整版应包含 Quota 检查
        $req_row = $conn->query("SELECT fyp_studid, fyp_projectid, fyp_staffid FROM project_request WHERE fyp_requestid = $req_id")->fetch_assoc();
        if ($req_row) {
            $sid = $req_row['fyp_studid']; $pid = $req_row['fyp_projectid']; $svid = $req_row['fyp_staffid'];
            $conn->query("INSERT INTO fyp_registration (fyp_studid, fyp_projectid, fyp_staffid, fyp_datecreated) VALUES ('$sid', '$pid', '$svid', NOW())");
            $conn->query("UPDATE project_request SET fyp_requeststatus = 'Approve' WHERE fyp_requestid = $req_id");
            $conn->query("UPDATE project SET fyp_projectstatus = 'Taken' WHERE fyp_projectid = $pid");
            echo "<script>alert('Approved!'); window.location.href='Coordinator_projectreq.php?auth_user_id=$auth_user_id';</script>";
        }
    }
}

// --- 获取数据 ---
$user_name = "Coordinator"; $user_avatar = "image/user.png"; $sv_id = 0;
// 获取 Coordinator 资料
if ($stmt = $conn->prepare("SELECT * FROM coordinator WHERE fyp_userid = ?")) {
    $stmt->bind_param("s", $auth_user_id); $stmt->execute(); $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) { $sv_id = $row['fyp_staffid']; $user_name = $row['fyp_name']; if($row['fyp_profileimg']) $user_avatar=$row['fyp_profileimg']; }
    $stmt->close();
}
// 获取 Request 列表
$requests = [];
if ($sv_id) {
    $res = $conn->query("SELECT r.*, s.fyp_studname, s.fyp_studid, p.fyp_projecttitle FROM project_request r JOIN student s ON r.fyp_studid = s.fyp_studid JOIN project p ON r.fyp_projectid = p.fyp_projectid WHERE r.fyp_staffid = '$sv_id' ORDER BY r.fyp_datecreated DESC");
    if($res) while($row=$res->fetch_assoc()) $requests[] = $row;
}

// 菜单 (一致)
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
    <title>Requests - Coordinator</title>
    <link rel="icon" type="image/png" href="<?php echo $user_avatar; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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
        
        .req-card { background: #fff; padding: 20px; border-radius: 10px; margin-bottom: 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.02); border-left: 4px solid #0056b3; display: flex; justify-content: space-between; align-items: center; }
        .status-badge { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
        .status-Pending { background: #fff3cd; color: #856404; }
        .status-Approve { background: #d4edda; color: #155724; }
        .status-Reject { background: #f8d7da; color: #721c24; }
        .btn-act { padding: 6px 15px; border-radius: 4px; color: white; text-decoration: none; font-size: 13px; margin-left: 5px; }
        .btn-app { background: #28a745; } .btn-rej { background: #dc3545; }
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
                    $isActive = ($key == 'project_mgmt'); 
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
            <div style="background: #fff; padding: 20px; border-radius: 12px; margin-bottom: 20px; box-shadow: var(--card-shadow);">
                <h2>Requests Management</h2>
            </div>
            <?php if (count($requests) > 0): foreach ($requests as $req): ?>
                <div class="req-card">
                    <div>
                        <span class="status-badge status-<?php echo $req['fyp_requeststatus']; ?>"><?php echo $req['fyp_requeststatus']; ?></span>
                        <h3 style="margin: 5px 0;"><?php echo htmlspecialchars($req['fyp_studname']); ?></h3>
                        <p style="margin: 0; color: #666; font-size: 14px;">Project: <?php echo htmlspecialchars($req['fyp_projecttitle']); ?></p>
                        <small style="color: #999;">Date: <?php echo $req['fyp_datecreated']; ?></small>
                    </div>
                    <?php if ($req['fyp_requeststatus'] == 'Pending'): ?>
                        <div>
                            <a href="?action=Approve&req_id=<?php echo $req['fyp_requestid']; ?>&auth_user_id=<?php echo $auth_user_id; ?>" class="btn-act btn-app">Accept</a>
                            <a href="?action=Reject&req_id=<?php echo $req['fyp_requestid']; ?>&auth_user_id=<?php echo $auth_user_id; ?>" class="btn-act btn-rej">Reject</a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; else: ?>
                <div style="text-align:center; padding:50px; color:#999;">No requests found.</div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>