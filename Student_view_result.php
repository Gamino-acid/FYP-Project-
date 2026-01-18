<?php
include("connect.php");

$auth_user_id = $_GET['auth_user_id'] ?? null;
$current_page = 'view_result';

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
            die("Student record not found for this user.");
        }
        $stmt->close(); 
    }
}

$real_stud_id = $stud_data['fyp_studid'];

function getGrade($score) {
    if ($score >= 90) return 'A+';
    if ($score >= 80) return 'A';
    if ($score >= 75) return 'A-';
    if ($score >= 70) return 'B+';
    if ($score >= 65) return 'B';
    if ($score >= 60) return 'B-';
    if ($score >= 55) return 'C+';
    if ($score >= 50) return 'C';
    if ($score >= 45) return 'C-';
    if ($score >= 40) return 'D';
    return 'F'; 
}

$assignments_data = [];
$total_weightage = 0;
$total_score = 0;
$is_finalized = false;

$target_group_id = 'UNKNOWN';
if ($stud_data['fyp_group'] == 'Group') {
    $gid_res = $conn->query("SELECT group_id FROM student_group WHERE leader_id='$real_stud_id' UNION SELECT group_id FROM group_request WHERE invitee_id='$real_stud_id' AND request_status='Accepted'");
    if ($g_row = $gid_res->fetch_assoc()) $target_group_id = $g_row['group_id'];
}

$sql_marks = "SELECT a.fyp_title, a.fyp_weightage, a.fyp_assignment_type,
                     sub.fyp_marks, sub.fyp_mod_marks 
              FROM assignment a
              LEFT JOIN assignment_submission sub ON a.fyp_assignmentid = sub.fyp_assignmentid AND sub.fyp_studid = '$real_stud_id'
              WHERE (a.fyp_target_id = 'ALL' 
                     OR (a.fyp_assignment_type = 'Individual' AND a.fyp_target_id = '$real_stud_id')
                     OR (a.fyp_assignment_type = 'Group' AND a.fyp_target_id = '$target_group_id')
                    )
              ORDER BY a.fyp_deadline ASC";

$res_m = $conn->query($sql_marks);

if ($res_m) {
    while ($row = $res_m->fetch_assoc()) {
        $w = intval($row['fyp_weightage']);
        $s_mark = isset($row['fyp_marks']) ? floatval($row['fyp_marks']) : 0;
        $m_mark = isset($row['fyp_mod_marks']) ? floatval($row['fyp_mod_marks']) : null;
        
        if ($m_mark !== null) {
            $raw_avg = ($s_mark + $m_mark) / 2;
        } else {
            $raw_avg = $s_mark; 
        }
        
        $contribution = ($raw_avg / 100) * $w; 
        
        $total_weightage += $w;
        $total_score += $contribution;
        
        $row['raw_avg'] = $raw_avg; 
        $row['contribution'] = $contribution; 
        $assignments_data[] = $row;
    }
}

