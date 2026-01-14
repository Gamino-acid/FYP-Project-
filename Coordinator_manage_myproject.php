<?php
// ====================================================
// Coordinator_manage_project.php - With Search & Filters
// ====================================================
include("connect.php");

// 1. 基础验证
session_start();
$auth_user_id = $_GET['auth_user_id'] ?? $_SESSION['user_id'] ?? null;
$current_page = 'project_list'; 

if (!$auth_user_id) {
    echo "<script>alert('Please login first.'); window.location.href='login.php';</script>";
    exit;
}

// ----------------------------------------------------
// 2. 获取 Coordinator 资料
// ----------------------------------------------------
$user_name = "Coordinator"; 
$user_avatar = "image/user.png"; 

if (isset($conn)) {
    $stmt = $conn->prepare("SELECT fyp_name, fyp_profileimg FROM coordinator WHERE fyp_userid = ?");
    $stmt->bind_param("i", $auth_user_id); 
    $stmt->execute(); 
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) { 
        $user_name = $row['fyp_name']; 
        if(!empty($row['fyp_profileimg'])) $user_avatar = $row['fyp_profileimg']; 
    }
    $stmt->close();
}

// ----------------------------------------------------
// 3. 获取筛选下拉菜单的数据 (Years & Intakes)
// ----------------------------------------------------
$filter_years = [];
$filter_intakes = [];

// 获取所有存在的 Academic Years
$year_sql = "SELECT DISTINCT fyp_acdyear FROM academic_year ORDER BY fyp_acdyear DESC";
$year_res = $conn->query($year_sql);
while($y = $year_res->fetch_assoc()) { if($y['fyp_acdyear']) $filter_years[] = $y['fyp_acdyear']; }

// 获取所有存在的 Intakes
$intake_sql = "SELECT DISTINCT fyp_intake FROM academic_year ORDER BY fyp_intake ASC";
$intake_res = $conn->query($intake_sql);
while($i = $intake_res->fetch_assoc()) { if($i['fyp_intake']) $filter_intakes[] = $i['fyp_intake']; }

// ----------------------------------------------------
// 4. 处理搜索和筛选参数
// ----------------------------------------------------
$search_keyword = $_GET['search'] ?? '';
$selected_year = $_GET['filter_year'] ?? '';
$selected_intake = $_GET['filter_intake'] ?? '';

// ----------------------------------------------------
// 5. 处理编辑提交 (UPDATE Project)
// ----------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_project'])) {
    
    $target_pid = $_POST['project_id'];
    $new_title = $_POST['edit_title'];
    $new_cat = $_POST['edit_category']; 
    $new_desc = $_POST['edit_desc'];
    $new_req = $_POST['edit_req'];
    $new_archive = $_POST['edit_archive_status']; 

    $update_sql = "UPDATE project SET 
                   fyp_projecttitle = ?, 
                   fyp_projectcat = ?, 
                   fyp_description = ?, 
                   fyp_requirement = ?, 
                   fyp_archive_status = ? 
                   WHERE fyp_projectid = ?";
                   
    if ($up_stmt = $conn->prepare($update_sql)) {
        $up_stmt->bind_param("sssssi", $new_title, $new_cat, $new_desc, $new_req, $new_archive, $target_pid);
        if ($up_stmt->execute()) {
            echo "<script>alert('Project details updated successfully!'); window.location.href='Coordinator_manage_project.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
        } else {
            echo "<script>alert('Update failed: " . $up_stmt->error . "');</script>";
        }
        $up_stmt->close();
    }
}

// ----------------------------------------------------
// 6. 获取项目列表 (带筛选功能)
// ----------------------------------------------------
$all_projects = [];

