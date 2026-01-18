<?php
include("connect.php");

$auth_user_id = $_GET['auth_user_id'] ?? null;
$assign_id = $_GET['id'] ?? null;

if (!$auth_user_id || !$assign_id) { 
    if(isset($_POST['ajax'])) { echo json_encode(['success'=>false, 'message'=>'Invalid access']); exit; }
    header("location: login.php"); exit; 
}

$stud_data = [];
$current_stud_id = '';
$user_name = 'Student';
$user_avatar = '';

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

$submission = null;
$sql_sub = "SELECT * FROM assignment_submission WHERE fyp_assignmentid = ? AND fyp_studid = ?";
if ($stmt = $conn->prepare($sql_sub)) {
    $stmt->bind_param("is", $assign_id, $current_stud_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $submission = $res->fetch_assoc();
    $stmt->close();
}

if (!$submission) {
    $stmt = $conn->prepare("INSERT INTO assignment_submission (fyp_assignmentid, fyp_studid, fyp_submission_status) VALUES (?, ?, 'Viewed')");
    $stmt->bind_param("is", $assign_id, $current_stud_id);
    $stmt->execute();
    $stmt->close();
    
    $submission = ['fyp_submission_status' => 'Viewed', 'fyp_marks' => null, 'fyp_feedback' => null, 'fyp_submitted_file' => null, 'fyp_submission_date' => null, 'fyp_submissionid' => $conn->insert_id];
} elseif ($submission['fyp_submission_status'] == 'Not Turned In') {
    $stmt = $conn->prepare("UPDATE assignment_submission SET fyp_submission_status = 'Viewed' WHERE fyp_submissionid = ?");
    $stmt->bind_param("i", $submission['fyp_submissionid']);
    $stmt->execute();
    $stmt->close();
    
    $submission['fyp_submission_status'] = 'Viewed';
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_assignment'])) {
    $response = ['success' => false, 'message' => 'Unknown error'];

    if (isset($_FILES['file_upload']) && $_FILES['file_upload']['error'] == 0) {
        
        $maxFileSize = 20 * 1024 * 1024; 
        if ($_FILES['file_upload']['size'] > $maxFileSize) {
            $fileSizeMB = round($_FILES['file_upload']['size'] / 1024 / 1024, 2);
            $response = ['success' => false, 'message' => "File too large ({$fileSizeMB}MB). Max allowed: 20MB"];
        } else {
            $allowedExtensions = ['pdf', 'doc', 'docx', 'zip', 'rar'];
            $fileExtension = strtolower(pathinfo($_FILES['file_upload']['name'], PATHINFO_EXTENSION));
            
            if (!in_array($fileExtension, $allowedExtensions)) {
                $response = ['success' => false, 'message' => "Invalid file type. Allowed: PDF, DOC, DOCX, ZIP, RAR"];
            } else {
                $originalName = basename($_FILES['file_upload']['name']);
                $safeName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $originalName);
                $uniqueName = time() . "_" . uniqid() . "_" . $safeName;
                
                $upload_dir = "uploads/assignments/";
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                
                $target_file = $upload_dir . $uniqueName;
                
                if (move_uploaded_file($_FILES['file_upload']['tmp_name'], $target_file)) {
                    
                    chmod($target_file, 0644);
                    
                    $date_now = date('Y-m-d H:i:s');
                    $new_status = 'Turned In';
                    
                    if ($date_now > $assignment['fyp_deadline']) {
                        $new_status = 'Late Turned In';
                    }
                    
                    if ($submission['fyp_submission_status'] == 'Need Revision') {
                        $new_status = 'Resubmitted';
                    }

                    $sql_upd = "UPDATE assignment_submission 
                                SET fyp_submitted_file = ?, fyp_submission_status = ?, fyp_submission_date = ? 
                                WHERE fyp_assignmentid = ? AND fyp_studid = ?";
                    if ($stmt = $conn->prepare($sql_upd)) {
                        $stmt->bind_param("sssis", $target_file, $new_status, $date_now, $assign_id, $current_stud_id);
                        if ($stmt->execute()) {
                            $response = ['success' => true, 'message' => 'Assignment submitted successfully!'];
                        } else {
                            $response = ['success' => false, 'message' => 'Database update failed.'];
                        }
                        $stmt->close();
                    }
                } else {
                    $response = ['success' => false, 'message' => 'Failed to move uploaded file. Check permissions.'];
                }
            }
        }
    } else {
        $errorMessage = 'Please select a file.';
        if (isset($_FILES['file_upload']['error'])) {
            switch ($_FILES['file_upload']['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE: $errorMessage = 'File too large.'; break;
                case UPLOAD_ERR_PARTIAL: $errorMessage = 'Upload incomplete.'; break;
                case UPLOAD_ERR_NO_FILE: $errorMessage = 'No file selected.'; break;
                default: $errorMessage = 'Upload error.';
            }
        }
        $response = ['success' => false, 'message' => $errorMessage];
    }

    if(isset($_POST['ajax'])) {
        echo json_encode($response);
        exit;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['undo_submission'])) {
    $current_status = $submission['fyp_submission_status'];
    $response = ['success' => false, 'message' => 'Unknown error'];

    if ($current_status != 'Graded') {
        $sql_undo = "UPDATE assignment_submission 
                     SET fyp_submitted_file = NULL, fyp_submission_status = 'Viewed', fyp_submission_date = NULL 
                     WHERE fyp_assignmentid = ? AND fyp_studid = ?";
        if ($stmt = $conn->prepare($sql_undo)) {
            $stmt->bind_param("is", $assign_id, $current_stud_id);
            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'Submission withdrawn.'];
            } else {
                $response = ['success' => false, 'message' => 'Database error during undo.'];
            }
            $stmt->close();
        }
    } else {
        $response = ['success' => false, 'message' => 'Cannot undo graded assignment.'];
    }

    if(isset($_POST['ajax'])) {
        echo json_encode($response);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignment Details</title>
    <link rel="icon" type="image/png" href="image/ladybug.png?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap');
        
        :root {
            --primary-color: #0056b3;
            --primary-hover: #004494;
            --bg-color: #f4f6f9;
            --card-bg: #ffffff;
            --text-color: #333;
            --text-secondary: #666;
            --sidebar-bg: #004085;
            --sidebar-hover: #003366;
            --sidebar-text: #e0e0e0;
            --card-shadow: 0 4px 6px rgba(0,0,0,0.05);
            --header-bg: #ffffff;
            --border-color: #e0e0e0;
            --slot-bg: #f8f9fa;
        }

        .dark-mode {
            --primary-color: #4da3ff;
            --primary-hover: #0069d9;
            --bg-color: #121212;
            --card-bg: #1e1e1e;
            --text-color: #e0e0e0;
            --text-secondary: #a0a0a0;
            --sidebar-bg: #0d1117;
            --sidebar-hover: #161b22;
            --sidebar-text: #c9d1d9;
            --card-shadow: 0 4px 6px rgba(0,0,0,0.3);
            --header-bg: #1e1e1e;
            --border-color: #333;
            --slot-bg: #2d2d2d;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            background-color: var(--bg-color);
            color: var(--text-color);
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
            transition: background-color 0.3s, color 0.3s;
        }

        .main-menu {
            background: var(--sidebar-bg);
            border-right: 1px solid rgba(255,255,255,0.1);
            position: fixed;
            top: 0;
            bottom: 0;
            height: 100%;
            left: 0;
            width: 60px;
            overflow: hidden;
            transition: width .05s linear;
            z-index: 1000;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }

        .main-menu:hover, nav.main-menu.expanded {
            width: 250px;
            overflow: visible;
        }

        .main-menu > ul {
            margin: 7px 0;
            padding: 0;
            list-style: none;
        }

        .main-menu li {
            position: relative;
            display: block;
            width: 250px;
        }

        .main-menu li > a {
            position: relative;
            display: table;
            border-collapse: collapse;
            border-spacing: 0;
            color: var(--sidebar-text);
            font-size: 14px;
            text-decoration: none;
            transition: all .1s linear;
            width: 100%;
        }

        .main-menu .nav-icon {
            position: relative;
            display: table-cell;
            width: 60px;
            height: 46px; 
            text-align: center;
            vertical-align: middle;
            font-size: 18px;
        }

        .main-menu .nav-text {
            position: relative;
            display: table-cell;
            vertical-align: middle;
            width: 190px;
            padding-left: 10px;
            white-space: nowrap;
        }

        .main-menu li:hover > a, nav.main-menu li.active > a {
            color: #fff;
            background-color: var(--sidebar-hover);
            border-left: 4px solid #fff; 
        }

        .main-menu > ul.logout {
            position: absolute;
            left: 0;
            bottom: 0;
            width: 100%;
        }
        
        .main-content-wrapper {
            margin-left: 60px;
            flex: 1;
            padding: 20px;
            width: calc(100% - 60px);
            transition: margin-left .05s linear;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            background: var(--header-bg);
            padding: 20px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            transition: background 0.3s;
        }
        
        .welcome-text h1 {
            margin: 0;
            font-size: 24px;
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .welcome-text p {
            margin: 5px 0 0;
            color: var(--text-secondary);
            font-size: 14px;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .logo-img {
            height: 40px;
            width: auto;
            background: white;
            padding: 2px;
            border-radius: 6px;
        }
        .system-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--primary-color);
            letter-spacing: 0.5px;
        }

        .user-section {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .user-badge {
            font-size: 13px; color: var(--text-secondary); background: var(--slot-bg);
            padding: 5px 10px; border-radius: 20px;
        }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
        .user-avatar-placeholder {
            width: 40px; height: 40px; border-radius: 50%; background: #0056b3;
            color: white; display: flex; align-items: center; justify-content: center; font-weight: bold;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: var(--card-bg);
            color: var(--primary-color);
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            box-shadow: var(--card-shadow);
            transition: all 0.2s;
            margin-bottom: 20px;
            border: 2px solid var(--primary-color);
        }

        .back-btn:hover {
            background: var(--primary-color);
            color: white;
            transform: translateX(-3px);
        }

        .header-card {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
            padding: 30px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            color: white;
            margin-bottom: 25px;
        }

        .ass-title {
            font-size: 28px;
            margin: 0 0 15px 0;
            font-weight: 600;
        }

        .ass-meta {
            display: flex;
            gap: 20px;
            font-size: 14px;
            flex-wrap: wrap;
        }

        .ass-badge {
            padding: 6px 14px;
            background: rgba(255,255,255,0.2);
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            backdrop-filter: blur(10px);
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 20px;
        }

        .desc-card, .submission-card {
            background: var(--card-bg);
            padding: 30px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
        }

        .section-h {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--text-color);
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border-color);
        }

        .desc-text {
            color: var(--text-secondary);
            line-height: 1.8;
            white-space: pre-wrap;
            font-size: 15px;
        }

        .status-Viewed { border-top: 4px solid #ffc107; }
        .status-TurnedIn { border-top: 4px solid #28a745; }
        .status-LateTurnedIn { border-top: 4px solid #ff9800; }
        .status-Resubmitted { border-top: 4px solid #28a745; }
        .status-Graded { border-top: 4px solid #17a2b8; }
        .status-NeedRevision { border-top: 4px solid #fd7e14; }

        .status-label {
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 20px;
            display: block;
            color: var(--text-secondary);
        }

        .status-tag {
            padding: 8px 16px;
            border-radius: 20px;
            color: white;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .tag-Viewed { background: #ffc107; color: #333; }
        .tag-TurnedIn { background: #28a745; }
        .tag-LateTurnedIn { background: #ff9800; color: white; }
        .tag-Resubmitted { background: #28a745; }
        .tag-Graded { background: #17a2b8; }
        .tag-NeedRevision { background: #fd7e14; }

        .upload-area {
            border: 3px dashed var(--border-color);
            padding: 40px 20px;
            text-align: center;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--slot-bg) 0%, var(--card-bg) 100%);
            margin-bottom: 20px;
            transition: all 0.3s;
            cursor: pointer;
            position: relative;
        }

        .upload-area:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }

        .upload-area i {
            font-size: 48px;
            color: var(--primary-color);
            margin-bottom: 15px;
            display: block;
        }

        .file-input-wrapper {
            position: relative;
            margin-bottom: 15px;
        }

        .file-input {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .file-input-label {
            display: inline-block;
            padding: 12px 30px;
            background: var(--primary-color);
            color: white;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            font-size: 14px;
        }

        .file-input-label:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 86, 179, 0.3);
        }

        .file-name-display {
            margin-top: 15px;
            font-size: 13px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .btn-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.3);
        }

        .btn-undo {
            width: 100%;
            padding: 12px;
            background: transparent;
            color: #dc3545;
            border: 2px solid #dc3545;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-undo:hover {
            background: #dc3545;
            color: white;
            transform: translateY(-2px);
        }

        .submitted-file {
            background: var(--slot-bg);
            padding: 15px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            color: var(--primary-color);
            text-decoration: none;
            margin-bottom: 15px;
            border: 2px solid var(--border-color);
            transition: all 0.3s;
        }

        .submitted-file:hover {
            transform: translateX(5px);
        }

        .submitted-file i {
            font-size: 24px;
        }

        .feedback-box {
            background: linear-gradient(135deg, #fff3cd 0%, #ffe69c 100%);
            border: 2px solid #ffc107;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #856404;
            line-height: 1.6;
        }

        .feedback-box strong {
            display: block;
            margin-bottom: 10px;
            font-size: 15px;
        }

        .marks-box {
            font-size: 48px;
            font-weight: 700;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 15px;
            text-align: center;
        }

        .theme-toggle {
            cursor: pointer; padding: 8px; border-radius: 50%;
            background: var(--slot-bg); border: 1px solid var(--border-color);
            color: var(--text-color); display: flex; align-items: center;
            justify-content: center; width: 35px; height: 35px; margin-right: 15px;
        }
        .theme-toggle img { width: 20px; height: 20px; object-fit: contain; }

        @media (max-width: 900px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .page-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
        }
    </style>
</head>
<body>

    <nav class="main-menu">
        <ul>
            <li>
                <a href="Student_mainpage.php?page=dashboard&auth_user_id=<?php echo $auth_user_id; ?>">
                    <i class="fa fa-home nav-icon"></i>
                    <span class="nav-text">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="std_profile.php?auth_user_id=<?php echo $auth_user_id; ?>">
                    <i class="fa fa-user nav-icon"></i>
                    <span class="nav-text">My Profile</span>
                </a>
            </li>
            <li>
                <a href="std_projectreg.php?auth_user_id=<?php echo $auth_user_id; ?>">
                    <i class="fa fa-users nav-icon"></i>
                    <span class="nav-text">Project Registration</span>
                </a>
            </li>
            <li>
                <a href="std_request_status.php?auth_user_id=<?php echo $auth_user_id; ?>">
                    <i class="fa fa-tasks nav-icon"></i>
                    <span class="nav-text">Request Status</span>
                </a>
            </li>
            <li class="active">
                <a href="student_assignment.php?auth_user_id=<?php echo $auth_user_id; ?>">
                    <i class="fa fa-file-text nav-icon"></i>
                    <span class="nav-text">Assignments</span>
                </a>
            </li>
            <li>
                <a href="Student_mainpage.php?page=doc_submission&auth_user_id=<?php echo $auth_user_id; ?>">
                    <i class="fa fa-cloud-upload nav-icon"></i>
                    <span class="nav-text">Document Upload</span>
                </a>
            </li>
            <li>
                <a href="student_appointment_meeting.php?auth_user_id=<?php echo $auth_user_id; ?>">
                    <i class="fa fa-calendar nav-icon"></i>
                    <span class="nav-text">Book Appointment</span>
                </a>
            </li>
            <li>
                <a href="Student_view_presentation.php?auth_user_id=<?php echo $auth_user_id; ?>">
                    <i class="fa fa-desktop nav-icon"></i>
                    <span class="nav-text">Presentation</span>
                </a>
            </li>
            <li>
                <a href="Student_view_result.php?auth_user_id=<?php echo $auth_user_id; ?>">
                    <i class="fa fa-star nav-icon"></i>
                    <span class="nav-text">View Results</span>
                </a>
            </li>
        </ul>

        <ul class="logout">
            <li>
                <a href="login.php">
                    <i class="fa fa-power-off nav-icon"></i>
                    <span class="nav-text">Logout</span>
                </a>
            </li>  
        </ul>
    </nav>

    <div class="main-content-wrapper">
        
        <div class="page-header">
            <div class="welcome-text">
                <h1>Assignment Details</h1>
                <p>Welcome back, <?php echo htmlspecialchars($user_name); ?></p>
            </div>
            
            <div class="logo-section">
                <img src="image/ladybug.png?v=<?php echo time(); ?>" alt="Logo" class="logo-img">
                <span class="system-title">FYP Portal</span>
            </div>

            <div class="user-section">
                <button class="theme-toggle" onclick="toggleDarkMode()" title="Toggle Dark Mode">
                    <img id="theme-icon" src="image/moon-solid-full.svg" alt="Toggle Theme">
                </button>
                <span class="user-badge">Student</span>
                <?php if(!empty($user_avatar) && $user_avatar != 'image/user.png'): ?>
                    <img src="<?php echo htmlspecialchars($user_avatar); ?>" class="user-avatar" alt="User Avatar">
                <?php else: ?>
                    <div class="user-avatar-placeholder">
                        <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <a href="student_assignment.php?auth_user_id=<?php echo $auth_user_id; ?>" class="back-btn">
            <i class="fa fa-arrow-left"></i> Back to Assignments
        </a>

        <div class="header-card">
            <h1 class="ass-title"><?php echo htmlspecialchars($assignment['fyp_title']); ?></h1>
            <div class="ass-meta">
                <span class="ass-badge"><?php echo htmlspecialchars($assignment['fyp_assignment_type']); ?></span>
                <div class="meta-item">
                    <i class="fa fa-calendar-alt"></i>
                    <span>Due: <?php echo date('M d, Y h:i A', strtotime($assignment['fyp_deadline'])); ?></span>
                </div>
            </div>
        </div>

        <div class="content-grid">
            <div class="desc-card">
                <div class="section-h"><i class="fa fa-info-circle"></i> Instructions</div>
                <div class="desc-text"><?php echo nl2br(htmlspecialchars($assignment['fyp_description'])); ?></div>
            </div>

            <?php 
                $status = $submission['fyp_submission_status'];
                $statusClass = 'status-' . str_replace(' ', '', $status);
                $tagClass = 'tag-' . str_replace(' ', '', $status);
                
                $canSubmit = ($status == 'Viewed' || $status == 'Not Turned In' || $status == 'Need Revision');
                $isSubmitted = ($status == 'Turned In' || $status == 'Late Turned In' || $status == 'Resubmitted' || $status == 'Graded');
                $isGraded = ($status == 'Graded');
            ?>
            <div class="submission-card <?php echo $statusClass; ?>">
                <div class="section-h"><i class="fa fa-upload"></i> Your Work</div>
                
                <span class="status-label">
                    Status: <span class="status-tag <?php echo $tagClass; ?>">
                        <i class="fa fa-circle"></i>
                        <?php echo $status; ?>
                    </span>
                </span>

                <?php if (!empty($submission['fyp_feedback'])): ?>
                    <div class="feedback-box">
                        <strong><i class="fa fa-comment-dots"></i> Teacher's Feedback:</strong>
                        <?php echo nl2br(htmlspecialchars($submission['fyp_feedback'])); ?>
                    </div>
                <?php endif; ?>

                <?php if ($isGraded && $submission['fyp_marks'] !== null): ?>
                    <div class="marks-box">
                        <?php echo $submission['fyp_marks']; ?> / 100
                    </div>
                    <div style="text-align:center; color:var(--text-secondary); font-size:14px; margin-bottom:20px;">Your Grade</div>
                <?php endif; ?>

                <?php if ($isSubmitted && !empty($submission['fyp_submitted_file'])): ?>
                    <a href="<?php echo $submission['fyp_submitted_file']; ?>" target="_blank" class="submitted-file">
                        <i class="fa fa-file-pdf"></i>
                        <div style="flex:1;">
                            <div style="font-weight:600;"><?php echo basename($submission['fyp_submitted_file']); ?></div>
                            <div style="font-size:11px; opacity:0.8;">Submitted on: <?php echo date('M d, Y h:i A', strtotime($submission['fyp_submission_date'])); ?></div>
                        </div>
                        <i class="fa fa-external-link-alt"></i>
                    </a>
                <?php endif; ?>

                <?php if ($canSubmit): ?>
                    <form id="submissionForm" enctype="multipart/form-data">
                        <input type="hidden" name="submit_assignment" value="1">
                        <input type="hidden" name="ajax" value="1">
                        
                        <div class="upload-area" onclick="document.getElementById('fileInput').click()">
                            <i class="fa fa-cloud-upload-alt"></i>
                            <div class="file-input-wrapper">
                                <input type="file" name="file_upload" id="fileInput" class="file-input" required onchange="displayFileName()">
                                <label for="fileInput" class="file-input-label">
                                    <i class="fa fa-folder-open"></i> Choose File
                                </label>
                            </div>
                            <div id="fileNameDisplay" class="file-name-display">No file selected</div>
                            <div style="font-size:12px; color:var(--text-secondary); margin-top:10px;">
                                <i class="fa fa-shield-alt"></i> Supported: PDF, DOC, DOCX, ZIP, RAR • Max: 20MB
                            </div>
                        </div>
                        <button type="submit" class="btn-submit" id="submitBtn">
                            <i class="fa fa-paper-plane"></i>
                            <?php echo ($status == 'Need Revision') ? 'Resubmit Assignment' : 'Turn In Assignment'; ?>
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ($isSubmitted && !$isGraded): ?>
                    <form id="undoForm">
                        <input type="hidden" name="undo_submission" value="1">
                        <input type="hidden" name="ajax" value="1">
                        <button type="button" class="btn-undo" onclick="confirmUndo()">
                            <i class="fa fa-undo"></i> Undo Submission
                        </button>
                    </form>
                <?php endif; ?>
                
            </div>
        </div>

    </div>

    <script>
        function displayFileName() {
            const input = document.getElementById('fileInput');
            const display = document.getElementById('fileNameDisplay');
            
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const fileName = file.name;
                const fileSize = (file.size / 1024 / 1024).toFixed(2); 
                const maxSize = 20; 
                
                if (file.size > maxSize * 1024 * 1024) {
                    display.innerHTML = `<i class="fa fa-exclamation-triangle" style="color:#dc3545;"></i> File too large! (${fileSize} MB > ${maxSize} MB)`;
                    display.style.color = '#dc3545';
                    display.style.fontWeight = '600';
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'File Too Large!',
                        text: `Your file is ${fileSize}MB. Maximum allowed size is ${maxSize}MB.`,
                        confirmButtonColor: '#d93025'
                    });
                    
                    input.value = ''; 
                } else {
                    display.innerHTML = `<i class="fa fa-check-circle" style="color:#28a745;"></i> ${fileName} (${fileSize} MB)`;
                    display.style.color = '#28a745';
                    display.style.fontWeight = '600';
                }
            } else {
                display.innerHTML = 'No file selected';
                display.style.color = '#666';
                display.style.fontWeight = 'normal';
            }
        }

        const submissionForm = document.getElementById('submissionForm');
        if(submissionForm) {
            submissionForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const fileInput = document.getElementById('fileInput');
                if (!fileInput.files || !fileInput.files[0]) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'No File Selected',
                        text: 'Please select a file to upload!',
                        confirmButtonColor: '#ffc107'
                    });
                    return;
                }

                const btn = document.getElementById('submitBtn');
                const originalText = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Uploading...';

                const formData = new FormData(this);

                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: data.message,
                            confirmButtonColor: '#28a745'
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Upload Failed',
                            text: data.message,
                            confirmButtonColor: '#d93025'
                        });
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'An unexpected error occurred.',
                        confirmButtonColor: '#d93025'
                    });
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                });
            });
        }

        function confirmUndo() {
            Swal.fire({
                title: "Are you sure?",
                text: "You won't be able to revert this!",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                confirmButtonText: "Yes, delete it!"
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.getElementById('undoForm');
                    const formData = new FormData(form);

                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: "Deleted!",
                                text: data.message,
                                icon: "success"
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Failed',
                                text: data.message
                            });
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'An unexpected error occurred.'
                        });
                    });
                }
            });
        }

        function toggleDarkMode() {
            document.body.classList.toggle('dark-mode');
            const isDark = document.body.classList.contains('dark-mode');
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
            
            const iconImg = document.getElementById('theme-icon');
            if (isDark) {
                iconImg.src = 'image/sun-solid-full.svg'; 
            } else {
                iconImg.src = 'image/moon-solid-full.svg'; 
            }
        }

        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            document.body.classList.add('dark-mode');
            const iconImg = document.getElementById('theme-icon');
            if(iconImg) {
                iconImg.src = 'image/sun-solid-full.svg'; 
            }
        }
    </script>
</body>
</html>