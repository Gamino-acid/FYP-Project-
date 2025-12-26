<?php
// ====================================================
// std_profile.php - 学生个人资料 + 状态概览 Dashboard
// ====================================================

include("connect.php");

// 1. 验证用户登录
$auth_user_id = $_GET['auth_user_id'] ?? null;
if (!$auth_user_id) {
    header("location: login.php");
    exit;
}

// 2. 处理 Profile 更新 (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    
    // 更新联系方式
    $contact = $_POST['contact'];
    $sql_update = "UPDATE STUDENT SET fyp_contactno = ? WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_update)) {
        $stmt->bind_param("si", $contact, $auth_user_id);
        $stmt->execute();
        $stmt->close();
    }

    // 更新图片
    if (!empty($_FILES["profile_img"]["name"])) {
        $tmp_name = $_FILES["profile_img"]["tmp_name"];
        if (getimagesize($tmp_name) !== false) {
            $image_content = file_get_contents($tmp_name);
            $file_ext = pathinfo($_FILES["profile_img"]["name"], PATHINFO_EXTENSION);
            $base64_image = 'data:image/' . $file_ext . ';base64,' . base64_encode($image_content);
            
            $sql_img = "UPDATE STUDENT SET fyp_profileimg = ? WHERE fyp_userid = ?";
            if ($stmt_img = $conn->prepare($sql_img)) {
                $stmt_img->bind_param("si", $base64_image, $auth_user_id);
                $stmt_img->execute();
                $stmt_img->close();
            }
        } else {
             echo "<script>alert('Invalid file format. Please upload an image.');</script>";
        }
    }
    // 刷新页面显示更新
    echo "<script>alert('Profile updated successfully!'); window.location.href='std_profile.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
}

// 3. 获取学生数据 (GET)
$stud_data = [];
$user_name = 'Student';
$current_stud_id = '';

if (isset($conn)) {
    // 查 USER 表
    $sql_user = "SELECT fyp_username FROM `USER` WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_user)) { 
        $stmt->bind_param("i", $auth_user_id); 
        $stmt->execute(); 
        $res=$stmt->get_result(); 
        if($row=$res->fetch_assoc()) $user_name=$row['fyp_username']; 
        $stmt->close(); 
    }
    
    // 查 STUDENT 表
    $sql_stud = "SELECT * FROM STUDENT WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_stud)) { 
        $stmt->bind_param("i", $auth_user_id); 
        $stmt->execute(); 
        $res=$stmt->get_result(); 
        if($res->num_rows > 0) { 
            $stud_data = $res->fetch_assoc(); 
            $current_stud_id = $stud_data['fyp_studid']; // 获取学生ID用于后续查询
            if(!empty($stud_data['fyp_studname'])) $user_name=$stud_data['fyp_studname']; 
        } else { 
            // 默认空数据
            $stud_data=['fyp_studid'=>'', 'fyp_studname'=>$user_name, 'fyp_studfullid'=>'', 'fyp_projectid'=>null, 'fyp_profileimg'=>'', 'fyp_contactno'=>'', 'fyp_email'=>'', 'fyp_tutgroup'=>'', 'fyp_academicid'=>'', 'fyp_progid'=>'', 'fyp_group'=>'Individual']; 
        } 
        $stmt->close(); 
    }
}

// ====================================================
// [新增] DASHBOARD LOGIC: 获取小组和项目状态
// ====================================================

// A. 获取小组状态
$my_group = null; 
$my_role = 'Individual';    
$my_group_name = 'No Team';

if ($current_stud_id) {
    // 1. 检查是否为 Leader
    $sql_leader = "SELECT * FROM student_group WHERE leader_id = ?";
    if ($stmt = $conn->prepare($sql_leader)) {
        $stmt->bind_param("s", $current_stud_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $my_group = $row;
            $my_role = 'Leader';
            $my_group_name = $row['group_name'];
        }
    }

    // 2. 如果不是 Leader，检查是否为 Member
    if (!$my_group) {
        $sql_member = "SELECT g.* FROM group_request gr 
                       JOIN student_group g ON gr.group_id = g.group_id 
                       WHERE gr.invitee_id = ? AND gr.request_status = 'Accepted'";
        if ($stmt = $conn->prepare($sql_member)) {
            $stmt->bind_param("s", $current_stud_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $my_group = $row;
                $my_role = 'Member';
                $my_group_name = $row['group_name'];
            }
        }
    }
}

