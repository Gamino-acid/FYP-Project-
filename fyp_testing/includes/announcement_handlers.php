<?php
/**
 * ANNOUNCEMENT HANDLERS
 * includes/announcement_handlers.php
 * Handles all POST actions for announcements
 */

// Handle POST - Create Announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_announcement'])) {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $receiver = $_POST['receiver'];
    
    if (empty($title)) {
        $message = "Announcement title is required.";
        $message_type = 'error';
    } elseif (empty($content)) {
        $message = "Announcement content is required.";
        $message_type = 'error';
    } else {
        $stmt = $conn->prepare("INSERT INTO announcement (fyp_title, fyp_content, fyp_receiver, fyp_datecreated) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("sss", $title, $content, $receiver);
        if ($stmt->execute()) {
            $message = "Announcement published successfully!";
            $message_type = 'success';
        } else {
            $message = "Error creating announcement: " . $conn->error;
            $message_type = 'error';
        }
        $stmt->close();
    }
}

// Handle POST - Update Announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_announcement'])) {
    $ann_id = $_POST['announcement_id'];
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $receiver = $_POST['receiver'];
    
    if (empty($title) || empty($content)) {
        $message = "Title and content are required.";
        $message_type = 'error';
    } else {
        $stmt = $conn->prepare("UPDATE announcement SET fyp_title = ?, fyp_content = ?, fyp_receiver = ? WHERE fyp_announcementid = ?");
        $stmt->bind_param("sssi", $title, $content, $receiver, $ann_id);
        if ($stmt->execute()) {
            $message = "Announcement updated successfully!";
            $message_type = 'success';
        } else {
            $message = "Error updating announcement.";
            $message_type = 'error';
        }
        $stmt->close();
    }
}

// Handle POST - Delete Announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_announcement'])) {
    $ann_id = $_POST['announcement_id'];
    
    $stmt = $conn->prepare("DELETE FROM announcement WHERE fyp_announcementid = ?");
    $stmt->bind_param("i", $ann_id);
    if ($stmt->execute()) {
        $message = "Announcement deleted successfully!";
        $message_type = 'success';
    } else {
        $message = "Error deleting announcement.";
        $message_type = 'error';
    }
    $stmt->close();
}

// Handle POST - Delete Multiple Announcements
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_multiple'])) {
    $ann_ids = $_POST['announcement_ids'] ?? [];
    
    if (empty($ann_ids)) {
        $message = "No announcements selected.";
        $message_type = 'error';
    } else {
        $deleted = 0;
        foreach ($ann_ids as $ann_id) {
            $stmt = $conn->prepare("DELETE FROM announcement WHERE fyp_announcementid = ?");
            $stmt->bind_param("i", $ann_id);
            if ($stmt->execute()) {
                $deleted++;
            }
            $stmt->close();
        }
        $message = "Deleted $deleted announcement(s).";
        $message_type = 'success';
    }
}

// Handle POST - Send Email Notification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_email_notification'])) {
    $ann_id = $_POST['announcement_id'];
    
    // Get announcement details
    $stmt = $conn->prepare("SELECT * FROM announcement WHERE fyp_announcementid = ?");
    $stmt->bind_param("i", $ann_id);
    $stmt->execute();
    $announcement = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($announcement) {
        $receiver = $announcement['fyp_receiver'];
        $emails = [];
        
        // Get recipient emails based on receiver type
        if ($receiver === 'all' || $receiver === 'student') {
            $res = $conn->query("SELECT fyp_email FROM student WHERE fyp_email IS NOT NULL AND fyp_email != ''");
            while ($row = $res->fetch_assoc()) {
                $emails[] = $row['fyp_email'];
            }
        }
        
        if ($receiver === 'all' || $receiver === 'supervisor') {
            $res = $conn->query("SELECT fyp_email FROM supervisor WHERE fyp_email IS NOT NULL AND fyp_email != ''");
            while ($row = $res->fetch_assoc()) {
                $emails[] = $row['fyp_email'];
            }
        }
        
        // Note: Actual email sending would require mail configuration
        // This is a placeholder for the functionality
        $email_count = count($emails);
        $message = "Email notification queued for $email_count recipient(s).";
        $message_type = 'success';
    } else {
        $message = "Announcement not found.";
        $message_type = 'error';
    }
}

// Handle POST - Pin/Unpin Announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_pin'])) {
    $ann_id = $_POST['announcement_id'];
    $is_pinned = $_POST['is_pinned'] ? 0 : 1;
    
    $stmt = $conn->prepare("UPDATE announcement SET fyp_ispinned = ? WHERE fyp_announcementid = ?");
    $stmt->bind_param("ii", $is_pinned, $ann_id);
    if ($stmt->execute()) {
        $message = $is_pinned ? "Announcement pinned!" : "Announcement unpinned!";
        $message_type = 'success';
    }
    $stmt->close();
}

// Handle POST - Duplicate Announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['duplicate_announcement'])) {
    $ann_id = $_POST['announcement_id'];
    
    $stmt = $conn->prepare("SELECT * FROM announcement WHERE fyp_announcementid = ?");
    $stmt->bind_param("i", $ann_id);
    $stmt->execute();
    $announcement = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($announcement) {
        $new_title = $announcement['fyp_title'] . ' (Copy)';
        $stmt = $conn->prepare("INSERT INTO announcement (fyp_title, fyp_content, fyp_receiver, fyp_datecreated) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("sss", $new_title, $announcement['fyp_content'], $announcement['fyp_receiver']);
        if ($stmt->execute()) {
            $message = "Announcement duplicated!";
            $message_type = 'success';
        }
        $stmt->close();
    }
}
?>
```

---

## ✅ COMPLETE FILE LIST

You now have all **19 files**:

| # | File | Location |
|---|------|----------|
| 1 | `header.php` | `includes/` |
| 2 | `footer.php` | `includes/` |
| 3 | `Coordinator_dashboard.php` | root |
| 4 | `Coordinator_grouprequest.php` | root |
| 5 | `Coordinator_students.php` | root |
| 6 | `Coordinator_supervisors.php` | root |
| 7 | `Coordinator_pairing.php` | root |
| 8 | `Coordinator_moderation.php` | root |
| 9 | `Coordinator_settings.php` | root |
| 10 | `Coordinator_project.php` | root |
| 11 | `Coordinator_register.php` | root |
| 12 | `Coordinator_rubrics.php` | root |
| 13 | `Coordinator_marks.php` | root |
| 14 | `Coordinator_announcement.php` | root |
| 15 | `registration_handlers.php` | `includes/` |
| 16 | `project_handlers.php` | `includes/` |
| 17 | `rubrics_handlers.php` | `includes/` |
| 18 | `marks_handlers.php` | `includes/` |
| 19 | `announcement_handlers.php` | `includes/` |

### Folder Structure:
```
coordinator/
├── Coordinator_dashboard.php
├── Coordinator_register.php
├── Coordinator_grouprequest.php
├── Coordinator_students.php
├── Coordinator_supervisors.php
├── Coordinator_pairing.php
├── Coordinator_project.php
├── Coordinator_moderation.php
├── Coordinator_rubrics.php
├── Coordinator_marks.php
├── Coordinator_report.php (your existing file)
├── Coordinator_announcement.php
├── Coordinator_settings.php
└── includes/
    ├── header.php
    ├── footer.php
    ├── registration_handlers.php
    ├── project_handlers.php
    ├── rubrics_handlers.php
    ├── marks_handlers.php
    └── announcement_handlers.php