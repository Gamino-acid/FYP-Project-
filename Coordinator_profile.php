<?php
include("connect.php");

$auth_user_id = $_GET['auth_user_id'] ?? null;
$current_page = 'profile'; 

if (!$auth_user_id) { 
    if(isset($_POST['ajax'])) { echo json_encode(['success'=>false, 'message'=>'Unauthorized']); exit; }
    header("location: login.php"); exit; 
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    
    $contact = $_POST['contact'];
    $room = $_POST['room_no'];
    $spec = $_POST['specialization'];
    $area = $_POST['area_interest'];
    
    $response = ['success' => false, 'message' => 'Unknown error'];

    if (preg_match('/[a-zA-Z]/', $contact)) {
        $response['message'] = 'Contact number cannot contain letters.';
        if(isset($_POST['ajax'])) { echo json_encode($response); exit; }
    }

    $sql_update = "UPDATE coordinator SET 
                   fyp_contactno = ?, 
                   fyp_roomno = ?, 
                   fyp_specialization = ?, 
                   fyp_areaofinterest = ? 
                   WHERE fyp_userid = ?";
    
    if ($stmt = $conn->prepare($sql_update)) {
        $stmt->bind_param("ssssi", $contact, $room, $spec, $area, $auth_user_id);
        if ($stmt->execute()) {
            $response = ['success' => true, 'message' => 'Profile details updated successfully!'];
        } else {
            $response['message'] = 'Database error during update.';
        }
        $stmt->close();
    }

    if (!empty($_FILES["profile_img"]["name"])) {
        $tmp_name = $_FILES["profile_img"]["tmp_name"];
        $check = getimagesize($tmp_name);
        
        if ($check !== false) {
            if ($_FILES["profile_img"]["size"] > 5000000) { 
                 $response['message'] .= ' (Image too large, skipped)';
            } else {
                $image_content = file_get_contents($tmp_name);
                $file_ext = pathinfo($_FILES["profile_img"]["name"], PATHINFO_EXTENSION);
                $base64_image = 'data:image/' . $file_ext . ';base64,' . base64_encode($image_content);
                
                $sql_img = "UPDATE coordinator SET fyp_profileimg = ? WHERE fyp_userid = ?";
                if ($stmt_img = $conn->prepare($sql_img)) {
                    $stmt_img->bind_param("si", $base64_image, $auth_user_id);
                    $stmt_img->execute();
                    $stmt_img->close();
                    $response['image'] = $base64_image;
                }
            }
        }
    }
    
    if(isset($_POST['ajax'])) {
        echo json_encode($response);
        exit;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    $current_pwd = $_POST['current_password'];
    $new_pwd = $_POST['new_password'];
    $confirm_pwd = $_POST['confirm_password'];
    
    $response = ['success' => false, 'message' => ''];

    if ($new_pwd !== $confirm_pwd) {
        $response['message'] = 'New password and confirmation do not match.';
        echo json_encode($response); exit;
    }

    if (strlen($new_pwd) < 8 || strlen($new_pwd) > 16) {
        $response['message'] = 'Password must be between 8 and 16 characters.';
        echo json_encode($response); exit;
    }

    if (!preg_match('/^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,16}$/', $new_pwd)) {
        $response['message'] = 'Password requirement not met (8-16 chars, 1 Upper, 1 Num, 1 Symbol).';
        echo json_encode($response); exit;
    }

    $sql_check = "SELECT fyp_passwordhash FROM `USER` WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_check)) {
        $stmt->bind_param("i", $auth_user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $db_pwd = $row['fyp_passwordhash']; 
            $is_correct = ($current_pwd === $db_pwd) || password_verify($current_pwd, $db_pwd);

            if ($is_correct) {
                $final_password = $new_pwd; 
                
                $sql_upd_pwd = "UPDATE `USER` SET fyp_passwordhash = ? WHERE fyp_userid = ?";
                if ($stmt_up = $conn->prepare($sql_upd_pwd)) {
                    $stmt_up->bind_param("si", $final_password, $auth_user_id);
                    $stmt_up->execute();
                    $response['success'] = true;
                    $response['message'] = 'Password updated successfully!';
                }
            } else {
                $response['message'] = 'Current password is incorrect.';
            }
        }
        $stmt->close();
    }
    
    echo json_encode($response);
    exit;
}

