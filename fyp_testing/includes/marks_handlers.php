<?php
/**
 * MARKS HANDLERS
 * includes/marks_handlers.php
 * Handles all POST actions for assessment marks
 */

// Handle POST - Update Student Mark
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_mark'])) {
    $student_id = $_POST['student_id'];
    $item_id = $_POST['item_id'];
    $mark = floatval($_POST['mark']);
    $assessor_type = $_POST['assessor_type'] ?? 'supervisor';
    
    // Check if mark exists
    $stmt = $conn->prepare("SELECT * FROM mark WHERE fyp_studid = ? AND fyp_itemid = ? AND fyp_assessortype = ?");
    $stmt->bind_param("sis", $student_id, $item_id, $assessor_type);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($existing) {
        // Update existing mark
        $stmt = $conn->prepare("UPDATE mark SET fyp_mark = ?, fyp_dateupdated = NOW() WHERE fyp_markid = ?");
        $stmt->bind_param("di", $mark, $existing['fyp_markid']);
    } else {
        // Insert new mark
        $stmt = $conn->prepare("INSERT INTO mark (fyp_studid, fyp_itemid, fyp_mark, fyp_assessortype, fyp_datecreated) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("sids", $student_id, $item_id, $mark, $assessor_type);
    }
    
    if ($stmt->execute()) {
        $message = "Mark updated successfully!";
        $message_type = 'success';
        
        // Recalculate total mark
        recalculateTotalMark($conn, $student_id);
    } else {
        $message = "Error updating mark.";
        $message_type = 'error';
    }
    $stmt->close();
}

// Handle POST - Bulk Update Marks
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_update_marks'])) {
    $student_id = $_POST['student_id'];
    $marks = $_POST['marks'] ?? [];
    
    $updated = 0;
    foreach ($marks as $item_id => $mark_value) {
        if (is_numeric($mark_value)) {
            $mark = floatval($mark_value);
            $assessor_type = 'supervisor';
            
            $stmt = $conn->prepare("SELECT fyp_markid FROM mark WHERE fyp_studid = ? AND fyp_itemid = ?");
            $stmt->bind_param("si", $student_id, $item_id);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($existing) {
                $stmt = $conn->prepare("UPDATE mark SET fyp_mark = ?, fyp_dateupdated = NOW() WHERE fyp_markid = ?");
                $stmt->bind_param("di", $mark, $existing['fyp_markid']);
            } else {
                $stmt = $conn->prepare("INSERT INTO mark (fyp_studid, fyp_itemid, fyp_mark, fyp_assessortype, fyp_datecreated) VALUES (?, ?, ?, ?, NOW())");
                $stmt->bind_param("sids", $student_id, $item_id, $mark, $assessor_type);
            }
            
            if ($stmt->execute()) {
                $updated++;
            }
            $stmt->close();
        }
    }
    
    // Recalculate total
    recalculateTotalMark($conn, $student_id);
    
    $message = "Updated $updated mark(s) successfully!";
    $message_type = 'success';
}

// Handle POST - Delete Mark
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_mark'])) {
    $mark_id = $_POST['mark_id'];
    $student_id = $_POST['student_id'];
    
    $stmt = $conn->prepare("DELETE FROM mark WHERE fyp_markid = ?");
    $stmt->bind_param("i", $mark_id);
    if ($stmt->execute()) {
        recalculateTotalMark($conn, $student_id);
        $message = "Mark deleted successfully!";
        $message_type = 'success';
    }
    $stmt->close();
}

// Handle POST - Finalize Student Marks
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finalize_marks'])) {
    $student_id = $_POST['student_id'];
    
    // Calculate and save final grade
    recalculateTotalMark($conn, $student_id);
    
    $message = "Marks finalized for student!";
    $message_type = 'success';
}

// Handle POST - Reset All Marks for Student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_student_marks'])) {
    $student_id = $_POST['student_id'];
    
    $stmt = $conn->prepare("DELETE FROM mark WHERE fyp_studid = ?");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $stmt->close();
    
    $stmt = $conn->prepare("DELETE FROM total_mark WHERE fyp_studid = ?");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $stmt->close();
    
    $message = "All marks reset for student!";
    $message_type = 'success';
}

// Handle POST - Export Marks to Excel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_marks'])) {
    $filter_year = $_POST['filter_year'] ?? '';
    
    // This would typically generate an Excel file
    // For now, redirect to report page
    header("Location: Coordinator_report.php?type=marks&year=" . urlencode($filter_year));
    exit;
}

/**
 * Recalculate total mark for a student
 */
function recalculateTotalMark($conn, $student_id) {
    // Get all marks for student with item weights
    $total_weighted = 0;
    $total_weight = 0;
    
    $res = $conn->query("SELECT m.fyp_mark, i.fyp_itemweight 
                         FROM mark m 
                         LEFT JOIN item i ON m.fyp_itemid = i.fyp_itemid 
                         WHERE m.fyp_studid = '$student_id'");
    
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $weight = $row['fyp_itemweight'] ?? 1;
            $total_weighted += $row['fyp_mark'] * $weight;
            $total_weight += $weight;
        }
    }
    
    $final_mark = $total_weight > 0 ? $total_weighted / $total_weight : 0;
    
    // Determine grade
    $grade = 'F';
    $res = $conn->query("SELECT fyp_grade FROM assessment_criteria WHERE $final_mark >= fyp_min AND $final_mark <= fyp_max LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) {
        $grade = $row['fyp_grade'];
    }
    
    // Update or insert total_mark
    $stmt = $conn->prepare("SELECT fyp_totalmarkid FROM total_mark WHERE fyp_studid = ?");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($existing) {
        $stmt = $conn->prepare("UPDATE total_mark SET fyp_totalmark = ?, fyp_grade = ?, fyp_dateupdated = NOW() WHERE fyp_totalmarkid = ?");
        $stmt->bind_param("dsi", $final_mark, $grade, $existing['fyp_totalmarkid']);
    } else {
        $stmt = $conn->prepare("INSERT INTO total_mark (fyp_studid, fyp_totalmark, fyp_grade, fyp_datecreated) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("sds", $student_id, $final_mark, $grade);
    }
    $stmt->execute();
    $stmt->close();
    
    return ['mark' => $final_mark, 'grade' => $grade];
}
?>