<?php
// ====================================================
// Supervisor_manage_project.php - Redesigned UI + Preserved Logic
// ====================================================
include("connect.php");

// 1. 基础验证
session_start();
$auth_user_id = $_GET['auth_user_id'] ?? $_SESSION['user_id'] ?? null;
$current_page = 'my_projects'; // Sidebar Highlight

if (!$auth_user_id) {
    echo "<script>alert('Please login first.'); window.location.href='login.php';</script>";
    exit;
}

// ----------------------------------------------------
// 2. 获取 Supervisor 资料
// ----------------------------------------------------
$user_name = "Supervisor"; 
$user_avatar = "image/user.png"; 
$my_staff_id = ""; 

if (isset($conn)) {
    $stmt = $conn->prepare("SELECT fyp_staffid, fyp_name, fyp_profileimg FROM supervisor WHERE fyp_userid = ?");
    $stmt->bind_param("i", $auth_user_id); 
    $stmt->execute(); 
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) { 
        $my_staff_id = $row['fyp_staffid']; 
        $user_name = $row['fyp_name']; 
        if(!empty($row['fyp_profileimg'])) $user_avatar = $row['fyp_profileimg']; 
    }
    $stmt->close();
}

// ----------------------------------------------------
// 3. 处理编辑提交 (UPDATE Project)
// ----------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_project'])) {
    
    $target_pid = $_POST['project_id'];
    $new_title = $_POST['edit_title'];
    $new_cat = $_POST['edit_category']; 
    $new_desc = $_POST['edit_desc'];
    $new_req = $_POST['edit_req'];
    $new_archive = $_POST['edit_archive_status']; 

    // Security Check
    $check_sql = "SELECT fyp_projectid FROM project WHERE fyp_projectid = ? AND fyp_staffid = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("is", $target_pid, $my_staff_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows > 0) {
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
                echo "<script>alert('Project updated successfully!'); window.location.href='Supervisor_manage_project.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
            } else {
                echo "<script>alert('Update failed: " . $up_stmt->error . "');</script>";
            }
            $up_stmt->close();
        }
    } else {
        echo "<script>alert('Security Error: You do not own this project.');</script>";
    }
    $check_stmt->close();
}

