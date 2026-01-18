<?php
include("connect.php");

$auth_user_id = $_GET['auth_user_id'] ?? null;
$current_page = 'propose_project'; 

if (!$auth_user_id) { 
    echo "<script>window.location.href='login.php';</script>";
    exit; 
}

$academic_years = [];
if (isset($conn)) {
    $sql_acd = "SELECT * FROM academic_year ORDER BY fyp_acdyear DESC, fyp_intake ASC";
    $res_acd = $conn->query($sql_acd);
    if ($res_acd) {
        while ($row = $res_acd->fetch_assoc()) {
            $academic_years[] = $row;
        }
    }
}

$swal_alert = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $real_staff_id = "";
    $real_contact_name = "Unknown";
    $real_contact_info = "";
    
    $sql_fetch_coor = "SELECT fyp_staffid, fyp_name, fyp_email, fyp_contactno FROM coordinator WHERE fyp_userid = ?";
    if ($stmt_fetch = $conn->prepare($sql_fetch_coor)) {
        $stmt_fetch->bind_param("i", $auth_user_id); 
        $stmt_fetch->execute();
        $res_fetch = $stmt_fetch->get_result();
        if ($row_c = $res_fetch->fetch_assoc()) {
            $real_staff_id   = $row_c['fyp_staffid']; 
            $real_contact_name = $row_c['fyp_name'];
            $real_contact_info = !empty($row_c['fyp_email']) ? $row_c['fyp_email'] : $row_c['fyp_contactno'];
        }
        $stmt_fetch->close();
    }

    if (empty($real_staff_id)) {
        $swal_alert = [
            'title' => 'Error',
            'text' => 'Staff ID not found. Please ensure your Coordinator profile has a Staff ID.',
            'icon' => 'error'
        ];
    } else {
        $p_title = $_POST['project_title'];
        $p_domain = $_POST['project_domain'];
        $p_desc = $_POST['project_description'];
        $p_req = $_POST['requirements'];
        $p_type = $_POST['project_type']; 
        $p_academic_id = $_POST['academic_id'];
        
        $p_course_req = 'FIST'; 
        $p_status = 'Open'; 

        $sql_insert = "INSERT INTO project (
            fyp_staffid,
            fyp_academicid,
            fyp_projecttitle, 
            fyp_description, 
            fyp_projectcat, 
            fyp_projecttype, 
            fyp_projectstatus, 
            fyp_requirement, 
            fyp_coursereq, 
            fyp_contactperson, 
            fyp_contactpersonname, 
            fyp_datecreated
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        if ($stmt_insert = $conn->prepare($sql_insert)) {
            $stmt_insert->bind_param("sisssssssss", 
                $real_staff_id,
                $p_academic_id, 
                $p_title, 
                $p_desc, 
                $p_domain, 
                $p_type, 
                $p_status, 
                $p_req, 
                $p_course_req,
                $real_contact_info, 
                $real_contact_name
            );

            if ($stmt_insert->execute()) {
                $swal_alert = [
                    'title' => 'Success!',
                    'text' => 'Project proposed successfully!',
                    'icon' => 'success',
                    'redirect' => 'Coordinator_mainpage.php?page=dashboard&auth_user_id=' . $auth_user_id
                ];
            } else {
                $swal_alert = [
                    'title' => 'Database Error',
                    'text' => $stmt_insert->error,
                    'icon' => 'error'
                ];
            }
            $stmt_insert->close();
        } else {
            $swal_alert = [
                'title' => 'System Error',
                'text' => $conn->error,
                'icon' => 'error'
            ];
        }
    }
}

$user_name = "Coordinator"; 
$user_avatar = "image/user.png"; 

