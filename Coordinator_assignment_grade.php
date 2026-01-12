<?php
// ====================================================
// Coordinator_assignment_grade.php - 作业评分 (Coordinator 版)
// ====================================================
include("connect.php");

$auth_user_id = $_GET['auth_user_id'] ?? null;
$current_page = 'grade_assignment'; 
if (!$auth_user_id) { header("location: login.php"); exit; }

// --- 获取 Coordinator 信息 ---
$user_name = "Coordinator"; $user_avatar = "image/user.png"; $sv_id = 0;
if ($stmt = $conn->prepare("SELECT * FROM supervisor WHERE fyp_userid = ?")) {
    $stmt->bind_param("i", $auth_user_id); $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc()) {
        $sv_id = $row['fyp_supervisorid']; $user_name = $row['fyp_name'];
        if($row['fyp_profileimg']) $user_avatar = $row['fyp_profileimg'];
    }
    $stmt->close();
}

// --- 处理打分提交 ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $assign_id = $_POST['assignment_id'];
    $stud_id = $_POST['student_id'];
    $marks = $_POST['marks'];
    $feedback = $_POST['feedback'];
    $status = isset($_POST['return_revision']) ? 'Need Revision' : 'Graded';
    
    // Check exist
    $chk = $conn->query("SELECT fyp_submissionid FROM assignment_submission WHERE fyp_assignmentid = '$assign_id' AND fyp_studid = '$stud_id'");
    if ($chk->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE assignment_submission SET fyp_marks=?, fyp_feedback=?, fyp_submission_status=?, fyp_graded_date=NOW() WHERE fyp_assignmentid=? AND fyp_studid=?");
        $stmt->bind_param("issis", $marks, $feedback, $status, $assign_id, $stud_id);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("INSERT INTO assignment_submission (fyp_assignmentid, fyp_studid, fyp_marks, fyp_feedback, fyp_submission_status, fyp_graded_date) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("isiss", $assign_id, $stud_id, $marks, $feedback, $status);
        $stmt->execute();
    }
    echo "<script>alert('Graded successfully!'); window.location.href='Coordinator_assignment_grade.php?auth_user_id=$auth_user_id&view_id=$assign_id';</script>";
}

// --- 获取作业列表或学生详情 ---
$view_id = $_GET['view_id'] ?? null;
$list = []; $students = []; $current_ass = null;

if ($sv_id > 0) {
    if (!$view_id) {
        // List View
        $sql = "SELECT a.*, (SELECT COUNT(*) FROM assignment_submission s WHERE s.fyp_assignmentid = a.fyp_assignmentid AND s.fyp_submission_status IN ('Turned In','Late Turned In')) as submitted_cnt, (SELECT COUNT(*) FROM assignment_submission s WHERE s.fyp_assignmentid = a.fyp_assignmentid AND s.fyp_submission_status = 'Graded') as graded_cnt FROM assignment a WHERE a.fyp_supervisorid = '$sv_id' ORDER BY a.fyp_datecreated DESC";
        $res = $conn->query($sql);
        while($row = $res->fetch_assoc()) $list[] = $row;
    } else {
        // Detail View
        $current_ass = $conn->query("SELECT * FROM assignment WHERE fyp_assignmentid = '$view_id'")->fetch_assoc();
        if ($current_ass) {
            // Simplified logic to fetch students based on assignment target
            // Note: This needs robust logic for Groups vs Individual (reusing Supervisor logic ideally)
            // Here we assume a simplified join for brevity, assuming standard Supervisor logic applies
            $sql_s = "SELECT s.fyp_studid, s.fyp_studname, s.fyp_group, sub.fyp_marks, sub.fyp_feedback, sub.fyp_submission_status, sub.fyp_submitted_file FROM fyp_registration r JOIN student s ON r.fyp_studid = s.fyp_studid LEFT JOIN assignment_submission sub ON (s.fyp_studid = sub.fyp_studid AND sub.fyp_assignmentid = '$view_id') WHERE r.fyp_supervisorid = '$sv_id'";
            // Filter by type if needed
            if($current_ass['fyp_assignment_type'] == 'Group') $sql_s .= " AND s.fyp_group = 'Group'";
            if($current_ass['fyp_assignment_type'] == 'Individual') $sql_s .= " AND s.fyp_group = 'Individual'";
            
            $res_s = $conn->query($sql_s);
            while($row = $res_s->fetch_assoc()) $students[] = $row;
        }
    }
}

