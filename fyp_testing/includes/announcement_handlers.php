<?php
/**
 * FILE 6: includes/announcement_handlers.php
 * Handles: Create Announcement, Delete Announcement
 */

// Create Announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_announcement'])) {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $receiver = $_POST['receiver'];
    
    $stmt = $conn->prepare("INSERT INTO announcement (fyp_title, fyp_content, fyp_receiver, fyp_datecreated) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("sss", $title, $content, $receiver);
    
    if ($stmt->execute()) {
        $message = "Announcement created successfully!";
        $message_type = 'success';
    } else {
        $message = "Error creating announcement: " . $stmt->error;
        $message_type = 'error';
    }
    $stmt->close();
}

// Delete Announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_announcement'])) {
    $ann_id = intval($_POST['announcement_id']);
    
    $stmt = $conn->prepare("DELETE FROM announcement WHERE fyp_announcementid = ?");
    $stmt->bind_param("i", $ann_id);
    
    if ($stmt->execute()) {
        $message = "Announcement deleted successfully!";
        $message_type = 'success';
    } else {
        $message = "Error deleting announcement: " . $stmt->error;
        $message_type = 'error';
    }
    $stmt->close();
}
?>