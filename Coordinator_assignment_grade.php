<?php
// ====================================================
// Coordinator_assignment_grade.php - UI Adapted from Supervisor
// ====================================================
include("connect.php");

// 1. 验证用户登录
$auth_user_id = $_GET['auth_user_id'] ?? null;
$current_page = 'grade_assignment'; 

if (!$auth_user_id) { header("location: login.php"); exit; }

// 2. 获取 Coordinator 信息 (包含 Staff ID 获取逻辑)
$user_name = "Coordinator"; 
$user_avatar = "image/user.png"; 
$sv_id = ""; // Staff ID

if (isset($conn)) {
    // A. 获取用户名
    $sql_user = "SELECT fyp_username FROM `USER` WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_user)) {
        $stmt->bind_param("i", $auth_user_id); $stmt->execute();
        $res = $stmt->get_result(); if ($row = $res->fetch_assoc()) $user_name = $row['fyp_username'];
        $stmt->close();
    }

    // B. 尝试从 SUPERVISOR 表获取 Staff ID (兼职情况)
    $sql_sv = "SELECT * FROM supervisor WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_sv)) {
        $stmt->bind_param("i", $auth_user_id); $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $row = $res->fetch_assoc();
            if (!empty($row['fyp_staffid'])) $sv_id = $row['fyp_staffid'];
            elseif (!empty($row['fyp_supervisorid'])) $sv_id = $row['fyp_supervisorid'];
            
            if (!empty($row['fyp_name'])) $user_name = $row['fyp_name'];
            if (!empty($row['fyp_profileimg'])) $user_avatar = $row['fyp_profileimg'];
        }
        $stmt->close();
    }

    // C. 如果为空，尝试从 COORDINATOR 表获取
    if (empty($sv_id)) {
        $sql_coor = "SELECT * FROM coordinator WHERE fyp_userid = ?";
        if ($stmt = $conn->prepare($sql_coor)) {
            $stmt->bind_param("i", $auth_user_id); $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows > 0) {
                $row = $res->fetch_assoc();
                if (!empty($row['fyp_staffid'])) $sv_id = $row['fyp_staffid'];
                elseif (!empty($row['fyp_coordinatorid'])) $sv_id = $row['fyp_coordinatorid'];
                
                if (!empty($row['fyp_name'])) $user_name = $row['fyp_name'];
                if (!empty($row['fyp_profileimg'])) $user_avatar = $row['fyp_profileimg'];
            }
            $stmt->close();
        }
    }
}

// ====================================================
// 3. 处理打分/退回提交 (POST)
// ====================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $date_now = date('Y-m-d H:i:s');
    $assign_id = $_POST['assignment_id'];
    $stud_id = $_POST['student_id'];
    $marks = $_POST['marks'];
    $feedback = $_POST['feedback'];
    
    // 判断操作类型
    $new_status = isset($_POST['return_revision']) ? 'Need Revision' : 'Graded';
    $msg = isset($_POST['return_revision']) ? "Assignment returned for revision!" : "Grade saved successfully!";

    $check_sql = "SELECT fyp_submissionid FROM assignment_submission WHERE fyp_assignmentid = ? AND fyp_studid = ?";
    if ($stmt = $conn->prepare($check_sql)) {
        $stmt->bind_param("is", $assign_id, $stud_id);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $sql_upd = "UPDATE assignment_submission 
                        SET fyp_marks = ?, fyp_feedback = ?, fyp_submission_status = ?, fyp_graded_date = ? 
                        WHERE fyp_assignmentid = ? AND fyp_studid = ?";
            $stmt_upd = $conn->prepare($sql_upd);
            $stmt_upd->bind_param("isssis", $marks, $feedback, $new_status, $date_now, $assign_id, $stud_id);
            $stmt_upd->execute();
        } else {
            $sql_ins = "INSERT INTO assignment_submission (fyp_assignmentid, fyp_studid, fyp_marks, fyp_feedback, fyp_submission_status, fyp_graded_date) 
                        VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_ins = $conn->prepare($sql_ins);
            $stmt_ins->bind_param("isissi", $assign_id, $stud_id, $marks, $feedback, $new_status, $date_now);
            $stmt_ins->execute();
        }
        echo "<script>alert('$msg'); window.location.href='Coordinator_assignment_grade.php?auth_user_id=" . urlencode($auth_user_id) . "&view_id=" . $assign_id . "';</script>";
    }
}

