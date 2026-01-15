<?php
// ====================================================
// Supervisor_project_list.php - 导师项目管理 (Staff ID 版)
// ====================================================
include("connect.php");

// 1. 验证用户登录
$auth_user_id = $_GET['auth_user_id'] ?? null;
// 手动设置当前页面，以便侧边栏高亮 'my_projects'
$current_page = 'my_projects'; 

if (!$auth_user_id) { header("location: login.php"); exit; }

// 2. 获取导师信息
$user_name = "Supervisor"; 
$user_avatar = "image/user.png"; 
$my_staff_id = ""; // 【修改】使用 Staff ID

if (isset($conn)) {
    // USER 表
    $sql_user = "SELECT fyp_username FROM `USER` WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_user)) {
        $stmt->bind_param("i", $auth_user_id); $stmt->execute();
        $res = $stmt->get_result(); if ($row = $res->fetch_assoc()) $user_name = $row['fyp_username'];
        $stmt->close();
    }
    // SUPERVISOR 表
    $sql_sv = "SELECT * FROM supervisor WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_sv)) {
        $stmt->bind_param("i", $auth_user_id); $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $row = $res->fetch_assoc();
            // 【修改】获取 Staff ID
            $my_staff_id = $row['fyp_staffid'];
            
            if (!empty($row['fyp_name'])) $user_name = $row['fyp_name'];
            if (!empty($row['fyp_profileimg'])) $user_avatar = $row['fyp_profileimg'];
        }
        $stmt->close();
    }
}

// ====================================================
// 3. 处理编辑提交 (UPDATE) - 使用 Staff ID
// ====================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_project'])) {
    $pid = $_POST['project_id'];
    $title = $_POST['project_title'];
    $cat = $_POST['project_cat'];
    $type = $_POST['project_type'];
    $status = $_POST['project_status'];
    $desc = $_POST['description'];
    $req = $_POST['requirement'];
    
    // 【修改】验证条件改为 fyp_staffid
    $sql_upd = "UPDATE project SET 
                fyp_projecttitle = ?, 
                fyp_projectcat = ?, 
                fyp_projecttype = ?, 
                fyp_projectstatus = ?, 
                fyp_description = ?, 
                fyp_requirement = ? 
                WHERE fyp_projectid = ? AND fyp_staffid = ?";
                
    if ($stmt = $conn->prepare($sql_upd)) {
        // 参数绑定: 
        // 前6个是更新值(String)
        // 第7个是 ProjectID(Int)
        // 第8个是 StaffID(String) -> 注意这里是 's'
        // 总共: ssssssis
        $stmt->bind_param("ssssssis", $title, $cat, $type, $status, $desc, $req, $pid, $my_staff_id);
        
        if ($stmt->execute()) {
            echo "<script>alert('Project details updated successfully!'); window.location.href='Supervisor_project_list.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
        } else {
            echo "<script>alert('Error updating project: " . $stmt->error . "');</script>";
        }
        $stmt->close();
    }
}

// ====================================================
// 4. 获取数据 (筛选 & 列表) - 使用 Staff ID
// ====================================================

// A. 获取所有可用的 Academic Years
$academic_options = [];
$acd_sql = "SELECT * FROM academic_year ORDER BY fyp_acdyear DESC";
$res_acd = $conn->query($acd_sql);
if ($res_acd) {
    while ($row = $res_acd->fetch_assoc()) {
        $academic_options[] = $row;
    }
}

// B. 获取筛选参数
$filter_academic = $_GET['filter_academic'] ?? 'All';

// C. 获取项目列表
$my_projects = [];
$stats = ['total' => 0, 'open' => 0, 'taken' => 0];

