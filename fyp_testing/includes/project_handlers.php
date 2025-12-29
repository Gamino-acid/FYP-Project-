<?php
/**
 * FILE 4: includes/project_handlers.php
 * Handles: Add Project, Update Project, Delete Project
 */

// Add Project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_project'])) {
    $title = trim($_POST['project_title']);
    $type = $_POST['project_type'];
    $status = $_POST['project_status'];
    $category = trim($_POST['project_category'] ?? '');
    $description = trim($_POST['project_description'] ?? '');
    $supervisor_id = !empty($_POST['supervisor_id']) ? intval($_POST['supervisor_id']) : null;
    $max_students = intval($_POST['max_students'] ?? 1);
    
    $stmt = $conn->prepare("INSERT INTO project (fyp_projecttitle, fyp_projecttype, fyp_projectstatus, fyp_projectcat, fyp_projectdesc, fyp_supervisorid, fyp_maxstudent, fyp_datecreated) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("sssssii", $title, $type, $status, $category, $description, $supervisor_id, $max_students);
    
    if ($stmt->execute()) {
        $message = "Project added successfully!";
        $message_type = 'success';
    } else {
        $message = "Error adding project: " . $stmt->error;
        $message_type = 'error';
    }
    $stmt->close();
}

// Update Project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_project'])) {
    $project_id = intval($_POST['project_id']);
    $title = trim($_POST['project_title']);
    $type = $_POST['project_type'];
    $status = $_POST['project_status'];
    $category = trim($_POST['project_category'] ?? '');
    $description = trim($_POST['project_description'] ?? '');
    $supervisor_id = !empty($_POST['supervisor_id']) ? intval($_POST['supervisor_id']) : null;
    $max_students = intval($_POST['max_students'] ?? 1);
    
    $stmt = $conn->prepare("UPDATE project SET fyp_projecttitle = ?, fyp_projecttype = ?, fyp_projectstatus = ?, fyp_projectcat = ?, fyp_projectdesc = ?, fyp_supervisorid = ?, fyp_maxstudent = ? WHERE fyp_projectid = ?");
    $stmt->bind_param("sssssiii", $title, $type, $status, $category, $description, $supervisor_id, $max_students, $project_id);
    
    if ($stmt->execute()) {
        $message = "Project updated successfully!";
        $message_type = 'success';
    } else {
        $message = "Error updating project: " . $stmt->error;
        $message_type = 'error';
    }
    $stmt->close();
}

// Delete Project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_project'])) {
    $project_id = intval($_POST['project_id']);
    
    $stmt = $conn->prepare("DELETE FROM project WHERE fyp_projectid = ?");
    $stmt->bind_param("i", $project_id);
    
    if ($stmt->execute()) {
        $message = "Project deleted successfully!";
        $message_type = 'success';
    } else {
        $message = "Error deleting project: " . $stmt->error;
        $message_type = 'error';
    }
    $stmt->close();
}
?>