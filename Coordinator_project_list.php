<?php
// ====================================================
// Coordinator_project_list.php - 项目与小组管理 (Disband & Adjust)
// ====================================================

include("connect.php");

// 1. 验证用户登录
$auth_user_id = $_GET['auth_user_id'] ?? null;
$current_page = 'project_list'; 

if (!$auth_user_id) { header("location: login.php"); exit; }

// 2. 获取 Coordinator 信息
$user_name = "Coordinator";
$user_avatar = "image/user.png";
if (isset($conn)) {
    $sql_user = "SELECT fyp_username FROM `USER` WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_user)) { $stmt->bind_param("i", $auth_user_id); $stmt->execute(); $res=$stmt->get_result(); if($row=$res->fetch_assoc()) $user_name=$row['fyp_username']; $stmt->close(); }
    
    $sql_coor = "SELECT * FROM coordinator WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_coor)) { $stmt->bind_param("i", $auth_user_id); $stmt->execute(); $res=$stmt->get_result(); if($row=$res->fetch_assoc()) { if(!empty($row['fyp_name'])) $user_name=$row['fyp_name']; if(!empty($row['fyp_profileimg'])) $user_avatar=$row['fyp_profileimg']; } $stmt->close(); }
}

// ====================================================
// 3. 处理 POST 动作 (Disband Group / Toggle Type)
// ====================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Action A: Disband Group
    if (isset($_POST['disband_group_id'])) {
        $gid = $_POST['disband_group_id'];
        
        // 1. 找出所有相关成员，重置状态为 Individual
        // Leader
        $l_sql = "SELECT leader_id FROM student_group WHERE group_id = ?";
        if ($stmt = $conn->prepare($l_sql)) {
            $stmt->bind_param("i", $gid); $stmt->execute(); $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $lid = $row['leader_id'];
                $conn->query("UPDATE student SET fyp_group = 'Individual' WHERE fyp_studid = '$lid'");
            }
        }
        // Members
        $m_sql = "SELECT invitee_id FROM group_request WHERE group_id = ?";
        if ($stmt = $conn->prepare($m_sql)) {
            $stmt->bind_param("i", $gid); $stmt->execute(); $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $mid = $row['invitee_id'];
                $conn->query("UPDATE student SET fyp_group = 'Individual' WHERE fyp_studid = '$mid'");
            }
        }

        // 2. 删除数据
        $conn->query("DELETE FROM group_request WHERE group_id = '$gid'");
        $conn->query("DELETE FROM student_group WHERE group_id = '$gid'");
        
        echo "<script>alert('Group disbanded successfully! Students are now Individual.'); window.location.href='Coordinator_project_list.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
    }

    // Action B: Toggle Project Type
    if (isset($_POST['toggle_project_id'])) {
        $pid = $_POST['toggle_project_id'];
        $current_type = $_POST['current_type'];
        $new_type = ($current_type == 'Group') ? 'Individual' : 'Group';
        
        $conn->query("UPDATE project SET fyp_projecttype = '$new_type' WHERE fyp_projectid = '$pid'");
        echo "<script>alert('Project type changed to $new_type.'); window.location.href='Coordinator_project_list.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
    }
}

// ====================================================
// 4. 获取所有项目及注册情况
// ====================================================
$all_projects = [];
$sql_proj = "SELECT p.*, s.fyp_name as sv_name 
             FROM project p 
             LEFT JOIN supervisor s ON p.fyp_supervisorid = s.fyp_supervisorid 
             ORDER BY p.fyp_datecreated DESC";
$res_proj = $conn->query($sql_proj);