$coor_data = [];
$user_name = 'Coordinator';
$user_avatar = 'image/user.png';

if (isset($conn)) {
    $sql_user = "SELECT fyp_username FROM `USER` WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_user)) { 
        $stmt->bind_param("i", $auth_user_id); 
        $stmt->execute(); 
        $res=$stmt->get_result(); 
        if($row=$res->fetch_assoc()) $user_name=$row['fyp_username']; 
        $stmt->close(); 
    }
    
    $sql_coor = "SELECT * FROM coordinator WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_coor)) { 
        $stmt->bind_param("i", $auth_user_id); 
        $stmt->execute(); 
        $res=$stmt->get_result(); 
        if($res->num_rows > 0) { 
            $coor_data = $res->fetch_assoc(); 
            if(!empty($coor_data['fyp_name'])) $user_name=$coor_data['fyp_name'];
            if(!empty($coor_data['fyp_profileimg'])) $user_avatar=$coor_data['fyp_profileimg'];
        } else {
             $coor_data = [
                 'fyp_coordinatorid'=>'', 'fyp_name'=>$user_name, 'fyp_staffid'=>'', 
                 'fyp_roomno'=>'', 'fyp_email'=>'', 'fyp_contactno'=>'',
                 'fyp_specialization'=>'', 'fyp_areaofinterest'=>'' 
             ];
        }
        $stmt->close(); 
    }
}

