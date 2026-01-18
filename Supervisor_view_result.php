<?php
include("connect.php");

$auth_user_id = $_GET['auth_user_id'] ?? null;
$current_page = 'view_result'; 

if (!$auth_user_id) { header("location: login.php"); exit; }

$user_name = "Supervisor"; 
$user_avatar = "image/user.png"; 
$sv_id = 0;

if (isset($conn)) {
    $stmt = $conn->prepare("SELECT fyp_username FROM `USER` WHERE fyp_userid = ?");
    $stmt->bind_param("i", $auth_user_id); $stmt->execute();
    $res = $stmt->get_result(); if ($row = $res->fetch_assoc()) $user_name = $row['fyp_username'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT * FROM supervisor WHERE fyp_userid = ?");
    $stmt->bind_param("i", $auth_user_id); $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $sv_id = $row['fyp_staffid'] ?? $row['fyp_supervisorid'];
        if (!empty($row['fyp_name'])) $user_name = $row['fyp_name'];
        if (!empty($row['fyp_profileimg'])) $user_avatar = $row['fyp_profileimg'];
    }
    $stmt->close();
}

function getGradeFromScore($score) {
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

$student_results = [];

if ($sv_id) {
    $sql_stud = "SELECT s.fyp_studid, s.fyp_studname, s.fyp_group, g.group_name
                 FROM fyp_registration r
                 JOIN student s ON r.fyp_studid = s.fyp_studid
                 LEFT JOIN student_group g ON s.fyp_studid = g.leader_id 
                 WHERE r.fyp_staffid = '$sv_id' 
                 AND (r.fyp_archive_status = 'Active' OR r.fyp_archive_status IS NULL)
                 ORDER BY s.fyp_studname ASC";
                 
    $res_stud = $conn->query($sql_stud);
    
    while ($stud = $res_stud->fetch_assoc()) {
        $stud_id = $stud['fyp_studid'];
        $group_name = $stud['group_name']; 
        
        if (empty($group_name) && $stud['fyp_group'] == 'Group') {
             $g_res = $conn->query("SELECT sg.group_name FROM group_request gr JOIN student_group sg ON gr.group_id = sg.group_id WHERE gr.invitee_id = '$stud_id' AND gr.request_status='Accepted'");
             if ($gr = $g_res->fetch_assoc()) $group_name = $gr['group_name'];
        }

        $target_sql_filter = "";
        if ($stud['fyp_group'] == 'Group') {
             $gid_res = $conn->query("SELECT group_id FROM student_group WHERE leader_id='$stud_id' UNION SELECT group_id FROM group_request WHERE invitee_id='$stud_id' AND request_status='Accepted'");
             $gid = ($gid_row = $gid_res->fetch_assoc()) ? $gid_row['group_id'] : 'UNKNOWN';
             $target_sql_filter = "(a.fyp_target_id = 'ALL' OR a.fyp_target_id = '$gid') AND a.fyp_assignment_type = 'Group'";
        } else {
             $target_sql_filter = "(a.fyp_target_id = 'ALL' OR a.fyp_target_id = '$stud_id') AND a.fyp_assignment_type = 'Individual'";
        }

        $sql_marks = "SELECT a.fyp_weightage, 
                             sub.fyp_marks, sub.fyp_mod_marks 
                      FROM assignment a
                      LEFT JOIN assignment_submission sub ON a.fyp_assignmentid = sub.fyp_assignmentid AND sub.fyp_studid = '$stud_id'
                      WHERE a.fyp_staffid = '$sv_id' AND $target_sql_filter";

        $res_marks = $conn->query($sql_marks);
        
        $total_weightage = 0;
        $total_score = 0;
        $missing_marks = false;

        while ($m = $res_marks->fetch_assoc()) {
            $w = intval($m['fyp_weightage']);
            $s_mark = isset($m['fyp_marks']) ? floatval($m['fyp_marks']) : 0;
            $m_mark = isset($m['fyp_mod_marks']) ? floatval($m['fyp_mod_marks']) : null; 
            
            $total_weightage += $w;

            $assignment_final_mark = 0;
            
            if ($m_mark !== null) {
                $raw_avg = ($s_mark + $m_mark) / 2;
            } else {
                $raw_avg = $s_mark; 
            }

            $weighted_score = ($raw_avg / 100) * $w;
            $total_score += $weighted_score;
        }

        $final_grade = "N/A";
        $status_class = "st-Pending";
        $status_text = "Pending";

        if ($total_weightage == 100) {
            $grade = getGradeFromScore($total_score);
            $final_grade = $grade;
            $status_class = "st-Completed";
            $status_text = "Finalized";
        } elseif ($total_weightage == 0) {
            $status_text = "No Assignments";
        } else {
            $status_text = "Incomplete ($total_weightage%)";
            $status_class = "st-Incomplete";
        }

        $student_results[] = [
            'name' => $stud['fyp_studname'],
            'id' => $stud['fyp_studid'],
            'type' => $stud['fyp_group'],
            'group' => $group_name,
            'total_weight' => $total_weightage,
            'final_grade' => $final_grade, 
            'status_class' => $status_class,
            'status_text' => $status_text
        ];
    }
}

$menu_items = [
    'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home', 'link' => 'Supervisor_mainpage.php?page=dashboard'],
    'profile'   => ['name' => 'My Profile', 'icon' => 'fa-user', 'link' => 'supervisor_profile.php'],
    'students'  => [
        'name' => 'My Students', 
        'icon' => 'fa-users',
        'sub_items' => [
            'project_requests' => ['name' => 'Project Requests', 'icon' => 'fa-envelope-open-text', 'link' => 'Supervisor_projectreq.php'],
            'student_list'     => ['name' => 'Student List', 'icon' => 'fa-list', 'link' => 'Supervisor_student_list.php'],
        ]
    ],
    'fyp_project' => [
        'name' => 'FYP Project',
        'icon' => 'fa-project-diagram',
        'sub_items' => [
            'propose_project' => ['name' => 'Propose Project', 'icon' => 'fa-plus-circle', 'link' => 'supervisor_purpose.php'],
            'my_projects'     => ['name' => 'My Projects', 'icon' => 'fa-folder-open', 'link' => 'Supervisor_manage_project.php'],
            'propose_assignment' => ['name' => 'Propose Assignment', 'icon' => 'fa-tasks', 'link' => 'supervisor_assignment_purpose.php'],
        ]
    ],
    'grading' => [
        'name' => 'Assessment',
        'icon' => 'fa-marker',
        'sub_items' => [
            'grade_assignment' => ['name' => 'Grade Assignments', 'icon' => 'fa-check-square', 'link' => 'Supervisor_assignment_grade.php'],
            'grade_mod' => ['name' => 'Moderator Grading', 'icon' => 'fa-gavel', 'link' => 'Moderator_assignment_grade.php'],
        ]
    ],
    'announcement' => [
        'name' => 'Announcement',
        'icon' => 'fa-bullhorn',
        'sub_items' => [
            'post_announcement' => ['name' => 'Post Announcement', 'icon' => 'fa-pen-square', 'link' => 'supervisor_announcement.php'],
            'view_announcements' => ['name' => 'View History', 'icon' => 'fa-history', 'link' => 'Supervisor_announcement_view.php'],
        ]
    ],
    'schedule'  => ['name' => 'My Schedule', 'icon' => 'fa-calendar-alt', 'link' => 'supervisor_meeting.php'],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Final Results</title>
    <link rel="icon" type="image/png" href="image/ladybug.png">
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

        body { font-family: 'Poppins', sans-serif; margin: 0; background-color: var(--bg-color); min-height: 100vh; display: flex; overflow-x: hidden; transition: background-color 0.3s, color 0.3s; }

        .main-menu { background: var(--sidebar-bg); border-right: 1px solid rgba(255,255,255,0.1); position: fixed; top: 0; bottom: 0; height: 100%; left: 0; width: 60px; overflow-y: auto; overflow-x: hidden; transition: width .05s linear; z-index: 1000; box-shadow: 2px 0 5px rgba(0,0,0,0.1); }
        .main-menu:hover, nav.main-menu.expanded { width: 250px; }
        .main-menu > ul { margin: 7px 0; padding: 0; list-style: none; }
        .main-menu li { position: relative; display: block; width: 250px; }
        .main-menu li > a { position: relative; display: table; border-collapse: collapse; border-spacing: 0; color: var(--sidebar-text); font-size: 14px; text-decoration: none; transition: all .1s linear; width: 100%; }
        .main-menu .nav-icon { position: relative; display: table-cell; width: 60px; height: 46px; text-align: center; vertical-align: middle; font-size: 18px; }
        .main-menu .nav-text { position: relative; display: table-cell; vertical-align: middle; width: 190px; padding-left: 10px; white-space: nowrap; }
        .main-menu li:hover > a, nav.main-menu li.active > a, .menu-item.open > a { color: #fff; background-color: var(--sidebar-hover); border-left: 4px solid #fff; }
        .main-menu > ul.logout { position: absolute; left: 0; bottom: 0; width: 100%; }
        .dropdown-arrow { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); transition: transform 0.3s; font-size: 12px; }
        .menu-item.open .dropdown-arrow { transform: translateY(-50%) rotate(180deg); }
        .submenu { list-style: none; padding: 0; margin: 0; background-color: rgba(0,0,0,0.2); max-height: 0; overflow: hidden; transition: max-height 0.3s ease-out; }
        .menu-item.open .submenu { max-height: 500px; transition: max-height 0.5s ease-in; }
        .submenu li > a { padding-left: 70px !important; font-size: 13px; height: 40px; }

        .main-content-wrapper { margin-left: 60px; flex: 1; padding: 20px; width: calc(100% - 60px); transition: margin-left .05s linear; }

        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; background: var(--card-bg); padding: 20px; border-radius: 12px; box-shadow: var(--card-shadow); transition: background 0.3s; }
        .welcome-text h1 { margin: 0; font-size: 24px; color: var(--primary-color); font-weight: 600; }
        .welcome-text p { margin: 5px 0 0; color: var(--text-color); font-size: 14px; opacity: 0.8; }
        .logo-section { display: flex; align-items: center; gap: 12px; }
        .logo-img { height: 40px; width: auto; background: white; padding: 2px; border-radius: 6px; }
        .system-title { font-size: 20px; font-weight: 600; color: var(--primary-color); letter-spacing: 0.5px; }

        .user-section { display: flex; align-items: center; gap: 10px; }
        .user-badge { font-size: 13px; color: var(--text-secondary); background: var(--slot-bg); padding: 5px 10px; border-radius: 20px; }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
        .user-avatar-placeholder { width: 40px; height: 40px; border-radius: 50%; background: #0056b3; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; }

        .result-container { background: var(--card-bg); padding: 25px; border-radius: 12px; box-shadow: var(--card-shadow); border-top: 5px solid var(--primary-color); transition: background 0.3s; }
        .result-header { text-align: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid var(--border-color); }
        .result-header h2 { margin: 0; color: var(--text-color); font-size: 22px; }

        .result-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .result-table th { background: var(--slot-bg); text-align: left; padding: 15px; color: var(--text-secondary); font-size: 13px; font-weight: 600; border-bottom: 2px solid var(--border-color); }
        .result-table td { padding: 15px; border-bottom: 1px solid var(--border-color); color: var(--text-color); font-size: 14px; vertical-align: middle; }
        .result-table tr:hover { background-color: var(--slot-bg); }

        .grade-badge { display: inline-block; width: 35px; height: 35px; line-height: 35px; text-align: center; border-radius: 50%; font-weight: 700; font-size: 13px; }
        .grade-A, .grade-A-plus, .grade-A-minus { background: #d4edda; color: #155724; }
        .grade-B, .grade-B-plus, .grade-B-minus { background: #cce5ff; color: #004085; }
        .grade-C, .grade-C-plus, .grade-C-minus { background: #fff3cd; color: #856404; }
        .grade-D, .grade-F { background: #f8d7da; color: #721c24; }
        .grade-NA { background: #eee; color: #999; width: auto; padding: 0 10px; border-radius: 4px; }

        .status-pill { font-size: 11px; padding: 3px 8px; border-radius: 10px; font-weight: 600; text-transform: uppercase; }
        .st-Completed { background: #d4edda; color: #155724; }
        .st-Incomplete { background: #f8d7da; color: #721c24; }
        .st-Pending { background: #fff3cd; color: #856404; }

        .weight-bar { height: 6px; background: #eee; border-radius: 3px; width: 100px; margin-top: 5px; overflow: hidden; }
        .weight-fill { height: 100%; background: var(--primary-color); }
        
        .empty-box { text-align:center; padding:50px; color:var(--text-secondary); }

        .theme-toggle {
            cursor: pointer; padding: 8px; border-radius: 50%;
            background: var(--slot-bg); border: 1px solid var(--border-color);
            color: var(--text-color); display: flex; align-items: center;
            justify-content: center; width: 35px; height: 35px; margin-right: 15px;
        }
        .theme-toggle img { width: 20px; height: 20px; object-fit: contain; }

        @media (max-width: 900px) { .main-content-wrapper { margin-left: 0; width: 100%; } }
    </style>
</head>
<body>
    <nav class="main-menu">
        <ul>
            <?php foreach ($menu_items as $key => $item): ?>
                <?php 
                    $isActive = ($key == 'grading' || ($key == 'grading' && in_array($current_page, array_keys($item['sub_items'] ?? []))));
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
                <li class="menu-item <?php echo $hasActiveChild ? 'open' : ''; ?>">
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
            <div class="welcome-text"><h1>Final Results</h1><p>Academic Session 2025/2026</p></div>
            <div class="logo-section"><img src="image/ladybug.png" alt="Logo" class="logo-img"><span class="system-title">FYP Portal</span></div>
            <div class="user-section">
                <button class="theme-toggle" onclick="toggleDarkMode()" title="Toggle Dark Mode">
                    <img id="theme-icon" src="image/moon-solid-full.svg" alt="Toggle Theme">
                </button>
                <span class="user-badge">Supervisor</span>
                <?php if(!empty($user_avatar) && $user_avatar != 'image/user.png'): ?>
                    <img src="<?php echo htmlspecialchars($user_avatar); ?>" class="user-avatar" alt="User Avatar">
                <?php else: ?>
                    <div class="user-avatar-placeholder"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="result-container">
            <div class="result-header"><h2>Final Examination Result Slip</h2></div>

            <?php if (count($student_results) > 0): ?>
                <table class="result-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Group</th>
                            <th>Weightage Progress</th>
                            <th>Status</th>
                            <th style="text-align:center;">Final Grade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($student_results as $res): 
                            $gradeClass = 'grade-' . str_replace(['+', '-'], ['-plus', '-minus'], $res['final_grade']);
                            if ($res['final_grade'] == 'N/A') $gradeClass = 'grade-NA';
                        ?>
                            <tr>
                                <td>
                                    <div style="font-weight:600;"><?php echo htmlspecialchars($res['name']); ?></div>
                                    <div style="font-size:12px; color:var(--text-secondary);"><?php echo $res['id']; ?></div>
                                </td>
                                <td>
                                    <?php if (!empty($res['group'])): ?>
                                        <span style="color:var(--primary-color); font-weight:500;"><i class="fa fa-users"></i> <?php echo htmlspecialchars($res['group']); ?></span>
                                    <?php else: ?>
                                        <span style="color:var(--text-secondary);">Individual</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-size:12px; font-weight:600; color:var(--text-secondary);"><?php echo $res['total_weight']; ?>% Collected</div>
                                    <div class="weight-bar">
                                        <div class="weight-fill" style="width: <?php echo min($res['total_weight'], 100); ?>%; background: <?php echo ($res['total_weight']==100) ? '#28a745' : '#d93025'; ?>;"></div>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-pill <?php echo $res['status_class']; ?>">
                                        <?php echo $res['status_text']; ?>
                                    </span>
                                </td>
                                <td style="text-align:center;">
                                    <div class="grade-badge <?php echo $gradeClass; ?>">
                                        <?php echo $res['final_grade']; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-box"><i class="fa fa-search" style="font-size:48px; opacity:0.3; margin-bottom:15px;"></i><p>No students found under your supervision.</p></div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleSubmenu(element) {
            const menuItem = element.parentElement;
            const isOpen = menuItem.classList.contains('open');
            document.querySelectorAll('.menu-item').forEach(item => { if (item !== menuItem) item.classList.remove('open'); });
            if (isOpen) menuItem.classList.remove('open'); else menuItem.classList.add('open');
        }

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