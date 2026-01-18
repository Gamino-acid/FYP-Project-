<?php
// ====================================================
// Supervisor_student_list.php - Student List (AJAX + UI Updated)
// ====================================================
include("connect.php");
session_start();

// 1. AJAX Handler (Process Hide/Active without Reload)
// [BACKEND PRESERVED]
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    $target_stud_id = $_POST['target_stud_id'];
    $target_proj_id = $_POST['target_proj_id'];
    $action_type = $_POST['action_type']; // 'Hide' or 'Active'
    
    $new_status = ($action_type == 'Hide') ? 'Hidden' : 'Active';

    // Update Status
    $sql_update = "UPDATE fyp_registration 
                   SET fyp_archive_status = ? 
                   WHERE fyp_studid = ? AND fyp_projectid = ?";
                   
    if ($stmt = $conn->prepare($sql_update)) {
        $stmt->bind_param("ssi", $new_status, $target_stud_id, $target_proj_id);
        
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'new_state' => $new_status, 'message' => 'Status updated successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'SQL Prepare error.']);
    }
    exit;
}

// 2. Auth Check
$auth_user_id = $_GET['auth_user_id'] ?? $_SESSION['user_id'] ?? null;
$current_page = 'student_list'; 

if (!$auth_user_id) { 
    echo "<script>alert('Please login first.'); window.location.href='login.php';</script>";
    exit; 
}

// 3. Get Supervisor Info
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

// 4. Get Academic Years
$academic_years_list = [];
$ay_res = $conn->query("SELECT * FROM academic_year ORDER BY fyp_acdyear DESC, fyp_intake ASC");
if ($ay_res) {
    while ($row = $ay_res->fetch_assoc()) {
        $academic_years_list[] = $row;
    }
}

// 5. Get Student List
$my_students = [];
$filter_view = $_GET['filter_view'] ?? 'Active'; 
$sort_order = $_GET['sort_order'] ?? 'ASC'; 
$filter_year = $_GET['filter_year'] ?? 'All'; 

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

    if ($filter_year != 'All') {
        $safe_year_id = $conn->real_escape_string($filter_year);
        $sql .= " AND p.fyp_academicid = '$safe_year_id'";
    }

    if ($filter_view == 'Active') {
        $sql .= " AND (r.fyp_archive_status = 'Active' OR r.fyp_archive_status IS NULL)";
    } elseif ($filter_view == 'Hidden') {
        $sql .= " AND r.fyp_archive_status = 'Hidden'";
    } 

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

