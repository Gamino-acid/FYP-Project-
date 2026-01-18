<?php
include("connect.php");
session_start();

$auth_user_id = $_GET['auth_user_id'] ?? $_SESSION['user_id'] ?? null;
$current_page = 'propose_assignment'; 

if (!$auth_user_id) {
    echo "<script>window.location.href='login.php';</script>";
    exit;
}

$user_name = "Coordinator"; 
$user_avatar = "image/user.png"; 
$my_staff_id = ""; 
$my_groups = [];      
$my_individuals = []; 

if (isset($conn)) {
    $stmt = $conn->prepare("SELECT fyp_username FROM `USER` WHERE fyp_userid = ?");
    $stmt->bind_param("i", $auth_user_id); 
    $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc()) $user_name = $row['fyp_username'];
    $stmt->close();
    
    $stmt = $conn->prepare("SELECT * FROM supervisor WHERE fyp_userid = ?");
    $stmt->bind_param("i", $auth_user_id); 
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $my_staff_id = !empty($row['fyp_staffid']) ? $row['fyp_staffid'] : $row['fyp_supervisorid'];
        if (!empty($row['fyp_name'])) $user_name = $row['fyp_name'];
        if (!empty($row['fyp_profileimg'])) $user_avatar = $row['fyp_profileimg'];
    } else {
        $stmt->close();
        $stmt = $conn->prepare("SELECT * FROM coordinator WHERE fyp_userid = ?");
        $stmt->bind_param("i", $auth_user_id); 
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $my_staff_id = !empty($row['fyp_staffid']) ? $row['fyp_staffid'] : $row['fyp_coordinatorid'];
            if (!empty($row['fyp_name'])) $user_name = $row['fyp_name'];
            if (!empty($row['fyp_profileimg'])) $user_avatar = $row['fyp_profileimg'];
        }
    }
    if(isset($stmt)) $stmt->close();

    if (!empty($my_staff_id)) {
        $sql_my_students = "SELECT s.fyp_studid, s.fyp_studname, s.fyp_group 
                            FROM fyp_registration r
                            JOIN student s ON r.fyp_studid = s.fyp_studid
                            WHERE r.fyp_staffid = ? 
                            AND (r.fyp_archive_status = 'Active' OR r.fyp_archive_status IS NULL)";
                            
        if ($stmt = $conn->prepare($sql_my_students)) {
            $stmt->bind_param("s", $my_staff_id); 
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                if ($row['fyp_group'] == 'Individual') {
                    $my_individuals[$row['fyp_studid']] = $row['fyp_studname'];
                } else {
                    $g_res = $conn->query("SELECT group_id, group_name FROM student_group WHERE leader_id = '{$row['fyp_studid']}' LIMIT 1");
                    if ($g_row = $g_res->fetch_assoc()) {
                        $my_groups[$g_row['group_id']] = $g_row['group_name'];
                    } else {
                        $m_res = $conn->query("SELECT g.group_id, g.group_name FROM group_request gr JOIN student_group g ON gr.group_id = g.group_id WHERE gr.invitee_id = '{$row['fyp_studid']}' AND gr.request_status = 'Accepted' LIMIT 1");
                        if ($m_row = $m_res->fetch_assoc()) $my_groups[$m_row['group_id']] = $m_row['group_name'];
                    }
                }
            }
            $stmt->close();
        }
    }
}

