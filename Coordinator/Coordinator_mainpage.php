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

// Use group_request table for pending requests
$res = $conn->query("SELECT COUNT(*) as cnt FROM group_request WHERE request_status = 'Pending'");
if ($res) $pending_requests = $res->fetch_assoc()['cnt'];

// Pending student registrations
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
    // First get user_id to delete from user table too
    $stmt = $conn->prepare("SELECT fyp_userid FROM student WHERE fyp_studid = ?");
    $stmt->bind_param("s", $studid);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();
    
    if ($student) {
        $conn->begin_transaction();
        try {
            // Delete from student table
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
            // Update existing set
            $stmt = $conn->prepare("UPDATE `set` SET fyp_projectphase = ?, fyp_academicid = ? WHERE fyp_setid = ?");
            $stmt->bind_param("iii", $project_phase, $academic_id, $set_id);
        } else {
            // Create new set
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
    
    // Check if link already exists
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
        
        // Calculate average (initial + final + moderator) / 3 or just available values
        $values = array_filter([$initial, $final, $mod_mark], function($v) { return $v > 0; });
        $avg_mark = count($values) > 0 ? array_sum($values) / count($values) : 0;
        
        // Check if mark exists for this student and criteria
        $stmt = $conn->prepare("SELECT fyp_criteriamarkid FROM criteria_mark WHERE fyp_studid = ? AND fyp_criteriaid = ?");
        $stmt->bind_param("si", $assess_student_id, $criteria_id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($existing) {
            // Update existing mark
            $stmt = $conn->prepare("UPDATE criteria_mark SET fyp_initialwork = ?, fyp_finalwork = ?, fyp_markbymoderator = ?, fyp_avgmark = ?, fyp_scaledmark = ? WHERE fyp_criteriamarkid = ?");
            $stmt->bind_param("dddddi", $initial, $final, $mod_mark, $avg_mark, $scaled, $existing['fyp_criteriamarkid']);
        } else {
            // Insert new mark
            $stmt = $conn->prepare("INSERT INTO criteria_mark (fyp_studid, fyp_criteriaid, fyp_initialwork, fyp_finalwork, fyp_markbymoderator, fyp_avgmark, fyp_scaledmark) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sidddddd", $assess_student_id, $criteria_id, $initial, $final, $mod_mark, $avg_mark, $scaled);
        }
        
        if ($stmt->execute()) {
            $success_count++;
        } else {
            $error_count++;
        }
        $stmt->close();
    }
    
    // Calculate and update total mark
    $total_scaled = 0;
    $res = $conn->query("SELECT SUM(fyp_scaledmark) as total FROM criteria_mark WHERE fyp_studid = '$assess_student_id'");
    if ($res) {
        $row = $res->fetch_assoc();
        $total_scaled = floatval($row['total'] ?? 0);
    }
    
    // Update or insert total_mark
    $stmt = $conn->prepare("SELECT fyp_studid FROM total_mark WHERE fyp_studid = ?");
    $stmt->bind_param("s", $assess_student_id);
    $stmt->execute();
    $existing_total = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($existing_total) {
        $stmt = $conn->prepare("UPDATE total_mark SET fyp_totalmark = ? WHERE fyp_studid = ?");
        $stmt->bind_param("ds", $total_scaled, $assess_student_id);
    } else {
        // Get project_id from pairing
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

// --- Approve Student Registration ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_registration'])) {
    $reg_id = $_POST['reg_id'];
    $stmt = $conn->prepare("SELECT * FROM pending_registration WHERE id = ? AND status = 'pending'");
    $stmt->bind_param("i", $reg_id);
    $stmt->execute();
    $reg = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($reg) {
        // Auto-generate random password
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        $generated_password = '';
        for ($i = 0; $i < 8; $i++) {
            $generated_password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $password_hash = password_hash($generated_password, PASSWORD_DEFAULT);
        
        $conn->begin_transaction();
        try {
            // Create user account with generated password
            $stmt = $conn->prepare("INSERT INTO user (fyp_username, fyp_passwordhash, fyp_usertype, fyp_datecreated) VALUES (?, ?, 'student', NOW())");
            $stmt->bind_param("ss", $reg['email'], $password_hash);
            $stmt->execute();
            $new_user_id = $stmt->insert_id;
            $stmt->close();
            
            // Create student record (only essential fields from registration)
            $stmt = $conn->prepare("INSERT INTO student (fyp_studid, fyp_studfullid, fyp_studname, fyp_email, fyp_userid) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssi", $reg['studid'], $reg['studfullid'], $reg['studname'], $reg['email'], $new_user_id);
            $stmt->execute();
            $stmt->close();
            
            // Update registration status
            $stmt = $conn->prepare("UPDATE pending_registration SET status = 'approved', processed_at = NOW(), processed_by = ? WHERE id = ?");
            $stmt->bind_param("ii", $auth_user_id, $reg_id);
            $stmt->execute();
            $stmt->close();
            
            $conn->commit();
            
            // Store credentials in session for viewing
            $_SESSION['last_approved_credentials'] = [
                'name' => $reg['studname'],
                'student_id' => $reg['studfullid'],
                'email' => $reg['email'],
                'password' => $generated_password
            ];
            
            $message = "<i class='fas fa-check-circle'></i> Registration approved for: <strong>" . htmlspecialchars($reg['studname']) . "</strong>
                       <button type='button' class='btn btn-success btn-sm' style='margin-left:15px;' onclick='openModal(\"credentialsModal\")'>
                       <i class='fas fa-eye'></i> View Credentials</button>";
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
    $remarks = $_POST['remarks'] ?? '';
    $stmt = $conn->prepare("UPDATE pending_registration SET status = 'rejected', processed_at = NOW(), processed_by = ?, remarks = ? WHERE id = ?");
    $stmt->bind_param("isi", $auth_user_id, $remarks, $reg_id);
    if ($stmt->execute()) {
        $message = "Registration rejected.";
        $message_type = 'success';
    }
    $stmt->close();
}

// --- Bulk Approve Registrations ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_approve']) && !empty($_POST['selected_regs'])) {
    $selected = $_POST['selected_regs'];
    $success_count = 0;
    $error_count = 0;
    $credentials_list = [];
    
    foreach ($selected as $reg_id) {
        $stmt = $conn->prepare("SELECT * FROM pending_registration WHERE id = ? AND status = 'pending'");
        $stmt->bind_param("i", $reg_id);
        $stmt->execute();
        $reg = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($reg) {
            // Auto-generate random password for each student
            $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
            $generated_password = '';
            for ($i = 0; $i < 8; $i++) {
                $generated_password .= $chars[random_int(0, strlen($chars) - 1)];
            }
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
                $success_count++;
                $credentials_list[] = [
                    'name' => $reg['studname'],
                    'email' => $reg['email'],
                    'password' => $generated_password
                ];
            } catch (Exception $e) {
                $conn->rollback();
                $error_count++;
            }
        }
    }
    
    // Store credentials in session for viewing
    $_SESSION['bulk_approved_credentials'] = $credentials_list;
    
    $names = array_column($credentials_list, 'name');
    $names_display = count($names) <= 3 ? implode(', ', $names) : implode(', ', array_slice($names, 0, 3)) . ' +' . (count($names) - 3) . ' more';
    
    $message = "<i class='fas fa-check-circle'></i> Approved <strong>$success_count</strong> student(s): $names_display";
    if ($success_count > 0) {
        $message .= " <button type='button' class='btn btn-success btn-sm' style='margin-left:15px;' onclick='openModal(\"bulkCredentialsModal\")'>
                     <i class='fas fa-eye'></i> View Credentials</button>";
    }
    $message_type = $error_count > 0 ? 'warning' : 'success';
}

// --- Bulk Reject Registrations ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_reject']) && !empty($_POST['selected_regs'])) {
    $selected = $_POST['selected_regs'];
    $remarks = $_POST['bulk_remarks'] ?? 'Rejected by coordinator';
    $success_count = 0;
    
    foreach ($selected as $reg_id) {
        $stmt = $conn->prepare("UPDATE pending_registration SET status = 'rejected', processed_at = NOW(), processed_by = ?, remarks = ? WHERE id = ? AND status = 'pending'");
        $stmt->bind_param("isi", $auth_user_id, $remarks, $reg_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $success_count++;
        }
        $stmt->close();
    }
    $message = "Bulk reject completed: $success_count rejected.";
    $message_type = 'success';
}

// --- Manually Add Student ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student_manual'])) {
    $email = trim($_POST['new_email']);
    $studfullid = trim($_POST['new_studfullid']);
    $studname = trim($_POST['new_studname']);
    
    // Generate student ID from full ID (e.g., TP055012 -> TP012)
    $studid = preg_replace('/[^A-Za-z0-9]/', '', $studfullid);
    if (strlen($studid) > 10) {
        $studid = substr($studid, 0, 10);
    }
    
    // Auto-generate random password
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    $generated_password = '';
    for ($i = 0; $i < 8; $i++) {
        $generated_password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    
    // Validate required fields
    if (empty($email) || empty($studfullid) || empty($studname)) {
        $message = "Please fill in all required fields.";
        $message_type = 'error';
    } else {
        // Check if email already exists in user table
        $stmt = $conn->prepare("SELECT fyp_userid FROM user WHERE fyp_username = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $message = "A user with this email already exists.";
            $message_type = 'error';
        } else {
            // Check if student ID already exists
            $stmt2 = $conn->prepare("SELECT fyp_studid FROM student WHERE fyp_studfullid = ?");
            $stmt2->bind_param("s", $studfullid);
            $stmt2->execute();
            $result2 = $stmt2->get_result();
            
            if ($result2->num_rows > 0) {
                $message = "A student with this ID already exists.";
                $message_type = 'error';
            } else {
                $conn->begin_transaction();
                try {
                    // Create user account
                    $password_hash = password_hash($generated_password, PASSWORD_DEFAULT);
                    $stmt3 = $conn->prepare("INSERT INTO user (fyp_username, fyp_passwordhash, fyp_usertype, fyp_datecreated) VALUES (?, ?, 'student', NOW())");
                    $stmt3->bind_param("ss", $email, $password_hash);
                    $stmt3->execute();
                    $new_user_id = $stmt3->insert_id;
                    $stmt3->close();
                    
                    // Create student record (minimal fields)
                    $stmt4 = $conn->prepare("INSERT INTO student (fyp_studid, fyp_studfullid, fyp_studname, fyp_email, fyp_userid) VALUES (?, ?, ?, ?, ?)");
                    $stmt4->bind_param("ssssi", $studid, $studfullid, $studname, $email, $new_user_id);
                    $stmt4->execute();
                    $stmt4->close();
                    
                    // Also add to pending_registration as approved (for history tracking)
                    $stmt5 = $conn->prepare("INSERT INTO pending_registration (email, studfullid, studid, studname, status, created_at, processed_at, processed_by) VALUES (?, ?, ?, ?, 'approved', NOW(), NOW(), ?)");
                    $stmt5->bind_param("ssssi", $email, $studfullid, $studid, $studname, $auth_user_id);
                    $stmt5->execute();
                    $stmt5->close();
                    
                    $conn->commit();
                    
                    // Store credentials in session for modal
                    $_SESSION['last_approved_credentials'] = [
                        'name' => $studname,
                        'student_id' => $studfullid,
                        'email' => $email,
                        'password' => $generated_password
                    ];
                    
                    $message = "<i class='fas fa-check-circle'></i> Student <strong>" . htmlspecialchars($studname) . "</strong> added successfully!
                               <button type='button' class='btn btn-success btn-sm' style='margin-left:15px;' onclick='openModal(\"credentialsModal\")'>
                               <i class='fas fa-eye'></i> View Credentials</button>";
                    $message_type = 'success';
                } catch (Exception $e) {
                    $conn->rollback();
                    $message = "Error adding student: " . $e->getMessage();
                    $message_type = 'error';
                }
            }
            $stmt2->close();
        }
        $stmt->close();
    }
}

// ===================== ASSESSMENT MARKS HANDLERS =====================

// --- Import Excel Students ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_excel'])) {
    if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['excel_file']['tmp_name'];
        $filename = $_FILES['excel_file']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        // Check file type
        if (!in_array($ext, ['csv', 'xls', 'xlsx'])) {
            $message = "Invalid file type. Please upload CSV, XLS, or XLSX file.";
            $message_type = 'error';
        } else {
            $import_count = 0;
            $skip_count = 0;
            $error_count = 0;
            $errors = [];
            $imported_credentials = [];
            $data_rows = [];
            
            // Parse file based on type
            if ($ext === 'csv') {
                // Handle CSV files
                if (($handle = fopen($file, "r")) !== FALSE) {
                    $header = fgetcsv($handle); // Skip header row
                    while (($data = fgetcsv($handle)) !== FALSE) {
                        if (count($data) >= 3 && !empty(trim($data[0]))) {
                            $data_rows[] = $data;
                        }
                    }
                    fclose($handle);
                }
            } else if ($ext === 'xlsx') {
                // Handle XLSX files using ZipArchive
                $zip = new ZipArchive();
                if ($zip->open($file) === TRUE) {
                    // Read shared strings
                    $shared_strings = [];
                    $xml_strings = $zip->getFromName('xl/sharedStrings.xml');
                    if ($xml_strings) {
                        // Remove namespace for easier parsing
                        $xml_strings = preg_replace('/xmlns[^=]*="[^"]*"/i', '', $xml_strings);
                        $xml = @simplexml_load_string($xml_strings);
                        if ($xml) {
                            foreach ($xml->si as $si) {
                                if (isset($si->t)) {
                                    $shared_strings[] = (string)$si->t;
                                } elseif (isset($si->r)) {
                                    // Handle rich text
                                    $text = '';
                                    foreach ($si->r as $r) {
                                        $text .= (string)$r->t;
                                    }
                                    $shared_strings[] = $text;
                                } else {
                                    $shared_strings[] = '';
                                }
                            }
                        }
                    }
                    
                    // Read sheet1
                    $xml_sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
                    if ($xml_sheet) {
                        // Remove namespace for easier parsing
                        $xml_sheet = preg_replace('/xmlns[^=]*="[^"]*"/i', '', $xml_sheet);
                        $xml = @simplexml_load_string($xml_sheet);
                        if ($xml && isset($xml->sheetData)) {
                            $row_index = 0;
                            foreach ($xml->sheetData->row as $row) {
                                $row_index++;
                                if ($row_index == 1) continue; // Skip header row
                                
                                $row_data = ['', '', '', '', '']; // Initialize 5 columns
                                
                                foreach ($row->c as $cell) {
                                    // Get cell reference (e.g., A2, B2)
                                    $cell_ref = (string)$cell['r'];
                                    $col_letter = preg_replace('/[0-9]/', '', $cell_ref);
                                    $col_index = ord(strtoupper($col_letter)) - ord('A');
                                    
                                    $type = (string)$cell['t'];
                                    $value = '';
                                    
                                    if (isset($cell->v)) {
                                        if ($type === 's') {
                                            // Shared string
                                            $idx = (int)$cell->v;
                                            $value = isset($shared_strings[$idx]) ? $shared_strings[$idx] : '';
                                        } else {
                                            // Direct value
                                            $value = (string)$cell->v;
                                        }
                                    }
                                    
                                    if ($col_index >= 0 && $col_index < 5) {
                                        $row_data[$col_index] = $value;
                                    }
                                }
                                
                                // Only add if has email (first column)
                                if (!empty(trim($row_data[0]))) {
                                    $data_rows[] = $row_data;
                                }
                            }
                        }
                    }
                    $zip->close();
                }
            } else {
                $message = "XLS format is not supported. Please save as XLSX or CSV.";
                $message_type = 'warning';
            }
            
            // Process the data rows
            if (!empty($data_rows)) {
                $row_num = 1;
                foreach ($data_rows as $data) {
                    $row_num++;
                    
                    $email = trim($data[0] ?? '');
                    $studfullid = trim($data[1] ?? '');
                    $studname = trim($data[2] ?? '');
                    $programme = trim($data[3] ?? '');
                    $contact = trim($data[4] ?? '');
                    
                    // Clean contact number (remove apostrophe if present)
                    $contact = ltrim($contact, "'");
                    
                    if (empty($email) || empty($studfullid) || empty($studname)) {
                        $skip_count++;
                        continue;
                    }
                    
                    // Generate studid from studfullid
                    $studid = preg_replace('/[^A-Za-z0-9]/', '', $studfullid);
                    if (strlen($studid) > 10) {
                        $studid = substr($studid, 0, 10);
                    }
                    
                    // Check if email already exists in user table
                    $stmt = $conn->prepare("SELECT fyp_userid FROM user WHERE fyp_username = ?");
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    if ($stmt->get_result()->num_rows > 0) {
                        $skip_count++;
                        $errors[] = "Row $row_num: Email '$email' already exists";
                        $stmt->close();
                        continue;
                    }
                    $stmt->close();
                    
                    // Check if student ID already exists in student table
                    $stmt = $conn->prepare("SELECT fyp_studid FROM student WHERE fyp_studfullid = ?");
                    $stmt->bind_param("s", $studfullid);
                    $stmt->execute();
                    if ($stmt->get_result()->num_rows > 0) {
                        $skip_count++;
                        $errors[] = "Row $row_num: Student ID '$studfullid' already exists";
                        $stmt->close();
                        continue;
                    }
                    $stmt->close();
                    
                    // Generate password
                    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
                    $generated_password = '';
                    for ($i = 0; $i < 8; $i++) {
                        $generated_password .= $chars[random_int(0, strlen($chars) - 1)];
                    }
                    $password_hash = password_hash($generated_password, PASSWORD_DEFAULT);
                    
                    // Get programme ID if provided
                    $prog_id = null;
                    if (!empty($programme)) {
                        $stmt = $conn->prepare("SELECT fyp_progid FROM programme WHERE fyp_progname = ? OR fyp_prognamefull LIKE ?");
                        $prog_like = "%$programme%";
                        $stmt->bind_param("ss", $programme, $prog_like);
                        $stmt->execute();
                        $prog_result = $stmt->get_result()->fetch_assoc();
                        $prog_id = $prog_result['fyp_progid'] ?? null;
                        $stmt->close();
                    }
                    
                    // Insert user and student in transaction
                    $conn->begin_transaction();
                    try {
                        // Insert into USER table
                        $stmt = $conn->prepare("INSERT INTO user (fyp_username, fyp_passwordhash, fyp_usertype, fyp_datecreated) VALUES (?, ?, 'student', NOW())");
                        $stmt->bind_param("ss", $email, $password_hash);
                        $stmt->execute();
                        $new_user_id = $stmt->insert_id;
                        $stmt->close();
                        
                        // Insert into STUDENT table
                        $stmt = $conn->prepare("INSERT INTO student (fyp_studid, fyp_studfullid, fyp_studname, fyp_email, fyp_contactno, fyp_progid, fyp_userid) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("sssssii", $studid, $studfullid, $studname, $email, $contact, $prog_id, $new_user_id);
                        $stmt->execute();
                        $stmt->close();
                        
                        // Also add to pending_registration as approved (for history tracking)
                        $stmt = $conn->prepare("INSERT INTO pending_registration (email, studfullid, studid, studname, programme_id, status, created_at, processed_at, processed_by) VALUES (?, ?, ?, ?, ?, 'approved', NOW(), NOW(), ?)");
                        $stmt->bind_param("ssssii", $email, $studfullid, $studid, $studname, $prog_id, $auth_user_id);
                        $stmt->execute();
                        $stmt->close();
                        
                        $conn->commit();
                        $import_count++;
                        
                        // Store credentials for download
                        $imported_credentials[] = [
                            'name' => $studname,
                            'student_id' => $studfullid,
                            'email' => $email,
                            'password' => $generated_password
                        ];
                    } catch (Exception $e) {
                        $conn->rollback();
                        $error_count++;
                        $errors[] = "Row $row_num: " . $e->getMessage();
                    }
                }
                
                // Store credentials in session for download
                $_SESSION['imported_credentials'] = $imported_credentials;
                
                // Build clean success message
                $message_type = ($error_count > 0) ? 'warning' : 'success';
            } elseif (empty($message)) {
                $message = "No valid data found in the file. Please check the format.";
                $message_type = 'error';
            }
        }
    } else {
        $message = "Please select a file to upload.";
        $message_type = 'error';
    }
    
    // Store import results in session for display
    if (isset($import_count)) {
        $_SESSION['import_results'] = [
            'import_count' => $import_count,
            'skip_count' => $skip_count,
            'error_count' => $error_count,
            'errors' => $errors
        ];
    }
}

// --- Download Imported Credentials ---
if (isset($_GET['download_credentials']) && !empty($_SESSION['imported_credentials'])) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="student_credentials_' . date('Y-m-d_His') . '.xls"');
    header('Cache-Control: max-age=0');
    
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>';
    echo '<h2>FYP System - Student Login Credentials</h2>';
    echo '<p>Generated: ' . date('F j, Y g:i A') . '</p>';
    echo '<p style="color:red;"><strong>IMPORTANT:</strong> Share these credentials with students. They should change their password after first login.</p>';
    echo '<table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;">';
    echo '<tr style="background:#8B5CF6;color:white;"><th>No.</th><th>Student Name</th><th>Student ID</th><th>Email (Username)</th><th>Temporary Password</th></tr>';
    
    $no = 1;
    foreach ($_SESSION['imported_credentials'] as $cred) {
        echo '<tr>';
        echo '<td>' . $no++ . '</td>';
        echo '<td>' . htmlspecialchars($cred['name']) . '</td>';
        echo '<td>' . htmlspecialchars($cred['student_id']) . '</td>';
        echo '<td>' . htmlspecialchars($cred['email']) . '</td>';
        echo '<td style="font-family:monospace;background:#fffde7;">' . htmlspecialchars($cred['password']) . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    echo '<br><p><strong>Login URL:</strong> [Your FYP System URL]/Login.php</p>';
    echo '<p><strong>Instructions for Students:</strong></p>';
    echo '<ol>';
    echo '<li>Go to the FYP System login page</li>';
    echo '<li>Enter your Email as username</li>';
    echo '<li>Enter the Temporary Password</li>';
    echo '<li>After logging in, go to Profile to change your password</li>';
    echo '</ol>';
    echo '</body></html>';
    
    // Clear the session data after download
    unset($_SESSION['imported_credentials']);
    exit;
}

// --- Add Assessment Mark ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_mark'])) {
    $studid = $_POST['mark_studid'];
    $sup_mark = floatval($_POST['supervisor_mark']);
    $mod_mark = floatval($_POST['moderator_mark']);
    $total_mark = ($sup_mark + $mod_mark) / 2;
    
    // Get project_id from form or find it
    $project_id = !empty($_POST['mark_projectid']) ? intval($_POST['mark_projectid']) : null;
    
    // If no project from form, try to get from pairing
    if (!$project_id) {
        $stmt = $conn->prepare("SELECT fyp_projectid FROM pairing WHERE fyp_studid = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $studid);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $project_id = $result['fyp_projectid'] ?? null;
            $stmt->close();
        }
    }
    
    // If still no project, try student table
    if (!$project_id) {
        $stmt = $conn->prepare("SELECT fyp_projectid FROM student WHERE fyp_studid = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $studid);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $project_id = $result['fyp_projectid'] ?? null;
            $stmt->close();
        }
    }
    
    // If still no project, get first available or use 1
    if (!$project_id) {
        $result = $conn->query("SELECT fyp_projectid FROM project LIMIT 1");
        if ($result && $row = $result->fetch_assoc()) {
            $project_id = $row['fyp_projectid'];
        } else {
            $project_id = 1;
        }
    }
    
    // Get set_id and academic_id
    $set_id = 1;
    $academic_id = 1;
    $result = $conn->query("SELECT fyp_setid FROM `set` ORDER BY fyp_setid DESC LIMIT 1");
    if ($result && $row = $result->fetch_assoc()) {
        $set_id = $row['fyp_setid'];
    }
    $result = $conn->query("SELECT fyp_academicid FROM academic_year ORDER BY fyp_academicid DESC LIMIT 1");
    if ($result && $row = $result->fetch_assoc()) {
        $academic_id = $row['fyp_academicid'];
    }
    
    // Insert mark
    $stmt = $conn->prepare("INSERT INTO total_mark (fyp_studid, fyp_projectid, fyp_totalfinalsupervisor, fyp_totalfinalmoderator, fyp_totalmark, fyp_setid, fyp_projectphase, fyp_academicid) VALUES (?, ?, ?, ?, ?, ?, 1, ?)");
    
    if ($stmt) {
        $stmt->bind_param("sidddii", $studid, $project_id, $sup_mark, $mod_mark, $total_mark, $set_id, $academic_id);
        if ($stmt->execute()) {
            $message = "Assessment mark added successfully! Total: " . number_format($total_mark, 2) . "%";
            $message_type = 'success';
        } else {
            $message = "Error adding mark: " . $conn->error;
            $message_type = 'error';
        }
        $stmt->close();
    } else {
        $message = "Database error: " . $conn->error;
        $message_type = 'error';
    }
}

// --- Update Assessment Mark ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_mark'])) {
    $studid = $_POST['edit_mark_studid'];
    $sup_mark = floatval($_POST['edit_supervisor_mark']);
    $mod_mark = floatval($_POST['edit_moderator_mark']);
    $total_mark = ($sup_mark + $mod_mark) / 2;
    
    $stmt = $conn->prepare("UPDATE total_mark SET fyp_totalfinalsupervisor = ?, fyp_totalfinalmoderator = ?, fyp_totalmark = ? WHERE fyp_studid = ?");
    
    if ($stmt) {
        $stmt->bind_param("ddds", $sup_mark, $mod_mark, $total_mark, $studid);
        if ($stmt->execute()) {
            $message = "Assessment mark updated successfully! New Total: " . number_format($total_mark, 2) . "%";
            $message_type = 'success';
        } else {
            $message = "Error updating mark: " . $conn->error;
            $message_type = 'error';
        }
        $stmt->close();
    } else {
        $message = "Database error: " . $conn->error;
        $message_type = 'error';
    }
}

// ===================== PROJECT CRUD HANDLERS =====================

// --- Add New Project ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_project'])) {
    $title = trim($_POST['project_title']);
    $description = trim($_POST['project_description'] ?? '');
    $type = $_POST['project_type'];
    $status = $_POST['project_status'];
    $category = $_POST['project_category'] ?? '';
    $supervisor_id = !empty($_POST['supervisor_id']) ? $_POST['supervisor_id'] : null;
    $course_required = trim($_POST['course_required'] ?? '');
    $requirements = trim($_POST['project_requirements'] ?? '');
    $max_students = intval($_POST['max_students'] ?? 2);
    
    // Check for duplicate title
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
            
            // Insert project - adjust column names as needed
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
            } else {
                $message = "Database error: " . $conn->error;
                $message_type = 'error';
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
    
    // Check for duplicate title (excluding current project)
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
            
            // Update project
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
            } else {
                $message = "Database error: " . $conn->error;
                $message_type = 'error';
            }
        }
    }
}

// --- Delete Project ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_project'])) {
    $project_id = $_POST['project_id'];
    
    // Check if project is assigned to any students
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

