<?php
// ====================================================
// std_projectreg.php - Logic: Insert into Request ONLY
// ====================================================
include("connect.php");

// 1. AJAX Search (保留之前的逻辑，这里省略以节省篇幅，跟之前一样)
if (isset($_GET['action'])) {
    // ... (保留之前的 AJAX 代码: search_students, search_supervisors) ...
    // 如果你需要我完整贴出这部分请告诉我
    header('Content-Type: application/json');
    $keyword = $_GET['keyword'] ?? '';
    if (strlen($keyword) < 1) { echo json_encode([]); exit; }
    $keyword = "%" . $keyword . "%";

    if ($_GET['action'] == 'search_supervisors') {
        $sql = "SELECT fyp_supervisorid, fyp_name FROM supervisor WHERE fyp_name LIKE ? LIMIT 5";
        $result = [];
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $keyword);
            $stmt->execute(); $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $result[] = ['id' => $row['fyp_supervisorid'], 'name' => $row['fyp_name']];
            }
        }
        echo json_encode($result); exit;
    }
    // 注意：在这个流程下，Teammate Search 暂时用不到了，因为申请通过后才能拉人
    // 但为了不报错，可以保留 search_students 的接口
    if ($_GET['action'] == 'search_students') {
         // ... (保留原代码) ...
         echo json_encode([]); exit; 
    }
}

// 2. Auth Check
$auth_user_id = $_GET['auth_user_id'] ?? null;
if (!$auth_user_id) { header("location: login.php"); exit; }

// 3. Get Student Data
$stud_data = [];
$user_name = 'Student';
if (isset($conn)) {
    $sql_user = "SELECT fyp_username FROM `USER` WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_user)) { $stmt->bind_param("i", $auth_user_id); $stmt->execute(); $res=$stmt->get_result(); if($row=$res->fetch_assoc()) $user_name=$row['fyp_username']; $stmt->close(); }
    
    $sql_stud = "SELECT * FROM STUDENT WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_stud)) { $stmt->bind_param("i", $auth_user_id); $stmt->execute(); $res=$stmt->get_result(); if($row=$res->fetch_assoc()){ $stud_data=$row; if(!empty($row['fyp_studname'])) $user_name=$row['fyp_studname']; } else { $stud_data=['fyp_studid'=>'', 'fyp_studname'=>$user_name, 'fyp_studfullid'=>'', 'fyp_projectid'=>null, 'fyp_profileimg'=>'']; } $stmt->close(); }
}
$my_stud_id = $stud_data['fyp_studid'];

// ----------------------------------------------------
// 4. Registration Logic (POST) -> Modified to use project_request
// ----------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register_project_confirm'])) {
    
    $proj_id = $_POST['project_id'];
    $selected_type = $_POST['selected_type']; 
    $selected_sv_id = $_POST['selected_sv_id'];
    $date_now = date('Y-m-d H:i:s');

    // Validation
    if (empty($selected_sv_id)) {
        echo "<script>alert('Please select a supervisor!'); window.history.back();</script>"; exit;
    }

    // 1. Check if already registered (Active Project)
    $check_reg = "SELECT fyp_regid FROM fyp_registration WHERE fyp_studid = ? LIMIT 1";
    $is_registered = false;
    if ($stmt = $conn->prepare($check_reg)) {
        $stmt->bind_param("s", $my_stud_id);
        $stmt->execute(); $stmt->store_result();
        if ($stmt->num_rows > 0) $is_registered = true;
    }

    // 2. Check if there is already a PENDING REQUEST
    $check_req = "SELECT fyp_requestid FROM project_request WHERE fyp_studid = ? AND fyp_requeststatus = 'Pending' LIMIT 1";
    $has_pending = false;
    if ($stmt = $conn->prepare($check_req)) {
        $stmt->bind_param("s", $my_stud_id);
        $stmt->execute(); $stmt->store_result();
        if ($stmt->num_rows > 0) $has_pending = true;
    }

    if ($is_registered) {
        echo "<script>alert('You already have an active project!'); window.location.href='std_projectreg.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
    } elseif ($has_pending) {
        echo "<script>alert('You already have a Pending Request. Please check your Status page.'); window.location.href='std_projectreg.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
    } else {
        
        // --- NEW LOGIC: Insert into project_request ONLY ---
        // We do NOT insert into PAIRING or REGISTRATION yet.
        // We assume 'fyp_academicid' is not used or same as supervisor, leaving it null for now based on your logic.
        
        $sql = "INSERT INTO project_request (fyp_studid, fyp_supervisorid, fyp_projectid, fyp_requeststatus, fyp_datecreated) VALUES (?, ?, ?, 'Pending', ?)";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ssis", $my_stud_id, $selected_sv_id, $proj_id, $date_now);
            
            if ($stmt->execute()) {
                // Success
                if ($selected_type == 'Group') {
                    echo "<script>alert('Request submitted! Once your supervisor APPROVES, you can invite your teammates in the Team Status page.'); window.location.href='std_projectreg.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
                } else {
                    echo "<script>alert('Request submitted to Supervisor! Please wait for approval.'); window.location.href='std_projectreg.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
                }
            } else {
                echo "<script>alert('Error submitting request.');</script>";
            }
            $stmt->close();
        }
    }
}

