<?php
/**
 * Coordinator Main Page - Handlers - FIXED VERSION
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

// ===================== STUDENT CRUD HANDLERS =====================

// --- Edit Student ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_student'])) {
    $studid = $_POST['studid'];
    $studname = trim($_POST['studname']);
    $email = trim($_POST['email']);
    $contactno = trim($_POST['contactno']);
    $progid = intval($_POST['progid']);
    $group_type = $_POST['group_type'];
    
    $stmt = $conn->prepare("UPDATE student SET fyp_studname = ?, fyp_email = ?, fyp_contactno = ?, fyp_progid = ?, fyp_group = ? WHERE fyp_studid = ?");
    if ($stmt) {
        $stmt->bind_param("sssiss", $studname, $email, $contactno, $progid, $group_type, $studid);
        if ($stmt->execute()) {
            $message = "Student updated successfully!";
            $message_type = 'success';
        } else {
            $message = "Error updating student.";
            $message_type = 'error';
        }
        $stmt->close();
    }
}

// --- Delete Student ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_student'])) {
    $studid = $_POST['studid'];
    
    // Check if student has pairings
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM pairing WHERE fyp_studid = ?");
    $stmt->bind_param("s", $studid);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($result['cnt'] > 0) {
        $message = "Cannot delete student - they have active pairings!";
        $message_type = 'error';
    } else {
        $stmt = $conn->prepare("DELETE FROM student WHERE fyp_studid = ?");
        $stmt->bind_param("s", $studid);
        if ($stmt->execute()) {
            $message = "Student deleted successfully!";
            $message_type = 'success';
        } else {
            $message = "Error deleting student.";
            $message_type = 'error';
        }
        $stmt->close();
    }
}

// --- Add Student Manually ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student_manual'])) {
    $email = trim($_POST['new_email']);
    $studname = trim($_POST['new_studname']);
    $studfullid = trim($_POST['new_studfullid']);
    
    // Check if email or ID already exists
    $stmt = $conn->prepare("SELECT id FROM pending_registration WHERE email = ? OR studfullid = ?");
    $stmt->bind_param("ss", $email, $studfullid);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $message = "A registration with this email or student ID already exists!";
        $message_type = 'error';
    } else {
        $stmt->close();
        
        $studid = strtoupper(substr(str_replace(['@', '.'], '', $email), 0, 10)) . rand(100, 999);
        
        $stmt = $conn->prepare("INSERT INTO pending_registration (email, studid, studfullid, studname, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
        $stmt->bind_param("ssss", $email, $studid, $studfullid, $studname);
        
        if ($stmt->execute()) {
            $message = "Student added to pending registrations!";
            $message_type = 'success';
        } else {
            $message = "Error adding student.";
            $message_type = 'error';
        }
    }
    $stmt->close();
}

// ===================== REGISTRATION HANDLERS =====================

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

// --- Bulk Approve ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_approve'])) {
    $selected_ids = $_POST['selected_ids'] ?? [];
    $success_count = 0;
    
    foreach ($selected_ids as $reg_id) {
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
                
                $success_count++;
            } catch (Exception $e) {
                // Continue with next
            }
        }
    }
    
    $message = "$success_count student(s) approved successfully!";
    $message_type = 'success';
}

// --- Bulk Reject ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_reject'])) {
    $selected_ids = $_POST['selected_ids'] ?? [];
    $success_count = 0;
    
    foreach ($selected_ids as $reg_id) {
        $stmt = $conn->prepare("UPDATE pending_registration SET status = 'rejected', processed_at = NOW(), processed_by = ? WHERE id = ?");
        $stmt->bind_param("ii", $auth_user_id, $reg_id);
        if ($stmt->execute()) $success_count++;
        $stmt->close();
    }
    
    $message = "$success_count registration(s) rejected.";
    $message_type = 'success';
}

// ===================== GROUP REQUEST HANDLERS =====================

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

// ===================== PAIRING HANDLERS =====================

// --- Create Pairing ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_pairing'])) {
    $supervisor_id = intval($_POST['supervisor_id']);
    $project_id = intval($_POST['project_id']);
    $moderator_id = !empty($_POST['moderator_id']) ? intval($_POST['moderator_id']) : null;
    $academic_id = intval($_POST['academic_id']);
    $pairing_type = $_POST['pairing_type'];
    
    $stmt = $conn->prepare("INSERT INTO pairing (fyp_supervisorid, fyp_projectid, fyp_moderatorid, fyp_academicid, fyp_type) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiis", $supervisor_id, $project_id, $moderator_id, $academic_id, $pairing_type);
    
    if ($stmt->execute()) {
        $message = "Pairing created successfully!";
        $message_type = 'success';
    } else {
        $message = "Error creating pairing.";
        $message_type = 'error';
    }
    $stmt->close();
}

// --- Delete Pairing ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_pairing'])) {
    $pairing_id = intval($_POST['pairing_id']);
    
    $stmt = $conn->prepare("DELETE FROM pairing WHERE fyp_pairingid = ?");
    $stmt->bind_param("i", $pairing_id);
    
    if ($stmt->execute()) {
        $message = "Pairing deleted successfully!";
        $message_type = 'success';
    } else {
        $message = "Error deleting pairing.";
        $message_type = 'error';
    }
    $stmt->close();
}

// ===================== PROJECT CRUD HANDLERS =====================

// --- Toggle Project Status ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_project_status'])) {
    $project_id = intval($_POST['project_id']);
    $new_status = $_POST['new_status'];
    
    $valid_statuses = ['Available', 'Unavailable', 'Open', 'Taken', 'FIST'];
    
    if (in_array($new_status, $valid_statuses) || !empty($new_status)) {
        $new_status_escaped = $conn->real_escape_string($new_status);
        $query = "UPDATE project SET fyp_projectstatus = '$new_status_escaped' WHERE fyp_projectid = $project_id";
        
        if ($conn->query($query)) {
            if ($conn->affected_rows > 0) {
                $message = "Project status updated to <strong>" . htmlspecialchars($new_status) . "</strong>";
                $message_type = 'success';
            } else {
                $check = $conn->query("SELECT fyp_projectid, fyp_projectstatus FROM project WHERE fyp_projectid = $project_id");
                if ($check && $check->num_rows > 0) {
                    $row = $check->fetch_assoc();
                    $message = "Project status is already <strong>" . htmlspecialchars($row['fyp_projectstatus']) . "</strong>";
                    $message_type = 'info';
                } else {
                    $message = "Project not found (ID: $project_id)";
                    $message_type = 'error';
                }
            }
        } else {
            $message = "Error updating status: " . $conn->error;
            $message_type = 'error';
        }
    } else {
        $message = "Invalid status: " . htmlspecialchars($new_status);
        $message_type = 'error';
    }
}

// --- Add New Project ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_project'])) {
    $title = trim($_POST['project_title']);
    $description = trim($_POST['project_description'] ?? '');
    $type = $_POST['project_type'] ?? 'Application';
    $status = 'Open';
    $category = $_POST['project_category'] ?? '';
    $supervisor_id = !empty($_POST['supervisor_id']) ? $_POST['supervisor_id'] : null;
    $max_students = intval($_POST['max_students'] ?? 2);
    
    $stmt = $conn->prepare("SELECT fyp_projectid FROM project WHERE fyp_projecttitle = ?");
    if ($stmt) {
        $stmt->bind_param("s", $title);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $message = "A project with this title already exists!";
            $message_type = 'error';
        } else {
            $stmt->close();
            
            $stmt = $conn->prepare("INSERT INTO project (fyp_projecttitle, fyp_description, fyp_projectcat, fyp_projecttype, fyp_projectstatus, fyp_supervisorid, fyp_datecreated) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            
            if ($stmt) {
                $stmt->bind_param("sssssi", $title, $description, $category, $type, $status, $supervisor_id);
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
    
    $stmt = $conn->prepare("SELECT fyp_projectid FROM project WHERE fyp_projecttitle = ? AND fyp_projectid != ?");
    if ($stmt) {
        $stmt->bind_param("si", $title, $project_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $message = "Another project with this title already exists!";
            $message_type = 'error';
        } else {
            $stmt->close();
            
            $stmt = $conn->prepare("UPDATE project SET fyp_projecttitle = ?, fyp_description = ?, fyp_projectcat = ?, fyp_projecttype = ?, fyp_projectstatus = ?, fyp_supervisorid = ? WHERE fyp_projectid = ?");
            
            if ($stmt) {
                $stmt->bind_param("sssssii", $title, $description, $category, $type, $status, $supervisor_id, $project_id);
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

// ===================== ANNOUNCEMENT HANDLERS =====================

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
    $result = $conn->query("DELETE FROM announcement WHERE fyp_annouceid = $ann_id");
    if ($conn->affected_rows > 0) {
        $message = "Announcement deleted successfully!";
        $message_type = 'success';
    } else {
        $message = "Error deleting announcement.";
        $message_type = 'error';
    }
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