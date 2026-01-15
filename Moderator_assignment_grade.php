<?php
// ====================================================
// Moderator_assignment_grade.php - UI 升级版 (三层视图)
// ====================================================
include("connect.php");

// 1. 验证登录
$auth_user_id = $_GET['auth_user_id'] ?? null;
$current_page = 'grade_mod'; // 对应 Sidebar 的高亮 ID

if (!$auth_user_id) { header("location: login.php"); exit; }

// 2. 获取 Moderator (Supervisor) 信息
$user_name = "Moderator"; 
$user_avatar = "image/user.png"; 
$my_staff_id = ""; 

if (isset($conn)) {
    // 获取当前用户基本信息
    $stmt = $conn->prepare("SELECT fyp_username FROM `USER` WHERE fyp_userid = ?");
    $stmt->bind_param("i", $auth_user_id); $stmt->execute();
    $res = $stmt->get_result(); if ($row = $res->fetch_assoc()) $user_name = $row['fyp_username'];
    $stmt->close();
    
    // 获取 Staff ID (用于在 Schedule 表中匹配 Moderator)
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
// 3. 处理 Moderator 打分 (POST)
// ====================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_mod_grade'])) {
    $date_now = date('Y-m-d H:i:s');
    $assign_id = $_POST['assignment_id'];
    $stud_id = $_POST['student_id'];
    $mod_marks = $_POST['mod_marks'];
    $mod_feedback = $_POST['mod_feedback'];
    
    // 跳转参数
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
            // 如果学生未提交，Moderator 先行打分，状态保持 'Not Turned In'
            $sql_ins = "INSERT INTO assignment_submission (fyp_assignmentid, fyp_studid, fyp_mod_marks, fyp_mod_feedback, fyp_mod_graded_date, fyp_submission_status) 
                        VALUES (?, ?, ?, ?, ?, 'Not Turned In')";
            $stmt_ins = $conn->prepare($sql_ins);
            $stmt_ins->bind_param("isiss", $assign_id, $stud_id, $mod_marks, $mod_feedback, $date_now);
            $stmt_ins->execute();
        }
        echo "<script>alert('Moderator grade saved!'); window.location.href='Moderator_assignment_grade.php?auth_user_id=" . urlencode($auth_user_id) . "&view_target=" . urlencode($redirect_target) . "&target_type=" . urlencode($redirect_type) . "&grade_assignment=" . urlencode($assign_id) . "';</script>";
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