// 5. Get Project List
$available_projects = [];
$sql = "SELECT * FROM PROJECT"; 
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) {
    $available_projects[] = $row;
}

// 6. Define Menu (Updated Sidebar Item Name)
$current_page = 'group_setup';
$menu_items = [
    'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home', 'link' => 'Student_mainpage.php?page=dashboard'],
    'profile' => ['name' => 'My Profile', 'icon' => 'fa-user', 'link' => 'std_profile.php'],
    'project_mgmt' => [
        'name' => 'Final Year Project',
        'icon' => 'fa-project-diagram',
        'sub_items' => [
            'group_setup' => ['name' => 'Project Registration', 'icon' => 'fa-users', 'link' => 'std_projectreg.php'], 
            // 名字改了一下，更符合你现在的逻辑
            'team_invitations' => ['name' => 'Request & Team Status', 'icon' => 'fa-tasks', 'link' => 'Student_mainpage.php?page=team_invitations'],
            'proposals' => ['name' => 'Proposal Submission', 'icon' => 'fa-file-alt', 'link' => 'Student_mainpage.php?page=proposals'],
            'doc_submission' => ['name' => 'Document Upload', 'icon' => 'fa-cloud-upload-alt', 'link' => 'Student_mainpage.php?page=doc_submission'],
        ]
    ],
    'appointments' => ['name' => 'Appointment', 'icon' => 'fa-calendar-check', 'sub_items' => ['book_session' => ['name' => 'Make Appointment', 'icon' => 'fa-plus-circle', 'link' => 'Student_mainpage.php?page=appointments']]],
    'grades' => ['name' => 'My Grades', 'icon' => 'fa-star', 'link' => 'Student_mainpage.php?page=grades'],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Registration</title>
    <?php $favicon = !empty($stud_data['fyp_profileimg']) ? $stud_data['fyp_profileimg'] : "image/user.png"; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* CSS Same as before */
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap');
        :root { --primary-color: #0056b3; --primary-hover: #004494; --secondary-color: #f4f4f9; --text-color: #333; --border-color: #e0e0e0; --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); --gradient-start: #eef2f7; --gradient-end: #ffffff; --sidebar-width: 260px; --student-accent: #007bff; }
        body { font-family: 'Poppins', sans-serif; margin: 0; background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end)); color: var(--text-color); min-height: 100vh; display: flex; flex-direction: column; }
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
        .submenu { list-style: none; padding: 0; margin: 0; background-color: #fafafa; display: none; }
        .menu-item.has-active-child .submenu, .menu-item:hover .submenu { display: block; }
        .submenu .menu-link { padding-left: 58px; font-size: 14px; padding-top: 10px; padding-bottom: 10px; }
        .main-content { flex: 1; display: flex; flex-direction: column; gap: 20px; }
        .welcome-card { background: #fff; padding: 30px; border-radius: 12px; box-shadow: var(--card-shadow); border-left: 5px solid var(--primary-color); }
        .page-title { font-size: 24px; margin: 0 0 10px 0; color: var(--text-color); }
        .project-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 25px; }
        .project-card { border: 1px solid #eee; border-radius: 10px; padding: 25px; background: #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.03); display: flex; flex-direction: column; justify-content: space-between; border-top: 4px solid var(--primary-color); transition: transform 0.3s, box-shadow 0.3s; position: relative; }
        .project-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.08); }
        .project-title { font-size: 18px; font-weight: 600; color: #333; margin-bottom: 10px; line-height: 1.4; }
        .project-meta { font-size: 13px; color: #777; margin-bottom: 15px; display: flex; flex-wrap: wrap; gap: 10px; }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .badge-cat { background: #e3f2fd; color: #1565c0; }
        .badge-type { background: #f3e5f5; color: #7b1fa2; }
        .project-desc { font-size: 14px; color: #555; margin-bottom: 20px; line-height: 1.6; flex-grow: 1; border-top: 1px solid #f0f0f0; padding-top: 15px; }
        .btn-select-proj { background-color: var(--primary-color); color: white; border: none; padding: 12px; border-radius: 8px; cursor: pointer; width: 100%; font-weight: 600; transition: background-color 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-select-proj:hover { background-color: var(--primary-hover); }
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; backdrop-filter: blur(2px); justify-content: center; align-items: center; opacity: 0; transition: opacity 0.3s ease; }
        .modal-overlay.show { display: flex; opacity: 1; }
        .modal-box { background: #fff; padding: 35px; border-radius: 12px; width: 90%; max-width: 600px; text-align: center; box-shadow: 0 15px 35px rgba(0,0,0,0.2); transform: scale(0.9); transition: transform 0.3s ease; }
        .modal-overlay.show .modal-box { transform: scale(1); }
        .modal-title { font-size: 20px; font-weight: 600; margin-bottom: 10px; color: #333; }
        .modal-actions { display: flex; gap: 15px; justify-content: center; margin-top: 20px; }
        .btn-confirm { background: var(--primary-color); color: white; padding: 12px 25px; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; font-size: 15px; }
        .btn-cancel { background: #f1f1f1; color: #444; padding: 12px 25px; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; font-size: 15px; transition: background 0.2s; }
        .modal-info-grid { display: grid; grid-template-columns: 1fr; gap: 15px; text-align: left; margin-bottom: 20px; background: #f8f9fa; padding: 15px; border-radius: 8px; }
        .info-group label { display: block; font-size: 12px; color: #888; margin-bottom: 3px; }
        .info-group span { font-weight: 600; color: #333; font-size: 14px; }
        .search-container { position: relative; margin-bottom: 5px; }
        .search-results { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #ddd; border-top: none; border-radius: 0 0 8px 8px; max-height: 200px; overflow-y: auto; z-index: 1000; box-shadow: 0 4px 6px rgba(0,0,0,0.1); display: none; }
        .search-item { padding: 10px 15px; cursor: pointer; border-bottom: 1px solid #f0f0f0; transition: background 0.2s; display: flex; justify-content: space-between; align-items: center; }
        .search-item:hover { background-color: #f9f9f9; color: var(--primary-color); }
        .selected-teammates { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
        .teammate-tag { background-color: #e3effd; color: var(--primary-color); padding: 5px 12px; border-radius: 20px; font-size: 13px; font-weight: 500; display: flex; align-items: center; gap: 8px; }
        .form-select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-family: inherit; }
        .form-input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-family: inherit; box-sizing: border-box; }
        @media (max-width: 900px) { .layout-container { flex-direction: column; } .sidebar { width: 100%; min-height: auto; } }
    </style>
</head>
<body>

    <header class="topbar">
        <div class="logo"> FYP System</div>
        <div class="topbar-right">
            <span class="user-name-display"><?php echo htmlspecialchars($user_name); ?></span>
            <div class="user-avatar-circle"><img src="<?php echo $favicon; ?>" alt="User Avatar"></div>
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
                        if (strpos($linkUrl, '.php') !== false) {
                             $separator = (strpos($linkUrl, '?') !== false) ? '&' : '?';
                             $linkUrl .= $separator . "auth_user_id=" . urlencode($auth_user_id);
                        } elseif (strpos($linkUrl, '?') === 0) {
                             $linkUrl .= "&auth_user_id=" . urlencode($auth_user_id);
                        }
                    ?>
                    <li class="menu-item <?php echo $hasActiveChild ? 'has-active-child' : ''; ?>">
                        <a href="<?php echo $linkUrl; ?>" class="menu-link <?php echo $isActive ? 'active' : ''; ?>">
                            <span class="menu-icon"><i class="fa <?php echo $item['icon']; ?>"></i></span>
                            <?php echo $item['name']; ?>
                        </a>
                        <?php if (isset($item['sub_items'])): ?>
                            <ul class="submenu">
                                <?php foreach ($item['sub_items'] as $sub_key => $sub_item): 
                                    $subLinkUrl = isset($sub_item['link']) ? $sub_item['link'] : "#";
                                    if (strpos($subLinkUrl, '.php') !== false) {
                                        $separator = (strpos($subLinkUrl, '?') !== false) ? '&' : '?';
                                        $subLinkUrl .= $separator . "auth_user_id=" . urlencode($auth_user_id);
                                    } elseif (strpos($subLinkUrl, '?') === 0) {
                                        $subLinkUrl .= "&auth_user_id=" . urlencode($auth_user_id);
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
            <div class="welcome-card">
                <h1 class="page-title">Project Registration</h1>
                <p style="color: #666; margin: 0;">Select a project topic and choose your supervisor.</p>
            </div>

            <div class="project-grid">
                <?php if (count($available_projects) > 0): ?>
                    <?php foreach ($available_projects as $proj): ?>
                        <div class="project-card">
                            <div>
                                <div class="project-title"><?php echo htmlspecialchars($proj['fyp_projecttitle']); ?></div>
                                <div class="project-meta">
                                    <span class="badge badge-cat"><?php echo htmlspecialchars($proj['fyp_projectcat']); ?></span>
                                    <?php $statusColor = ($proj['fyp_projectstatus'] == 'Taken') ? '#dc3545' : '#28a745'; ?>
                                    <span class="badge" style="background-color: <?php echo $statusColor; ?>; color: white;">
                                        <?php echo htmlspecialchars($proj['fyp_projectstatus']); ?>
                                    </span>
                                </div>
                                <div class="project-desc"><?php echo nl2br(htmlspecialchars($proj['fyp_description'])); ?></div>
                            </div>
                            <button type="button" class="btn-select-proj" 
                                onclick="initRegistration(
                                    '<?php echo $proj['fyp_projectid']; ?>', 
                                    '<?php echo addslashes($proj['fyp_projecttitle']); ?>', 
                                    '<?php echo addslashes($proj['fyp_description']); ?>', 
                                    '<?php echo $proj['fyp_projectstatus']; ?>'
                                )">
                                <i class="fa fa-check-circle"></i> Select Topic
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">No projects available.</div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <div id="registerModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-icon"><i class="fa fa-file-contract"></i></div>
            <div class="modal-title">Confirm Selection</div>
            
            <form method="POST" id="regForm">
                <input type="hidden" name="project_id" id="modProjId">
                <input type="hidden" name="selected_sv_id" id="selectedSvId">

                <div class="modal-info-grid">
                    <div class="info-group"><label>Project Title</label><span id="modTitle"></span></div>
                    <div class="info-group"><label>Description</label><span id="modDesc" style="font-weight:400; font-size:13px;"></span></div>
                    
                    <div class="info-group" style="border-top: 1px solid #eee; padding-top: 10px;">
                        <label style="margin-bottom: 5px;">Select Type</label>
                        <select name="selected_type" id="typeSelect" class="form-select">
                            <option value="Individual">Individual</option>
                            <option value="Group">Group</option>
                        </select>
                    </div>

                    <div class="info-group">
                        <label style="margin-bottom: 5px;">Select Supervisor</label>
                        <div class="search-container">
                            <input type="text" id="svSearchInput" class="form-input" placeholder="Search lecturer name..." autocomplete="off">
                            <div id="svSearchResults" class="search-results"></div>
                        </div>
                        <div id="svSelectedDisplay" style="font-size: 13px; font-weight: 600; color: var(--primary-color); display:none;">
                            Selected: <span id="svNameText"></span> <i class="fa fa-times" style="color:red; cursor:pointer;" onclick="clearSv()"></i>
                        </div>
                    </div>
                    
                    <div id="groupNote" style="display:none; font-size:12px; color:#e67e22; margin-top:5px;">
                        <i class="fa fa-info-circle"></i> For Group projects, you can invite teammates <b>after</b> your supervisor approves your request.
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeRegModal()">Cancel</button>
                    <button type="submit" name="register_project_confirm" class="btn-confirm">Submit Request</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const regModal = document.getElementById('registerModal');
        const svInput = document.getElementById('svSearchInput');
        const svResults = document.getElementById('svSearchResults');
        const svHiddenId = document.getElementById('selectedSvId');
        const typeSelect = document.getElementById('typeSelect');
        const groupNote = document.getElementById('groupNote');

        function initRegistration(id, title, desc, status) {
            if (status.trim() === 'Taken') { alert('This project is TAKEN.'); return; }
            
            document.getElementById('modProjId').value = id;
            document.getElementById('modTitle').innerText = title;
            document.getElementById('modDesc').innerText = desc.substring(0, 100) + '...';
            
            // Reset UI
            typeSelect.value = 'Individual';
            groupNote.style.display = 'none';
            clearSv();
            
            regModal.classList.add('show');
        }

        function closeRegModal() { regModal.classList.remove('show'); }

        // 简单的显示/隐藏提示语
        typeSelect.addEventListener('change', function() {
            if (this.value === 'Group') {
                groupNote.style.display = 'block';
            } else {
                groupNote.style.display = 'none';
            }
        });

        // Supervisor Search Logic (Same as before)
        svInput.addEventListener('keyup', function() {
            const val = this.value;
            if (val.length < 2) { svResults.style.display = 'none'; return; }
            fetch(`std_projectreg.php?action=search_supervisors&keyword=${encodeURIComponent(val)}`)
                .then(res => res.json())
                .then(data => {
                    svResults.innerHTML = '';
                    if (data.length > 0) {
                        svResults.style.display = 'block';
                        data.forEach(sv => {
                            const div = document.createElement('div');
                            div.className = 'search-item';
                            div.innerHTML = `<i class="fa fa-user-tie"></i> ${sv.name}`;
                            div.onclick = () => selectSv(sv);
                            svResults.appendChild(div);
                        });
                    } else { svResults.style.display = 'none'; }
                });
        });

        function selectSv(sv) {
            svHiddenId.value = sv.id;
            document.getElementById('svNameText').innerText = sv.name;
            document.getElementById('svSelectedDisplay').style.display = 'block';
            svInput.style.display = 'none';
            svResults.style.display = 'none';
        }

        function clearSv() {
            svHiddenId.value = '';
            svInput.value = '';
            svInput.style.display = 'block';
            document.getElementById('svSelectedDisplay').style.display = 'none';
        }

        window.onclick = function(e) { if (e.target.classList.contains('modal-overlay')) closeRegModal(); }
    </script>
</body>
</html>