$menu_items = [
    'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home', 'link' => 'Coordinator_mainpage.php?page=dashboard'],
    'profile'   => ['name' => 'My Profile', 'icon' => 'fa-user', 'link' => 'Coordinator_profile.php'], 
    
     'management' => [
        'name' => 'User Management',
        'icon' => 'fa-users-cog',
        'sub_items' => [
            'manage_students' => ['name' => 'Student List', 'icon' => 'fa-user-graduate', 'link' => 'Coordinator_manage_users.php?tab=student'],
            'manage_supervisors' => ['name' => 'Supervisor List', 'icon' => 'fa-chalkboard-teacher', 'link' => 'Coordinator_manage_users.php?tab=supervisor'],
            'manage_quota' => ['name' => 'Supervisor Quota', 'icon' => 'fa-chalkboard-teacher', 'link' => 'Coordinator_manage_quota.php'],
        ]
    ],
    
    'project_mgmt' => [
        'name' => 'Project Manage', 
        'icon' => 'fa-tasks', 
        'sub_items' => [
            'propose_project' => ['name' => 'Propose Project', 'icon' => 'fa-plus-circle', 'link' => 'Coordinator_purpose.php'],
            'project_requests' => ['name' => 'Project Requests', 'icon' => 'fa-envelope-open-text', 'link' => 'Coordinator_projectreq.php'],
            'project_list' => ['name' => 'All Projects & Groups', 'icon' => 'fa-list-alt', 'link' => 'Coordinator_manage_project.php'],
        ]
    ],
    'assessment' => [
        'name' => 'Assessment', 
        'icon' => 'fa-clipboard-check', 
        'sub_items' => [
            'propose_assignment' => ['name' => 'Create Assignment', 'icon' => 'fa-plus', 'link' => 'Coordinator_assignment_purpose.php'],
            'grade_assignment' => ['name' => 'Grade Assignments', 'icon' => 'fa-check-square', 'link' => 'Coordinator_assignment_grade.php'], 
        ]
    ],
    'announcements' => [
        'name' => 'Announcements', 
        'icon' => 'fa-bullhorn', 
        'sub_items' => [
            'post_announcement' => ['name' => 'Post New', 'icon' => 'fa-pen', 'link' => 'Coordinator_announcement.php'], 
            'view_announcements' => ['name' => 'View History', 'icon' => 'fa-history', 'link' => 'Coordinator_announcement_view.php'],
        ]
    ],
    'schedule' => ['name' => 'My Schedule', 'icon' => 'fa-calendar-alt', 'link' => 'Coordinator_meeting.php'], 
    'allocation' => ['name' => 'Moderator Allocation', 'icon' => 'fa-people-arrows', 'link' => 'Coordinator_allocation.php'],
    'data_mgmt' => ['name' => 'Data Management', 'icon' => 'fa-database', 'link' => 'Coordinator_data_io.php'],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Coordinator</title>
    <link rel="icon" type="image/png" href="image/ladybug.png?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap');
        
        :root {
            --primary-color: #0056b3;
            --primary-hover: #004494;
            --bg-color: #f4f6f9;
            --card-bg: #ffffff;
            --text-color: #333;
            --text-secondary: #666;
            --sidebar-bg: #004085; 
            --sidebar-hover: #003366;
            --sidebar-text: #e0e0e0;
            --card-shadow: 0 4px 6px rgba(0,0,0,0.05);
            --border-color: #e0e0e0;
            --slot-bg: #f8f9fa;
        }

        .dark-mode {
            --primary-color: #4da3ff;
            --primary-hover: #0069d9;
            --bg-color: #121212;
            --card-bg: #1e1e1e;
            --text-color: #e0e0e0;
            --text-secondary: #a0a0a0;
            --sidebar-bg: #0d1117;
            --sidebar-hover: #161b22;
            --sidebar-text: #c9d1d9;
            --card-shadow: 0 4px 6px rgba(0,0,0,0.3);
            --border-color: #333;
            --slot-bg: #2d2d2d;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            background-color: var(--bg-color);
            color: var(--text-color);
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
            transition: background-color 0.3s, color 0.3s;
        }

        .main-menu {
            background: var(--sidebar-bg);
            border-right: 1px solid rgba(255,255,255,0.1);
            position: fixed;
            top: 0;
            bottom: 0;
            height: 100%;
            left: 0;
            width: 60px;
            overflow-y: auto;
            overflow-x: hidden;
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

        .dropdown-arrow {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            transition: transform 0.3s;
            font-size: 12px;
        }

        .menu-item.open .dropdown-arrow {
            transform: translateY(-50%) rotate(180deg);
        }

        .submenu {
            list-style: none;
            padding: 0;
            margin: 0;
            background-color: rgba(0,0,0,0.2);
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }

        .menu-item.open .submenu {
            max-height: 500px;
            transition: max-height 0.5s ease-in;
        }

        .submenu li > a {
            padding-left: 70px !important;
            font-size: 13px;
            height: 40px;
        }

        .menu-item > a {
            cursor: pointer;
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
            background: var(--card-bg);
            padding: 20px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            transition: background 0.3s;
        }
        
        .welcome-text h1 {
            margin: 0;
            font-size: 24px;
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .welcome-text p {
            margin: 5px 0 0;
            color: var(--text-secondary);
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

        .user-section {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-badge {
            font-size: 13px;
            color: var(--text-secondary);
            background: var(--slot-bg);
            padding: 5px 10px;
            border-radius: 20px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .user-avatar-placeholder {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #0056b3;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .profile-container {
            display: flex;
            background: var(--card-bg);
            padding: 30px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            gap: 30px;
            transition: background 0.3s;
            margin-bottom: 20px;
        }

        .left-column {
            width: 250px;
            display: flex;
            flex-direction: column;
            align-items: center;
            border-right: 1px solid var(--border-color);
            padding-right: 30px;
        }

        .right-column {
            flex: 1;
        }

        .profile-img-box {
            width: 160px;
            height: 160px;
            border-radius: 50%;
            background-color: #e0e0e0;
            overflow: hidden;
            margin-bottom: 15px;
            border: 4px solid var(--card-bg);
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .profile-img-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-img-placeholder {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 50px;
        }

        .student-id-display {
            font-size: 1.2em;
            font-weight: bold;
            color: var(--text-color);
            margin-top: 10px;
            text-align: center;
        }

        .upload-photo-btn {
            width: 100%;
            padding: 10px 20px;
            background: linear-gradient(135deg, var(--primary-color), #0073e6);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9em;
            font-weight: 500;
            font-family: inherit;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 86, 179, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 15px;
        }

        .upload-photo-btn:hover {
            background: linear-gradient(135deg, var(--primary-hover), #005bb5);
            box-shadow: 0 4px 12px rgba(0, 86, 179, 0.3);
            transform: translateY(-2px);
        }

        .upload-photo-btn:active {
            transform: translateY(0);
        }

        .upload-photo-btn i {
            font-size: 1.1em;
        }

        .form-section-title {
            color: var(--text-secondary);
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 5px;
            margin-bottom: 20px;
            font-size: 1.1em;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 15px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--text-color);
            font-size: 0.9em;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 0.95em;
            font-family: inherit;
            background: var(--card-bg);
            color: var(--text-color);
        }

        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
        }

        input[readonly] {
            background-color: var(--slot-bg);
            cursor: not-allowed;
            color: var(--text-secondary);
            border-color: var(--border-color);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 60px;
        }

        .row-group {
            display: flex;
            gap: 20px;
        }

        .col-group {
            flex: 1;
        }

        .action-buttons {
            margin-top: 25px;
            display: flex;
            gap: 15px;
        }

        .save-btn {
            padding: 12px 25px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1em;
            transition: background 0.3s;
            font-family: inherit;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .save-btn:hover {
            background-color: var(--primary-hover);
        }

        .save-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .btn-reset-pwd {
            padding: 12px 25px;
            background: linear-gradient(135deg, #ffc107, #ff9800);
            color: #333;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1em;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-reset-pwd:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255,193,7,0.4);
        }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal-overlay.show {
            display: flex;
            opacity: 1;
        }

        .modal-box {
            background: var(--card-bg);
            width: 90%;
            max-width: 550px;
            padding: 35px;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            transform: scale(0.9);
            transition: transform 0.3s ease;
        }

        .modal-overlay.show .modal-box {
            transform: scale(1);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border-color);
        }

        .modal-title {
            font-size: 22px;
            font-weight: 600;
            color: var(--text-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .close-modal {
            font-size: 28px;
            cursor: pointer;
            color: var(--text-secondary);
            transition: color 0.3s;
            line-height: 1;
        }

        .close-modal:hover {
            color: var(--text-color);
        }

        .pwd-req-list {
            list-style: none;
            padding: 0;
            margin: 15px 0 25px 0;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 10px;
        }

        .pwd-req-item {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--slot-bg);
            padding: 8px 12px;
            border-radius: 8px;
            border: 2px solid var(--border-color);
            font-size: 13px;
            transition: all 0.3s;
            color: var(--text-secondary);
        }

        .pwd-req-item.valid {
            background: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }

        .pwd-req-item.invalid {
            background: #fff3cd;
            color: #856404;
            border-color: #ffeeba;
        }

        .pwd-req-item i {
            font-size: 12px;
        }

        .modal-footer {
            margin-top: 25px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn-cancel {
            padding: 12px 25px;
            background-color: var(--slot-bg);
            color: var(--text-secondary);
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s;
        }

        .btn-cancel:hover {
            background-color: var(--border-color);
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 38px;
            cursor: pointer;
            color: var(--text-secondary);
            transition: color 0.3s;
        }

        .toggle-password:hover {
            color: var(--text-color);
        }

        .theme-toggle {
            cursor: pointer; padding: 8px; border-radius: 50%;
            background: var(--slot-bg); border: 1px solid var(--border-color);
            color: var(--text-color); display: flex; align-items: center;
            justify-content: center; width: 35px; height: 35px; margin-right: 15px;
        }
        .theme-toggle img { width: 20px; height: 20px; object-fit: contain; }

        @media (max-width: 900px) {
            .main-content-wrapper {
                margin-left: 0;
                width: 100%;
            }
            .profile-container {
                flex-direction: column;
            }
            .left-column {
                width: 100%;
                border-right: none;
                border-bottom: 1px solid var(--border-color);
                padding-bottom: 20px;
                margin-bottom: 20px;
                padding-right: 0;
            }
            .row-group {
                flex-direction: column;
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
    <nav class="main-menu">
        <ul>
            <?php foreach ($menu_items as $key => $item): ?>
                <?php 
                    $isActive = ($key == $current_page);
                    $hasActiveChild = false;
                    if (isset($item['sub_items'])) {
                        foreach ($item['sub_items'] as $sub_key => $sub) {
                            if ($sub_key == $current_page) { $hasActiveChild = true; break; }
                        }
                    }
                    $linkUrl = isset($item['link']) ? $item['link'] : "#";
                    if ($linkUrl !== "#") { $separator = (strpos($linkUrl, '?') !== false) ? '&' : '?'; $linkUrl .= $separator . "auth_user_id=" . urlencode($auth_user_id); }
                    $hasSubmenu = isset($item['sub_items']);
                ?>
                <li class="menu-item <?php echo ($hasActiveChild || $isActive) ? 'open active' : ''; ?>">
                    <a href="<?php echo $hasSubmenu ? 'javascript:void(0)' : $linkUrl; ?>" class="<?php echo $isActive ? 'active' : ''; ?>" <?php if ($hasSubmenu): ?>onclick="toggleSubmenu(this)"<?php endif; ?>>
                        <i class="fa <?php echo $item['icon']; ?> nav-icon"></i><span class="nav-text"><?php echo $item['name']; ?></span><?php if ($hasSubmenu): ?><i class="fa fa-chevron-down dropdown-arrow"></i><?php endif; ?>
                    </a>
                    <?php if ($hasSubmenu): ?>
                        <ul class="submenu">
                            <?php foreach ($item['sub_items'] as $sub_key => $sub_item): 
                                $subLinkUrl = isset($sub_item['link']) ? $sub_item['link'] : "#";
                                if ($subLinkUrl !== "#") { $separator = (strpos($subLinkUrl, '?') !== false) ? '&' : '?'; $subLinkUrl .= $separator . "auth_user_id=" . urlencode($auth_user_id); }
                            ?>
                                <li><a href="<?php echo $subLinkUrl; ?>" class="<?php echo ($sub_key == $current_page) ? 'active' : ''; ?>"><i class="fa <?php echo $sub_item['icon']; ?> nav-icon"></i><span class="nav-text"><?php echo $sub_item['name']; ?></span></a></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <ul class="logout"><li><a href="login.php"><i class="fa fa-power-off nav-icon"></i><span class="nav-text">Logout</span></a></li></ul>
    </nav>

    <div class="main-content-wrapper">
        <div class="page-header">
            <div class="welcome-text">
                <h1>My Profile</h1>
                <p>Welcome back, <?php echo htmlspecialchars($user_name); ?></p>
            </div>
            
            <div class="logo-section">
                <img src="image/ladybug.png" alt="Logo" class="logo-img">
                <span class="system-title">FYP Portal</span>
            </div>

            <div class="user-section">
                <button class="theme-toggle" onclick="toggleDarkMode()" title="Toggle Dark Mode">
                    <img id="theme-icon" src="image/moon-solid-full.svg" alt="Toggle Theme">
                </button>
                <span class="user-badge">Coordinator</span>
                <img src="<?php echo htmlspecialchars($user_avatar); ?>" class="user-avatar" id="headerAvatar" alt="User Avatar">
            </div>
        </div>

        <form id="profileForm" enctype="multipart/form-data">
            <div class="profile-container">
                <div class="left-column">
                    <div class="profile-img-box" id="profileImgBox">
                        <?php if (!empty($user_avatar) && $user_avatar != 'image/user.png'): ?>
                            <img id="profilePreview" src="<?php echo htmlspecialchars($user_avatar); ?>" alt="Profile">
                        <?php else: ?>
                            <div class="profile-img-placeholder" id="profilePlaceholder">
                                <i class="fa fa-user"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <input type="file" name="profile_img" id="profileImageInput" accept="image/jpeg,image/jpg,image/png,image/gif" style="display: none;">
                    <button type="button" class="upload-photo-btn" onclick="document.getElementById('profileImageInput').click()">
                        <i class="fa fa-camera"></i> Change Photo
                    </button>
                    <div class="student-id-display"><?php echo htmlspecialchars($coor_data['fyp_staffid']); ?></div>
                    <div style="font-size: 0.8em; color: var(--text-secondary); text-align: center;">(Staff ID)</div>
                </div>

                <div class="right-column">
                    <h3 class="form-section-title">Personal Information</h3>
                    <div class="form-group">
                        <label>Full Name:</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($coor_data['fyp_name']); ?>" readonly>
                    </div>
                    <div class="row-group">
                        <div class="col-group form-group">
                            <label>Email Address:</label>
                            <input type="email" class="form-control" value="<?php echo htmlspecialchars($coor_data['fyp_email']); ?>" readonly>
                        </div>
                        <div class="col-group form-group">
                            <label>Room Number:</label>
                            <input type="text" name="room_no" class="form-control" value="<?php echo htmlspecialchars($coor_data['fyp_roomno']); ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Contact Number:</label>
                        <input type="text" name="contact" class="form-control" 
                               value="<?php echo htmlspecialchars($coor_data['fyp_contactno']); ?>" 
                               pattern="^[0-9+\-\s()]*$" 
                               title="Contact number can only contain numbers, spaces, and symbols like + - ( )"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label>Specialization:</label>
                        <textarea name="specialization" class="form-control" placeholder="e.g. Artificial Intelligence, Data Science"><?php echo htmlspecialchars($coor_data['fyp_specialization'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Area of Interest:</label>
                        <textarea name="area_interest" class="form-control" placeholder="Describe your research interests..."><?php echo htmlspecialchars($coor_data['fyp_areaofinterest'] ?? ''); ?></textarea>
                    </div>

                    <div class="action-buttons">
                        <button type="submit" id="saveBtn" class="save-btn">
                            <i class="fa fa-save"></i> Save Changes
                        </button>
                        <button type="button" onclick="openPwdModal()" class="btn-reset-pwd">
                            <i class="fa fa-key"></i> Change Password
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="modal-overlay" id="pwdModal">
        <div class="modal-box">
            <div class="modal-header">
                <div class="modal-title"><i class="fa fa-lock"></i> Change Password</div>
                <span class="close-modal" onclick="closeModal()">&times;</span>
            </div>
            
            <form id="pwdForm">
                <input type="hidden" name="change_password" value="1">
                <input type="hidden" name="ajax" value="1">
                
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" id="current_password" class="form-control" required>
                    <i class="fa fa-eye toggle-password" onclick="togglePwdVisibility('current_password', this)"></i>
                </div>
                
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" id="newPwd" class="form-control" required oninput="validatePwd()">
                    <i class="fa fa-eye toggle-password" onclick="togglePwdVisibility('newPwd', this)"></i>
                </div>
                
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" id="confirmPwd" class="form-control" required oninput="validatePwd()">
                    <i class="fa fa-eye toggle-password" onclick="togglePwdVisibility('confirmPwd', this)"></i>
                </div>

                <ul class="pwd-req-list">
                    <li class="pwd-req-item" id="req-len"><i class="fa fa-circle"></i> 8-16 Chars</li>
                    <li class="pwd-req-item" id="req-num"><i class="fa fa-circle"></i> 1 Number</li>
                    <li class="pwd-req-item" id="req-up"><i class="fa fa-circle"></i> 1 Uppercase</li>
                    <li class="pwd-req-item" id="req-spec"><i class="fa fa-circle"></i> 1 Symbol</li>
                    <li class="pwd-req-item" id="req-match"><i class="fa fa-circle"></i> Passwords Match</li>
                </ul>

                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="save-btn" id="pwdSubmitBtn" disabled>Update Password</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleSubmenu(element) {
            const menuItem = element.parentElement;
            const isOpen = menuItem.classList.contains('open');
            document.querySelectorAll('.menu-item').forEach(item => { if (item !== menuItem) item.classList.remove('open'); });
            if (isOpen) menuItem.classList.remove('open'); else menuItem.classList.add('open');
        }

        const profileImageInput = document.getElementById('profileImageInput');
        const profileImgBox = document.getElementById('profileImgBox');

        profileImageInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!validTypes.includes(file.type)) {
                    Swal.fire('Error', 'Invalid file format', 'error');
                    this.value = '';
                    return;
                }
                if (file.size > 5 * 1024 * 1024) {
                    Swal.fire('Error', 'Image too large (Max 5MB)', 'error');
                    this.value = '';
                    return;
                }
                const reader = new FileReader();
                reader.onload = function(e) {
                    profileImgBox.innerHTML = `<img id="profilePreview" src="${e.target.result}" alt="Profile">`;
                };
                reader.readAsDataURL(file);
            }
        });

        document.getElementById('profileForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('saveBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving...';

            const formData = new FormData(this);
            formData.append('update_profile', '1');
            formData.append('ajax', '1');

            try {
                const res = await fetch('Coordinator_profile.php?auth_user_id=<?php echo $auth_user_id; ?>', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Updated!',
                        text: data.message,
                        timer: 1500,
                        showConfirmButton: false
                    });
                    if(data.image) {
                        document.getElementById('headerAvatar').src = data.image;
                    }
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            } catch (err) {
                Swal.fire('Error', 'Network error', 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa fa-save"></i> Save Changes';
            }
        });

        const modal = document.getElementById('pwdModal');
        function openPwdModal() {
            modal.classList.add('show');
            document.getElementById('pwdForm').reset();
            validatePwd();
        }
        function closeModal() {
            modal.classList.remove('show');
        }
        window.onclick = function(e) {
            if (e.target == modal) closeModal();
        }

        function togglePwdVisibility(id, icon) {
            const input = document.getElementById(id);
            if (input.type === "password") {
                input.type = "text";
                icon.classList.replace("fa-eye", "fa-eye-slash");
            } else {
                input.type = "password";
                icon.classList.replace("fa-eye-slash", "fa-eye");
            }
        }

        function validatePwd() {
            const pwd = document.getElementById('newPwd').value;
            const confirm = document.getElementById('confirmPwd').value;
            const btn = document.getElementById('pwdSubmitBtn');
            let valid = true;

            const updateReq = (id, isValid) => {
                const el = document.getElementById(id);
                if (isValid) {
                    el.classList.add('valid');
                    el.classList.remove('invalid');
                    el.querySelector('i').className = 'fa fa-check';
                } else {
                    el.classList.add('invalid');
                    el.classList.remove('valid');
                    el.querySelector('i').className = 'fa fa-circle';
                    valid = false;
                }
            };

            updateReq('req-len', pwd.length >= 8 && pwd.length <= 16);
            updateReq('req-num', /\d/.test(pwd));
            updateReq('req-up', /[A-Z]/.test(pwd));
            updateReq('req-spec', /[\W_]/.test(pwd));
            updateReq('req-match', pwd === confirm && pwd !== '');

            btn.disabled = !valid;
        }

        document.getElementById('pwdForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('pwdSubmitBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Updating...';

            const formData = new FormData(this);

            try {
                const res = await fetch('Coordinator_profile.php?auth_user_id=<?php echo $auth_user_id; ?>', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                
                if (data.success) {
                    Swal.fire('Success', data.message, 'success');
                    closeModal();
                } else {
                    Swal.fire('Error', data.message, 'error');
                    btn.disabled = false;
                    btn.innerHTML = 'Update Password';
                }
            } catch (err) {
                Swal.fire('Error', 'Error changing password', 'error');
                btn.disabled = false;
                btn.innerHTML = 'Update Password';
            }
        });

        function toggleDarkMode() {
            document.body.classList.toggle('dark-mode');
            const isDark = document.body.classList.contains('dark-mode');
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
            
            const iconImg = document.getElementById('theme-icon');
            if (isDark) {
                iconImg.src = 'image/sun-solid-full.svg'; 
            } else {
                iconImg.src = 'image/moon-solid-full.svg'; 
            }
        }

        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            document.body.classList.add('dark-mode');
            const iconImg = document.getElementById('theme-icon');
            if(iconImg) {
                iconImg.src = 'image/sun-solid-full.svg'; 
            }
        }
    </script>
</body>
</html>