// 数据获取逻辑
if ($view_mode == 'list_targets') {
    // --- VIEW 1: Groups & Individuals (基于 schedule_presentation 表) ---
    
    // 1. 获取分配给我 Moderator 的 Groups
    $sql_groups = "SELECT DISTINCT g.group_id, g.group_name, 
                          (SELECT COUNT(*) FROM student_group sg2 WHERE sg2.group_id = g.group_id) as member_count
                   FROM schedule_presentation ps
                   JOIN student_group g ON ps.target_id = g.group_id
                   WHERE ps.moderator_id = '$my_staff_id' AND ps.target_type = 'Group'";
    
    $res_g = $conn->query($sql_groups);
    while($row = $res_g->fetch_assoc()) {
        $gid = $row['group_id'];
        // 统计有多少作业学生交了但我还没打分 (mod_marks IS NULL)
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

    // 2. 获取分配给我 Moderator 的 Individuals
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
    // --- VIEW 2: Assignments List ---
    
    $target_sql_filter = "";
    if ($selected_target_type == 'Group') {
        $target_sql_filter = "(a.fyp_target_id = 'ALL' OR a.fyp_target_id = '$selected_target_id') AND a.fyp_assignment_type = 'Group'";
        $g_res = $conn->query("SELECT group_name FROM student_group WHERE group_id = '$selected_target_id'");
        if($g = $g_res->fetch_assoc()) $target_name_display = $g['group_name'];
        // 获取组长ID用于查看提交状态
        $l_res = $conn->query("SELECT leader_id FROM student_group WHERE group_id = '$selected_target_id'");
        $leader_id = ($l_res && $l_row = $l_res->fetch_assoc()) ? $l_row['leader_id'] : null;
    } else {
        $target_sql_filter = "(a.fyp_target_id = 'ALL' OR a.fyp_target_id = '$selected_target_id') AND a.fyp_assignment_type = 'Individual'";
        $s_res = $conn->query("SELECT fyp_studname FROM student WHERE fyp_studid = '$selected_target_id'");
        if($s = $s_res->fetch_assoc()) $target_name_display = $s['fyp_studname'];
        $leader_id = $selected_target_id;
    }

    // 关键：不限制 fyp_staffid，因为作业可能是 Coordinator 发的
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
        // Moderator 状态覆盖: 如果我已经打分了，显示 "Mod Graded"
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
    // --- VIEW 3: Grading Form (Moderator Specific) ---
    
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
        // Inheritance Logic
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

// 菜单定义 (复用 Supervisor 菜单)
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
    <link rel="icon" type="image/png" href="<?php echo $user_avatar; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap');
        :root { --primary-color: #0056b3; --primary-hover: #004494; --secondary-color: #f4f4f9; --text-color: #333; --border-color: #e0e0e0; --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); --gradient-start: #eef2f7; --gradient-end: #ffffff; --sidebar-width: 260px; }
        body { font-family: 'Poppins', sans-serif; margin: 0; background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end)); color: var(--text-color); min-height: 100vh; display: flex; flex-direction: column; }
        
        /* Topbar & Sidebar (Standardized) */
        .topbar { display: flex; justify-content: space-between; align-items: center; padding: 15px 40px; background-color: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.05); position: sticky; top: 0; z-index:100; }
        .logo { font-size: 22px; font-weight: 600; color: var(--primary-color); display: flex; align-items: center; gap: 10px; }
        .topbar-right { display: flex; align-items: center; gap: 20px; }
        .user-name-display { font-weight: 600; font-size: 14px; display: block; }
        .user-role-badge { font-size: 11px; background-color: #e3effd; color: var(--primary-color); padding: 2px 8px; border-radius: 12px; font-weight: 500; }
        .user-avatar-circle { width: 40px; height: 40px; border-radius: 50%; overflow: hidden; border: 2px solid #e3effd; margin-left: 10px; }
        .user-avatar-circle img { width: 100%; height: 100%; object-fit: cover; }
        .logout-btn { color: #d93025; text-decoration: none; font-size: 14px; font-weight: 500; padding: 8px 15px; border-radius: 6px; }
        
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
        .card { background: #fff; padding: 25px; border-radius: 12px; box-shadow: var(--card-shadow); margin-bottom: 20px; }
        .card-header { font-size: 18px; font-weight: 600; color: #333; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }

        /* Grid for Groups/Students */
        .grid-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; }
        .target-card { border: 1px solid #eee; border-radius: 10px; padding: 20px; text-align: center; transition: 0.2s; cursor: pointer; text-decoration: none; color: inherit; display: block; background: #fff; }
        .target-card:hover { transform: translateY(-5px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); border-color: var(--primary-color); }
        .target-icon { font-size: 40px; color: var(--primary-color); margin-bottom: 15px; background: #e3effd; width: 80px; height: 80px; line-height: 80px; border-radius: 50%; margin: 0 auto 15px auto; }
        .target-name { font-weight: 600; font-size: 16px; margin-bottom: 5px; }
        .target-sub { font-size: 12px; color: #777; }
        .badge-pending { background: #d93025; color: white; padding: 2px 8px; border-radius: 10px; font-size: 11px; margin-top: 10px; display: inline-block; }

        /* Assignment Rows */
        .ass-row { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding: 15px 0; }
        .ass-row:last-child { border-bottom: none; }
        .status-pill { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .st-TurnedIn, .st-Resubmitted, .st-ModGraded { background: #d4edda; color: #155724; }
        .st-NotTurnedIn { background: #e2e3e5; color: #6c757d; }
        .st-Graded { background: #cce5ff; color: #004085; } /* Supervisor Graded */
        .st-LateTurnedIn { background: #f8d7da; color: #721c24; }

        /* Grading Form */
        .grading-box { border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; margin-bottom: 20px; background: #fafafa; }
        .file-link { display: inline-block; margin-top: 5px; font-size: 13px; color: #0056b3; text-decoration: none; }
        .btn-action { padding: 8px 15px; background: var(--primary-color); color: white; border: none; border-radius: 4px; cursor: pointer; transition: background 0.2s; }
        .btn-action:hover { background: var(--primary-hover); }
        .back-btn { display: inline-flex; align-items: center; gap: 5px; color: #666; text-decoration: none; margin-bottom: 15px; font-size: 14px; }
        .back-btn:hover { color: var(--primary-color); }
        
        .gf-input { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .sv-score-box { background: #e9ecef; padding: 8px 12px; border-radius: 6px; font-size: 13px; color: #495057; margin-bottom: 15px; border-left: 3px solid #6c757d; }

        @media (max-width: 900px) { .layout-container { flex-direction: column; } .sidebar { width: 100%; min-height: auto; } }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="logo"><img src="image/ladybug.png" alt="Logo" style="width: 32px; margin-right: 10px;"> FYP Moderator</div>
        <div class="topbar-right">
            <div class="user-profile-summary">
                <span class="user-name-display"><?php echo htmlspecialchars($user_name); ?></span>
                <span class="user-role-badge">Moderator</span>
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
            
            <?php if ($view_mode == 'list_targets'): ?>
                <div class="card">
                    <div class="card-header">
                        <div>Moderation Groups</div>
                        <span style="font-size:12px; font-weight:normal; color:#888;">Assigned via Schedule</span>
                    </div>
                    <div class="grid-container">
                        <?php foreach ($my_groups as $g): ?>
                            <a href="?auth_user_id=<?php echo $auth_user_id; ?>&view_target=<?php echo $g['group_id']; ?>&target_type=Group" class="target-card">
                                <div class="target-icon"><i class="fa fa-users"></i></div>
                                <div class="target-name"><?php echo htmlspecialchars($g['group_name']); ?></div>
                                <div class="target-sub"><?php echo $g['member_count']; ?> Members</div>
                                <?php if($g['pending_grading'] > 0): ?>
                                    <span class="badge-pending"><?php echo $g['pending_grading']; ?> submissions pending</span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                        <?php if(empty($my_groups)) echo "<div style='color:#999; grid-column:1/-1;'>No groups allocated for moderation.</div>"; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">Moderation Students</div>
                    <div class="grid-container">
                        <?php foreach ($my_individuals as $i): ?>
                            <a href="?auth_user_id=<?php echo $auth_user_id; ?>&view_target=<?php echo $i['fyp_studid']; ?>&target_type=Individual" class="target-card">
                                <div class="target-icon"><i class="fa fa-user"></i></div>
                                <div class="target-name"><?php echo htmlspecialchars($i['fyp_studname']); ?></div>
                                <div class="target-sub"><?php echo $i['fyp_studid']; ?></div>
                                <?php if($i['pending_grading'] > 0): ?>
                                    <span class="badge-pending"><?php echo $i['pending_grading']; ?> submissions pending</span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                        <?php if(empty($my_individuals)) echo "<div style='color:#999; grid-column:1/-1;'>No individual students allocated.</div>"; ?>
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
                        <?php foreach($target_assignments as $ass): 
                            $st = $ass['display_status'];
                            $css = $ass['css_class'];
                        ?>
                            <div class="ass-row">
                                <div>
                                    <div style="font-weight:600; font-size:15px;"><?php echo htmlspecialchars($ass['fyp_title']); ?></div>
                                    <div style="font-size:12px; color:#888;">Deadline: <?php echo $ass['fyp_deadline']; ?> | Weightage: <?php echo $ass['fyp_weightage']; ?>%</div>
                                    <?php if($ass['fyp_mod_marks'] > 0): ?>
                                        <div style="font-size:12px; color:var(--primary-color); margin-top:3px;"><strong>Mod Score: <?php echo $ass['fyp_mod_marks']; ?> / 100</strong></div>
                                    <?php endif; ?>
                                </div>
                                <div style="text-align:right;">
                                    <span class="status-pill st-<?php echo $css; ?>"><?php echo $st; ?></span>
                                    <br>
                                    <a href="?auth_user_id=<?php echo $auth_user_id; ?>&view_target=<?php echo $selected_target_id; ?>&target_type=<?php echo $selected_target_type; ?>&grade_assignment=<?php echo $ass['fyp_assignmentid']; ?>" 
                                       style="display:inline-block; margin-top:5px; font-size:12px; color:#0056b3;">
                                       <?php echo (!empty($ass['fyp_mod_marks'])) ? 'Update Grade' : 'Grade Now'; ?> <i class="fa fa-chevron-right"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="padding:20px; text-align:center; color:#999;">No assignments found for this target.</div>
                    <?php endif; ?>
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
                                <div>
                                    <div style="font-weight:600;"><?php echo htmlspecialchars($stud['fyp_studname']); ?></div>
                                    <div style="font-size:12px; color:#888;"><?php echo $stud['fyp_studid']; ?></div>
                                </div>
                                <div class="status-pill st-<?php echo str_replace(' ', '', $stud['final_status']); ?>"><?php echo $stud['final_status']; ?></div>
                            </div>

                            <div style="margin:15px 0; border-top:1px solid #eee; border-bottom:1px solid #eee; padding:10px 0;">
                                <?php if(!empty($stud['fyp_submitted_file'])): ?>
                                    <a href="<?php echo $stud['fyp_submitted_file']; ?>" target="_blank" class="file-link"><i class="fa fa-paperclip"></i> View Submitted File</a>
                                    <?php if(!empty($stud['is_inherited'])): ?><span style="font-size:11px; color:#666;"> (Group Leader Submission)</span><?php endif; ?>
                                    <div style="font-size:11px; color:#999; margin-top:2px;">Date: <?php echo $stud['fyp_submission_date']; ?></div>
                                <?php else: ?>
                                    <span style="font-size:13px; color:#999; font-style:italic;">No file submitted yet.</span>
                                <?php endif; ?>
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
                                    <div style="flex:0 0 100px;">
                                        <label style="font-size:12px; font-weight:600;">Mod. Marks</label>
                                        <input type="number" name="mod_marks" value="<?php echo $stud['fyp_mod_marks']; ?>" class="gf-input" min="0" max="100" placeholder="0-100">
                                    </div>
                                    <div style="flex:1;">
                                        <label style="font-size:12px; font-weight:600;">Mod. Feedback</label>
                                        <input type="text" name="mod_feedback" value="<?php echo htmlspecialchars($stud['fyp_mod_feedback']); ?>" class="gf-input" placeholder="Moderator comments...">
                                    </div>
                                </div>
                                
                                <button type="submit" name="save_mod_grade" class="btn-action"><i class="fa fa-save"></i> Save Grade</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </main>
    </div>
</body>
</html>