<?php
// ====================================================
// student_assignment_details.php - Assignment Details & Submission
// ====================================================
include("connect.php");

// 1. Validate user login
$auth_user_id = $_GET['auth_user_id'] ?? null;
$assign_id = $_GET['id'] ?? null;

if (!$auth_user_id || !$assign_id) { header("location: login.php"); exit; }

// 2. Get current student info
$stud_data = [];
$current_stud_id = '';
$user_name = 'Student';
$user_avatar = 'image/user.png';

if (isset($conn)) {
    $sql_user = "SELECT fyp_username FROM `USER` WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_user)) { 
        $stmt->bind_param("i", $auth_user_id); $stmt->execute(); 
        $res = $stmt->get_result(); if ($row = $res->fetch_assoc()) $user_name = $row['fyp_username']; 
        $stmt->close(); 
    }
    $sql_stud = "SELECT * FROM STUDENT WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_stud)) { 
        $stmt->bind_param("i", $auth_user_id); $stmt->execute(); 
        $res = $stmt->get_result(); 
        if ($row = $res->fetch_assoc()) { 
            $stud_data = $row; 
            $current_stud_id = $row['fyp_studid'];
            if (!empty($row['fyp_studname'])) $user_name = $row['fyp_studname'];
            if (!empty($row['fyp_profileimg'])) $user_avatar = $row['fyp_profileimg'];
        } 
        $stmt->close(); 
    }
}

