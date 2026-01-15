<?php
// ====================================================
// std_team.php - Team Invitations
// ====================================================
include("connect.php");

// 1. 基础验证
$auth_user_id = $_GET['auth_user_id'] ?? null;
if (!$auth_user_id) { header("location: login.php"); exit; }

// 2. 获取当前学生信息 (用于 Topbar 和逻辑)
$stud_data = [];
$user_name = 'Student';
if (isset($conn)) {
    $sql_user = "SELECT fyp_username FROM `USER` WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_user)) { $stmt->bind_param("i", $auth_user_id); $stmt->execute(); $res=$stmt->get_result(); if($row=$res->fetch_assoc()) $user_name=$row['fyp_username']; $stmt->close(); }
    
    $sql_stud = "SELECT * FROM STUDENT WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_stud)) { $stmt->bind_param("i", $auth_user_id); $stmt->execute(); $res=$stmt->get_result(); if($row=$res->fetch_assoc()){ $stud_data=$row; if(!empty($row['fyp_studname'])) $user_name=$row['fyp_studname']; } else { $stud_data=['fyp_studid'=>'', 'fyp_studname'=>$user_name, 'fyp_studfullid'=>'', 'fyp_projectid'=>null, 'fyp_profileimg'=>'']; } $stmt->close(); }
}

// 3. 逻辑处理 (接受/拒绝邀请)
if (isset($_GET['action']) && in_array($_GET['action'], ['Accept', 'Reject'])) {
    $target_stud_id = $_GET['sid']; 
    $target_pairing_id = $_GET['pid'];
    $response = $_GET['action'];
    
    // 更新 student_group 状态
    $upd_grp = "UPDATE student_group SET fyp_acceptance = ? WHERE fyp_studid = ? AND fyp_pairingid = ?";
    if ($stmt = $conn->prepare($upd_grp)) {
        $stmt->bind_param("ssi", $response, $target_stud_id, $target_pairing_id);
        $stmt->execute();
        $stmt->close();
    }
    
    // 如果接受，正式注册进 Registration 表并更新 Student 表
    if ($response == 'Accept') {
        $proj_id = 0;
        $get_p = "SELECT fyp_projectid FROM pairing WHERE fyp_pairingid = ? LIMIT 1";
        if ($stmt = $conn->prepare($get_p)) {
            $stmt->bind_param("i", $target_pairing_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) $proj_id = $row['fyp_projectid'];
            $stmt->close();
        }
        
        if ($proj_id > 0) {
            $date_now = date('Y-m-d H:i:s');
            // 插入注册表
            $conn->query("INSERT INTO fyp_registration (fyp_studid, fyp_projectid, fyp_pairingid, fyp_datecreated) VALUES ('$target_stud_id', $proj_id, $target_pairing_id, '$date_now')");
            // 更新学生当前项目
            $conn->query("UPDATE STUDENT SET fyp_projectid = $proj_id WHERE fyp_studid = '$target_stud_id'");
        }
    }
    echo "<script>alert('Invitation " . $response . "ed!'); window.location.href='std_team.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
}

// 4. 数据查询 - 获取我的邀请函
$my_invitations = [];
if (!empty($stud_data['fyp_studid'])) {
    $my_sid = $stud_data['fyp_studid'];
    $sql_inv = "SELECT sg.*, p.fyp_projecttitle, p.fyp_projecttype, s.fyp_name as sv_name
                FROM student_group sg
                JOIN pairing pa ON sg.fyp_pairingid = pa.fyp_pairingid
                JOIN project p ON pa.fyp_projectid = p.fyp_projectid
                JOIN supervisor s ON pa.fyp_supervisorid = s.fyp_supervisorid
                WHERE sg.fyp_studid = ? ORDER BY sg.fyp_datecreated DESC";
    if ($stmt = $conn->prepare($sql_inv)) {
        $stmt->bind_param("s", $my_sid);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $my_invitations[] = $row;
        $stmt->close();
    }
}