// --- Menu ---
$menu_items = [
    'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home', 'link' => 'Coordinator_mainpage.php?page=dashboard'],
    'profile'   => ['name' => 'Profile', 'icon' => 'fa-user', 'link' => 'Coordinator_profile.php'], 
    'project_mgmt' => ['name' => 'Project Mgmt', 'icon' => 'fa-tasks', 'sub_items' => ['propose_project' => ['name' => 'Propose Project', 'icon' => 'fa-plus-circle', 'link' => 'Coordinator_purpose.php'], 'project_requests' => ['name' => 'Project Requests', 'icon' => 'fa-envelope-open-text', 'link' => 'Coordinator_projectreq.php'], 'allocation' => ['name' => 'Auto Allocation', 'icon' => 'fa-bullseye', 'link' => 'Coordinator_mainpage.php?page=allocation']]],
    'assessment' => ['name' => 'Assessment', 'icon' => 'fa-clipboard-check', 'sub_items' => ['propose_assignment' => ['name' => 'Create Assignment', 'icon' => 'fa-plus', 'link' => 'Coordinator_assignment_purpose.php'], 'grade_assignment' => ['name' => 'Grade Assignments', 'icon' => 'fa-check-square', 'link' => 'Coordinator_assignment_grade.php']]],
    'announcements' => ['name' => 'Announcements', 'icon' => 'fa-bullhorn', 'sub_items' => ['post_announcement' => ['name' => 'Post New', 'icon' => 'fa-pen', 'link' => 'Coordinator_announcement.php']]],
    'schedule' => ['name' => 'My Schedule', 'icon' => 'fa-calendar-alt', 'link' => 'Coordinator_meeting.php'], 
    'data_io' => ['name' => 'Data Management', 'icon' => 'fa-database', 'link' => 'Coordinator_mainpage.php?page=data_io'],
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
        /* 复用样式 */
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap');
        :root { --primary-color: #0056b3; --primary-hover: #004494; --secondary-color: #f4f4f9; --text-color: #333; --border-color: #e0e0e0; --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); --sidebar-width: 260px; }
        body { font-family: 'Poppins', sans-serif; margin: 0; background: #eef2f7; color: var(--text-color); min-height: 100vh; display: flex; flex-direction: column; }
        .topbar { display: flex; justify-content: space-between; align-items: center; padding: 15px 40px; background-color: #fff; position: sticky; top: 0; z-index:100; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .logo { font-size: 22px; font-weight: 600; color: var(--primary-color); display: flex; align-items: center; gap: 10px; }
        .topbar-right { display: flex; align-items: center; gap: 20px; }
        .user-name-display { font-weight: 600; font-size: 14px; }
        .user-avatar-circle { width: 40px; height: 40px; border-radius: 50%; overflow: hidden; border: 2px solid #e3effd; margin-left: 10px; }
        .user-avatar-circle img { width: 100%; height: 100%; object-fit: cover; }
        .logout-btn { color: #d93025; text-decoration: none; font-weight: 500; }
        .layout-container { display: flex; flex: 1; max-width: 1400px; margin: 0 auto; width: 100%; padding: 20px; gap: 20px; }
        .sidebar { width: var(--sidebar-width); background: #fff; border-radius: 12px; box-shadow: var(--card-shadow); padding: 20px 0; min-height: calc(100vh - 120px); }
        .menu-list { list-style: none; padding: 0; margin: 0; }
        .menu-link { display: flex; align-items: center; padding: 12px 25px; text-decoration: none; color: #555; transition: all 0.3s; border-left: 4px solid transparent; }
        .menu-link:hover, .menu-link.active { background-color: #e3effd; color: var(--primary-color); border-left-color: var(--primary-color); }
        .menu-icon { width: 24px; margin-right: 10px; text-align: center; }
        .submenu { list-style: none; padding: 0; margin: 0; background-color: #fafafa; display: block; }
        .submenu .menu-link { padding-left: 58px; font-size: 14px; padding-top: 10px; padding-bottom: 10px; }
        .main-content { flex: 1; display: flex; flex-direction: column; gap: 20px; }
        
        /* Grading Specific */
        .ass-card { background: #fff; padding: 20px; border-radius: 8px; margin-bottom: 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .btn-view { padding: 8px 15px; background: var(--primary-color); color: white; text-decoration: none; border-radius: 4px; font-size: 13px; }
        .student-row { background: #fff; padding: 20px; margin-bottom: 15px; border-radius: 8px; border: 1px solid #eee; display: flex; flex-wrap: wrap; gap: 20px; }
        .st-info { width: 200px; border-right: 1px solid #eee; }
        .st-form { flex: 1; display: flex; gap: 10px; align-items: flex-end; }
        .form-input { padding: 8px; border: 1px solid #ccc; border-radius: 4px; width: 100%; }
        .status-badge { display: inline-block; padding: 3px 8px; border-radius: 10px; font-size: 11px; background: #eee; }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="logo"><img src="image/ladybug.png" alt="Logo" style="width: 32px;"> FYP System</div>
        <div class="topbar-right">
            <div><span class="user-name-display"><?php echo htmlspecialchars($user_name); ?></span> <span style="font-size:11px; background:#e3effd; color:#0056b3; padding:2px 8px; border-radius:12px;">Coordinator</span></div>
            <div class="user-avatar-circle"><img src="<?php echo $user_avatar; ?>" alt="User"></div>
            <a href="login.php" class="logout-btn"><i class="fa fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>
    <div class="layout-container">
        <aside class="sidebar">
            <ul class="menu-list">
                <?php foreach ($menu_items as $key => $item): 
                    $isActive = ($key == 'assessment'); 
                    $linkUrl = (isset($item['link']) && $item['link'] !== "#") ? $item['link'] . (strpos($item['link'], '?') !== false ? '&' : '?') . "auth_user_id=" . urlencode($auth_user_id) : "#";
                ?>
                <li class="menu-item">
                    <a href="<?php echo $linkUrl; ?>" class="menu-link <?php echo $isActive ? 'active' : ''; ?>">
                        <span class="menu-icon"><i class="fa <?php echo $item['icon']; ?>"></i></span> <?php echo $item['name']; ?>
                    </a>
                    <?php if (isset($item['sub_items'])): ?>
                        <ul class="submenu">
                        <?php foreach ($item['sub_items'] as $sub_key => $sub_item): 
                             $subLinkUrl = (isset($sub_item['link']) && $sub_item['link'] !== "#") ? $sub_item['link'] . (strpos($sub_item['link'], '?') !== false ? '&' : '?') . "auth_user_id=" . urlencode($auth_user_id) : "#";
                        ?>
                            <li><a href="<?php echo $subLinkUrl; ?>" class="menu-link <?php echo ($sub_key == $current_page) ? 'active' : ''; ?>">
                                <i class="fa <?php echo $sub_item['icon']; ?>" style="margin-right:10px;"></i> <?php echo $sub_item['name']; ?>
                            </a></li>
                        <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </aside>
        <main class="main-content">
            <?php if (!$view_id): ?>
                <div style="background:#fff; padding:20px; border-radius:12px; margin-bottom:20px;"><h2>Assignments List</h2></div>
                <?php foreach($list as $a): ?>
                    <div class="ass-card">
                        <div>
                            <h3 style="margin:0 0 5px 0;"><?php echo htmlspecialchars($a['fyp_title']); ?></h3>
                            <span style="font-size:13px; color:#666;">Deadline: <?php echo $a['fyp_deadline']; ?> | Type: <?php echo $a['fyp_assignment_type']; ?></span>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-size:12px; margin-bottom:5px;">Submitted: <?php echo $a['submitted_cnt']; ?> | Graded: <?php echo $a['graded_cnt']; ?></div>
                            <a href="?auth_user_id=<?php echo $auth_user_id; ?>&view_id=<?php echo $a['fyp_assignmentid']; ?>" class="btn-view">Grade</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <a href="?auth_user_id=<?php echo $auth_user_id; ?>" style="display:inline-block; margin-bottom:10px; color:#666; text-decoration:none;"><i class="fa fa-arrow-left"></i> Back</a>
                <div style="background:#fff; padding:20px; border-radius:12px; margin-bottom:20px;">
                    <h2 style="margin:0;">Grading: <?php echo htmlspecialchars($current_ass['fyp_title']); ?></h2>
                </div>
                <?php foreach($students as $s): 
                    $st = $s['fyp_submission_status'] ?: 'Not Turned In';
                ?>
                    <div class="student-row">
                        <div class="st-info">
                            <strong><?php echo htmlspecialchars($s['fyp_studname']); ?></strong>
                            <div style="font-size:12px; margin-bottom:5px; color:#888;"><?php echo $s['fyp_group']; ?></div>
                            <span class="status-badge"><?php echo $st; ?></span>
                            <?php if($s['fyp_submitted_file']): ?>
                                <div style="margin-top:5px;"><a href="<?php echo $s['fyp_submitted_file']; ?>" target="_blank" style="font-size:12px;">View File</a></div>
                            <?php endif; ?>
                        </div>
                        <form method="POST" class="st-form">
                            <input type="hidden" name="assignment_id" value="<?php echo $view_id; ?>">
                            <input type="hidden" name="student_id" value="<?php echo $s['fyp_studid']; ?>">
                            <div style="width:80px;">
                                <label style="font-size:11px;">Marks</label>
                                <input type="number" name="marks" class="form-input" value="<?php echo $s['fyp_marks']; ?>">
                            </div>
                            <div style="flex:1;">
                                <label style="font-size:11px;">Feedback</label>
                                <input type="text" name="feedback" class="form-input" value="<?php echo $s['fyp_feedback']; ?>">
                            </div>
                            <button type="submit" class="btn-view" style="height:38px;">Save</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>