// B. 获取项目申请状态
$my_project_status = 'Not Applied';
$my_project_title = 'None';
$status_color_class = 'badge-secondary'; // 默认灰色

if ($current_stud_id) {
    // 确定查询哪个 ID (如果是 Member，查 Leader 的申请)
    $applicant_id = $current_stud_id;
    if ($my_group && $my_role == 'Member') {
        $applicant_id = $my_group['leader_id'];
    }

    // 查询最新的一条申请
    $req_sql = "SELECT pr.fyp_requeststatus, p.fyp_projecttitle 
                FROM project_request pr 
                LEFT JOIN project p ON pr.fyp_projectid = p.fyp_projectid 
                WHERE pr.fyp_studid = ? 
                ORDER BY pr.fyp_datecreated DESC LIMIT 1";

    if ($stmt = $conn->prepare($req_sql)) {
        $stmt->bind_param("s", $applicant_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $my_project_status = $row['fyp_requeststatus'];
            $my_project_title = $row['fyp_projecttitle'];
            
            // 设置状态颜色
            if($my_project_status == 'Approve') $status_color_class = 'badge-success';
            else if($my_project_status == 'Reject') $status_color_class = 'badge-danger';
            else if($my_project_status == 'Pending') $status_color_class = 'badge-warning';
        }
        $stmt->close();
    }
}

