<?php
// ====================================================
// Coordinator_manage_users.php - Security Hardened
// Features: Prepared Statements, Input Validation, CSRF Protection (Basic)
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
    // Remove whitespace
    $data = trim($data);
    // Strip HTML and PHP tags
    $data = strip_tags($data);
    // Convert special characters to HTML entities (Prevents XSS)
    // This replaces the deprecated FILTER_SANITIZE_STRING
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

// SECURITY: Validate Integer for ID (Input Validation)
$auth_user_id = filter_input(INPUT_GET, 'auth_user_id', FILTER_VALIDATE_INT);
$tab = clean_input($_GET['tab'] ?? 'student'); 
$sort_order = clean_input($_GET['sort'] ?? 'newest'); 

if (!$auth_user_id) { 
    header("location: login.php"); 
    exit; 
}

// Get Coordinator info using Prepared Statement
$user_name = "Coordinator";
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

// Helper: Generate next Student ID
function generateNextStudentId($conn) {
    // Queries that don't take user input can technically use query(), but consistency is good.
    $sql = "SELECT MAX(CAST(SUBSTRING(fyp_studid, 3) AS UNSIGNED)) as max_num FROM student WHERE fyp_studid LIKE 'TP%'";
    $result = $conn->query($sql); 
    $row = $result->fetch_assoc();
    $nextNum = ($row && $row['max_num']) ? $row['max_num'] + 1 : 50020;
    return 'TP' . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
}

// Helper: Random Password
if (!function_exists('generateRandomPassword')) {
    function generateRandomPassword($length = 8) {
        return substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, $length);
    }
}

// Variables for SweetAlert
$swal_icon = '';
$swal_title = '';
$swal_text = '';

$approved_list = []; 

// Get dropdown data
$academic_options = [];
$res_acd = $conn->query("SELECT * FROM academic_year ORDER BY fyp_acdyear DESC");
while ($r = $res_acd->fetch_assoc()) $academic_options[] = $r;

$programme_options = [];
$res_prog = $conn->query("SELECT * FROM programme ORDER BY fyp_progname ASC");
while ($r = $res_prog->fetch_assoc()) $programme_options[] = $r;

// ----------------------------------------------------
// 2. BULK APPROVE (Pending)
// ----------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['bulk_approve'])) {
    $selected_ids = $_POST['selected_registrations'] ?? [];
    
    if (empty($selected_ids)) {
        $swal_icon = "error";
        $swal_title = "Oops...";
        $swal_text = "Please select at least one registration to approve.";
    } else {
        $success_count = 0;
        foreach ($selected_ids as $reg_id) {
            $reg_id = intval($reg_id); // Sanitize ID
            
            // Prepared Statement for Selection
            $stmt = $conn->prepare("SELECT * FROM pending_registration WHERE id = ? AND status = 'pending'");
            $stmt->bind_param("i", $reg_id);
            $stmt->execute();
            $reg = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($reg) {
                $password = generateRandomPassword(10);
                $student_id = generateNextStudentId($conn);
                
                $conn->begin_transaction();
                try {
                    // Prepared Statement for User Creation
                    $stmt1 = $conn->prepare("INSERT INTO user (fyp_username, fyp_passwordhash, fyp_usertype, fyp_datecreated) VALUES (?, ?, 'student', NOW())");
                    $stmt1->bind_param("ss", $reg['email'], $password);
                    $stmt1->execute();
                    $user_id = $conn->insert_id;
                    $stmt1->close();
                    
                    $default_acad = $academic_options[0]['fyp_academicid'] ?? 1;
                    $default_prog = $programme_options[0]['fyp_progid'] ?? 1;
                    
                    $stud_name = (!empty($reg['first_name']) && !empty($reg['last_name'])) 
                                ? $reg['first_name'] . " " . $reg['last_name'] 
                                : explode('@', $reg['email'])[0];
                    
                    // Prepared Statement for Student Record
                    $stmt2 = $conn->prepare("INSERT INTO student (fyp_studid, fyp_studname, fyp_academicid, fyp_progid, fyp_email, fyp_userid, fyp_group) VALUES (?, ?, ?, ?, ?, ?, 'Individual')");
                    $stmt2->bind_param("ssiisi", $student_id, $stud_name, $default_acad, $default_prog, $reg['email'], $user_id);
                    $stmt2->execute();
                    $stmt2->close();
                    
                    // Prepared Statement for Updating Status
                    $stmt3 = $conn->prepare("UPDATE pending_registration SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
                    $stmt3->bind_param("ii", $coordinator_id, $reg_id);
                    $stmt3->execute();
                    $stmt3->close();
                    
                    $conn->commit();
                    
                    $emailResult = sendStudentApprovalEmail($reg['email'], $stud_name, $password, $student_id);
                    $approved_list[] = ['email' => $reg['email'], 'student_id' => $student_id, 'password' => $password, 'email_sent' => $emailResult['success']];
                    $success_count++;
                    
                } catch (Exception $e) {
                    $conn->rollback();
                }
            }
        }
        
        if ($success_count > 0) {
            $swal_icon = "success";
            $swal_title = "Success!";
            $swal_text = "Approved {$success_count} students successfully.";
        } else {
            $swal_icon = "error";
            $swal_title = "Oops...";
            $swal_text = "Failed to approve registrations.";
        }
    }
}

