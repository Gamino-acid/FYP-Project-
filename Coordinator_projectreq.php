<?php
include("connect.php");

if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $req_id = $_POST['req_id'];
    $action = $_POST['action'];
    $auth_user_id = $_POST['auth_user_id'];
    
    if ($action == 'Reject') {
        $conn->query("UPDATE project_request SET fyp_requeststatus = 'Reject' WHERE fyp_requestid = $req_id");
        echo json_encode(['status' => 'success', 'message' => 'Request Rejected.', 'new_status' => 'Reject']);
    } elseif ($action == 'Approve') {
        $req_sql = "SELECT fyp_studid, fyp_projectid, fyp_staffid FROM project_request WHERE fyp_requestid = $req_id LIMIT 1";
        $req_res = $conn->query($req_sql);
        if ($req_row = $req_res->fetch_assoc()) {
            $sid = $req_row['fyp_studid']; $pid = $req_row['fyp_projectid']; $svid = $req_row['fyp_staffid'];
            
            $students_to_register = [$sid];
            
            foreach ($students_to_register as $student_id) {
                $conn->query("INSERT INTO fyp_registration (fyp_studid, fyp_projectid, fyp_staffid, fyp_datecreated, fyp_archive_status) VALUES ('$student_id', '$pid', '$svid', NOW(), 'Active')");
            }
            $conn->query("UPDATE project_request SET fyp_requeststatus = 'Approve' WHERE fyp_requestid = $req_id");
            $conn->query("UPDATE project SET fyp_projectstatus = 'Taken' WHERE fyp_projectid = $pid");
            echo json_encode(['status' => 'success', 'message' => 'Approved! Project Taken.', 'new_status' => 'Approve']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Request not found.']);
        }
    }
    exit;
}

$auth_user_id = $_GET['auth_user_id'] ?? null;
$current_page = 'project_requests'; 

if (!$auth_user_id) { 
    echo "<script>window.location.href='login.php';</script>";
    exit; 
}

$user_name = "Coordinator"; 
$user_avatar = "image/user.png"; 
$sv_id = ""; 

if ($stmt = $conn->prepare("SELECT * FROM coordinator WHERE fyp_userid = ?")) {
    $stmt->bind_param("i", $auth_user_id); $stmt->execute(); $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) { 
        $sv_id = !empty($row['fyp_staffid']) ? $row['fyp_staffid'] : $row['fyp_coordinatorid'];
        $user_name = $row['fyp_name']; 
        if($row['fyp_profileimg']) $user_avatar=$row['fyp_profileimg']; 
    }
    $stmt->close();
}

