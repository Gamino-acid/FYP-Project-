<?php
// ====================================================
// Coordinator_manage_users.php - With Active/Archived Status
// ====================================================
include("connect.php");

// Security: Use SMTP config if available
if (file_exists('Email_config_SMTP.php')) {
    include("Email_config_SMTP.php");
} else {
    include("email_config.php");
}

// ----------------------------------------------------
// SECURITY: Input Sanitization Helper
// ----------------------------------------------------
function clean_input($data) {
    if (is_array($data)) {
        return array_map('clean_input', $data);
    }
    $data = trim($data);
    $data = strip_tags($data);
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// ----------------------------------------------------
// 1. Handle CSV Template Download
// ----------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] == 'download_template') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="student_import_template.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, array('First Name', 'Last Name', 'Student ID', 'Email', 'Contact', 'ProgID', 'AcdID'));
    fputcsv($output, array('John', 'Doe', 'TP050000', 'john@example.com', '0123456789', '1', '1'));
    fclose($output);
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// SECURITY: Validate Integer for ID
$auth_user_id = filter_input(INPUT_GET, 'auth_user_id', FILTER_VALIDATE_INT);
$tab = clean_input($_GET['tab'] ?? 'student'); 
$sort_order = clean_input($_GET['sort'] ?? 'newest'); 

if (!$auth_user_id) { header("location: login.php"); exit; }

// Get Coordinator info
$user_name = "Coordinator";
$user_avatar = "image/user.png"; 
$coordinator_id = null;

$stmt = $conn->prepare("SELECT fyp_coordinatorid, fyp_name FROM coordinator WHERE fyp_userid = ?");
$stmt->bind_param("i", $auth_user_id); 
$stmt->execute(); 
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) { 
    $coordinator_id = $row['fyp_coordinatorid'];
    if (!empty($row['fyp_name'])) $user_name = htmlspecialchars($row['fyp_name']); 
}
$stmt->close();

// --- 菜单定义 ---
$current_page_key = 'management'; 
$current_sub_key = ($tab == 'supervisor') ? 'manage_supervisors' : 'manage_students'; 

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
        'name' => 'Project Mgmt', 
        'icon' => 'fa-tasks', 
        'sub_items' => [
            'propose_project' => ['name' => 'Propose Project', 'icon' => 'fa-plus-circle', 'link' => 'Coordinator_purpose.php'],
            'project_requests' => ['name' => 'Project Requests', 'icon' => 'fa-envelope-open-text', 'link' => 'Coordinator_projectreq.php'],
            'project_list' => ['name' => 'All Projects & Groups', 'icon' => 'fa-list-alt', 'link' => 'Coordinator_project_list.php'],
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
        ]
    ],
    'schedule' => ['name' => 'My Schedule', 'icon' => 'fa-calendar-alt', 'link' => 'Coordinator_meeting.php'], 
    'data_io' => ['name' => 'Data Management', 'icon' => 'fa-database', 'link' => 'Coordinator_data_io.php'],
    'reports' => ['name' => 'System Reports', 'icon' => 'fa-chart-bar', 'link' => 'Coordinator_history.php'],
];

// Helper Functions
function generateNextStudentId($conn) {
    $sql = "SELECT MAX(CAST(SUBSTRING(fyp_studid, 3) AS UNSIGNED)) as max_num FROM student WHERE fyp_studid LIKE 'TP%'";
    $result = $conn->query($sql); 
    $row = $result->fetch_assoc();
    $nextNum = ($row && $row['max_num']) ? $row['max_num'] + 1 : 50020;
    return 'TP' . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
}

if (!function_exists('generateRandomPassword')) {
    function generateRandomPassword($length = 8) {
        return substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, $length);
    }
}

// Variables for SweetAlert
$swal_icon = ''; $swal_title = ''; $swal_text = '';
$approved_list = []; 

// Get dropdown data
$academic_options = [];
$res_acd = $conn->query("SELECT * FROM academic_year ORDER BY fyp_acdyear DESC");
while ($r = $res_acd->fetch_assoc()) $academic_options[] = $r;

$programme_options = [];
$res_prog = $conn->query("SELECT * FROM programme ORDER BY fyp_progname ASC");
while ($r = $res_prog->fetch_assoc()) $programme_options[] = $r;

// ====================================================
// LOGIC HANDLERS
// ====================================================

