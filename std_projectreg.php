<?php
include("connect.php");

$auth_user_id = $_GET['auth_user_id'] ?? null;
if (!$auth_user_id) { 
    header("Content-Type: application/json");
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register_project_confirm'])) {
    header('Content-Type: application/json');
    
    date_default_timezone_set('Asia/Kuala_Lumpur');
    $proj_id = $_POST['project_id'];
    $stud_id = $_POST['student_id'];
    $date_now = date('Y-m-d H:i:s');

    $chk_proj_sql = "SELECT fyp_projecttype, fyp_staffid, fyp_projectstatus FROM PROJECT WHERE fyp_projectid = ?";
    $stmt_p = $conn->prepare($chk_proj_sql);
    $stmt_p->bind_param("i", $proj_id);
    $stmt_p->execute();
    $res_p = $stmt_p->get_result();
    $proj_info = $res_p->fetch_assoc();
    $stmt_p->close();

    if (!$proj_info) {
        echo json_encode(['success' => false, 'message' => 'Project not found.']);
        exit;
    }
    
    $target_sv_id = $proj_info['fyp_staffid'];
    $proj_status = $proj_info['fyp_projectstatus'];

    if ($proj_status == 'Taken') {
        echo json_encode(['success' => false, 'message' => 'Sorry, this project is already TAKEN.']);
        exit;
    }

    $sql_ins = "INSERT INTO project_request (fyp_studid, fyp_staffid, fyp_projectid, fyp_requeststatus, fyp_datecreated) VALUES (?, ?, ?, 'Pending', ?)";
    if ($stmt = $conn->prepare($sql_ins)) {
        $stmt->bind_param("ssis", $stud_id, $target_sv_id, $proj_id, $date_now);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Application Submitted Successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $stmt->error]);
        }
        $stmt->close();
    }
    exit;
}

$stud_data = [];
$my_stud_id = '';
$my_group_status = 'Individual';
$user_name = 'Student';
$is_leader = false;
$my_group_id = 0;
$my_academic_id = 0;

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
        if($row=$res->fetch_assoc()){ 
            $stud_data=$row; 
            $my_stud_id = $row['fyp_studid'];
            if(!empty($row['fyp_studname'])) $user_name=$row['fyp_studname'];
            if(!empty($row['fyp_group'])) $my_group_status = $row['fyp_group']; 
            if(!empty($row['fyp_academicid'])) $my_academic_id = $row['fyp_academicid'];
        } 
        $stmt->close(); 
    }

    $target_applicant_id = $my_stud_id;

    if ($my_group_status == 'Group') {
        $chk_leader = "SELECT group_id FROM student_group WHERE leader_id = '$my_stud_id'";
        $res_l = $conn->query($chk_leader);
        if ($res_l && $res_l->num_rows > 0) {
            $is_leader = true;
            $grp = $res_l->fetch_assoc();
            $my_group_id = $grp['group_id'];
        } else {
            $find_leader_sql = "SELECT g.leader_id 
                                FROM group_request gr 
                                JOIN student_group g ON gr.group_id = g.group_id 
                                WHERE gr.invitee_id = '$my_stud_id' AND gr.request_status = 'Accepted' LIMIT 1";
            $res_fl = $conn->query($find_leader_sql);
            if ($row_fl = $res_fl->fetch_assoc()) {
                $target_applicant_id = $row_fl['leader_id'];
            }
        }
    }
}

$has_active_application = false;
$active_app_status = '';

$chk_app_sql = "SELECT fyp_requeststatus FROM project_request 
                WHERE fyp_studid = '$target_applicant_id' 
                AND (fyp_requeststatus = 'Pending' OR fyp_requeststatus = 'Approve') 
                LIMIT 1";
$res_app = $conn->query($chk_app_sql);
if ($res_app && $res_app->num_rows > 0) {
    $has_active_application = true;
    $row_app = $res_app->fetch_assoc();
    $active_app_status = $row_app['fyp_requeststatus'];
}

