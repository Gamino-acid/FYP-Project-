<?php
/**
 * RUBRICS HANDLERS
 * includes/rubrics_handlers.php
 * Handles all POST actions for rubrics assessment
 */

// Handle POST - Add Assessment Set
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_set'])) {
    $setname = trim($_POST['setname']);
    $academic_id = $_POST['academic_id'];
    
    if (empty($setname)) {
        $message = "Set name is required.";
        $message_type = 'error';
    } else {
        $stmt = $conn->prepare("INSERT INTO `set` (fyp_setname, fyp_academicid) VALUES (?, ?)");
        $stmt->bind_param("si", $setname, $academic_id);
        if ($stmt->execute()) {
            $message = "Assessment set '$setname' created successfully!";
            $message_type = 'success';
        } else {
            $message = "Error creating set: " . $conn->error;
            $message_type = 'error';
        }
        $stmt->close();
    }
}

// Handle POST - Update Assessment Set
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_set'])) {
    $set_id = $_POST['set_id'];
    $setname = trim($_POST['setname']);
    $academic_id = $_POST['academic_id'];
    
    $stmt = $conn->prepare("UPDATE `set` SET fyp_setname = ?, fyp_academicid = ? WHERE fyp_setid = ?");
    $stmt->bind_param("sii", $setname, $academic_id, $set_id);
    if ($stmt->execute()) {
        $message = "Assessment set updated successfully!";
        $message_type = 'success';
    }
    $stmt->close();
}

// Handle POST - Delete Assessment Set
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_set'])) {
    $set_id = $_POST['set_id'];
    $stmt = $conn->prepare("DELETE FROM `set` WHERE fyp_setid = ?");
    $stmt->bind_param("i", $set_id);
    if ($stmt->execute()) {
        $message = "Assessment set deleted successfully!";
        $message_type = 'success';
    }
    $stmt->close();
}

// Handle POST - Add Item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    $itemname = trim($_POST['itemname']);
    $itemdesc = trim($_POST['itemdesc'] ?? '');
    
    if (empty($itemname)) {
        $message = "Item name is required.";
        $message_type = 'error';
    } else {
        $stmt = $conn->prepare("INSERT INTO item (fyp_itemname, fyp_itemdesc) VALUES (?, ?)");
        $stmt->bind_param("ss", $itemname, $itemdesc);
        if ($stmt->execute()) {
            $message = "Item '$itemname' added successfully!";
            $message_type = 'success';
        }
        $stmt->close();
    }
}

// Handle POST - Update Item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_item'])) {
    $item_id = $_POST['item_id'];
    $itemname = trim($_POST['itemname']);
    $itemdesc = trim($_POST['itemdesc'] ?? '');
    
    $stmt = $conn->prepare("UPDATE item SET fyp_itemname = ?, fyp_itemdesc = ? WHERE fyp_itemid = ?");
    $stmt->bind_param("ssi", $itemname, $itemdesc, $item_id);
    if ($stmt->execute()) {
        $message = "Item updated successfully!";
        $message_type = 'success';
    }
    $stmt->close();
}

// Handle POST - Delete Item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_item'])) {
    $item_id = $_POST['item_id'];
    $stmt = $conn->prepare("DELETE FROM item WHERE fyp_itemid = ?");
    $stmt->bind_param("i", $item_id);
    if ($stmt->execute()) {
        $message = "Item deleted successfully!";
        $message_type = 'success';
    }
    $stmt->close();
}

// Handle POST - Add Marking Criteria
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_marking_criteria'])) {
    $criterianame = trim($_POST['criterianame']);
    $percent = floatval($_POST['percentallocation']);
    
    if (empty($criterianame)) {
        $message = "Criteria name is required.";
        $message_type = 'error';
    } elseif ($percent < 0 || $percent > 100) {
        $message = "Percentage must be between 0 and 100.";
        $message_type = 'error';
    } else {
        $stmt = $conn->prepare("INSERT INTO marking_criteria (fyp_criterianame, fyp_percentallocation) VALUES (?, ?)");
        $stmt->bind_param("sd", $criterianame, $percent);
        if ($stmt->execute()) {
            $message = "Marking criteria '$criterianame' added successfully!";
            $message_type = 'success';
        }
        $stmt->close();
    }
}

// Handle POST - Update Marking Criteria
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_marking_criteria'])) {
    $criteria_id = $_POST['criteria_id'];
    $criterianame = trim($_POST['criterianame']);
    $percent = floatval($_POST['percentallocation']);
    
    $stmt = $conn->prepare("UPDATE marking_criteria SET fyp_criterianame = ?, fyp_percentallocation = ? WHERE fyp_criteriaid = ?");
    $stmt->bind_param("sdi", $criterianame, $percent, $criteria_id);
    if ($stmt->execute()) {
        $message = "Marking criteria updated successfully!";
        $message_type = 'success';
    }
    $stmt->close();
}

// Handle POST - Delete Marking Criteria
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_marking_criteria'])) {
    $criteria_id = $_POST['criteria_id'];
    $stmt = $conn->prepare("DELETE FROM marking_criteria WHERE fyp_criteriaid = ?");
    $stmt->bind_param("i", $criteria_id);
    if ($stmt->execute()) {
        $message = "Marking criteria deleted successfully!";
        $message_type = 'success';
    }
    $stmt->close();
}

// Handle POST - Link Item to Marking Criteria
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['link_item_criteria'])) {
    $item_id = $_POST['item_id'];
    $criteria_id = $_POST['criteria_id'];
    
    // Check if link already exists
    $stmt = $conn->prepare("SELECT * FROM item_marking_criteria WHERE fyp_itemid = ? AND fyp_criteriaid = ?");
    $stmt->bind_param("ii", $item_id, $criteria_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $message = "This link already exists.";
        $message_type = 'error';
    } else {
        $stmt->close();
        $stmt = $conn->prepare("INSERT INTO item_marking_criteria (fyp_itemid, fyp_criteriaid) VALUES (?, ?)");
        $stmt->bind_param("ii", $item_id, $criteria_id);
        if ($stmt->execute()) {
            $message = "Item linked to criteria successfully!";
            $message_type = 'success';
        }
    }
    $stmt->close();
}

// Handle POST - Unlink Item from Marking Criteria
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unlink_item_criteria'])) {
    $link_id = $_POST['link_id'];
    $stmt = $conn->prepare("DELETE FROM item_marking_criteria WHERE fyp_itemmarkingid = ?");
    $stmt->bind_param("i", $link_id);
    if ($stmt->execute()) {
        $message = "Link removed successfully!";
        $message_type = 'success';
    }
    $stmt->close();
}

// Handle POST - Add Assessment Criteria (Grade)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_assessment_criteria'])) {
    $grade = trim($_POST['grade']);
    $min = floatval($_POST['min_percent']);
    $max = floatval($_POST['max_percent']);
    
    if (empty($grade)) {
        $message = "Grade name is required.";
        $message_type = 'error';
    } elseif ($min > $max) {
        $message = "Minimum percentage cannot be greater than maximum.";
        $message_type = 'error';
    } else {
        $stmt = $conn->prepare("INSERT INTO assessment_criteria (fyp_grade, fyp_min, fyp_max) VALUES (?, ?, ?)");
        $stmt->bind_param("sdd", $grade, $min, $max);
        if ($stmt->execute()) {
            $message = "Assessment criteria '$grade' added successfully!";
            $message_type = 'success';
        }
        $stmt->close();
    }
}
?>