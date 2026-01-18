<?php
include("connect.php");

$auth_user_id = $_GET['auth_user_id'] ?? null;
$current_page = 'view_presentation';

if (!$auth_user_id) { header("location: login.php"); exit; }

$stud_data = [];
$user_name = 'Student';
$user_avatar = '';

if (isset($conn)) {
    $sql_user = "SELECT fyp_username FROM `USER` WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_user)) { 
        $stmt->bind_param("i", $auth_user_id); $stmt->execute(); 
        $res = $stmt->get_result(); if($row=$res->fetch_assoc()) $user_name=$row['fyp_username']; 
        $stmt->close(); 
    }
    $sql_stud = "SELECT * FROM STUDENT WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_stud)) { 
        $stmt->bind_param("i", $auth_user_id); $stmt->execute(); 
        $res=$stmt->get_result(); 
        if($res->num_rows > 0) { 
            $stud_data = $res->fetch_assoc(); 
            if(!empty($stud_data['fyp_studname'])) $user_name=$stud_data['fyp_studname']; 
            if(!empty($stud_data['fyp_profileimg'])) $user_avatar = $stud_data['fyp_profileimg'];
        } else { 
             $stud_data=['fyp_studid'=>'', 'fyp_studname'=>$user_name, 'fyp_group'=>'Individual', 'fyp_profileimg'=>''];
        } 
        $stmt->close(); 
    }
}

$student_id = $stud_data['fyp_studid'];
$student_group_type = $stud_data['fyp_group'];
$target_id = "";
$display_identity = "";

if ($student_group_type == 'Individual') {
    $target_id = $student_id;
    $display_identity = "Individual: " . $user_name . " (" . $student_id . ")";
} else {
    $g_sql = "SELECT group_id, group_name FROM student_group WHERE leader_id = '$student_id' 
              UNION 
              SELECT sg.group_id, sg.group_name FROM group_request gr JOIN student_group sg ON gr.group_id = sg.group_id WHERE gr.invitee_id = '$student_id' AND gr.request_status = 'Accepted'";
    $g_res = $conn->query($g_sql);
    if ($g_row = $g_res->fetch_assoc()) {
        $target_id = $g_row['group_id'];
        $display_identity = "Group: " . $g_row['group_name'];
    } else {
        $display_identity = "Group: Not Assigned Yet";
    }
}

$schedule_info = null;
$moderator_info = [
    'name' => 'Pending Assignment',
    'image' => 'image/user.png', 
    'role' => 'Moderator'
];

