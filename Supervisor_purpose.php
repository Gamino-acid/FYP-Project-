<?php
include("connect.php");

$auth_user_id = $_GET['auth_user_id'] ?? null;
$current_page = 'propose_project';

if (!$auth_user_id) { header("location: login.php"); exit; }

$user_name = "Supervisor"; 
$user_avatar = "image/user.png"; 
$my_staff_id = ""; 

if (isset($conn)) {
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
}

$academic_years = [];
if (isset($conn)) {
    $ay_res = $conn->query("SELECT * FROM academic_year ORDER BY fyp_acdyear DESC");
    while ($row = $ay_res->fetch_assoc()) {
        $academic_years[] = $row;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax_propose'])) {
    header('Content-Type: application/json');
    
    if (empty($my_staff_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Staff ID not found.']);
        exit;
    }

    $title = $_POST['project_title'];
    $category = $_POST['project_category'];
    $type = $_POST['project_type'];
    $desc = $_POST['description'];
    $tech_req = $_POST['tech_req']; 
    $course_req = $_POST['course_req'];
    $academic_id = $_POST['academic_year'];
    
    $status = 'Open'; 
    $date_now = date('Y-m-d H:i:s');
    
    $sql_insert = "INSERT INTO project (fyp_projecttitle, fyp_projectcat, fyp_projecttype, fyp_description, fyp_requirement, fyp_coursereq, fyp_projectstatus, fyp_datecreated, fyp_staffid, fyp_academicid) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                   
    if ($stmt = $conn->prepare($sql_insert)) {
        $stmt->bind_param("sssssssssi", $title, $category, $type, $desc, $tech_req, $course_req, $status, $date_now, $my_staff_id, $academic_id);
        
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Project Proposed Successfully!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'SQL Error: ' . $conn->error]);
    }
    exit;
}

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
            'propose_assignment' => ['name' => 'Propose Assignment', 'icon' => 'fa-tasks', 'link' => 'supervisor_assignment_purpose.php'],
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
    <title>Propose Project</title>
    <link rel="icon" type="image/png" href="image/ladybug.png">
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
            --secondary-color: #f8f9fa;
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
            --secondary-color: #a0a0a0;
            --sidebar-bg: #0d1117;
            --sidebar-hover: #161b22;
            --sidebar-text: #c9d1d9;
            --card-shadow: 0 4px 6px rgba(0,0,0,0.3);
            --border-color: #333;
            --slot-bg: #2d2d2d;
        }

        body { font-family: 'Poppins', sans-serif; margin: 0; background-color: var(--bg-color); color: var(--text-color); min-height: 100vh; display: flex; overflow-x: hidden; transition: background-color 0.3s, color 0.3s; }

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

        .main-content-wrapper { margin-left: 60px; flex: 1; padding: 20px; width: calc(100% - 60px); transition: margin-left .05s linear; }

        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; background: var(--card-bg); padding: 20px; border-radius: 12px; box-shadow: var(--card-shadow); transition: background 0.3s; }
        .welcome-text h1 { margin: 0; font-size: 24px; color: var(--primary-color); font-weight: 600; }
        .welcome-text p { margin: 5px 0 0; color: var(--text-color); font-size: 14px; opacity: 0.8; }
        .logo-section { display: flex; align-items: center; gap: 12px; }
        .logo-img { height: 40px; width: auto; background: white; padding: 2px; border-radius: 6px; }
        .system-title { font-size: 20px; font-weight: 600; color: var(--primary-color); letter-spacing: 0.5px; }

        .user-section { display: flex; align-items: center; gap: 10px; }
        .user-badge { font-size: 13px; color: var(--text-secondary); background: var(--slot-bg); padding: 5px 10px; border-radius: 20px; }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
        .user-avatar-placeholder { width: 40px; height: 40px; border-radius: 50%; background: #0056b3; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; }

        .form-card { background: var(--card-bg); padding: 30px; border-radius: 12px; box-shadow: var(--card-shadow); transition: background 0.3s; }
        .section-title { border-bottom: 2px solid var(--border-color); padding-bottom: 15px; margin-bottom: 25px; }
        .section-title h2 { margin: 0; color: var(--primary-color); font-size: 18px; display: flex; align-items: center; gap: 10px; }

        .form-row { display: flex; gap: 20px; }
        .form-group { margin-bottom: 20px; flex: 1; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-color); font-size: 14px; opacity: 0.9; }
        .form-control { width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 14px; box-sizing: border-box; transition: border 0.3s; font-family: inherit; background: var(--card-bg); color: var(--text-color); }
        .form-control:focus { border-color: var(--primary-color); outline: none; }
        textarea.form-control { resize: vertical; min-height: 120px; }

        .info-note { background-color: #e3effd; padding: 15px; border-radius: 8px; color: #0056b3; font-size: 13px; margin-bottom: 25px; border-left: 4px solid #0056b3; line-height: 1.5; }
        
        .btn-submit { background-color: var(--primary-color); color: white; border: none; padding: 12px 30px; border-radius: 6px; font-weight: 600; cursor: pointer; transition: background 0.2s; display: inline-flex; align-items: center; gap: 8px; font-size: 15px; }
        .btn-submit:hover { background-color: var(--primary-hover); }
        .btn-submit.loading { opacity: 0.7; pointer-events: none; }

        .char-count { font-size: 12px; color: var(--text-secondary); text-align: right; margin-top: 5px; }

        .theme-toggle {
            cursor: pointer; padding: 8px; border-radius: 50%;
            background: var(--slot-bg); border: 1px solid var(--border-color);
            color: var(--text-color); display: flex; align-items: center;
            justify-content: center; width: 35px; height: 35px; margin-right: 15px;
        }
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
            <div class="welcome-text"><h1>Propose New Project</h1><p>Create a new FYP proposal for students to view.</p></div>
            <div class="logo-section"><img src="image/ladybug.png" alt="Logo" class="logo-img"><span class="system-title">FYP Portal</span></div>
            <div class="user-section">
                <button class="theme-toggle" onclick="toggleDarkMode()" title="Toggle Dark Mode">
                    <img id="theme-icon" src="image/moon-solid-full.svg" alt="Toggle Theme">
                </button>
                <span class="user-badge">Supervisor</span>
                <?php if(!empty($user_avatar) && $user_avatar != 'image/user.png'): ?>
                    <img src="<?php echo htmlspecialchars($user_avatar); ?>" class="user-avatar" alt="User Avatar">
                <?php else: ?>
                    <div class="user-avatar-placeholder"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-card">
            <div class="section-title"><h2><i class="fa fa-lightbulb"></i> Project Details</h2></div>

            <div class="info-note">
                <i class="fa fa-info-circle"></i> 
                <strong>Important:</strong> Please ensure the project description is clear and the technical requirements are feasible for students.
            </div>
            
            <form id="proposeForm" onsubmit="submitForm(event)">
                <input type="hidden" name="ajax_propose" value="true">
                
                <div class="form-group">
                    <label for="project_title">Project Title <span style="color:red">*</span></label>
                    <input type="text" id="project_title" name="project_title" class="form-control" placeholder="Enter project title" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="project_category">Category <span style="color:red">*</span></label>
                        <select id="project_category" name="project_category" class="form-control" required>
                            <option value="" disabled selected>-- Select Category --</option>
                            <option value="Development">Development</option>
                            <option value="Research">Research</option>
                            <option value="Hybrid">Hybrid</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="project_type">Project Type <span style="color:red">*</span></label>
                        <select id="project_type" name="project_type" class="form-control" required>
                            <option value="" disabled selected>-- Select Type --</option>
                            <option value="Individual">Individual</option>
                            <option value="Group">Group</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="academic_year">Academic Year <span style="color:red">*</span></label>
                    <select id="academic_year" name="academic_year" class="form-control" required>
                        <option value="" disabled selected>-- Select Intake --</option>
                        <?php foreach ($academic_years as $ay): ?>
                            <option value="<?php echo $ay['fyp_academicid']; ?>">
                                <?php echo htmlspecialchars($ay['fyp_acdyear'] . " (" . $ay['fyp_intake'] . ")"); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="description">Description <span style="color:red">*</span></label>
                    <textarea id="description" name="description" class="form-control" placeholder="Describe the project scope and objectives..." maxlength="2000" oninput="updateCount('description', 'descCount', 350)" required></textarea>
                    <div class="char-count" id="descCount">0 / 350 words</div>
                </div>

                <div class="form-group">
                    <label for="tech_req">Technical Requirements <span style="color:red">*</span></label>
                    <textarea id="tech_req" name="tech_req" class="form-control" placeholder="List required skills (e.g. PHP, Python, AI, IoT)..." maxlength="1200" oninput="updateCount('tech_req', 'techCount', 200)" required></textarea>
                    <div class="char-count" id="techCount">0 / 200 words</div>
                </div>
                
                <div class="form-group">
                    <label for="course_req">Course Requirement</label>
                    <input type="text" id="course_req" name="course_req" class="form-control" placeholder="e.g. Software Engineering Only (Optional)">
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

        function updateCount(inputId, countId, limit) {
            const input = document.getElementById(inputId);
            const counter = document.getElementById(countId);
            const words = input.value.trim().split(/\s+/).filter(word => word.length > 0).length;
            
            counter.innerText = `${words} / ${limit} words`;
            
            if (words > limit) {
                counter.style.color = 'red';
                input.style.borderColor = 'red';
            } else {
                counter.style.color = 'var(--text-secondary)';
                input.style.borderColor = '';
            }
        }

        function submitForm(event) {
            event.preventDefault();
            
            const descWords = document.getElementById('description').value.trim().split(/\s+/).filter(w => w.length > 0).length;
            const techWords = document.getElementById('tech_req').value.trim().split(/\s+/).filter(w => w.length > 0).length;

            if (descWords > 350) {
                Swal.fire("Limit Exceeded", "Description cannot exceed 350 words.", "warning");
                return;
            }
            if (techWords > 200) {
                Swal.fire("Limit Exceeded", "Technical Requirements cannot exceed 200 words.", "warning");
                return;
            }

            const btn = document.getElementById('submitBtn');
            btn.classList.add('loading');
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Processing...';

            const formData = new FormData(document.getElementById('proposeForm'));

            fetch('supervisor_purpose.php?auth_user_id=<?php echo $auth_user_id; ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire({
                        title: "Success!",
                        text: data.message,
                        icon: "success",
                        confirmButtonColor: "#28a745",
                        draggable: true
                    }).then(() => {
                        window.location.href = 'Supervisor_manage_project.php?auth_user_id=<?php echo $auth_user_id; ?>';
                    });
                } else {
                    Swal.fire("Error", data.message, "error");
                }
                btn.classList.remove('loading');
                btn.innerHTML = '<i class="fa fa-paper-plane"></i> Submit Proposal';
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire("Error", "An unexpected error occurred.", "error");
                btn.classList.remove('loading');
                btn.innerHTML = '<i class="fa fa-paper-plane"></i> Submit Proposal';
            });
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