<?php
include("connect.php");

session_start();
$auth_user_id = $_GET['auth_user_id'] ?? $_SESSION['user_id'] ?? null;
$current_page = 'schedule';

if (!$auth_user_id) { 
    if(isset($_POST['ajax'])) { echo json_encode(['success'=>false, 'message'=>'Unauthorized']); exit; }
    header("location: login.php"); exit; 
}

$user_name = "Supervisor"; 
$user_avatar = "image/user.png"; 
$sv_id = ""; 
$sv_room = "Office";

if (isset($conn)) {
    $sql_sv = "SELECT * FROM supervisor WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_sv)) {
        $stmt->bind_param("i", $auth_user_id); $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $row = $res->fetch_assoc();
            if (!empty($row['fyp_staffid'])) {
                $sv_id = $row['fyp_staffid'];
            } elseif (!empty($row['fyp_supervisorid'])) {
                $sv_id = $row['fyp_supervisorid'];
            }
            if (!empty($row['fyp_name'])) $user_name = $row['fyp_name'];
            if (!empty($row['fyp_profileimg'])) $user_avatar = $row['fyp_profileimg'];
            if (!empty($row['fyp_roomno'])) $sv_room = $row['fyp_roomno'];
        }
        $stmt->close();
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    header('Content-Type: application/json');

    if (isset($_POST['add_slot'])) {
        if (empty($sv_id)) {
            echo json_encode(['success' => false, 'message' => 'Supervisor ID not found.']);
            exit;
        }

        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $days_selected = $_POST['days'] ?? []; 
        
        $start_time = $_POST['start_hour'] . ':' . $_POST['start_min'] . ':00';
        $end_time   = $_POST['end_hour'] . ':' . $_POST['end_min'] . ':00';
        $location   = !empty($_POST['location']) ? $_POST['location'] : $sv_room;
        
        if ($start_time >= $end_time) {
            echo json_encode(['success' => false, 'message' => 'End time must be later than Start time.']);
            exit;
        } elseif (empty($days_selected)) {
            echo json_encode(['success' => false, 'message' => 'Please select at least one day.']);
            exit;
        }

        $sql_add = "INSERT INTO schedule_meeting (fyp_staffid, fyp_date, fyp_day, fyp_fromtime, fyp_totime, fyp_location, fyp_status) VALUES (?, ?, ?, ?, ?, ?, 'Available')";
        
        if ($stmt = $conn->prepare($sql_add)) {
            $count_added = 0;
            $current = strtotime($start_date);
            $end = strtotime($end_date);
            
            while ($current <= $end) {
                $current_date_str = date('Y-m-d', $current); 
                $day_name = date('l', $current); 
                
                if (in_array($day_name, $days_selected)) {
                    $stmt->bind_param("ssssss", $sv_id, $current_date_str, $day_name, $start_time, $end_time, $location);
                    if($stmt->execute()) {
                        $count_added++;
                    }
                }
                $current = strtotime('+1 day', $current);
            }
            $stmt->close();
            
            if ($count_added > 0) {
                echo json_encode(['success' => true, 'message' => "Successfully added $count_added slots."]);
            } else {
                echo json_encode(['success' => false, 'message' => 'No slots were added. Check date range.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error.']);
        }
        exit;
    }

    if (isset($_POST['edit_slot'])) {
        $sch_id = $_POST['schedule_id'];
        $new_date = $_POST['edit_date'];
        $new_day = date('l', strtotime($new_date));
        
        $new_start_time = $_POST['edit_start_hour'] . ':' . $_POST['edit_start_min'] . ':00';
        $new_end_time   = $_POST['edit_end_hour'] . ':' . $_POST['edit_end_min'] . ':00';
        $new_location   = $_POST['edit_location'];

        if ($new_start_time >= $new_end_time) {
            echo json_encode(['success' => false, 'message' => 'End time must be later than Start time.']);
            exit;
        }

        $sql_edit = "UPDATE schedule_meeting SET fyp_date = ?, fyp_day = ?, fyp_fromtime = ?, fyp_totime = ?, fyp_location = ? WHERE fyp_scheduleid = ? AND fyp_status = 'Available'";
        
        if ($stmt = $conn->prepare($sql_edit)) {
            $stmt->bind_param("sssssi", $new_date, $new_day, $new_start_time, $new_end_time, $new_location, $sch_id);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Slot updated successfully.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error updating slot.']);
            }
            $stmt->close();
        }
        exit;
    }

    if (isset($_POST['delete_slot'])) {
        $sch_id = $_POST['schedule_id'];
        $conn->query("DELETE FROM schedule_meeting WHERE fyp_scheduleid = '$sch_id' AND fyp_status = 'Available'");
        echo json_encode(['success' => true, 'message' => 'Slot deleted.']);
        exit;
    }
    
    if (isset($_POST['handle_appointment'])) {
        $app_id = $_POST['appointment_id'];
        $sch_id = $_POST['schedule_id'];
        $action = $_POST['action_type']; 
        
        if ($action == 'approve') {
            $conn->query("UPDATE appointment_meeting SET fyp_status = 'Approved' WHERE fyp_appointmentid = '$app_id'");
            echo json_encode(['success' => true, 'message' => 'Appointment Approved!']);
        } 
        elseif ($action == 'reject' || $action == 'cancel') {
            $new_status = ($action == 'reject') ? 'Rejected' : 'Cancelled';
            $conn->query("UPDATE appointment_meeting SET fyp_status = '$new_status' WHERE fyp_appointmentid = '$app_id'");
            $conn->query("UPDATE schedule_meeting SET fyp_status = 'Available' WHERE fyp_scheduleid = '$sch_id'");
            
            $msg = ($action == 'reject') ? 'Request Rejected.' : 'Appointment Cancelled.';
            echo json_encode(['success' => true, 'message' => $msg]);
        }
        exit;
    }
}

