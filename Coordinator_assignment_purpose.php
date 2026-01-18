<?php
// ====================================================
// Coordinator_assignment_purpose.php - 发布新作业 (Staff ID 适配版)
// (完全复刻 Supervisor 逻辑，修复 Target 列表为空的问题)
// ====================================================

include("connect.php");

// 1. 基础验证
$auth_user_id = $_GET['auth_user_id'] ?? null;
$current_page = 'propose_assignment'; 

if (!$auth_user_id) { header("location: login.php"); exit; }

// 2. 获取 Coordinator 信息 & 学生列表
$user_name = "Coordinator"; 
$user_avatar = "image/user.png"; 
$my_staff_id = ""; // 【关键修改】使用 Staff ID
$my_groups = [];      
$my_individuals = []; 

if (isset($conn)) {
    // 获取用户基本名
    $stmt = $conn->prepare("SELECT fyp_username FROM `USER` WHERE fyp_userid = ?");
    $stmt->bind_param("i", $auth_user_id); $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc()) $user_name = $row['fyp_username'];
    $stmt->close();
    
    // 获取 Coordinator 详细信息 (优先从 supervisor 表获取 fyp_staffid)
    $stmt = $conn->prepare("SELECT * FROM supervisor WHERE fyp_userid = ?");
    $stmt->bind_param("i", $auth_user_id); $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        
        // 智能获取 Staff ID (优先用 staffid 列，兼容 coordinatorid)
        if (!empty($row['fyp_staffid'])) {
            $my_staff_id = $row['fyp_staffid'];
        } elseif (!empty($row['fyp_supervisorid'])) {
            $my_staff_id = $row['fyp_supervisorid']; // 后备
        }
        
        if (!empty($row['fyp_name'])) $user_name = $row['fyp_name'];
        if (!empty($row['fyp_profileimg'])) $user_avatar = $row['fyp_profileimg'];
    }
    $stmt->close();

    // 如果在 supervisor 表没找到，尝试从 coordinator 表找 (双重保险)
    if (empty($my_staff_id)) {
        $stmt = $conn->prepare("SELECT * FROM coordinator WHERE fyp_userid = ?");
        $stmt->bind_param("i", $auth_user_id); $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $row = $res->fetch_assoc();
            if (!empty($row['fyp_staffid'])) {
                $my_staff_id = $row['fyp_staffid'];
            } elseif (!empty($row['fyp_coordinatorid'])) {
                $my_staff_id = $row['fyp_coordinatorid'];
            }
            if (!empty($row['fyp_name'])) $user_name = $row['fyp_name'];
        }
        $stmt->close();
    }

    // 获取该 Coordinator 指导的学生 (用于下拉菜单)
    // 【关键修改】查询条件改为 fyp_staffid = ?
    if (!empty($my_staff_id)) {
        $sql_my_students = "SELECT s.fyp_studid, s.fyp_studname, s.fyp_group 
                            FROM fyp_registration r
                            JOIN student s ON r.fyp_studid = s.fyp_studid
                            WHERE r.fyp_staffid = ? 
                            AND (r.fyp_archive_status = 'Active' OR r.fyp_archive_status IS NULL)";
                            
        if ($stmt = $conn->prepare($sql_my_students)) {
            $stmt->bind_param("s", $my_staff_id); // 绑定字符串
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                if ($row['fyp_group'] == 'Individual') {
                    $my_individuals[$row['fyp_studid']] = $row['fyp_studname'];
                } else {
                    // 获取组名
                    // 1. 尝试作为组长查找
                    $g_res = $conn->query("SELECT group_id, group_name FROM student_group WHERE leader_id = '{$row['fyp_studid']}' LIMIT 1");
                    if ($g_row = $g_res->fetch_assoc()) {
                        $my_groups[$g_row['group_id']] = $g_row['group_name'];
                    } else {
                        // 2. 尝试作为组员查找
                        $m_res = $conn->query("SELECT g.group_id, g.group_name FROM group_request gr JOIN student_group g ON gr.group_id = g.group_id WHERE gr.invitee_id = '{$row['fyp_studid']}' AND gr.request_status = 'Accepted' LIMIT 1");
                        if ($m_row = $m_res->fetch_assoc()) $my_groups[$m_row['group_id']] = $m_row['group_name'];
                    }
                }
            }
            $stmt->close();
        }
    }
}

