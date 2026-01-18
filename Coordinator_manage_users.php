<?php
// ====================================================
// Coordinator_manage_users.php - Mainpage UI Style
// ====================================================
include("connect.php");

// Include email configuration
if (file_exists('Email_config_SMTP.php')) {
    include("Email_config_SMTP.php");
} else {
    include("email_config.php");
}

// Input cleaning function
function clean_input($data) {
    if (is_array($data)) {
        return array_map('clean_input', $data);
    }
    $data = trim($data);
    $data = strip_tags($data);
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// Download CSV Template
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

// Get parameters
$auth_user_id = filter_input(INPUT_GET, 'auth_user_id', FILTER_VALIDATE_INT);
$tab = clean_input($_GET['tab'] ?? 'student'); 
$sort_order = clean_input($_GET['sort'] ?? 'newest'); 
$search_query = clean_input($_GET['search'] ?? '');
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$limit = 15; 
$offset = ($page - 1) * $limit;

// Menu Active State Helper
$current_page = ($tab == 'supervisor') ? 'manage_supervisors' : 'manage_students';

if (!$auth_user_id) { 
    header("location: login.php"); 
    exit; 
}

// Get Coordinator Info
$user_name = "Coordinator";
$user_avatar = "image/user.png"; 
$coordinator_id = null;

$stmt = $conn->prepare("SELECT fyp_coordinatorid, fyp_name, fyp_profileimg FROM coordinator WHERE fyp_userid = ?");
$stmt->bind_param("i", $auth_user_id); 
$stmt->execute(); 
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) { 
    $coordinator_id = $row['fyp_coordinatorid'];
    if (!empty($row['fyp_name'])) $user_name = htmlspecialchars($row['fyp_name']); 
    if (!empty($row['fyp_profileimg'])) $user_avatar = $row['fyp_profileimg'];
}
$stmt->close();

// Helper functions
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

// Initialize SweetAlert variables
$swal_icon = '';
$swal_title = '';
$swal_text = '';
$approved_list = []; 

// Get Dropdown Options
$academic_options = [];
$res_acd = $conn->query("SELECT * FROM academic_year ORDER BY fyp_acdyear DESC");
while ($r = $res_acd->fetch_assoc()) $academic_options[] = $r;

$programme_options = [];
$res_prog = $conn->query("SELECT * FROM programme ORDER BY fyp_progname ASC");
while ($r = $res_prog->fetch_assoc()) $programme_options[] = $r;

// ====================================================
// POST Request Handling
// ====================================================

// 1. Toggle User Status (Active <-> Archived)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['toggle_status'])) {
    $target_uid = intval($_POST['user_id']);
    $current_status = $_POST['current_status'];
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

// 2. Toggle Moderator Role (1 <-> 0)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['toggle_moderator'])) {
    $target_uid = intval($_POST['user_id']);
    $new_mod_status = intval($_POST['new_mod_status']); // 1 or 0
    
    // Update supervisor table
    $stmt = $conn->prepare("UPDATE supervisor SET fyp_ismoderator = ? WHERE fyp_userid = ?");
    $stmt->bind_param("ii", $new_mod_status, $target_uid);
    
    if ($stmt->execute()) {
        $swal_icon = "success"; 
        $swal_title = "Role Updated"; 
        $swal_text = "Supervisor is " . ($new_mod_status == 1 ? "now a Moderator." : "no longer a Moderator.");
    } else {
        $swal_icon = "error"; $swal_title = "Error"; $swal_text = "Failed to update role.";
    }
    $stmt->close();
}

// 3. Approve Password Reset
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['approve_reset'])) {
    $req_id = intval($_POST['request_id']);
    $stmt = $conn->prepare("SELECT student_id, email FROM password_reset_requests WHERE request_id = ? AND status = 'Pending'");
    $stmt->bind_param("i", $req_id);
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($request) {
        $new_pass = generateRandomPassword(10);
        $stud_id = $request['student_id'];
        $conn->begin_transaction();
        try {
            $stmtUser = $conn->prepare("UPDATE user u JOIN student s ON u.fyp_userid = s.fyp_userid SET u.fyp_passwordhash = ? WHERE s.fyp_studid = ?");
            $stmtUser->bind_param("ss", $new_pass, $stud_id);
            $stmtUser->execute();
            $stmtUser->close();
            
            $stmtReq = $conn->prepare("UPDATE password_reset_requests SET status = 'Approved', reviewed_by = ?, reviewed_at = NOW() WHERE request_id = ?");
            $stmtReq->bind_param("ii", $coordinator_id, $req_id);
            $stmtReq->execute();
            $stmtReq->close();
            
            $conn->commit();
            
            $subject = "✅ Password Reset Approved";
            $htmlBody = "<div style='font-family: Arial; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'><h2 style='color:#28a745;'>Password Reset Successful</h2><p>Your request has been approved.</p><div style='background:#f8f9fa; padding:15px; border-left:4px solid #28a745;'><p><strong>Student ID:</strong> {$stud_id}</p><p><strong>New Password:</strong> <span style='font-family:monospace; background:#eee; padding:3px;'>{$new_pass}</span></p></div><p>Please login and change it immediately.</p></div>";
            sendSimpleEmail($request['email'], $subject, $htmlBody);
            $swal_icon = "success"; $swal_title = "Approved"; $swal_text = "Password reset and emailed to student.";
        } catch (Exception $e) {
            $conn->rollback(); $swal_icon = "error"; $swal_title = "Error"; $swal_text = "Failed to reset password.";
        }
    }
}

// 4. Reject Password Reset
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reject_registration'])) {
    $reg_id = intval($_POST['reg_id']);
    $conn->query("DELETE FROM pending_registration WHERE id=$reg_id");
    $swal_icon = "info"; $swal_title = "Rejected"; $swal_text = "Registration rejected.";
}

