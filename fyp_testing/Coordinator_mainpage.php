<?php
/**
 * COORDINATOR MAIN PAGE
 * Coordinator_mainpage.php
 * Complete single-file coordinator portal
 */

session_start();
include("../db_connect.php");

// Authentication check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'coordinator') {
    header("Location: ../Login.php");
    exit;
}

$auth_user_id = $_SESSION['user_id'];
$user_name = $_SESSION['username'] ?? 'Coordinator';
$current_page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

$img_src = "https://ui-avatars.com/api/?name=" . urlencode($user_name) . "&background=7C3AED&color=fff";

// Get counts
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

// Get dropdown data
$academic_years = [];
$res = $conn->query("SELECT * FROM academic_year ORDER BY fyp_academicid DESC");
if ($res) { while ($row = $res->fetch_assoc()) { $academic_years[] = $row; } }

$programmes = [];
$res = $conn->query("SELECT * FROM programme ORDER BY fyp_progname");
if ($res) { while ($row = $res->fetch_assoc()) { $programmes[] = $row; } }

$message = '';
$message_type = '';

// Menu items
$menu_items = [
    'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home'],
    'register' => ['name' => 'Student Registrations', 'icon' => 'fa-user-plus', 'badge' => $pending_registrations],
    'grouprequest' => ['name' => 'Group Requests', 'icon' => 'fa-users', 'badge' => $pending_requests],
    'students' => ['name' => 'Manage Students', 'icon' => 'fa-user-graduate'],
    'supervisors' => ['name' => 'Manage Supervisors', 'icon' => 'fa-user-tie'],
    'pairing' => ['name' => 'Student-Supervisor Pairing', 'icon' => 'fa-link'],
    'project' => ['name' => 'Manage Projects', 'icon' => 'fa-folder-open'],
    'moderation' => ['name' => 'Student Moderation', 'icon' => 'fa-clipboard-check'],
    'rubrics' => ['name' => 'Rubrics Assessment', 'icon' => 'fa-list-check'],
    'marks' => ['name' => 'Assessment Marks', 'icon' => 'fa-calculator'],
    'report' => ['name' => 'Reports', 'icon' => 'fa-file-alt'],
    'announcement' => ['name' => 'Announcements', 'icon' => 'fa-bullhorn'],
    'settings' => ['name' => 'Settings', 'icon' => 'fa-cog'],
];

$page_title = $menu_items[$current_page]['name'] ?? 'Dashboard';

// ============== HANDLE POST REQUESTS ==============

// Group Request - Update Status
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

// Student - Edit
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
        $message = "Student information updated successfully!";
        $message_type = 'success';
    }
    $stmt->close();
}

// Student - Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_student'])) {
    $studid = $_POST['studid'];
    $stmt = $conn->prepare("DELETE FROM student WHERE fyp_studid = ?");
    $stmt->bind_param("s", $studid);
    if ($stmt->execute()) {
        $message = "Student deleted successfully!";
        $message_type = 'success';
    }
    $stmt->close();
}

// Pairing - Create
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_pairing'])) {
    $supervisor_id = $_POST['supervisor_id'];
    $project_id = $_POST['project_id'];
    $moderator_id = $_POST['moderator_id'] ?: null;
    $academic_id = $_POST['academic_id'];
    $pairing_type = $_POST['pairing_type'];
    
    $stmt = $conn->prepare("INSERT INTO pairing (fyp_supervisorid, fyp_projectid, fyp_moderatorid, fyp_academicid, fyp_type, fyp_datecreated) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("iiiss", $supervisor_id, $project_id, $moderator_id, $academic_id, $pairing_type);
    if ($stmt->execute()) {
        $message = "Pairing created successfully!";
        $message_type = 'success';
    }
    $stmt->close();
}

// Project - Add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_project'])) {
    $title = trim($_POST['project_title']);
    $type = $_POST['project_type'];
    $status = $_POST['project_status'];
    $category = trim($_POST['project_category'] ?? '');
    $description = trim($_POST['project_description'] ?? '');
    $supervisor_id = !empty($_POST['supervisor_id']) ? $_POST['supervisor_id'] : null;
    $max_students = $_POST['max_students'] ?? 1;
    
    $stmt = $conn->prepare("INSERT INTO project (fyp_projecttitle, fyp_projecttype, fyp_projectstatus, fyp_projectcat, fyp_projectdesc, fyp_supervisorid, fyp_maxstudent, fyp_datecreated) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("sssssii", $title, $type, $status, $category, $description, $supervisor_id, $max_students);
    if ($stmt->execute()) {
        $message = "Project added successfully!";
        $message_type = 'success';
    }
    $stmt->close();
}

// Project - Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_project'])) {
    $project_id = $_POST['project_id'];
    $title = trim($_POST['project_title']);
    $type = $_POST['project_type'];
    $status = $_POST['project_status'];
    $category = trim($_POST['project_category'] ?? '');
    $description = trim($_POST['project_description'] ?? '');
    $supervisor_id = !empty($_POST['supervisor_id']) ? $_POST['supervisor_id'] : null;
    $max_students = $_POST['max_students'] ?? 1;
    
    $stmt = $conn->prepare("UPDATE project SET fyp_projecttitle = ?, fyp_projecttype = ?, fyp_projectstatus = ?, fyp_projectcat = ?, fyp_projectdesc = ?, fyp_supervisorid = ?, fyp_maxstudent = ? WHERE fyp_projectid = ?");
    $stmt->bind_param("sssssiii", $title, $type, $status, $category, $description, $supervisor_id, $max_students, $project_id);
    if ($stmt->execute()) {
        $message = "Project updated successfully!";
        $message_type = 'success';
    }
    $stmt->close();
}

// Project - Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_project'])) {
    $project_id = $_POST['project_id'];
    $stmt = $conn->prepare("DELETE FROM project WHERE fyp_projectid = ?");
    $stmt->bind_param("i", $project_id);
    if ($stmt->execute()) {
        $message = "Project deleted successfully!";
        $message_type = 'success';
    }
    $stmt->close();
}

// Registration - Approve
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_registration'])) {
    $reg_id = $_POST['registration_id'];
    
    $stmt = $conn->prepare("SELECT * FROM pending_registration WHERE id = ?");
    $stmt->bind_param("i", $reg_id);
    $stmt->execute();
    $reg = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($reg) {
        $username = strtolower(str_replace(' ', '', $reg['student_id']));
        $password = bin2hex(random_bytes(4));
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("INSERT INTO user (fyp_username, fyp_password, fyp_usertype) VALUES (?, ?, 'student')");
        $stmt->bind_param("ss", $username, $hashed_password);
        $stmt->execute();
        $user_id = $conn->insert_id;
        $stmt->close();
        
        $stmt = $conn->prepare("INSERT INTO student (fyp_studfullid, fyp_studname, fyp_email, fyp_contactno, fyp_progid, fyp_academicid, fyp_userid) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssiis", $reg['student_id'], $reg['full_name'], $reg['email'], $reg['phone'], $reg['programme_id'], $reg['academic_year_id'], $user_id);
        $stmt->execute();
        $stmt->close();
        
        $stmt = $conn->prepare("UPDATE pending_registration SET status = 'approved' WHERE id = ?");
        $stmt->bind_param("i", $reg_id);
        $stmt->execute();
        $stmt->close();
        
        $message = "Registration approved! Username: $username, Password: $password";
        $message_type = 'success';
    }
}

// Registration - Reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_registration'])) {
    $reg_id = $_POST['registration_id'];
    $stmt = $conn->prepare("UPDATE pending_registration SET status = 'rejected' WHERE id = ?");
    $stmt->bind_param("i", $reg_id);
    if ($stmt->execute()) {
        $message = "Registration rejected.";
        $message_type = 'success';
    }
    $stmt->close();
}