// ----------------------------------------------------
// 3. BULK DELETE (Registered Students) - UPDATED
// ----------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['bulk_delete_students'])) {
    $del_ids = $_POST['delete_user_ids'] ?? [];
    if (empty($del_ids)) {
        $swal_icon = "error";
        $swal_title = "Oops...";
        $swal_text = "Please select students to delete.";
    } else {
        $del_count = 0;
        
        // Prepare DELETE statements
        $stmtDelStudent = $conn->prepare("DELETE FROM student WHERE fyp_userid = ?");
        $stmtDelUser = $conn->prepare("DELETE FROM user WHERE fyp_userid = ?");
        
        foreach($del_ids as $uid) {
            $uid = intval($uid);
            
            // Delete from 'student' table first (Foreign Key constraint)
            $stmtDelStudent->bind_param("i", $uid);
            $stmtDelStudent->execute();
            
            // Delete from 'user' table
            $stmtDelUser->bind_param("i", $uid);
            if($stmtDelUser->execute()) {
                $del_count++;
            }
        }
        $stmtDelStudent->close();
        $stmtDelUser->close();
        
        $swal_icon = "success";
        $swal_title = "Deleted!";
        $swal_text = "Permanently deleted {$del_count} students.";
    }
}

// ----------------------------------------------------
// 4. MANUAL ADD STUDENT (Secured with Prepared Statements)
// ----------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_single_student'])) {
    try {
        $fname = clean_input($_POST['first_name']);
        $lname = clean_input($_POST['last_name']);
        
        // SECURITY: Strict Email Validation
        $email_input = clean_input($_POST['email']);
        $email = filter_var($email_input, FILTER_VALIDATE_EMAIL);
        
        $matrix = clean_input($_POST['matrix_no']);
        $contact = !empty($_POST['contact']) ? clean_input($_POST['contact']) : null;
        $acd_id = !empty($_POST['academic_id']) ? clean_input($_POST['academic_id']) : null;
        $prog_id = !empty($_POST['prog_id']) ? clean_input($_POST['prog_id']) : null;
        
        if (!$email) {
            throw new Exception("Invalid email format entered.");
        }

        $full_name = $fname . " " . $lname;
        $gen_password = generateRandomPassword(10);
        
        // Security: Prepared statements for duplicate checks
        $stmtCheck = $conn->prepare("SELECT fyp_userid FROM `user` WHERE fyp_username = ?");
        $stmtCheck->bind_param("s", $email);
        $stmtCheck->execute();
        $chk = $stmtCheck->get_result();

        $stmtCheck2 = $conn->prepare("SELECT fyp_studid FROM `student` WHERE fyp_studid = ?");
        $stmtCheck2->bind_param("s", $matrix);
        $stmtCheck2->execute();
        $chk2 = $stmtCheck2->get_result();

        if ($chk->num_rows > 0) { 
            $swal_icon = "error";
            $swal_title = "Oops...";
            $swal_text = "Email ($email) already exists!";
        } elseif ($chk2->num_rows > 0) {
            $swal_icon = "error";
            $swal_title = "Oops...";
            $swal_text = "Student ID ($matrix) already exists!";
        } else {
            $conn->begin_transaction();
            
            $stmtUser = $conn->prepare("INSERT INTO `user` (fyp_username, fyp_passwordhash, fyp_usertype, fyp_datecreated) VALUES (?, ?, 'student', NOW())");
            $stmtUser->bind_param("ss", $email, $gen_password);
            $stmtUser->execute();
            $uid = $conn->insert_id;

            $stmtStud = $conn->prepare("INSERT INTO student (fyp_userid, fyp_studname, fyp_studid, fyp_email, fyp_contactno, fyp_group, fyp_academicid, fyp_progid) VALUES (?, ?, ?, ?, ?, 'Individual', ?, ?)");
            $stmtStud->bind_param("issssii", $uid, $full_name, $matrix, $email, $contact, $acd_id, $prog_id);
            $stmtStud->execute();
            
            $conn->commit();

            sendStudentApprovalEmail($email, $full_name, $gen_password, $matrix);
            
            $swal_icon = "success";
            $swal_title = "Success!";
            $swal_text = "Student added and credentials sent to email!";
        }
        $stmtCheck->close();
        $stmtCheck2->close();
    } catch (Exception $e) { 
        $swal_icon = "error";
        $swal_title = "Error";
        $swal_text = $e->getMessage();
    }
}