// 5. Bulk Approve Students
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['bulk_approve'])) {
    $selected_ids = $_POST['selected_registrations'] ?? [];
    if (empty($selected_ids)) { $swal_icon = "error"; $swal_title = "Oops..."; $swal_text = "Please select at least one registration."; } else {
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
                    
                    $stud_name = (!empty($reg['first_name']) && !empty($reg['last_name'])) ? $reg['first_name'] . " " . $reg['last_name'] : explode('@', $reg['email'])[0];
                    $stmt2 = $conn->prepare("INSERT INTO student (fyp_studid, fyp_studname, fyp_academicid, fyp_progid, fyp_email, fyp_userid, fyp_group) VALUES (?, ?, 1, 1, ?, ?, 'Individual')");
                    $stmt2->bind_param("sssi", $student_id, $stud_name, $reg['email'], $user_id); $stmt2->execute(); $stmt2->close();
                    
                    $stmt3 = $conn->prepare("UPDATE pending_registration SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
                    $stmt3->bind_param("ii", $coordinator_id, $reg_id); $stmt3->execute(); $stmt3->close();
                    $conn->commit();
                    
                    $emailResult = sendStudentApprovalEmail($reg['email'], $stud_name, $password, $student_id);
                    $success_count++;
                } catch (Exception $e) { $conn->rollback(); }
            }
        }
        if ($success_count > 0) { $swal_icon = "success"; $swal_title = "Success!"; $swal_text = "Approved {$success_count} students."; } 
        else { $swal_icon = "error"; $swal_title = "Oops..."; $swal_text = "Failed to approve."; }
    }
}

// 6. Bulk Archive Students
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['bulk_archive_students'])) {
    $del_ids = $_POST['archive_user_ids'] ?? [];
    if (empty($del_ids)) { $swal_icon = "error"; $swal_title = "Oops..."; $swal_text = "Please select students to archive."; } else {
        $count = 0;
        $stmtArch = $conn->prepare("UPDATE `user` SET fyp_status = 'Archived' WHERE fyp_userid = ?");
        foreach($del_ids as $uid) {
            $uid = intval($uid);
            $stmtArch->bind_param("i", $uid);
            if($stmtArch->execute()) { $count++; }
        }
        $stmtArch->close();
        $swal_icon = "success"; $swal_title = "Archived!"; $swal_text = "Archived {$count} students.";
    }
}

// 7. Add Single Student
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_single_student'])) {
    try {
        $fname = clean_input($_POST['first_name']); $lname = clean_input($_POST['last_name']);
        $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
        $matrix = clean_input($_POST['matrix_no']);
        $contact = $_POST['contact']; $acd_id = $_POST['academic_id'] ?: 1; $prog_id = $_POST['prog_id'] ?: 1;
        
        if (!$email) throw new Exception("Invalid email.");
        $full_name = $fname . " " . $lname;
        $gen_password = generateRandomPassword(10);
        
        $chk = $conn->query("SELECT fyp_userid FROM `user` WHERE fyp_username = '$email'");
        if ($chk->num_rows > 0) { $swal_icon = "error"; $swal_title = "Exists"; $swal_text = "Email already exists!"; } else {
            $conn->begin_transaction();
            $stmtUser = $conn->prepare("INSERT INTO `user` (fyp_username, fyp_passwordhash, fyp_usertype, fyp_status, fyp_datecreated) VALUES (?, ?, 'student', 'Active', NOW())");
            $stmtUser->bind_param("ss", $email, $gen_password); $stmtUser->execute(); $uid = $conn->insert_id;
            
            $stmtStud = $conn->prepare("INSERT INTO student (fyp_userid, fyp_studname, fyp_studid, fyp_email, fyp_contactno, fyp_group, fyp_academicid, fyp_progid) VALUES (?, ?, ?, ?, ?, 'Individual', ?, ?)");
            $stmtStud->bind_param("issssii", $uid, $full_name, $matrix, $email, $contact, $acd_id, $prog_id);
            $stmtStud->execute();
            $conn->commit();
            sendStudentApprovalEmail($email, $full_name, $gen_password, $matrix);
            $swal_icon = "success"; $swal_title = "Success"; $swal_text = "Student added!";
        }
    } catch (Exception $e) { $swal_icon = "error"; $swal_title = "Error"; $swal_text = $e->getMessage(); }
}

// 8. CSV Import Students
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['import_students']) && is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
    $file = fopen($_FILES['csv_file']['tmp_name'], "r"); fgetcsv($file); $count = 0;
    while (($data = fgetcsv($file, 1000, ",")) !== FALSE) {
        if ($data[0] && $data[3]) {
            $count++; 
        }
    }
    $swal_icon = "success"; $swal_title = "Imported"; $swal_text = "Processed CSV import.";
}

// 9. Add Supervisor
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_supervisor'])) {
    try {
        $uname = clean_input($_POST['username']); 
        if (!filter_var($uname, FILTER_VALIDATE_EMAIL)) throw new Exception("Invalid email format.");
        $pass = clean_input($_POST['password']); $name = clean_input($_POST['full_name']); $sid = clean_input($_POST['staff_id']);
        
        // Check if username exists
        $stmtCheck = $conn->prepare("SELECT fyp_userid FROM `user` WHERE fyp_username = ?");
        $stmtCheck->bind_param("s", $uname); $stmtCheck->execute();
        if ($stmtCheck->get_result()->num_rows > 0) throw new Exception("Username exists!");
        
        // A. Insert into USER table
        $stmt = $conn->prepare("INSERT INTO `user` (fyp_username, fyp_passwordhash, fyp_usertype, fyp_status, fyp_datecreated) VALUES (?, ?, 'lecturer', 'Active', NOW())");
        $stmt->bind_param("ss", $uname, $pass); $stmt->execute(); $nid = $conn->insert_id; 

        // B. Insert into SUPERVISOR table
        $is_mod = isset($_POST['is_moderator']) ? 1 : 0; 
        
        $stmt2 = $conn->prepare("INSERT INTO supervisor (fyp_userid, fyp_name, fyp_staffid, fyp_email, fyp_ismoderator, fyp_datecreated) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt2->bind_param("isssi", $nid, $name, $sid, $uname, $is_mod); 
        $stmt2->execute(); 
        $new_sup_id = $conn->insert_id; 
        
        // C. Insert into Quota
        $stmtQ = $conn->prepare("INSERT INTO quota (fyp_supervisorid, fyp_numofstudent) VALUES (?, 3)");
        $stmtQ->bind_param("i", $new_sup_id); $stmtQ->execute();
        
        sendLecturerCredentialsEmail($uname, $name, $pass, $sid);
        $swal_icon = "success"; $swal_title = "Success"; $swal_text = "Supervisor added successfully!";
    } catch (Exception $e) { 
        $swal_icon = "error"; $swal_title = "Error"; $swal_text = $e->getMessage();
    }
}