// ====================================================
// 3. 处理表单提交 (POST)
// ====================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    if (empty($my_staff_id)) {
        echo "<script>alert('Error: Staff ID not found. You must be a registered staff to post assignments.');</script>";
    } else {
        $a_title = $_POST['assignment_title'];
        $a_desc = $_POST['assignment_description'];
        $a_deadline = $_POST['deadline'];
        $a_type = $_POST['assignment_type']; 
        $target_id = $_POST['target_selection']; 
        $weightage = $_POST['weightage'];

        $final_target = ($target_id == 'all_groups' || $target_id == 'all_individuals') ? 'ALL' : $target_id;
        $a_status = 'Active';
        $date_now = date('Y-m-d H:i:s');
        
        // 【关键修改】插入到 fyp_staffid 列 (不再是 fyp_supervisorid)
        $sql_insert = "INSERT INTO assignment (fyp_staffid, fyp_title, fyp_description, fyp_deadline, fyp_weightage, fyp_assignment_type, fyp_status, fyp_datecreated, fyp_target_id) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        if ($stmt = $conn->prepare($sql_insert)) {
            // 参数绑定: ssssissss (注意第一位 StaffID 是字符串)
            $stmt->bind_param("ssssissss", 
                $my_staff_id, 
                $a_title, 
                $a_desc, 
                $a_deadline, 
                $weightage, 
                $a_type, 
                $a_status, 
                $date_now, 
                $final_target
            );
            
            if ($stmt->execute()) {
                echo "<script>alert('Assignment created successfully!'); window.location.href='Coordinator_assignment_grade.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
            } else {
                echo "<script>alert('Database Error: " . addslashes($stmt->error) . "');</script>";
            }
            $stmt->close();
        } else {
            echo "<script>alert('SQL Prepare Error: " . addslashes($conn->error) . "');</script>";
        }
    }
}