$swal_alert = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (empty($my_staff_id)) {
        $swal_alert = ['title' => 'Error', 'text' => 'Staff ID not found.', 'icon' => 'error'];
    } else {
        $a_title = $_POST['assignment_title'];
        $a_desc = $_POST['assignment_description'];
        $a_deadline = $_POST['deadline'];
        $a_type = $_POST['assignment_type']; 
        $target_id = $_POST['target_selection']; 
        $weightage = $_POST['weightage'];

        $final_target = ($target_id == 'all_groups' || $target_id == 'all_individuals') ? 'ALL' : $target_id;
        $a_status = 'Active';
        $date_now = date('Y-m-d H:i:s');
        
        $sql_insert = "INSERT INTO assignment (fyp_staffid, fyp_title, fyp_description, fyp_deadline, fyp_weightage, fyp_assignment_type, fyp_status, fyp_datecreated, fyp_target_id) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        if ($stmt = $conn->prepare($sql_insert)) {
            $stmt->bind_param("ssssissss", $my_staff_id, $a_title, $a_desc, $a_deadline, $weightage, $a_type, $a_status, $date_now, $final_target);
            if ($stmt->execute()) {
                $swal_alert = [
                    'title' => 'Success!',
                    'text' => 'Assignment created successfully!',
                    'icon' => 'success',
                    'redirect' => 'Coordinator_assignment_grade.php?auth_user_id=' . urlencode($auth_user_id)
                ];
            } else {
                $swal_alert = ['title' => 'Database Error', 'text' => $stmt->error, 'icon' => 'error'];
            }
            $stmt->close();
        } else {
            $swal_alert = ['title' => 'SQL Error', 'text' => $conn->error, 'icon' => 'error'];
        }
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
    <title>Create Assignment - Coordinator</title>
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

        .content-card {
            background: var(--card-bg);
            padding: 30px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
        }

        .form-row { display: flex; gap: 20px; }
        .form-group { margin-bottom: 20px; flex: 1; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-color); font-size: 14px; }
        .form-control { width: 100%; padding: 10px 15px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 14px; box-sizing: border-box; transition: border 0.3s; font-family: inherit; background: var(--slot-bg); color: var(--text-color); }
        .form-control:focus { border-color: var(--primary-color); outline: none; }
        textarea.form-control { resize: vertical; min-height: 120px; }
        
        .info-note { background-color: var(--slot-bg); padding: 12px 15px; border-radius: 6px; color: var(--primary-color); font-size: 13px; margin-bottom: 25px; border-left: 4px solid var(--primary-color); display: flex; align-items: center; gap: 10px; }

        .btn-submit { background-color: var(--primary-color); color: white; border: none; padding: 12px 30px; border-radius: 6px; font-weight: 600; cursor: pointer; transition: background 0.2s; display: inline-flex; align-items: center; gap: 8px; font-size: 15px; }
        .btn-submit:hover { background-color: var(--primary-hover); }
        
        .target-select-container { display: none; margin-top: 10px; background: var(--slot-bg); padding: 15px; border-radius: 8px; border: 1px dashed var(--border-color); }
        
        .theme-toggle { cursor: pointer; padding: 8px; border-radius: 50%; background: var(--slot-bg); border: 1px solid var(--border-color); color: var(--text-color); display: flex; align-items: center; justify-content: center; width: 35px; height: 35px; margin-right: 15px; }
        .theme-toggle img { width: 20px; height: 20px; object-fit: contain; }

        @media (max-width: 900px) { 
            .main-content-wrapper { margin-left: 0; width: 100%; } 
            .form-row { flex-direction: column; gap: 0; }
            .page-header { flex-direction: column; gap: 15px; text-align: center; } 
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
                <h1>Create Assignment</h1>
                <p>Assign tasks to students or groups. You can set specific weightage and deadlines.</p>
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

        <div class="content-card">
            <div class="info-note">
                <i class="fa fa-info-circle"></i> 
                <strong>Tip:</strong> Selecting a standard "Category" will auto-fill the Title and recommended Weightage.
            </div>
            
            <form method="POST">
                
                <div class="form-group">
                    <label>Assignment Title <span style="color:red">*</span></label>
                    <input type="text" id="assignment_title" name="assignment_title" class="form-control" placeholder="e.g. Final Report Submission" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Category (Auto-fill)</label>
                        <select class="form-control" onchange="autoFillWeightage(this.value)">
                            <option value="" disabled selected>-- Select Helper --</option>
                            <option value="Proposal_10">Project Proposal (10%)</option>
                            <option value="Interim_20">Interim / Progress Report (20%)</option>
                            <option value="Final_40">Final Report (40%)</option>
                            <option value="Presentation_30">Presentation (30%)</option>
                            <option value="Custom">Custom / Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Target Type <span style="color:red">*</span></label>
                        <select id="assignment_type" name="assignment_type" class="form-control" onchange="toggleTargetList(this.value)" required>
                            <option value="" disabled selected>-- Select Type --</option>
                            <option value="Individual">Individual Student</option>
                            <option value="Group">Student Group</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Deadline <span style="color:red">*</span></label>
                        <input type="datetime-local" name="deadline" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Weightage (%) <span style="color:red">*</span></label>
                        <input type="number" id="weightage" name="weightage" class="form-control" min="0" max="100" required readonly style="background-color:var(--slot-bg); cursor:not-allowed;">
                    </div>
                </div>
                
                <div id="target_container" class="target-select-container form-group">
                    <label>Select Specific Target:</label>
                    <select id="target_selection" name="target_selection" class="form-control"></select>
                </div>

                <div class="form-group">
                    <label>Instructions / Description <span style="color:red">*</span></label>
                    <textarea name="assignment_description" class="form-control" placeholder="Detailed instructions for the students..." required></textarea>
                </div>

                <div style="text-align:right;">
                    <button type="submit" class="btn-submit"><i class="fa fa-paper-plane"></i> Publish Assignment</button>
                </div>
            </form>
        </div>
    </div>

    <script>
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

        const myGroups = <?php echo json_encode($my_groups); ?>;
        const myIndividuals = <?php echo json_encode($my_individuals); ?>;

        function autoFillWeightage(val) {
            const w = document.getElementById('weightage');
            const t = document.getElementById('assignment_title');
            
            if(val === 'Custom'){ 
                w.readOnly = false; 
                w.style.cursor = 'text';
                w.value = ''; 
            } else { 
                w.readOnly = true; 
                w.style.cursor = 'not-allowed';
                w.value = val.split('_')[1]; 
            }

            if(val.startsWith('Proposal')) t.value = 'Project Proposal Submission';
            else if(val.startsWith('Interim')) t.value = 'Interim Report Submission';
            else if(val.startsWith('Final')) t.value = 'Final Report Submission';
            else if(val.startsWith('Presentation')) t.value = 'Final Presentation';
        }

        function toggleTargetList(type) {
            const c = document.getElementById('target_container');
            const s = document.getElementById('target_selection');
            
            s.innerHTML = ''; 
            c.style.display = 'block';

            if(type === 'Group'){
                s.add(new Option('All My Groups', 'all_groups'));
                if (Object.keys(myGroups).length === 0) {
                    s.add(new Option('(No groups found)', ''));
                    s.disabled = true;
                } else {
                    s.disabled = false;
                    for(let k in myGroups) {
                        s.add(new Option('Group: ' + myGroups[k], k));
                    }
                }
            } else if(type === 'Individual'){
                s.add(new Option('All My Individual Students', 'all_individuals'));
                if (Object.keys(myIndividuals).length === 0) {
                    s.add(new Option('(No students found)', ''));
                    s.disabled = true;
                } else {
                    s.disabled = false;
                    for(let k in myIndividuals) {
                        s.add(new Option('Student: ' + myIndividuals[k], k));
                    }
                }
            } else {
                c.style.display = 'none';
            }
        }

        <?php if ($swal_alert): ?>
            Swal.fire({
                title: "<?php echo $swal_alert['title']; ?>",
                text: "<?php echo $swal_alert['text']; ?>",
                icon: "<?php echo $swal_alert['icon']; ?>",
                confirmButtonColor: '#0056b3'
            }).then(() => {
                <?php if (!empty($swal_alert['redirect'])): ?>
                    window.location.href = "<?php echo $swal_alert['redirect']; ?>";
                <?php endif; ?>
            });
        <?php endif; ?>
    </script>
</body>
</html>