// 基础 SQL
$sql = "SELECT p.*, ay.fyp_acdyear, ay.fyp_intake,
               COALESCE(s.fyp_name, c.fyp_name, 'Unknown Owner') as owner_name,
               CASE 
                   WHEN s.fyp_staffid IS NOT NULL THEN 'Supervisor'
                   WHEN c.fyp_staffid IS NOT NULL THEN 'Coordinator'
                   ELSE 'Unknown'
               END as owner_role
        FROM project p 
        LEFT JOIN academic_year ay ON p.fyp_academicid = ay.fyp_academicid
        LEFT JOIN supervisor s ON p.fyp_staffid = s.fyp_staffid
        LEFT JOIN coordinator c ON p.fyp_staffid = c.fyp_staffid
        WHERE 1=1 "; // 1=1 方便后续拼接 AND

// 动态参数绑定
$params = [];
$types = "";

// A. 搜索 (Title 或 Owner Name)
if (!empty($search_keyword)) {
    $sql .= " AND (p.fyp_projecttitle LIKE ? OR s.fyp_name LIKE ? OR c.fyp_name LIKE ?)";
    $like_term = "%" . $search_keyword . "%";
    $params[] = $like_term;
    $params[] = $like_term;
    $params[] = $like_term;
    $types .= "sss";
}

// B. 筛选 Year
if (!empty($selected_year)) {
    $sql .= " AND ay.fyp_acdyear = ?";
    $params[] = $selected_year;
    $types .= "s";
}

// C. 筛选 Intake
if (!empty($selected_intake)) {
    $sql .= " AND ay.fyp_intake = ?";
    $params[] = $selected_intake;
    $types .= "s";
}

$sql .= " ORDER BY p.fyp_datecreated DESC";

// 执行查询
if ($stmt = $conn->prepare($sql)) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $all_projects[] = $row;
    }
    $stmt->close();
}