$pending_requests = [];
$upcoming_meetings = [];
$my_slots = [];

if (!empty($sv_id)) {
    $sql_pen = "SELECT am.*, s.fyp_studname, s.fyp_studid, sm.fyp_date, sm.fyp_day, sm.fyp_fromtime, sm.fyp_totime 
                FROM appointment_meeting am
                JOIN student s ON am.fyp_studid = s.fyp_studid
                JOIN schedule_meeting sm ON am.fyp_scheduleid = sm.fyp_scheduleid
                WHERE am.fyp_staffid = '$sv_id' AND am.fyp_status = 'Pending'
                ORDER BY sm.fyp_date ASC";
    $res_pen = $conn->query($sql_pen);
    if ($res_pen) { 
        while ($row = $res_pen->fetch_assoc()) {
            $row['fmt_date'] = $row['fyp_date'] . " (" . $row['fyp_day'] . ")";
            $row['fmt_time'] = date('H:i', strtotime($row['fyp_fromtime'])) . ' - ' . date('H:i', strtotime($row['fyp_totime']));
            $row['stud_name_safe'] = htmlspecialchars($row['fyp_studname']);
            $row['stud_id_safe'] = htmlspecialchars($row['fyp_studid']);
            $row['reason_safe'] = !empty($row['fyp_reason']) ? htmlspecialchars($row['fyp_reason']) : 'No reason provided';
            $pending_requests[] = $row;
        }
    }

    $sql_up = "SELECT am.*, s.fyp_studname, s.fyp_studid, sm.fyp_date, sm.fyp_day, sm.fyp_fromtime, sm.fyp_totime, sm.fyp_location
               FROM appointment_meeting am
               JOIN student s ON am.fyp_studid = s.fyp_studid
               JOIN schedule_meeting sm ON am.fyp_scheduleid = sm.fyp_scheduleid
               WHERE am.fyp_staffid = '$sv_id' AND am.fyp_status = 'Approved' AND sm.fyp_date >= CURDATE()
               ORDER BY sm.fyp_date ASC";
    $res_up = $conn->query($sql_up);
    if ($res_up) { 
        while ($row = $res_up->fetch_assoc()) {
            $row['fmt_date'] = $row['fyp_date']; 
            $row['fmt_time'] = date('H:i', strtotime($row['fyp_fromtime'])) . ' - ' . date('H:i', strtotime($row['fyp_totime']));
            $row['stud_name_safe'] = htmlspecialchars($row['fyp_studname']);
            $row['stud_id_safe'] = htmlspecialchars($row['fyp_studid']);
            $row['location_safe'] = htmlspecialchars($row['fyp_location']);
            $upcoming_formatted[] = $row;
            $upcoming_meetings[] = $row;
        }
    }

    $sql_slots = "SELECT * FROM schedule_meeting 
                  WHERE fyp_staffid = '$sv_id' AND fyp_status = 'Available' 
                  ORDER BY fyp_date ASC";
    $res_slots = $conn->query($sql_slots);
    if ($res_slots) { while ($row = $res_slots->fetch_assoc()) $my_slots[] = $row; }
}