// ===================== MENU =====================
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
    'reports' => ['name' => 'Reports', 'icon' => 'fa-file-alt'],
    'announcements' => ['name' => 'Announcements', 'icon' => 'fa-bullhorn'],
    'settings' => ['name' => 'Settings', 'icon' => 'fa-cog'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Coordinator Dashboard - FYP Portal</title>
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
        .stat-card { background: linear-gradient(135deg, rgba(139, 92, 246, 0.1) 0%, rgba(59, 130, 246, 0.1) 100%); padding: 25px; border-radius: 16px; border: 1px solid rgba(139, 92, 246, 0.2); display: flex; align-items: center; transition: transform 0.3s; cursor: pointer; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-icon { width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-right: 20px; font-size: 1.5rem; }
        .stat-icon.purple { background: rgba(139, 92, 246, 0.2); color: #a78bfa; }
        .stat-icon.blue { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
        .stat-icon.green { background: rgba(16, 185, 129, 0.2); color: #34d399; }
        .stat-icon.orange { background: rgba(249, 115, 22, 0.2); color: #fb923c; }
        .stat-icon.blue { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
        .stat-icon.red { background: rgba(239, 68, 68, 0.2); color: #f87171; }
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
        .badge-research { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
        .badge-application { background: rgba(16, 185, 129, 0.2); color: #34d399; }
        .badge-package { background: rgba(249, 115, 22, 0.2); color: #fb923c; }
        
        .btn-info { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; }
        
        .empty-state { text-align: center; padding: 50px 20px; color: #64748b; }
        .empty-state i { font-size: 4rem; margin-bottom: 20px; opacity: 0.3; }
        
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 2000; align-items: center; justify-content: center; }
        .modal-overlay.active { display: flex; }
        .modal { background: #1a1a2e; border-radius: 16px; border: 1px solid rgba(139, 92, 246, 0.2); width: 100%; max-width: 600px; max-height: 90vh; overflow-y: auto; }
        .modal-header { padding: 20px 25px; border-bottom: 1px solid rgba(139, 92, 246, 0.1); display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { color: #fff; }
        .modal-close { background: none; border: none; color: #94a3b8; font-size: 1.5rem; cursor: pointer; }
        .modal-body { padding: 25px; }
        .modal-footer { padding: 20px 25px; border-top: 1px solid rgba(139, 92, 246, 0.1); display: flex; justify-content: flex-end; gap: 10px; }
        
        .quick-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; }
        .quick-action { background: rgba(139, 92, 246, 0.1); border: 1px solid rgba(139, 92, 246, 0.2); border-radius: 12px; padding: 20px; text-align: center; cursor: pointer; transition: all 0.3s; text-decoration: none; color: inherit; display: block; }
        .quick-action:hover { background: rgba(139, 92, 246, 0.2); transform: translateY(-3px); }
        .quick-action i { font-size: 2rem; color: #a78bfa; margin-bottom: 10px; display: block; }
        .quick-action h4 { color: #fff; font-size: 0.95rem; margin-bottom: 5px; }
        .quick-action p { color: #64748b; font-size: 0.8rem; }
        
        .search-box { display: flex; gap: 10px; margin-bottom: 20px; }
        .filter-row { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
        .filter-row .form-group { margin-bottom: 0; min-width: 150px; }
        
        .tab-nav { display: flex; gap: 5px; margin-bottom: 20px; border-bottom: 1px solid rgba(139, 92, 246, 0.1); padding-bottom: 10px; }
        .tab-btn { padding: 10px 20px; background: transparent; border: none; color: #94a3b8; cursor: pointer; border-radius: 8px 8px 0 0; transition: all 0.3s; }
        .tab-btn.active { background: rgba(139, 92, 246, 0.2); color: #fff; }
        
        /* Search Preview Dropdown */
        #searchPreviewDropdown::-webkit-scrollbar { width: 6px; }
        #searchPreviewDropdown::-webkit-scrollbar-track { background: rgba(30,41,59,0.5); border-radius: 3px; }
        #searchPreviewDropdown::-webkit-scrollbar-thumb { background: rgba(139,92,246,0.5); border-radius: 3px; }
        #searchPreviewDropdown::-webkit-scrollbar-thumb:hover { background: rgba(139,92,246,0.7); }
        
        /* Filter Tags */
        .filter-tag { transition: all 0.2s; }
        .filter-tag:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
        
        /* Search Input Focus */
        #marksSearchInput:focus { border-color: #8b5cf6; box-shadow: 0 0 0 3px rgba(139,92,246,0.2); outline: none; }
        
        /* Intake/Status Radio Buttons */
        .intake-option span:hover, .status-option span:hover { background: rgba(139,92,246,0.15) !important; }
        
        @media (max-width: 768px) {
            #marksSearchInput { font-size: 0.9rem; height: 45px; }
        }
    </style>
</head>
<body>

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
                <?php if (isset($item['badge']) && $item['badge'] > 0): ?><span class="nav-badge"><?= $item['badge']; ?></span><?php endif; ?>
            </a></li>
        <?php endforeach; ?>
    </ul>
    <div class="sidebar-footer"><a href="../Login.php?logout=1"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
</nav>

<div class="main-content">
    <header class="header">
        <h1><?= $menu_items[$current_page]['name'] ?? 'Dashboard'; ?></h1>
        <span class="header-time"><?= date('l, F j, Y'); ?></span>
    </header>
    
    <div class="content">
        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type; ?>"><i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i> <?= $message; ?></div>
        <?php endif; ?>

        <!-- ==================== DASHBOARD ==================== -->
        <?php if ($current_page === 'dashboard'): ?>
            <div class="welcome-box">
                <h2>Welcome back, <?= htmlspecialchars($user_name); ?>!</h2>
                <p>Manage FYP students, supervisors, pairings, assessments and generate reports.</p>
            </div>
            
            <div class="stats-grid">
                <a href="?page=students" class="stat-card"><div class="stat-icon purple"><i class="fas fa-user-graduate"></i></div><div class="stat-info"><h4><?= $total_students; ?></h4><p>Total Students</p></div></a>
                <a href="?page=supervisors" class="stat-card"><div class="stat-icon blue"><i class="fas fa-user-tie"></i></div><div class="stat-info"><h4><?= $total_supervisors; ?></h4><p>Total Supervisors</p></div></a>
                <a href="?page=group_requests" class="stat-card"><div class="stat-icon orange"><i class="fas fa-user-plus"></i></div><div class="stat-info"><h4><?= $pending_requests; ?></h4><p>Pending Group Requests</p></div></a>
                <a href="?page=projects" class="stat-card"><div class="stat-icon green"><i class="fas fa-folder-open"></i></div><div class="stat-info"><h4><?= $total_projects; ?></h4><p>Total Projects</p></div></a>
                <a href="?page=pairing" class="stat-card"><div class="stat-icon red"><i class="fas fa-link"></i></div><div class="stat-info"><h4><?= $total_pairings; ?></h4><p>Total Pairings</p></div></a>
            </div>
            
            <?php if ($pending_requests > 0): ?>
            <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> You have <strong><?= $pending_requests; ?></strong> pending group request(s). <a href="?page=group_requests" style="color:#fb923c;margin-left:10px;">Review Now →</a></div>
            <?php endif; ?>
            
            <div class="card"><div class="card-header"><h3>Quick Actions</h3></div><div class="card-body"><div class="quick-actions">
                <a href="?page=group_requests" class="quick-action"><i class="fas fa-user-plus"></i><h4>Group Requests</h4><p>Approve or reject requests</p></a>
                <a href="?page=pairing" class="quick-action"><i class="fas fa-link"></i><h4>Manage Pairings</h4><p>Assign students to supervisors</p></a>
                <a href="?page=rubrics" class="quick-action"><i class="fas fa-list-check"></i><h4>Assessment Rubrics</h4><p>Create and manage rubrics</p></a>
                <a href="?page=marks" class="quick-action"><i class="fas fa-calculator"></i><h4>View Marks</h4><p>Assessment mark allocation</p></a>
                <a href="?page=reports" class="quick-action"><i class="fas fa-file-alt"></i><h4>Generate Reports</h4><p>Export forms and reports</p></a>
                <a href="?page=announcements" class="quick-action"><i class="fas fa-bullhorn"></i><h4>Announcements</h4><p>Post announcements</p></a>
            </div></div></div>

        <!-- ==================== STUDENT REGISTRATIONS ==================== -->
        <?php elseif ($current_page === 'registrations'): 
            $pending_regs = [];
            $res = $conn->query("SELECT pr.*, p.fyp_progname, ay.fyp_acdyear, ay.fyp_intake 
                                 FROM pending_registration pr 
                                 LEFT JOIN programme p ON pr.programme_id = p.fyp_progid 
                                 LEFT JOIN academic_year ay ON pr.academic_year_id = ay.fyp_academicid 
                                 ORDER BY pr.status = 'pending' DESC, pr.created_at DESC");
            if ($res) { while ($row = $res->fetch_assoc()) { $pending_regs[] = $row; } }
            
            $pending_only = array_filter($pending_regs, function($r) { return $r['status'] === 'pending'; });
            $pending_only = array_values($pending_only); // Re-index array
            
            // Check for import results in session
            $import_results = $_SESSION['import_results'] ?? null;
            $imported_credentials = $_SESSION['imported_credentials'] ?? [];
            if (isset($_SESSION['import_results'])) {
                unset($_SESSION['import_results']); // Clear after display
            }
        ?>
            
            <!-- Import Results Card (only show if there are results) -->
            <?php if ($import_results): ?>
            <div class="card" style="margin-bottom:25px;border:2px solid rgba(16,185,129,0.3);">
                <div class="card-header" style="background:rgba(16,185,129,0.1);">
                    <h3 style="color:#34d399;"><i class="fas fa-check-circle"></i> Import Results</h3>
                    <button class="btn btn-secondary btn-sm" onclick="this.closest('.card').remove()"><i class="fas fa-times"></i> Dismiss</button>
                </div>
                <div class="card-body">
                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(150px, 1fr));gap:20px;margin-bottom:20px;">
                        <div style="text-align:center;padding:20px;background:rgba(16,185,129,0.1);border-radius:12px;">
                            <div style="font-size:2rem;font-weight:700;color:#34d399;"><?= $import_results['import_count']; ?></div>
                            <div style="color:#94a3b8;font-size:0.9rem;">Successfully Imported</div>
                        </div>
                        <div style="text-align:center;padding:20px;background:rgba(249,115,22,0.1);border-radius:12px;">
                            <div style="font-size:2rem;font-weight:700;color:#fb923c;"><?= $import_results['skip_count']; ?></div>
                            <div style="color:#94a3b8;font-size:0.9rem;">Skipped (Duplicates)</div>
                        </div>
                        <div style="text-align:center;padding:20px;background:rgba(239,68,68,0.1);border-radius:12px;">
                            <div style="font-size:2rem;font-weight:700;color:#f87171;"><?= $import_results['error_count']; ?></div>
                            <div style="color:#94a3b8;font-size:0.9rem;">Errors</div>
                        </div>
                    </div>
                    
                    <?php if ($import_results['import_count'] > 0 && !empty($imported_credentials)): ?>
                    <div style="background:rgba(16,185,129,0.1);padding:20px;border-radius:12px;border:1px solid rgba(16,185,129,0.3);">
                        <h4 style="color:#34d399;margin-bottom:10px;"><i class="fas fa-download"></i> Download Credentials Report</h4>
                        <p style="color:#94a3b8;margin-bottom:15px;">Share this file with students so they can login to their accounts.</p>
                        <a href="?page=registrations&download_credentials=1" class="btn btn-success">
                            <i class="fas fa-file-excel"></i> Download Credentials (Excel)
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($import_results['errors'])): ?>
                    <div style="margin-top:15px;padding:15px;background:rgba(239,68,68,0.1);border-radius:10px;">
                        <strong style="color:#f87171;">Error Details:</strong>
                        <ul style="color:#94a3b8;margin:10px 0 0 20px;font-size:0.85rem;">
                            <?php foreach (array_slice($import_results['errors'], 0, 5) as $err): ?>
                            <li><?= htmlspecialchars($err); ?></li>
                            <?php endforeach; ?>
                            <?php if (count($import_results['errors']) > 5): ?>
                            <li>... and <?= count($import_results['errors']) - 5; ?> more errors</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Registered Students Card (Imported Students with credentials) -->
            <?php if (!empty($imported_credentials)): ?>
            <div class="card" style="margin-bottom:25px;">
                <div class="card-header">
                    <h3><i class="fas fa-user-check" style="color:#34d399;"></i> Recently Imported Students (<?= count($imported_credentials); ?>)</h3>
                    <a href="?page=registrations&download_credentials=1" class="btn btn-success btn-sm">
                        <i class="fas fa-download"></i> Download All Credentials
                    </a>
                </div>
                <div class="card-body">
                    <p style="color:#94a3b8;margin-bottom:15px;"><i class="fas fa-info-circle"></i> These students have been added to both the <strong>User</strong> and <strong>Student</strong> tables.</p>
                    <div style="overflow-x:auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Email (Username)</th>
                                    <th>Temp Password</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($imported_credentials as $idx => $cred): ?>
                                <tr>
                                    <td><?= $idx + 1; ?></td>
                                    <td><strong><?= htmlspecialchars($cred['student_id']); ?></strong></td>
                                    <td><?= htmlspecialchars($cred['name']); ?></td>
                                    <td><?= htmlspecialchars($cred['email']); ?></td>
                                    <td><code style="background:rgba(251,191,36,0.2);padding:3px 8px;border-radius:4px;color:#fbbf24;"><?= htmlspecialchars($cred['password']); ?></code></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Pending Registrations Card -->
            <div class="card">
                <div class="card-header">
                    <h3>Pending Student Registrations</h3>
                    <div style="display:flex;gap:10px;align-items:center;">
                        <button type="button" class="btn btn-info" onclick="openModal('importExcelModal')">
                            <i class="fas fa-file-excel"></i> Import Excel
                        </button>
                        <button type="button" class="btn btn-primary" onclick="openModal('addStudentModal')">
                            <i class="fas fa-user-plus"></i> Add Student Manually
                        </button>
                        <span class="badge badge-pending"><?= count($pending_only); ?> Pending</span>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_only)): ?>
                        <div class="empty-state"><i class="fas fa-inbox"></i><p>No pending registrations</p></div>
                    <?php else: ?>
                        <!-- Filter and Sort Controls -->
                        <div class="filter-controls" style="display:flex;flex-wrap:wrap;gap:15px;margin-bottom:20px;padding:15px;background:rgba(139,92,246,0.1);border-radius:12px;">
                            <div style="flex:1;min-width:200px;">
                                <label style="color:#a78bfa;font-size:0.85rem;display:block;margin-bottom:5px;"><i class="fas fa-search"></i> Search</label>
                                <input type="text" id="searchPending" class="form-control" placeholder="Search by name, email, ID..." onkeyup="filterPendingTable()">
                            </div>
                            <div style="min-width:180px;">
                                <label style="color:#a78bfa;font-size:0.85rem;display:block;margin-bottom:5px;"><i class="fas fa-sort"></i> Sort By</label>
                                <select id="sortPending" class="form-control" onchange="sortPendingTable()">
                                    <option value="newest">Newest First</option>
                                    <option value="oldest">Oldest First</option>
                                    <option value="name_asc">Name (A-Z)</option>
                                    <option value="name_desc">Name (Z-A)</option>
                                    <option value="id_asc">Student ID (A-Z)</option>
                                    <option value="id_desc">Student ID (Z-A)</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Bulk Actions -->
                        <form method="POST" id="bulkForm">
                            <div class="bulk-actions" style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:15px;padding:15px;background:rgba(26,26,46,0.6);border-radius:12px;align-items:center;">
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <input type="checkbox" id="selectAll" onclick="toggleSelectAll()" style="width:18px;height:18px;cursor:pointer;">
                                    <label for="selectAll" style="color:#94a3b8;cursor:pointer;margin:0;">Select All</label>
                                </div>
                                <span style="color:#4a4a6a;">|</span>
                                <span id="selectedCount" style="color:#a78bfa;font-weight:600;">0 selected</span>
                                <div style="flex:1;"></div>
                                <button type="submit" name="bulk_approve" class="btn btn-success" onclick="return confirmBulkAction('approve')">
                                    <i class="fas fa-check-double"></i> Approve Selected
                                </button>
                                <button type="button" class="btn btn-danger" onclick="openBulkRejectModal()">
                                    <i class="fas fa-times"></i> Reject Selected
                                </button>
                            </div>
                            
                            <!-- Table -->
                            <table class="data-table" id="pendingTable">
                                <thead>
                                    <tr>
                                        <th style="width:40px;text-align:center;">
                                            <i class="fas fa-check-square" style="color:#a78bfa;"></i>
                                        </th>
                                        <th>Email</th>
                                        <th>Student ID</th>
                                        <th>Name</th>
                                        <th>Programme</th>
                                        <th>Type</th>
                                        <th>Submitted</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_only as $index => $reg): ?>
                                    <tr data-name="<?= htmlspecialchars(strtolower($reg['studname'])); ?>" 
                                        data-email="<?= htmlspecialchars(strtolower($reg['email'])); ?>"
                                        data-id="<?= htmlspecialchars(strtolower($reg['studfullid'])); ?>"
                                        data-date="<?= strtotime($reg['created_at']); ?>">
                                        <td style="text-align:center;">
                                            <input type="checkbox" name="selected_regs[]" value="<?= $reg['id']; ?>" class="reg-checkbox" onclick="updateSelectedCount()" style="width:18px;height:18px;cursor:pointer;">
                                        </td>
                                        <td><?= htmlspecialchars($reg['email']); ?></td>
                                        <td><strong><?= htmlspecialchars($reg['studfullid']); ?></strong></td>
                                        <td><?= htmlspecialchars($reg['studname']); ?></td>
                                        <td><?= htmlspecialchars($reg['fyp_progname'] ?? '-'); ?></td>
                                        <td><span class="badge badge-<?= $reg['group_type'] === 'Individual' ? 'approved' : 'pending'; ?>"><?= htmlspecialchars($reg['group_type']); ?></span></td>
                                        <td><?= date('M j, Y', strtotime($reg['created_at'])); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-success btn-sm" onclick="approveSingle(<?= $reg['id']; ?>)"><i class="fas fa-check"></i></button>
                                            <button type="button" class="btn btn-danger btn-sm" onclick="showRejectRegModal(<?= $reg['id']; ?>,'<?= htmlspecialchars($reg['studname'], ENT_QUOTES); ?>')"><i class="fas fa-times"></i></button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </form>
                        
                        <!-- Hidden form for single approve -->
                        <form method="POST" id="singleApproveForm" style="display:none;">
                            <input type="hidden" name="reg_id" id="singleApproveId">
                            <input type="hidden" name="approve_registration" value="1">
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- All Registrations History Card -->
            <div class="card">
                <div class="card-header">
                    <h3>All Registrations History</h3>
                    <div style="display:flex;gap:10px;align-items:center;">
                        <select id="sortHistory" class="form-control" style="width:auto;" onchange="sortHistoryTable()">
                            <option value="newest">Newest First</option>
                            <option value="oldest">Oldest First</option>
                            <option value="name_asc">Name (A-Z)</option>
                            <option value="name_desc">Name (Z-A)</option>
                        </select>
                        <select id="filterStatus" class="form-control" style="width:auto;" onchange="filterHistoryTable()">
                            <option value="all">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_regs)): ?>
                        <div class="empty-state"><i class="fas fa-history"></i><p>No registrations found</p></div>
                    <?php else: ?>
                        <table class="data-table" id="historyTable">
                            <thead>
                                <tr>
                                    <th>Email</th>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Programme</th>
                                    <th>Status</th>
                                    <th>Submitted</th>
                                    <th>Processed</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_regs as $reg): ?>
                                <tr data-name="<?= htmlspecialchars(strtolower($reg['studname'])); ?>"
                                    data-status="<?= $reg['status']; ?>"
                                    data-date="<?= strtotime($reg['created_at']); ?>">
                                    <td><?= htmlspecialchars($reg['email']); ?></td>
                                    <td><?= htmlspecialchars($reg['studfullid']); ?></td>
                                    <td><?= htmlspecialchars($reg['studname']); ?></td>
                                    <td><?= htmlspecialchars($reg['fyp_progname'] ?? '-'); ?></td>
                                    <td><span class="badge badge-<?= $reg['status'] === 'approved' ? 'approved' : ($reg['status'] === 'pending' ? 'pending' : 'rejected'); ?>"><?= ucfirst($reg['status']); ?></span></td>
                                    <td><?= date('M j, Y', strtotime($reg['created_at'])); ?></td>
                                    <td><?= $reg['processed_at'] ? date('M j, Y', strtotime($reg['processed_at'])) : '-'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Bulk Reject Modal -->
            <div class="modal-overlay" id="bulkRejectModal">
                <div class="modal" style="max-width:500px;">
                    <div class="modal-header">
                        <h3><i class="fas fa-times-circle" style="color:#f87171;"></i> Bulk Reject</h3>
                        <button class="modal-close" onclick="closeModal('bulkRejectModal')">&times;</button>
                    </div>
                    <div class="modal-body">
                        <p style="margin-bottom:15px;">Reject <strong id="bulkRejectCount">0</strong> selected registration(s)?</p>
                        <div class="form-group">
                            <label>Reason for Rejection (optional)</label>
                            <textarea name="bulk_remarks" form="bulkForm" class="form-control" rows="3" placeholder="Enter reason for rejection..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('bulkRejectModal')">Cancel</button>
                        <button type="submit" name="bulk_reject" form="bulkForm" class="btn btn-danger"><i class="fas fa-times"></i> Reject Selected</button>
                    </div>
                </div>
            </div>
            
            <!-- Import Excel Modal -->
            <div class="modal-overlay" id="importExcelModal">
                <div class="modal" style="max-width:700px;">
                    <div class="modal-header">
                        <h3><i class="fas fa-file-excel" style="color:#10b981;"></i> Import Students from Excel/CSV</h3>
                        <button class="modal-close" onclick="closeModal('importExcelModal')">&times;</button>
                    </div>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="modal-body">
                            <div style="background:rgba(59,130,246,0.1);padding:20px;border-radius:12px;border:1px solid rgba(59,130,246,0.2);margin-bottom:20px;">
                                <h4 style="color:#60a5fa;margin-bottom:10px;"><i class="fas fa-info-circle"></i> Required CSV/Excel Format</h4>
                                <p style="color:#94a3b8;margin-bottom:10px;font-size:0.9rem;">Your file should have these columns (first row as header):</p>
                                <div style="background:rgba(0,0,0,0.3);padding:15px;border-radius:8px;font-family:monospace;font-size:0.9rem;overflow-x:auto;">
                                    <span style="color:#34d399;">EMAIL</span>, 
                                    <span style="color:#34d399;">STUDENT_ID</span>, 
                                    <span style="color:#34d399;">NAME</span>, 
                                    <span style="color:#fbbf24;">PROGRAMME</span>, 
                                    <span style="color:#fbbf24;">CONTACT</span>
                                </div>
                                <p style="color:#64748b;margin-top:10px;font-size:0.8rem;">
                                    <span style="color:#34d399;">●</span> Required fields &nbsp;&nbsp;
                                    <span style="color:#fbbf24;">●</span> Optional fields
                                </p>
                            </div>
                            
                            <div style="background:rgba(139,92,246,0.1);padding:15px;border-radius:12px;margin-bottom:20px;">
                                <h4 style="color:#a78bfa;margin-bottom:10px;"><i class="fas fa-table"></i> Example Data</h4>
                                <div style="overflow-x:auto;">
                                    <table style="width:100%;font-size:0.8rem;color:#e2e8f0;border-collapse:collapse;">
                                        <tr style="background:rgba(139,92,246,0.3);">
                                            <th style="padding:10px;text-align:left;border:1px solid rgba(139,92,246,0.3);">EMAIL</th>
                                            <th style="padding:10px;text-align:left;border:1px solid rgba(139,92,246,0.3);">STUDENT_ID</th>
                                            <th style="padding:10px;text-align:left;border:1px solid rgba(139,92,246,0.3);">NAME</th>
                                            <th style="padding:10px;text-align:left;border:1px solid rgba(139,92,246,0.3);">PROGRAMME</th>
                                            <th style="padding:10px;text-align:left;border:1px solid rgba(139,92,246,0.3);">CONTACT</th>
                                        </tr>
                                        <tr>
                                            <td style="padding:10px;border:1px solid rgba(139,92,246,0.2);">john.doe@student.edu.my</td>
                                            <td style="padding:10px;border:1px solid rgba(139,92,246,0.2);">TP055011</td>
                                            <td style="padding:10px;border:1px solid rgba(139,92,246,0.2);">John Doe</td>
                                            <td style="padding:10px;border:1px solid rgba(139,92,246,0.2);">SE</td>
                                            <td style="padding:10px;border:1px solid rgba(139,92,246,0.2);">0123456789</td>
                                        </tr>
                                        <tr style="background:rgba(0,0,0,0.1);">
                                            <td style="padding:10px;border:1px solid rgba(139,92,246,0.2);">jane.smith@student.edu.my</td>
                                            <td style="padding:10px;border:1px solid rgba(139,92,246,0.2);">TP055012</td>
                                            <td style="padding:10px;border:1px solid rgba(139,92,246,0.2);">Jane Smith</td>
                                            <td style="padding:10px;border:1px solid rgba(139,92,246,0.2);">AI</td>
                                            <td style="padding:10px;border:1px solid rgba(139,92,246,0.2);">0198765432</td>
                                        </tr>
                                        <tr>
                                            <td style="padding:10px;border:1px solid rgba(139,92,246,0.2);">bob.wilson@student.edu.my</td>
                                            <td style="padding:10px;border:1px solid rgba(139,92,246,0.2);">TP055013</td>
                                            <td style="padding:10px;border:1px solid rgba(139,92,246,0.2);">Bob Wilson</td>
                                            <td style="padding:10px;border:1px solid rgba(139,92,246,0.2);">CS</td>
                                            <td style="padding:10px;border:1px solid rgba(139,92,246,0.2);">0167891234</td>
                                        </tr>
                                        <tr style="background:rgba(0,0,0,0.1);">
                                            <td style="padding:10px;border:1px solid rgba(139,92,246,0.2);">alice.tan@student.edu.my</td>
                                            <td style="padding:10px;border:1px solid rgba(139,92,246,0.2);">TP055014</td>
                                            <td style="padding:10px;border:1px solid rgba(139,92,246,0.2);">Alice Tan</td>
                                            <td style="padding:10px;border:1px solid rgba(139,92,246,0.2);">SE</td>
                                            <td style="padding:10px;border:1px solid rgba(139,92,246,0.2);">0112223344</td>
                                        </tr>
                                        <tr>
                                            <td style="padding:10px;border:1px solid rgba(139,92,246,0.2);">charlie.lee@student.edu.my</td>
                                            <td style="padding:10px;border:1px solid rgba(139,92,246,0.2);">TP055015</td>
                                            <td style="padding:10px;border:1px solid rgba(139,92,246,0.2);">Charlie Lee</td>
                                            <td style="padding:10px;border:1px solid rgba(139,92,246,0.2);">AI</td>
                                            <td style="padding:10px;border:1px solid rgba(139,92,246,0.2);">0156667777</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-upload"></i> Select File <span style="color:#f87171;">*</span></label>
                                <input type="file" name="excel_file" accept=".csv,.xls,.xlsx" class="form-control" required 
                                       style="padding:15px;background:rgba(15,15,26,0.6);border:2px dashed rgba(139,92,246,0.3);">
                                <small style="color:#64748b;display:block;margin-top:8px;">
                                    <i class="fas fa-file-csv" style="color:#10b981;"></i> CSV (Recommended) &nbsp;
                                    <i class="fas fa-file-excel" style="color:#10b981;"></i> XLS/XLSX (Convert to CSV)
                                </small>
                            </div>
                            
                            <div style="background:rgba(16,185,129,0.1);padding:15px;border-radius:10px;border:1px solid rgba(16,185,129,0.2);">
                                <p style="color:#34d399;margin:0;font-size:0.9rem;">
                                    <i class="fas fa-key"></i> <strong>Passwords will be auto-generated</strong> for each student. 
                                    They can reset their password after first login.
                                </p>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <a href="sample_import_template.csv" download class="btn btn-secondary" style="margin-right:auto;" onclick="downloadTemplate(event)">
                                <i class="fas fa-download"></i> Download Template
                            </a>
                            <button type="button" class="btn btn-secondary" onclick="closeModal('importExcelModal')">Cancel</button>
                            <button type="submit" name="import_excel" class="btn btn-success">
                                <i class="fas fa-upload"></i> Import Students
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Single Credentials Modal -->
            <?php $last_cred = $_SESSION['last_approved_credentials'] ?? null; ?>
            <div class="modal-overlay" id="credentialsModal">
                <div class="modal" style="max-width:500px;">
                    <div class="modal-header">
                        <h3><i class="fas fa-key" style="color:#34d399;"></i> Login Credentials</h3>
                        <button class="modal-close" onclick="closeModal('credentialsModal')">&times;</button>
                    </div>
                    <div class="modal-body">
                        <?php if ($last_cred): ?>
                        <div style="text-align:center;margin-bottom:20px;">
                            <div style="width:70px;height:70px;background:rgba(16,185,129,0.2);border-radius:50%;display:inline-flex;align-items:center;justify-content:center;margin-bottom:10px;">
                                <i class="fas fa-user-check" style="font-size:2rem;color:#34d399;"></i>
                            </div>
                            <h4 style="color:#e2e8f0;"><?= htmlspecialchars($last_cred['name']); ?></h4>
                            <p style="color:#94a3b8;"><?= htmlspecialchars($last_cred['student_id'] ?? ''); ?></p>
                        </div>
                        
                        <div style="background:rgba(16,185,129,0.1);padding:20px;border-radius:12px;border:1px solid rgba(16,185,129,0.2);">
                            <div style="margin-bottom:15px;">
                                <label style="color:#94a3b8;font-size:0.85rem;display:block;margin-bottom:5px;">Email (Username)</label>
                                <div style="background:rgba(0,0,0,0.3);padding:12px;border-radius:8px;font-family:monospace;color:#e2e8f0;">
                                    <?= htmlspecialchars($last_cred['email']); ?>
                                </div>
                            </div>
                            <div>
                                <label style="color:#94a3b8;font-size:0.85rem;display:block;margin-bottom:5px;">Temporary Password</label>
                                <div style="background:rgba(251,191,36,0.2);padding:12px;border-radius:8px;font-family:monospace;color:#fbbf24;font-size:1.1rem;letter-spacing:1px;">
                                    <?= htmlspecialchars($last_cred['password']); ?>
                                </div>
                            </div>
                        </div>
                        
                        <p style="color:#64748b;font-size:0.85rem;margin-top:15px;text-align:center;">
                            <i class="fas fa-info-circle"></i> Share these credentials with the student. They can change their password after login.
                        </p>
                        <?php else: ?>
                        <div class="empty-state"><i class="fas fa-key"></i><p>No credentials available</p></div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('credentialsModal')">Close</button>
                        <?php if ($last_cred): ?>
                        <button type="button" class="btn btn-success" onclick="copyCredentials('<?= htmlspecialchars($last_cred['email']); ?>', '<?= htmlspecialchars($last_cred['password']); ?>')">
                            <i class="fas fa-copy"></i> Copy to Clipboard
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Bulk Credentials Modal -->
            <?php $bulk_creds = $_SESSION['bulk_approved_credentials'] ?? []; ?>
            <div class="modal-overlay" id="bulkCredentialsModal">
                <div class="modal" style="max-width:700px;">
                    <div class="modal-header">
                        <h3><i class="fas fa-users" style="color:#34d399;"></i> Approved Students Credentials (<?= count($bulk_creds); ?>)</h3>
                        <button class="modal-close" onclick="closeModal('bulkCredentialsModal')">&times;</button>
                    </div>
                    <div class="modal-body" style="max-height:60vh;overflow-y:auto;">
                        <?php if (!empty($bulk_creds)): ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email (Username)</th>
                                    <th>Password</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bulk_creds as $cred): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($cred['name']); ?></strong></td>
                                    <td><?= htmlspecialchars($cred['email']); ?></td>
                                    <td><code style="background:rgba(251,191,36,0.2);padding:4px 10px;border-radius:4px;color:#fbbf24;"><?= htmlspecialchars($cred['password']); ?></code></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p style="color:#64748b;font-size:0.85rem;margin-top:15px;text-align:center;">
                            <i class="fas fa-info-circle"></i> Share these credentials with the students. They can change their password after login.
                        </p>
                        <?php else: ?>
                        <div class="empty-state"><i class="fas fa-key"></i><p>No credentials available</p></div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('bulkCredentialsModal')">Close</button>
                        <?php if (!empty($bulk_creds)): ?>
                        <button type="button" class="btn btn-success" onclick="copyAllCredentials()">
                            <i class="fas fa-copy"></i> Copy All
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        <!-- ==================== GROUP REQUESTS ==================== -->
        <?php elseif ($current_page === 'group_requests'): 
            // Get all group requests with student details
            $all_requests = [];
            $res = $conn->query("SELECT gr.*, 
                                        sg.group_name,
                                        s1.fyp_studname as inviter_name, s1.fyp_studfullid as inviter_fullid,
                                        s2.fyp_studname as invitee_name, s2.fyp_studfullid as invitee_fullid
                                 FROM group_request gr
                                 LEFT JOIN student_group sg ON gr.group_id = sg.group_id
                                 LEFT JOIN student s1 ON gr.inviter_id = s1.fyp_studid
                                 LEFT JOIN student s2 ON gr.invitee_id = s2.fyp_studid
                                 ORDER BY gr.request_status = 'Pending' DESC, gr.request_id DESC");
            if ($res) { while ($row = $res->fetch_assoc()) { $all_requests[] = $row; } }
            
            $pending_only = array_filter($all_requests, function($r) { return $r['request_status'] === 'Pending'; });
        ?>
            <div class="card"><div class="card-header"><h3>All Group Requests</h3><span class="badge badge-pending"><?= count($pending_only); ?> Pending</span></div><div class="card-body">
                <?php if (empty($all_requests)): ?>
                    <div class="empty-state"><i class="fas fa-inbox"></i><p>No group requests found</p></div>
                <?php else: ?>
                    <table class="data-table"><thead><tr><th>ID</th><th>Group</th><th>Inviter</th><th>Invitee</th><th>Current Status</th><th>Change Status</th></tr></thead><tbody>
                        <?php foreach ($all_requests as $req): ?>
                        <tr>
                            <td><?= $req['request_id']; ?></td>
                            <td><strong><?= htmlspecialchars($req['group_name'] ?? 'Group #' . $req['group_id']); ?></strong></td>
                            <td><?= htmlspecialchars(($req['inviter_fullid'] ?? $req['inviter_id']) . ' - ' . ($req['inviter_name'] ?? '')); ?></td>
                            <td><?= htmlspecialchars(($req['invitee_fullid'] ?? $req['invitee_id']) . ' - ' . ($req['invitee_name'] ?? '')); ?></td>
                            <td><span class="badge badge-<?= $req['request_status'] === 'Accepted' ? 'approved' : ($req['request_status'] === 'Pending' ? 'pending' : 'rejected'); ?>"><?= $req['request_status']; ?></span></td>
                            <td>
                                <form method="POST" style="display:flex;gap:8px;align-items:center;">
                                    <input type="hidden" name="request_id" value="<?= $req['request_id']; ?>">
                                    <select name="new_status" class="form-control" style="width:auto;padding:6px 10px;">
                                        <option value="Pending" <?= $req['request_status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="Accepted" <?= $req['request_status'] === 'Accepted' ? 'selected' : ''; ?>>Accepted</option>
                                        <option value="Rejected" <?= $req['request_status'] === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                                    </select>
                                    <button type="submit" name="update_request_status" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Update</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody></table>
                <?php endif; ?>
            </div></div>

        <!-- ==================== MANAGE STUDENTS ==================== -->
        <?php elseif ($current_page === 'students'): 
            $students = [];
            $res = $conn->query("SELECT s.*, p.fyp_progname, p.fyp_progid, a.fyp_acdyear, a.fyp_intake, u.fyp_username,
                                        pr.fyp_projecttitle, pr.fyp_projectid
                                 FROM student s 
                                 LEFT JOIN programme p ON s.fyp_progid = p.fyp_progid 
                                 LEFT JOIN academic_year a ON s.fyp_academicid = a.fyp_academicid 
                                 LEFT JOIN user u ON s.fyp_userid = u.fyp_userid 
                                 LEFT JOIN fyp_registration fr ON s.fyp_studid = fr.fyp_studid
                                 LEFT JOIN project pr ON fr.fyp_projectid = pr.fyp_projectid
                                 ORDER BY s.fyp_studname");
            if ($res) { while ($row = $res->fetch_assoc()) { $students[] = $row; } }
        ?>
            <div class="card"><div class="card-header"><h3>All Students (<?= count($students); ?>)</h3></div><div class="card-body">
                <div class="search-box"><input type="text" class="form-control" placeholder="Search students..." id="studentSearch" onkeyup="filterTable('studentSearch','studentTable')"></div>
                <?php if (empty($students)): ?><div class="empty-state"><i class="fas fa-user-graduate"></i><p>No students found</p></div>
                <?php else: ?>
                    <div style="overflow-x:auto;"><table class="data-table" id="studentTable"><thead><tr><th>Student ID</th><th>Name</th><th>Programme</th><th>Project</th><th>Group Type</th><th>Email</th><th>Contact</th><th>Actions</th></tr></thead><tbody>
                        <?php foreach ($students as $s): ?>
                        <tr>
                            <td><?= htmlspecialchars($s['fyp_studfullid']); ?></td>
                            <td><?= htmlspecialchars($s['fyp_studname']); ?></td>
                            <td><?= htmlspecialchars($s['fyp_progname'] ?? '-'); ?></td>
                            <td><?= htmlspecialchars($s['fyp_projecttitle'] ?? '<span style="color:#64748b;">No Project</span>'); ?></td>
                            <td><?= htmlspecialchars($s['fyp_group'] ?? '-'); ?></td>
                            <td><?= htmlspecialchars($s['fyp_email'] ?? '-'); ?></td>
                            <td><?= htmlspecialchars($s['fyp_contactno'] ?? '-'); ?></td>
                            <td>
                                <button class="btn btn-primary btn-sm" onclick="openEditStudentModal('<?= $s['fyp_studid']; ?>','<?= htmlspecialchars($s['fyp_studname'], ENT_QUOTES); ?>','<?= htmlspecialchars($s['fyp_email'] ?? '', ENT_QUOTES); ?>','<?= htmlspecialchars($s['fyp_contactno'] ?? '', ENT_QUOTES); ?>','<?= $s['fyp_progid'] ?? 1; ?>','<?= htmlspecialchars($s['fyp_group'] ?? '', ENT_QUOTES); ?>')"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-danger btn-sm" onclick="confirmDeleteStudent('<?= $s['fyp_studid']; ?>','<?= htmlspecialchars($s['fyp_studname'], ENT_QUOTES); ?>')"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody></table></div>
                <?php endif; ?>
            </div></div>

        <!-- ==================== MANAGE SUPERVISORS ==================== -->
        <?php elseif ($current_page === 'supervisors'): 
            $supervisors = [];
            $res = $conn->query("SELECT s.*, u.fyp_username, u.fyp_usertype FROM supervisor s LEFT JOIN user u ON s.fyp_userid = u.fyp_userid ORDER BY s.fyp_name");
            if ($res) { while ($row = $res->fetch_assoc()) { $supervisors[] = $row; } }
        ?>
            <div class="card"><div class="card-header"><h3>All Supervisors (<?= count($supervisors); ?>)</h3></div><div class="card-body">
                <?php if (empty($supervisors)): ?><div class="empty-state"><i class="fas fa-user-tie"></i><p>No supervisors found</p></div>
                <?php else: ?>
                    <div style="overflow-x:auto;"><table class="data-table"><thead><tr><th>ID</th><th>Name</th><th>Room</th><th>Programme</th><th>Email</th><th>Contact</th><th>Specialization</th><th>Moderator</th></tr></thead><tbody>
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
                    </tbody></table></div>
                <?php endif; ?>
            </div></div>

        <!-- ==================== STUDENT-SUPERVISOR PAIRING ==================== -->
        <?php elseif ($current_page === 'pairing'): 
            $pairings = [];
            $res = $conn->query("SELECT p.*, pr.fyp_projecttitle, s.fyp_name as supervisor_name, 
                                 (SELECT fyp_name FROM supervisor WHERE fyp_supervisorid = p.fyp_moderatorid) as moderator_name,
                                 a.fyp_acdyear, a.fyp_intake
                                 FROM pairing p 
                                 LEFT JOIN project pr ON p.fyp_projectid = pr.fyp_projectid 
                                 LEFT JOIN supervisor s ON p.fyp_supervisorid = s.fyp_supervisorid 
                                 LEFT JOIN academic_year a ON p.fyp_academicid = a.fyp_academicid 
                                 ORDER BY p.fyp_datecreated DESC");
            if ($res) { while ($row = $res->fetch_assoc()) { $pairings[] = $row; } }
            
            $sup_list = [];
            $res = $conn->query("SELECT fyp_supervisorid, fyp_name FROM supervisor ORDER BY fyp_name");
            if ($res) { while ($row = $res->fetch_assoc()) { $sup_list[] = $row; } }
            
            $proj_list = [];
            $res = $conn->query("SELECT fyp_projectid, fyp_projecttitle FROM project ORDER BY fyp_projecttitle");
            if ($res) { while ($row = $res->fetch_assoc()) { $proj_list[] = $row; } }
        ?>
            <div class="card"><div class="card-header"><h3>Student-Supervisor Pairings (<?= count($pairings); ?>)</h3><button class="btn btn-primary" onclick="openModal('createPairingModal')"><i class="fas fa-plus"></i> Create Pairing</button></div><div class="card-body">
                <?php if (empty($pairings)): ?><div class="empty-state"><i class="fas fa-link"></i><p>No pairings found</p></div>
                <?php else: ?>
                    <div style="overflow-x:auto;"><table class="data-table"><thead><tr><th>ID</th><th>Project</th><th>Supervisor</th><th>Moderator</th><th>Type</th><th>Academic Year</th><th>Created</th></tr></thead><tbody>
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
                    </tbody></table></div>
                <?php endif; ?>
            </div></div>

        <!-- ==================== MANAGE PROJECTS ==================== -->
        <?php elseif ($current_page === 'projects'): 
            $projects = [];
            $res = $conn->query("SELECT p.*, s.fyp_name as supervisor_name, s.fyp_email as supervisor_email,
                                        (SELECT COUNT(*) FROM pairing WHERE fyp_projectid = p.fyp_projectid) as student_count
                                 FROM project p 
                                 LEFT JOIN supervisor s ON p.fyp_supervisorid = s.fyp_supervisorid 
                                 ORDER BY p.fyp_datecreated DESC");
            if ($res) { while ($row = $res->fetch_assoc()) { $projects[] = $row; } }
            
            // Get supervisors for dropdown
            $supervisors_list = [];
            $res = $conn->query("SELECT fyp_supervisorid, fyp_name, fyp_email FROM supervisor ORDER BY fyp_name");
            if ($res) { while ($row = $res->fetch_assoc()) { $supervisors_list[] = $row; } }
            
            $available_count = count(array_filter($projects, function($p) { return ($p['fyp_projectstatus'] ?? '') === 'Available'; }));
            $unavailable_count = count(array_filter($projects, function($p) { return ($p['fyp_projectstatus'] ?? '') === 'Unavailable'; }));
        ?>
            <!-- Stats Cards -->
            <div class="stats-grid" style="margin-bottom:25px;">
                <div class="stat-card" style="cursor:default;">
                    <div class="stat-icon purple"><i class="fas fa-folder-open"></i></div>
                    <div class="stat-info"><h4><?= count($projects); ?></h4><p>Total Projects</p></div>
                </div>
                <div class="stat-card" style="cursor:default;">
                    <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-info"><h4><?= $available_count; ?></h4><p>Available</p></div>
                </div>
                <div class="stat-card" style="cursor:default;">
                    <div class="stat-icon orange"><i class="fas fa-times-circle"></i></div>
                    <div class="stat-info"><h4><?= $unavailable_count; ?></h4><p>Unavailable</p></div>
                </div>
            </div>
            
            <!-- Main Projects Card -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-project-diagram"></i> Project Allocation</h3>
                    <button class="btn btn-primary" onclick="openModal('addProjectModal')">
                        <i class="fas fa-plus"></i> Add New Project
                    </button>
                </div>
                <div class="card-body">
                    <!-- Filter Controls -->
                    <div class="filter-controls" style="display:flex;flex-wrap:wrap;gap:15px;margin-bottom:20px;padding:15px;background:rgba(139,92,246,0.1);border-radius:12px;">
                        <div style="flex:1;min-width:200px;">
                            <label style="color:#a78bfa;font-size:0.85rem;display:block;margin-bottom:5px;"><i class="fas fa-search"></i> Search</label>
                            <input type="text" id="projectSearch" class="form-control" placeholder="Search by title, supervisor..." onkeyup="filterProjectTable()">
                        </div>
                        <div style="min-width:150px;">
                            <label style="color:#a78bfa;font-size:0.85rem;display:block;margin-bottom:5px;"><i class="fas fa-filter"></i> Status</label>
                            <select id="filterProjectStatus" class="form-control" onchange="filterProjectTable()">
                                <option value="all">All Status</option>
                                <option value="Available">Available</option>
                                <option value="Unavailable">Unavailable</option>
                            </select>
                        </div>
                        <div style="min-width:150px;">
                            <label style="color:#a78bfa;font-size:0.85rem;display:block;margin-bottom:5px;"><i class="fas fa-tags"></i> Type</label>
                            <select id="filterProjectType" class="form-control" onchange="filterProjectTable()">
                                <option value="all">All Types</option>
                                <option value="Research">Research</option>
                                <option value="Application">Application</option>
                                <option value="Package">Package</option>
                            </select>
                        </div>
                        <div style="min-width:150px;">
                            <label style="color:#a78bfa;font-size:0.85rem;display:block;margin-bottom:5px;"><i class="fas fa-sort"></i> Sort By</label>
                            <select id="sortProject" class="form-control" onchange="sortProjectTable()">
                                <option value="newest">Newest First</option>
                                <option value="oldest">Oldest First</option>
                                <option value="title_asc">Title (A-Z)</option>
                                <option value="title_desc">Title (Z-A)</option>
                            </select>
                        </div>
                    </div>
                    
                    <?php if (empty($projects)): ?>
                        <div class="empty-state"><i class="fas fa-folder-open"></i><p>No projects found. Click "Add New Project" to create one.</p></div>
                    <?php else: ?>
                        <div style="overflow-x:auto;">
                            <table class="data-table" id="projectTable">
                                <thead>
                                    <tr>
                                        <th style="width:50px;">ID</th>
                                        <th>Project Title</th>
                                        <th>Type</th>
                                        <th>Category</th>
                                        <th>Status</th>
                                        <th>Supervisor</th>
                                        <th>Students</th>
                                        <th>Created</th>
                                        <th style="width:120px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($projects as $p): ?>
                                    <tr data-title="<?= htmlspecialchars(strtolower($p['fyp_projecttitle'])); ?>"
                                        data-status="<?= $p['fyp_projectstatus'] ?? ''; ?>"
                                        data-type="<?= $p['fyp_projecttype'] ?? ''; ?>"
                                        data-date="<?= strtotime($p['fyp_datecreated'] ?? 'now'); ?>"
                                        data-supervisor="<?= htmlspecialchars(strtolower($p['supervisor_name'] ?? '')); ?>">
                                        <td><?= $p['fyp_projectid']; ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($p['fyp_projecttitle']); ?></strong>
                                            <?php if (!empty($p['fyp_projectdesc'])): ?>
                                                <br><small style="color:#64748b;"><?= htmlspecialchars(substr($p['fyp_projectdesc'], 0, 80)); ?><?= strlen($p['fyp_projectdesc']) > 80 ? '...' : ''; ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?= strtolower($p['fyp_projecttype'] ?? 'default'); ?>">
                                                <?= $p['fyp_projecttype'] ?? '-'; ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($p['fyp_projectcat'] ?? '-'); ?></td>
                                        <td>
                                            <span class="badge badge-<?= ($p['fyp_projectstatus'] ?? '') === 'Available' ? 'approved' : 'pending'; ?>">
                                                <?= $p['fyp_projectstatus'] ?? '-'; ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($p['supervisor_name'] ?? '-'); ?></td>
                                        <td style="text-align:center;">
                                            <span class="badge" style="background:rgba(139,92,246,0.2);color:#a78bfa;">
                                                <?= $p['student_count']; ?>/<?= $p['fyp_maxstudent'] ?? 2; ?>
                                            </span>
                                        </td>
                                        <td><?= $p['fyp_datecreated'] ? date('M j, Y', strtotime($p['fyp_datecreated'])) : '-'; ?></td>
                                        <td>
                                            <button class="btn btn-primary btn-sm" onclick="openEditProjectModal(<?= htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8'); ?>)" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-info btn-sm" onclick="viewProjectDetails(<?= htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8'); ?>)" title="View">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-danger btn-sm" onclick="confirmDeleteProject(<?= $p['fyp_projectid']; ?>, '<?= htmlspecialchars($p['fyp_projecttitle'], ENT_QUOTES); ?>')" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
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
                    <div class="modal-header">
                        <h3><i class="fas fa-plus-circle" style="color:#34d399;"></i> Add New Project</h3>
                        <button class="modal-close" onclick="closeModal('addProjectModal')">&times;</button>
                    </div>
                    <form method="POST">
                        <div class="modal-body" style="max-height:70vh;overflow-y:auto;">
                            <div class="form-row" style="display:grid;grid-template-columns:2fr 1fr;gap:15px;">
                                <div class="form-group">
                                    <label>Project Title <span style="color:#f87171;">*</span></label>
                                    <input type="text" name="project_title" class="form-control" placeholder="Enter project title" required>
                                </div>
                                <div class="form-group">
                                    <label>Project Type <span style="color:#f87171;">*</span></label>
                                    <select name="project_type" class="form-control" required>
                                        <option value="Research">Research</option>
                                        <option value="Application" selected>Application</option>
                                        <option value="Package">Package</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                                <div class="form-group">
                                    <label>Project Status <span style="color:#f87171;">*</span></label>
                                    <select name="project_status" class="form-control" required>
                                        <option value="Available" selected>Available</option>
                                        <option value="Unavailable">Unavailable</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Category</label>
                                    <input type="text" name="project_category" class="form-control" placeholder="e.g. Web, Mobile, AI">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Description</label>
                                <textarea name="project_description" class="form-control" rows="4" placeholder="Enter project description..."></textarea>
                            </div>
                            
                            <div class="form-row" style="display:grid;grid-template-columns:2fr 1fr;gap:15px;">
                                <div class="form-group">
                                    <label>Contact Person / Supervisor</label>
                                    <select name="supervisor_id" class="form-control">
                                        <option value="">-- Select Supervisor --</option>
                                        <?php foreach ($supervisors_list as $sv): ?>
                                        <option value="<?= $sv['fyp_supervisorid']; ?>"><?= htmlspecialchars($sv['fyp_name'] . ' (' . $sv['fyp_email'] . ')'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Max Students <span style="color:#f87171;">*</span></label>
                                    <input type="number" name="max_students" class="form-control" value="2" min="1" max="10" required>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="closeModal('addProjectModal')">Cancel</button>
                            <button type="submit" name="add_project" class="btn btn-success">
                                <i class="fas fa-plus"></i> Add Project
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Edit Project Modal -->
            <div class="modal-overlay" id="editProjectModal">
                <div class="modal" style="max-width:700px;">
                    <div class="modal-header">
                        <h3><i class="fas fa-edit" style="color:#60a5fa;"></i> Edit Project</h3>
                        <button class="modal-close" onclick="closeModal('editProjectModal')">&times;</button>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="project_id" id="edit_project_id">
                        <div class="modal-body" style="max-height:70vh;overflow-y:auto;">
                            <div class="form-row" style="display:grid;grid-template-columns:2fr 1fr;gap:15px;">
                                <div class="form-group">
                                    <label>Project Title <span style="color:#f87171;">*</span></label>
                                    <input type="text" name="project_title" id="edit_project_title" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label>Project Type <span style="color:#f87171;">*</span></label>
                                    <select name="project_type" id="edit_project_type" class="form-control" required>
                                        <option value="Research">Research</option>
                                        <option value="Application">Application</option>
                                        <option value="Package">Package</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                                <div class="form-group">
                                    <label>Project Status <span style="color:#f87171;">*</span></label>
                                    <select name="project_status" id="edit_project_status" class="form-control" required>
                                        <option value="Available">Available</option>
                                        <option value="Unavailable">Unavailable</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Category</label>
                                    <input type="text" name="project_category" id="edit_project_category" class="form-control">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Description</label>
                                <textarea name="project_description" id="edit_project_description" class="form-control" rows="4"></textarea>
                            </div>
                            
                            <div class="form-row" style="display:grid;grid-template-columns:2fr 1fr;gap:15px;">
                                <div class="form-group">
                                    <label>Contact Person / Supervisor</label>
                                    <select name="supervisor_id" id="edit_supervisor_id" class="form-control">
                                        <option value="">-- Select Supervisor --</option>
                                        <?php foreach ($supervisors_list as $sv): ?>
                                        <option value="<?= $sv['fyp_supervisorid']; ?>"><?= htmlspecialchars($sv['fyp_name'] . ' (' . $sv['fyp_email'] . ')'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Max Students <span style="color:#f87171;">*</span></label>
                                    <input type="number" name="max_students" id="edit_max_students" class="form-control" min="1" max="10" required>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="closeModal('editProjectModal')">Cancel</button>
                            <button type="button" class="btn btn-danger" onclick="resetProjectForm()" style="margin-right:auto;">
                                <i class="fas fa-undo"></i> Reset Fields
                            </button>
                            <button type="submit" name="update_project" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Project
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- View Project Modal -->
            <div class="modal-overlay" id="viewProjectModal">
                <div class="modal" style="max-width:600px;">
                    <div class="modal-header">
                        <h3><i class="fas fa-folder-open" style="color:#a78bfa;"></i> Project Details</h3>
                        <button class="modal-close" onclick="closeModal('viewProjectModal')">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div id="viewProjectContent"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('viewProjectModal')">Close</button>
                    </div>
                </div>
            </div>
            
            <!-- Delete Project Modal -->
            <div class="modal-overlay" id="deleteProjectModal">
                <div class="modal" style="max-width:450px;">
                    <div class="modal-header">
                        <h3><i class="fas fa-trash-alt" style="color:#f87171;"></i> Delete Project</h3>
                        <button class="modal-close" onclick="closeModal('deleteProjectModal')">&times;</button>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="project_id" id="delete_project_id">
                        <div class="modal-body">
                            <div style="text-align:center;padding:20px;">
                                <i class="fas fa-exclamation-triangle" style="font-size:3rem;color:#f87171;margin-bottom:15px;"></i>
                                <p style="color:#e2e8f0;margin-bottom:10px;">Are you sure you want to delete this project?</p>
                                <p style="color:#f87171;font-weight:600;" id="delete_project_title"></p>
                                <p style="color:#94a3b8;font-size:0.85rem;margin-top:15px;">This action cannot be undone.</p>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="closeModal('deleteProjectModal')">Cancel</button>
                            <button type="submit" name="delete_project" class="btn btn-danger">
                                <i class="fas fa-trash"></i> Delete Project
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        <!-- ==================== STUDENT MODERATION ==================== -->
        <?php elseif ($current_page === 'moderation'): 
            $moderations = [];
            $res = $conn->query("SELECT sm.*, s.fyp_studname, s.fyp_studfullid, mc.fyp_criterianame, mc.fyp_criteriadesc 
                                 FROM student_moderation sm 
                                 LEFT JOIN student s ON sm.fyp_studid = s.fyp_studid 
                                 LEFT JOIN moderation_criteria mc ON sm.fyp_mdcriteriaid = mc.fyp_mdcriteriaid 
                                 ORDER BY sm.fyp_studid");
            if ($res) { while ($row = $res->fetch_assoc()) { $moderations[] = $row; } }
        ?>
            <div class="card"><div class="card-header"><h3>Student Moderation Records</h3></div><div class="card-body">
                <?php if (empty($moderations)): ?><div class="empty-state"><i class="fas fa-clipboard-check"></i><p>No moderation records found</p></div>
                <?php else: ?>
                    <table class="data-table"><thead><tr><th>Student ID</th><th>Student Name</th><th>Criteria</th><th>Description</th><th>Comply</th></tr></thead><tbody>
                        <?php foreach ($moderations as $m): ?>
                        <tr>
                            <td><?= htmlspecialchars($m['fyp_studfullid'] ?? $m['fyp_studid']); ?></td>
                            <td><?= htmlspecialchars($m['fyp_studname'] ?? '-'); ?></td>
                            <td><?= htmlspecialchars($m['fyp_criterianame'] ?? '-'); ?></td>
                            <td><?= htmlspecialchars($m['fyp_criteriadesc'] ?? '-'); ?></td>
                            <td><?= $m['fyp_comply'] ? '<span class="badge badge-approved">Yes</span>' : '<span class="badge badge-pending">No</span>'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody></table>
                <?php endif; ?>
            </div></div>

        <!-- ==================== RUBRICS ASSESSMENT ==================== -->
        <?php elseif ($current_page === 'rubrics'): 
            // Get action for sub-pages
            $rubrics_action = $_GET['action'] ?? 'sets';
            
            // Get all sets with academic year info
            $sets = [];
            $res = $conn->query("SELECT s.*, a.fyp_acdyear, a.fyp_intake FROM `set` s LEFT JOIN academic_year a ON s.fyp_academicid = a.fyp_academicid ORDER BY s.fyp_setid DESC");
            if ($res) { while ($row = $res->fetch_assoc()) { $sets[] = $row; } }
            
            // Get all items
            $items = [];
            $res = $conn->query("SELECT * FROM item ORDER BY fyp_itemid");
            if ($res) { while ($row = $res->fetch_assoc()) { $items[] = $row; } }
            
            // Get all assessment criteria (Poor, Pass, Credit, etc.)
            $criteria = [];
            $res = $conn->query("SELECT * FROM assessment_criteria ORDER BY fyp_min ASC");
            if ($res) { while ($row = $res->fetch_assoc()) { $criteria[] = $row; } }
            
            // Get all marking criteria (Introduction, Lit Review, etc.)
            $marking_criteria = [];
            $res = $conn->query("SELECT * FROM marking_criteria ORDER BY fyp_criteriaid");
            if ($res) { while ($row = $res->fetch_assoc()) { $marking_criteria[] = $row; } }
            
            // Get item-marking criteria links
            $item_marking_links = [];
            $res = $conn->query("SELECT imc.*, i.fyp_itemname, mc.fyp_criterianame, mc.fyp_percentallocation 
                                 FROM item_marking_criteria imc 
                                 LEFT JOIN item i ON imc.fyp_itemid = i.fyp_itemid 
                                 LEFT JOIN marking_criteria mc ON imc.fyp_criteriaid = mc.fyp_criteriaid 
                                 ORDER BY imc.fyp_itemid, imc.fyp_criteriaid");
            if ($res) { while ($row = $res->fetch_assoc()) { $item_marking_links[] = $row; } }
        ?>
            <!-- Rubrics Sub-Navigation -->
            <div class="card" style="margin-bottom:20px;">
                <div class="card-body" style="padding:15px;">
                    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                        <a href="?page=rubrics&action=sets" class="btn <?= $rubrics_action === 'sets' ? 'btn-primary' : 'btn-secondary'; ?>">
                            <i class="fas fa-layer-group"></i> Assessment Sets
                        </a>
                        <a href="?page=rubrics&action=items" class="btn <?= $rubrics_action === 'items' ? 'btn-primary' : 'btn-secondary'; ?>">
                            <i class="fas fa-list-ol"></i> Assessment Items
                        </a>
                        <a href="?page=rubrics&action=criteria" class="btn <?= $rubrics_action === 'criteria' ? 'btn-primary' : 'btn-secondary'; ?>">
                            <i class="fas fa-star"></i> Assessment Criteria
                        </a>
                        <a href="?page=rubrics&action=marking" class="btn <?= $rubrics_action === 'marking' ? 'btn-primary' : 'btn-secondary'; ?>">
                            <i class="fas fa-percent"></i> Marking Criteria
                        </a>
                        <a href="?page=rubrics&action=link" class="btn <?= $rubrics_action === 'link' ? 'btn-warning' : 'btn-secondary'; ?>">
                            <i class="fas fa-link"></i> Link Items & Criteria
                        </a>
                        <span style="border-left:2px solid rgba(139,92,246,0.3);margin:0 10px;height:30px;"></span>
                        <a href="?page=marks" class="btn btn-success">
                            <i class="fas fa-calculator"></i> Go to Marks
                        </a>
                        <a href="Coordinator_rubrics.php" class="btn btn-info" title="Open Standalone Rubrics Manager">
                            <i class="fas fa-external-link-alt"></i> Advanced
                        </a>
                    </div>
                </div>
            </div>
            
            <?php if ($rubrics_action === 'sets'): ?>
            <!-- ========== ASSESSMENT SETS ========== -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-layer-group" style="color:#a78bfa;"></i> Assessment Sets</h3>
                    <button class="btn btn-primary" onclick="openModal('createSetModal')">
                        <i class="fas fa-plus"></i> Create Set
                    </button>
                </div>
                <div class="card-body">
                    <p style="color:#94a3b8;margin-bottom:20px;"><i class="fas fa-info-circle"></i> Assessment sets define the evaluation structure for each academic year/intake for Diploma IT FYP.</p>
                    
                    <?php if (empty($sets)): ?>
                        <div class="empty-state">
                            <i class="fas fa-layer-group"></i>
                            <p>No assessment sets found</p>
                            <button class="btn btn-primary" onclick="openModal('createSetModal')">Create First Set</button>
                        </div>
                    <?php else: ?>
                        <div style="overflow-x:auto;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Set Name</th>
                                        <th>Academic Year</th>
                                        <th>Intake</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sets as $s): ?>
                                    <tr>
                                        <td><?= $s['fyp_setid']; ?></td>
                                        <td>
                                            <strong style="color:#a78bfa;">
                                                <i class="fas fa-graduation-cap"></i> 
                                                FYP <?= htmlspecialchars($s['fyp_acdyear'] ?? ''); ?> - <?= htmlspecialchars($s['fyp_intake'] ?? ''); ?>
                                            </strong>
                                        </td>
                                        <td><?= htmlspecialchars($s['fyp_acdyear'] ?? '-'); ?></td>
                                        <td><span class="badge badge-approved"><?= htmlspecialchars($s['fyp_intake'] ?? '-'); ?></span></td>
                                        <td>
                                            <button class="btn btn-info btn-sm" onclick="editSet(<?= $s['fyp_setid']; ?>, <?= $s['fyp_academicid'] ?? 0; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-danger btn-sm" onclick="deleteSet(<?= $s['fyp_setid']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Create/Edit Set Modal -->
            <div class="modal-overlay" id="createSetModal">
                <div class="modal" style="max-width:500px;">
                    <div class="modal-header">
                        <h3><i class="fas fa-layer-group" style="color:#a78bfa;"></i> <span id="setModalTitle">Create Assessment Set</span></h3>
                        <button class="modal-close" onclick="closeModal('createSetModal')">&times;</button>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="set_id" id="edit_set_id" value="">
                        <input type="hidden" name="project_phase" value="1">
                        <div class="modal-body">
                            <div class="form-group">
                                <label>Academic Year <span style="color:#f87171;">*</span></label>
                                <select name="academic_id" id="set_academic_id" class="form-control" required>
                                    <option value="">-- Select Academic Year --</option>
                                    <?php foreach ($academic_years as $ay): ?>
                                    <option value="<?= $ay['fyp_academicid']; ?>"><?= htmlspecialchars($ay['fyp_acdyear'] . ' - ' . $ay['fyp_intake']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="closeModal('createSetModal')">Cancel</button>
                            <button type="submit" name="save_set" class="btn btn-primary">
                                <i class="fas fa-save"></i> Create Set
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php elseif ($rubrics_action === 'items'): ?>
            <!-- ========== ASSESSMENT ITEMS ========== -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list-ol" style="color:#fb923c;"></i> Assessment Items</h3>
                    <button class="btn btn-primary" onclick="openModal('createItemModal')">
                        <i class="fas fa-plus"></i> Add New Item
                    </button>
                </div>
                <div class="card-body">
                    <?php if (empty($items)): ?>
                        <div class="empty-state">
                            <i class="fas fa-list-ol"></i>
                            <p>No assessment items found</p>
                            <button class="btn btn-primary" onclick="openModal('createItemModal')">Add First Item</button>
                        </div>
                    <?php else: ?>
                        <div style="overflow-x:auto;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Item Name</th>
                                        <th>Doc?</th>
                                        <th>Mark %</th>
                                        <th>Start Date</th>
                                        <th>Deadline</th>
                                        <th>Moderation</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td><?= $item['fyp_itemid']; ?></td>
                                        <td><strong><?= htmlspecialchars($item['fyp_itemname']); ?></strong></td>
                                        <td>
                                            <?php if ($item['fyp_isdocument'] ?? 1): ?>
                                                <span style="color:#34d399;"><i class="fas fa-check-circle"></i> Yes</span>
                                            <?php else: ?>
                                                <span style="color:#f87171;"><i class="fas fa-times-circle"></i> No</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?= number_format($item['fyp_originalmarkallocation'] ?? 0, 1); ?>%</strong></td>
                                        <td><?= $item['fyp_startdate'] ? date('d/m/Y', strtotime($item['fyp_startdate'])) : '-'; ?></td>
                                        <td><?= $item['fyp_finaldeadline'] ? date('d/m/Y', strtotime($item['fyp_finaldeadline'])) : '-'; ?></td>
                                        <td>
                                            <?php if ($item['fyp_ismoderation'] ?? 0): ?>
                                                <span style="color:#34d399;"><i class="fas fa-check-circle"></i> Yes</span>
                                            <?php else: ?>
                                                <span style="color:#f87171;"><i class="fas fa-times-circle"></i> No</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-info btn-sm" onclick="editItem(<?= htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8'); ?>)" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-danger btn-sm" onclick="deleteItem(<?= $item['fyp_itemid']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Total Mark Allocation -->
                        <?php 
                        $total_mark = 0;
                        foreach ($items as $item) {
                            $total_mark += floatval($item['fyp_originalmarkallocation'] ?? 0);
                        }
                        ?>
                        <div style="margin-top:20px;padding:15px;background:<?= $total_mark == 100 ? 'rgba(16,185,129,0.1)' : 'rgba(249,115,22,0.1)'; ?>;border-radius:10px;border:1px solid <?= $total_mark == 100 ? 'rgba(16,185,129,0.3)' : 'rgba(249,115,22,0.3)'; ?>;">
                            <strong style="color:<?= $total_mark == 100 ? '#34d399' : '#fb923c'; ?>;">
                                <i class="fas fa-<?= $total_mark == 100 ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                                Total Mark Allocation: <?= number_format($total_mark, 1); ?>%
                                <?= $total_mark != 100 ? ' (Should be 100%)' : ''; ?>
                            </strong>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Create/Edit Item Modal -->
            <div class="modal-overlay" id="createItemModal">
                <div class="modal" style="max-width:600px;">
                    <div class="modal-header">
                        <h3><i class="fas fa-list-ol" style="color:#fb923c;"></i> <span id="itemModalTitle">Add Assessment Item</span></h3>
                        <button class="modal-close" onclick="closeModal('createItemModal')">&times;</button>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="item_id" id="edit_item_id" value="">
                        <div class="modal-body">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Item Name <span style="color:#f87171;">*</span></label>
                                    <input type="text" name="item_name" id="item_name" class="form-control" placeholder="e.g., Proposal, Chapter 1" required>
                                </div>
                                <div class="form-group">
                                    <label>Mark Allocation (%)</label>
                                    <input type="number" name="item_mark" id="item_mark" class="form-control" placeholder="20" min="0" max="100" step="0.001">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Document Required?</label>
                                    <div style="display:flex;gap:20px;padding:10px 0;">
                                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;color:#e2e8f0;">
                                            <input type="radio" name="item_doc" id="item_doc_yes" value="1" checked style="width:18px;height:18px;"> Yes
                                        </label>
                                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;color:#e2e8f0;">
                                            <input type="radio" name="item_doc" id="item_doc_no" value="0" style="width:18px;height:18px;"> No
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Moderation Required?</label>
                                    <div style="display:flex;gap:20px;padding:10px 0;">
                                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;color:#e2e8f0;">
                                            <input type="radio" name="item_moderate" id="item_mod_yes" value="1" style="width:18px;height:18px;"> Yes
                                        </label>
                                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;color:#e2e8f0;">
                                            <input type="radio" name="item_moderate" id="item_mod_no" value="0" checked style="width:18px;height:18px;"> No
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Start Date</label>
                                    <input type="datetime-local" name="item_start" id="item_start" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label>Deadline</label>
                                    <input type="datetime-local" name="item_deadline" id="item_deadline" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="closeModal('createItemModal')">Cancel</button>
                            <button type="submit" name="save_item" class="btn btn-success">
                                <i class="fas fa-save"></i> Save Item
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php elseif ($rubrics_action === 'criteria'): ?>
            <!-- ========== ASSESSMENT CRITERIA (Poor, Pass, Credit, etc.) ========== -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-star" style="color:#fbbf24;"></i> Assessment Criteria</h3>
                    <button class="btn btn-primary" onclick="openModal('createCriteriaModal')">
                        <i class="fas fa-plus"></i> Add Criteria
                    </button>
                </div>
                <div class="card-body">
                    <p style="color:#94a3b8;margin-bottom:20px;"><i class="fas fa-info-circle"></i> Assessment criteria define performance/grade levels with mark ranges.</p>
                    
                    <?php if (empty($criteria)): ?>
                        <div class="empty-state">
                            <i class="fas fa-star"></i>
                            <p>No assessment criteria found</p>
                            <button class="btn btn-primary" onclick="openModal('createCriteriaModal')">Add First Criteria</button>
                        </div>
                    <?php else: ?>
                        <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(220px, 1fr));gap:20px;">
                            <?php foreach ($criteria as $c): ?>
                            <div style="background:rgba(251,191,36,0.1);padding:20px;border-radius:12px;border:1px solid rgba(251,191,36,0.2);">
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                                    <h4 style="color:#fbbf24;margin:0;"><?= htmlspecialchars($c['fyp_assessmentcriterianame']); ?></h4>
                                </div>
                                <div style="background:rgba(0,0,0,0.2);padding:10px;border-radius:8px;margin-bottom:15px;">
                                    <span style="color:#e2e8f0;font-size:1.2rem;font-weight:600;"><?= $c['fyp_min']; ?> - <?= $c['fyp_max']; ?></span>
                                    <span style="color:#94a3b8;font-size:0.85rem;"> marks</span>
                                </div>
                                <p style="color:#94a3b8;font-size:0.85rem;margin-bottom:15px;"><?= htmlspecialchars($c['fyp_description'] ?? 'No description'); ?></p>
                                <div style="display:flex;gap:8px;">
                                    <button class="btn btn-info btn-sm" onclick="editCriteria(<?= $c['fyp_assessmentcriteriaid']; ?>, '<?= htmlspecialchars($c['fyp_assessmentcriterianame'], ENT_QUOTES); ?>', <?= $c['fyp_min']; ?>, <?= $c['fyp_max']; ?>, '<?= htmlspecialchars($c['fyp_description'] ?? '', ENT_QUOTES); ?>')">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="deleteCriteria(<?= $c['fyp_assessmentcriteriaid']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Create/Edit Criteria Modal -->
            <div class="modal-overlay" id="createCriteriaModal">
                <div class="modal" style="max-width:500px;">
                    <div class="modal-header">
                        <h3><i class="fas fa-star" style="color:#fbbf24;"></i> <span id="criteriaModalTitle">Add Assessment Criteria</span></h3>
                        <button class="modal-close" onclick="closeModal('createCriteriaModal')">&times;</button>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="criteria_id" id="edit_criteria_id" value="">
                        <div class="modal-body">
                            <div class="form-group">
                                <label>Criteria Name <span style="color:#f87171;">*</span></label>
                                <input type="text" name="crit_name" id="crit_name" class="form-control" placeholder="e.g., Poor, Pass, Credit, Distinction" required>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Min Mark</label>
                                    <input type="number" name="crit_min" id="crit_min" class="form-control" placeholder="0" min="0" max="100" value="0">
                                </div>
                                <div class="form-group">
                                    <label>Max Mark</label>
                                    <input type="number" name="crit_max" id="crit_max" class="form-control" placeholder="100" min="0" max="100" value="100">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Description</label>
                                <textarea name="crit_desc" id="crit_desc" class="form-control" rows="3" placeholder="e.g., Below expectations, Meets expectations"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="closeModal('createCriteriaModal')">Cancel</button>
                            <button type="submit" name="save_criteria" class="btn btn-success">
                                <i class="fas fa-save"></i> Save Assessment Criteria
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php elseif ($rubrics_action === 'marking'): ?>
            <!-- ========== MARKING CRITERIA (Introduction, Lit Review, etc.) ========== -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-percent" style="color:#60a5fa;"></i> Marking Criteria</h3>
                    <button class="btn btn-primary" onclick="openModal('createMarkingModal')">
                        <i class="fas fa-plus"></i> Add Marking Criteria
                    </button>
                </div>
                <div class="card-body">
                    <p style="color:#94a3b8;margin-bottom:20px;"><i class="fas fa-info-circle"></i> Marking criteria define what aspects are assessed (e.g., Introduction, Methodology) with percentage allocation.</p>
                    
                    <?php if (empty($marking_criteria)): ?>
                        <div class="empty-state">
                            <i class="fas fa-percent"></i>
                            <p>No marking criteria found</p>
                            <button class="btn btn-primary" onclick="openModal('createMarkingModal')">Add First Marking Criteria</button>
                        </div>
                    <?php else: ?>
                        <div style="overflow-x:auto;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Criteria Name</th>
                                        <th>% Allocation</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($marking_criteria as $mc): ?>
                                    <tr>
                                        <td><?= $mc['fyp_criteriaid']; ?></td>
                                        <td><strong><?= htmlspecialchars($mc['fyp_criterianame']); ?></strong></td>
                                        <td><span class="badge badge-approved"><?= number_format($mc['fyp_percentallocation'], 1); ?>%</span></td>
                                        <td>
                                            <button class="btn btn-info btn-sm" onclick="editMarking(<?= $mc['fyp_criteriaid']; ?>, '<?= htmlspecialchars($mc['fyp_criterianame'], ENT_QUOTES); ?>', <?= $mc['fyp_percentallocation']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-danger btn-sm" onclick="deleteMarking(<?= $mc['fyp_criteriaid']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Total Percentage -->
                        <?php 
                        $total_percent = 0;
                        foreach ($marking_criteria as $mc) {
                            $total_percent += floatval($mc['fyp_percentallocation'] ?? 0);
                        }
                        ?>
                        <div style="margin-top:20px;padding:15px;background:<?= $total_percent == 100 ? 'rgba(16,185,129,0.1)' : 'rgba(249,115,22,0.1)'; ?>;border-radius:10px;border:1px solid <?= $total_percent == 100 ? 'rgba(16,185,129,0.3)' : 'rgba(249,115,22,0.3)'; ?>;">
                            <strong style="color:<?= $total_percent == 100 ? '#34d399' : '#fb923c'; ?>;">
                                <i class="fas fa-<?= $total_percent == 100 ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                                Total Percentage: <?= number_format($total_percent, 1); ?>%
                                <?= $total_percent != 100 ? ' (Should be 100%)' : ''; ?>
                            </strong>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Link Items to Marking Criteria -->
            <div class="card" style="margin-top:25px;">
                <div class="card-header">
                    <h3><i class="fas fa-link" style="color:#a78bfa;"></i> Item-Marking Criteria Links</h3>
                    <button class="btn btn-primary" onclick="openModal('linkItemCriteriaModal')">
                        <i class="fas fa-plus"></i> Link Item to Criteria
                    </button>
                </div>
                <div class="card-body">
                    <?php if (empty($item_marking_links)): ?>
                        <div class="empty-state">
                            <i class="fas fa-link"></i>
                            <p>No links found. Link items to marking criteria.</p>
                        </div>
                    <?php else: ?>
                        <?php 
                        // Group by item
                        $grouped_links = [];
                        foreach ($item_marking_links as $link) {
                            $item_name = $link['fyp_itemname'] ?? 'Unknown Item';
                            $grouped_links[$item_name][] = $link;
                        }
                        ?>
                        <?php foreach ($grouped_links as $item_name => $links): ?>
                        <div style="background:rgba(139,92,246,0.1);padding:15px;border-radius:12px;margin-bottom:15px;border:1px solid rgba(139,92,246,0.2);">
                            <h4 style="color:#a78bfa;margin-bottom:10px;"><i class="fas fa-file-alt"></i> <?= htmlspecialchars($item_name); ?></h4>
                            <div style="display:flex;flex-wrap:wrap;gap:10px;">
                                <?php foreach ($links as $link): ?>
                                <span style="background:rgba(59,130,246,0.2);padding:8px 15px;border-radius:20px;color:#60a5fa;font-size:0.85rem;">
                                    <?= htmlspecialchars($link['fyp_criterianame']); ?> (<?= number_format($link['fyp_percentallocation'], 1); ?>%)
                                    <button onclick="unlinkItemCriteria(<?= $link['fyp_itemid']; ?>, <?= $link['fyp_criteriaid']; ?>)" style="background:none;border:none;color:#f87171;cursor:pointer;margin-left:5px;"><i class="fas fa-times"></i></button>
                                </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Create Marking Criteria Modal -->
            <div class="modal-overlay" id="createMarkingModal">
                <div class="modal" style="max-width:500px;">
                    <div class="modal-header">
                        <h3><i class="fas fa-percent" style="color:#60a5fa;"></i> <span id="markingModalTitle">Add Marking Criteria</span></h3>
                        <button class="modal-close" onclick="closeModal('createMarkingModal')">&times;</button>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="marking_id" id="edit_marking_id" value="">
                        <div class="modal-body">
                            <div class="form-group">
                                <label>Criteria Name <span style="color:#f87171;">*</span></label>
                                <input type="text" name="marking_name" id="marking_name" class="form-control" placeholder="e.g., Introduction, Methodology, Results" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Percentage Allocation</label>
                                <input type="number" name="marking_percent" id="marking_percent" class="form-control" placeholder="20" min="0" max="100" step="0.001">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="closeModal('createMarkingModal')">Cancel</button>
                            <button type="submit" name="save_marking" class="btn btn-success">
                                <i class="fas fa-save"></i> Save Marking Criteria
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Link Item to Criteria Modal -->
            <div class="modal-overlay" id="linkItemCriteriaModal">
                <div class="modal" style="max-width:500px;">
                    <div class="modal-header">
                        <h3><i class="fas fa-link" style="color:#a78bfa;"></i> Link Item to Marking Criteria</h3>
                        <button class="modal-close" onclick="closeModal('linkItemCriteriaModal')">&times;</button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <div class="form-group">
                                <label>Assessment Item <span style="color:#f87171;">*</span></label>
                                <select name="link_itemid" class="form-control" required>
                                    <option value="">-- Select Item --</option>
                                    <?php foreach ($items as $item): ?>
                                    <option value="<?= $item['fyp_itemid']; ?>"><?= htmlspecialchars($item['fyp_itemname']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Marking Criteria <span style="color:#f87171;">*</span></label>
                                <select name="link_criteriaid" class="form-control" required>
                                    <option value="">-- Select Criteria --</option>
                                    <?php foreach ($marking_criteria as $mc): ?>
                                    <option value="<?= $mc['fyp_criteriaid']; ?>"><?= htmlspecialchars($mc['fyp_criterianame']); ?> (<?= number_format($mc['fyp_percentallocation'], 1); ?>%)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="closeModal('linkItemCriteriaModal')">Cancel</button>
                            <button type="submit" name="link_item_criteria" class="btn btn-success">
                                <i class="fas fa-link"></i> Link
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php elseif ($rubrics_action === 'link'): ?>
            <!-- ========== LINK ITEMS & CRITERIA ========== -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-link" style="color:#fbbf24;"></i> Link Items & Marking Criteria</h3>
                    <button class="btn btn-primary" onclick="openModal('linkItemCriteriaModal2')">
                        <i class="fas fa-plus"></i> Create Link
                    </button>
                </div>
                <div class="card-body">
                    <div class="alert alert-info" style="background:rgba(59,130,246,0.1);border:1px solid rgba(59,130,246,0.3);color:#60a5fa;">
                        <i class="fas fa-info-circle"></i>
                        <strong>Important:</strong> You must link marking criteria to assessment items before you can mark students. Each item can have multiple criteria linked to it.
                    </div>
                    
                    <?php if (empty($items)): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> No assessment items found. Please <a href="?page=rubrics&action=items" style="color:#fbbf24;">create items first</a>.
                        </div>
                    <?php elseif (empty($marking_criteria)): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> No marking criteria found. Please <a href="?page=rubrics&action=marking" style="color:#fbbf24;">create marking criteria first</a>.
                        </div>
                    <?php else: ?>
                    
                    <!-- Group Links by Item -->
                    <?php 
                    $links_by_item = [];
                    foreach ($items as $item) {
                        $links_by_item[$item['fyp_itemid']] = [
                            'item' => $item,
                            'criteria' => []
                        ];
                    }
                    foreach ($item_marking_links as $link) {
                        if (isset($links_by_item[$link['fyp_itemid']])) {
                            $links_by_item[$link['fyp_itemid']]['criteria'][] = $link;
                        }
                    }
                    ?>
                    
                    <div style="display:grid;gap:20px;">
                        <?php foreach ($links_by_item as $item_id => $data): ?>
                        <div style="background:rgba(139,92,246,0.05);border:1px solid rgba(139,92,246,0.1);border-radius:12px;overflow:hidden;">
                            <div style="background:rgba(139,92,246,0.1);padding:15px 20px;display:flex;justify-content:space-between;align-items:center;">
                                <h4 style="color:#a78bfa;margin:0;font-size:1rem;">
                                    <i class="fas fa-file-alt"></i> <?= htmlspecialchars($data['item']['fyp_itemname']); ?>
                                    <span style="font-weight:normal;color:#94a3b8;font-size:0.85rem;margin-left:10px;">(<?= number_format($data['item']['fyp_originalmarkallocation'] ?? 0, 1); ?>%)</span>
                                </h4>
                                <span class="badge" style="background:rgba(59,130,246,0.2);color:#60a5fa;padding:5px 12px;border-radius:15px;">
                                    <?= count($data['criteria']); ?> criteria linked
                                </span>
                            </div>
                            <div style="padding:15px 20px;">
                                <?php if (empty($data['criteria'])): ?>
                                <p style="color:#fb923c;margin:0;"><i class="fas fa-exclamation-circle"></i> No criteria linked yet</p>
                                <?php else: ?>
                                <table class="data-table" style="margin:0;">
                                    <thead>
                                        <tr>
                                            <th>Criteria Name</th>
                                            <th>Percent Allocation</th>
                                            <th style="width:80px;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($data['criteria'] as $c): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($c['fyp_criterianame']); ?></strong></td>
                                            <td><span style="color:#a78bfa;"><?= number_format($c['fyp_percentallocation'], 1); ?>%</span></td>
                                            <td>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this link?');">
                                                    <input type="hidden" name="unlink_itemid" value="<?= $c['fyp_itemid']; ?>">
                                                    <input type="hidden" name="unlink_criteriaid" value="<?= $c['fyp_criteriaid']; ?>">
                                                    <button type="submit" name="unlink_item_criteria" class="btn btn-danger btn-sm" title="Unlink">
                                                        <i class="fas fa-unlink"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Link Item Criteria Modal (for Link action) -->
            <div class="modal-overlay" id="linkItemCriteriaModal2">
                <div class="modal" style="max-width:500px;">
                    <div class="modal-header">
                        <h3><i class="fas fa-link" style="color:#fbbf24;"></i> Link Item to Criteria</h3>
                        <button class="modal-close" onclick="closeModal('linkItemCriteriaModal2')">&times;</button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <div class="form-group">
                                <label>Select Assessment Item <span style="color:#f87171;">*</span></label>
                                <select name="link_itemid" class="form-control" required>
                                    <option value="">-- Select Item --</option>
                                    <?php foreach ($items as $item): ?>
                                    <option value="<?= $item['fyp_itemid']; ?>"><?= htmlspecialchars($item['fyp_itemname']); ?> (<?= number_format($item['fyp_originalmarkallocation'] ?? 0, 1); ?>%)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Select Marking Criteria <span style="color:#f87171;">*</span></label>
                                <select name="link_criteriaid" class="form-control" required>
                                    <option value="">-- Select Criteria --</option>
                                    <?php foreach ($marking_criteria as $mc): ?>
                                    <option value="<?= $mc['fyp_criteriaid']; ?>"><?= htmlspecialchars($mc['fyp_criterianame']); ?> (<?= number_format($mc['fyp_percentallocation'], 1); ?>%)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="closeModal('linkItemCriteriaModal2')">Cancel</button>
                            <button type="submit" name="link_item_criteria" class="btn btn-success">
                                <i class="fas fa-link"></i> Create Link
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php endif; ?>

        <!-- ==================== ASSESSMENT MARKS ==================== -->
        <?php elseif ($current_page === 'marks'): 
            // Get marks sub-action
            $marks_action = $_GET['action'] ?? 'list';
            $view_student_id = $_GET['view'] ?? '';
            
            // Get filter values
            $filter_year = $_GET['year'] ?? '';
            $filter_intake = $_GET['intake'] ?? '';
            $filter_status = $_GET['status'] ?? '';
            $filter_search = $_GET['search'] ?? '';
            
            // Build query for students with marks
            $where_conditions = ["1=1"];
            if (!empty($filter_year)) {
                $where_conditions[] = "(a.fyp_acdyear = '" . $conn->real_escape_string($filter_year) . "')";
            }
            if (!empty($filter_intake)) {
                $where_conditions[] = "a.fyp_intake = '" . $conn->real_escape_string($filter_intake) . "'";
            }
            if (!empty($filter_status)) {
                if ($filter_status === 'marked') {
                    $where_conditions[] = "tm.fyp_totalmark IS NOT NULL AND tm.fyp_totalmark > 0";
                } elseif ($filter_status === 'pending') {
                    $where_conditions[] = "(tm.fyp_totalmark IS NULL OR tm.fyp_totalmark = 0)";
                }
            }
            if (!empty($filter_search)) {
                $search_escaped = $conn->real_escape_string($filter_search);
                $where_conditions[] = "(s.fyp_studid LIKE '%$search_escaped%' OR s.fyp_studname LIKE '%$search_escaped%' OR p.fyp_projecttitle LIKE '%$search_escaped%')";
            }
            $where_sql = implode(" AND ", $where_conditions);
            
            // Get all students with their marks
            $students_marks = [];
            $res = $conn->query("SELECT s.fyp_studid, s.fyp_studname, s.fyp_studfullid, s.fyp_email,
                                        p.fyp_projectid, p.fyp_projecttitle,
                                        pa.fyp_pairingid, pa.fyp_supervisorid, pa.fyp_moderatorid,
                                        sup.fyp_name as supervisor_name,
                                        mod_sup.fyp_name as moderator_name,
                                        tm.fyp_totalmark, tm.fyp_totalfinalsupervisor, tm.fyp_totalfinalmoderator,
                                        a.fyp_acdyear, a.fyp_intake, a.fyp_academicid
                                 FROM student s
                                 LEFT JOIN pairing pa ON s.fyp_studid = pa.fyp_studid
                                 LEFT JOIN project p ON pa.fyp_projectid = p.fyp_projectid
                                 LEFT JOIN supervisor sup ON pa.fyp_supervisorid = sup.fyp_supervisorid
                                 LEFT JOIN supervisor mod_sup ON pa.fyp_moderatorid = mod_sup.fyp_supervisorid
                                 LEFT JOIN total_mark tm ON s.fyp_studid = tm.fyp_studid
                                 LEFT JOIN academic_year a ON a.fyp_academicid = (SELECT MAX(fyp_academicid) FROM academic_year)
                                 ORDER BY s.fyp_studname");
            if ($res) { while ($row = $res->fetch_assoc()) { $students_marks[] = $row; } }
            
            // Get view student data
            $view_student = null;
            $view_student_marks = [];
            if (!empty($view_student_id)) {
                $stmt = $conn->prepare("SELECT s.*, p.fyp_projecttitle, p.fyp_projectid, pa.fyp_pairingid,
                                               sup.fyp_name as supervisor_name,
                                               mod_sup.fyp_name as moderator_name,
                                               tm.fyp_totalmark, tm.fyp_totalfinalsupervisor, tm.fyp_totalfinalmoderator
                                       FROM student s
                                       LEFT JOIN pairing pa ON s.fyp_studid = pa.fyp_studid
                                       LEFT JOIN project p ON pa.fyp_projectid = p.fyp_projectid
                                       LEFT JOIN supervisor sup ON pa.fyp_supervisorid = sup.fyp_supervisorid
                                       LEFT JOIN supervisor mod_sup ON pa.fyp_moderatorid = mod_sup.fyp_supervisorid
                                       LEFT JOIN total_mark tm ON s.fyp_studid = tm.fyp_studid
                                       WHERE s.fyp_studid = ?");
                $stmt->bind_param("s", $view_student_id);
                $stmt->execute();
                $view_student = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                // Get detailed marks with item and criteria info
                $res = $conn->query("SELECT cm.*, mc.fyp_criterianame, mc.fyp_percentallocation,
                                           imc.fyp_itemid, i.fyp_itemname, i.fyp_originalmarkallocation
                                    FROM criteria_mark cm
                                    LEFT JOIN marking_criteria mc ON cm.fyp_criteriaid = mc.fyp_criteriaid
                                    LEFT JOIN item_marking_criteria imc ON cm.fyp_criteriaid = imc.fyp_criteriaid
                                    LEFT JOIN item i ON imc.fyp_itemid = i.fyp_itemid
                                    WHERE cm.fyp_studid = '" . $conn->real_escape_string($view_student_id) . "'
                                    ORDER BY i.fyp_itemid, mc.fyp_criteriaid");
                if ($res) { while ($row = $res->fetch_assoc()) { $view_student_marks[] = $row; } }
            }
            
            // Get selected student for marking
            $selected_student_id = $_GET['mark_student'] ?? '';
            $selected_student = null;
            $student_criteria_marks = [];
            
            if (!empty($selected_student_id)) {
                $stmt = $conn->prepare("SELECT s.*, p.fyp_projecttitle, p.fyp_projectid, pa.fyp_pairingid,
                                               sup.fyp_name as supervisor_name,
                                               mod_sup.fyp_name as moderator_name
                                       FROM student s
                                       LEFT JOIN pairing pa ON s.fyp_studid = pa.fyp_studid
                                       LEFT JOIN project p ON pa.fyp_projectid = p.fyp_projectid
                                       LEFT JOIN supervisor sup ON pa.fyp_supervisorid = sup.fyp_supervisorid
                                       LEFT JOIN supervisor mod_sup ON pa.fyp_moderatorid = mod_sup.fyp_supervisorid
                                       WHERE s.fyp_studid = ?");
                $stmt->bind_param("s", $selected_student_id);
                $stmt->execute();
                $selected_student = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                $res = $conn->query("SELECT cm.*, mc.fyp_criterianame, mc.fyp_percentallocation
                                    FROM criteria_mark cm
                                    LEFT JOIN marking_criteria mc ON cm.fyp_criteriaid = mc.fyp_criteriaid
                                    WHERE cm.fyp_studid = '" . $conn->real_escape_string($selected_student_id) . "'");
                if ($res) { while ($row = $res->fetch_assoc()) { $student_criteria_marks[$row['fyp_criteriaid']] = $row; } }
            }
            
            // Get items and their linked marking criteria for marking form
            $items = [];
            $item_marking_links = [];
            $res = $conn->query("SELECT * FROM item ORDER BY fyp_itemid");
            if ($res) { while ($row = $res->fetch_assoc()) { $items[] = $row; } }
            
            $res = $conn->query("SELECT imc.*, mc.fyp_criterianame, mc.fyp_percentallocation, i.fyp_itemname, i.fyp_originalmarkallocation
                                FROM item_marking_criteria imc
                                LEFT JOIN marking_criteria mc ON imc.fyp_criteriaid = mc.fyp_criteriaid
                                LEFT JOIN item i ON imc.fyp_itemid = i.fyp_itemid
                                ORDER BY imc.fyp_itemid, mc.fyp_criteriaid");
            if ($res) { while ($row = $res->fetch_assoc()) { $item_marking_links[] = $row; } }
            
            // Get student for overall view
            $view_student_id = $_GET['view_student'] ?? '';
            $view_student = null;
            $overall_marks_data = [];
            
            if (!empty($view_student_id)) {
                $stmt = $conn->prepare("SELECT s.*, p.fyp_projecttitle, pa.fyp_pairingid,
                                               sup.fyp_name as supervisor_name,
                                               mod_sup.fyp_name as moderator_name,
                                               tm.fyp_totalmark, tm.fyp_totalfinalsupervisor, tm.fyp_totalfinalmoderator
                                       FROM student s
                                       LEFT JOIN pairing pa ON s.fyp_studid = pa.fyp_studid
                                       LEFT JOIN project p ON pa.fyp_projectid = p.fyp_projectid
                                       LEFT JOIN supervisor sup ON pa.fyp_supervisorid = sup.fyp_supervisorid
                                       LEFT JOIN supervisor mod_sup ON pa.fyp_moderatorid = mod_sup.fyp_supervisorid
                                       LEFT JOIN total_mark tm ON s.fyp_studid = tm.fyp_studid
                                       WHERE s.fyp_studid = ?");
                $stmt->bind_param("s", $view_student_id);
                $stmt->execute();
                $view_student = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                $res = $conn->query("SELECT cm.*, mc.fyp_criterianame, mc.fyp_percentallocation, 
                                            i.fyp_itemname, i.fyp_itemid, i.fyp_originalmarkallocation
                                    FROM criteria_mark cm
                                    LEFT JOIN marking_criteria mc ON cm.fyp_criteriaid = mc.fyp_criteriaid
                                    LEFT JOIN item_marking_criteria imc ON cm.fyp_criteriaid = imc.fyp_criteriaid
                                    LEFT JOIN item i ON imc.fyp_itemid = i.fyp_itemid
                                    WHERE cm.fyp_studid = '" . $conn->real_escape_string($view_student_id) . "'
                                    ORDER BY i.fyp_itemid, mc.fyp_criteriaid");
                if ($res) { 
                    while ($row = $res->fetch_assoc()) { 
                        $item_name = $row['fyp_itemname'] ?? 'Unknown';
                        if (!isset($overall_marks_data[$item_name])) {
                            $overall_marks_data[$item_name] = [
                                'allocation' => $row['fyp_originalmarkallocation'],
                                'criteria' => []
                            ];
                        }
                        $overall_marks_data[$item_name]['criteria'][] = $row;
                    } 
                }
            }
            
            // Statistics
            $total_students = count($students_marks);
            $students_with_marks = 0;
            $total_marks_sum = 0;
            foreach ($students_marks as $sm) {
                if (!empty($sm['fyp_totalmark'])) {
                    $students_with_marks++;
                    $total_marks_sum += $sm['fyp_totalmark'];
                }
            }
            $avg_mark = $students_with_marks > 0 ? $total_marks_sum / $students_with_marks : 0;
            
            // Get view student for detailed marks
            $view_student_id = $_GET['view'] ?? '';
            $view_student = null;
            $student_detailed_marks = [];
            
            if (!empty($view_student_id)) {
                // Get student info
                $stmt = $conn->prepare("SELECT s.*, p.fyp_projecttitle, p.fyp_projectid, pa.fyp_pairingid,
                                               sup.fyp_name as supervisor_name,
                                               mod_sup.fyp_name as moderator_name,
                                               tm.fyp_totalmark, tm.fyp_totalfinalsupervisor, tm.fyp_totalfinalmoderator
                                       FROM student s
                                       LEFT JOIN pairing pa ON s.fyp_studid = pa.fyp_studid
                                       LEFT JOIN project p ON pa.fyp_projectid = p.fyp_projectid
                                       LEFT JOIN supervisor sup ON pa.fyp_supervisorid = sup.fyp_supervisorid
                                       LEFT JOIN supervisor mod_sup ON pa.fyp_moderatorid = mod_sup.fyp_supervisorid
                                       LEFT JOIN total_mark tm ON s.fyp_studid = tm.fyp_studid
                                       WHERE s.fyp_studid = ?");
                $stmt->bind_param("s", $view_student_id);
                $stmt->execute();
                $view_student = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                // Get detailed marks with items and criteria
                $res = $conn->query("SELECT i.fyp_itemid, i.fyp_itemname, i.fyp_originalmarkallocation,
                                           mc.fyp_criteriaid, mc.fyp_criterianame, mc.fyp_percentallocation,
                                           cm.fyp_initialwork, cm.fyp_finalwork, cm.fyp_markbymoderator,
                                           cm.fyp_avgmark, cm.fyp_scaledmark
                                    FROM item i
                                    LEFT JOIN item_marking_criteria imc ON i.fyp_itemid = imc.fyp_itemid
                                    LEFT JOIN marking_criteria mc ON imc.fyp_criteriaid = mc.fyp_criteriaid
                                    LEFT JOIN criteria_mark cm ON mc.fyp_criteriaid = cm.fyp_criteriaid AND cm.fyp_studid = '" . $conn->real_escape_string($view_student_id) . "'
                                    WHERE mc.fyp_criteriaid IS NOT NULL
                                    ORDER BY i.fyp_itemid, mc.fyp_criteriaid");
                if ($res) {
                    while ($row = $res->fetch_assoc()) {
                        $item_id = $row['fyp_itemid'];
                        if (!isset($student_detailed_marks[$item_id])) {
                            $student_detailed_marks[$item_id] = [
                                'name' => $row['fyp_itemname'],
                                'allocation' => $row['fyp_originalmarkallocation'],
                                'criteria' => []
                            ];
                        }
                        $student_detailed_marks[$item_id]['criteria'][] = $row;
                    }
                }
            }
            
            // Store students data for JavaScript
            $students_json = json_encode(array_map(function($s) {
                return [
                    'id' => $s['fyp_studid'],
                    'name' => $s['fyp_studname'],
                    'project' => $s['fyp_projecttitle'] ?? '',
                    'total_mark' => $s['fyp_totalmark'] ?? null,
                    'sup_mark' => $s['fyp_totalfinalsupervisor'] ?? null,
                    'mod_mark' => $s['fyp_totalfinalmoderator'] ?? null
                ];
            }, $students_marks));
            
            // Count active filters
            $active_filters = 0;
            if (!empty($filter_year)) $active_filters++;
            if (!empty($filter_intake)) $active_filters++;
            if (!empty($filter_status)) $active_filters++;
        ?>
            <!-- Success/Error Messages -->
            <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'danger'; ?>" style="margin-bottom:20px;">
                <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?= htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>
            
            <!-- Page Title with Quick Navigation -->
            <div class="card" style="margin-bottom:20px;background:linear-gradient(135deg, rgba(139,92,246,0.15), rgba(59,130,246,0.1));">
                <div class="card-body" style="padding:25px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px;">
                        <h2 style="margin:0;color:#e2e8f0;"><i class="fas fa-clipboard-check" style="color:#8b5cf6;margin-right:10px;"></i>Students Marks</h2>
                        <div style="display:flex;gap:10px;flex-wrap:wrap;">
                            <a href="?page=rubrics" class="btn btn-secondary btn-sm">
                                <i class="fas fa-list-check"></i> Rubrics
                            </a>
                            <a href="?page=rubrics&action=link" class="btn btn-warning btn-sm">
                                <i class="fas fa-link"></i> Link Items
                            </a>
                            <a href="Coordinator_marks.php" class="btn btn-info btn-sm" title="Open Standalone Marks Manager">
                                <i class="fas fa-external-link-alt"></i> Advanced
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($view_student)): ?>
            <!-- VIEW STUDENT MARKS DETAIL -->
            <div class="card">
                <div class="card-header" style="background:linear-gradient(135deg, rgba(59,130,246,0.2), rgba(139,92,246,0.1));">
                    <h3><i class="fas fa-user-graduate" style="color:#60a5fa;"></i> Student Marks Detail</h3>
                    <a href="?page=marks" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back to List</a>
                </div>
                <div class="card-body">
                    <!-- Student Info Header -->
                    <div style="background:rgba(30,41,59,0.5);padding:20px;border-radius:12px;margin-bottom:25px;">
                        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:20px;">
                            <div>
                                <label style="color:#94a3b8;font-size:0.85rem;display:block;margin-bottom:5px;">Student ID</label>
                                <span style="color:#60a5fa;font-weight:700;font-size:1.1rem;"><?= htmlspecialchars($view_student['fyp_studid']); ?></span>
                            </div>
                            <div>
                                <label style="color:#94a3b8;font-size:0.85rem;display:block;margin-bottom:5px;">Student Name</label>
                                <span style="color:#e2e8f0;font-weight:600;"><?= htmlspecialchars($view_student['fyp_studname']); ?></span>
                            </div>
                            <div>
                                <label style="color:#94a3b8;font-size:0.85rem;display:block;margin-bottom:5px;">Project Title</label>
                                <span style="color:#a78bfa;font-weight:600;"><?= htmlspecialchars($view_student['fyp_projecttitle'] ?? 'Not assigned'); ?></span>
                            </div>
                            <div>
                                <label style="color:#94a3b8;font-size:0.85rem;display:block;margin-bottom:5px;">Supervisor</label>
                                <span style="color:#e2e8f0;"><?= htmlspecialchars($view_student['supervisor_name'] ?? 'Not assigned'); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Summary Cards -->
                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:20px;margin-bottom:25px;">
                        <div style="background:rgba(139,92,246,0.1);border:1px solid rgba(139,92,246,0.2);border-radius:12px;padding:20px;text-align:center;">
                            <i class="fas fa-calculator" style="font-size:1.5rem;color:#a78bfa;margin-bottom:10px;"></i>
                            <h4 style="font-size:1.8rem;color:#e2e8f0;margin:0;"><?= $view_student['fyp_totalmark'] ? number_format($view_student['fyp_totalmark'], 2) : '-'; ?></h4>
                            <p style="color:#94a3b8;font-size:0.85rem;margin:5px 0 0;">Total Mark</p>
                        </div>
                        <div style="background:rgba(59,130,246,0.1);border:1px solid rgba(59,130,246,0.2);border-radius:12px;padding:20px;text-align:center;">
                            <i class="fas fa-user-tie" style="font-size:1.5rem;color:#60a5fa;margin-bottom:10px;"></i>
                            <h4 style="font-size:1.8rem;color:#e2e8f0;margin:0;"><?= $view_student['fyp_totalfinalsupervisor'] ? number_format($view_student['fyp_totalfinalsupervisor'], 2) : '-'; ?></h4>
                            <p style="color:#94a3b8;font-size:0.85rem;margin:5px 0 0;">Supervisor Mark</p>
                        </div>
                        <div style="background:rgba(249,115,22,0.1);border:1px solid rgba(249,115,22,0.2);border-radius:12px;padding:20px;text-align:center;">
                            <i class="fas fa-user-check" style="font-size:1.5rem;color:#fb923c;margin-bottom:10px;"></i>
                            <h4 style="font-size:1.8rem;color:#e2e8f0;margin:0;"><?= $view_student['fyp_totalfinalmoderator'] ? number_format($view_student['fyp_totalfinalmoderator'], 2) : '-'; ?></h4>
                            <p style="color:#94a3b8;font-size:0.85rem;margin:5px 0 0;">Moderator Mark</p>
                        </div>
                        <?php
                        $grade = '-';
                        $grade_color = '#94a3b8';
                        if ($view_student['fyp_totalmark']) {
                            $tm = $view_student['fyp_totalmark'];
                            if ($tm >= 80) { $grade = 'A'; $grade_color = '#34d399'; }
                            elseif ($tm >= 70) { $grade = 'B'; $grade_color = '#60a5fa'; }
                            elseif ($tm >= 60) { $grade = 'C'; $grade_color = '#fbbf24'; }
                            elseif ($tm >= 50) { $grade = 'D'; $grade_color = '#fb923c'; }
                            else { $grade = 'F'; $grade_color = '#f87171'; }
                        }
                        ?>
                        <div style="background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.2);border-radius:12px;padding:20px;text-align:center;">
                            <i class="fas fa-award" style="font-size:1.5rem;color:<?= $grade_color; ?>;margin-bottom:10px;"></i>
                            <h4 style="font-size:1.8rem;color:<?= $grade_color; ?>;margin:0;"><?= $grade; ?></h4>
                            <p style="color:#94a3b8;font-size:0.85rem;margin:5px 0 0;">Grade</p>
                        </div>
                    </div>
                    
                    <!-- Detailed Marks Table -->
                    <?php if (empty($view_student_marks)): ?>
                    <div class="empty-state" style="padding:40px;">
                        <i class="fas fa-file-alt" style="font-size:3rem;color:#64748b;"></i>
                        <p style="color:#94a3b8;margin-top:15px;">No detailed marks available for this student.</p>
                        <a href="?page=marks&action=mark&student=<?= urlencode($view_student['fyp_studid']); ?>" class="btn btn-success" style="margin-top:15px;">
                            <i class="fas fa-edit"></i> Enter Marks
                        </a>
                    </div>
                    <?php else: ?>
                    <h4 style="color:#a78bfa;margin-bottom:15px;"><i class="fas fa-list-alt"></i> Detailed Marks Breakdown</h4>
                    <div style="overflow-x:auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Criteria</th>
                                    <th>Initial</th>
                                    <th>Final</th>
                                    <th>Moderator</th>
                                    <th>Average</th>
                                    <th>Scaled</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $current_item = '';
                                foreach ($view_student_marks as $mark): 
                                    $show_item = $mark['fyp_itemname'] !== $current_item;
                                    $current_item = $mark['fyp_itemname'];
                                ?>
                                <tr>
                                    <td><?= $show_item ? '<strong style="color:#a78bfa;">' . htmlspecialchars($mark['fyp_itemname'] ?? '-') . '</strong>' : ''; ?></td>
                                    <td><?= htmlspecialchars($mark['fyp_criterianame']); ?></td>
                                    <td><?= $mark['fyp_initialwork'] ? number_format($mark['fyp_initialwork'], 2) : '-'; ?></td>
                                    <td><?= $mark['fyp_finalwork'] ? number_format($mark['fyp_finalwork'], 2) : '-'; ?></td>
                                    <td><?= $mark['fyp_markbymoderator'] ? number_format($mark['fyp_markbymoderator'], 2) : '-'; ?></td>
                                    <td><span style="color:#60a5fa;"><?= $mark['fyp_avgmark'] ? number_format($mark['fyp_avgmark'], 2) : '-'; ?></span></td>
                                    <td><strong style="color:#34d399;"><?= $mark['fyp_scaledmark'] ? number_format($mark['fyp_scaledmark'], 2) : '-'; ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Action Buttons -->
                    <div style="margin-top:25px;display:flex;gap:15px;flex-wrap:wrap;">
                        <a href="?page=marks&action=mark&student=<?= urlencode($view_student['fyp_studid']); ?>" class="btn btn-success">
                            <i class="fas fa-edit"></i> Edit Marks
                        </a>
                        <a href="?page=marks" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to List
                        </a>
                    </div>
                </div>
            </div>
            
            <?php elseif ($marks_action === 'list' || $marks_action === ''): ?>
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list-alt" style="color:#60a5fa;"></i> Students Marks</h3>
                    <div style="display:flex;gap:10px;">
                        <button class="btn btn-info btn-sm" onclick="exportMarksToCSV()"><i class="fas fa-download"></i> Export</button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Unified Search Bar with Filter Button -->
                    <div style="margin-bottom:25px;">
                        <form method="GET" action="" id="searchForm">
                            <input type="hidden" name="page" value="marks">
                            <input type="hidden" name="year" value="<?= htmlspecialchars($filter_year); ?>" id="hiddenYear">
                            <input type="hidden" name="intake" value="<?= htmlspecialchars($filter_intake); ?>" id="hiddenIntake">
                            <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status); ?>" id="hiddenStatus">
                            
                            <div style="display:flex;gap:15px;align-items:center;flex-wrap:wrap;">
                                <!-- Search Input with Live Preview -->
                                <div style="flex:1;min-width:300px;position:relative;">
                                    <div style="position:relative;">
                                        <i class="fas fa-search" style="position:absolute;left:15px;top:50%;transform:translateY(-50%);color:#64748b;"></i>
                                        <input type="text" name="search" id="marksSearchInput" class="form-control" 
                                               style="padding-left:45px;padding-right:100px;height:50px;font-size:1rem;border-radius:25px;background:rgba(30,41,59,0.8);border:2px solid rgba(139,92,246,0.3);"
                                               placeholder="Search by Student ID or Name..." 
                                               autocomplete="off"
                                               value="<?= htmlspecialchars($filter_search); ?>"
                                               onkeyup="liveSearchStudents(this.value)">
                                        <button type="submit" style="position:absolute;right:60px;top:50%;transform:translateY(-50%);background:#8b5cf6;border:none;color:white;cursor:pointer;padding:8px 12px;border-radius:15px;font-size:0.85rem;">
                                            <i class="fas fa-search"></i>
                                        </button>
                                        <button type="button" id="clearSearchBtn" onclick="clearSearch()" 
                                                style="position:absolute;right:15px;top:50%;transform:translateY(-50%);background:none;border:none;color:#64748b;cursor:pointer;<?= empty($filter_search) ? 'display:none;' : ''; ?>">
                                            <i class="fas fa-times-circle" style="font-size:1.2rem;"></i>
                                        </button>
                                    </div>
                                    
                                    <!-- Live Search Preview Dropdown -->
                                    <div id="searchPreviewDropdown" style="display:none;position:absolute;top:100%;left:0;right:0;background:rgba(30,41,59,0.98);border:1px solid rgba(139,92,246,0.3);border-radius:12px;margin-top:5px;max-height:400px;overflow-y:auto;z-index:1000;box-shadow:0 10px 40px rgba(0,0,0,0.5);">
                                        <div id="searchPreviewContent" style="padding:5px;"></div>
                                    </div>
                                </div>
                                
                                <!-- Filter Button -->
                                <button type="button" class="btn btn-secondary" onclick="openModal('marksFilterModal')" style="height:50px;padding:0 25px;border-radius:25px;">
                                    <i class="fas fa-sliders-h"></i> Filters
                                    <?php if ($active_filters > 0): ?>
                                    <span style="background:#8b5cf6;color:white;padding:2px 8px;border-radius:10px;font-size:0.75rem;margin-left:5px;"><?= $active_filters; ?></span>
                                    <?php endif; ?>
                                </button>
                                
                                <?php if ($active_filters > 0 || !empty($filter_search)): ?>
                                <a href="?page=marks" class="btn btn-outline" style="height:50px;padding:0 20px;border-radius:25px;border:2px solid rgba(239,68,68,0.5);color:#f87171;">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                                <?php endif; ?>
                            </div>
                        </form>
                        
                        <!-- Active Filters Tags -->
                        <?php if ($active_filters > 0 || !empty($filter_search)): ?>
                        <div style="margin-top:15px;display:flex;gap:10px;flex-wrap:wrap;">
                            <?php if (!empty($filter_year)): ?>
                            <span style="background:rgba(139,92,246,0.2);color:#a78bfa;padding:8px 15px;border-radius:20px;font-size:0.85rem;display:inline-flex;align-items:center;gap:8px;">
                                <i class="fas fa-calendar"></i> Year: <?= htmlspecialchars($filter_year); ?>
                                <a href="?page=marks&intake=<?= urlencode($filter_intake); ?>&status=<?= urlencode($filter_status); ?>&search=<?= urlencode($filter_search); ?>" style="color:#f87171;"><i class="fas fa-times"></i></a>
                            </span>
                            <?php endif; ?>
                            <?php if (!empty($filter_intake)): ?>
                            <span style="background:rgba(59,130,246,0.2);color:#60a5fa;padding:8px 15px;border-radius:20px;font-size:0.85rem;display:inline-flex;align-items:center;gap:8px;">
                                <i class="fas fa-graduation-cap"></i> Intake: <?= htmlspecialchars($filter_intake); ?>
                                <a href="?page=marks&year=<?= urlencode($filter_year); ?>&status=<?= urlencode($filter_status); ?>&search=<?= urlencode($filter_search); ?>" style="color:#f87171;"><i class="fas fa-times"></i></a>
                            </span>
                            <?php endif; ?>
                            <?php if (!empty($filter_status)): ?>
                            <span style="background:rgba(16,185,129,0.2);color:#34d399;padding:8px 15px;border-radius:20px;font-size:0.85rem;display:inline-flex;align-items:center;gap:8px;">
                                <i class="fas fa-check-circle"></i> Status: <?= ucfirst(htmlspecialchars($filter_status)); ?>
                                <a href="?page=marks&year=<?= urlencode($filter_year); ?>&intake=<?= urlencode($filter_intake); ?>&search=<?= urlencode($filter_search); ?>" style="color:#f87171;"><i class="fas fa-times"></i></a>
                            </span>
                            <?php endif; ?>
                            <?php if (!empty($filter_search)): ?>
                            <span style="background:rgba(251,191,36,0.2);color:#fbbf24;padding:8px 15px;border-radius:20px;font-size:0.85rem;display:inline-flex;align-items:center;gap:8px;">
                                <i class="fas fa-search"></i> Search: "<?= htmlspecialchars($filter_search); ?>"
                                <a href="?page=marks&year=<?= urlencode($filter_year); ?>&intake=<?= urlencode($filter_intake); ?>&status=<?= urlencode($filter_status); ?>" style="color:#f87171;"><i class="fas fa-times"></i></a>
                            </span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Results Count -->
                        <div id="resultsCount" style="margin-top:15px;color:#64748b;font-size:0.9rem;">
                            Showing <span id="visibleCount"><?= count($students_marks); ?></span> of <?= count($students_marks); ?> student(s)
                        </div>
                    </div>
                    
                    <?php if (empty($students_marks)): ?>
                    <div class="empty-state">
                        <i class="fas fa-list-alt"></i>
                        <p>No students found matching the criteria</p>
                    </div>
                    <?php else: ?>
                    
                    <!-- Students Table -->
                    <div style="overflow-x:auto;">
                        <table class="data-table" id="marksTable">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Student Name</th>
                                    <th>Project Title</th>
                                    <th>Total Mark</th>
                                    <th>Total Final Supervisor</th>
                                    <th>Total Final Moderator</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="marksTableBody">
                                <?php foreach ($students_marks as $sm): ?>
                                <tr class="student-row" 
                                    data-id="<?= htmlspecialchars(strtolower($sm['fyp_studid'])); ?>"
                                    data-name="<?= htmlspecialchars(strtolower($sm['fyp_studname'])); ?>"
                                    data-project="<?= htmlspecialchars(strtolower($sm['fyp_projecttitle'] ?? '')); ?>">
                                    <td><strong style="color:#60a5fa;"><?= htmlspecialchars($sm['fyp_studid']); ?></strong></td>
                                    <td><?= htmlspecialchars($sm['fyp_studname']); ?></td>
                                    <td style="max-width:250px;"><?= htmlspecialchars($sm['fyp_projecttitle'] ?? '-'); ?></td>
                                    <td>
                                        <?php if ($sm['fyp_totalmark']): ?>
                                        <span style="color:<?= $sm['fyp_totalmark'] >= 50 ? '#34d399' : '#f87171'; ?>;font-weight:600;">
                                            <?= number_format($sm['fyp_totalmark'], 2); ?>
                                        </span>
                                        <?php else: ?>
                                        <span style="color:#94a3b8;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $sm['fyp_totalfinalsupervisor'] ? number_format($sm['fyp_totalfinalsupervisor'], 2) : '-'; ?></td>
                                    <td><?= $sm['fyp_totalfinalmoderator'] ? number_format($sm['fyp_totalfinalmoderator'], 2) : '-'; ?></td>
                                    <td>
                                        <div style="display:flex;gap:5px;">
                                            <a href="?page=marks&action=mark&student=<?= urlencode($sm['fyp_studid']); ?>" class="btn btn-success btn-sm" title="Mark Student">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="?page=marks&view=<?= urlencode($sm['fyp_studid']); ?>" class="btn btn-info btn-sm" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($marks_action === 'mark'): 
                // Get student to mark
                $mark_student_id = $_GET['student'] ?? '';
                $mark_student = null;
                $mark_student_criteria = [];
                
                if (!empty($mark_student_id)) {
                    $stmt = $conn->prepare("SELECT s.*, p.fyp_projecttitle, p.fyp_projectid, pa.fyp_pairingid,
                                                   sup.fyp_name as supervisor_name,
                                                   mod_sup.fyp_name as moderator_name,
                                                   tm.fyp_totalmark, tm.fyp_totalfinalsupervisor, tm.fyp_totalfinalmoderator
                                           FROM student s
                                           LEFT JOIN pairing pa ON s.fyp_studid = pa.fyp_studid
                                           LEFT JOIN project p ON pa.fyp_projectid = p.fyp_projectid
                                           LEFT JOIN supervisor sup ON pa.fyp_supervisorid = sup.fyp_supervisorid
                                           LEFT JOIN supervisor mod_sup ON pa.fyp_moderatorid = mod_sup.fyp_supervisorid
                                           LEFT JOIN total_mark tm ON s.fyp_studid = tm.fyp_studid
                                           WHERE s.fyp_studid = ?");
                    $stmt->bind_param("s", $mark_student_id);
                    $stmt->execute();
                    $mark_student = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    
                    // Get existing marks for this student
                    $res = $conn->query("SELECT cm.*, mc.fyp_criterianame, mc.fyp_percentallocation
                                        FROM criteria_mark cm
                                        LEFT JOIN marking_criteria mc ON cm.fyp_criteriaid = mc.fyp_criteriaid
                                        WHERE cm.fyp_studid = '" . $conn->real_escape_string($mark_student_id) . "'");
                    if ($res) { 
                        while ($row = $res->fetch_assoc()) { 
                            $mark_student_criteria[$row['fyp_criteriaid']] = $row; 
                        } 
                    }
                }
                
                // Get items and marking criteria
                $mark_items = [];
                $res = $conn->query("SELECT * FROM item ORDER BY fyp_itemid");
                if ($res) { while ($row = $res->fetch_assoc()) { $mark_items[] = $row; } }
                
                $mark_item_links = [];
                $res = $conn->query("SELECT imc.*, mc.fyp_criterianame, mc.fyp_percentallocation, i.fyp_itemname, i.fyp_originalmarkallocation
                                    FROM item_marking_criteria imc
                                    LEFT JOIN marking_criteria mc ON imc.fyp_criteriaid = mc.fyp_criteriaid
                                    LEFT JOIN item i ON imc.fyp_itemid = i.fyp_itemid
                                    ORDER BY imc.fyp_itemid, mc.fyp_criteriaid");
                if ($res) { while ($row = $res->fetch_assoc()) { $mark_item_links[] = $row; } }
            ?>
            <!-- Mark Student Section -->
            <div class="card">
                <div class="card-header" style="background:linear-gradient(135deg, rgba(52,211,153,0.2), rgba(139,92,246,0.1));">
                    <h3><i class="fas fa-edit" style="color:#34d399;"></i> Mark Student Assessment</h3>
                    <a href="?page=marks" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back to List</a>
                </div>
                <div class="card-body">
                    <?php if (!$mark_student): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> Student not found.
                    </div>
                    <?php else: ?>
                    
                    <!-- Student Info Header -->
                    <div style="background:rgba(52,211,153,0.1);padding:20px;border-radius:12px;margin-bottom:25px;border:1px solid rgba(52,211,153,0.2);">
                        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:20px;">
                            <div>
                                <label style="color:#94a3b8;font-size:0.85rem;display:block;margin-bottom:5px;">Student ID</label>
                                <span style="color:#34d399;font-weight:700;font-size:1.1rem;"><?= htmlspecialchars($mark_student['fyp_studid']); ?></span>
                            </div>
                            <div>
                                <label style="color:#94a3b8;font-size:0.85rem;display:block;margin-bottom:5px;">Student Name</label>
                                <span style="color:#e2e8f0;font-weight:600;"><?= htmlspecialchars($mark_student['fyp_studname']); ?></span>
                            </div>
                            <div>
                                <label style="color:#94a3b8;font-size:0.85rem;display:block;margin-bottom:5px;">Project Title</label>
                                <span style="color:#a78bfa;font-weight:600;"><?= htmlspecialchars($mark_student['fyp_projecttitle'] ?? 'Not assigned'); ?></span>
                            </div>
                            <div>
                                <label style="color:#94a3b8;font-size:0.85rem;display:block;margin-bottom:5px;">Supervisor</label>
                                <span style="color:#e2e8f0;"><?= htmlspecialchars($mark_student['supervisor_name'] ?? 'Not assigned'); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Assessment Form -->
                    <form method="POST" id="markStudentForm">
                        <input type="hidden" name="save_student_assessment" value="1">
                        <input type="hidden" name="assess_student_id" value="<?= htmlspecialchars($mark_student['fyp_studid']); ?>">
                        
                        <?php 
                        // Group items with their marking criteria
                        $items_with_criteria = [];
                        foreach ($mark_items as $item) {
                            $item_id = $item['fyp_itemid'];
                            $items_with_criteria[$item_id] = [
                                'item' => $item,
                                'criteria' => []
                            ];
                            foreach ($mark_item_links as $link) {
                                if ($link['fyp_itemid'] == $item_id) {
                                    $items_with_criteria[$item_id]['criteria'][] = $link;
                                }
                            }
                        }
                        
                        $has_criteria = false;
                        foreach ($items_with_criteria as $item_data) {
                            if (!empty($item_data['criteria'])) {
                                $has_criteria = true;
                                break;
                            }
                        }
                        ?>
                        
                        <?php if (!$has_criteria): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            No marking criteria linked to assessment items. Please configure rubrics first.
                            <a href="?page=rubrics&action=link" class="btn btn-warning btn-sm" style="margin-left:15px;">Configure Rubrics</a>
                        </div>
                        <?php else: ?>
                        
                        <?php foreach ($items_with_criteria as $item_id => $item_data): 
                            if (empty($item_data['criteria'])) continue;
                        ?>
                        <div style="background:rgba(139,92,246,0.1);padding:15px 20px;border-radius:8px 8px 0 0;margin-top:20px;">
                            <h4 style="color:#a78bfa;margin:0;">
                                <i class="fas fa-file-alt"></i> <?= htmlspecialchars($item_data['item']['fyp_itemname']); ?>
                                <span style="font-size:0.85rem;color:#94a3b8;margin-left:10px;">(<?= number_format($item_data['item']['fyp_originalmarkallocation'], 1); ?>%)</span>
                            </h4>
                        </div>
                        <div style="overflow-x:auto;">
                            <table class="data-table" style="margin-bottom:0;border-radius:0 0 8px 8px;">
                                <thead>
                                    <tr>
                                        <th>Criteria</th>
                                        <th>% Allocation</th>
                                        <th>Initial Work</th>
                                        <th>Final Work</th>
                                        <th>Moderator</th>
                                        <th>Average</th>
                                        <th>Scaled Mark</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($item_data['criteria'] as $mc): 
                                        $existing_mark = $mark_student_criteria[$mc['fyp_criteriaid']] ?? null;
                                    ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($mc['fyp_criterianame']); ?></strong></td>
                                        <td><span class="badge" style="background:rgba(139,92,246,0.2);color:#a78bfa;"><?= number_format($mc['fyp_percentallocation'], 1); ?>%</span></td>
                                        <td>
                                            <input type="number" name="initial_work[<?= $mc['fyp_criteriaid']; ?>]" 
                                                   class="form-control mark-input" style="width:80px;" min="0" max="100" step="0.01"
                                                   data-criteria="<?= $mc['fyp_criteriaid']; ?>" data-percent="<?= $mc['fyp_percentallocation']; ?>"
                                                   value="<?= $existing_mark['fyp_initialwork'] ?? ''; ?>"
                                                   onchange="calculateMarks(<?= $mc['fyp_criteriaid']; ?>, <?= $mc['fyp_percentallocation']; ?>)">
                                        </td>
                                        <td>
                                            <input type="number" name="final_work[<?= $mc['fyp_criteriaid']; ?>]" 
                                                   class="form-control mark-input" style="width:80px;" min="0" max="100" step="0.01"
                                                   data-criteria="<?= $mc['fyp_criteriaid']; ?>" data-percent="<?= $mc['fyp_percentallocation']; ?>"
                                                   value="<?= $existing_mark['fyp_finalwork'] ?? ''; ?>"
                                                   onchange="calculateMarks(<?= $mc['fyp_criteriaid']; ?>, <?= $mc['fyp_percentallocation']; ?>)">
                                        </td>
                                        <td>
                                            <input type="number" name="moderator_mark[<?= $mc['fyp_criteriaid']; ?>]" 
                                                   class="form-control mark-input" style="width:80px;" min="0" max="100" step="0.01"
                                                   data-criteria="<?= $mc['fyp_criteriaid']; ?>" data-percent="<?= $mc['fyp_percentallocation']; ?>"
                                                   value="<?= $existing_mark['fyp_markbymoderator'] ?? ''; ?>"
                                                   onchange="calculateMarks(<?= $mc['fyp_criteriaid']; ?>, <?= $mc['fyp_percentallocation']; ?>)">
                                        </td>
                                        <td>
                                            <input type="text" id="avg_<?= $mc['fyp_criteriaid']; ?>" 
                                                   class="form-control" style="width:80px;background:rgba(15,15,26,0.6);color:#60a5fa;" readonly
                                                   value="<?= isset($existing_mark['fyp_avgmark']) ? number_format($existing_mark['fyp_avgmark'], 2) : ''; ?>">
                                        </td>
                                        <td>
                                            <input type="text" id="scaled_<?= $mc['fyp_criteriaid']; ?>" name="scaled_mark[<?= $mc['fyp_criteriaid']; ?>]"
                                                   class="form-control scaled-mark" style="width:80px;background:rgba(15,15,26,0.6);color:#34d399;font-weight:600;" readonly
                                                   value="<?= isset($existing_mark['fyp_scaledmark']) ? number_format($existing_mark['fyp_scaledmark'], 2) : ''; ?>">
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endforeach; ?>
                        
                        <!-- Final Subtotal & Buttons -->
                        <div style="background:rgba(139,92,246,0.1);padding:20px;border-radius:12px;margin-top:25px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:15px;">
                            <div style="display:flex;align-items:center;gap:20px;">
                                <div>
                                    <label style="color:#a78bfa;font-weight:600;display:block;margin-bottom:5px;">Final Total:</label>
                                    <input type="text" id="finalTotal" class="form-control" style="width:120px;background:rgba(15,15,26,0.6);color:#fbbf24;font-weight:700;font-size:1.3rem;text-align:center;" readonly value="0.00">
                                </div>
                            </div>
                            <div style="display:flex;gap:10px;">
                                <a href="?page=marks" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-success" style="min-width:150px;">
                                    <i class="fas fa-save"></i> Save Marks
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <script>
            // Calculate average and scaled marks
            function calculateMarks(criteriaId, percentAllocation) {
                var initial = parseFloat(document.querySelector('input[name="initial_work[' + criteriaId + ']"]').value) || 0;
                var finalW = parseFloat(document.querySelector('input[name="final_work[' + criteriaId + ']"]').value) || 0;
                var moderator = parseFloat(document.querySelector('input[name="moderator_mark[' + criteriaId + ']"]').value) || 0;
                
                // Calculate average of non-zero values
                var values = [initial, finalW, moderator].filter(function(v) { return v > 0; });
                var avg = values.length > 0 ? values.reduce(function(a, b) { return a + b; }, 0) / values.length : 0;
                
                // Calculate scaled mark
                var scaled = (avg * percentAllocation) / 100;
                
                document.getElementById('avg_' + criteriaId).value = avg.toFixed(2);
                document.getElementById('scaled_' + criteriaId).value = scaled.toFixed(2);
                
                // Update final total
                updateFinalTotal();
            }
            
            function updateFinalTotal() {
                var total = 0;
                document.querySelectorAll('.scaled-mark').forEach(function(input) {
                    total += parseFloat(input.value) || 0;
                });
                document.getElementById('finalTotal').value = total.toFixed(2);
            }
            
            // Initialize calculations on page load
            document.addEventListener('DOMContentLoaded', function() {
                updateFinalTotal();
            });
            </script>
            <?php endif; ?>
            
            <!-- Filter Modal -->
            <div class="modal-overlay" id="marksFilterModal">
                <div class="modal" style="max-width:500px;">
                    <div class="modal-header">
                        <h3><i class="fas fa-sliders-h" style="color:#8b5cf6;"></i> Filter Options</h3>
                        <button class="modal-close" onclick="closeModal('marksFilterModal')">&times;</button>
                    </div>
                    <form method="GET" action="">
                        <input type="hidden" name="page" value="marks">
                        <div class="modal-body" style="padding:25px;">
                            <div style="display:grid;gap:25px;">
                                <!-- Academic Year -->
                                <div class="form-group" style="margin:0;">
                                    <label style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                                        <span style="width:40px;height:40px;border-radius:10px;background:rgba(139,92,246,0.2);display:flex;align-items:center;justify-content:center;">
                                            <i class="fas fa-calendar" style="color:#a78bfa;"></i>
                                        </span>
                                        <span style="font-weight:600;">Academic Year</span>
                                    </label>
                                    <select name="year" class="form-control" style="height:50px;font-size:1rem;">
                                        <option value="">-- All Years --</option>
                                        <?php 
                                        $years_shown = [];
                                        foreach ($academic_years as $ay): 
                                            if (!in_array($ay['fyp_acdyear'], $years_shown)):
                                                $years_shown[] = $ay['fyp_acdyear'];
                                        ?>
                                        <option value="<?= htmlspecialchars($ay['fyp_acdyear']); ?>" <?= $filter_year === $ay['fyp_acdyear'] ? 'selected' : ''; ?>><?= htmlspecialchars($ay['fyp_acdyear']); ?></option>
                                        <?php endif; endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Intake -->
                                <div class="form-group" style="margin:0;">
                                    <label style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                                        <span style="width:40px;height:40px;border-radius:10px;background:rgba(59,130,246,0.2);display:flex;align-items:center;justify-content:center;">
                                            <i class="fas fa-graduation-cap" style="color:#60a5fa;"></i>
                                        </span>
                                        <span style="font-weight:600;">Intake</span>
                                    </label>
                                    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;">
                                        <label style="cursor:pointer;">
                                            <input type="radio" name="intake" value="" <?= empty($filter_intake) ? 'checked' : ''; ?> style="display:none;">
                                            <span class="intake-radio-btn" style="display:block;padding:12px;text-align:center;border-radius:10px;background:<?= empty($filter_intake) ? 'rgba(139,92,246,0.3)' : 'rgba(30,41,59,0.6)'; ?>;border:2px solid <?= empty($filter_intake) ? '#8b5cf6' : 'transparent'; ?>;transition:all 0.2s;font-weight:500;">All</span>
                                        </label>
                                        <label style="cursor:pointer;">
                                            <input type="radio" name="intake" value="MAR" <?= $filter_intake === 'MAR' ? 'checked' : ''; ?> style="display:none;">
                                            <span class="intake-radio-btn" style="display:block;padding:12px;text-align:center;border-radius:10px;background:<?= $filter_intake === 'MAR' ? 'rgba(139,92,246,0.3)' : 'rgba(30,41,59,0.6)'; ?>;border:2px solid <?= $filter_intake === 'MAR' ? '#8b5cf6' : 'transparent'; ?>;transition:all 0.2s;font-weight:500;">MAR</span>
                                        </label>
                                        <label style="cursor:pointer;">
                                            <input type="radio" name="intake" value="JUN" <?= $filter_intake === 'JUN' ? 'checked' : ''; ?> style="display:none;">
                                            <span class="intake-radio-btn" style="display:block;padding:12px;text-align:center;border-radius:10px;background:<?= $filter_intake === 'JUN' ? 'rgba(139,92,246,0.3)' : 'rgba(30,41,59,0.6)'; ?>;border:2px solid <?= $filter_intake === 'JUN' ? '#8b5cf6' : 'transparent'; ?>;transition:all 0.2s;font-weight:500;">JUN</span>
                                        </label>
                                        <label style="cursor:pointer;">
                                            <input type="radio" name="intake" value="SEP" <?= $filter_intake === 'SEP' ? 'checked' : ''; ?> style="display:none;">
                                            <span class="intake-radio-btn" style="display:block;padding:12px;text-align:center;border-radius:10px;background:<?= $filter_intake === 'SEP' ? 'rgba(139,92,246,0.3)' : 'rgba(30,41,59,0.6)'; ?>;border:2px solid <?= $filter_intake === 'SEP' ? '#8b5cf6' : 'transparent'; ?>;transition:all 0.2s;font-weight:500;">SEP</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Mark Status -->
                                <div class="form-group" style="margin:0;">
                                    <label style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                                        <span style="width:40px;height:40px;border-radius:10px;background:rgba(16,185,129,0.2);display:flex;align-items:center;justify-content:center;">
                                            <i class="fas fa-check-circle" style="color:#34d399;"></i>
                                        </span>
                                        <span style="font-weight:600;">Mark Status</span>
                                    </label>
                                    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;">
                                        <label style="cursor:pointer;">
                                            <input type="radio" name="status" value="" <?= empty($filter_status) ? 'checked' : ''; ?> style="display:none;">
                                            <span class="status-radio-btn" style="display:block;padding:12px;text-align:center;border-radius:10px;background:<?= empty($filter_status) ? 'rgba(139,92,246,0.3)' : 'rgba(30,41,59,0.6)'; ?>;border:2px solid <?= empty($filter_status) ? '#8b5cf6' : 'transparent'; ?>;transition:all 0.2s;font-weight:500;">All</span>
                                        </label>
                                        <label style="cursor:pointer;">
                                            <input type="radio" name="status" value="marked" <?= $filter_status === 'marked' ? 'checked' : ''; ?> style="display:none;">
                                            <span class="status-radio-btn" style="display:block;padding:12px;text-align:center;border-radius:10px;background:<?= $filter_status === 'marked' ? 'rgba(139,92,246,0.3)' : 'rgba(30,41,59,0.6)'; ?>;border:2px solid <?= $filter_status === 'marked' ? '#8b5cf6' : 'transparent'; ?>;transition:all 0.2s;font-weight:500;">Marked</span>
                                        </label>
                                        <label style="cursor:pointer;">
                                            <input type="radio" name="status" value="pending" <?= $filter_status === 'pending' ? 'checked' : ''; ?> style="display:none;">
                                            <span class="status-radio-btn" style="display:block;padding:12px;text-align:center;border-radius:10px;background:<?= $filter_status === 'pending' ? 'rgba(139,92,246,0.3)' : 'rgba(30,41,59,0.6)'; ?>;border:2px solid <?= $filter_status === 'pending' ? '#8b5cf6' : 'transparent'; ?>;transition:all 0.2s;font-weight:500;">Pending</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer" style="display:flex;gap:10px;justify-content:space-between;">
                            <button type="button" class="btn btn-secondary" onclick="window.location='?page=marks'">
                                <i class="fas fa-undo"></i> Reset All
                            </button>
                            <div style="display:flex;gap:10px;">
                                <button type="button" class="btn btn-secondary" onclick="closeModal('marksFilterModal')">Cancel</button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-check"></i> Apply Filters
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Student Marks Detail Modal -->
            <div class="modal-overlay" id="studentMarksModal">
                <div class="modal" style="max-width:95%;width:1200px;">
                    <div class="modal-header" style="background:linear-gradient(135deg, rgba(59,130,246,0.2), rgba(139,92,246,0.1));">
                        <h3><i class="fas fa-user-graduate" style="color:#60a5fa;"></i> Student Marks Details</h3>
                        <button class="modal-close" onclick="closeModal('studentMarksModal')">&times;</button>
                    </div>
                    <div class="modal-body" style="padding:25px;max-height:70vh;overflow-y:auto;">
                        <div id="studentMarksContent">
                            <!-- Content will be loaded dynamically -->
                            <div style="text-align:center;padding:50px;">
                                <i class="fas fa-spinner fa-spin" style="font-size:2rem;color:#8b5cf6;"></i>
                                <p style="margin-top:15px;color:#94a3b8;">Loading student marks...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Store students data for live search -->
            <script>
            var allStudentsMarks = <?= $students_json; ?>;
            
            // Live search function
            function liveSearchStudents(query) {
                var searchInput = document.getElementById('marksSearchInput');
                var clearBtn = document.getElementById('clearSearchBtn');
                var dropdown = document.getElementById('searchPreviewDropdown');
                var content = document.getElementById('searchPreviewContent');
                
                query = query.trim().toLowerCase();
                
                // Show/hide clear button
                clearBtn.style.display = query ? 'block' : 'none';
                
                if (query.length < 1) {
                    dropdown.style.display = 'none';
                    filterTable('');
                    return;
                }
                
                // Filter students
                var matches = allStudentsMarks.filter(function(s) {
                    return s.id.toLowerCase().indexOf(query) !== -1 || 
                           s.name.toLowerCase().indexOf(query) !== -1 ||
                           (s.project && s.project.toLowerCase().indexOf(query) !== -1);
                });
                
                // Build preview HTML
                var html = '';
                if (matches.length === 0) {
                    html = '<div style="padding:20px;text-align:center;color:#94a3b8;"><i class="fas fa-search" style="font-size:1.5rem;margin-bottom:10px;display:block;"></i>No students found</div>';
                } else {
                    html = '<div style="padding:8px 15px;color:#64748b;font-size:0.8rem;border-bottom:1px solid rgba(255,255,255,0.1);">Found ' + matches.length + ' student(s)</div>';
                    matches.slice(0, 10).forEach(function(s) {
                        var markBadge = s.total_mark ? 
                            '<span style="background:' + (s.total_mark >= 50 ? 'rgba(16,185,129,0.2);color:#34d399' : 'rgba(239,68,68,0.2);color:#f87171') + ';padding:3px 10px;border-radius:12px;font-size:0.8rem;">' + parseFloat(s.total_mark).toFixed(2) + '</span>' :
                            '<span style="background:rgba(148,163,184,0.2);color:#94a3b8;padding:3px 10px;border-radius:12px;font-size:0.8rem;">Not Marked</span>';
                        
                        html += '<div class="search-preview-item" onclick="selectStudent(\'' + s.id + '\')" style="padding:12px 15px;cursor:pointer;border-bottom:1px solid rgba(255,255,255,0.05);transition:background 0.2s;">' +
                                '<div style="display:flex;justify-content:space-between;align-items:center;">' +
                                '<div>' +
                                '<div style="font-weight:600;color:#60a5fa;">' + highlightMatch(s.id, query) + '</div>' +
                                '<div style="color:#e2e8f0;font-size:0.95rem;">' + highlightMatch(s.name, query) + '</div>' +
                                '<div style="color:#94a3b8;font-size:0.85rem;margin-top:3px;">' + (s.project ? highlightMatch(s.project.substring(0, 50), query) + (s.project.length > 50 ? '...' : '') : 'No project assigned') + '</div>' +
                                '</div>' +
                                '<div style="text-align:right;">' +
                                markBadge +
                                '</div>' +
                                '</div>' +
                                '</div>';
                    });
                    if (matches.length > 10) {
                        html += '<div style="padding:10px 15px;text-align:center;color:#64748b;font-size:0.85rem;">+ ' + (matches.length - 10) + ' more results</div>';
                    }
                }
                
                content.innerHTML = html;
                dropdown.style.display = 'block';
                
                // Also filter the table
                filterTable(query);
            }
            
            // Highlight matching text
            function highlightMatch(text, query) {
                if (!query || !text) return text || '';
                var regex = new RegExp('(' + query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
                return text.replace(regex, '<span style="background:rgba(139,92,246,0.4);padding:1px 2px;border-radius:3px;">$1</span>');
            }
            
            // Filter table rows
            function filterTable(query) {
                var rows = document.querySelectorAll('.student-row');
                var visibleCount = 0;
                
                rows.forEach(function(row) {
                    var id = row.getAttribute('data-id');
                    var name = row.getAttribute('data-name');
                    var project = row.getAttribute('data-project');
                    
                    if (!query || id.indexOf(query) !== -1 || name.indexOf(query) !== -1 || project.indexOf(query) !== -1) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });
                
                document.getElementById('visibleCount').textContent = visibleCount;
            }
            
            // Select student from preview
            function selectStudent(studentId) {
                document.getElementById('searchPreviewDropdown').style.display = 'none';
                viewStudentMarks(studentId);
            }
            
            // Clear search
            function clearSearch() {
                document.getElementById('marksSearchInput').value = '';
                document.getElementById('clearSearchBtn').style.display = 'none';
                document.getElementById('searchPreviewDropdown').style.display = 'none';
                filterTable('');
            }
            
            // View student marks in modal
            function viewStudentMarks(studentId) {
                openModal('studentMarksModal');
                
                // Load content via AJAX or redirect
                var url = '?page=marks&view=' + encodeURIComponent(studentId) + '&ajax=1';
                
                fetch(url)
                    .then(function(response) { return response.text(); })
                    .then(function(html) {
                        // For now, redirect to view
                        window.location.href = '?page=marks&view=' + encodeURIComponent(studentId);
                    })
                    .catch(function() {
                        window.location.href = '?page=marks&view=' + encodeURIComponent(studentId);
                    });
            }
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                var dropdown = document.getElementById('searchPreviewDropdown');
                var searchInput = document.getElementById('marksSearchInput');
                if (dropdown && !dropdown.contains(e.target) && e.target !== searchInput) {
                    dropdown.style.display = 'none';
                }
            });
            
            // Style for hover on preview items
            document.addEventListener('DOMContentLoaded', function() {
                var style = document.createElement('style');
                style.textContent = '.search-preview-item:hover { background: rgba(139,92,246,0.15) !important; }';
                document.head.appendChild(style);
                
                // Radio button styling
                document.querySelectorAll('input[type="radio"]').forEach(function(radio) {
                    radio.addEventListener('change', function() {
                        var name = this.name;
                        document.querySelectorAll('input[name="' + name + '"]').forEach(function(r) {
                            var span = r.nextElementSibling;
                            if (r.checked) {
                                span.style.background = 'rgba(139,92,246,0.3)';
                                span.style.borderColor = '#8b5cf6';
                            } else {
                                span.style.background = 'rgba(30,41,59,0.6)';
                                span.style.borderColor = 'transparent';
                            }
                        });
                    });
                });
            });
            </script>

        <!-- ==================== REPORTS ==================== -->
        <?php elseif ($current_page === 'reports'): ?>
            <div class="card"><div class="card-header"><h3>Reports Generation</h3><a href="Coordinator_report.php" class="btn btn-primary"><i class="fas fa-file-export"></i> Open Export Center</a></div><div class="card-body">
                <p style="color:#94a3b8;margin-bottom:20px;">Generate and export various FYP reports. Click on any report to download.</p>
                
                <h4 style="color:#a78bfa;margin-bottom:15px;"><i class="fas fa-download"></i> Quick Downloads</h4>
                <div class="quick-actions">
                    <a href="Coordinator_report.php?export=excel&report=students" class="quick-action" style="border-left:4px solid #10B981;">
                        <i class="fas fa-user-graduate" style="color:#10B981;"></i>
                        <h4>Student List</h4>
                        <p>Download Excel</p>
                    </a>
                    <a href="Coordinator_report.php?export=excel&report=marks" class="quick-action" style="border-left:4px solid #10B981;">
                        <i class="fas fa-chart-line" style="color:#10B981;"></i>
                        <h4>Assessment Marks</h4>
                        <p>Download Excel</p>
                    </a>
                    <a href="Coordinator_report.php?export=excel&report=workload" class="quick-action" style="border-left:4px solid #10B981;">
                        <i class="fas fa-briefcase" style="color:#10B981;"></i>
                        <h4>Supervisor Workload</h4>
                        <p>Download Excel</p>
                    </a>
                    <a href="Coordinator_report.php?export=excel&report=pairing" class="quick-action" style="border-left:4px solid #10B981;">
                        <i class="fas fa-link" style="color:#10B981;"></i>
                        <h4>Pairing List</h4>
                        <p>Download Excel</p>
                    </a>
                    <a href="Coordinator_report.php?export=pdf&report=students" target="_blank" class="quick-action" style="border-left:4px solid #EF4444;">
                        <i class="fas fa-file-pdf" style="color:#EF4444;"></i>
                        <h4>Student List PDF</h4>
                        <p>Print to PDF</p>
                    </a>
                    <a href="Coordinator_report.php?export=pdf&report=marks" target="_blank" class="quick-action" style="border-left:4px solid #EF4444;">
                        <i class="fas fa-file-pdf" style="color:#EF4444;"></i>
                        <h4>Marks Report PDF</h4>
                        <p>Print to PDF</p>
                    </a>
                </div>
                
                <div style="margin-top:30px;padding:20px;background:rgba(139,92,246,0.1);border-radius:12px;border:1px solid rgba(139,92,246,0.2);">
                    <h4 style="color:#a78bfa;margin-bottom:10px;"><i class="fas fa-info-circle"></i> Need More Options?</h4>
                    <p style="color:#94a3b8;margin-bottom:15px;">Visit the full Export Center for CSV downloads, filtered reports, and failed students report.</p>
                    <a href="Coordinator_report.php" class="btn btn-primary"><i class="fas fa-external-link-alt"></i> Go to Export Center</a>
                </div>
            </div></div>

        <!-- ==================== ANNOUNCEMENTS ==================== -->
        <?php elseif ($current_page === 'announcements'): 
            $announcements = [];
            $res = $conn->query("SELECT * FROM announcement ORDER BY fyp_datecreated DESC LIMIT 20");
            if ($res) { while ($row = $res->fetch_assoc()) { $announcements[] = $row; } }
        ?>
            <div class="card"><div class="card-header"><h3>Announcements</h3><button class="btn btn-primary" onclick="openModal('createAnnouncementModal')"><i class="fas fa-plus"></i> New Announcement</button></div><div class="card-body">
                <?php if (empty($announcements)): ?><div class="empty-state"><i class="fas fa-bullhorn"></i><p>No announcements yet</p></div>
                <?php else: ?>
                    <table class="data-table"><thead><tr><th>Subject</th><th>Description</th><th>From</th><th>Receiver</th><th>Date</th></tr></thead><tbody>
                        <?php foreach ($announcements as $a): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($a['fyp_subject']); ?></strong></td>
                            <td><?= htmlspecialchars($a['fyp_description']); ?></td>
                            <td><?= htmlspecialchars($a['fyp_supervisorid']); ?></td>
                            <td><?= htmlspecialchars($a['fyp_receiver']); ?></td>
                            <td><?= date('M j, Y H:i', strtotime($a['fyp_datecreated'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody></table>
                <?php endif; ?>
            </div></div>

        <!-- ==================== SETTINGS ==================== -->
        <?php elseif ($current_page === 'settings'): 
            $maintenance = [];
            $res = $conn->query("SELECT * FROM fyp_maintenance ORDER BY fyp_category, fyp_subject");
            if ($res) { while ($row = $res->fetch_assoc()) { $maintenance[] = $row; } }
        ?>
            <div class="card"><div class="card-header"><h3>System Settings</h3></div><div class="card-body">
                <div class="quick-actions">
                    <div class="quick-action"><i class="fas fa-calendar-alt"></i><h4>Academic Years</h4><p><?= count($academic_years); ?> years</p></div>
                    <div class="quick-action"><i class="fas fa-graduation-cap"></i><h4>Programmes</h4><p><?= count($programmes); ?> programmes</p></div>
                    <div class="quick-action"><i class="fas fa-sliders-h"></i><h4>Maintenance</h4><p><?= count($maintenance); ?> settings</p></div>
                    <div class="quick-action"><i class="fas fa-database"></i><h4>Backup Data</h4><p>Export database</p></div>
                </div>
            </div></div>
            
            <div class="card"><div class="card-header"><h3>Academic Years</h3></div><div class="card-body">
                <table class="data-table"><thead><tr><th>ID</th><th>Year</th><th>Intake</th><th>Created</th></tr></thead><tbody>
                    <?php foreach ($academic_years as $ay): ?>
                    <tr><td><?= $ay['fyp_academicid']; ?></td><td><?= htmlspecialchars($ay['fyp_acdyear']); ?></td><td><?= htmlspecialchars($ay['fyp_intake']); ?></td><td><?= $ay['fyp_datecreated'] ? date('M j, Y', strtotime($ay['fyp_datecreated'])) : '-'; ?></td></tr>
                    <?php endforeach; ?>
                </tbody></table>
            </div></div>
            
            <div class="card"><div class="card-header"><h3>Programmes</h3></div><div class="card-body">
                <table class="data-table"><thead><tr><th>ID</th><th>Code</th><th>Full Name</th></tr></thead><tbody>
                    <?php foreach ($programmes as $p): ?>
                    <tr><td><?= $p['fyp_progid']; ?></td><td><?= htmlspecialchars($p['fyp_progname']); ?></td><td><?= htmlspecialchars($p['fyp_prognamefull']); ?></td></tr>
                    <?php endforeach; ?>
                </tbody></table>
            </div></div>

        <?php endif; ?>
    </div>
</div>

<!-- MODALS -->

<!-- Success Modal -->
<div class="modal-overlay" id="successModal">
    <div class="modal" style="max-width:400px;text-align:center;">
        <div class="modal-body" style="padding:40px;">
            <div style="width:80px;height:80px;border-radius:50%;background:rgba(52,211,153,0.2);display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
                <i class="fas fa-check" style="font-size:40px;color:#34d399;"></i>
            </div>
            <h2 style="color:#34d399;margin-bottom:15px;">Success</h2>
            <p id="successMessage" style="color:#94a3b8;margin-bottom:25px;">Marks updated successfully.</p>
            <button class="btn btn-success" onclick="closeModal('successModal')" style="min-width:100px;">OK</button>
        </div>
    </div>
</div>

<!-- Add Student Manually Modal -->
<div class="modal-overlay" id="addStudentModal">
    <div class="modal" style="max-width:500px;">
        <div class="modal-header">
            <h3><i class="fas fa-user-plus" style="color:#34d399;"></i> Add Student Manually</h3>
            <button class="modal-close" onclick="closeModal('addStudentModal')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <p style="color:#94a3b8;margin-bottom:20px;"><i class="fas fa-info-circle"></i> Add a new student directly. A random password will be generated.</p>
                
                <div class="form-group">
                    <label>Email <span style="color:#f87171;">*</span></label>
                    <input type="email" name="new_email" class="form-control" placeholder="student@student.edu.my" required>
                    <small style="color:#64748b;">Must be a valid .edu.my email</small>
                </div>
                
                <div class="form-group">
                    <label>Full Name <span style="color:#f87171;">*</span></label>
                    <input type="text" name="new_studname" class="form-control" placeholder="Enter student's full name" required>
                </div>
                
                <div class="form-group">
                    <label>Full Student ID <span style="color:#f87171;">*</span></label>
                    <input type="text" name="new_studfullid" class="form-control" placeholder="e.g. TP055012" required>
                    <small style="color:#64748b;">This will be used as login username</small>
                </div>
                
                <div style="background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.3);border-radius:10px;padding:15px;margin-top:15px;">
                    <p style="color:#34d399;margin:0;"><i class="fas fa-key"></i> <strong>Password will be auto-generated</strong></p>
                    <p style="color:#94a3b8;margin:5px 0 0 0;font-size:0.85rem;">Student can reset password from their profile after first login.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addStudentModal')">Cancel</button>
                <button type="submit" name="add_student_manual" class="btn btn-success">
                    <i class="fas fa-user-plus"></i> Add Student
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Reject Registration Modal -->
<div class="modal-overlay" id="rejectRegModal">
    <div class="modal" style="max-width:500px;">
        <div class="modal-header"><h3><i class="fas fa-user-times" style="color:#f87171;"></i> Reject Registration</h3><button class="modal-close" onclick="closeModal('rejectRegModal')">&times;</button></div>
        <form method="POST"><div class="modal-body">
            <input type="hidden" name="reg_id" id="reject_reg_id">
            <p style="margin-bottom:15px;">Reject registration for: <strong id="reject_reg_name"></strong></p>
            <div class="form-group">
                <label>Reason for Rejection (optional)</label>
                <textarea name="remarks" class="form-control" rows="3" placeholder="Enter reason for rejection..."></textarea>
            </div>
        </div><div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('rejectRegModal')">Cancel</button>
            <button type="submit" name="reject_registration" class="btn btn-danger"><i class="fas fa-times"></i> Reject</button>
        </div></form>
    </div>
</div>

<!-- Edit Student Modal -->
<div class="modal-overlay" id="editStudentModal">
    <div class="modal">
        <div class="modal-header"><h3><i class="fas fa-user-edit"></i> Edit Student Information</h3><button class="modal-close" onclick="closeModal('editStudentModal')">&times;</button></div>
        <form method="POST"><div class="modal-body">
            <input type="hidden" name="studid" id="edit_studid">
            <div class="form-group">
                <label>Student Name</label>
                <input type="text" name="studname" id="edit_studname" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" id="edit_email" class="form-control">
            </div>
            <div class="form-group">
                <label>Contact Number</label>
                <input type="text" name="contactno" id="edit_contactno" class="form-control">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Programme</label>
                    <select name="progid" id="edit_progid" class="form-control">
                        <?php foreach ($programmes as $p): ?>
                        <option value="<?= $p['fyp_progid']; ?>"><?= htmlspecialchars($p['fyp_progname'] . ' - ' . $p['fyp_prognamefull']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Group Type</label>
                    <select name="group_type" id="edit_group_type" class="form-control">
                        <option value="Individual">Individual</option>
                        <option value="Group">Group</option>
                    </select>
                </div>
            </div>
        </div><div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('editStudentModal')">Cancel</button>
            <button type="submit" name="edit_student" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
        </div></form>
    </div>
</div>

<!-- Delete Student Confirmation Modal -->
<div class="modal-overlay" id="deleteStudentModal">
    <div class="modal" style="max-width:450px;">
        <div class="modal-header"><h3><i class="fas fa-exclamation-triangle" style="color:#f87171;"></i> Confirm Delete</h3><button class="modal-close" onclick="closeModal('deleteStudentModal')">&times;</button></div>
        <form method="POST"><div class="modal-body">
            <input type="hidden" name="studid" id="delete_studid">
            <p style="text-align:center;margin-bottom:15px;">Are you sure you want to delete student:</p>
            <p style="text-align:center;font-size:1.1rem;color:#fff;"><strong id="delete_studname"></strong></p>
            <p style="text-align:center;color:#f87171;font-size:0.85rem;margin-top:15px;"><i class="fas fa-warning"></i> This action cannot be undone!</p>
        </div><div class="modal-footer" style="justify-content:center;">
            <button type="button" class="btn btn-secondary" onclick="closeModal('deleteStudentModal')">Cancel</button>
            <button type="submit" name="delete_student" class="btn btn-danger"><i class="fas fa-trash"></i> Delete Student</button>
        </div></form>
    </div>
</div>

<!-- Create Pairing Modal -->
<div class="modal-overlay" id="createPairingModal">
    <div class="modal">
        <div class="modal-header"><h3>Create New Pairing</h3><button class="modal-close" onclick="closeModal('createPairingModal')">&times;</button></div>
        <form method="POST"><div class="modal-body">
            <div class="form-group"><label>Supervisor</label><select name="supervisor_id" class="form-control" required>
                <option value="">-- Select Supervisor --</option>
                <?php foreach ($sup_list ?? [] as $s): ?><option value="<?= $s['fyp_supervisorid']; ?>"><?= htmlspecialchars($s['fyp_name']); ?></option><?php endforeach; ?>
            </select></div>
            <div class="form-group"><label>Project</label><select name="project_id" class="form-control" required>
                <option value="">-- Select Project --</option>
                <?php foreach ($proj_list ?? [] as $p): ?><option value="<?= $p['fyp_projectid']; ?>"><?= htmlspecialchars($p['fyp_projecttitle']); ?></option><?php endforeach; ?>
            </select></div>
            <div class="form-group"><label>Moderator</label><select name="moderator_id" class="form-control">
                <option value="">-- Select Moderator --</option>
                <?php foreach ($sup_list ?? [] as $s): ?><option value="<?= $s['fyp_supervisorid']; ?>"><?= htmlspecialchars($s['fyp_name']); ?></option><?php endforeach; ?>
            </select></div>
            <div class="form-row">
                <div class="form-group"><label>Academic Year</label><select name="academic_id" class="form-control" required>
                    <?php foreach ($academic_years as $ay): ?><option value="<?= $ay['fyp_academicid']; ?>"><?= $ay['fyp_acdyear'] . ' ' . $ay['fyp_intake']; ?></option><?php endforeach; ?>
                </select></div>
                <div class="form-group"><label>Type</label><select name="pairing_type" class="form-control" required>
                    <option value="Individual">Individual</option><option value="Group">Group</option>
                </select></div>
            </div>
        </div><div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('createPairingModal')">Cancel</button>
            <button type="submit" name="create_pairing" class="btn btn-primary">Create Pairing</button>
        </div></form>
    </div>
</div>

<!-- Create Assessment Set Modal -->
<div class="modal-overlay" id="createSetModal">
    <div class="modal">
        <div class="modal-header"><h3>Create Assessment Set</h3><button class="modal-close" onclick="closeModal('createSetModal')">&times;</button></div>
        <form method="POST"><div class="modal-body">
            <div class="form-group"><label>Set Name</label><input type="text" name="set_name" class="form-control" placeholder="e.g. FYP Assessment 2024" required></div>
            <div class="form-group"><label>Academic Year</label><select name="academic_id" class="form-control" required>
                <?php foreach ($academic_years as $ay): ?><option value="<?= $ay['fyp_academicid']; ?>"><?= $ay['fyp_acdyear'] . ' ' . $ay['fyp_intake']; ?></option><?php endforeach; ?>
            </select></div>
        </div><div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('createSetModal')">Cancel</button>
            <button type="submit" name="create_set" class="btn btn-primary">Create Set</button>
        </div></form>
    </div>
</div>

<!-- Create Announcement Modal -->
<div class="modal-overlay" id="createAnnouncementModal">
    <div class="modal">
        <div class="modal-header"><h3>Create Announcement</h3><button class="modal-close" onclick="closeModal('createAnnouncementModal')">&times;</button></div>
        <form method="POST"><div class="modal-body">
            <div class="form-group"><label>Subject</label><input type="text" name="subject" class="form-control" placeholder="Announcement subject" required></div>
            <div class="form-group"><label>Description</label><textarea name="description" class="form-control" rows="4" placeholder="Announcement details..." required></textarea></div>
            <div class="form-group"><label>Send To</label><select name="receiver" class="form-control" required>
                <option value="All Students">All Students</option>
                <option value="All Supervisors">All Supervisors</option>
                <option value="Everyone">Everyone</option>
            </select></div>
        </div><div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('createAnnouncementModal')">Cancel</button>
            <button type="submit" name="create_announcement" class="btn btn-primary">Post Announcement</button>
        </div></form>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }

function showRejectRegModal(id, name) {
    document.getElementById('reject_reg_id').value = id;
    document.getElementById('reject_reg_name').textContent = name;
    openModal('rejectRegModal');
}

function openEditStudentModal(studid, studname, email, contactno, progid, group_type) {
    document.getElementById('edit_studid').value = studid;
    document.getElementById('edit_studname').value = studname;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_contactno').value = contactno;
    document.getElementById('edit_progid').value = progid;
    document.getElementById('edit_group_type').value = group_type || 'Individual';
    openModal('editStudentModal');
}

function confirmDeleteStudent(studid, studname) {
    document.getElementById('delete_studid').value = studid;
    document.getElementById('delete_studname').textContent = studname;
    openModal('deleteStudentModal');
}

function filterTable(inputId, tableId) {
    const filter = document.getElementById(inputId).value.toLowerCase();
    const rows = document.getElementById(tableId).getElementsByTagName('tr');
    for (let i = 1; i < rows.length; i++) {
        let found = false;
        const cells = rows[i].getElementsByTagName('td');
        for (let j = 0; j < cells.length; j++) { if (cells[j].textContent.toLowerCase().indexOf(filter) > -1) { found = true; break; } }
        rows[i].style.display = found ? '' : 'none';
    }
}

// ==================== REGISTRATION TABLE FUNCTIONS ====================

// Select All / Deselect All
function toggleSelectAll() {
    var selectAll = document.getElementById("selectAll");
    var checkboxes = document.querySelectorAll(".reg-checkbox");
    checkboxes.forEach(function(cb) {
        if (cb.closest("tr").style.display !== "none") {
            cb.checked = selectAll.checked;
        }
    });
    updateSelectedCount();
}

// Update Selected Count
function updateSelectedCount() {
    var checkboxes = document.querySelectorAll(".reg-checkbox:checked");
    var count = checkboxes.length;
    document.getElementById("selectedCount").textContent = count + " selected";
    
    // Update select all checkbox state
    var allCheckboxes = document.querySelectorAll(".reg-checkbox");
    var visibleCheckboxes = Array.from(allCheckboxes).filter(function(cb) { return cb.closest("tr").style.display !== "none"; });
    var selectAll = document.getElementById("selectAll");
    if (selectAll) {
        selectAll.checked = visibleCheckboxes.length > 0 && visibleCheckboxes.every(function(cb) { return cb.checked; });
    }
}

// Filter Pending Table
function filterPendingTable() {
    var search = document.getElementById("searchPending").value.toLowerCase();
    var rows = document.querySelectorAll("#pendingTable tbody tr");
    
    rows.forEach(function(row) {
        var name = row.getAttribute("data-name") || "";
        var email = row.getAttribute("data-email") || "";
        var id = row.getAttribute("data-id") || "";
        
        if (name.includes(search) || email.includes(search) || id.includes(search)) {
            row.style.display = "";
        } else {
            row.style.display = "none";
        }
    });
    updateSelectedCount();
}

// Sort Pending Table
function sortPendingTable() {
    var sortBy = document.getElementById("sortPending").value;
    var tbody = document.querySelector("#pendingTable tbody");
    var rows = Array.from(tbody.querySelectorAll("tr"));
    
    rows.sort(function(a, b) {
        switch (sortBy) {
            case "newest":
                return parseInt(b.getAttribute("data-date")) - parseInt(a.getAttribute("data-date"));
            case "oldest":
                return parseInt(a.getAttribute("data-date")) - parseInt(b.getAttribute("data-date"));
            case "name_asc":
                return (a.getAttribute("data-name") || "").localeCompare(b.getAttribute("data-name") || "");
            case "name_desc":
                return (b.getAttribute("data-name") || "").localeCompare(a.getAttribute("data-name") || "");
            case "id_asc":
                return (a.getAttribute("data-id") || "").localeCompare(b.getAttribute("data-id") || "");
            case "id_desc":
                return (b.getAttribute("data-id") || "").localeCompare(a.getAttribute("data-id") || "");
            default:
                return 0;
        }
    });
    
    rows.forEach(function(row) { tbody.appendChild(row); });
}

// Sort History Table
function sortHistoryTable() {
    var sortBy = document.getElementById("sortHistory").value;
    var tbody = document.querySelector("#historyTable tbody");
    if (!tbody) return;
    var rows = Array.from(tbody.querySelectorAll("tr"));
    
    rows.sort(function(a, b) {
        switch (sortBy) {
            case "newest":
                return parseInt(b.getAttribute("data-date")) - parseInt(a.getAttribute("data-date"));
            case "oldest":
                return parseInt(a.getAttribute("data-date")) - parseInt(b.getAttribute("data-date"));
            case "name_asc":
                return (a.getAttribute("data-name") || "").localeCompare(b.getAttribute("data-name") || "");
            case "name_desc":
                return (b.getAttribute("data-name") || "").localeCompare(a.getAttribute("data-name") || "");
            default:
                return 0;
        }
    });
    
    rows.forEach(function(row) { tbody.appendChild(row); });
    filterHistoryTable(); // Re-apply status filter
}

// Filter History Table by Status
function filterHistoryTable() {
    var status = document.getElementById("filterStatus").value;
    var rows = document.querySelectorAll("#historyTable tbody tr");
    
    rows.forEach(function(row) {
        var rowStatus = row.getAttribute("data-status");
        if (status === "all" || rowStatus === status) {
            row.style.display = "";
        } else {
            row.style.display = "none";
        }
    });
}

// Single Approve
function approveSingle(regId) {
    if (confirm("Approve this registration?")) {
        document.getElementById("singleApproveId").value = regId;
        document.getElementById("singleApproveForm").submit();
    }
}

// Confirm Bulk Action
function confirmBulkAction(action) {
    var count = document.querySelectorAll(".reg-checkbox:checked").length;
    if (count === 0) {
        alert("Please select at least one registration.");
        return false;
    }
    return confirm("Are you sure you want to " + action + " " + count + " registration(s)?");
}

// Open Bulk Reject Modal
function openBulkRejectModal() {
    const count = document.querySelectorAll('.reg-checkbox:checked').length;
    if (count === 0) {
        alert('Please select at least one registration.');
        return;
    }
    document.getElementById('bulkRejectCount').textContent = count;
    openModal('bulkRejectModal');
}

// ==================== ASSESSMENT MARKS FUNCTIONS ====================

// Filter Mark Table
function filterMarkTable() {
    var searchEl = document.getElementById("markSearch");
    var filterEl = document.getElementById("markFilter");
    var search = searchEl ? searchEl.value.toLowerCase() : "";
    var filter = filterEl ? filterEl.value : "all";
    var rows = document.querySelectorAll("#markTable tbody tr");
    
    rows.forEach(function(row) {
        var name = row.getAttribute("data-name") || "";
        var id = row.getAttribute("data-id") || "";
        var passed = row.getAttribute("data-passed") || "";
        
        var matchesSearch = name.includes(search) || id.includes(search);
        var matchesFilter = filter === "all" || 
                              (filter === "passed" && passed === "yes") || 
                              (filter === "failed" && passed === "no");
        
        row.style.display = (matchesSearch && matchesFilter) ? "" : "none";
    });
}

// Edit Mark
function editMark(studid, studentName, supMark, modMark) {
    document.getElementById("edit_mark_studid").value = studid;
    document.getElementById("edit_mark_student_name").value = studentName;
    document.getElementById("edit_supervisor_mark").value = supMark;
    document.getElementById("edit_moderator_mark").value = modMark;
    openModal("editMarkModal");
}

// Quick Add Mark (from Students Without Marks table)
function quickAddMark(studid, studentDisplay, projectId) {
    projectId = projectId || null;
    // Select the student
    var studentSelect = document.querySelector('select[name="mark_studid"]');
    if (studentSelect) {
        let optionExists = false;
        for (let option of studentSelect.options) {
            if (option.value === studid) {
                option.selected = true;
                optionExists = true;
                break;
            }
        }
        if (!optionExists) {
            const newOption = new Option(studentDisplay, studid, true, true);
            studentSelect.add(newOption);
        }
    }
    
    // Select the project if provided
    if (projectId) {
        const projectSelect = document.querySelector('select[name="mark_projectid"]');
        if (projectSelect) {
            for (let option of projectSelect.options) {
                if (option.value == projectId) {
                    option.selected = true;
                    break;
                }
            }
        }
    }
    
    openModal('addMarkModal');
}

// Download CSV Template
function downloadTemplate(event) {
    event.preventDefault();
    // Use tab-separated or wrap numbers in quotes to prevent Excel conversion
    var csvContent = "EMAIL,STUDENT_ID,NAME,PROGRAMME,CONTACT\n" +
        "john.doe@student.edu.my,TP055011,John Doe,SE,\"0123456789\"\n" +
        "jane.smith@student.edu.my,TP055012,Jane Smith,AI,\"0198765432\"\n" +
        "bob.wilson@student.edu.my,TP055013,Bob Wilson,CS,\"0167891234\"\n" +
        "alice.tan@student.edu.my,TP055014,Alice Tan,SE,\"0112223344\"\n" +
        "charlie.lee@student.edu.my,TP055015,Charlie Lee,AI,\"0156667777\"";
    
    // Add BOM for Excel to recognize UTF-8
    var BOM = "\uFEFF";
    var blob = new Blob([BOM + csvContent], { type: "text/csv;charset=utf-8;" });
    var link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = "student_import_template.csv";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Copy single credentials to clipboard
function copyCredentials(email, password) {
    var text = "Email: " + email + "\nPassword: " + password;
    navigator.clipboard.writeText(text).then(function() {
        alert("Credentials copied to clipboard!");
    }).catch(function() {
        // Fallback
        var textarea = document.createElement("textarea");
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand("copy");
        document.body.removeChild(textarea);
        alert("Credentials copied to clipboard!");
    });
}

// Copy all bulk credentials to clipboard
function copyAllCredentials() {
    var table = document.querySelector("#bulkCredentialsModal table tbody");
    if (!table) return;
    
    var text = "Name\tEmail\tPassword\n";
    table.querySelectorAll("tr").forEach(function(row) {
        var cells = row.querySelectorAll("td");
        if (cells.length >= 3) {
            var name = cells[0].textContent.trim();
            var email = cells[1].textContent.trim();
            var password = cells[2].textContent.trim();
            text += name + "\t" + email + "\t" + password + "\n";
        }
    });
    
    navigator.clipboard.writeText(text).then(function() {
        alert("All credentials copied to clipboard!");
    }).catch(function() {
        var textarea = document.createElement("textarea");
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand("copy");
        document.body.removeChild(textarea);
        alert("All credentials copied to clipboard!");
    });
}

// ===================== RUBRICS ASSESSMENT FUNCTIONS =====================

// Add criteria row in Set creation
function addCriteriaRow() {
    var container = document.getElementById("criteriaContainer");
    var count = container.querySelectorAll(".criteria-row").length + 1;
    var html = '<div class="criteria-row" style="display:grid;grid-template-columns:1fr 80px 40px;gap:10px;margin-bottom:10px;">' +
        '<input type="text" name="criteria_name[]" class="form-control" placeholder="Criteria Name">' +
        '<input type="number" name="criteria_order[]" class="form-control" placeholder="Order" value="' + count + '" min="1">' +
        '<button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'.criteria-row\').remove()"><i class="fas fa-times"></i></button>' +
        '</div>';
    container.insertAdjacentHTML("beforeend", html);
}

// Edit Set
function editSet(id, academicId) {
    document.getElementById('setModalTitle').textContent = 'Edit Assessment Set';
    document.getElementById('edit_set_id').value = id;
    document.getElementById('set_academic_id').value = academicId || '';
    openModal('createSetModal');
}

// Delete Set
function deleteSet(id) {
    if (confirm('Are you sure you want to delete this assessment set?')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="delete_set" value="1"><input type="hidden" name="set_id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

// Edit Item
function editItem(item) {
    document.getElementById("itemModalTitle").textContent = "Edit Assessment Item";
    document.getElementById("edit_item_id").value = item.fyp_itemid;
    document.getElementById("item_name").value = item.fyp_itemname || "";
    document.getElementById("item_mark").value = item.fyp_originalmarkallocation || 0;
    document.querySelector('input[name="item_doc"][value="' + (item.fyp_isdocumentrequired || 1) + '"]').checked = true;
    document.querySelector('input[name="item_moderate"][value="' + (item.fyp_ismoderation || 0) + '"]').checked = true;
    document.getElementById("item_start").value = item.fyp_startdate || "";
    document.getElementById("item_deadline").value = item.fyp_finaldeadline || "";
    document.getElementById("item_setid").value = item.fyp_setid || "";
    openModal("createItemModal");
}

// Delete Item
function deleteItem(id) {
    if (confirm('Are you sure you want to delete this assessment item?')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="delete_item" value="1"><input type="hidden" name="item_id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

// Edit Criteria
function editCriteria(id, name, min, max, desc) {
    document.getElementById('criteriaModalTitle').textContent = 'Edit Assessment Criteria';
    document.getElementById('edit_criteria_id').value = id;
    document.getElementById('crit_name').value = name;
    document.getElementById('crit_min').value = min;
    document.getElementById('crit_max').value = max;
    document.getElementById('crit_desc').value = desc;
    openModal('createCriteriaModal');
}

// Delete Criteria
function deleteCriteria(id) {
    if (confirm('Are you sure you want to delete this assessment criteria?')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="delete_criteria" value="1"><input type="hidden" name="criteria_id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

// Edit Marking Criteria
function editMarking(id, name, percent) {
    document.getElementById('markingModalTitle').textContent = 'Edit Marking Criteria';
    document.getElementById('edit_marking_id').value = id;
    document.getElementById('marking_name').value = name;
    document.getElementById('marking_percent').value = percent;
    openModal('createMarkingModal');
}

// Delete Marking Criteria
function deleteMarking(id) {
    if (confirm('Are you sure you want to delete this marking criteria?')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="delete_marking" value="1"><input type="hidden" name="marking_id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

// Unlink Item from Marking Criteria
function unlinkItemCriteria(itemId, criteriaId) {
    if (confirm('Are you sure you want to remove this link?')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="unlink_item_criteria" value="1"><input type="hidden" name="unlink_itemid" value="' + itemId + '"><input type="hidden" name="unlink_criteriaid" value="' + criteriaId + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

// Reset modals when closed
document.querySelectorAll('.modal-overlay').forEach(function(modal) {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal(this.id);
        }
    });
});

// ==================== PROJECT FUNCTIONS ====================

// Filter Project Table
function filterProjectTable() {
    var searchEl = document.getElementById("projectSearch");
    var statusEl = document.getElementById("filterProjectStatus");
    var typeEl = document.getElementById("filterProjectType");
    var search = searchEl ? searchEl.value.toLowerCase() : "";
    var statusFilter = statusEl ? statusEl.value : "all";
    var typeFilter = typeEl ? typeEl.value : "all";
    var rows = document.querySelectorAll("#projectTable tbody tr");
    
    rows.forEach(function(row) {
        var title = row.getAttribute("data-title") || "";
        var supervisor = row.getAttribute("data-supervisor") || "";
        var status = row.getAttribute("data-status") || "";
        var type = row.getAttribute("data-type") || "";
        
        var matchesSearch = title.includes(search) || supervisor.includes(search);
        var matchesStatus = statusFilter === "all" || status === statusFilter;
        var matchesType = typeFilter === "all" || type === typeFilter;
        
        row.style.display = (matchesSearch && matchesStatus && matchesType) ? "" : "none";
    });
}

// Sort Project Table
function sortProjectTable() {
    var sortEl = document.getElementById("sortProject");
    var sortBy = sortEl ? sortEl.value : "newest";
    var tbody = document.querySelector("#projectTable tbody");
    if (!tbody) return;
    var rows = Array.from(tbody.querySelectorAll("tr"));
    
    rows.sort(function(a, b) {
        switch (sortBy) {
            case "newest":
                return parseInt(b.getAttribute("data-date") || 0) - parseInt(a.getAttribute("data-date") || 0);
            case "oldest":
                return parseInt(a.getAttribute("data-date") || 0) - parseInt(b.getAttribute("data-date") || 0);
            case "title_asc":
                return (a.getAttribute("data-title") || "").localeCompare(b.getAttribute("data-title") || "");
            case "title_desc":
                return (b.getAttribute("data-title") || "").localeCompare(a.getAttribute("data-title") || "");
            default:
                return 0;
        }
    });
    
    rows.forEach(function(row) { tbody.appendChild(row); });
}

// Open Edit Project Modal
function openEditProjectModal(project) {
    document.getElementById('edit_project_id').value = project.fyp_projectid;
    document.getElementById('edit_project_title').value = project.fyp_projecttitle || '';
    document.getElementById('edit_project_type').value = project.fyp_projecttype || 'Application';
    document.getElementById('edit_project_status').value = project.fyp_projectstatus || 'Available';
    document.getElementById('edit_project_category').value = project.fyp_projectcat || '';
    document.getElementById('edit_project_description').value = project.fyp_projectdesc || '';
    document.getElementById('edit_supervisor_id').value = project.fyp_supervisorid || '';
    document.getElementById('edit_max_students').value = project.fyp_maxstudent || 2;
    
    // Store original values for reset
    window.originalProjectData = {...project};
    
    openModal('editProjectModal');
}

// Reset Project Form to original values
function resetProjectForm() {
    const p = window.originalProjectData;
    if (!p) return;
    
    document.getElementById('edit_project_title').value = p.fyp_projecttitle || '';
    document.getElementById('edit_project_type').value = p.fyp_projecttype || 'Application';
    document.getElementById('edit_project_status').value = p.fyp_projectstatus || 'Available';
    document.getElementById('edit_project_category').value = p.fyp_projectcat || '';
    document.getElementById('edit_project_description').value = p.fyp_projectdesc || '';
    document.getElementById('edit_supervisor_id').value = p.fyp_supervisorid || '';
    document.getElementById('edit_max_students').value = p.fyp_maxstudent || 2;
}

// View Project Details
function viewProjectDetails(project) {
    var html = '<div style="padding:10px;">' +
        '<h4 style="color:#a78bfa;margin-bottom:15px;">' + escapeHtml(project.fyp_projecttitle) + '</h4>' +
        '<div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:20px;">' +
            '<div style="background:rgba(139,92,246,0.1);padding:12px;border-radius:8px;">' +
                '<small style="color:#64748b;">Type</small>' +
                '<p style="color:#e2e8f0;margin:5px 0 0;font-weight:600;">' + (project.fyp_projecttype || '-') + '</p>' +
            '</div>' +
            '<div style="background:rgba(139,92,246,0.1);padding:12px;border-radius:8px;">' +
                '<small style="color:#64748b;">Status</small>' +
                '<p style="color:' + ((project.fyp_projectstatus === 'Available' || project.fyp_projectstatus === 'Open') ? '#34d399' : '#fb923c') + ';margin:5px 0 0;font-weight:600;">' + (project.fyp_projectstatus || '-') + '</p>' +
            '</div>' +
        '</div>' +
        '<div style="margin-bottom:15px;">' +
            '<small style="color:#64748b;">Description</small>' +
            '<p style="color:#e2e8f0;margin:5px 0 0;line-height:1.6;">' + (escapeHtml(project.fyp_projectdesc) || 'No description provided.') + '</p>' +
        '</div>' +
        '<div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:15px;">' +
            '<div>' +
                '<small style="color:#64748b;">Category</small>' +
                '<p style="color:#e2e8f0;margin:5px 0 0;">' + (escapeHtml(project.fyp_projectcat) || '-') + '</p>' +
            '</div>' +
            '<div>' +
                '<small style="color:#64748b;">Max Students</small>' +
                '<p style="color:#e2e8f0;margin:5px 0 0;">' + (project.fyp_maxstudent || 2) + '</p>' +
            '</div>' +
        '</div>' +
        '<div style="border-top:1px solid rgba(139,92,246,0.2);padding-top:15px;margin-top:15px;">' +
            '<small style="color:#64748b;">Supervisor</small>' +
            '<p style="color:#e2e8f0;margin:5px 0 0;font-weight:600;">' + (escapeHtml(project.supervisor_name) || 'Not assigned') + '</p>' +
            (project.supervisor_email ? '<p style="color:#94a3b8;margin:5px 0 0;font-size:0.85rem;">' + escapeHtml(project.supervisor_email) + '</p>' : '') +
        '</div>' +
    '</div>';
    
    document.getElementById('viewProjectContent').innerHTML = html;
    openModal('viewProjectModal');
}

// Escape HTML for safe display
function escapeHtml(text) {
    if (!text) return "";
    var div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
}

// Confirm Delete Project
function confirmDeleteProject(projectId, projectTitle) {
    document.getElementById("delete_project_id").value = projectId;
    document.getElementById("delete_project_title").textContent = projectTitle;
    openModal("deleteProjectModal");
}

// ==================== ASSESSMENT MARKS FUNCTIONS ====================

// Calculate average and scaled mark for assessment
function calculateAvgMark(criteriaId, percentAllocation) {
    var initialEl = document.querySelector('input[name="initial_work[' + criteriaId + ']"]');
    var finalEl = document.querySelector('input[name="final_work[' + criteriaId + ']"]');
    var moderatorEl = document.querySelector('input[name="moderator_mark[' + criteriaId + ']"]');
    
    var initial = initialEl ? parseFloat(initialEl.value) || 0 : 0;
    var final = finalEl ? parseFloat(finalEl.value) || 0 : 0;
    var moderator = moderatorEl ? parseFloat(moderatorEl.value) || 0 : 0;
    
    // Calculate average of non-zero values
    var values = [];
    if (initial > 0) values.push(initial);
    if (final > 0) values.push(final);
    if (moderator > 0) values.push(moderator);
    
    var avg = 0;
    if (values.length > 0) {
        var sum = 0;
        for (var i = 0; i < values.length; i++) {
            sum += values[i];
        }
        avg = sum / values.length;
    }
    
    // Calculate scaled mark
    var scaled = (avg * percentAllocation) / 100;
    
    // Update display
    var avgEl = document.getElementById("avg_" + criteriaId);
    var scaledEl = document.getElementById("scaled_" + criteriaId);
    
    if (avgEl) avgEl.value = avg.toFixed(2);
    if (scaledEl) scaledEl.value = scaled.toFixed(2);
    
    // Recalculate total
    calculateTotalMark();
}

// Calculate final subtotal
function calculateTotalMark() {
    var scaledInputs = document.querySelectorAll('input[id^="scaled_"]');
    var total = 0;
    
    scaledInputs.forEach(function(input) {
        total += parseFloat(input.value) || 0;
    });
    
    var finalEl = document.getElementById("finalSubtotal");
    if (finalEl) {
        finalEl.value = total.toFixed(2);
    }
}

// Export marks to CSV
function exportMarksToCSV() {
    var table = document.getElementById("marksTable");
    if (!table) return;
    
    var csv = [];
    var rows = table.querySelectorAll("tr");
    
    rows.forEach(function(row) {
        var cols = row.querySelectorAll("th, td");
        var rowData = [];
        cols.forEach(function(col, index) {
            // Skip last column (actions)
            if (index < cols.length - 1) {
                var text = col.textContent.trim().replace(/"/g, '""');
                rowData.push('"' + text + '"');
            }
        });
        if (rowData.length > 0) {
            csv.push(rowData.join(","));
        }
    });
    
    var csvContent = "\uFEFF" + csv.join("\n");
    var blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
    var link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = "student_marks_" + new Date().toISOString().slice(0,10) + ".csv";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Initialize marks calculation on page load
document.addEventListener("DOMContentLoaded", function() {
    if (document.getElementById("finalSubtotal")) {
        calculateTotalMark();
    }
    
    // Initialize filter modal radio buttons styling
    initFilterRadioButtons();
    
    // Initialize search input
    var searchInput = document.getElementById("marksSearchInput");
    if (searchInput && searchInput.value) {
        document.getElementById("clearSearchBtn").style.display = "block";
    }
});

// ==================== LIVE SEARCH FUNCTIONS ====================

var searchTimeout = null;

// Live search with debounce
function liveSearchMarks(query) {
    var clearBtn = document.getElementById("clearSearchBtn");
    if (clearBtn) {
        clearBtn.style.display = query.length > 0 ? "block" : "none";
    }
    
    // Clear previous timeout
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }
    
    // Debounce search
    searchTimeout = setTimeout(function() {
        performLiveSearch(query);
    }, 150);
}

// Perform the actual search
function performLiveSearch(query) {
    var dropdown = document.getElementById("searchPreviewDropdown");
    var content = document.getElementById("searchPreviewContent");
    var tableBody = document.getElementById("marksTableBody");
    
    if (!query || query.length < 1) {
        dropdown.style.display = "none";
        // Show all rows
        if (tableBody) {
            var rows = tableBody.querySelectorAll("tr");
            rows.forEach(function(row) {
                row.style.display = "";
            });
            updateResultsCount(rows.length);
        }
        return;
    }
    
    query = query.toLowerCase();
    var matches = [];
    
    // Search through allStudentsData
    if (typeof allStudentsData !== "undefined") {
        allStudentsData.forEach(function(student) {
            var searchStr = (student.id + " " + student.name + " " + student.project + " " + student.supervisor).toLowerCase();
            if (searchStr.indexOf(query) !== -1) {
                matches.push(student);
            }
        });
    }
    
    // Update preview dropdown
    if (matches.length > 0) {
        var html = '<div style="padding:5px 10px;color:#64748b;font-size:0.8rem;border-bottom:1px solid rgba(139,92,246,0.2);margin-bottom:10px;">' + 
                   '<i class="fas fa-search"></i> Found ' + matches.length + ' result(s)</div>';
        
        matches.slice(0, 8).forEach(function(student) {
            var markBadge = student.total_mark ? 
                '<span style="background:' + (student.total_mark >= 50 ? 'rgba(16,185,129,0.2);color:#34d399' : 'rgba(239,68,68,0.2);color:#f87171') + ';padding:3px 10px;border-radius:10px;font-size:0.8rem;">' + parseFloat(student.total_mark).toFixed(1) + '%</span>' :
                '<span style="background:rgba(245,158,11,0.2);color:#fbbf24;padding:3px 10px;border-radius:10px;font-size:0.8rem;">Pending</span>';
            
            html += '<div class="search-preview-item" onclick="selectSearchResult(\'' + student.id + '\')" ' +
                    'style="padding:12px 15px;cursor:pointer;border-radius:8px;margin-bottom:5px;transition:all 0.2s;display:flex;justify-content:space-between;align-items:center;" ' +
                    'onmouseover="this.style.background=\'rgba(139,92,246,0.2)\'" onmouseout="this.style.background=\'transparent\'">' +
                    '<div>' +
                    '<div style="font-weight:600;color:#e2e8f0;">' + escapeHtml(student.name) + '</div>' +
                    '<div style="font-size:0.85rem;color:#64748b;margin-top:3px;">' +
                    '<span style="color:#a78bfa;">' + escapeHtml(student.id) + '</span>';
            
            if (student.project) {
                html += ' &bull; ' + escapeHtml(student.project.substring(0, 30)) + (student.project.length > 30 ? '...' : '');
            }
            
            html += '</div></div>' + markBadge + '</div>';
        });
        
        if (matches.length > 8) {
            html += '<div style="padding:10px;text-align:center;color:#64748b;font-size:0.85rem;border-top:1px solid rgba(139,92,246,0.2);">' +
                    'Showing 8 of ' + matches.length + ' results. Type more to narrow down.</div>';
        }
        
        content.innerHTML = html;
        dropdown.style.display = "block";
    } else {
        content.innerHTML = '<div style="padding:20px;text-align:center;color:#64748b;">' +
                           '<i class="fas fa-search" style="font-size:2rem;margin-bottom:10px;opacity:0.5;"></i>' +
                           '<p>No students found matching "' + escapeHtml(query) + '"</p></div>';
        dropdown.style.display = "block";
    }
    
    // Also filter the table
    if (tableBody) {
        var visibleCount = 0;
        var rows = tableBody.querySelectorAll("tr");
        rows.forEach(function(row) {
            var studid = (row.getAttribute("data-studid") || "").toLowerCase();
            var name = (row.getAttribute("data-name") || "").toLowerCase();
            var project = (row.getAttribute("data-project") || "").toLowerCase();
            var supervisor = (row.getAttribute("data-supervisor") || "").toLowerCase();
            
            var searchStr = studid + " " + name + " " + project + " " + supervisor;
            if (searchStr.indexOf(query) !== -1) {
                row.style.display = "";
                visibleCount++;
            } else {
                row.style.display = "none";
            }
        });
        updateResultsCount(visibleCount);
    }
}

// Select search result
function selectSearchResult(studentId) {
    window.location.href = "?page=marks&action=mark&mark_student=" + encodeURIComponent(studentId);
}

// Show search preview on focus
function showSearchPreview() {
    var input = document.getElementById("marksSearchInput");
    if (input && input.value.length > 0) {
        performLiveSearch(input.value);
    }
}

// Clear search
function clearMarksSearch() {
    var input = document.getElementById("marksSearchInput");
    if (input) {
        input.value = "";
        input.focus();
    }
    document.getElementById("clearSearchBtn").style.display = "none";
    document.getElementById("searchPreviewDropdown").style.display = "none";
    
    // Show all rows
    var tableBody = document.getElementById("marksTableBody");
    if (tableBody) {
        var rows = tableBody.querySelectorAll("tr");
        rows.forEach(function(row) {
            row.style.display = "";
        });
        updateResultsCount(rows.length);
    }
}

// Update results count
function updateResultsCount(count) {
    var countEl = document.getElementById("resultsCount");
    if (countEl) {
        countEl.textContent = "Showing " + count + " student(s)";
    }
}

// Close search dropdown when clicking outside
document.addEventListener("click", function(e) {
    var dropdown = document.getElementById("searchPreviewDropdown");
    var searchInput = document.getElementById("marksSearchInput");
    if (dropdown && searchInput) {
        if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.style.display = "none";
        }
    }
});

// ==================== FILTER MODAL FUNCTIONS ====================

// Initialize radio button styling
function initFilterRadioButtons() {
    var intakeOptions = document.querySelectorAll(".intake-option input");
    intakeOptions.forEach(function(input) {
        updateRadioStyle(input);
        input.addEventListener("change", function() {
            document.querySelectorAll(".intake-option input").forEach(function(i) {
                updateRadioStyle(i);
            });
        });
    });
    
    var statusOptions = document.querySelectorAll(".status-option input");
    statusOptions.forEach(function(input) {
        updateRadioStyle(input);
        input.addEventListener("change", function() {
            document.querySelectorAll(".status-option input").forEach(function(i) {
                updateRadioStyle(i);
            });
        });
    });
}

// Update radio button visual style
function updateRadioStyle(input) {
    var span = input.nextElementSibling;
    if (span) {
        if (input.checked) {
            span.style.background = "rgba(139,92,246,0.3)";
            span.style.borderColor = "#8b5cf6";
            span.style.color = "#a78bfa";
        } else {
            span.style.background = "rgba(30,41,59,0.6)";
            span.style.borderColor = "transparent";
            span.style.color = "#94a3b8";
        }
    }
}

// Reset filter form
function resetFilterForm() {
    var form = document.querySelector("#marksFilterModal form");
    if (form) {
        form.querySelectorAll("select").forEach(function(s) { s.value = ""; });
        form.querySelectorAll('input[type="radio"][value=""]').forEach(function(r) { 
            r.checked = true; 
        });
        initFilterRadioButtons();
    }
}

// Close modal when clicking outside
document.querySelectorAll(".modal-overlay").forEach(function(m) {
    m.addEventListener("click", function(e) {
        if (e.target === this) {
            this.classList.remove("active");
        }
    });
});
</script>

</body>
</html>