$rejected_project_ids = [];
$chk_rej_sql = "SELECT fyp_projectid FROM project_request 
                WHERE fyp_studid = '$target_applicant_id' 
                AND fyp_requeststatus = 'Reject'";
$res_rej = $conn->query($chk_rej_sql);
if ($res_rej) {
    while ($row_rej = $res_rej->fetch_assoc()) {
        $rejected_project_ids[] = $row_rej['fyp_projectid'];
    }
}

$available_projects = [];
$filter_status = $_GET['filter_status'] ?? '';
$search_title = $_GET['search_title'] ?? '';
$search_sv = $_GET['search_sv'] ?? '';

$sql = "SELECT p.*, s.fyp_name as sv_name, s.fyp_email as sv_email, s.fyp_contactno as sv_phone 
        FROM PROJECT p 
        LEFT JOIN supervisor s ON p.fyp_staffid = s.fyp_staffid 
        WHERE 1=1";

if ($my_academic_id > 0) {
    $sql .= " AND p.fyp_academicid = '$my_academic_id'";
}

if (!empty($filter_status)) {
    $sql .= " AND p.fyp_projectstatus = '" . $conn->real_escape_string($filter_status) . "'";
}

if (!empty($search_title)) {
    $sql .= " AND p.fyp_projecttitle LIKE '%" . $conn->real_escape_string($search_title) . "%'";
}

if (!empty($search_sv)) {
    $sv_term = $conn->real_escape_string($search_sv);
    $sql .= " AND (s.fyp_name LIKE '%$sv_term%' OR p.fyp_contactpersonname LIKE '%$sv_term%')";
}

$sql .= " ORDER BY p.fyp_datecreated DESC"; 