if ($res_proj) {
    while ($row = $res_proj->fetch_assoc()) {
        $pid = $row['fyp_projectid'];
        
        // 获取该项目下的注册学生/小组
        // 我们查找 registration 表
        $regs = [];
        $sql_reg = "SELECT r.*, s.fyp_studname, s.fyp_studid, s.fyp_group 
                    FROM fyp_registration r 
                    JOIN student s ON r.fyp_studid = s.fyp_studid 
                    WHERE r.fyp_projectid = '$pid'";
        $res_reg = $conn->query($sql_reg);
        
        $groups_found = []; // 用于去重显示小组
        $individuals_found = [];

        while ($reg = $res_reg->fetch_assoc()) {
            if ($reg['fyp_group'] == 'Group') {
                // 查找组信息
                $gid = 0;
                $gname = "Unknown Group";
                
                // 查 Leader
                $chk_l = $conn->query("SELECT group_id, group_name FROM student_group WHERE leader_id = '{$reg['fyp_studid']}'");
                if ($l = $chk_l->fetch_assoc()) { $gid = $l['group_id']; $gname = $l['group_name']; }
                else {
                    // 查 Member
                    $chk_m = $conn->query("SELECT sg.group_id, sg.group_name FROM group_request gr JOIN student_group sg ON gr.group_id = sg.group_id WHERE gr.invitee_id = '{$reg['fyp_studid']}' AND gr.request_status = 'Accepted'");
                    if ($m = $chk_m->fetch_assoc()) { $gid = $m['group_id']; $gname = $m['group_name']; }
                }

                if ($gid > 0) {
                    if (!isset($groups_found[$gid])) {
                        $groups_found[$gid] = [
                            'id' => $gid,
                            'name' => $gname,
                            'members' => []
                        ];
                    }
                    $groups_found[$gid]['members'][] = $reg['fyp_studname'];
                }
            } else {
                $individuals_found[] = $reg;
            }
        }
        
        $row['registered_groups'] = $groups_found;
        $row['registered_individuals'] = $individuals_found;
        $all_projects[] = $row;
    }
}

