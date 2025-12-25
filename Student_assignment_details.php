<?php
// ====================================================
// student_assignment_details.php - 作业详情与提交
// ====================================================
include("connect.php");

// 1. 验证用户登录
$auth_user_id = $_GET['auth_user_id'] ?? null;
$assign_id = $_GET['id'] ?? null;

if (!$auth_user_id || !$assign_id) { header("location: login.php"); exit; }

// 2. 获取当前学生信息
$stud_data = [];
$current_stud_id = '';
$user_name = 'Student';
$user_avatar = 'image/user.png';

if (isset($conn)) {
    $sql_user = "SELECT fyp_username FROM `USER` WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_user)) { 
        $stmt->bind_param("i", $auth_user_id); $stmt->execute(); 
        $res = $stmt->get_result(); if ($row = $res->fetch_assoc()) $user_name = $row['fyp_username']; 
        $stmt->close(); 
    }
    $sql_stud = "SELECT * FROM STUDENT WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_stud)) { 
        $stmt->bind_param("i", $auth_user_id); $stmt->execute(); 
        $res = $stmt->get_result(); 
        if ($row = $res->fetch_assoc()) { 
            $stud_data = $row; 
            $current_stud_id = $row['fyp_studid'];
            if (!empty($row['fyp_studname'])) $user_name = $row['fyp_studname'];
            if (!empty($row['fyp_profileimg'])) $user_avatar = $row['fyp_profileimg'];
        } 
        $stmt->close(); 
    }
}

// 3. 获取作业详情
$assignment = null;
$sql_ass = "SELECT * FROM assignment WHERE fyp_assignmentid = ?";
if ($stmt = $conn->prepare($sql_ass)) {
    $stmt->bind_param("i", $assign_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $assignment = $res->fetch_assoc();
    $stmt->close();
}

if (!$assignment) { die("Assignment not found."); }

// 4. 获取/初始化 提交记录 (Submission Record)
$submission = null;
$sql_sub = "SELECT * FROM assignment_submission WHERE fyp_assignmentid = ? AND fyp_studid = ?";
if ($stmt = $conn->prepare($sql_sub)) {
    $stmt->bind_param("is", $assign_id, $current_stud_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $submission = $res->fetch_assoc();
    $stmt->close();
}

// === 核心逻辑 A: 自动标记为 "Viewed" ===
if (!$submission) {
    // 如果没有记录，创建一条 'Viewed'
    $conn->query("INSERT INTO assignment_submission (fyp_assignmentid, fyp_studid, fyp_submission_status) VALUES ('$assign_id', '$current_stud_id', 'Viewed')");
    // 刷新数据
    $submission = ['fyp_submission_status' => 'Viewed', 'fyp_marks' => null, 'fyp_feedback' => null, 'fyp_submitted_file' => null];
} elseif ($submission['fyp_submission_status'] == 'Not Turned In') {
    // 如果状态是默认的 Not Turned In，更新为 Viewed
    $conn->query("UPDATE assignment_submission SET fyp_submission_status = 'Viewed' WHERE fyp_submissionid = '{$submission['fyp_submissionid']}'");
    $submission['fyp_submission_status'] = 'Viewed';
}

// === 核心逻辑 B: 处理文件上传 (Submit / Resubmit) ===
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_assignment'])) {
    
    // 检查文件
    if (isset($_FILES['file_upload']) && $_FILES['file_upload']['error'] == 0) {
        $upload_dir = "uploads/assignments/";
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $file_name = time() . "_" . basename($_FILES['file_upload']['name']);
        $target_file = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['file_upload']['tmp_name'], $target_file)) {
            $date_now = date('Y-m-d H:i:s');
            
            // 判断状态
            $new_status = 'Turned In';
            
            // 1. 检查是否迟交
            if ($date_now > $assignment['fyp_deadline']) {
                $new_status = 'Late Turned In';
            }
            
            // 2. 检查是否是 Revision 后的重新提交
            if ($submission['fyp_submission_status'] == 'Need Revision') {
                $new_status = 'Resubmitted';
            }

            // 更新数据库
            $sql_upd = "UPDATE assignment_submission 
                        SET fyp_submitted_file = ?, fyp_submission_status = ?, fyp_submission_date = ? 
                        WHERE fyp_assignmentid = ? AND fyp_studid = ?";
            if ($stmt = $conn->prepare($sql_upd)) {
                $stmt->bind_param("sssis", $target_file, $new_status, $date_now, $assign_id, $current_stud_id);
                $stmt->execute();
                $stmt->close();
                
                echo "<script>alert('Assignment submitted successfully!'); window.location.href='student_assignment_details.php?id=$assign_id&auth_user_id=" . urlencode($auth_user_id) . "';</script>";
            }
        } else {
            echo "<script>alert('File upload failed.');</script>";
        }
    } else {
        echo "<script>alert('Please select a file.');</script>";
    }
}

