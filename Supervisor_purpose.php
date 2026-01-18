<?php
// ====================================================
// supervisor_purpose.php - Propose Project (AJAX + UI Updated)
// ====================================================

include("connect.php");

// 1. AJAX Handler for Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax_propose_project'])) {
    header('Content-Type: application/json');

    $auth_user_id = $_POST['auth_user_id'] ?? null;
    
    // Fetch SV Info
    $real_staff_id = "";
    $real_sv_name = "Unknown Supervisor";
    $real_sv_contact = "";
    
    $stmt = $conn->prepare("SELECT fyp_staffid, fyp_name, fyp_email, fyp_contactno FROM supervisor WHERE fyp_userid = ?");
    $stmt->bind_param("i", $auth_user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row_sv = $res->fetch_assoc()) {
        $real_staff_id = $row_sv['fyp_staffid'];
        $real_sv_name = $row_sv['fyp_name'];
        $real_sv_contact = !empty($row_sv['fyp_email']) ? $row_sv['fyp_email'] : $row_sv['fyp_contactno'];
    }
    $stmt->close();

    if (empty($real_staff_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Staff ID not found.']);
        exit;
    }

    // Get Data
    $p_title = $_POST['project_title'];
    $p_domain = $_POST['project_domain'];
    $p_desc = $_POST['project_description'];
    $p_req = $_POST['requirements'];
    $p_type = $_POST['project_type']; 
    $p_academic_id = $_POST['academic_id']; 
    $p_course_req = 'FIST'; 
    $p_status = 'Open'; 

    $sql_insert = "INSERT INTO project (
        fyp_staffid, fyp_academicid, fyp_projecttitle, fyp_description, 
        fyp_projectcat, fyp_projecttype, fyp_projectstatus, fyp_requirement, 
        fyp_coursereq, fyp_contactperson, fyp_contactpersonname, fyp_datecreated
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    if ($stmt_insert = $conn->prepare($sql_insert)) {
        $stmt_insert->bind_param("sisssssssss", 
            $real_staff_id, $p_academic_id, $p_title, $p_desc, $p_domain, 
            $p_type, $p_status, $p_req, $p_course_req, $real_sv_contact, $real_sv_name
        );

        if ($stmt_insert->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Project proposed successfully!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $stmt_insert->error]);
        }
        $stmt_insert->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'SQL Error: ' . $conn->error]);
    }
    exit;
}

// 2. Standard Page Load
$auth_user_id = $_GET['auth_user_id'] ?? null;
$current_page = 'propose_project'; 

if (!$auth_user_id) { header("location: login.php"); exit; }

// Fetch Academic Years
$academic_years = [];
if (isset($conn)) {
    $res_acd = $conn->query("SELECT * FROM academic_year ORDER BY fyp_acdyear DESC, fyp_intake ASC");
    if ($res_acd) while ($row = $res_acd->fetch_assoc()) $academic_years[] = $row;
}

// Fetch User Info for UI
$user_name = "Supervisor"; 
$user_avatar = "image/user.png"; 
if (isset($conn)) {
    $stmt = $conn->prepare("SELECT fyp_name, fyp_profileimg FROM supervisor WHERE fyp_userid = ?");
    $stmt->bind_param("i", $auth_user_id); $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        if (!empty($row['fyp_name'])) $user_name = $row['fyp_name'];
        if (!empty($row['fyp_profileimg'])) $user_avatar = $row['fyp_profileimg'];
    }
    $stmt->close();
}