// --- 1. TOGGLE USER STATUS (NEW) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['toggle_status'])) {
    $target_uid = intval($_POST['user_id']);
    $current_status = $_POST['current_status'];
    // 切换状态: Active <-> Archived
    $new_status = ($current_status == 'Active') ? 'Archived' : 'Active';
    
    $stmt = $conn->prepare("UPDATE `user` SET fyp_status = ? WHERE fyp_userid = ?");
    $stmt->bind_param("si", $new_status, $target_uid);
    
    if ($stmt->execute()) {
        $swal_icon = "success"; $swal_title = "Updated"; 
        $swal_text = "User status changed to " . $new_status;
    } else {
        $swal_icon = "error"; $swal_title = "Error"; $swal_text = "Failed to update status.";
    }
    $stmt->close();
}

// --- 2. BULK APPROVE ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['bulk_approve'])) {
    $selected_ids = $_POST['selected_registrations'] ?? [];
    if (empty($selected_ids)) {
        $swal_icon = "error"; $swal_title = "Oops..."; $swal_text = "Please select at least one registration.";
    } else {
        $success_count = 0;
        foreach ($selected_ids as $reg_id) {
            $reg_id = intval($reg_id);
            $stmt = $conn->prepare("SELECT * FROM pending_registration WHERE id = ? AND status = 'pending'");
            $stmt->bind_param("i", $reg_id); $stmt->execute();
            $reg = $stmt->get_result()->fetch_assoc(); $stmt->close();
            
            if ($reg) {
                $password = generateRandomPassword(10);
                $student_id = generateNextStudentId($conn);
                $conn->begin_transaction();
                try {
                    $stmt1 = $conn->prepare("INSERT INTO user (fyp_username, fyp_passwordhash, fyp_usertype, fyp_status, fyp_datecreated) VALUES (?, ?, 'student', 'Active', NOW())");
                    $stmt1->bind_param("ss", $reg['email'], $password); $stmt1->execute();
                    $user_id = $conn->insert_id; $stmt1->close();
                    
                    $default_acad = $academic_options[0]['fyp_academicid'] ?? 1;
                    $default_prog = $programme_options[0]['fyp_progid'] ?? 1;
                    $stud_name = (!empty($reg['first_name']) && !empty($reg['last_name'])) ? $reg['first_name'] . " " . $reg['last_name'] : explode('@', $reg['email'])[0];
                    
                    $stmt2 = $conn->prepare("INSERT INTO student (fyp_studid, fyp_studname, fyp_academicid, fyp_progid, fyp_email, fyp_userid, fyp_group) VALUES (?, ?, ?, ?, ?, ?, 'Individual')");
                    $stmt2->bind_param("ssiisi", $student_id, $stud_name, $default_acad, $default_prog, $reg['email'], $user_id);
                    $stmt2->execute(); $stmt2->close();
                    
                    $stmt3 = $conn->prepare("UPDATE pending_registration SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
                    $stmt3->bind_param("ii", $coordinator_id, $reg_id); $stmt3->execute(); $stmt3->close();
                    
                    $conn->commit();
                    
                    $emailResult = sendStudentApprovalEmail($reg['email'], $stud_name, $password, $student_id);
                    $approved_list[] = ['email' => $reg['email'], 'student_id' => $student_id, 'password' => $password, 'email_sent' => $emailResult['success']];
                    $success_count++;
                } catch (Exception $e) { $conn->rollback(); }
            }
        }
        if ($success_count > 0) { $swal_icon = "success"; $swal_title = "Success!"; $swal_text = "Approved {$success_count} students."; }
        else { $swal_icon = "error"; $swal_title = "Oops..."; $swal_text = "Failed to approve registrations."; }
    }
}

// --- 3. BULK DELETE ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['bulk_delete_students'])) {
    $del_ids = $_POST['delete_user_ids'] ?? [];
    if (empty($del_ids)) {
        $swal_icon = "error"; $swal_title = "Oops..."; $swal_text = "Please select students to delete.";
    } else {
        $del_count = 0;
        $stmtDelStudent = $conn->prepare("DELETE FROM student WHERE fyp_userid = ?");
        $stmtDelUser = $conn->prepare("DELETE FROM user WHERE fyp_userid = ?");
        foreach($del_ids as $uid) {
            $uid = intval($uid);
            $stmtDelStudent->bind_param("i", $uid); $stmtDelStudent->execute();
            $stmtDelUser->bind_param("i", $uid);
            if($stmtDelUser->execute()) { $del_count++; }
        }
        $stmtDelStudent->close(); $stmtDelUser->close();
        $swal_icon = "success"; $swal_title = "Deleted!"; $swal_text = "Deleted {$del_count} students.";
    }
}

