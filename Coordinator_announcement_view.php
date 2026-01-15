<?php
// ====================================================
// Coordinator_announcement_view.php - 高级公告管理
// ====================================================
include("connect.php");

// 1. 验证用户登录
$auth_user_id = $_GET['auth_user_id'] ?? null;
$current_page = 'view_announcements'; 

if (!$auth_user_id) { header("location: login.php"); exit; }

// 2. 获取 Coordinator 信息
$user_name = "Coordinator"; 
$user_avatar = "image/user.png"; 
$my_staff_id = "";

if (isset($conn)) {
    $sql_coor = "SELECT * FROM coordinator WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_coor)) {
        $stmt->bind_param("i", $auth_user_id); $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $row = $res->fetch_assoc();
            // 获取 Staff ID (优先使用 fyp_staffid)
            $my_staff_id = !empty($row['fyp_staffid']) ? $row['fyp_staffid'] : $row['fyp_coordinatorid'];
            
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
    
    // A. 编辑公告
    if (isset($_POST['edit_announcement'])) {
        $anno_id = $_POST['announce_id'];
        $subject = $_POST['edit_subject'];
        $description = $_POST['edit_description'];
        
        $sql_upd = "UPDATE announcement SET fyp_subject = ?, fyp_description = ? WHERE fyp_annouceid = ?";
        if ($stmt = $conn->prepare($sql_upd)) {
            $stmt->bind_param("ssi", $subject, $description, $anno_id);
            if ($stmt->execute()) {
                echo "<script>alert('Updated successfully!'); window.location.href='Coordinator_announcement_view.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
            }
            $stmt->close();
        }
    }

    // B. 切换状态
    if (isset($_POST['toggle_status'])) {
        $anno_id = $_POST['announce_id'];
        $current_status = $_POST['current_status'];
        $new_status = ($current_status == 'Active') ? 'Archived' : 'Active';
        
        $sql_stat = "UPDATE announcement SET fyp_status = ? WHERE fyp_annouceid = ?";
        if ($stmt = $conn->prepare($sql_stat)) {
            $stmt->bind_param("si", $new_status, $anno_id);
            $stmt->execute();
            echo "<script>window.location.href='Coordinator_announcement_view.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
            $stmt->close();
        }
    }

    // C. 删除公告
    if (isset($_POST['delete_announcement'])) {
        $anno_id = $_POST['announce_id'];
        $sql_del = "DELETE FROM announcement WHERE fyp_annouceid = ?";
        if ($stmt = $conn->prepare($sql_del)) {
            $stmt->bind_param("i", $anno_id);
            $stmt->execute();
            echo "<script>alert('Announcement deleted.'); window.location.href='Coordinator_announcement_view.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
            $stmt->close();
        }
    }
}

// ====================================================
// 4. 获取筛选参数 & 构建查询
// ====================================================

// 视图模式: 'mine' (默认) 或 'all'
$view_mode = $_GET['view'] ?? 'mine';
$filter_year = $_GET['filter_year'] ?? '';
$search_name = $_GET['search_name'] ?? '';

$announcements = [];

// A. 获取所有可用的年份 (用于下拉菜单)
$years_available = [];
$year_sql = "SELECT DISTINCT YEAR(fyp_datecreated) as yr FROM announcement ORDER BY yr DESC";
$res_y = $conn->query($year_sql);
if($res_y) {
    while($row_y = $res_y->fetch_assoc()) $years_available[] = $row_y['yr'];
}

// B. 构建主查询
// 我们需要 JOIN supervisor 表和 coordinator 表来获取发布者的名字
$sql_list = "SELECT a.*, 
                    COALESCE(s.fyp_name, c.fyp_name, a.fyp_staffid) as author_name 
             FROM announcement a
             LEFT JOIN supervisor s ON a.fyp_staffid = s.fyp_staffid
             LEFT JOIN coordinator c ON a.fyp_staffid = c.fyp_staffid
             WHERE 1=1 ";

$params = [];
$types = "";

if ($view_mode == 'mine') {
    // 只能看自己
    $sql_list .= " AND a.fyp_staffid = ? ";
    $params[] = $my_staff_id;
    $types .= "s";
} else {
    // 看所有人 (All) - 可以在这里应用筛选
    
    // 1. 年份筛选
    if (!empty($filter_year)) {
        $sql_list .= " AND YEAR(a.fyp_datecreated) = ? ";
        $params[] = $filter_year;
        $types .= "s";
    }

    // 2. 名字搜索
    if (!empty($search_name)) {
        $sql_list .= " AND (s.fyp_name LIKE ? OR c.fyp_name LIKE ?) ";
        $search_term = "%" . $search_name . "%";
        $params[] = $search_term;
        $params[] = $search_term;
        $types .= "ss";
    }
}

$sql_list .= " ORDER BY a.fyp_datecreated DESC";

