<?php
/**
 * PROJECT HANDLERS
 * includes/project_handlers.php
 * Handles all POST actions for project management
 */

// Handle POST - Add Project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_project'])) {
    $title = trim($_POST['project_title']);
    $type = $_POST['project_type'];
    $status = $_POST['project_status'];
    $category = trim($_POST['project_category'] ?? '');
    $description = trim($_POST['project_description'] ?? '');
    $supervisor_id = !empty($_POST['supervisor_id']) ? $_POST['supervisor_id'] : null;
    $max_students = $_POST['max_students'] ?? 1;
    
    if (empty($title)) {
        $message = "Project title is required.";
        $message_type = 'error';
    } else {
        $stmt = $conn->prepare("INSERT INTO project (fyp_projecttitle, fyp_projecttype, fyp_projectstatus, fyp_projectcat, fyp_projectdesc, fyp_supervisorid, fyp_maxstudent, fyp_datecreated) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("sssssii", $title, $type, $status, $category, $description, $supervisor_id, $max_students);
        if ($stmt->execute()) {
            $message = "Project '$title' added successfully!";
            $message_type = 'success';
        } else {
            $message = "Error adding project: " . $conn->error;
            $message_type = 'error';
        }
        $stmt->close();
    }
}

// Handle POST - Update Project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_project'])) {
    $project_id = $_POST['project_id'];
    $title = trim($_POST['project_title']);
    $type = $_POST['project_type'];
    $status = $_POST['project_status'];
    $category = trim($_POST['project_category'] ?? '');
    $description = trim($_POST['project_description'] ?? '');
    $supervisor_id = !empty($_POST['supervisor_id']) ? $_POST['supervisor_id'] : null;
    $max_students = $_POST['max_students'] ?? 1;
    
    if (empty($title)) {
        $message = "Project title is required.";
        $message_type = 'error';
    } else {
        $stmt = $conn->prepare("UPDATE project SET fyp_projecttitle = ?, fyp_projecttype = ?, fyp_projectstatus = ?, fyp_projectcat = ?, fyp_projectdesc = ?, fyp_supervisorid = ?, fyp_maxstudent = ? WHERE fyp_projectid = ?");
        $stmt->bind_param("sssssiii", $title, $type, $status, $category, $description, $supervisor_id, $max_students, $project_id);
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

// Handle POST - Delete Project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_project'])) {
    $project_id = $_POST['project_id'];
    
    // Check if project has pairings
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM pairing WHERE fyp_projectid = ?");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($result['cnt'] > 0) {
        $message = "Cannot delete project. It has {$result['cnt']} student pairing(s). Remove pairings first.";
        $message_type = 'error';
    } else {
        $stmt = $conn->prepare("DELETE FROM project WHERE fyp_projectid = ?");
        $stmt->bind_param("i", $project_id);
        if ($stmt->execute()) {
            $message = "Project deleted successfully!";
            $message_type = 'success';
        } else {
            $message = "Error deleting project: " . $conn->error;
            $message_type = 'error';
        }
        $stmt->close();
    }
}

// Handle POST - Bulk Update Project Status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_update_status'])) {
    $project_ids = $_POST['project_ids'] ?? [];
    $new_status = $_POST['bulk_status'];
    
    if (empty($project_ids)) {
        $message = "No projects selected.";
        $message_type = 'error';
    } else {
        $updated = 0;
        foreach ($project_ids as $pid) {
            $stmt = $conn->prepare("UPDATE project SET fyp_projectstatus = ? WHERE fyp_projectid = ?");
            $stmt->bind_param("si", $new_status, $pid);
            if ($stmt->execute()) {
                $updated++;
            }
            $stmt->close();
        }
        $message = "Updated status for $updated project(s).";
        $message_type = 'success';
    }
}

// Handle POST - Assign Supervisor to Project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_supervisor'])) {
    $project_id = $_POST['project_id'];
    $supervisor_id = $_POST['supervisor_id'];
    
    $stmt = $conn->prepare("UPDATE project SET fyp_supervisorid = ? WHERE fyp_projectid = ?");
    $stmt->bind_param("ii", $supervisor_id, $project_id);
    if ($stmt->execute()) {
        $message = "Supervisor assigned successfully!";
        $message_type = 'success';
    } else {
        $message = "Error assigning supervisor.";
        $message_type = 'error';
    }
    $stmt->close();
}

// Handle POST - Duplicate Project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['duplicate_project'])) {
    $project_id = $_POST['project_id'];
    
    $stmt = $conn->prepare("SELECT * FROM project WHERE fyp_projectid = ?");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $project = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($project) {
        $new_title = $project['fyp_projecttitle'] . ' (Copy)';
        $stmt = $conn->prepare("INSERT INTO project (fyp_projecttitle, fyp_projecttype, fyp_projectstatus, fyp_projectcat, fyp_projectdesc, fyp_supervisorid, fyp_maxstudent, fyp_datecreated) VALUES (?, ?, 'Available', ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssssii", $new_title, $project['fyp_projecttype'], $project['fyp_projectcat'], $project['fyp_projectdesc'], $project['fyp_supervisorid'], $project['fyp_maxstudent']);
        if ($stmt->execute()) {
            $message = "Project duplicated successfully!";
            $message_type = 'success';
        }
        $stmt->close();
    }
}
?>