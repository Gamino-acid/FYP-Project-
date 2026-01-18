<?php
include("connect.php");

$auth_user_id = $_GET['auth_user_id'] ?? null;
$current_page = 'schedule'; 

if (!$auth_user_id) { header("location: login.php"); exit; }

$swal_alert = null; 

$user_name = "Coordinator"; 
$user_avatar = "image/user.png"; 
$staff_id = ""; 
$my_room = "Office"; 

if (isset($conn)) {
    $sql_user = "SELECT fyp_username FROM `USER` WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_user)) {
        $stmt->bind_param("i", $auth_user_id); $stmt->execute();
        $res = $stmt->get_result(); if ($row = $res->fetch_assoc()) $user_name = $row['fyp_username'];
        $stmt->close();
    }

    $sql_coor = "SELECT * FROM coordinator WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_coor)) {
        $stmt->bind_param("i", $auth_user_id); $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $row = $res->fetch_assoc();
            if (!empty($row['fyp_staffid'])) $staff_id = $row['fyp_staffid'];
            if (!empty($row['fyp_name'])) $user_name = $row['fyp_name'];
            if (!empty($row['fyp_profileimg'])) $user_avatar = $row['fyp_profileimg'];
            if (!empty($row['fyp_roomno'])) $my_room = $row['fyp_roomno'];
        }
        $stmt->close();
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    if (isset($_POST['add_slot'])) {
        if (empty($staff_id)) {
            $swal_alert = ['icon' => 'error', 'title' => 'Error', 'text' => 'Staff ID not found for this profile.'];
        } else {
            $start_date = $_POST['start_date'];
            $end_date = $_POST['end_date'];
            $days_selected = $_POST['days'] ?? []; 
            
            $start_time = $_POST['start_hour'] . ':' . $_POST['start_min'] . ':00';
            $end_time   = $_POST['end_hour'] . ':' . $_POST['end_min'] . ':00';
            $location   = !empty($_POST['location']) ? $_POST['location'] : $my_room;
            
            if ($start_time >= $end_time) {
                $swal_alert = ['icon' => 'warning', 'title' => 'Invalid Time', 'text' => 'End time must be later than Start time.'];
            } elseif (empty($days_selected)) {
                $swal_alert = ['icon' => 'warning', 'title' => 'No Days Selected', 'text' => 'Please select at least one day of the week.'];
            } else {
                $sql_add = "INSERT INTO schedule_meeting (fyp_staffid, fyp_date, fyp_day, fyp_fromtime, fyp_totime, fyp_location, fyp_status) 
                            VALUES (?, ?, ?, ?, ?, ?, 'Available')";
                
                if ($stmt = $conn->prepare($sql_add)) {
                    $count_added = 0;
                    $current = strtotime($start_date);
                    $end = strtotime($end_date);
                    
                    while ($current <= $end) {
                        $current_date_str = date('Y-m-d', $current); 
                        $day_name = date('l', $current); 
                        
                        if (in_array($day_name, $days_selected)) {
                            $stmt->bind_param("ssssss", $staff_id, $current_date_str, $day_name, $start_time, $end_time, $location);
                            if($stmt->execute()) {
                                $count_added++;
                            }
                        }
                        $current = strtotime('+1 day', $current);
                    }
                    
                    if ($count_added > 0) {
                        $swal_alert = ['icon' => 'success', 'title' => 'Success!', 'text' => "$count_added slots published successfully."];
                    } else {
                        $swal_alert = ['icon' => 'info', 'title' => 'No Slots Added', 'text' => 'No matching days found in the selected date range.'];
                    }
                    $stmt->close();
                } else {
                    $swal_alert = ['icon' => 'error', 'title' => 'Database Error', 'text' => $conn->error];
                }
            }
        }
    }

    if (isset($_POST['edit_slot'])) {
        $sch_id = $_POST['schedule_id'];
        $new_date = $_POST['edit_date'];
        $new_day = date('l', strtotime($new_date));
        $new_start_time = $_POST['edit_start_hour'] . ':' . $_POST['edit_start_min'] . ':00';
        $new_end_time   = $_POST['edit_end_hour'] . ':' . $_POST['edit_end_min'] . ':00';
        $new_location   = $_POST['edit_location'];

        if ($new_start_time >= $new_end_time) {
            $swal_alert = ['icon' => 'warning', 'title' => 'Invalid Time', 'text' => 'End time must be later than Start time.'];
        } else {
            $sql_edit = "UPDATE schedule_meeting 
                         SET fyp_date = ?, fyp_day = ?, fyp_fromtime = ?, fyp_totime = ?, fyp_location = ? 
                         WHERE fyp_scheduleid = ? AND fyp_status = 'Available'";
            
            if ($stmt = $conn->prepare($sql_edit)) {
                $stmt->bind_param("sssssi", $new_date, $new_day, $new_start_time, $new_end_time, $new_location, $sch_id);
                if ($stmt->execute()) {
                    $swal_alert = ['icon' => 'success', 'title' => 'Updated', 'text' => 'Slot updated successfully.'];
                } else {
                    $swal_alert = ['icon' => 'error', 'title' => 'Error', 'text' => 'Failed to update slot.'];
                }
                $stmt->close();
            }
        }
    }

    if (isset($_POST['delete_slot'])) {
        $sch_id = $_POST['schedule_id'];
        $conn->query("DELETE FROM schedule_meeting WHERE fyp_scheduleid = '$sch_id' AND fyp_status = 'Available'");
        $swal_alert = ['icon' => 'success', 'title' => 'Deleted', 'text' => 'Slot removed successfully.'];
    }
    
    if (isset($_POST['handle_appointment'])) {
        $app_id = $_POST['appointment_id'];
        $sch_id = $_POST['schedule_id'];
        $action = $_POST['action_type']; 
        
        if ($action == 'approve') {
            $conn->query("UPDATE appointment_meeting SET fyp_status = 'Approved' WHERE fyp_appointmentid = '$app_id'");
            $swal_alert = ['icon' => 'success', 'title' => 'Approved', 'text' => 'Meeting request approved.'];
        } 
        elseif ($action == 'reject' || $action == 'cancel') {
            $new_status = ($action == 'reject') ? 'Rejected' : 'Cancelled';
            $conn->query("UPDATE appointment_meeting SET fyp_status = '$new_status' WHERE fyp_appointmentid = '$app_id'");
            $conn->query("UPDATE schedule_meeting SET fyp_status = 'Available' WHERE fyp_scheduleid = '$sch_id'");
            
            $msg = ($action == 'reject') ? 'Request rejected.' : 'Meeting cancelled.';
            $swal_alert = ['icon' => 'success', 'title' => 'Processed', 'text' => $msg];
        }
    }
}