// 3. Get assignment details
$assignment = null;
$sql_ass = "SELECT * FROM assignment WHERE fyp_assignmentid = ?";
if ($stmt = $conn->prepare($sql_ass)) {
    $stmt->bind_param("i", $assign_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $assignment = $res->fetch_assoc();
    $stmt->close();
}

if (!$assignment) { die("Assignment not found."); }

// 4. Get/Initialize submission record
$submission = null;
$sql_sub = "SELECT * FROM assignment_submission WHERE fyp_assignmentid = ? AND fyp_studid = ?";
if ($stmt = $conn->prepare($sql_sub)) {
    $stmt->bind_param("is", $assign_id, $current_stud_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $submission = $res->fetch_assoc();
    $stmt->close();
}

// === Core Logic A: Auto-mark as "Viewed" ===
if (!$submission) {
    // If no record exists, create one with 'Viewed' status
    $stmt = $conn->prepare("INSERT INTO assignment_submission (fyp_assignmentid, fyp_studid, fyp_submission_status) VALUES (?, ?, 'Viewed')");
    $stmt->bind_param("is", $assign_id, $current_stud_id);
    $stmt->execute();
    $stmt->close();
    
    // Refresh data
    $submission = ['fyp_submission_status' => 'Viewed', 'fyp_marks' => null, 'fyp_feedback' => null, 'fyp_submitted_file' => null, 'fyp_submission_date' => null, 'fyp_submissionid' => $conn->insert_id];
} elseif ($submission['fyp_submission_status'] == 'Not Turned In') {
    // If status is 'Not Turned In', update to 'Viewed'
    $stmt = $conn->prepare("UPDATE assignment_submission SET fyp_submission_status = 'Viewed' WHERE fyp_submissionid = ?");
    $stmt->bind_param("i", $submission['fyp_submissionid']);
    $stmt->execute();
    $stmt->close();
    
    $submission['fyp_submission_status'] = 'Viewed';
}

// === Core Logic B: Handle file upload (Submit/Resubmit) ===
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_assignment'])) {
    if (isset($_FILES['file_upload']) && $_FILES['file_upload']['error'] == 0) {
        
        // Security Check 1: File size validation (20MB max)
        $maxFileSize = 20 * 1024 * 1024; // 20MB in bytes
        if ($_FILES['file_upload']['size'] > $maxFileSize) {
            $fileSizeMB = round($_FILES['file_upload']['size'] / 1024 / 1024, 2);
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'File Too Large!',
                        text: 'Maximum file size is 20MB. Your file is {$fileSizeMB}MB',
                        confirmButtonColor: '#d93025',
                        draggable: true
                    });
                });
            </script>";
        } else {
            
            // Security Check 2: Validate file type
            $allowedExtensions = ['pdf', 'doc', 'docx', 'zip', 'rar'];
            $allowedMimeTypes = [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/zip',
                'application/x-zip-compressed',
                'application/x-rar-compressed',
                'application/octet-stream'
            ];
            
            $fileExtension = strtolower(pathinfo($_FILES['file_upload']['name'], PATHINFO_EXTENSION));
            $fileMimeType = mime_content_type($_FILES['file_upload']['tmp_name']);
            
            if (!in_array($fileExtension, $allowedExtensions)) {
                echo "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Invalid File Type!',
                            text: 'Only PDF, DOC, DOCX, ZIP, and RAR files are allowed.',
                            footer: '<small>File extension detected: <strong>" . strtoupper($fileExtension) . "</strong></small>',
                            confirmButtonColor: '#d93025',
                            draggable: true
                        });
                    });
                </script>";
            } elseif (!in_array($fileMimeType, $allowedMimeTypes)) {
                echo "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Security Warning!',
                            text: 'File validation failed. The file may be corrupted or malicious.',
                            footer: '<small>Please ensure your file is a genuine document.</small>',
                            confirmButtonColor: '#d93025',
                            draggable: true
                        });
                    });
                </script>";
            } else {
                
                // Security Check 3: Sanitize filename
                $originalName = basename($_FILES['file_upload']['name']);
                $safeName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $originalName);
                $uniqueName = time() . "_" . uniqid() . "_" . $safeName;
                
                $upload_dir = "uploads/assignments/";
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                
                $target_file = $upload_dir . $uniqueName;
                
                // Security Check 4: Additional file content validation
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $detectedMime = finfo_file($finfo, $_FILES['file_upload']['tmp_name']);
                finfo_close($finfo);
                
                if (!in_array($detectedMime, $allowedMimeTypes)) {
                    echo "<script>
                        document.addEventListener('DOMContentLoaded', function() {
                            Swal.fire({
                                icon: 'error',
                                title: 'Security Alert!',
                                text: 'File content does not match its extension. Upload blocked for security.',
                                footer: '<a href=\"#\" onclick=\"alert(\'Files must be genuine documents. Renamed executables or suspicious files are not allowed.\'); return false;\">Why is this blocked?</a>',
                                confirmButtonColor: '#d93025',
                                draggable: true
                            });
                        });
                    </script>";
                } elseif (move_uploaded_file($_FILES['file_upload']['tmp_name'], $target_file)) {
                    
                    // Set proper file permissions
                    chmod($target_file, 0644);
                    
                    $date_now = date('Y-m-d H:i:s');
                    $new_status = 'Turned In';
                    
                    if ($date_now > $assignment['fyp_deadline']) {
                        $new_status = 'Late Turned In';
                    }
                    
                    if ($submission['fyp_submission_status'] == 'Need Revision') {
                        $new_status = 'Resubmitted';
                    }

                    $sql_upd = "UPDATE assignment_submission 
                                SET fyp_submitted_file = ?, fyp_submission_status = ?, fyp_submission_date = ? 
                                WHERE fyp_assignmentid = ? AND fyp_studid = ?";
                    if ($stmt = $conn->prepare($sql_upd)) {
                        $stmt->bind_param("sssis", $target_file, $new_status, $date_now, $assign_id, $current_stud_id);
                        $stmt->execute();
                        $stmt->close();
                        
                        echo "<script>
                            document.addEventListener('DOMContentLoaded', function() {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Success!',
                                    text: 'Assignment submitted successfully!',
                                    confirmButtonColor: '#28a745',
                                    draggable: true
                                }).then(() => {
                                    window.location.href='student_assignment_details.php?id=$assign_id&auth_user_id=" . urlencode($auth_user_id) . "';
                                });
                            });
                        </script>";
                    }
                } else {
                    echo "<script>
                        document.addEventListener('DOMContentLoaded', function() {
                            Swal.fire({
                                icon: 'error',
                                title: 'Upload Failed!',
                                text: 'Unable to upload file. Please check server permissions.',
                                footer: '<a href=\"#\" onclick=\"alert(\'Server may have insufficient permissions. Contact administrator.\'); return false;\">Why did this fail?</a>',
                                confirmButtonColor: '#d93025',
                                draggable: true
                            });
                        });
                    </script>";
                }
            }
        }
    } else {
        $errorMessage = 'Please select a file to upload.';
        if (isset($_FILES['file_upload']['error'])) {
            switch ($_FILES['file_upload']['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $errorMessage = 'File exceeds maximum size limit (20MB).';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $errorMessage = 'File was only partially uploaded. Please try again.';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $errorMessage = 'No file was selected for upload.';
                    break;
                default:
                    $errorMessage = 'An error occurred during upload. Please try again.';
            }
        }
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'warning',
                    title: 'No File Selected',
                    text: '$errorMessage',
                    confirmButtonColor: '#ffc107',
                    draggable: true
                });
            });
        </script>";
    }
}

