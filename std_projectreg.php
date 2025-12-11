<?php
// ====================================================
// std_projectreg.php - Project Registration
// ====================================================
include("connect.php");

// 1. AJAX Student Search
if (isset($_GET['action']) && $_GET['action'] == 'search_students') {
    header('Content-Type: application/json');
    $keyword = $_GET['keyword'] ?? '';
    $current_userid = $_GET['current_userid'] ?? 0;
    
    if (strlen($keyword) < 1) { echo json_encode([]); exit; }

    $keyword = "%" . $keyword . "%";
    $sql = "SELECT fyp_userid, fyp_studname, fyp_studid FROM STUDENT WHERE fyp_studname LIKE ? AND fyp_userid != ? LIMIT 5";
    $students = [];
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("si", $keyword, $current_userid);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $students[] = ['id' => $row['fyp_userid'], 'studid' => $row['fyp_studid'], 'name' => $row['fyp_studname']];
        }
        $stmt->close();
    }
    echo json_encode($students); exit;
}

// 2. Auth Check
$auth_user_id = $_GET['auth_user_id'] ?? null;
if (!$auth_user_id) { header("location: login.php"); exit; }

// 3. Get Student Data (for logic and topbar)
$stud_data = [];
$user_name = 'Student';
if (isset($conn)) {
    $sql_user = "SELECT fyp_username FROM `USER` WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_user)) { $stmt->bind_param("i", $auth_user_id); $stmt->execute(); $res=$stmt->get_result(); if($row=$res->fetch_assoc()) $user_name=$row['fyp_username']; $stmt->close(); }
    
    $sql_stud = "SELECT * FROM STUDENT WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_stud)) { $stmt->bind_param("i", $auth_user_id); $stmt->execute(); $res=$stmt->get_result(); if($row=$res->fetch_assoc()){ $stud_data=$row; if(!empty($row['fyp_studname'])) $user_name=$row['fyp_studname']; } else { $stud_data=['fyp_studid'=>'', 'fyp_studname'=>$user_name, 'fyp_studfullid'=>'', 'fyp_projectid'=>null, 'fyp_profileimg'=>'']; } $stmt->close(); }
}

// 4. Registration Logic (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register_project_confirm'])) {
    
    $proj_id = $_POST['project_id'];
    $proj_type = $_POST['project_type'];
    $my_stud_id = $_POST['my_stud_id']; 
    $teammates_json = $_POST['teammates_data']; 
    
    // Check existing registration
    $is_registered = false;
    $check_sql = "SELECT fyp_regid FROM fyp_registration WHERE fyp_studid = ? LIMIT 1";
    if ($stmt = $conn->prepare($check_sql)) {
        $stmt->bind_param("s", $my_stud_id);
        $stmt->execute(); $stmt->store_result();
        if ($stmt->num_rows > 0) $is_registered = true;
        $stmt->close();
    }

    if ($is_registered) {
        if ($proj_type == 'Individual') {
            $upd_sql = "UPDATE fyp_registration SET fyp_projectid = ? WHERE fyp_studid = ?";
            if ($stmt = $conn->prepare($upd_sql)) {
                $stmt->bind_param("is", $proj_id, $my_stud_id);
                $stmt->execute(); $stmt->close();
                $conn->query("UPDATE STUDENT SET fyp_projectid = $proj_id WHERE fyp_userid = $auth_user_id");
                echo "<script>alert('Project changed successfully!'); window.location.href='std_projectreg.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
            }
        } else {
            echo "<script>alert('You are already registered. Changing Group projects requires contacting the coordinator.'); window.location.href='std_projectreg.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
        }
    } else {
        $pairing_id = 0; 
        $get_pair = "SELECT fyp_pairingid FROM pairing WHERE fyp_projectid = ? LIMIT 1";
        if ($stmt = $conn->prepare($get_pair)) {
            $stmt->bind_param("i", $proj_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) { $pairing_id = $row['fyp_pairingid']; }
            $stmt->close();
        }

        $date_now = date('Y-m-d H:i:s');
        $reg_sql = "INSERT INTO fyp_registration (fyp_studid, fyp_projectid, fyp_pairingid, fyp_datecreated) VALUES (?, ?, ?, ?)";
        if ($stmt = $conn->prepare($reg_sql)) {
            $stmt->bind_param("siis", $my_stud_id, $proj_id, $pairing_id, $date_now);
            $stmt->execute(); $stmt->close();
        }
        
        $conn->query("UPDATE STUDENT SET fyp_projectid = $proj_id WHERE fyp_userid = $auth_user_id");

        if ($proj_type == 'Group' && !empty($teammates_json)) {
            $teammates = json_decode($teammates_json, true); 
            if (is_array($teammates)) {
                $invite_sql = "INSERT INTO student_group (fyp_studid, fyp_pairingid, fyp_request, fyp_acceptance, fyp_datecreated) VALUES (?, ?, ?, 'Pending', ?)";
                $req_val = 1; 
                if ($stmt = $conn->prepare($invite_sql)) {
                    foreach ($teammates as $tm_stud_id) {
                        $stmt->bind_param("siis", $tm_stud_id, $pairing_id, $req_val, $date_now);
                        $stmt->execute();
                    }
                    $stmt->close();
                }
            }
        }
        echo "<script>alert('Registered successfully!'); window.location.href='std_projectreg.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
    }
}

