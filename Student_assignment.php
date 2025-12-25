<?php
// ====================================================
// student_assignment.php - 学生查看作业列表 (Split View & No Marks)
// ====================================================
include("connect.php");

// 1. 验证用户登录
$auth_user_id = $_GET['auth_user_id'] ?? null;
if (!$auth_user_id) { header("location: login.php"); exit; }

// 2. 获取当前学生信息
$stud_data = [];
$current_stud_id = '';
$user_name = 'Student';
$user_avatar = 'image/user.png'; 
$my_group_status = 'Individual';

if (isset($conn)) {
    // USER
    $sql_user = "SELECT fyp_username FROM `USER` WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_user)) { 
        $stmt->bind_param("i", $auth_user_id); $stmt->execute(); 
        $res = $stmt->get_result(); if ($row = $res->fetch_assoc()) $user_name = $row['fyp_username']; 
        $stmt->close(); 
    }
    // STUDENT
    $sql_stud = "SELECT * FROM STUDENT WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_stud)) { 
        $stmt->bind_param("i", $auth_user_id); $stmt->execute(); 
        $res = $stmt->get_result(); 
        if ($row = $res->fetch_assoc()) { 
            $stud_data = $row; 
            $current_stud_id = $row['fyp_studid'];
            if (!empty($row['fyp_studname'])) $user_name = $row['fyp_studname'];
            if (!empty($row['fyp_profileimg'])) $user_avatar = $row['fyp_profileimg'];
            if (!empty($row['fyp_group'])) $my_group_status = $row['fyp_group'];
        } 
        $stmt->close(); 
    }
}

// ----------------------------------------------------
// 3. 获取上下文信息 (Supervisor & Group ID)
// ----------------------------------------------------
$sv_id = 0;
$my_group_id = 0;

if ($current_stud_id) {
    // A. 获取 Supervisor ID (从注册表)
    $sql_reg = "SELECT fyp_supervisorid FROM fyp_registration WHERE fyp_studid = ? LIMIT 1";
    if ($stmt = $conn->prepare($sql_reg)) {
        $stmt->bind_param("s", $current_stud_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $sv_id = $row['fyp_supervisorid'];
        }
        $stmt->close();
    }

    // B. 获取 Group ID (如果是 Group 模式)
    if ($my_group_status == 'Group') {
        // 检查是否为 Leader
        $sql_l = "SELECT group_id FROM student_group WHERE leader_id = '$current_stud_id' LIMIT 1";
        $res_l = $conn->query($sql_l);
        if ($res_l && $row_l = $res_l->fetch_assoc()) {
            $my_group_id = $row_l['group_id'];
        } else {
            // 检查是否为 Member
            $sql_m = "SELECT group_id FROM group_request WHERE invitee_id = '$current_stud_id' AND request_status = 'Accepted' LIMIT 1";
            $res_m = $conn->query($sql_m);
            if ($res_m && $row_m = $res_m->fetch_assoc()) {
                $my_group_id = $row_m['group_id'];
            }
        }
    }
}

// ----------------------------------------------------
// 4. 获取作业列表 (Split Logic)
// ----------------------------------------------------
$pending_assignments = [];
$completed_assignments = [];

if ($sv_id > 0) {
    
    $sql_ass = "SELECT a.*, 
                       s.fyp_submission_status, 
                       s.fyp_submission_date 
                FROM assignment a
                LEFT JOIN assignment_submission s ON (a.fyp_assignmentid = s.fyp_assignmentid AND s.fyp_studid = ?)
                WHERE a.fyp_supervisorid = ? 
                AND a.fyp_status = 'Active'
                AND (
                    (a.fyp_assignment_type = 'Individual' AND ? = 'Individual' AND (a.fyp_target_id = 'ALL' OR a.fyp_target_id = ?))
                    OR
                    (a.fyp_assignment_type = 'Group' AND ? = 'Group' AND (a.fyp_target_id = 'ALL' OR a.fyp_target_id = ?))
                )
                ORDER BY a.fyp_deadline ASC";

    if ($stmt = $conn->prepare($sql_ass)) {
        $stmt->bind_param("sisisi", 
            $current_stud_id, 
            $sv_id, 
            $my_group_status, 
            $current_stud_id, 
            $my_group_status, 
            $my_group_id
        );
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            // 设置默认状态
            if (empty($row['fyp_submission_status'])) {
                $row['fyp_submission_status'] = 'Not Turned In';
            }
            
            // 检查迟交逻辑
            $row['is_late'] = false;
            if ($row['fyp_submission_status'] == 'Not Turned In' && strtotime($row['fyp_deadline']) < time()) {
                 $row['is_late'] = true; 
                 $row['fyp_submission_status'] = 'Missing'; 
            }
            
            // 分类
            $status = $row['fyp_submission_status'];
            if ($status == 'Not Turned In' || $status == 'Viewed' || $status == 'Need Revision' || $status == 'Missing') {
                $pending_assignments[] = $row;
            } else {
                // Turned In, Late Turned In, Resubmitted, Graded
                $completed_assignments[] = $row;
            }
        }
        $stmt->close();
    }
}

