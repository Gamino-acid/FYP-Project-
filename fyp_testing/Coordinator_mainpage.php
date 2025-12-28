<?php
/**
 * COORDINATOR MAIN PAGE - Refactored Version
 * Uses include files for better organization
 * 
 * File Structure:
 * - includes/coordinator_handlers.php - Basic student/pairing handlers
 * - includes/rubrics_handlers.php - Rubrics assessment handlers
 * - includes/project_handlers.php - Project CRUD handlers
 * - includes/announcement_handlers.php - Announcement handlers
 * - includes/marks_handlers.php - Assessment marks handlers
 * - includes/registration_handlers.php - Registration handlers
 */

session_start();
include("../db_connect.php");

// ===================== AUTHENTICATION =====================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'coordinator') {
    header("Location: ../Login.php");
    exit;
}

$auth_user_id = $_SESSION['user_id'];
$user_name = $_SESSION['username'];
$current_page = $_GET['page'] ?? 'dashboard';
$action = $_GET['action'] ?? '';

$img_src = "https://ui-avatars.com/api/?name=" . urlencode($user_name) . "&background=7C3AED&color=fff";

// ===================== GET COUNTS =====================
$total_students = 0;
$total_supervisors = 0;
$pending_requests = 0;
$pending_registrations = 0;
$total_projects = 0;
$total_pairings = 0;

$res = $conn->query("SELECT COUNT(*) as cnt FROM student");
if ($res) $total_students = $res->fetch_assoc()['cnt'];

$res = $conn->query("SELECT COUNT(*) as cnt FROM supervisor");
if ($res) $total_supervisors = $res->fetch_assoc()['cnt'];

$res = $conn->query("SELECT COUNT(*) as cnt FROM group_request WHERE request_status = 'Pending'");
if ($res) $pending_requests = $res->fetch_assoc()['cnt'];

$res = $conn->query("SELECT COUNT(*) as cnt FROM pending_registration WHERE status = 'pending'");
if ($res) $pending_registrations = $res->fetch_assoc()['cnt'];

$res = $conn->query("SELECT COUNT(*) as cnt FROM project");
if ($res) $total_projects = $res->fetch_assoc()['cnt'];

$res = $conn->query("SELECT COUNT(*) as cnt FROM pairing");
if ($res) $total_pairings = $res->fetch_assoc()['cnt'];

// ===================== GET DROPDOWN DATA =====================
$academic_years = [];
$res = $conn->query("SELECT * FROM academic_year ORDER BY fyp_academicid DESC");
if ($res) { while ($row = $res->fetch_assoc()) { $academic_years[] = $row; } }

$programmes = [];
$res = $conn->query("SELECT * FROM programme ORDER BY fyp_progname");
if ($res) { while ($row = $res->fetch_assoc()) { $programmes[] = $row; } }

$supervisors = [];
$res = $conn->query("SELECT * FROM supervisor ORDER BY fyp_name");
if ($res) { while ($row = $res->fetch_assoc()) { $supervisors[] = $row; } }

$message = '';
$message_type = '';

// ===================== INCLUDE HANDLER FILES =====================
// Check if include files exist, otherwise use inline handlers
$includes_path = __DIR__ . '/includes/';

if (file_exists($includes_path . 'coordinator_handlers.php')) {
    include($includes_path . 'coordinator_handlers.php');
} else {
    // Inline basic handlers
    include_basic_handlers();
}

if (file_exists($includes_path . 'rubrics_handlers.php')) {
    include($includes_path . 'rubrics_handlers.php');
}

if (file_exists($includes_path . 'project_handlers.php')) {
    include($includes_path . 'project_handlers.php');
}

if (file_exists($includes_path . 'announcement_handlers.php')) {
    include($includes_path . 'announcement_handlers.php');
}

if (file_exists($includes_path . 'marks_handlers.php')) {
    include($includes_path . 'marks_handlers.php');
}

