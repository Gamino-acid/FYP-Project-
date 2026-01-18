<?php
include("connect.php");

$auth_user_id = $_GET['auth_user_id'] ?? null;
$current_page = 'announcements'; 

if (!$auth_user_id) { header("location: login.php"); exit; }

$user_name = "Coordinator"; 
$user_avatar = "image/user.png"; 
$current_staff_id = ""; 

$swal_script = "";

if (isset($conn)) {
    $sql_user = "SELECT fyp_username FROM `USER` WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_user)) {
        $stmt->bind_param("i", $auth_user_id); 
        $stmt->execute();
        $res = $stmt->get_result(); 
        if ($row = $res->fetch_assoc()) $user_name = $row['fyp_username'];
        $stmt->close();
    }
    
    $sql_coor = "SELECT * FROM coordinator WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_coor)) {
        $stmt->bind_param("i", $auth_user_id); 
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $row = $res->fetch_assoc();
            
            if (!empty($row['fyp_staffid'])) {
                $current_staff_id = $row['fyp_staffid'];
            } elseif (!empty($row['fyp_coordinatorid'])) {
                $current_staff_id = $row['fyp_coordinatorid']; 
            }

            if (!empty($row['fyp_name'])) $user_name = $row['fyp_name'];
            if (!empty($row['fyp_profileimg'])) $user_avatar = $row['fyp_profileimg'];
        }
        $stmt->close();
    }

    $my_active_targets = []; 
    $sql_targets = "SELECT p.fyp_projecttitle, p.fyp_projecttype, 
                           GROUP_CONCAT(s.fyp_studname SEPARATOR ', ') as students
                    FROM fyp_registration r
                    JOIN project p ON r.fyp_projectid = p.fyp_projectid
                    JOIN student s ON r.fyp_studid = s.fyp_studid
                    GROUP BY r.fyp_projectid";
    
    $res_t = $conn->query($sql_targets);
    if ($res_t) {
        while ($row_t = $res_t->fetch_assoc()) {
            $type_tag = ($row_t['fyp_projecttype'] == 'Group') ? '[Group]' : '[Indiv]';
            $label = $type_tag . " " . substr($row_t['fyp_projecttitle'], 0, 30) . "...";
            $value = "Project: " . $row_t['fyp_projecttitle']; 
            $my_active_targets[] = ['label' => $label, 'value' => $value];
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $subject = $_POST['subject'];
    $description = $_POST['description'];
    $receiver_type = $_POST['receiver_type'];
    $date_now = date('Y-m-d H:i:s');
    
    $final_receiver = "";
    if ($receiver_type == 'specific') {
        $final_receiver = $_POST['specific_target']; 
    } else {
        $final_receiver = $receiver_type; 
    }

    $sender_identity = $auth_user_id; 
    
    $id_query = "SELECT fyp_staffid FROM coordinator WHERE fyp_userid = '$auth_user_id' LIMIT 1";
    $id_res = $conn->query($id_query);
    if ($id_res && $id_row = $id_res->fetch_assoc()) {
        if(!empty($id_row['fyp_staffid'])) {
            $sender_identity = $id_row['fyp_staffid']; 
        }
    }

    $sql_insert = "INSERT INTO announcement (fyp_subject, fyp_description, fyp_receiver, fyp_datecreated, fyp_staffid) VALUES (?, ?, ?, ?, ?)";
    
    if ($stmt = $conn->prepare($sql_insert)) {
        $stmt->bind_param("sssss", $subject, $description, $final_receiver, $date_now, $sender_identity);
        
        if ($stmt->execute()) {
            $redirect_url = "Coordinator_announcement_view.php?auth_user_id=" . $auth_user_id;
            $swal_script = "
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        title: 'Success!',
                        text: 'Announcement posted successfully!',
                        icon: 'success',
                        confirmButtonColor: '#0056b3',
                        confirmButtonText: 'OK'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = '$redirect_url';
                        }
                    });
                });
            </script>";
        } else {
            $error_msg = addslashes($stmt->error);
            $swal_script = "
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        title: 'Error!',
                        text: 'SQL Execute Error: $error_msg',
                        icon: 'error',
                        confirmButtonColor: '#0056b3'
                    });
                });
            </script>";
        }
        $stmt->close();
    } else {
        $error_msg = addslashes($conn->error);
        $swal_script = "
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: 'Database Error!',
                    text: 'DB Prepare Error: $error_msg',
                    icon: 'error',
                    confirmButtonColor: '#0056b3'
                });
            });
        </script>";
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
    <title>Post Announcement - Coordinator</title>
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

        .form-card {
            background: var(--card-bg);
            padding: 30px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            border-left: 4px solid var(--primary-color);
            max-width: 900px;
            margin: 0 auto;
        }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-color); }
        .form-control { 
            width: 100%; 
            padding: 12px 15px; 
            border: 1px solid var(--border-color); 
            border-radius: 6px; 
            font-size: 14px; 
            background: var(--slot-bg);
            color: var(--text-color);
            box-sizing: border-box; 
            transition: all 0.3s; 
            font-family: inherit; 
        }
        .form-control:focus { border-color: var(--primary-color); outline: none; background: var(--card-bg); }
        textarea.form-control { resize: vertical; min-height: 150px; }
        
        .btn-submit { 
            background-color: var(--primary-color); 
            color: white; 
            border: none; 
            padding: 12px 30px; 
            border-radius: 6px; 
            font-weight: 600; 
            cursor: pointer; 
            transition: background 0.2s; 
            display: inline-flex; 
            align-items: center; 
            gap: 8px; 
        }
        .btn-submit:hover { background-color: var(--primary-hover); }
        
        .specific-target-container { 
            display: none; 
            margin-top: 10px; 
            padding: 15px; 
            background: var(--bg-color); 
            border-radius: 6px; 
            border: 1px dashed var(--border-color); 
        }

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
                            if ($sub_key == 'post_announcement') { $hasActiveChild = true; break; }
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
                                <li><a href="<?php echo $subLinkUrl; ?>" class="<?php echo ($sub_key == 'post_announcement') ? 'active' : ''; ?>"><i class="fa <?php echo $sub_item['icon']; ?> nav-icon"></i><span class="nav-text"><?php echo $sub_item['name']; ?></span></a></li>
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
                <h1>Post Announcement</h1>
                <p>Create and publish new announcements for students and supervisors.</p>
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

        <div class="form-card">
            <form action="" method="POST">
                <div class="form-group">
                    <label for="subject">Subject / Title <span style="color:#d93025">*</span></label>
                    <input type="text" id="subject" name="subject" class="form-control" placeholder="Enter announcement subject..." required>
                </div>

                <div class="form-group">
                    <label for="receiver_type">Receiver <span style="color:#d93025">*</span></label>
                    <select id="receiver_type" name="receiver_type" class="form-control" onchange="toggleSpecificTarget(this.value)">
                        <option value="All Students">All Students (System Wide)</option>
                        <option value="All Supervisors">All Supervisors</option>
                        <option value="specific">Specific Project Group (Any)</option>
                    </select>
                    
                    <div id="specific_target_container" class="specific-target-container">
                        <label for="specific_target" style="font-size:13px; color:var(--text-secondary); margin-bottom:5px; display:block;">Select Project / Team:</label>
                        <select id="specific_target" name="specific_target" class="form-control">
                            <?php if(empty($my_active_targets)): ?>
                                <option value="" disabled>No active projects found.</option>
                            <?php else: ?>
                                <?php foreach ($my_active_targets as $tgt): ?>
                                    <option value="<?php echo htmlspecialchars($tgt['value']); ?>">
                                        <?php echo htmlspecialchars($tgt['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Content / Message <span style="color:#d93025">*</span></label>
                    <textarea id="description" name="description" class="form-control" placeholder="Type your message here..." required></textarea>
                </div>

                <button type="submit" class="btn-submit"><i class="fa fa-paper-plane"></i> Publish Announcement</button>
            </form>
        </div>

    </div>
    
    <?php if(!empty($swal_script)) echo $swal_script; ?>

    <script>
        function toggleSpecificTarget(val) {
            const container = document.getElementById('specific_target_container');
            if (val === 'specific') {
                container.style.display = 'block';
            } else {
                container.style.display = 'none';
            }
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