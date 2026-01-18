<?php
// ====================================================
// supervisor_assignment_purpose.php - Propose Assignment (AJAX + UI Updated)
// ====================================================

include("connect.php");

// 1. AJAX Handler for Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax_propose_assignment'])) {
    header('Content-Type: application/json');
    $auth_user_id = $_POST['auth_user_id'] ?? null;

    // Fetch SV Info
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

    if ($stmt_insert = $conn->prepare($sql_insert)) {
        $stmt_insert->bind_param("ssssissss", $my_staff_id, $a_title, $a_desc, $a_deadline, $weightage, $a_type, $a_status, $date_now, $final_target);

        if ($stmt_insert->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Assignment proposed successfully!']);
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
$current_page = 'propose_assignment'; 

if (!$auth_user_id) { header("location: login.php"); exit; }

// Fetch SV Info & Students
$user_name = "Supervisor"; 
$user_avatar = "image/user.png"; 
$my_staff_id = ""; 
$my_groups = [];      
$my_individuals = []; 

if (isset($conn)) {
    // SV Info
    $stmt = $conn->prepare("SELECT * FROM supervisor WHERE fyp_userid = ?");
    $stmt->bind_param("i", $auth_user_id); $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $my_staff_id = $row['fyp_staffid'];
        if (!empty($row['fyp_name'])) $user_name = $row['fyp_name'];
        if (!empty($row['fyp_profileimg'])) $user_avatar = $row['fyp_profileimg'];
    }
    $stmt->close();

    // Students for Dropdown
    if (!empty($my_staff_id)) {
        $sql_my_students = "SELECT s.fyp_studid, s.fyp_studname, s.fyp_group 
                            FROM fyp_registration r
                            JOIN student s ON r.fyp_studid = s.fyp_studid
                            WHERE r.fyp_staffid = ? AND (r.fyp_archive_status = 'Active' OR r.fyp_archive_status IS NULL)"; 
        if ($stmt = $conn->prepare($sql_my_students)) {
            $stmt->bind_param("s", $my_staff_id); $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                if ($row['fyp_group'] == 'Individual') {
                    $my_individuals[$row['fyp_studid']] = $row['fyp_studname'];
                } else {
                    $g_sql = "SELECT group_id, group_name FROM student_group WHERE leader_id = '" . $row['fyp_studid'] . "' LIMIT 1";
                    $g_res = $conn->query($g_sql);
                    if ($g_row = $g_res->fetch_assoc()) {
                        $my_groups[$g_row['group_id']] = $g_row['group_name'];
                    } else {
                        $m_sql = "SELECT g.group_id, g.group_name FROM group_request gr JOIN student_group g ON gr.group_id = g.group_id WHERE gr.invitee_id = '" . $row['fyp_studid'] . "' AND gr.request_status = 'Accepted' LIMIT 1";
                        $m_res = $conn->query($m_sql);
                        if ($m_row = $m_res->fetch_assoc()) $my_groups[$m_row['group_id']] = $m_row['group_name'];
                    }
                }
            }
            $stmt->close();
        }
    }
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
    <title>Propose Assignment</title>
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
        .target-select-container { display: none; margin-top: 10px; background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px dashed #ced4da; }

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
            <div class="welcome-text"><h1>Propose New Assignment</h1><p>Create a task or assignment for your supervised students.</p></div>
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
            <div class="section-title"><h2><i class="fa fa-tasks"></i> Assignment Details</h2></div>

            <div class="info-note">
                <i class="fa fa-info-circle"></i> 
                <strong>Assessment Category:</strong> Selecting a category will automatically set the recommended weightage.
            </div>
            
            <form id="assignForm" onsubmit="submitForm(event)">
                <input type="hidden" name="ajax_propose_assignment" value="true">
                <input type="hidden" name="auth_user_id" value="<?php echo $auth_user_id; ?>">
                
                <div class="form-group">
                    <label for="assessment_category">Assessment Category <span style="color:red">*</span></label>
                    <select id="assessment_category" class="form-control" onchange="autoFillWeightage(this.value)">
                        <option value="" disabled selected>-- Select Category --</option>
                        <option value="Proposal_10">Project Proposal (10%)</option>
                        <option value="Interim_20">Interim / Progress Report (20%)</option>
                        <option value="Final_40">Final Report (40%)</option>
                        <option value="Presentation_30">Presentation (30%)</option>
                        <option value="Custom">Custom Assignment</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="assignment_title">Assignment Title <span style="color:red">*</span></label>
                    <input type="text" id="assignment_title" name="assignment_title" class="form-control" placeholder="Enter assignment title (e.g. Chapter 1 Draft)" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="assignment_type">Target Type <span style="color:red">*</span></label>
                        <select id="assignment_type" name="assignment_type" class="form-control" onchange="toggleTargetList(this.value)">
                            <option value="" disabled selected>-- Select Type --</option>
                            <option value="Individual">Individual Students</option>
                            <option value="Group">Group Project</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="weightage">Weightage (%) <span style="color:red">*</span></label>
                        <input type="number" id="weightage" name="weightage" class="form-control" placeholder="0-100" min="0" max="100" required readonly style="background-color:#e9ecef;">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="deadline">Deadline <span style="color:red">*</span></label>
                    <input type="datetime-local" id="deadline" name="deadline" class="form-control" required>
                </div>
                
                <div id="target_container" class="target-select-container">
                    <label for="target_selection" style="font-size:13px; font-weight:600; color:#555; margin-bottom:5px; display:block;">Select Specific Target <span style="color:red">*</span></label>
                    <select id="target_selection" name="target_selection" class="form-control">
                    </select>
                </div>

                <div class="form-group" style="margin-top:20px;">
                    <label for="assignment_description">Description / Instructions <span style="color:red">*</span></label>
                    <textarea id="assignment_description" name="assignment_description" class="form-control" placeholder="Describe the task requirements..." required></textarea>
                </div>

                <button type="submit" class="btn-submit" id="submitBtn"><i class="fa fa-paper-plane"></i> Publish Assignment</button>
            </form>
        </div>
    </div>

    <script>
        const myGroups = <?php echo json_encode($my_groups); ?>;
        const myIndividuals = <?php echo json_encode($my_individuals); ?>;

        function toggleSubmenu(element) {
            const menuItem = element.parentElement;
            const isOpen = menuItem.classList.contains('open');
            document.querySelectorAll('.menu-item').forEach(item => { if (item !== menuItem) item.classList.remove('open'); });
            if (isOpen) menuItem.classList.remove('open'); else menuItem.classList.add('open');
        }

        function autoFillWeightage(val) {
            const wInput = document.getElementById('weightage');
            const tInput = document.getElementById('assignment_title');
            
            if (val === 'Custom') {
                wInput.readOnly = false;
                wInput.style.backgroundColor = '#fff';
                wInput.value = '';
                wInput.focus();
            } else {
                const parts = val.split('_');
                const titleKey = parts[0];
                const weight = parts[1];
                
                wInput.readOnly = true;
                wInput.style.backgroundColor = '#e9ecef';
                wInput.value = weight;
                
                if (titleKey === 'Proposal') tInput.value = "Project Proposal Submission";
                else if (titleKey === 'Interim') tInput.value = "Interim / Progress Report";
                else if (titleKey === 'Final') tInput.value = "Final Report Submission";
                else if (titleKey === 'Presentation') tInput.value = "Presentation Slides / Materials";
            }
        }

        function toggleTargetList(type) {
            const container = document.getElementById('target_container');
            const select = document.getElementById('target_selection');
            
            select.innerHTML = '';
            container.style.display = 'block';

            if (type === 'Group') {
                const allOpt = document.createElement('option');
                allOpt.value = 'all_groups';
                allOpt.text = 'All My Groups';
                select.appendChild(allOpt);

                for (const [id, name] of Object.entries(myGroups)) {
                    const opt = document.createElement('option');
                    opt.value = id; 
                    opt.text = `Group: ${name}`;
                    select.appendChild(opt);
                }
                
                if (Object.keys(myGroups).length === 0) {
                    const opt = document.createElement('option');
                    opt.text = "No active groups found.";
                    opt.disabled = true;
                    select.appendChild(opt);
                }

            } else if (type === 'Individual') {
                const allOpt = document.createElement('option');
                allOpt.value = 'all_individuals';
                allOpt.text = 'All My Individual Students';
                select.appendChild(allOpt);

                for (const [id, name] of Object.entries(myIndividuals)) {
                    const opt = document.createElement('option');
                    opt.value = id; 
                    opt.text = `Student: ${name} (${id})`;
                    select.appendChild(opt);
                }

                if (Object.keys(myIndividuals).length === 0) {
                    const opt = document.createElement('option');
                    opt.text = "No active individual students found.";
                    opt.disabled = true;
                    select.appendChild(opt);
                }
            } else {
                container.style.display = 'none';
            }
        }

        function submitForm(event) {
            event.preventDefault();
            const btn = document.getElementById('submitBtn');
            btn.classList.add('loading');
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Publishing...';

            const formData = new FormData(document.getElementById('assignForm'));

            fetch('Supervisor_assignment_purpose.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    document.getElementById('assignForm').reset();
                    document.getElementById('target_container').style.display = 'none';
                } else {
                    alert(data.message);
                }
                btn.classList.remove('loading');
                btn.innerHTML = '<i class="fa fa-paper-plane"></i> Publish Assignment';
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
                btn.classList.remove('loading');
                btn.innerHTML = '<i class="fa fa-paper-plane"></i> Publish Assignment';
            });
        }
    </script>
</body>
</html>