$menu_items = [
    'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home', 'link' => 'Supervisor_mainpage.php?page=dashboard'],
    'profile'   => ['name' => 'My Profile', 'icon' => 'fa-user', 'link' => 'supervisor_profile.php'],
    'students'  => [
        'name' => 'My Students', 
        'icon' => 'fa-users',
        'sub_items' => [
            'project_requests' => ['name' => 'Project Requests', 'icon' => 'fa-envelope-open-text', 'link' => 'Supervisor_projectreq.php'],
            'student_list'     => ['name' => 'Student List', 'icon' => 'fa-list', 'link' => 'Supervisor_student_list.php'],
        ]
    ],
    'fyp_project' => [
        'name' => 'FYP Project',
        'icon' => 'fa-project-diagram',
        'sub_items' => [
            'propose_project' => ['name' => 'Propose Project', 'icon' => 'fa-plus-circle', 'link' => 'supervisor_purpose.php'],
            'my_projects'     => ['name' => 'My Projects', 'icon' => 'fa-folder-open', 'link' => 'Supervisor_mainpage.php?page=my_projects'],
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
            'view_announcements' => ['name' => 'View History', 'icon' => 'fa-history', 'link' => 'Supervisor_mainpage.php?page=view_announcements'],
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
    <title>Propose New Project - Supervisor</title>
    <link rel="icon" type="image/png" href="image/ladybug.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap');
        
        :root {
            --primary-color: #0056b3; --primary-hover: #004494; --secondary-color: #f8f9fa; --text-color: #333; --border-color: #e0e0e0; 
            --card-shadow: 0 4px 6px rgba(0,0,0,0.05); --bg-color: #f4f6f9; --sidebar-bg: #004085; --sidebar-hover: #003366; --sidebar-text: #e0e0e0;
        }

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
        
        .form-row { display: flex; gap: 20px; }
        .form-group { margin-bottom: 20px; flex: 1; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #444; font-size: 14px; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; box-sizing: border-box; transition: border 0.3s; font-family: inherit; }
        .form-control:focus { border-color: var(--primary-color); outline: none; }
        textarea.form-control { resize: vertical; min-height: 120px; }
        
        .info-note { background-color: #e3effd; padding: 15px; border-radius: 8px; color: #0056b3; font-size: 13px; margin-bottom: 25px; border-left: 4px solid #0056b3; line-height: 1.5; }

        .btn-submit { background-color: var(--primary-color); color: white; border: none; padding: 12px 30px; border-radius: 6px; font-weight: 600; cursor: pointer; transition: background 0.2s; display: inline-flex; align-items: center; gap: 8px; font-size: 15px; }
        .btn-submit:hover { background-color: var(--primary-hover); }
        .btn-submit.loading { opacity: 0.7; pointer-events: none; }

        @media (max-width: 900px) { .main-content-wrapper { margin-left: 0; width: 100%; } .form-row { flex-direction: column; gap: 0; } }
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
                    if ($linkUrl !== "#") {
                         $separator = (strpos($linkUrl, '?') !== false) ? '&' : '?';
                         $linkUrl .= $separator . "auth_user_id=" . urlencode($auth_user_id);
                    }
                    $hasSubmenu = isset($item['sub_items']);
                ?>
                <li class="menu-item <?php echo $hasActiveChild ? 'open' : ''; ?>">
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
            <div class="welcome-text"><h1>Propose New Project</h1><p>Create a new project for students to apply.</p></div>
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
            <div class="section-title"><h2><i class="fa fa-plus-circle"></i> Project Details</h2></div>

            <div class="info-note">
                <i class="fa fa-info-circle"></i> 
                <strong>Project Status:</strong> All new projects are set to "<strong>Open</strong>" by default. 
                <br>
                <strong>Contact Info:</strong> Your name and email (<?php echo htmlspecialchars($user_name); ?>) will be automatically linked to this project upon submission.
            </div>
            
            <form id="proposeForm" onsubmit="submitForm(event)">
                <input type="hidden" name="ajax_propose_project" value="true">
                <input type="hidden" name="auth_user_id" value="<?php echo $auth_user_id; ?>">
                
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
                        <input type="text" id="course_req" name="course_req" class="form-control" value="FIST" readonly style="background-color: #e9ecef; cursor: not-allowed;">
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

                <button type="submit" class="btn-submit" id="submitBtn"><i class="fa fa-paper-plane"></i> Submit Proposal</button>
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

        function submitForm(event) {
            event.preventDefault();
            const btn = document.getElementById('submitBtn');
            btn.classList.add('loading');
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Submitting...';

            const formData = new FormData(document.getElementById('proposeForm'));

            fetch('Supervisor_purpose.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    document.getElementById('proposeForm').reset();
                    // Optional: Redirect or just stay on page
                } else {
                    alert(data.message);
                }
                btn.classList.remove('loading');
                btn.innerHTML = '<i class="fa fa-paper-plane"></i> Submit Proposal';
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
                btn.classList.remove('loading');
                btn.innerHTML = '<i class="fa fa-paper-plane"></i> Submit Proposal';
            });
        }
    </script>
</body>
</html>