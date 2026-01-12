<?php
// ====================================================
// Coordinator_assignment_purpose.php - 发布新作业 (Coordinator 版)
// ====================================================

include("connect.php");

$auth_user_id = $_GET['auth_user_id'] ?? null;
$current_page = 'propose_assignment'; 

if (!$auth_user_id) { header("location: login.php"); exit; }

// --- 获取 Coordinator 信息 & 学生列表 ---
$user_name = "Coordinator"; 
$user_avatar = "image/user.png"; 
$sv_id = 0;
$my_groups = [];      
$my_individuals = []; 

if (isset($conn)) {
    // 获取用户基本名
    $stmt = $conn->prepare("SELECT fyp_username FROM `USER` WHERE fyp_userid = ?");
    $stmt->bind_param("i", $auth_user_id); $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc()) $user_name = $row['fyp_username'];
    $stmt->close();
    
    // 获取 Supervisor 表详细信息 (Coordinator 也在其中)
    $stmt = $conn->prepare("SELECT * FROM supervisor WHERE fyp_userid = ?");
    $stmt->bind_param("i", $auth_user_id); $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $sv_id = $row['fyp_supervisorid'];
        if (!empty($row['fyp_name'])) $user_name = $row['fyp_name'];
        if (!empty($row['fyp_profileimg'])) $user_avatar = $row['fyp_profileimg'];
    }
    $stmt->close();

    // 获取该 Coordinator 指导的学生 (用于下拉菜单)
    if ($sv_id > 0) {
        $sql_my_students = "SELECT s.fyp_studid, s.fyp_studname, s.fyp_group 
                            FROM fyp_registration r
                            JOIN student s ON r.fyp_studid = s.fyp_studid
                            WHERE r.fyp_supervisorid = ?";
        if ($stmt = $conn->prepare($sql_my_students)) {
            $stmt->bind_param("i", $sv_id); $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                if ($row['fyp_group'] == 'Individual') {
                    $my_individuals[$row['fyp_studid']] = $row['fyp_studname'];
                } else {
                    // 获取组名
                    $g_res = $conn->query("SELECT group_id, group_name FROM student_group WHERE leader_id = '{$row['fyp_studid']}' LIMIT 1");
                    if ($g_row = $g_res->fetch_assoc()) {
                        $my_groups[$g_row['group_id']] = $g_row['group_name'];
                    } else {
                        // 检查成员
                        $m_res = $conn->query("SELECT g.group_id, g.group_name FROM group_request gr JOIN student_group g ON gr.group_id = g.group_id WHERE gr.invitee_id = '{$row['fyp_studid']}' AND gr.request_status = 'Accepted' LIMIT 1");
                        if ($m_row = $m_res->fetch_assoc()) $my_groups[$m_row['group_id']] = $m_row['group_name'];
                    }
                }
            }
            $stmt->close();
        }
    }
}

// --- 处理提交 ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $a_title = $_POST['assignment_title'];
    $a_desc = $_POST['assignment_description'];
    $a_deadline = $_POST['deadline'];
    $a_type = $_POST['assignment_type']; 
    $target_id = $_POST['target_selection']; 
    $weightage = $_POST['weightage'];

    $final_target = ($target_id == 'all_groups' || $target_id == 'all_individuals') ? 'ALL' : $target_id;
    
    $sql_insert = "INSERT INTO assignment (fyp_supervisorid, fyp_title, fyp_description, fyp_deadline, fyp_weightage, fyp_assignment_type, fyp_status, fyp_datecreated, fyp_target_id) 
                   VALUES (?, ?, ?, ?, ?, ?, 'Active', NOW(), ?)";

    if ($stmt = $conn->prepare($sql_insert)) {
        $stmt->bind_param("isssiss", $sv_id, $a_title, $a_desc, $a_deadline, $weightage, $a_type, $final_target);
        if ($stmt->execute()) {
            echo "<script>alert('Assignment created successfully!'); window.location.href='Coordinator_assignment_grade.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
        } else {
            echo "<script>alert('Error: " . $stmt->error . "');</script>";
        }
        $stmt->close();
    }
}

