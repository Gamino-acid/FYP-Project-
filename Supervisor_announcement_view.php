<?php
// ====================================================
// Supervisor_announcement_view.php - 公告历史与管理
// ====================================================
include("connect.php");

// 1. 验证用户登录
$auth_user_id = $_GET['auth_user_id'] ?? null;
// 手动设置当前页面，以便侧边栏高亮 (对应你之前菜单里的 key)
$current_page = 'view_announcements'; 

if (!$auth_user_id) { header("location: login.php"); exit; }

// 2. 获取导师信息
$user_name = "Supervisor"; 
$user_avatar = "image/user.png"; 
$sv_id = 0;
$my_staff_id = "";

if (isset($conn)) {
    $sql_sv = "SELECT * FROM supervisor WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_sv)) {
        $stmt->bind_param("i", $auth_user_id); $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $my_staff_id = $row['fyp_staffid']; // 获取 Staff ID 用于查询公告
            
            if (!empty($row['fyp_name'])) $user_name = $row['fyp_name'];
            if (!empty($row['fyp_profileimg'])) $user_avatar = $row['fyp_profileimg'];
        }
        $stmt->close();
    }
}

// ====================================================
// 3. 处理 POST 请求 (编辑 / 归档 / 删除)
// ====================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // A. 编辑公告 (Update)
    if (isset($_POST['edit_announcement'])) {
        $anno_id = $_POST['announce_id'];
        $subject = $_POST['edit_subject'];
        $description = $_POST['edit_description'];
        
        $sql_upd = "UPDATE announcement SET fyp_subject = ?, fyp_description = ? WHERE fyp_annouceid = ?";
        if ($stmt = $conn->prepare($sql_upd)) {
            $stmt->bind_param("ssi", $subject, $description, $anno_id);
            if ($stmt->execute()) {
                echo "<script>alert('Announcement updated successfully!'); window.location.href='Supervisor_announcement_view.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
            } else {
                echo "<script>alert('Error updating.');</script>";
            }
            $stmt->close();
        }
    }

    // B. 切换状态 (Toggle Status: Active <-> Archived)
    if (isset($_POST['toggle_status'])) {
        $anno_id = $_POST['announce_id'];
        $current_status = $_POST['current_status'];
        $new_status = ($current_status == 'Active') ? 'Archived' : 'Active';
        
        $sql_stat = "UPDATE announcement SET fyp_status = ? WHERE fyp_annouceid = ?";
        if ($stmt = $conn->prepare($sql_stat)) {
            $stmt->bind_param("si", $new_status, $anno_id);
            $stmt->execute();
            echo "<script>window.location.href='Supervisor_announcement_view.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
            $stmt->close();
        }
    }

    // C. 删除公告 (Delete)
    if (isset($_POST['delete_announcement'])) {
        $anno_id = $_POST['announce_id'];
        $sql_del = "DELETE FROM announcement WHERE fyp_annouceid = ?";
        if ($stmt = $conn->prepare($sql_del)) {
            $stmt->bind_param("i", $anno_id);
            $stmt->execute();
            echo "<script>alert('Announcement deleted.'); window.location.href='Supervisor_announcement_view.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
            $stmt->close();
        }
    }
}

// ====================================================
// 4. 获取公告列表
// ====================================================
$announcements = [];
if (!empty($my_staff_id)) {
    $sql_list = "SELECT * FROM announcement WHERE fyp_staffid = ? ORDER BY fyp_datecreated DESC";
    if ($stmt = $conn->prepare($sql_list)) {
        $stmt->bind_param("s", $my_staff_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            // 如果数据库还没有 fyp_status 列，默认为 Active (防止报错)
            if (!isset($row['fyp_status'])) $row['fyp_status'] = 'Active'; 
            $announcements[] = $row;
        }
        $stmt->close();
    }
}