// === Core Logic C: Handle undo ===
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['undo_submission'])) {
    $current_status = $submission['fyp_submission_status'];
    
    if ($current_status != 'Graded') {
        $sql_undo = "UPDATE assignment_submission 
                     SET fyp_submitted_file = NULL, fyp_submission_status = 'Viewed', fyp_submission_date = NULL 
                     WHERE fyp_assignmentid = ? AND fyp_studid = ?";
        if ($stmt = $conn->prepare($sql_undo)) {
            $stmt->bind_param("is", $assign_id, $current_stud_id);
            $stmt->execute();
            $stmt->close();
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        title: 'Undone!',
                        text: 'Your submission has been withdrawn.',
                        icon: 'success',
                        confirmButtonColor: '#3085d6'
                    }).then(() => {
                        window.location.href='student_assignment_details.php?id=$assign_id&auth_user_id=" . urlencode($auth_user_id) . "';
                    });
                });
            </script>";
        }
    } else {
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Cannot Undo',
                    text: 'Assignment has already been graded and cannot be withdrawn.',
                    footer: '<small>Graded assignments are finalized and cannot be changed.</small>',
                    confirmButtonColor: '#d93025',
                    draggable: true
                });
            });
        </script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignment Details</title>
    <link rel="icon" type="image/png" href="image/ladybug.png?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap');
        
        :root {
            --primary-color: #0056b3;
            --primary-hover: #004494;
            --secondary-color: #f8f9fa;
            --text-color: #333;
            --sidebar-bg: #004085; 
            --sidebar-hover: #003366;
            --sidebar-text: #e0e0e0;
            --card-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            background-color: #f4f6f9;
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
        }

        /* Sidebar */
        .main-menu {
            background: var(--sidebar-bg);
            border-right: 1px solid rgba(255,255,255,0.1);
            position: fixed;
            top: 0;
            bottom: 0;
            height: 100%;
            left: 0;
            width: 60px;
            overflow: hidden;
            transition: width .05s linear;
            z-index: 1000;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }

        .main-menu:hover, nav.main-menu.expanded {
            width: 250px;
            overflow: visible;
        }

        .main-menu > ul {
            margin: 7px 0;
            padding: 0;
            list-style: none;
        }

        .main-menu li {
            position: relative;
            display: block;
            width: 250px;
        }

        .main-menu li > a {
            position: relative;
            display: table;
            border-collapse: collapse;
            border-spacing: 0;
            color: var(--sidebar-text);
            font-size: 14px;
            text-decoration: none;
            transition: all .1s linear;
            width: 100%;
        }

        .main-menu .nav-icon {
            position: relative;
            display: table-cell;
            width: 60px;
            height: 46px; 
            text-align: center;
            vertical-align: middle;
            font-size: 18px;
        }

        .main-menu .nav-text {
            position: relative;
            display: table-cell;
            vertical-align: middle;
            width: 190px;
            padding-left: 10px;
            white-space: nowrap;
        }

        .main-menu li:hover > a, nav.main-menu li.active > a {
            color: #fff;
            background-color: var(--sidebar-hover);
            border-left: 4px solid #fff; 
        }

        .main-menu > ul.logout {
            position: absolute;
            left: 0;
            bottom: 0;
            width: 100%;
        }
        
        .main-content-wrapper {
            margin-left: 60px;
            flex: 1;
            padding: 20px;
            width: calc(100% - 60px);
            transition: margin-left .05s linear;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
        }
        
        .welcome-text h1 {
            margin: 0;
            font-size: 24px;
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .welcome-text p {
            margin: 5px 0 0;
            color: #666;
            font-size: 14px;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .logo-img {
            height: 40px;
            width: auto;
            background: white;
            padding: 2px;
            border-radius: 6px;
        }
        
        .system-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--primary-color);
            letter-spacing: 0.5px;
        }

        /* Back Button */
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: white;
            color: var(--primary-color);
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            box-shadow: var(--card-shadow);
            transition: all 0.2s;
            margin-bottom: 20px;
            border: 2px solid var(--primary-color);
        }

        .back-btn:hover {
            background: var(--primary-color);
            color: white;
            transform: translateX(-3px);
        }

        /* Assignment Header Card */
        .header-card {
            background: linear-gradient(135deg, #0056b3 0%, #004494 100%);
            padding: 30px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            color: white;
            margin-bottom: 25px;
        }

        .ass-title {
            font-size: 28px;
            margin: 0 0 15px 0;
            font-weight: 600;
        }

        .ass-meta {
            display: flex;
            gap: 20px;
            font-size: 14px;
            flex-wrap: wrap;
        }

        .ass-badge {
            padding: 6px 14px;
            background: rgba(255,255,255,0.2);
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            backdrop-filter: blur(10px);
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 20px;
        }

        .desc-card, .submission-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
        }

        .section-h {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
            padding-bottom: 12px;
            border-bottom: 2px solid #f0f0f0;
        }

        .desc-text {
            color: #555;
            line-height: 1.8;
            white-space: pre-wrap;
            font-size: 15px;
        }

        /* Status Colors */
        .status-Viewed { border-top: 4px solid #ffc107; }
        .status-TurnedIn { border-top: 4px solid #28a745; }
        .status-LateTurnedIn { border-top: 4px solid #ff9800; }
        .status-Resubmitted { border-top: 4px solid #28a745; }
        .status-Graded { border-top: 4px solid #17a2b8; }
        .status-NeedRevision { border-top: 4px solid #fd7e14; }

        .status-label {
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 20px;
            display: block;
            color: #666;
        }

        .status-tag {
            padding: 8px 16px;
            border-radius: 20px;
            color: white;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .tag-Viewed { background: #ffc107; color: #333; }
        .tag-TurnedIn { background: #28a745; }
        .tag-LateTurnedIn { background: #ff9800; color: white; }
        .tag-Resubmitted { background: #28a745; }
        .tag-Graded { background: #17a2b8; }
        .tag-NeedRevision { background: #fd7e14; }

        /* Custom File Upload */
        .upload-area {
            border: 3px dashed #d0d0d0;
            padding: 40px 20px;
            text-align: center;
            border-radius: 12px;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            margin-bottom: 20px;
            transition: all 0.3s;
            cursor: pointer;
            position: relative;
        }

        .upload-area:hover {
            border-color: var(--primary-color);
            background: linear-gradient(135deg, #e3effd 0%, #f8f9fa 100%);
            transform: translateY(-2px);
        }

        .upload-area i {
            font-size: 48px;
            color: var(--primary-color);
            margin-bottom: 15px;
            display: block;
        }

        .file-input-wrapper {
            position: relative;
            margin-bottom: 15px;
        }

        .file-input {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .file-input-label {
            display: inline-block;
            padding: 12px 30px;
            background: var(--primary-color);
            color: white;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            font-size: 14px;
        }

        .file-input-label:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 86, 179, 0.3);
        }

        .file-name-display {
            margin-top: 15px;
            font-size: 13px;
            color: #666;
            font-weight: 500;
        }

        .btn-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.3);
        }

        .btn-undo {
            width: 100%;
            padding: 12px;
            background: white;
            color: #dc3545;
            border: 2px solid #dc3545;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-undo:hover {
            background: #dc3545;
            color: white;
            transform: translateY(-2px);
        }

        .submitted-file {
            background: linear-gradient(135deg, #e3effd 0%, #d0e4ff 100%);
            padding: 15px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            color: var(--primary-color);
            text-decoration: none;
            margin-bottom: 15px;
            border: 2px solid #b8d4ff;
            transition: all 0.3s;
        }

        .submitted-file:hover {
            background: linear-gradient(135deg, #d0e4ff 0%, #b8d4ff 100%);
            transform: translateX(5px);
        }

        .submitted-file i {
            font-size: 24px;
        }

        .feedback-box {
            background: linear-gradient(135deg, #fff3cd 0%, #ffe69c 100%);
            border: 2px solid #ffc107;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #856404;
            line-height: 1.6;
        }

        .feedback-box strong {
            display: block;
            margin-bottom: 10px;
            font-size: 15px;
        }

        .marks-box {
            font-size: 48px;
            font-weight: 700;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 15px;
            text-align: center;
        }

        @media (max-width: 900px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .page-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
        }
    </style>
</head>
<body>

    <!-- Sidebar Navigation -->
    <nav class="main-menu">
        <ul>
            <li>
                <a href="Student_mainpage.php?page=dashboard&auth_user_id=<?php echo $auth_user_id; ?>">
                    <i class="fa fa-home nav-icon"></i>
                    <span class="nav-text">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="std_profile.php?auth_user_id=<?php echo $auth_user_id; ?>">
                    <i class="fa fa-user nav-icon"></i>
                    <span class="nav-text">My Profile</span>
                </a>
            </li>
            <li>
                <a href="std_projectreg.php?auth_user_id=<?php echo $auth_user_id; ?>">
                    <i class="fa fa-users nav-icon"></i>
                    <span class="nav-text">Project Registration</span>
                </a>
            </li>
            <li>
                <a href="std_request_status.php?auth_user_id=<?php echo $auth_user_id; ?>">
                    <i class="fa fa-tasks nav-icon"></i>
                    <span class="nav-text">Request Status</span>
                </a>
            </li>
            <li class="active">
                <a href="student_assignment.php?auth_user_id=<?php echo $auth_user_id; ?>">
                    <i class="fa fa-file-text nav-icon"></i>
                    <span class="nav-text">Assignments</span>
                </a>
            </li>
            <li>
                <a href="Student_mainpage.php?page=doc_submission&auth_user_id=<?php echo $auth_user_id; ?>">
                    <i class="fa fa-cloud-upload nav-icon"></i>
                    <span class="nav-text">Document Upload</span>
                </a>
            </li>
            <li>
                <a href="student_appointment_meeting.php?auth_user_id=<?php echo $auth_user_id; ?>">
                    <i class="fa fa-calendar nav-icon"></i>
                    <span class="nav-text">Book Appointment</span>
                </a>
            </li>
            <li>
                <a href="Student_mainpage.php?page=presentation&auth_user_id=<?php echo $auth_user_id; ?>">
                    <i class="fa fa-desktop nav-icon"></i>
                    <span class="nav-text">Presentation</span>
                </a>
            </li>
            <li>
                <a href="Student_mainpage.php?page=grades&auth_user_id=<?php echo $auth_user_id; ?>">
                    <i class="fa fa-star nav-icon"></i>
                    <span class="nav-text">My Grades</span>
                </a>
            </li>
        </ul>

        <ul class="logout">
            <li>
                <a href="login.php">
                    <i class="fa fa-power-off nav-icon"></i>
                    <span class="nav-text">Logout</span>
                </a>
            </li>  
        </ul>
    </nav>

    <!-- Main Content -->
    <div class="main-content-wrapper">
        
        <!-- Page Header -->
        <div class="page-header">
            <div class="welcome-text">
                <h1>Assignment Details</h1>
                <p>Welcome back, <?php echo htmlspecialchars($user_name); ?></p>
            </div>
            
            <div class="logo-section">
                <img src="image/ladybug.png?v=<?php echo time(); ?>" alt="Logo" class="logo-img">
                <span class="system-title">FYP Portal</span>
            </div>

            <div style="display:flex; align-items:center; gap:10px;">
                <span style="font-size:13px; color:#666; background:#f0f0f0; padding:5px 10px; border-radius:20px;">Student</span>
                <?php if(!empty($user_avatar) && $user_avatar != 'image/user.png'): ?>
                    <img src="<?php echo htmlspecialchars($user_avatar); ?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
                <?php else: ?>
                    <div style="width:40px;height:40px;border-radius:50%;background:#0056b3;color:white;display:flex;align-items:center;justify-content:center;font-weight:bold;">
                        <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Back Button -->
        <a href="student_assignment.php?auth_user_id=<?php echo $auth_user_id; ?>" class="back-btn">
            <i class="fa fa-arrow-left"></i> Back to Assignments
        </a>

        <!-- Assignment Header -->
        <div class="header-card">
            <h1 class="ass-title"><?php echo htmlspecialchars($assignment['fyp_title']); ?></h1>
            <div class="ass-meta">
                <span class="ass-badge"><?php echo htmlspecialchars($assignment['fyp_assignment_type']); ?></span>
                <div class="meta-item">
                    <i class="fa fa-calendar-alt"></i>
                    <span>Due: <?php echo date('M d, Y h:i A', strtotime($assignment['fyp_deadline'])); ?></span>
                </div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Left: Description -->
            <div class="desc-card">
                <div class="section-h"><i class="fa fa-info-circle"></i> Instructions</div>
                <div class="desc-text"><?php echo nl2br(htmlspecialchars($assignment['fyp_description'])); ?></div>
            </div>

            <!-- Right: Submission Box -->
            <?php 
                $status = $submission['fyp_submission_status'];
                $statusClass = 'status-' . str_replace(' ', '', $status);
                $tagClass = 'tag-' . str_replace(' ', '', $status);
                
                $canSubmit = ($status == 'Viewed' || $status == 'Not Turned In' || $status == 'Need Revision');
                $isSubmitted = ($status == 'Turned In' || $status == 'Late Turned In' || $status == 'Resubmitted' || $status == 'Graded');
                $isGraded = ($status == 'Graded');
            ?>
            <div class="submission-card <?php echo $statusClass; ?>">
                <div class="section-h"><i class="fa fa-upload"></i> Your Work</div>
                
                <span class="status-label">
                    Status: <span class="status-tag <?php echo $tagClass; ?>">
                        <i class="fa fa-circle"></i>
                        <?php echo $status; ?>
                    </span>
                </span>

                <!-- Feedback Display -->
                <?php if (!empty($submission['fyp_feedback'])): ?>
                    <div class="feedback-box">
                        <strong><i class="fa fa-comment-dots"></i> Teacher's Feedback:</strong>
                        <?php echo nl2br(htmlspecialchars($submission['fyp_feedback'])); ?>
                    </div>
                <?php endif; ?>

                <!-- Marks Display -->
                <?php if ($isGraded && $submission['fyp_marks'] !== null): ?>
                    <div class="marks-box">
                        <?php echo $submission['fyp_marks']; ?> / 100
                    </div>
                    <div style="text-align:center; color:#666; font-size:14px; margin-bottom:20px;">Your Grade</div>
                <?php endif; ?>

                <!-- View Submitted File -->
                <?php if ($isSubmitted && !empty($submission['fyp_submitted_file'])): ?>
                    <a href="<?php echo $submission['fyp_submitted_file']; ?>" target="_blank" class="submitted-file">
                        <i class="fa fa-file-pdf"></i>
                        <div style="flex:1;">
                            <div style="font-weight:600;"><?php echo basename($submission['fyp_submitted_file']); ?></div>
                            <div style="font-size:11px; opacity:0.8;">Submitted on: <?php echo date('M d, Y h:i A', strtotime($submission['fyp_submission_date'])); ?></div>
                        </div>
                        <i class="fa fa-external-link-alt"></i>
                    </a>
                <?php endif; ?>

                <!-- Submission Form -->
                <?php if ($canSubmit): ?>
                    <form method="POST" enctype="multipart/form-data" id="uploadForm">
                        <div class="upload-area" onclick="document.getElementById('fileInput').click()">
                            <i class="fa fa-cloud-upload-alt"></i>
                            <div class="file-input-wrapper">
                                <input type="file" name="file_upload" id="fileInput" class="file-input" required onchange="displayFileName()">
                                <label for="fileInput" class="file-input-label">
                                    <i class="fa fa-folder-open"></i> Choose File
                                </label>
                            </div>
                            <div id="fileNameDisplay" class="file-name-display">No file selected</div>
                            <div style="font-size:12px; color:#999; margin-top:10px;">
                                <i class="fa fa-shield-alt"></i> Supported: PDF, DOC, DOCX, ZIP, RAR • Max: 20MB
                            </div>
                        </div>
                        <button type="submit" name="submit_assignment" class="btn-submit">
                            <i class="fa fa-paper-plane"></i>
                            <?php echo ($status == 'Need Revision') ? 'Resubmit Assignment' : 'Turn In Assignment'; ?>
                        </button>
                    </form>
                <?php endif; ?>

                <!-- Undo Button -->
                <?php if ($isSubmitted && !$isGraded): ?>
                    <form method="POST" onsubmit="return confirm('Are you sure you want to unsubmit this assignment?');">
                        <button type="submit" name="undo_submission" class="btn-undo">
                            <i class="fa fa-undo"></i> Undo Submission
                        </button>
                    </form>
                <?php endif; ?>
                
            </div>
        </div>

    </div>

    <script>
        // Confirm undo submission with SweetAlert2 - NO BROWSER DIALOG!
        function confirmUndo(event) {
            if (event) {
                event.preventDefault();
                event.stopPropagation();
                event.stopImmediatePropagation();
            }
            
            Swal.fire({
                title: "Are you sure?",
                text: "You won't be able to revert this!",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                confirmButtonText: "Yes, delete it!"
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: "Deleted!",
                        text: "Your file has been deleted.",
                        icon: "success"
                    }).then(() => {
                        // Submit the form after showing success
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '';
                        form.innerHTML = '<input type="hidden" name="undo_submission" value="1">';
                        document.body.appendChild(form);
                        form.submit();
                    });
                }
            });
            
            return false;
        }
        
        // Override any window.confirm calls
        window.confirm = function(msg) {
            return true;
        };
        
        function displayFileName() {
            const input = document.getElementById('fileInput');
            const display = document.getElementById('fileNameDisplay');
            
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const fileName = file.name;
                const fileSize = (file.size / 1024 / 1024).toFixed(2); // Convert to MB
                const maxSize = 20; // 20MB
                
                if (file.size > maxSize * 1024 * 1024) {
                    display.innerHTML = `<i class="fa fa-exclamation-triangle" style="color:#dc3545;"></i> File too large! (${fileSize} MB > ${maxSize} MB)`;
                    display.style.color = '#dc3545';
                    display.style.fontWeight = '600';
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'File Too Large!',
                        text: `Your file is ${fileSize}MB. Maximum allowed size is ${maxSize}MB.`,
                        confirmButtonColor: '#d93025',
                        draggable: true
                    });
                    
                    input.value = ''; // Clear the input
                } else {
                    display.innerHTML = `<i class="fa fa-check-circle" style="color:#28a745;"></i> ${fileName} (${fileSize} MB)`;
                    display.style.color = '#28a745';
                    display.style.fontWeight = '600';
                }
            } else {
                display.innerHTML = '<i class="fa fa-info-circle"></i> No file selected';
                display.style.color = '#666';
                display.style.fontWeight = 'normal';
            }
        }

        // Prevent form submission if no file selected or file too large
        document.getElementById('uploadForm')?.addEventListener('submit', function(e) {
            const fileInput = document.getElementById('fileInput');
            
            if (!fileInput.files || !fileInput.files[0]) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'No File Selected',
                    text: 'Please select a file to upload!',
                    confirmButtonColor: '#ffc107',
                    draggable: true
                });
                return false;
            }
            
            const fileSize = fileInput.files[0].size / 1024 / 1024; // MB
            if (fileSize > 20) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'File Too Large!',
                    text: `Your file is ${fileSize.toFixed(2)}MB. Maximum allowed size is 20MB.`,
                    confirmButtonColor: '#d93025',
                    draggable: true
                });
                return false;
            }
            
            // Show loading
            Swal.fire({
                title: 'Uploading...',
                text: 'Please wait while we upload your file',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
        });
    </script>
</body>
</html>