// ====================================================
// Data Fetching Area
// ====================================================

// Get Pending Registrations
$pending_registrations = [];
if ($tab == 'student') {
    $checkTable = $conn->query("SHOW TABLES LIKE 'pending_registration'");
    if ($checkTable && $checkTable->num_rows > 0) {
        $res_pending = $conn->query("SELECT * FROM pending_registration WHERE status = 'pending' ORDER BY created_at ASC");
        if ($res_pending) while ($row = $res_pending->fetch_assoc()) $pending_registrations[] = $row;
    }
}

// Get Password Reset Requests
$pending_resets = [];
$checkResetTable = $conn->query("SHOW TABLES LIKE 'password_reset_requests'");
if ($checkResetTable && $checkResetTable->num_rows > 0) {
    $res_pwd = $conn->query("SELECT * FROM password_reset_requests WHERE status = 'Pending' ORDER BY request_date DESC");
    if($res_pwd) while($r = $res_pwd->fetch_assoc()) $pending_resets[] = $r;
}

// Main Data List
$data_list = [];
$orderBy = "s.fyp_studname ASC"; 
$colName = ($tab == 'student') ? "s.fyp_studname" : "s.fyp_name";

if ($sort_order == 'newest') {
    $orderBy = "u.fyp_datecreated DESC, $colName ASC";
} elseif ($sort_order == 'oldest') {
    $orderBy = "u.fyp_datecreated ASC, $colName ASC";
} else {
    $orderBy = "$colName ASC";
}

$total_rows = 0;
$total_pages = 1;

$status_filter = "";
if ($sort_order == 'archived') {
    $status_filter = " AND u.fyp_status = 'Archived'";
}