// 5. 菜单定义 (Same as Profile)
$menu_items = [
    'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home', 'link' => 'Coordinator_mainpage.php?page=dashboard'],
    'profile'   => ['name' => 'My Profile', 'icon' => 'fa-user', 'link' => 'Coordinator_profile.php'],
    'management' => [
        'name' => 'User Management',
        'icon' => 'fa-users-cog',
        'sub_items' => [
            'manage_students' => ['name' => 'Student List', 'icon' => 'fa-user-graduate', 'link' => 'Coordinator_mainpage.php?page=manage_students'],
            'manage_supervisors' => ['name' => 'Supervisor List', 'icon' => 'fa-chalkboard-teacher', 'link' => 'Coordinator_mainpage.php?page=manage_supervisors'],
        ]
    ],
    'project_mgmt' => [
        'name' => 'Project Management',
        'icon' => 'fa-project-diagram',
        'sub_items' => [
            'project_list' => ['name' => 'All Projects & Groups', 'icon' => 'fa-list-alt', 'link' => 'Coordinator_project_list.php'],
            'pairing_list' => ['name' => 'Pairing List', 'icon' => 'fa-link', 'link' => 'Coordinator_mainpage.php?page=pairing_list'],
        ]
    ],
    'announcement' => [
        'name' => 'Announcement',
        'icon' => 'fa-bullhorn',
        'sub_items' => [
            'post_announcement' => ['name' => 'Post Announcement', 'icon' => 'fa-pen-square', 'link' => 'Coordinator_announcement.php'], 
            'view_announcements' => ['name' => 'View History', 'icon' => 'fa-history', 'link' => 'Coordinator_mainpage.php?page=view_announcements'],
        ]
    ],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Projects</title>
    <link rel="icon" type="image/png" href="<?php echo $user_avatar; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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
        .submenu { list-style: none; padding: 0; margin: 0; background-color: #fafafa; display: block; }
        .submenu .menu-link { padding-left: 58px; font-size: 14px; padding-top: 10px; padding-bottom: 10px; }
        .main-content { flex: 1; display: flex; flex-direction: column; gap: 20px; }
        
        .welcome-card { background: #fff; padding: 30px; border-radius: 12px; box-shadow: var(--card-shadow); border-left: 5px solid var(--primary-color); }
        .page-title { font-size: 24px; margin: 0 0 10px 0; color: var(--text-color); }

        /* Project List */
        .proj-card { background: #fff; border: 1px solid #eee; border-radius: 8px; padding: 20px; margin-bottom: 15px; display: flex; flex-direction: column; gap: 15px; }
        .proj-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f0f0f0; padding-bottom: 10px; }
        .proj-title { font-size: 18px; font-weight: 600; color: #333; }
        .proj-type { font-size: 12px; padding: 4px 10px; border-radius: 4px; font-weight: 600; text-transform: uppercase; }
        .type-Group { background: #e3effd; color: #0056b3; }
        .type-Individual { background: #f3f3f3; color: #555; }
        
        .sv-name { font-size: 13px; color: #777; margin-bottom: 10px; }
        
        .groups-section { background: #f9f9f9; padding: 15px; border-radius: 6px; }
        .grp-item { display: flex; justify-content: space-between; align-items: center; padding: 10px; background: white; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 5px; }
        .grp-name { font-weight: 600; color: #333; font-size: 14px; }
        .grp-members { font-size: 12px; color: #666; margin-top: 2px; }
        
        .btn-disband { padding: 5px 10px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; }
        .btn-toggle { padding: 5px 10px; background: #ffc107; color: #212529; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; margin-left: 10px; }

        @media (max-width: 900px) { .layout-container { flex-direction: column; } .sidebar { width: 100%; min-height: auto; } }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="logo">FYP System</div>
        <div class="topbar-right">
            <span class="user-name-display"><?php echo htmlspecialchars($user_name); ?></span>
            <span class="user-role-badge">Coordinator</span>
            <div class="user-avatar-circle"><img src="<?php echo $user_avatar; ?>" alt="User"></div>
            <a href="login.php" class="logout-btn">Logout</a>
        </div>
    </header>

    <div class="layout-container">
        <aside class="sidebar">
            <ul class="menu-list">
                <?php foreach ($menu_items as $key => $item): ?>
                    <?php 
                        $isActive = ($key == 'project_mgmt');
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
                <h1 class="page-title">All Projects & Groups</h1>
                <p style="color: #666; margin: 0;">Monitor project allocations and manage student groups.</p>
            </div>

            <?php if (empty($all_projects)): ?>
                <div style="text-align:center; padding:40px; color:#999;">No projects found.</div>
            <?php else: ?>
                <?php foreach ($all_projects as $proj): ?>
                    <div class="proj-card">
                        <div class="proj-header">
                            <div>
                                <div class="proj-title"><?php echo htmlspecialchars($proj['fyp_projecttitle']); ?></div>
                                <div class="sv-name">Supervisor: <?php echo htmlspecialchars($proj['sv_name']); ?></div>
                            </div>
                            <div>
                                <span class="proj-type type-<?php echo $proj['fyp_projecttype']; ?>"><?php echo $proj['fyp_projecttype']; ?></span>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Change project type? This might affect current registrations.');">
                                    <input type="hidden" name="toggle_project_id" value="<?php echo $proj['fyp_projectid']; ?>">
                                    <input type="hidden" name="current_type" value="<?php echo $proj['fyp_projecttype']; ?>">
                                    <button type="submit" class="btn-toggle">Change Type</button>
                                </form>
                            </div>
                        </div>

                        <?php if (!empty($proj['registered_groups'])): ?>
                            <div class="groups-section">
                                <div style="font-size:12px; font-weight:600; color:#555; margin-bottom:5px;">Registered Groups:</div>
                                <?php foreach ($proj['registered_groups'] as $grp): ?>
                                    <div class="grp-item">
                                        <div>
                                            <div class="grp-name"><i class="fa fa-users"></i> <?php echo htmlspecialchars($grp['name']); ?></div>
                                            <div class="grp-members"><?php echo implode(", ", $grp['members']); ?></div>
                                        </div>
                                        <form method="POST" onsubmit="return confirm('WARNING: Disband this group? All members will become Individual.');">
                                            <input type="hidden" name="disband_group_id" value="<?php echo $grp['id']; ?>">
                                            <button type="submit" class="btn-disband">Disband</button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($proj['registered_individuals'])): ?>
                            <div class="groups-section" style="background:#fff; border-top:1px solid #eee;">
                                <div style="font-size:12px; font-weight:600; color:#555; margin-bottom:5px;">Registered Individuals:</div>
                                <ul style="margin:0; padding-left:20px; font-size:13px; color:#333;">
                                    <?php foreach ($proj['registered_individuals'] as $ind): ?>
                                        <li><?php echo htmlspecialchars($ind['fyp_studname']); ?> (<?php echo $ind['fyp_studid']; ?>)</li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if (empty($proj['registered_groups']) && empty($proj['registered_individuals'])): ?>
                            <div style="font-size:13px; color:#999; font-style:italic;">No students registered yet.</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>