// 5. 定义菜单
$current_page = 'team_invitations';
$menu_items = [
    'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home', 'link' => 'Student_mainpage.php?page=dashboard'],
    'profile' => ['name' => 'My Profile', 'icon' => 'fa-user', 'link' => 'std_profile.php'],
    'project_mgmt' => [
        'name' => 'Final Year Project',
        'icon' => 'fa-project-diagram',
        'sub_items' => [
            'group_setup' => ['name' => 'Project Registration', 'icon' => 'fa-users', 'link' => 'std_projectreg.php'], 
            'team_invitations' => ['name' => 'Team Invitations', 'icon' => 'fa-envelope-open-text', 'link' => 'std_team.php'], // 指向自己
            'proposals' => ['name' => 'Proposal Submission', 'icon' => 'fa-file-alt', 'link' => 'Student_mainpage.php?page=proposals'],
            'doc_submission' => ['name' => 'Document Upload', 'icon' => 'fa-cloud-upload-alt', 'link' => 'Student_mainpage.php?page=doc_submission'],
        ]
    ],
    'appointments' => ['name' => 'Appointment', 'icon' => 'fa-calendar-check', 'sub_items' => ['book_session' => ['name' => 'Make Appointment', 'icon' => 'fa-plus-circle', 'link' => 'Student_mainpage.php?page=appointments']]],
    'grades' => ['name' => 'My Grades', 'icon' => 'fa-star', 'link' => 'Student_mainpage.php?page=grades'],
    'announcements' => ['name' => 'Announcements', 'icon' => 'fa-bullhorn', 'link' => 'Student_mainpage.php?page=announcements'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Invitations</title>
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
        .invitation-card { background: #fff; border: 1px solid #e0e0e0; border-radius: 10px; padding: 20px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; transition: transform 0.2s; border-left: 5px solid #ffc107; }
        .invitation-card:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .invite-info h3 { margin: 0 0 5px 0; font-size: 18px; color: #333; }
        .invite-info p { margin: 0; color: #666; font-size: 14px; }
        .invite-actions { display: flex; gap: 10px; }
        .btn-accept, .btn-reject, .btn-details { color: white; border: none; padding: 8px 15px; border-radius: 6px; cursor: pointer; font-size: 13px; display: flex; align-items: center; gap: 5px; }
        .btn-accept { background-color: #28a745; }
        .btn-reject { background-color: #dc3545; }
        .btn-details { background-color: #17a2b8; }
        .status-badge { padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-accept { background: #d4edda; color: #155724; }
        .badge-reject { background: #f8d7da; color: #721c24; }
        .empty-state { text-align: center; padding: 40px; color: #999; }
        /* Modal */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; backdrop-filter: blur(2px); justify-content: center; align-items: center; opacity: 0; transition: opacity 0.3s ease; }
        .modal-overlay.show { display: flex; opacity: 1; }
        .modal-box { background: #fff; padding: 35px; border-radius: 12px; width: 90%; max-width: 450px; text-align: center; box-shadow: 0 15px 35px rgba(0,0,0,0.2); transform: scale(0.9); transition: transform 0.3s ease; }
        .modal-overlay.show .modal-box { transform: scale(1); }
        .modal-title { font-size: 20px; font-weight: 600; margin-bottom: 10px; color: #333; }
        .modal-actions { display: flex; gap: 15px; justify-content: center; margin-top:20px; }
        .btn-cancel { background: #f1f1f1; color: #444; padding: 12px 25px; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; font-size: 15px; transition: background 0.2s; }
        .btn-cancel:hover { background: #e0e0e0; }
        @media (max-width: 900px) { .layout-container { flex-direction: column; } .sidebar { width: 100%; min-height: auto; } }
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
            <div class="user-avatar-circle">
                <img src="<?php echo $favicon; ?>" alt="User Avatar">
            </div>
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
                <h1 class="page-title">Team Invitations</h1>
                <p style="color: #666; margin: 0;">View and manage your team invitations.</p>
            </div>

            <?php if (count($my_invitations) > 0): ?>
                <?php foreach ($my_invitations as $inv): 
                    $badgeClass = 'badge-pending';
                    if($inv['fyp_acceptance'] == 'Accept') $badgeClass = 'badge-accept';
                    if($inv['fyp_acceptance'] == 'Reject') $badgeClass = 'badge-reject';
                ?>
                    <div class="invitation-card" style="border-left-color: <?php echo ($inv['fyp_acceptance'] == 'Accept' ? '#28a745' : ($inv['fyp_acceptance'] == 'Reject' ? '#dc3545' : '#ffc107')); ?>">
                        <div class="invite-info">
                            <h3>Team Invitation</h3>
                            <p>Project: <strong><?php echo htmlspecialchars($inv['fyp_projecttitle']); ?></strong> (<?php echo htmlspecialchars($inv['fyp_projecttype']); ?>)</p>
                            <p>Supervisor: <?php echo htmlspecialchars($inv['sv_name']); ?></p>
                            <span class="status-badge <?php echo $badgeClass; ?>"><?php echo $inv['fyp_acceptance']; ?></span>
                        </div>
                        <div class="invite-actions">
                            <button class="btn-details" onclick="showInviteDetails('<?php echo addslashes($inv['fyp_projecttitle']); ?>', '<?php echo addslashes($inv['sv_name']); ?>')"><i class="fa fa-info-circle"></i> More Details</button>
                            <?php if ($inv['fyp_acceptance'] == 'Pending'): ?>
                                <a href="?action=Accept&sid=<?php echo $inv['fyp_studid']; ?>&pid=<?php echo $inv['fyp_pairingid']; ?>&auth_user_id=<?php echo $auth_user_id; ?>" class="btn-accept"><i class="fa fa-check"></i> Accept</a>
                                <a href="?action=Reject&sid=<?php echo $inv['fyp_studid']; ?>&pid=<?php echo $inv['fyp_pairingid']; ?>&auth_user_id=<?php echo $auth_user_id; ?>" class="btn-reject"><i class="fa fa-times"></i> Reject</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state"><i class="fa fa-envelope-open" style="font-size: 48px; opacity:0.3;"></i><p>No invitations found.</p></div>
            <?php endif; ?>

        </main>
    </div>

    <div id="inviteModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-title">Invitation Details</div>
            <p style="text-align:left; margin-top:15px;"><strong>Project:</strong> <span id="invProjTitle"></span></p>
            <p style="text-align:left;"><strong>Supervisor:</strong> <span id="invSvName"></span></p>
            <p style="font-size:13px; color:#666; margin-top:20px;">Note: Accepting this invitation will register you for this project group.</p>
            <div class="modal-actions"><button type="button" class="btn-cancel" onclick="document.getElementById('inviteModal').classList.remove('show')">Close</button></div>
        </div>
    </div>

    <script>
        function showInviteDetails(title, sv) {
            document.getElementById('invProjTitle').innerText = title;
            document.getElementById('invSvName').innerText = sv;
            document.getElementById('inviteModal').classList.add('show');
        }
        window.onclick = function(e) { if (e.target.classList.contains('modal-overlay')) e.target.classList.remove('show'); }
    </script>
</body>
</html>