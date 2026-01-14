<?php
include("connect.php");

if (file_exists('Email_config_SMTP.php')) {
    include("Email_config_SMTP.php");
} else {
    include("email_config.php");
}

function clean_input($data) {
    if (is_array($data)) {
        return array_map('clean_input', $data);
    }
    $data = trim($data);
    $data = strip_tags($data);
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

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

$auth_user_id = filter_input(INPUT_GET, 'auth_user_id', FILTER_VALIDATE_INT);
$tab = clean_input($_GET['tab'] ?? 'student'); 
$sort_order = clean_input($_GET['sort'] ?? 'newest'); 
$search_query = clean_input($_GET['search'] ?? '');
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$limit = 15; 
$offset = ($page - 1) * $limit;

if (!$auth_user_id) { 
    header("location: login.php"); 
    exit; 
}

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

$swal_icon = '';
$swal_title = '';
$swal_text = '';
$approved_list = []; 

$academic_options = [];
$res_acd = $conn->query("SELECT * FROM academic_year ORDER BY fyp_acdyear DESC");
while ($r = $res_acd->fetch_assoc()) $academic_options[] = $r;

$programme_options = [];
$res_prog = $conn->query("SELECT * FROM programme ORDER BY fyp_progname ASC");
while ($r = $res_prog->fetch_assoc()) $programme_options[] = $r;

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

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reject_reset'])) {
    $req_id = intval($_POST['request_id']);
    $stmt = $conn->prepare("UPDATE password_reset_requests SET status = 'Rejected', reviewed_by = ?, reviewed_at = NOW() WHERE request_id = ?");
    $stmt->bind_param("ii", $coordinator_id, $req_id);
    if($stmt->execute()) { $swal_icon = "info"; $swal_title = "Rejected"; $swal_text = "Password reset request rejected."; }
    $stmt->close();
}

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
                    $stmt2->bind_param("ssisi", $student_id, $stud_name, $reg['email'], $user_id); $stmt2->execute(); $stmt2->close();
                    
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
        else { $swal_icon = "error"; $swal_title = "Oops..."; $swal_text = "Failed to approve."; }
    }
}

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

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['import_students']) && is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
    $file = fopen($_FILES['csv_file']['tmp_name'], "r"); fgetcsv($file); $count = 0;
    while (($data = fgetcsv($file, 1000, ",")) !== FALSE) {
        if ($data[0] && $data[3]) {
            $count++; 
        }
    }
    $swal_icon = "success"; $swal_title = "Imported"; $swal_text = "Processed CSV import.";
}

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

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reject_registration'])) {
    $reg_id = intval($_POST['reg_id']);
    $conn->query("UPDATE pending_registration SET status='rejected' WHERE id=$reg_id");
    $swal_icon = "info"; $swal_title = "Rejected"; $swal_text = "Registration rejected.";
}

$pending_registrations = [];
if ($tab == 'student') {
    $checkTable = $conn->query("SHOW TABLES LIKE 'pending_registration'");
    if ($checkTable && $checkTable->num_rows > 0) {
        $res_pending = $conn->query("SELECT * FROM pending_registration WHERE status = 'pending' ORDER BY created_at ASC");
        if ($res_pending) while ($row = $res_pending->fetch_assoc()) $pending_registrations[] = $row;
    }
}

