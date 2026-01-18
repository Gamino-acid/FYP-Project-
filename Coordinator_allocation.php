<?php
include("connect.php");

$auth_user_id = $_GET['auth_user_id'] ?? null;
$current_page = 'allocation'; 

if (!$auth_user_id) { header("location: login.php"); exit; }

$user_name = "Coordinator"; 
$user_avatar = "image/user.png"; 

$swal_script = "";

if (isset($conn)) {
    $stmt = $conn->prepare("SELECT fyp_name, fyp_profileimg FROM coordinator WHERE fyp_userid = ?");
    $stmt->bind_param("i", $auth_user_id); $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        if(!empty($row['fyp_name'])) $user_name = $row['fyp_name'];
        if(!empty($row['fyp_profileimg'])) $user_avatar = $row['fyp_profileimg'];
    }
    $stmt->close();
}

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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
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
            
            if ($stmt->execute()) {
                $redirect_url = "Coordinator_allocation.php?auth_user_id=" . $auth_user_id;
                $swal_script = "
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            title: 'Success!',
                            text: 'Schedule updated successfully!',
                            icon: 'success',
                            confirmButtonColor: '#0056b3'
                        }).then(() => {
                            window.location.href = '$redirect_url';
                        });
                    });
                </script>";
            } else {
                $swal_script = "
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            title: 'Error!',
                            text: 'Failed to update schedule.',
                            icon: 'error',
                            confirmButtonColor: '#d33'
                        });
                    });
                </script>";
            }
        }
    }

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
                    $mod_index++;
                    $attempts++;

                    if ($candidate_id != $proj['supervisor_id']) {
                        $assigned_mod = $candidate_id;
                        break;
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
            
            $redirect_url = "Coordinator_allocation.php?auth_user_id=" . $auth_user_id;
            $swal_script = "
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        title: 'Auto Allocation Complete',
                        text: 'Successfully assigned moderators to $count_assigned pending projects.',
                        icon: 'success',
                        confirmButtonColor: '#0056b3'
                    }).then(() => {
                        window.location.href = '$redirect_url';
                    });
                });
            </script>";
        } else {
             $swal_script = "
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        title: 'Error',
                        text: 'No active Moderators found.',
                        icon: 'error',
                        confirmButtonColor: '#d33'
                    });
                });
            </script>";
        }
    }
}

$allocations = [];

