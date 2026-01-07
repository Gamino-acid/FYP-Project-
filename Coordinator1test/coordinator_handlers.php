<?php
/**
 * Coordinator Main Page - Handlers
 * Registration Handlers, Excel Import, Assessment Marks, Project CRUD
 */

// Only start session if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
if (!isset($conn)) {
    $conn = new mysqli("localhost", "root", "", "fyp_management");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
}

// Get user info from session
$auth_user_id = $_SESSION['user_id'] ?? 1;
$user_name = $_SESSION['user_name'] ?? 'Coordinator';
$user_email = $_SESSION['user_email'] ?? 'coordinator@staff.edu.my';

// Initialize message variables
$message = '';
$message_type = '';

// Get current page
$current_page = $_GET['page'] ?? 'dashboard';

// --- Save Student Assessment Marks ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_student_assessment'])) {
    $assess_student_id = $_POST['assess_student_id'];
    $initial_work = $_POST['initial_work'] ?? [];
    $final_work = $_POST['final_work'] ?? [];
    $moderator_mark = $_POST['moderator_mark'] ?? [];
    $scaled_mark = $_POST['scaled_mark'] ?? [];
    
    $success_count = 0;
    $error_count = 0;
    
    foreach ($initial_work as $criteria_id => $initial) {
        $initial = floatval($initial);
        $final = floatval($final_work[$criteria_id] ?? 0);
        $mod_mark = floatval($moderator_mark[$criteria_id] ?? 0);
        $scaled = floatval($scaled_mark[$criteria_id] ?? 0);
        
        $values = array_filter([$initial, $final, $mod_mark], function($v) { return $v > 0; });
        $avg_mark = count($values) > 0 ? array_sum($values) / count($values) : 0;
        
        $stmt = $conn->prepare("SELECT fyp_criteriamarkid FROM criteria_mark WHERE fyp_studid = ? AND fyp_criteriaid = ?");
        $stmt->bind_param("si", $assess_student_id, $criteria_id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($existing) {
            $stmt = $conn->prepare("UPDATE criteria_mark SET fyp_initialwork = ?, fyp_finalwork = ?, fyp_markbymoderator = ?, fyp_avgmark = ?, fyp_scaledmark = ? WHERE fyp_criteriamarkid = ?");
            $stmt->bind_param("dddddi", $initial, $final, $mod_mark, $avg_mark, $scaled, $existing['fyp_criteriamarkid']);
        } else {
            $stmt = $conn->prepare("INSERT INTO criteria_mark (fyp_studid, fyp_criteriaid, fyp_initialwork, fyp_finalwork, fyp_markbymoderator, fyp_avgmark, fyp_scaledmark) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("siddddd", $assess_student_id, $criteria_id, $initial, $final, $mod_mark, $avg_mark, $scaled);
        }
        
        if ($stmt->execute()) { $success_count++; } else { $error_count++; }
        $stmt->close();
    }
    
    $total_scaled = 0;
    $res = $conn->query("SELECT SUM(fyp_scaledmark) as total FROM criteria_mark WHERE fyp_studid = '$assess_student_id'");
    if ($res) { $row = $res->fetch_assoc(); $total_scaled = floatval($row['total'] ?? 0); }
    
    $stmt = $conn->prepare("SELECT fyp_studid FROM total_mark WHERE fyp_studid = ?");
    $stmt->bind_param("s", $assess_student_id);
    $stmt->execute();
    $existing_total = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($existing_total) {
        $stmt = $conn->prepare("UPDATE total_mark SET fyp_totalmark = ? WHERE fyp_studid = ?");
        $stmt->bind_param("ds", $total_scaled, $assess_student_id);
    } else {
        $res = $conn->query("SELECT fyp_projectid FROM pairing WHERE fyp_studid = '$assess_student_id'");
        $project_id = $res ? ($res->fetch_assoc()['fyp_projectid'] ?? null) : null;
        $stmt = $conn->prepare("INSERT INTO total_mark (fyp_studid, fyp_projectid, fyp_totalmark) VALUES (?, ?, ?)");
        $stmt->bind_param("sid", $assess_student_id, $project_id, $total_scaled);
    }
    $stmt->execute();
    $stmt->close();
    
    if ($success_count > 0) { $message = "Marks saved successfully! ($success_count criteria updated)"; $message_type = 'success'; }
    else { $message = "Error saving marks."; $message_type = 'error'; }
}

// --- Create Announcement ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_announcement'])) {
    $subject = $_POST['subject'];
    $description = $_POST['description'];
    $receiver = $_POST['receiver'];
    
    $stmt = $conn->prepare("INSERT INTO announcement (fyp_supervisorid, fyp_subject, fyp_description, fyp_receiver, fyp_datecreated) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssss", $user_name, $subject, $description, $receiver);
    if ($stmt->execute()) { $message = "Announcement created successfully!"; $message_type = 'success'; }
    $stmt->close();
}