if ($total_weightage >= 100) {
    $is_finalized = true;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Results</title>
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

        .result-container { 
            background: var(--card-bg); 
            padding: 40px; 
            border-radius: 12px; 
            box-shadow: var(--card-shadow); 
            max-width: 900px; 
            margin: 0 auto; 
            border-top: 5px solid var(--primary-color);
        }
        
        .result-header { text-align: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid var(--border-color); }
        .result-header h2 { margin: 0; color: var(--text-color); font-size: 22px; }
        
        .stud-info-box { display: flex; justify-content: space-between; margin-bottom: 30px; background: var(--slot-bg); padding: 20px; border-radius: 8px; border: 1px solid var(--border-color); }
        .stud-info-item strong { display: block; font-size: 12px; color: var(--text-secondary); text-transform: uppercase; margin-bottom: 4px; }
        .stud-info-item span { font-size: 15px; color: var(--text-color); font-weight: 600; }

        .score-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .score-table th { background: var(--primary-color); color: #fff; text-align: left; padding: 12px 15px; font-size: 13px; text-transform: uppercase; }
        .score-table td { padding: 15px; border-bottom: 1px solid var(--border-color); font-size: 14px; color: var(--text-color); }
        .score-table tr:last-child td { border-bottom: 2px solid var(--primary-color); }
        .total-row td { background: var(--slot-bg); font-weight: 700; color: var(--primary-color); font-size: 16px; border-bottom: none; }

        .final-grade-box { text-align: center; margin-top: 30px; padding: 30px; border: 2px dashed var(--primary-color); border-radius: 12px; background: var(--slot-bg); }
        .final-grade-display { font-size: 56px; font-weight: 800; color: var(--primary-color); line-height: 1; margin: 10px 0; }
        
        .pending-box { text-align: center; padding: 60px 20px; color: var(--text-secondary); border: 1px dashed var(--border-color); border-radius: 12px; }
        .badge { padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; text-transform: uppercase; display: inline-block; }
        .badge-ind { background: #eee; color: #555; }
        .badge-grp { background: #e3effd; color: var(--primary-color); }

        .theme-toggle {
            cursor: pointer; padding: 8px; border-radius: 50%;
            background: var(--slot-bg); border: 1px solid var(--border-color);
            color: var(--text-color); display: flex; align-items: center;
            justify-content: center; width: 35px; height: 35px; margin-right: 15px;
        }
        .theme-toggle img { width: 20px; height: 20px; object-fit: contain; }
        
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
            <li>
                <a href="Student_view_presentation.php?auth_user_id=<?php echo $auth_user_id; ?>">
                    <i class="fa fa-desktop nav-icon"></i>
                    <span class="nav-text">Presentation</span>
                </a>
            </li>
            <li class="active">
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
                <h1>Assessment Result</h1>
                <p>Academic Session 2025/2026</p>
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

        <div class="result-container">
            
            <div class="result-header">
                <h2>Final Examination Result Slip</h2>
            </div>

            <div class="stud-info-box">
                <div class="stud-info-item">
                    <strong>Student Name</strong>
                    <span><?php echo htmlspecialchars($stud_data['fyp_studname']); ?></span>
                </div>
                <div class="stud-info-item">
                    <strong>Student ID</strong>
                    <span><?php echo htmlspecialchars($stud_data['fyp_studid']); ?></span>
                </div>
                <div class="stud-info-item">
                    <strong>Project Type</strong>
                    <span>
                        <?php echo $stud_data['fyp_group']; ?>
                        <?php if($target_group_id != 'UNKNOWN') echo " (Group)"; ?>
                    </span>
                </div>
            </div>

            <?php if ($is_finalized): ?>
                <table class="score-table">
                    <thead>
                        <tr>
                            <th style="width: 40%;">Assessment Component</th>
                            <th style="width: 15%;">Type</th>
                            <th style="width: 15%; text-align: center;">Weightage</th>
                            <th style="width: 15%; text-align: center;">Raw Score</th>
                            <th style="width: 15%; text-align: right;">Contribution</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assignments_data as $row): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['fyp_title']); ?></strong>
                                </td>
                                <td>
                                    <span class="badge <?php echo ($row['fyp_assignment_type']=='Individual') ? 'badge-ind' : 'badge-grp'; ?>">
                                        <?php echo $row['fyp_assignment_type']; ?>
                                    </span>
                                </td>
                                <td style="text-align: center; color: var(--text-secondary);">
                                    <?php echo $row['fyp_weightage']; ?>%
                                </td>
                                <td style="text-align: center;">
                                    <?php echo number_format($row['raw_avg'], 1); ?>
                                </td>
                                <td style="text-align: right; font-weight: 600;">
                                    <?php echo number_format($row['contribution'], 1); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <tr class="total-row">
                            <td colspan="4" style="text-align: right;">TOTAL SCORE</td>
                            <td style="text-align: right;">
                                <?php echo number_format($total_score, 1); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="final-grade-box">
                    <div style="color: var(--text-secondary); font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">Overall Final Grade</div>
                    <div class="final-grade-display"><?php echo getGrade($total_score); ?></div>
                    <div style="font-weight: 600; color: var(--primary-color);"><?php echo ($total_score >= 50) ? "PASS" : "FAIL"; ?></div>
                </div>

                <div style="text-align: center; margin-top: 30px; font-size: 12px; color: var(--text-secondary); line-height:1.5;">
                    This result slip is computer generated. No signature is required.<br>
                    Date Generated: <?php echo date('d M Y'); ?>
                </div>

            <?php else: ?>
                <div class="pending-box">
                    <i class="fa fa-lock" style="font-size: 50px; color: #ddd; margin-bottom: 20px;"></i>
                    <div style="font-size: 18px; font-weight: 500; color: var(--text-color);">Results Not Yet Released</div>
                    <div style="font-size: 14px; margin-top: 10px; color: var(--text-secondary);">
                        Your assessment is currently in progress or partially graded.<br>
                        Current Progress: <b style="color: var(--primary-color);"><?php echo $total_weightage; ?>%</b> / 100% Weightage Collected.
                    </div>
                </div>
            <?php endif; ?>

        </div>
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