$sql_view = "SELECT 
                p.fyp_projecttitle, 
                p.fyp_projecttype,
                p.fyp_projectid,
                r.fyp_studid,
                s.fyp_studname,
                COALESCE(sup.fyp_name, coor.fyp_name, r.fyp_staffid) as supervisor_name,
                r.fyp_staffid as supervisor_staff_id,
                sched.moderator_id,
                sched.venue,
                sched.presentation_date,
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
             WHERE 
                (p.fyp_projecttype = 'Individual') 
                OR 
                (p.fyp_projecttype = 'Group' AND sg.group_id IS NOT NULL)
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
        'name' => 'Project Manage', 
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
    'allocation' => ['name' => 'Moderator Allocation', 'icon' => 'fa-people-arrows', 'link' => 'Coordinator_allocation.php'],
    'data_mgmt' => ['name' => 'Data Management', 'icon' => 'fa-database', 'link' => 'Coordinator_data_io.php'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moderator Allocation - Coordinator</title>
    <link rel="icon" type="image/png" href="image/ladybug.png?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap');
        
        :root {
            --primary-color: #0056b3;
            --primary-hover: #004494;
            --bg-color: #f4f6f9;
            --card-bg: #ffffff;
            --text-color: #333;
            --text-secondary: #666;
            --sidebar-bg: #004085; 
            --sidebar-hover: #003366;
            --sidebar-text: #e0e0e0;
            --card-shadow: 0 4px 6px rgba(0,0,0,0.05);
            --border-color: #e0e0e0;
            --slot-bg: #f8f9fa;
        }

        .dark-mode {
            --primary-color: #4da3ff;
            --primary-hover: #0069d9;
            --bg-color: #121212;
            --card-bg: #1e1e1e;
            --text-color: #e0e0e0;
            --text-secondary: #a0a0a0;
            --sidebar-bg: #0d1117;
            --sidebar-hover: #161b22;
            --sidebar-text: #c9d1d9;
            --card-shadow: 0 4px 6px rgba(0,0,0,0.3);
            --border-color: #333;
            --slot-bg: #2d2d2d;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            background-color: var(--bg-color);
            color: var(--text-color);
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
            transition: background-color 0.3s, color 0.3s;
        }

        .main-menu {
            background: var(--sidebar-bg);
            border-right: 1px solid rgba(255,255,255,0.1);
            position: fixed;
            top: 0;
            bottom: 0;
            height: 100%;
            left: 0;
            width: 60px;
            overflow-y: auto;
            overflow-x: hidden;
            transition: width .05s linear;
            z-index: 1000;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }

        .main-menu:hover, nav.main-menu.expanded {
            width: 250px;
            overflow: visible;
        }

        .main-menu > ul {
            margin: 7px 0;
            padding: 0;
            list-style: none;
        }

        .main-menu li {
            position: relative;
            display: block;
            width: 250px;
        }

        .main-menu li > a {
            position: relative;
            display: table;
            border-collapse: collapse;
            border-spacing: 0;
            color: var(--sidebar-text);
            font-size: 14px;
            text-decoration: none;
            transition: all .1s linear;
            width: 100%;
        }

        .main-menu .nav-icon {
            position: relative;
            display: table-cell;
            width: 60px;
            height: 46px; 
            text-align: center;
            vertical-align: middle;
            font-size: 18px;
        }

        .main-menu .nav-text {
            position: relative;
            display: table-cell;
            vertical-align: middle;
            width: 190px;
            padding-left: 10px;
            white-space: nowrap;
        }

        .main-menu li:hover > a, nav.main-menu li.active > a {
            color: #fff;
            background-color: var(--sidebar-hover);
            border-left: 4px solid #fff; 
        }

        .main-menu > ul.logout {
            position: absolute;
            left: 0;
            bottom: 0;
            width: 100%;
        }

        .dropdown-arrow {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            transition: transform 0.3s;
            font-size: 12px;
        }

        .menu-item.open .dropdown-arrow {
            transform: translateY(-50%) rotate(180deg);
        }

        .submenu {
            list-style: none;
            padding: 0;
            margin: 0;
            background-color: rgba(0,0,0,0.2);
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }

        .menu-item.open .submenu {
            max-height: 500px;
            transition: max-height 0.5s ease-in;
        }

        .submenu li > a {
            padding-left: 70px !important;
            font-size: 13px;
            height: 40px;
        }

        .menu-item > a {
            cursor: pointer;
        }
        
        .main-content-wrapper {
            margin-left: 60px;
            flex: 1;
            padding: 20px;
            width: calc(100% - 60px);
            transition: margin-left .05s linear;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            background: var(--card-bg);
            padding: 20px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            transition: background 0.3s;
        }
        
        .welcome-text h1 {
            margin: 0;
            font-size: 24px;
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .welcome-text p {
            margin: 5px 0 0;
            color: var(--text-secondary);
            font-size: 14px;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .logo-img {
            height: 40px;
            width: auto;
            background: white;
            padding: 2px;
            border-radius: 6px;
        }
        
        .system-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--primary-color);
            letter-spacing: 0.5px;
        }

        .user-section {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-badge {
            font-size: 13px;
            color: var(--text-secondary);
            background: var(--slot-bg);
            padding: 5px 10px;
            border-radius: 20px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .user-avatar-placeholder {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #0056b3;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .theme-toggle {
            cursor: pointer; padding: 8px; border-radius: 50%;
            background: var(--slot-bg); border: 1px solid var(--border-color);
            color: var(--text-color); display: flex; align-items: center;
            justify-content: center; width: 35px; height: 35px; margin-right: 15px;
        }
        .theme-toggle img { width: 20px; height: 20px; object-fit: contain; }

        .allocation-card {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
        }

        .allocation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 15px;
        }

        .btn-auto { 
            background: #28a745; 
            color: white; 
            border: none; 
            padding: 10px 20px; 
            border-radius: 6px; 
            cursor: pointer; 
            font-weight: 600; 
            display: flex; 
            align-items: center; 
            gap: 8px; 
            transition: background 0.3s;
        }
        .btn-auto:hover { background: #218838; }

        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { text-align: left; padding: 12px; background: var(--slot-bg); color: var(--text-secondary); font-size: 13px; font-weight: 600; border-bottom: 2px solid var(--border-color); }
        td { padding: 12px; border-bottom: 1px solid var(--border-color); font-size: 14px; vertical-align: middle; color: var(--text-color); }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; }
        .badge-gray { background: #e9ecef; color: #666; }
        .badge-blue { background: #cce5ff; color: #004085; }
        .badge-purple { background: #e0d4fc; color: #59359a; }
        
        .btn-edit { background: var(--primary-color); color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; transition: background 0.3s; }
        .btn-edit:hover { background: var(--primary-hover); }

        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background: var(--card-bg); padding: 25px; border-radius: 12px; width: 500px; max-width: 90%; color: var(--text-color); box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; color: var(--text-color); }
        .form-control { width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 4px; box-sizing: border-box; background: var(--slot-bg); color: var(--text-color); }
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }

        @media (max-width: 900px) { 
            .main-content-wrapper { 
                margin-left: 0; 
                width: 100%; 
            }
            .page-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
        }
    </style>
</head>
<body>

    <nav class="main-menu">
        <ul>
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
                    if ($linkUrl !== "#" && strpos($linkUrl, '.php') !== false) {
                         $separator = (strpos($linkUrl, '?') !== false) ? '&' : '?';
                         $linkUrl .= $separator . "auth_user_id=" . urlencode($auth_user_id);
                    }
                    $hasSubmenu = isset($item['sub_items']);
                ?>
                <li class="menu-item <?php echo ($hasActiveChild || $isActive) ? 'open active' : ''; ?>">
                    <a href="<?php echo $hasSubmenu ? 'javascript:void(0)' : $linkUrl; ?>" 
                       class="<?php echo $isActive ? 'active' : ''; ?>"
                       <?php if ($hasSubmenu): ?>onclick="toggleSubmenu(this)"<?php endif; ?>>
                        <i class="fa <?php echo $item['icon']; ?> nav-icon"></i>
                        <span class="nav-text"><?php echo $item['name']; ?></span>
                        <?php if ($hasSubmenu): ?>
                            <i class="fa fa-chevron-down dropdown-arrow"></i>
                        <?php endif; ?>
                    </a>
                    
                    <?php if ($hasSubmenu): ?>
                        <ul class="submenu">
                            <?php foreach ($item['sub_items'] as $sub_key => $sub_item): 
                                $subLinkUrl = isset($sub_item['link']) ? $sub_item['link'] : "#";
                                if ($subLinkUrl !== "#" && strpos($subLinkUrl, '.php') !== false) {
                                    $separator = (strpos($subLinkUrl, '?') !== false) ? '&' : '?';
                                    $subLinkUrl .= $separator . "auth_user_id=" . urlencode($auth_user_id);
                                }
                            ?>
                                <li><a href="<?php echo $subLinkUrl; ?>" class="<?php echo ($sub_key == $current_page) ? 'active' : ''; ?>"><i class="fa <?php echo $sub_item['icon']; ?> nav-icon"></i><span class="nav-text"><?php echo $sub_item['name']; ?></span></a></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <ul class="logout">
            <li>
                <a href="login.php">
                    <i class="fa fa-power-off nav-icon"></i>
                    <span class="nav-text">Logout</span>
                </a>
            </li>  
        </ul>
    </nav>

    <div class="main-content-wrapper">
        <div class="page-header">
            <div class="welcome-text">
                <h1>Moderator Allocation</h1>
                <p>Manage presentation schedules and assign moderators.</p>
            </div>
            
            <div class="logo-section">
                <img src="image/ladybug.png" alt="Logo" class="logo-img">
                <span class="system-title">FYP Portal</span>
            </div>

            <div class="user-section">
                <button class="theme-toggle" onclick="toggleDarkMode()" title="Toggle Dark Mode">
                    <img id="theme-icon" src="image/moon-solid-full.svg" alt="Toggle Theme">
                </button>
                <span class="user-badge">Coordinator</span>
                <?php if(!empty($user_avatar) && $user_avatar !== 'image/user.png'): ?>
                    <img src="<?php echo htmlspecialchars($user_avatar); ?>" class="user-avatar" alt="Avatar">
                <?php else: ?>
                    <div class="user-avatar-placeholder">
                        <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="allocation-card">
            <div class="allocation-header">
                <div>
                    <h2 style="margin:0; color:var(--primary-color); font-size: 18px;"><i class="fa fa-bullseye"></i> Presentation Allocation</h2>
                </div>
                <form id="autoAllocateForm" method="POST">
                    <input type="hidden" name="auto_allocate" value="1">
                    <button type="button" onclick="confirmAutoAllocate()" class="btn-auto">
                        <i class="fa fa-magic"></i> Auto Allocate
                    </button>
                </form>
            </div>

            <table>
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
                                <div style="font-weight:600; color:var(--text-color);"><?php echo htmlspecialchars($row['fyp_projecttitle']); ?></div>
                                <div style="font-size:12px; color:var(--text-secondary); margin-top:2px;">
                                    <span class="badge badge-gray"><?php echo $row['fyp_projecttype']; ?></span> 
                                    <?php echo htmlspecialchars($row['display_name']); ?>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($row['supervisor_name']); ?></td>
                            <td>
                                <?php if($hasMod): ?>
                                    <span class="badge badge-purple"><i class="fa fa-user-tie"></i> <?php echo htmlspecialchars($row['moderator_name']); ?></span>
                                <?php else: ?>
                                    <span style="color:var(--text-secondary); font-size:12px; font-style:italic;">- Not Assigned -</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($hasTime): ?>
                                    <div style="font-size:13px;"><i class="fa fa-calendar"></i> <?php echo date('d M Y, h:i A', strtotime($row['presentation_date'])); ?></div>
                                    <div style="font-size:12px; color:var(--text-secondary); margin-top:3px;"><i class="fa fa-map-marker-alt"></i> <?php echo htmlspecialchars($row['venue']); ?></div>
                                <?php else: ?>
                                    <span style="color:var(--text-secondary); font-size:12px;">- Not Scheduled -</span>
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
                                    <i class="fa fa-edit"></i> Edit
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align:center; padding:30px; color:var(--text-secondary);">No active projects found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="allocationModal" class="modal">
        <div class="modal-content">
            <h3 style="margin-top:0; border-bottom:1px solid var(--border-color); padding-bottom:10px; color:var(--primary-color);">Edit Schedule</h3>
            <p id="modalTargetName" style="font-weight:600; color:var(--text-color); font-size:14px; margin-bottom:20px;"></p>
            
            <form method="POST">
                <input type="hidden" name="project_id" id="mod_project_id">
                <input type="hidden" name="target_type" id="mod_target_type">
                <input type="hidden" name="target_id" id="mod_target_id">
                
                <div class="form-group">
                    <label>Assign Moderator (Including Coordinators)</label>
                    <select name="moderator_id" id="mod_moderator_id" class="form-control" required>
                        <option value="">-- Select Moderator --</option>
                        <?php foreach($moderators as $id => $m): ?>
                            <option value="<?php echo $id; ?>">
                                <?php echo htmlspecialchars($m['fyp_name']); ?> (<?php echo $m['role']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color:#d93025; display:none; margin-top:5px;" id="conflictMsg"><i class="fa fa-exclamation-triangle"></i> Warning: This is the Supervisor!</small>
                </div>
                
                <div class="form-group">
                    <label>Date & Time</label>
                    <input type="datetime-local" name="presentation_date" id="mod_date" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Venue / Room</label>
                    <input type="text" name="venue" id="mod_venue" class="form-control" placeholder="e.g. Lab 3, Meeting Room A" required>
                </div>
                
                <div class="modal-footer">
                    <button type="submit" name="save_allocation" class="btn-auto" style="background:var(--primary-color);">Save Changes</button>
                    <button type="button" onclick="closeModal()" class="btn-auto" style="background:#6c757d;">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <?php if(!empty($swal_script)) echo $swal_script; ?>

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
        
        function closeModal() {
            document.getElementById('allocationModal').style.display = 'none';
        }

        document.getElementById('mod_moderator_id').addEventListener('change', checkConflict);

        function checkConflict() {
            const selected = document.getElementById('mod_moderator_id').value;
            const warning = document.getElementById('conflictMsg');
            if (selected && selected === currentSupervisorId) {
                warning.style.display = 'block';
            } else {
                warning.style.display = 'none';
            }
        }
        
        window.onclick = function(e) {
            if (e.target == document.getElementById('allocationModal')) {
                closeModal();
            }
        }
        
        function confirmAutoAllocate() {
            Swal.fire({
                title: 'Are you sure?',
                text: "This will automatically assign moderators to all pending slots based on availability logic.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, Auto Allocate!'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('autoAllocateForm').submit();
                }
            })
        }

        function toggleSubmenu(element) {
            const menuItem = element.parentElement;
            const isOpen = menuItem.classList.contains('open');
            
            document.querySelectorAll('.menu-item').forEach(item => {
                if (item !== menuItem) {
                    item.classList.remove('open');
                }
            });
            
            if (isOpen) {
                menuItem.classList.remove('open');
            } else {
                menuItem.classList.add('open');
            }
        }

        function toggleDarkMode() {
            document.body.classList.toggle('dark-mode');
            const isDark = document.body.classList.contains('dark-mode');
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
            
            const iconImg = document.getElementById('theme-icon');
            if (isDark) {
                iconImg.src = 'image/sun-solid-full.svg'; 
            } else {
                iconImg.src = 'image/moon-solid-full.svg'; 
            }
        }

        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            document.body.classList.add('dark-mode');
            const iconImg = document.getElementById('theme-icon');
            if(iconImg) {
                iconImg.src = 'image/sun-solid-full.svg'; 
            }
        }
    </script>
</body>
</html>