if ($stmt = $conn->prepare($sql_list)) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        if (!isset($row['fyp_status'])) $row['fyp_status'] = 'Active'; 
        
        // 标记是否是自己发的
        $row['is_mine'] = ($row['fyp_staffid'] == $my_staff_id);
        
        $announcements[] = $row;
    }
    $stmt->close();
}

// 菜单定义 (Coordinator)
$menu_items = [
    'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home', 'link' => 'Coordinator_mainpage.php?page=dashboard'],
    'profile'   => ['name' => 'My Profile', 'icon' => 'fa-user', 'link' => 'Coordinator_profile.php'],
    'project_mgmt' => ['name' => 'Project Mgmt', 'icon' => 'fa-tasks', 'sub_items' => ['propose_project' => ['name' => 'Propose Project', 'icon' => 'fa-plus-circle', 'link' => 'Coordinator_purpose.php'], 'project_requests' => ['name' => 'Project Requests', 'icon' => 'fa-envelope-open-text', 'link' => 'Coordinator_projectreq.php'], 'allocation' => ['name' => 'Auto Allocation', 'icon' => 'fa-bullseye', 'link' => 'Coordinator_mainpage.php?page=allocation']]],
    'assessment' => ['name' => 'Assessment', 'icon' => 'fa-clipboard-check', 'sub_items' => ['propose_assignment' => ['name' => 'Create Assignment', 'icon' => 'fa-plus', 'link' => 'Coordinator_assignment_purpose.php'], 'grade_assignment' => ['name' => 'Grade Assignments', 'icon' => 'fa-check-square', 'link' => 'Coordinator_assignment_grade.php']]],
    'announcements' => ['name' => 'Announcement', 'icon' => 'fa-bullhorn', 'sub_items' => [
        'post_announcement' => ['name' => 'Post New', 'icon' => 'fa-pen', 'link' => 'Coordinator_announcement.php'],
        'view_announcements' => ['name' => 'Manage All', 'icon' => 'fa-history', 'link' => 'Coordinator_announcement_view.php']
    ]],
    'schedule' => ['name' => 'My Schedule', 'icon' => 'fa-calendar-alt', 'link' => 'Coordinator_meeting.php'], 
    'data_io' => ['name' => 'Data Management', 'icon' => 'fa-database', 'link' => 'Coordinator_mainpage.php?page=data_io'],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Announcements</title>
    <link rel="icon" type="image/png" href="<?php echo $user_avatar; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* 复用 Coordinator 风格 */
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
        .submenu { list-style: none; padding: 0; margin: 0; background-color: #fafafa; display: none; }
        .menu-item.has-active-child .submenu, .menu-item:hover .submenu { display: block; }
        .submenu .menu-link { padding-left: 58px; font-size: 14px; padding-top: 10px; padding-bottom: 10px; }
        .main-content { flex: 1; display: flex; flex-direction: column; gap: 20px; }

        /* Control Bar & Tabs */
        .control-bar { background: #fff; padding: 15px 25px; border-radius: 12px; box-shadow: var(--card-shadow); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 5px; }
        
        .view-tabs { display: flex; background: #f1f3f5; padding: 5px; border-radius: 8px; }
        .view-tab { padding: 8px 20px; text-decoration: none; color: #666; font-size: 14px; font-weight: 500; border-radius: 6px; transition: 0.2s; }
        .view-tab.active { background: white; color: var(--primary-color); box-shadow: 0 2px 5px rgba(0,0,0,0.05); font-weight: 600; }
        
        .filter-form { display: flex; gap: 10px; align-items: center; }
        .filter-select, .search-input { padding: 8px 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 13px; outline: none; }
        .btn-filter { padding: 8px 15px; background: var(--primary-color); color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; }

        /* Announcement Cards */
        .anno-card { background: #fff; border-radius: 12px; box-shadow: var(--card-shadow); padding: 25px; margin-bottom: 15px; border-left: 5px solid; position: relative; transition: transform 0.2s; }
        .anno-card:hover { transform: translateY(-2px); }
        .anno-active { border-left-color: #28a745; }
        .anno-archived { border-left-color: #6c757d; background-color: #fafafa; }
        
        .anno-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; }
        .anno-subject { font-size: 18px; font-weight: 600; color: #333; }
        .anno-badges { display: flex; gap: 8px; align-items: center; }
        .anno-status { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .st-Active { background: #d4edda; color: #155724; }
        .st-Archived { background: #e2e3e5; color: #383d41; }
        
        .author-badge { font-size: 11px; padding: 4px 10px; background: #e3effd; color: #0056b3; border-radius: 4px; font-weight: 500; display: flex; align-items: center; gap: 5px; }
        .author-badge.is-me { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }

        .anno-meta { font-size: 13px; color: #777; margin-bottom: 15px; display: flex; gap: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .anno-desc { font-size: 14px; color: #444; line-height: 1.6; white-space: pre-wrap; margin-bottom: 15px; }
        
        .anno-actions { display: flex; gap: 10px; justify-content: flex-end; }
        .btn-action { border: none; padding: 7px 15px; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 500; display: flex; align-items: center; gap: 5px; transition: 0.2s; }
        .btn-edit { background: #007bff; color: white; } .btn-edit:hover { background: #0056b3; }
        .btn-archive { background: #ffc107; color: #333; } .btn-archive:hover { background: #e0a800; }
        .btn-activate { background: #28a745; color: white; } .btn-activate:hover { background: #1e7e34; }
        .btn-delete { background: #dc3545; color: white; } .btn-delete:hover { background: #c82333; }

        /* Modal */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-overlay.show { display: flex; }
        .modal-box { background: #fff; padding: 25px; border-radius: 12px; width: 90%; max-width: 600px; }
        .modal-title { font-size: 20px; font-weight: 600; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; color: #555; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; }
        textarea.form-control { resize: vertical; min-height: 120px; }
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
        .btn-save { background: var(--primary-color); color: white; padding: 10px 25px; border: none; border-radius: 6px; cursor: pointer; }
        .btn-close { background: #6c757d; color: white; padding: 10px 25px; border: none; border-radius: 6px; cursor: pointer; }

        @media (max-width: 900px) { .layout-container { flex-direction: column; } .control-bar { flex-direction: column; align-items: stretch; } .filter-form { flex-direction: column; } }
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
                        $isActive = ($key == 'announcements'); 
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
            
            <div class="control-bar">
                <div class="view-tabs">
                    <a href="?auth_user_id=<?php echo $auth_user_id; ?>&view=mine" class="view-tab <?php echo $view_mode == 'mine' ? 'active' : ''; ?>">
                        My Announcements
                    </a>
                    <a href="?auth_user_id=<?php echo $auth_user_id; ?>&view=all" class="view-tab <?php echo $view_mode == 'all' ? 'active' : ''; ?>">
                        Manage All
                    </a>
                </div>

                <?php if ($view_mode == 'all'): ?>
                    <form method="GET" class="filter-form">
                        <input type="hidden" name="auth_user_id" value="<?php echo $auth_user_id; ?>">
                        <input type="hidden" name="view" value="all">
                        
                        <select name="filter_year" class="filter-select">
                            <option value="">All Years</option>
                            <?php foreach($years_available as $yr): ?>
                                <option value="<?php echo $yr; ?>" <?php echo $filter_year == $yr ? 'selected' : ''; ?>>
                                    <?php echo $yr; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <input type="text" name="search_name" placeholder="Search Supervisor..." class="search-input" value="<?php echo htmlspecialchars($search_name); ?>">
                        
                        <button type="submit" class="btn-filter"><i class="fa fa-search"></i> Filter</button>
                    </form>
                <?php endif; ?>
            </div>

            <?php if (count($announcements) > 0): ?>
                <?php foreach ($announcements as $anno): 
                    $status = $anno['fyp_status'];
                    $isArchived = ($status == 'Archived');
                    $cardClass = $isArchived ? 'anno-archived' : 'anno-active';
                ?>
                    <div class="anno-card <?php echo $cardClass; ?>">
                        <div class="anno-top">
                            <div class="anno-subject"><?php echo htmlspecialchars($anno['fyp_subject']); ?></div>
                            <div class="anno-badges">
                                <span class="author-badge <?php echo $anno['is_mine'] ? 'is-me' : ''; ?>">
                                    <i class="fa fa-user-tag"></i> 
                                    <?php echo $anno['is_mine'] ? 'You' : htmlspecialchars($anno['author_name']); ?>
                                </span>
                                <span class="anno-status st-<?php echo $status; ?>"><?php echo $status; ?></span>
                            </div>
                        </div>
                        <div class="anno-meta">
                            <span><i class="fa fa-calendar"></i> Posted: <?php echo date('d M Y', strtotime($anno['fyp_datecreated'])); ?></span>
                            <span><i class="fa fa-bullseye"></i> To: <?php echo htmlspecialchars($anno['fyp_receiver']); ?></span>
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

                            <form method="POST" style="display:inline;" onsubmit="return confirm('ADMIN WARNING: Are you sure you want to permanently delete this announcement?');">
                                <input type="hidden" name="announce_id" value="<?php echo $anno['fyp_annouceid']; ?>">
                                <button type="submit" name="delete_announcement" class="btn-action btn-delete">
                                    <i class="fa fa-trash"></i> Delete
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align:center; padding:40px; color:#999;">
                    <i class="fa fa-inbox" style="font-size:40px; margin-bottom:10px;"></i><br>
                    No announcements found matching your criteria.
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
            document.getElementById('edit_description').value = description.replace(/<br\s*\/?>/mg, "\n");
            editModal.classList.add('show');
        }
        function closeEditModal() { editModal.classList.remove('show'); }
        window.onclick = function(e) { if (e.target.classList.contains('modal-overlay')) closeEditModal(); }
    </script>
</body>
</html>