// --- 4. ADD STUDENT MANUALLY ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_single_student'])) {
    try {
        $fname = clean_input($_POST['first_name']); $lname = clean_input($_POST['last_name']);
        $email_input = clean_input($_POST['email']); $email = filter_var($email_input, FILTER_VALIDATE_EMAIL);
        $matrix = clean_input($_POST['matrix_no']); $contact = clean_input($_POST['contact']);
        $acd_id = clean_input($_POST['academic_id']); $prog_id = clean_input($_POST['prog_id']);
        
        if (!$email) throw new Exception("Invalid email.");
        $full_name = $fname . " " . $lname; $gen_password = generateRandomPassword(10);
        
        $stmtCheck = $conn->prepare("SELECT fyp_userid FROM `user` WHERE fyp_username = ?");
        $stmtCheck->bind_param("s", $email); $stmtCheck->execute(); $chk = $stmtCheck->get_result();
        $stmtCheck2 = $conn->prepare("SELECT fyp_studid FROM `student` WHERE fyp_studid = ?");
        $stmtCheck2->bind_param("s", $matrix); $stmtCheck2->execute(); $chk2 = $stmtCheck2->get_result();

        if ($chk->num_rows > 0) { throw new Exception("Email ($email) already exists!"); } 
        elseif ($chk2->num_rows > 0) { throw new Exception("ID ($matrix) already exists!"); } 
        else {
            $conn->begin_transaction();
            $stmtUser = $conn->prepare("INSERT INTO `user` (fyp_username, fyp_passwordhash, fyp_usertype, fyp_status, fyp_datecreated) VALUES (?, ?, 'student', 'Active', NOW())");
            $stmtUser->bind_param("ss", $email, $gen_password); $stmtUser->execute(); $uid = $conn->insert_id;

            $stmtStud = $conn->prepare("INSERT INTO student (fyp_userid, fyp_studname, fyp_studid, fyp_email, fyp_contactno, fyp_group, fyp_academicid, fyp_progid) VALUES (?, ?, ?, ?, ?, 'Individual', ?, ?)");
            $stmtStud->bind_param("issssii", $uid, $full_name, $matrix, $email, $contact, $acd_id, $prog_id); $stmtStud->execute();
            $conn->commit();
            sendStudentApprovalEmail($email, $full_name, $gen_password, $matrix);
            $swal_icon = "success"; $swal_title = "Success!"; $swal_text = "Student added!";
        }
    } catch (Exception $e) { 
        $swal_icon = "error"; $swal_title = "Error"; $swal_text = $e->getMessage();
    }
}

// --- 5. IMPORT STUDENTS ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['import_students'])) {
    if (is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
        $file = fopen($_FILES['csv_file']['tmp_name'], "r"); 
        fgetcsv($file); $count = 0;
        $stmtChk = $conn->prepare("SELECT fyp_userid FROM `user` WHERE fyp_username = ?");
        $stmtU = $conn->prepare("INSERT INTO `user` (fyp_username, fyp_passwordhash, fyp_usertype, fyp_status, fyp_datecreated) VALUES (?, ?, 'student', 'Active', NOW())");
        $stmtS = $conn->prepare("INSERT INTO student (fyp_userid, fyp_studname, fyp_studid, fyp_email, fyp_contactno, fyp_group, fyp_academicid, fyp_progid) VALUES (?, ?, ?, ?, ?, 'Individual', ?, ?)");

        while (($data = fgetcsv($file, 1000, ",")) !== FALSE) {
            $i_fname = clean_input($data[0]??''); $i_lname = clean_input($data[1]??''); $i_matrix = clean_input($data[2]??''); 
            $i_email = filter_var(clean_input($data[3]??''), FILTER_VALIDATE_EMAIL);
            $i_contact = clean_input($data[4]??''); $i_prog = intval($data[5]??1); $i_acd = intval($data[6]??1);
            
            if ($i_fname && $i_email && $i_matrix) {
                $i_fullname = $i_fname . " " . $i_lname;
                $stmtChk->bind_param("s", $i_email); $stmtChk->execute();
                if ($stmtChk->get_result()->num_rows == 0) {
                    $pass = generateRandomPassword(10);
                    $stmtU->bind_param("ss", $i_email, $pass); $stmtU->execute(); $uid = $conn->insert_id;
                    $stmtS->bind_param("issssii", $uid, $i_fullname, $i_matrix, $i_email, $i_contact, $i_acd, $i_prog); $stmtS->execute();
                    sendStudentApprovalEmail($i_email, $i_fullname, $pass, $i_matrix);
                    $count++;
                }
            }
        }
        $swal_icon = "success"; $swal_title = "Imported"; $swal_text = "$count students imported.";
    }
}

