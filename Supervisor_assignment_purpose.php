<?php
// ====================================================
// supervisor_assignment_purpose.php - 发布新作业 (Staff ID 版)
// ====================================================

include("connect.php");

// 1. 基础验证
$auth_user_id = $_GET['auth_user_id'] ?? null;
$current_page = 'propose_assignment'; 

if (!$auth_user_id) { 
    echo "<script>alert('Session Error. Please Login.'); window.location.href='login.php';</script>";
    exit; 
}

// 2. 获取导师信息 & 获取名下学生列表
$user_name = "Supervisor"; 
$user_avatar = "image/user.png"; 
$my_staff_id = ""; // 【修改】使用 Staff ID
$my_groups = [];      
$my_individuals = []; 

if (isset($conn)) {
    // 获取 USER 表名字
    $sql_user = "SELECT fyp_username FROM `USER` WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_user)) {
        $stmt->bind_param("i", $auth_user_id); $stmt->execute();
        $res = $stmt->get_result(); if ($row = $res->fetch_assoc()) $user_name = $row['fyp_username'];
        $stmt->close();
    }
    
    // 获取 SUPERVISOR 资料 (含 Staff ID)
    $sql_sv = "SELECT * FROM supervisor WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_sv)) {
        $stmt->bind_param("i", $auth_user_id); $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $sv_data = $res->fetch_assoc();
            // 【修改】获取 Staff ID
            $my_staff_id = $sv_data['fyp_staffid'];
            
            if (!empty($sv_data['fyp_name'])) $user_name = $sv_data['fyp_name'];
            if (!empty($sv_data['fyp_profileimg'])) $user_avatar = $sv_data['fyp_profileimg'];
        }
        $stmt->close();
    }

    // 获取 Active Students (用于下拉菜单)
    // 【修改】使用 fyp_staffid 查询注册表
    if (!empty($my_staff_id)) {
        // 注意：这里假设 fyp_registration 表里已经是 fyp_staffid 了 (我们之前改过)
        $sql_my_students = "SELECT s.fyp_studid, s.fyp_studname, s.fyp_group 
                            FROM fyp_registration r
                            JOIN student s ON r.fyp_studid = s.fyp_studid
                            WHERE r.fyp_staffid = ? 
                            AND (r.fyp_archive_status = 'Active' OR r.fyp_archive_status IS NULL)"; // 加上 Active 过滤更保险
                            
        if ($stmt = $conn->prepare($sql_my_students)) {
            $stmt->bind_param("s", $my_staff_id); // 绑定字符串
            $stmt->execute();
            $res = $stmt->get_result();
            
            while ($row = $res->fetch_assoc()) {
                if ($row['fyp_group'] == 'Individual') {
                    $my_individuals[$row['fyp_studid']] = $row['fyp_studname'];
                } else {
                    // 查 Group Name
                    $g_sql = "SELECT group_id, group_name FROM student_group WHERE leader_id = '" . $row['fyp_studid'] . "' LIMIT 1";
                    $g_res = $conn->query($g_sql);
                    if ($g_row = $g_res->fetch_assoc()) {
                        $my_groups[$g_row['group_id']] = $g_row['group_name'];
                    } else {
                        // 如果不是 Leader，尝试找他是 member 的组
                        $m_sql = "SELECT g.group_id, g.group_name 
                                  FROM group_request gr 
                                  JOIN student_group g ON gr.group_id = g.group_id
                                  WHERE gr.invitee_id = '" . $row['fyp_studid'] . "' AND gr.request_status = 'Accepted' LIMIT 1";
                        $m_res = $conn->query($m_sql);
                        if ($m_row = $m_res->fetch_assoc()) {
                            $my_groups[$m_row['group_id']] = $m_row['group_name'];
                        }
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
        echo "<script>alert('Error: Staff ID not found.');</script>";
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

        // SQL: 【修改】插入 fyp_staffid
        $sql_insert = "INSERT INTO assignment (fyp_staffid, fyp_title, fyp_description, fyp_deadline, fyp_weightage, fyp_assignment_type, fyp_status, fyp_datecreated, fyp_target_id) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        if ($stmt_insert = $conn->prepare($sql_insert)) {
            // 参数绑定: 
            // StaffID 是 String -> 's'
            // Weightage 是 Int -> 'i'
            // 其他都是 String -> 's'
            // 顺序: StaffID(s), Title(s), Desc(s), Deadline(s), Weight(i), Type(s), Status(s), Date(s), Target(s)
            // 总共: ssssissss
            $stmt_insert->bind_param("ssssissss", 
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

            if ($stmt_insert->execute()) {
                echo "<script>alert('Assignment proposed successfully with $weightage% weightage!'); window.location.href='Supervisor_mainpage.php?auth_user_id=" . $auth_user_id . "';</script>";
            } else {
                echo "<script>alert('Database Error: " . $stmt_insert->error . "');</script>";
            }
            $stmt_insert->close();
        } else {
             echo "<script>alert('SQL Prepare Error: " . $conn->error . "');</script>";
        }
    }
}

// 4. 菜单定义
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
            'my_projects'     => ['name' => 'My Projects', 'icon' => 'fa-folder-open', 'link' => 'Supervisor_mainpage.php?page=my_projects'],
            'propose_assignment' => ['name' => 'Propose Assignment', 'icon' => 'fa-tasks', 'link' => 'supervisor_assignment_purpose.php']
        ]
    ],
    'grading' => [
        'name' => 'Assessment',
        'icon' => 'fa-marker',
        'sub_items' => [
            'grade_assignment' => ['name' => 'Grade Assignments', 'icon' => 'fa-check-square', 'link' => 'Supervisor_assignment_grade.php'],
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
    <link rel="icon" type="image/png" href="<?php echo $user_avatar; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* 复用 mainpage 的 CSS */
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
        
        /* 页面特定样式 */
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
                <span class="user-role-badge">Lecturer</span>
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
                        $isActive = ($key == $current_page); // 这里检查主菜单
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
                                    if ($subLinkUrl !== "#") {
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
                    <h2><i class="fa fa-tasks"></i> Propose New Assignment</h2>
                    <p>Create a task or assignment for your supervised students.</p>
                </div>

                <div class="info-note">
                    <i class="fa fa-info-circle"></i> 
                    <strong>Assessment Category:</strong> Selecting a category will automatically set the recommended weightage.
                </div>
                
                <form action="" method="POST">
                    
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

                    <button type="submit" class="btn-submit"><i class="fa fa-paper-plane"></i> Publish Assignment</button>
                </form>
            </div>
        </main>
    </div>

    <script>
        const myGroups = <?php echo json_encode($my_groups); ?>;
        const myIndividuals = <?php echo json_encode($my_individuals); ?>;

        function autoFillWeightage(val) {
            const wInput = document.getElementById('weightage');
            const tInput = document.getElementById('assignment_title');
            
            if (val === 'Custom') {
                wInput.readOnly = false;
                wInput.style.backgroundColor = '#fff';
                wInput.value = '';
                wInput.focus();
            } else {
                // Format is like "Proposal_10"
                const parts = val.split('_');
                const titleKey = parts[0];
                const weight = parts[1];
                
                wInput.readOnly = true;
                wInput.style.backgroundColor = '#e9ecef';
                wInput.value = weight;
                
                // Auto-suggest title
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
    </script>
</body>
</html>