// Rubrics - Add Set
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_set'])) {
    $setname = $_POST['setname'];
    $academic_id = $_POST['academic_id'];
    $stmt = $conn->prepare("INSERT INTO `set` (fyp_setname, fyp_academicid) VALUES (?, ?)");
    $stmt->bind_param("si", $setname, $academic_id);
    if ($stmt->execute()) {
        $message = "Assessment set added successfully!";
        $message_type = 'success';
    }
    $stmt->close();
}

// Rubrics - Add Item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    $itemname = $_POST['itemname'];
    $itemdesc = $_POST['itemdesc'] ?? '';
    $stmt = $conn->prepare("INSERT INTO item (fyp_itemname, fyp_itemdesc) VALUES (?, ?)");
    $stmt->bind_param("ss", $itemname, $itemdesc);
    if ($stmt->execute()) {
        $message = "Item added successfully!";
        $message_type = 'success';
    }
    $stmt->close();
}

// Rubrics - Add Marking Criteria
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_marking_criteria'])) {
    $criterianame = $_POST['criterianame'];
    $percent = $_POST['percentallocation'];
    $stmt = $conn->prepare("INSERT INTO marking_criteria (fyp_criterianame, fyp_percentallocation) VALUES (?, ?)");
    $stmt->bind_param("sd", $criterianame, $percent);
    if ($stmt->execute()) {
        $message = "Marking criteria added successfully!";
        $message_type = 'success';
    }
    $stmt->close();
}

// Announcement - Create
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_announcement'])) {
    $title = $_POST['title'];
    $content = $_POST['content'];
    $receiver = $_POST['receiver'];
    
    $stmt = $conn->prepare("INSERT INTO announcement (fyp_title, fyp_content, fyp_receiver, fyp_datecreated) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("sss", $title, $content, $receiver);
    if ($stmt->execute()) {
        $message = "Announcement created successfully!";
        $message_type = 'success';
    }
    $stmt->close();
}