// --- 6. ADD SUPERVISOR ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_supervisor'])) {
    try {
        $uname = clean_input($_POST['username']); 
        if (!filter_var($uname, FILTER_VALIDATE_EMAIL)) throw new Exception("Invalid email format.");
        $pass = clean_input($_POST['password']); $name = clean_input($_POST['full_name']); $sid = clean_input($_POST['staff_id']);
        
        $stmtCheck = $conn->prepare("SELECT fyp_userid FROM `user` WHERE fyp_username = ?");
        $stmtCheck->bind_param("s", $uname); $stmtCheck->execute();
        if ($stmtCheck->get_result()->num_rows > 0) throw new Exception("Username exists!");
        
        $stmt = $conn->prepare("INSERT INTO `user` (fyp_username, fyp_passwordhash, fyp_usertype, fyp_status, fyp_datecreated) VALUES (?, ?, 'lecturer', 'Active', NOW())");
        $stmt->bind_param("ss", $uname, $pass); $stmt->execute(); $nid = $conn->insert_id; 

        $stmt2 = $conn->prepare("INSERT INTO supervisor (fyp_userid, fyp_name, fyp_staffid, fyp_email, fyp_ismoderator) VALUES (?, ?, ?, ?, 0)");
        $stmt2->bind_param("isss", $nid, $name, $sid, $uname); $stmt2->execute(); $new_sup_id = $conn->insert_id; 
        
        $stmtQ = $conn->prepare("INSERT INTO quota (fyp_supervisorid, fyp_numofstudent) VALUES (?, 3)");
        $stmtQ->bind_param("i", $new_sup_id); $stmtQ->execute();
        
        sendLecturerCredentialsEmail($uname, $name, $pass, $sid);
        $swal_icon = "success"; $swal_title = "Success"; $swal_text = "Supervisor added!";
    } catch (Exception $e) { 
        $swal_icon = "error"; $swal_title = "Error"; $swal_text = $e->getMessage();
    }
}

// --- 7. IMPORT SUPERVISORS ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['import_supervisors'])) {
    if (is_uploaded_file($_FILES['csv_file_sup']['tmp_name'])) {
        $file = fopen($_FILES['csv_file_sup']['tmp_name'], "r"); fgetcsv($file); $count = 0;
        $stmtChk = $conn->prepare("SELECT fyp_userid FROM `user` WHERE fyp_username = ?");
        $stmtU = $conn->prepare("INSERT INTO `user` (fyp_username, fyp_passwordhash, fyp_usertype, fyp_status, fyp_datecreated) VALUES (?, ?, 'lecturer', 'Active', NOW())");
        $stmtS = $conn->prepare("INSERT INTO supervisor (fyp_userid, fyp_name, fyp_staffid, fyp_email, fyp_ismoderator) VALUES (?, ?, ?, ?, 0)");
        $stmtQ = $conn->prepare("INSERT INTO quota (fyp_supervisorid, fyp_numofstudent) VALUES (?, 3)");

        while (($data = fgetcsv($file, 1000, ",")) !== FALSE) {
            $i_name = clean_input($data[0]??''); $i_staffid = clean_input($data[1]??''); $i_email = filter_var(clean_input($data[2]??''), FILTER_VALIDATE_EMAIL);
            if ($i_name && $i_staffid && $i_email) {
                $stmtChk->bind_param("s", $i_email); $stmtChk->execute();
                if ($stmtChk->get_result()->num_rows == 0) {
                    $pass = generateRandomPassword(10);
                    $stmtU->bind_param("ss", $i_email, $pass); $stmtU->execute(); $uid = $conn->insert_id;
                    $stmtS->bind_param("isss", $uid, $i_name, $i_staffid, $i_email); $stmtS->execute(); $sup_id = $conn->insert_id;
                    $stmtQ->bind_param("i", $sup_id); $stmtQ->execute();
                    sendLecturerCredentialsEmail($i_email, $i_name, $pass, $i_staffid);
                    $count++;
                }
            }
        }
        $swal_icon = "success"; $swal_title = "Imported"; $swal_text = "$count supervisors.";
    }
}

// --- 8. REJECT REGISTRATION ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reject_registration'])) {
    $reg_id = intval($_POST['reg_id']);
    $stmt = $conn->prepare("UPDATE pending_registration SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW() WHERE id = ? AND status = 'pending'");
    $stmt->bind_param("ii", $coordinator_id, $reg_id);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $swal_icon = "info"; $swal_title = "Rejected"; $swal_text = "Registration rejected.";
    }
}