if (!empty($target_id)) {
    $sql_sch = "SELECT sp.*, p.fyp_projecttitle 
                FROM schedule_presentation sp
                LEFT JOIN project p ON sp.project_id = p.fyp_projectid
                WHERE sp.target_type = ? AND sp.target_id = ?";
                
    if ($stmt = $conn->prepare($sql_sch)) {
        $stmt->bind_param("ss", $student_group_type, $target_id);
        $stmt->execute();
        $res_sch = $stmt->get_result();
        
        if ($row_sch = $res_sch->fetch_assoc()) {
            $schedule_info = $row_sch;
            
            if (!empty($row_sch['moderator_id'])) {
                $mod_staff_id = $row_sch['moderator_id'];
                
                $sup_sql = "SELECT fyp_name, fyp_profileimg FROM supervisor WHERE fyp_staffid = '$mod_staff_id'";
                $sup_res = $conn->query($sup_sql);
                if ($sup_row = $sup_res->fetch_assoc()) {
                    $moderator_info['name'] = $sup_row['fyp_name'];
                    if(!empty($sup_row['fyp_profileimg'])) $moderator_info['image'] = $sup_row['fyp_profileimg'];
                    $moderator_info['role'] = 'Supervisor (Moderator)';
                } else {
                    $coor_sql = "SELECT fyp_name, fyp_profileimg FROM coordinator WHERE fyp_staffid = '$mod_staff_id'";
                    $coor_res = $conn->query($coor_sql);
                    if ($coor_row = $coor_res->fetch_assoc()) {
                        $moderator_info['name'] = $coor_row['fyp_name'];
                        if(!empty($coor_row['fyp_profileimg'])) $moderator_info['image'] = $coor_row['fyp_profileimg'];
                        $moderator_info['role'] = 'Coordinator (Moderator)';
                    }
                }
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Presentation</title>
    <link rel="icon" type="image/png" href="image/ladybug.png?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        
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
            --header-bg: #ffffff;
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
            --header-bg: #1e1e1e;
            --border-color: #333;
            --slot-bg: #2d2d2d;
        }

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
            position: fixed; top: 0; bottom: 0; height: 100%; left: 0; width: 60px;
            overflow: hidden; transition: width .05s linear; z-index: 1000;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }
        .main-menu:hover { width: 250px; overflow: visible; }
        .main-menu > ul { margin: 7px 0; padding: 0; list-style: none; }
        .main-menu li { position: relative; display: block; width: 250px; }
        .main-menu li > a { position: relative; display: table; border-collapse: collapse; border-spacing: 0; color: var(--sidebar-text); font-size: 14px; text-decoration: none; transition: all .1s linear; width: 100%; }
        .main-menu .nav-icon { position: relative; display: table-cell; width: 60px; height: 46px; text-align: center; vertical-align: middle; font-size: 18px; }
        .main-menu .nav-text { position: relative; display: table-cell; vertical-align: middle; width: 190px; padding-left: 10px; white-space: nowrap; }
        .main-menu li:hover > a, nav.main-menu li.active > a { color: #fff; background-color: var(--sidebar-hover); border-left: 4px solid #fff; }
        .main-menu > ul.logout { position: absolute; left: 0; bottom: 0; width: 100%; }
        
        .main-content-wrapper { margin-left: 60px; flex: 1; padding: 20px; width: calc(100% - 60px); transition: margin-left .05s linear; }
        
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; background: var(--header-bg); padding: 20px; border-radius: 12px; box-shadow: var(--card-shadow); transition: background 0.3s; }
        .welcome-text h1 { margin: 0; font-size: 24px; color: var(--primary-color); font-weight: 600; }
        .welcome-text p { margin: 5px 0 0; color: var(--text-secondary); font-size: 14px; }
        .logo-section { display: flex; align-items: center; gap: 12px; }
        .logo-img { height: 40px; width: auto; background: white; padding: 2px; border-radius: 6px; }
        .system-title { font-size: 20px; font-weight: 600; color: var(--primary-color); letter-spacing: 0.5px; }

        .user-section { display: flex; align-items: center; gap: 10px; }
        .user-badge {
            font-size: 13px; color: var(--text-secondary); background: var(--slot-bg);
            padding: 5px 10px; border-radius: 20px;
        }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
        .user-avatar-placeholder {
            width: 40px; height: 40px; border-radius: 50%; background: #0056b3;
            color: white; display: flex; align-items: center; justify-content: center; font-weight: bold;
        }

        .schedule-card { 
            background: var(--card-bg); 
            border-radius: 12px; 
            box-shadow: var(--card-shadow); 
            overflow: hidden; 
            max-width: 900px; 
            margin: 0 auto; 
            display: flex; 
            flex-direction: row; 
            border: 1px solid var(--border-color);
        }
        
        .date-section { 
            background: var(--primary-color); 
            color: white; 
            width: 200px; 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            justify-content: center; 
            padding: 30px; 
            text-align: center; 
            position: relative;
        }
        .date-day { font-size: 56px; font-weight: 700; line-height: 1; }
        .date-month { font-size: 20px; font-weight: 500; text-transform: uppercase; margin-bottom: 5px; opacity: 0.9; }
        .date-year { font-size: 14px; opacity: 0.7; }
        .date-time { 
            background: rgba(255,255,255,0.15); 
            padding: 6px 15px; 
            border-radius: 20px; 
            font-size: 14px; 
            margin-top: 15px; 
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .info-section { padding: 30px 40px; flex: 1; position: relative; }
        .project-title { font-size: 22px; font-weight: 700; color: var(--text-color); margin-bottom: 5px; line-height: 1.3; }
        
        .status-badge {
            position: absolute; top: 20px; right: 20px; 
            font-size: 12px; font-weight: 600; 
            text-transform: uppercase; 
            display: flex; align-items: center; gap: 5px;
        }
        
        .badges-row { display: flex; gap: 10px; margin-bottom: 25px; margin-top: 15px; flex-wrap: wrap; }
        .info-badge { display: inline-flex; align-items: center; gap: 6px; background: var(--slot-bg); color: var(--text-secondary); padding: 6px 12px; border-radius: 6px; font-size: 13px; border: 1px solid var(--border-color); }
        .badge-venue { background: #e3effd; color: var(--primary-color); border-color: #d6e4ff; }
        .badge-identity { background: #fff3cd; color: #856404; border-color: #ffeeba; }

        .note-box {
            font-size: 13px; color: var(--text-secondary); line-height: 1.6;
            background: var(--slot-bg); padding: 10px 15px; 
            border-left: 3px solid var(--border-color); margin-bottom: 20px;
        }

        .moderator-box { display: flex; align-items: center; gap: 15px; border-top: 1px solid var(--border-color); padding-top: 20px; }
        .mod-avatar { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 2px solid #e3effd; }
        .mod-details h4 { margin: 0; font-size: 15px; color: var(--text-color); font-weight: 600; }
        .mod-details p { margin: 2px 0 0; font-size: 12px; color: var(--text-secondary); text-transform: uppercase; }

        .empty-state { text-align: center; padding: 50px; background: var(--card-bg); border-radius: 12px; box-shadow: var(--card-shadow); color: var(--text-secondary); }

        .theme-toggle {
            cursor: pointer; padding: 8px; border-radius: 50%;
            background: var(--slot-bg); border: 1px solid var(--border-color);
            color: var(--text-color); display: flex; align-items: center;
            justify-content: center; width: 35px; height: 35px; margin-right: 15px;
        }
        .theme-toggle img { width: 20px; height: 20px; object-fit: contain; }

        @media (max-width: 800px) { 
            .schedule-card { flex-direction: column; } 
            .date-section { width: 100%; padding: 20px; box-sizing: border-box; flex-direction: row; gap: 20px; justify-content: space-between; text-align: left; }
            .date-time { margin-top: 0; }
            .status-badge { position: relative; top: 0; right: 0; margin-bottom: 10px; display: inline-flex;}
        }
    </style>
</head>
<body>

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
            <li>
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
            <li class="active">
                <a href="Student_view_presentation.php?auth_user_id=<?php echo $auth_user_id; ?>">
                    <i class="fa fa-desktop nav-icon"></i>
                    <span class="nav-text">Presentation</span>
                </a>
            </li>
            <li>
                <a href="Student_view_result.php?auth_user_id=<?php echo $auth_user_id; ?>">
                    <i class="fa fa-star nav-icon"></i>
                    <span class="nav-text">View Results</span>
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

    <div class="main-content-wrapper">
        
        <div class="page-header">
            <div class="welcome-text">
                <h1>Presentation</h1>
                <p>Welcome back, <?php echo htmlspecialchars($user_name); ?></p>
            </div>
            
            <div class="logo-section">
                <img src="image/ladybug.png?v=<?php echo time(); ?>" alt="Logo" class="logo-img">
                <span class="system-title">FYP Portal</span>
            </div>

            <div class="user-section">
                <button class="theme-toggle" onclick="toggleDarkMode()" title="Toggle Dark Mode">
                    <img id="theme-icon" src="image/moon-solid-full.svg" alt="Toggle Theme">
                </button>
                <span class="user-badge">Student</span>
                <?php if(!empty($user_avatar) && $user_avatar != 'image/user.png'): ?>
                    <img src="<?php echo htmlspecialchars($user_avatar); ?>" class="user-avatar" alt="User Avatar">
                <?php else: ?>
                    <div class="user-avatar-placeholder">
                        <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($schedule_info): 
            $raw_date = strtotime($schedule_info['presentation_date']);
            $day = date('d', $raw_date);
            $month = date('M', $raw_date);
            $year = date('Y', $raw_date);
            $time = date('h:i A', $raw_date);
            
            $days_left = floor(($raw_date - time()) / (60 * 60 * 24));
            $time_status = ($days_left < 0) ? "Completed" : "Upcoming";
            $status_color = ($days_left < 0) ? "#28a745" : "#e67e22";
        ?>
            <h3 style="margin-bottom: 20px; color: var(--text-color); margin-left: 5px;">Your Schedule</h3>
            
            <div class="schedule-card">
                <div class="date-section">
                    <div>
                        <div class="date-day"><?php echo $day; ?></div>
                        <div class="date-month"><?php echo $month; ?></div>
                        <div class="date-year"><?php echo $year; ?></div>
                    </div>
                    <div class="date-time"><i class="fa fa-clock-o"></i> <?php echo $time; ?></div>
                </div>
                
                <div class="info-section">
                    <div class="status-badge" style="color: <?php echo $status_color; ?>;">
                        <i class="fa fa-circle" style="font-size:8px;"></i> <?php echo $time_status; ?>
                    </div>

                    <div class="project-title"><?php echo htmlspecialchars($schedule_info['fyp_projecttitle'] ?? 'Final Year Project'); ?></div>
                    
                    <div class="badges-row">
                        <span class="info-badge badge-venue">
                            <i class="fa fa-map-marker"></i> Venue: <?php echo htmlspecialchars($schedule_info['venue']); ?>
                        </span>
                        
                        <span class="info-badge badge-identity">
                            <i class="fa fa-id-badge"></i> <?php echo htmlspecialchars($display_identity); ?>
                        </span>
                    </div>
                    
                    <div class="note-box">
                        <i class="fa fa-info-circle"></i> Please ensure all presentation materials are ready. Arrive at the venue at least 15 minutes prior to your slot.
                    </div>

                    <div class="moderator-box">
                        <img src="<?php echo $moderator_info['image']; ?>" alt="Mod" class="mod-avatar">
                        <div class="mod-details">
                            <p>Assigned Moderator</p>
                            <h4><?php echo htmlspecialchars($moderator_info['name']); ?></h4>
                            <span style="font-size:12px; color:var(--text-secondary);"><?php echo htmlspecialchars($moderator_info['role']); ?></span>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>
            
            <div class="empty-state">
                <i class="fa fa-calendar-times-o" style="font-size: 50px; opacity: 0.3; margin-bottom: 20px;"></i>
                <h3 style="color: var(--text-secondary); font-size: 18px;">No Presentation Scheduled</h3>
                <p style="color: var(--text-secondary); font-size: 14px;">Your presentation slot hasn't been finalized yet.<br>Please check back later or contact your coordinator.</p>
            </div>

        <?php endif; ?>

    </div>

    <script>
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