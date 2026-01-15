<?php
// ====================================================
// std_request_status.php - 组队管理 (Redesigned UI)
// ====================================================
include("connect.php");

// 1. 验证用户登录
$auth_user_id = $_GET['auth_user_id'] ?? null;
if (!$auth_user_id) { header("location: login.php"); exit; }

// 2. 获取当前登录学生信息
$stud_data = [];
$current_stud_id = '';
$current_stud_name = 'Student';
$current_stud_group_status = 'Individual';
$current_academic_id = 0;

if (isset($conn)) {
    $sql_user = "SELECT fyp_username FROM `USER` WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_user)) { 
        $stmt->bind_param("i", $auth_user_id); $stmt->execute(); 
        $res = $stmt->get_result(); 
        if ($row = $res->fetch_assoc()) $current_stud_name = $row['fyp_username']; 
        $stmt->close(); 
    }
    
    $sql_stud = "SELECT * FROM STUDENT WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_stud)) { 
        $stmt->bind_param("i", $auth_user_id); $stmt->execute(); 
        $res = $stmt->get_result(); 
        if ($row = $res->fetch_assoc()) { 
            $stud_data = $row; 
            $current_stud_id = $row['fyp_studid'];
            if (!empty($row['fyp_studname'])) $current_stud_name = $row['fyp_studname'];
            if (!empty($row['fyp_group'])) $current_stud_group_status = $row['fyp_group'];
            if (!empty($row['fyp_academicid'])) $current_academic_id = $row['fyp_academicid'];
        }
        $stmt->close(); 
    }
}

// ====================================================
// PRE-CALCULATION: DETERMINE GROUP STATUS
// ====================================================
$my_group = null; 
$my_role = '';    

if ($current_stud_id) {
    $sql_leader = "SELECT * FROM student_group WHERE leader_id = ?";
    if ($stmt = $conn->prepare($sql_leader)) {
        $stmt->bind_param("s", $current_stud_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $my_group = $row;
            $my_role = 'Leader';
        }
    }

    if (!$my_group) {
        $sql_member = "SELECT g.*, gr.request_id 
                       FROM group_request gr 
                       JOIN student_group g ON gr.group_id = g.group_id 
                       WHERE gr.invitee_id = ? AND gr.request_status = 'Accepted'";
        if ($stmt = $conn->prepare($sql_member)) {
            $stmt->bind_param("s", $current_stud_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $my_group = $row;
                $my_role = 'Member';
            }
        }
    }
}

// ====================================================
// MODULE A: PROJECT APPLICATION STATUS & LOCK CHECK
// ====================================================
$my_project_request = null;
$is_team_locked = false; 

if ($current_stud_id) {
    $applicant_id_to_check = $current_stud_id; 
    
    if ($my_group) {
        $applicant_id_to_check = $my_group['leader_id'];
    }

    $req_sql = "SELECT pr.*, 
                   p.fyp_projecttitle, 
                   p.fyp_projecttype, 
                   COALESCE(s.fyp_name, c.fyp_name) as sv_name, 
                   COALESCE(s.fyp_email, c.fyp_email) as sv_email
            FROM project_request pr 
            LEFT JOIN project p ON pr.fyp_projectid = p.fyp_projectid 
            LEFT JOIN supervisor s ON p.fyp_staffid = s.fyp_staffid
            LEFT JOIN coordinator c ON p.fyp_staffid = c.fyp_staffid
            WHERE pr.fyp_studid = ? 
            ORDER BY pr.fyp_datecreated DESC LIMIT 1";

    if ($stmt = $conn->prepare($req_sql)) {
        $stmt->bind_param("s", $applicant_id_to_check);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $my_project_request = $row;
                $status = $row['fyp_requeststatus'];
                if ($status == 'Pending' || $status == 'Approve' || $status == 'Approved') {
                    $is_team_locked = true;
                }
            }
        }
        $stmt->close();
    }
}

// ====================================================
// MODULE B: TEAM MANAGEMENT LOGIC (ACTIONS)
// ====================================================