// === 核心逻辑 C: 处理撤销 (Undo) ===
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['undo_submission'])) {
    $current_status = $submission['fyp_submission_status'];
    
    // 只有未被打分的状态才能撤销
    if ($current_status != 'Graded') {
        // 删除服务器上的文件 (可选，这里只清空数据库记录)
        /* if (file_exists($submission['fyp_submitted_file'])) { unlink($submission['fyp_submitted_file']); } */
        
        $sql_undo = "UPDATE assignment_submission 
                     SET fyp_submitted_file = NULL, fyp_submission_status = 'Viewed', fyp_submission_date = NULL 
                     WHERE fyp_assignmentid = ? AND fyp_studid = ?";
        if ($stmt = $conn->prepare($sql_undo)) {
            $stmt->bind_param("is", $assign_id, $current_stud_id);
            $stmt->execute();
            $stmt->close();
             echo "<script>alert('Submission undone. You can now submit again.'); window.location.href='student_assignment_details.php?id=$assign_id&auth_user_id=" . urlencode($auth_user_id) . "';</script>";
        }
    } else {
        echo "<script>alert('Cannot undo: Assignment has already been graded.');</script>";
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
    <title>Assignment Details</title>
    <link rel="icon" type="image/png" href="<?php echo $user_avatar; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* 复用样式 */
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap');
        :root { --primary-color: #0056b3; --primary-hover: #004494; --secondary-color: #f4f4f9; --text-color: #333; --border-color: #e0e0e0; --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); }
        body { font-family: 'Poppins', sans-serif; margin: 0; background: linear-gradient(135deg, #eef2f7, #ffffff); color: var(--text-color); min-height: 100vh; display: flex; flex-direction: column; }
        
        /* Topbar & Sidebar (Standard) */
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

        /* Detail Page Styles */
        .header-card { background: #fff; padding: 30px; border-radius: 12px; box-shadow: var(--card-shadow); border-left: 5px solid var(--primary-color); }
        .ass-title { font-size: 24px; margin: 0 0 10px 0; color: var(--text-color); }
        .ass-meta { display: flex; gap: 20px; font-size: 14px; color: #666; margin-bottom: 10px; }
        .ass-badge { padding: 4px 10px; background: #f0f0f0; border-radius: 4px; font-weight: 600; font-size: 12px; text-transform: uppercase; }

        .content-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
        .desc-card { background: #fff; padding: 25px; border-radius: 12px; box-shadow: var(--card-shadow); }
        .submission-card { background: #fff; padding: 25px; border-radius: 12px; box-shadow: var(--card-shadow); border-top: 4px solid #ddd; }
        
        .section-h { font-size: 16px; font-weight: 600; margin-bottom: 15px; color: #333; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .desc-text { color: #444; line-height: 1.6; white-space: pre-wrap; font-size: 15px; }
        
        /* Submission Status Colors */
        .status-Viewed { border-top-color: #007bff; }
        .status-TurnedIn { border-top-color: #28a745; }
        .status-LateTurnedIn { border-top-color: #ffc107; }
        .status-Resubmitted { border-top-color: #28a745; }
        .status-Graded { border-top-color: #17a2b8; }
        .status-NeedRevision { border-top-color: #fd7e14; }

        .status-label { font-size: 14px; font-weight: 600; margin-bottom: 20px; display: block; }
        .status-tag { padding: 5px 12px; border-radius: 20px; color: white; font-size: 12px; }
        .tag-Viewed { background: #007bff; }
        .tag-TurnedIn { background: #28a745; }
        .tag-LateTurnedIn { background: #ffc107; color: #333; }
        .tag-Resubmitted { background: #28a745; }
        .tag-Graded { background: #17a2b8; }
        .tag-NeedRevision { background: #fd7e14; }

        .upload-area { border: 2px dashed #ccc; padding: 20px; text-align: center; border-radius: 8px; background: #f9f9f9; margin-bottom: 15px; transition: 0.2s; }
        .upload-area:hover { border-color: var(--primary-color); background: #eef2f7; }
        .file-input { width: 100%; margin-bottom: 10px; }
        
        .btn-submit { width: 100%; padding: 10px; background: var(--primary-color); color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .btn-submit:hover { background: #004494; }
        .btn-undo { width: 100%; padding: 10px; background: #f8f9fa; color: #d93025; border: 1px solid #d93025; border-radius: 6px; font-weight: 600; cursor: pointer; transition: 0.2s; margin-top: 10px; }
        .btn-undo:hover { background: #fff5f5; }

        .submitted-file { background: #e3effd; padding: 10px; border-radius: 6px; display: flex; align-items: center; gap: 10px; font-size: 14px; color: var(--primary-color); text-decoration: none; margin-bottom: 15px; }
        .submitted-file:hover { background: #d0e4ff; }

        .feedback-box { background: #fff3cd; border: 1px solid #ffeeba; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; color: #856404; }
        .marks-box { font-size: 24px; font-weight: 700; color: #28a745; margin-bottom: 10px; text-align: center; }

        @media (max-width: 900px) { .layout-container { flex-direction: column; } .sidebar { width: 100%; min-height: auto; } .content-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="logo">FYP System</div>
        <div class="topbar-right">
            <span class="user-name-display"><?php echo htmlspecialchars($user_name); ?></span>
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
            <!-- Header -->
            <div class="header-card">
                <a href="student_assignment.php?auth_user_id=<?php echo $auth_user_id; ?>" style="color:#666; text-decoration:none; font-size:13px; margin-bottom:10px; display:inline-block;"><i class="fa fa-arrow-left"></i> Back to List</a>
                <h1 class="ass-title"><?php echo htmlspecialchars($assignment['fyp_title']); ?></h1>
                <div class="ass-meta">
                    <span class="ass-badge"><?php echo $assignment['fyp_assignment_type']; ?></span>
                    <span><i class="fa fa-calendar-alt"></i> Deadline: <?php echo date('M d, Y h:i A', strtotime($assignment['fyp_deadline'])); ?></span>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Left: Description -->
                <div class="desc-card">
                    <div class="section-h">Instructions</div>
                    <div class="desc-text"><?php echo nl2br(htmlspecialchars($assignment['fyp_description'])); ?></div>
                </div>

                <!-- Right: Submission Box -->
                <?php 
                    $status = $submission['fyp_submission_status'];
                    $statusClass = 'status-' . str_replace(' ', '', $status);
                    $tagClass = 'tag-' . str_replace(' ', '', $status);
                    
                    // Logic to show form vs submitted view
                    $canSubmit = ($status == 'Viewed' || $status == 'Not Turned In' || $status == 'Need Revision');
                    $isSubmitted = ($status == 'Turned In' || $status == 'Late Turned In' || $status == 'Resubmitted' || $status == 'Graded');
                    $isGraded = ($status == 'Graded');
                ?>
                <div class="submission-card <?php echo $statusClass; ?>">
                    <div class="section-h">Your Work</div>
                    
                    <span class="status-label">
                        Status: <span class="status-tag <?php echo $tagClass; ?>"><?php echo $status; ?></span>
                    </span>

                    <!-- Feedback Display -->
                    <?php if (!empty($submission['fyp_feedback'])): ?>
                        <div class="feedback-box">
                            <strong><i class="fa fa-comment-dots"></i> Feedback:</strong><br>
                            <?php echo htmlspecialchars($submission['fyp_feedback']); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Marks Display -->
                    <?php if ($isGraded && $submission['fyp_marks'] !== null): ?>
                        <div class="marks-box">
                            <?php echo $submission['fyp_marks']; ?> / 100
                        </div>
                    <?php endif; ?>

                    <!-- View Submitted File -->
                    <?php if ($isSubmitted && !empty($submission['fyp_submitted_file'])): ?>
                        <a href="<?php echo $submission['fyp_submitted_file']; ?>" target="_blank" class="submitted-file">
                            <i class="fa fa-file-pdf fa-lg"></i> 
                            <?php echo basename($submission['fyp_submitted_file']); ?>
                        </a>
                        <div style="font-size:12px; color:#888; text-align:right; margin-bottom:15px;">
                            Submitted on: <?php echo $submission['fyp_submission_date']; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Submission Form -->
                    <?php if ($canSubmit): ?>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="upload-area">
                                <i class="fa fa-cloud-upload-alt" style="font-size:32px; color:#ccc; margin-bottom:10px;"></i>
                                <input type="file" name="file_upload" class="file-input" required>
                                <div style="font-size:12px; color:#888;">Supported: PDF, DOCX, ZIP</div>
                            </div>
                            <button type="submit" name="submit_assignment" class="btn-submit">
                                <?php echo ($status == 'Need Revision') ? 'Resubmit' : 'Turn In'; ?>
                            </button>
                        </form>
                    <?php endif; ?>

                    <!-- Undo Button -->
                    <?php if ($isSubmitted && !$isGraded): ?>
                        <form method="POST" onsubmit="return confirm('Are you sure you want to unsubmit?');">
                            <button type="submit" name="undo_submission" class="btn-undo">
                                <i class="fa fa-undo"></i> Undo Turn In
                            </button>
                        </form>
                    <?php endif; ?>
                    
                </div>
            </div>

        </main>
    </div>
</body>
</html>