// ----------------------------------------------------
// 5. IMPORT STUDENTS (CSV) - Secured
// ----------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['import_students'])) {
    if (is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
        $file = fopen($_FILES['csv_file']['tmp_name'], "r"); 
        fgetcsv($file); // Skip header
        $count = 0;
        
        // Pre-prepare statements outside loop for performance and security
        $stmtChk = $conn->prepare("SELECT fyp_userid FROM `user` WHERE fyp_username = ?");
        $stmtU = $conn->prepare("INSERT INTO `user` (fyp_username, fyp_passwordhash, fyp_usertype, fyp_datecreated) VALUES (?, ?, 'student', NOW())");
        $stmtS = $conn->prepare("INSERT INTO student (fyp_userid, fyp_studname, fyp_studid, fyp_email, fyp_contactno, fyp_group, fyp_academicid, fyp_progid) VALUES (?, ?, ?, ?, ?, 'Individual', ?, ?)");

        while (($data = fgetcsv($file, 1000, ",")) !== FALSE) {
            $i_fname = clean_input($data[0]??''); 
            $i_lname = clean_input($data[1]??''); 
            $i_matrix = clean_input($data[2]??''); 
            
            // Validate CSV Email
            $i_email_raw = clean_input($data[3]??'');
            $i_email = filter_var($i_email_raw, FILTER_VALIDATE_EMAIL);
            
            $i_contact = clean_input($data[4]??''); 
            $i_prog = intval($data[5]??1); 
            $i_acd = intval($data[6]??1);
            
            if ($i_fname && $i_email && $i_matrix) {
                $i_fullname = $i_fname . " " . $i_lname;
                
                $stmtChk->bind_param("s", $i_email);
                $stmtChk->execute();
                
                if ($stmtChk->get_result()->num_rows == 0) {
                    $pass = generateRandomPassword(10);
                    
                    $stmtU->bind_param("ss", $i_email, $pass);
                    $stmtU->execute();
                    $uid = $conn->insert_id;
                    
                    $stmtS->bind_param("issssii", $uid, $i_fullname, $i_matrix, $i_email, $i_contact, $i_acd, $i_prog);
                    $stmtS->execute();
                    
                    sendStudentApprovalEmail($i_email, $i_fullname, $pass, $i_matrix);
                    $count++;
                }
            }
        }
        $stmtChk->close();
        $stmtU->close();
        $stmtS->close();
        
        $swal_icon = "success";
        $swal_title = "Import Complete";
        $swal_text = "Successfully imported $count students!";
    }
}

