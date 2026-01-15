<?php
// ====================================================
// student_appointment_meeting.php - Dual Check Version
// ====================================================
include("connect.php");

// 1. Verify user login
$auth_user_id = $_GET['auth_user_id'] ?? null;
if (!$auth_user_id) { header("location: login.php"); exit; }

// 2. Get current student information
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

// 3. Get my Supervisor (Dual Check: Supervisor OR Coordinator)
$my_supervisor = null;
$sv_id = "";

if ($current_stud_id) {
    $sql_reg = "SELECT fyp_staffid FROM fyp_registration WHERE fyp_studid = ? LIMIT 1";
    if ($stmt = $conn->prepare($sql_reg)) {
        $stmt->bind_param("s", $current_stud_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $sv_id = $row['fyp_staffid'];
        }
        $stmt->close();
    }
}

// Get details - CHECK 1: Supervisor Table
if (!empty($sv_id)) {
    $sql_sv = "SELECT * FROM supervisor WHERE fyp_staffid = ?";
    if ($stmt = $conn->prepare($sql_sv)) {
        $stmt->bind_param("s", $sv_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $my_supervisor = $res->fetch_assoc();
        }
        $stmt->close();
    }
    
    // Get details - CHECK 2: Coordinator Table (If not found in Supervisor)
    if (!$my_supervisor) {
        $sql_coor = "SELECT * FROM coordinator WHERE fyp_staffid = ?";
        if ($stmt = $conn->prepare($sql_coor)) {
            $stmt->bind_param("s", $sv_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows > 0) {
                $my_supervisor = $res->fetch_assoc();
            }
            $stmt->close();
        }
    }
}

// 4. Get available slots
$available_slots = [];
if (!empty($sv_id)) {
    $sql_sch = "SELECT * FROM schedule_meeting 
                WHERE fyp_staffid = ? 
                AND fyp_status = 'Available' 
                AND fyp_date >= CURDATE() 
                ORDER BY fyp_date ASC, fyp_fromtime ASC";
                
    if ($stmt = $conn->prepare($sql_sch)) {
        $stmt->bind_param("s", $sv_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $available_slots[] = $row;
        }
        $stmt->close();
    }
}
$slots_json = json_encode($available_slots);

// 5. Handle booking submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_booking'])) {
    $target_schedule_id = $_POST['schedule_id'];
    $reason = $_POST['reason'] ?? ''; 
    
    $sql_book = "INSERT INTO appointment_meeting (fyp_studid, fyp_scheduleid, fyp_staffid, fyp_status, fyp_reason, fyp_datecreated) 
                 VALUES (?, ?, ?, 'Pending', ?, NOW())";
                 
    if ($stmt = $conn->prepare($sql_book)) {
        $stmt->bind_param("siss", $current_stud_id, $target_schedule_id, $sv_id, $reason);
        if ($stmt->execute()) {
            $conn->query("UPDATE schedule_meeting SET fyp_status = 'Booked' WHERE fyp_scheduleid = '$target_schedule_id'");
            echo "<script>alert('Appointment Requested Successfully!'); window.location.href='student_appointment_meeting.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
        } else {
            echo "<script>alert('Error booking slot: " . $stmt->error . "');</script>";
        }
        $stmt->close();
    }
}

