<?php
include("connect.php");
session_start();

$auth_user_id = $_GET['auth_user_id'] ?? $_SESSION['user_id'] ?? null;
$current_page = 'grade_assignment'; 

if (!$auth_user_id) {
    echo "<script>window.location.href='login.php';</script>";
    exit;
}

$user_name = "Coordinator"; 
$user_avatar = "image/user.png"; 
$sv_id = ""; 

if (isset($conn)) {
    $sql_user = "SELECT fyp_username FROM `USER` WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_user)) {
        $stmt->bind_param("i", $auth_user_id); 
        $stmt->execute();
        $res = $stmt->get_result(); 
        if ($row = $res->fetch_assoc()) $user_name = $row['fyp_username'];
        $stmt->close();
    }

    $sql_sv = "SELECT * FROM supervisor WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_sv)) {
        $stmt->bind_param("i", $auth_user_id); 
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $sv_id = !empty($row['fyp_staffid']) ? $row['fyp_staffid'] : $row['fyp_supervisorid'];
            if (!empty($row['fyp_name'])) $user_name = $row['fyp_name'];
            if (!empty($row['fyp_profileimg'])) $user_avatar = $row['fyp_profileimg'];
        }
        $stmt->close();
    }

    if (empty($sv_id)) {
        $sql_coor = "SELECT * FROM coordinator WHERE fyp_userid = ?";
        if ($stmt = $conn->prepare($sql_coor)) {
            $stmt->bind_param("i", $auth_user_id); 
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows > 0) {
                $row = $res->fetch_assoc();
                $sv_id = !empty($row['fyp_staffid']) ? $row['fyp_staffid'] : $row['fyp_coordinatorid'];
                if (!empty($row['fyp_name'])) $user_name = $row['fyp_name'];
                if (!empty($row['fyp_profileimg'])) $user_avatar = $row['fyp_profileimg'];
            }
            $stmt->close();
        }
    }
}

$swal_alert = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $date_now = date('Y-m-d H:i:s');
    $assign_id = $_POST['assignment_id'];
    $stud_id = $_POST['student_id'];
    $marks = $_POST['marks'];
    $feedback = $_POST['feedback'];
    
    $new_status = '';
    $msg = '';
    $icon = 'success';

    if (isset($_POST['return_grade'])) {
        $new_status = 'Graded';
        $msg = "Assignment graded successfully!";
    } elseif (isset($_POST['return_revision'])) {
        $new_status = 'Need Revision';
        $msg = "Assignment returned for revision!";
        $icon = 'warning';
    }

    if ($new_status) {
        $check_sql = "SELECT fyp_submissionid FROM assignment_submission WHERE fyp_assignmentid = ? AND fyp_studid = ?";
        if ($stmt = $conn->prepare($check_sql)) {
            $stmt->bind_param("is", $assign_id, $stud_id);
            $stmt->execute();
            $stmt->store_result();
            
            if ($stmt->num_rows > 0) {
                $sql_upd = "UPDATE assignment_submission 
                            SET fyp_marks = ?, fyp_feedback = ?, fyp_submission_status = ?, fyp_graded_date = ? 
                            WHERE fyp_assignmentid = ? AND fyp_studid = ?";
                $stmt_upd = $conn->prepare($sql_upd);
                $stmt_upd->bind_param("isssis", $marks, $feedback, $new_status, $date_now, $assign_id, $stud_id);
                $stmt_upd->execute();
            } else {
                $sql_ins = "INSERT INTO assignment_submission (fyp_assignmentid, fyp_studid, fyp_marks, fyp_feedback, fyp_submission_status, fyp_graded_date) 
                            VALUES (?, ?, ?, ?, ?, ?)";
                $stmt_ins = $conn->prepare($sql_ins);
                $stmt_ins->bind_param("isissi", $assign_id, $stud_id, $marks, $feedback, $new_status, $date_now);
                $stmt_ins->execute();
            }
            
            $swal_alert = [
                'title' => ($new_status == 'Graded') ? 'Graded!' : 'Returned!',
                'text' => $msg,
                'icon' => $icon
            ];
        }
    }
}

$view_assignment_id = $_GET['view_id'] ?? null;
$assignments_list = [];
$students_to_grade = [];
$current_assignment = null;

