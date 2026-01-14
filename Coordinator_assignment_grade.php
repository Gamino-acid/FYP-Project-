<?php
// ====================================================
// Coordinator_assignment_grade.php - UI适配版
// (功能同步: 包含小组继承逻辑、筛选、统计、Staff ID适配)
// ====================================================
include("connect.php");

// 1. 验证用户登录
$auth_user_id = $_GET['auth_user_id'] ?? null;
$current_page = 'grade_assignment'; // 对应 Coordinator 菜单的高亮 ID

if (!$auth_user_id) { header("location: login.php"); exit; }

// 2. 获取 Coordinator 信息
$user_name = "Coordinator"; 
$user_avatar = "image/user.png"; 
$sv_id = 0; // 这里代表 Coordinator 作为 Staff 的 ID

if (isset($conn)) {
    // 获取用户名
    $sql_user = "SELECT fyp_username FROM `USER` WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_user)) {
        $stmt->bind_param("i", $auth_user_id); $stmt->execute();
        $res = $stmt->get_result(); if ($row = $res->fetch_assoc()) $user_name = $row['fyp_username'];
        $stmt->close();
    }
    // 获取 Supervisor/Coordinator 详情表信息
    $sql_sv = "SELECT * FROM supervisor WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_sv)) {
        $stmt->bind_param("i", $auth_user_id); $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $row = $res->fetch_assoc();
            
            // 自动检测列名: 优先使用 fyp_staffid，兼容 fyp_supervisorid
            if (isset($row['fyp_staffid'])) {
                $sv_id = $row['fyp_staffid'];
            } elseif (isset($row['fyp_supervisorid'])) {
                $sv_id = $row['fyp_supervisorid'];
            }
            
            if (!empty($row['fyp_name'])) $user_name = $row['fyp_name'];
            if (!empty($row['fyp_profileimg'])) $user_avatar = $row['fyp_profileimg'];
        }
        $stmt->close();
    }
}

// ====================================================
// 3. 处理打分/退回提交 (POST) - 与 Supervisor 逻辑同步
// ====================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $date_now = date('Y-m-d H:i:s');
    $assign_id = $_POST['assignment_id'];
    $stud_id = $_POST['student_id'];
    $marks = $_POST['marks'];
    $feedback = $_POST['feedback'];
    
    $new_status = '';
    $msg = '';

    if (isset($_POST['return_grade'])) {
        $new_status = 'Graded';
        $msg = "Assignment graded successfully!";
    } elseif (isset($_POST['return_revision'])) {
        $new_status = 'Need Revision';
        $msg = "Assignment returned for revision!";
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
            echo "<script>alert('$msg'); window.location.href='Coordinator_assignment_grade.php?auth_user_id=" . urlencode($auth_user_id) . "&view_id=" . $assign_id . "';</script>";
        }
    }
}

// ====================================================
// 4. 获取数据 (逻辑同步)
// ====================================================
$view_assignment_id = $_GET['view_id'] ?? null;
$assignments_list = [];
$students_to_grade = [];
$current_assignment = null;

$sort_by = $_GET['sort_by'] ?? 'DESC'; 
$filter_type = $_GET['filter_type'] ?? 'All';