// 7. 菜单定义
$menu_items = [
    'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home', 'link' => 'Coordinator_mainpage.php?page=dashboard'],
    'profile'   => ['name' => 'My Profile', 'icon' => 'fa-user', 'link' => 'Coordinator_profile.php'], 
    
     'management' => [
        'name' => 'User Management',
        'icon' => 'fa-users-cog',
        'sub_items' => [
            // 两个链接都指向同一个管理页面，通过 tab 参数区分默认显示
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
    <title>Manage Projects - Coordinator</title>
    <link rel="icon" type="image/png" href="<?php echo $user_avatar; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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
        .content-card { background: #fff; padding: 30px; border-radius: 12px; box-shadow: var(--card-shadow); }
        
        /* Filter Bar Styles */
        .filter-bar { background: #f8f9fa; padding: 20px; border-radius: 12px; margin-bottom: 25px; border: 1px solid #e9ecef; display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; }
        .filter-group { flex: 1; min-width: 150px; }
        .filter-group label { display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px; color: #666; }
        .filter-input, .filter-select { width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 6px; font-size: 14px; box-sizing: border-box; }
        .btn-filter { background: var(--primary-color); color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 500; transition: background 0.2s; height: 42px; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-filter:hover { background: var(--primary-hover); }
        .btn-reset { background: #e9ecef; color: #555; border: 1px solid #ced4da; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 500; text-decoration: none; height: 42px; display: flex; align-items: center; justify-content: center; box-sizing: border-box; }
        .btn-reset:hover { background: #dee2e6; color: #333; }

        .proj-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .proj-table th { background: #fff; text-align: left; padding: 15px; color: #555; font-weight: 600; border-bottom: 2px solid #eee; }
        .proj-table td { padding: 15px; border-bottom: 1px solid #eee; color: #333; vertical-align: middle; }
        .proj-table tr:hover { background-color: #f9fbfd; }
        
        .badge { padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-block; }
        .badge-status-Taken { background: #e3effd; color: #0056b3; }
        .badge-status-Open { background: #d4edda; color: #155724; }
        .badge-vis-Active { background: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; }
        .badge-vis-Archived { background: #f8d7da; color: #842029; border: 1px solid #f5c2c7; }
        
        .role-badge-small { font-size: 10px; padding: 2px 6px; border-radius: 4px; margin-left: 5px; font-weight: normal; vertical-align: middle; }
        .role-Supervisor { background: #e0f7fa; color: #006064; border: 1px solid #b2ebf2; }
        .role-Coordinator { background: #f3e5f5; color: #4a148c; border: 1px solid #e1bee7; }

        .btn-edit { background: #fff; border: 1px solid #ffc107; color: #d39e00; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 500; transition: all 0.2s; display: inline-flex; align-items: center; gap: 5px; }
        .btn-edit:hover { background: #ffc107; color: #333; }
        
        .btn-new { background: var(--primary-color); color: white; text-decoration: none; padding: 8px 15px; border-radius: 6px; font-size: 14px; display: inline-flex; align-items: center; gap: 6px; }

        /* Modal */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-box { background: #fff; width: 600px; max-width: 90%; max-height: 90vh; overflow-y: auto; border-radius: 12px; box-shadow: 0 15px 40px rgba(0,0,0,0.2); padding: 30px; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; box-sizing: border-box; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; }
        .modal-footer { margin-top: 25px; display: flex; justify-content: flex-end; gap: 10px; }
        .btn-save { background: var(--primary-color); color: white; border: none; padding: 10px 25px; border-radius: 6px; cursor: pointer; }
        .btn-close { background: #f1f1f1; color: #333; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; }
        .close-modal { float:right; cursor:pointer; font-size:24px; color:#999; }
        
        @media (max-width: 900px) { .layout-container { flex-direction: column; } .filter-bar { flex-direction: column; align-items: stretch; } }
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
                        $isActive = ($key == $current_page);
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
                                    $isSubActive = ($sub_key == 'manage_projects'); 
                                ?>
                                    <li><a href="<?php echo $subLinkUrl; ?>" class="menu-link <?php echo $isSubActive ? 'active' : ''; ?>">
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
            <div class="content-card">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                    <div>
                        <h2 style="margin:0; color:#333;"><i class="fa fa-tasks"></i> Project Management</h2>
                        <span style="font-size:13px; color:#777;">Administer all student projects.</span>
                    </div>
                    <a href="Coordinator_purpose.php?auth_user_id=<?php echo $auth_user_id; ?>" class="btn-new">
                        <i class="fa fa-plus-circle"></i> New Project
                    </a>
                </div>

                <form method="GET" class="filter-bar">
                    <input type="hidden" name="auth_user_id" value="<?php echo htmlspecialchars($auth_user_id); ?>">
                    
                    <div class="filter-group" style="flex: 2;">
                        <label>Search Keyword</label>
                        <input type="text" name="search" class="filter-input" placeholder="Project Title or Owner Name..." value="<?php echo htmlspecialchars($search_keyword); ?>">
                    </div>

                    <div class="filter-group">
                        <label>Academic Year</label>
                        <select name="filter_year" class="filter-select">
                            <option value="">All Years</option>
                            <?php foreach($filter_years as $yr): ?>
                                <option value="<?php echo $yr; ?>" <?php echo ($selected_year == $yr) ? 'selected' : ''; ?>>
                                    <?php echo $yr; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Intake</label>
                        <select name="filter_intake" class="filter-select">
                            <option value="">All Intakes</option>
                            <?php foreach($filter_intakes as $intk): ?>
                                <option value="<?php echo $intk; ?>" <?php echo ($selected_intake == $intk) ? 'selected' : ''; ?>>
                                    <?php echo $intk; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn-filter"><i class="fa fa-search"></i> Filter</button>
                    <a href="Coordinator_manage_project.php?auth_user_id=<?php echo $auth_user_id; ?>" class="btn-reset">Reset</a>
                </form>

                <?php if (count($all_projects) > 0): ?>
                    <table class="proj-table">
                        <thead>
                            <tr>
                                <th>Project Info</th>
                                <th>Owner (Staff)</th>
                                <th>Academic Info</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_projects as $proj): ?>
                                <tr>
                                    <td>
                                        <strong style="color:#333; font-size:15px;"><?php echo htmlspecialchars($proj['fyp_projecttitle']); ?></strong><br>
                                        <small style="color:#888;">
                                            Type: <?php echo htmlspecialchars($proj['fyp_projecttype']); ?> | 
                                            Domain: <?php echo htmlspecialchars($proj['fyp_projectcat']); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div style="display:flex; align-items:center;">
                                            <span style="font-weight:600; color:#444;">
                                                <?php echo htmlspecialchars($proj['owner_name']); ?>
                                            </span>
                                            <span class="role-badge-small role-<?php echo $proj['owner_role']; ?>">
                                                <?php echo $proj['owner_role']; ?>
                                            </span>
                                        </div>
                                        <small style="color:#999; font-family:monospace;"><?php echo htmlspecialchars($proj['fyp_staffid']); ?></small>
                                    </td>
                                    <td>
                                        <div style="font-size:13px; font-weight:500; color:#555;">
                                            <?php echo htmlspecialchars($proj['fyp_acdyear'] ?? 'N/A'); ?>
                                        </div>
                                        <div style="font-size:12px; color:#999;">
                                            Intake: <?php echo htmlspecialchars($proj['fyp_intake'] ?? 'N/A'); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-status-<?php echo $proj['fyp_projectstatus']; ?>">
                                            <?php echo $proj['fyp_projectstatus']; ?>
                                        </span>
                                        <br>
                                        <span class="badge badge-vis-<?php echo $proj['fyp_archive_status']; ?>" style="margin-top:5px;">
                                            <?php echo $proj['fyp_archive_status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button type="button" class="btn-edit" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($proj)); ?>)">
                                            <i class="fa fa-sliders-h"></i> Manage
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="text-align:center; padding:50px; color:#999;">
                        <i class="fa fa-search" style="font-size:48px; opacity:0.3; margin-bottom:15px;"></i>
                        <p>No projects found matching your criteria.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <div id="editModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header" style="border-bottom:1px solid #eee; padding-bottom:10px; margin-bottom:15px; display:flex; justify-content:space-between; align-items:center;">
                <h3 style="margin:0;"><i class="fa fa-user-shield"></i> Admin Edit Project</h3>
                <span class="close-modal" onclick="closeModal()">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="project_id" id="modal_project_id">
                <input type="hidden" name="update_project" value="1">

                <div class="form-group">
                    <label>Project Title</label>
                    <input type="text" name="edit_title" id="modal_title" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>Visibility Status (Archive to hide)</label>
                    <select name="edit_archive_status" id="modal_archive" class="form-control" style="font-weight:bold;">
                        <option value="Active">Active (Visible)</option>
                        <option value="Archived">Archived (Hidden)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Category / Domain</label>
                    <select name="edit_category" id="modal_cat" class="form-control">
                        <option value="Software Eng.">Software Engineering</option>
                        <option value="Networking">Networking</option>
                        <option value="AI">Artificial Intelligence</option>
                        <option value="Cybersecurity">Cybersecurity</option>
                        <option value="Data Science">Data Science</option>
                        <option value="IoT">IoT</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="edit_desc" id="modal_desc" class="form-control" style="height:100px;" required></textarea>
                </div>

                <div class="form-group">
                    <label>Requirements</label>
                    <textarea name="edit_req" id="modal_req" class="form-control" style="height:80px;"></textarea>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-close" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-save">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('editModal');

        function openEditModal(projectData) {
            document.getElementById('modal_project_id').value = projectData.fyp_projectid;
            document.getElementById('modal_title').value = projectData.fyp_projecttitle;
            document.getElementById('modal_cat').value = projectData.fyp_projectcat;
            document.getElementById('modal_desc').value = projectData.fyp_description;
            document.getElementById('modal_req').value = projectData.fyp_requirement;
            document.getElementById('modal_archive').value = projectData.fyp_archive_status;
            
            modal.style.display = 'flex';
        }

        function closeModal() {
            modal.style.display = 'none';
        }

        window.onclick = function(e) {
            if (e.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>