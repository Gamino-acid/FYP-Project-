<?php
include("connect.php");

$auth_user_id = $_GET['auth_user_id'] ?? null;
if (!$auth_user_id) {
    header("location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $contact = $_POST['contact'];
    $sql_update = "UPDATE STUDENT SET fyp_contactno = ? WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_update)) {
        $stmt->bind_param("si", $contact, $auth_user_id);
        $stmt->execute();
        $stmt->close();
    }

    // Handle profile image upload
    if (!empty($_FILES["profile_img"]["name"]) && $_FILES["profile_img"]["error"] === 0) {
        $tmp_name = $_FILES["profile_img"]["tmp_name"];
        
        // Verify it's an actual image
        $image_info = getimagesize($tmp_name);
        if ($image_info !== false) {
            $image_content = file_get_contents($tmp_name);
            $file_ext = pathinfo($_FILES["profile_img"]["name"], PATHINFO_EXTENSION);
            
            // Create base64 encoded image with proper data URI scheme
            $base64_image = 'data:image/' . $file_ext . ';base64,' . base64_encode($image_content);
            
            $sql_img = "UPDATE STUDENT SET fyp_profileimg = ? WHERE fyp_userid = ?";
            if ($stmt_img = $conn->prepare($sql_img)) {
                $stmt_img->bind_param("si", $base64_image, $auth_user_id);
                $stmt_img->execute();
                $stmt_img->close();
            }
            
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully!', 'image' => $base64_image]);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid file format. Please upload an image (JPG, PNG, GIF).']);
            exit;
        }
    }
    
    echo json_encode(['success' => true, 'message' => 'Profile updated successfully!']);
    exit;
}

$stud_data = [];
$user_name = 'Student';
$current_stud_id = '';

if (isset($conn)) {
    $sql_user = "SELECT fyp_username FROM `USER` WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_user)) { 
        $stmt->bind_param("i", $auth_user_id); 
        $stmt->execute(); 
        $res=$stmt->get_result(); 
        if($row=$res->fetch_assoc()) $user_name=$row['fyp_username']; 
        $stmt->close(); 
    }
    
    $sql_stud = "SELECT * FROM STUDENT WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_stud)) { 
        $stmt->bind_param("i", $auth_user_id); 
        $stmt->execute(); 
        $res=$stmt->get_result(); 
        if($res->num_rows > 0) { 
            $stud_data = $res->fetch_assoc(); 
            $current_stud_id = $stud_data['fyp_studid'];
            if(!empty($stud_data['fyp_studname'])) $user_name=$stud_data['fyp_studname']; 
        } else { 
            $stud_data=['fyp_studid'=>'', 'fyp_studname'=>$user_name, 'fyp_studfullid'=>'', 'fyp_projectid'=>null, 'fyp_profileimg'=>'', 'fyp_contactno'=>'', 'fyp_email'=>'', 'fyp_tutgroup'=>'', 'fyp_academicid'=>'', 'fyp_progid'=>'', 'fyp_group'=>'Individual']; 
        } 
        $stmt->close(); 
    }
}

$my_group = null; 
$my_role = 'Individual';    
$my_group_name = 'No Team';

if ($current_stud_id) {
    $sql_leader = "SELECT * FROM student_group WHERE leader_id = ?";
    if ($stmt = $conn->prepare($sql_leader)) {
        $stmt->bind_param("s", $current_stud_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $my_group = $row;
            $my_role = 'Leader';
            $my_group_name = $row['group_name'];
        }
    }

    if (!$my_group) {
        $sql_member = "SELECT g.* FROM group_request gr 
                       JOIN student_group g ON gr.group_id = g.group_id 
                       WHERE gr.invitee_id = ? AND gr.request_status = 'Accepted'";
        if ($stmt = $conn->prepare($sql_member)) {
            $stmt->bind_param("s", $current_stud_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $my_group = $row;
                $my_role = 'Member';
                $my_group_name = $row['group_name'];
            }
        }
    }
}

$my_project_status = 'Not Applied';
$my_project_title = 'None';
$status_color_class = 'badge-secondary';