// ====================================================
// 4. 获取数据 (视图逻辑)
// ====================================================
$view_assignment_id = $_GET['view_id'] ?? null;
$assignments_list = [];
$students_to_grade = [];
$current_assignment = null;

$sort_by = $_GET['sort_by'] ?? 'DESC'; 
$filter_type = $_GET['filter_type'] ?? 'All';

if (!empty($sv_id)) {
    if (!$view_assignment_id) {
        // --- VIEW 1: Assignment List ---
        $sql_list = "SELECT a.*,
                            (SELECT COUNT(*) FROM assignment_submission sub WHERE sub.fyp_assignmentid = a.fyp_assignmentid AND sub.fyp_submission_status IN ('Turned In', 'Late Turned In', 'Resubmitted')) as submitted_count,
                            (SELECT COUNT(*) FROM assignment_submission sub WHERE sub.fyp_assignmentid = a.fyp_assignmentid AND sub.fyp_submission_status = 'Graded') as graded_count,
                            (SELECT COUNT(*) FROM assignment_submission sub WHERE sub.fyp_assignmentid = a.fyp_assignmentid AND sub.fyp_submission_status = 'Need Revision') as revision_count
                     FROM assignment a 
                     WHERE a.fyp_staffid = ?"; 

        $types = "s";
        $params = [$sv_id];

        if ($filter_type != 'All') {
            $sql_list .= " AND a.fyp_assignment_type = ?";
            $types .= "s";
            $params[] = $filter_type;
        }
        $sql_list .= " ORDER BY a.fyp_datecreated $sort_by";

        if ($stmt = $conn->prepare($sql_list)) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                // 计算显示目标名称
                $target_display_name = 'All Students'; 
                $target_id = $row['fyp_target_id'];
                $target_type = $row['fyp_assignment_type'];

                if ($target_id != 'ALL') {
                    if ($target_type == 'Group') {
                        $g_res = $conn->query("SELECT group_name FROM student_group WHERE group_id = '$target_id'");
                        if ($g_res && $g = $g_res->fetch_assoc()) $target_display_name = "Group: " . $g['group_name'];
                    } else {
                        $s_res = $conn->query("SELECT fyp_studname FROM student WHERE fyp_studid = '$target_id'");
                        if ($s_res && $s = $s_res->fetch_assoc()) $target_display_name = "Student: " . $s['fyp_studname'];
                    }
                } else {
                    $target_display_name = ($target_type == 'Group') ? 'All Groups' : 'All Individual Students';
                }
                
                $row['target_display_name'] = $target_display_name;
                $row['stats'] = [
                    'submitted' => $row['submitted_count'], 
                    'graded' => $row['graded_count'], 
                    'revision' => $row['revision_count']
                ];
                $assignments_list[] = $row;
            }
            $stmt->close();
        }

    } else {
        // --- VIEW 2: Grading Details (Student List) ---
        
        $sql_detail = "SELECT * FROM assignment WHERE fyp_assignmentid = ? AND fyp_staffid = ?";
        if ($stmt = $conn->prepare($sql_detail)) {
            $stmt->bind_param("is", $view_assignment_id, $sv_id);
            $stmt->execute();
            $res_d = $stmt->get_result();
            $current_assignment = $res_d->fetch_assoc();
            $stmt->close();
        }

        if ($current_assignment) {
            $target_type = $current_assignment['fyp_assignment_type'];
            $target_id = $current_assignment['fyp_target_id']; 

            $base_fields = "s.fyp_studid, s.fyp_studname, s.fyp_group, 
                            g.fyp_marks, g.fyp_feedback, g.fyp_submission_status, g.fyp_submission_date, g.fyp_submitted_file";
            
            $join_part = "LEFT JOIN assignment_submission g ON (s.fyp_studid = g.fyp_studid AND g.fyp_assignmentid = '$view_assignment_id')";
            $sql_studs = "";
            
            if ($target_id == 'ALL') {
                // 查询该 Coordinator 指导的所有学生
                $sql_studs = "SELECT $base_fields 
                              FROM fyp_registration r 
                              JOIN student s ON r.fyp_studid = s.fyp_studid 
                              $join_part 
                              WHERE r.fyp_staffid = '$sv_id'";
                if ($target_type == 'Individual') {
                    $sql_studs .= " AND s.fyp_group = 'Individual'";
                } elseif ($target_type == 'Group') {
                    $sql_studs .= " AND s.fyp_group = 'Group'";
                }
            } else {
                // 特定目标
                if ($target_type == 'Individual') {
                    $sql_studs = "SELECT $base_fields FROM student s $join_part WHERE s.fyp_studid = '$target_id'";
                } else {
                    $sql_studs = "SELECT $base_fields FROM student s $join_part WHERE s.fyp_studid IN (
                                      SELECT leader_id FROM student_group WHERE group_id = '$target_id'
                                      UNION
                                      SELECT invitee_id FROM group_request WHERE group_id = '$target_id' AND request_status = 'Accepted'
                                  )";
                }
            }
            
            if (!empty($sql_studs)) {
                $res_s = $conn->query($sql_studs);
                if($res_s) {
                    while ($row = $res_s->fetch_assoc()) {
                        
                        // 获取小组名
                        $row['display_group_name'] = '';
                        $g_query = "SELECT group_name, group_id FROM student_group WHERE leader_id = '{$row['fyp_studid']}' UNION SELECT sg.group_name, sg.group_id FROM group_request gr JOIN student_group sg ON gr.group_id = sg.group_id WHERE gr.invitee_id = '{$row['fyp_studid']}' AND gr.request_status = 'Accepted' LIMIT 1";
                        $g_result = $conn->query($g_query);
                        if ($g_result && $g_data = $g_result->fetch_assoc()) {
                             $row['display_group_name'] = $g_data['group_name'];
                             $row['group_id'] = $g_data['group_id'];
                        }

                        // --- 【核心功能】继承组长作业逻辑 ---
                        $inherited = false;
                        if ($target_type == 'Group' && empty($row['fyp_submitted_file']) && !empty($row['group_id'])) {
                            // 查找该组组长的提交
                            $leader_sql = "SELECT sub.fyp_submitted_file, sub.fyp_submission_date, sub.fyp_submission_status 
                                           FROM assignment_submission sub 
                                           JOIN student_group sg ON sg.leader_id = sub.fyp_studid
                                           WHERE sg.group_id = '{$row['group_id']}' AND sub.fyp_assignmentid = '$view_assignment_id'";
                            $l_res = $conn->query($leader_sql);
                            if ($l_res && $l_row = $l_res->fetch_assoc()) {
                                if (!empty($l_row['fyp_submitted_file'])) {
                                    $row['fyp_submitted_file'] = $l_row['fyp_submitted_file'];
                                    $row['fyp_submission_date'] = $l_row['fyp_submission_date'];
                                    // 仅当学生自己没有状态时才继承状态
                                    if (empty($row['fyp_submission_status']) || $row['fyp_submission_status'] == 'Not Turned In') {
                                        $row['fyp_submission_status'] = $l_row['fyp_submission_status'];
                                    }
                                    $inherited = true;
                                }
                            }
                        }

                        // 状态处理
                        $raw_status = $row['fyp_submission_status'];
                        if (empty($raw_status)) $raw_status = 'Not Turned In';
                        
                        $display_status = $raw_status;
                        if (($raw_status == 'Turned In' || $raw_status == 'Resubmitted') && !empty($row['fyp_submission_date'])) {
                            if ($row['fyp_submission_date'] > $current_assignment['fyp_deadline']) {
                                $display_status = 'Late Turned In';
                            }
                        }
                        
                        $row['final_status'] = $display_status;
                        $row['is_inherited'] = $inherited; 

                        $students_to_grade[] = $row;
                    }
                }
            }
        }
    }
}

