<?php
// ====================================================
// Supervisor_assignment_grade.php - 作业打分与反馈 (With File View)
// ====================================================
include("connect.php");

// 1. 验证用户登录
$auth_user_id = $_GET['auth_user_id'] ?? null;
$current_page = 'assessment'; 

if (!$auth_user_id) { header("location: login.php"); exit; }

// 2. 获取导师信息
$user_name = "Supervisor"; 
$user_avatar = "image/user.png"; 
$sv_id = 0;

if (isset($conn)) {
    $sql_user = "SELECT fyp_username FROM `USER` WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_user)) {
        $stmt->bind_param("i", $auth_user_id); $stmt->execute();
        $res = $stmt->get_result(); if ($row = $res->fetch_assoc()) $user_name = $row['fyp_username'];
        $stmt->close();
    }
    $sql_sv = "SELECT * FROM supervisor WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_sv)) {
        $stmt->bind_param("i", $auth_user_id); $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $sv_id = $row['fyp_supervisorid'];
            if (!empty($row['fyp_name'])) $user_name = $row['fyp_name'];
            if (!empty($row['fyp_profileimg'])) $user_avatar = $row['fyp_profileimg'];
        }
        $stmt->close();
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
    
    // 按钮逻辑判断
    $new_status = '';
    $msg = '';

    if (isset($_POST['return_grade'])) {
        $new_status = 'Graded';
        $msg = "Assignment returned (graded) successfully!";
    } elseif (isset($_POST['return_revision'])) {
        $new_status = 'Need Revision';
        $msg = "Assignment returned for revision!";
    }

    if ($new_status) {
        // 检查是否已经有记录
        $check_sql = "SELECT fyp_submissionid FROM assignment_submission WHERE fyp_assignmentid = ? AND fyp_studid = ?";
        if ($stmt = $conn->prepare($check_sql)) {
            $stmt->bind_param("is", $assign_id, $stud_id);
            $stmt->execute();
            $stmt->store_result();
            
            if ($stmt->num_rows > 0) {
                // Update
                $sql_upd = "UPDATE assignment_submission 
                            SET fyp_marks = ?, fyp_feedback = ?, fyp_submission_status = ?, fyp_graded_date = ? 
                            WHERE fyp_assignmentid = ? AND fyp_studid = ?";
                $stmt_upd = $conn->prepare($sql_upd);
                $stmt_upd->bind_param("isssis", $marks, $feedback, $new_status, $date_now, $assign_id, $stud_id);
                $stmt_upd->execute();
            } else {
                // Insert (Case: Grading before student submission, e.g. for absent student)
                $sql_ins = "INSERT INTO assignment_submission (fyp_assignmentid, fyp_studid, fyp_marks, fyp_feedback, fyp_submission_status, fyp_graded_date) 
                            VALUES (?, ?, ?, ?, ?, ?)";
                $stmt_ins = $conn->prepare($sql_ins);
                $stmt_ins->bind_param("isissi", $assign_id, $stud_id, $marks, $feedback, $new_status, $date_now);
                $stmt_ins->execute();
            }
            // 注意：这里跳转回当前文件
            echo "<script>alert('$msg'); window.location.href='Supervisor_assignment_grade.php?auth_user_id=" . urlencode($auth_user_id) . "&view_id=" . $assign_id . "';</script>";
        }
    }
}

// ====================================================
// 4. 获取数据 (Assignments List or Student List)
// ====================================================
$view_assignment_id = $_GET['view_id'] ?? null;
$assignments_list = [];
$students_to_grade = [];
$current_assignment = null;
$insight_stats = [
    'Total' => 0,
    'Not Turned In' => 0,
    'Viewed' => 0,
    'Turned In' => 0,
    'Late Turned In' => 0,
    'Graded' => 0,
    'Need Revision' => 0,
    'Resubmitted' => 0
];

