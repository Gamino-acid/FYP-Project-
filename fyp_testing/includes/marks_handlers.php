<?php
/**
 * FILE 7: includes/marks_handlers.php
 * Handles: Save Student Mark
 */

// Save Student Mark
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_mark'])) {
    $studid = intval($_POST['studid']);
    $total_mark = floatval($_POST['total_mark']);
    
    // Determine grade based on marks
    $grade = 'F';
    if ($total_mark >= 90) $grade = 'A+';
    elseif ($total_mark >= 80) $grade = 'A';
    elseif ($total_mark >= 75) $grade = 'A-';
    elseif ($total_mark >= 70) $grade = 'B+';
    elseif ($total_mark >= 65) $grade = 'B';
    elseif ($total_mark >= 60) $grade = 'B-';
    elseif ($total_mark >= 55) $grade = 'C+';
    elseif ($total_mark >= 50) $grade = 'C';
    elseif ($total_mark >= 45) $grade = 'C-';
    elseif ($total_mark >= 40) $grade = 'D';
    
    // Check if mark exists
    $check = $conn->prepare("SELECT fyp_markid FROM total_mark WHERE fyp_studid = ?");
    $check->bind_param("i", $studid);
    $check->execute();
    $exists = $check->get_result()->num_rows > 0;
    $check->close();
    
    if ($exists) {
        $stmt = $conn->prepare("UPDATE total_mark SET fyp_totalmark = ?, fyp_grade = ? WHERE fyp_studid = ?");
        $stmt->bind_param("dsi", $total_mark, $grade, $studid);
    } else {
        $stmt = $conn->prepare("INSERT INTO total_mark (fyp_studid, fyp_totalmark, fyp_grade) VALUES (?, ?, ?)");
        $stmt->bind_param("ids", $studid, $total_mark, $grade);
    }
    
    if ($stmt->execute()) {
        $message = "Mark saved successfully!";
        $message_type = 'success';
    } else {
        $message = "Error saving mark: " . $stmt->error;
        $message_type = 'error';
    }
    $stmt->close();
}
?>