$sort_by = $_GET['sort_by'] ?? 'DESC'; 
$filter_type = $_GET['filter_type'] ?? 'All';

if (!empty($sv_id)) {
    if (!$view_assignment_id) {
        $sql_list = "SELECT a.*,
                            (SELECT COUNT(*) FROM fyp_registration r JOIN student s ON r.fyp_studid = s.fyp_studid 
                             WHERE r.fyp_staffid = a.fyp_staffid 
                             AND (
                                (a.fyp_target_id = 'ALL' AND (
                                    (a.fyp_assignment_type = 'Individual' AND s.fyp_group = 'Individual') OR 
                                    (a.fyp_assignment_type = 'Group' AND s.fyp_group = 'Group')
                                )) OR 
                                (a.fyp_target_id != 'ALL' AND (
                                    (a.fyp_assignment_type = 'Individual' AND s.fyp_studid = a.fyp_target_id) OR
                                    (a.fyp_assignment_type = 'Group' AND s.fyp_studid IN (SELECT leader_id FROM student_group WHERE group_id = a.fyp_target_id UNION SELECT invitee_id FROM group_request WHERE group_id = a.fyp_target_id AND request_status = 'Accepted'))
                                ))
                             )) as total_students,
                             
                            (SELECT COUNT(*) FROM assignment_submission sub WHERE sub.fyp_assignmentid = a.fyp_assignmentid AND sub.fyp_submission_status IN ('Turned In', 'Late Turned In', 'Resubmitted')) as submitted_count,
                            
                            (SELECT COUNT(*) FROM assignment_submission sub WHERE sub.fyp_assignmentid = a.fyp_assignmentid AND sub.fyp_submission_status = 'Graded') as graded_count,

                            (SELECT COUNT(*) FROM assignment_submission sub WHERE sub.fyp_assignmentid = a.fyp_assignmentid AND sub.fyp_submission_status = 'Need Revision') as revision_count
                            
                      FROM assignment a 
                      WHERE a.fyp_staffid = ?"; 

        $types = "s";
        $params = [$sv_id];

        if ($filter_type != 'All') {
            $sql_list .= " AND a.fyp_assignment_type = ?";
            $types .= "s";
            $params[] = $filter_type;
        }
        $sql_list .= " ORDER BY a.fyp_datecreated $sort_by";

        if ($stmt = $conn->prepare($sql_list)) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $target_display_name = 'All Students'; 
                $target_id = $row['fyp_target_id'];
                $target_type = $row['fyp_assignment_type'];

                if ($target_id != 'ALL') {
                    if ($target_type == 'Group') {
                        $g_name_sql = "SELECT group_name FROM student_group WHERE group_id = '$target_id'";
                        $g_name_res = $conn->query($g_name_sql);
                        if ($g_name_res && $g_row = $g_name_res->fetch_assoc()) $target_display_name = "Group: " . $g_row['group_name'];
                    } else {
                        $s_name_sql = "SELECT fyp_studname FROM student WHERE fyp_studid = '$target_id'";
                        $s_name_res = $conn->query($s_name_sql);
                        if ($s_name_res && $s_row = $s_name_res->fetch_assoc()) $target_display_name = "Student: " . $s_row['fyp_studname'];
                    }
                } else {
                    $target_display_name = ($target_type == 'Group') ? 'All Groups' : 'All Individual Students';
                }
                
                $row['target_display_name'] = $target_display_name;
                
                $total_count = $row['total_students'];
                $submitted_count = $row['submitted_count'];
                $graded_count = $row['graded_count'];
                $revision_count = $row['revision_count']; 
                
                $not_submitted = $total_count - ($submitted_count + $graded_count + $revision_count);
                if ($not_submitted < 0) $not_submitted = 0;

                $row['stats'] = ['not_submitted' => $not_submitted, 'submitted' => $submitted_count, 'graded' => $graded_count, 'revision' => $revision_count];
                $assignments_list[] = $row;
            }
            $stmt->close();
        }

    } else {
        $sql_detail = "SELECT * FROM assignment WHERE fyp_assignmentid = ? AND fyp_staffid = ?";
        if ($stmt = $conn->prepare($sql_detail)) {
            $stmt->bind_param("is", $view_assignment_id, $sv_id);
            $stmt->execute();
            $res_d = $stmt->get_result();
            $current_assignment = $res_d->fetch_assoc();
            $stmt->close();
        }

        if ($current_assignment) {
            $target_type = $current_assignment['fyp_assignment_type'];
            $target_id = $current_assignment['fyp_target_id']; 

            $base_fields = "s.fyp_studid, s.fyp_studname, s.fyp_group, 
                            g.fyp_marks, g.fyp_feedback, g.fyp_submission_status, g.fyp_submission_date, g.fyp_submitted_file";
            
            $join_part = "LEFT JOIN assignment_submission g ON (s.fyp_studid = g.fyp_studid AND g.fyp_assignmentid = '$view_assignment_id')";

            $sql_studs = "";
            
            if ($target_id == 'ALL') {
                $sql_studs = "SELECT $base_fields 
                              FROM fyp_registration r 
                              JOIN student s ON r.fyp_studid = s.fyp_studid 
                              $join_part 
                              WHERE r.fyp_staffid = '$sv_id'";
                              
                if ($target_type == 'Individual') {
                    $sql_studs .= " AND s.fyp_group = 'Individual'";
                } elseif ($target_type == 'Group') {
                    $sql_studs .= " AND s.fyp_group = 'Group'";
                }
                
            } else {
                if ($target_type == 'Individual') {
                    $sql_studs = "SELECT $base_fields FROM student s $join_part WHERE s.fyp_studid = '$target_id'";
                } else {
                    $sql_studs = "SELECT $base_fields FROM student s $join_part WHERE s.fyp_studid IN (
                                      SELECT leader_id FROM student_group WHERE group_id = '$target_id'
                                      UNION
                                      SELECT invitee_id FROM group_request WHERE group_id = '$target_id' AND request_status = 'Accepted'
                                  )";
                }
            }
            
            if (!empty($sql_studs)) {
                $res_s = $conn->query($sql_studs);
                if($res_s) {
                    while ($row = $res_s->fetch_assoc()) {
                        
                        $row['display_group_name'] = '';
                        $g_query = "SELECT group_name FROM student_group WHERE leader_id = '{$row['fyp_studid']}' UNION SELECT sg.group_name FROM group_request gr JOIN student_group sg ON gr.group_id = sg.group_id WHERE gr.invitee_id = '{$row['fyp_studid']}' AND gr.request_status = 'Accepted' LIMIT 1";
                        $g_result = $conn->query($g_query);
                        if ($g_result && $g_data = $g_result->fetch_assoc()) {
                             $row['display_group_name'] = $g_data['group_name'];
                        }

                        $inherited = false;
                        if ($target_type == 'Group' && (empty($row['fyp_submission_status']) || $row['fyp_submission_status'] == 'Not Turned In' || $row['fyp_submission_status'] == 'Viewed')) {
                            $leader_id = null;
                            $chk_l = $conn->query("SELECT leader_id FROM student_group WHERE leader_id = '{$row['fyp_studid']}'");
                            if ($chk_l->num_rows > 0) $leader_id = $row['fyp_studid']; 
                            else {
                                $chk_m = $conn->query("SELECT sg.leader_id FROM group_request gr JOIN student_group sg ON gr.group_id = sg.group_id WHERE gr.invitee_id = '{$row['fyp_studid']}' AND gr.request_status = 'Accepted' LIMIT 1");
                                if ($lm = $chk_m->fetch_assoc()) $leader_id = $lm['leader_id'];
                            }

                            if ($leader_id && $leader_id != $row['fyp_studid']) {
                                $l_sub = $conn->query("SELECT fyp_submission_status, fyp_submission_date, fyp_submitted_file FROM assignment_submission WHERE fyp_assignmentid = '$view_assignment_id' AND fyp_studid = '$leader_id'");
                                if ($l_row = $l_sub->fetch_assoc()) {
                                    if (!empty($l_row['fyp_submission_status']) && $l_row['fyp_submission_status'] != 'Not Turned In' && $l_row['fyp_submission_status'] != 'Viewed') {
                                        $row['fyp_submission_status'] = $l_row['fyp_submission_status']; 
                                        $row['fyp_submission_date'] = $l_row['fyp_submission_date'];     
                                        $row['fyp_submitted_file'] = $l_row['fyp_submitted_file'];       
                                        $inherited = true; 
                                    }
                                }
                            }
                        }

                        $raw_status = $row['fyp_submission_status'];
                        if (empty($raw_status)) $raw_status = 'Not Turned In';
                        
                        $display_status = $raw_status;
                        
                        if (($raw_status == 'Turned In' || $raw_status == 'Resubmitted') && !empty($row['fyp_submission_date'])) {
                            if ($row['fyp_submission_date'] > $current_assignment['fyp_deadline']) {
                                $display_status = 'Late Turned In';
                            }
                        }
                        $row['final_status'] = $display_status;
                        $row['is_inherited'] = $inherited; 

                        $students_to_grade[] = $row;
                    }
                }
            }
        }
    }
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
    <title>Grade Assignments - Coordinator</title>
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
        .user-avatar-placeholder { width: 40px; height: 40px; border-radius: 50%; background: #0056b3; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; }

        .content-card {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
        }
        
        .filter-bar { background: var(--slot-bg); padding: 20px; border-radius: 12px; margin-bottom: 25px; border: 1px solid var(--border-color); display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; }
        .filter-group { flex: 1; min-width: 150px; }
        .filter-group label { display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px; color: var(--text-secondary); }
        .filter-input, .filter-select { width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 14px; box-sizing: border-box; background: var(--card-bg); color: var(--text-color); }
        .btn-filter { background: var(--primary-color); color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 500; transition: background 0.2s; height: 42px; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-filter:hover { background: var(--primary-hover); }
        
        .ass-card { border: 1px solid var(--border-color); background: var(--card-bg); border-radius: 8px; padding: 20px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; transition: all 0.2s; box-shadow: var(--card-shadow); }
        .ass-card:hover { transform: translateY(-2px); box-shadow: 0 6px 12px rgba(0,0,0,0.1); }
        .ass-info { flex: 1; }
        .ass-title { font-weight: 600; font-size: 16px; color: var(--text-color); margin-bottom: 5px; }
        .ass-meta { font-size: 13px; color: var(--text-secondary); display: flex; gap: 15px; }
        .ass-stats { display: flex; gap: 15px; margin-left: 20px; }
        
        .stat-badge { font-size: 11px; padding: 4px 8px; border-radius: 4px; background: var(--slot-bg); color: var(--text-color); font-weight: 500; }
        .stat-badge i { margin-right: 4px; }
        .stat-submitted { color: #155724; background: #d4edda; }
        .stat-graded { color: #004085; background: #cce5ff; }
        .stat-pending { color: #856404; background: #fff3cd; }
        .stat-revision { color: #721c24; background: #f8d7da; }

        .btn-view { padding: 8px 16px; background: var(--primary-color); color: white; text-decoration: none; border-radius: 6px; font-size: 13px; white-space: nowrap; font-weight: 500; transition: background 0.2s; }
        .btn-view:hover { background: var(--primary-hover); }

        .back-link { display: inline-flex; align-items: center; gap: 5px; margin-bottom: 15px; color: var(--text-secondary); text-decoration: none; font-size: 14px; font-weight: 500; }
        .back-link:hover { color: var(--primary-color); }

        .grade-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .grade-table th { background: var(--slot-bg); text-align: left; padding: 15px; color: var(--text-secondary); font-weight: 600; border-bottom: 2px solid var(--border-color); font-size: 13px; }
        .grade-table td { padding: 15px; border-bottom: 1px solid var(--border-color); color: var(--text-color); vertical-align: middle; font-size: 14px; }
        .grade-table tr:hover { background-color: var(--slot-bg); }

        .stud-status-badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; margin-top: 5px; }
        .st-NotTurnedIn { background: #e2e3e5; color: #6c757d; }
        .st-Viewed { background: #cce5ff; color: #004085; }
        .st-TurnedIn { background: #d4edda; color: #155724; }
        .st-LateTurnedIn { background: #f8d7da; color: #721c24; }
        .st-Graded { background: #d1ecf1; color: #0c5460; }
        .st-NeedRevision { background: #fff3cd; color: #856404; }
        .st-Resubmitted { background: #d4edda; color: #155724; border: 2px solid #28a745; }

        .form-control-sm { width: 100%; padding: 6px; border: 1px solid var(--border-color); border-radius: 4px; font-size: 13px; background: var(--card-bg); color: var(--text-color); margin-bottom: 0; }
        
        .action-buttons { display: flex; gap: 5px; }
        .btn-return { padding: 6px 12px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 500; display: inline-flex; align-items: center; gap: 4px; }
        .btn-revision { padding: 6px 12px; background: #ffc107; color: #212529; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 500; display: inline-flex; align-items: center; gap: 4px; }
        .btn-return:hover { background: #218838; }
        .btn-revision:hover { background: #e0a800; }
        .btn-return:disabled, .btn-revision:disabled { opacity: 0.6; cursor: not-allowed; background: #6c757d; color: white; }

        .file-link { cursor: pointer; display: inline-flex; align-items: center; gap: 5px; font-size: 13px; color: var(--primary-color); text-decoration: none; padding: 4px 8px; background: var(--slot-bg); border-radius: 4px; border: 1px solid var(--border-color); transition: all 0.2s; margin-top: 5px; }
        .file-link:hover { background: var(--border-color); }
        .no-file { font-size: 13px; color: var(--text-secondary); font-style: italic; margin-top: 5px; display: block; }
        .inherited-badge { font-size: 10px; color: var(--text-secondary); background: var(--slot-bg); padding: 2px 5px; border-radius: 4px; margin-left: 5px; border: 1px solid var(--border-color); }

        .theme-toggle { cursor: pointer; padding: 8px; border-radius: 50%; background: var(--slot-bg); border: 1px solid var(--border-color); color: var(--text-color); display: flex; align-items: center; justify-content: center; width: 35px; height: 35px; margin-right: 15px; }
        .theme-toggle img { width: 20px; height: 20px; object-fit: contain; }

        @media (max-width: 900px) { 
            .main-content-wrapper { margin-left: 0; width: 100%; } 
            .page-header { flex-direction: column; gap: 15px; text-align: center; } 
            .ass-card { flex-direction: column; align-items: flex-start; gap: 15px; }
            .ass-stats { margin-left: 0; flex-wrap: wrap; }
            .grade-table, .grade-table tbody, .grade-table tr, .grade-table td { display: block; width: 100%; }
            .grade-table thead { display: none; }
            .grade-table tr { margin-bottom: 15px; border: 1px solid var(--border-color); border-radius: 8px; padding: 10px; }
            .grade-table td { border: none; padding: 5px 0; }
        }
    </style>
</head>
<body>

    <nav class="main-menu">
        <ul>
            <?php foreach ($menu_items as $key => $item): ?>
                <?php 
                    $isActive = ($key == 'assessment'); 
                    $hasActiveChild = false;
                    if (isset($item['sub_items'])) {
                        foreach ($item['sub_items'] as $sub_key => $sub) {
                            if ($sub_key == $current_page) { $hasActiveChild = true; break; }
                        }
                    }
                    $linkUrl = isset($item['link']) ? $item['link'] : "#";
                    if (strpos($linkUrl, '.php') !== false) {
                         $separator = (strpos($linkUrl, '?') !== false) ? '&' : '?';
                         $linkUrl .= $separator . "auth_user_id=" . urlencode($auth_user_id);
                    }
                    $hasSubmenu = isset($item['sub_items']);
                ?>
                <li class="menu-item <?php echo ($hasActiveChild || ($isActive && $hasSubmenu)) ? 'open active' : ''; ?>">
                    <a href="<?php echo $linkUrl; ?>" class="menu-link <?php echo $isActive ? 'active' : ''; ?>" <?php if ($hasSubmenu): ?>onclick="toggleSubmenu(this)"<?php endif; ?>>
                        <i class="fa <?php echo $item['icon']; ?> nav-icon"></i>
                        <span class="nav-text"><?php echo $item['name']; ?></span>
                        <?php if ($hasSubmenu): ?><i class="fa fa-chevron-down dropdown-arrow"></i><?php endif; ?>
                    </a>
                    <?php if (isset($item['sub_items'])): ?>
                        <ul class="submenu">
                            <?php foreach ($item['sub_items'] as $sub_key => $sub_item): 
                                $subLinkUrl = isset($sub_item['link']) ? $sub_item['link'] : "#";
                                if (strpos($subLinkUrl, '.php') !== false) {
                                    $separator = (strpos($subLinkUrl, '?') !== false) ? '&' : '?';
                                    $subLinkUrl .= $separator . "auth_user_id=" . urlencode($auth_user_id);
                                }
                                $isSubActive = ($sub_key == $current_page); 
                            ?>
                                <li><a href="<?php echo $subLinkUrl; ?>" class="menu-link <?php echo $isSubActive ? 'active' : ''; ?>">
                                    <i class="fa <?php echo $sub_item['icon']; ?> nav-icon"></i> <span class="nav-text"><?php echo $sub_item['name']; ?></span>
                                </a></li>
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
                <h1>Grade Assignments</h1>
                <p>Review student submissions and provide feedback.</p>
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

        <div class="content-card">
            <?php if (!$view_assignment_id): ?>
                <div style="margin-bottom:20px;">
                    <h2 style="margin:0; color:var(--text-color); font-size: 18px;"><i class="fa fa-list-alt"></i> Assignment List</h2>
                </div>

                <form method="GET" class="filter-bar">
                    <input type="hidden" name="auth_user_id" value="<?php echo htmlspecialchars($auth_user_id); ?>">
                    <div class="filter-group">
                        <label>Sort:</label>
                        <select name="sort_by" class="filter-select">
                            <option value="DESC" <?php echo $sort_by=='DESC'?'selected':''; ?>>Newest First</option>
                            <option value="ASC" <?php echo $sort_by=='ASC'?'selected':''; ?>>Oldest First</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Type:</label>
                        <select name="filter_type" class="filter-select">
                            <option value="All">All Types</option>
                            <option value="Individual" <?php echo $filter_type=='Individual'?'selected':''; ?>>Individual</option>
                            <option value="Group" <?php echo $filter_type=='Group'?'selected':''; ?>>Group</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-filter"><i class="fa fa-filter"></i> Apply</button>
                </form>

                <?php if (count($assignments_list) > 0): ?>
                    <div class="ass-list">
                        <?php foreach ($assignments_list as $ass): ?>
                            <div class="ass-card">
                                <div class="ass-info">
                                    <div class="ass-title"><?php echo htmlspecialchars($ass['fyp_title']); ?></div>
                                    <div class="ass-meta">
                                        <span><i class="fa fa-tag"></i> <?php echo $ass['fyp_assignment_type']; ?></span>
                                        <span><i class="fa fa-calendar-alt"></i> Due: <?php echo date('M d, Y', strtotime($ass['fyp_deadline'])); ?></span>
                                        <span><i class="fa fa-bullseye"></i> <?php echo $ass['target_display_name']; ?></span>
                                    </div>
                                </div>
                                
                                <div class="ass-stats">
                                    <div class="stat-badge stat-submitted">
                                        <i class="fa fa-file-upload"></i> Submitted: <?php echo $ass['stats']['submitted']; ?>
                                    </div>
                                    <div class="stat-badge stat-pending">
                                        <i class="fa fa-clock"></i> Pending: <?php echo $ass['stats']['not_submitted']; ?>
                                    </div>
                                    <div class="stat-badge stat-graded">
                                        <i class="fa fa-check"></i> Graded: <?php echo $ass['stats']['graded']; ?>
                                    </div>
                                    <div class="stat-badge stat-revision">
                                        <i class="fa fa-undo"></i> Revise: <?php echo $ass['stats']['revision']; ?>
                                    </div>
                                </div>
                                
                                <div style="margin-left:20px;">
                                    <a href="?auth_user_id=<?php echo $auth_user_id; ?>&view_id=<?php echo $ass['fyp_assignmentid']; ?>" class="btn-view">Grade Students</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align:center; color:var(--text-secondary); padding:40px;">
                        <i class="fa fa-folder-open" style="font-size:48px; opacity:0.3; margin-bottom:15px;"></i>
                        <p>No assignments found.</p>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <a href="?auth_user_id=<?php echo $auth_user_id; ?>" class="back-link"><i class="fa fa-arrow-left"></i> Back to Assignments</a>
                
                <div style="margin-bottom:20px; border-bottom:1px solid var(--border-color); padding-bottom:15px;">
                    <h2 style="margin:0; color:var(--text-color); font-size: 18px;">Grading: <?php echo htmlspecialchars($current_assignment['fyp_title']); ?></h2>
                    <div style="font-size:13px; color:var(--text-secondary); margin-top:5px;">Deadline: <?php echo date('F d, Y h:i A', strtotime($current_assignment['fyp_deadline'])); ?></div>
                </div>
                
                <?php if (count($students_to_grade) > 0): ?>
                    <table class="grade-table">
                        <thead>
                            <tr>
                                <th style="width:25%;">Student Info</th>
                                <th style="width:20%;">Status & File</th>
                                <th style="width:15%;">Marks (0-100)</th>
                                <th style="width:25%;">Feedback</th>
                                <th style="width:15%;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students_to_grade as $stud): 
                                $status = $stud['final_status'];
                                $cssStatus = str_replace(' ', '', $status);
                                $canGrade = ($status == 'Turned In' || $status == 'Late Turned In' || $status == 'Resubmitted' || $status == 'Graded' || $status == 'Need Revision');
                            ?>
                                <tr>
                                    <td>
                                        <div style="font-weight:600; color:var(--text-color);"><?php echo htmlspecialchars($stud['fyp_studname']); ?></div>
                                        <div style="font-size:12px; color:var(--text-secondary); margin-top:2px;"><?php echo htmlspecialchars($stud['fyp_studid']); ?></div>
                                        <?php if(!empty($stud['display_group_name'])): ?>
                                            <div style="font-size:11px; color:var(--primary-color); margin-top:4px;">
                                                <i class="fa fa-users"></i> <?php echo htmlspecialchars($stud['display_group_name']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="stud-status-badge st-<?php echo $cssStatus; ?>"><?php echo $status; ?></span>
                                        <?php if(!empty($stud['fyp_submission_date'])): ?>
                                            <div style="font-size:11px; color:var(--text-secondary); margin-top:4px;">
                                                <?php echo date('M d, H:i', strtotime($stud['fyp_submission_date'])); ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if(!empty($stud['fyp_submitted_file'])): ?>
                                            <div onclick="viewFile('<?php echo addslashes($stud['fyp_submitted_file']); ?>')" class="file-link">
                                                <i class="fa fa-download"></i> View File
                                            </div>
                                            <?php if (!empty($stud['is_inherited'])): ?>
                                                <span class="inherited-badge" title="Submitted by group leader">Leader</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="no-file">No file</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <form method="POST">
                                        <input type="hidden" name="assignment_id" value="<?php echo $view_assignment_id; ?>">
                                        <input type="hidden" name="student_id" value="<?php echo $stud['fyp_studid']; ?>">
                                        
                                        <td>
                                            <input type="number" name="marks" class="form-control-sm" value="<?php echo $stud['fyp_marks']; ?>" min="0" max="100" placeholder="0">
                                        </td>
                                        <td>
                                            <input type="text" name="feedback" class="form-control-sm" value="<?php echo htmlspecialchars($stud['fyp_feedback']); ?>" placeholder="Enter comments...">
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button type="submit" name="return_grade" class="btn-return" title="Grade" <?php echo $canGrade ? '' : 'disabled'; ?>>
                                                    <i class="fa fa-check"></i>
                                                </button>
                                                <button type="submit" name="return_revision" class="btn-revision" title="Revision" <?php echo $canGrade ? '' : 'disabled'; ?>>
                                                    <i class="fa fa-undo"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </form>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="text-align:center; color:var(--text-secondary); padding:40px;">No students found for this assignment target.</div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleSubmenu(element) {
            const menuItem = element.parentElement;
            const isOpen = menuItem.classList.contains('open');
            document.querySelectorAll('.menu-item').forEach(item => { if (item !== menuItem) item.classList.remove('open'); });
            if (isOpen) menuItem.classList.remove('open'); else menuItem.classList.add('open');
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

        function viewFile(url) {
            if (!url || url.trim() === '') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No file path found associated with this submission.'
                });
                return;
            }

            fetch(url, { method: 'HEAD' })
                .then(response => {
                    if (response.ok) {
                        window.open(url, '_blank');
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'File Not Found',
                            text: 'The file could not be located on the server (404).'
                        });
                    }
                })
                .catch(() => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Access Error',
                        text: 'Could not verify file accessibility or file is missing.'
                    });
                });
        }

        <?php if ($swal_alert): ?>
            Swal.fire({
                title: "<?php echo $swal_alert['title']; ?>",
                text: "<?php echo $swal_alert['text']; ?>",
                icon: "<?php echo $swal_alert['icon']; ?>",
                confirmButtonColor: '#0056b3'
            });
        <?php endif; ?>
    </script>
</body>
</html>