if ($sv_id > 0) {
    if (!$view_assignment_id) {
        // A. 列表模式：显示所有作业
        $sql_list = "SELECT * FROM assignment WHERE fyp_supervisorid = '$sv_id' ORDER BY fyp_datecreated DESC";
        $res = $conn->query($sql_list);
        while ($row = $res->fetch_assoc()) $assignments_list[] = $row;
    } else {
        // B. 打分模式：显示该作业下的学生
        // 1. 先获取作业详情
        $sql_detail = "SELECT * FROM assignment WHERE fyp_assignmentid = '$view_assignment_id' AND fyp_supervisorid = '$sv_id'";
        $res_d = $conn->query($sql_detail);
        $current_assignment = $res_d->fetch_assoc();

        if ($current_assignment) {
            $target_type = $current_assignment['fyp_assignment_type'];
            $target_id = $current_assignment['fyp_target_id']; 

            // 2. 构造查询：为每位学生获取独立状态
            // 【关键修改】增加了 g.fyp_submitted_file 字段
            $base_fields = "s.fyp_studid, s.fyp_studname, s.fyp_studfullid, 
                            g.fyp_marks, g.fyp_feedback, g.fyp_submission_status, g.fyp_submission_date, g.fyp_submitted_file";
            
            $join_part = "LEFT JOIN assignment_submission g ON (s.fyp_studid = g.fyp_studid AND g.fyp_assignmentid = '$view_assignment_id')";

            if ($target_id == 'ALL') {
                if ($target_type == 'Individual') {
                    $sql_studs = "SELECT $base_fields FROM fyp_registration r JOIN student s ON r.fyp_studid = s.fyp_studid $join_part WHERE r.fyp_supervisorid = '$sv_id' AND s.fyp_group = 'Individual'";
                } else {
                    $sql_studs = "SELECT $base_fields FROM fyp_registration r JOIN student s ON r.fyp_studid = s.fyp_studid $join_part WHERE r.fyp_supervisorid = '$sv_id' AND s.fyp_group = 'Group'";
                }
            } else {
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
            
            if (isset($sql_studs)) {
                $res_s = $conn->query($sql_studs);
                if($res_s) {
                    while ($row = $res_s->fetch_assoc()) {
                        $raw_status = $row['fyp_submission_status'];
                        if (empty($raw_status)) $raw_status = 'Not Turned In';
                        
                        $display_status = $raw_status;
                        
                        // 判定 Late Turned In
                        if (($raw_status == 'Turned In' || $raw_status == 'Resubmitted') && !empty($row['fyp_submission_date'])) {
                            if ($row['fyp_submission_date'] > $current_assignment['fyp_deadline']) {
                                $display_status = 'Late Turned In';
                            }
                        }

                        $row['final_status'] = $display_status;
                        
                        // 统计
                        $insight_stats['Total']++;
                        if (isset($insight_stats[$display_status])) {
                            $insight_stats[$display_status]++;
                        } else {
                            $insight_stats[$display_status] = 1; 
                        }

                        $students_to_grade[] = $row;
                    }
                }
            }
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
    <title>Assessment & Grading</title>
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
        .submenu { list-style: none; padding: 0; margin: 0; background-color: #fafafa; display: none; }
        .menu-item.has-active-child .submenu, .menu-item:hover .submenu { display: block; }
        .submenu .menu-link { padding-left: 58px; font-size: 14px; padding-top: 10px; padding-bottom: 10px; }
        .main-content { flex: 1; display: flex; flex-direction: column; gap: 20px; }

        /* Page Specific */
        .card { background: #fff; padding: 25px; border-radius: 12px; box-shadow: var(--card-shadow); margin-bottom: 20px; }
        .card-header { font-size: 18px; font-weight: 600; color: #333; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        
        /* Insights Panel */
        .insights-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .insight-box { background: #fff; border: 1px solid #eee; padding: 15px; border-radius: 8px; text-align: center; border-bottom: 4px solid transparent; }
        .insight-val { font-size: 20px; font-weight: 700; color: #333; }
        .insight-lbl { font-size: 12px; color: #777; text-transform: uppercase; font-weight: 600; margin-top: 5px; }
        
        .ins-total { border-bottom-color: #6c757d; }
        .ins-pending { border-bottom-color: #ffc107; } 
        .ins-submitted { border-bottom-color: #28a745; } 
        .ins-graded { border-bottom-color: #17a2b8; }
        .ins-revision { border-bottom-color: #fd7e14; }

        /* Student Card */
        .student-card { background: #fff; border: 1px solid #eee; border-radius: 8px; padding: 20px; margin-bottom: 20px; display: flex; gap: 20px; flex-wrap: wrap; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
        .stud-info { width: 240px; border-right: 1px solid #eee; padding-right: 20px; }
        .stud-status-badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; margin-top: 5px; }
        
        /* Status Colors */
        .st-NotTurnedIn { background: #e2e3e5; color: #6c757d; }
        .st-Viewed { background: #cce5ff; color: #004085; }
        .st-TurnedIn { background: #d4edda; color: #155724; }
        .st-LateTurnedIn { background: #f8d7da; color: #721c24; }
        .st-Graded { background: #d1ecf1; color: #0c5460; }
        .st-NeedRevision { background: #fff3cd; color: #856404; }
        .st-Resubmitted { background: #d4edda; color: #155724; border: 2px solid #28a745; }

        .grade-form { flex: 1; display: flex; flex-direction: column; gap: 15px; }
        .gf-row { display: flex; gap: 15px; align-items: flex-start; }
        .gf-group { flex: 1; }
        .gf-group label { font-size: 12px; color: #666; font-weight: 600; display: block; margin-bottom: 5px; }
        .gf-input { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-family: inherit; }
        
        .action-buttons { display: flex; gap: 10px; margin-top: 10px; }
        .btn-return { padding: 9px 20px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 500; display: flex; align-items: center; gap: 5px; }
        .btn-revision { padding: 9px 20px; background: #ffc107; color: #212529; border: none; border-radius: 4px; cursor: pointer; font-weight: 500; display: flex; align-items: center; gap: 5px; }
        .btn-return:hover { background: #218838; }
        .btn-revision:hover { background: #e0a800; }
        .btn-return:disabled, .btn-revision:disabled { opacity: 0.5; cursor: not-allowed; background: #ccc; color: #666; }
        
        /* New File Link Style */
        .file-link { display: inline-block; margin-top: 8px; font-size: 13px; color: #0056b3; text-decoration: none; padding: 6px 12px; background: #e3effd; border-radius: 4px; border: 1px solid #b8daff; transition: all 0.2s; }
        .file-link:hover { background: #d0e4ff; }
        .file-link i { margin-right: 5px; }
        .no-file { font-size: 13px; color: #999; font-style: italic; margin-top: 5px; display: block; }

        .ass-table { width: 100%; border-collapse: collapse; }
        .ass-table th { text-align: left; padding: 12px 15px; background: #f8f9fa; color: #555; font-weight: 600; font-size: 13px; }
        .ass-table td { padding: 12px 15px; border-bottom: 1px solid #eee; font-size: 14px; color: #333; }
        .btn-view { padding: 6px 15px; background: var(--primary-color); color: white; text-decoration: none; border-radius: 4px; font-size: 12px; }

        .back-link { display: inline-block; margin-bottom: 15px; color: #666; text-decoration: none; font-size: 14px; }
        .back-link:hover { color: var(--primary-color); }
        
        @media (max-width: 900px) { .layout-container { flex-direction: column; } .sidebar { width: 100%; min-height: auto; } .student-card { flex-direction: column; } .stud-info { width: 100%; border-right: none; border-bottom: 1px solid #eee; padding-bottom: 15px; padding-right: 0; } }
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
                        $isActive = ($key == 'grading'); 
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
                                    <li><a href="<?php echo $subLinkUrl; ?>" class="menu-link <?php echo ($sub_key == 'grade_assignment') ? 'active' : ''; ?>">
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
                <!-- VIEW A: List Assignments -->
                <div class="card">
                    <div class="card-header">
                        Select Assignment to Grade
                    </div>
                    <?php if (count($assignments_list) > 0): ?>
                        <table class="ass-table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Type</th>
                                    <th>Target</th>
                                    <th>Deadline</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assignments_list as $ass): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($ass['fyp_title']); ?></strong></td>
                                        <td><span style="font-size:12px; background:#f0f0f0; padding:2px 8px; border-radius:4px;"><?php echo $ass['fyp_assignment_type']; ?></span></td>
                                        <td><span style="font-size:12px; color:#666;"><?php echo $ass['fyp_target_id'] == 'ALL' ? 'All Students' : 'Specific Group/Student'; ?></span></td>
                                        <td><?php echo $ass['fyp_deadline']; ?></td>
                                        <td>
                                            <a href="?auth_user_id=<?php echo $auth_user_id; ?>&view_id=<?php echo $ass['fyp_assignmentid']; ?>" class="btn-view">Grade</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="text-align:center; color:#999; padding:20px;">No assignments found.</div>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <!-- VIEW B: Grade Students -->
                <a href="?auth_user_id=<?php echo $auth_user_id; ?>" class="back-link"><i class="fa fa-arrow-left"></i> Back to Assignments</a>
                
                <!-- INSIGHT DASHBOARD -->
                <div class="insights-grid">
                    <div class="insight-box ins-total">
                        <div class="insight-val"><?php echo $insight_stats['Total']; ?></div>
                        <div class="insight-lbl">Total Students</div>
                    </div>
                    <div class="insight-box ins-submitted">
                        <div class="insight-val">
                            <?php 
                                echo $insight_stats['Turned In'] + $insight_stats['Late Turned In'] + $insight_stats['Resubmitted'];
                            ?>
                        </div>
                        <div class="insight-lbl">Submitted</div>
                    </div>
                    <div class="insight-box ins-pending">
                        <div class="insight-val">
                            <?php echo $insight_stats['Not Turned In'] + $insight_stats['Viewed']; ?>
                        </div>
                        <div class="insight-lbl">Not Submitted</div>
                    </div>
                    <div class="insight-box ins-graded">
                        <div class="insight-val"><?php echo $insight_stats['Graded']; ?></div>
                        <div class="insight-lbl">Graded</div>
                    </div>
                    <div class="insight-box ins-revision">
                        <div class="insight-val"><?php echo $insight_stats['Need Revision']; ?></div>
                        <div class="insight-lbl">Need Revision</div>
                    </div>
                </div>

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

                            // 是否允许打分
                            $canGrade = ($status == 'Turned In' || $status == 'Late Turned In' || $status == 'Resubmitted' || $status == 'Graded' || $status == 'Need Revision');
                        ?>
                            <div class="student-card">
                                <div class="stud-info">
                                    <div style="font-weight:600; color:#333; font-size:16px;"><?php echo htmlspecialchars($stud['fyp_studname']); ?></div>
                                    <div style="font-size:13px; color:#888; margin-bottom:5px;"><?php echo htmlspecialchars($stud['fyp_studfullid']); ?></div>
                                    <span class="stud-status-badge st-<?php echo $cssStatus; ?>"><?php echo $status; ?></span>
                                    
                                    <?php if(!empty($stud['fyp_submission_date'])): ?>
                                        <div class="submission-meta">
                                            Submitted: <br><?php echo $stud['fyp_submission_date']; ?>
                                        </div>
                                    <?php endif; ?>

                                    <!-- FILE DOWNLOAD LINK -->
                                    <?php if(!empty($stud['fyp_submitted_file'])): ?>
                                        <a href="<?php echo $stud['fyp_submitted_file']; ?>" target="_blank" class="file-link">
                                            <i class="fa fa-download"></i> View File
                                        </a>
                                    <?php else: ?>
                                        <span class="no-file">No file uploaded</span>
                                    <?php endif; ?>
                                </div>
                                
                                <form method="POST" class="grade-form">
                                    <input type="hidden" name="assignment_id" value="<?php echo $view_assignment_id; ?>">
                                    <input type="hidden" name="student_id" value="<?php echo $stud['fyp_studid']; ?>">
                                    
                                    <div class="gf-row">
                                        <div class="gf-group" style="flex:0 0 100px;">
                                            <label>Marks (0-100)</label>
                                            <input type="number" name="marks" class="gf-input" value="<?php echo $stud['fyp_marks']; ?>" min="0" max="100">
                                        </div>
                                        <div class="gf-group">
                                            <label>Feedback / Comments</label>
                                            <input type="text" name="feedback" class="gf-input" value="<?php echo htmlspecialchars($stud['fyp_feedback']); ?>" placeholder="Enter comments for student...">
                                        </div>
                                    </div>
                                    
                                    <div class="action-buttons">
                                        <button type="submit" name="return_grade" class="btn-return" title="Grade and Return" <?php echo $canGrade ? '' : 'disabled'; ?>>
                                            <i class="fa fa-check-circle"></i> Return (Grade)
                                        </button>
                                        <button type="submit" name="return_revision" class="btn-revision" title="Request Revision" <?php echo $canGrade ? '' : 'disabled'; ?>>
                                            <i class="fa fa-undo"></i> Return with Revision
                                        </button>
                                        <?php if(!$canGrade): ?>
                                            <span style="font-size:11px; color:#999; align-self: center;">Waiting for submission...</span>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align:center; color:#999; padding:20px;">No students found for this assignment target.</div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>