// 菜单定义
$current_page = 'assignments'; 
$menu_items = [
    'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home', 'link' => 'Student_mainpage.php?page=dashboard'],
    'profile' => ['name' => 'My Profile', 'icon' => 'fa-user', 'link' => 'std_profile.php'], 
    'project_mgmt' => [
        'name' => 'Final Year Project',
        'icon' => 'fa-project-diagram',
        'sub_items' => [
            'group_setup' => ['name' => 'Project Registration', 'icon' => 'fa-users', 'link' => 'std_projectreg.php'], 
            'team_invitations' => ['name' => 'Request & Team Status', 'icon' => 'fa-tasks', 'link' => 'std_request_status.php'], 
            'proposals' => ['name' => 'Proposal Submission', 'icon' => 'fa-file-alt', 'link' => 'Student_mainpage.php?page=proposals'],
            'doc_submission' => ['name' => 'Document Upload', 'icon' => 'fa-cloud-upload-alt', 'link' => 'Student_mainpage.php?page=doc_submission'],
        ]
    ],
    'assignments' => ['name' => 'Assignments', 'icon' => 'fa-tasks', 'link' => 'student_assignment.php'],
    'appointments' => [
        'name' => 'Appointment', 
        'icon' => 'fa-calendar-check', 
        'sub_items' => [
            'book_session' => ['name' => 'Book Consultation', 'icon' => 'fa-comments', 'link' => 'student_appointment_meeting.php'], 
            'presentation' => ['name' => 'Final Presentation', 'icon' => 'fa-chalkboard-teacher', 'link' => 'Student_mainpage.php?page=presentation']
        ]
    ],
    'grades' => ['name' => 'My Grades', 'icon' => 'fa-star', 'link' => 'Student_mainpage.php?page=grades'],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Assignments</title>
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
        .welcome-card { background: #fff; padding: 30px; border-radius: 12px; box-shadow: var(--card-shadow); border-left: 5px solid var(--primary-color); }
        .page-title { font-size: 24px; margin: 0 0 10px 0; color: var(--text-color); }
        .empty-state { text-align: center; padding: 30px; color: #999; background: #fff; border-radius: 12px; border: 1px dashed #ddd; margin-bottom: 20px; }

        /* Assignment List Styles */
        .section-header { font-size: 18px; font-weight: 600; color: #333; margin-bottom: 15px; margin-top: 10px; padding-bottom: 5px; border-bottom: 2px solid #eee; }
        
        .ass-grid { display: flex; flex-direction: column; gap: 15px; margin-bottom: 30px; }
        .ass-card { background: #fff; border-radius: 10px; padding: 20px; box-shadow: var(--card-shadow); display: flex; justify-content: space-between; align-items: center; transition: transform 0.2s; border-left: 5px solid #ccc; position: relative; }
        .ass-card:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.08); }
        
        /* Status Colors */
        .ass-card.st-NotTurnedIn { border-left-color: #6c757d; }
        .ass-card.st-Viewed { border-left-color: #007bff; }
        .ass-card.st-TurnedIn { border-left-color: #28a745; }
        .ass-card.st-LateTurnedIn { border-left-color: #ffc107; }
        .ass-card.st-Missing { border-left-color: #dc3545; }
        .ass-card.st-Graded { border-left-color: #17a2b8; }
        .ass-card.st-NeedRevision { border-left-color: #fd7e14; }

        .ass-content { flex: 1; }
        .ass-title { font-size: 18px; font-weight: 600; color: #333; margin-bottom: 5px; }
        .ass-meta { font-size: 13px; color: #777; display: flex; gap: 15px; align-items: center; }
        .ass-meta i { color: #999; }
        .ass-badge { padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; background: #f0f0f0; color: #555; }
        
        .ass-action { text-align: right; min-width: 120px; }
        .status-pill { display: inline-block; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-bottom: 8px; }
        
        .pill-NotTurnedIn { background: #e2e3e5; color: #555; }
        .pill-Viewed { background: #cce5ff; color: #004085; }
        .pill-TurnedIn { background: #d4edda; color: #155724; }
        .pill-LateTurnedIn { background: #fff3cd; color: #856404; }
        .pill-Missing { background: #f8d7da; color: #721c24; }
        .pill-Graded { background: #d1ecf1; color: #0c5460; }
        .pill-NeedRevision { background: #ffeeba; color: #856404; }

        .btn-view { display: inline-block; padding: 8px 20px; background: var(--primary-color); color: white; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 500; transition: background 0.2s; }
        .btn-view:hover { background: #004494; }

        @media (max-width: 900px) { .layout-container { flex-direction: column; } .sidebar { width: 100%; min-height: auto; } .ass-card { flex-direction: column; align-items: flex-start; gap: 15px; } .ass-action { width: 100%; text-align: left; display: flex; justify-content: space-between; align-items: center; } }
    </style>
</head>
<body>

    <header class="topbar">
        <div class="logo">FYP System</div>
        <div class="topbar-right">
            <div class="user-profile-summary">
                <span class="user-name-display"><?php echo htmlspecialchars($user_name); ?></span>
                <span class="user-role-badge">Student</span>
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
                        $linkUrl = isset($item['link']) ? $item['link'] : "#";
                        if (strpos($linkUrl, '.php') !== false) {
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
            <div class="welcome-card">
                <h1 class="page-title">My Assignments</h1>
                <p style="color: #666; margin: 0;">View and manage your tasks assigned by supervisor.</p>
            </div>

            <?php if ($sv_id == 0): ?>
                <div class="empty-state">
                    <i class="fa fa-exclamation-circle" style="font-size: 48px; color: #f0ad4e; margin-bottom: 15px;"></i>
                    <p><strong>Supervisor Not Assigned</strong></p>
                    <p>You need to have an approved project and supervisor to receive assignments.</p>
                </div>
            <?php else: ?>
                
                <!-- 1. To Do (Pending) Assignments -->
                <h3 class="section-header"><i class="fa fa-clipboard-list"></i> To Do</h3>
                <?php if (count($pending_assignments) > 0): ?>
                    <div class="ass-grid">
                        <?php foreach ($pending_assignments as $ass): 
                            $status = $ass['fyp_submission_status'];
                            $deadline = date('M d, Y h:i A', strtotime($ass['fyp_deadline']));
                            $statusClass = 'st-' . str_replace(' ', '', $status);
                            $pillClass = 'pill-' . str_replace(' ', '', $status);
                            
                            $deadline_ts = strtotime($ass['fyp_deadline']);
                            $time_left = $deadline_ts - time();
                            $days_left = floor($time_left / (60 * 60 * 24));
                            $deadline_color = ($time_left < 0) ? 'red' : (($days_left < 3) ? '#f39c12' : '#777');
                        ?>
                            <div class="ass-card <?php echo $statusClass; ?>">
                                <div class="ass-content">
                                    <div class="ass-title"><?php echo htmlspecialchars($ass['fyp_title']); ?></div>
                                    <div class="ass-meta">
                                        <span class="ass-badge"><?php echo $ass['fyp_assignment_type']; ?></span>
                                        <span><i class="fa fa-clock"></i> Due: <span style="color:<?php echo $deadline_color; ?>; font-weight:500;"><?php echo $deadline; ?></span></span>
                                    </div>
                                </div>
                                <div class="ass-action">
                                    <span class="status-pill <?php echo $pillClass; ?>"><?php echo $status; ?></span>
                                    <br>
                                    <a href="student_assignment_details.php?id=<?php echo $ass['fyp_assignmentid']; ?>&auth_user_id=<?php echo $auth_user_id; ?>" class="btn-view">
                                        View Details
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state" style="margin-bottom: 30px;">
                        <p>No pending assignments.</p>
                    </div>
                <?php endif; ?>

                <!-- 2. Completed (Submitted/Graded) Assignments -->
                <h3 class="section-header"><i class="fa fa-check-circle"></i> Completed / Submitted</h3>
                <?php if (count($completed_assignments) > 0): ?>
                    <div class="ass-grid">
                        <?php foreach ($completed_assignments as $ass): 
                            $status = $ass['fyp_submission_status'];
                            $deadline = date('M d, Y h:i A', strtotime($ass['fyp_deadline']));
                            $statusClass = 'st-' . str_replace(' ', '', $status);
                            $pillClass = 'pill-' . str_replace(' ', '', $status);
                        ?>
                            <div class="ass-card <?php echo $statusClass; ?>">
                                <div class="ass-content">
                                    <div class="ass-title"><?php echo htmlspecialchars($ass['fyp_title']); ?></div>
                                    <div class="ass-meta">
                                        <span class="ass-badge"><?php echo $ass['fyp_assignment_type']; ?></span>
                                        <span><i class="fa fa-clock"></i> Due: <?php echo $deadline; ?></span>
                                    </div>
                                </div>
                                <div class="ass-action">
                                    <span class="status-pill <?php echo $pillClass; ?>"><?php echo $status; ?></span>
                                    <br>
                                    <a href="student_assignment_details.php?id=<?php echo $ass['fyp_assignmentid']; ?>&auth_user_id=<?php echo $auth_user_id; ?>" class="btn-view">
                                        View Details
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <p>No completed assignments yet.</p>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </main>
    </div>
</body>
</html>