if ($sv_id > 0) {
    if (!$view_assignment_id) {
        // --- VIEW A: List Assignments (Coordinator Dashboard Style) ---
        
        // 注意：这里假设 Coordinator 也是作为 Supervisor 给自己的学生布置作业
        // 这里的 SQL 逻辑与 Supervisor_assignment_grade.php 保持一致，统计各种状态
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
                     WHERE a.fyp_staffid = '$sv_id'"; // 使用 staffid 过滤

        if ($filter_type != 'All') {
            $sql_list .= " AND a.fyp_assignment_type = '$filter_type'";
        }
        $sql_list .= " ORDER BY a.fyp_datecreated $sort_by";

        $res = $conn->query($sql_list);
        if($res) {
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
                
                // 计算统计数据
                $total_count = $row['total_students'];
                $submitted_count = $row['submitted_count'];
                $graded_count = $row['graded_count'];
                $revision_count = $row['revision_count']; 
                
                $not_submitted = $total_count - ($submitted_count + $graded_count + $revision_count);
                if ($not_submitted < 0) $not_submitted = 0;

                $row['stats'] = ['not_submitted' => $not_submitted, 'submitted' => $submitted_count, 'graded' => $graded_count, 'revision' => $revision_count];
                $assignments_list[] = $row;
            }
        }

    } else {
        // --- VIEW B: Grade Students (Detail with Inheritance Logic) ---
        
        $sql_detail = "SELECT * FROM assignment WHERE fyp_assignmentid = '$view_assignment_id' AND fyp_staffid = '$sv_id'";
        $res_d = $conn->query($sql_detail);
        $current_assignment = $res_d->fetch_assoc();

        if ($current_assignment) {
            $target_type = $current_assignment['fyp_assignment_type'];
            $target_id = $current_assignment['fyp_target_id']; 

            $base_fields = "s.fyp_studid, s.fyp_studname, s.fyp_group, 
                            g.fyp_marks, g.fyp_feedback, g.fyp_submission_status, g.fyp_submission_date, g.fyp_submitted_file";
            
            $join_part = "LEFT JOIN assignment_submission g ON (s.fyp_studid = g.fyp_studid AND g.fyp_assignmentid = '$view_assignment_id')";

            // 根据 Target 筛选学生，并确保是该 Coordinator 的学生 (通过 fyp_registration)
            if ($target_id == 'ALL') {
                $sql_studs = "SELECT $base_fields FROM fyp_registration r JOIN student s ON r.fyp_studid = s.fyp_studid $join_part WHERE r.fyp_staffid = '$sv_id'";
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
            
            if (isset($sql_studs)) {
                $res_s = $conn->query($sql_studs);
                if($res_s) {
                    while ($row = $res_s->fetch_assoc()) {
                        
                        // 获取小组名称用于显示
                        $row['display_group_name'] = '';
                        $g_query = "SELECT group_name FROM student_group WHERE leader_id = '{$row['fyp_studid']}' UNION SELECT sg.group_name FROM group_request gr JOIN student_group sg ON gr.group_id = sg.group_id WHERE gr.invitee_id = '{$row['fyp_studid']}' AND gr.request_status = 'Accepted' LIMIT 1";
                        $g_result = $conn->query($g_query);
                        if ($g_result && $g_data = $g_result->fetch_assoc()) {
                             $row['display_group_name'] = $g_data['group_name'];
                        }

                        // --- 核心逻辑: 小组组员继承组长提交 ---
                        $inherited = false;
                        if ($target_type == 'Group' && (empty($row['fyp_submission_status']) || $row['fyp_submission_status'] == 'Not Turned In' || $row['fyp_submission_status'] == 'Viewed')) {
                            $leader_id = null;
                            // 检查自己是否是组长
                            $chk_l = $conn->query("SELECT leader_id FROM student_group WHERE leader_id = '{$row['fyp_studid']}'");
                            if ($chk_l->num_rows > 0) $leader_id = $row['fyp_studid']; 
                            else {
                                // 查找自己的组长
                                $chk_m = $conn->query("SELECT sg.leader_id FROM group_request gr JOIN student_group sg ON gr.group_id = sg.group_id WHERE gr.invitee_id = '{$row['fyp_studid']}' AND gr.request_status = 'Accepted' LIMIT 1");
                                if ($lm = $chk_m->fetch_assoc()) $leader_id = $lm['leader_id'];
                            }

                            // 如果找到了组长且组长不是自己，检查组长的提交
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

                        // 状态展示处理
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

// ====================================================
// Coordinator 专属菜单定义 (保持 Coordinator 的结构)
// ====================================================
$menu_items = [
    'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home', 'link' => 'Coordinator_mainpage.php?page=dashboard'],
    'profile'   => ['name' => 'Profile', 'icon' => 'fa-user', 'link' => 'Coordinator_profile.php'], 
    'project_mgmt' => [
        'name' => 'Project Mgmt', 
        'icon' => 'fa-tasks', 
        'sub_items' => [
            'propose_project' => ['name' => 'Propose Project', 'icon' => 'fa-plus-circle', 'link' => 'Coordinator_purpose.php'], 
            'project_requests' => ['name' => 'Project Requests', 'icon' => 'fa-envelope-open-text', 'link' => 'Coordinator_projectreq.php'], 
            'allocation' => ['name' => 'Auto Allocation', 'icon' => 'fa-bullseye', 'link' => 'Coordinator_mainpage.php?page=allocation']
        ]
    ],
    'assessment' => [
        'name' => 'Assessment', 
        'icon' => 'fa-clipboard-check', 
        'sub_items' => [
            'propose_assignment' => ['name' => 'Create Assignment', 'icon' => 'fa-plus', 'link' => 'Coordinator_assignment_purpose.php'], 
            'grade_assignment' => ['name' => 'Grade Assignments', 'icon' => 'fa-check-square', 'link' => 'Coordinator_assignment_grade.php']
        ]
    ],
    'announcements' => [
        'name' => 'Announcements', 
        'icon' => 'fa-bullhorn', 
        'sub_items' => [
            'post_announcement' => ['name' => 'Post New', 'icon' => 'fa-pen', 'link' => 'Coordinator_announcement.php']
        ]
    ],
    'schedule' => ['name' => 'My Schedule', 'icon' => 'fa-calendar-alt', 'link' => 'Coordinator_meeting.php'], 
    'data_io' => ['name' => 'Data Management', 'icon' => 'fa-database', 'link' => 'Coordinator_mainpage.php?page=data_io'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Assignments - Coordinator</title>
    <link rel="icon" type="image/png" href="<?php echo $user_avatar; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* 复用 Supervisor 的优质 UI 样式 */
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap');
        :root { --primary-color: #0056b3; --primary-hover: #004494; --secondary-color: #f4f4f9; --text-color: #333; --border-color: #e0e0e0; --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); }
        body { font-family: 'Poppins', sans-serif; margin: 0; background: linear-gradient(135deg, #eef2f7, #ffffff); color: var(--text-color); min-height: 100vh; display: flex; flex-direction: column; }
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
        .sidebar { width: 260px; background: #fff; border-radius: 12px; box-shadow: var(--card-shadow); padding: 20px 0; flex-shrink: 0; min-height: calc(100vh - 120px); }
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

        /* Page Specific UI */
        .card { background: #fff; padding: 25px; border-radius: 12px; box-shadow: var(--card-shadow); margin-bottom: 20px; }
        .card-header { font-size: 18px; font-weight: 600; color: #333; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        
        /* Filter Bar */
        .filter-bar { display: flex; gap: 10px; align-items: center; background: #f8f9fa; padding: 10px 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #eee; }
        .filter-group { display: flex; align-items: center; gap: 8px; }
        .filter-label { font-size: 13px; font-weight: 600; color: #555; }
        .filter-select { padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px; cursor: pointer; }
        .btn-apply { padding: 6px 15px; background: var(--primary-color); color: white; border: none; border-radius: 4px; font-size: 13px; cursor: pointer; }

        /* Stats in List View */
        .ass-card { border: 1px solid #eee; border-radius: 8px; padding: 15px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; transition: all 0.2s; }
        .ass-card:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .ass-info { flex: 1; }
        .ass-title { font-weight: 600; font-size: 16px; color: #333; margin-bottom: 5px; }
        .ass-meta { font-size: 13px; color: #777; display: flex; gap: 15px; }
        .ass-stats { display: flex; gap: 15px; margin-left: 20px; }
        .stat-badge { font-size: 11px; padding: 4px 8px; border-radius: 4px; background: #f1f3f5; color: #555; font-weight: 500; }
        .stat-badge i { margin-right: 4px; }
        .stat-submitted { color: #155724; background: #d4edda; }
        .stat-graded { color: #004085; background: #cce5ff; }
        .stat-pending { color: #856404; background: #fff3cd; }
        .stat-revision { color: #721c24; background: #f8d7da; }

        /* Student Card */
        .student-card { background: #fff; border: 1px solid #eee; border-radius: 8px; padding: 20px; margin-bottom: 20px; display: flex; gap: 20px; flex-wrap: wrap; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
        .stud-info { width: 240px; border-right: 1px solid #eee; padding-right: 20px; }
        .stud-status-badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; margin-top: 5px; }
        
        .st-NotTurnedIn { background: #e2e3e5; color: #6c757d; }
        .st-Viewed { background: #cce5ff; color: #004085; }
        .st-TurnedIn { background: #d4edda; color: #155724; }
        .st-LateTurnedIn { background: #f8d7da; color: #721c24; }
        .st-Graded { background: #d1ecf1; color: #0c5460; }
        .st-NeedRevision { background: #fff3cd; color: #856404; }
        .st-Resubmitted { background: #d4edda; color: #155724; border: 2px solid #28a745; }

        .grade-form { flex: 1; display: flex; flex-direction: column; gap: 15px; }
        .gf-row { display: flex; gap: 15px; align-items: flex-start; }
        .gf-group { flex: 1; }
        .gf-group label { font-size: 12px; color: #666; font-weight: 600; display: block; margin-bottom: 5px; }
        .gf-input { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-family: inherit; }
        
        .action-buttons { display: flex; gap: 10px; margin-top: 10px; }
        .btn-return { padding: 9px 20px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 500; display: flex; align-items: center; gap: 5px; }
        .btn-revision { padding: 9px 20px; background: #ffc107; color: #212529; border: none; border-radius: 4px; cursor: pointer; font-weight: 500; display: flex; align-items: center; gap: 5px; }
        .btn-return:hover { background: #218838; }
        .btn-revision:hover { background: #e0a800; }
        .btn-return:disabled, .btn-revision:disabled { opacity: 0.5; cursor: not-allowed; background: #ccc; color: #666; }
        
        .file-link { display: inline-block; margin-top: 8px; font-size: 13px; color: #0056b3; text-decoration: none; padding: 6px 12px; background: #e3effd; border-radius: 4px; border: 1px solid #b8daff; transition: all 0.2s; }
        .file-link:hover { background: #d0e4ff; }
        .file-link i { margin-right: 5px; }
        .no-file { font-size: 13px; color: #999; font-style: italic; margin-top: 5px; display: block; }
        .inherited-badge { font-size: 10px; color: #666; background: #eee; padding: 2px 5px; border-radius: 4px; margin-left: 5px; }

        .btn-view { padding: 6px 15px; background: var(--primary-color); color: white; text-decoration: none; border-radius: 4px; font-size: 12px; white-space: nowrap; }
        .back-link { display: inline-block; margin-bottom: 15px; color: #666; text-decoration: none; font-size: 14px; }
        .back-link:hover { color: var(--primary-color); }
        
        @media (max-width: 900px) { .layout-container { flex-direction: column; } .sidebar { width: 100%; min-height: auto; } .student-card { flex-direction: column; } .stud-info { width: 100%; border-right: none; border-bottom: 1px solid #eee; padding-bottom: 15px; padding-right: 0; } }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="logo"><img src="image/ladybug.png" alt="Logo" style="width: 32px; margin-right: 10px;"> FYP System</div>
        <div class="topbar-right">
            <div class="user-profile-summary">
                <span class="user-name-display"><?php echo htmlspecialchars($user_name); ?></span>
                <span class="user-role-badge">Coordinator</span>
            </div>
            <div class="user-avatar-circle"><img src="<?php echo $user_avatar; ?>" alt="User Avatar"></div>
            <a href="login.php" class="logout-btn"><i class="fa fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>

    <div class="layout-container">
        <aside class="sidebar">
            <ul class="menu-list">
                <?php foreach ($menu_items as $key => $item): ?>
                    <?php 
                        // 判断是否激活当前菜单组
                        $isActive = ($key == 'assessment'); 
                        $linkUrl = isset($item['link']) ? $item['link'] : "#";
                        if (strpos($linkUrl, '.php') !== false) {
                             $separator = (strpos($linkUrl, '?') !== false) ? '&' : '?';
                             $linkUrl .= $separator . "auth_user_id=" . urlencode($auth_user_id);
                        }
                    ?>
                    <li class="menu-item <?php echo $isActive ? 'has-active-child' : ''; ?>">
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
            <?php if (!$view_assignment_id): ?>
                <div class="card">
                    <div class="card-header">
                        Select Assignment to Grade
                    </div>
                    
                    <form method="GET" class="filter-bar">
                        <input type="hidden" name="auth_user_id" value="<?php echo htmlspecialchars($auth_user_id); ?>">
                        <div class="filter-group">
                            <span class="filter-label">Sort:</span>
                            <select name="sort_by" class="filter-select">
                                <option value="DESC" <?php echo $sort_by=='DESC'?'selected':''; ?>>Newest First</option>
                                <option value="ASC" <?php echo $sort_by=='ASC'?'selected':''; ?>>Oldest First</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <span class="filter-label">Type:</span>
                            <select name="filter_type" class="filter-select">
                                <option value="All">All Types</option>
                                <option value="Individual" <?php echo $filter_type=='Individual'?'selected':''; ?>>Individual</option>
                                <option value="Group" <?php echo $filter_type=='Group'?'selected':''; ?>>Group</option>
                            </select>
                        </div>
                        <button type="submit" class="btn-apply">Apply</button>
                    </form>

                    <?php if (count($assignments_list) > 0): ?>
                        <div class="ass-list">
                            <?php foreach ($assignments_list as $ass): ?>
                                <div class="ass-card">
                                    <div class="ass-info">
                                        <div class="ass-title"><?php echo htmlspecialchars($ass['fyp_title']); ?></div>
                                        <div class="ass-meta">
                                            <span><?php echo $ass['fyp_assignment_type']; ?></span>
                                            <span style="color:#666;">Deadline: <?php echo date('M d, Y', strtotime($ass['fyp_deadline'])); ?></span>
                                            <span style="color:#666; margin-left:15px;"><?php echo $ass['target_display_name']; ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="ass-stats">
                                        <div class="stat-badge stat-submitted">
                                            <i class="fa fa-file-upload"></i> Submitted: <?php echo $ass['stats']['submitted']; ?>
                                        </div>
                                        <div class="stat-badge stat-pending">
                                            <i class="fa fa-clock"></i> Not Submitted: <?php echo $ass['stats']['not_submitted']; ?>
                                        </div>
                                        <div class="stat-badge stat-graded">
                                            <i class="fa fa-check"></i> Graded: <?php echo $ass['stats']['graded']; ?>
                                        </div>
                                        <div class="stat-badge stat-revision">
                                            <i class="fa fa-undo"></i> Revision: <?php echo $ass['stats']['revision']; ?>
                                        </div>
                                    </div>
                                    
                                    <a href="?auth_user_id=<?php echo $auth_user_id; ?>&view_id=<?php echo $ass['fyp_assignmentid']; ?>" class="btn-view">Grade Students</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align:center; color:#999; padding:20px;">No assignments found.</div>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <a href="?auth_user_id=<?php echo $auth_user_id; ?>" class="back-link"><i class="fa fa-arrow-left"></i> Back to Assignments</a>
                
                <div class="card">
                    <div class="card-header">
                        <div>
                            Grading: <?php echo htmlspecialchars($current_assignment['fyp_title']); ?>
                            <div style="font-size:12px; font-weight:400; color:#666; margin-top:2px;">Deadline: <?php echo $current_assignment['fyp_deadline']; ?></div>
                        </div>
                    </div>
                    
                    <?php if (count($students_to_grade) > 0): ?>
                        <?php foreach ($students_to_grade as $stud): 
                            $status = $stud['final_status'];
                            $cssStatus = str_replace(' ', '', $status);
                            $canGrade = ($status == 'Turned In' || $status == 'Late Turned In' || $status == 'Resubmitted' || $status == 'Graded' || $status == 'Need Revision');
                        ?>
                            <div class="student-card">
                                <div class="stud-info">
                                    <div style="font-weight:600; color:#333; font-size:16px;"><?php echo htmlspecialchars($stud['fyp_studname']); ?></div>
                                    
                                    <div style="font-size:13px; color:#888; margin-bottom:5px;"><?php echo htmlspecialchars($stud['fyp_studid']); ?></div>
                                    
                                    <?php if(!empty($stud['display_group_name'])): ?>
                                        <div style="font-size:12px; color:#0056b3; font-weight:600; margin-bottom:5px;">
                                            <i class="fa fa-users"></i> <?php echo htmlspecialchars($stud['display_group_name']); ?>
                                        </div>
                                    <?php endif; ?>

                                    <span class="stud-status-badge st-<?php echo $cssStatus; ?>"><?php echo $status; ?></span>
                                    
                                    <?php if(!empty($stud['fyp_submission_date'])): ?>
                                        <div class="submission-meta">
                                            Submitted: <br><?php echo $stud['fyp_submission_date']; ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if(!empty($stud['fyp_submitted_file'])): ?>
                                        <a href="<?php echo $stud['fyp_submitted_file']; ?>" target="_blank" class="file-link">
                                            <i class="fa fa-download"></i> View File
                                        </a>
                                        <?php if (!empty($stud['is_inherited'])): ?>
                                            <span class="inherited-badge" title="Submitted by group leader">(via Leader)</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="no-file">No file uploaded</span>
                                    <?php endif; ?>
                                </div>
                                
                                <form method="POST" class="grade-form">
                                    <input type="hidden" name="assignment_id" value="<?php echo $view_assignment_id; ?>">
                                    <input type="hidden" name="student_id" value="<?php echo $stud['fyp_studid']; ?>">
                                    
                                    <div class="gf-row">
                                        <div class="gf-group" style="flex:0 0 100px;">
                                            <label>Marks (0-100)</label>
                                            <input type="number" name="marks" class="gf-input" value="<?php echo $stud['fyp_marks']; ?>" min="0" max="100">
                                        </div>
                                        <div class="gf-group">
                                            <label>Feedback / Comments</label>
                                            <input type="text" name="feedback" class="gf-input" value="<?php echo htmlspecialchars($stud['fyp_feedback']); ?>" placeholder="Enter comments for student...">
                                        </div>
                                    </div>
                                    
                                    <div class="action-buttons">
                                        <button type="submit" name="return_grade" class="btn-return" title="Grade and Return" <?php echo $canGrade ? '' : 'disabled'; ?>>
                                            <i class="fa fa-check-circle"></i> Return (Grade)
                                        </button>
                                        <button type="submit" name="return_revision" class="btn-revision" title="Request Revision" <?php echo $canGrade ? '' : 'disabled'; ?>>
                                            <i class="fa fa-undo"></i> Return with Revision
                                        </button>
                                    </div>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align:center; color:#999; padding:20px;">No students found for this assignment target.</div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>