// 【修改】判断 Staff ID 是否存在
if (!empty($my_staff_id)) {
    // 【修改】WHERE 条件改为 p.fyp_staffid
    $sql_proj = "SELECT p.*, a.fyp_acdyear, a.fyp_intake 
                 FROM project p 
                 LEFT JOIN academic_year a ON p.fyp_academicid = a.fyp_academicid
                 WHERE p.fyp_staffid = '$my_staff_id'";
    
    // 应用筛选
    if ($filter_academic != 'All') {
        $sql_proj .= " AND p.fyp_academicid = '" . $conn->real_escape_string($filter_academic) . "'";
    }
    
    $sql_proj .= " ORDER BY p.fyp_datecreated DESC";
    
    $res_proj = $conn->query($sql_proj);
    if ($res_proj) {
        while ($row = $res_proj->fetch_assoc()) {
            // 统计
            $stats['total']++;
            if ($row['fyp_projectstatus'] == 'Open') $stats['open']++;
            if ($row['fyp_projectstatus'] == 'Taken') $stats['taken']++;
            
            $my_projects[] = $row;
        }
    }
}

// 菜单定义
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
            'my_projects'     => ['name' => 'My Projects', 'icon' => 'fa-folder-open', 'link' => 'Supervisor_project_list.php'],
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
    <title>My Projects</title>
    <link rel="icon" type="image/png" href="<?php echo $user_avatar; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* 复用样式 */
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap');
        :root { --primary-color: #0056b3; --primary-hover: #004494; --secondary-color: #f4f4f9; --text-color: #333; --border-color: #e0e0e0; --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); }
        body { font-family: 'Poppins', sans-serif; margin: 0; background: linear-gradient(135deg, #eef2f7, #ffffff); color: var(--text-color); min-height: 100vh; display: flex; flex-direction: column; }
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
        .sidebar { width: 260px; background: #fff; border-radius: 12px; box-shadow: var(--card-shadow); padding: 20px 0; flex-shrink: 0; min-height: calc(100vh - 120px); }
        .menu-list { list-style: none; padding: 0; margin: 0; }
        .menu-item { margin-bottom: 5px; }
        .menu-link { display: flex; align-items: center; padding: 12px 25px; text-decoration: none; color: #555; font-weight: 500; font-size: 15px; transition: all 0.3s; border-left: 4px solid transparent; }
        .menu-link:hover { background-color: var(--secondary-color); color: var(--primary-color); }
        .menu-link.active { background-color: #e3effd; color: var(--primary-color); border-left-color: var(--primary-color); }
        .menu-icon { width: 24px; margin-right: 10px; text-align: center; }
        .submenu { list-style: none; padding: 0; margin: 0; background-color: #fafafa; display: block; }
        .submenu .menu-link { padding-left: 58px; font-size: 14px; padding-top: 10px; padding-bottom: 10px; }
        .main-content { flex: 1; display: flex; flex-direction: column; gap: 20px; }

        /* Page Specific */
        .welcome-card { background: #fff; padding: 30px; border-radius: 12px; box-shadow: var(--card-shadow); border-left: 5px solid var(--primary-color); margin-bottom: 20px; }
        .page-title { font-size: 24px; margin: 0 0 10px 0; color: var(--text-color); }

        /* Stats & Filter */
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px; }
        .stat-box { background: #fff; padding: 15px; border-radius: 8px; border: 1px solid #eee; text-align: center; }
        .stat-num { font-size: 24px; font-weight: 700; color: #333; }
        .stat-lbl { font-size: 12px; color: #777; text-transform: uppercase; font-weight: 600; }
        
        .filter-bar { background: #fff; padding: 15px; border-radius: 8px; box-shadow: var(--card-shadow); display: flex; align-items: center; gap: 15px; margin-bottom: 20px; }
        .filter-label { font-size: 13px; font-weight: 600; color: #555; }
        .filter-select { padding: 8px; border-radius: 4px; border: 1px solid #ccc; font-size: 14px; min-width: 250px; }
        .btn-filter { padding: 8px 20px; background: var(--primary-color); color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; }

        /* Project Cards */
        .proj-grid { display: grid; grid-template-columns: 1fr; gap: 15px; }
        .proj-card { background: #fff; border-radius: 10px; padding: 20px; box-shadow: var(--card-shadow); border-left: 5px solid #ccc; transition: transform 0.2s; position: relative; }
        .proj-card:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.08); }
        
        .proj-status-Open { border-left-color: #28a745; }
        .proj-status-Taken { border-left-color: #d93025; }

        .proj-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; }
        .proj-title { font-size: 18px; font-weight: 600; color: #333; margin: 0; }
        .proj-badge { font-size: 11px; padding: 3px 10px; border-radius: 12px; font-weight: 600; text-transform: uppercase; margin-left: 10px; background: #eee; color: #555; }
        .badge-Open { background: #d4edda; color: #155724; }
        .badge-Taken { background: #f8d7da; color: #721c24; }
        
        .proj-meta { font-size: 13px; color: #666; margin-bottom: 10px; display: flex; gap: 15px; align-items: center; }
        .proj-meta i { color: #999; margin-right: 5px; }
        .proj-desc { font-size: 14px; color: #555; line-height: 1.5; margin-bottom: 15px; max-height: 60px; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }

        .btn-edit { background: var(--primary-color); color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 500; text-decoration: none; display: inline-block; }
        .btn-edit:hover { background: #004494; }

        .empty-state { text-align: center; padding: 40px; color: #999; background: #fff; border-radius: 12px; border: 1px dashed #ddd; }

        /* Edit Modal */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-overlay.show { display: flex; }
        .modal-box { background: #fff; padding: 30px; border-radius: 12px; width: 600px; max-width: 90%; max-height: 90vh; overflow-y: auto; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .modal-header { font-size: 20px; font-weight: 600; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px; display: flex; justify-content: space-between; }
        .close-modal { cursor: pointer; font-size: 24px; color: #999; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #555; margin-bottom: 5px; }
        .form-control { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-family: inherit; font-size: 14px; box-sizing: border-box; }
        textarea.form-control { resize: vertical; min-height: 100px; }
        
        @media (max-width: 900px) { .layout-container { flex-direction: column; } .sidebar { width: 100%; min-height: auto; } }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="logo">FYP System</div>
        <div class="topbar-right">
            <span class="user-name-display"><?php echo htmlspecialchars($user_name); ?></span>
            <span class="user-role-badge">Supervisor</span>
            <div class="user-avatar-circle"><img src="<?php echo $user_avatar; ?>" alt="User"></div>
            <a href="login.php" class="logout-btn">Logout</a>
        </div>
    </header>

    <div class="layout-container">
        <aside class="sidebar">
            <ul class="menu-list">
                <?php foreach ($menu_items as $key => $item): ?>
                    <?php 
                        $isActive = ($key == 'fyp_project'); 
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
            <div class="welcome-card">
                <h1 class="page-title">My Projects</h1>
                <p style="color: #666; margin: 0;">Manage and update your published FYP projects.</p>
            </div>

            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-num"><?php echo $stats['total']; ?></div>
                    <div class="stat-lbl">Total Projects</div>
                </div>
                <div class="stat-box" style="border-bottom: 4px solid #28a745;">
                    <div class="stat-num"><?php echo $stats['open']; ?></div>
                    <div class="stat-lbl">Open</div>
                </div>
                <div class="stat-box" style="border-bottom: 4px solid #d93025;">
                    <div class="stat-num"><?php echo $stats['taken']; ?></div>
                    <div class="stat-lbl">Taken</div>
                </div>
            </div>

            <form method="GET" class="filter-bar">
                <input type="hidden" name="auth_user_id" value="<?php echo htmlspecialchars($auth_user_id); ?>">
                <span class="filter-label">Filter by Session:</span>
                <select name="filter_academic" class="filter-select">
                    <option value="All">All Sessions</option>
                    <?php foreach ($academic_options as $opt): ?>
                        <option value="<?php echo $opt['fyp_academicid']; ?>" <?php echo ($filter_academic == $opt['fyp_academicid']) ? 'selected' : ''; ?>>
                            <?php echo $opt['fyp_acdyear'] . " - " . $opt['fyp_intake']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-filter">Apply Filter</button>
            </form>

            <?php if (empty($my_projects)): ?>
                <div class="empty-state">
                    <p>No projects found. Try changing the filter or propose a new project.</p>
                </div>
            <?php else: ?>
                <div class="proj-grid">
                    <?php foreach ($my_projects as $proj): 
                        $statusClass = 'proj-status-' . $proj['fyp_projectstatus'];
                        $badgeClass = 'badge-' . $proj['fyp_projectstatus'];
                    ?>
                        <div class="proj-card <?php echo $statusClass; ?>">
                            <div class="proj-header">
                                <div>
                                    <span class="proj-title"><?php echo htmlspecialchars($proj['fyp_projecttitle']); ?></span>
                                    <span class="proj-badge <?php echo $badgeClass; ?>"><?php echo $proj['fyp_projectstatus']; ?></span>
                                </div>
                                <button type="button" class="btn-edit" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($proj)); ?>)">
                                    <i class="fa fa-edit"></i> Edit Details
                                </button>
                            </div>
                            <div class="proj-meta">
                                <span><i class="fa fa-tag"></i> <?php echo htmlspecialchars($proj['fyp_projectcat']); ?></span>
                                <span><i class="fa fa-users"></i> <?php echo htmlspecialchars($proj['fyp_projecttype']); ?></span>
                                <span><i class="fa fa-calendar-alt"></i> Session: <?php echo htmlspecialchars($proj['fyp_acdyear'] ?? 'N/A') . ' (' . htmlspecialchars($proj['fyp_intake'] ?? '') . ')'; ?></span>
                            </div>
                            <div class="proj-desc"><?php echo htmlspecialchars($proj['fyp_description']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <div id="editModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <span>Edit Project Details</span>
                <span class="close-modal" onclick="closeModal()">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="project_id" id="edit_pid">
                
                <div class="form-group">
                    <label>Project Title</label>
                    <input type="text" name="project_title" id="edit_title" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>Category / Domain</label>
                    <select name="project_cat" id="edit_cat" class="form-control">
                        <option value="Software Engineering">Software Engineering</option>
                        <option value="Networking">Networking</option>
                        <option value="AI">AI</option>
                        <option value="Cybersecurity">Cybersecurity</option>
                        <option value="Data Science">Data Science</option>
                        <option value="IoT">IoT</option>
                    </select>
                </div>

                <div style="display:flex; gap:15px;">
                    <div class="form-group" style="flex:1;">
                        <label>Type</label>
                        <select name="project_type" id="edit_type" class="form-control">
                            <option value="Individual">Individual</option>
                            <option value="Group">Group</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Status</label>
                        <select name="project_status" id="edit_status" class="form-control">
                            <option value="Open">Open</option>
                            <option value="Taken">Taken</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="edit_desc" class="form-control" rows="4" required></textarea>
                </div>

                <div class="form-group">
                    <label>Technical Requirements</label>
                    <textarea name="requirement" id="edit_req" class="form-control" rows="2"></textarea>
                </div>

                <div style="text-align:right;">
                    <button type="button" class="btn-edit" style="background:#6c757d; margin-right:10px;" onclick="closeModal()">Cancel</button>
                    <button type="submit" name="update_project" class="btn-edit" style="background:#28a745;">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('editModal');

        function openEditModal(data) {
            document.getElementById('edit_pid').value = data.fyp_projectid;
            document.getElementById('edit_title').value = data.fyp_projecttitle;
            document.getElementById('edit_cat').value = data.fyp_projectcat; // Simple match, ensure DB values match options
            document.getElementById('edit_type').value = data.fyp_projecttype;
            document.getElementById('edit_status').value = data.fyp_projectstatus;
            document.getElementById('edit_desc').value = data.fyp_description;
            document.getElementById('edit_req').value = data.fyp_requirement;
            
            modal.classList.add('show');
        }

        function closeModal() {
            modal.classList.remove('show');
        }

        window.onclick = function(e) {
            if (e.target == modal) closeModal();
        }
    </script>
</body>
</html>