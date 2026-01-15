<?php
// ====================================================
// Supervisor_student_list.php - 学生列表 (含 Academic Year 筛选)
// ====================================================
include("connect.php");
session_start();

// 1. 验证登录
$auth_user_id = $_GET['auth_user_id'] ?? $_SESSION['user_id'] ?? null;
$current_page = 'student_list'; 

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
// 3. 获取 Academic Year 列表 (用于下拉菜单)
// ----------------------------------------------------
$academic_years_list = [];
$ay_sql = "SELECT * FROM academic_year ORDER BY fyp_acdyear DESC, fyp_intake ASC";
$ay_res = $conn->query($ay_sql);
if ($ay_res) {
    while ($row = $ay_res->fetch_assoc()) {
        $academic_years_list[] = $row;
    }
}

// ----------------------------------------------------
// 4. 处理 Hide/Active 操作 (POST 方法)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action_type'])) {
    
    $target_stud_id = $_POST['target_stud_id'];
    $target_proj_id = $_POST['target_proj_id'];
    $action_type = $_POST['action_type']; // 'Hide' 或 'Active'
    
    $new_status = ($action_type == 'Hide') ? 'Hidden' : 'Active';

    // 更新状态
    $sql_update = "UPDATE fyp_registration 
                   SET fyp_archive_status = ? 
                   WHERE fyp_studid = ? AND fyp_projectid = ?";
                   
    if ($stmt = $conn->prepare($sql_update)) {
        $stmt->bind_param("ssi", $new_status, $target_stud_id, $target_proj_id);
        
        if ($stmt->execute()) {
            // 成功后刷新页面，保持所有筛选状态 (View, Sort, Year)
            $f_view = $_POST['current_filter'] ?? 'Active';
            $f_sort = $_POST['current_sort'] ?? 'ASC';
            $f_year = $_POST['current_year'] ?? 'All'; // [新增] 保持学年筛选
            
            echo "<script>window.location.href='Supervisor_student_list.php?auth_user_id=$auth_user_id&filter_view=$f_view&sort_order=$f_sort&filter_year=$f_year';</script>";
        } else {
            echo "<script>alert('Error updating status: " . $stmt->error . "');</script>";
        }
        $stmt->close();
    }
}

// ----------------------------------------------------
// 5. 获取列表 (查询逻辑)
// ----------------------------------------------------
$my_students = [];

// 获取筛选参数
$filter_view = $_GET['filter_view'] ?? 'Active'; 
$sort_order = $_GET['sort_order'] ?? 'ASC'; 
$filter_year = $_GET['filter_year'] ?? 'All'; // [新增] 学年参数

if (!empty($my_staff_id)) {
    $sql = "SELECT s.fyp_studid, s.fyp_studname, s.fyp_email, s.fyp_contactno, s.fyp_profileimg,
                   p.fyp_projectid, p.fyp_projecttitle, p.fyp_projecttype, p.fyp_academicid,
                   r.fyp_datecreated as reg_date, r.fyp_archive_status,
                   ay.fyp_acdyear, ay.fyp_intake
            FROM fyp_registration r
            JOIN student s ON r.fyp_studid = s.fyp_studid
            JOIN project p ON r.fyp_projectid = p.fyp_projectid
            LEFT JOIN academic_year ay ON p.fyp_academicid = ay.fyp_academicid
            WHERE p.fyp_staffid = ?";

    // [新增] 学年筛选逻辑
    if ($filter_year != 'All') {
        // 绑定参数比较麻烦，这里直接拼 SQL (确保 filter_year 是安全的 int 或字符串)
        // 既然是下拉菜单选的 ID，我们可以简单转义一下
        $safe_year_id = $conn->real_escape_string($filter_year);
        $sql .= " AND p.fyp_academicid = '$safe_year_id'";
    }

    // 状态筛选
    if ($filter_view == 'Active') {
        $sql .= " AND (r.fyp_archive_status = 'Active' OR r.fyp_archive_status IS NULL)";
    } elseif ($filter_view == 'Hidden') {
        $sql .= " AND r.fyp_archive_status = 'Hidden'";
    } 

    // 排序
    $direction = ($sort_order == 'DESC') ? 'DESC' : 'ASC';
    $sql .= " ORDER BY s.fyp_studname $direction";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $my_staff_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $my_students[] = $row;
        }
        $stmt->close();
    }
}

