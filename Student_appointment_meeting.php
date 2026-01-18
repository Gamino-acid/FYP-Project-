<?php
include("connect.php");

$auth_user_id = $_GET['auth_user_id'] ?? null;
if (!$auth_user_id) { 
    if(isset($_POST['ajax'])) { echo json_encode(['success'=>false, 'message'=>'Unauthorized']); exit; }
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

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_booking'])) {
    $target_schedule_id = $_POST['schedule_id'];
    $reason = $_POST['reason'] ?? ''; 
    
    $sql_book = "INSERT INTO appointment_meeting (fyp_studid, fyp_scheduleid, fyp_staffid, fyp_status, fyp_reason, fyp_datecreated) 
                 VALUES (?, ?, ?, 'Pending', ?, NOW())";
                 
    if ($stmt = $conn->prepare($sql_book)) {
        $stmt->bind_param("siss", $current_stud_id, $target_schedule_id, $sv_id, $reason);
        if ($stmt->execute()) {
            $conn->query("UPDATE schedule_meeting SET fyp_status = 'Booked' WHERE fyp_scheduleid = '$target_schedule_id'");
            
            if(isset($_POST['ajax'])) {
                echo json_encode(['success' => true, 'message' => 'Appointment Requested Successfully!']);
                exit;
            }
            echo "<script>alert('Appointment Requested Successfully!'); window.location.href='student_appointment_meeting.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
        } else {
            if(isset($_POST['ajax'])) {
                echo json_encode(['success' => false, 'message' => 'Error booking slot: ' . $stmt->error]);
                exit;
            }
            echo "<script>alert('Error booking slot: " . $stmt->error . "');</script>";
        }
        $stmt->close();
    }
}

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
            $dateObj = date_create($row['fyp_date']);
            $row['fmt_month'] = date_format($dateObj, "M");
            $row['fmt_day'] = date_format($dateObj, "d");
            $row['fmt_time'] = htmlspecialchars($row['fyp_day']) . ", " . 
                               date('h:i A', strtotime($row['fyp_fromtime'])) . " - " . 
                               date('h:i A', strtotime($row['fyp_totime']));
            $row['fmt_location'] = !empty($row['fyp_location']) ? htmlspecialchars($row['fyp_location']) : 'Office';
            $row['fmt_reason'] = !empty($row['fyp_reason']) ? htmlspecialchars($row['fyp_reason']) : '';
            
            $statusClass = 'status-Pending';
            if(strtolower($row['fyp_status']) == 'approved') $statusClass = 'status-Approved';
            if(strtolower($row['fyp_status']) == 'rejected') $statusClass = 'status-Rejected';
            if(strtolower($row['fyp_status']) == 'cancelled') $statusClass = 'status-Cancelled';
            $row['fmt_status_class'] = $statusClass;
            $row['fmt_status'] = htmlspecialchars($row['fyp_status']);

            $my_history[] = $row;
        }
        $stmt->close();
    }
}
$history_json = json_encode($my_history);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Consultation - FYP Portal</title>
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

        .supervisor-profile-card {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
            border-left: 5px solid #28a745;
            transition: background 0.3s;
        }

        .sv-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--slot-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: var(--text-secondary);
            overflow: hidden;
            border: 3px solid var(--card-bg);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .sv-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .sv-info h2 {
            margin: 0 0 5px 0;
            color: var(--text-color);
            font-size: 20px;
        }

        .sv-info p {
            margin: 3px 0;
            color: var(--text-secondary);
            font-size: 14px;
        }

        .calendar-wrapper {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            margin-bottom: 30px;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
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
            font-size: 14px;
            transition: background 0.2s;
        }

        .calendar-nav-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        .current-month {
            font-size: 16px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            padding: 15px;
            gap: 8px;
        }

        .cal-day-header {
            text-align: center;
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 12px;
            padding-bottom: 5px;
        }

        .cal-day {
            aspect-ratio: 1.2;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            padding-top: 5px;
            cursor: pointer;
            position: relative;
            transition: all 0.2s;
            font-size: 14px;
            color: var(--text-color);
        }

        .cal-day:hover {
            background-color: var(--slot-bg);
            border-color: var(--primary-color);
        }

        .cal-day.empty {
            border: none;
            background: transparent;
            cursor: default;
        }

        .cal-day.has-slots {
            background-color: rgba(33, 150, 243, 0.1);
            border-color: #90caf9;
            color: var(--primary-color);
            font-weight: 600;
        }

        .cal-day.has-slots:hover {
            background-color: rgba(33, 150, 243, 0.2);
            transform: translateY(-2px);
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
            font-size: 9px;
            margin-top: 2px;
            background: rgba(255,255,255,0.8);
            padding: 1px 4px;
            border-radius: 4px;
            color: #333;
        }

        .cal-day.has-slots .slot-indicator {
            background: rgba(33, 150, 243, 0.2);
            color: var(--primary-color);
        }

        .cal-day.selected .slot-indicator {
            background: rgba(255,255,255,0.3);
            color: white;
        }

        .selected-date-slots {
            background: var(--card-bg);
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
            color: var(--text-color);
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 10px;
        }

        .slots-list-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 15px;
        }

        .time-slot-btn {
            background: var(--slot-bg);
            border: 1px solid var(--border-color);
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
            color: var(--text-color);
        }

        .ts-loc {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .section-header {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 20px;
        }

        .appointment-list {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--card-shadow);
        }

        .app-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .app-item:last-child {
            border-bottom: none;
        }

        .app-date {
            display: flex;
            flex-direction: column;
            align-items: center;
            min-width: 60px;
            background: var(--slot-bg);
            padding: 5px 10px;
            border-radius: 6px;
        }

        .app-month {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
        }

        .app-day {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-color);
        }

        .app-details {
            flex: 1;
            margin-left: 20px;
        }

        .app-time {
            font-weight: 600;
            font-size: 15px;
            color: var(--text-color);
        }

        .app-loc {
            font-size: 13px;
            color: var(--text-secondary);
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

        .modal-overlay.show {
            display: flex;
        }

        .modal-box {
            background: var(--card-bg);
            padding: 35px;
            border-radius: 12px;
            width: 90%;
            max-width: 450px;
            text-align: center;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
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
            color: var(--text-color);
        }

        .modal-text {
            color: var(--text-secondary);
            margin-bottom: 20px;
            font-size: 15px;
            line-height: 1.6;
        }

        .reason-input {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-family: inherit;
            margin-bottom: 20px;
            resize: vertical;
            min-height: 80px;
            box-sizing: border-box;
            background: var(--slot-bg);
            color: var(--text-color);
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
            background: var(--slot-bg);
            color: var(--text-color);
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            font-size: 15px;
            transition: background 0.2s;
        }

        .btn-cancel:hover {
            background: var(--border-color);
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--text-secondary);
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px dashed var(--border-color);
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

        .toast {
            position: fixed; top: 20px; right: 20px; background: var(--card-bg);
            padding: 15px 20px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: none; align-items: center; gap: 10px; z-index: 2000;
            animation: slideIn 0.3s ease; color: var(--text-color);
        }
        .toast.show { display: flex; }
        .toast.success { border-left: 4px solid #28a745; }
        .toast.error { border-left: 4px solid #dc3545; }

        .theme-toggle {
            cursor: pointer; padding: 8px; border-radius: 50%;
            background: var(--slot-bg); border: 1px solid var(--border-color);
            color: var(--text-color); display: flex; align-items: center;
            justify-content: center; width: 35px; height: 35px; margin-right: 15px;
        }
        .theme-toggle img { width: 20px; height: 20px; object-fit: contain; }
        
        .form-input {
            width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 6px; font-family: inherit; font-size: 14px;
            background: var(--card-bg); color: var(--text-color);
        }

        @keyframes slideIn {
            from { transform: translateX(400px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
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
                <h1>Book Consultation</h1>
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
            
            <div class="appointment-list">
                <div style="display:flex; justify-content:space-between; margin-bottom:15px;">
                    <select id="statusFilter" class="form-input" style="width:auto; padding:8px;" onchange="filterAppointments()">
                        <option value="All">All Status</option>
                        <option value="Approved">Approved</option>
                        <option value="Pending">Pending</option>
                        <option value="Rejected">Rejected</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>
                
                <div id="appointmentContainer"></div>
                
                <div id="pagination" style="display:flex; justify-content:center; gap:10px; margin-top:20px; align-items: center;">
                    <button id="prevBtn" class="calendar-nav-btn" style="color:var(--text-color); background:var(--slot-bg);" onclick="prevPage()"><i class="fa fa-chevron-left"></i></button>
                    <span id="pageIndicator" style="font-size:14px; color:var(--text-secondary);">Page 1 of 1</span>
                    <button id="nextBtn" class="calendar-nav-btn" style="color:var(--text-color); background:var(--slot-bg);" onclick="nextPage()"><i class="fa fa-chevron-right"></i></button>
                </div>
            </div>

        <?php else: ?>
            <div class="empty-state">
                <i class="fa fa-user-slash" style="color:#d9534f;"></i>
                <p><strong>Supervisor Not Assigned</strong></p>
                <p style="font-size:14px; color:var(--text-secondary);">You need to have an approved FYP project and supervisor before you can book appointments.</p>
                <a href="std_projectreg.php?auth_user_id=<?php echo urlencode($auth_user_id); ?>" class="btn-book">Go to Registration</a>
            </div>
        <?php endif; ?>

    </div>

    <div id="confirmationModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-icon"><i class="fa fa-question-circle"></i></div>
            <div class="modal-title">Book Slot</div>
            <p class="modal-text" id="modalText"></p>
            
            <form id="bookingForm" method="POST" style="display:block;">
                <input type="hidden" name="ajax" value="1">
                <input type="hidden" name="schedule_id" id="modalScheduleId">
                
                <div style="text-align:left; margin-bottom:15px;">
                    <label style="font-size:13px; font-weight:600; color:var(--text-secondary);">Reason for Consultation:</label>
                    <textarea name="reason" class="reason-input" placeholder="E.g. Discussing Chapter 2 difficulties..." required></textarea>
                </div>

                <div class="modal-actions">
                    <button type="submit" name="confirm_booking" class="btn-confirm">Yes, Book it</button>
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <div id="toast" class="toast">
        <i class="fa fa-check-circle" style="font-size: 20px; color: #28a745;"></i>
        <span id="toastMessage"></span>
    </div>

    <script>
        const confirmModal = document.getElementById('confirmationModal');
        const modalText = document.getElementById('modalText');
        const bookingForm = document.getElementById('bookingForm');
        const toast = document.getElementById('toast');
        const toastMessage = document.getElementById('toastMessage');
        const slotsJson = <?php echo $slots_json; ?>;
        
        const allAppointments = <?php echo $history_json; ?>;
        let currentPage = 1;
        const itemsPerPage = 5;
        let currentFilter = 'All';
        let filteredAppointments = [];

        let currentDate = new Date();
        let currentMonth = currentDate.getMonth();
        let currentYear = currentDate.getFullYear();

        const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

        renderCalendar(currentMonth, currentYear);
        filterAppointments(); 

        function filterAppointments() {
            currentFilter = document.getElementById('statusFilter').value;
            
            if (currentFilter === 'All') {
                filteredAppointments = allAppointments;
            } else {
                filteredAppointments = allAppointments.filter(app => app.fyp_status === currentFilter);
            }
            
            currentPage = 1;
            renderAppointments();
        }

        function renderAppointments() {
            const container = document.getElementById('appointmentContainer');
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            const indicator = document.getElementById('pageIndicator');
            
            container.innerHTML = '';
            
            if (filteredAppointments.length === 0) {
                container.innerHTML = '<div class="empty-state" style="padding:20px; border:none;"><i class="fa fa-calendar-times" style="font-size:32px;"></i><p>No appointments found.</p></div>';
                prevBtn.style.display = 'none';
                nextBtn.style.display = 'none';
                indicator.style.display = 'none';
                return;
            }

            const totalPages = Math.ceil(filteredAppointments.length / itemsPerPage);
            const start = (currentPage - 1) * itemsPerPage;
            const end = start + itemsPerPage;
            const pageItems = filteredAppointments.slice(start, end);

            pageItems.forEach(app => {
                const item = document.createElement('div');
                item.className = 'app-item';
                item.innerHTML = `
                    <div class="app-date">
                        <span class="app-month">${app.fmt_month}</span>
                        <span class="app-day">${app.fmt_day}</span>
                    </div>
                    <div class="app-details">
                        <div class="app-time">${app.fmt_time}</div>
                        <div class="app-loc">
                            <i class="fa fa-map-marker-alt" style="font-size:12px;"></i> ${app.fmt_location}
                            ${app.fmt_reason ? `<div style="font-size:12px; color:var(--text-secondary); margin-top:2px;">Topic: ${app.fmt_reason}</div>` : ''}
                        </div>
                        <div class="app-status ${app.fmt_status_class}">${app.fmt_status}</div>
                    </div>
                `;
                container.appendChild(item);
            });

            indicator.style.display = 'block';
            prevBtn.style.display = 'block';
            nextBtn.style.display = 'block';
            
            indicator.innerText = `Page ${currentPage} of ${totalPages}`;
            prevBtn.disabled = currentPage === 1;
            prevBtn.style.opacity = currentPage === 1 ? '0.5' : '1';
            nextBtn.disabled = currentPage === totalPages;
            nextBtn.style.opacity = currentPage === totalPages ? '0.5' : '1';
        }

        function prevPage() {
            if (currentPage > 1) {
                currentPage--;
                renderAppointments();
            }
        }

        function nextPage() {
            const totalPages = Math.ceil(filteredAppointments.length / itemsPerPage);
            if (currentPage < totalPages) {
                currentPage++;
                renderAppointments();
            }
        }

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
            setTimeout(() => { toast.classList.remove('show'); }, 3000);
        }

        bookingForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = bookingForm.querySelector('button[type="submit"]');
            const originalText = btn.innerText;
            btn.disabled = true;
            btn.innerText = 'Booking...';

            const formData = new FormData(bookingForm);
            formData.append('confirm_booking', '1');

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    closeModal();
                    Swal.fire({
                        title: "Booking Successful!",
                        text: data.message,
                        icon: "success",
                        draggable: true
                    }).then((result) => {
                        window.location.reload();
                    });
                } else {
                    showToast(data.message, 'error');
                    btn.disabled = false;
                    btn.innerText = originalText;
                }
            })
            .catch(err => {
                showToast('Network error', 'error');
                btn.disabled = false;
                btn.innerText = originalText;
            });
        });

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