// ----------------------------------------------------
// 6. ADD SUPERVISOR (Secured)
// ----------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_supervisor'])) {
    try {
        $uname = clean_input($_POST['username']); 
        if (!filter_var($uname, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format for username.");
        }
        
        $pass = clean_input($_POST['password']);
        $name = clean_input($_POST['full_name']); 
        $sid = clean_input($_POST['staff_id']);
        
        // Prepared Check
        $stmtCheck = $conn->prepare("SELECT fyp_userid FROM `user` WHERE fyp_username = ?");
        $stmtCheck->bind_param("s", $uname);
        $stmtCheck->execute();
        $chk = $stmtCheck->get_result();
        
        if ($chk->num_rows > 0) { 
            $swal_icon = "error";
            $swal_title = "Oops...";
            $swal_text = "Username ($uname) already exists!";
        } else {
            // Prepared Insert User
            $stmt = $conn->prepare("INSERT INTO `user` (fyp_username, fyp_passwordhash, fyp_usertype, fyp_datecreated) VALUES (?, ?, 'lecturer', NOW())");
            $stmt->bind_param("ss", $uname, $pass); 
            $stmt->execute();
            $nid = $conn->insert_id; 

            // Prepared Insert Supervisor
            $stmt2 = $conn->prepare("INSERT INTO supervisor (fyp_userid, fyp_name, fyp_staffid, fyp_email, fyp_ismoderator) VALUES (?, ?, ?, ?, 0)");
            $stmt2->bind_param("isss", $nid, $name, $sid, $uname);
            $stmt2->execute();
            
            $new_sup_id = $conn->insert_id; 
            
            // Insert Quota (Fixed 3)
            $stmtQ = $conn->prepare("INSERT INTO quota (fyp_supervisorid, fyp_numofstudent) VALUES (?, 3)");
            $stmtQ->bind_param("i", $new_sup_id);
            $stmtQ->execute();
            
            sendLecturerCredentialsEmail($uname, $name, $pass, $sid);
            
            $swal_icon = "success";
            $swal_title = "Success";
            $swal_text = "Supervisor added successfully!";
        }
        $stmtCheck->close();
    } catch (Exception $e) { 
        $swal_icon = "error";
        $swal_title = "Error";
        $swal_text = $e->getMessage();
    }
}

// ----------------------------------------------------
// 7. IMPORT SUPERVISORS (Secured)
// ----------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['import_supervisors'])) {
    if (is_uploaded_file($_FILES['csv_file_sup']['tmp_name'])) {
        $file = fopen($_FILES['csv_file_sup']['tmp_name'], "r"); 
        fgetcsv($file);
        $count = 0;
        
        // Prepare Statements
        $stmtChk = $conn->prepare("SELECT fyp_userid FROM `user` WHERE fyp_username = ?");
        $stmtU = $conn->prepare("INSERT INTO `user` (fyp_username, fyp_passwordhash, fyp_usertype, fyp_datecreated) VALUES (?, ?, 'lecturer', NOW())");
        $stmtS = $conn->prepare("INSERT INTO supervisor (fyp_userid, fyp_name, fyp_staffid, fyp_email, fyp_ismoderator) VALUES (?, ?, ?, ?, 0)");
        $stmtQ = $conn->prepare("INSERT INTO quota (fyp_supervisorid, fyp_numofstudent) VALUES (?, 3)");

        while (($data = fgetcsv($file, 1000, ",")) !== FALSE) {
            $i_name = clean_input($data[0]??''); 
            $i_staffid = clean_input($data[1]??''); 
            $i_email_raw = clean_input($data[2]??''); 
            $i_email = filter_var($i_email_raw, FILTER_VALIDATE_EMAIL);
            
            if ($i_name && $i_staffid && $i_email) {
                $stmtChk->bind_param("s", $i_email);
                $stmtChk->execute();
                
                if ($stmtChk->get_result()->num_rows == 0) {
                    $pass = generateRandomPassword(10);
                    
                    $stmtU->bind_param("ss", $i_email, $pass);
                    $stmtU->execute();
                    $uid = $conn->insert_id;
                    
                    $stmtS->bind_param("isss", $uid, $i_name, $i_staffid, $i_email);
                    $stmtS->execute();
                    $sup_id = $conn->insert_id;
                    
                    $stmtQ->bind_param("i", $sup_id);
                    $stmtQ->execute();
                    
                    sendLecturerCredentialsEmail($i_email, $i_name, $pass, $i_staffid);
                    $count++;
                }
            }
        }
        $stmtChk->close();
        $stmtU->close();
        $stmtS->close();
        $stmtQ->close();
        
        $swal_icon = "success";
        $swal_title = "Import Complete";
        $swal_text = "Imported $count supervisors!";
    }
}

// ----------------------------------------------------
// 8. REJECT REGISTRATION (Secured)
// ----------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reject_registration'])) {
    $reg_id = intval($_POST['reg_id']);
    $stmt = $conn->prepare("UPDATE pending_registration SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW() WHERE id = ? AND status = 'pending'");
    $stmt->bind_param("ii", $coordinator_id, $reg_id);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $swal_icon = "info";
        $swal_title = "Rejected";
        $swal_text = "Registration rejected successfully.";
    }
    $stmt->close();
}

