<?php
// ====================================================
// supervisor_announcement.php - Post Announcement (AJAX + Redesign)
// ====================================================
date_default_timezone_set('Asia/Kuala_Lumpur');
include("connect.php");

// 1. AJAX Handler (Process Post without Reload)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax_post'])) {
    header('Content-Type: application/json');
    
    $auth_user_id = $_POST['auth_user_id'];
    
    // Retrieve Staff ID (Logic preserved)
    $my_staff_id = "";
    $stmt = $conn->prepare("SELECT fyp_staffid FROM supervisor WHERE fyp_userid = ?");
    $stmt->bind_param("i", $auth_user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) $my_staff_id = $row['fyp_staffid'];
    $stmt->close();

    if (empty($my_staff_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Staff ID not found.']);
        exit;
    }

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

    $sql_insert = "INSERT INTO announcement (fyp_subject, fyp_description, fyp_receiver, fyp_datecreated, fyp_staffid) VALUES (?, ?, ?, ?, ?)";
    
    if ($stmt = $conn->prepare($sql_insert)) {
        $stmt->bind_param("sssss", $subject, $description, $final_receiver, $date_now, $my_staff_id);
        
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => "Announcement posted to: $final_receiver"]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'SQL Prepare Error: ' . $conn->error]);
    }
    exit;
}

// 2. Standard Page Load
$auth_user_id = $_GET['auth_user_id'] ?? null;
$current_page = 'post_announcement'; 

if (!$auth_user_id) { header("location: login.php"); exit; }

// Fetch SV Info & Targets
$user_name = "Supervisor"; 
$user_avatar = "image/user.png"; 
$my_staff_id = ""; 
$my_active_targets = []; 

if (isset($conn)) {
    // Get User Info
    $stmt = $conn->prepare("SELECT * FROM supervisor WHERE fyp_userid = ?");
    $stmt->bind_param("i", $auth_user_id); 
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($row = $res->fetch_assoc()) {
        $my_staff_id = $row['fyp_staffid'];
        if (!empty($row['fyp_name'])) $user_name = $row['fyp_name'];
        if (!empty($row['fyp_profileimg'])) $user_avatar = $row['fyp_profileimg'];
    }
    $stmt->close();

    // Get Targets (Preserved Logic)
    if (!empty($my_staff_id)) { 
        $sql_targets = "SELECT p.fyp_projecttitle, p.fyp_projecttype, 
                               GROUP_CONCAT(s.fyp_studname SEPARATOR ', ') as students
                        FROM fyp_registration r
                        JOIN project p ON r.fyp_projectid = p.fyp_projectid
                        JOIN student s ON r.fyp_studid = s.fyp_studid
                        WHERE p.fyp_staffid = ? 
                        GROUP BY r.fyp_projectid";
        
        if ($stmt_t = $conn->prepare($sql_targets)) {
            $stmt_t->bind_param("s", $my_staff_id); 
            $stmt_t->execute();
            $res_t = $stmt_t->get_result();
            while ($row_t = $res_t->fetch_assoc()) {
                $type_tag = ($row_t['fyp_projecttype'] == 'Group') ? '[Group]' : '[Indiv]';
                $label = $type_tag . " " . substr($row_t['fyp_projecttitle'], 0, 30) . "... (" . $row_t['students'] . ")";
                $value = "Project: " . $row_t['fyp_projecttitle']; 
                $my_active_targets[] = ['label' => $label, 'value' => $value];
            }
            $stmt_t->close();
        }
    }
}

