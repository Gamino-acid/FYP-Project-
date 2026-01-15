<?php
// ====================================================
// Coordinator_allocation.php - UI Adapted to Mainpage
// ====================================================
include("connect.php");

$auth_user_id = $_GET['auth_user_id'] ?? null;
$current_page = 'allocation'; // 用于 Sidebar 高亮

if (!$auth_user_id) { header("location: login.php"); exit; }

// 1. 获取当前 Coordinator 信息 (用于 Topbar)
$user_name = "Coordinator"; 
$user_avatar = "image/user.png"; 

if (isset($conn)) {
    // 尝试从 USER 表获取名字 (保持统一)
    $sql_user = "SELECT fyp_username FROM `USER` WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_user)) {
        $stmt->bind_param("i", $auth_user_id); $stmt->execute();
        $res = $stmt->get_result(); if ($row = $res->fetch_assoc()) $user_name = $row['fyp_username'];
        $stmt->close();
    }
    
    $stmt = $conn->prepare("SELECT fyp_name, fyp_profileimg FROM coordinator WHERE fyp_userid = ?");
    $stmt->bind_param("i", $auth_user_id); $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        if(!empty($row['fyp_name'])) $user_name = $row['fyp_name'];
        if(!empty($row['fyp_profileimg'])) $user_avatar = $row['fyp_profileimg'];
    }
    $stmt->close();
}

// 2. 获取所有可用 Moderator (Supervisor + Coordinator)
$moderators = [];
$sql_mods = "SELECT fyp_staffid, fyp_name, 'Supervisor' as role FROM supervisor WHERE fyp_ismoderator = 1 AND fyp_staffid IS NOT NULL
             UNION
             SELECT fyp_staffid, fyp_name, 'Coordinator' as role FROM coordinator WHERE fyp_staffid IS NOT NULL";

$res_m = $conn->query($sql_mods);
if ($res_m) {
    while($m = $res_m->fetch_assoc()) {
        $moderators[$m['fyp_staffid']] = $m;
    }
}

// ====================================================
// 3. 处理 POST 请求
// ====================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // A. 手动保存
    if (isset($_POST['save_allocation'])) {
        $proj_id = $_POST['project_id'];
        $tgt_type = $_POST['target_type'];
        $tgt_id = $_POST['target_id'];
        $mod_id = $_POST['moderator_id'];
        $venue = $_POST['venue'];
        $datetime = $_POST['presentation_date'];
        
        $sql = "INSERT INTO schedule_presentation (project_id, target_type, target_id, moderator_id, venue, presentation_date) 
                VALUES (?, ?, ?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE moderator_id = VALUES(moderator_id), venue = VALUES(venue), presentation_date = VALUES(presentation_date)";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("isssss", $proj_id, $tgt_type, $tgt_id, $mod_id, $venue, $datetime);
            $stmt->execute();
            echo "<script>alert('Schedule updated successfully!'); window.location.href='Coordinator_allocation.php?auth_user_id=$auth_user_id';</script>";
        }
    }

    // B. 自动分配
    if (isset($_POST['auto_allocate'])) {
        if (count($moderators) > 0) {
            $sql_pend = "SELECT p.fyp_projectid, p.fyp_projecttype, 
                                r.fyp_staffid as supervisor_id,
                                CASE 
                                    WHEN p.fyp_projecttype = 'Group' THEN (SELECT group_id FROM student_group WHERE leader_id = r.fyp_studid LIMIT 1)
                                    ELSE r.fyp_studid 
                                END as target_id
                         FROM fyp_registration r
                         JOIN project p ON r.fyp_projectid = p.fyp_projectid
                         LEFT JOIN schedule_presentation ps ON (
                             (p.fyp_projecttype = 'Group' AND ps.target_id = (SELECT group_id FROM student_group WHERE leader_id = r.fyp_studid LIMIT 1))
                             OR 
                             (p.fyp_projecttype = 'Individual' AND ps.target_id = r.fyp_studid)
                         )
                         WHERE (ps.moderator_id IS NULL OR ps.moderator_id = '') 
                         AND (r.fyp_archive_status = 'Active' OR r.fyp_archive_status IS NULL)
                         GROUP BY target_id";

            $res_p = $conn->query($sql_pend);
            $count_assigned = 0;
            $mod_list = array_keys($moderators);
            $mod_count = count($mod_list);
            $mod_index = 0;

            while ($proj = $res_p->fetch_assoc()) {
                if (empty($proj['target_id'])) continue;
                $assigned_mod = null;
                $attempts = 0;
                while ($attempts < $mod_count) {
                    $candidate_id = $mod_list[$mod_index % $mod_count];
                    $mod_index++; $attempts++;
                    if ($candidate_id != $proj['supervisor_id']) {
                        $assigned_mod = $candidate_id; break;
                    }
                }
                if ($assigned_mod) {
                    $ins_sql = "INSERT INTO schedule_presentation (project_id, target_type, target_id, moderator_id) 
                                VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE moderator_id = VALUES(moderator_id)";
                    $stmt = $conn->prepare($ins_sql);
                    $stmt->bind_param("isss", $proj['fyp_projectid'], $proj['fyp_projecttype'], $proj['target_id'], $assigned_mod);
                    $stmt->execute();
                    $count_assigned++;
                }
            }
            echo "<script>alert('Auto allocation complete! Assigned $count_assigned projects.'); window.location.href='Coordinator_allocation.php?auth_user_id=$auth_user_id';</script>";
        } else {
            echo "<script>alert('Error: No active Moderators found.');</script>";
        }
    }
}

