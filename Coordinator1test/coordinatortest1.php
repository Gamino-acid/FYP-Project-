<?php
session_start();
include("../db_connect.php");

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'coordinator') {
    header("Location: ../Login.php");
    exit;
}

$auth_user_id = $_SESSION['user_id'];
$user_name = $_SESSION['username'];
$current_page = $_GET['page'] ?? 'dashboard';
$action = $_GET['action'] ?? '';

$img_src = "https://ui-avatars.com/api/?name=" . urlencode($user_name) . "&background=7C3AED&color=fff";

// Get counts with error handling
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

// Get academic years
$academic_years = [];
$res = $conn->query("SELECT * FROM academic_year ORDER BY fyp_academicid DESC");
if ($res) { while ($row = $res->fetch_assoc()) { $academic_years[] = $row; } }

// Get programmes
$programmes = [];
$res = $conn->query("SELECT * FROM programme ORDER BY fyp_progname");
if ($res) { while ($row = $res->fetch_assoc()) { $programmes[] = $row; } }

$message = '';
$message_type = '';

// ===================== HANDLE POST ACTIONS =====================

// --- Update Group Request Status ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_request_status'])) {
    $request_id = $_POST['request_id'];
    $new_status = $_POST['new_status'];
    $stmt = $conn->prepare("UPDATE group_request SET request_status = ? WHERE request_id = ?");
    $stmt->bind_param("si", $new_status, $request_id);
    if ($stmt->execute()) {
        $message = "Group request status updated to: $new_status";
        $message_type = 'success';
    } else {
        $message = "Error updating request status.";
        $message_type = 'error';
    }
    $stmt->close();
}

// --- Edit Student Info ---
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
    } else {
        $message = "Error updating student information.";
        $message_type = 'error';
    }
    $stmt->close();
}

// --- Delete Student ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_student'])) {
    $studid = $_POST['studid'];
    $stmt = $conn->prepare("SELECT fyp_userid FROM student WHERE fyp_studid = ?");
    $stmt->bind_param("s", $studid);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();
    
    if ($student) {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("DELETE FROM student WHERE fyp_studid = ?");
            $stmt->bind_param("s", $studid);
            $stmt->execute();
            $stmt->close();
            
            $conn->commit();
            $message = "Student deleted successfully!";
            $message_type = 'success';
            $total_students--;
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error deleting student: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// --- Create Pairing ---
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
    } else {
        $message = "Error creating pairing.";
        $message_type = 'error';
    }
    $stmt->close();
}

// ===================== RUBRICS ASSESSMENT HANDLERS =====================

// --- Create/Update Assessment Set ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_set'])) {
    $set_id = $_POST['set_id'] ?? '';
    $project_phase = intval($_POST['project_phase'] ?? 1);
    $academic_id = !empty($_POST['academic_id']) ? intval($_POST['academic_id']) : null;
    
    if (empty($academic_id)) {
        $message = "Please select an academic year.";
        $message_type = 'error';
    } else {
        if (!empty($set_id)) {
            $stmt = $conn->prepare("UPDATE `set` SET fyp_projectphase = ?, fyp_academicid = ? WHERE fyp_setid = ?");
            $stmt->bind_param("iii", $project_phase, $academic_id, $set_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO `set` (fyp_projectphase, fyp_academicid) VALUES (?, ?)");
            $stmt->bind_param("ii", $project_phase, $academic_id);
        }
        
        if ($stmt->execute()) {
            $message = empty($set_id) ? "Assessment set created successfully!" : "Assessment set updated successfully!";
            $message_type = 'success';
        } else {
            $message = "Error: " . $conn->error;
            $message_type = 'error';
        }
        $stmt->close();
    }
}

// --- Delete Assessment Set ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_set'])) {
    $set_id = intval($_POST['set_id']);
    $stmt = $conn->prepare("DELETE FROM `set` WHERE fyp_setid = ?");
    $stmt->bind_param("i", $set_id);
    if ($stmt->execute()) {
        $message = "Assessment set deleted successfully!";
        $message_type = 'success';
    } else {
        $message = "Error deleting set: " . $conn->error;
        $message_type = 'error';
    }
    $stmt->close();
}