$slots_json = json_encode($my_slots);

$menu_items = [
    'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home', 'link' => 'Supervisor_mainpage.php?page=dashboard'],
    'profile'   => ['name' => 'My Profile', 'icon' => 'fa-user', 'link' => 'supervisor_profile.php'],
    'students'  => [
        'name' => 'My Students', 
        'icon' => 'fa-users',
        'sub_items' => [
            'project_requests' => ['name' => 'Project Requests', 'icon' => 'fa-envelope-open-text', 'link' => 'supervisor_projectreq.php'],
            'student_list'     => ['name' => 'Student List', 'icon' => 'fa-list', 'link' => 'Supervisor_student_list.php'],
        ]
    ],
    'fyp_project' => [
        'name' => 'FYP Project',
        'icon' => 'fa-project-diagram',
        'sub_items' => [
            'propose_project' => ['name' => 'Propose Project', 'icon' => 'fa-plus-circle', 'link' => 'supervisor_purpose.php'],
            'my_projects'     => ['name' => 'My Projects', 'icon' => 'fa-folder-open', 'link' => 'Supervisor_manage_project.php'],
            'propose_assignment' => ['name' => 'Propose Assignment', 'icon' => 'fa-tasks', 'link' => 'supervisor_assignment_purpose.php'],
        ]
    ],
    'grading' => [
        'name' => 'Assessment',
        'icon' => 'fa-marker',
        'sub_items' => [
            'grade_assignment' => ['name' => 'Grade Assignments', 'icon' => 'fa-check-square', 'link' => 'Supervisor_assignment_grade.php'],
            'grade_mod' => ['name' => 'Moderator Grading', 'icon' => 'fa-gavel', 'link' => 'Moderator_assignment_grade.php'],
        ]
    ],
    'announcement' => [
        'name' => 'Announcement',
        'icon' => 'fa-bullhorn',
        'sub_items' => [
            'post_announcement' => ['name' => 'Post Announcement', 'icon' => 'fa-pen-square', 'link' => 'supervisor_announcement.php'],
            'view_announcements' => ['name' => 'View History', 'icon' => 'fa-history', 'link' => 'Supervisor_announcement_view.php'],
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
    <title>Manage Schedule</title>
    <link rel="icon" type="image/png" href="image/ladybug.png">
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
            overflow-y: auto;
            overflow-x: hidden;
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

        .dropdown-arrow {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            transition: transform 0.3s;
            font-size: 12px;
        }

        .menu-item.open .dropdown-arrow {
            transform: translateY(-50%) rotate(180deg);
        }

        .submenu {
            list-style: none;
            padding: 0;
            margin: 0;
            background-color: rgba(0,0,0,0.2);
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }

        .menu-item.open .submenu {
            max-height: 500px;
            transition: max-height 0.5s ease-in;
        }

        .submenu li > a {
            padding-left: 70px !important;
            font-size: 13px;
            height: 40px;
        }

        .menu-item > a {
            cursor: pointer;
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
            background: var(--card-bg);
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

        .container-cards {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .card {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            margin-bottom: 20px;
            transition: background 0.3s;
        }

        .card-header {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-color);
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 15px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .form-row { display: flex; gap: 15px; margin-bottom: 15px; }
        .form-group { flex: 1; }
        .form-group label { display: block; font-size: 13px; margin-bottom: 5px; color: var(--text-secondary); font-weight: 500; }
        .form-control { width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 6px; box-sizing: border-box; background: var(--card-bg); color: var(--text-color); }

        .time-select-wrapper { display: flex; align-items: center; gap: 5px; }
        .time-select { padding: 10px; border: 1px solid var(--border-color); border-radius: 6px; font-family: inherit; font-size: 14px; flex: 1; cursor: pointer; background: var(--card-bg); color: var(--text-color); }
        .time-sep { font-weight: bold; color: var(--text-secondary); }

        .days-checkbox-group { display: flex; flex-wrap: wrap; gap: 10px; }
        .day-label { display: flex; align-items: center; font-size: 13px; cursor: pointer; background: var(--slot-bg); padding: 5px 10px; border-radius: 20px; border: 1px solid var(--border-color); transition: all 0.2s; color: var(--text-color); }
        .day-label:hover { background: var(--border-color); }
        .day-label input { margin-right: 5px; }
        .day-label input:checked + span { font-weight: 600; color: var(--primary-color); }

        .btn-add { background: var(--primary-color); color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; width: 100%; transition: background 0.2s; }
        .btn-add:hover { background: var(--primary-hover); }

        .req-table { width: 100%; border-collapse: collapse; }
        .req-table th { text-align: left; padding: 10px; background: var(--slot-bg); font-size: 13px; color: var(--text-secondary); font-weight: 600; border-bottom: 1px solid var(--border-color); }
        .req-table td { padding: 12px 10px; border-bottom: 1px solid var(--border-color); font-size: 14px; color: var(--text-color); }
        .req-table tr:last-child td { border-bottom: none; }

        .badge { padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .bg-pending { background: #fff3cd; color: #856404; }

        .btn-sm { padding: 5px 10px; border: none; border-radius: 4px; font-size: 12px; cursor: pointer; color: white; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; transition: opacity 0.2s; }
        .btn-sm:hover { opacity: 0.9; }
        .btn-accept { background: #28a745; }
        .btn-reject { background: #dc3545; }
        .btn-cancel { background: #6c757d; }

        .btn-edit { background: var(--primary-color); color: white; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; border-radius: 4px; border:none; cursor: pointer; transition: background 0.2s; }
        .btn-edit:hover { background: var(--primary-hover); }
        .btn-delete { background: #dc3545; color: white; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; border-radius: 4px; border:none; cursor: pointer; transition: background 0.2s; }
        .btn-delete:hover { background: #c82333; }

        .empty-state { text-align: center; padding: 30px; color: var(--text-secondary); font-style: italic; }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-overlay.show { display: flex; }
        .modal-box { background: var(--card-bg); padding: 25px; border-radius: 12px; width: 90%; max-width: 500px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
        .modal-title { font-size: 18px; font-weight: 600; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; color: var(--text-color); }
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
        .btn-save { background: var(--primary-color); color: white; padding: 8px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: 500; transition: background 0.2s; }
        .btn-save:hover { background: var(--primary-hover); }
        .btn-close { background: var(--slot-bg); color: var(--text-color); padding: 8px 20px; border: 1px solid var(--border-color); border-radius: 6px; cursor: pointer; font-weight: 500; transition: background 0.2s; }
        .btn-close:hover { background: var(--border-color); }

        .pagination-controls { display: flex; justify-content: center; margin-top: 15px; gap: 10px; align-items: center; }
        .pg-btn { background: var(--slot-bg); border: 1px solid var(--border-color); padding: 5px 12px; border-radius: 4px; cursor: pointer; color: var(--text-color); }
        .pg-btn:disabled { opacity: 0.5; cursor: not-allowed; }

        .theme-toggle {
            cursor: pointer; padding: 8px; border-radius: 50%;
            background: var(--slot-bg); border: 1px solid var(--border-color);
            color: var(--text-color); display: flex; align-items: center;
            justify-content: center; width: 35px; height: 35px; margin-right: 15px;
        }
        .theme-toggle img { width: 20px; height: 20px; object-fit: contain; }

        @media (max-width: 1100px) { .container-cards { grid-template-columns: 1fr; } }
        @media (max-width: 900px) { .main-content-wrapper { margin-left: 0; width: 100%; } .page-header { flex-direction: column; gap: 15px; text-align: center; } }
    </style>
    <script>
        // Apply dark mode immediately
        const savedThemePre = localStorage.getItem('theme');
        if (savedThemePre === 'dark') {
            document.documentElement.classList.add('dark-mode');
        }
    </script>
</head>
<body>

    <nav class="main-menu">
        <ul>
            <?php foreach ($menu_items as $key => $item): ?>
                <?php 
                    $isActive = ($key == 'schedule'); 
                    $hasActiveChild = false;
                    if (isset($item['sub_items'])) {
                        foreach ($item['sub_items'] as $sub_key => $sub) {
                            if ($sub_key == $current_page) { $hasActiveChild = true; break; }
                        }
                    }
                    $linkUrl = isset($item['link']) ? $item['link'] : "#";
                    if ($linkUrl !== "#") {
                         $separator = (strpos($linkUrl, '?') !== false) ? '&' : '?';
                         $linkUrl .= $separator . "auth_user_id=" . urlencode($auth_user_id);
                    }
                    $hasSubmenu = isset($item['sub_items']);
                ?>
                <li class="menu-item <?php echo $hasActiveChild ? 'open' : ''; ?>">
                    <a href="<?php echo $hasSubmenu ? 'javascript:void(0)' : $linkUrl; ?>" class="<?php echo $isActive ? 'active' : ''; ?>" <?php if ($hasSubmenu): ?>onclick="toggleSubmenu(this)"<?php endif; ?>>
                        <i class="fa <?php echo $item['icon']; ?> nav-icon"></i><span class="nav-text"><?php echo $item['name']; ?></span><?php if ($hasSubmenu): ?><i class="fa fa-chevron-down dropdown-arrow"></i><?php endif; ?>
                    </a>
                    <?php if ($hasSubmenu): ?>
                        <ul class="submenu">
                            <?php foreach ($item['sub_items'] as $sub_key => $sub_item): 
                                $subLinkUrl = isset($sub_item['link']) ? $sub_item['link'] : "#";
                                if ($subLinkUrl !== "#") {
                                    $separator = (strpos($subLinkUrl, '?') !== false) ? '&' : '?';
                                    $subLinkUrl .= $separator . "auth_user_id=" . urlencode($auth_user_id);
                                }
                            ?>
                                <li><a href="<?php echo $subLinkUrl; ?>" class="<?php echo ($sub_key == $current_page) ? 'active' : ''; ?>"><i class="fa <?php echo $sub_item['icon']; ?> nav-icon"></i><span class="nav-text"><?php echo $sub_item['name']; ?></span></a></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
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
                <h1>My Schedule</h1>
                <p>Welcome back, <?php echo htmlspecialchars($user_name); ?></p>
            </div>
            <div class="logo-section">
                <img src="image/ladybug.png" alt="Logo" class="logo-img">
                <span class="system-title">FYP Portal</span>
            </div>
            <div class="user-section">
                <button class="theme-toggle" onclick="toggleDarkMode()" title="Toggle Dark Mode">
                    <img id="theme-icon" src="image/moon-solid-full.svg" alt="Toggle Theme">
                </button>
                <span class="user-badge">Supervisor</span>
                <?php if(!empty($user_avatar) && $user_avatar != 'image/user.png'): ?>
                    <img src="<?php echo htmlspecialchars($user_avatar); ?>" class="user-avatar" alt="Avatar">
                <?php else: ?>
                    <div class="user-avatar-placeholder">
                        <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="container-cards">
            <div class="card">
                <div class="card-header">Publish Availability (Batch)</div>
                <form id="addSlotForm" onsubmit="submitForm(event, 'addSlotForm', 'add_slot')">
                    <input type="hidden" name="ajax" value="1">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Start Date</label>
                            <input type="date" name="start_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group">
                            <label>End Date</label>
                            <input type="date" name="end_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label>Repeat on Days:</label>
                        <div class="days-checkbox-group">
                            <label class="day-label"><input type="checkbox" name="days[]" value="Monday"> <span>Mon</span></label>
                            <label class="day-label"><input type="checkbox" name="days[]" value="Tuesday"> <span>Tue</span></label>
                            <label class="day-label"><input type="checkbox" name="days[]" value="Wednesday"> <span>Wed</span></label>
                            <label class="day-label"><input type="checkbox" name="days[]" value="Thursday"> <span>Thu</span></label>
                            <label class="day-label"><input type="checkbox" name="days[]" value="Friday"> <span>Fri</span></label>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Start Time</label>
                            <div class="time-select-wrapper">
                                <select name="start_hour" class="time-select">
                                    <?php for($i=8; $i<=20; $i++): $h = str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                                        <option value="<?php echo $h; ?>"><?php echo $h; ?></option>
                                    <?php endfor; ?>
                                </select>
                                <span class="time-sep">:</span>
                                <select name="start_min" class="time-select">
                                    <option value="00">00</option><option value="15">15</option><option value="30">30</option><option value="45">45</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>End Time</label>
                            <div class="time-select-wrapper">
                                <select name="end_hour" class="time-select">
                                    <?php for($i=9; $i<=21; $i++): $h = str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                                        <option value="<?php echo $h; ?>"><?php echo $h; ?></option>
                                    <?php endfor; ?>
                                </select>
                                <span class="time-sep">:</span>
                                <select name="end_min" class="time-select">
                                    <option value="00">00</option><option value="15">15</option><option value="30">30</option><option value="45">45</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label>Location (Optional)</label>
                        <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($sv_room); ?>" placeholder="e.g. Online / Room 101">
                    </div>
                    <button type="submit" class="btn-add"><i class="fa fa-plus-circle"></i> Add Slots (Batch)</button>
                </form>
            </div>

            <div class="card">
                <div class="card-header">
                    My Available Slots
                    <span style="font-size:11px; color:var(--text-secondary); margin-left:10px; font-weight:normal;">(Showing all dates)</span>
                </div>
                <?php if (count($my_slots) > 0): ?>
                    <div style="max-height: 400px; overflow-y: auto;">
                        <table class="req-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($my_slots as $slot): ?>
                                    <tr id="row-<?php echo $slot['fyp_scheduleid']; ?>">
                                        <td>
                                            <div style="font-weight:600;"><?php echo $slot['fyp_date']; ?></div>
                                            <div style="font-size:12px; color:var(--text-secondary);"><?php echo $slot['fyp_day']; ?></div>
                                        </td>
                                        <td>
                                            <?php echo date('H:i', strtotime($slot['fyp_fromtime'])) . ' - ' . date('H:i', strtotime($slot['fyp_totime'])); ?>
                                            <div style="font-size:11px; color:var(--text-secondary);"><?php echo $slot['fyp_location']; ?></div>
                                        </td>
                                        <td>
                                            <div style="display:flex; gap:5px;">
                                                <button type="button" class="btn-edit" title="Manage / Edit" 
                                                    onclick="openEditModal('<?php echo $slot['fyp_scheduleid']; ?>', '<?php echo $slot['fyp_date']; ?>', '<?php echo $slot['fyp_fromtime']; ?>', '<?php echo $slot['fyp_totime']; ?>', '<?php echo htmlspecialchars($slot['fyp_location']); ?>')">
                                                    <i class="fa fa-edit"></i>
                                                </button>
                                                
                                                <button type="button" class="btn-delete" title="Delete" onclick="handleAction('delete_slot', '<?php echo $slot['fyp_scheduleid']; ?>')">
                                                    <i class="fa fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">No available slots posted yet.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header" style="border-left: 5px solid #d93025; padding-left:10px;">
                Incoming Appointment Requests <span class="badge bg-pending" style="margin-left:10px;"><?php echo count($pending_requests); ?> Pending</span>
            </div>
            
            <?php if (count($pending_requests) > 0): ?>
                <table class="req-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Requested Slot</th>
                            <th>Reason</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="pendingTableBody"></tbody>
                </table>
                <div id="pendingPagination" class="pagination-controls">
                    <button class="pg-btn" id="penPrev" onclick="changePage('pending', -1)"><i class="fa fa-chevron-left"></i></button>
                    <span id="penInd" style="font-size:13px; color:var(--text-secondary);">Page 1</span>
                    <button class="pg-btn" id="penNext" onclick="changePage('pending', 1)"><i class="fa fa-chevron-right"></i></button>
                </div>
            <?php else: ?>
                <div class="empty-state">No pending requests.</div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-header" style="border-left: 5px solid #28a745; padding-left:10px;">
                Upcoming Scheduled Meetings
            </div>
            
            <?php if (count($upcoming_meetings) > 0): ?>
                <table class="req-table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Student</th>
                            <th>Location</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="upcomingTableBody"></tbody>
                </table>
                <div id="upcomingPagination" class="pagination-controls">
                    <button class="pg-btn" id="upPrev" onclick="changePage('upcoming', -1)"><i class="fa fa-chevron-left"></i></button>
                    <span id="upInd" style="font-size:13px; color:var(--text-secondary);">Page 1</span>
                    <button class="pg-btn" id="upNext" onclick="changePage('upcoming', 1)"><i class="fa fa-chevron-right"></i></button>
                </div>
            <?php else: ?>
                <div class="empty-state">No upcoming meetings scheduled.</div>
            <?php endif; ?>
        </div>
    </div>

    <div id="editSlotModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-title">Manage Slot</div>
            <form id="editSlotForm" onsubmit="submitForm(event, 'editSlotForm', 'edit_slot')">
                <input type="hidden" name="ajax" value="1">
                <input type="hidden" name="schedule_id" id="edit_id">
                
                <div class="form-group" style="margin-bottom:15px;">
                    <label>Date</label>
                    <input type="date" name="edit_date" id="edit_date" class="form-control" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Start Time</label>
                        <div class="time-select-wrapper">
                            <select name="edit_start_hour" id="edit_start_hour" class="time-select">
                                <?php for($i=8; $i<=20; $i++): $h = str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                                    <option value="<?php echo $h; ?>"><?php echo $h; ?></option>
                                <?php endfor; ?>
                            </select>
                            <span class="time-sep">:</span>
                            <select name="edit_start_min" id="edit_start_min" class="time-select">
                                <option value="00">00</option><option value="15">15</option><option value="30">30</option><option value="45">45</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>End Time</label>
                        <div class="time-select-wrapper">
                            <select name="edit_end_hour" id="edit_end_hour" class="time-select">
                                <?php for($i=9; $i<=21; $i++): $h = str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                                    <option value="<?php echo $h; ?>"><?php echo $h; ?></option>
                                <?php endfor; ?>
                            </select>
                            <span class="time-sep">:</span>
                            <select name="edit_end_min" id="edit_end_min" class="time-select">
                                <option value="00">00</option><option value="15">15</option><option value="30">30</option><option value="45">45</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label>Location</label>
                    <input type="text" name="edit_location" id="edit_location" class="form-control" required>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-close" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn-save">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleSubmenu(element) {
            const menuItem = element.parentElement;
            const isOpen = menuItem.classList.contains('open');
            document.querySelectorAll('.menu-item').forEach(item => { if (item !== menuItem) item.classList.remove('open'); });
            if (isOpen) menuItem.classList.remove('open'); else menuItem.classList.add('open');
        }

        const editModal = document.getElementById('editSlotModal');

        function openEditModal(id, date, start_time, end_time, location) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_date').value = date;
            document.getElementById('edit_location').value = location;

            if(start_time) {
                const s = start_time.split(':');
                document.getElementById('edit_start_hour').value = s[0];
                document.getElementById('edit_start_min').value = s[1];
            }
            if(end_time) {
                const e = end_time.split(':');
                document.getElementById('edit_end_hour').value = e[0];
                document.getElementById('edit_end_min').value = e[1];
            }

            editModal.classList.add('show');
        }

        function closeEditModal() {
            editModal.classList.remove('show');
        }

        window.onclick = function(e) {
            if (e.target.classList.contains('modal-overlay')) {
                e.target.classList.remove('show');
            }
        }

        function submitForm(event, formId, actionName) {
            event.preventDefault();
            const form = document.getElementById(formId);
            const formData = new FormData(form);
            formData.append(actionName, '1');

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    closeEditModal();
                    Swal.fire({
                        title: 'Success!',
                        text: data.message,
                        icon: 'success',
                        confirmButtonColor: '#28a745'
                    }).then(() => location.reload());
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            })
            .catch(err => Swal.fire('Error', 'An unexpected error occurred.', 'error'));
        }

        function handleAction(actionType, id) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append(actionType, '1');
                    formData.append('schedule_id', id);
                    formData.append('ajax', '1');

                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if(data.success) {
                            document.getElementById('row-' + id).remove();
                            Swal.fire('Deleted!', data.message, 'success');
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    });
                }
            });
        }

        function handleAppointment(action, appId, schId) {
            let confirmTitle = action === 'approve' ? 'Approve Appointment?' : (action === 'reject' ? 'Reject Request?' : 'Cancel Appointment?');
            let confirmBtnColor = action === 'approve' ? '#28a745' : '#d33';
            
            Swal.fire({
                title: confirmTitle,
                text: "Proceed with this action?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: confirmBtnColor,
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('handle_appointment', '1');
                    formData.append('appointment_id', appId);
                    formData.append('schedule_id', schId);
                    formData.append('action_type', action);
                    formData.append('ajax', '1');

                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if(data.success) {
                            Swal.fire('Success!', data.message, 'success').then(() => location.reload());
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    });
                }
            });
        }

        const pendingData = <?php 
            $pending_formatted = [];
            foreach($pending_requests as $req) {
                $req['fmt_date'] = $req['fyp_date'] . " (" . $req['fyp_day'] . ")";
                $req['fmt_time'] = date('H:i', strtotime($req['fyp_fromtime'])) . ' - ' . date('H:i', strtotime($req['fyp_totime']));
                $req['stud_name_safe'] = htmlspecialchars($req['fyp_studname']);
                $req['stud_id_safe'] = htmlspecialchars($req['fyp_studid']);
                $req['reason_safe'] = !empty($req['fyp_reason']) ? htmlspecialchars($req['fyp_reason']) : 'No reason provided';
                $pending_formatted[] = $req;
            }
            echo json_encode($pending_formatted); 
        ?>;
        
        const upcomingData = <?php 
            $upcoming_formatted = [];
            foreach($upcoming_meetings as $up) {
                $up['fmt_date'] = $up['fyp_date']; 
                $up['fmt_time'] = date('H:i', strtotime($up['fyp_fromtime'])) . ' - ' . date('H:i', strtotime($up['fyp_totime']));
                $up['stud_name_safe'] = htmlspecialchars($up['fyp_studname']);
                $up['stud_id_safe'] = htmlspecialchars($up['fyp_studid']);
                $up['location_safe'] = htmlspecialchars($up['fyp_location']);
                $upcoming_formatted[] = $up;
            }
            echo json_encode($upcoming_formatted); 
        ?>;
        
        const itemsPerPage = 5;
        let pagePending = 1;
        let pageUpcoming = 1;

        function renderTable(type) {
            const data = type === 'pending' ? pendingData : upcomingData;
            const page = type === 'pending' ? pagePending : pageUpcoming;
            const tableBody = document.getElementById(type + 'TableBody');
            const indicator = document.getElementById(type === 'pending' ? 'penInd' : 'upInd');
            const prevBtn = document.getElementById(type === 'pending' ? 'penPrev' : 'upPrev');
            const nextBtn = document.getElementById(type === 'pending' ? 'penNext' : 'upNext');

            tableBody.innerHTML = '';
            
            if(data.length === 0) return;

            const start = (page - 1) * itemsPerPage;
            const end = start + itemsPerPage;
            const pageItems = data.slice(start, end);
            const totalPages = Math.ceil(data.length / itemsPerPage);

            pageItems.forEach(item => {
                const tr = document.createElement('tr');
                if (type === 'pending') {
                    tr.innerHTML = `
                        <td><strong>${item.stud_name_safe}</strong><div style="font-size:12px; color:var(--text-secondary);">${item.stud_id_safe}</div></td>
                        <td><div>${item.fmt_date}</div><div style="font-weight:600; color:var(--primary-color);">${item.fmt_time}</div></td>
                        <td><div style="max-width:200px; font-size:13px; color:var(--text-secondary);">${item.reason_safe}</div></td>
                        <td>
                            <div style="display:flex; gap:5px;">
                                <button type="button" class="btn-sm btn-accept" onclick="handleAppointment('approve', '${item.fyp_appointmentid}', '${item.fyp_scheduleid}')"><i class="fa fa-check"></i> Accept</button>
                                <button type="button" class="btn-sm btn-reject" onclick="handleAppointment('reject', '${item.fyp_appointmentid}', '${item.fyp_scheduleid}')"><i class="fa fa-times"></i> Reject</button>
                            </div>
                        </td>`;
                } else {
                     tr.innerHTML = `
                        <td><div style="font-weight:600; color:var(--text-color);">${item.fmt_date}</div><div>${item.fmt_time}</div></td>
                        <td>${item.stud_name_safe}<div style="font-size:12px; color:var(--text-secondary);">${item.stud_id_safe}</div></td>
                        <td>${item.location_safe}</td>
                        <td><button type="button" class="btn-sm btn-cancel" onclick="handleAppointment('cancel', '${item.fyp_appointmentid}', '${item.fyp_scheduleid}')"><i class="fa fa-ban"></i> Cancel</button></td>`;
                }
                tableBody.appendChild(tr);
            });

            indicator.innerText = `Page ${page} of ${totalPages}`;
            prevBtn.disabled = page === 1;
            nextBtn.disabled = page === totalPages;
        }

        function changePage(type, direction) {
            const data = type === 'pending' ? pendingData : upcomingData;
            const totalPages = Math.ceil(data.length / itemsPerPage);
            
            if (type === 'pending') {
                if (direction === 1 && pagePending < totalPages) pagePending++;
                else if (direction === -1 && pagePending > 1) pagePending--;
            } else {
                if (direction === 1 && pageUpcoming < totalPages) pageUpcoming++;
                else if (direction === -1 && pageUpcoming > 1) pageUpcoming--;
            }
            renderTable(type);
        }

        renderTable('pending');
        renderTable('upcoming');

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