// Menu Definition
$menu_items = [
    'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home', 'link' => 'Supervisor_mainpage.php?page=dashboard'],
    'profile'   => ['name' => 'My Profile', 'icon' => 'fa-user', 'link' => 'supervisor_profile.php'],
    'students'  => [
        'name' => 'My Students', 
        'icon' => 'fa-users',
        'sub_items' => [
            'project_requests' => ['name' => 'Project Requests', 'icon' => 'fa-envelope-open-text', 'link' => 'supervisor_projectreq.php'],
            'student_list'     => ['name' => 'Student List', 'icon' => 'fa-list', 'link' => 'Supervisor_student_list.php'],
        ]
    ],
    'fyp_project' => [
        'name' => 'FYP Project',
        'icon' => 'fa-project-diagram',
        'sub_items' => [
            'propose_project' => ['name' => 'Propose Project', 'icon' => 'fa-plus-circle', 'link' => 'supervisor_purpose.php'],
            'my_projects'     => ['name' => 'My Projects', 'icon' => 'fa-folder-open', 'link' => 'Supervisor_manage_project.php'],
            'propose_assignment' => ['name' => 'Propose Assignment', 'icon' => 'fa-tasks', 'link' => 'supervisor_assignment_purpose.php']
        ]
    ],
    'grading' => [
        'name' => 'Assessment',
        'icon' => 'fa-marker',
        'sub_items' => [
            'grade_assignment' => ['name' => 'Grade Assignments', 'icon' => 'fa-check-square', 'link' => 'Supervisor_assignment_grade.php'],
            'grade_mod' => ['name' => 'Moderator Grading', 'icon' => 'fa-gavel', 'link' => 'Moderator_assignment_grade.php'],
        ]
    ],
    'announcement' => [
        'name' => 'Announcement',
        'icon' => 'fa-bullhorn',
        'sub_items' => [
            'post_announcement' => ['name' => 'Post Announcement', 'icon' => 'fa-pen-square', 'link' => 'supervisor_announcement.php'],
            'view_announcements' => ['name' => 'View History', 'icon' => 'fa-history', 'link' => 'Supervisor_announcement_view.php'],
        ]
    ],
    'schedule'  => ['name' => 'My Schedule', 'icon' => 'fa-calendar-alt', 'link' => 'supervisor_meeting.php'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post Announcement</title>
    <link rel="icon" type="image/png" href="image/ladybug.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap');
        
        :root { --primary-color: #0056b3; --primary-hover: #004494; --secondary-color: #f8f9fa; --text-color: #333; --border-color: #e0e0e0; --card-shadow: 0 4px 6px rgba(0,0,0,0.05); --bg-color: #f4f6f9; --sidebar-bg: #004085; --sidebar-hover: #003366; --sidebar-text: #e0e0e0; }
        body { font-family: 'Poppins', sans-serif; margin: 0; background-color: var(--bg-color); min-height: 100vh; display: flex; overflow-x: hidden; }

        /* Sidebar & Menu */
        .main-menu { background: var(--sidebar-bg); border-right: 1px solid rgba(255,255,255,0.1); position: fixed; top: 0; bottom: 0; height: 100%; left: 0; width: 60px; overflow-y: auto; overflow-x: hidden; transition: width .05s linear; z-index: 1000; box-shadow: 2px 0 5px rgba(0,0,0,0.1); }
        .main-menu:hover, nav.main-menu.expanded { width: 250px; }
        .main-menu > ul { margin: 7px 0; padding: 0; list-style: none; }
        .main-menu li { position: relative; display: block; width: 250px; }
        .main-menu li > a { position: relative; display: table; border-collapse: collapse; border-spacing: 0; color: var(--sidebar-text); font-size: 14px; text-decoration: none; transition: all .1s linear; width: 100%; }
        .main-menu .nav-icon { position: relative; display: table-cell; width: 60px; height: 46px; text-align: center; vertical-align: middle; font-size: 18px; }
        .main-menu .nav-text { position: relative; display: table-cell; vertical-align: middle; width: 190px; padding-left: 10px; white-space: nowrap; }
        .main-menu li:hover > a, nav.main-menu li.active > a, .menu-item.open > a { color: #fff; background-color: var(--sidebar-hover); border-left: 4px solid #fff; }
        .main-menu > ul.logout { position: absolute; left: 0; bottom: 0; width: 100%; }
        .dropdown-arrow { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); transition: transform 0.3s; font-size: 12px; }
        .menu-item.open .dropdown-arrow { transform: translateY(-50%) rotate(180deg); }
        .submenu { list-style: none; padding: 0; margin: 0; background-color: rgba(0,0,0,0.2); max-height: 0; overflow: hidden; transition: max-height 0.3s ease-out; }
        .menu-item.open .submenu { max-height: 500px; transition: max-height 0.5s ease-in; }
        .submenu li > a { padding-left: 70px !important; font-size: 13px; height: 40px; }

        /* Main Content */
        .main-content-wrapper { margin-left: 60px; flex: 1; padding: 20px; width: calc(100% - 60px); transition: margin-left .05s linear; }

        /* Header */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; background: white; padding: 20px; border-radius: 12px; box-shadow: var(--card-shadow); }
        .welcome-text h1 { margin: 0; font-size: 24px; color: var(--primary-color); font-weight: 600; }
        .welcome-text p { margin: 5px 0 0; color: #666; font-size: 14px; }
        .logo-section { display: flex; align-items: center; gap: 12px; }
        .logo-img { height: 40px; width: auto; background: white; padding: 2px; border-radius: 6px; }
        .system-title { font-size: 20px; font-weight: 600; color: var(--primary-color); letter-spacing: 0.5px; }

        /* Form Card */
        .form-card { background: #fff; padding: 30px; border-radius: 12px; box-shadow: var(--card-shadow); }
        .section-title { border-bottom: 2px solid #eee; padding-bottom: 15px; margin-bottom: 25px; }
        .section-title h2 { margin: 0; color: var(--primary-color); font-size: 18px; display: flex; align-items: center; gap: 10px; }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #444; font-size: 14px; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; box-sizing: border-box; transition: border 0.3s; font-family: inherit; }
        .form-control:focus { border-color: var(--primary-color); outline: none; }
        textarea.form-control { resize: vertical; min-height: 150px; }
        
        .btn-submit { background-color: var(--primary-color); color: white; border: none; padding: 12px 30px; border-radius: 6px; font-weight: 600; cursor: pointer; transition: background 0.2s; display: inline-flex; align-items: center; gap: 8px; font-size: 15px; }
        .btn-submit:hover { background-color: var(--primary-hover); }
        .btn-submit.loading { opacity: 0.7; pointer-events: none; }
        
        .specific-target-container { display: none; margin-top: 10px; padding: 15px; background: #f8f9fa; border-radius: 6px; border: 1px dashed #ddd; }

        @media (max-width: 900px) { .main-content-wrapper { margin-left: 0; width: 100%; } }
    </style>
</head>
<body>
    <nav class="main-menu">
        <ul>
            <?php foreach ($menu_items as $key => $item): ?>
                <?php 
                    $isActive = ($key == $current_page); // Highlight specific sub page
                    // Check if parent should be active based on key (announcement)
                    if($key == 'announcement') $isActive = true;

                    $hasActiveChild = false;
                    if (isset($item['sub_items'])) {
                        foreach ($item['sub_items'] as $sub_key => $sub) {
                            if ($sub_key == $current_page) { $hasActiveChild = true; break; }
                        }
                    }
                    $linkUrl = isset($item['link']) ? $item['link'] : "#";
                    if ($linkUrl !== "#") { $separator = (strpos($linkUrl, '?') !== false) ? '&' : '?'; $linkUrl .= $separator . "auth_user_id=" . urlencode($auth_user_id); }
                    $hasSubmenu = isset($item['sub_items']);
                ?>
                <li class="menu-item <?php echo $hasActiveChild ? 'open' : ''; ?>">
                    <a href="<?php echo $hasSubmenu ? 'javascript:void(0)' : $linkUrl; ?>" class="<?php echo ($key == 'announcement') ? 'active' : ''; ?>" <?php if ($hasSubmenu): ?>onclick="toggleSubmenu(this)"<?php endif; ?>>
                        <i class="fa <?php echo $item['icon']; ?> nav-icon"></i><span class="nav-text"><?php echo $item['name']; ?></span><?php if ($hasSubmenu): ?><i class="fa fa-chevron-down dropdown-arrow"></i><?php endif; ?>
                    </a>
                    <?php if ($hasSubmenu): ?>
                        <ul class="submenu">
                            <?php foreach ($item['sub_items'] as $sub_key => $sub_item): 
                                $subLinkUrl = isset($sub_item['link']) ? $sub_item['link'] : "#";
                                if ($subLinkUrl !== "#") { $separator = (strpos($subLinkUrl, '?') !== false) ? '&' : '?'; $subLinkUrl .= $separator . "auth_user_id=" . urlencode($auth_user_id); }
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
            <div class="welcome-text"><h1>Post Announcement</h1><p>Share updates, deadlines, or important information.</p></div>
            <div class="logo-section"><img src="image/ladybug.png" alt="Logo" class="logo-img"><span class="system-title">FYP Portal</span></div>
            <div style="display:flex; align-items:center; gap:10px;">
                <span style="font-size:13px; color:#666; background:#f0f0f0; padding:5px 10px; border-radius:20px;">Supervisor</span>
                <?php if(!empty($user_avatar) && $user_avatar != 'image/user.png'): ?>
                    <img src="<?php echo htmlspecialchars($user_avatar); ?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
                <?php else: ?>
                    <div style="width:40px;height:40px;border-radius:50%;background:#0056b3;color:white;display:flex;align-items:center;justify-content:center;font-weight:bold;"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-card">
            <div class="section-title"><h2><i class="fa fa-bullhorn"></i> New Announcement</h2></div>
            
            <form id="postForm" onsubmit="submitForm(event)">
                <input type="hidden" name="ajax_post" value="true">
                <input type="hidden" name="auth_user_id" value="<?php echo $auth_user_id; ?>">
                
                <div class="form-group">
                    <label for="subject">Subject / Title <span style="color:red">*</span></label>
                    <input type="text" id="subject" name="subject" class="form-control" placeholder="Enter announcement subject..." required>
                </div>

                <div class="form-group">
                    <label for="receiver_type">Receiver <span style="color:red">*</span></label>
                    <select id="receiver_type" name="receiver_type" class="form-control" onchange="toggleSpecificTarget(this.value)">
                        <option value="My Supervisees">All My Supervisees</option>
                        <option value="specific">Specific Project / Team</option>
                    </select>
                    
                    <div id="specific_target_container" class="specific-target-container">
                        <label for="specific_target" style="font-size:13px; color:#555;">Select Project / Team:</label>
                        <select id="specific_target" name="specific_target" class="form-control">
                            <?php if(empty($my_active_targets)): ?>
                                <option value="" disabled>No active projects found under your supervision.</option>
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
                    <label for="description">Content / Message <span style="color:red">*</span></label>
                    <textarea id="description" name="description" class="form-control" placeholder="Type your message here..." required></textarea>
                </div>

                <button type="submit" class="btn-submit" id="submitBtn"><i class="fa fa-paper-plane"></i> Publish Announcement</button>
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

        function toggleSpecificTarget(val) {
            const container = document.getElementById('specific_target_container');
            container.style.display = (val === 'specific') ? 'block' : 'none';
        }

        function submitForm(event) {
            event.preventDefault();
            const btn = document.getElementById('submitBtn');
            btn.classList.add('loading');
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Publishing...';

            const formData = new FormData(document.getElementById('postForm'));

            fetch('supervisor_announcement.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire({
                        title: 'Published!',
                        text: data.message,
                        icon: 'success',
                        confirmButtonColor: '#0056b3'
                    }).then(() => {
                        window.location.href = 'Supervisor_announcement_view.php?auth_user_id=<?php echo $auth_user_id; ?>';
                    });
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
                btn.classList.remove('loading');
                btn.innerHTML = '<i class="fa fa-paper-plane"></i> Publish Announcement';
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error', 'An error occurred.', 'error');
                btn.classList.remove('loading');
                btn.innerHTML = '<i class="fa fa-paper-plane"></i> Publish Announcement';
            });
        }
    </script>
</body>
</html>