if ($tab == 'student') {
    $countSql = "SELECT COUNT(*) as total FROM `user` u
                 LEFT JOIN student s ON u.fyp_userid = s.fyp_userid 
                 WHERE u.fyp_usertype = 'student' $status_filter";
                 
    if (!empty($search_query)) {
        $countSql .= " AND (s.fyp_studname LIKE '%$search_query%' OR s.fyp_studid LIKE '%$search_query%' OR u.fyp_username LIKE '%$search_query%')";
    }
    $total_res = $conn->query($countSql);
    $total_rows = $total_res->fetch_assoc()['total'];
    $total_pages = ceil($total_rows / $limit);

    $sql = "SELECT s.*, u.fyp_username, u.fyp_datecreated, u.fyp_status, u.fyp_userid, p.fyp_progname, a.fyp_acdyear, a.fyp_intake 
            FROM `user` u
            LEFT JOIN student s ON u.fyp_userid = s.fyp_userid 
            LEFT JOIN programme p ON s.fyp_progid = p.fyp_progid 
            LEFT JOIN academic_year a ON s.fyp_academicid = a.fyp_academicid 
            WHERE u.fyp_usertype = 'student' $status_filter";
            
    if (!empty($search_query)) {
        $search_term = "%{$search_query}%";
        $sql .= " AND (s.fyp_studname LIKE ? OR s.fyp_studid LIKE ? OR u.fyp_username LIKE ?)";
    }
    $sql .= " ORDER BY $orderBy LIMIT ?, ?";
    
    $stmt = $conn->prepare($sql);
    if (!empty($search_query)) {
        $stmt->bind_param("sssii", $search_term, $search_term, $search_term, $offset, $limit);
    } else {
        $stmt->bind_param("ii", $offset, $limit);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    
} else {
    // SUPERVISOR Query
    $countSql = "SELECT COUNT(*) as total FROM `user` u
                 LEFT JOIN supervisor s ON u.fyp_userid = s.fyp_userid 
                 WHERE u.fyp_usertype = 'lecturer' $status_filter";
    if (!empty($search_query)) {
        $countSql .= " AND (s.fyp_name LIKE '%$search_query%' OR s.fyp_staffid LIKE '%$search_query%' OR u.fyp_username LIKE '%$search_query%')";
    }
    $total_res = $conn->query($countSql);
    $total_rows = $total_res->fetch_assoc()['total'];
    $total_pages = ceil($total_rows / $limit);

    $sql = "SELECT s.*, u.fyp_username, u.fyp_datecreated, u.fyp_status, u.fyp_userid 
            FROM `user` u
            LEFT JOIN supervisor s ON u.fyp_userid = s.fyp_userid 
            WHERE u.fyp_usertype = 'lecturer' $status_filter";
            
    if (!empty($search_query)) {
        $search_term = "%{$search_query}%";
        $sql .= " AND (s.fyp_name LIKE ? OR s.fyp_staffid LIKE ? OR u.fyp_username LIKE ?)";
    }
    $sql .= " ORDER BY $orderBy LIMIT ?, ?";
    
    $stmt = $conn->prepare($sql);
    if (!empty($search_query)) {
        $stmt->bind_param("sssii", $search_term, $search_term, $search_term, $offset, $limit);
    } else {
        $stmt->bind_param("ii", $offset, $limit);
    }
    $stmt->execute();
    $res = $stmt->get_result();
}

if ($res) while ($r = $res->fetch_assoc()) $data_list[] = $r;

// Menu Definition
$menu_items = [
    'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home', 'link' => 'Coordinator_mainpage.php?page=dashboard'],
    'profile'   => ['name' => 'My Profile', 'icon' => 'fa-user', 'link' => 'Coordinator_profile.php'], 
    'management' => ['name' => 'User Management', 'icon' => 'fa-users-cog', 'sub_items' => ['manage_students' => ['name' => 'Student List', 'icon' => 'fa-user-graduate', 'link' => 'Coordinator_manage_users.php?tab=student'], 'manage_supervisors' => ['name' => 'Supervisor List', 'icon' => 'fa-chalkboard-teacher', 'link' => 'Coordinator_manage_users.php?tab=supervisor'], 'manage_quota' => ['name' => 'Supervisor Quota', 'icon' => 'fa-chalkboard-teacher', 'link' => 'Coordinator_manage_quota.php']]],
    'project_mgmt' => ['name' => 'Project Mgmt', 'icon' => 'fa-tasks', 'sub_items' => ['propose_project' => ['name' => 'Propose Project', 'icon' => 'fa-plus-circle', 'link' => 'Coordinator_purpose.php'], 'project_requests' => ['name' => 'Project Requests', 'icon' => 'fa-envelope-open-text', 'link' => 'Coordinator_projectreq.php'], 'project_list' => ['name' => 'All Projects & Groups', 'icon' => 'fa-list-alt', 'link' => 'Coordinator_manage_project.php']]],
    'assessment' => ['name' => 'Assessment', 'icon' => 'fa-clipboard-check', 'sub_items' => ['propose_assignment' => ['name' => 'Create Assignment', 'icon' => 'fa-plus', 'link' => 'Coordinator_assignment_purpose.php'], 'grade_assignment' => ['name' => 'Grade Assignments', 'icon' => 'fa-check-square', 'link' => 'Coordinator_assignment_grade.php']]],
    'announcements' => ['name' => 'Announcements', 'icon' => 'fa-bullhorn', 'sub_items' => ['post_announcement' => ['name' => 'Post New', 'icon' => 'fa-pen', 'link' => 'Coordinator_announcement.php'], 'view_announcements' => ['name' => 'View History', 'icon' => 'fa-history', 'link' => 'Coordinator_announcement_view.php']]],
    'schedule' => ['name' => 'My Schedule', 'icon' => 'fa-calendar-alt', 'link' => 'Coordinator_meeting.php'], 
    'allocation' => ['name' => 'Moderator Allocation', 'icon' => 'fa-people-arrows', 'link' => 'Coordinator_allocation.php'],
    'reports' => ['name' => 'System Reports', 'icon' => 'fa-chart-bar', 'link' => 'Coordinator_history.php'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage <?php echo ucfirst($tab); ?>s</title>
    <link rel="icon" type="image/png" href="image/ladybug.png?v=<?php echo time(); ?>">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
            margin: 0;
            background-color: #f4f6f9;
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
        }

        /* Sidebar - Mainpage Style */
        .main-menu { background: var(--sidebar-bg); border-right: 1px solid rgba(255,255,255,0.1); position: fixed; top: 0; bottom: 0; height: 100%; left: 0; width: 60px; overflow-y: auto; overflow-x: hidden; transition: width 0.3s ease; z-index: 1000; box-shadow: 2px 0 10px rgba(0,0,0,0.1); }
        .main-menu:hover, nav.main-menu.expanded { width: 260px; overflow: visible; }
        .main-menu ul { margin: 0; padding: 0; list-style: none; }
        .main-menu li { position: relative; display: block; width: 260px; }
        .main-menu li > a { display: flex; align-items: center; padding: 15px 25px; color: var(--sidebar-text); text-decoration: none; font-size: 14px; transition: all 0.2s; border-left: 4px solid transparent; width: 100%; padding: 0; display: table; border-collapse: collapse; }
        
        .main-menu .nav-icon { position: relative; display: table-cell; width: 60px; height: 46px; text-align: center; vertical-align: middle; font-size: 18px; }
        .main-menu .nav-text { position: relative; display: table-cell; vertical-align: middle; width: 190px; padding-left: 10px; white-space: nowrap; }
        .main-menu li:hover > a, nav.main-menu li.active > a { color: #fff; background-color: var(--sidebar-hover); border-left: 4px solid #fff; }
        .main-menu > ul.logout { position: absolute; left: 0; bottom: 0; width: 100%; }

        /* Submenu */
        .dropdown-arrow { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); transition: transform 0.3s; font-size: 12px; }
        .menu-item.open .dropdown-arrow { transform: translateY(-50%) rotate(180deg); }
        .submenu { background-color: rgba(0,0,0,0.2); max-height: 0; overflow: hidden; transition: max-height 0.3s ease-out; margin: 0; padding: 0; list-style: none; }
        .menu-item.open .submenu { max-height: 500px; transition: max-height 0.5s ease-in; }
        .submenu li > a { padding-left: 0; }
        .submenu .nav-text { padding-left: 20px; font-size: 13px; }

        /* Main Content */
        .main-content-wrapper { margin-left: 60px; flex: 1; padding: 25px; width: calc(100% - 60px); transition: margin-left 0.3s ease; }
        
        /* Page Header */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; background: white; padding: 20px; border-radius: 12px; box-shadow: var(--card-shadow); }
        .welcome-text h1 { margin: 0; font-size: 24px; color: var(--primary-color); font-weight: 600; }
        .welcome-text p { margin: 5px 0 0; color: #666; font-size: 14px; }
        .logo-section { display: flex; align-items: center; gap: 12px; }
        .logo-img { height: 40px; width: auto; background: white; padding: 2px; border-radius: 6px; }
        .system-title { font-size: 20px; font-weight: 600; color: var(--primary-color); letter-spacing: 0.5px; }
        
        .user-section { display: flex; align-items: center; gap: 10px; }
        .user-badge { font-size: 13px; color: #666; background: #f0f0f0; padding: 5px 10px; border-radius: 20px; }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
        .user-avatar-placeholder { width: 40px; height: 40px; border-radius: 50%; background: #0056b3; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; }

        /* Page Specific */
        .card { background: white; padding: 25px; border-radius: 12px; box-shadow: var(--card-shadow); margin-bottom: 25px; border: 1px solid #eef2f7; }
        
        .tabs { display: flex; gap: 10px; margin-bottom: 5px; }
        .tab-btn { text-decoration: none; padding: 10px 20px; border-radius: 8px; font-weight: 500; font-size: 14px; transition: all 0.3s; border: 1px solid transparent; display: inline-flex; align-items: center; gap: 8px; }
        .tab-btn.active { background: var(--primary-color); color: white; box-shadow: 0 4px 10px rgba(0,86,179,0.3); }
        .tab-btn.inactive { background: white; color: #666; border-color: #ddd; }
        .tab-btn.inactive:hover { background: #e7f1ff; color: var(--primary-color); border-color: var(--primary-color); }

        .action-bar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; background: #fff; padding: 15px; border-radius: 12px; box-shadow: var(--card-shadow); margin-bottom: 20px; }
        .search-form { display: flex; flex: 1; min-width: 300px; gap: 10px; }
        .search-wrapper { position: relative; flex: 1; }
        .search-wrapper i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #aaa; }
        .search-input { width: 100%; padding: 12px 15px 12px 40px; border: 1px solid #e0e0e0; border-radius: 8px; outline: none; font-family: 'Poppins', sans-serif; transition: border 0.3s; box-sizing: border-box; }
        .search-btn { padding: 0 25px; background: var(--primary-color); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; font-size: 14px; transition: 0.3s; height: 45px; }
        .search-btn:hover { background: #004494; transform: translateY(-1px); }
        .clear-btn { padding: 0 15px; background: #dc3545; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; font-size: 14px; transition: 0.3s; height: 45px; display: flex; align-items: center; justify-content: center; text-decoration: none; }
        .clear-btn:hover { background: #c82333; }

        .sort-select { padding: 12px; border: 1px solid #e0e0e0; border-radius: 8px; outline: none; font-family: 'Poppins', sans-serif; cursor: pointer; color: #555; }

        table { width: 100%; border-collapse: separate; border-spacing: 0; }
        th { background: #f8f9fa; color: #555; font-weight: 600; padding: 15px; text-align: left; border-bottom: 2px solid #eee; font-size: 13px; text-transform: uppercase; }
        td { padding: 15px; border-bottom: 1px solid #eee; font-size: 14px; color: #444; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background-color: #fafbfc; }

        .badge { padding: 5px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge-yellow { background: #fff3cd; color: #856404; }
        
        .status-badge { padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .status-Active { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .status-Archived { background: #e2e3e5; color: #383d41; border: 1px solid #d6d8db; }
        
        .btn-icon { padding: 5px 10px; border-radius: 4px; border: none; cursor: pointer; transition: 0.2s; }
        .btn-archive { background: #ffc107; color: #333; }
        .btn-activate { background: #28a745; color: #fff; }
        .btn-action { padding: 8px 15px; border-radius: 6px; border: none; cursor: pointer; font-size: 13px; font-weight: 500; transition: 0.2s; display: inline-flex; align-items: center; gap: 5px; }
        .btn-blue { background: var(--primary-color); color: white; }
        .btn-red { background: #ff4d4f; color: white; }
        .btn-green { background: #28a745; color: white; }

        .alert-box { padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-size: 14px; }
        .alert-pending { background: #fff8e1; border-left: 4px solid #ffc107; color: #856404; }
        .alert-reset { background: #e3f2fd; border-left: 4px solid #2196f3; color: #0d47a1; }
        
        /* Modal */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
        .modal-content { background: white; margin: 5% auto; padding: 30px; border-radius: 16px; width: 500px; box-shadow: 0 20px 50px rgba(0,0,0,0.2); animation: fadeIn 0.3s ease; position: relative; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        .close-modal { position: absolute; right: 25px; top: 20px; font-size: 24px; cursor: pointer; color: #999; }
        
        .modal h2 { margin-top: 0; margin-bottom: 25px; font-size: 22px; color: #333; border-bottom: 1px solid #eee; padding-bottom: 15px; }
        .input-row { display: flex; gap: 15px; margin-bottom: 15px; }
        .input-group { margin-bottom: 15px; width: 100%; }
        .input-group label { display: block; margin-bottom: 8px; font-size: 13px; font-weight: 500; color: #555; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; font-family: 'Poppins'; transition: 0.3s; }
        .form-control:focus { border-color: var(--primary-color); outline: none; box-shadow: 0 0 0 3px rgba(0,86,179,0.1); }
        .btn-block { width: 100%; padding: 12px; font-size: 15px; justify-content: center; margin-top: 10px; }

        @media (max-width: 900px) { .main-content-wrapper { margin-left: 0; width: 100%; } }
    </style>
</head>
<body>

    <!-- Sidebar Navigation -->
    <nav class="main-menu">
        <ul>
            <?php foreach ($menu_items as $key => $item): ?>
                <?php 
                    $isActive = ($key == $current_page);
                    // Also check if any child is active
                    $childActive = false;
                    if (isset($item['sub_items'])) {
                        foreach ($item['sub_items'] as $sub_key => $sub) {
                             if($sub_key == $current_page) $childActive = true;
                        }
                    }
                    
                    $linkUrl = isset($item['link']) ? $item['link'] : "#";
                    if ($linkUrl !== "#" && strpos($linkUrl, '.php') !== false) {
                         $separator = (strpos($linkUrl, '?') !== false) ? '&' : '?';
                         $linkUrl .= $separator . "auth_user_id=" . urlencode($auth_user_id);
                    }
                    
                    $hasSubmenu = isset($item['sub_items']);
                ?>
                <li class="menu-item <?php echo ($isActive || $childActive) ? 'open active' : ''; ?>">
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
                                <li>
                                    <a href="<?php echo $subLinkUrl; ?>" class="<?php echo ($sub_key == $current_page) ? 'active' : ''; ?>">
                                        <i class="fa <?php echo $sub_item['icon']; ?> nav-icon"></i>
                                        <span class="nav-text"><?php echo $sub_item['name']; ?></span>
                                    </a>
                                </li>
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

    <!-- Content -->
    <div class="main-content-wrapper">
        <div class="page-header">
            <div class="welcome-text">
                <h1>User Management</h1>
                <p>Manage students, supervisors and access controls.</p>
            </div>
            
            <div class="logo-section">
                <img src="image/ladybug.png?v=<?php echo time(); ?>" alt="Logo" class="logo-img">
                <span class="system-title">FYP Portal</span>
            </div>

            <div class="user-section">
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

        <?php if (!empty($pending_resets)): ?>
        <div class="alert-box alert-reset">
            <i class="fas fa-key fa-lg"></i>
            <div style="flex:1;">
                <strong>Password Reset Requests</strong><br>
                Students have requested password resets. Please review below.
            </div>
        </div>
        <div class="card">
            <table>
                <thead><tr><th>Student ID</th><th>Email</th><th>Requested</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($pending_resets as $req): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($req['student_id']); ?></strong></td>
                        <td><?php echo htmlspecialchars($req['email']); ?></td>
                        <td><?php echo htmlspecialchars($req['request_date']); ?></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="request_id" value="<?php echo $req['request_id']; ?>">
                                <button type="submit" name="approve_reset" class="btn-action btn-green"><i class="fas fa-check"></i> Approve</button>
                                <button type="submit" name="reject_reset" class="btn-action btn-red"><i class="fas fa-times"></i> Reject</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if ($tab == 'student' && !empty($pending_registrations)): ?>
        <div class="alert-box alert-pending">
            <i class="fas fa-user-clock fa-lg"></i>
            <div style="flex:1;">
                <strong>Pending Registrations</strong><br>
                New students are waiting for approval to access the system.
            </div>
        </div>
        <div class="card" style="border: 1px solid #ffeeba;">
            <form method="POST">
                <div class="section-header">
                    <h3 style="color:#856404; margin:0;">New Accounts</h3>
                    <button type="submit" name="bulk_approve" class="btn-action btn-green"><i class="fas fa-check-double"></i> Approve Selected</button>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 40px;"><input type="checkbox" onclick="toggleAll(this, 'pending-chk')"></th>
                            <th>Name</th><th>Email</th><th>Applied On</th><th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_registrations as $reg): ?>
                        <tr>
                            <td><input type="checkbox" name="selected_registrations[]" value="<?php echo $reg['id']; ?>" class="pending-chk"></td>
                            <td><?php echo htmlspecialchars((!empty($reg['first_name']) ? $reg['first_name'].' '.$reg['last_name'] : $reg['email'])); ?></td>
                            <td><?php echo htmlspecialchars($reg['email']); ?></td>
                            <td><?php echo date('d M, h:i A', strtotime($reg['created_at'])); ?></td>
                            <td><button type="button" class="btn-action btn-red" onclick="rejectOne(<?php echo $reg['id']; ?>)">Reject</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
        </div>
        <?php endif; ?>

        <div class="section-header">
            <h2 style="font-size: 24px; margin:0;"><i class="fas fa-users-cog"></i> User List</h2>
            <div class="tabs">
                <a href="?auth_user_id=<?php echo $auth_user_id; ?>&tab=student" class="tab-btn <?php echo $tab=='student'?'active':'inactive';?>">
                    <i class="fas fa-user-graduate"></i> Students
                </a>
                <a href="?auth_user_id=<?php echo $auth_user_id; ?>&tab=supervisor" class="tab-btn <?php echo $tab=='supervisor'?'active':'inactive';?>">
                    <i class="fas fa-chalkboard-teacher"></i> Supervisors
                </a>
            </div>
        </div>

        <div class="action-bar">
            <form method="GET" class="search-form">
                <input type="hidden" name="auth_user_id" value="<?php echo $auth_user_id; ?>">
                <input type="hidden" name="tab" value="<?php echo $tab; ?>">
                <input type="hidden" name="sort" value="<?php echo $sort_order; ?>">
                
                <div class="search-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" class="search-input" placeholder="Search by name, ID or email..." value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
                
                <button type="submit" class="search-btn"><i class="fas fa-arrow-right"></i> Search</button>
                
                <?php if (!empty($search_query) || $sort_order != 'newest'): ?>
                    <a href="?auth_user_id=<?php echo $auth_user_id; ?>&tab=<?php echo $tab; ?>" class="clear-btn"><i class="fas fa-times"></i> Clear</a>
                <?php endif; ?>
            </form>

            <div style="display: flex; gap: 10px; align-items: center;">
                <select class="sort-select" onchange="updateSort(this.value)">
                    <option value="newest" <?php echo $sort_order=='newest'?'selected':''; ?>>Newest First</option>
                    <option value="oldest" <?php echo $sort_order=='oldest'?'selected':''; ?>>Oldest First</option>
                    <option value="archived" <?php echo $sort_order=='archived'?'selected':''; ?>>Archived Only</option>
                </select>
                
                <?php if($tab == 'student'): ?>
                    <a href="?auth_user_id=<?php echo $auth_user_id; ?>&tab=student&action=download_template" class="btn-action btn-blue" style="background:#6c757d; border-radius:8px;">
                        <i class="fas fa-download"></i> CSV Template
                    </a>
                <?php endif; ?>

                <button onclick="openModal()" class="btn-action btn-blue" style="padding: 12px 20px; font-size: 14px;">
                    <i class="fas fa-plus"></i> Add New
                </button>
            </div>
        </div>

        <div style="background: #e9ecef; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-file-upload" style="color: #666;"></i>
            <span style="font-size: 14px; font-weight: 500; color: #555;">Bulk Upload:</span>
            <form method="POST" enctype="multipart/form-data" style="display:flex; gap:10px; flex:1;">
                <input type="file" name="<?php echo $tab=='student'?'csv_file':'csv_file_sup'; ?>" accept=".csv" required style="font-size: 13px;">
                <button name="<?php echo $tab=='student'?'import_students':'import_supervisors'; ?>" class="btn-action btn-green" style="padding: 5px 10px;">Upload CSV</button>
            </form>
        </div>

        <div class="card" style="padding: 0; overflow: hidden;">
            <form method="POST" id="deleteForm">
                <?php if($tab == 'student'): ?>
                    <div style="padding: 15px; background: #fff5f5; border-bottom: 1px solid #ffe3e3; display: flex; justify-content: flex-end; gap: 10px;">
                        <button type="button" onclick="confirmArchive()" class="btn-action btn-archive"><i class="fas fa-archive"></i> Archive Selected</button>
                    </div>
                <?php endif; ?>
                
                <table id="dataTable">
                    <thead>
                        <tr>
                            <?php if($tab == 'student'): ?><th style="width: 40px; text-align: center;"><input type="checkbox" onclick="toggleAll(this, 'del-chk')"></th><?php endif; ?>
                            <th>Name</th>
                            <th>ID / Staff ID</th>
                            <th>Email</th>
                            
                            <?php if($tab == 'supervisor'): ?><th>Role</th><?php endif; ?>
                            
                            <th>Status</th>
                            <?php if($tab == 'student'): ?>
                                <th>Programme</th>
                                <th>Intake</th>
                            <?php endif; ?>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($data_list as $row): 
                            $status = !empty($row['fyp_status']) ? $row['fyp_status'] : 'Active';
                        ?>
                        <tr style="<?php echo ($status == 'Archived') ? 'opacity: 0.6; background: #f9f9f9;' : ''; ?>">
                            <?php if($tab == 'student'): ?>
                                <td style="text-align: center;"><input type="checkbox" name="archive_user_ids[]" value="<?php echo $row['fyp_userid']; ?>" class="del-chk"></td>
                            <?php endif; ?>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div style="width: 35px; height: 35px; background: #e3f2fd; color: var(--primary-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600;">
                                        <?php echo strtoupper(substr($row[$tab=='student'?'fyp_studname':'fyp_name'] ?? 'U', 0, 1)); ?>
                                    </div>
                                    <?php echo htmlspecialchars($row[$tab=='student'?'fyp_studname':'fyp_name'] ?? $row['fyp_username']); ?>
                                </div>
                            </td>
                            <td><span style="font-family: monospace; background: #f8f9fa; padding: 4px 8px; border-radius: 4px; font-weight: 600;"><?php echo htmlspecialchars($row[$tab=='student'?'fyp_studid':'fyp_staffid'] ?? '-'); ?></span></td>
                            <td><?php echo htmlspecialchars($row['fyp_username']); ?></td>
                            
                            <?php if($tab == 'supervisor'): 
                                $isMod = isset($row['fyp_ismoderator']) && $row['fyp_ismoderator'] == 1;
                            ?>
                                <td>
                                    <?php if($isMod): ?>
                                        <span class="badge" style="background:#6f42c1; color:white;"><i class="fas fa-gavel"></i> Moderator</span>
                                    <?php else: ?>
                                        <span class="badge" style="background:#e9ecef; color:#666;">Supervisor</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            
                            <td>
                                <span class="status-badge status-<?php echo $status; ?>">
                                    <?php echo $status; ?>
                                </span>
                            </td>

                            <?php if($tab == 'student'): ?>
                                <td><?php echo htmlspecialchars($row['fyp_progname'] ?? '-'); ?></td>
                                <td><span class="badge badge-yellow"><?php echo htmlspecialchars(($row['fyp_acdyear']??'') . ' ' . ($row['fyp_intake']??'')); ?></span></td>
                            <?php endif; ?>
                            
                            <td>
                                <button type="button" onclick="toggleStatus(<?php echo $row['fyp_userid']; ?>, '<?php echo $status; ?>')" 
                                        class="btn-icon <?php echo ($status == 'Active') ? 'btn-archive' : 'btn-activate'; ?>"
                                        title="<?php echo ($status == 'Active') ? 'Archive User' : 'Activate User'; ?>">
                                    <i class="fas <?php echo ($status == 'Active') ? 'fa-archive' : 'fa-undo'; ?>"></i>
                                </button>
                                
                                <?php if($tab == 'supervisor'): 
                                    $isMod = isset($row['fyp_ismoderator']) && $row['fyp_ismoderator'] == 1;
                                ?>
                                    <button type="button" onclick="toggleModerator(<?php echo $row['fyp_userid']; ?>, <?php echo $isMod ? 0 : 1; ?>)" 
                                            class="btn-icon" 
                                            style="background: <?php echo $isMod ? '#6c757d' : '#6f42c1'; ?>; color: white; margin-left: 5px;"
                                            title="<?php echo $isMod ? 'Remove Moderator Role' : 'Assign as Moderator'; ?>">
                                        <i class="fas <?php echo $isMod ? 'fa-user-minus' : 'fa-user-plus'; ?>"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($data_list)): ?>
                            <tr><td colspan="7" style="text-align:center; padding: 30px; color: #999;">No results found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <input type="hidden" name="bulk_archive_students" value="1">
            </form>
            
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?auth_user_id=<?php echo $auth_user_id; ?>&tab=<?php echo $tab; ?>&sort=<?php echo $sort_order; ?>&search=<?php echo urlencode($search_query); ?>&page=<?php echo $page - 1; ?>" class="page-link">Previous</a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?auth_user_id=<?php echo $auth_user_id; ?>&tab=<?php echo $tab; ?>&sort=<?php echo $sort_order; ?>&search=<?php echo urlencode($search_query); ?>&page=<?php echo $i; ?>" 
                       class="page-link <?php echo ($i == $page) ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?auth_user_id=<?php echo $auth_user_id; ?>&tab=<?php echo $tab; ?>&sort=<?php echo $sort_order; ?>&search=<?php echo urlencode($search_query); ?>&page=<?php echo $page + 1; ?>" class="page-link">Next</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

    </div>

    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="document.getElementById('addModal').style.display='none'">&times;</span>
            <h2>Add New <?php echo ucfirst($tab); ?></h2>
            <form method="POST">
                <?php if($tab == 'student'): ?>
                    <div class="input-row">
                        <div class="input-group">
                            <label>First Name</label>
                            <input type="text" name="first_name" class="form-control" required>
                        </div>
                        <div class="input-group">
                            <label>Last Name</label>
                            <input type="text" name="last_name" class="form-control" required>
                        </div>
                    </div>
                    <div class="input-group">
                        <label>Student ID (Matrix)</label>
                        <input type="text" name="matrix_no" class="form-control" placeholder="TP0..." required>
                    </div>
                    <div class="input-group">
                        <label>Email Address</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <label style="font-size: 12px; color: #999; font-weight: 600; text-transform: uppercase;">Optional Details</label>
                        <div class="input-row" style="margin-top: 10px;">
                            <input type="text" name="contact" class="form-control" placeholder="Phone">
                            <select name="academic_id" class="form-control">
                                <option value="">Intake</option>
                                <?php foreach($academic_options as $a) echo "<option value='{$a['fyp_academicid']}'>{$a['fyp_intake']}</option>"; ?>
                            </select>
                        </div>
                        <select name="prog_id" class="form-control">
                            <option value="">Programme</option>
                            <?php foreach($programme_options as $p) echo "<option value='{$p['fyp_progid']}'>{$p['fyp_progname']}</option>"; ?>
                        </select>
                    </div>

                    <button name="add_single_student" class="btn-action btn-blue btn-block">
                        <i class="fas fa-save"></i> Save Student
                    </button>
                <?php else: ?>
                    <div class="input-group"><label>Full Name</label><input type="text" name="full_name" class="form-control" required></div>
                    <div class="input-group"><label>Email (Username)</label><input type="text" name="username" class="form-control" required></div>
                    <div class="input-row">
                        <div class="input-group"><label>Staff ID</label><input type="text" name="staff_id" class="form-control" required></div>
                        <div class="input-group"><label>Password</label><input type="password" name="password" class="form-control" required></div>
                    </div>

                    <div class="input-group" style="display:flex; align-items:center; gap:10px; background:#f8f9fa; padding:10px; border-radius:8px;">
                        <input type="checkbox" name="is_moderator" id="chk_mod" value="1" style="width:auto; transform:scale(1.2);">
                        <label for="chk_mod" style="margin:0; cursor:pointer;">Assign as <strong>Moderator</strong> (Can grade other groups)</label>
                    </div>

                    <button name="add_supervisor" class="btn-action btn-blue btn-block">Save Supervisor</button>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <form id="singleDeleteForm" method="POST" style="display:none;">
        <input type="hidden" name="delete_user_ids[]" id="single_delete_id">
        <input type="hidden" name="bulk_delete_students" value="1">
    </form>
    
    <form id="rejectForm" method="POST" style="display:none;">
        <input type="hidden" name="reg_id" id="reject_reg_id">
        <input type="hidden" name="reject_registration" value="1">
    </form>
    
    <form id="statusForm" method="POST" style="display:none;">
        <input type="hidden" name="user_id" id="status_user_id">
        <input type="hidden" name="current_status" id="status_current_val">
        <input type="hidden" name="toggle_status" value="1">
    </form>

    <form id="modForm" method="POST" style="display:none;">
        <input type="hidden" name="user_id" id="mod_user_id">
        <input type="hidden" name="new_mod_status" id="mod_new_status">
        <input type="hidden" name="toggle_moderator" value="1">
    </form>

    <script>
        function toggleSubmenu(element) {
            element.parentElement.classList.toggle('open');
        }

        <?php if (!empty($swal_icon)): ?>
        Swal.fire({
            icon: "<?php echo $swal_icon; ?>",
            title: "<?php echo $swal_title; ?>",
            text: "<?php echo $swal_text; ?>"
        });
        <?php endif; ?>

        function openModal() { document.getElementById("addModal").style.display = "block"; }
        function toggleAll(source, className) {
            document.querySelectorAll('.' + className).forEach(cb => cb.checked = source.checked);
        }
        
        function updateSort(sortValue) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('sort', sortValue);
            window.location.search = urlParams.toString();
        }

        function rejectOne(id) {
            document.getElementById('reject_reg_id').value = id;
            Swal.fire({
                title: 'Reject Registration?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Yes, Reject'
            }).then((result) => {
                if (result.isConfirmed) document.getElementById('rejectForm').submit();
            });
        }
        
        function confirmDelete() {
            Swal.fire({
                title: 'Delete Selected Users?', text: "This action cannot be undone.", icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Yes, Delete'
            }).then((result) => {
                if (result.isConfirmed) document.getElementById('deleteForm').submit();
            });
        }
        
        function confirmArchive() {
            Swal.fire({
                title: 'Archive Selected Users?', text: "Users will be disabled but data kept.", icon: 'warning', showCancelButton: true, confirmButtonColor: '#ffc107', confirmButtonText: 'Yes, Archive'
            }).then((result) => {
                if (result.isConfirmed) document.getElementById('deleteForm').submit();
            });
        }
        
        function deleteSingle(userId) {
            Swal.fire({
                title: 'Delete User?', text: "This action cannot be undone.", icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Yes, Delete'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('single_delete_id').value = userId;
                    document.getElementById('singleDeleteForm').submit();
                }
            });
        }
        
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

        // Toggle Moderator Function
        function toggleModerator(userId, newStatus) {
            let actionText = (newStatus === 1) ? 'Promote to Moderator' : 'Demote to Supervisor';
            let confirmText = (newStatus === 1) 
                ? 'They will be able to grade assignments for other groups.' 
                : 'They will lose moderator privileges.';
            
            Swal.fire({
                title: actionText + '?',
                text: confirmText,
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#6f42c1',
                confirmButtonText: 'Yes, Confirm',
                cancelButtonColor: '#d33'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('mod_user_id').value = userId;
                    document.getElementById('mod_new_status').value = newStatus;
                    document.getElementById('modForm').submit();
                }
            });
        }
        
        window.onclick = function(event) {
            if (event.target == document.getElementById("addModal")) {
                document.getElementById("addModal").style.display = "none";
            }
        }
    </script>
</body>
</html>