// 6. Menu Definition
$menu_items = [
    'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home', 'link' => 'Supervisor_mainpage.php?page=dashboard'],
    'profile'   => ['name' => 'My Profile', 'icon' => 'fa-user', 'link' => 'supervisor_profile.php'],
    'students'  => [
        'name' => 'My Students', 
        'icon' => 'fa-users',
        'sub_items' => [
            'project_requests' => ['name' => 'Project Requests', 'icon' => 'fa-envelope-open-text', 'link' => 'Supervisor_projectreq.php'],
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
    <title>My Student List</title>
    <link rel="icon" type="image/png" href="image/ladybug.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap');
        
        :root {
            --primary-color: #0056b3;
            --primary-hover: #004494;
            --secondary-color: #f8f9fa;
            --text-color: #333;
            --sidebar-bg: #004085; 
            --sidebar-hover: #003366;
            --sidebar-text: #e0e0e0;
            --card-shadow: 0 4px 6px rgba(0,0,0,0.05);
            --bg-color: #f4f6f9;
        }

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
        .content-card { background: #fff; padding: 25px; border-radius: 12px; box-shadow: var(--card-shadow); }
        .section-header-box { border-bottom: 2px solid #eee; padding-bottom: 15px; margin-bottom: 25px; display:flex; justify-content:space-between; align-items:center; }

        /* Filters */
        .filter-bar { background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 20px; display: flex; gap: 20px; align-items: flex-end; border: 1px solid #eee; flex-wrap: wrap; }
        .filter-group { display: flex; flex-direction: column; gap: 8px; flex: 1; min-width: 180px; }
        .filter-group label { font-size: 13px; font-weight: 600; color: #666; }
        .filter-select { padding: 10px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; width: 100%; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        .btn-apply { background: var(--primary-color); color: white; border: none; padding: 10px 25px; border-radius: 6px; cursor: pointer; font-weight: 500; height: 42px; font-family: 'Poppins', sans-serif; }

        /* Table */
        .data-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; margin-top: 10px; }
        .data-table th { background: #f8f9fa; text-align: left; padding: 15px; color: #555; font-size: 13px; font-weight: 600; border-bottom: 2px solid #eee; }
        .data-table td { padding: 15px; border-bottom: 1px solid #f0f0f0; font-size: 14px; color: #333; vertical-align: middle; }
        .data-table tr:hover { background-color: #f9fbfd; }
        
        .row-hidden td { opacity: 0.6; background-color: #fcfcfc; }
        .row-hidden .std-name { text-decoration: line-through; color: #999; }

        .type-badge { padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; display: inline-block; }
        .type-Group { background: #e3effd; color: #0056b3; }
        .type-Individual { background: #e8f5e9; color: #2e7d32; }
        
        .status-badge { font-size: 11px; padding: 3px 8px; border-radius: 4px; font-weight: 600; }
        .st-Active { background: #d4edda; color: #155724; }
        .st-Hidden { background: #e2e3e5; color: #383d41; }

        .proj-title { font-weight: 500; color: #0056b3; display: block; margin-bottom: 4px; }
        .std-id { font-size: 12px; color: #777; font-family: monospace; background: #f5f5f5; padding: 2px 6px; border-radius: 4px; }
        
        .btn-action { padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; cursor: pointer; transition: all 0.2s; border: 1px solid transparent; }
        .btn-hide { background: #fff3cd; color: #856404; border-color: #ffeeba; }
        .btn-hide:hover { background: #ffeeba; transform: translateY(-1px); }
        .btn-active { background: #d4edda; color: #155724; border-color: #c3e6cb; }
        .btn-active:hover { background: #c3e6cb; transform: translateY(-1px); }
        .btn-action.loading { opacity: 0.6; pointer-events: none; }

        .empty-box { text-align:center; padding:50px; color:#999; }

        @media (max-width: 900px) { .main-content-wrapper { margin-left: 0; width: 100%; } .filter-bar { flex-direction: column; align-items: stretch; } }
    </style>
</head>
<body>
    <!-- Sidebar Navigation -->
    <nav class="main-menu">
        <ul>
            <?php foreach ($menu_items as $key => $item): ?>
                <?php 
                    $isActive = ($key == $current_page);
                    if($key == 'students') $isActive = true; // Highlight student parent menu
                    
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
                    <a href="<?php echo $hasSubmenu ? 'javascript:void(0)' : $linkUrl; ?>" class="<?php echo $key == 'students' ? 'active' : ''; ?>" <?php if ($hasSubmenu): ?>onclick="toggleSubmenu(this)"<?php endif; ?>>
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

    <!-- Main Content -->
    <div class="main-content-wrapper">
        <!-- Header -->
        <div class="page-header">
            <div class="welcome-text"><h1>My Student List</h1><p>Manage and track your active supervision students.</p></div>
            <div class="logo-section"><img src="image/ladybug.png" alt="Logo" class="logo-img"><span class="system-title">FYP Portal</span></div>
            <div style="display:flex; align-items:center; gap:10px;">
                <span style="font-size:13px; color:#666; background:#f0f0f0; padding:5px 10px; border-radius:20px;">Supervisor</span>
                <?php if(!empty($user_avatar) && $user_avatar != 'image/user.png'): ?>
                    <img src="<?php echo htmlspecialchars($user_avatar); ?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
                <?php else: ?>
                    <div style="width:40px;height:40px;border-radius:50%;background:#0056b3;color:white;display:flex;align-items:center;justify-content:center;font-weight:bold;"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="content-card">
            <div class="section-header-box">
                <div><h2 style="margin:0; color:#333; font-size:18px;"><i class="fa fa-user-graduate"></i> Student Records</h2></div>
                <div style="background:#e3effd; color:#0056b3; padding:8px 15px; border-radius:20px; font-weight:600; font-size:14px;">Total Students: <?php echo count($my_students); ?></div>
            </div>

            <!-- Filters -->
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

            <!-- List Table -->
            <?php if (count($my_students) > 0): ?>
                <table class="data-table">
                    <thead><tr><th>Student Info</th><th>Contact Info</th><th>Academic Year</th><th>Project Info</th><th>Status</th><th>Manage</th></tr></thead>
                    <tbody>
                        <?php foreach ($my_students as $std): 
                            $isHidden = ($std['fyp_archive_status'] == 'Hidden');
                            $rowClass = $isHidden ? 'row-hidden' : '';
                            $rowId = 'row-' . $std['fyp_studid'] . '-' . $std['fyp_projectid'];
                        ?>
                            <tr id="<?php echo $rowId; ?>" class="<?php echo $rowClass; ?>">
                                <td>
                                    <strong class="std-name" style="font-size:15px;"><?php echo htmlspecialchars($std['fyp_studname']); ?></strong><br>
                                    <span class="std-id"><?php echo htmlspecialchars($std['fyp_studid']); ?></span>
                                </td>
                                <td>
                                    <div style="font-size:13px; color:#555;"><i class="fa fa-envelope" style="width:16px; color:#888;"></i> <?php echo htmlspecialchars($std['fyp_email']); ?></div>
                                    <div style="font-size:13px; color:#555; margin-top:4px;"><i class="fa fa-phone" style="width:16px; color:#888;"></i> <?php echo htmlspecialchars($std['fyp_contactno']); ?></div>
                                </td>
                                <td>
                                    <?php if (!empty($std['fyp_acdyear'])): ?>
                                        <div style="font-weight:500; color:#555;"><?php echo htmlspecialchars($std['fyp_acdyear']); ?></div>
                                        <div style="font-size:12px; color:#888;">(<?php echo htmlspecialchars($std['fyp_intake']); ?>)</div>
                                    <?php else: ?>
                                        <span style="color:#ccc;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="proj-title"><?php echo htmlspecialchars($std['fyp_projecttitle']); ?></span>
                                    <span class="type-badge type-<?php echo $std['fyp_projecttype']; ?>"><?php echo $std['fyp_projecttype']; ?></span>
                                </td>
                                <td>
                                    <span id="status-<?php echo $rowId; ?>" class="status-badge st-<?php echo $std['fyp_archive_status'] ?? 'Active'; ?>"><?php echo $std['fyp_archive_status'] ?? 'Active'; ?></span>
                                </td>
                                <td>
                                    <div id="action-<?php echo $rowId; ?>">
                                        <?php if ($isHidden): ?>
                                            <button type="button" class="btn-action btn-active" onclick="toggleStatus('Active', '<?php echo $std['fyp_studid']; ?>', <?php echo $std['fyp_projectid']; ?>, '<?php echo $rowId; ?>')"><i class="fa fa-check-circle"></i> Set Active</button>
                                        <?php else: ?>
                                            <button type="button" class="btn-action btn-hide" onclick="toggleStatus('Hide', '<?php echo $std['fyp_studid']; ?>', <?php echo $std['fyp_projectid']; ?>, '<?php echo $rowId; ?>')"><i class="fa fa-archive"></i> Archive</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-box"><i class="fa fa-search" style="font-size:48px; opacity:0.3; margin-bottom:15px;"></i><p>No students found matching your filters.</p></div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleSubmenu(element) {
            const menuItem = element.parentElement;
            const isOpen = menuItem.classList.contains('open');
            document.querySelectorAll('.menu-item').forEach(item => { if (item !== menuItem) item.classList.remove('open'); });
            if (isOpen) menuItem.classList.remove('open'); else menuItem.classList.add('open');
        }

        function toggleStatus(action, studId, projId, rowId) {
            // Confirmation using SweetAlert2
            if (action === 'Hide') {
                Swal.fire({
                    title: "Archive Student?",
                    text: "They will be hidden from the default list.",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonColor: "#f0ad4e", // Yellowish/Orange
                    cancelButtonColor: "#d33",
                    confirmButtonText: "Yes, Archive",
                    draggable: true 
                }).then((result) => {
                    if (result.isConfirmed) {
                        performStatusUpdate(action, studId, projId, rowId);
                    }
                });
            } else {
                performStatusUpdate(action, studId, projId, rowId);
            }
        }

        function performStatusUpdate(action, studId, projId, rowId) {
            const btn = document.querySelector(`#action-${rowId} button`);
            if(btn) {
                btn.classList.add('loading');
                btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Processing...';
            }

            const formData = new FormData();
            formData.append('ajax_action', 'true');
            formData.append('action_type', action);
            formData.append('target_stud_id', studId);
            formData.append('target_proj_id', projId);

            fetch('Supervisor_student_list.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    const row = document.getElementById(rowId);
                    const statusBadge = document.getElementById('status-' + rowId);
                    const actionContainer = document.getElementById('action-' + rowId);

                    // Update Status Badge
                    statusBadge.className = 'status-badge st-' + data.new_state;
                    statusBadge.textContent = data.new_state;

                    // Update Row Styling
                    if (data.new_state === 'Hidden') row.classList.add('row-hidden');
                    else row.classList.remove('row-hidden');

                    // Update Button & Show SweetAlert2
                    if (data.new_state === 'Hidden') {
                        actionContainer.innerHTML = `<button type="button" class="btn-action btn-active" onclick="toggleStatus('Active', '${studId}', ${projId}, '${rowId}')"><i class="fa fa-check-circle"></i> Set Active</button>`;
                        
                        Swal.fire({
                          title: "Archived!",
                          text: "Student has been moved to archive.",
                          icon: "warning", // Yellowish icon
                          confirmButtonColor: "#f0ad4e",
                          draggable: true
                        });

                    } else {
                        actionContainer.innerHTML = `<button type="button" class="btn-action btn-hide" onclick="toggleStatus('Hide', '${studId}', ${projId}, '${rowId}')"><i class="fa fa-archive"></i> Archive</button>`;
                        
                        Swal.fire({
                          title: "Activated!",
                          text: "Student is now active.",
                          icon: "success",
                          draggable: true
                        });
                    }
                } else {
                    Swal.fire("Error", data.message, "error");
                    // Revert button state
                    if(btn) {
                        btn.classList.remove('loading');
                        btn.innerHTML = action === 'Hide' ? '<i class="fa fa-archive"></i> Archive' : '<i class="fa fa-check-circle"></i> Set Active';
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire("Error", "An unexpected error occurred.", "error");
                if(btn) {
                    btn.classList.remove('loading');
                    btn.innerHTML = action === 'Hide' ? '<i class="fa fa-archive"></i> Archive' : '<i class="fa fa-check-circle"></i> Set Active';
                }
            });
        }
    </script>
</body>
</html>