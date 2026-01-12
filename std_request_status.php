<?php
// ====================================================
// std_request_status.php - 组队管理 (Added Academic Year Validation)
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
$current_academic_id = 0; // 初始化

if (isset($conn)) {
    // 获取 USER 表名字
    $sql_user = "SELECT fyp_username FROM `USER` WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_user)) { 
        $stmt->bind_param("i", $auth_user_id); $stmt->execute(); 
        $res = $stmt->get_result(); 
        if ($row = $res->fetch_assoc()) $current_stud_name = $row['fyp_username']; 
        $stmt->close(); 
    }
    
    // 获取 STUDENT 表详细信息
    $sql_stud = "SELECT * FROM STUDENT WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_stud)) { 
        $stmt->bind_param("i", $auth_user_id); $stmt->execute(); 
        $res = $stmt->get_result(); 
        if ($row = $res->fetch_assoc()) { 
            $stud_data = $row; 
            $current_stud_id = $row['fyp_studid'];
            if (!empty($row['fyp_studname'])) $current_stud_name = $row['fyp_studname'];
            if (!empty($row['fyp_group'])) $current_stud_group_status = $row['fyp_group'];
            if (!empty($row['fyp_academicid'])) $current_academic_id = $row['fyp_academicid']; // 获取 Academic ID
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
    // 1. 检查我是否是 Leader
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

    // 2. 如果不是 Leader，检查我是否是 Member
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

    // 核心修改：同时 LEFT JOIN supervisor 和 coordinator 表
// 使用 COALESCE(s.fyp_name, c.fyp_name) 来获取名字：如果 supervisor 表没名字，就去 coordinator 表找
// =============================================================
// 核心查询：通过 Project 表的 Staff ID 来查找 Coordinator/Supervisor
// =============================================================

$req_sql = "SELECT pr.*, 
                   p.fyp_projecttitle, 
                   p.fyp_projecttype, 
                   -- 如果 Supervisor 表有名字就用 Supervisor 的，否则用 Coordinator 的
                   COALESCE(s.fyp_name, c.fyp_name) as sv_name, 
                   COALESCE(s.fyp_email, c.fyp_email) as sv_email
            FROM project_request pr 
            
            -- 1. 先连接 Project 表 (为了拿到 fyp_staffid)
            LEFT JOIN project p ON pr.fyp_projectid = p.fyp_projectid 
            
            -- 2. 用 Project 表里的 Staff ID 去匹配 Supervisor 表
            LEFT JOIN supervisor s ON p.fyp_staffid = s.fyp_staffid
            
            -- 3. 用 Project 表里的 Staff ID 去匹配 Coordinator 表
            LEFT JOIN coordinator c ON p.fyp_staffid = c.fyp_staffid
            
            WHERE pr.fyp_studid = ? 
            ORDER BY pr.fyp_datecreated DESC LIMIT 1";

// =============================================================
// 执行查询 (带错误检查)
// =============================================================

if ($stmt = $conn->prepare($req_sql)) {
    // 假设 studid 是字符串 (String)，所以这里用 "s"
    $stmt->bind_param("s", $applicant_id_to_check);
    
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $my_project_request = $row;
            
            // 检查状态 (涵盖所有可能的写法)
            $status = $row['fyp_requeststatus'];
            if ($status == 'Pending' || $status == 'Approve' || $status == 'Approved') {
                $is_team_locked = true;
            }
        }
    } else {
        // 执行出错
        die("SQL Execute Error: " . $stmt->error);
    }
    $stmt->close();
}
}

// ====================================================
// MODULE B: TEAM MANAGEMENT LOGIC (ACTIONS)
// ====================================================