// --- Create/Update Assessment Item ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_item'])) {
    $item_id = $_POST['item_id'] ?? '';
    $item_name = trim($_POST['item_name']);
    $item_mark = floatval($_POST['item_mark'] ?? 0);
    $item_doc = intval($_POST['item_doc'] ?? 1);
    $item_moderate = intval($_POST['item_moderate'] ?? 0);
    $item_start = !empty($_POST['item_start']) ? $_POST['item_start'] : null;
    $item_deadline = !empty($_POST['item_deadline']) ? $_POST['item_deadline'] : null;
    
    if (empty($item_name)) {
        $message = "Item name is required.";
        $message_type = 'error';
    } else {
        if (!empty($item_id)) {
            $stmt = $conn->prepare("UPDATE item SET fyp_itemname = ?, fyp_originalmarkallocation = ?, fyp_isdocument = ?, fyp_ismoderation = ?, fyp_startdate = ?, fyp_finaldeadline = ? WHERE fyp_itemid = ?");
            $stmt->bind_param("sdiissi", $item_name, $item_mark, $item_doc, $item_moderate, $item_start, $item_deadline, $item_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO item (fyp_itemname, fyp_originalmarkallocation, fyp_isdocument, fyp_ismoderation, fyp_startdate, fyp_finaldeadline) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sdiiss", $item_name, $item_mark, $item_doc, $item_moderate, $item_start, $item_deadline);
        }
        
        if ($stmt->execute()) {
            $message = empty($item_id) ? "Assessment item created successfully!" : "Assessment item updated successfully!";
            $message_type = 'success';
        } else {
            $message = "Error: " . $conn->error;
            $message_type = 'error';
        }
        $stmt->close();
    }
}

// --- Delete Assessment Item ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_item'])) {
    $item_id = intval($_POST['item_id']);
    $stmt = $conn->prepare("DELETE FROM item WHERE fyp_itemid = ?");
    $stmt->bind_param("i", $item_id);
    if ($stmt->execute()) {
        $message = "Assessment item deleted successfully!";
        $message_type = 'success';
    } else {
        $message = "Error deleting item: " . $conn->error;
        $message_type = 'error';
    }
    $stmt->close();
}

// --- Create/Update Assessment Criteria ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_criteria'])) {
    $criteria_id = $_POST['criteria_id'] ?? '';
    $crit_name = trim($_POST['crit_name']);
    $crit_min = floatval($_POST['crit_min'] ?? 0);
    $crit_max = floatval($_POST['crit_max'] ?? 10);
    $crit_desc = trim($_POST['crit_desc'] ?? '');
    
    if (empty($crit_name)) {
        $message = "Criteria name is required.";
        $message_type = 'error';
    } else {
        if (!empty($criteria_id)) {
            $stmt = $conn->prepare("UPDATE assessment_criteria SET fyp_assessmentcriterianame = ?, fyp_min = ?, fyp_max = ?, fyp_description = ? WHERE fyp_assessmentcriteriaid = ?");
            $stmt->bind_param("sddsi", $crit_name, $crit_min, $crit_max, $crit_desc, $criteria_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO assessment_criteria (fyp_assessmentcriterianame, fyp_min, fyp_max, fyp_description) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sdds", $crit_name, $crit_min, $crit_max, $crit_desc);
        }
        
        if ($stmt->execute()) {
            $message = empty($criteria_id) ? "Assessment criteria created successfully!" : "Assessment criteria updated successfully!";
            $message_type = 'success';
        } else {
            $message = "Error: " . $conn->error;
            $message_type = 'error';
        }
        $stmt->close();
    }
}

// --- Delete Assessment Criteria ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_criteria'])) {
    $criteria_id = intval($_POST['criteria_id']);
    $stmt = $conn->prepare("DELETE FROM assessment_criteria WHERE fyp_assessmentcriteriaid = ?");
    $stmt->bind_param("i", $criteria_id);
    if ($stmt->execute()) {
        $message = "Assessment criteria deleted successfully!";
        $message_type = 'success';
    } else {
        $message = "Error deleting criteria: " . $conn->error;
        $message_type = 'error';
    }
    $stmt->close();
}