if ($current_stud_id) {
    $applicant_id = $current_stud_id;
    if ($my_group && $my_role == 'Member') {
        $applicant_id = $my_group['leader_id'];
    }

    $req_sql = "SELECT pr.fyp_requeststatus, p.fyp_projecttitle 
                FROM project_request pr 
                LEFT JOIN project p ON pr.fyp_projectid = p.fyp_projectid 
                WHERE pr.fyp_studid = ? 
                ORDER BY pr.fyp_datecreated DESC LIMIT 1";

    if ($stmt = $conn->prepare($req_sql)) {
        $stmt->bind_param("s", $applicant_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $my_project_status = $row['fyp_requeststatus'];
            $my_project_title = $row['fyp_projecttitle'];
            
            if($my_project_status == 'Approve') $status_color_class = 'badge-success';
            else if($my_project_status == 'Reject') $status_color_class = 'badge-danger';
            else if($my_project_status == 'Pending') $status_color_class = 'badge-warning';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard & Profile</title>
    <link rel="icon" type="image/png" href="image/ladybug.png?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
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
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f4f6f9;
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
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
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
        }
        
        .welcome-text h1 {
            margin: 0;
            font-size: 24px;
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .welcome-text p {
            margin: 5px 0 0;
            color: #666;
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
            font-size: 13px;
            color: #666;
            background: #f0f0f0;
            padding: 5px 10px;
            border-radius: 20px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .user-avatar-placeholder {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #0056b3;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--card-shadow);
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
            border-left: 4px solid transparent;
        }

        .stat-card.group-card {
            border-left-color: #6610f2;
        }

        .stat-card.project-card {
            border-left-color: #28a745;
        }
        
        .stat-title {
            font-size: 13px;
            color: #888;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .stat-value {
            font-size: 18px;
            color: #333;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-sub {
            font-size: 13px;
            color: #666;
        }

        .stat-icon {
            position: absolute;
            right: 20px;
            top: 20px;
            font-size: 32px;
            opacity: 0.1;
            color: #000;
        }
        
        .badge {
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }

        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-secondary {
            background: #e2e3e5;
            color: #383d41;
        }

        .alert-box {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }

        .alert-info {
            background-color: #cce5ff;
            color: #004085;
            border: 1px solid #b8daff;
        }

        .alert-btn {
            margin-left: auto;
            background: rgba(0,0,0,0.1);
            border: none;
            padding: 5px 12px;
            border-radius: 4px;
            color: inherit;
            font-weight: 600;
            text-decoration: none;
            font-size: 13px;
            cursor: pointer;
        }

        .alert-btn:hover {
            background: rgba(0,0,0,0.2);
        }

        .profile-container {
            display: flex;
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            gap: 30px;
        }

        .left-column {
            width: 250px;
            display: flex;
            flex-direction: column;
            align-items: center;
            border-right: 1px solid #eee;
            padding-right: 30px;
        }

        .right-column {
            flex: 1;
        }

        .profile-img-box {
            width: 160px;
            height: 160px;
            border-radius: 50%;
            background-color: #e0e0e0;
            overflow: hidden;
            margin-bottom: 15px;
            border: 4px solid #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .profile-img-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-img-placeholder {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 50px;
        }

        .student-id-display {
            font-size: 1.2em;
            font-weight: bold;
            color: #333;
            margin-top: 10px;
            text-align: center;
        }

        .upload-photo-btn {
            width: 100%;
            padding: 10px 20px;
            background: linear-gradient(135deg, var(--primary-color), #0073e6);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9em;
            font-weight: 500;
            font-family: inherit;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 86, 179, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 15px;
        }

        .upload-photo-btn:hover {
            background: linear-gradient(135deg, var(--primary-hover), #005bb5);
            box-shadow: 0 4px 12px rgba(0, 86, 179, 0.3);
            transform: translateY(-2px);
        }

        .upload-photo-btn:active {
            transform: translateY(0);
        }

        .upload-photo-btn i {
            font-size: 1.1em;
        }

        .form-section-title {
            color: #555;
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 5px;
            margin-bottom: 20px;
            font-size: 1.1em;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #444;
            font-size: 0.9em;
        }

        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="number"],
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 0.95em;
            font-family: inherit;
        }

        input[readonly],
        select[disabled] {
            background-color: #e9ecef;
            cursor: not-allowed;
            color: #6c757d;
            border-color: #ced4da;
        }

        .row-group {
            display: flex;
            gap: 20px;
        }

        .col-group {
            flex: 1;
        }

        .save-btn {
            margin-top: 25px;
            padding: 12px 25px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1em;
            transition: background 0.3s;
            font-family: inherit;
            font-weight: 500;
        }

        .save-btn:hover {
            background-color: var(--primary-hover);
        }

        .save-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: none;
            align-items: center;
            gap: 10px;
            z-index: 2000;
            animation: slideIn 0.3s ease;
        }

        .toast.show {
            display: flex;
        }

        .toast.success {
            border-left: 4px solid #28a745;
        }

        .toast.error {
            border-left: 4px solid #dc3545;
        }

        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @media (max-width: 900px) {
            .profile-container {
                flex-direction: column;
            }
            
            .left-column {
                width: 100%;
                border-right: none;
                border-bottom: 1px solid #eee;
                padding-bottom: 20px;
                margin-bottom: 20px;
                padding-right: 0;
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
            <li class="active">
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
            <li>
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
                <a href="Student_mainpage.php?page=presentation&auth_user_id=<?php echo $auth_user_id; ?>">
                    <i class="fa fa-desktop nav-icon"></i>
                    <span class="nav-text">Presentation</span>
                </a>
            </li>
            <li>
                <a href="Student_mainpage.php?page=grades&auth_user_id=<?php echo $auth_user_id; ?>">
                    <i class="fa fa-star nav-icon"></i>
                    <span class="nav-text">My Grades</span>
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
                <h1>Dashboard & Profile</h1>
                <p>Welcome back, <?php echo htmlspecialchars($user_name); ?></p>
            </div>
            
            <div class="logo-section">
                <img src="image/ladybug.png?v=<?php echo time(); ?>" alt="Logo" class="logo-img">
                <span class="system-title">FYP Portal</span>
            </div>

            <div class="user-section">
                <span class="user-badge">Student</span>
                <?php if(!empty($stud_data['fyp_profileimg'])): ?>
                    <img src="<?php echo htmlspecialchars($stud_data['fyp_profileimg']); ?>" class="user-avatar" alt="User Avatar">
                <?php else: ?>
                    <div class="user-avatar-placeholder">
                        <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="stat-card group-card">
                <div class="stat-title">Team Status</div>
                <div class="stat-value">
                    <?php echo htmlspecialchars($my_group_name); ?>
                </div>
                <div class="stat-sub">
                    Role: <?php echo htmlspecialchars($my_role); ?>
                </div>
                <i class="fa fa-users stat-icon"></i>
            </div>

            <div class="stat-card project-card">
                <div class="stat-title">Project Application</div>
                <div class="stat-value" style="font-size: 16px; margin-bottom:8px;">
                    <?php echo htmlspecialchars(strlen($my_project_title) > 25 ? substr($my_project_title,0,25).'...' : $my_project_title); ?>
                </div>
                <div>
                    <span class="badge <?php echo $status_color_class; ?>"><?php echo $my_project_status; ?></span>
                </div>
                <i class="fa fa-file-contract stat-icon"></i>
            </div>
        </div>

        <?php if ($my_role == 'Individual'): ?>
            <div class="alert-box alert-info">
                <i class="fa fa-info-circle fa-lg"></i>
                <div>
                    <strong>You are currently working individually.</strong>
                    <br>Prefer team work? You can create or join a group.
                </div>
                <a href="std_request_status.php?auth_user_id=<?php echo $auth_user_id; ?>" class="alert-btn">Manage Team</a>
            </div>
        <?php endif; ?>

        <?php if ($my_project_status == 'Not Applied'): ?>
            <div class="alert-box alert-warning">
                <i class="fa fa-exclamation-triangle fa-lg"></i>
                <div>
                    <strong>Action Required: You haven't applied for a project yet!</strong>
                    <br>Please browse the available projects and submit your proposal.
                </div>
                <a href="std_projectreg.php?auth_user_id=<?php echo $auth_user_id; ?>" class="alert-btn">Apply Now</a>
            </div>
        <?php endif; ?>

        <form id="profileForm" enctype="multipart/form-data">
            <div class="profile-container">
                <div class="left-column">
                    <div class="profile-img-box" id="profileImgBox">
                        <?php 
                        $img_data = $stud_data['fyp_profileimg'] ?? '';
                        if (!empty($img_data)) {
                            echo "<img id='profilePreview' src='" . htmlspecialchars($img_data) . "' alt='Profile'>";
                        } else {
                            echo "<div class='profile-img-placeholder' id='profilePlaceholder'>";
                            echo "<i class='fa fa-user'></i>";
                            echo "</div>";
                        }
                        ?>
                    </div>
                    <input type="file" name="profile_img" id="profileImageInput" accept="image/jpeg,image/jpg,image/png,image/gif" style="display: none;">
                    <button type="button" class="upload-photo-btn" onclick="document.getElementById('profileImageInput').click()">
                        <i class="fa fa-camera"></i> Change Photo
                    </button>
                    <div class="student-id-display"><?php echo htmlspecialchars($stud_data['fyp_studid']); ?></div>
                    <div style="font-size: 0.8em; color: #888; text-align: center;">(Student ID)</div>
                </div>

                <div class="right-column">
                    <h3 class="form-section-title">Personal & Academic Details</h3>
                    <div class="form-group">
                        <label>Full Name:</label>
                        <input type="text" name="stud_name" value="<?php echo htmlspecialchars($stud_data['fyp_studname']); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Student ID:</label>
                        <input type="text" name="stud_fullid" value="<?php echo htmlspecialchars($stud_data['fyp_studid']); ?>" readonly>
                    </div>
                    <div class="row-group">
                        <div class="col-group form-group">
                            <label>Academic Year:</label>
                            <select name="academic_id" disabled>
                                <option value="">-- Select --</option>
                                <?php
                                if (isset($conn)) {
                                    $result_acd = $conn->query("SELECT * FROM ACADEMIC_YEAR");
                                    if ($result_acd) {
                                        while($row = $result_acd->fetch_assoc()) {
                                            $selected = ($row['fyp_academicid'] == $stud_data['fyp_academicid']) ? "selected" : "";
                                            echo "<option value='" . $row['fyp_academicid'] . "' $selected>" . $row['fyp_acdyear'] . "</option>";
                                        }
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-group form-group">
                            <label>Programme:</label>
                            <select name="prog_id" disabled>
                                <option value="">-- Select --</option>
                                <?php
                                if (isset($conn)) {
                                    $result_prog = $conn->query("SELECT * FROM PROGRAMME");
                                    if ($result_prog) {
                                        while($row = $result_prog->fetch_assoc()) {
                                            $selected = ($row['fyp_progid'] == $stud_data['fyp_progid']) ? "selected" : "";
                                            echo "<option value='" . $row['fyp_progid'] . "' $selected>" . $row['fyp_progname'] . "</option>";
                                        }
                                    }
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Email Address:</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($stud_data['fyp_email']); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label style="color: var(--primary-color); font-weight: 600;">Contact Number (Editable):</label>
                        <input type="text" name="contact" id="contactInput" value="<?php echo htmlspecialchars($stud_data['fyp_contactno']); ?>" required style="border-color: var(--primary-color);">
                    </div>
                    <button type="submit" id="saveBtn" class="save-btn">
                        <i class="fa fa-save"></i> Save Changes
                    </button>
                </div>
            </div>
        </form>

    </div>

    <div id="toast" class="toast">
        <i class="fa fa-check-circle" style="font-size: 20px; color: #28a745;"></i>
        <span id="toastMessage"></span>
    </div>

    <script>
        const profileForm = document.getElementById('profileForm');
        const saveBtn = document.getElementById('saveBtn');
        const toast = document.getElementById('toast');
        const toastMessage = document.getElementById('toastMessage');
        const profileImageInput = document.getElementById('profileImageInput');
        const profileImgBox = document.getElementById('profileImgBox');

        // Handle image preview
        profileImageInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Validate file type
                const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!validTypes.includes(file.type)) {
                    showToast('Please upload a valid image file (JPG, PNG, GIF)', 'error');
                    this.value = '';
                    return;
                }

                // Validate file size (max 5MB)
                if (file.size > 5 * 1024 * 1024) {
                    showToast('Image size should be less than 5MB', 'error');
                    this.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    // Update the image preview
                    profileImgBox.innerHTML = `<img id="profilePreview" src="${e.target.result}" alt="Profile">`;
                };
                reader.readAsDataURL(file);
            }
        });

        // Handle form submission
        profileForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving...';
            
            const formData = new FormData(profileForm);
            formData.append('update_profile', '1');
            
            try {
                const response = await fetch('std_profile.php?auth_user_id=<?php echo $auth_user_id; ?>', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToast(data.message, 'success');
                    
                    // Update the header avatar if image was uploaded
                    if (data.image) {
                        const headerAvatar = document.querySelector('.user-section .user-avatar');
                        if (headerAvatar) {
                            headerAvatar.src = data.image;
                        } else {
                            // Replace placeholder with actual image
                            const placeholder = document.querySelector('.user-avatar-placeholder');
                            if (placeholder) {
                                placeholder.outerHTML = `<img src="${data.image}" class="user-avatar" alt="User Avatar">`;
                            }
                        }
                    }
                    
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showToast(data.message, 'error');
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '<i class="fa fa-save"></i> Save Changes';
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('An error occurred. Please try again.', 'error');
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fa fa-save"></i> Save Changes';
            }
        });
        
        function showToast(message, type) {
            toastMessage.textContent = message;
            toast.className = 'toast show ' + type;
            
            const icon = toast.querySelector('i');
            if (type === 'success') {
                icon.className = 'fa fa-check-circle';
                icon.style.color = '#28a745';
            } else {
                icon.className = 'fa fa-exclamation-circle';
                icon.style.color = '#dc3545';
            }
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }
    </script>
</body>
</html>