// --- Delete/Unsend Announcement ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_announcement'])) {
    $ann_id = intval($_POST['announcement_id']);
    $deleted = false;
    $column_names = ['fyp_announcementid', 'announcementid', 'id'];
    
    foreach ($column_names as $col) {
        $stmt = $conn->prepare("DELETE FROM announcement WHERE $col = ?");
        if ($stmt) {
            $stmt->bind_param("i", $ann_id);
            if ($stmt->execute() && $stmt->affected_rows > 0) { $deleted = true; $stmt->close(); break; }
            $stmt->close();
        }
    }
    
    if ($deleted) { $message = "Announcement unsent/deleted successfully!"; $message_type = 'success'; }
    else { $message = "Error deleting announcement."; $message_type = 'error'; }
}

// --- Update Group Request Status ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_request_status'])) {
    $request_id = intval($_POST['request_id']);
    $new_status = $_POST['new_status'];
    
    $valid_statuses = ['Pending', 'Accepted', 'Rejected'];
    if (in_array($new_status, $valid_statuses)) {
        $stmt = $conn->prepare("UPDATE group_request SET request_status = ? WHERE request_id = ?");
        if ($stmt) {
            $stmt->bind_param("si", $new_status, $request_id);
            if ($stmt->execute()) {
                $message = "Group request status updated to: <strong>$new_status</strong>";
                $message_type = 'success';
            } else {
                $message = "Error updating group request status.";
                $message_type = 'error';
            }
            $stmt->close();
        }
    } else {
        $message = "Invalid status selected.";
        $message_type = 'error';
    }
}

// --- Approve Student Registration ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_registration'])) {
    $reg_id = $_POST['reg_id'];
    $stmt = $conn->prepare("SELECT * FROM pending_registration WHERE id = ? AND status = 'pending'");
    $stmt->bind_param("i", $reg_id);
    $stmt->execute();
    $reg = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($reg) {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        $generated_password = '';
        for ($i = 0; $i < 8; $i++) { $generated_password .= $chars[random_int(0, strlen($chars) - 1)]; }
        $password_hash = password_hash($generated_password, PASSWORD_DEFAULT);
        
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO user (fyp_username, fyp_passwordhash, fyp_usertype, fyp_datecreated) VALUES (?, ?, 'student', NOW())");
            $stmt->bind_param("ss", $reg['email'], $password_hash);
            $stmt->execute();
            $new_user_id = $stmt->insert_id;
            $stmt->close();
            
            $stmt = $conn->prepare("INSERT INTO student (fyp_studid, fyp_studfullid, fyp_studname, fyp_email, fyp_userid) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssi", $reg['studid'], $reg['studfullid'], $reg['studname'], $reg['email'], $new_user_id);
            $stmt->execute();
            $stmt->close();
            
            $stmt = $conn->prepare("UPDATE pending_registration SET status = 'approved', processed_at = NOW(), processed_by = ? WHERE id = ?");
            $stmt->bind_param("ii", $auth_user_id, $reg_id);
            $stmt->execute();
            $stmt->close();
            
            $conn->commit();
            
            $message = "Student <strong>" . htmlspecialchars($reg['studname']) . "</strong> approved successfully!";
            $message_type = 'success';
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// --- Reject Student Registration ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_registration'])) {
    $reg_id = $_POST['reg_id'];
    $stmt = $conn->prepare("UPDATE pending_registration SET status = 'rejected', processed_at = NOW(), processed_by = ? WHERE id = ?");
    $stmt->bind_param("ii", $auth_user_id, $reg_id);
    if ($stmt->execute()) {
        $message = "Registration rejected.";
        $message_type = 'success';
    }
    $stmt->close();
}

// ===================== PROJECT CRUD HANDLERS =====================

// --- Toggle Project Status ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_project_status'])) {
    $project_id = intval($_POST['project_id']);
    $new_status = $_POST['new_status'];
    
    if (in_array($new_status, ['Available', 'Unavailable'])) {
        $stmt = $conn->prepare("UPDATE project SET fyp_projectstatus = ? WHERE fyp_projectid = ?");
        $stmt->bind_param("si", $new_status, $project_id);
        if ($stmt->execute()) {
            $message = "Project status updated to <strong>" . htmlspecialchars($new_status) . "</strong>";
            $message_type = 'success';
        } else {
            $message = "Error updating status: " . $conn->error;
            $message_type = 'error';
        }
        $stmt->close();
    }
}

// --- Add New Project ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_project'])) {
    $title = trim($_POST['project_title']);
    $description = trim($_POST['project_description'] ?? '');
    $type = $_POST['project_type'] ?? 'Application';
    $status = 'Available';
    $category = $_POST['project_category'] ?? '';
    $supervisor_id = !empty($_POST['supervisor_id']) ? $_POST['supervisor_id'] : null;
    $max_students = intval($_POST['max_students'] ?? 2);
    
    $stmt = $conn->prepare("SELECT fyp_projectid FROM project WHERE fyp_projecttitle = ?");
    if (!$stmt) {
        $message = "Database error: " . $conn->error;
        $message_type = 'error';
    } else {
        $stmt->bind_param("s", $title);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $message = "A project with this title already exists!";
            $message_type = 'error';
        } else {
            $stmt->close();
            
            $stmt = $conn->prepare("INSERT INTO project (fyp_projecttitle, fyp_projectdesc, fyp_projectcat, fyp_projecttype, fyp_projectstatus, fyp_supervisorid, fyp_maxstudent, fyp_datecreated) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            
            if ($stmt) {
                $stmt->bind_param("ssssssi", $title, $description, $category, $type, $status, $supervisor_id, $max_students);
                if ($stmt->execute()) {
                    $message = "Project <strong>" . htmlspecialchars($title) . "</strong> added successfully!";
                    $message_type = 'success';
                } else {
                    $message = "Error adding project: " . $conn->error;
                    $message_type = 'error';
                }
                $stmt->close();
            }
        }
    }
}