$pending_resets = [];
$checkResetTable = $conn->query("SHOW TABLES LIKE 'password_reset_requests'");
if ($checkResetTable && $checkResetTable->num_rows > 0) {
    $res_pwd = $conn->query("SELECT * FROM password_reset_requests WHERE status = 'Pending' ORDER BY request_date DESC");
    if($res_pwd) while($r = $res_pwd->fetch_assoc()) $pending_resets[] = $r;
}

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
            --primary: #0056b3;
            --primary-light: #e7f1ff;
            --text-dark: #333;
            --text-grey: #666;
            --bg-body: #f4f6f9;
            --card-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        body { font-family: 'Poppins', sans-serif; margin: 0; background: var(--bg-body); color: var(--text-dark); }

        .topbar {
            background: linear-gradient(90deg, #0056b3 0%, #004494 60%, #ffffff 100%);
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            color: white;
        }
        
        .topbar a.btn-back {
            color: white;
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: opacity 0.2s;
        }
        .topbar a.btn-back:hover { opacity: 0.8; }
        
        .logo-section { display: flex; align-items: center; gap: 12px; }
        .logo-img { height: 40px; width: auto; background: white; padding: 2px; border-radius: 6px; }
        .system-title { font-size: 20px; font-weight: 600; letter-spacing: 0.5px; }
        
        .logout-link { color: #dc3545 !important; font-weight: 600; text-decoration: none; background: white; padding: 6px 15px; border-radius: 20px; font-size: 14px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .logout-link:hover { background: #f8d7da; }

        .container { max-width: 1250px; margin: 30px auto; padding: 0 20px; }

        .card { background: white; padding: 25px; border-radius: 12px; box-shadow: var(--card-shadow); margin-bottom: 25px; border: 1px solid #eef2f7; }
        
        h2 { font-weight: 600; color: #2c3e50; margin: 0; display: flex; align-items: center; gap: 10px; }
        h3 { font-weight: 600; font-size: 18px; margin-bottom: 15px; color: #444; }

        .tabs { display: flex; gap: 10px; }
        .tab-btn {
            text-decoration: none; padding: 10px 20px; border-radius: 8px; font-weight: 500; font-size: 14px; transition: all 0.3s;
            border: 1px solid transparent; display: inline-flex; align-items: center; gap: 8px;
        }
        .tab-btn.active { background: var(--primary); color: white; box-shadow: 0 4px 10px rgba(0,86,179,0.3); }
        .tab-btn.inactive { background: white; color: var(--text-grey); border-color: #ddd; }
        .tab-btn.inactive:hover { background: var(--primary-light); color: var(--primary); border-color: var(--primary); }

        .action-bar {
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;
            background: #fff; padding: 15px; border-radius: 12px; box-shadow: var(--card-shadow); margin-bottom: 20px;
        }
        .search-form { display: flex; flex: 1; min-width: 300px; gap: 10px; }
        .search-wrapper { position: relative; flex: 1; }
        .search-wrapper i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #aaa; }
        .search-input { width: 100%; padding: 12px 15px 12px 40px; border: 1px solid #e0e0e0; border-radius: 8px; outline: none; font-family: 'Poppins', sans-serif; transition: border 0.3s; box-sizing: border-box; }
        .search-btn { padding: 0 25px; background: var(--primary); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; font-size: 14px; transition: 0.3s; height: 45px; }
        .search-btn:hover { background: #004494; transform: translateY(-1px); box-shadow: 0 4px 10px rgba(0,86,179,0.2); }
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
        .badge-green { background: #d4edda; color: #155724; }

        .btn-action { padding: 8px 15px; border-radius: 6px; border: none; cursor: pointer; font-size: 13px; font-weight: 500; transition: 0.2s; display: inline-flex; align-items: center; gap: 5px; }
        .btn-blue { background: var(--primary); color: white; }
        .btn-blue:hover { background: #004494; transform: translateY(-1px); }
        .btn-red { background: #ff4d4f; color: white; }
        .btn-red:hover { background: #d9363e; }
        .btn-green { background: #28a745; color: white; }
        .btn-green:hover { background: #218838; }

        .section-header { margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; }
        .alert-box { padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-size: 14px; }
        .alert-pending { background: #fff8e1; border-left: 4px solid #ffc107; color: #856404; }
        .alert-reset { background: #e3f2fd; border-left: 4px solid #2196f3; color: #0d47a1; }

        .status-badge { padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .status-Active { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .status-Archived { background: #e2e3e5; color: #383d41; border: 1px solid #d6d8db; }
        
        .btn-icon { padding: 5px 10px; border-radius: 4px; border: none; cursor: pointer; transition: 0.2s; }
        .btn-archive { background: #ffc107; color: #333; }
        .btn-activate { background: #28a745; color: #fff; }
        .btn-trash { background: #ff4d4f; color: white; margin-left: 5px; }

        .pagination { display: flex; justify-content: center; gap: 10px; margin-top: 20px; }
        .page-link { padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; color: #555; font-size: 13px; }
        .page-link.active { background: var(--primary); color: white; border-color: var(--primary); }
        .page-link:hover:not(.active) { background: #f0f0f0; }

        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
        .modal-content {
            background: white; margin: 5% auto; padding: 30px; border-radius: 16px; width: 500px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2); animation: fadeIn 0.3s ease; position: relative;
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        .close-modal { position: absolute; right: 25px; top: 20px; font-size: 24px; cursor: pointer; color: #999; transition: 0.2s; }
        .close-modal:hover { color: #333; }
        
        .modal h2 { margin-top: 0; margin-bottom: 25px; font-size: 22px; color: #333; border-bottom: 1px solid #eee; padding-bottom: 15px; }
        .input-row { display: flex; gap: 15px; margin-bottom: 15px; }
        .input-group { margin-bottom: 15px; width: 100%; }
        .input-group label { display: block; margin-bottom: 8px; font-size: 13px; font-weight: 500; color: #555; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; font-family: 'Poppins'; transition: 0.3s; }
        .form-control:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(0,86,179,0.1); }
        .btn-block { width: 100%; padding: 12px; font-size: 15px; justify-content: center; margin-top: 10px; }
    </style>
</head>
<body>

    <div class="topbar">
        <div class="logo-section">
            <a href="Coordinator_mainpage.php?auth_user_id=<?php echo $auth_user_id; ?>" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <span style="opacity: 0.5;">|</span>
            <img src="image/ladybug.png?v=<?php echo time(); ?>" alt="Logo" class="logo-img">
            <span class="system-title">FYP Management System</span>
        </div>
        <div style="display: flex; align-items: center; gap: 15px;">
            <span style="font-weight: 500; color: #004494; background: rgba(255,255,255,0.9); padding: 5px 12px; border-radius: 15px; font-size: 13px;">
                <i class="fas fa-user-shield"></i> <?php echo $user_name; ?>
            </span>
            <a href="login.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <div class="container">

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
            <h2 style="font-size: 24px;"><i class="fas fa-users-cog"></i> User Management</h2>
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
                                    <div style="width: 35px; height: 35px; background: #e3f2fd; color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600;">
                                        <?php echo strtoupper(substr($row[$tab=='student'?'fyp_studname':'fyp_name'] ?? 'U', 0, 1)); ?>
                                    </div>
                                    <?php echo htmlspecialchars($row[$tab=='student'?'fyp_studname':'fyp_name'] ?? $row['fyp_username']); ?>
                                </div>
                            </td>
                            <td><span style="font-family: monospace; background: #f8f9fa; padding: 4px 8px; border-radius: 4px; font-weight: 600;"><?php echo htmlspecialchars($row[$tab=='student'?'fyp_studid':'fyp_staffid'] ?? '-'); ?></span></td>
                            <td><?php echo htmlspecialchars($row['fyp_username']); ?></td>
                            
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

    <script>
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
        
        window.onclick = function(event) {
            if (event.target == document.getElementById("addModal")) {
                document.getElementById("addModal").style.display = "none";
            }
        }
    </script>
</body>
</html>