// 定义菜单
$current_page = 'profile'; 
$menu_items = [
    'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home', 'link' => 'Student_mainpage.php?page=dashboard'],
    'profile' => ['name' => 'My Profile', 'icon' => 'fa-user', 'link' => 'std_profile.php'], 
    'project_mgmt' => [
        'name' => 'Final Year Project',
        'icon' => 'fa-project-diagram',
        'sub_items' => [
            'group_setup' => ['name' => 'Project Registration', 'icon' => 'fa-users', 'link' => 'std_projectreg.php'],
            'team_invitations' => ['name' => 'Request & Team Status', 'icon' => 'fa-tasks', 'link' => 'std_request_status.php'],
            'proposals' => ['name' => 'Proposal Submission', 'icon' => 'fa-file-alt', 'link' => 'Student_mainpage.php?page=proposals'],
        ]
    ],
    'grades' => ['name' => 'My Grades', 'icon' => 'fa-star', 'link' => 'Student_mainpage.php?page=grades'],
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard & Profile</title>
    
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

        /* Profile Styles */
        .profile-container { display: flex; background: #fff; padding: 30px; border-radius: 12px; box-shadow: var(--card-shadow); gap: 30px; }
        .left-column { width: 250px; display: flex; flex-direction: column; align-items: center; border-right: 1px solid #eee; padding-right: 30px; }
        .right-column { flex: 1; }
        .profile-img-box { width: 160px; height: 160px; border-radius: 50%; background-color: #e0e0e0; overflow: hidden; margin-bottom: 15px; border: 4px solid #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.15); }
        .profile-img-box img { width: 100%; height: 100%; object-fit: cover; }
        .form-section-title { color: #555; border-bottom: 2px solid var(--primary-color); padding-bottom: 5px; margin-bottom: 20px; font-size: 1.1em; font-weight: 600; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; color: #444; font-size: 0.9em; }
        .form-group input[type="text"], .form-group input[type="email"], .form-group input[type="number"], .form-group select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; font-size: 0.95em; font-family: inherit; }
        input[readonly], select[disabled] { background-color: #e9ecef; cursor: not-allowed; color: #6c757d; border-color: #ced4da; }
        .row-group { display: flex; gap: 20px; }
        .col-group { flex: 1; }
        .save-btn { margin-top: 25px; padding: 12px 25px; background-color: var(--primary-color); color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 1em; transition: background 0.3s; font-family: inherit; font-weight: 500; }
        .save-btn:hover { background-color: var(--primary-hover); }
        .student-id-display { font-size: 1.2em; font-weight: bold; color: #333; margin-top: 10px; text-align: center; }

        /* Dashboard specific styles */
        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .stat-card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: var(--card-shadow); display: flex; flex-direction: column; justify-content: center; position: relative; overflow: hidden; border-left: 4px solid transparent; }
        .stat-card.group-card { border-left-color: #6610f2; }
        .stat-card.project-card { border-left-color: #28a745; }
        
        .stat-title { font-size: 13px; color: #888; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px; }
        .stat-value { font-size: 18px; color: #333; font-weight: 700; margin-bottom: 5px; }
        .stat-sub { font-size: 13px; color: #666; }
        .stat-icon { position: absolute; right: 20px; top: 20px; font-size: 32px; opacity: 0.1; color: #000; }
        
        .badge { padding: 4px 10px; border-radius: 15px; font-size: 12px; font-weight: 600; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-secondary { background: #e2e3e5; color: #383d41; }

        /* Alert Styles */
        .alert-box { padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 15px; }
        .alert-warning { background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .alert-info { background-color: #cce5ff; color: #004085; border: 1px solid #b8daff; }
        .alert-btn { margin-left: auto; background: rgba(0,0,0,0.1); border: none; padding: 5px 12px; border-radius: 4px; color: inherit; font-weight: 600; text-decoration: none; font-size: 13px; cursor: pointer; }
        .alert-btn:hover { background: rgba(0,0,0,0.2); }

        @media (max-width: 900px) { .layout-container { flex-direction: column; } .sidebar { width: 100%; min-height: auto; } .profile-container { flex-direction: column; } .left-column { width: 100%; border-right: none; border-bottom: 1px solid #eee; padding-bottom: 20px; margin-bottom: 20px; padding-right: 0; } }
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
                                        <span class="menu-icon"><i class="fa <?php echo $sub_item['icon']; ?>"></i></span>
                                        <?php echo $sub_item['name']; ?>
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
                <h1 class="page-title">Dashboard & Profile</h1>
                <p style="color: #666; margin: 0;">Welcome back! Here is your project overview and personal details.</p>
            </div>

            <!-- DASHBOARD SECTION -->
            <div class="dashboard-grid">
                <!-- Group Status Card -->
                <div class="stat-card group-card">
                    <div class="stat-title">Team Status</div>
                    <div class="stat-value">
                        <?php echo htmlspecialchars($my_group_name); ?>
                    </div>
                    <div class="stat-sub">
                        Role: <?php echo htmlspecialchars($my_role); ?>
                    </div>
                    <i class="fa fa-users stat-icon"></i>
                </div>

                <!-- Project Status Card -->
                <div class="stat-card project-card">
                    <div class="stat-title">Project Application</div>
                    <div class="stat-value" style="font-size: 16px; margin-bottom:8px;">
                        <?php echo htmlspecialchars(strlen($my_project_title) > 25 ? substr($my_project_title,0,25).'...' : $my_project_title); ?>
                    </div>
                    <div>
                        <span class="badge <?php echo $status_color_class; ?>"><?php echo $my_project_status; ?></span>
                    </div>
                    <i class="fa fa-file-contract stat-icon"></i>
                </div>
            </div>

            <!-- ALERTS SECTION -->
            <?php if ($my_role == 'Individual'): ?>
                <div class="alert-box alert-info">
                    <i class="fa fa-info-circle fa-lg"></i>
                    <div>
                        <strong>You are currently working individually.</strong>
                        <br>Prefer team work? You can create or join a group.
                    </div>
                    <a href="std_request_status.php?auth_user_id=<?php echo $auth_user_id; ?>" class="alert-btn">Manage Team</a>
                </div>
            <?php endif; ?>

            <?php if ($my_project_status == 'Not Applied'): ?>
                <div class="alert-box alert-warning">
                    <i class="fa fa-exclamation-triangle fa-lg"></i>
                    <div>
                        <strong>Action Required: You haven't applied for a project yet!</strong>
                        <br>Please browse the available projects and submit your proposal.
                    </div>
                    <a href="std_projectreg.php?auth_user_id=<?php echo $auth_user_id; ?>" class="alert-btn">Apply Now</a>
                </div>
            <?php endif; ?>

            <!-- PROFILE FORM SECTION -->
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="profile-container">
                    <div class="left-column">
                        <div class="profile-img-box">
                            <?php 
                            $img_data = $stud_data['fyp_profileimg'];
                            if (!empty($img_data)) {
                                echo "<img src='$img_data' alt='Profile'>";
                            } else {
                                echo "<div style='width:100%;height:100%;background:#f0f0f0;display:flex;align-items:center;justify-content:center;color:#999;'><i class='fa fa-user' style='font-size:50px;'></i></div>";
                            }
                            ?>
                        </div>
                        <label style="font-size: 0.9em; font-weight: 500; margin-bottom: 5px;">Change Photo:</label>
                        <input type="file" name="profile_img" accept="image/*" style="font-size: 0.8em; max-width: 100%;">
                        <div class="student-id-display"><?php echo htmlspecialchars($stud_data['fyp_studid']); ?></div>
                        <div style="font-size: 0.8em; color: #888; text-align: center;">(Matrix No.)</div>
                    </div>

                    <div class="right-column">
                        <h3 class="form-section-title">Personal & Academic Details</h3>
                        <div class="form-group">
                            <label>Full Name:</label>
                            <input type="text" name="stud_name" value="<?php echo htmlspecialchars($stud_data['fyp_studname']); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label>Student Full ID (Internal):</label>
                            <input type="text" name="stud_fullid" value="<?php echo htmlspecialchars($stud_data['fyp_studfullid']); ?>" readonly>
                        </div>
                        <div class="row-group">
                            <div class="col-group form-group">
                                <label>Academic Year:</label>
                                <select name="academic_id" disabled>
                                    <option value="">-- Select --</option>
                                    <?php
                                    if (isset($conn)) {
                                        $result_acd = $conn->query("SELECT * FROM ACADEMIC_YEAR");
                                        if ($result_acd) {
                                            while($row = $result_acd->fetch_assoc()) {
                                                $selected = ($row['fyp_academicid'] == $stud_data['fyp_academicid']) ? "selected" : "";
                                                echo "<option value='" . $row['fyp_academicid'] . "' $selected>" . $row['fyp_acdyear'] . "</option>";
                                            }
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-group form-group">
                                <label>Programme:</label>
                                <select name="prog_id" disabled>
                                    <option value="">-- Select --</option>
                                    <?php
                                    if (isset($conn)) {
                                        $result_prog = $conn->query("SELECT * FROM PROGRAMME");
                                        if ($result_prog) {
                                            while($row = $result_prog->fetch_assoc()) {
                                                $selected = ($row['fyp_progid'] == $stud_data['fyp_progid']) ? "selected" : "";
                                                echo "<option value='" . $row['fyp_progid'] . "' $selected>" . $row['fyp_progname'] . "</option>";
                                            }
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Tutorial Group:</label>
                            <input type="number" name="tut_group" value="<?php echo htmlspecialchars($stud_data['fyp_tutgroup']); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label>Email Address:</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($stud_data['fyp_email']); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label style="color: var(--primary-color); font-weight: 600;">Contact Number (Editable):</label>
                            <input type="text" name="contact" value="<?php echo htmlspecialchars($stud_data['fyp_contactno']); ?>" required style="border-color: var(--primary-color);">
                        </div>
                        <button type="submit" name="update_profile" class="save-btn"><i class="fa fa-save"></i> Save Changes</button>
                    </div>
                </div>
            </form>

        </main>
    </div>
</body>
</html>