// 5. 菜单定义
$menu_items = [
    'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home', 'link' => 'Coordinator_mainpage.php?page=dashboard'],
    'profile'   => ['name' => 'My Profile', 'icon' => 'fa-user', 'link' => 'Coordinator_profile.php'], 
    
     'management' => [
        'name' => 'User Management',
        'icon' => 'fa-users-cog',
        'sub_items' => [
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
            'grade_mod' => ['name' => 'Moderator Grading', 'icon' => 'fa-gavel', 'link' => 'Moderator_assignment_grade_coordinator.php'], 
        ]
    ],
    'announcements' => [
        'name' => 'Announcements', 
        'icon' => 'fa-bullhorn', 
        'sub_items' => [
            'post_announcement' => ['name' => 'Post New', 'icon' => 'fa-pen', 'link' => 'Coordinator_announcement.php'], 
            'view_announcements' => ['name' => 'View History', 'icon' => 'fa-history', 'link' => 'Coordinator_announcement_view.php'],
        ]
    ],
    'schedule' => ['name' => 'My Schedule', 'icon' => 'fa-calendar-alt', 'link' => 'Coordinator_meeting.php'], 
    'allocation' => ['name' => 'Moderator & Schedule Management', 'icon' => 'fa-database', 'link' => 'Coordinator_allocation.php'],
    'reports' => ['name' => 'System Reports', 'icon' => 'fa-chart-bar', 'link' => 'Coordinator_history.php'],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Assignments - Coordinator</title>
    <link rel="icon" type="image/png" href="<?php echo $user_avatar; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap');
        
        :root { --primary-color: #0056b3; --primary-hover: #004494; --secondary-color: #f4f4f9; --text-color: #333; --border-color: #e0e0e0; --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); --gradient-start: #eef2f7; --gradient-end: #ffffff; --sidebar-width: 260px; }
        body { font-family: 'Poppins', sans-serif; margin: 0; background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end)); color: var(--text-color); min-height: 100vh; display: flex; flex-direction: column; }
        
        /* Layout & Sidebar */
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

        /* Page Specific UI (Adapted from Supervisor) */
        .card { background: #fff; padding: 25px; border-radius: 12px; box-shadow: var(--card-shadow); margin-bottom: 20px; }
        .card-header { font-size: 18px; font-weight: 600; color: #333; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        
        .filter-bar { display: flex; gap: 10px; align-items: center; background: #f8f9fa; padding: 10px 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #eee; }
        .filter-select { padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px; cursor: pointer; }
        .btn-apply { padding: 6px 15px; background: var(--primary-color); color: white; border: none; border-radius: 4px; font-size: 13px; cursor: pointer; }

        .ass-card { border: 1px solid #eee; border-radius: 8px; padding: 15px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; transition: all 0.2s; background: #fff; }
        .ass-card:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.05); border-color: var(--primary-color); }
        .ass-title { font-weight: 600; font-size: 16px; color: #333; margin-bottom: 5px; }
        .ass-meta { font-size: 13px; color: #777; display: flex; gap: 15px; }
        
        .ass-stats { display: flex; gap: 10px; }
        .stat-badge { font-size: 11px; padding: 4px 8px; border-radius: 4px; background: #f1f3f5; color: #555; font-weight: 500; }
        .stat-submitted { color: #155724; background: #d4edda; }
        .stat-graded { color: #004085; background: #cce5ff; }

        /* Grading Box */
        .grading-box { border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; margin-bottom: 20px; background: #fafafa; }
        .status-pill { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        
        .st-NotTurnedIn { background: #e2e3e5; color: #6c757d; }
        .st-TurnedIn, .st-Resubmitted { background: #d4edda; color: #155724; }
        .st-LateTurnedIn { background: #f8d7da; color: #721c24; }
        .st-Graded { background: #cce5ff; color: #004085; }
        .st-NeedRevision { background: #fff3cd; color: #856404; }

        .gf-input { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        
        .action-buttons { display: flex; gap: 10px; margin-top: 15px; }
        .btn-action { padding: 8px 15px; background: var(--primary-color); color: white; border: none; border-radius: 4px; cursor: pointer; transition: background 0.2s; }
        .btn-revision { background: #ffc107; color: #333; }
        .btn-view { padding: 6px 15px; background: var(--primary-color); color: white; text-decoration: none; border-radius: 4px; font-size: 12px; }
        
        .file-link { display: inline-block; margin-top: 5px; font-size: 13px; color: #0056b3; text-decoration: none; padding: 4px 8px; background: #e3effd; border-radius: 4px; }
        .inherited-badge { font-size: 10px; color: #666; background: #eee; padding: 2px 5px; border-radius: 4px; margin-left: 5px; vertical-align: middle; }
        
        @media (max-width: 900px) { .layout-container { flex-direction: column; } .sidebar { width: 100%; min-height: auto; } }
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
                        $isActive = ($key == 'assessment');
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
            <?php if (!$view_assignment_id): ?>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                    <h2 style="margin:0; font-size:24px; color:var(--text-color);">Grade Assignments</h2>
                    <a href="Coordinator_view_result.php?auth_user_id=<?php echo $auth_user_id; ?>" class="btn-action" style="background:#28a745; text-decoration:none;">
                        <i class="fa fa-poll"></i> View Final Results
                    </a>
                </div>

                <div class="card">
                    <form method="GET" class="filter-bar">
                        <input type="hidden" name="auth_user_id" value="<?php echo htmlspecialchars($auth_user_id); ?>">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <span style="font-size:13px; font-weight:600;">Sort:</span>
                            <select name="sort_by" class="filter-select">
                                <option value="DESC" <?php echo $sort_by=='DESC'?'selected':''; ?>>Newest First</option>
                                <option value="ASC" <?php echo $sort_by=='ASC'?'selected':''; ?>>Oldest First</option>
                            </select>
                            <span style="font-size:13px; font-weight:600;">Type:</span>
                            <select name="filter_type" class="filter-select">
                                <option value="All">All Types</option>
                                <option value="Individual" <?php echo $filter_type=='Individual'?'selected':''; ?>>Individual</option>
                                <option value="Group" <?php echo $filter_type=='Group'?'selected':''; ?>>Group</option>
                            </select>
                            <button type="submit" class="btn-apply">Apply</button>
                        </div>
                    </form>

                    <?php if (count($assignments_list) > 0): ?>
                        <div class="ass-list">
                            <?php foreach ($assignments_list as $ass): ?>
                                <div class="ass-card">
                                    <div class="ass-info">
                                        <div class="ass-title"><?php echo htmlspecialchars($ass['fyp_title']); ?></div>
                                        <div class="ass-meta">
                                            <span><?php echo $ass['fyp_assignment_type']; ?></span>
                                            <span>Deadline: <?php echo date('M d, Y', strtotime($ass['fyp_deadline'])); ?></span>
                                            <span>Target: <?php echo $ass['target_display_name']; ?></span>
                                        </div>
                                    </div>
                                    
                                    <div style="display:flex; align-items:center; gap:20px;">
                                        <div class="ass-stats">
                                            <div class="stat-badge stat-submitted" title="Submitted">
                                                <i class="fa fa-upload"></i> <?php echo $ass['stats']['submitted']; ?>
                                            </div>
                                            <div class="stat-badge stat-graded" title="Graded">
                                                <i class="fa fa-check"></i> <?php echo $ass['stats']['graded']; ?>
                                            </div>
                                        </div>
                                        <a href="?auth_user_id=<?php echo $auth_user_id; ?>&view_id=<?php echo $ass['fyp_assignmentid']; ?>" class="btn-view">Grade Students</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align:center; color:#999; padding:20px;">No assignments found.</div>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <a href="?auth_user_id=<?php echo $auth_user_id; ?>" style="display:inline-block; margin-bottom:15px; color:#666; text-decoration:none;"><i class="fa fa-arrow-left"></i> Back to Assignments</a>
                
                <div class="card">
                    <div class="card-header">
                        <div>
                            Grading: <?php echo htmlspecialchars($current_assignment['fyp_title']); ?>
                            <div style="font-size:12px; font-weight:400; color:#666; margin-top:2px;">Deadline: <?php echo $current_assignment['fyp_deadline']; ?></div>
                        </div>
                    </div>
                    
                    <?php if (count($students_to_grade) > 0): ?>
                        <?php foreach ($students_to_grade as $stud): 
                            $status = $stud['final_status'];
                            $cssStatus = str_replace(' ', '', $status);
                        ?>
                            <div class="grading-box">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                                    <div>
                                        <div style="font-weight:600; font-size:16px;"><?php echo htmlspecialchars($stud['fyp_studname']); ?></div>
                                        <div style="font-size:13px; color:#888;"><?php echo htmlspecialchars($stud['fyp_studid']); ?></div>
                                        <?php if(!empty($stud['display_group_name'])): ?>
                                            <div style="font-size:12px; color:#0056b3; font-weight:600; margin-top:2px;">
                                                <i class="fa fa-users"></i> <?php echo htmlspecialchars($stud['display_group_name']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <span class="status-pill st-<?php echo $cssStatus; ?>"><?php echo $status; ?></span>
                                </div>

                                <div style="margin-bottom:15px; padding:10px; background:#fff; border:1px solid #eee; border-radius:6px;">
                                    <?php if(!empty($stud['fyp_submitted_file'])): ?>
                                        <div style="font-size:12px; color:#666;">Submission File:</div>
                                        <a href="<?php echo $stud['fyp_submitted_file']; ?>" target="_blank" class="file-link">
                                            <i class="fa fa-download"></i> View File
                                        </a>
                                        <?php if (!empty($stud['is_inherited'])): ?>
                                            <span class="inherited-badge" title="Submitted by group leader">(Group Leader Submission)</span>
                                        <?php endif; ?>
                                        <div style="font-size:11px; color:#999; margin-top:5px;">Submitted on: <?php echo $stud['fyp_submission_date']; ?></div>
                                    <?php else: ?>
                                        <span style="font-size:13px; color:#999; font-style:italic;">No file submitted.</span>
                                    <?php endif; ?>
                                </div>
                                
                                <form method="POST">
                                    <input type="hidden" name="assignment_id" value="<?php echo $view_assignment_id; ?>">
                                    <input type="hidden" name="student_id" value="<?php echo $stud['fyp_studid']; ?>">
                                    
                                    <div style="display:flex; gap:15px; margin-bottom:10px;">
                                        <div style="flex:0 0 100px;">
                                            <label style="font-size:12px; font-weight:600;">Marks</label>
                                            <input type="number" name="marks" class="gf-input" value="<?php echo $stud['fyp_marks']; ?>" min="0" max="100" placeholder="0-100">
                                        </div>
                                        <div style="flex:1;">
                                            <label style="font-size:12px; font-weight:600;">Feedback</label>
                                            <input type="text" name="feedback" class="gf-input" value="<?php echo htmlspecialchars($stud['fyp_feedback']); ?>" placeholder="Enter comments...">
                                        </div>
                                    </div>
                                    
                                    <div class="action-buttons">
                                        <button type="submit" name="return_grade" class="btn-action">
                                            <i class="fa fa-save"></i> Save Grade
                                        </button>
                                        <button type="submit" name="return_revision" class="btn-action btn-revision">
                                            <i class="fa fa-undo"></i> Request Revision
                                        </button>
                                    </div>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align:center; color:#999; padding:20px;">No students found for this assignment.</div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>