<?php
// ====================================================
// Moderator_assignment_grade.php - Mainpage Design + Pagination (Max 10 Groups)
// ====================================================
include("connect.php");

// 1. 验证登录
$auth_user_id = $_GET['auth_user_id'] ?? null;
$current_page = 'grade_mod';

if (!$auth_user_id) { header("location: login.php"); exit; }

// 2. 获取 Moderator (Supervisor) 信息
$user_name = "Moderator"; 
$user_avatar = "image/user.png"; 
$my_staff_id = ""; 

if (isset($conn)) {
    $stmt = $conn->prepare("SELECT fyp_username FROM `USER` WHERE fyp_userid = ?");
    $stmt->bind_param("i", $auth_user_id); $stmt->execute();
    $res = $stmt->get_result(); if ($row = $res->fetch_assoc()) $user_name = $row['fyp_username'];
    $stmt->close();
    
    $stmt = $conn->prepare("SELECT * FROM supervisor WHERE fyp_userid = ?");
    $stmt->bind_param("i", $auth_user_id); 
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($row = $res->fetch_assoc()) {
        $my_staff_id = isset($row['fyp_staffid']) ? $row['fyp_staffid'] : $row['fyp_supervisorid'];
        if (!empty($row['fyp_name'])) $user_name = $row['fyp_name'];
        if (!empty($row['fyp_profileimg'])) $user_avatar = $row['fyp_profileimg'];
    }
    $stmt->close();
}

if (empty($my_staff_id)) {
    die("<div style='padding:20px; color:red;'>Error: Supervisor Staff ID not found. Access Denied.</div>");
}

// ====================================================
// 3. 处理 Moderator 打分 (POST) - SweetAlert
// ====================================================
$swal_script = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_mod_grade'])) {
    $date_now = date('Y-m-d H:i:s');
    $assign_id = $_POST['assignment_id'];
    $stud_id = $_POST['student_id'];
    $mod_marks = $_POST['mod_marks'];
    $mod_feedback = $_POST['mod_feedback'];
    $redirect_target = $_POST['redirect_target'];
    $redirect_type = $_POST['redirect_type'];
    
    $check_sql = "SELECT fyp_submissionid FROM assignment_submission WHERE fyp_assignmentid = ? AND fyp_studid = ?";
    if ($stmt = $conn->prepare($check_sql)) {
        $stmt->bind_param("is", $assign_id, $stud_id);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $sql_upd = "UPDATE assignment_submission 
                        SET fyp_mod_marks = ?, fyp_mod_feedback = ?, fyp_mod_graded_date = ?
                        WHERE fyp_assignmentid = ? AND fyp_studid = ?";
            $stmt_upd = $conn->prepare($sql_upd);
            $stmt_upd->bind_param("issis", $mod_marks, $mod_feedback, $date_now, $assign_id, $stud_id);
            $stmt_upd->execute();
        } else {
            $sql_ins = "INSERT INTO assignment_submission (fyp_assignmentid, fyp_studid, fyp_mod_marks, fyp_mod_feedback, fyp_mod_graded_date, fyp_submission_status) 
                        VALUES (?, ?, ?, ?, ?, 'Not Turned In')";
            $stmt_ins = $conn->prepare($sql_ins);
            $stmt_ins->bind_param("isiss", $assign_id, $stud_id, $mod_marks, $mod_feedback, $date_now);
            $stmt_ins->execute();
        }
        
        $redirect_url = "Moderator_assignment_grade.php?auth_user_id=" . urlencode($auth_user_id) . "&view_target=" . urlencode($redirect_target) . "&target_type=" . urlencode($redirect_type) . "&grade_assignment=" . urlencode($assign_id);
        
        $swal_script = "
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: 'Saved!',
                    text: 'Moderator grade recorded successfully.',
                    icon: 'success',
                    confirmButtonColor: '#0056b3',
                    draggable: true
                }).then((result) => {
                    window.location.href = '$redirect_url';
                });
            });
        </script>";
    }
}

// ====================================================
// 4. 视图逻辑控制 & 数据获取
// ====================================================
$view_mode = 'list_targets'; 
$selected_target_id = $_GET['view_target'] ?? null;
$selected_target_type = $_GET['target_type'] ?? null;
$selected_assignment_id = $_GET['grade_assignment'] ?? null;

if ($selected_assignment_id && $selected_target_id) {
    $view_mode = 'grading_form';
} elseif ($selected_target_id) {
    $view_mode = 'list_assignments';
}