// 菜单定义 (保持一致)
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
    'announcement' => [
        'name' => 'Announcement',
        'icon' => 'fa-bullhorn',
        'sub_items' => [
            'post_announcement' => ['name' => 'Post Announcement', 'icon' => 'fa-pen-square', 'link' => 'supervisor_announcement.php'],
            'view_announcements' => ['name' => 'View History', 'icon' => 'fa-history', 'link' => 'Supervisor_announcement_view.php'],
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
    <title>Announcement History</title>
    <link rel="icon" type="image/png" href="<?php echo $user_avatar; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* 复用样式 */
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap');
        :root { --primary-color: #0056b3; --primary-hover: #004494; --secondary-color: #f4f4f9; --text-color: #333; --border-color: #e0e0e0; --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); }
        body { font-family: 'Poppins', sans-serif; margin: 0; background: linear-gradient(135deg, #eef2f7, #ffffff); color: var(--text-color); min-height: 100vh; display: flex; flex-direction: column; }
        
        /* Topbar & Sidebar */
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
        .submenu { list-style: none; padding: 0; margin: 0; background-color: #fafafa; display: none; }
        .menu-item.has-active-child .submenu, .menu-item:hover .submenu { display: block; }
        .submenu .menu-link { padding-left: 58px; font-size: 14px; padding-top: 10px; padding-bottom: 10px; }
        .main-content { flex: 1; display: flex; flex-direction: column; gap: 20px; }

        /* Announcement Cards */
        .history-header { font-size: 24px; font-weight: 600; color: #333; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        
        .anno-card { background: #fff; border-radius: 12px; box-shadow: var(--card-shadow); padding: 20px; margin-bottom: 20px; border-left: 5px solid; position: relative; transition: transform 0.2s; }
        .anno-card:hover { transform: translateY(-2px); }
        
        .anno-active { border-left-color: #28a745; }
        .anno-archived { border-left-color: #6c757d; background-color: #f9f9f9; }
        
        .anno-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; }
        .anno-subject { font-size: 18px; font-weight: 600; color: #333; }
        .anno-status { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
        .st-Active { background: #d4edda; color: #155724; }
        .st-Archived { background: #e2e3e5; color: #383d41; }
        
        .anno-meta { font-size: 13px; color: #777; margin-bottom: 15px; display: flex; gap: 20px; }
        .anno-desc { font-size: 14px; color: #555; line-height: 1.6; white-space: pre-wrap; margin-bottom: 20px; }
        
        .anno-actions { display: flex; gap: 10px; justify-content: flex-end; border-top: 1px solid #eee; padding-top: 15px; }
        .btn-action { border: none; padding: 8px 15px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 500; display: flex; align-items: center; gap: 5px; transition: 0.2s; }
        
        .btn-edit { background: #007bff; color: white; }
        .btn-edit:hover { background: #0056b3; }
        
        .btn-archive { background: #ffc107; color: #333; }
        .btn-archive:hover { background: #e0a800; }
        
        .btn-activate { background: #28a745; color: white; }
        .btn-activate:hover { background: #1e7e34; }
        
        .btn-delete { background: #dc3545; color: white; }
        .btn-delete:hover { background: #c82333; }

        .empty-state { text-align: center; padding: 40px; color: #999; background: #fff; border-radius: 12px; }

        /* Modal */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-overlay.show { display: flex; }
        .modal-box { background: #fff; padding: 25px; border-radius: 12px; width: 90%; max-width: 600px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
        .modal-title { font-size: 20px; font-weight: 600; margin-bottom: 20px; color: #333; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; color: #555; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; font-family: inherit; }
        textarea.form-control { resize: vertical; min-height: 120px; }
        
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
        .btn-save { background: var(--primary-color); color: white; padding: 10px 25px; border: none; border-radius: 6px; cursor: pointer; }
        .btn-close { background: #6c757d; color: white; padding: 10px 25px; border: none; border-radius: 6px; cursor: pointer; }

        @media (max-width: 900px) { .layout-container { flex-direction: column; } .sidebar { width: 100%; min-height: auto; } }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="logo"><img src="image/ladybug.png" alt="Logo" style="width: 32px; margin-right: 10px;"> FYP System</div>
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
                        $isActive = ($key == 'announcement'); 
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
            <div class="history-header">Announcement History</div>

            <?php if (count($announcements) > 0): ?>
                <?php foreach ($announcements as $anno): 
                    $status = $anno['fyp_status'];
                    $isArchived = ($status == 'Archived');
                    $cardClass = $isArchived ? 'anno-archived' : 'anno-active';
                ?>
                    <div class="anno-card <?php echo $cardClass; ?>">
                        <div class="anno-top">
                            <div class="anno-subject"><?php echo htmlspecialchars($anno['fyp_subject']); ?></div>
                            <span class="anno-status st-<?php echo $status; ?>"><?php echo $status; ?></span>
                        </div>
                        <div class="anno-meta">
                            <span><i class="fa fa-calendar"></i> <?php echo date('d M Y, h:i A', strtotime($anno['fyp_datecreated'])); ?></span>
                            <span><i class="fa fa-users"></i> To: <?php echo htmlspecialchars($anno['fyp_receiver']); ?></span>
                        </div>
                        <div class="anno-desc"><?php echo nl2br(htmlspecialchars($anno['fyp_description'])); ?></div>
                        
                        <div class="anno-actions">
                            <button type="button" class="btn-action btn-edit" onclick="openEditModal(
                                '<?php echo $anno['fyp_annouceid']; ?>', 
                                '<?php echo addslashes(htmlspecialchars($anno['fyp_subject'])); ?>', 
                                '<?php echo addslashes(htmlspecialchars($anno['fyp_description'])); ?>'
                            )">
                                <i class="fa fa-edit"></i> Edit
                            </button>

                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="announce_id" value="<?php echo $anno['fyp_annouceid']; ?>">
                                <input type="hidden" name="current_status" value="<?php echo $status; ?>">
                                <?php if ($isArchived): ?>
                                    <button type="submit" name="toggle_status" class="btn-action btn-activate">
                                        <i class="fa fa-box-open"></i> Set Active
                                    </button>
                                <?php else: ?>
                                    <button type="submit" name="toggle_status" class="btn-action btn-archive">
                                        <i class="fa fa-archive"></i> Archive
                                    </button>
                                <?php endif; ?>
                            </form>

                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to permanently delete this announcement?');">
                                <input type="hidden" name="announce_id" value="<?php echo $anno['fyp_annouceid']; ?>">
                                <button type="submit" name="delete_announcement" class="btn-action btn-delete">
                                    <i class="fa fa-trash"></i> Delete
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fa fa-bullhorn" style="font-size:48px; margin-bottom:15px;"></i>
                    <p>No announcements found.</p>
                    <a href="supervisor_announcement.php?auth_user_id=<?php echo $auth_user_id; ?>" style="color:var(--primary-color);">Create one now</a>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <div id="editModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-title">Edit Announcement</div>
            <form method="POST">
                <input type="hidden" name="announce_id" id="edit_id">
                
                <div class="form-group">
                    <label>Subject</label>
                    <input type="text" name="edit_subject" id="edit_subject" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="edit_description" id="edit_description" class="form-control" required></textarea>
                </div>

                <div class="modal-footer">
                    <button type="submit" name="edit_announcement" class="btn-save">Save Changes</button>
                    <button type="button" class="btn-close" onclick="closeEditModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const editModal = document.getElementById('editModal');

        function openEditModal(id, subject, description) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_subject').value = subject;
            // 处理换行符，将 <br /> 替换回换行
            const desc = description.replace(/<br\s*\/?>/mg, "\n");
            document.getElementById('edit_description').value = desc;
            
            editModal.classList.add('show');
        }

        function closeEditModal() {
            editModal.classList.remove('show');
        }

        window.onclick = function(e) {
            if (e.target.classList.contains('modal-overlay')) {
                e.target.classList.remove('show');
            }
        }
    </script>
</body>
</html>