// 4. AJAX 搜索功能 (Updated with Academic Year Filter)
if (isset($_GET['action']) && $_GET['action'] == 'search_students') {
    header('Content-Type: application/json');
    $keyword = $_GET['keyword'] ?? '';
    
    if (strlen($keyword) < 1) { echo json_encode([]); exit; }
    $keyword = "%" . $keyword . "%";
    
    // 增加 AND s.fyp_academicid = ? 条件
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
        // 绑定 3 个参数：keyword(s), userid(i), academicid(i)
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

// Action: KICK Member
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

// Action: LEAVE Team
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

// Action: DISBAND Group
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

// Action: Accept/Reject Invitation
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

            // 额外检查：再次确认 Academic ID 是否匹配 (防止前端绕过)
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

// A. Create Group
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

// B. Invite
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['invite_teammate'])) {
    if ($my_role != 'Leader') { echo "<script>alert('Only Leader can invite.');</script>"; }
    elseif ($is_team_locked) { echo "<script>alert('Team Locked.');</script>"; } 
    else {
        $target_stud_id = $_POST['target_stud_id'];
        $my_group_id = $my_group['group_id'];
        
        // 1. 验证目标学生是否同一年级 (后端二次验证)
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

// ----------------------------------------------------
// 7. Prepare View Data
// ----------------------------------------------------

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

$current_page = 'team_invitations';
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
    <title>Request & Team Status</title>
    <?php $favicon = !empty($stud_data['fyp_profileimg']) ? $stud_data['fyp_profileimg'] : "image/user.png"; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* CSS Same as before */
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap');
        :root { --primary-color: #0056b3; --primary-hover: #004494; --secondary-color: #f4f4f9; --text-color: #333; --border-color: #e0e0e0; --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); --gradient-start: #eef2f7; --gradient-end: #ffffff; --sidebar-width: 260px; }
        body { font-family: 'Poppins', sans-serif; margin: 0; background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end)); color: var(--text-color); min-height: 100vh; display: flex; flex-direction: column; }
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
        .sidebar { width: var(--sidebar-width); background: #fff; border-radius: 12px; box-shadow: var(--card-shadow); padding: 20px 0; flex-shrink: 0; min-height: calc(100vh - 120px); }
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
        .welcome-card { background: #fff; padding: 30px; border-radius: 12px; box-shadow: var(--card-shadow); border-left: 5px solid var(--primary-color); }
        .page-title { font-size: 24px; margin: 0 0 10px 0; color: var(--text-color); }
        .status-card { background: #fff; border-radius: 10px; padding: 25px; margin-bottom: 20px; box-shadow: 0 4px 10px rgba(0,0,0,0.03); border: 1px solid #eee; }
        .card-header { font-size: 18px; font-weight: 600; color: #333; margin-bottom: 15px; border-bottom: 1px solid #f0f0f0; padding-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
        .btn-action { background: var(--primary-color); color: white; border: none; padding: 8px 15px; border-radius: 6px; cursor: pointer; text-decoration: none; font-size: 14px; }
        .btn-reject { background: #dc3545; } .btn-accept { background: #28a745; }
        .form-input { padding: 10px; border: 1px solid #ddd; border-radius: 6px; width: 100%; box-sizing: border-box; }
        .team-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .team-table th { text-align: left; padding: 10px; background: #f8f9fa; color: #555; font-size: 13px; }
        .team-table td { padding: 10px; border-bottom: 1px solid #eee; font-size: 14px; }
        .search-results { position: absolute; background: white; border: 1px solid #ddd; width: 100%; z-index: 10; display: none; }
        .search-item { padding: 10px; cursor: pointer; border-bottom: 1px solid #eee; }
        .search-item:hover { background: #f5f5f5; }
        .group-badge { background: #e3effd; color: var(--primary-color); padding: 5px 12px; border-radius: 15px; font-weight: 600; }
        
        .app-status-box { background: #f8f9fa; border-radius: 8px; padding: 20px; border: 1px solid #e9ecef; margin-bottom: 10px; }
        .app-status-row { display: flex; margin-bottom: 10px; align-items: center; }
        .app-label { width: 140px; color: #666; font-size: 14px; font-weight: 500; }
        .app-value { font-weight: 600; color: #333; font-size: 15px; }
        .st-badge { padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
        .st-Pending { background: #fff3cd; color: #856404; }
        .st-Approve { background: #d4edda; color: #155724; }
        .st-Reject { background: #f8d7da; color: #721c24; }

        @media (max-width: 900px) { .layout-container { flex-direction: column; } .sidebar { width: 100%; min-height: auto; } }
    </style>
</head>
<body>

    <header class="topbar">
        <div class="logo">FYP System</div>
        <div class="topbar-right">
            <div class="user-profile-summary">
                <span class="user-name-display"><?php echo htmlspecialchars($current_stud_name); ?></span>
                <span class="user-role-badge">Student</span>
            </div>
            <div class="user-avatar-circle"><img src="<?php echo $favicon; ?>" alt="User Avatar"></div>
            <a href="login.php" class="logout-btn"><i class="fa fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>

    <div class="layout-container">
        <aside class="sidebar">
            <ul class="menu-list">
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
            <div class="welcome-card">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <h1 class="page-title">Request & Team Status</h1>
                        <p style="color: #666; margin: 0;">Track your Project Application and Manage your Team.</p>
                    </div>
                    <div>
                        <span style="color:#666;">My Status:</span>
                        <span class="group-badge"><?php echo $current_stud_group_status; ?></span>
                    </div>
                </div>
            </div>

            <!-- MODULE A: PROJECT APPLICATION STATUS -->
            <div class="status-card">
                <div class="card-header">
                    <span><i class="fa fa-file-contract"></i> My Project Application</span>
                </div>
                
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
                    <div style="text-align:center; padding:30px; color:#999; background:#f9f9f9; border-radius:8px;">
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

            <!-- MODULE B: TEAM MANAGEMENT -->
            <div class="status-card">
                <div class="card-header">
                    <span><i class="fa fa-users"></i> Team Management</span>
                </div>

                <!-- 逻辑分支：显示哪个视图 -->
                <?php if (!$my_group): ?>
                    
                    <!-- SCENE 1: 没有小组 (Free Agent) -->
                    
                    <div style="display:flex; flex-wrap:wrap; gap:20px;">
                        <!-- 1a. 创建小组表单 -->
                        <div style="flex:1; min-width:300px; background: #f9fbff; padding: 20px; border-radius: 8px; border: 1px dashed #b8daff;">
                            <h4 style="margin-top:0; color:#0056b3;"><i class="fa fa-plus-circle"></i> Create Team (For Group Projects)</h4>
                            <p style="color:#666; font-size:13px; margin-bottom:15px;">
                                <i class="fa fa-info-circle"></i> <strong>Individual Project?</strong> You don't need to create a group. Go directly to <a href="std_projectreg.php?auth_user_id=<?php echo $auth_user_id; ?>" style="color:#0056b3; font-weight:600;">Project Registration</a>.
                                <br><br>
                                Only create a team if you want to be a <strong>Leader</strong> for a Group Project.
                            </p>
                            <form method="POST" style="display:flex; gap:10px;">
                                <input type="text" name="group_name" class="form-input" placeholder="Team Name (e.g. Tech-Team)" required>
                                <button type="submit" name="create_group" class="btn-action">Create Group</button>
                            </form>
                        </div>

                        <!-- 1b. 收到的邀请 -->
                        <div style="flex:1; min-width:300px;">
                            <h4 style="border-bottom:1px solid #eee; padding-bottom:10px; margin-top:0;"><i class="fa fa-envelope"></i> Incoming Invitations</h4>
                            <?php if (count($incoming_invites) > 0): ?>
                                <table class="team-table">
                                    <thead><tr><th>Team Name</th><th>Leader</th><th>Action</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($incoming_invites as $inv): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($inv['group_name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($inv['leader_name']); ?></td>
                                                <td>
                                                    <a href="?auth_user_id=<?php echo $auth_user_id; ?>&action=AcceptInvite&req_id=<?php echo $inv['request_id']; ?>" class="btn-action btn-accept" onclick="return confirm('Join this team?')">Accept</a>
                                                    <a href="?auth_user_id=<?php echo $auth_user_id; ?>&action=RejectInvite&req_id=<?php echo $inv['request_id']; ?>" class="btn-action btn-reject" onclick="return confirm('Decline invitation?')">Decline</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p style="color:#999; font-style:italic;">No invitations yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php else: ?>

                    <!-- SCENE 2: 已经在小组中 (Leader 或 Member) -->
                    
                    <div style="border-left: 5px solid #0056b3; padding-left: 15px; margin-bottom: 20px; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <h3 style="margin:0; color:#333;"><?php echo htmlspecialchars($my_group['group_name']); ?></h3>
                            <span class="group-badge" style="margin-top:5px; display:inline-block;"><?php echo $my_role; ?></span>
                            <?php if ($is_team_locked): ?>
                                <span style="background:#ffebee; color:#c62828; padding:3px 10px; border-radius:12px; font-size:12px; margin-left:10px;"><i class="fa fa-lock"></i> Locked (Pending/Approved)</span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($my_role == 'Leader'): ?>
                            <!-- 队长解散按钮 (现在即便有组员也能解散) -->
                            <?php if (!$is_team_locked): ?>
                                <a href="?auth_user_id=<?php echo $auth_user_id; ?>&action=DisbandGroup" class="btn-action btn-reject" onclick="return confirm('WARNING: Disbanding will remove ALL members and delete the team. Continue?')">Disband Team</a>
                            <?php else: ?>
                                <button class="btn-action btn-reject" style="opacity:0.5; cursor:not-allowed;" title="Team Locked">Disband Team</button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <!-- 2a. 成员列表 -->
                    <table class="team-table">
                        <thead><tr><th>Role</th><th>Name</th><th>Student ID</th><th>Status</th><th>Actions</th></tr></thead>
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
                                            <!-- KICK Logic (For Leader, against Members) -->
                                            <?php if ($my_role == 'Leader' && $mem['role'] == 'Member'): ?>
                                                <a href="?auth_user_id=<?php echo $auth_user_id; ?>&action=KickMember&tid=<?php echo $mem['id']; ?>" style="color:#dc3545; font-weight:600; text-decoration:none;" onclick="return confirm('Kick this member?')">Kick</a>
                                            <?php endif; ?>

                                            <!-- LEAVE Logic (For Members, against themselves) -->
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

                    <!-- 2b. Leader 专属功能：邀请成员 -->
                    <?php if ($my_role == 'Leader'): ?>
                        <div style="margin-top:30px; padding-top:20px; border-top:1px solid #eee;">
                            <h4 style="margin-bottom:15px;"><i class="fa fa-user-plus"></i> Invite Members <span style="font-size:12px; font-weight:400; color:#666;">(Max 3 members)</span></h4>
                            
                            <?php if ($is_team_locked): ?>
                                <div style="background:#fff3cd; padding:10px; border-radius:5px; color:#856404;">
                                    <i class="fa fa-lock"></i> Cannot invite new members while project application is Pending/Approved.
                                </div>
                            <?php elseif (count($team_members) < 3): ?>
                                <div style="position:relative;">
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

                            <!-- 显示已发送但未处理的邀请 -->
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

        </main>
    </div>

    <script>
        // 搜索逻辑
        const searchInput = document.getElementById('studSearch');
        const searchBox = document.getElementById('searchRes');
        const targetInput = document.getElementById('targetStudId');
        const inviteBtn = document.getElementById('btnInvite');

        if (searchInput) {
            searchInput.addEventListener('keyup', function() {
                const val = this.value;
                if (val.length < 2) { searchBox.style.display = 'none'; return; }
                
                fetch(`std_request_status.php?action=search_students&keyword=${encodeURIComponent(val)}&auth_user_id=<?php echo $auth_user_id; ?>`)
                    .then(res => res.json())
                    .then(data => {
                        searchBox.innerHTML = '';
                        if (data.length > 0) {
                            searchBox.style.display = 'block';
                            data.forEach(stud => {
                                const div = document.createElement('div');
                                div.className = 'search-item';
                                div.innerHTML = `${stud.name} <small>(${stud.id || stud.studid})</small>`;
                                div.onclick = () => selectStudent(stud);
                                searchBox.appendChild(div);
                            });
                        } else { searchBox.style.display = 'none'; }
                    });
            });
        }

        function selectStudent(stud) {
            searchInput.value = stud.name;
            targetInput.value = stud.studid;
            searchBox.style.display = 'none';
            inviteBtn.disabled = false;
        }
    </script>
</body>
</html>