if (isset($conn)) {
    $sql_ui = "SELECT fyp_name, fyp_profileimg FROM coordinator WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_ui)) {
        $stmt->bind_param("i", $auth_user_id); 
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            if (!empty($row['fyp_name'])) $user_name = $row['fyp_name'];
            if (!empty($row['fyp_profileimg'])) $user_avatar = $row['fyp_profileimg'];
        }
        $stmt->close();
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
    <title>Propose Project - Coordinator</title>
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

        .form-card {
            background: var(--card-bg);
            padding: 30px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
        }

        .info-note {
            background-color: #e3effd;
            padding: 15px;
            border-radius: 6px;
            color: #0056b3;
            font-size: 14px;
            margin-bottom: 25px;
            border-left: 4px solid #0056b3;
            display: flex;
            align-items: start;
            gap: 10px;
        }

        .form-row {
            display: flex;
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
            flex: 1;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-color);
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 14px;
            font-family: inherit;
            background: var(--slot-bg);
            color: var(--text-color);
            transition: border 0.3s;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 120px;
        }

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
            font-size: 15px;
        }

        .btn-submit:hover {
            background-color: var(--primary-hover);
        }

        .theme-toggle {
            cursor: pointer; padding: 8px; border-radius: 50%;
            background: var(--slot-bg); border: 1px solid var(--border-color);
            color: var(--text-color); display: flex; align-items: center;
            justify-content: center; width: 35px; height: 35px; margin-right: 15px;
        }
        .theme-toggle img { width: 20px; height: 20px; object-fit: contain; }

        @media (max-width: 900px) {
            .main-content-wrapper {
                margin-left: 0;
                width: 100%;
            }
            .form-row {
                flex-direction: column;
                gap: 0;
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
                    $isActive = ($key == 'project_mgmt'); 
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
                <li class="menu-item <?php echo ($hasActiveChild || ($isActive && $hasSubmenu)) ? 'open active' : ''; ?>">
                    <a href="<?php echo $hasSubmenu ? 'javascript:void(0)' : $linkUrl; ?>" class="<?php echo $isActive ? 'active' : ''; ?>" <?php if ($hasSubmenu): ?>onclick="toggleSubmenu(this)"<?php endif; ?>>
                        <i class="fa <?php echo $item['icon']; ?> nav-icon"></i><span class="nav-text"><?php echo $item['name']; ?></span><?php if ($hasSubmenu): ?><i class="fa fa-chevron-down dropdown-arrow"></i><?php endif; ?>
                    </a>
                    <?php if ($hasSubmenu): ?>
                        <ul class="submenu">
                            <?php foreach ($item['sub_items'] as $sub_key => $sub_item): 
                                $subLinkUrl = isset($sub_item['link']) ? $sub_item['link'] : "#";
                                if ($subLinkUrl !== "#") {
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
        <ul class="logout"><li><a href="login.php"><i class="fa fa-power-off nav-icon"></i><span class="nav-text">Logout</span></a></li></ul>
    </nav>

    <div class="main-content-wrapper">
        <div class="page-header">
            <div class="welcome-text">
                <h1>Propose Project</h1>
                <p>Create a new project for students. As a Coordinator, you act as the Supervisor.</p>
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
            <div class="info-note">
                <i class="fa fa-info-circle" style="font-size: 18px; margin-top: 2px;"></i> 
                <div>
                    <strong>Project Status:</strong> All new projects are set to "<strong>Open</strong>" by default. 
                    <br>
                    <strong>Contact Info:</strong> Your name (<?php echo htmlspecialchars($user_name); ?>) will be automatically listed as the contact person.
                </div>
            </div>
            
            <form action="" method="POST">
                <div class="form-group">
                    <label for="project_title">Project Title <span style="color:red">*</span></label>
                    <input type="text" id="project_title" name="project_title" class="form-control" placeholder="Enter the title of the FYP project" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="academic_id">Target Academic Year / Intake <span style="color:red">*</span></label>
                        <select id="academic_id" name="academic_id" class="form-control" required>
                            <?php if (empty($academic_years)): ?>
                                <option value="" disabled>No academic years found</option>
                            <?php else: ?>
                                <?php foreach ($academic_years as $acy): ?>
                                    <option value="<?php echo $acy['fyp_academicid']; ?>">
                                        <?php echo htmlspecialchars($acy['fyp_acdyear'] . " - " . $acy['fyp_intake']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="project_domain">Domain / Category <span style="color:red">*</span></label>
                        <select id="project_domain" name="project_domain" class="form-control">
                            <option value="Software Eng.">Software Engineering</option>
                            <option value="Networking">Networking</option>
                            <option value="AI">Artificial Intelligence</option>
                            <option value="Cybersecurity">Cybersecurity</option>
                            <option value="Data Science">Data Science</option>
                            <option value="IoT">IoT</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="project_type">Project Type <span style="color:red">*</span></label>
                        <select id="project_type" name="project_type" class="form-control">
                            <option value="Individual">Individual</option>
                            <option value="Group">Group</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="course_req">Course Requirement</label>
                        <input type="text" id="course_req" name="course_req" class="form-control" value="FIST" readonly style="background-color: var(--slot-bg); cursor: not-allowed; opacity: 0.7;">
                    </div>
                </div>

                <div class="form-group">
                    <label for="project_description">Project Description <span style="color:red">*</span></label>
                    <textarea id="project_description" name="project_description" class="form-control" placeholder="Describe the objectives and scope of the project..." required></textarea>
                </div>

                <div class="form-group">
                    <label for="requirements">Technical Requirements</label>
                    <textarea id="requirements" name="requirements" class="form-control" placeholder="e.g. PHP, Python, MySQL, Flutter..."></textarea>
                </div>

                <button type="submit" class="btn-submit"><i class="fa fa-paper-plane"></i> Submit Proposal</button>
            </form>
        </div>
    </div>

    <script>
        function toggleSubmenu(element) {
            const menuItem = element.parentElement;
            const isOpen = menuItem.classList.contains('open');
            document.querySelectorAll('.menu-item').forEach(item => { if (item !== menuItem) item.classList.remove('open'); });
            if (isOpen) menuItem.classList.remove('open'); else menuItem.classList.add('open');
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

        <?php if ($swal_alert): ?>
            Swal.fire({
                title: "<?php echo $swal_alert['title']; ?>",
                text: "<?php echo $swal_alert['text']; ?>",
                icon: "<?php echo $swal_alert['icon']; ?>",
                confirmButtonColor: '#0056b3'
            }).then((result) => {
                <?php if (isset($swal_alert['redirect'])): ?>
                    if (result.isConfirmed) {
                        window.location.href = "<?php echo $swal_alert['redirect']; ?>";
                    }
                <?php endif; ?>
            });
        <?php endif; ?>
    </script>
</body>
</html>