// --- 统一 Coordinator 菜单 ---
$menu_items = [
    'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home', 'link' => 'Coordinator_mainpage.php?page=dashboard'],
    'profile'   => ['name' => 'Profile', 'icon' => 'fa-user', 'link' => 'Coordinator_profile.php'], 
    'project_mgmt' => ['name' => 'Project Mgmt', 'icon' => 'fa-tasks', 'sub_items' => ['propose_project' => ['name' => 'Propose Project', 'icon' => 'fa-plus-circle', 'link' => 'Coordinator_purpose.php'], 'project_requests' => ['name' => 'Project Requests', 'icon' => 'fa-envelope-open-text', 'link' => 'Coordinator_projectreq.php'], 'allocation' => ['name' => 'Auto Allocation', 'icon' => 'fa-bullseye', 'link' => 'Coordinator_mainpage.php?page=allocation']]],
    'assessment' => ['name' => 'Assessment', 'icon' => 'fa-clipboard-check', 'sub_items' => ['propose_assignment' => ['name' => 'Create Assignment', 'icon' => 'fa-plus', 'link' => 'Coordinator_assignment_purpose.php'], 'grade_assignment' => ['name' => 'Grade Assignments', 'icon' => 'fa-check-square', 'link' => 'Coordinator_assignment_grade.php']]],
    'announcements' => ['name' => 'Announcements', 'icon' => 'fa-bullhorn', 'sub_items' => ['post_announcement' => ['name' => 'Post New', 'icon' => 'fa-pen', 'link' => 'Coordinator_announcement.php']]],
    'schedule' => ['name' => 'My Schedule', 'icon' => 'fa-calendar-alt', 'link' => 'Coordinator_meeting.php'], 
    'data_io' => ['name' => 'Data Management', 'icon' => 'fa-database', 'link' => 'Coordinator_mainpage.php?page=data_io'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Assignment - Coordinator</title>
    <link rel="icon" type="image/png" href="<?php echo $user_avatar; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* 复用 Coordinator 样式 */
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap');
        :root { --primary-color: #0056b3; --primary-hover: #004494; --secondary-color: #f4f4f9; --text-color: #333; --border-color: #e0e0e0; --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); --sidebar-width: 260px; }
        body { font-family: 'Poppins', sans-serif; margin: 0; background: #eef2f7; color: var(--text-color); min-height: 100vh; display: flex; flex-direction: column; }
        .topbar { display: flex; justify-content: space-between; align-items: center; padding: 15px 40px; background-color: #fff; position: sticky; top: 0; z-index:100; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .logo { font-size: 22px; font-weight: 600; color: var(--primary-color); display: flex; align-items: center; gap: 10px; }
        .topbar-right { display: flex; align-items: center; gap: 20px; }
        .user-name-display { font-weight: 600; font-size: 14px; }
        .user-avatar-circle { width: 40px; height: 40px; border-radius: 50%; overflow: hidden; border: 2px solid #e3effd; margin-left: 10px; }
        .user-avatar-circle img { width: 100%; height: 100%; object-fit: cover; }
        .logout-btn { color: #d93025; text-decoration: none; font-weight: 500; }
        .layout-container { display: flex; flex: 1; max-width: 1400px; margin: 0 auto; width: 100%; padding: 20px; gap: 20px; }
        .sidebar { width: var(--sidebar-width); background: #fff; border-radius: 12px; box-shadow: var(--card-shadow); padding: 20px 0; min-height: calc(100vh - 120px); }
        .menu-list { list-style: none; padding: 0; margin: 0; }
        .menu-link { display: flex; align-items: center; padding: 12px 25px; text-decoration: none; color: #555; transition: all 0.3s; border-left: 4px solid transparent; }
        .menu-link:hover, .menu-link.active { background-color: #e3effd; color: var(--primary-color); border-left-color: var(--primary-color); }
        .menu-icon { width: 24px; margin-right: 10px; text-align: center; }
        .submenu { list-style: none; padding: 0; margin: 0; background-color: #fafafa; display: block; }
        .submenu .menu-link { padding-left: 58px; font-size: 14px; padding-top: 10px; padding-bottom: 10px; }
        .main-content { flex: 1; display: flex; flex-direction: column; gap: 20px; }
        
        .form-card { background: #fff; padding: 30px; border-radius: 12px; box-shadow: var(--card-shadow); }
        .info-note { background-color: #e3effd; padding: 10px 15px; border-radius: 6px; color: #0056b3; font-size: 13px; margin-bottom: 20px; border-left: 4px solid #0056b3; }
        .form-group { margin-bottom: 20px; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; }
        .btn-submit { background-color: var(--primary-color); color: white; border: none; padding: 12px 30px; border-radius: 6px; cursor: pointer; font-weight: 600; }
        .target-select-container { display: none; margin-top: 10px; background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px dashed #ced4da; }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="logo"><img src="image/ladybug.png" alt="Logo" style="width: 32px;"> FYP System</div>
        <div class="topbar-right">
            <div><span class="user-name-display"><?php echo htmlspecialchars($user_name); ?></span> <span style="font-size:11px; background:#e3effd; color:#0056b3; padding:2px 8px; border-radius:12px;">Coordinator</span></div>
            <div class="user-avatar-circle"><img src="<?php echo $user_avatar; ?>" alt="User"></div>
            <a href="login.php" class="logout-btn"><i class="fa fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>
    <div class="layout-container">
        <aside class="sidebar">
            <ul class="menu-list">
                <?php foreach ($menu_items as $key => $item): 
                    $isActive = ($key == 'assessment'); 
                    $linkUrl = (isset($item['link']) && $item['link'] !== "#") ? $item['link'] . (strpos($item['link'], '?') !== false ? '&' : '?') . "auth_user_id=" . urlencode($auth_user_id) : "#";
                ?>
                <li class="menu-item">
                    <a href="<?php echo $linkUrl; ?>" class="menu-link <?php echo $isActive ? 'active' : ''; ?>">
                        <span class="menu-icon"><i class="fa <?php echo $item['icon']; ?>"></i></span> <?php echo $item['name']; ?>
                    </a>
                    <?php if (isset($item['sub_items'])): ?>
                        <ul class="submenu">
                        <?php foreach ($item['sub_items'] as $sub_key => $sub_item): 
                             $subLinkUrl = (isset($sub_item['link']) && $sub_item['link'] !== "#") ? $sub_item['link'] . (strpos($sub_item['link'], '?') !== false ? '&' : '?') . "auth_user_id=" . urlencode($auth_user_id) : "#";
                        ?>
                            <li><a href="<?php echo $subLinkUrl; ?>" class="menu-link <?php echo ($sub_key == $current_page) ? 'active' : ''; ?>">
                                <i class="fa <?php echo $sub_item['icon']; ?>" style="margin-right:10px;"></i> <?php echo $sub_item['name']; ?>
                            </a></li>
                        <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </aside>
        <main class="main-content">
            <div class="form-card">
                <h2><i class="fa fa-tasks"></i> Create Assignment</h2>
                <div class="info-note"><i class="fa fa-info-circle"></i> Selecting a category will auto-fill the recommended weightage.</div>
                
                <form method="POST">
                    <div class="form-group">
                        <label>Category</label>
                        <select class="form-control" onchange="autoFillWeightage(this.value)">
                            <option value="" disabled selected>-- Select --</option>
                            <option value="Proposal_10">Project Proposal (10%)</option>
                            <option value="Interim_20">Interim / Progress Report (20%)</option>
                            <option value="Final_40">Final Report (40%)</option>
                            <option value="Presentation_30">Presentation (30%)</option>
                            <option value="Custom">Custom</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Title</label><input type="text" id="assignment_title" name="assignment_title" class="form-control" required>
                    </div>

                    <div style="display:flex; gap:20px;">
                        <div class="form-group" style="flex:1;">
                            <label>Target Type</label>
                            <select id="assignment_type" name="assignment_type" class="form-control" onchange="toggleTargetList(this.value)">
                                <option value="" disabled selected>-- Select --</option>
                                <option value="Individual">Individual</option>
                                <option value="Group">Group</option>
                            </select>
                        </div>
                        <div class="form-group" style="flex:1;">
                            <label>Weightage (%)</label><input type="number" id="weightage" name="weightage" class="form-control" min="0" max="100" required readonly style="background:#e9ecef;">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Deadline</label><input type="datetime-local" name="deadline" class="form-control" required>
                    </div>
                    
                    <div id="target_container" class="target-select-container">
                        <label>Select Target:</label>
                        <select id="target_selection" name="target_selection" class="form-control"></select>
                    </div>

                    <div class="form-group">
                        <label>Instructions</label><textarea name="assignment_description" class="form-control" rows="4" required></textarea>
                    </div>
                    <button type="submit" class="btn-submit">Publish Assignment</button>
                </form>
            </div>
        </main>
    </div>
    <script>
        const myGroups = <?php echo json_encode($my_groups); ?>;
        const myIndividuals = <?php echo json_encode($my_individuals); ?>;
        function autoFillWeightage(val) {
            const w = document.getElementById('weightage');
            const t = document.getElementById('assignment_title');
            if(val==='Custom'){ w.readOnly=false; w.style.background='#fff'; w.value=''; }
            else { w.readOnly=true; w.style.background='#e9ecef'; w.value=val.split('_')[1]; }
            if(val.startsWith('Proposal')) t.value='Project Proposal Submission';
            if(val.startsWith('Interim')) t.value='Interim Report';
            if(val.startsWith('Final')) t.value='Final Report Submission';
        }
        function toggleTargetList(type) {
            const c = document.getElementById('target_container');
            const s = document.getElementById('target_selection');
            s.innerHTML=''; c.style.display='block';
            if(type==='Group'){
                s.add(new Option('All My Groups','all_groups'));
                for(let k in myGroups) s.add(new Option('Group: '+myGroups[k], k));
            } else if(type==='Individual'){
                s.add(new Option('All My Individual Students','all_individuals'));
                for(let k in myIndividuals) s.add(new Option('Student: '+myIndividuals[k], k));
            } else c.style.display='none';
        }
    </script>
</body>
</html>