if (file_exists($includes_path . 'registration_handlers.php')) {
    include($includes_path . 'registration_handlers.php');
}

// ===================== FALLBACK INLINE HANDLERS =====================
function include_basic_handlers() {
    global $conn, $message, $message_type, $total_students;
    
    // Update Group Request Status
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_request_status'])) {
        $request_id = $_POST['request_id'];
        $new_status = $_POST['new_status'];
        $stmt = $conn->prepare("UPDATE group_request SET request_status = ? WHERE request_id = ?");
        $stmt->bind_param("si", $new_status, $request_id);
        if ($stmt->execute()) {
            $message = "Group request status updated to: $new_status";
            $message_type = 'success';
        }
        $stmt->close();
    }
    
    // Edit Student
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_student'])) {
        $studid = $_POST['studid'];
        $studname = $_POST['studname'];
        $email = $_POST['email'];
        $contact = $_POST['contactno'];
        $progid = $_POST['progid'];
        $group_type = $_POST['group_type'];
        
        $stmt = $conn->prepare("UPDATE student SET fyp_studname = ?, fyp_email = ?, fyp_contactno = ?, fyp_progid = ?, fyp_group = ? WHERE fyp_studid = ?");
        $stmt->bind_param("sssiss", $studname, $email, $contact, $progid, $group_type, $studid);
        if ($stmt->execute()) {
            $message = "Student updated successfully!";
            $message_type = 'success';
        }
        $stmt->close();
    }
    
    // Delete Student
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_student'])) {
        $studid = $_POST['studid'];
        $stmt = $conn->prepare("DELETE FROM student WHERE fyp_studid = ?");
        $stmt->bind_param("s", $studid);
        if ($stmt->execute()) {
            $message = "Student deleted successfully!";
            $message_type = 'success';
            $total_students--;
        }
        $stmt->close();
    }
    
    // Create Pairing
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_pairing'])) {
        $supervisor_id = $_POST['supervisor_id'];
        $project_id = $_POST['project_id'];
        $moderator_id = $_POST['moderator_id'];
        $academic_id = $_POST['academic_id'];
        $pairing_type = $_POST['pairing_type'];
        
        $stmt = $conn->prepare("INSERT INTO pairing (fyp_supervisorid, fyp_projectid, fyp_moderatorid, fyp_academicid, fyp_type, fyp_datecreated) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("siiss", $supervisor_id, $project_id, $moderator_id, $academic_id, $pairing_type);
        if ($stmt->execute()) {
            $message = "Pairing created successfully!";
            $message_type = 'success';
        }
        $stmt->close();
    }
}

