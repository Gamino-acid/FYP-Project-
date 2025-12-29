<?php
/**
 * FILE 5: includes/rubrics_handlers.php
 * Handles: Add Set, Add Item, Add Marking Criteria
 */

// Add Set
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_set'])) {
    $setname = trim($_POST['setname']);
    $academic_id = intval($_POST['academic_id']);
    
    $stmt = $conn->prepare("INSERT INTO `set` (fyp_setname, fyp_academicid) VALUES (?, ?)");
    $stmt->bind_param("si", $setname, $academic_id);
    
    if ($stmt->execute()) {
        $message = "Assessment set added successfully!";
        $message_type = 'success';
    } else {
        $message = "Error adding set: " . $stmt->error;
        $message_type = 'error';
    }
    $stmt->close();
}

// Add Item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    $itemname = trim($_POST['itemname']);
    $itemdesc = trim($_POST['itemdesc'] ?? '');
    
    $stmt = $conn->prepare("INSERT INTO item (fyp_itemname, fyp_itemdesc) VALUES (?, ?)");
    $stmt->bind_param("ss", $itemname, $itemdesc);
    
    if ($stmt->execute()) {
        $message = "Item added successfully!";
        $message_type = 'success';
    } else {
        $message = "Error adding item: " . $stmt->error;
        $message_type = 'error';
    }
    $stmt->close();
}

// Add Marking Criteria
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_marking_criteria'])) {
    $criterianame = trim($_POST['criterianame']);
    $percent = floatval($_POST['percentallocation']);
    
    $stmt = $conn->prepare("INSERT INTO marking_criteria (fyp_criterianame, fyp_percentallocation) VALUES (?, ?)");
    $stmt->bind_param("sd", $criterianame, $percent);
    
    if ($stmt->execute()) {
        $message = "Marking criteria added successfully!";
        $message_type = 'success';
    } else {
        $message = "Error adding criteria: " . $stmt->error;
        $message_type = 'error';
    }
    $stmt->close();
}
?>