$my_groups = [];
$my_individuals = [];
$target_assignments = [];
$grading_students = [];

if ($view_mode == 'list_targets') {
    // Groups
    $sql_groups = "SELECT DISTINCT g.group_id, g.group_name, 
                          (SELECT COUNT(*) FROM student_group sg2 WHERE sg2.group_id = g.group_id) as member_count
                   FROM schedule_presentation ps
                   JOIN student_group g ON ps.target_id = g.group_id
                   WHERE ps.moderator_id = '$my_staff_id' AND ps.target_type = 'Group'";
    
    $res_g = $conn->query($sql_groups);
    while($row = $res_g->fetch_assoc()) {
        $gid = $row['group_id'];
        $sql_pending = "SELECT COUNT(*) as cnt FROM assignment a 
                        LEFT JOIN assignment_submission sub ON a.fyp_assignmentid = sub.fyp_assignmentid 
                        WHERE (a.fyp_target_id = 'ALL' OR a.fyp_target_id = '$gid') 
                        AND a.fyp_assignment_type = 'Group'
                        AND (sub.fyp_submission_status = 'Turned In' OR sub.fyp_submission_status = 'Graded')
                        AND sub.fyp_mod_marks IS NULL"; 
        
        $p_res = $conn->query($sql_pending);
        $row['pending_grading'] = $p_res->fetch_assoc()['cnt'];
        $my_groups[] = $row;
    }

    // Individuals
    $sql_indiv = "SELECT s.fyp_studid, s.fyp_studname
                  FROM schedule_presentation ps
                  JOIN student s ON ps.target_id = s.fyp_studid
                  WHERE ps.moderator_id = '$my_staff_id' AND ps.target_type = 'Individual'";
                  
    $res_i = $conn->query($sql_indiv);
    while($row = $res_i->fetch_assoc()) {
         $sid = $row['fyp_studid'];
         $sql_pending = "SELECT COUNT(*) as cnt FROM assignment a 
                        LEFT JOIN assignment_submission sub ON a.fyp_assignmentid = sub.fyp_assignmentid AND sub.fyp_studid = '$sid'
                        WHERE (a.fyp_target_id = 'ALL' OR a.fyp_target_id = '$sid') 
                        AND a.fyp_assignment_type = 'Individual'
                        AND (sub.fyp_submission_status = 'Turned In' OR sub.fyp_submission_status = 'Graded')
                        AND sub.fyp_mod_marks IS NULL";
        
        $p_res = $conn->query($sql_pending);
        $row['pending_grading'] = $p_res->fetch_assoc()['cnt'];
        $my_individuals[] = $row;
    }

} elseif ($view_mode == 'list_assignments') {
    $target_sql_filter = "";
    if ($selected_target_type == 'Group') {
        $target_sql_filter = "(a.fyp_target_id = 'ALL' OR a.fyp_target_id = '$selected_target_id') AND a.fyp_assignment_type = 'Group'";
        $g_res = $conn->query("SELECT group_name FROM student_group WHERE group_id = '$selected_target_id'");
        if($g = $g_res->fetch_assoc()) $target_name_display = $g['group_name'];
        $l_res = $conn->query("SELECT leader_id FROM student_group WHERE group_id = '$selected_target_id'");
        $leader_id = ($l_res && $l_row = $l_res->fetch_assoc()) ? $l_row['leader_id'] : null;
    } else {
        $target_sql_filter = "(a.fyp_target_id = 'ALL' OR a.fyp_target_id = '$selected_target_id') AND a.fyp_assignment_type = 'Individual'";
        $s_res = $conn->query("SELECT fyp_studname FROM student WHERE fyp_studid = '$selected_target_id'");
        if($s = $s_res->fetch_assoc()) $target_name_display = $s['fyp_studname'];
        $leader_id = $selected_target_id;
    }

    $sql_ass = "SELECT a.*, 
                       sub.fyp_submission_status, sub.fyp_submission_date, sub.fyp_marks, sub.fyp_submitted_file, sub.fyp_mod_marks
                FROM assignment a
                LEFT JOIN assignment_submission sub ON a.fyp_assignmentid = sub.fyp_assignmentid AND sub.fyp_studid = '$leader_id'
                WHERE $target_sql_filter
                ORDER BY a.fyp_deadline DESC";
                
    $res_ass = $conn->query($sql_ass);
    while($row = $res_ass->fetch_assoc()) {
        $row['final_status'] = $row['fyp_submission_status'] ? $row['fyp_submission_status'] : 'Not Turned In';
        if (($row['final_status'] == 'Turned In') && $row['fyp_submission_date'] > $row['fyp_deadline']) {
            $row['final_status'] = 'Late Turned In';
        }
        if (!empty($row['fyp_mod_marks'])) {
             $row['display_status'] = 'Mod Graded';
             $row['css_class'] = 'Graded';
        } else {
             $row['display_status'] = $row['final_status'];
             $row['css_class'] = str_replace(' ', '', $row['final_status']);
        }
        $target_assignments[] = $row;
    }

} elseif ($view_mode == 'grading_form') {
    $ass_res = $conn->query("SELECT * FROM assignment WHERE fyp_assignmentid = '$selected_assignment_id'");
    $current_assignment = $ass_res->fetch_assoc();

    if ($selected_target_type == 'Group') {
         $g_res = $conn->query("SELECT group_name FROM student_group WHERE group_id = '$selected_target_id'");
         if($g = $g_res->fetch_assoc()) $target_name_display = $g['group_name'];

        $sql_members = "SELECT s.fyp_studid, s.fyp_studname, 
                               sub.fyp_marks, sub.fyp_feedback, 
                               sub.fyp_mod_marks, sub.fyp_mod_feedback,
                               sub.fyp_submitted_file, sub.fyp_submission_status, sub.fyp_submission_date
                        FROM student s
                        LEFT JOIN assignment_submission sub ON sub.fyp_assignmentid = '$selected_assignment_id' AND sub.fyp_studid = s.fyp_studid
                        WHERE s.fyp_studid IN (
                            SELECT leader_id FROM student_group WHERE group_id = '$selected_target_id'
                            UNION
                            SELECT invitee_id FROM group_request WHERE group_id = '$selected_target_id' AND request_status = 'Accepted'
                        )";
    } else {
         $s_res = $conn->query("SELECT fyp_studname FROM student WHERE fyp_studid = '$selected_target_id'");
         if($s = $s_res->fetch_assoc()) $target_name_display = $s['fyp_studname'];

        $sql_members = "SELECT s.fyp_studid, s.fyp_studname, 
                               sub.fyp_marks, sub.fyp_feedback, 
                               sub.fyp_mod_marks, sub.fyp_mod_feedback,
                               sub.fyp_submitted_file, sub.fyp_submission_status, sub.fyp_submission_date
                        FROM student s
                        LEFT JOIN assignment_submission sub ON sub.fyp_assignmentid = '$selected_assignment_id' AND sub.fyp_studid = s.fyp_studid
                        WHERE s.fyp_studid = '$selected_target_id'";
    }

    $res_m = $conn->query($sql_members);
    while($row = $res_m->fetch_assoc()) {
        if ($selected_target_type == 'Group' && empty($row['fyp_submitted_file'])) {
            $leader_sub_sql = "SELECT sub.fyp_submitted_file, sub.fyp_submission_date, sub.fyp_submission_status 
                               FROM assignment_submission sub 
                               JOIN student_group sg ON sg.leader_id = sub.fyp_studid
                               WHERE sg.group_id = '$selected_target_id' AND sub.fyp_assignmentid = '$selected_assignment_id'";
            $ls_res = $conn->query($leader_sub_sql);
            if ($ls_row = $ls_res->fetch_assoc()) {
                if (!empty($ls_row['fyp_submitted_file'])) {
                    $row['fyp_submitted_file'] = $ls_row['fyp_submitted_file'];
                    $row['fyp_submission_date'] = $ls_row['fyp_submission_date'];
                    $row['fyp_submission_status'] = $ls_row['fyp_submission_status'];
                    $row['is_inherited'] = true;
                }
            }
        }
        $row['final_status'] = $row['fyp_submission_status'] ? $row['fyp_submission_status'] : 'Not Turned In';
        $grading_students[] = $row;
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
            'propose_assignment' => ['name' => 'Propose Assignment', 'icon' => 'fa-tasks','link' => 'supervisor_assignment_purpose.php'],
             'all_project' => ['name' => 'All Project', 'icon' => 'fa-tasks', 'link' => 'supervisor_project_list.php']
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
    <title>Moderator Grading</title>
    <link rel="icon" type="image/png" href="image/ladybug.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap');
        :root { --primary-color: #0056b3; --primary-hover: #004494; --secondary-color: #f8f9fa; --text-color: #333; --border-color: #e0e0e0; --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); --bg-color: #f4f6f9; --sidebar-bg: #004085; --sidebar-hover: #003366; --sidebar-text: #e0e0e0; }
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
        .card { background: #fff; padding: 25px; border-radius: 12px; box-shadow: var(--card-shadow); margin-bottom: 20px; }
        .card-header { font-size: 18px; font-weight: 600; color: #333; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }

        /* Row UI */
        .list-row { display: flex; align-items: center; padding: 15px; border-bottom: 1px solid #f0f0f0; transition: background 0.2s; text-decoration: none; color: inherit; }
        .list-row:last-child { border-bottom: none; }
        .list-row:hover { background-color: #f9fbfd; transform: translateX(5px); }
        .row-icon { width: 45px; height: 45px; background: #e3effd; color: var(--primary-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 18px; margin-right: 15px; }
        .row-info { flex: 1; }
        .row-title { font-weight: 600; font-size: 15px; color: #333; margin-bottom: 2px; }
        .row-subtitle { font-size: 12px; color: #777; }
        .row-meta { text-align: right; }
        .badge-pending { background: #d93025; color: white; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 500; display: inline-block; }

        /* Pagination */
        .pagination { margin-top: 20px; display: flex; justify-content: center; gap: 5px; }
        .page-link { padding: 8px 14px; border: 1px solid #ddd; background: #fff; color: #333; text-decoration: none; border-radius: 6px; transition: 0.2s; font-size: 14px; }
        .page-link:hover { background: #f0f0f0; }
        .page-link.active { background: var(--primary-color); color: #fff; border-color: var(--primary-color); }

        /* Grading Form Elements */
        .ass-row { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding: 15px 0; }
        .status-pill { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .st-TurnedIn, .st-Resubmitted, .st-ModGraded { background: #d4edda; color: #155724; }
        .st-NotTurnedIn { background: #e2e3e5; color: #6c757d; }
        .st-Graded { background: #cce5ff; color: #004085; }
        .st-LateTurnedIn { background: #f8d7da; color: #721c24; }
        .grading-box { border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; margin-bottom: 20px; background: #fafafa; }
        .file-link { display: inline-block; margin-top: 5px; font-size: 13px; color: #0056b3; text-decoration: none; }
        .btn-action { padding: 8px 15px; background: var(--primary-color); color: white; border: none; border-radius: 4px; cursor: pointer; transition: background 0.2s; }
        .back-btn { display: inline-flex; align-items: center; gap: 5px; color: #666; text-decoration: none; margin-bottom: 15px; font-size: 14px; }
        .gf-input { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .sv-score-box { background: #e9ecef; padding: 8px 12px; border-radius: 6px; font-size: 13px; color: #495057; margin-bottom: 15px; border-left: 3px solid #6c757d; }

        @media (max-width: 900px) { .main-content-wrapper { margin-left: 0; width: 100%; } }
    </style>
</head>
<body>
    <?php echo $swal_script; ?>

    <nav class="main-menu">
        <ul>
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
        <div class="page-header">
            <div class="welcome-text"><h1>Moderator Grading</h1><p>Review and validate grades for assigned groups/students.</p></div>
            <div class="logo-section"><img src="image/ladybug.png" alt="Logo" class="logo-img"><span class="system-title">FYP Moderator</span></div>
            <div style="display:flex; align-items:center; gap:10px;">
                <span style="font-size:13px; color:#666; background:#f0f0f0; padding:5px 10px; border-radius:20px;">Moderator</span>
                <?php if(!empty($user_avatar) && $user_avatar != 'image/user.png'): ?>
                    <img src="<?php echo htmlspecialchars($user_avatar); ?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
                <?php else: ?>
                    <div style="width:40px;height:40px;border-radius:50%;background:#0056b3;color:white;display:flex;align-items:center;justify-content:center;font-weight:bold;"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <main class="main-content">
            <?php if ($view_mode == 'list_targets'): ?>
                <div class="card">
                    <div class="card-header">
                        <div>Moderation Groups</div>
                        <span style="font-size:12px; font-weight:normal; color:#888;">Max 10 per page</span>
                    </div>
                    <div class="list-container">
                        <?php 
                            // Pagination Logic: Max 10 Groups
                            $limit = 10;
                            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                            $total_groups = count($my_groups);
                            $total_pages = ceil($total_groups / $limit);
                            $offset = ($page - 1) * $limit;
                            $display_groups = array_slice($my_groups, $offset, $limit);

                            foreach ($display_groups as $g): 
                        ?>
                            <a href="?auth_user_id=<?php echo $auth_user_id; ?>&view_target=<?php echo $g['group_id']; ?>&target_type=Group" class="list-row">
                                <div class="row-icon"><i class="fa fa-users"></i></div>
                                <div class="row-info">
                                    <div class="row-title"><?php echo htmlspecialchars($g['group_name']); ?></div>
                                    <div class="row-subtitle"><?php echo $g['member_count']; ?> Members</div>
                                </div>
                                <div class="row-meta">
                                    <?php if($g['pending_grading'] > 0): ?><span class="badge-pending"><?php echo $g['pending_grading']; ?> Pending</span><?php else: ?><span style="color:#28a745; font-size:12px;"><i class="fa fa-check"></i> Reviewed</span><?php endif; ?>
                                    <div style="font-size:18px; color:#ccc; margin-top:5px;"><i class="fa fa-chevron-right"></i></div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                        <?php if(empty($my_groups)) echo "<div style='color:#999; padding:15px;'>No groups allocated for moderation.</div>"; ?>
                    </div>

                    <!-- Pagination Controls -->
                    <?php if($total_pages > 1): ?>
                        <div class="pagination">
                            <?php for($p = 1; $p <= $total_pages; $p++): ?>
                                <a href="?auth_user_id=<?php echo $auth_user_id; ?>&page=<?php echo $p; ?>" class="page-link <?php echo ($p == $page) ? 'active' : ''; ?>"><?php echo $p; ?></a>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <div class="card-header">Moderation Students</div>
                    <div class="list-container">
                        <?php foreach ($my_individuals as $i): ?>
                            <a href="?auth_user_id=<?php echo $auth_user_id; ?>&view_target=<?php echo $i['fyp_studid']; ?>&target_type=Individual" class="list-row">
                                <div class="row-icon"><i class="fa fa-user"></i></div>
                                <div class="row-info"><div class="row-title"><?php echo htmlspecialchars($i['fyp_studname']); ?></div><div class="row-subtitle">ID: <?php echo $i['fyp_studid']; ?></div></div>
                                <div class="row-meta">
                                    <?php if($i['pending_grading'] > 0): ?><span class="badge-pending"><?php echo $i['pending_grading']; ?> Pending</span><?php else: ?><span style="color:#28a745; font-size:12px;"><i class="fa fa-check"></i> Reviewed</span><?php endif; ?>
                                    <div style="font-size:18px; color:#ccc; margin-top:5px;"><i class="fa fa-chevron-right"></i></div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                        <?php if(empty($my_individuals)) echo "<div style='color:#999; padding:15px;'>No individual students allocated.</div>"; ?>
                    </div>
                </div>

            <?php elseif ($view_mode == 'list_assignments'): ?>
                <a href="?auth_user_id=<?php echo $auth_user_id; ?>" class="back-btn"><i class="fa fa-arrow-left"></i> Back to Target List</a>
                <div class="card">
                    <div class="card-header">
                        Assignments for: <?php echo htmlspecialchars($target_name_display ?? $selected_target_id); ?>
                        <span style="font-size:12px; background:#eee; padding:2px 8px; border-radius:4px; font-weight:400;"><?php echo $selected_target_type; ?></span>
                    </div>
                    <?php if(count($target_assignments) > 0): ?>
                        <?php foreach($target_assignments as $ass): $st = $ass['display_status']; $css = $ass['css_class']; ?>
                            <div class="ass-row">
                                <div>
                                    <div style="font-weight:600; font-size:15px;"><?php echo htmlspecialchars($ass['fyp_title']); ?></div>
                                    <div style="font-size:12px; color:#888;">Deadline: <?php echo $ass['fyp_deadline']; ?> | Weightage: <?php echo $ass['fyp_weightage']; ?>%</div>
                                    <?php if($ass['fyp_mod_marks'] > 0): ?><div style="font-size:12px; color:var(--primary-color); margin-top:3px;"><strong>Mod Score: <?php echo $ass['fyp_mod_marks']; ?> / 100</strong></div><?php endif; ?>
                                </div>
                                <div style="text-align:right;">
                                    <span class="status-pill st-<?php echo $css; ?>"><?php echo $st; ?></span><br>
                                    <a href="?auth_user_id=<?php echo $auth_user_id; ?>&view_target=<?php echo $selected_target_id; ?>&target_type=<?php echo $selected_target_type; ?>&grade_assignment=<?php echo $ass['fyp_assignmentid']; ?>" style="display:inline-block; margin-top:5px; font-size:12px; color:#0056b3;"><?php echo (!empty($ass['fyp_mod_marks'])) ? 'Update Grade' : 'Grade Now'; ?> <i class="fa fa-chevron-right"></i></a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?><div style="padding:20px; text-align:center; color:#999;">No assignments found for this target.</div><?php endif; ?>
                </div>

            <?php elseif ($view_mode == 'grading_form'): ?>
                <a href="?auth_user_id=<?php echo $auth_user_id; ?>&view_target=<?php echo $selected_target_id; ?>&target_type=<?php echo $selected_target_type; ?>" class="back-btn"><i class="fa fa-arrow-left"></i> Back to Assignments</a>
                <div class="card">
                    <div class="card-header">
                        Grading: <?php echo htmlspecialchars($current_assignment['fyp_title']); ?>
                        <div style="font-size:12px; font-weight:normal;">Target: <?php echo htmlspecialchars($target_name_display); ?></div>
                    </div>
                    <?php foreach($grading_students as $stud): ?>
                        <div class="grading-box">
                            <div style="display:flex; justify-content:space-between;">
                                <div><div style="font-weight:600;"><?php echo htmlspecialchars($stud['fyp_studname']); ?></div><div style="font-size:12px; color:#888;"><?php echo $stud['fyp_studid']; ?></div></div>
                                <div class="status-pill st-<?php echo str_replace(' ', '', $stud['final_status']); ?>"><?php echo $stud['final_status']; ?></div>
                            </div>
                            <div style="margin:15px 0; border-top:1px solid #eee; border-bottom:1px solid #eee; padding:10px 0;">
                                <?php if(!empty($stud['fyp_submitted_file'])): ?>
                                    <a href="<?php echo $stud['fyp_submitted_file']; ?>" target="_blank" class="file-link"><i class="fa fa-paperclip"></i> View Submitted File</a>
                                    <?php if(!empty($stud['is_inherited'])): ?><span style="font-size:11px; color:#666;"> (Group Leader Submission)</span><?php endif; ?>
                                    <div style="font-size:11px; color:#999; margin-top:2px;">Date: <?php echo $stud['fyp_submission_date']; ?></div>
                                <?php else: ?><span style="font-size:13px; color:#999; font-style:italic;">No file submitted yet.</span><?php endif; ?>
                            </div>
                            <div class="sv-score-box">
                                <div style="font-weight:600; font-size:11px; text-transform:uppercase; margin-bottom:5px;">Supervisor Grading</div>
                                <div>Score: <b><?php echo isset($stud['fyp_marks']) ? $stud['fyp_marks'] : '-'; ?></b> / 100</div>
                                <div style="font-size:12px; color:#666;">Feedback: <?php echo isset($stud['fyp_feedback']) ? htmlspecialchars($stud['fyp_feedback']) : '-'; ?></div>
                            </div>
                            <form method="POST">
                                <input type="hidden" name="assignment_id" value="<?php echo $selected_assignment_id; ?>">
                                <input type="hidden" name="student_id" value="<?php echo $stud['fyp_studid']; ?>">
                                <input type="hidden" name="redirect_target" value="<?php echo $selected_target_id; ?>">
                                <input type="hidden" name="redirect_type" value="<?php echo $selected_target_type; ?>">
                                <div style="display:flex; gap:15px; margin-bottom:10px;">
                                    <div style="flex:0 0 100px;"><label style="font-size:12px; font-weight:600;">Mod. Marks</label><input type="number" name="mod_marks" value="<?php echo $stud['fyp_mod_marks']; ?>" class="gf-input" min="0" max="100" placeholder="0-100"></div>
                                    <div style="flex:1;"><label style="font-size:12px; font-weight:600;">Mod. Feedback</label><input type="text" name="mod_feedback" value="<?php echo htmlspecialchars($stud['fyp_mod_feedback']); ?>" class="gf-input" placeholder="Moderator comments..."></div>
                                </div>
                                <button type="submit" name="save_mod_grade" class="btn-action"><i class="fa fa-save"></i> Save Grade</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
    <script>
        function toggleSubmenu(element) {
            const menuItem = element.parentElement;
            const isOpen = menuItem.classList.contains('open');
            document.querySelectorAll('.menu-item').forEach(item => { if (item !== menuItem) item.classList.remove('open'); });
            if (isOpen) menuItem.classList.remove('open'); else menuItem.classList.add('open');
        }
    </script>
</body>   
</html>