// Announcement - Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_announcement'])) {
    $ann_id = $_POST['announcement_id'];
    $stmt = $conn->prepare("DELETE FROM announcement WHERE fyp_announcementid = ?");
    $stmt->bind_param("i", $ann_id);
    if ($stmt->execute()) {
        $message = "Announcement deleted successfully!";
        $message_type = 'success';
    }
    $stmt->close();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $page_title; ?> - FYP Portal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #0f0f1a; color: #e2e8f0; min-height: 100vh; }
        
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
        
        .main-content { margin-left: 280px; min-height: 100vh; }
        .header { background: rgba(26, 26, 46, 0.8); backdrop-filter: blur(10px); padding: 20px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(139, 92, 246, 0.1); position: sticky; top: 0; z-index: 100; }
        .header h1 { font-size: 1.5rem; color: #fff; }
        .header-time { color: #94a3b8; font-size: 0.85rem; }
        
        .content { padding: 30px; }
        
        .card { background: rgba(26, 26, 46, 0.6); border-radius: 16px; border: 1px solid rgba(139, 92, 246, 0.1); margin-bottom: 25px; }
        .card-header { padding: 20px 25px; border-bottom: 1px solid rgba(139, 92, 246, 0.1); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .card-header h3 { font-size: 1.1rem; color: #fff; }
        .card-body { padding: 25px; }
        
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
        
        .welcome-box { background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 50%, #3b82f6 100%); color: white; padding: 35px; border-radius: 20px; margin-bottom: 30px; position: relative; overflow: hidden; }
        .welcome-box::before { content: ''; position: absolute; top: -50%; right: -20%; width: 400px; height: 400px; background: rgba(255,255,255,0.1); border-radius: 50%; }
        .welcome-box h2 { font-size: 1.8rem; margin-bottom: 10px; position: relative; }
        .welcome-box p { opacity: 0.9; position: relative; }
        
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 15px; text-align: left; border-bottom: 1px solid rgba(139, 92, 246, 0.1); }
        .data-table th { background: rgba(139, 92, 246, 0.1); color: #a78bfa; font-weight: 600; font-size: 0.8rem; text-transform: uppercase; }
        .data-table tr:hover { background: rgba(139, 92, 246, 0.05); }
        .data-table td { font-size: 0.9rem; }
        
        .btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-size: 0.85rem; font-weight: 600; transition: all 0.3s; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
        .btn-primary { background: linear-gradient(135deg, #8b5cf6, #6366f1); color: white; }
        .btn-primary:hover { box-shadow: 0 5px 20px rgba(139, 92, 246, 0.4); transform: translateY(-2px); }
        .btn-success { background: linear-gradient(135deg, #10b981, #059669); color: white; }
        .btn-danger { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; }
        .btn-secondary { background: rgba(100, 116, 139, 0.2); color: #94a3b8; border: 1px solid rgba(100, 116, 139, 0.3); }
        .btn-info { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; }
        .btn-warning { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
        .btn-sm { padding: 6px 12px; font-size: 0.8rem; }
        
        .alert { padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); color: #34d399; }
        .alert-error { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #f87171; }
        .alert-warning { background: rgba(249, 115, 22, 0.1); border: 1px solid rgba(249, 115, 22, 0.3); color: #fb923c; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; color: #a78bfa; font-size: 0.9rem; margin-bottom: 8px; font-weight: 500; }
        .form-control { width: 100%; padding: 12px 15px; background: rgba(15, 15, 26, 0.5); border: 1px solid rgba(139, 92, 246, 0.2); border-radius: 8px; color: #fff; font-size: 0.95rem; }
        .form-control:focus { outline: none; border-color: #8b5cf6; box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1); }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
        
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .badge-pending { background: rgba(249, 115, 22, 0.2); color: #fb923c; }
        .badge-approved { background: rgba(16, 185, 129, 0.2); color: #34d399; }
        .badge-rejected { background: rgba(239, 68, 68, 0.2); color: #f87171; }
        .badge-open { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
        .badge-taken { background: rgba(139, 92, 246, 0.2); color: #a78bfa; }
        
        .quick-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; }
        .quick-action { background: rgba(139, 92, 246, 0.1); border: 1px solid rgba(139, 92, 246, 0.2); border-radius: 12px; padding: 20px; text-align: center; cursor: pointer; transition: all 0.3s; text-decoration: none; color: inherit; display: block; }
        .quick-action:hover { background: rgba(139, 92, 246, 0.2); transform: translateY(-3px); }
        .quick-action i { font-size: 2rem; color: #a78bfa; margin-bottom: 10px; display: block; }
        .quick-action h4 { color: #fff; font-size: 0.95rem; margin-bottom: 5px; }
        .quick-action p { color: #64748b; font-size: 0.8rem; }
        
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 2000; align-items: center; justify-content: center; }
        .modal-overlay.active { display: flex; }
        .modal { background: #1a1a2e; border-radius: 16px; border: 1px solid rgba(139, 92, 246, 0.2); width: 100%; max-width: 600px; max-height: 90vh; overflow-y: auto; }
        .modal-header { padding: 20px 25px; border-bottom: 1px solid rgba(139, 92, 246, 0.1); display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { color: #fff; }
        .modal-close { background: none; border: none; color: #94a3b8; font-size: 1.5rem; cursor: pointer; }
        .modal-body { padding: 25px; }
        .modal-footer { padding: 20px 25px; border-top: 1px solid rgba(139, 92, 246, 0.1); display: flex; justify-content: flex-end; gap: 10px; }
        
        .empty-state { text-align: center; padding: 50px 20px; color: #64748b; }
        .empty-state i { font-size: 4rem; margin-bottom: 20px; opacity: 0.3; }
        
        .search-box { display: flex; gap: 10px; margin-bottom: 20px; }
        .filter-row { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
        
        .tab-nav { display: flex; gap: 5px; margin-bottom: 20px; border-bottom: 1px solid rgba(139, 92, 246, 0.1); padding-bottom: 10px; flex-wrap: wrap; }
        .tab-btn { padding: 10px 20px; background: transparent; border: none; color: #94a3b8; cursor: pointer; border-radius: 8px 8px 0 0; transition: all 0.3s; text-decoration: none; }
        .tab-btn:hover, .tab-btn.active { background: rgba(139, 92, 246, 0.2); color: #fff; }
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
        <?php foreach ($menu_items as $key => $item): ?>
            <li><a href="?page=<?= $key; ?>" class="<?= $current_page === $key ? 'active' : ''; ?>">
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
        <h1><?= $page_title; ?></h1>
        <span class="header-time"><?= date('l, F j, Y'); ?></span>
    </header>
    
    <div class="content">
        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type; ?>">
                <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i> 
                <?= $message; ?>
            </div>
        <?php endif; ?>

<?php
// ============== PAGE CONTENT ==============

switch ($current_page) {

// ==================== DASHBOARD ====================
case 'dashboard':
?>
<div class="welcome-box">
    <h2>Welcome back, <?= htmlspecialchars($user_name); ?>!</h2>
    <p>Manage FYP students, supervisors, pairings, assessments and generate reports.</p>
</div>

<div class="stats-grid">
    <a href="?page=students" class="stat-card"><div class="stat-icon purple"><i class="fas fa-user-graduate"></i></div><div class="stat-info"><h4><?= $total_students; ?></h4><p>Total Students</p></div></a>
    <a href="?page=supervisors" class="stat-card"><div class="stat-icon blue"><i class="fas fa-user-tie"></i></div><div class="stat-info"><h4><?= $total_supervisors; ?></h4><p>Total Supervisors</p></div></a>
    <a href="?page=grouprequest" class="stat-card"><div class="stat-icon orange"><i class="fas fa-user-plus"></i></div><div class="stat-info"><h4><?= $pending_requests; ?></h4><p>Pending Group Requests</p></div></a>
    <a href="?page=project" class="stat-card"><div class="stat-icon green"><i class="fas fa-folder-open"></i></div><div class="stat-info"><h4><?= $total_projects; ?></h4><p>Total Projects</p></div></a>
    <a href="?page=pairing" class="stat-card"><div class="stat-icon red"><i class="fas fa-link"></i></div><div class="stat-info"><h4><?= $total_pairings; ?></h4><p>Total Pairings</p></div></a>
</div>

<?php if ($pending_requests > 0): ?>
<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> You have <strong><?= $pending_requests; ?></strong> pending group request(s). <a href="?page=grouprequest" style="color:#fb923c;margin-left:10px;">Review Now →</a></div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h3>Quick Actions</h3></div>
    <div class="card-body">
        <div class="quick-actions">
            <a href="?page=grouprequest" class="quick-action"><i class="fas fa-user-plus"></i><h4>Group Requests</h4><p>Approve or reject requests</p></a>
            <a href="?page=pairing" class="quick-action"><i class="fas fa-link"></i><h4>Manage Pairings</h4><p>Assign students to supervisors</p></a>
            <a href="?page=rubrics" class="quick-action"><i class="fas fa-list-check"></i><h4>Assessment Rubrics</h4><p>Create and manage rubrics</p></a>
            <a href="?page=marks" class="quick-action"><i class="fas fa-calculator"></i><h4>View Marks</h4><p>Assessment mark allocation</p></a>
            <a href="?page=report" class="quick-action"><i class="fas fa-file-alt"></i><h4>Generate Reports</h4><p>Export forms and reports</p></a>
            <a href="?page=announcement" class="quick-action"><i class="fas fa-bullhorn"></i><h4>Announcements</h4><p>Post announcements</p></a>
        </div>
    </div>
</div>
<?php
break;

// ==================== GROUP REQUESTS ====================
case 'grouprequest':
    $all_requests = [];
    $res = $conn->query("SELECT gr.*, sg.group_name, s1.fyp_studname as inviter_name, s1.fyp_studfullid as inviter_fullid, s2.fyp_studname as invitee_name, s2.fyp_studfullid as invitee_fullid FROM group_request gr LEFT JOIN student_group sg ON gr.group_id = sg.group_id LEFT JOIN student s1 ON gr.inviter_id = s1.fyp_studid LEFT JOIN student s2 ON gr.invitee_id = s2.fyp_studid ORDER BY gr.request_status = 'Pending' DESC, gr.request_id DESC");
    if ($res) { while ($row = $res->fetch_assoc()) { $all_requests[] = $row; } }
    $pending_only = array_filter($all_requests, function($r) { return $r['request_status'] === 'Pending'; });
?>
<div class="card">
    <div class="card-header"><h3>All Group Requests</h3><span class="badge badge-pending"><?= count($pending_only); ?> Pending</span></div>
    <div class="card-body">
        <?php if (empty($all_requests)): ?>
            <div class="empty-state"><i class="fas fa-inbox"></i><p>No group requests found</p></div>
        <?php else: ?>
            <table class="data-table">
                <thead><tr><th>ID</th><th>Group</th><th>Inviter</th><th>Invitee</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach ($all_requests as $req): ?>
                <tr>
                    <td><?= $req['request_id']; ?></td>
                    <td><strong><?= htmlspecialchars($req['group_name'] ?? 'Group #' . $req['group_id']); ?></strong></td>
                    <td><?= htmlspecialchars(($req['inviter_fullid'] ?? '') . ' - ' . ($req['inviter_name'] ?? '')); ?></td>
                    <td><?= htmlspecialchars(($req['invitee_fullid'] ?? '') . ' - ' . ($req['invitee_name'] ?? '')); ?></td>
                    <td><span class="badge badge-<?= $req['request_status'] === 'Accepted' ? 'approved' : ($req['request_status'] === 'Pending' ? 'pending' : 'rejected'); ?>"><?= $req['request_status']; ?></span></td>
                    <td>
                        <form method="POST" style="display:flex;gap:8px;align-items:center;">
                            <input type="hidden" name="request_id" value="<?= $req['request_id']; ?>">
                            <select name="new_status" class="form-control" style="width:auto;padding:6px 10px;">
                                <option value="Pending" <?= $req['request_status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="Accepted" <?= $req['request_status'] === 'Accepted' ? 'selected' : ''; ?>>Accepted</option>
                                <option value="Rejected" <?= $req['request_status'] === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                            <button type="submit" name="update_request_status" class="btn btn-primary btn-sm"><i class="fas fa-save"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?php
break;

// ==================== STUDENTS ====================
case 'students':
    $students = [];
    $res = $conn->query("SELECT s.*, p.fyp_progname, p.fyp_progid, pr.fyp_projecttitle, pa_pr.fyp_projecttitle as paired_project FROM student s LEFT JOIN programme p ON s.fyp_progid = p.fyp_progid LEFT JOIN fyp_registration fr ON s.fyp_studid = fr.fyp_studid LEFT JOIN project pr ON fr.fyp_projectid = pr.fyp_projectid LEFT JOIN pairing pa ON s.fyp_studid = pa.fyp_studid LEFT JOIN project pa_pr ON pa.fyp_projectid = pa_pr.fyp_projectid ORDER BY s.fyp_studname");
    if ($res) { while ($row = $res->fetch_assoc()) { $students[] = $row; } }
?>
<div class="card">
    <div class="card-header"><h3>All Students (<?= count($students); ?>)</h3></div>
    <div class="card-body">
        <div class="search-box"><input type="text" class="form-control" placeholder="Search students..." id="studentSearch" onkeyup="filterTable('studentSearch','studentTable')"></div>
        <?php if (empty($students)): ?>
            <div class="empty-state"><i class="fas fa-user-graduate"></i><p>No students found</p></div>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="data-table" id="studentTable">
                    <thead><tr><th>Student ID</th><th>Name</th><th>Programme</th><th>Project</th><th>Group</th><th>Email</th><th>Contact</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($students as $s): $project_title = $s['fyp_projecttitle'] ?? $s['paired_project'] ?? null; ?>
                    <tr>
                        <td><?= htmlspecialchars($s['fyp_studfullid']); ?></td>
                        <td><?= htmlspecialchars($s['fyp_studname']); ?></td>
                        <td><?= htmlspecialchars($s['fyp_progname'] ?? '-'); ?></td>
                        <td><?= $project_title ? htmlspecialchars($project_title) : '<span style="color:#64748b;">No Project</span>'; ?></td>
                        <td><?= $s['fyp_group'] ? htmlspecialchars($s['fyp_group']) : '<span style="color:#64748b;">Individual</span>'; ?></td>
                        <td><?= htmlspecialchars($s['fyp_email'] ?? '-'); ?></td>
                        <td><?= htmlspecialchars($s['fyp_contactno'] ?? '-'); ?></td>
                        <td>
                            <button class="btn btn-primary btn-sm" onclick="openEditStudentModal('<?= $s['fyp_studid']; ?>','<?= htmlspecialchars($s['fyp_studname'], ENT_QUOTES); ?>','<?= htmlspecialchars($s['fyp_email'] ?? '', ENT_QUOTES); ?>','<?= htmlspecialchars($s['fyp_contactno'] ?? '', ENT_QUOTES); ?>','<?= $s['fyp_progid'] ?? 1; ?>','<?= htmlspecialchars($s['fyp_group'] ?? '', ENT_QUOTES); ?>')"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-danger btn-sm" onclick="confirmDeleteStudent('<?= $s['fyp_studid']; ?>','<?= htmlspecialchars($s['fyp_studname'], ENT_QUOTES); ?>')"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Student Modal -->
<div class="modal-overlay" id="editStudentModal">
    <div class="modal">
        <div class="modal-header"><h3><i class="fas fa-user-edit"></i> Edit Student</h3><button class="modal-close" onclick="closeModal('editStudentModal')">&times;</button></div>
        <form method="POST"><div class="modal-body">
            <input type="hidden" name="studid" id="edit_studid">
            <div class="form-group"><label>Name</label><input type="text" name="studname" id="edit_studname" class="form-control" required></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" id="edit_email" class="form-control"></div>
            <div class="form-group"><label>Contact</label><input type="text" name="contactno" id="edit_contactno" class="form-control"></div>
            <div class="form-row">
                <div class="form-group"><label>Programme</label><select name="progid" id="edit_progid" class="form-control"><?php foreach ($programmes as $p): ?><option value="<?= $p['fyp_progid']; ?>"><?= htmlspecialchars($p['fyp_progname']); ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Group Type</label><select name="group_type" id="edit_group_type" class="form-control"><option value="Individual">Individual</option><option value="Group">Group</option></select></div>
            </div>
        </div><div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('editStudentModal')">Cancel</button>
            <button type="submit" name="edit_student" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
        </div></form>
    </div>
</div>

<!-- Delete Student Modal -->
<div class="modal-overlay" id="deleteStudentModal">
    <div class="modal" style="max-width:450px;">
        <div class="modal-header"><h3><i class="fas fa-exclamation-triangle" style="color:#f87171;"></i> Confirm Delete</h3><button class="modal-close" onclick="closeModal('deleteStudentModal')">&times;</button></div>
        <form method="POST"><div class="modal-body">
            <input type="hidden" name="studid" id="delete_studid">
            <p style="text-align:center;">Are you sure you want to delete:<br><strong id="delete_studname" style="color:#fff;"></strong></p>
        </div><div class="modal-footer" style="justify-content:center;">
            <button type="button" class="btn btn-secondary" onclick="closeModal('deleteStudentModal')">Cancel</button>
            <button type="submit" name="delete_student" class="btn btn-danger"><i class="fas fa-trash"></i> Delete</button>
        </div></form>
    </div>
</div>
<?php
break;

// ==================== SUPERVISORS ====================
case 'supervisors':
    $supervisors = [];
    $res = $conn->query("SELECT * FROM supervisor ORDER BY fyp_name");
    if ($res) { while ($row = $res->fetch_assoc()) { $supervisors[] = $row; } }
?>
<div class="card">
    <div class="card-header"><h3>All Supervisors (<?= count($supervisors); ?>)</h3></div>
    <div class="card-body">
        <?php if (empty($supervisors)): ?>
            <div class="empty-state"><i class="fas fa-user-tie"></i><p>No supervisors found</p></div>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead><tr><th>ID</th><th>Name</th><th>Room</th><th>Programme</th><th>Email</th><th>Contact</th><th>Specialization</th><th>Moderator</th></tr></thead>
                    <tbody>
                    <?php foreach ($supervisors as $sup): ?>
                    <tr>
                        <td><?= $sup['fyp_supervisorid']; ?></td>
                        <td><?= htmlspecialchars($sup['fyp_name']); ?></td>
                        <td><?= htmlspecialchars($sup['fyp_roomno'] ?? '-'); ?></td>
                        <td><?= htmlspecialchars($sup['fyp_programme'] ?? '-'); ?></td>
                        <td><?= htmlspecialchars($sup['fyp_email'] ?? '-'); ?></td>
                        <td><?= htmlspecialchars($sup['fyp_contactno'] ?? '-'); ?></td>
                        <td><?= htmlspecialchars($sup['fyp_specialization'] ?? '-'); ?></td>
                        <td><?= $sup['fyp_ismoderator'] ? '<span class="badge badge-approved">Yes</span>' : '<span class="badge badge-pending">No</span>'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php
break;

// ==================== PAIRING ====================
case 'pairing':
    $pairings = [];
    $res = $conn->query("SELECT p.*, pr.fyp_projecttitle, s.fyp_name as supervisor_name, (SELECT fyp_name FROM supervisor WHERE fyp_supervisorid = p.fyp_moderatorid) as moderator_name, a.fyp_acdyear, a.fyp_intake FROM pairing p LEFT JOIN project pr ON p.fyp_projectid = pr.fyp_projectid LEFT JOIN supervisor s ON p.fyp_supervisorid = s.fyp_supervisorid LEFT JOIN academic_year a ON p.fyp_academicid = a.fyp_academicid ORDER BY p.fyp_datecreated DESC");
    if ($res) { while ($row = $res->fetch_assoc()) { $pairings[] = $row; } }
    
    $sup_list = [];
    $res = $conn->query("SELECT fyp_supervisorid, fyp_name FROM supervisor ORDER BY fyp_name");
    if ($res) { while ($row = $res->fetch_assoc()) { $sup_list[] = $row; } }
    
    $proj_list = [];
    $res = $conn->query("SELECT fyp_projectid, fyp_projecttitle FROM project ORDER BY fyp_projecttitle");
    if ($res) { while ($row = $res->fetch_assoc()) { $proj_list[] = $row; } }
?>
<div class="card">
    <div class="card-header"><h3>Student-Supervisor Pairings (<?= count($pairings); ?>)</h3><button class="btn btn-primary" onclick="openModal('createPairingModal')"><i class="fas fa-plus"></i> Create Pairing</button></div>
    <div class="card-body">
        <?php if (empty($pairings)): ?>
            <div class="empty-state"><i class="fas fa-link"></i><p>No pairings found</p></div>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead><tr><th>ID</th><th>Project</th><th>Supervisor</th><th>Moderator</th><th>Type</th><th>Academic Year</th><th>Created</th></tr></thead>
                    <tbody>
                    <?php foreach ($pairings as $p): ?>
                    <tr>
                        <td><?= $p['fyp_pairingid']; ?></td>
                        <td><?= htmlspecialchars($p['fyp_projecttitle'] ?? '-'); ?></td>
                        <td><?= htmlspecialchars($p['supervisor_name'] ?? '-'); ?></td>
                        <td><?= htmlspecialchars($p['moderator_name'] ?? '-'); ?></td>
                        <td><?= htmlspecialchars($p['fyp_type'] ?? '-'); ?></td>
                        <td><?= htmlspecialchars(($p['fyp_acdyear'] ?? '') . ' ' . ($p['fyp_intake'] ?? '')); ?></td>
                        <td><?= $p['fyp_datecreated'] ? date('M j, Y', strtotime($p['fyp_datecreated'])) : '-'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create Pairing Modal -->
<div class="modal-overlay" id="createPairingModal">
    <div class="modal">
        <div class="modal-header"><h3>Create New Pairing</h3><button class="modal-close" onclick="closeModal('createPairingModal')">&times;</button></div>
        <form method="POST"><div class="modal-body">
            <div class="form-group"><label>Supervisor</label><select name="supervisor_id" class="form-control" required><option value="">-- Select --</option><?php foreach ($sup_list as $s): ?><option value="<?= $s['fyp_supervisorid']; ?>"><?= htmlspecialchars($s['fyp_name']); ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label>Project</label><select name="project_id" class="form-control" required><option value="">-- Select --</option><?php foreach ($proj_list as $p): ?><option value="<?= $p['fyp_projectid']; ?>"><?= htmlspecialchars($p['fyp_projecttitle']); ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label>Moderator</label><select name="moderator_id" class="form-control"><option value="">-- Select --</option><?php foreach ($sup_list as $s): ?><option value="<?= $s['fyp_supervisorid']; ?>"><?= htmlspecialchars($s['fyp_name']); ?></option><?php endforeach; ?></select></div>
            <div class="form-row">
                <div class="form-group"><label>Academic Year</label><select name="academic_id" class="form-control" required><?php foreach ($academic_years as $ay): ?><option value="<?= $ay['fyp_academicid']; ?>"><?= $ay['fyp_acdyear'] . ' ' . $ay['fyp_intake']; ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Type</label><select name="pairing_type" class="form-control" required><option value="Individual">Individual</option><option value="Group">Group</option></select></div>
            </div>
        </div><div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('createPairingModal')">Cancel</button>
            <button type="submit" name="create_pairing" class="btn btn-primary">Create</button>
        </div></form>
    </div>
</div>
<?php
break;

// ==================== PROJECT ====================
case 'project':
    $projects = [];
    $res = $conn->query("SELECT p.*, s.fyp_name as supervisor_name, (SELECT COUNT(*) FROM pairing WHERE fyp_projectid = p.fyp_projectid) as student_count FROM project p LEFT JOIN supervisor s ON p.fyp_supervisorid = s.fyp_supervisorid ORDER BY p.fyp_datecreated DESC");
    if ($res) { while ($row = $res->fetch_assoc()) { $projects[] = $row; } }
    
    $supervisors_list = [];
    $res = $conn->query("SELECT fyp_supervisorid, fyp_name FROM supervisor ORDER BY fyp_name");
    if ($res) { while ($row = $res->fetch_assoc()) { $supervisors_list[] = $row; } }
    
    $available_count = count(array_filter($projects, function($p) { return ($p['fyp_projectstatus'] ?? '') === 'Available'; }));
?>
<div class="stats-grid" style="margin-bottom:25px;">
    <div class="stat-card" style="cursor:default;"><div class="stat-icon purple"><i class="fas fa-folder-open"></i></div><div class="stat-info"><h4><?= count($projects); ?></h4><p>Total Projects</p></div></div>
    <div class="stat-card" style="cursor:default;"><div class="stat-icon green"><i class="fas fa-check-circle"></i></div><div class="stat-info"><h4><?= $available_count; ?></h4><p>Available</p></div></div>
    <div class="stat-card" style="cursor:default;"><div class="stat-icon orange"><i class="fas fa-times-circle"></i></div><div class="stat-info"><h4><?= count($projects) - $available_count; ?></h4><p>Unavailable</p></div></div>
</div>

<div class="card">
    <div class="card-header"><h3>Project Allocation</h3><button class="btn btn-primary" onclick="openModal('addProjectModal')"><i class="fas fa-plus"></i> Add Project</button></div>
    <div class="card-body">
        <div class="search-box"><input type="text" class="form-control" placeholder="Search projects..." id="projectSearch" onkeyup="filterTable('projectSearch','projectTable')"></div>
        <?php if (empty($projects)): ?>
            <div class="empty-state"><i class="fas fa-folder-open"></i><p>No projects found</p></div>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="data-table" id="projectTable">
                    <thead><tr><th>ID</th><th>Title</th><th>Type</th><th>Status</th><th>Supervisor</th><th>Students</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($projects as $p): ?>
                    <tr>
                        <td><?= $p['fyp_projectid']; ?></td>
                        <td><strong><?= htmlspecialchars($p['fyp_projecttitle']); ?></strong></td>
                        <td><span class="badge badge-open"><?= $p['fyp_projecttype'] ?? '-'; ?></span></td>
                        <td><span class="badge badge-<?= ($p['fyp_projectstatus'] ?? '') === 'Available' ? 'approved' : 'pending'; ?>"><?= $p['fyp_projectstatus'] ?? '-'; ?></span></td>
                        <td><?= htmlspecialchars($p['supervisor_name'] ?? '-'); ?></td>
                        <td><?= $p['student_count']; ?>/<?= $p['fyp_maxstudent'] ?? 1; ?></td>
                        <td>
                            <button class="btn btn-primary btn-sm" onclick="editProject(<?= $p['fyp_projectid']; ?>,'<?= htmlspecialchars($p['fyp_projecttitle'], ENT_QUOTES); ?>','<?= $p['fyp_projecttype'] ?? ''; ?>','<?= $p['fyp_projectstatus'] ?? ''; ?>','<?= htmlspecialchars($p['fyp_projectcat'] ?? '', ENT_QUOTES); ?>','<?= htmlspecialchars($p['fyp_projectdesc'] ?? '', ENT_QUOTES); ?>','<?= $p['fyp_supervisorid'] ?? ''; ?>',<?= $p['fyp_maxstudent'] ?? 1; ?>)"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-danger btn-sm" onclick="deleteProject(<?= $p['fyp_projectid']; ?>,'<?= htmlspecialchars($p['fyp_projecttitle'], ENT_QUOTES); ?>')"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Project Modal -->
<div class="modal-overlay" id="addProjectModal">
    <div class="modal" style="max-width:700px;">
        <div class="modal-header"><h3><i class="fas fa-plus-circle" style="color:#34d399;"></i> Add New Project</h3><button class="modal-close" onclick="closeModal('addProjectModal')">&times;</button></div>
        <form method="POST"><div class="modal-body">
            <div class="form-row">
                <div class="form-group"><label>Project Title *</label><input type="text" name="project_title" class="form-control" required></div>
                <div class="form-group"><label>Type *</label><select name="project_type" class="form-control" required><option value="Research">Research</option><option value="Application">Application</option><option value="Package">Package</option></select></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Status *</label><select name="project_status" class="form-control" required><option value="Available">Available</option><option value="Unavailable">Unavailable</option></select></div>
                <div class="form-group"><label>Category</label><input type="text" name="project_category" class="form-control"></div>
            </div>
            <div class="form-group"><label>Description</label><textarea name="project_description" class="form-control" rows="3"></textarea></div>
            <div class="form-row">
                <div class="form-group"><label>Supervisor</label><select name="supervisor_id" class="form-control"><option value="">-- Select --</option><?php foreach ($supervisors_list as $sv): ?><option value="<?= $sv['fyp_supervisorid']; ?>"><?= htmlspecialchars($sv['fyp_name']); ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Max Students</label><input type="number" name="max_students" class="form-control" value="1" min="1" max="10"></div>
            </div>
        </div><div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('addProjectModal')">Cancel</button>
            <button type="submit" name="add_project" class="btn btn-success"><i class="fas fa-plus"></i> Add</button>
        </div></form>
    </div>
</div>

<!-- Edit Project Modal -->
<div class="modal-overlay" id="editProjectModal">
    <div class="modal" style="max-width:700px;">
        <div class="modal-header"><h3><i class="fas fa-edit" style="color:#60a5fa;"></i> Edit Project</h3><button class="modal-close" onclick="closeModal('editProjectModal')">&times;</button></div>
        <form method="POST"><input type="hidden" name="project_id" id="edit_project_id"><div class="modal-body">
            <div class="form-row">
                <div class="form-group"><label>Project Title *</label><input type="text" name="project_title" id="edit_project_title" class="form-control" required></div>
                <div class="form-group"><label>Type *</label><select name="project_type" id="edit_project_type" class="form-control" required><option value="Research">Research</option><option value="Application">Application</option><option value="Package">Package</option></select></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Status *</label><select name="project_status" id="edit_project_status" class="form-control" required><option value="Available">Available</option><option value="Unavailable">Unavailable</option></select></div>
                <div class="form-group"><label>Category</label><input type="text" name="project_category" id="edit_project_category" class="form-control"></div>
            </div>
            <div class="form-group"><label>Description</label><textarea name="project_description" id="edit_project_description" class="form-control" rows="3"></textarea></div>
            <div class="form-row">
                <div class="form-group"><label>Supervisor</label><select name="supervisor_id" id="edit_supervisor_id" class="form-control"><option value="">-- Select --</option><?php foreach ($supervisors_list as $sv): ?><option value="<?= $sv['fyp_supervisorid']; ?>"><?= htmlspecialchars($sv['fyp_name']); ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Max Students</label><input type="number" name="max_students" id="edit_max_students" class="form-control" min="1" max="10"></div>
            </div>
        </div><div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('editProjectModal')">Cancel</button>
            <button type="submit" name="update_project" class="btn btn-primary"><i class="fas fa-save"></i> Update</button>
        </div></form>
    </div>
</div>

<!-- Delete Project Modal -->
<div class="modal-overlay" id="deleteProjectModal">
    <div class="modal" style="max-width:450px;">
        <div class="modal-header"><h3><i class="fas fa-trash-alt" style="color:#f87171;"></i> Delete Project</h3><button class="modal-close" onclick="closeModal('deleteProjectModal')">&times;</button></div>
        <form method="POST"><input type="hidden" name="project_id" id="delete_project_id"><div class="modal-body">
            <div style="text-align:center;"><i class="fas fa-exclamation-triangle" style="font-size:3rem;color:#f87171;margin-bottom:15px;"></i><p>Delete project: <strong id="delete_project_title" style="color:#f87171;"></strong>?</p></div>
        </div><div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('deleteProjectModal')">Cancel</button>
            <button type="submit" name="delete_project" class="btn btn-danger"><i class="fas fa-trash"></i> Delete</button>
        </div></form>
    </div>
</div>
<?php
break;

// ==================== MODERATION ====================
case 'moderation':
    $moderations = [];
    $res = $conn->query("SELECT sm.*, s.fyp_studname, s.fyp_studfullid, mc.fyp_criterianame, mc.fyp_criteriadesc FROM student_moderation sm LEFT JOIN student s ON sm.fyp_studid = s.fyp_studid LEFT JOIN moderation_criteria mc ON sm.fyp_mdcriteriaid = mc.fyp_mdcriteriaid ORDER BY sm.fyp_studid");
    if ($res) { while ($row = $res->fetch_assoc()) { $moderations[] = $row; } }
?>
<div class="card">
    <div class="card-header"><h3>Student Moderation Records</h3></div>
    <div class="card-body">
        <?php if (empty($moderations)): ?>
            <div class="empty-state"><i class="fas fa-clipboard-check"></i><p>No moderation records found</p></div>
        <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Student ID</th><th>Name</th><th>Criteria</th><th>Description</th><th>Comply</th></tr></thead>
                <tbody>
                <?php foreach ($moderations as $m): ?>
                <tr>
                    <td><?= htmlspecialchars($m['fyp_studfullid'] ?? $m['fyp_studid']); ?></td>
                    <td><?= htmlspecialchars($m['fyp_studname'] ?? '-'); ?></td>
                    <td><?= htmlspecialchars($m['fyp_criterianame'] ?? '-'); ?></td>
                    <td><?= htmlspecialchars($m['fyp_criteriadesc'] ?? '-'); ?></td>
                    <td><?= $m['fyp_comply'] ? '<span class="badge badge-approved">Yes</span>' : '<span class="badge badge-pending">No</span>'; ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?php
break;

// ==================== REGISTER ====================
case 'register':
    $pending_regs = [];
    $res = $conn->query("SELECT pr.*, p.fyp_progname, ay.fyp_acdyear, ay.fyp_intake FROM pending_registration pr LEFT JOIN programme p ON pr.programme_id = p.fyp_progid LEFT JOIN academic_year ay ON pr.academic_year_id = ay.fyp_academicid ORDER BY pr.status = 'pending' DESC, pr.created_at DESC");
    if ($res) { while ($row = $res->fetch_assoc()) { $pending_regs[] = $row; } }
    $pending_only = array_filter($pending_regs, function($r) { return $r['status'] === 'pending'; });
?>
<div class="card">
    <div class="card-header"><h3>Student Registrations</h3><span class="badge badge-pending"><?= count($pending_only); ?> Pending</span></div>
    <div class="card-body">
        <?php if (empty($pending_regs)): ?>
            <div class="empty-state"><i class="fas fa-user-plus"></i><p>No registration requests found</p></div>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead><tr><th>ID</th><th>Student ID</th><th>Name</th><th>Email</th><th>Programme</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($pending_regs as $reg): ?>
                    <tr>
                        <td><?= $reg['id']; ?></td>
                        <td><?= htmlspecialchars($reg['student_id']); ?></td>
                        <td><?= htmlspecialchars($reg['full_name']); ?></td>
                        <td><?= htmlspecialchars($reg['email']); ?></td>
                        <td><?= htmlspecialchars($reg['fyp_progname'] ?? '-'); ?></td>
                        <td><span class="badge badge-<?= $reg['status'] === 'approved' ? 'approved' : ($reg['status'] === 'pending' ? 'pending' : 'rejected'); ?>"><?= ucfirst($reg['status']); ?></span></td>
                        <td>
                            <?php if ($reg['status'] === 'pending'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="registration_id" value="<?= $reg['id']; ?>">
                                <button type="submit" name="approve_registration" class="btn btn-success btn-sm"><i class="fas fa-check"></i></button>
                                <button type="submit" name="reject_registration" class="btn btn-danger btn-sm"><i class="fas fa-times"></i></button>
                            </form>
                            <?php else: ?>-<?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php
break;

// ==================== RUBRICS ====================
case 'rubrics':
    $rubrics_action = $_GET['action'] ?? 'sets';
    
    $sets = [];
    $res = $conn->query("SELECT s.*, a.fyp_acdyear, a.fyp_intake FROM `set` s LEFT JOIN academic_year a ON s.fyp_academicid = a.fyp_academicid ORDER BY s.fyp_setid DESC");
    if ($res) { while ($row = $res->fetch_assoc()) { $sets[] = $row; } }
    
    $items = [];
    $res = $conn->query("SELECT * FROM item ORDER BY fyp_itemid");
    if ($res) { while ($row = $res->fetch_assoc()) { $items[] = $row; } }
    
    $criteria = [];
    $res = $conn->query("SELECT * FROM assessment_criteria ORDER BY fyp_min ASC");
    if ($res) { while ($row = $res->fetch_assoc()) { $criteria[] = $row; } }
    
    $marking_criteria = [];
    $res = $conn->query("SELECT * FROM marking_criteria ORDER BY fyp_criteriaid");
    if ($res) { while ($row = $res->fetch_assoc()) { $marking_criteria[] = $row; } }
?>
<div class="card" style="margin-bottom:20px;">
    <div class="card-body" style="padding:15px;">
        <div class="tab-nav">
            <a href="?page=rubrics&action=sets" class="tab-btn <?= $rubrics_action === 'sets' ? 'active' : ''; ?>"><i class="fas fa-layer-group"></i> Sets</a>
            <a href="?page=rubrics&action=items" class="tab-btn <?= $rubrics_action === 'items' ? 'active' : ''; ?>"><i class="fas fa-list"></i> Items</a>
            <a href="?page=rubrics&action=criteria" class="tab-btn <?= $rubrics_action === 'criteria' ? 'active' : ''; ?>"><i class="fas fa-star"></i> Grade Criteria</a>
            <a href="?page=rubrics&action=marking" class="tab-btn <?= $rubrics_action === 'marking' ? 'active' : ''; ?>"><i class="fas fa-percent"></i> Marking Criteria</a>
        </div>
    </div>
</div>

<?php if ($rubrics_action === 'sets'): ?>
<div class="card">
    <div class="card-header"><h3>Assessment Sets</h3><button class="btn btn-primary" onclick="openModal('addSetModal')"><i class="fas fa-plus"></i> Add Set</button></div>
    <div class="card-body">
        <?php if (empty($sets)): ?><div class="empty-state"><i class="fas fa-layer-group"></i><p>No sets found</p></div>
        <?php else: ?>
            <table class="data-table"><thead><tr><th>ID</th><th>Set Name</th><th>Academic Year</th></tr></thead><tbody>
            <?php foreach ($sets as $s): ?><tr><td><?= $s['fyp_setid']; ?></td><td><?= htmlspecialchars($s['fyp_setname']); ?></td><td><?= htmlspecialchars(($s['fyp_acdyear'] ?? '') . ' ' . ($s['fyp_intake'] ?? '')); ?></td></tr><?php endforeach; ?>
            </tbody></table>
        <?php endif; ?>
    </div>
</div>
<?php elseif ($rubrics_action === 'items'): ?>
<div class="card">
    <div class="card-header"><h3>Assessment Items</h3><button class="btn btn-primary" onclick="openModal('addItemModal')"><i class="fas fa-plus"></i> Add Item</button></div>
    <div class="card-body">
        <?php if (empty($items)): ?><div class="empty-state"><i class="fas fa-list"></i><p>No items found</p></div>
        <?php else: ?>
            <table class="data-table"><thead><tr><th>ID</th><th>Item Name</th><th>Description</th></tr></thead><tbody>
            <?php foreach ($items as $i): ?><tr><td><?= $i['fyp_itemid']; ?></td><td><?= htmlspecialchars($i['fyp_itemname']); ?></td><td><?= htmlspecialchars($i['fyp_itemdesc'] ?? '-'); ?></td></tr><?php endforeach; ?>
            </tbody></table>
        <?php endif; ?>
    </div>
</div>
<?php elseif ($rubrics_action === 'criteria'): ?>
<div class="card">
    <div class="card-header"><h3>Grade Criteria</h3></div>
    <div class="card-body">
        <?php if (empty($criteria)): ?><div class="empty-state"><i class="fas fa-star"></i><p>No criteria found</p></div>
        <?php else: ?>
            <table class="data-table"><thead><tr><th>ID</th><th>Grade</th><th>Min %</th><th>Max %</th></tr></thead><tbody>
            <?php foreach ($criteria as $c): ?><tr><td><?= $c['fyp_assessmentid']; ?></td><td><span class="badge badge-approved"><?= htmlspecialchars($c['fyp_grade']); ?></span></td><td><?= $c['fyp_min']; ?>%</td><td><?= $c['fyp_max']; ?>%</td></tr><?php endforeach; ?>
            </tbody></table>
        <?php endif; ?>
    </div>
</div>
<?php elseif ($rubrics_action === 'marking'): ?>
<div class="card">
    <div class="card-header"><h3>Marking Criteria</h3><button class="btn btn-primary" onclick="openModal('addMarkingModal')"><i class="fas fa-plus"></i> Add Criteria</button></div>
    <div class="card-body">
        <?php if (empty($marking_criteria)): ?><div class="empty-state"><i class="fas fa-percent"></i><p>No criteria found</p></div>
        <?php else: ?>
            <table class="data-table"><thead><tr><th>ID</th><th>Criteria Name</th><th>Percentage</th></tr></thead><tbody>
            <?php foreach ($marking_criteria as $mc): ?><tr><td><?= $mc['fyp_criteriaid']; ?></td><td><?= htmlspecialchars($mc['fyp_criterianame']); ?></td><td><?= $mc['fyp_percentallocation']; ?>%</td></tr><?php endforeach; ?>
            </tbody></table>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Add Set Modal -->
<div class="modal-overlay" id="addSetModal">
    <div class="modal"><div class="modal-header"><h3>Add Assessment Set</h3><button class="modal-close" onclick="closeModal('addSetModal')">&times;</button></div>
        <form method="POST"><div class="modal-body">
            <div class="form-group"><label>Set Name</label><input type="text" name="setname" class="form-control" required></div>
            <div class="form-group"><label>Academic Year</label><select name="academic_id" class="form-control" required><?php foreach ($academic_years as $ay): ?><option value="<?= $ay['fyp_academicid']; ?>"><?= $ay['fyp_acdyear'] . ' ' . $ay['fyp_intake']; ?></option><?php endforeach; ?></select></div>
        </div><div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('addSetModal')">Cancel</button><button type="submit" name="add_set" class="btn btn-primary">Add Set</button></div></form>
    </div>
</div>

<!-- Add Item Modal -->
<div class="modal-overlay" id="addItemModal">
    <div class="modal"><div class="modal-header"><h3>Add Assessment Item</h3><button class="modal-close" onclick="closeModal('addItemModal')">&times;</button></div>
        <form method="POST"><div class="modal-body">
            <div class="form-group"><label>Item Name</label><input type="text" name="itemname" class="form-control" required></div>
            <div class="form-group"><label>Description</label><textarea name="itemdesc" class="form-control" rows="3"></textarea></div>
        </div><div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('addItemModal')">Cancel</button><button type="submit" name="add_item" class="btn btn-primary">Add Item</button></div></form>
    </div>
</div>

<!-- Add Marking Criteria Modal -->
<div class="modal-overlay" id="addMarkingModal">
    <div class="modal"><div class="modal-header"><h3>Add Marking Criteria</h3><button class="modal-close" onclick="closeModal('addMarkingModal')">&times;</button></div>
        <form method="POST"><div class="modal-body">
            <div class="form-group"><label>Criteria Name</label><input type="text" name="criterianame" class="form-control" required></div>
            <div class="form-group"><label>Percentage Allocation</label><input type="number" name="percentallocation" class="form-control" min="0" max="100" required></div>
        </div><div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('addMarkingModal')">Cancel</button><button type="submit" name="add_marking_criteria" class="btn btn-primary">Add Criteria</button></div></form>
    </div>
</div>
<?php
break;

// ==================== MARKS ====================
case 'marks':
    $students_marks = [];
    $res = $conn->query("SELECT s.fyp_studid, s.fyp_studname, s.fyp_studfullid, p.fyp_projecttitle, sup.fyp_name as supervisor_name, a.fyp_acdyear, a.fyp_intake, tm.fyp_totalmark, tm.fyp_grade FROM student s LEFT JOIN pairing pa ON s.fyp_studid = pa.fyp_studid LEFT JOIN project p ON pa.fyp_projectid = p.fyp_projectid LEFT JOIN supervisor sup ON pa.fyp_supervisorid = sup.fyp_supervisorid LEFT JOIN academic_year a ON s.fyp_academicid = a.fyp_academicid LEFT JOIN total_mark tm ON s.fyp_studid = tm.fyp_studid ORDER BY s.fyp_studname");
    if ($res) { while ($row = $res->fetch_assoc()) { $students_marks[] = $row; } }
?>
<div class="card">
    <div class="card-header"><h3><i class="fas fa-calculator"></i> Assessment Marks</h3></div>
    <div class="card-body">
        <div class="search-box"><input type="text" class="form-control" placeholder="Search students..." id="marksSearch" onkeyup="filterTable('marksSearch','marksTable')"></div>
        <?php if (empty($students_marks)): ?>
            <div class="empty-state"><i class="fas fa-calculator"></i><p>No students found</p></div>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="data-table" id="marksTable">
                    <thead><tr><th>Student ID</th><th>Name</th><th>Project</th><th>Supervisor</th><th>Academic Year</th><th>Total Mark</th><th>Grade</th></tr></thead>
                    <tbody>
                    <?php foreach ($students_marks as $sm): ?>
                    <tr>
                        <td><?= htmlspecialchars($sm['fyp_studfullid']); ?></td>
                        <td><?= htmlspecialchars($sm['fyp_studname']); ?></td>
                        <td><?= htmlspecialchars($sm['fyp_projecttitle'] ?? '-'); ?></td>
                        <td><?= htmlspecialchars($sm['supervisor_name'] ?? '-'); ?></td>
                        <td><?= htmlspecialchars(($sm['fyp_acdyear'] ?? '') . ' ' . ($sm['fyp_intake'] ?? '')); ?></td>
                        <td><?= $sm['fyp_totalmark'] ? '<strong style="color:#34d399;">' . number_format($sm['fyp_totalmark'], 2) . '%</strong>' : '<span style="color:#64748b;">Not marked</span>'; ?></td>
                        <td><?= $sm['fyp_grade'] ? '<span class="badge badge-approved">' . $sm['fyp_grade'] . '</span>' : '<span class="badge badge-pending">-</span>'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php
break;

// ==================== REPORT ====================
case 'report':
?>
<div class="card">
    <div class="card-header"><h3><i class="fas fa-file-alt"></i> Generate Reports</h3></div>
    <div class="card-body">
        <p style="margin-bottom:20px;color:#94a3b8;">Click on a report type to generate and download.</p>
        <div class="quick-actions">
            <a href="Coordinator_report.php" class="quick-action"><i class="fas fa-file-pdf"></i><h4>Full Report Generator</h4><p>Open advanced report page</p></a>
            <div class="quick-action"><i class="fas fa-users"></i><h4>Student List</h4><p>Export all students</p></div>
            <div class="quick-action"><i class="fas fa-user-tie"></i><h4>Supervisor List</h4><p>Export all supervisors</p></div>
            <div class="quick-action"><i class="fas fa-link"></i><h4>Pairing Report</h4><p>Student-supervisor assignments</p></div>
            <div class="quick-action"><i class="fas fa-chart-bar"></i><h4>Marks Report</h4><p>Assessment marks summary</p></div>
            <div class="quick-action"><i class="fas fa-folder"></i><h4>Project Report</h4><p>All projects overview</p></div>
        </div>
    </div>
</div>
<?php
break;

// ==================== ANNOUNCEMENT ====================
case 'announcement':
    $announcements = [];
    $res = $conn->query("SELECT * FROM announcement ORDER BY fyp_datecreated DESC LIMIT 50");
    if ($res) { while ($row = $res->fetch_assoc()) { $announcements[] = $row; } }
?>
<div class="card">
    <div class="card-header"><h3><i class="fas fa-bullhorn" style="color:#a78bfa;"></i> Announcements</h3><button class="btn btn-primary" onclick="openModal('createAnnModal')"><i class="fas fa-plus"></i> Create</button></div>
    <div class="card-body">
        <?php if (empty($announcements)): ?>
            <div class="empty-state"><i class="fas fa-bullhorn"></i><p>No announcements found</p></div>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead><tr><th>ID</th><th>Title</th><th>Content</th><th>Receiver</th><th>Date</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($announcements as $ann): ?>
                    <tr>
                        <td><?= $ann['fyp_announcementid']; ?></td>
                        <td><strong><?= htmlspecialchars($ann['fyp_title']); ?></strong></td>
                        <td><?= htmlspecialchars(substr($ann['fyp_content'], 0, 60)); ?>...</td>
                        <td><span class="badge badge-open"><?= ucfirst($ann['fyp_receiver']); ?></span></td>
                        <td><?= date('M j, Y', strtotime($ann['fyp_datecreated'])); ?></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="announcement_id" value="<?= $ann['fyp_announcementid']; ?>">
                                <button type="submit" name="delete_announcement" class="btn btn-danger btn-sm" onclick="return confirm('Delete this announcement?')"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create Announcement Modal -->
<div class="modal-overlay" id="createAnnModal">
    <div class="modal" style="max-width:600px;">
        <div class="modal-header"><h3><i class="fas fa-plus-circle" style="color:#34d399;"></i> Create Announcement</h3><button class="modal-close" onclick="closeModal('createAnnModal')">&times;</button></div>
        <form method="POST"><div class="modal-body">
            <div class="form-group"><label>Title *</label><input type="text" name="title" class="form-control" required></div>
            <div class="form-group"><label>Content *</label><textarea name="content" class="form-control" rows="5" required></textarea></div>
            <div class="form-group"><label>Send To *</label><select name="receiver" class="form-control" required><option value="all">Everyone</option><option value="student">Students Only</option><option value="supervisor">Supervisors Only</option></select></div>
        </div><div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('createAnnModal')">Cancel</button>
            <button type="submit" name="create_announcement" class="btn btn-success"><i class="fas fa-paper-plane"></i> Publish</button>
        </div></form>
    </div>
</div>
<?php
break;

// ==================== SETTINGS ====================
case 'settings':
    $maintenance = [];
    $res = $conn->query("SELECT * FROM fyp_maintenance ORDER BY fyp_category, fyp_subject");
    if ($res) { while ($row = $res->fetch_assoc()) { $maintenance[] = $row; } }
?>
<div class="card">
    <div class="card-header"><h3>System Settings</h3></div>
    <div class="card-body">
        <div class="quick-actions">
            <div class="quick-action"><i class="fas fa-calendar-alt"></i><h4>Academic Years</h4><p><?= count($academic_years); ?> years</p></div>
            <div class="quick-action"><i class="fas fa-graduation-cap"></i><h4>Programmes</h4><p><?= count($programmes); ?> programmes</p></div