// ----------------------------------------------------
// 4. 获取我的项目列表
// ----------------------------------------------------
$my_projects = [];
if (!empty($my_staff_id)) {
    $sql = "SELECT p.*, ay.fyp_acdyear, ay.fyp_intake 
            FROM project p 
            LEFT JOIN academic_year ay ON p.fyp_academicid = ay.fyp_academicid
            WHERE p.fyp_staffid = ? 
            ORDER BY p.fyp_datecreated DESC";
            
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $my_staff_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $my_projects[] = $row;
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
            'student_list'     => ['name' => 'Student List', 'icon' => 'fa-list', 'link' => 'Supervisor_student_list.php'],
        ]
    ],
    'fyp_project' => [
        'name' => 'FYP Project',
        'icon' => 'fa-project-diagram',
        'sub_items' => [
            'propose_project' => ['name' => 'Propose Project', 'icon' => 'fa-plus-circle', 'link' => 'supervisor_purpose.php'],
            'my_projects'     => ['name' => 'My Projects', 'icon' => 'fa-folder-open', 'link' => 'Supervisor_manage_project.php'],
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
    <title>Manage Projects</title>
    <link rel="icon" type="image/png" href="image/ladybug.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap');
        
        :root { --primary-color: #0056b3; --primary-hover: #004494; --secondary-color: #f8f9fa; --text-color: #333; --border-color: #e0e0e0; --card-shadow: 0 4px 6px rgba(0,0,0,0.05); --bg-color: #f4f6f9; --sidebar-bg: #004085; --sidebar-hover: #003366; --sidebar-text: #e0e0e0; }
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

        /* Content Card */
        .card { background: #fff; padding: 25px; border-radius: 12px; box-shadow: var(--card-shadow); }
        .section-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px; }
        .section-header h2 { margin: 0; font-size: 20px; color: #333; display: flex; align-items: center; gap: 10px; }

        /* Table */
        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .data-table th { background: #f8f9fa; text-align: left; padding: 15px; color: #555; font-weight: 600; font-size: 13px; border-bottom: 2px solid #eee; }
        .data-table td { padding: 15px; border-bottom: 1px solid #f0f0f0; color: #333; vertical-align: middle; font-size: 14px; }
        .data-table tr:hover { background-color: #f9fbfd; }

        /* Badges */
        .badge { padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-block; }
        .badge-status-Taken { background: #e3effd; color: #0056b3; }
        .badge-status-Open { background: #d4edda; color: #155724; }
        .badge-vis-Active { background: #d1e7dd; color: #0f5132; }
        .badge-vis-Archived { background: #f8d7da; color: #842029; }

        .btn-main { background: var(--primary-color); color: white; border: none; padding: 8px 16px; border-radius: 6px; font-size: 14px; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; cursor: pointer; transition: 0.2s; }
        .btn-main:hover { background: var(--primary-hover); }
        
        .btn-edit { background: #ffc107; color: #333; border: none; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; }
        .btn-edit:hover { background: #e0a800; }

        /* Modal */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-box { background: #fff; width: 600px; max-width: 90%; max-height: 90vh; overflow-y: auto; border-radius: 12px; padding: 30px; animation: popIn 0.3s ease; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .close-modal { font-size: 24px; cursor: pointer; color: #999; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; font-size: 13px; color: #555; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; box-sizing: border-box; }
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
        
        @keyframes popIn { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        @media (max-width: 900px) { .main-content-wrapper { margin-left: 0; width: 100%; } }
    </style>
</head>
<body>

    <nav class="main-menu">
        <ul>
            <?php foreach ($menu_items as $key => $item): ?>
                <?php 
                    $isActive = ($key == 'fyp_project');
                    $hasActiveChild = false;
                    if (isset($item['sub_items'])) {
                        foreach ($item['sub_items'] as $sub_key => $sub) {
                            if ($sub_key == $current_page) { $hasActiveChild = true; break; }
                        }
                    }
                    $linkUrl = isset($item['link']) ? $item['link'] : "#";
                    if ($linkUrl !== "#") { $separator = (strpos($linkUrl, '?') !== false) ? '&' : '?'; $linkUrl .= $separator . "auth_user_id=" . urlencode($auth_user_id); }
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
                                if ($subLinkUrl !== "#") { $separator = (strpos($subLinkUrl, '?') !== false) ? '&' : '?'; $subLinkUrl .= $separator . "auth_user_id=" . urlencode($auth_user_id); }
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
        <header class="page-header">
            <div class="welcome-text"><h1>Manage Projects</h1><p>Edit and track your proposed FYP projects.</p></div>
            <div class="logo-section"><img src="image/ladybug.png" alt="Logo" class="logo-img"><span class="system-title">FYP Portal</span></div>
            <div style="display:flex; align-items:center; gap:10px;">
                <span style="font-size:13px; color:#666; background:#f0f0f0; padding:5px 10px; border-radius:20px;">Supervisor</span>
                <?php if(!empty($user_avatar) && $user_avatar != 'image/user.png'): ?>
                    <img src="<?php echo htmlspecialchars($user_avatar); ?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
                <?php else: ?>
                    <div style="width:40px;height:40px;border-radius:50%;background:#0056b3;color:white;display:flex;align-items:center;justify-content:center;font-weight:bold;"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div>
                <?php endif; ?>
            </div>
        </header>

        <main class="main-content">
            <div class="card">
                <div class="section-header">
                    <h2><i class="fa fa-folder-open"></i> My Project List</h2>
                    <a href="supervisor_purpose.php?auth_user_id=<?php echo $auth_user_id; ?>" class="btn-main"><i class="fa fa-plus"></i> Propose New</a>
                </div>

                <?php if (count($my_projects) > 0): ?>
                    <table class="data-table">
                        <thead><tr><th>Project Info</th><th>Category</th><th>Status</th><th>Visibility</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php foreach ($my_projects as $proj): ?>
                                <tr>
                                    <td>
                                        <strong style="color:#333; font-size:15px;"><?php echo htmlspecialchars($proj['fyp_projecttitle']); ?></strong><br>
                                        <small style="color:#888;">Type: <?php echo $proj['fyp_projecttype']; ?> | <?php echo htmlspecialchars($proj['fyp_acdyear'] ?? 'N/A'); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($proj['fyp_projectcat']); ?></td>
                                    <td><span class="badge badge-status-<?php echo $proj['fyp_projectstatus']; ?>"><?php echo $proj['fyp_projectstatus']; ?></span></td>
                                    <td><span class="badge badge-vis-<?php echo $proj['fyp_archive_status']; ?>"><?php echo $proj['fyp_archive_status']; ?></span></td>
                                    <td>
                                        <button type="button" class="btn-edit" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($proj)); ?>)">
                                            <i class="fa fa-edit"></i> Edit
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="text-align:center; padding:50px; color:#999;"><i class="fa fa-box-open" style="font-size:48px; opacity:0.3; margin-bottom:15px;"></i><p>You haven't proposed any projects yet.</p></div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header"><h3>Edit Project Details</h3><span class="close-modal" onclick="closeModal()">&times;</span></div>
            <form method="POST">
                <input type="hidden" name="project_id" id="modal_project_id">
                <input type="hidden" name="update_project" value="1">
                <div class="form-group"><label>Project Title</label><input type="text" name="edit_title" id="modal_title" class="form-control" required></div>
                <div class="form-group"><label>Visibility Status</label><select name="edit_archive_status" id="modal_archive" class="form-control"><option value="Active">Active</option><option value="Archived">Archived</option></select></div>
                <div class="form-group"><label>Category</label><select name="edit_category" id="modal_cat" class="form-control"><option value="Software Eng.">Software Engineering</option><option value="Networking">Networking</option><option value="AI">AI</option><option value="Cybersecurity">Cybersecurity</option><option value="Data Science">Data Science</option><option value="IoT">IoT</option></select></div>
                <div class="form-group"><label>Description</label><textarea name="edit_desc" id="modal_desc" class="form-control" rows="3" required></textarea></div>
                <div class="form-group"><label>Requirements</label><textarea name="edit_req" id="modal_req" class="form-control" rows="2"></textarea></div>
                <div class="modal-footer"><button type="button" class="btn-edit" style="background:#f1f1f1;" onclick="closeModal()">Cancel</button><button type="submit" class="btn-main">Save Changes</button></div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('editModal');
        function openEditModal(data) {
            document.getElementById('modal_project_id').value = data.fyp_projectid;
            document.getElementById('modal_title').value = data.fyp_projecttitle;
            document.getElementById('modal_cat').value = data.fyp_projectcat;
            document.getElementById('modal_desc').value = data.fyp_description;
            document.getElementById('modal_req').value = data.fyp_requirement;
            document.getElementById('modal_archive').value = data.fyp_archive_status;
            modal.style.display = 'flex';
        }
        function closeModal() { modal.style.display = 'none'; }
        window.onclick = function(e) { if (e.target == modal) closeModal(); }
        
        function toggleSubmenu(element) {
            const menuItem = element.parentElement;
            const isOpen = menuItem.classList.contains('open');
            document.querySelectorAll('.menu-item').forEach(item => { if (item !== menuItem) item.classList.remove('open'); });
            if (isOpen) menuItem.classList.remove('open'); else menuItem.classList.add('open');
        }
    </script>
</body>
</html>