// 4. 获取显示列表
$allocations = [];
$sql_view = "SELECT 
                p.fyp_projecttitle, p.fyp_projecttype, p.fyp_projectid,
                r.fyp_studid, s.fyp_studname,
                COALESCE(sup.fyp_name, coor.fyp_name, r.fyp_staffid) as supervisor_name,
                r.fyp_staffid as supervisor_staff_id,
                sched.moderator_id, sched.venue, sched.presentation_date,
                COALESCE(mod_sup.fyp_name, mod_coor.fyp_name, sched.moderator_id) as moderator_name
             FROM fyp_registration r
             JOIN project p ON r.fyp_projectid = p.fyp_projectid
             JOIN student s ON r.fyp_studid = s.fyp_studid
             LEFT JOIN supervisor sup ON r.fyp_staffid = sup.fyp_staffid
             LEFT JOIN coordinator coor ON r.fyp_staffid = coor.fyp_staffid
             LEFT JOIN student_group sg ON (p.fyp_projecttype = 'Group' AND sg.leader_id = r.fyp_studid)
             LEFT JOIN schedule_presentation sched ON (
                (p.fyp_projecttype = 'Group' AND sched.target_id = sg.group_id) OR
                (p.fyp_projecttype = 'Individual' AND sched.target_id = r.fyp_studid)
             )
             LEFT JOIN supervisor mod_sup ON sched.moderator_id = mod_sup.fyp_staffid
             LEFT JOIN coordinator mod_coor ON sched.moderator_id = mod_coor.fyp_staffid
             WHERE (p.fyp_projecttype = 'Individual') OR (p.fyp_projecttype = 'Group' AND sg.group_id IS NOT NULL)
             ORDER BY p.fyp_projecttitle ASC";

$res = $conn->query($sql_view);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        if ($row['fyp_projecttype'] == 'Group') {
             $g_res = $conn->query("SELECT group_id, group_name FROM student_group WHERE leader_id = '{$row['fyp_studid']}'");
             if ($g_r = $g_res->fetch_assoc()) {
                 $row['target_id'] = $g_r['group_id'];
                 $row['display_name'] = "Group: " . $g_r['group_name'];
             }
        } else {
            $row['target_id'] = $row['fyp_studid'];
            $row['display_name'] = "Student: " . $row['fyp_studname'];
        }
        $allocations[] = $row;
    }
}