// 4. AJAX 搜索功能
if (isset($_GET['action']) && $_GET['action'] == 'search_students') {
    header('Content-Type: application/json');
    $keyword = $_GET['keyword'] ?? '';
    
    if (strlen($keyword) < 1) { echo json_encode([]); exit; }
    $keyword = "%" . $keyword . "%";
    
    $sql = "SELECT s.fyp_studid, s.fyp_studname, s.fyp_studid
            FROM STUDENT s
            LEFT JOIN student_group g ON s.fyp_studid = g.leader_id
            WHERE s.fyp_studname LIKE ? 
            AND s.fyp_userid != ? 
            AND s.fyp_group = 'Individual'
            AND g.group_id IS NULL
            AND s.fyp_academicid = ? 
            LIMIT 5";
            
    $result = [];
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("sii", $keyword, $auth_user_id, $current_academic_id);
        $stmt->execute(); 
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $result[] = [
                'studid' => $row['fyp_studid'], 
                'name' => $row['fyp_studname'],
                'id' => $row['fyp_studid']
            ];
        }
    }
    echo json_encode($result); exit;
}

// 5. GET Actions (Kick, Leave, Disband, Accept, Reject)

if (isset($_GET['action']) && $_GET['action'] == 'KickMember' && isset($_GET['tid'])) {
    if ($is_team_locked) {
        echo "<script>alert('Action Failed: Team is locked.'); window.location.href='std_request_status.php?auth_user_id=".urlencode($auth_user_id)."';</script>";
    } elseif ($my_role == 'Leader' && $my_group) {
        $target_member_id = $_GET['tid'];
        $gid = $my_group['group_id'];
        
        $stmt = $conn->prepare("DELETE FROM group_request WHERE group_id = ? AND invitee_id = ?");
        $stmt->bind_param("is", $gid, $target_member_id);
        if ($stmt->execute()) {
            $conn->query("UPDATE STUDENT SET fyp_group = 'Individual' WHERE fyp_studid = '$target_member_id'");
            $conn->query("UPDATE student_group SET status = 'Recruiting' WHERE group_id = $gid");
            echo "<script>alert('Member kicked successfully.'); window.location.href='std_request_status.php?auth_user_id=".urlencode($auth_user_id)."';</script>";
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] == 'LeaveTeam') {
    if ($is_team_locked) {
        echo "<script>alert('Action Failed: Team is locked.'); window.location.href='std_request_status.php?auth_user_id=".urlencode($auth_user_id)."';</script>";
    } elseif ($my_role == 'Member' && $my_group) {
        $gid = $my_group['group_id'];
        
        $stmt = $conn->prepare("DELETE FROM group_request WHERE group_id = ? AND invitee_id = ?");
        $stmt->bind_param("is", $gid, $current_stud_id);
        if ($stmt->execute()) {
            $conn->query("UPDATE STUDENT SET fyp_group = 'Individual' WHERE fyp_studid = '$current_stud_id'");
            $conn->query("UPDATE student_group SET status = 'Recruiting' WHERE group_id = $gid");
            echo "<script>alert('You have left the team.'); window.location.href='std_request_status.php?auth_user_id=".urlencode($auth_user_id)."';</script>";
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] == 'DisbandGroup' && $my_group) {
    if ($is_team_locked) {
        echo "<script>alert('Action Failed: Team is locked.'); window.location.href='std_request_status.php?auth_user_id=".urlencode($auth_user_id)."';</script>";
    } elseif ($my_role == 'Leader') {
        $gid = $my_group['group_id'];
        
        $sql_m = "SELECT invitee_id FROM group_request WHERE group_id = ? AND request_status = 'Accepted'";
        if ($stmt = $conn->prepare($sql_m)) {
            $stmt->bind_param("i", $gid);
            $stmt->execute();
            $res = $stmt->get_result();
            while($row = $res->fetch_assoc()){
                $mem_id = $row['invitee_id'];
                $conn->query("UPDATE STUDENT SET fyp_group = 'Individual' WHERE fyp_studid = '$mem_id'");
            }
        }
        
        $conn->query("DELETE FROM group_request WHERE group_id = $gid");
        $conn->query("DELETE FROM student_group WHERE group_id = $gid");
        $conn->query("UPDATE STUDENT SET fyp_group = 'Individual' WHERE fyp_studid = '$current_stud_id'");

        echo "<script>alert('Team Disbanded.'); window.location.href='std_request_status.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
    }
}

if (isset($_GET['action']) && isset($_GET['req_id'])) {
    $req_id = $_GET['req_id'];
    $sql_req = "SELECT gr.*, g.leader_id FROM group_request gr JOIN student_group g ON gr.group_id = g.group_id WHERE gr.request_id = ? AND gr.invitee_id = ?";
    $stmt_r = $conn->prepare($sql_req);
    $stmt_r->bind_param("is", $req_id, $current_stud_id);
    $stmt_r->execute();
    $res_r = $stmt_r->get_result();
    
    if ($row_req = $res_r->fetch_assoc()) {
        $group_id = $row_req['group_id'];
        $leader_id = $row_req['leader_id'];
        
        if ($_GET['action'] == 'AcceptInvite') {
            if ($is_team_locked) {
                echo "<script>alert('Action Failed: You are locked in a process.'); window.location.href='std_request_status.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
                exit;
            }

            $chk_leader_acd = $conn->query("SELECT fyp_academicid FROM STUDENT WHERE fyp_studid = '$leader_id'")->fetch_assoc();
            if ($chk_leader_acd && $chk_leader_acd['fyp_academicid'] != $current_academic_id) {
                 echo "<script>alert('Error: You cannot join a team from a different academic intake.'); window.location.href='std_request_status.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
                 exit;
            }

            $sql_check = "SELECT count(*) as c FROM group_request WHERE group_id = ? AND request_status = 'Accepted'";
            $stmt_ck = $conn->prepare($sql_check);
            $stmt_ck->bind_param("i", $group_id);
            $stmt_ck->execute();
            $curr_mem = $stmt_ck->get_result()->fetch_assoc()['c'];
            
            if (($curr_mem + 1) >= 3) {
                 echo "<script>alert('Team is full.');</script>";
            } else {
                $conn->query("UPDATE group_request SET request_status = 'Accepted' WHERE request_id = $req_id");
                $conn->query("UPDATE STUDENT SET fyp_group = 'Group' WHERE fyp_studid = '$current_stud_id'");
                $conn->query("UPDATE STUDENT SET fyp_group = 'Group' WHERE fyp_studid = '$leader_id'");
                
                if (($curr_mem + 2) >= 3) {
                    $conn->query("UPDATE student_group SET status = 'Full' WHERE group_id = $group_id");
                }
                echo "<script>alert('Joined Team!'); window.location.href='std_request_status.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
            }
        }
        if ($_GET['action'] == 'RejectInvite') {
            $conn->query("UPDATE group_request SET request_status = 'Rejected' WHERE request_id = $req_id");
            echo "<script>alert('Invitation Rejected.'); window.location.href='std_request_status.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
        }
    }
}

// 6. POST Actions (Create / Invite)

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_group'])) {
    if ($is_team_locked) {
        echo "<script>alert('Action Failed: Locked.'); window.location.href='std_request_status.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
        exit;
    }

    $group_name = trim($_POST['group_name']);
    if (empty($group_name)) { echo "<script>alert('Please enter a group name.');</script>"; } 
    else {
        $chk = $conn->prepare("SELECT group_id FROM student_group WHERE group_name = ?");
        $chk->bind_param("s", $group_name);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) { echo "<script>alert('Group Name already taken.');</script>"; } 
        else {
            $sql_ins = "INSERT INTO student_group (group_name, leader_id, created_at, status) VALUES (?, ?, NOW(), 'Recruiting')";
            if ($stmt = $conn->prepare($sql_ins)) {
                $stmt->bind_param("ss", $group_name, $current_stud_id);
                if ($stmt->execute()) {
                    $rej = $conn->prepare("UPDATE group_request SET request_status = 'Rejected' WHERE invitee_id = ? AND request_status = 'Pending'");
                    $rej->bind_param("s", $current_stud_id); $rej->execute(); $rej->close();
                    
                    echo "<script>alert('Group Created!'); window.location.href='std_request_status.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
                }
            }
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['invite_teammate'])) {
    if ($my_role != 'Leader') { echo "<script>alert('Only Leader can invite.');</script>"; }
    elseif ($is_team_locked) { echo "<script>alert('Team Locked.');</script>"; } 
    else {
        $target_stud_id = $_POST['target_stud_id'];
        $my_group_id = $my_group['group_id'];
        
        $chk_target = $conn->prepare("SELECT fyp_academicid FROM STUDENT WHERE fyp_studid = ?");
        $chk_target->bind_param("s", $target_stud_id);
        $chk_target->execute();
        $res_target = $chk_target->get_result();
        
        if ($row_target = $res_target->fetch_assoc()) {
            if ($row_target['fyp_academicid'] != $current_academic_id) {
                echo "<script>alert('Cannot invite: Student is from a different academic intake.'); window.location.href='std_request_status.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
                exit;
            }
        } else {
            echo "<script>alert('Student not found.');</script>";
            exit;
        }
        $chk_target->close();

        $sql_count = "SELECT count(*) as c FROM group_request WHERE group_id = ? AND request_status = 'Accepted'";
        $stmt_c = $conn->prepare($sql_count); $stmt_c->bind_param("i", $my_group_id); $stmt_c->execute();
        $cnt = $stmt_c->get_result()->fetch_assoc()['c'];
        
        if (($cnt + 1) >= 3) { echo "<script>alert('Team is full.');</script>"; } 
        else {
            $chk = $conn->prepare("SELECT request_id FROM group_request WHERE group_id = ? AND invitee_id = ? AND request_status != 'Rejected'");
            $chk->bind_param("is", $my_group_id, $target_stud_id); $chk->execute();
            if ($chk->get_result()->num_rows == 0) {
                $ins = $conn->prepare("INSERT INTO group_request (group_id, inviter_id, invitee_id, request_status) VALUES (?, ?, ?, 'Pending')");
                $ins->bind_param("iss", $my_group_id, $current_stud_id, $target_stud_id);
                if ($ins->execute()) { echo "<script>alert('Invitation Sent!'); window.location.href='std_request_status.php?auth_user_id=".urlencode($auth_user_id)."';</script>"; }
            } else { echo "<script>alert('Already invited.');</script>"; }
        }
    }
}

// Prepare View Data
$incoming_invites = [];
if (!$my_group) {
    $sql_inc = "SELECT gr.*, g.group_name, s.fyp_studname as leader_name 
                FROM group_request gr JOIN student_group g ON gr.group_id = g.group_id JOIN STUDENT s ON g.leader_id = s.fyp_studid
                WHERE gr.invitee_id = ? AND gr.request_status = 'Pending'";
    if ($stmt = $conn->prepare($sql_inc)) { $stmt->bind_param("s", $current_stud_id); $stmt->execute(); $res = $stmt->get_result(); while ($row = $res->fetch_assoc()) $incoming_invites[] = $row; }
}

$team_members = [];
if ($my_group) {
    $group_id = $my_group['group_id'];
    $sql_l = "SELECT fyp_studname, fyp_studid FROM STUDENT WHERE fyp_studid = ?";
    $stmt_l = $conn->prepare($sql_l); $stmt_l->bind_param("s", $my_group['leader_id']); $stmt_l->execute();
    $leader_info = $stmt_l->get_result()->fetch_assoc();
    if ($leader_info) { $team_members[] = ['role' => 'Leader', 'name' => $leader_info['fyp_studname'], 'id' => $leader_info['fyp_studid'], 'status' => 'Active']; }
    
    $sql_m = "SELECT gr.*, s.fyp_studname, s.fyp_studid FROM group_request gr JOIN STUDENT s ON gr.invitee_id = s.fyp_studid WHERE gr.group_id = ? AND gr.request_status = 'Accepted'";
    $stmt_m = $conn->prepare($sql_m); $stmt_m->bind_param("i", $group_id); $stmt_m->execute(); $res_m = $stmt_m->get_result();
    while ($row = $res_m->fetch_assoc()) { $team_members[] = ['role' => 'Member', 'name' => $row['fyp_studname'], 'id' => $row['fyp_studid'], 'status' => 'Active']; }
}

$pending_requests = [];
if ($my_role == 'Leader') {
    $sql_p = "SELECT gr.*, s.fyp_studname FROM group_request gr JOIN STUDENT s ON gr.invitee_id = s.fyp_studid WHERE gr.group_id = ? AND gr.request_status = 'Pending'";
    $stmt_p = $conn->prepare($sql_p); $stmt_p->bind_param("i", $my_group['group_id']); $stmt_p->execute(); $res_p = $stmt_p->get_result();
    while ($row = $res_p->fetch_assoc()) $pending_requests[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request & Team Status</title>
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

        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
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
            position: relative;
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
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
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

        .status-badge {
            font-size: 13px;
            color: #666;
            background: #f0f0f0;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 500;
        }

        .info-card {
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            margin-bottom: 20px;
            border-left: 5px solid var(--primary-color);
        }

        .info-card h3 {
            margin: 0 0 15px 0;
            color: var(--primary-color);
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .app-status-box {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid #e9ecef;
        }

        .app-status-row {
            display: flex;
            margin-bottom: 12px;
            align-items: center;
        }

        .app-label {
            width: 140px;
            color: #666;
            font-size: 14px;
            font-weight: 500;
        }

        .app-value {
            font-weight: 600;
            color: #333;
            font-size: 15px;
        }

        .st-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .st-Pending { background: #fff3cd; color: #856404; }
        .st-Approve { background: #d4edda; color: #155724; }
        .st-Reject { background: #f8d7da; color: #721c24; }

        .team-section {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }

        .team-box {
            flex: 1;
            min-width: 300px;
            background: #f9fbff;
            padding: 20px;
            border-radius: 8px;
            border: 1px dashed #b8daff;
        }

        .team-box h4 {
            margin-top: 0;
            color: #0056b3;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-input {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            width: 100%;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        .btn-action {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: 0.2s;
            display: inline-block;
        }

        .btn-action:hover { background: var(--primary-hover); }
        .btn-reject { background: #dc3545; }
        .btn-reject:hover { background: #c82333; }
        .btn-accept { background: #28a745; }
        .btn-accept:hover { background: #218838; }

        .team-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }

        .team-table th {
            text-align: left;
            padding: 12px;
            background: #f8f9fa;
            color: #555;
            font-size: 13px;
            font-weight: 600;
        }

        .team-table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }

        .team-table tr:hover {
            background: #f8f9fa;
        }

        .search-container {
            position: relative;
        }

        .search-results {
            position: absolute;
            background: white;
            border: 1px solid #ddd;
            width: 100%;
            z-index: 10;
            display: none;
            max-height: 200px;
            overflow-y: auto;
            border-radius: 6px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-top: 5px;
        }

        .search-item {
            padding: 10px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
            transition: 0.2s;
        }

        .search-item:hover {
            background: #f5f5f5;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
            background: white;
            border-radius: 12px;
        }

        .group-header {
            border-left: 5px solid #0056b3;
            padding-left: 15px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
            padding: 20px;
            border-radius: 8px;
        }

        .group-header h3 {
            margin: 0;
            color: #333;
        }

        .role-badge {
            background: #e3effd;
            color: var(--primary-color);
            padding: 5px 12px;
            border-radius: 15px;
            font-weight: 600;
            font-size: 12px;
            margin-left: 10px;
        }

        .lock-badge {
            background: #ffebee;
            color: #c62828;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            margin-left: 10px;
        }

        .info-note {
            color: #666;
            font-size: 13px;
            margin-bottom: 15px;
            padding: 10px;
            background: #fff9e6;
            border-left: 3px solid #ffc107;
            border-radius: 4px;
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
            <li class="active">
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
                <h1>Request & Team Status</h1>
                <p>Track your Project Application and Manage your Team</p>
            </div>
            
            <div class="logo-section">
                <img src="image/ladybug.png?v=<?php echo time(); ?>" alt="Logo" class="logo-img">
                <span class="system-title">FYP Portal</span>
            </div>

            <div style="display:flex; align-items:center; gap:10px;">
                <span class="status-badge">Status: <?php echo $current_stud_group_status; ?></span>
                <span class="status-badge">Student</span>
                <?php if(!empty($stud_data['fyp_profileimg'])): ?>
                    <img src="<?php echo htmlspecialchars($stud_data['fyp_profileimg']); ?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
                <?php else: ?>
                    <div style="width:40px;height:40px;border-radius:50%;background:#0056b3;color:white;display:flex;align-items:center;justify-content:center;font-weight:bold;">
                        <?php echo strtoupper(substr($current_stud_name, 0, 1)); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- PROJECT APPLICATION STATUS -->
        <div class="info-card">
            <h3><i class="fa fa-file-contract"></i> My Project Application</h3>
            
            <?php if ($my_project_request): ?>
                <div class="app-status-box">
                    <div class="app-status-row">
                        <div class="app-label">Project Title:</div>
                        <div class="app-value"><?php echo htmlspecialchars($my_project_request['fyp_projecttitle']); ?></div>
                    </div>
                    <div class="app-status-row">
                        <div class="app-label">Type:</div>
                        <div class="app-value"><?php echo htmlspecialchars($my_project_request['fyp_projecttype']); ?></div>
                    </div>
                    <div class="app-status-row">
                        <div class="app-label">Supervisor:</div>
                        <div class="app-value">
                            <?php echo !empty($my_project_request['sv_name']) ? htmlspecialchars($my_project_request['sv_name']) : '<span style="color:#999; font-style:italic;">Name Not Available</span>'; ?>
                        </div>
                    </div>
                    <div class="app-status-row">
                        <div class="app-label">Status:</div>
                        <div class="app-value">
                            <span class="st-badge st-<?php echo $my_project_request['fyp_requeststatus']; ?>">
                                <?php echo $my_project_request['fyp_requeststatus']; ?>
                            </span>
                        </div>
                    </div>
                    <div class="app-status-row">
                        <div class="app-label">Submitted On:</div>
                        <div class="app-value" style="font-weight:400; font-size:13px; color:#777;">
                            <?php echo $my_project_request['fyp_datecreated']; ?>
                        </div>
                    </div>
                    
                    <?php if($my_project_request['fyp_requeststatus'] == 'Reject'): ?>
                        <div style="margin-top:15px; border-top:1px dashed #ddd; padding-top:10px;">
                            <a href="std_projectreg.php?auth_user_id=<?php echo $auth_user_id; ?>" class="btn-action" style="background:#6c757d;">
                                <i class="fa fa-redo"></i> Apply for another project
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fa fa-folder-open" style="font-size:32px; margin-bottom:10px; display:block; opacity:0.3;"></i>
                    No project application found. 
                    <?php if ($my_role == 'Member'): ?>
                        <br><small>(If your leader applied, check with them. Or wait for data refresh.)</small>
                    <?php endif; ?>
                    <br><br>
                    <a href="std_projectreg.php?auth_user_id=<?php echo $auth_user_id; ?>" class="btn-action">Browse Projects</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- TEAM MANAGEMENT -->
        <div class="info-card">
            <h3><i class="fa fa-users"></i> Team Management</h3>

            <?php if (!$my_group): ?>
                
                <div class="team-section">
                    <!-- Create Team -->
                    <div class="team-box">
                        <h4><i class="fa fa-plus-circle"></i> Create Team (For Group Projects)</h4>
                        <div class="info-note">
                            <i class="fa fa-info-circle"></i> <strong>Individual Project?</strong> You don't need to create a group. Go directly to <a href="std_projectreg.php?auth_user_id=<?php echo $auth_user_id; ?>" style="color:#0056b3; font-weight:600;">Project Registration</a>.
                            <br><br>
                            Only create a team if you want to be a <strong>Leader</strong> for a Group Project.
                        </div>
                        <form method="POST" style="display:flex; gap:10px;">
                            <input type="text" name="group_name" class="form-input" placeholder="Team Name (e.g. Tech-Team)" required>
                            <button type="submit" name="create_group" class="btn-action">Create Group</button>
                        </form>
                    </div>

                    <!-- Incoming Invitations -->
                    <div class="team-box" style="background:white; border:1px solid #e0e0e0;">
                        <h4><i class="fa fa-envelope"></i> Incoming Invitations</h4>
                        <?php if (count($incoming_invites) > 0): ?>
                            <table class="team-table">
                                <thead>
                                    <tr>
                                        <th>Team Name</th>
                                        <th>Leader</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($incoming_invites as $inv): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($inv['group_name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($inv['leader_name']); ?></td>
                                            <td>
                                                <a href="?auth_user_id=<?php echo $auth_user_id; ?>&action=AcceptInvite&req_id=<?php echo $inv['request_id']; ?>" class="btn-action btn-accept" onclick="return confirm('Join this team?')" style="padding:6px 12px; font-size:12px; margin-right:5px;">Accept</a>
                                                <a href="?auth_user_id=<?php echo $auth_user_id; ?>&action=RejectInvite&req_id=<?php echo $inv['request_id']; ?>" class="btn-action btn-reject" onclick="return confirm('Decline invitation?')" style="padding:6px 12px; font-size:12px;">Decline</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p style="color:#999; font-style:italic; text-align:center; padding:20px;">No invitations yet.</p>
                        <?php endif; ?>
                    </div>
                </div>

            <?php else: ?>

                <!-- Team Info Header -->
                <div class="group-header">
                    <div>
                        <h3><?php echo htmlspecialchars($my_group['group_name']); ?></h3>
                        <span class="role-badge"><?php echo $my_role; ?></span>
                        <?php if ($is_team_locked): ?>
                            <span class="lock-badge"><i class="fa fa-lock"></i> Locked (Pending/Approved)</span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($my_role == 'Leader'): ?>
                        <?php if (!$is_team_locked): ?>
                            <a href="?auth_user_id=<?php echo $auth_user_id; ?>&action=DisbandGroup" class="btn-action btn-reject" onclick="return confirm('WARNING: Disbanding will remove ALL members and delete the team. Continue?')">Disband Team</a>
                        <?php else: ?>
                            <button class="btn-action btn-reject" style="opacity:0.5; cursor:not-allowed;" title="Team Locked" disabled>Disband Team</button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Team Members Table -->
                <table class="team-table">
                    <thead>
                        <tr>
                            <th>Role</th>
                            <th>Name</th>
                            <th>Student ID</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($team_members as $mem): ?>
                            <tr>
                                <td>
                                    <?php if($mem['role']=='Leader') echo '<i class="fa fa-crown" style="color:gold; margin-right:5px;"></i>'; ?>
                                    <?php echo $mem['role']; ?>
                                </td>
                                <td><?php echo htmlspecialchars($mem['name']); ?></td>
                                <td><?php echo $mem['id']; ?></td>
                                <td><span style="color:green; font-weight:600;">Active</span></td>
                                <td>
                                    <?php if (!$is_team_locked): ?>
                                        <?php if ($my_role == 'Leader' && $mem['role'] == 'Member'): ?>
                                            <a href="?auth_user_id=<?php echo $auth_user_id; ?>&action=KickMember&tid=<?php echo $mem['id']; ?>" style="color:#dc3545; font-weight:600; text-decoration:none;" onclick="return confirm('Kick this member?')">Kick</a>
                                        <?php endif; ?>

                                        <?php if ($my_role == 'Member' && $mem['id'] == $current_stud_id): ?>
                                            <a href="?auth_user_id=<?php echo $auth_user_id; ?>&action=LeaveTeam" style="color:#dc3545; font-weight:600; text-decoration:none;" onclick="return confirm('Leave this team?')">Leave Team</a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color:#ccc; font-size:12px;">Locked</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Leader: Invite Members -->
                <?php if ($my_role == 'Leader'): ?>
                    <div style="margin-top:30px; padding-top:20px; border-top:1px solid #eee;">
                        <h4 style="margin-bottom:15px;"><i class="fa fa-user-plus"></i> Invite Members <span style="font-size:12px; font-weight:400; color:#666;">(Max 3 members)</span></h4>
                        
                        <?php if ($is_team_locked): ?>
                            <div style="background:#fff3cd; padding:10px; border-radius:5px; color:#856404;">
                                <i class="fa fa-lock"></i> Cannot invite new members while project application is Pending/Approved.
                            </div>
                        <?php elseif (count($team_members) < 3): ?>
                            <div class="search-container">
                                <form method="POST">
                                    <input type="hidden" name="target_stud_id" id="targetStudId">
                                    <div style="display:flex; gap:10px;">
                                        <input type="text" id="studSearch" class="form-input" placeholder="Search student name..." autocomplete="off">
                                        <button type="submit" name="invite_teammate" id="btnInvite" class="btn-action" disabled>Send Invite</button>
                                    </div>
                                    <div id="searchRes" class="search-results"></div>
                                </form>
                                <p style="font-size:12px; color:#888; margin-top:5px;">Note: If you are Individual, just don't invite anyone.</p>
                            </div>
                        <?php else: ?>
                            <div style="background:#e9ecef; padding:10px; border-radius:5px; text-align:center; color:#495057;">
                                <i class="fa fa-check-circle"></i> Team is Full (3/3).
                            </div>
                        <?php endif; ?>

                        <?php if (count($pending_requests) > 0): ?>
                            <h5 style="margin-top:20px; margin-bottom:10px; color:#666;">Pending Invitations</h5>
                            <ul style="list-style:none; padding:0;">
                                <?php foreach ($pending_requests as $pr): ?>
                                    <li style="padding:10px; border-bottom:1px solid #f0f0f0; display:flex; justify-content:space-between; align-items:center;">
                                        <span>Invited: <strong><?php echo htmlspecialchars($pr['fyp_studname']); ?></strong></span>
                                        <span style="font-size:12px; background:#fff3cd; color:#856404; padding:2px 8px; border-radius:10px;">Pending...</span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>

    </div>

    <script>
        const searchInput = document.getElementById('studSearch');
        const searchBox = document.getElementById('searchRes');
        const targetInput = document.getElementById('targetStudId');
        const inviteBtn = document.getElementById('btnInvite');

        if (searchInput) {
            searchInput.addEventListener('keyup', function() {
                const val = this.value;
                if (val.length < 2) { 
                    searchBox.style.display = 'none'; 
                    return; 
                }
                
                fetch(`std_request_status.php?action=search_students&keyword=${encodeURIComponent(val)}&auth_user_id=<?php echo $auth_user_id; ?>`)
                    .then(res => res.json())
                    .then(data => {
                        searchBox.innerHTML = '';
                        if (data.length > 0) {
                            searchBox.style.display = 'block';
                            data.forEach(stud => {
                                const div = document.createElement('div');
                                div.className = 'search-item';
                                div.innerHTML = `${stud.name} <small style="color:#999;">(${stud.id || stud.studid})</small>`;
                                div.onclick = () => selectStudent(stud);
                                searchBox.appendChild(div);
                            });
                        } else { 
                            searchBox.style.display = 'none'; 
                        }
                    });
            });
        }

        function selectStudent(stud) {
            searchInput.value = stud.name;
            targetInput.value = stud.studid;
            searchBox.style.display = 'none';
            inviteBtn.disabled = false;
        }

        // Close search results when clicking outside
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !searchBox.contains(e.target)) {
                searchBox.style.display = 'none';
            }
        });
    </script>
</body>
</html>