// 6. 菜单定义
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
    <title>My Student List</title>
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
        
        /* Filter Bar */
        .filter-bar { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; gap: 15px; align-items: flex-end; border: 1px solid #eee; flex-wrap: wrap; }
        .filter-group { display: flex; flex-direction: column; gap: 5px; flex: 1; min-width: 150px; }
        .filter-group label { font-size: 12px; font-weight: 600; color: #666; }
        .filter-select { padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; width: 100%; box-sizing: border-box; }
        .btn-apply { background: var(--primary-color); color: white; border: none; padding: 9px 20px; border-radius: 6px; cursor: pointer; font-weight: 500; height: 38px; }

        /* Table */
        .std-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .std-table th { background: #f8f9fa; text-align: left; padding: 15px; color: #555; font-weight: 600; border-bottom: 2px solid #eee; }
        .std-table td { padding: 15px; border-bottom: 1px solid #eee; color: #333; vertical-align: middle; }
        .std-table tr:hover { background-color: #f9fbfd; }
        
        .row-hidden td { opacity: 0.6; background-color: #fcfcfc; }
        .row-hidden .std-name { text-decoration: line-through; color: #999; }

        .type-badge { padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; display: inline-block; }
        .type-Group { background: #e3effd; color: #0056b3; }
        .type-Individual { background: #e8f5e9; color: #2e7d32; }
        .status-badge { font-size: 11px; padding: 3px 8px; border-radius: 4px; font-weight: 600; }
        .st-Active { background: #d4edda; color: #155724; }
        .st-Hidden { background: #e2e3e5; color: #383d41; }

        .proj-title { font-weight: 600; color: #0056b3; display: block; margin-bottom: 4px; }
        .std-id { font-size: 12px; color: #888; font-family: monospace; background: #f5f5f5; padding: 2px 6px; border-radius: 4px; }
        
        /* New Action Buttons (FORM based) */
        .action-form { display: inline-block; }
        .btn-action { padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; cursor: pointer; transition: all 0.2s; border: 1px solid transparent; }
        .btn-hide { background: #fff3cd; color: #856404; border-color: #ffeeba; }
        .btn-hide:hover { background: #ffeeba; transform: translateY(-1px); }
        .btn-active { background: #d4edda; color: #155724; border-color: #c3e6cb; }
        .btn-active:hover { background: #c3e6cb; transform: translateY(-1px); }

        .empty-box { text-align:center; padding:50px; color:#999; }
        @media (max-width: 900px) { .layout-container { flex-direction: column; } .sidebar { width: 100%; min-height: auto; } .filter-bar { flex-direction: column; align-items: stretch; } }
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
                        $isActive = ($key == 'students');
                        $linkUrl = isset($item['link']) ? $item['link'] : "#";
                        if (strpos($linkUrl, '.php') !== false) {
                             $separator = (strpos($linkUrl, '?') !== false) ? '&' : '?';
                             $linkUrl .= $separator . "auth_user_id=" . urlencode($auth_user_id);
                        } elseif (strpos($linkUrl, '?') === 0) {
                             $linkUrl .= "&auth_user_id=" . urlencode($auth_user_id);
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
                                    $subIsActive = ($sub_key == $current_page);
                                    $subLinkUrl = isset($sub_item['link']) ? $sub_item['link'] : "#";
                                    if (strpos($subLinkUrl, '.php') !== false) {
                                        $separator = (strpos($subLinkUrl, '?') !== false) ? '&' : '?';
                                        $subLinkUrl .= $separator . "auth_user_id=" . urlencode($auth_user_id);
                                    }
                                ?>
                                    <li><a href="<?php echo $subLinkUrl; ?>" class="menu-link <?php echo $subIsActive ? 'active' : ''; ?>">
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
                <div style="border-bottom: 2px solid #eee; padding-bottom: 15px; margin-bottom: 20px; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <h2 style="margin:0; color:#333;"><i class="fa fa-user-graduate"></i> My Student List</h2>
                        <p style="margin:5px 0 0; color:#666; font-size:14px;">Manage your active and archived students.</p>
                    </div>
                    <div style="background:#e3effd; color:#0056b3; padding:8px 15px; border-radius:20px; font-weight:600;">
                        Count: <?php echo count($my_students); ?>
                    </div>
                </div>

                <form method="GET" class="filter-bar">
                    <input type="hidden" name="auth_user_id" value="<?php echo htmlspecialchars($auth_user_id); ?>">
                    
                    <div class="filter-group">
                        <label>Academic Year</label>
                        <select name="filter_year" class="filter-select">
                            <option value="All">All Years</option>
                            <?php foreach ($academic_years_list as $ay): ?>
                                <option value="<?php echo $ay['fyp_academicid']; ?>" <?php if($filter_year == $ay['fyp_academicid']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($ay['fyp_acdyear'] . " (" . $ay['fyp_intake'] . ")"); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>View Mode</label>
                        <select name="filter_view" class="filter-select">
                            <option value="Active" <?php if($filter_view == 'Active') echo 'selected'; ?>>Default (Active Only)</option>
                            <option value="Hidden" <?php if($filter_view == 'Hidden') echo 'selected'; ?>>Archived (Hidden Only)</option>
                            <option value="All" <?php if($filter_view == 'All') echo 'selected'; ?>>Show All</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Sort By Name</label>
                        <select name="sort_order" class="filter-select">
                            <option value="ASC" <?php if($sort_order == 'ASC') echo 'selected'; ?>>A - Z</option>
                            <option value="DESC" <?php if($sort_order == 'DESC') echo 'selected'; ?>>Z - A</option>
                        </select>
                    </div>

                    <button type="submit" class="btn-apply"><i class="fa fa-filter"></i> Apply</button>
                </form>

                <?php if (count($my_students) > 0): ?>
                    <table class="std-table">
                        <thead>
                            <tr>
                                <th>Student Info</th>
                                <th>Contact Info</th>
                                <th>Academic Year</th>
                                <th>Project Info</th>
                                <th>Status</th>
                                <th>Manage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($my_students as $std): 
                                $isHidden = ($std['fyp_archive_status'] == 'Hidden');
                                $rowClass = $isHidden ? 'row-hidden' : '';
                            ?>
                                <tr class="<?php echo $rowClass; ?>">
                                    <td>
                                        <strong class="std-name" style="font-size:15px;"><?php echo htmlspecialchars($std['fyp_studname']); ?></strong><br>
                                        <span class="std-id"><?php echo htmlspecialchars($std['fyp_studid']); ?></span>
                                    </td>
                                    <td>
                                        <div style="font-size:13px; color:#555;">
                                            <i class="fa fa-envelope" style="width:16px; color:#888;"></i> <?php echo htmlspecialchars($std['fyp_email']); ?>
                                        </div>
                                        <div style="font-size:13px; color:#555; margin-top:4px;">
                                            <i class="fa fa-phone" style="width:16px; color:#888;"></i> <?php echo htmlspecialchars($std['fyp_contactno']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($std['fyp_acdyear'])): ?>
                                            <div style="font-weight:600; color:#555;"><?php echo htmlspecialchars($std['fyp_acdyear']); ?></div>
                                            <div style="font-size:12px; color:#888;">(<?php echo htmlspecialchars($std['fyp_intake']); ?>)</div>
                                        <?php else: ?>
                                            <span style="color:#ccc;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="proj-title"><?php echo htmlspecialchars($std['fyp_projecttitle']); ?></span>
                                        <span class="type-badge type-<?php echo $std['fyp_projecttype']; ?>">
                                            <?php echo $std['fyp_projecttype']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge st-<?php echo $std['fyp_archive_status'] ?? 'Active'; ?>">
                                            <?php echo $std['fyp_archive_status'] ?? 'Active'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" class="action-form">
                                            <input type="hidden" name="target_stud_id" value="<?php echo $std['fyp_studid']; ?>">
                                            <input type="hidden" name="target_proj_id" value="<?php echo $std['fyp_projectid']; ?>">
                                            <input type="hidden" name="current_filter" value="<?php echo $filter_view; ?>">
                                            <input type="hidden" name="current_sort" value="<?php echo $sort_order; ?>">
                                            <input type="hidden" name="current_year" value="<?php echo $filter_year; ?>"> <?php if ($isHidden): ?>
                                                <input type="hidden" name="action_type" value="Active">
                                                <button type="submit" class="btn-action btn-active" title="Set to Active">
                                                    <i class="fa fa-check-circle"></i> Set Active
                                                </button>
                                            <?php else: ?>
                                                <input type="hidden" name="action_type" value="Hide">
                                                <button type="submit" class="btn-action btn-hide" title="Archive Student" onclick="return confirm('Archive this student? They will be hidden from the default list.')">
                                                    <i class="fa fa-archive"></i> Archive
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-box">
                        <i class="fa fa-search" style="font-size:48px; opacity:0.3; margin-bottom:15px;"></i>
                        <p>No students found matching your filters.</p>
                        <?php if ($filter_view != 'All' || $filter_year != 'All'): ?>
                            <a href="?auth_user_id=<?php echo $auth_user_id; ?>&filter_view=All&filter_year=All" style="color:#0056b3;">View All Students</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>