// --- 菜单定义 (保持 Coordinator 结构) ---
$menu_items = [
    'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home', 'link' => 'Coordinator_mainpage.php?page=dashboard'],
    'profile'   => ['name' => 'Profile', 'icon' => 'fa-user', 'link' => 'Coordinator_profile.php'], 
    
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
        'name' => 'Project Mgmt', 
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
    'data_io' => ['name' => 'Data Management', 'icon' => 'fa-database', 'link' => 'Coordinator_data_io.php'],
    'reports' => ['name' => 'System Reports', 'icon' => 'fa-chart-bar', 'link' => 'Coordinator_history.php'],
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
        /* 复用样式 */
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap');
        :root { --primary-color: #0056b3; --primary-hover: #004494; --secondary-color: #f4f4f9; --text-color: #333; --border-color: #e0e0e0; --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); --gradient-start: #eef2f7; --gradient-end: #ffffff; --sidebar-width: 260px; }
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
        .submenu { list-style: none; padding: 0; margin: 0; background-color: #fafafa; display: block; }
        .submenu .menu-link { padding-left: 58px; font-size: 14px; padding-top: 10px; padding-bottom: 10px; }

        .main-content { flex: 1; display: flex; flex-direction: column; gap: 20px; }
        
        /* Form 样式 */
        .form-card { background: #fff; padding: 30px; border-radius: 12px; box-shadow: var(--card-shadow); }
        .page-header { border-bottom: 2px solid #eee; padding-bottom: 15px; margin-bottom: 25px; }
        .page-header h2 { margin: 0; color: var(--primary-color); }
        .page-header p { color: #666; font-size: 14px; margin-top: 5px; }
        
        .form-row { display: flex; gap: 20px; }
        .form-group { margin-bottom: 20px; flex: 1; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #444; }
        .form-control { width: 100%; padding: 10px 15px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; box-sizing: border-box; transition: border 0.3s; font-family: inherit; }
        .form-control:focus { border-color: var(--primary-color); outline: none; }
        textarea.form-control { resize: vertical; min-height: 120px; }
        
        .info-note { background-color: #e3effd; padding: 10px 15px; border-radius: 6px; color: #0056b3; font-size: 13px; margin-bottom: 20px; border-left: 4px solid #0056b3; }

        .btn-submit { background-color: var(--primary-color); color: white; border: none; padding: 12px 30px; border-radius: 6px; font-weight: 600; cursor: pointer; transition: background 0.2s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-submit:hover { background-color: var(--primary-hover); }
        
        .target-select-container { display: none; margin-top: 10px; background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px dashed #ced4da; }
        
        @media (max-width: 900px) { .layout-container { flex-direction: column; } .sidebar { width: 100%; min-height: auto; } .form-row { flex-direction: column; gap: 0; } }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="logo"><img src="image/ladybug.png" alt="Logo" style="width: 32px; margin-right: 10px;"> FYP System</div>
        <div class="topbar-right">
            <div class="user-profile-summary">
                <span class="user-name-display"><?php echo htmlspecialchars($user_name); ?></span>
                <span class="user-role-badge">Coordinator</span>
            </div>
            <div class="user-avatar-circle"><img src="<?php echo $user_avatar; ?>" alt="User Avatar"></div>
            <a href="login.php" class="logout-btn"><i class="fa fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>

    <div class="layout-container">
        <aside class="sidebar">
            <ul class="menu-list">
                <?php foreach ($menu_items as $key => $item): ?>
                    <?php 
                        $isActive = ($key == 'assessment'); // 主菜单高亮
                        $linkUrl = isset($item['link']) ? $item['link'] : "#";
                        if (strpos($linkUrl, '.php') !== false) {
                             $separator = (strpos($linkUrl, '?') !== false) ? '&' : '?';
                             $linkUrl .= $separator . "auth_user_id=" . urlencode($auth_user_id);
                        }
                    ?>
                    <li class="menu-item <?php echo $isActive ? 'has-active-child' : ''; ?>">
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
            <div class="form-card">
                <div class="page-header">
                    <h2><i class="fa fa-tasks"></i> Create Assignment</h2>
                    <p>Assign tasks to students or groups. You can set specific weightage and deadlines.</p>
                </div>

                <div class="info-note">
                    <i class="fa fa-info-circle"></i> 
                    <strong>Tip:</strong> Selecting a standard "Category" will auto-fill the Title and recommended Weightage.
                </div>
                
                <form method="POST">
                    
                    <div class="form-group">
                        <label>Assignment Title <span style="color:red">*</span></label>
                        <input type="text" id="assignment_title" name="assignment_title" class="form-control" placeholder="e.g. Final Report Submission" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Category (Auto-fill)</label>
                            <select class="form-control" onchange="autoFillWeightage(this.value)">
                                <option value="" disabled selected>-- Select Helper --</option>
                                <option value="Proposal_10">Project Proposal (10%)</option>
                                <option value="Interim_20">Interim / Progress Report (20%)</option>
                                <option value="Final_40">Final Report (40%)</option>
                                <option value="Presentation_30">Presentation (30%)</option>
                                <option value="Custom">Custom / Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Target Type <span style="color:red">*</span></label>
                            <select id="assignment_type" name="assignment_type" class="form-control" onchange="toggleTargetList(this.value)" required>
                                <option value="" disabled selected>-- Select Type --</option>
                                <option value="Individual">Individual Student</option>
                                <option value="Group">Student Group</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Deadline <span style="color:red">*</span></label>
                            <input type="datetime-local" name="deadline" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Weightage (%) <span style="color:red">*</span></label>
                            <input type="number" id="weightage" name="weightage" class="form-control" min="0" max="100" required readonly style="background-color:#e9ecef; cursor:not-allowed;">
                        </div>
                    </div>
                    
                    <div id="target_container" class="target-select-container form-group">
                        <label>Select Specific Target:</label>
                        <select id="target_selection" name="target_selection" class="form-control"></select>
                    </div>

                    <div class="form-group">
                        <label>Instructions / Description <span style="color:red">*</span></label>
                        <textarea name="assignment_description" class="form-control" placeholder="Detailed instructions for the students..." required></textarea>
                    </div>

                    <button type="submit" class="btn-submit"><i class="fa fa-paper-plane"></i> Publish Assignment</button>
                </form>
            </div>
        </main>
    </div>

    <script>
        // 从 PHP 注入数据
        const myGroups = <?php echo json_encode($my_groups); ?>;
        const myIndividuals = <?php echo json_encode($my_individuals); ?>;

        function autoFillWeightage(val) {
            const w = document.getElementById('weightage');
            const t = document.getElementById('assignment_title');
            
            if(val === 'Custom'){ 
                w.readOnly = false; 
                w.style.backgroundColor = '#fff'; 
                w.style.cursor = 'text';
                w.value = ''; 
            } else { 
                w.readOnly = true; 
                w.style.backgroundColor = '#e9ecef'; 
                w.style.cursor = 'not-allowed';
                w.value = val.split('_')[1]; 
            }

            // Auto-fill Title based on selection
            if(val.startsWith('Proposal')) t.value = 'Project Proposal Submission';
            else if(val.startsWith('Interim')) t.value = 'Interim Report Submission';
            else if(val.startsWith('Final')) t.value = 'Final Report Submission';
            else if(val.startsWith('Presentation')) t.value = 'Final Presentation';
        }

        function toggleTargetList(type) {
            const c = document.getElementById('target_container');
            const s = document.getElementById('target_selection');
            
            s.innerHTML = ''; 
            c.style.display = 'block';

            if(type === 'Group'){
                s.add(new Option('All My Groups', 'all_groups'));
                // 如果 myGroups 为空，可能是因为没有指导任何小组
                if (Object.keys(myGroups).length === 0) {
                    s.add(new Option('(No groups found)', ''));
                    s.disabled = true;
                } else {
                    s.disabled = false;
                    for(let k in myGroups) {
                        s.add(new Option('Group: ' + myGroups[k], k));
                    }
                }
            } else if(type === 'Individual'){
                s.add(new Option('All My Individual Students', 'all_individuals'));
                if (Object.keys(myIndividuals).length === 0) {
                    s.add(new Option('(No students found)', ''));
                    s.disabled = true;
                } else {
                    s.disabled = false;
                    for(let k in myIndividuals) {
                        s.add(new Option('Student: ' + myIndividuals[k], k));
                    }
                }
            } else {
                c.style.display = 'none';
            }
        }
    </script>
</body>
</html>