$requests = [];
if ($sv_id) {
    $sql = "SELECT r.*, s.fyp_studname, s.fyp_studid, p.fyp_projecttitle 
            FROM project_request r 
            JOIN student s ON r.fyp_studid = s.fyp_studid 
            JOIN project p ON r.fyp_projectid = p.fyp_projectid 
            WHERE r.fyp_staffid = '$sv_id' 
            ORDER BY r.fyp_datecreated DESC";
    $res = $conn->query($sql);
    if($res) while($row=$res->fetch_assoc()) $requests[] = $row;
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
    <title>Requests - Coordinator</title>
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

        .card-container {
            background: var(--card-bg);
            padding: 30px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
        }

        .req-card {
            background: var(--slot-bg);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            border: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s;
            border-left: 5px solid #0056b3;
        }

        .req-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .req-card[data-status="Approve"] { border-left-color: #28a745; }
        .req-card[data-status="Reject"] { border-left-color: #dc3545; }
        .req-card[data-status="Pending"] { border-left-color: #ffc107; }

        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 5px;
            display: inline-block;
        }

        .st-Pending { background: #fff3cd; color: #856404; }
        .st-Approve { background: #d4edda; color: #155724; }
        .st-Reject { background: #f8d7da; color: #721c24; }

        .btn-act {
            padding: 8px 18px;
            border-radius: 6px;
            color: white;
            border: none;
            font-size: 13px;
            margin-left: 8px;
            cursor: pointer;
            transition: 0.2s;
            font-weight: 500;
        }

        .btn-act.loading { opacity: 0.7; pointer-events: none; }
        .btn-app { background: #28a745; } .btn-app:hover { background: #218838; }
        .btn-rej { background: #dc3545; } .btn-rej:hover { background: #c82333; }

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
            .page-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            .req-card {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            .req-card > div:last-child {
                width: 100%;
                display: flex;
                justify-content: flex-end;
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
                <h1>Request Management</h1>
                <p>Manage project application requests from students.</p>
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

        <div class="card-container">
            <?php if (count($requests) > 0): foreach ($requests as $req): 
                $status = $req['fyp_requeststatus'];
                if (empty($status)) $status = 'Pending';
            ?>
                <div class="req-card" id="card-<?php echo $req['fyp_requestid']; ?>" data-status="<?php echo $status; ?>">
                    <div>
                        <span id="badge-<?php echo $req['fyp_requestid']; ?>" class="status-badge st-<?php echo $status; ?>"><?php echo $status; ?></span>
                        <h3 style="margin: 5px 0; font-size: 16px; color: var(--text-color);"><?php echo htmlspecialchars($req['fyp_studname']); ?></h3>
                        <p style="margin: 5px 0; color: var(--text-secondary); font-size: 14px;">Project: <strong style="color: var(--primary-color);"><?php echo htmlspecialchars($req['fyp_projecttitle']); ?></strong></p>
                        <small style="color: var(--text-secondary); font-size: 12px;"><i class="fa fa-calendar-alt"></i> Applied: <?php echo date('d M Y', strtotime($req['fyp_datecreated'])); ?></small>
                    </div>
                    <div id="actions-<?php echo $req['fyp_requestid']; ?>">
                        <?php if ($status == 'Pending'): ?>
                            <button onclick="handleDecision('Approve', <?php echo $req['fyp_requestid']; ?>)" class="btn-act btn-app"><i class="fa fa-check"></i> Accept</button>
                            <button onclick="handleDecision('Reject', <?php echo $req['fyp_requestid']; ?>)" class="btn-act btn-rej"><i class="fa fa-times"></i> Reject</button>
                        <?php else: ?>
                            <span style="font-size:12px; color:var(--text-secondary); font-style:italic;">Decision Recorded</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; else: ?>
                <div style="text-align:center; padding:50px; color:var(--text-secondary);">No project requests found.</div>
            <?php endif; ?>
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

        function handleDecision(action, reqId) {
            Swal.fire({
                title: action === 'Approve' ? 'Accept Request?' : 'Reject Request?',
                text: action === 'Approve' ? 'This student will be assigned to the project.' : 'This request will be declined.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: action === 'Approve' ? '#28a745' : '#dc3545',
                confirmButtonText: 'Yes'
            }).then((result) => {
                if (result.isConfirmed) {
                    const btns = document.querySelector(`#actions-${reqId}`).querySelectorAll('button');
                    btns.forEach(b => b.classList.add('loading'));

                    const formData = new FormData();
                    formData.append('ajax_action', 'true');
                    formData.append('req_id', reqId);
                    formData.append('action', action);
                    formData.append('auth_user_id', '<?php echo $auth_user_id; ?>');

                    fetch('Coordinator_projectreq.php', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        if(data.status === 'success') {
                            const card = document.getElementById('card-' + reqId);
                            const badge = document.getElementById('badge-' + reqId);
                            const actions = document.getElementById('actions-' + reqId);
                            
                            badge.className = 'status-badge st-' + data.new_status;
                            badge.innerText = data.new_status;
                            card.setAttribute('data-status', data.new_status);
                            actions.innerHTML = '<span style="font-size:12px; color:var(--text-secondary); font-style:italic;">Decision Recorded</span>';
                            
                            Swal.fire('Success!', data.message, 'success');
                        } else {
                            Swal.fire('Error', data.message, 'error');
                            btns.forEach(b => b.classList.remove('loading'));
                        }
                    })
                    .catch(err => {
                        Swal.fire('Error', 'Network error occurred.', 'error');
                        btns.forEach(b => b.classList.remove('loading'));
                    });
                }
            });
        }
    </script>
</body> 
</html>