$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $available_projects[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Registration</title>
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
            font-size: 13px;
            color: var(--text-secondary);
            background: var(--slot-bg);
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

        .status-pill {
            background: #e3effd;
            color: var(--primary-color);
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 13px;
            display: inline-block;
            margin-left: 10px;
        }

        .filter-card {
            background: var(--card-bg);
            padding: 20px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
            transition: background 0.3s;
        }

        .filter-group { flex: 1; min-width: 150px; }
        
        .filter-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 5px;
        }

        .filter-input, .filter-select {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-family: inherit;
            font-size: 14px;
            box-sizing: border-box;
            background-color: var(--card-bg);
            color: var(--text-color);
        }

        .btn-filter {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            height: 40px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-filter:hover { background-color: var(--primary-hover); }

        .btn-reset {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 40px;
            box-sizing: border-box;
        }

        .btn-reset:hover { background-color: #5a6268; }

        .project-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }

        .project-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 25px;
            box-shadow: var(--card-shadow);
            display: flex;
            flex-direction: column;
            transition: transform 0.3s, background 0.3s;
            border-top: 4px solid #ddd;
            position: relative;
        }

        .project-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .card-Group { border-top-color: #7b1fa2; }
        .card-Individual { border-top-color: #0288d1; }

        .p-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .p-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-color);
            line-height: 1.4;
            flex: 1;
            margin-right: 10px;
        }

        .p-status {
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: 600;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .st-Open { background: #e8f5e9; color: #2e7d32; }
        .st-Taken { background: #ffebee; color: #c62828; }

        .p-meta { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 15px; }
        
        .badge {
            font-size: 11px;
            padding: 4px 10px;
            border-radius: 12px;
            font-weight: 500;
        }

        .badge-cat { background: #fff3e0; color: #ef6c00; }
        .badge-type { background: #f3e5f5; color: #7b1fa2; }
        
        .p-desc {
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 20px;
            line-height: 1.6;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            height: 63px;
        }
        
        .sv-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            padding-top: 15px;
            border-top: 1px solid var(--border-color);
        }

        .sv-avatar {
            width: 30px;
            height: 30px;
            background: #eee;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #888;
            font-size: 12px;
        }

        .sv-name { font-size: 13px; font-weight: 500; color: var(--text-color); }

        .btn-view {
            background: var(--card-bg);
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
            padding: 10px;
            border-radius: 6px;
            width: 100%;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-view:hover { background: var(--primary-color); color: #fff; }

        .project-card.disabled { opacity: 0.6; }
        .project-card.disabled .btn-view {
            border-color: #ccc;
            color: #999;
            cursor: not-allowed;
            background: var(--slot-bg);
        }
        .project-card.disabled:hover { transform: none; }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1100;
            justify-content: center;
            align-items: center;
        }

        .modal-overlay.show { display: flex; }

        .modal-box {
            background: var(--card-bg);
            width: 90%;
            max-width: 700px;
            border-radius: 12px;
            padding: 30px;
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
        }

        .close-modal {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-secondary);
        }
        
        .m-section { margin-bottom: 20px; }
        
        .m-label {
            font-size: 12px;
            color: var(--text-secondary);
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .m-value { font-size: 15px; color: var(--text-color); line-height: 1.6; }
        
        .m-title {
            font-size: 22px;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 5px;
        }

        .sv-card-mini {
            background: var(--slot-bg);
            padding: 15px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .btn-confirm {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            margin-top: 10px;
        }

        .btn-confirm:hover { background: var(--primary-hover); }
        .btn-confirm:disabled { opacity: 0.6; cursor: not-allowed; }

        .alert-banner {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            color: #856404;
        }

        .theme-toggle {
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            background: var(--slot-bg);
            border: 1px solid var(--border-color);
            color: var(--text-color);
            display: flex;
            align-items: center;
            justify-content: center;
            width: 35px;
            height: 35px;
            margin-right: 15px;
        }

        .theme-toggle img {
            width: 20px;
            height: 20px;
            object-fit: contain;
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
            <li class="active">
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
                <h1>Available Projects</h1>
                <p>Browse and apply for Final Year Projects - Status: <span class="status-pill"><?php echo $my_group_status; ?></span><?php if($is_leader) echo '<span class="status-pill" style="background:#fff3cd; color:#856404;">Leader</span>'; ?></p>
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
                <?php if(!empty($stud_data['fyp_profileimg'])): ?>
                    <img src="<?php echo htmlspecialchars($stud_data['fyp_profileimg']); ?>" class="user-avatar" alt="User Avatar">
                <?php else: ?>
                    <div class="user-avatar-placeholder"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($has_active_application): ?>
            <div class="alert-banner">
                <i class="fa fa-info-circle" style="font-size: 18px;"></i>
                <div>
                    <strong>Action Required:</strong> You already have a project application with status: 
                    <span style="font-weight:bold; text-transform:uppercase;"><?php echo $active_app_status; ?></span>.
                    You cannot apply for another project.
                </div>
            </div>
        <?php endif; ?>
        
        <div class="filter-card">
            <div class="filter-group">
                <label>Status</label>
                <select id="filterStatus" class="filter-select">
                    <option value="">All</option>
                    <option value="Open" <?php if($filter_status == 'Open') echo 'selected'; ?>>Open</option>
                    <option value="Taken" <?php if($filter_status == 'Taken') echo 'selected'; ?>>Taken</option>
                </select>
            </div>
            
            <div class="filter-group" style="flex: 2;">
                <label>Search Title</label>
                <input type="text" id="searchTitle" class="filter-input" placeholder="e.g. AI Chatbot" value="<?php echo htmlspecialchars($search_title); ?>">
            </div>
            
            <div class="filter-group" style="flex: 2;">
                <label>Search Supervisor</label>
                <input type="text" id="searchSv" class="filter-input" placeholder="e.g. Dr. Smith" value="<?php echo htmlspecialchars($search_sv); ?>">
            </div>
            
            <button onclick="applyFilter()" class="btn-filter"><i class="fa fa-search"></i> Filter</button>
            <button onclick="resetFilter()" class="btn-reset">Reset</button>
        </div>

        <div class="project-grid" id="projectGrid">
            <?php if (count($available_projects) > 0): ?>
                <?php foreach ($available_projects as $proj): ?>
                    <?php 
                        $isTaken = ($proj['fyp_projectstatus'] == 'Taken');
                        $isMismatch = ($my_group_status != $proj['fyp_projecttype']);
                        $isMemberRestrict = ($my_group_status == 'Group' && !$is_leader && $proj['fyp_projecttype'] == 'Group');
                        $isAlreadyApplied = $has_active_application;
                        $isRejected = in_array($proj['fyp_projectid'], $rejected_project_ids);
                        $isDisabled = $isTaken || $isMismatch || $isMemberRestrict || $isAlreadyApplied || $isRejected;
                        
                        $btnText = "View & Apply";
                        if ($isTaken) $btnText = "Taken";
                        else if ($isRejected) $btnText = "Application Rejected"; 
                        else if ($isAlreadyApplied) $btnText = "Already Applied"; 
                        else if ($isMismatch) $btnText = "Type Mismatch";
                        else if ($isMemberRestrict) $btnText = "Leader Only"; 
                    ?>
                    
                    <div class="project-card card-<?php echo $proj['fyp_projecttype']; ?> <?php echo $isDisabled ? 'disabled' : ''; ?>">
                        <div class="p-header">
                            <div class="p-title"><?php echo htmlspecialchars($proj['fyp_projecttitle']); ?></div>
                            <span class="p-status st-<?php echo $proj['fyp_projectstatus']; ?>"><?php echo $proj['fyp_projectstatus']; ?></span>
                        </div>
                        
                        <div class="p-meta">
                            <span class="badge badge-cat"><?php echo htmlspecialchars($proj['fyp_projectcat']); ?></span>
                            <span class="badge badge-type"><?php echo htmlspecialchars($proj['fyp_projecttype']); ?></span>
                        </div>

                        <div class="p-desc"><?php echo htmlspecialchars($proj['fyp_description']); ?></div>

                        <div class="sv-info">
                            <div class="sv-avatar"><i class="fa fa-user-tie"></i></div>
                            <div class="sv-name">
                                SV: <?php echo htmlspecialchars(!empty($proj['sv_name']) ? $proj['sv_name'] : $proj['fyp_contactpersonname']); ?>
                            </div>
                        </div>

                        <button type="button" class="btn-view" id="btn-view-<?php echo $proj['fyp_projectid']; ?>"
                            <?php if (!$isDisabled): ?>
                                onclick='openModal(<?php echo json_encode($proj); ?>)' 
                            <?php endif; ?>
                            <?php echo $isDisabled ? 'disabled' : ''; ?>>
                            <?php echo $btnText; ?>
                        </button>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align:center; padding:40px; color:var(--text-secondary); grid-column: 1/-1;">No projects match your criteria.</div>
            <?php endif; ?>
        </div>
    </div>

    <div id="detailModal" class="modal-overlay">
        <div class="modal-box">
            <span class="close-modal" onclick="closeModal()">&times;</span>
            
            <form id="applicationForm">
                <input type="hidden" name="project_id" id="modalProjId">
                <input type="hidden" name="student_id" value="<?php echo $my_stud_id; ?>">
                
                <div class="m-title" id="mTitle"></div>
                <div style="margin-bottom:20px;">
                    <span class="badge badge-cat" id="mCat"></span>
                    <span class="badge badge-type" id="mType"></span>
                    <span class="p-status" id="mStatus"></span>
                </div>

                <div class="m-section">
                    <div class="m-label">Description</div>
                    <div class="m-value" id="mDesc"></div>
                </div>

                <div class="m-section">
                    <div class="m-label">Technical Requirements</div>
                    <div class="m-value" id="mReq"></div>
                </div>

                <div class="m-section">
                    <div class="m-label">Course Requirement</div>
                    <div class="m-value" id="mCourse"></div>
                </div>

                <div class="m-section">
                    <div class="m-label">Supervisor Info</div>
                    <div class="sv-card-mini">
                        <div style="font-size:32px; color:#ccc;"><i class="fa fa-user-circle"></i></div>
                        <div>
                            <div style="font-weight:600; color:var(--text-color);" id="mSvName"></div>
                            <div style="font-size:13px; color:var(--text-secondary);">
                                <i class="fa fa-envelope"></i> <span id="mSvEmail"></span> &nbsp;|&nbsp; 
                                <i class="fa fa-phone"></i> <span id="mSvPhone"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="button" id="submitBtn" class="btn-confirm" onclick="confirmApplication()">
                    <i class="fa fa-paper-plane"></i> Apply for this Project
                </button>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('detailModal');
        const applicationForm = document.getElementById('applicationForm');
        const submitBtn = document.getElementById('submitBtn');

        function openModal(proj) {
            document.getElementById('modalProjId').value = proj.fyp_projectid;
            document.getElementById('mTitle').innerText = proj.fyp_projecttitle;
            document.getElementById('mCat').innerText = proj.fyp_projectcat;
            document.getElementById('mType').innerText = proj.fyp_projecttype;
            
            const stEl = document.getElementById('mStatus');
            stEl.innerText = proj.fyp_projectstatus;
            stEl.className = 'p-status st-' + proj.fyp_projectstatus;

            document.getElementById('mDesc').innerText = proj.fyp_description;
            document.getElementById('mReq').innerText = proj.fyp_requirement || 'None specified';
            document.getElementById('mCourse').innerText = proj.fyp_coursereq || 'Open to all';

            const svName = proj.sv_name || proj.fyp_contactpersonname || 'Unknown';
            const svEmail = proj.sv_email || proj.fyp_contactperson || 'N/A';
            const svPhone = proj.sv_phone || 'N/A';

            document.getElementById('mSvName').innerText = svName;
            document.getElementById('mSvEmail').innerText = svEmail;
            document.getElementById('mSvPhone').innerText = svPhone;

            modal.classList.add('show');
        }

        function closeModal() {
            modal.classList.remove('show');
        }

        window.onclick = function(e) {
            if (e.target == modal) closeModal();
        }

        function confirmApplication() {
            Swal.fire({
                title: 'Confirm Application?',
                text: "You are about to apply for this project. Once submitted, you cannot apply for another project until this is processed.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#0056b3',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, Apply!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    submitApplication();
                }
            });
        }

        async function submitApplication() {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Submitting...';
            
            const formData = new FormData(applicationForm);
            formData.append('register_project_confirm', '1');
            
            try {
                const response = await fetch('std_projectreg.php?auth_user_id=<?php echo $auth_user_id; ?>', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    Swal.fire({
                        title: 'Success!',
                        text: data.message,
                        icon: 'success',
                        confirmButtonColor: '#0056b3'
                    }).then(() => {
                        closeModal();
                        location.reload(); 
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: data.message,
                        icon: 'error',
                        confirmButtonColor: '#d33'
                    });
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fa fa-paper-plane"></i> Apply for this Project';
                }
            } catch (error) {
                Swal.fire({
                    title: 'System Error',
                    text: 'An error occurred. Please try again.',
                    icon: 'error',
                    confirmButtonColor: '#d33'
                });
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fa fa-paper-plane"></i> Apply for this Project';
            }
        }

        function applyFilter() {
            const status = document.getElementById('filterStatus').value;
            const title = document.getElementById('searchTitle').value;
            const sv = document.getElementById('searchSv').value;
            
            const params = new URLSearchParams({
                auth_user_id: '<?php echo $auth_user_id; ?>',
                filter_status: status,
                search_title: title,
                search_sv: sv
            });
            
            window.location.href = 'std_projectreg.php?' + params.toString();
        }

        function resetFilter() {
            window.location.href = 'std_projectreg.php?auth_user_id=<?php echo $auth_user_id; ?>';
        }

        document.getElementById('searchTitle').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                applyFilter();
            }
        });

        document.getElementById('searchSv').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                applyFilter();
            }
        });

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