// 6. Get appointment history
$my_history = [];
if ($current_stud_id) {
    $sql_hist = "SELECT am.*, sm.fyp_date, sm.fyp_day, sm.fyp_fromtime, sm.fyp_totime, sm.fyp_location 
                 FROM appointment_meeting am
                 JOIN schedule_meeting sm ON am.fyp_scheduleid = sm.fyp_scheduleid
                 WHERE am.fyp_studid = ?
                 ORDER BY sm.fyp_date DESC";
    if ($stmt = $conn->prepare($sql_hist)) {
        $stmt->bind_param("s", $current_stud_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $my_history[] = $row;
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
    <title>Book Consultation - FYP Portal</title>
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

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f4f6f9;
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
        }

        /* Sidebar Styles */
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
        
        /* Main Content */
        .main-content-wrapper {
            margin-left: 60px;
            flex: 1;
            padding: 20px;
            width: calc(100% - 60px);
            transition: margin-left .05s linear;
        }
        
        /* Page Header */
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

        .user-info-section {
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

        /* Supervisor Profile Card */
        .supervisor-profile-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
            border-left: 5px solid #28a745;
        }

        .sv-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: #6c757d;
            overflow: hidden;
            border: 3px solid #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .sv-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .sv-info h2 {
            margin: 0 0 5px 0;
            color: #333;
            font-size: 20px;
        }

        .sv-info p {
            margin: 3px 0;
            color: #666;
            font-size: 14px;
        }

        /* Calendar Styles */
        .calendar-wrapper {
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: var(--primary-color);
            color: white;
        }

        .calendar-nav-btn {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            padding: 5px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background 0.2s;
        }

        .calendar-nav-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        .current-month {
            font-size: 18px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            padding: 20px;
            gap: 10px;
        }

        .cal-day-header {
            text-align: center;
            font-weight: 600;
            color: #888;
            font-size: 13px;
            padding-bottom: 10px;
        }

        .cal-day {
            aspect-ratio: 1;
            border-radius: 8px;
            border: 1px solid #eee;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            padding-top: 8px;
            cursor: pointer;
            position: relative;
            transition: all 0.2s;
        }

        .cal-day:hover {
            background-color: #f9f9f9;
            border-color: #ddd;
        }

        .cal-day.empty {
            border: none;
            background: transparent;
            cursor: default;
        }

        .cal-day.has-slots {
            background-color: #e3f2fd;
            border-color: #90caf9;
            color: #0d47a1;
            font-weight: 600;
        }

        .cal-day.has-slots:hover {
            background-color: #bbdefb;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .cal-day.selected {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
            transform: scale(1.05);
            z-index: 2;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }

        .slot-indicator {
            font-size: 10px;
            margin-top: 5px;
            background: rgba(255,255,255,0.8);
            padding: 2px 5px;
            border-radius: 4px;
            color: #333;
        }

        .cal-day.has-slots .slot-indicator {
            background: rgba(33, 150, 243, 0.2);
            color: #0d47a1;
        }

        .cal-day.selected .slot-indicator {
            background: rgba(255,255,255,0.3);
            color: white;
        }

        /* Selected Slots Area */
        .selected-date-slots {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            display: none;
            border-top: 4px solid var(--primary-color);
            margin-bottom: 30px;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .selected-date-slots h3 {
            margin: 0 0 15px 0;
            font-size: 18px;
            color: #333;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }

        .slots-list-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 15px;
        }

        .time-slot-btn {
            background: #fff;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .time-slot-btn:hover {
            border-color: var(--primary-color);
            background: #f8f9fa;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }

        .ts-icon {
            font-size: 20px;
            color: var(--primary-color);
            margin-bottom: 5px;
        }

        .ts-time {
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }

        .ts-loc {
            font-size: 12px;
            color: #777;
        }

        /* Appointment History */
        .section-header {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
        }

        .appointment-list {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--card-shadow);
        }

        .app-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
        }

        .app-item:last-child {
            border-bottom: none;
        }

        .app-date {
            display: flex;
            flex-direction: column;
            align-items: center;
            min-width: 60px;
            background: #f1f3f5;
            padding: 5px 10px;
            border-radius: 6px;
        }

        .app-month {
            font-size: 12px;
            font-weight: 600;
            color: #666;
            text-transform: uppercase;
        }

        .app-day {
            font-size: 20px;
            font-weight: 700;
            color: #333;
        }

        .app-details {
            flex: 1;
            margin-left: 20px;
        }

        .app-time {
            font-weight: 600;
            font-size: 15px;
            color: #333;
        }

        .app-loc {
            font-size: 13px;
            color: #777;
            margin-top: 2px;
        }

        .app-status {
            font-size: 12px;
            padding: 3px 10px;
            border-radius: 20px;
            font-weight: 600;
            display: inline-block;
            margin-top: 5px;
        }

        .status-Pending { background: #fff3cd; color: #856404; }
        .status-Approved { background: #d4edda; color: #155724; }
        .status-Rejected { background: #f8d7da; color: #721c24; }
        .status-Cancelled { background: #e2e3e5; color: #383d41; }

        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1100;
            backdrop-filter: blur(2px);
            justify-content: center;
            align-items: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal-overlay.show {
            display: flex;
            opacity: 1;
        }

        .modal-box {
            background: white;
            padding: 35px;
            border-radius: 12px;
            width: 90%;
            max-width: 450px;
            text-align: center;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            transform: scale(0.9);
            transition: transform 0.3s ease;
        }

        .modal-overlay.show .modal-box {
            transform: scale(1);
        }

        .modal-icon {
            font-size: 40px;
            color: var(--primary-color);
            margin-bottom: 15px;
        }

        .modal-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #333;
        }

        .modal-text {
            color: #666;
            margin-bottom: 20px;
            font-size: 15px;
            line-height: 1.6;
        }

        .reason-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-family: inherit;
            margin-bottom: 20px;
            resize: vertical;
            min-height: 80px;
            box-sizing: border-box;
        }

        .modal-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .btn-confirm {
            background: var(--primary-color);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            font-size: 15px;
            transition: background 0.2s;
        }

        .btn-confirm:hover {
            background: var(--primary-hover);
        }

        .btn-cancel {
            background: #f1f1f1;
            color: #444;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            font-size: 15px;
            transition: background 0.2s;
        }

        .btn-cancel:hover {
            background: #e0e0e0;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
            background: white;
            border-radius: 12px;
            border: 1px dashed #ddd;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.3;
        }

        .btn-book {
            display: inline-block;
            margin-top: 15px;
            padding: 10px 25px;
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            transition: background 0.2s;
        }

        .btn-book:hover {
            background: var(--primary-hover);
        }

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                gap: 15px;
            }

            .supervisor-profile-card {
                flex-direction: column;
                text-align: center;
            }

            .calendar-grid {
                gap: 5px;
                padding: 10px;
            }

            .slots-list-grid {
                grid-template-columns: 1fr;
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
            <li class="active">
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
                <h1>Book Consultation</h1>
                <p>Welcome back, <?php echo htmlspecialchars($user_name); ?></p>
            </div>
            
            <div class="logo-section">
                <img src="image/ladybug.png?v=<?php echo time(); ?>" alt="Logo" class="logo-img">
                <span class="system-title">FYP Portal</span>
            </div>

            <div class="user-info-section">
                <span class="user-badge">Student</span>
                <?php if(!empty($user_avatar) && $user_avatar !== 'image/user.png'): ?>
                    <img src="<?php echo htmlspecialchars($user_avatar); ?>" class="user-avatar" alt="Avatar">
                <?php else: ?>
                    <div class="user-avatar-placeholder">
                        <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($my_supervisor): ?>
            <div class="supervisor-profile-card">
                <div class="sv-avatar">
                    <?php 
                        echo !empty($my_supervisor['fyp_profileimg']) 
                            ? "<img src='{$my_supervisor['fyp_profileimg']}'>" 
                            : strtoupper(substr($my_supervisor['fyp_name'] ?? 'U', 0, 1));
                    ?>
                </div>
                <div class="sv-info">
                    <h2><?php echo htmlspecialchars($my_supervisor['fyp_name']); ?></h2>
                    <p><i class="fa fa-envelope" style="width:20px;"></i> <?php echo htmlspecialchars($my_supervisor['fyp_email']); ?></p>
                    <p><i class="fa fa-door-open" style="width:20px;"></i> Room: <?php echo htmlspecialchars($my_supervisor['fyp_roomno']); ?></p>
                    <p><i class="fa fa-phone" style="width:20px;"></i> <?php echo htmlspecialchars($my_supervisor['fyp_contactno']); ?></p>
                </div>
            </div>

            <div class="calendar-wrapper">
                <div class="calendar-header">
                    <button type="button" class="calendar-nav-btn" onclick="prevMonth()"><i class="fa fa-chevron-left"></i></button>
                    <div class="current-month" id="calendarMonthYear"></div>
                    <button type="button" class="calendar-nav-btn" onclick="nextMonth()"><i class="fa fa-chevron-right"></i></button>
                </div>
                
                <div class="calendar-grid" id="calendarDays"></div>
            </div>

            <div id="selectedSlotsContainer" class="selected-date-slots">
                <h3>Available Times for <span id="selectedDateText" style="color:var(--primary-color);"></span></h3>
                <div id="slotsList" class="slots-list-grid"></div>
            </div>

            <div class="section-header">My Appointments</div>
            
            <?php if (count($my_history) > 0): ?>
                <div class="appointment-list">
                    <?php foreach ($my_history as $myapp): ?>
                        <?php 
                            $statusClass = 'status-Pending';
                            if(strtolower($myapp['fyp_status']) == 'approved') $statusClass = 'status-Approved';
                            if(strtolower($myapp['fyp_status']) == 'rejected') $statusClass = 'status-Rejected';
                            if(strtolower($myapp['fyp_status']) == 'cancelled') $statusClass = 'status-Cancelled';
                            $dateObj = date_create($myapp['fyp_date']);
                        ?>
                        <div class="app-item">
                            <div class="app-date">
                                <span class="app-month"><?php echo date_format($dateObj, "M"); ?></span>
                                <span class="app-day"><?php echo date_format($dateObj, "d"); ?></span>
                            </div>
                            <div class="app-details">
                                <div class="app-time">
                                    <?php echo htmlspecialchars($myapp['fyp_day']); ?>, 
                                    <?php echo date('h:i A', strtotime($myapp['fyp_fromtime'])); ?> - <?php echo date('h:i A', strtotime($myapp['fyp_totime'])); ?>
                                </div>
                                <div class="app-loc">
                                    <i class="fa fa-map-marker-alt" style="font-size:12px;"></i> <?php echo !empty($myapp['fyp_location']) ? htmlspecialchars($myapp['fyp_location']) : 'Office'; ?>
                                    <?php if(!empty($myapp['fyp_reason'])): ?>
                                        <div style="font-size:12px; color:#888; margin-top:2px;">Topic: <?php echo htmlspecialchars($myapp['fyp_reason']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="app-status <?php echo $statusClass; ?>">
                                    <?php echo htmlspecialchars($myapp['fyp_status']); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fa fa-calendar-times"></i>
                    <p style="margin:0; font-style:italic;">No booking history.</p>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="empty-state">
                <i class="fa fa-user-slash" style="color:#d9534f;"></i>
                <p><strong>Supervisor Not Assigned</strong></p>
                <p style="font-size:14px; color:#666;">You need to have an approved FYP project and supervisor before you can book appointments.</p>
                <a href="std_projectreg.php?auth_user_id=<?php echo urlencode($auth_user_id); ?>" class="btn-book">Go to Registration</a>
            </div>
        <?php endif; ?>

    </div>

    <div id="confirmationModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-icon"><i class="fa fa-question-circle"></i></div>
            <div class="modal-title">Book Slot</div>
            <p class="modal-text" id="modalText"></p>
            
            <form method="POST" style="display:block;">
                <input type="hidden" name="schedule_id" id="modalScheduleId">
                
                <div style="text-align:left; margin-bottom:15px;">
                    <label style="font-size:13px; font-weight:600; color:#555;">Reason for Consultation:</label>
                    <textarea name="reason" class="reason-input" placeholder="E.g. Discussing Chapter 2 difficulties..." required></textarea>
                </div>

                <div class="modal-actions">
                    <button type="submit" name="confirm_booking" class="btn-confirm">Yes, Book it</button>
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const confirmModal = document.getElementById('confirmationModal');
        const modalText = document.getElementById('modalText');
        const slotsJson = <?php echo $slots_json; ?>;

        let currentDate = new Date();
        let currentMonth = currentDate.getMonth();
        let currentYear = currentDate.getFullYear();

        const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

        renderCalendar(currentMonth, currentYear);

        function renderCalendar(month, year) {
            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const calendarDays = document.getElementById('calendarDays');
            const monthYearText = document.getElementById('calendarMonthYear');
            
            calendarDays.innerHTML = '';
            monthYearText.innerText = `${monthNames[month]} ${year}`;
            
            const daysShort = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            daysShort.forEach(day => {
                const header = document.createElement('div');
                header.className = 'cal-day-header';
                header.innerText = day;
                calendarDays.appendChild(header);
            });

            for (let i = 0; i < firstDay; i++) {
                const emptyCell = document.createElement('div');
                emptyCell.className = 'cal-day empty';
                calendarDays.appendChild(emptyCell);
            }

            for (let i = 1; i <= daysInMonth; i++) {
                const dayCell = document.createElement('div');
                dayCell.className = 'cal-day';
                dayCell.innerText = i;
                
                const monthStr = (month + 1).toString().padStart(2, '0');
                const dayStr = i.toString().padStart(2, '0');
                const fullDate = `${year}-${monthStr}-${dayStr}`;
                
                const slotsForDay = slotsJson.filter(s => s.fyp_date === fullDate);
                
                if (slotsForDay.length > 0) {
                    dayCell.classList.add('has-slots');
                    const indicator = document.createElement('div');
                    indicator.className = 'slot-indicator';
                    indicator.innerText = `${slotsForDay.length} Slots`;
                    dayCell.appendChild(indicator);
                    
                    dayCell.onclick = function() {
                        document.querySelectorAll('.cal-day').forEach(d => d.classList.remove('selected'));
                        this.classList.add('selected');
                        showSlotsForDate(fullDate, slotsForDay);
                    };
                }

                calendarDays.appendChild(dayCell);
            }
        }

        function prevMonth() {
            currentMonth--;
            if (currentMonth < 0) {
                currentMonth = 11;
                currentYear--;
            }
            renderCalendar(currentMonth, currentYear);
        }

        function nextMonth() {
            currentMonth++;
            if (currentMonth > 11) {
                currentMonth = 0;
                currentYear++;
            }
            renderCalendar(currentMonth, currentYear);
        }

        function showSlotsForDate(dateStr, slots) {
            const container = document.getElementById('selectedSlotsContainer');
            const list = document.getElementById('slotsList');
            const dateText = document.getElementById('selectedDateText');
            
            dateText.innerText = dateStr;
            list.innerHTML = '';
            
            slots.forEach(slot => {
                const btn = document.createElement('div');
                btn.className = 'time-slot-btn';
                
                const formatTime = (timeStr) => {
                    const [hour, minute] = timeStr.split(':');
                    const h = parseInt(hour);
                    const ampm = h >= 12 ? 'PM' : 'AM';
                    const h12 = h % 12 || 12;
                    return `${h12}:${minute} ${ampm}`;
                };
                
                btn.innerHTML = `
                    <div class="ts-icon"><i class="fa fa-clock"></i></div>
                    <div class="ts-time">${formatTime(slot.fyp_fromtime)} - ${formatTime(slot.fyp_totime)}</div>
                    <div class="ts-loc">${slot.fyp_location || 'Office'}</div>
                `;
                btn.onclick = () => openBookModal(slot.fyp_scheduleid, `${slot.fyp_date} (${formatTime(slot.fyp_fromtime)})`);
                list.appendChild(btn);
            });
            
            container.style.display = 'block';
            container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        function openBookModal(scheduleId, dateStr) {
            modalText.innerHTML = "Confirm booking for:<br><strong>" + dateStr + "</strong>?";
            document.getElementById('modalScheduleId').value = scheduleId;
            confirmModal.classList.add('show');
        }

        function closeModal() {
            confirmModal.classList.remove('show');
        }

        window.onclick = function(e) {
            if (e.target.classList.contains('modal-overlay')) {
                e.target.classList.remove('show');
            }
        }
    </script>
</body>
</html>