@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap');
:root {
    --primary-color: #0056b3;
    --primary-hover: #004494;
    --secondary-color: #f4f4f9;
    --text-color: #333;
    --border-color: #e0e0e0;
    --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    --gradient-start: #eef2f7;
    --gradient-end: #ffffff;
    --sidebar-width: 260px;
    --student-accent: #007bff;
}

body {
    font-family: 'Poppins', sans-serif;
    margin: 0;
    background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
    color: var(--text-color);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

/* Topbar & Sidebar Styles */
.topbar { display: flex; justify-content: space-between; align-items: center; padding: 15px 40px; background-color: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.05); z-index: 100; position: sticky; top: 0; }
.logo { font-size: 22px; font-weight: 600; color: var(--primary-color); display: flex; align-items: center; gap: 10px; }
.topbar-right { display: flex; align-items: center; gap: 20px; }
.user-name-display { font-weight: 600; font-size: 14px; display: block; }
.user-role-badge { font-size: 11px; background-color: #e3effd; color: var(--primary-color); padding: 2px 8px; border-radius: 12px; font-weight: 500; }
.logout-btn { color: #d93025; text-decoration: none; font-size: 14px; font-weight: 500; padding: 8px 15px; border-radius: 6px; transition: background 0.2s; }
.logout-btn:hover { background-color: #fff0f0; }

.layout-container { display: flex; flex: 1; max-width: 1400px; margin: 0 auto; width: 100%; padding: 20px; box-sizing: border-box; gap: 20px; }

.sidebar { width: var(--sidebar-width); background: #fff; border-radius: 12px; box-shadow: var(--card-shadow); padding: 20px 0; flex-shrink: 0; min-height: calc(100vh - 120px); }
.menu-list { list-style: none; padding: 0; margin: 0; }
.menu-item { margin-bottom: 5px; }
.menu-link { display: flex; align-items: center; padding: 12px 25px; text-decoration: none; color: #555; font-weight: 500; font-size: 15px; transition: all 0.3s; border-left: 4px solid transparent; }
.menu-link:hover { background-color: var(--secondary-color); color: var(--primary-color); }
.menu-link.active { background-color: #e3effd; color: var(--primary-color); border-left-color: var(--primary-color); }
.menu-icon { width: 24px; margin-right: 10px; text-align: center; }
.submenu { list-style: none; padding: 0; margin: 0; background-color: #fafafa; display: none; }
.menu-item.has-active-child .submenu, .menu-item:hover .submenu { display: block; }
.submenu .menu-link { padding-left: 58px; font-size: 14px; padding-top: 10px; padding-bottom: 10px; }

.main-content { flex: 1; display: flex; flex-direction: column; gap: 20px; }
.welcome-card { background: #fff; padding: 30px; border-radius: 12px; box-shadow: var(--card-shadow); border-left: 5px solid var(--primary-color); }
.page-title { font-size: 24px; margin: 0 0 10px 0; color: var(--text-color); }

/* Dashboard & Profile Styles */
.info-cards-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; }
.info-card { background: #fff; padding: 25px; border-radius: 12px; box-shadow: var(--card-shadow); display: flex; flex-direction: column; transition: transform 0.2s; border-left: 4px solid var(--student-accent); }
.info-card:hover { transform: translateY(-3px); }
.info-card h3 { margin: 0 0 15px 0; color: var(--student-accent); font-size: 16px; font-weight: 600; }

.profile-container { display: flex; background: #fff; padding: 30px; border-radius: 12px; box-shadow: var(--card-shadow); gap: 30px; }
.left-column { width: 250px; display: flex; flex-direction: column; align-items: center; border-right: 1px solid #eee; padding-right: 30px; }
.right-column { flex: 1; }
.profile-img-box { width: 160px; height: 160px; border-radius: 50%; background-color: #e0e0e0; overflow: hidden; margin-bottom: 15px; border: 4px solid #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.15); }
.profile-img-box img { width: 100%; height: 100%; object-fit: cover; }
.form-section-title { color: #555; border-bottom: 2px solid var(--primary-color); padding-bottom: 5px; margin-bottom: 20px; font-size: 1.1em; font-weight: 600; }
.form-group { margin-bottom: 15px; }
.form-group label { display: block; margin-bottom: 5px; font-weight: 500; color: #444; font-size: 0.9em; }
.form-group input[type="text"], .form-group input[type="email"], .form-group input[type="number"], .form-group select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; font-size: 0.95em; font-family: inherit; }
input[readonly], select[disabled] { background-color: #e9ecef; cursor: not-allowed; color: #6c757d; border-color: #ced4da; }
.row-group { display: flex; gap: 20px; }
.col-group { flex: 1; }
.save-btn { margin-top: 25px; padding: 12px 25px; background-color: var(--primary-color); color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 1em; transition: background 0.3s; font-family: inherit; font-weight: 500; }
.save-btn:hover { background-color: var(--primary-hover); }
.student-id-display { font-size: 1.2em; font-weight: bold; color: #333; margin-top: 10px; text-align: center; }

/* Announcement Styles */
.announcement-feed { max-height: calc(100vh - 250px); overflow-y: auto; padding-right: 10px; }
.announcement-feed::-webkit-scrollbar { width: 8px; }
.announcement-feed::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
.announcement-feed::-webkit-scrollbar-thumb { background: #ccc; border-radius: 4px; }
.ann-card { background: #fff; border-radius: 8px; padding: 20px; margin-bottom: 15px; box-shadow: 0 2px 6px rgba(0,0,0,0.06); border-left: 5px solid #6264A7; transition: transform 0.2s; position: relative; }
.ann-card:hover { transform: translateX(3px); }
.ann-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #eee; }
.ann-sender-info { display: flex; align-items: center; gap: 10px; }
.ann-avatar { width: 40px; height: 40px; background-color: #6264A7; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 18px; }
.ann-sender-name { font-weight: 600; color: #333; font-size: 15px; }
.ann-role { font-size: 12px; color: #888; background: #f0f0f0; padding: 2px 8px; border-radius: 10px; margin-left: 8px;}
.ann-date { font-size: 12px; color: #999; display: flex; align-items: center; gap: 5px; }
.ann-subject { font-size: 18px; font-weight: 600; color: #2c2c2c; margin-bottom: 8px; }
.ann-body { color: #555; line-height: 1.6; font-size: 14px; white-space: pre-wrap; }

/* Project Registration Cards & Modal */
.project-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 25px; }
.project-card { border: 1px solid #eee; border-radius: 10px; padding: 25px; background: #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.03); display: flex; flex-direction: column; justify-content: space-between; border-top: 4px solid var(--primary-color); transition: transform 0.3s, box-shadow 0.3s; position: relative; }
.project-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.08); }
.project-title { font-size: 18px; font-weight: 600; color: #333; margin-bottom: 10px; line-height: 1.4; }
.project-meta { font-size: 13px; color: #777; margin-bottom: 15px; display: flex; flex-wrap: wrap; gap: 10px; }
.badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
.badge-cat { background: #e3f2fd; color: #1565c0; }
.badge-type { background: #f3e5f5; color: #7b1fa2; }
.badge-status { background: #e8f5e9; color: #2e7d32; }
.project-desc { font-size: 14px; color: #555; margin-bottom: 20px; line-height: 1.6; flex-grow: 1; border-top: 1px solid #f0f0f0; padding-top: 15px; }
.supervisor-info { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #666; margin-bottom: 20px; }
.btn-select-proj { background-color: var(--primary-color); color: white; border: none; padding: 12px; border-radius: 8px; cursor: pointer; width: 100%; font-weight: 600; transition: background-color 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px; }
.btn-select-proj:hover { background-color: var(--primary-hover); }

/* Modal Styles */
.modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; backdrop-filter: blur(2px); justify-content: center; align-items: center; opacity: 0; transition: opacity 0.3s ease; }
.modal-overlay.show { display: flex; opacity: 1; }
.modal-box { background: #fff; padding: 35px; border-radius: 12px; width: 90%; max-width: 450px; text-align: center; box-shadow: 0 15px 35px rgba(0,0,0,0.2); transform: scale(0.9); transition: transform 0.3s ease; }
.modal-overlay.show .modal-box { transform: scale(1); }
.modal-icon { font-size: 40px; color: var(--primary-color); margin-bottom: 15px; }
.modal-title { font-size: 20px; font-weight: 600; margin-bottom: 10px; color: #333; }
.modal-text { color: #666; margin-bottom: 30px; font-size: 15px; line-height: 1.6; }
.modal-actions { display: flex; gap: 15px; justify-content: center; }
.btn-confirm { background: var(--primary-color); color: white; padding: 12px 25px; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; font-size: 15px; }
.btn-cancel { background: #f1f1f1; color: #444; padding: 12px 25px; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; font-size: 15px; transition: background 0.2s; }
.btn-cancel:hover { background: #e0e0e0; }
.proj-name-highlight { color: var(--primary-color); font-weight: 600; }

@media (max-width: 900px) {
    .layout-container { flex-direction: column; }
    .sidebar { width: 100%; min-height: auto; }
    .profile-container { flex-direction: column; }
    .left-column { width: 100%; border-right: none; border-bottom: 1px solid #eee; padding-bottom: 20px; margin-bottom: 20px; padding-right: 0; }
}
<?php
// ----------------------------------------------------
// 1. Database Connection & Auth
// ----------------------------------------------------

// FIXED: Changed from "connect.php" to "db_connect.php"
include("db_connect.php");

// Get User ID from URL
$auth_user_id = $_GET['auth_user_id'] ?? null;
$current_page = $_GET['page'] ?? 'dashboard';

// Security Check
if (!$auth_user_id) {
    header("location: login.php");
    exit;
}

// ----------------------------------------------------
// 2. Logic Handling (POST Requests)
// ----------------------------------------------------

// A. Handle Profile Update
if ($current_page == 'profile' && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    
    // 1. Update Contact Number
    $contact = $_POST['contact'];
    $sql_update = "UPDATE STUDENT SET fyp_contactno = ? WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_update)) {
        $stmt->bind_param("ss", $contact, $auth_user_id);
        $stmt->execute();
        $stmt->close();
    }

    // 2. Handle Image Upload
    if (!empty($_FILES["profile_img"]["name"])) {
        $check = getimagesize($_FILES["profile_img"]["tmp_name"]);
        if($check !== false) {
            $image_content = file_get_contents($_FILES["profile_img"]["tmp_name"]);
            $file_ext = pathinfo($_FILES["profile_img"]["name"], PATHINFO_EXTENSION);
            
            // Convert to Base64
            $base64_image = 'data:image/' . $file_ext . ';base64,' . base64_encode($image_content);
            
            $sql_img = "UPDATE STUDENT SET fyp_profileimg = ? WHERE fyp_userid = ?";
            if ($stmt_img = $conn->prepare($sql_img)) {
                $stmt_img->bind_param("ss", $base64_image, $auth_user_id);
                $stmt_img->execute();
                $stmt_img->close();
            }
        } else {
             echo "<script>alert('File is not an image.');</script>";
        }
    }
    
    echo "<script>alert('Profile updated successfully!'); window.location.href='?page=profile&auth_user_id=" . urlencode($auth_user_id) . "';</script>";
}

// B. Handle Project Selection
if ($current_page == 'group_setup' && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_project_selection'])) {
    $selected_project_id = $_POST['selected_project_id'];
    
    $sql_assign = "UPDATE STUDENT SET fyp_projectid = ? WHERE fyp_userid = ?";
    
    if ($stmt = $conn->prepare($sql_assign)) {
        $stmt->bind_param("is", $selected_project_id, $auth_user_id);
        if ($stmt->execute()) {
             echo "<script>alert('Project selected successfully!'); window.location.href='?page=group_setup&auth_user_id=" . urlencode($auth_user_id) . "';</script>";
        } else {
             echo "<script>alert('Error selecting project. Please try again.');</script>";
        }
        $stmt->close();
    } else {
        echo "<script>alert('System Error: Could not assign project.');</script>";
    }
}

// ----------------------------------------------------
// 3. Data Fetching (GET Requests)
// ----------------------------------------------------

$user_role = 'student';
$user_name = 'Student';

// Fetch Student Info
if (isset($conn)) {
    // Check USER table
    $sql_user = "SELECT fyp_username FROM `USER` WHERE fyp_userid = ? LIMIT 1";
    if ($stmt = $conn->prepare($sql_user)) {
        $stmt->bind_param("s", $auth_user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) { $user_name = $row['fyp_username']; }
        $stmt->close();
    }
    // Check STUDENT table
    $stud_data = []; 
    $sql_stud = "SELECT * FROM STUDENT WHERE fyp_userid = ? LIMIT 1";
    if ($stmt = $conn->prepare($sql_stud)) {
        $stmt->bind_param("s", $auth_user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $stud_data = $res->fetch_assoc();
            if (!empty($stud_data['fyp_studname'])) { $user_name = $stud_data['fyp_studname']; }
        } else {
            $stud_data = [
                'fyp_studid' => 'N/A', 'fyp_studname' => $user_name, 'fyp_studfullid' => '',
                'fyp_academicid' => '', 'fyp_progid' => '', 'fyp_tutgroup' => '',
                'fyp_email' => '', 'fyp_contactno' => '', 'fyp_profileimg' => '',
                'fyp_projectid' => null 
            ];
        }
        $stmt->close();
    }
}

// Fetch Announcements
$announcements = []; 
if ($current_page == 'announcements' && isset($conn)) {
    $sql_ann = "SELECT * FROM announcement ORDER BY fyp_datecreated DESC";
    if ($result = $conn->query($sql_ann)) {
        while ($row = $result->fetch_assoc()) { $announcements[] = $row; }
    }
}

// Fetch Projects
$available_projects = [];
if ($current_page == 'group_setup' && isset($conn)) {
    $sql_proj = "SELECT * FROM PROJECT";
    if ($result = $conn->query($sql_proj)) {
        while ($row = $result->fetch_assoc()) {
            $available_projects[] = $row;
        }
    }
}

// ----------------------------------------------------
// 4. Menu Definition
// ----------------------------------------------------

$menu_items = [
    'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home'],
    'profile' => ['name' => 'My Profile', 'icon' => 'fa-user'],
    'project_mgmt' => [
        'name' => 'Final Year Project',
        'icon' => 'fa-project-diagram',
        'sub_items' => [
            'group_setup' => ['name' => 'Project Registration', 'icon' => 'fa-users'],
            'proposals' => ['name' => 'Proposal Submission', 'icon' => 'fa-file-alt'],
            'doc_submission' => ['name' => 'Document Upload', 'icon' => 'fa-cloud-upload-alt'],
        ]
    ],
    'appointments' => [
        'name' => 'Appointment',
        'icon' => 'fa-calendar-check',
        'sub_items' => [
            'book_session' => ['name' => 'Make Appointment', 'icon' => 'fa-plus-circle'],
        ]
    ],
    'grades' => ['name' => 'My Grades', 'icon' => 'fa-star'],
    'announcements' => ['name' => 'Announcements', 'icon' => 'fa-bullhorn'],
];

$dashboard_data = [
    'project_status' => 'Ongoing - Chapter 2 Review',
    'next_deadline' => 'Chapter 3 Draft: 2026-01-15',
    'supervisor_name' => 'Dr. Alex Smith',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - FYP System</title>
    <link rel="icon" type="image/png" sizes="42x42" href="image/ladybug.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Link to the external CSS file -->
    <link rel="stylesheet" href="css/Student_mainpage.css">
</head>
<body>

    <header class="topbar">
        <div class="logo"><i class="fa fa-graduation-cap"></i> FYP System</div>
        <div class="topbar-right">
            <div class="user-profile-summary">
                <span class="user-name-display"><?php echo htmlspecialchars($user_name); ?></span>
                <span class="user-role-badge">Student</span>
            </div>
            <img src="image/ladybug.png" alt="Logo" style="width: 24px; opacity: 0.8;">
            <a href="login.php" class="logout-btn"><i class="fa fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>

    <div class="layout-container">
        
        <aside class="sidebar">
            <ul class="menu-list">
                <?php foreach ($menu_items as $key => $item): ?>
                    <?php 
                        $isActive = ($key == $current_page);
                        $hasActiveChild = false;
                        if (isset($item['sub_items'])) {
                            foreach ($item['sub_items'] as $sub_key => $sub) {
                                if ($sub_key == $current_page) { $hasActiveChild = true; break; }
                            }
                        }
                    ?>
                    <li class="menu-item <?php echo $hasActiveChild ? 'has-active-child' : ''; ?>">
                        <a href="?page=<?php echo $key; ?>&auth_user_id=<?php echo urlencode($auth_user_id); ?>" class="menu-link <?php echo $isActive ? 'active' : ''; ?>">
                            <span class="menu-icon"><i class="fa <?php echo $item['icon']; ?>"></i></span>
                            <?php echo $item['name']; ?>
                        </a>
                        <?php if (isset($item['sub_items'])): ?>
                            <ul class="submenu">
                                <?php foreach ($item['sub_items'] as $sub_key => $sub_item): ?>
                                    <li><a href="?page=<?php echo $sub_key; ?>&auth_user_id=<?php echo urlencode($auth_user_id); ?>" class="menu-link <?php echo ($sub_key == $current_page) ? 'active' : ''; ?>">
                                        <span class="menu-icon"><i class="fa <?php echo $sub_item['icon']; ?>"></i></span>
                                        <?php echo $sub_item['name']; ?>
                                    </a></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </aside>

        <main class="main-content">
            
            <div class="welcome-card">
                <?php if ($current_page == 'profile'): ?>
                    <h1 class="page-title">My Profile</h1>
                    <p style="color: #666; margin: 0;">View your student details and update your contact information.</p>
                <?php elseif ($current_page == 'announcements'): ?>
                    <h1 class="page-title">Announcements</h1>
                    <p style="color: #666; margin: 0;">Latest updates and news from coordinators and lecturers.</p>
                <?php elseif ($current_page == 'group_setup'): ?>
                    <h1 class="page-title">Project Registration</h1>
                    <p style="color: #666; margin: 0;">Browse available projects and select your topic for the final year project.</p>
                <?php else: ?>
                    <h1 class="page-title">Hello, <?php echo htmlspecialchars($user_name); ?>! 👋</h1>
                    <p style="color: #666; margin: 0;">Welcome to your Student Dashboard.</p>
                <?php endif; ?>
            </div>

            <?php if ($current_page == 'dashboard'): ?>
                <div class="info-cards-grid">
                    <div class="info-card">
                        <h3><i class="fa fa-tasks"></i> Project Status</h3>
                        <p><?php echo htmlspecialchars($dashboard_data['project_status']); ?></p>
                    </div>
                    <div class="info-card" style="border-left-color: #d93025;">
                        <h3 style="color: #d93025;"><i class="fa fa-clock"></i> Next Deadline</h3>
                        <p><?php echo htmlspecialchars($dashboard_data['next_deadline']); ?></p>
                    </div>
                    <div class="info-card" style="border-left-color: #28a745;">
                        <h3 style="color: #28a745;"><i class="fa fa-chalkboard-teacher"></i> Supervisor</h3>
                        <p><?php echo htmlspecialchars($dashboard_data['supervisor_name']); ?></p>
                    </div>
                </div>

            <?php elseif ($current_page == 'profile'): ?>
                <form method="POST" enctype="multipart/form-data">
                    <div class="profile-container">
                        
                        <div class="left-column">
                            <div class="profile-img-box">
                                <?php 
                                $img_data = $stud_data['fyp_profileimg'];
                                if (!empty($img_data)) {
                                    echo "<img src='$img_data' alt='Profile'>";
                                } else {
                                    echo "<div style='width:100%;height:100%;background:#f0f0f0;display:flex;align-items:center;justify-content:center;color:#999;'><i class='fa fa-user' style='font-size:50px;'></i></div>";
                                }
                                ?>
                            </div>
                            
                            <label style="font-size: 0.9em; font-weight: 500; margin-bottom: 5px;">Change Photo:</label>
                            <input type="file" name="profile_img" accept="image/*" style="font-size: 0.8em; max-width: 100%;">
                            
                            <div class="student-id-display"><?php echo htmlspecialchars($stud_data['fyp_studid']); ?></div>
                            <div style="font-size: 0.8em; color: #888; text-align: center;">(Matrix No.)</div>
                        </div>

                        <div class="right-column">
                            <h3 class="form-section-title">Personal & Academic Details</h3>
                            <div class="form-group">
                                <label>Full Name:</label>
                                <input type="text" name="stud_name" value="<?php echo htmlspecialchars($stud_data['fyp_studname']); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label>Student ID :</label>
                                <input type="text" name="stud_fullid" value="<?php echo htmlspecialchars($stud_data['fyp_studfullid']); ?>" readonly>
                            </div>
                            <div class="row-group">
                                <div class="col-group form-group">
                                    <label>Academic Year:</label>
                                    <select name="academic_id" disabled>
                                        <option value="">-- Select --</option>
                                        <?php
                                        if (isset($conn)) {
                                            $result_acd = $conn->query("SELECT * FROM ACADEMIC_YEAR");
                                            if ($result_acd) {
                                                while($row = $result_acd->fetch_assoc()) {
                                                    $selected = ($row['fyp_academicid'] == $stud_data['fyp_academicid']) ? "selected" : "";
                                                    echo "<option value='" . $row['fyp_academicid'] . "' $selected>" . $row['fyp_acdyear'] . "</option>";
                                                }
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-group form-group">
                                    <label>Programme:</label>
                                    <select name="prog_id" disabled>
                                        <option value="">-- Select --</option>
                                        <?php
                                        if (isset($conn)) {
                                            $result_prog = $conn->query("SELECT * FROM PROGRAMME");
                                            if ($result_prog) {
                                                while($row = $result_prog->fetch_assoc()) {
                                                    $selected = ($row['fyp_progid'] == $stud_data['fyp_progid']) ? "selected" : "";
                                                    echo "<option value='" . $row['fyp_progid'] . "' $selected>" . $row['fyp_progname'] . "</option>";
                                                }
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Tutorial Group:</label>
                                <input type="number" name="tut_group" value="<?php echo htmlspecialchars($stud_data['fyp_tutgroup']); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label>Email Address:</label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($stud_data['fyp_email']); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label style="color: var(--primary-color); font-weight: 600;">Contact Number (Editable):</label>
                                <input type="text" name="contact" value="<?php echo htmlspecialchars($stud_data['fyp_contactno']); ?>" required style="border-color: var(--primary-color);">
                            </div>
                            <button type="submit" name="update_profile" class="save-btn"><i class="fa fa-save"></i> Save Changes</button>
                        </div>
                    </div>
                </form>

            <?php elseif ($current_page == 'announcements'): ?>
                <div class="announcement-feed">
                    <?php if (count($announcements) > 0): ?>
                        <?php foreach ($announcements as $ann): ?>
                            <div class="ann-card">
                                <div class="ann-header">
                                    <div class="ann-sender-info">
                                        <div class="ann-avatar"><?php echo strtoupper(substr($ann['fyp_supervisorid'], 0, 1)); ?></div>
                                        <div>
                                            <div class="ann-sender-name"><?php echo htmlspecialchars($ann['fyp_supervisorid']); ?><span class="ann-role">Lecturer</span></div>
                                            <div style="font-size: 11px; color: #888;">To: <?php echo htmlspecialchars($ann['fyp_receiver']); ?></div>
                                        </div>
                                    </div>
                                    <div class="ann-date"><i class="fa fa-clock"></i> <?php $date = date_create($ann['fyp_datecreated']); echo date_format($date, "M d, Y h:i A"); ?></div>
                                </div>
                                <div class="ann-subject"><?php echo htmlspecialchars($ann['fyp_subject']); ?></div>
                                <div class="ann-body"><?php echo nl2br(htmlspecialchars($ann['fyp_description'])); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fa fa-bullhorn" style="font-size: 48px; margin-bottom: 20px; opacity: 0.3;"></i>
                            <p>Currently there are no announcements.</p>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($current_page == 'group_setup'): ?>
                <div class="project-grid">
                    <?php if (count($available_projects) > 0): ?>
                        <?php foreach ($available_projects as $proj): ?>
                            <div class="project-card">
                                <div>
                                    <div class="project-title"><?php echo htmlspecialchars($proj['fyp_projecttitle']); ?></div>
                                    <div class="project-meta">
                                        <span class="badge badge-cat"><?php echo htmlspecialchars($proj['fyp_projectcat']); ?></span>
                                        <span class="badge badge-type"><?php echo htmlspecialchars($proj['fyp_projecttype']); ?></span>
                                        <span class="badge badge-status"><?php echo htmlspecialchars($proj['fyp_projectstatus']); ?></span>
                                    </div>
                                    
                                    <div class="supervisor-info">
                                        <i class="fa fa-user-tie" style="color: var(--primary-color);"></i>
                                        <span>Supervisor: <strong><?php echo htmlspecialchars($proj['fyp_contactpersonname']); ?></strong></span>
                                    </div>
                                    
                                    <div class="project-desc">
                                        <?php echo nl2br(htmlspecialchars($proj['fyp_description'])); ?>
                                        <div style="margin-top: 10px; font-size: 12px; color: #888;">
                                            <strong>Requirement:</strong> <?php echo htmlspecialchars($proj['fyp_requirement']); ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="button" class="btn-select-proj" 
                                    onclick="openModal(
                                        '<?php echo $proj['fyp_projectid']; ?>', 
                                        '<?php echo addslashes(htmlspecialchars($proj['fyp_projecttitle'])); ?>'
                                    )">
                                    <i class="fa fa-check-circle"></i> Select This Project
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state" style="grid-column: 1 / -1;">
                            <i class="fa fa-folder-open" style="font-size: 48px; margin-bottom: 20px; opacity: 0.3;"></i>
                            <p>No projects available for registration at the moment.</p>
                        </div>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <div style="background: #fff; padding: 30px; border-radius: 12px; box-shadow: var(--card-shadow); text-align: center;">
                    <i class="fa fa-wrench" style="font-size: 32px; color: #ddd; margin-bottom: 15px;"></i>
                    <p><strong><?php echo ucfirst($current_page); ?></strong> module is under construction.</p>
                </div>
            <?php endif; ?>

        </main>
    </div>

    <div id="confirmationModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-icon"><i class="fa fa-question-circle"></i></div>
            <div class="modal-title">Confirm Selection</div>
            <p class="modal-text">
                Are you sure you want to register for the project:<br>
                <span id="modalProjectTitle" class="proj-name-highlight"></span>?
            </p>
            
            <form method="POST" class="modal-actions">
                <input type="hidden" name="selected_project_id" id="modalProjectId">
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button type="submit" name="confirm_project_selection" class="btn-confirm">Yes, Confirm</button>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('confirmationModal');
        const modalTitle = document.getElementById('modalProjectTitle');
        const modalInput = document.getElementById('modalProjectId');

        function openModal(id, title) {
            modalTitle.textContent = title;
            modalInput.value = id;
            modal.classList.add('show');
        }

        function closeModal() {
            modal.classList.remove('show');
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