// 5. 菜单定义 (与 Mainpage 保持一致)
$menu_items = [
    'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home', 'link' => 'Coordinator_mainpage.php?page=dashboard'],
    'profile'   => ['name' => 'My Profile', 'icon' => 'fa-user', 'link' => 'Coordinator_profile.php'], 
    
     'management' => [
        'name' => 'User Management',
        'icon' => 'fa-users-cog',
        'sub_items' => [
            'manage_students' => ['name' => 'Student List', 'icon' => 'fa-user-graduate', 'link' => 'Coordinator_manage_users.php?tab=student'],
            'manage_supervisors' => ['name' => 'Supervisor List', 'icon' => 'fa-chalkboard-teacher', 'link' => 'Coordinator_manage_users.php?tab=supervisor'],
            'manage_quota' => ['name' => 'Supervisor Quota', 'icon' => 'fa-chalkboard-teacher', 'link' => 'Coordinator_manage_quota.php'],
        ]
    ],
    'project_mgmt' => [
        'name' => 'Project Mgmt', 
        'icon' => 'fa-tasks', 
        'sub_items' => [
            'propose_project' => ['name' => 'Propose Project', 'icon' => 'fa-plus-circle', 'link' => 'Coordinator_purpose.php'],
            'project_requests' => ['name' => 'Project Requests', 'icon' => 'fa-envelope-open-text', 'link' => 'Coordinator_projectreq.php'],
            'project_list' => ['name' => 'All Projects & Groups', 'icon' => 'fa-list-alt', 'link' => 'Coordinator_manage_project.php'],
        ]
    ],
    'assessment' => [
        'name' => 'Assessment', 
        'icon' => 'fa-clipboard-check', 
        'sub_items' => [
            'propose_assignment' => ['name' => 'Create Assignment', 'icon' => 'fa-plus', 'link' => 'Coordinator_assignment_purpose.php'],
            'grade_assignment' => ['name' => 'Grade Assignments', 'icon' => 'fa-check-square', 'link' => 'Coordinator_assignment_grade.php'],
            'grade_mod' => ['name' => 'Moderator Grading', 'icon' => 'fa-gavel', 'link' => 'Moderator_assignment_grade_coordinator.php'], 
        ]
    ],
    'announcements' => [
        'name' => 'Announcements', 
        'icon' => 'fa-bullhorn', 
        'sub_items' => [
            'post_announcement' => ['name' => 'Post New', 'icon' => 'fa-pen', 'link' => 'Coordinator_announcement.php'], 
            'view_announcements' => ['name' => 'View History', 'icon' => 'fa-history', 'link' => 'Coordinator_announcement_view.php'],
        ]
    ],
    'schedule' => ['name' => 'My Schedule', 'icon' => 'fa-calendar-alt', 'link' => 'Coordinator_meeting.php'], 
    'allocation' => ['name' => 'Moderator & Schedule Management', 'icon' => 'fa-database', 'link' => 'Coordinator_allocation.php'],
    'reports' => ['name' => 'System Reports', 'icon' => 'fa-chart-bar', 'link' => 'Coordinator_history.php'],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moderator Allocation</title>
    <link rel="icon" type="image/png" href="<?php echo $user_avatar; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* 复用 Coordinator Mainpage 的 CSS */
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap');
        :root { --primary-color: #0056b3; --primary-hover: #004494; --secondary-color: #f4f4f9; --text-color: #333; --border-color: #e0e0e0; --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); --gradient-start: #eef2f7; --gradient-end: #ffffff; --sidebar-width: 260px; --student-accent: #007bff; }
        body { font-family: 'Poppins', sans-serif; margin: 0; background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end)); color: var(--text-color); min-height: 100vh; display: flex; flex-direction: column; }
        
        /* Topbar & Sidebar Styles */
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
        .submenu { list-style: none; padding: 0; margin: 0; background-color: #fafafa; display: block; }
        .submenu .menu-link { padding-left: 58px; font-size: 14px; padding-top: 10px; padding-bottom: 10px; }

        .main-content { flex: 1; display: flex; flex-direction: column; gap: 20px; }
        
        /* Content Specific Styles */
        .page-header-card { background: #fff; padding: 25px; border-radius: 12px; box-shadow: var(--card-shadow); border-left: 5px solid var(--primary-color); display: flex; justify-content: space-between; align-items: center; }
        .page-title { font-size: 22px; font-weight: 600; color: var(--text-color); margin: 0; }
        
        .card { background: #fff; padding: 25px; border-radius: 12px; box-shadow: var(--card-shadow); }

        /* Table Styles (Matching Mainpage) */
        .data-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; margin-top: 10px; }
        .data-table th { background: #f8f9fa; text-align: left; padding: 12px 15px; color: #555; font-size: 13px; font-weight: 600; border-bottom: 2px solid #eee; }
        .data-table td { padding: 12px 15px; border-bottom: 1px solid #f0f0f0; font-size: 14px; color: #333; vertical-align: middle; }
        .data-table tr:last-child td { border-bottom: none; }

        /* Allocation Specific Badges & Buttons */
        .badge { padding: 4px 10px; border-radius: 15px; font-size: 12px; font-weight: 600; display: inline-block; }
        .badge-gray { background: #e9ecef; color: #666; }
        .badge-purple { background: #f3e5f5; color: #7b1fa2; border: 1px solid #e1bee7; }
        .badge-green { background: #e8f5e9; color: #2e7d32; }
        
        .btn-auto { background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 500; display: flex; align-items: center; gap: 8px; box-shadow: 0 2px 5px rgba(40,167,69,0.3); transition: 0.2s; }
        .btn-auto:hover { background: #218838; transform: translateY(-2px); }
        
        .btn-edit { background: white; color: var(--primary-color); border: 1px solid var(--primary-color); padding: 6px 15px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 500; transition: 0.2s; }
        .btn-edit:hover { background: var(--primary-color); color: white; }
        
        /* Modal Styles */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1100; justify-content: center; align-items: center; }
        .modal-box { background: #fff; padding: 30px; border-radius: 12px; width: 500px; max-width: 90%; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; margin-bottom: 8px; font-weight: 500; color: #444; font-size: 14px; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; box-sizing: border-box; font-family: inherit; }
        .form-control:focus { outline: none; border-color: var(--primary-color); }
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 25px; }

        @media (max-width: 900px) { .layout-container { flex-direction: column; } .sidebar { width: 100%; min-height: auto; } }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="logo"><img src="image/ladybug.png" alt="Logo" style="width: 32px; margin-right: 10px;"> FYP System</div>
        <div class="topbar-right">
            <div class="user-profile-summary">
                <span class="user-name-display"><?php echo htmlspecialchars($user_name); ?></span>
                <span class="user-role-badge">Coordinator</span>
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
                    <li class="menu-item">
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
            <div class="page-header-card">
                <div>
                    <h1 class="page-title"><i class="fa fa-database"></i> Moderator & Schedule</h1>
                    <p style="color: #666; margin: 5px 0 0; font-size:14px;">Assign moderators, set venues and times for presentations.</p>
                </div>
                <form method="POST" onsubmit="return confirm('Auto Allocate? This will assign moderators to all pending slots randomly.');">
                    <button type="submit" name="auto_allocate" class="btn-auto">
                        <i class="fa fa-magic"></i> Auto Allocate
                    </button>
                </form>
            </div>
            
            <div class="card">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Project / Target</th>
                            <th>Supervisor</th>
                            <th>Assigned Moderator</th>
                            <th>Venue & Time</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($allocations) > 0): ?>
                            <?php foreach($allocations as $row): 
                                $hasMod = !empty($row['moderator_name']);
                                $hasTime = !empty($row['presentation_date']);
                            ?>
                            <tr>
                                <td>
                                    <div style="font-weight:600; color:#333;"><?php echo htmlspecialchars($row['fyp_projecttitle']); ?></div>
                                    <div style="font-size:12px; color:#888; margin-top:4px;">
                                        <span class="badge badge-gray"><?php echo $row['fyp_projecttype']; ?></span> 
                                        <?php echo htmlspecialchars($row['display_name']); ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($row['supervisor_name']); ?></td>
                                <td>
                                    <?php if($hasMod): ?>
                                        <span class="badge badge-purple"><i class="fa fa-user-tie"></i> <?php echo htmlspecialchars($row['moderator_name']); ?></span>
                                    <?php else: ?>
                                        <span style="color:#bbb; font-size:13px; font-style:italic;">-- Unassigned --</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($hasTime): ?>
                                        <div style="font-size:13px; font-weight:500;"><i class="fa fa-calendar-alt" style="color:#666; width:15px;"></i> <?php echo date('d M Y, h:i A', strtotime($row['presentation_date'])); ?></div>
                                        <div style="font-size:12px; color:#666; margin-top:3px;"><i class="fa fa-map-marker-alt" style="color:#666; width:15px;"></i> <?php echo htmlspecialchars($row['venue']); ?></div>
                                    <?php else: ?>
                                        <span style="color:#bbb; font-size:13px;">-- Not Scheduled --</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn-edit" onclick="openModal(
                                        '<?php echo $row['fyp_projectid']; ?>',
                                        '<?php echo $row['fyp_projecttype']; ?>',
                                        '<?php echo $row['target_id']; ?>',
                                        '<?php echo htmlspecialchars($row['display_name']); ?>',
                                        '<?php echo $row['moderator_id']; ?>',
                                        '<?php echo $row['venue']; ?>',
                                        '<?php echo $row['presentation_date'] ? date('Y-m-d\TH:i', strtotime($row['presentation_date'])) : ''; ?>',
                                        '<?php echo $row['supervisor_staff_id']; ?>'
                                    )">
                                        Edit Details
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align:center; padding:30px; color:#999;">No active projects available for allocation.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <div id="allocationModal" class="modal-overlay">
        <div class="modal-box">
            <h3 style="margin-top:0; color: var(--primary-color); border-bottom:1px solid #eee; padding-bottom:15px;">
                <i class="fa fa-edit"></i> Edit Schedule
            </h3>
            <p id="modalTargetName" style="font-weight:600; color:#555; font-size:14px; margin-bottom:20px; background:#f9f9f9; padding:10px; border-radius:6px;"></p>
            
            <form method="POST">
                <input type="hidden" name="project_id" id="mod_project_id">
                <input type="hidden" name="target_type" id="mod_target_type">
                <input type="hidden" name="target_id" id="mod_target_id">
                
                <div class="form-group">
                    <label class="form-label">Assign Moderator</label>
                    <select name="moderator_id" id="mod_moderator_id" class="form-control" required>
                        <option value="">-- Select Moderator --</option>
                        <?php foreach($moderators as $id => $m): ?>
                            <option value="<?php echo $id; ?>">
                                <?php echo htmlspecialchars($m['fyp_name']); ?> (<?php echo $m['role']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color:#d93025; display:none; margin-top:5px;" id="conflictMsg">
                        <i class="fa fa-exclamation-triangle"></i> Cannot assign: This person is the Project Supervisor.
                    </small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Date & Time</label>
                    <input type="datetime-local" name="presentation_date" id="mod_date" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Venue / Room</label>
                    <input type="text" name="venue" id="mod_venue" class="form-control" placeholder="e.g. Lab 3, Meeting Room A" required>
                </div>
                
                <div class="modal-footer">
                    <button type="button" onclick="document.getElementById('allocationModal').style.display='none'" class="btn-auto" style="background:#eee; color:#333; box-shadow:none;">Cancel</button>
                    <button type="submit" name="save_allocation" id="btnSave" class="btn-auto" style="background:var(--primary-color);">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentSupervisorId = '';

        function openModal(pid, type, tid, name, modId, venue, date, svId) {
            document.getElementById('mod_project_id').value = pid;
            document.getElementById('mod_target_type').value = type;
            document.getElementById('mod_target_id').value = tid;
            document.getElementById('modalTargetName').innerText = name;
            
            document.getElementById('mod_moderator_id').value = modId;
            document.getElementById('mod_venue').value = venue;
            document.getElementById('mod_date').value = date;
            
            currentSupervisorId = svId; 
            checkConflict();
            
            document.getElementById('allocationModal').style.display = 'flex';
        }

        document.getElementById('mod_moderator_id').addEventListener('change', checkConflict);

        function checkConflict() {
            const selected = document.getElementById('mod_moderator_id').value;
            const warning = document.getElementById('conflictMsg');
            const btn = document.getElementById('btnSave');
            
            if (selected && selected === currentSupervisorId) {
                warning.style.display = 'block';
                btn.style.opacity = '0.5';
                btn.disabled = true;
            } else {
                warning.style.display = 'none';
                btn.style.opacity = '1';
                btn.disabled = false;
            }
        }
        
        window.onclick = function(e) {
            if (e.target == document.getElementById('allocationModal')) {
                document.getElementById('allocationModal').style.display = 'none';
            }
        }
    </script>
</body>
</html>