$pending_requests = [];
$upcoming_meetings = [];
$my_slots = [];

if (!empty($staff_id)) {
    $sql_pen = "SELECT am.*, s.fyp_studname, s.fyp_studid, sm.fyp_date, sm.fyp_day, sm.fyp_fromtime, sm.fyp_totime 
                FROM appointment_meeting am
                JOIN student s ON am.fyp_studid = s.fyp_studid
                JOIN schedule_meeting sm ON am.fyp_scheduleid = sm.fyp_scheduleid
                WHERE am.fyp_staffid = '$staff_id' AND am.fyp_status = 'Pending'
                ORDER BY sm.fyp_date ASC";
    $res_pen = $conn->query($sql_pen);
    if ($res_pen) { while ($row = $res_pen->fetch_assoc()) $pending_requests[] = $row; }

    $sql_up = "SELECT am.*, s.fyp_studname, s.fyp_studid, sm.fyp_date, sm.fyp_day, sm.fyp_fromtime, sm.fyp_totime, sm.fyp_location
               FROM appointment_meeting am
               JOIN student s ON am.fyp_studid = s.fyp_studid
               JOIN schedule_meeting sm ON am.fyp_scheduleid = sm.fyp_scheduleid
               WHERE am.fyp_staffid = '$staff_id' AND am.fyp_status = 'Approved' AND sm.fyp_date >= CURDATE()
               ORDER BY sm.fyp_date ASC";
    $res_up = $conn->query($sql_up);
    if ($res_up) { while ($row = $res_up->fetch_assoc()) $upcoming_meetings[] = $row; }

    $sql_slots = "SELECT * FROM schedule_meeting 
                  WHERE fyp_staffid = '$staff_id' AND fyp_status = 'Available' 
                  ORDER BY fyp_date ASC";
    $res_slots = $conn->query($sql_slots);
    if ($res_slots) { while ($row = $res_slots->fetch_assoc()) $my_slots[] = $row; }
}