// ===================== EXCEL IMPORT HANDLER =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_excel'])) {
    if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['excel_file']['tmp_name'];
        $filename = $_FILES['excel_file']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (!in_array($ext, ['csv', 'xls', 'xlsx'])) {
            $message = "Invalid file type. Please upload CSV, XLS, or XLSX file.";
            $message_type = 'error';
        } else {
            $import_count = 0;
            $data_rows = [];
            
            if ($ext === 'csv') {
                if (($handle = fopen($file, "r")) !== FALSE) {
                    $header = fgetcsv($handle);
                    while (($data = fgetcsv($handle)) !== FALSE) {
                        if (count($data) >= 3 && !empty(trim($data[0]))) {
                            $data_rows[] = $data;
                        }
                    }
                    fclose($handle);
                }
            } else if ($ext === 'xlsx') {
                $zip = new ZipArchive();
                if ($zip->open($file) === TRUE) {
                    $shared_strings = [];
                    $xml_strings = $zip->getFromName('xl/sharedStrings.xml');
                    if ($xml_strings) {
                        $xml_strings = preg_replace('/xmlns[^=]*="[^"]*"/i', '', $xml_strings);
                        $xml = @simplexml_load_string($xml_strings);
                        if ($xml) {
                            foreach ($xml->si as $si) {
                                $shared_strings[] = isset($si->t) ? (string)$si->t : '';
                            }
                        }
                    }
                    
                    $xml_sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
                    if ($xml_sheet) {
                        $xml_sheet = preg_replace('/xmlns[^=]*="[^"]*"/i', '', $xml_sheet);
                        $xml = @simplexml_load_string($xml_sheet);
                        if ($xml && isset($xml->sheetData)) {
                            $row_index = 0;
                            foreach ($xml->sheetData->row as $row) {
                                $row_index++;
                                if ($row_index == 1) continue;
                                
                                $row_data = ['', '', '', '', ''];
                                foreach ($row->c as $cell) {
                                    $cell_ref = (string)$cell['r'];
                                    $col_letter = preg_replace('/[0-9]/', '', $cell_ref);
                                    $col_index = ord(strtoupper($col_letter)) - ord('A');
                                    
                                    $type = (string)$cell['t'];
                                    $value = '';
                                    
                                    if (isset($cell->v)) {
                                        $value = ($type === 's' && isset($shared_strings[(int)$cell->v])) 
                                            ? $shared_strings[(int)$cell->v] 
                                            : (string)$cell->v;
                                    }
                                    
                                    if ($col_index >= 0 && $col_index < 5) {
                                        $row_data[$col_index] = $value;
                                    }
                                }
                                
                                if (!empty(trim($row_data[0]))) {
                                    $data_rows[] = $row_data;
                                }
                            }
                        }
                    }
                    $zip->close();
                }
            }
            
            // Process data rows
            foreach ($data_rows as $data) {
                $email = trim($data[0] ?? '');
                $studfullid = trim($data[1] ?? '');
                $studname = trim($data[2] ?? '');
                
                if (empty($email) || empty($studfullid) || empty($studname)) continue;
                
                $studid = substr(preg_replace('/[^A-Za-z0-9]/', '', $studfullid), 0, 10);
                $generated_password = 'FYP' . rand(100000, 999999);
                
                // Check if exists
                $stmt = $conn->prepare("SELECT fyp_userid FROM user WHERE fyp_username = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    $stmt->close();
                    continue;
                }
                $stmt->close();
                
                $conn->begin_transaction();
                try {
                    $password_hash = password_hash($generated_password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO user (fyp_username, fyp_passwordhash, fyp_usertype, fyp_datecreated) VALUES (?, ?, 'student', NOW())");
                    $stmt->bind_param("ss", $email, $password_hash);
                    $stmt->execute();
                    $new_user_id = $stmt->insert_id;
                    $stmt->close();
                    
                    $stmt = $conn->prepare("INSERT INTO student (fyp_studid, fyp_studfullid, fyp_studname, fyp_email, fyp_userid) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssssi", $studid, $studfullid, $studname, $email, $new_user_id);
                    $stmt->execute();
                    $stmt->close();
                    
                    $conn->commit();
                    $import_count++;
                } catch (Exception $e) {
                    $conn->rollback();
                }
            }
            
            $message = "Import complete! $import_count students imported.";
            $message_type = 'success';
        }
    }
}

// ===================== MENU CONFIGURATION =====================
$menu_items = [
    'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home'],
    'registrations' => ['name' => 'Student Registrations', 'icon' => 'fa-user-plus', 'badge' => $pending_registrations],
    'group_requests' => ['name' => 'Group Requests', 'icon' => 'fa-users', 'badge' => $pending_requests],
    'students' => ['name' => 'Manage Students', 'icon' => 'fa-user-graduate'],
    'supervisors' => ['name' => 'Manage Supervisors', 'icon' => 'fa-user-tie'],
    'pairing' => ['name' => 'Student-Supervisor Pairing', 'icon' => 'fa-link'],
    'projects' => ['name' => 'Manage Projects', 'icon' => 'fa-folder-open'],
    'moderation' => ['name' => 'Student Moderation', 'icon' => 'fa-clipboard-check'],
    'rubrics' => ['name' => 'Rubrics Assessment', 'icon' => 'fa-list-check'],
    'marks' => ['name' => 'Assessment Marks', 'icon' => 'fa-calculator'],
    'reports' => ['name' => 'Reports', 'icon' => 'fa-file-alt', 'link' => 'Coordinator_report.php'],
    'announcements' => ['name' => 'Announcements', 'icon' => 'fa-bullhorn'],
    'settings' => ['name' => 'Settings', 'icon' => 'fa-cog'],
];