// --- Update Project ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_project'])) {
    $project_id = $_POST['project_id'];
    $title = trim($_POST['project_title']);
    $description = trim($_POST['project_description'] ?? '');
    $type = $_POST['project_type'];
    $status = $_POST['project_status'];
    $category = $_POST['project_category'] ?? '';
    $supervisor_id = !empty($_POST['supervisor_id']) ? $_POST['supervisor_id'] : null;
    $max_students = intval($_POST['max_students'] ?? 2);
    
    $stmt = $conn->prepare("SELECT fyp_projectid FROM project WHERE fyp_projecttitle = ? AND fyp_projectid != ?");
    if (!$stmt) {
        $message = "Database error: " . $conn->error;
        $message_type = 'error';
    } else {
        $stmt->bind_param("si", $title, $project_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $message = "Another project with this title already exists!";
            $message_type = 'error';
        } else {
            $stmt->close();
            
            $stmt = $conn->prepare("UPDATE project SET fyp_projecttitle = ?, fyp_projectdesc = ?, fyp_projectcat = ?, fyp_projecttype = ?, fyp_projectstatus = ?, fyp_supervisorid = ?, fyp_maxstudent = ? WHERE fyp_projectid = ?");
            
            if ($stmt) {
                $stmt->bind_param("ssssssii", $title, $description, $category, $type, $status, $supervisor_id, $max_students, $project_id);
                if ($stmt->execute()) {
                    $message = "Project updated successfully!";
                    $message_type = 'success';
                } else {
                    $message = "Error updating project: " . $conn->error;
                    $message_type = 'error';
                }
                $stmt->close();
            }
        }
    }
}

// --- Delete Project ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_project'])) {
    $project_id = $_POST['project_id'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM pairing WHERE fyp_projectid = ?");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result['cnt'] > 0) {
        $message = "Cannot delete this project! It is assigned to " . $result['cnt'] . " student(s).";
        $message_type = 'error';
    } else {
        $stmt->close();
        $stmt = $conn->prepare("DELETE FROM project WHERE fyp_projectid = ?");
        $stmt->bind_param("i", $project_id);
        if ($stmt->execute()) {
            $message = "Project deleted successfully!";
            $message_type = 'success';
        } else {
            $message = "Error deleting project.";
            $message_type = 'error';
        }
    }
    $stmt->close();
}

// ===================== FETCH DATA FOR PAGES =====================

// Fetch pending registrations count
$pending_registrations = 0;
$result = $conn->query("SELECT COUNT(*) as cnt FROM pending_registration WHERE status = 'pending'");
if ($result) { $pending_registrations = $result->fetch_assoc()['cnt']; }

// Fetch pending group requests count
$pending_requests = 0;
$result = $conn->query("SELECT COUNT(*) as cnt FROM group_request WHERE request_status = 'Pending'");
if ($result) { $pending_requests = $result->fetch_assoc()['cnt']; }

// Fetch dashboard counts
$total_students = 0;
$result = $conn->query("SELECT COUNT(*) as cnt FROM student");
if ($result) { $total_students = $result->fetch_assoc()['cnt']; }

$total_supervisors = 0;
$result = $conn->query("SELECT COUNT(*) as cnt FROM supervisor");
if ($result) { $total_supervisors = $result->fetch_assoc()['cnt']; }

$total_projects = 0;
$result = $conn->query("SELECT COUNT(*) as cnt FROM project");
if ($result) { $total_projects = $result->fetch_assoc()['cnt']; }

$total_pairings = 0;
$result = $conn->query("SELECT COUNT(*) as cnt FROM pairing");
if ($result) { $total_pairings = $result->fetch_assoc()['cnt']; }

// Fetch recently registered students (approved in last 30 days)
$recent_registered_students = [];
$result = $conn->query("
    SELECT s.fyp_studid, s.fyp_studfullid, s.fyp_studname, s.fyp_email, 
           pr.processed_at as registered_date, p.fyp_progname, pr.fyp_type
    FROM student s
    LEFT JOIN pending_registration pr ON s.fyp_email = pr.email
    LEFT JOIN programme p ON s.fyp_progid = p.fyp_progid
    WHERE pr.status = 'approved' 
    AND pr.processed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ORDER BY pr.processed_at DESC
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recent_registered_students[] = $row;
    }
}

// Profile image
$img_src = "https://ui-avatars.com/api/?name=" . urlencode($user_name) . "&background=8b5cf6&color=fff&size=80";

// Include the layout file
include("Coordinator_layout.php");
?>