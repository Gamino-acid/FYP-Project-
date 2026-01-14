<?php
// ====================================================
// Supervisor_manage_project.php - 管理/编辑/归档项目
// ====================================================
include("connect.php");

// 1. 基础验证
session_start();
$auth_user_id = $_GET['auth_user_id'] ?? $_SESSION['user_id'] ?? null;
$current_page = 'my_projects'; // 对应 Sidebar 高亮

if (!$auth_user_id) {
    echo "<script>alert('Please login first.'); window.location.href='login.php';</script>";
    exit;
}

// ----------------------------------------------------
// 2. 获取 Supervisor 资料 (主要是 Staff ID)
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
    $new_cat = $_POST['edit_category']; // Domain
    $new_desc = $_POST['edit_desc'];
    $new_req = $_POST['edit_req'];
    $new_archive = $_POST['edit_archive_status']; // Active or Archived

    // 安全检查：确保这个 Project 属于当前的 Staff ID
    $check_sql = "SELECT fyp_projectid FROM project WHERE fyp_projectid = ? AND fyp_staffid = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("is", $target_pid, $my_staff_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows > 0) {
        // 执行更新
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
    // 获取 Academic Year 以便显示
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
            'my_projects'     => ['name' => 'My Projects', 'icon' => 'fa-folder-open', 'link' => 'Supervisor_manage_project.php'], // 当前页
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
    <title>Manage My Projects</title>
    <link rel="icon" type="image/png" href="<?php echo $user_avatar; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap');
        :root { --primary-color: #0056b3; --primary-hover: #004494; --secondary-color: #f4f4f9; --text-color: #333; --border-color: #e0e0e0; --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); --gradient-start: #eef2f7; --gradient-end: #ffffff; --sidebar-width: 260px; }
        body { font-family: 'Poppins', sans-serif; margin: 0; background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end)); color: var(--text-color); min-height: 100vh; display: flex; flex-direction: column; }
        
        /* Layout & Topbar & Sidebar (Standard) */
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
        
        /* Table Styles */
        .proj-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .proj-table th { background: #f8f9fa; text-align: left; padding: 15px; color: #555; font-weight: 600; border-bottom: 2px solid #eee; }
        .proj-table td { padding: 15px; border-bottom: 1px solid #eee; color: #333; vertical-align: middle; }
        .proj-table tr:hover { background-color: #f9fbfd; }
        
        /* Badges */
        .badge { padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-block; }
        .badge-status-Taken { background: #e3effd; color: #0056b3; }
        .badge-status-Open { background: #d4edda; color: #155724; }
        
        /* Visibility Badges */
        .badge-vis-Active { background: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; }
        .badge-vis-Archived { background: #f8d7da; color: #842029; border: 1px solid #f5c2c7; }

        .btn-edit { background: #ffc107; color: #333; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 500; transition: all 0.2s; display: inline-flex; align-items: center; gap: 5px; }
        .btn-edit:hover { background: #e0a800; transform: translateY(-1px); }

        /* Modal Styles */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-box { background: #fff; width: 600px; max-width: 90%; max-height: 90vh; overflow-y: auto; border-radius: 12px; box-shadow: 0 15px 40px rgba(0,0,0,0.2); padding: 30px; animation: popIn 0.3s ease; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px; }
        .modal-header h3 { margin: 0; color: #333; }
        .close-modal { font-size: 24px; cursor: pointer; color: #999; transition: color 0.2s; }
        .close-modal:hover { color: #333; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; font-size: 13px; color: #555; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; box-sizing: border-box; }
        textarea.form-control { resize: vertical; min-height: 100px; }
        
        .modal-footer { margin-top: 25px; display: flex; justify-content: flex-end; gap: 10px; }
        .btn-save { background: var(--primary-color); color: white; border: none; padding: 10px 25px; border-radius: 6px; cursor: pointer; font-weight: 600; }
        .btn-close { background: #f1f1f1; color: #333; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; }

        @keyframes popIn { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        @media (max-width: 900px) { .layout-container { flex-direction: column; } .sidebar { width: 100%; min-height: auto; } }
    </style>
</head>
<body>

    <header class="topbar">
        <div class="logo">FYP System</div>
        <div class="topbar-right">
            <div class="user-profile-summary">
                <span class="user-name-display"><?php echo htmlspecialchars($user_name); ?></span>
                <span class="user-role-badge">Supervisor</span>
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
                                    $isSubActive = ($sub_key == 'my_projects');
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
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid #eee; padding-bottom:15px; margin-bottom:20px;">
                    <h2 style="margin:0; color:#333;"><i class="fa fa-folder-open"></i> Manage My Projects</h2>
                    <a href="supervisor_purpose.php?auth_user_id=<?php echo $auth_user_id; ?>" class="btn-edit" style="background:var(--primary-color); color:white; text-decoration:none;">
                        <i class="fa fa-plus"></i> Propose New
                    </a>
                </div>

                <?php if (count($my_projects) > 0): ?>
                    <table class="proj-table">
                        <thead>
                            <tr>
                                <th>Project Info</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Visibility</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($my_projects as $proj): ?>
                                <tr>
                                    <td>
                                        <strong style="color:#333; font-size:15px;"><?php echo htmlspecialchars($proj['fyp_projecttitle']); ?></strong><br>
                                        <small style="color:#888;">
                                            Type: <?php echo $proj['fyp_projecttype']; ?> | 
                                            <?php echo htmlspecialchars($proj['fyp_acdyear'] ?? 'N/A'); ?>
                                        </small>
                                    </td>
                                    <td><?php echo htmlspecialchars($proj['fyp_projectcat']); ?></td>
                                    <td>
                                        <span class="badge badge-status-<?php echo $proj['fyp_projectstatus']; ?>">
                                            <?php echo $proj['fyp_projectstatus']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-vis-<?php echo $proj['fyp_archive_status']; ?>">
                                            <?php echo $proj['fyp_archive_status']; ?>
                                        </span>
                                    </td>
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
                    <div style="text-align:center; padding:50px; color:#999;">
                        <i class="fa fa-box-open" style="font-size:48px; opacity:0.3; margin-bottom:15px;"></i>
                        <p>You haven't proposed any projects yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <div id="editModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Edit Project Details</h3>
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
                    <label>Visibility Status (Archive to hide from students)</label>
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
                    <textarea name="edit_desc" id="modal_desc" class="form-control" required></textarea>
                </div>

                <div class="form-group">
                    <label>Requirements</label>
                    <textarea name="edit_req" id="modal_req" class="form-control"></textarea>
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
            // 将 PHP 传来的 JSON 数据填充到表单
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