// ===================== INCLUDE PAGE CONTENT =====================
// Check if page content file exists
$page_file = __DIR__ . '/pages/coordinator_' . $current_page . '.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Coordinator Dashboard - FYP Portal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/coordinator_styles.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #0f0f1a; color: #e2e8f0; min-height: 100vh; }
        
        /* Sidebar */
        .sidebar { width: 280px; height: 100vh; background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%); position: fixed; left: 0; top: 0; border-right: 1px solid rgba(139, 92, 246, 0.1); overflow-y: auto; z-index: 1000; }
        .sidebar-header { padding: 25px; text-align: center; border-bottom: 1px solid rgba(139, 92, 246, 0.1); background: rgba(139, 92, 246, 0.05); }
        .sidebar-header img { width: 80px; height: 80px; border-radius: 50%; margin-bottom: 12px; border: 3px solid #8b5cf6; }
        .sidebar-header h3 { font-size: 1rem; margin-bottom: 5px; color: #fff; }
        .sidebar-header p { font-size: 0.75rem; color: #a78bfa; text-transform: uppercase; letter-spacing: 1px; }
        
        .sidebar-nav { list-style: none; padding: 15px 0; }
        .sidebar-nav li a { display: flex; align-items: center; padding: 14px 25px; color: #94a3b8; text-decoration: none; transition: all 0.3s; border-left: 3px solid transparent; font-size: 0.9rem; }
        .sidebar-nav li a:hover, .sidebar-nav li a.active { background: rgba(139, 92, 246, 0.15); color: #fff; border-left-color: #8b5cf6; }
        .sidebar-nav li a i { margin-right: 12px; width: 20px; }
        .nav-badge { margin-left: auto; background: #ef4444; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.7rem; }
        
        .sidebar-footer { padding: 20px 25px; border-top: 1px solid rgba(139, 92, 246, 0.1); }
        .sidebar-footer a { color: #f87171; text-decoration: none; display: flex; align-items: center; }
        .sidebar-footer a i { margin-right: 10px; }
        
        /* Main Content */
        .main-content { margin-left: 280px; min-height: 100vh; }
        .header { background: rgba(26, 26, 46, 0.8); backdrop-filter: blur(10px); padding: 20px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(139, 92, 246, 0.1); position: sticky; top: 0; z-index: 100; }
        .header h1 { font-size: 1.5rem; color: #fff; }
        .header-time { color: #94a3b8; font-size: 0.85rem; }
        
        .content { padding: 30px; }
        
        /* Cards */
        .card { background: rgba(26, 26, 46, 0.6); border-radius: 16px; border: 1px solid rgba(139, 92, 246, 0.1); margin-bottom: 25px; }
        .card-header { padding: 20px 25px; border-bottom: 1px solid rgba(139, 92, 246, 0.1); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .card-header h3 { font-size: 1.1rem; color: #fff; }
        .card-body { padding: 25px; }
        
        /* Stats */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: linear-gradient(135deg, rgba(139, 92, 246, 0.1) 0%, rgba(59, 130, 246, 0.1) 100%); padding: 25px; border-radius: 16px; border: 1px solid rgba(139, 92, 246, 0.2); display: flex; align-items: center; transition: transform 0.3s; cursor: pointer; text-decoration: none; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-icon { width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-right: 20px; font-size: 1.5rem; }
        .stat-icon.purple { background: rgba(139, 92, 246, 0.2); color: #a78bfa; }
        .stat-icon.blue { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
        .stat-icon.green { background: rgba(16, 185, 129, 0.2); color: #34d399; }
        .stat-icon.orange { background: rgba(249, 115, 22, 0.2); color: #fb923c; }
        .stat-icon.red { background: rgba(239, 68, 68, 0.2); color: #f87171; }
        .stat-info h4 { font-size: 2rem; color: #fff; }
        .stat-info p { color: #94a3b8; font-size: 0.85rem; margin-top: 5px; }
        
        /* Welcome Box */
        .welcome-box { background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 50%, #3b82f6 100%); color: white; padding: 35px; border-radius: 20px; margin-bottom: 30px; position: relative; overflow: hidden; }
        .welcome-box::before { content: ''; position: absolute; top: -50%; right: -20%; width: 400px; height: 400px; background: rgba(255,255,255,0.1); border-radius: 50%; }
        .welcome-box h2 { font-size: 1.8rem; margin-bottom: 10px; position: relative; }
        .welcome-box p { opacity: 0.9; position: relative; }
        
        /* Tables */
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 15px; text-align: left; border-bottom: 1px solid rgba(139, 92, 246, 0.1); }
        .data-table th { background: rgba(139, 92, 246, 0.1); color: #a78bfa; font-weight: 600; font-size: 0.8rem; text-transform: uppercase; }
        .data-table tr:hover { background: rgba(139, 92, 246, 0.05); }
        .data-table td { font-size: 0.9rem; }
        
        /* Buttons */
        .btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-size: 0.85rem; font-weight: 600; transition: all 0.3s; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
        .btn-primary { background: linear-gradient(135deg, #8b5cf6, #6366f1); color: white; }
        .btn-primary:hover { box-shadow: 0 5px 20px rgba(139, 92, 246, 0.4); transform: translateY(-2px); }
        .btn-success { background: linear-gradient(135deg, #10b981, #059669); color: white; }
        .btn-danger { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; }
        .btn-secondary { background: rgba(100, 116, 139, 0.2); color: #94a3b8; border: 1px solid rgba(100, 116, 139, 0.3); }
        .btn-info { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; }
        .btn-sm { padding: 6px 12px; font-size: 0.8rem; }
        
        /* Alerts */
        .alert { padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); color: #34d399; }
        .alert-error { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #f87171; }
        .alert-warning { background: rgba(249, 115, 22, 0.1); border: 1px solid rgba(249, 115, 22, 0.3); color: #fb923c; }
        
        /* Forms */
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; color: #a78bfa; font-size: 0.9rem; margin-bottom: 8px; font-weight: 500; }
        .form-control { width: 100%; padding: 12px 15px; background: rgba(15, 15, 26, 0.5); border: 1px solid rgba(139, 92, 246, 0.2); border-radius: 8px; color: #fff; font-size: 0.95rem; }
        .form-control:focus { outline: none; border-color: #8b5cf6; box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1); }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
        
        /* Badges */
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .badge-pending { background: rgba(249, 115, 22, 0.2); color: #fb923c; }
        .badge-approved { background: rgba(16, 185, 129, 0.2); color: #34d399; }
        .badge-rejected { background: rgba(239, 68, 68, 0.2); color: #f87171; }
        .badge-open { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
        .badge-taken { background: rgba(139, 92, 246, 0.2); color: #a78bfa; }
        
        /* Quick Actions */
        .quick-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; }
        .quick-action { background: rgba(139, 92, 246, 0.1); border: 1px solid rgba(139, 92, 246, 0.2); border-radius: 12px; padding: 20px; text-align: center; cursor: pointer; transition: all 0.3s; text-decoration: none; color: inherit; display: block; }
        .quick-action:hover { background: rgba(139, 92, 246, 0.2); transform: translateY(-3px); }
        .quick-action i { font-size: 2rem; color: #a78bfa; margin-bottom: 10px; display: block; }
        .quick-action h4 { color: #fff; font-size: 0.95rem; margin-bottom: 5px; }
        .quick-action p { color: #64748b; font-size: 0.8rem; }
        
        /* Modal */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 2000; align-items: center; justify-content: center; }
        .modal-overlay.active { display: flex; }
        .modal { background: #1a1a2e; border-radius: 16px; border: 1px solid rgba(139, 92, 246, 0.2); width: 100%; max-width: 600px; max-height: 90vh; overflow-y: auto; }
        .modal-header { padding: 20px 25px; border-bottom: 1px solid rgba(139, 92, 246, 0.1); display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { color: #fff; }
        .modal-close { background: none; border: none; color: #94a3b8; font-size: 1.5rem; cursor: pointer; }
        .modal-body { padding: 25px; }
        
        /* Empty State */
        .empty-state { text-align: center; padding: 50px 20px; color: #64748b; }
        .empty-state i { font-size: 4rem; margin-bottom: 20px; opacity: 0.3; }
        
        /* Search & Filter */
        .search-box { display: flex; gap: 10px; margin-bottom: 20px; }
        .filter-row { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
        .filter-row .form-group { margin-bottom: 0; min-width: 150px; }
        
        /* Tabs */
        .tab-nav { display: flex; gap: 5px; margin-bottom: 20px; border-bottom: 1px solid rgba(139, 92, 246, 0.1); padding-bottom: 10px; }
        .tab-btn { padding: 10px 20px; background: transparent; border: none; color: #94a3b8; cursor: pointer; border-radius: 8px 8px 0 0; transition: all 0.3s; }
        .tab-btn.active { background: rgba(139, 92, 246, 0.2); color: #fff; }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<nav class="sidebar">
    <div class="sidebar-header">
        <img src="<?= $img_src; ?>" alt="Profile">
        <h3><?= htmlspecialchars($user_name); ?></h3>
        <p>Coordinator</p>
    </div>
    <ul class="sidebar-nav">
        <?php foreach ($menu_items as $key => $item): 
            $href = isset($item['link']) ? $item['link'] : "?page=$key";
            $is_active = isset($item['link']) ? false : ($key === $current_page);
        ?>
            <li><a href="<?= $href; ?>" class="<?= $is_active ? 'active' : ''; ?>">
                <i class="fas <?= $item['icon']; ?>"></i><?= $item['name']; ?>
                <?php if (isset($item['badge']) && $item['badge'] > 0): ?>
                    <span class="nav-badge"><?= $item['badge']; ?></span>
                <?php endif; ?>
            </a></li>
        <?php endforeach; ?>
    </ul>
    <div class="sidebar-footer">
        <a href="../Login.php?logout=1"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</nav>

<!-- MAIN CONTENT -->
<div class="main-content">
    <header class="header">
        <h1><?= $menu_items[$current_page]['name'] ?? 'Dashboard'; ?></h1>
        <span class="header-time"><?= date('l, F j, Y'); ?></span>
    </header>
    
    <div class="content">
        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type; ?>">
                <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i> 
                <?= $message; ?>
            </div>
        <?php endif; ?>

        <!-- PAGE CONTENT -->
        <?php 
        // Include page-specific content
        $page_content_file = __DIR__ . '/pages/coordinator_' . $current_page . '.php';
        if (file_exists($page_content_file)) {
            include($page_content_file);
        } else {
            // Fallback to inline content (for backward compatibility)
            include_page_content($current_page);
        }
        ?>
    </div>
</div>

<!-- MODAL TEMPLATE -->
<div class="modal-overlay" id="genericModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalTitle">Modal</h3>
            <button class="modal-close" onclick="closeModal('genericModal')">&times;</button>
        </div>
        <div class="modal-body" id="modalBody"></div>
    </div>
</div>

<script>
function openModal(id) {
    document.getElementById(id).classList.add('active');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

// Close modal on overlay click
document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});
</script>

<?php
// ===================== FALLBACK PAGE CONTENT FUNCTION =====================
function include_page_content($page) {
    global $conn, $total_students, $total_supervisors, $pending_requests, $total_projects, $total_pairings, $pending_registrations, $user_name, $academic_years, $programmes, $supervisors, $auth_user_id;
    
    switch ($page) {
        case 'dashboard':
            ?>
            <div class="welcome-box">
                <h2>Welcome back, <?= htmlspecialchars($user_name); ?>!</h2>
                <p>Manage FYP students, supervisors, pairings, assessments and generate reports.</p>
            </div>
            
            <div class="stats-grid">
                <a href="?page=students" class="stat-card">
                    <div class="stat-icon purple"><i class="fas fa-user-graduate"></i></div>
                    <div class="stat-info"><h4><?= $total_students; ?></h4><p>Total Students</p></div>
                </a>
                <a href="?page=supervisors" class="stat-card">
                    <div class="stat-icon blue"><i class="fas fa-user-tie"></i></div>
                    <div class="stat-info"><h4><?= $total_supervisors; ?></h4><p>Total Supervisors</p></div>
                </a>
                <a href="?page=group_requests" class="stat-card">
                    <div class="stat-icon orange"><i class="fas fa-user-plus"></i></div>
                    <div class="stat-info"><h4><?= $pending_requests; ?></h4><p>Pending Group Requests</p></div>
                </a>
                <a href="?page=projects" class="stat-card">
                    <div class="stat-icon green"><i class="fas fa-folder-open"></i></div>
                    <div class="stat-info"><h4><?= $total_projects; ?></h4><p>Total Projects</p></div>
                </a>
                <a href="?page=pairing" class="stat-card">
                    <div class="stat-icon red"><i class="fas fa-link"></i></div>
                    <div class="stat-info"><h4><?= $total_pairings; ?></h4><p>Total Pairings</p></div>
                </a>
            </div>
            
            <?php if ($pending_requests > 0): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> 
                You have <strong><?= $pending_requests; ?></strong> pending group request(s). 
                <a href="?page=group_requests" style="color:#fb923c;margin-left:10px;">Review Now →</a>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header"><h3>Quick Actions</h3></div>
                <div class="card-body">
                    <div class="quick-actions">
                        <a href="?page=group_requests" class="quick-action">
                            <i class="fas fa-user-plus"></i>
                            <h4>Group Requests</h4>
                            <p>Approve or reject requests</p>
                        </a>
                        <a href="?page=pairing" class="quick-action">
                            <i class="fas fa-link"></i>
                            <h4>Manage Pairings</h4>
                            <p>Assign students to supervisors</p>
                        </a>
                        <a href="?page=rubrics" class="quick-action">
                            <i class="fas fa-list-check"></i>
                            <h4>Assessment Rubrics</h4>
                            <p>Create and manage rubrics</p>
                        </a>
                        <a href="?page=marks" class="quick-action">
                            <i class="fas fa-calculator"></i>
                            <h4>View Marks</h4>
                            <p>Assessment mark allocation</p>
                        </a>
                        <a href="Coordinator_report.php" class="quick-action">
                            <i class="fas fa-file-alt"></i>
                            <h4>Generate Reports</h4>
                            <p>Export forms and reports</p>
                        </a>
                        <a href="?page=announcements" class="quick-action">
                            <i class="fas fa-bullhorn"></i>
                            <h4>Announcements</h4>
                            <p>Post announcements</p>
                        </a>
                    </div>
                </div>
            </div>
            <?php
            break;
            
        default:
            ?>
            <div class="card">
                <div class="card-body">
                    <div class="empty-state">
                        <i class="fas fa-tools"></i>
                        <h3>Page Under Construction</h3>
                        <p>This page content will be loaded from separate include files.</p>
                        <p>Create file: <code>pages/coordinator_<?= htmlspecialchars($page); ?>.php</code></p>
                    </div>
                </div>
            </div>
            <?php
            break;
    }
}
?>

</body>
</html>