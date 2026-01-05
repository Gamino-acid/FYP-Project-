<?php
// ====================================================
// supervisor_purpose.php - 提议新项目页面 (Added Academic Year Selection)
// ====================================================

include("connect.php");

// 1. 基础验证
$auth_user_id = $_GET['auth_user_id'] ?? null;
$current_page = 'propose_project'; 

// 安全检查
if (!$auth_user_id) { 
    echo "<script>alert('Session Error. Please Login.'); window.location.href='login.php';</script>";
    exit; 
}

// 2. 获取学术年列表 (New Feature)
$academic_years = [];
if (isset($conn)) {
    $sql_acd = "SELECT * FROM academic_year ORDER BY fyp_acdyear DESC, fyp_intake ASC";
    $res_acd = $conn->query($sql_acd);
    if ($res_acd) {
        while ($row = $res_acd->fetch_assoc()) {
            $academic_years[] = $row;
        }
    }
}

// ====================================================
// 3. 表单提交处理逻辑
// ====================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // A. 实时获取导师 ID 和 信息
    $real_sv_id = 0;
    $real_sv_name = "Unknown Supervisor";
    $real_sv_contact = "";
    
    $sql_fetch_sv = "SELECT fyp_supervisorid, fyp_name, fyp_email, fyp_contactno FROM supervisor WHERE fyp_userid = ?";
    if ($stmt_fetch = $conn->prepare($sql_fetch_sv)) {
        $stmt_fetch->bind_param("i", $auth_user_id);
        $stmt_fetch->execute();
        $res_fetch = $stmt_fetch->get_result();
        if ($row_sv = $res_fetch->fetch_assoc()) {
            $real_sv_id   = $row_sv['fyp_supervisorid']; 
            $real_sv_name = $row_sv['fyp_name'];
            $real_sv_contact = !empty($row_sv['fyp_email']) ? $row_sv['fyp_email'] : $row_sv['fyp_contactno'];
        }
        $stmt_fetch->close();
    }

    if ($real_sv_id == 0) {
        echo "<script>alert('Error: Supervisor Profile not found. Please update your profile first.');</script>";
    } else {
        // B. 获取表单数据
        $p_title = $_POST['project_title'];
        $p_domain = $_POST['project_domain'];
        $p_desc = $_POST['project_description'];
        $p_req = $_POST['requirements'];
        $p_type = $_POST['project_type']; 
        $p_academic_id = $_POST['academic_id']; // 获取选中的 Academic ID
        
        $p_course_req = 'FIST'; 
        $p_status = 'Open'; 

        // C. 插入数据库 (包含 fyp_supervisorid 和 fyp_academicid)
        // SQL 语句更新：增加了 fyp_academicid
        $sql_insert = "INSERT INTO project (
            fyp_supervisorid,
            fyp_academicid,
            fyp_projecttitle, 
            fyp_description, 
            fyp_projectcat, 
            fyp_projecttype, 
            fyp_projectstatus, 
            fyp_requirement, 
            fyp_coursereq, 
            fyp_contactperson, 
            fyp_contactpersonname, 
            fyp_datecreated
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        if ($stmt_insert = $conn->prepare($sql_insert)) {
            // 参数绑定: i = int, s = string (共11个参数: 2个int, 9个string)
            $stmt_insert->bind_param("iisssssssss", 
                $real_sv_id,
                $p_academic_id, // 绑定 Academic ID
                $p_title, 
                $p_desc, 
                $p_domain, 
                $p_type, 
                $p_status, 
                $p_req, 
                $p_course_req,
                $real_sv_contact, 
                $real_sv_name
            );

            if ($stmt_insert->execute()) {
                echo "<script>alert('Project proposed successfully!'); window.location.href='Supervisor_mainpage.php?page=my_projects&auth_user_id=" . $auth_user_id . "';</script>";
            } else {
                echo "<script>alert('Database Error: " . $stmt_insert->error . "');</script>";
            }
            $stmt_insert->close();
        } else {
            echo "<script>alert('SQL Prepare Error: " . $conn->error . "');</script>";
        }
    }
}

// 4. 获取用户信息用于显示 Topbar
$user_name = "Supervisor"; 
$user_avatar = "image/user.png"; 

if (isset($conn)) {
    $sql_sv = "SELECT fyp_name, fyp_profileimg FROM supervisor WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_sv)) {
        $stmt->bind_param("i", $auth_user_id); $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            if (!empty($row['fyp_name'])) $user_name = $row['fyp_name'];
            if (!empty($row['fyp_profileimg'])) $user_avatar = $row['fyp_profileimg'];
        }
        $stmt->close();
    }
}

// 5. 菜单定义
$menu_items = [
    'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home', 'link' => 'Supervisor_mainpage.php?page=dashboard'],
    'profile'   => ['name' => 'My Profile', 'icon' => 'fa-user', 'link' => 'supervisor_profile.php'],
    'students'  => [
        'name' => 'My Students', 
        'icon' => 'fa-users',
        'sub_items' => [
            'project_requests' => ['name' => 'Project Requests', 'icon' => 'fa-envelope-open-text', 'link' => 'supervisor_projectreq.php'],
            'student_list'     => ['name' => 'Student List', 'icon' => 'fa-list', 'link' => 'Supervisor_mainpage.php?page=student_list'],
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
    <title>Propose New Project - Supervisor</title>
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
        
        /* Sidebar Expanded */
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
                    <h2><i class="fa fa-plus-circle"></i> Propose New Project</h2>
                    <p>Create a new project for students to apply. Your contact details will be attached automatically.</p>
                </div>

                <div class="info-note">
                    <i class="fa fa-info-circle"></i> 
                    <strong>Project Status:</strong> All new projects are set to "<strong>Open</strong>" by default. 
                    <br>
                    <strong>Contact Info:</strong> Your name and email (<?php echo htmlspecialchars($user_name); ?>) will be automatically linked to this project upon submission.
                </div>
                
                <!-- Action 为空表示提交给自己 -->
                <form action="" method="POST">
                    
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
                                <!-- 值被缩短以适应 varchar(16) 限制 -->
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
                            <!-- 锁定为 FIST 并设置为只读 -->
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

                    <button type="submit" class="btn-submit"><i class="fa fa-paper-plane"></i> Submit Proposal</button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>