// --- Create/Update Marking Criteria ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_marking'])) {
    $marking_id = $_POST['marking_id'] ?? '';
    $marking_name = trim($_POST['marking_name']);
    $marking_percent = floatval($_POST['marking_percent'] ?? 0);
    
    if (empty($marking_name)) {
        $message = "Marking criteria name is required.";
        $message_type = 'error';
    } else {
        if (!empty($marking_id)) {
            $stmt = $conn->prepare("UPDATE marking_criteria SET fyp_criterianame = ?, fyp_percentallocation = ? WHERE fyp_criteriaid = ?");
            $stmt->bind_param("sdi", $marking_name, $marking_percent, $marking_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO marking_criteria (fyp_criterianame, fyp_percentallocation) VALUES (?, ?)");
            $stmt->bind_param("sd", $marking_name, $marking_percent);
        }
        
        if ($stmt->execute()) {
            $message = empty($marking_id) ? "Marking criteria created successfully!" : "Marking criteria updated successfully!";
            $message_type = 'success';
        } else {
            $message = "Error: " . $conn->error;
            $message_type = 'error';
        }
        $stmt->close();
    }
}

// --- Delete Marking Criteria ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_marking'])) {
    $marking_id = intval($_POST['marking_id']);
    $stmt = $conn->prepare("DELETE FROM marking_criteria WHERE fyp_criteriaid = ?");
    $stmt->bind_param("i", $marking_id);
    if ($stmt->execute()) {
        $message = "Marking criteria deleted successfully!";
        $message_type = 'success';
    } else {
        $message = "Error deleting marking criteria: " . $conn->error;
        $message_type = 'error';
    }
    $stmt->close();
}

// --- Link Item to Marking Criteria ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['link_item_criteria'])) {
    $link_itemid = intval($_POST['link_itemid']);
    $link_criteriaid = intval($_POST['link_criteriaid']);
    
    $stmt = $conn->prepare("SELECT * FROM item_marking_criteria WHERE fyp_itemid = ? AND fyp_criteriaid = ?");
    $stmt->bind_param("ii", $link_itemid, $link_criteriaid);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $message = "This link already exists!";
        $message_type = 'warning';
    } else {
        $stmt->close();
        $stmt = $conn->prepare("INSERT INTO item_marking_criteria (fyp_itemid, fyp_criteriaid) VALUES (?, ?)");
        $stmt->bind_param("ii", $link_itemid, $link_criteriaid);
        if ($stmt->execute()) {
            $message = "Item linked to marking criteria successfully!";
            $message_type = 'success';
        } else {
            $message = "Error: " . $conn->error;
            $message_type = 'error';
        }
    }
    $stmt->close();
}

// --- Unlink Item from Marking Criteria ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unlink_item_criteria'])) {
    $unlink_itemid = intval($_POST['unlink_itemid']);
    $unlink_criteriaid = intval($_POST['unlink_criteriaid']);
    
    $stmt = $conn->prepare("DELETE FROM item_marking_criteria WHERE fyp_itemid = ? AND fyp_criteriaid = ?");
    $stmt->bind_param("ii", $unlink_itemid, $unlink_criteriaid);
    if ($stmt->execute()) {
        $message = "Link removed successfully!";
        $message_type = 'success';
    } else {
        $message = "Error: " . $conn->error;
        $message_type = 'error';
    }
    $stmt->close();
}

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
        
        if ($stmt->execute()) {
            $success_count++;
        } else {
            $error_count++;
        }
        $stmt->close();
    }
    
    $total_scaled = 0;
    $res = $conn->query("SELECT SUM(fyp_scaledmark) as total FROM criteria_mark WHERE fyp_studid = '$assess_student_id'");
    if ($res) {
        $row = $res->fetch_assoc();
        $total_scaled = floatval($row['total'] ?? 0);
    }
    
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
    
    if ($success_count > 0) {
        $message = "Marks saved successfully! ($success_count criteria updated)";
        $message_type = 'success';
    } else {
        $message = "Error saving marks.";
        $message_type = 'error';
    }
}

// --- Create Announcement ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_announcement'])) {
    $subject = $_POST['subject'];
    $description = $_POST['description'];
    $receiver = $_POST['receiver'];
    
    $stmt = $conn->prepare("INSERT INTO announcement (fyp_supervisorid, fyp_subject, fyp_description, fyp_receiver, fyp_datecreated) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssss", $user_name, $subject, $description, $receiver);
    if ($stmt->execute()) {
        $message = "Announcement created successfully!";
        $message_type = 'success';
    }
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
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $deleted = true;
                $stmt->close();
                break;
            }
            $stmt->close();
        }
    }
    
    if ($deleted) {
        $message = "Announcement unsent/deleted successfully!";
        $message_type = 'success';
    } else {
        $message = "Error deleting announcement.";
        $message_type = 'error';
    }
}

// Include Part 2
include("Coordinator_mainpage_part2.php");