$menu_items = [
    'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home', 'link' => 'Coordinator_mainpage.php?page=dashboard'],
    'profile'   => ['name' => 'My Profile', 'icon' => 'fa-user', 'link' => 'Coordinator_profile.php'], 
    
     'management' => [
        'name' => 'User Management',
        'icon' => 'fa-users-cog',
        'sub_items' => [
            'manage_students' => ['name' => 'Student List', 'icon' => 'fa-user-graduate', 'link' => 'Coordinator_manage_users.php?tab=student'],
            'manage_supervisors' => ['name' => 'Supervisor List', 'icon' => 'fa-chalkboard-teacher', 'link' => 'Coordinator_manage_users.php?tab=supervisor'],
            'manage_quota' => ['name' => 'Supervisor Quota', 'icon' => 'fa-chalkboard-teacher', 'link' => 'Coordinator_manage_quota.php'],
        ]
    ],
    
    'project_mgmt' => [
        'name' => 'Project Manage', 
        'icon' => 'fa-tasks', 
        'sub_items' => [
            'propose_project' => ['name' => 'Propose Project', 'icon' => 'fa-plus-circle', 'link' => 'Coordinator_purpose.php'],
            'project_requests' => ['name' => 'Project Requests', 'icon' => 'fa-envelope-open-text', 'link' => 'Coordinator_projectreq.php'],
            'project_list' => ['name' => 'All Projects & Groups', 'icon' => 'fa-list-alt', 'link' => 'Coordinator_manage_project.php'],
        ]
    ],
    'assessment' => [
        'name' => 'Assessment', 
        'icon' => 'fa-clipboard-check', 
        'sub_items' => [
            'propose_assignment' => ['name' => 'Create Assignment', 'icon' => 'fa-plus', 'link' => 'Coordinator_assignment_purpose.php'],
            'grade_assignment' => ['name' => 'Grade Assignments', 'icon' => 'fa-check-square', 'link' => 'Coordinator_assignment_grade.php'], 
        ]
    ],
    'announcements' => [
        'name' => 'Announcements', 
        'icon' => 'fa-bullhorn', 
        'sub_items' => [
            'post_announcement' => ['name' => 'Post New', 'icon' => 'fa-pen', 'link' => 'Coordinator_announcement.php'], 
            'view_announcements' => ['name' => 'View History', 'icon' => 'fa-history', 'link' => 'Coordinator_announcement_view.php'],
        ]
    ],
    'schedule' => ['name' => 'My Schedule', 'icon' => 'fa-calendar-alt', 'link' => 'Coordinator_meeting.php'], 
    'allocation' => ['name' => 'Moderator Allocation', 'icon' => 'fa-people-arrows', 'link' => 'Coordinator_allocation.php'],
    'data_mgmt' => ['name' => 'Data Management', 'icon' => 'fa-database', 'link' => 'Coordinator_data_io.php'],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coordinator Schedule</title>
    <link rel="icon" type="image/png" href="image/ladybug.png?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
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
            top: 0; bottom: 0; left: 0;
            width: 60px;
            overflow-y: auto; overflow-x: hidden;
            transition: width .05s linear;
            z-index: 1000;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }

        .main-menu:hover, nav.main-menu.expanded {
            width: 250px;
            overflow: visible;
        }

        .main-menu > ul { margin: 7px 0; padding: 0; list-style: none; }
        .main-menu li { position: relative; display: block; width: 250px; }
        
        .main-menu li > a {
            position: relative; display: table;
            border-collapse: collapse; border-spacing: 0;
            color: var(--sidebar-text); font-size: 14px;
            text-decoration: none; transition: all .1s linear; width: 100%;
        }

        .main-menu .nav-icon {
            position: relative; display: table-cell;
            width: 60px; height: 46px; 
            text-align: center; vertical-align: middle; font-size: 18px;
        }

        .main-menu .nav-text {
            position: relative; display: table-cell;
            vertical-align: middle; width: 190px; padding-left: 10px; white-space: nowrap;
        }

        .main-menu li:hover > a, nav.main-menu li.active > a {
            color: #fff; background-color: var(--sidebar-hover);
            border-left: 4px solid #fff; 
        }

        .main-menu > ul.logout { position: absolute; left: 0; bottom: 0; width: 100%; }
        
        .dropdown-arrow {
            position: absolute; right: 15px; top: 50%;
            transform: translateY(-50%); transition: transform 0.3s; font-size: 12px;
        }

        .menu-item.open .dropdown-arrow { transform: translateY(-50%) rotate(180deg); }
        
        .submenu {
            list-style: none; padding: 0; margin: 0;
            background-color: rgba(0,0,0,0.2); max-height: 0; overflow: hidden;
            transition: max-height 0.3s ease-out;
        }

        .menu-item.open .submenu { max-height: 500px; transition: max-height 0.5s ease-in; }
        .submenu li > a { padding-left: 70px !important; font-size: 13px; height: 40px; }
        .menu-item > a { cursor: pointer; }

        .main-content-wrapper {
            margin-left: 60px; flex: 1; padding: 20px;
            width: calc(100% - 60px); transition: margin-left .05s linear;
        }

        .page-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 25px; background: var(--card-bg); padding: 20px;
            border-radius: 12px; box-shadow: var(--card-shadow); transition: background 0.3s;
        }

        .welcome-text h1 { margin: 0; font-size: 24px; color: var(--primary-color); font-weight: 600; }
        .welcome-text p { margin: 5px 0 0; color: var(--text-secondary); font-size: 14px; }
        
        .logo-section { display: flex; align-items: center; gap: 12px; }
        .logo-img { height: 40px; width: auto; background: white; padding: 2px; border-radius: 6px; }
        .system-title { font-size: 20px; font-weight: 600; color: var(--primary-color); letter-spacing: 0.5px; }

        .user-section { display: flex; align-items: center; gap: 10px; }
        .user-badge { font-size: 13px; color: var(--text-secondary); background: var(--slot-bg); padding: 5px 10px; border-radius: 20px; }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
        
        .user-avatar-placeholder {
            width: 40px; height: 40px; border-radius: 50%;
            background: #0056b3; color: white; display: flex; align-items: center;
            justify-content: center; font-weight: bold;
        }
        
        .theme-toggle {
            cursor: pointer; padding: 8px; border-radius: 50%;
            background: var(--slot-bg); border: 1px solid var(--border-color);
            color: var(--text-color); display: flex; align-items: center;
            justify-content: center; width: 35px; height: 35px; margin-right: 15px;
        }
        .theme-toggle img { width: 20px; height: 20px; object-fit: contain; }

        .grid-container { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        
        .card {
            background: var(--card-bg); padding: 25px; border-radius: 12px;
            box-shadow: var(--card-shadow); transition: background 0.3s;
        }
        
        .card-header {
            font-size: 18px; font-weight: 600; color: var(--text-color);
            border-bottom: 1px solid var(--border-color); padding-bottom: 15px;
            margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;
        }

        .form-row { display: flex; gap: 15px; margin-bottom: 15px; }
        .form-group { flex: 1; }
        .form-group label { display: block; font-size: 13px; margin-bottom: 5px; color: var(--text-secondary); font-weight: 500; }
        
        .form-control, .time-select {
            width: 100%; padding: 10px; border: 1px solid var(--border-color);
            border-radius: 6px; box-sizing: border-box; background: var(--bg-color);
            color: var(--text-color); font-family: inherit;
        }
        
        .time-select-wrapper { display: flex; align-items: center; gap: 5px; }
        .time-select { flex: 1; cursor: pointer; }
        .time-sep { font-weight: bold; color: var(--text-secondary); }

        .days-checkbox-group { display: flex; flex-wrap: wrap; gap: 10px; }
        .day-label {
            display: flex; align-items: center; font-size: 13px; cursor: pointer;
            background: var(--slot-bg); padding: 5px 10px; border-radius: 20px;
            border: 1px solid var(--border-color); color: var(--text-color);
            transition: all 0.2s;
        }
        .day-label:hover { background: var(--primary-color); color: white; }
        .day-label input { margin-right: 5px; }
        .day-label input:checked + span { font-weight: 600; }

        .btn-add {
            background: var(--primary-color); color: white; border: none;
            padding: 10px 20px; border-radius: 6px; cursor: pointer;
            font-weight: 600; width: 100%; transition: background 0.2s;
        }
        .btn-add:hover { background: var(--primary-hover); }

        .req-table { width: 100%; border-collapse: collapse; }
        .req-table th {
            text-align: left; padding: 10px; background: var(--slot-bg);
            font-size: 13px; color: var(--text-secondary); font-weight: 600;
            border-bottom: 2px solid var(--border-color);
        }
        .req-table td {
            padding: 12px 10px; border-bottom: 1px solid var(--border-color);
            font-size: 14px; color: var(--text-color);
        }
        .req-table tr:last-child td { border-bottom: none; }
        
        .badge { padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .bg-pending { background: #fff3cd; color: #856404; }
        
        .btn-sm { padding: 5px 10px; border: none; border-radius: 4px; font-size: 12px; cursor: pointer; color: white; }
        .btn-accept { background: #28a745; }
        .btn-reject { background: #dc3545; }
        .btn-cancel { background: #6c757d; }
        .btn-edit { background: #007bff; color: white; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; border-radius: 4px; border:none; cursor: pointer; }
        .btn-delete { background: #dc3545; color: white; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; border-radius: 4px; border:none; cursor: pointer; }

        .empty-state { text-align: center; padding: 30px; color: var(--text-secondary); font-style: italic; }

        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;
        }
        .modal-overlay.show { display: flex; }
        .modal-box {
            background: var(--card-bg); padding: 25px; border-radius: 12px;
            width: 90%; max-width: 500px; box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            color: var(--text-color);
        }
        .modal-title { font-size: 18px; font-weight: 600; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; }
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
        .btn-save { background: #28a745; color: white; padding: 8px 20px; border: none; border-radius: 6px; cursor: pointer; }
        .btn-close { background: #6c757d; color: white; padding: 8px 20px; border: none; border-radius: 6px; cursor: pointer; }

        @media (max-width: 1100px) { .grid-container { grid-template-columns: 1fr; } }
        @media (max-width: 900px) { 
            .main-content-wrapper { margin-left: 0; width: 100%; }
            .page-header { flex-direction: column; gap: 15px; text-align: center; }
        }
    </style>
</head>
<body>

    <nav class="main-menu">
        <ul>
            <?php foreach ($menu_items as $key => $item): ?>
                <?php 
                    $isActive = ($key == $current_page);
                    $hasActiveChild = false;
                    if (isset($item['sub_items'])) {
                        foreach ($item['sub_items'] as $sub_key => $sub) {
                            if ($sub_key == $current_page) { $hasActiveChild = true; break; }
                        }
                    }
                    $linkUrl = isset($item['link']) ? $item['link'] : "#";
                    if ($linkUrl !== "#" && strpos($linkUrl, '.php') !== false) {
                         $separator = (strpos($linkUrl, '?') !== false) ? '&' : '?';
                         $linkUrl .= $separator . "auth_user_id=" . urlencode($auth_user_id);
                    }
                    $hasSubmenu = isset($item['sub_items']);
                ?>
                <li class="menu-item <?php echo ($hasActiveChild || $isActive) ? 'open active' : ''; ?>">
                    <a href="<?php echo $hasSubmenu ? 'javascript:void(0)' : $linkUrl; ?>" 
                       class="<?php echo $isActive ? 'active' : ''; ?>"
                       <?php if ($hasSubmenu): ?>onclick="toggleSubmenu(this)"<?php endif; ?>>
                        <i class="fa <?php echo $item['icon']; ?> nav-icon"></i>
                        <span class="nav-text"><?php echo $item['name']; ?></span>
                        <?php if ($hasSubmenu): ?>
                            <i class="fa fa-chevron-down dropdown-arrow"></i>
                        <?php endif; ?>
                    </a>
                    
                    <?php if ($hasSubmenu): ?>
                        <ul class="submenu">
                            <?php foreach ($item['sub_items'] as $sub_key => $sub_item): 
                                $subLinkUrl = isset($sub_item['link']) ? $sub_item['link'] : "#";
                                if ($subLinkUrl !== "#" && strpos($subLinkUrl, '.php') !== false) {
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
                <p>Manage your availability and appointments, <?php echo htmlspecialchars($user_name); ?></p>
            </div>
            
            <div class="logo-section">
                <img src="image/ladybug.png" alt="Logo" class="logo-img">
                <span class="system-title">FYP Portal</span>
            </div>

            <div class="user-section">
                <button class="theme-toggle" onclick="toggleDarkMode()" title="Toggle Dark Mode">
                    <img id="theme-icon" src="image/moon-solid-full.svg" alt="Toggle Theme">
                </button>
                <span class="user-badge">Coordinator</span>
                <?php if(!empty($user_avatar) && $user_avatar !== 'image/user.png'): ?>
                    <img src="<?php echo htmlspecialchars($user_avatar); ?>" class="user-avatar" alt="Avatar">
                <?php else: ?>
                    <div class="user-avatar-placeholder">
                        <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid-container">
            <div class="card">
                <div class="card-header">Publish Availability (Batch)</div>
                <form method="POST" id="addSlotForm">
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
                                    <option value="00">00</option>
                                    <option value="15">15</option>
                                    <option value="30">30</option>
                                    <option value="45">45</option>
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
                                    <option value="00">00</option>
                                    <option value="15">15</option>
                                    <option value="30">30</option>
                                    <option value="45">45</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label>Location (Optional)</label>
                        <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($my_room); ?>" placeholder="e.g. Online / Room 101">
                    </div>
                    
                    <button type="submit" name="add_slot" class="btn-add"><i class="fa fa-plus-circle"></i> Add Slots</button>
                </form>
            </div>

            <div class="card">
                <div class="card-header">
                    My Available Slots
                    <span style="font-size:11px; color:var(--text-secondary); margin-left:10px; font-weight:normal;">(Showing all)</span>
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
                                    <tr>
                                        <td>
                                            <div><?php echo $slot['fyp_date']; ?></div>
                                            <div style="font-size:12px; color:var(--text-secondary);"><?php echo $slot['fyp_day']; ?></div>
                                        </td>
                                        <td>
                                            <?php echo date('H:i', strtotime($slot['fyp_fromtime'])) . ' - ' . date('H:i', strtotime($slot['fyp_totime'])); ?>
                                            <div style="font-size:11px; color:var(--text-secondary);"><?php echo $slot['fyp_location']; ?></div>
                                        </td>
                                        <td>
                                            <div style="display:flex; gap:5px;">
                                                <button type="button" class="btn-edit" 
                                                    onclick="openEditModal('<?php echo $slot['fyp_scheduleid']; ?>', '<?php echo $slot['fyp_date']; ?>', '<?php echo $slot['fyp_fromtime']; ?>', '<?php echo $slot['fyp_totime']; ?>', '<?php echo htmlspecialchars($slot['fyp_location']); ?>')">
                                                    <i class="fa fa-edit"></i>
                                                </button>
                                                
                                                <form method="POST" id="deleteForm_<?php echo $slot['fyp_scheduleid']; ?>">
                                                    <input type="hidden" name="schedule_id" value="<?php echo $slot['fyp_scheduleid']; ?>">
                                                    <input type="hidden" name="delete_slot" value="1">
                                                    <button type="button" class="btn-delete" onclick="confirmDelete('deleteForm_<?php echo $slot['fyp_scheduleid']; ?>')">
                                                        <i class="fa fa-trash"></i>
                                                    </button>
                                                </form>
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
                    <tbody>
                        <?php foreach ($pending_requests as $req): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($req['fyp_studname']); ?></strong>
                                    <div style="font-size:12px; color:var(--text-secondary);"><?php echo htmlspecialchars($req['fyp_studid']); ?></div>
                                </td>
                                <td>
                                    <div><?php echo $req['fyp_date'] . " (" . $req['fyp_day'] . ")"; ?></div>
                                    <div style="font-weight:600; color:var(--primary-color);">
                                        <?php echo date('H:i', strtotime($req['fyp_fromtime'])) . ' - ' . date('H:i', strtotime($req['fyp_totime'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="max-width:200px; font-size:13px; color:var(--text-secondary);">
                                        <?php echo !empty($req['fyp_reason']) ? htmlspecialchars($req['fyp_reason']) : 'No reason provided'; ?>
                                    </div>
                                </td>
                                <td>
                                    <form method="POST" style="display:flex; gap:5px;">
                                        <input type="hidden" name="appointment_id" value="<?php echo $req['fyp_appointmentid']; ?>">
                                        <input type="hidden" name="schedule_id" value="<?php echo $req['fyp_scheduleid']; ?>">
                                        
                                        <button type="submit" name="handle_appointment" value="approve" class="btn-sm btn-accept" onclick="this.form.action_type.value='approve'">
                                            <i class="fa fa-check"></i> Accept
                                        </button>
                                        <button type="submit" name="handle_appointment" value="reject" class="btn-sm btn-reject" onclick="this.form.action_type.value='reject'">
                                            <i class="fa fa-times"></i> Reject
                                        </button>
                                        <input type="hidden" name="action_type" value="">
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">No pending requests.</div>
            <?php endif; ?>
        </div>

        <div class="card" style="margin-top:20px;">
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
                    <tbody>
                        <?php foreach ($upcoming_meetings as $up): ?>
                            <tr>
                                <td>
                                    <div style="font-weight:600;"><?php echo $up['fyp_date']; ?></div>
                                    <div><?php echo date('H:i', strtotime($up['fyp_fromtime'])) . ' - ' . date('H:i', strtotime($up['fyp_totime'])); ?></div>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($up['fyp_studname']); ?>
                                    <div style="font-size:12px; color:var(--text-secondary);"><?php echo htmlspecialchars($up['fyp_studid']); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($up['fyp_location']); ?></td>
                                <td>
                                    <form method="POST" id="cancelForm_<?php echo $up['fyp_appointmentid']; ?>">
                                        <input type="hidden" name="appointment_id" value="<?php echo $up['fyp_appointmentid']; ?>">
                                        <input type="hidden" name="schedule_id" value="<?php echo $up['fyp_scheduleid']; ?>">
                                        <input type="hidden" name="action_type" value="cancel">
                                        <input type="hidden" name="handle_appointment" value="1">
                                        <button type="button" class="btn-sm btn-cancel" onclick="confirmCancel('cancelForm_<?php echo $up['fyp_appointmentid']; ?>')">
                                            <i class="fa fa-ban"></i> Cancel
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">No upcoming meetings scheduled.</div>
            <?php endif; ?>
        </div>

    </div>

    <div id="editSlotModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-title">Manage Slot</div>
            <form method="POST">
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
                                <option value="00">00</option>
                                <option value="15">15</option>
                                <option value="30">30</option>
                                <option value="45">45</option>
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
                                <option value="00">00</option>
                                <option value="15">15</option>
                                <option value="30">30</option>
                                <option value="45">45</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label>Location</label>
                    <input type="text" name="edit_location" id="edit_location" class="form-control" required>
                </div>

                <div class="modal-footer">
                    <button type="submit" name="edit_slot" class="btn-save">Save Changes</button>
                    <button type="button" class="btn-close" onclick="closeEditModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function toggleSubmenu(element) {
            const menuItem = element.parentElement;
            const isOpen = menuItem.classList.contains('open');
            document.querySelectorAll('.menu-item').forEach(item => {
                if (item !== menuItem) item.classList.remove('open');
            });
            menuItem.classList.toggle('open');
        }

        function toggleDarkMode() {
            document.body.classList.toggle('dark-mode');
            const isDark = document.body.classList.contains('dark-mode');
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
            const iconImg = document.getElementById('theme-icon');
            if (iconImg) iconImg.src = isDark ? 'image/sun-solid-full.svg' : 'image/moon-solid-full.svg';
        }

        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            document.body.classList.add('dark-mode');
            const iconImg = document.getElementById('theme-icon');
            if(iconImg) iconImg.src = 'image/sun-solid-full.svg';
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
        function closeEditModal() { editModal.classList.remove('show'); }
        window.onclick = function(e) { if (e.target.classList.contains('modal-overlay')) closeEditModal(); }

        function confirmDelete(formId) {
            Swal.fire({
                title: 'Are you sure?',
                text: "Do you want to delete this available slot?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById(formId).submit();
                }
            })
        }

        function confirmCancel(formId) {
            Swal.fire({
                title: 'Cancel Appointment?',
                text: "This action cannot be undone.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, cancel it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById(formId).submit();
                }
            })
        }

        <?php if ($swal_alert): ?>
        Swal.fire({
            icon: '<?php echo $swal_alert['icon']; ?>',
            title: '<?php echo $swal_alert['title']; ?>',
            text: '<?php echo $swal_alert['text']; ?>',
            confirmButtonColor: '#0056b3'
        }).then(() => {
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>