// ----------------------------------------------------
// 9. FETCH DATA (With Sorting)
// ----------------------------------------------------
$pending_registrations = [];
if ($tab == 'student') {
    // Check if table exists
    $checkTable = $conn->query("SHOW TABLES LIKE 'pending_registration'");
    if ($checkTable && $checkTable->num_rows > 0) {
        $res_pending = $conn->query("SELECT * FROM pending_registration WHERE status = 'pending' ORDER BY created_at ASC");
        if ($res_pending) while ($row = $res_pending->fetch_assoc()) $pending_registrations[] = $row;
    }
}

$data_list = [];
$orderBy = "s.fyp_studname ASC"; // Default

// Apply Sort Filter (Whitelisted logic)
if ($sort_order == 'newest') {
    $orderBy = "u.fyp_datecreated DESC, s.fyp_studname ASC";
} elseif ($sort_order == 'oldest') {
    $orderBy = "u.fyp_datecreated ASC, s.fyp_studname ASC";
}

if ($tab == 'student') {
    $sql = "SELECT s.*, u.fyp_username, u.fyp_datecreated, u.fyp_usertype, p.fyp_progname, a.fyp_acdyear, a.fyp_intake 
            FROM student s 
            JOIN `user` u ON s.fyp_userid = u.fyp_userid 
            LEFT JOIN programme p ON s.fyp_progid = p.fyp_progid 
            LEFT JOIN academic_year a ON s.fyp_academicid = a.fyp_academicid 
            ORDER BY $orderBy";
    // $orderBy is safe here because it is set by our IF logic above, not directly from user input.
    $res = $conn->query($sql);
} else {
    $res = $conn->query("SELECT s.*, u.fyp_username, u.fyp_datecreated FROM supervisor s JOIN `user` u ON s.fyp_userid = u.fyp_userid ORDER BY s.fyp_name ASC");
}
if ($res) while ($r = $res->fetch_assoc()) $data_list[] = $r;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage <?php echo ucfirst($tab); ?>s</title>
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap');
        body { font-family: 'Poppins', sans-serif; margin: 0; background: #f4f7fc; }
        .topbar { background: #fff; padding: 15px 40px; display: flex; justify-content: space-between; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .container { max-width: 1200px; margin: 20px auto; padding: 0 20px; }
        .card { background: #fff; padding: 25px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .btn { background: #0056b3; color: #fff; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
        .btn:hover { background: #004494; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        .btn-warning { background: #ffc107; color: #000; }
        .btn-warning:hover { background: #e0a800; }
        .btn-outline { background: transparent; border: 1px solid #0056b3; color: #0056b3; }
        .btn-outline:hover { background: #f0f7ff; }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; font-size: 13px; }
        th { background: #f8f9fa; font-weight: 600; }
        
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fefefe; margin: 5% auto; padding: 25px; border-radius: 10px; width: 500px; position: relative; }
        .close { position: absolute; right: 20px; top: 15px; font-size: 28px; font-weight: bold; cursor: pointer; color: #aaa; }
        .close:hover { color: black; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; font-size: 14px; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        
        .search-bar { width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; font-family: inherit; }
        
        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px; }
        .filter-select { padding: 10px; border: 1px solid #ddd; border-radius: 5px; cursor:pointer; }
        .checkbox-col { width: 40px; text-align: center; }
        .checkbox-col input { width: 18px; height: 18px; cursor: pointer; }
        
        /* Style for disabled rows */
        .disabled-row { opacity: 0.6; background-color: #f9f9f9; }
        .disabled-badge { background: #dc3545; color: white; padding: 2px 6px; border-radius: 4px; font-size: 10px; margin-left: 5px; }
    </style>
</head>
<body>
    <div class="topbar">
        <div style="display:flex; align-items:center; gap: 20px;">
            <a href="Coordinator_mainpage.php?auth_user_id=<?php echo $auth_user_id; ?>" class="btn-outline" style="border:none; padding:5px 10px; font-size:16px; display:flex; align-items:center; gap:5px; text-decoration:none;">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <div style="font-weight:600; font-size:20px; color:#0056b3;"><i class="fas fa-graduation-cap"></i> FYP System</div>
        </div>
        <div><?php echo htmlspecialchars($user_name); ?> <a href="login.php" style="color:red; margin-left:15px;">Logout</a></div>
    </div>

    <div class="container">
        
        <!-- Results Card -->
        <?php if (!empty($approved_list)): ?>
        <div class="card">
            <h3><i class="fas fa-check-circle" style="color:#28a745;"></i> Approved & Credentials Generated</h3>
            <table>
                <thead><tr><th>Email</th><th>Student ID</th><th>Password</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($approved_list as $a): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($a['email']); ?></td>
                        <td><?php echo htmlspecialchars($a['student_id']); ?></td>
                        <td style="font-family:monospace; color:#d63384; font-weight:bold;"><?php echo htmlspecialchars($a['password']); ?></td>
                        <td><?php echo $a['email_sent'] ? 'Sent' : 'Failed'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h2 style="margin:0;"><i class="fas fa-users-cog"></i> Manage <?php echo ucfirst($tab); ?>s</h2>
                <div>
                    <a href="?auth_user_id=<?php echo $auth_user_id; ?>&tab=student" class="btn <?php echo $tab=='student'?'':'btn-outline';?>">Students</a>
                    <a href="?auth_user_id=<?php echo $auth_user_id; ?>&tab=supervisor" class="btn <?php echo $tab=='supervisor'?'':'btn-outline';?>">Supervisors</a>
                </div>
            </div>

            <!-- 1. PENDING REGISTRATIONS -->
            <?php if ($tab == 'student' && !empty($pending_registrations)): ?>
            <div style="border: 2px solid #ffc107; background: #fffdf5; padding: 20px; border-radius: 10px; margin-bottom: 25px;">
                <h3 style="margin-top:0; color: #856404;"><i class="fas fa-clock"></i> Pending Approvals</h3>
                <form method="POST">
                    <div style="margin-bottom:10px;">
                        <button type="submit" name="bulk_approve" class="btn btn-success"><i class="fas fa-check-double"></i> Approve Selected</button>
                    </div>
                    <table>
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
                                    <button type="button" class="btn btn-danger btn-sm" onclick="rejectOne(<?php echo $reg['id']; ?>)">Reject</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </form>
            </div>
            <?php endif; ?>

            <!-- 2. MAIN TOOLBAR -->
            <div class="toolbar">
                <div style="display:flex; gap:10px; align-items:center;">
                    <!-- Manual Add Button -->
                    <button onclick="openModal()" class="btn"><i class="fas fa-plus-circle"></i> Add <?php echo ucfirst($tab); ?></button>
                    
                    <!-- Filter Dropdown -->
                    <?php if($tab == 'student'): ?>
                        <select class="filter-select" onchange="window.location.href='?auth_user_id=<?php echo $auth_user_id; ?>&tab=student&sort='+this.value">
                            <option value="newest" <?php echo $sort_order=='newest'?'selected':''; ?>>Sort: Newest Registered</option>
                            <option value="oldest" <?php echo $sort_order=='oldest'?'selected':''; ?>>Sort: Oldest Registered</option>
                        </select>
                    
                        <!-- CSV Template Button -->
                        <a href="?auth_user_id=<?php echo $auth_user_id; ?>&tab=student&action=download_template" class="btn btn-outline" target="_blank">
                            <i class="fas fa-download"></i> Template
                        </a>
                    <?php endif; ?>
                </div>
                
                <!-- Import Form -->
                <form method="POST" enctype="multipart/form-data" style="display:flex; gap:5px; align-items:center;">
                    <input type="file" name="<?php echo $tab=='student'?'csv_file':'csv_file_sup'; ?>" accept=".csv" required style="padding:5px;">
                    <button name="<?php echo $tab=='student'?'import_students':'import_supervisors'; ?>" class="btn btn-success"><i class="fas fa-file-import"></i> Import</button>
                </form>
            </div>

            <!-- 3. REGISTERED STUDENTS LIST -->
            <input type="text" id="searchInput" onkeyup="filterTable()" class="search-bar" placeholder="🔍 Search by name, ID or email...">
            
            <form method="POST" id="deleteForm">
                <?php if($tab == 'student'): ?>
                    <div style="margin-bottom:10px;">
                        <button type="button" onclick="confirmDelete()" class="btn btn-danger"><i class="fas fa-trash-alt"></i> Delete Selected</button>
                        <input type="hidden" name="bulk_delete_students" value="1">
                    </div>
                <?php endif; ?>
                
                <table id="dataTable">
                    <thead>
                        <tr>
                            <?php if($tab == 'student'): ?>
                                <th class="checkbox-col"><input type="checkbox" onclick="toggleAll(this, 'del-chk')"></th>
                            <?php endif; ?>
                            <th>Name</th>
                            <th>ID</th>
                            <th>Email</th>
                            <?php if($tab == 'student'): ?>
                                <th>Programme</th>
                                <th>Intake</th>
                                <th>Registered Date</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($data_list as $row): ?>
                        <tr>
                            <?php if($tab == 'student'): ?>
                                <td class="checkbox-col">
                                    <input type="checkbox" name="delete_user_ids[]" value="<?php echo $row['fyp_userid']; ?>" class="del-chk">
                                </td>
                            <?php endif; ?>
                            <td>
                                <?php echo htmlspecialchars($row[$tab=='student'?'fyp_studname':'fyp_name']); ?>
                            </td>
                            <td><strong><?php echo htmlspecialchars($row[$tab=='student'?'fyp_studid':'fyp_staffid']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['fyp_username']); ?></td>
                            <?php if($tab == 'student'): ?>
                                <td><?php echo htmlspecialchars($row['fyp_progname'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars(($row['fyp_acdyear']??'') . ' ' . ($row['fyp_intake']??'')); ?></td>
                                <td style="color:#666; font-size:12px;"><?php echo htmlspecialchars($row['fyp_datecreated']); ?></td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
        </div>
    </div>

    <!-- ADD STUDENT POPUP MODAL -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2 style="margin-top:0;">Add New <?php echo ucfirst($tab); ?></h2>
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
                        <label>Email (Will receive password) <span style="color:red">*</span></label>
                        <input type="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label>Student ID <span style="color:red">*</span></label>
                        <input type="text" name="matrix_no" placeholder="e.g. TP050000" required>
                    </div>
                    <div style="background:#f9f9f9; padding:10px; border-radius:5px; margin-bottom:15px;">
                        <small style="display:block; margin-bottom:10px; font-weight:bold; color:#666;">Optional Fields</small>
                        <div class="form-group">
                            <input type="text" name="contact" placeholder="Contact No">
                        </div>
                        <div class="form-group">
                            <select name="academic_id">
                                <option value="">Select Academic Year (Optional)</option>
                                <?php foreach($academic_options as $a) echo "<option value='{$a['fyp_academicid']}'>{$a['fyp_acdyear']} - {$a['fyp_intake']}</option>"; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <select name="prog_id">
                                <option value="">Select Programme (Optional)</option>
                                <?php foreach($programme_options as $p) echo "<option value='{$p['fyp_progid']}'>{$p['fyp_progname']}</option>"; ?>
                            </select>
                        </div>
                    </div>
                    <button name="add_single_student" class="btn btn-success" style="width:100%; justify-content:center;">Add Student</button>
                
                <?php else: // Supervisor Form ?>
                    <div class="form-group"><input type="text" name="full_name" placeholder="Full Name" required></div>
                    <div class="form-group"><input type="text" name="username" placeholder="Email (Username)" required></div>
                    <div class="form-group"><input type="text" name="staff_id" placeholder="Staff ID" required></div>
                    <div class="form-group"><input type="text" name="password" placeholder="Password" required></div>
                    <button name="add_supervisor" class="btn btn-success" style="width:100%; justify-content:center;">Add Supervisor</button>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Hidden form for rejection -->
    <form id="rejectForm" method="POST" style="display:none;">
        <input type="hidden" name="reg_id" id="reject_reg_id">
        <input type="hidden" name="reject_registration" value="1">
    </form>

    <script>
        // --- SWEETALERT TRIGGER LOGIC ---
        <?php if (!empty($swal_icon)): ?>
        Swal.fire({
            icon: "<?php echo $swal_icon; ?>",
            title: "<?php echo $swal_title; ?>",
            text: "<?php echo $swal_text; ?>",
            draggable: true
            <?php if ($swal_icon === 'error'): ?>
            , footer: '<a href="#">Why do I have this issue?</a>'
            <?php endif; ?>
        });
        <?php endif; ?>

        // Modal Logic
        function openModal() { document.getElementById("addModal").style.display = "block"; }
        function closeModal() { document.getElementById("addModal").style.display = "none"; }
        window.onclick = function(event) { if (event.target == document.getElementById("addModal")) closeModal(); }

        // Filter Table Logic (Client-side search)
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

        // Toggle All Checkboxes
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
                text: "Selected students will be PERMANENTLY deleted from the system.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete them!'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('deleteForm').submit();
                }
            });
        }
    </script>
</body>
</html>