// ----------------------------------------------------
// FETCH DATA
// ----------------------------------------------------
$pending_registrations = [];
if ($tab == 'student') {
    $checkTable = $conn->query("SHOW TABLES LIKE 'pending_registration'");
    if ($checkTable && $checkTable->num_rows > 0) {
        $res_pending = $conn->query("SELECT * FROM pending_registration WHERE status = 'pending' ORDER BY created_at ASC");
        if ($res_pending) while ($row = $res_pending->fetch_assoc()) $pending_registrations[] = $row;
    }
}

$data_list = [];
$orderBy = "s.fyp_studname ASC"; 
if ($sort_order == 'newest') { $orderBy = "u.fyp_datecreated DESC, s.fyp_studname ASC"; } 
elseif ($sort_order == 'oldest') { $orderBy = "u.fyp_datecreated ASC, s.fyp_studname ASC"; }

if ($tab == 'student') {
    // 增加 u.fyp_status 查询
    $sql = "SELECT s.*, u.fyp_username, u.fyp_datecreated, u.fyp_status, p.fyp_progname, a.fyp_acdyear, a.fyp_intake 
            FROM student s JOIN `user` u ON s.fyp_userid = u.fyp_userid 
            LEFT JOIN programme p ON s.fyp_progid = p.fyp_progid LEFT JOIN academic_year a ON s.fyp_academicid = a.fyp_academicid 
            ORDER BY $orderBy";
    $res = $conn->query($sql);
} else {
    // 增加 u.fyp_status 查询
    $res = $conn->query("SELECT s.*, u.fyp_username, u.fyp_datecreated, u.fyp_status FROM supervisor s JOIN `user` u ON s.fyp_userid = u.fyp_userid ORDER BY s.fyp_name ASC");
}
if ($res) while ($r = $res->fetch_assoc()) $data_list[] = $r;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | Coordinator</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* === UI Design Synchronized with Mainpage === */
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap');
        :root { --primary-color: #0056b3; --primary-hover: #004494; --secondary-color: #f4f4f9; --text-color: #333; --border-color: #e0e0e0; --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); --gradient-start: #eef2f7; --gradient-end: #ffffff; --sidebar-width: 260px; }
        body { font-family: 'Poppins', sans-serif; margin: 0; background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end)); color: var(--text-color); min-height: 100vh; display: flex; flex-direction: column; }
        
        /* Topbar & Sidebar */
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
        
        /* Page Content Specific */
        .card { background: #fff; padding: 25px; border-radius: 12px; box-shadow: var(--card-shadow); margin-bottom: 20px; }
        .card-header { font-size: 18px; font-weight: 600; color: #333; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        
        .btn { background: #0056b3; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; transition: 0.2s; }
        .btn:hover { background: #004494; transform: translateY(-1px); }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        .btn-outline { background: transparent; border: 1px solid #0056b3; color: #0056b3; }
        .btn-outline:hover { background: #e3effd; }
        
        /* Table Styles */
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { background: #f8f9fa; text-align: left; padding: 12px 15px; color: #555; font-size: 13px; font-weight: 600; border-bottom: 2px solid #eee; }
        .data-table td { padding: 12px 15px; border-bottom: 1px solid #f0f0f0; font-size: 14px; color: #333; }
        .data-table tr:hover { background-color: #fcfcfc; }
        .checkbox-col { width: 40px; text-align: center; }
        
        .search-bar { width: 100%; padding: 10px 15px; border: 1px solid #ddd; border-radius: 6px; margin-bottom: 20px; font-family: inherit; box-sizing: border-box; }
        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
        .filter-select { padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; cursor:pointer; font-family: inherit; }
        
        /* New Status Badges */
        .status-badge { padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .status-Active { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .status-Archived { background: #e2e3e5; color: #383d41; border: 1px solid #d6d8db; }
        
        .btn-icon { padding: 5px 10px; border-radius: 4px; border: none; cursor: pointer; transition: 0.2s; }
        .btn-archive { background: #ffc107; color: #333; }
        .btn-activate { background: #28a745; color: #fff; }

        /* Modal */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); justify-content: center; align-items: center; }
        .modal-content { background-color: #fff; padding: 30px; border-radius: 12px; width: 500px; position: relative; box-shadow: 0 10px 25px rgba(0,0,0,0.2); margin: 5% auto; }
        .close { position: absolute; right: 20px; top: 15px; font-size: 24px; cursor: pointer; color: #aaa; }
        .close:hover { color: #333; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; font-size: 13px; color: #555; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; font-family: inherit; }
        
        @media (max-width: 900px) { .layout-container { flex-direction: column; } .sidebar { width: 100%; min-height: auto; } }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="logo"><img src="image/ladybug.png" alt="Logo" style="width: 32px; margin-right: 10px;"> FYP Coordinator</div>
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
                        $isActive = ($key == $current_page_key);
                        $hasActiveChild = false;
                        if (isset($item['sub_items'])) {
                            foreach ($item['sub_items'] as $sub_key => $sub) {
                                if ($sub_key == $current_sub_key) { $hasActiveChild = true; break; }
                            }
                        }
                        $linkUrl = (isset($item['link']) && $item['link'] !== "#") ? $item['link'] . (strpos($item['link'], '?') !== false ? '&' : '?') . "auth_user_id=" . urlencode($auth_user_id) : "#";
                    ?>
                    <li class="menu-item <?php echo $hasActiveChild ? 'has-active-child' : ''; ?>">
                        <a href="<?php echo $linkUrl; ?>" class="menu-link <?php echo $isActive ? 'active' : ''; ?>">
                            <span class="menu-icon"><i class="fa <?php echo $item['icon']; ?>"></i></span>
                            <?php echo $item['name']; ?>
                        </a>
                        <?php if (isset($item['sub_items'])): ?>
                            <ul class="submenu">
                                <?php foreach ($item['sub_items'] as $sub_key => $sub_item): 
                                    $subLinkUrl = (isset($sub_item['link']) && $sub_item['link'] !== "#") ? $sub_item['link'] . (strpos($sub_item['link'], '?') !== false ? '&' : '?') . "auth_user_id=" . urlencode($auth_user_id) : "#";
                                    $isSubActive = ($sub_key == $current_sub_key);
                                ?>
                                    <li><a href="<?php echo $subLinkUrl; ?>" class="menu-link <?php echo $isSubActive ? 'active' : ''; ?>" style="<?php echo $isSubActive ? 'background-color:#e3effd; color:#0056b3;' : ''; ?>">
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
            
            <?php if (!empty($approved_list)): ?>
            <div class="card" style="border-left: 5px solid #28a745;">
                <div class="card-header" style="color:#28a745;"><i class="fas fa-check-circle"></i> Successfully Approved</div>
                <table class="data-table">
                    <thead><tr><th>Email</th><th>ID</th><th>Password</th><th>Email Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($approved_list as $a): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($a['email']); ?></td>
                            <td><?php echo htmlspecialchars($a['student_id']); ?></td>
                            <td style="font-family:monospace; color:#d63384;"><?php echo htmlspecialchars($a['password']); ?></td>
                            <td><?php echo $a['email_sent'] ? 'Sent' : 'Failed'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <span>Manage <?php echo ucfirst($tab); ?>s</span>
                    <div>
                        <a href="?auth_user_id=<?php echo $auth_user_id; ?>&tab=student" class="btn <?php echo $tab=='student'?'':'btn-outline';?>" style="padding:6px 12px; font-size:12px;">Students</a>
                        <a href="?auth_user_id=<?php echo $auth_user_id; ?>&tab=supervisor" class="btn <?php echo $tab=='supervisor'?'':'btn-outline';?>" style="padding:6px 12px; font-size:12px;">Supervisors</a>
                    </div>
                </div>

                <?php if ($tab == 'student' && !empty($pending_registrations)): ?>
                <div style="background: #fffdf5; border: 1px solid #ffeba8; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
                    <h4 style="margin-top:0; color: #856404; margin-bottom:15px;"><i class="fas fa-clock"></i> Pending Approvals</h4>
                    <form method="POST">
                        <div style="margin-bottom:10px;">
                            <button type="submit" name="bulk_approve" class="btn btn-success"><i class="fas fa-check-double"></i> Approve Selected</button>
                        </div>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th class="checkbox-col"><input type="checkbox" onclick="toggleAll(this, 'pending-chk')"></th>
                                    <th>Name</th><th>Email</th><th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_registrations as $reg): ?>
                                <tr>
                                    <td class="checkbox-col"><input type="checkbox" name="selected_registrations[]" value="<?php echo $reg['id']; ?>" class="pending-chk"></td>
                                    <td><?php echo htmlspecialchars((!empty($reg['first_name']) ? $reg['first_name'].' '.$reg['last_name'] : $reg['email'])); ?></td>
                                    <td><?php echo htmlspecialchars($reg['email']); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-danger" style="padding:4px 8px; font-size:11px;" onclick="rejectOne(<?php echo $reg['id']; ?>)">Reject</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </form>
                </div>
                <?php endif; ?>

                <div class="toolbar">
                    <div style="display:flex; gap:10px; align-items:center;">
                        <button onclick="openModal()" class="btn"><i class="fas fa-plus-circle"></i> Add <?php echo ucfirst($tab); ?></button>
                        
                        <?php if($tab == 'student'): ?>
                            <select class="filter-select" onchange="window.location.href='?auth_user_id=<?php echo $auth_user_id; ?>&tab=student&sort='+this.value">
                                <option value="newest" <?php echo $sort_order=='newest'?'selected':''; ?>>Sort: Newest</option>
                                <option value="oldest" <?php echo $sort_order=='oldest'?'selected':''; ?>>Sort: Oldest</option>
                            </select>
                            <a href="?auth_user_id=<?php echo $auth_user_id; ?>&tab=student&action=download_template" class="btn btn-outline" target="_blank">
                                <i class="fas fa-download"></i> Template
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data" style="display:flex; gap:5px; align-items:center;">
                        <input type="file" name="<?php echo $tab=='student'?'csv_file':'csv_file_sup'; ?>" accept=".csv" required style="font-size:12px;">
                        <button name="<?php echo $tab=='student'?'import_students':'import_supervisors'; ?>" class="btn btn-success"><i class="fas fa-file-import"></i> Import</button>
                    </form>
                </div>

                <input type="text" id="searchInput" onkeyup="filterTable()" class="search-bar" placeholder="🔍 Search by name, ID or email...">
                
                <form method="POST" id="deleteForm">
                    <?php if($tab == 'student'): ?>
                        <div style="margin-bottom:10px; text-align:right;">
                            <button type="button" onclick="confirmDelete()" class="btn btn-danger"><i class="fas fa-trash-alt"></i> Delete Selected</button>
                            <input type="hidden" name="bulk_delete_students" value="1">
                        </div>
                    <?php endif; ?>
                    
                    <table class="data-table" id="dataTable">
                        <thead>
                            <tr>
                                <?php if($tab == 'student'): ?>
                                    <th class="checkbox-col"><input type="checkbox" onclick="toggleAll(this, 'del-chk')"></th>
                                <?php endif; ?>
                                <th>Name</th>
                                <th>ID</th>
                                <th>Email</th>
                                <th>Status</th>
                                <?php if($tab == 'student'): ?>
                                    <th>Programme</th>
                                    <th>Intake</th>
                                <?php endif; ?>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($data_list as $row): 
                                $status = !empty($row['fyp_status']) ? $row['fyp_status'] : 'Active';
                            ?>
                            <tr style="<?php echo ($status == 'Archived') ? 'opacity: 0.6; background: #f9f9f9;' : ''; ?>">
                                <?php if($tab == 'student'): ?>
                                    <td class="checkbox-col">
                                        <input type="checkbox" name="delete_user_ids[]" value="<?php echo $row['fyp_userid']; ?>" class="del-chk">
                                    </td>
                                <?php endif; ?>
                                <td>
                                    <?php echo htmlspecialchars($row[$tab=='student'?'fyp_studname':'fyp_name']); ?>
                                </td>
                                <td><strong style="color:var(--primary-color);"><?php echo htmlspecialchars($row[$tab=='student'?'fyp_studid':'fyp_staffid']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['fyp_username']); ?></td>
                                
                                <td>
                                    <span class="status-badge status-<?php echo $status; ?>">
                                        <?php echo $status; ?>
                                    </span>
                                </td>

                                <?php if($tab == 'student'): ?>
                                    <td><?php echo htmlspecialchars($row['fyp_progname'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars(($row['fyp_acdyear']??'') . ' ' . ($row['fyp_intake']??'')); ?></td>
                                <?php endif; ?>
                                
                                <td>
                                    <button type="button" onclick="toggleStatus(<?php echo $row['fyp_userid']; ?>, '<?php echo $status; ?>')" 
                                            class="btn-icon <?php echo ($status == 'Active') ? 'btn-archive' : 'btn-activate'; ?>"
                                            title="<?php echo ($status == 'Active') ? 'Archive User' : 'Activate User'; ?>">
                                        <i class="fas <?php echo ($status == 'Active') ? 'fa-archive' : 'fa-undo'; ?>"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </form>
            </div>
        </main>
    </div>

    <form id="statusForm" method="POST" style="display:none;">
        <input type="hidden" name="user_id" id="status_user_id">
        <input type="hidden" name="current_status" id="status_current_val">
        <input type="hidden" name="toggle_status" value="1">
    </form>

    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">Add New <?php echo ucfirst($tab); ?></h3>
            <form method="POST">
                <?php if($tab == 'student'): ?>
                    <div style="display:flex; gap:10px;">
                        <div class="form-group" style="flex:1;">
                            <label>First Name <span style="color:red">*</span></label>
                            <input type="text" name="first_name" required>
                        </div>
                        <div class="form-group" style="flex:1;">
                            <label>Last Name <span style="color:red">*</span></label>
                            <input type="text" name="last_name" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Email (Username) <span style="color:red">*</span></label>
                        <input type="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label>Student ID <span style="color:red">*</span></label>
                        <input type="text" name="matrix_no" placeholder="e.g. TP050000" required>
                    </div>
                    <div style="background:#f9f9f9; padding:15px; border-radius:6px; margin-bottom:15px;">
                        <div class="form-group">
                            <label>Contact No</label>
                            <input type="text" name="contact">
                        </div>
                        <div class="form-group">
                            <label>Academic Year</label>
                            <select name="academic_id">
                                <option value="">Select...</option>
                                <?php foreach($academic_options as $a) echo "<option value='{$a['fyp_academicid']}'>{$a['fyp_acdyear']} - {$a['fyp_intake']}</option>"; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Programme</label>
                            <select name="prog_id">
                                <option value="">Select...</option>
                                <?php foreach($programme_options as $p) echo "<option value='{$p['fyp_progid']}'>{$p['fyp_progname']}</option>"; ?>
                            </select>
                        </div>
                    </div>
                    <button name="add_single_student" class="btn btn-success" style="width:100%; justify-content:center;">Add Student</button>
                
                <?php else: ?>
                    <div class="form-group"><label>Full Name</label><input type="text" name="full_name" required></div>
                    <div class="form-group"><label>Email</label><input type="text" name="username" required></div>
                    <div class="form-group"><label>Staff ID</label><input type="text" name="staff_id" required></div>
                    <div class="form-group"><label>Password</label><input type="text" name="password" required></div>
                    <button name="add_supervisor" class="btn btn-success" style="width:100%; justify-content:center;">Add Supervisor</button>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <form id="rejectForm" method="POST" style="display:none;">
        <input type="hidden" name="reg_id" id="reject_reg_id">
        <input type="hidden" name="reject_registration" value="1">
    </form>

    <script>
        // SweetAlert
        <?php if (!empty($swal_icon)): ?>
        Swal.fire({
            icon: "<?php echo $swal_icon; ?>",
            title: "<?php echo $swal_title; ?>",
            text: "<?php echo $swal_text; ?>"
        });
        <?php endif; ?>

        function openModal() { document.getElementById("addModal").style.display = "flex"; }
        function closeModal() { document.getElementById("addModal").style.display = "none"; }
        window.onclick = function(event) { if (event.target == document.getElementById("addModal")) closeModal(); }

        function filterTable() {
            var input, filter, table, tr, td, i, txtValue;
            input = document.getElementById("searchInput");
            filter = input.value.toUpperCase();
            table = document.getElementById("dataTable");
            tr = table.getElementsByTagName("tr");
            for (i = 1; i < tr.length; i++) {
                var found = false;
                for(var j=1; j<=3; j++) {
                    td = tr[i].getElementsByTagName("td")[j];
                    if (td) {
                        txtValue = td.textContent || td.innerText;
                        if (txtValue.toUpperCase().indexOf(filter) > -1) {
                            found = true;
                            break;
                        }
                    }
                }
                tr[i].style.display = found ? "" : "none";
            }
        }

        function toggleAll(source, className) {
            var checkboxes = document.querySelectorAll('.' + className);
            checkboxes.forEach(function(cb) { cb.checked = source.checked; });
        }
        
        function rejectOne(regId) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You are about to reject this registration.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, reject it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('reject_reg_id').value = regId;
                    document.getElementById('rejectForm').submit();
                }
            });
        }
        
        function confirmDelete() {
            Swal.fire({
                title: 'Are you sure?',
                text: "This action cannot be undone.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Yes, delete!'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('deleteForm').submit();
                }
            });
        }

        // New function to handle status toggling
        function toggleStatus(userId, currentStatus) {
            let actionText = (currentStatus === 'Active') ? 'Archive' : 'Activate';
            let confirmText = (currentStatus === 'Active') ? 'User will not be able to login.' : 'User access will be restored.';
            
            Swal.fire({
                title: actionText + ' User?',
                text: confirmText,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: (currentStatus === 'Active') ? '#ffc107' : '#28a745',
                confirmButtonText: 'Yes, ' + actionText,
                cancelButtonColor: '#d33'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('status_user_id').value = userId;
                    document.getElementById('status_current_val').value = currentStatus;
                    document.getElementById('statusForm').submit();
                }
            });
        }
    </script>
</body>
</html>