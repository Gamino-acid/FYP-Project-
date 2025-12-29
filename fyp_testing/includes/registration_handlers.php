<?php
/**
 * FILE 3: includes/registration_handlers.php
 * Handles: Group Requests, Student Registration, Student Edit/Delete, Pairing
 */

// Group Request - Update Status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_request_status'])) {
    $request_id = intval($_POST['request_id']);
    $new_status = $_POST['new_status'];
    
    $stmt = $conn->prepare("UPDATE group_request SET request_status = ? WHERE request_id = ?");
    $stmt->bind_param("si", $new_status, $request_id);
    
    if ($stmt->execute()) {
        $message = "Group request status updated to: $new_status";
        $message_type = 'success';
    } else {
        $message = "Error updating status.";
        $message_type = 'error';
    }
    $stmt->close();
}

// Approve Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_registration'])) {
    $reg_id = intval($_POST['registration_id']);
    
    // Get registration data
    $stmt = $conn->prepare("SELECT * FROM pending_registration WHERE id = ?");
    $stmt->bind_param("i", $reg_id);
    $stmt->execute();
    $reg = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($reg) {
        // Generate credentials
        $username = strtolower(str_replace(' ', '', $reg['student_id']));
        $password = bin2hex(random_bytes(4));
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Create user account
        $stmt = $conn->prepare("INSERT INTO user (fyp_username, fyp_password, fyp_usertype) VALUES (?, ?, 'student')");
        $stmt->bind_param("ss", $username, $hashed_password);
        $stmt->execute();
        $user_id = $conn->insert_id;
        $stmt->close();
        
        // Create student record
        $stmt = $conn->prepare("INSERT INTO student (fyp_studfullid, fyp_studname, fyp_email, fyp_contactno, fyp_progid, fyp_academicid, fyp_userid) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssiis", $reg['student_id'], $reg['full_name'], $reg['email'], $reg['phone'], $reg['programme_id'], $reg['academic_year_id'], $user_id);
        $stmt->execute();
        $stmt->close();
        
        // Update registration status
        $stmt = $conn->prepare("UPDATE pending_registration SET status = 'approved' WHERE id = ?");
        $stmt->bind_param("i", $reg_id);
        $stmt->execute();
        $stmt->close();
        
        $message = "Registration approved! Username: <strong>$username</strong>, Password: <strong>$password</strong>";
        $message_type = 'success';
    }
}

// Reject Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_registration'])) {
    $reg_id = intval($_POST['registration_id']);
    
    $stmt = $conn->prepare("UPDATE pending_registration SET status = 'rejected' WHERE id = ?");
    $stmt->bind_param("i", $reg_id);
    
    if ($stmt->execute()) {
        $message = "Registration rejected.";
        $message_type = 'success';
    } else {
        $message = "Error rejecting registration.";
        $message_type = 'error';
    }
    $stmt->close();
}

// Student - Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_student'])) {
    $studid = $_POST['studid'];
    $studname = trim($_POST['studname']);
    $email = trim($_POST['email']);
    $contact = trim($_POST['contactno']);
    $progid = intval($_POST['progid']);
    $group_type = $_POST['group_type'];
    
    $stmt = $conn->prepare("UPDATE student SET fyp_studname = ?, fyp_email = ?, fyp_contactno = ?, fyp_progid = ?, fyp_group = ? WHERE fyp_studid = ?");
    $stmt->bind_param("sssiss", $studname, $email, $contact, $progid, $group_type, $studid);
    
    if ($stmt->execute()) {
        $message = "Student information updated successfully!";
        $message_type = 'success';
    } else {
        $message = "Error updating student.";
        $message_type = 'error';
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
    } else {
        $message = "Error deleting student.";
        $message_type = 'error';
    }
    $stmt->close();
}

// Create Pairing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_pairing'])) {
    $supervisor_id = intval($_POST['supervisor_id']);
    $project_id = intval($_POST['project_id']);
    $moderator_id = !empty($_POST['moderator_id']) ? intval($_POST['moderator_id']) : null;
    $academic_id = intval($_POST['academic_id']);
    $pairing_type = $_POST['pairing_type'];
    
    $stmt = $conn->prepare("INSERT INTO pairing (fyp_supervisorid, fyp_projectid, fyp_moderatorid, fyp_academicid, fyp_type, fyp_datecreated) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("iiiss", $supervisor_id, $project_id, $moderator_id, $academic_id, $pairing_type);
    
    if ($stmt->execute()) {
        $message = "Pairing created successfully!";
        $message_type = 'success';
    } else {
        $message = "Error creating pairing.";
        $message_type = 'error';
    }
    $stmt->close();
}
?>