// 5. Get Project List (GET)
$available_projects = [];
$sql = "SELECT p.*, s.fyp_name AS real_supervisor_name 
        FROM PROJECT p
        LEFT JOIN PAIRING pa ON p.fyp_projectid = pa.fyp_projectid
        LEFT JOIN SUPERVISOR s ON pa.fyp_supervisorid = s.fyp_supervisorid";
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) {
    $available_projects[] = $row;
}

// 6. Define Menu
$current_page = 'group_setup';
$menu_items = [
    'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home', 'link' => 'Student_mainpage.php?page=dashboard'],
    'profile' => ['name' => 'My Profile', 'icon' => 'fa-user', 'link' => 'std_profile.php'],
    'project_mgmt' => [
        'name' => 'Final Year Project',
        'icon' => 'fa-project-diagram',
        'sub_items' => [
            'group_setup' => ['name' => 'Project Registration', 'icon' => 'fa-users', 'link' => 'std_projectreg.php'], 
            'team_invitations' => ['name' => 'Team Invitations', 'icon' => 'fa-envelope-open-text', 'link' => 'Student_mainpage.php?page=team_invitations'],
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
        .badge-status { background: #e8f5e9; color: #2e7d32; }
        .project-desc { font-size: 14px; color: #555; margin-bottom: 20px; line-height: 1.6; flex-grow: 1; border-top: 1px solid #f0f0f0; padding-top: 15px; }
        .supervisor-info { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #666; margin-bottom: 20px; }
        .btn-select-proj { background-color: var(--primary-color); color: white; border: none; padding: 12px; border-radius: 8px; cursor: pointer; width: 100%; font-weight: 600; transition: background-color 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-select-proj:hover { background-color: var(--primary-hover); }
        .empty-state { text-align: center; padding: 40px; color: #999; }
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; backdrop-filter: blur(2px); justify-content: center; align-items: center; opacity: 0; transition: opacity 0.3s ease; }
        .modal-overlay.show { display: flex; opacity: 1; }
        .modal-box { background: #fff; padding: 35px; border-radius: 12px; width: 90%; max-width: 600px; text-align: center; box-shadow: 0 15px 35px rgba(0,0,0,0.2); transform: scale(0.9); transition: transform 0.3s ease; }
        .modal-overlay.show .modal-box { transform: scale(1); }
        .modal-icon { font-size: 40px; color: var(--primary-color); margin-bottom: 15px; }
        .modal-title { font-size: 20px; font-weight: 600; margin-bottom: 10px; color: #333; }
        .modal-actions { display: flex; gap: 15px; justify-content: center; margin-top: 20px; }
        .btn-confirm { background: var(--primary-color); color: white; padding: 12px 25px; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; font-size: 15px; }
        .btn-cancel { background: #f1f1f1; color: #444; padding: 12px 25px; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; font-size: 15px; transition: background 0.2s; }
        .btn-cancel:hover { background: #e0e0e0; }
        .modal-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; text-align: left; margin-bottom: 20px; background: #f8f9fa; padding: 15px; border-radius: 8px; }
        .info-group label { display: block; font-size: 12px; color: #888; margin-bottom: 3px; }
        .info-group span { font-weight: 600; color: #333; font-size: 14px; }
        .full-width { grid-column: 1 / -1; }
        .group-section { text-align: left; margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee; }
        .search-container { position: relative; margin-bottom: 15px; }
        .search-results { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #ddd; border-top: none; border-radius: 0 0 8px 8px; max-height: 200px; overflow-y: auto; z-index: 1000; box-shadow: 0 4px 6px rgba(0,0,0,0.1); display: none; }
        .search-item { padding: 10px 15px; cursor: pointer; border-bottom: 1px solid #f0f0f0; transition: background 0.2s; display: flex; justify-content: space-between; align-items: center; }
        .search-item:hover { background-color: #f9f9f9; color: var(--primary-color); }
        .selected-teammates { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 15px; }
        .teammate-tag { background-color: #e3effd; color: var(--primary-color); padding: 5px 12px; border-radius: 20px; font-size: 13px; font-weight: 500; display: flex; align-items: center; gap: 8px; }
        .teammate-tag i.remove-btn { cursor: pointer; color: #d93025; font-size: 14px; }
        .disabled-overlay { opacity: 0.5; pointer-events: none; background: #f0f0f0; }
        @media (max-width: 900px) { .layout-container { flex-direction: column; } .sidebar { width: 100%; min-height: auto; } }
    </style>
</head>
<body>

    <header class="topbar">
        <div class="logo"> FYP System</div>
        <div class="topbar-right">
            <div class="user-profile-summary">
                <span class="user-name-display"><?php echo htmlspecialchars($user_name); ?></span>
                <span class="user-role-badge">Student</span>
            </div>
            <div class="user-avatar-circle">
                <img src="<?php echo $favicon; ?>" alt="User Avatar">
            </div>
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
                <p style="color: #666; margin: 0;">Browse available projects and select your topic for the final year project.</p>
            </div>

            <div class="project-grid">
                <?php if (count($available_projects) > 0): ?>
                    <?php foreach ($available_projects as $proj): ?>
                        <div class="project-card">
                            <div>
                                <div class="project-title"><?php echo htmlspecialchars($proj['fyp_projecttitle']); ?></div>
                                <div class="project-meta">
                                    <span class="badge badge-cat"><?php echo htmlspecialchars($proj['fyp_projectcat']); ?></span>
                                    <span class="badge badge-type"><?php echo htmlspecialchars($proj['fyp_projecttype']); ?></span>
                                    <?php $statusColor = ($proj['fyp_projectstatus'] == 'Taken') ? '#dc3545' : '#28a745'; ?>
                                    <span class="badge" style="background-color: <?php echo $statusColor; ?>; color: white;">
                                        <?php echo htmlspecialchars($proj['fyp_projectstatus']); ?>
                                    </span>
                                </div>
                                <div class="supervisor-info">
                                    <i class="fa fa-user-tie" style="color: var(--primary-color);"></i>
                                    <span>Supervisor: <strong><?php echo htmlspecialchars($proj['real_supervisor_name'] ?? 'N/A'); ?></strong></span>
                                </div>
                                <div class="project-desc"><?php echo nl2br(htmlspecialchars($proj['fyp_description'])); ?></div>
                            </div>
                            <button type="button" class="btn-select-proj" 
                                onclick="initRegistration(
                                    '<?php echo $proj['fyp_projectid']; ?>', 
                                    '<?php echo addslashes($proj['fyp_projecttitle']); ?>', 
                                    '<?php echo $proj['fyp_projecttype']; ?>', 
                                    '<?php echo addslashes($proj['real_supervisor_name'] ?? 'N/A'); ?>', 
                                    '<?php echo addslashes($proj['fyp_description']); ?>', 
                                    '<?php echo $proj['fyp_projectstatus']; ?>'
                                )">
                                <i class="fa fa-check-circle"></i> Select
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
            <div class="modal-title">Project Registration</div>
            <form method="POST" id="regForm">
                <input type="hidden" name="project_id" id="modProjId">
                <input type="hidden" name="project_type" id="modProjType">
                <input type="hidden" name="my_stud_id" value="<?php echo $stud_data['fyp_studid']; ?>">
                <input type="hidden" name="teammates_data" id="teammatesData"> 
                <div class="modal-info-grid">
                    <div class="info-group full-width"><label>Project Title</label><span id="modTitle"></span></div>
                    <div class="info-group full-width"><label>Description</label><span id="modDesc" style="font-weight:400; font-size:13px;"></span></div>
                    <div class="info-group"><label>Type</label><span id="modType"></span></div>
                    <div class="info-group"><label>Supervisor</label><span id="modSv"></span></div>
                </div>
                <div id="groupSection" class="group-section" style="display:none;">
                    <h4>Invite Teammates (Group Project)</h4>
                    <div class="search-container">
                        <input type="text" id="studentSearch" class="form-group input" placeholder="Type student name (e.g. 'cho')..." style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px;">
                        <div id="searchResults" class="search-results"></div>
                    </div>
                    <div id="selectedTeammates" class="selected-teammates"></div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeRegModal()">Cancel</button>
                    <button type="submit" name="register_project_confirm" class="btn-confirm">Confirm Registration</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const regModal = document.getElementById('registerModal');
        const searchInput = document.getElementById('studentSearch');
        const resultsBox = document.getElementById('searchResults');
        const teammatesBox = document.getElementById('selectedTeammates');
        let currentTeammates = [];

        function initRegistration(id, title, type, sv, desc, status) {
            if (status.trim() === 'Taken') { alert('This project is TAKEN and cannot be selected.'); return; }
            document.getElementById('modProjId').value = id;
            document.getElementById('modProjType').value = type;
            document.getElementById('modTitle').innerText = title;
            document.getElementById('modDesc').innerText = desc.substring(0, 100) + '...';
            document.getElementById('modType').innerText = type;
            document.getElementById('modSv').innerText = sv;
            currentTeammates = []; updateTeammatesUI(); searchInput.value = '';
            const grpSec = document.getElementById('groupSection');
            if (type === 'Group') {
                grpSec.style.display = 'block'; searchInput.disabled = false; grpSec.classList.remove('disabled-overlay');
            } else {
                grpSec.style.display = 'block'; searchInput.disabled = true; searchInput.placeholder = "Individual Project - No teammates needed"; grpSec.classList.add('disabled-overlay');
            }
            regModal.classList.add('show');
        }

        function closeRegModal() { regModal.classList.remove('show'); }

        searchInput.addEventListener('keyup', function() {
            const val = this.value;
            if (val.length < 2) { resultsBox.style.display = 'none'; return; }
            fetch(`std_projectreg.php?action=search_students&keyword=${val}&current_userid=<?php echo $auth_user_id; ?>`)
                .then(res => res.json())
                .then(data => {
                    resultsBox.innerHTML = '';
                    if (data.length > 0) {
                        resultsBox.style.display = 'block';
                        data.forEach(stud => {
                            const div = document.createElement('div');
                            div.className = 'search-item';
                            div.innerHTML = `<span>${stud.name}</span> <small style='color:#999'>(${stud.studid})</small>`;
                            div.onclick = () => addTeammate(stud);
                            resultsBox.appendChild(div);
                        });
                    } else { resultsBox.style.display = 'none'; }
                });
        });

        function addTeammate(stud) {
            if (currentTeammates.some(t => t.studid === stud.studid)) return;
            currentTeammates.push(stud); updateTeammatesUI(); resultsBox.style.display = 'none'; searchInput.value = '';
        }

        function removeTeammate(studid) { currentTeammates = currentTeammates.filter(t => t.studid !== studid); updateTeammatesUI(); }

        function updateTeammatesUI() {
            teammatesBox.innerHTML = '';
            currentTeammates.forEach(t => {
                const tag = document.createElement('div');
                tag.className = 'teammate-tag';
                tag.innerHTML = `${t.name} <i class="fa fa-times remove-btn" onclick="removeTeammate('${t.studid}')"></i>`;
                teammatesBox.appendChild(tag);
            });
            const idsOnly = currentTeammates.map(t => t.studid);
            document.getElementById('teammatesData').value = JSON.stringify(idsOnly);
        }

        window.onclick = function(e) { if (e.target.classList.contains('modal-overlay')) e.target.classList.remove('show'); }
    </script>
</body>
</html>