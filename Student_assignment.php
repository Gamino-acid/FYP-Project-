<?php
include("connect.php");

$auth_user_id = $_GET['auth_user_id'] ?? null;
if (!$auth_user_id) { header("location: login.php"); exit; }

$stud_data = [];
$current_stud_id = '';
$user_name = 'Student';
$user_avatar = '';

if (isset($conn)) {
    $sql_user = "SELECT fyp_username FROM `USER` WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_user)) { 
        $stmt->bind_param("i", $auth_user_id); 
        $stmt->execute(); 
        $res = $stmt->get_result(); 
        if ($row = $res->fetch_assoc()) $user_name = $row['fyp_username']; 
        $stmt->close(); 
    }
    
    $sql_stud = "SELECT * FROM STUDENT WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_stud)) { 
        $stmt->bind_param("i", $auth_user_id); 
        $stmt->execute(); 
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

$my_supervisor_id = null;
if (!empty($current_stud_id)) {
    $sql_sv = "SELECT fyp_staffid FROM fyp_registration WHERE fyp_studid = ? ORDER BY fyp_datecreated DESC LIMIT 1"; 
    
    if ($stmt = $conn->prepare($sql_sv)) {
        $stmt->bind_param("s", $current_stud_id);
        $stmt->execute();
        $stmt->bind_result($my_supervisor_id);
        $stmt->fetch();
        $stmt->close();
    }
}

$assignments = [];

if ($my_supervisor_id) {
    $sql = "SELECT a.*, 
            s.fyp_submission_status, s.fyp_marks, s.fyp_submitted_file, s.fyp_submission_date
            FROM assignment a
            LEFT JOIN assignment_submission s ON a.fyp_assignmentid = s.fyp_assignmentid 
                AND s.fyp_studid = ?
            WHERE a.fyp_staffid = ? 
            ORDER BY a.fyp_deadline DESC";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ss", $current_stud_id, $my_supervisor_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            if (empty($row['fyp_submission_status'])) {
                $row['fyp_submission_status'] = 'Not Turned In';
            }
            $assignments[] = $row;
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
    <title>My Assignments</title>
    <link rel="icon" type="image/png" href="image/ladybug.png?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            background: var(--header-bg);
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
            font-size: 13px; color: var(--text-secondary); background: var(--slot-bg);
            padding: 5px 10px; border-radius: 20px;
        }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
        .user-avatar-placeholder {
            width: 40px; height: 40px; border-radius: 50%; background: #0056b3;
            color: white; display: flex; align-items: center; justify-content: center; font-weight: bold;
        }

        .filter-tabs {
            background: var(--card-bg);
            padding: 15px 20px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 8px 20px;
            border: 2px solid var(--border-color);
            background: var(--card-bg);
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-secondary);
        }

        .filter-tab:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .filter-tab.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .assignments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .assignment-card {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            padding: 20px;
            transition: transform 0.2s, box-shadow 0.2s, background 0.3s;
            border-left: 4px solid var(--primary-color);
            cursor: pointer;
        }

        .assignment-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .assignment-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 5px;
        }

        .assignment-type {
            display: inline-block;
            padding: 4px 12px;
            background: var(--slot-bg);
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--text-secondary);
        }

        .assignment-meta {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--text-secondary);
        }

        .meta-item i {
            width: 16px;
            text-align: center;
            color: var(--primary-color);
        }

        .assignment-description {
            font-size: 14px;
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .status-NotTurnedIn { background: #fff3cd; color: #856404; }
        .status-Viewed { background: #d1ecf1; color: #0c5460; }
        .status-TurnedIn { background: #d4edda; color: #155724; }
        .status-LateTurnedIn { background: #fff3cd; color: #856404; }
        .status-Resubmitted { background: #d4edda; color: #155724; }
        .status-Graded { background: #cce5ff; color: #004085; }
        .status-NeedRevision { background: #f8d7da; color: #721c24; }

        .btn-view {
            padding: 8px 20px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-view:hover {
            background: var(--primary-hover);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            transition: background 0.3s;
        }

        .empty-state i {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .empty-state p {
            color: var(--text-secondary);
            font-size: 16px;
        }

        .deadline-warning {
            color: #d93025;
            font-weight: 600;
        }

        .deadline-soon {
            color: #f0ad4e;
            font-weight: 600;
        }

        .theme-toggle {
            cursor: pointer; padding: 8px; border-radius: 50%;
            background: var(--slot-bg); border: 1px solid var(--border-color);
            color: var(--text-color); display: flex; align-items: center;
            justify-content: center; width: 35px; height: 35px; margin-right: 15px;
        }
        .theme-toggle img { width: 20px; height: 20px; object-fit: contain; }

        @media (max-width: 768px) {
            .assignments-grid {
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
                <h1>My Assignments</h1>
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

        <div class="filter-tabs">
            <div class="filter-tab active" data-filter="all">
                <i class="fa fa-list"></i> All Assignments
            </div>
            <div class="filter-tab" data-filter="pending">
                <i class="fa fa-clock"></i> Pending
            </div>
            <div class="filter-tab" data-filter="submitted">
                <i class="fa fa-check-circle"></i> Submitted
            </div>
            <div class="filter-tab" data-filter="graded">
                <i class="fa fa-star"></i> Graded
            </div>
        </div>

        <?php if (count($assignments) > 0): ?>
            <div class="assignments-grid" id="assignmentsGrid">
                <?php foreach ($assignments as $assignment): 
                    $status = $assignment['fyp_submission_status'];
                    $statusClass = 'status-' . str_replace(' ', '', $status);
                    
                    $deadline = strtotime($assignment['fyp_deadline']);
                    $now = time();
                    $daysLeft = ceil(($deadline - $now) / (60 * 60 * 24));
                    
                    $deadlineClass = '';
                    if ($daysLeft < 0 && $status == 'Not Turned In') {
                        $deadlineClass = 'deadline-warning';
                    } elseif ($daysLeft <= 3 && $daysLeft >= 0 && $status == 'Not Turned In') {
                        $deadlineClass = 'deadline-soon';
                    }
                    
                    $filterCategory = 'all';
                    if (in_array($status, ['Not Turned In', 'Viewed', 'Need Revision'])) {
                        $filterCategory = 'pending';
                    } elseif (in_array($status, ['Turned In', 'Late Turned In', 'Resubmitted'])) {
                        $filterCategory = 'submitted';
                    } elseif ($status == 'Graded') {
                        $filterCategory = 'graded';
                    }
                ?>
                    <div class="assignment-card" data-category="<?php echo $filterCategory; ?>" onclick="window.location.href='student_assignment_details.php?id=<?php echo $assignment['fyp_assignmentid']; ?>&auth_user_id=<?php echo $auth_user_id; ?>'">
                        <div class="card-header">
                            <div style="flex:1;">
                                <div class="assignment-title"><?php echo htmlspecialchars($assignment['fyp_title']); ?></div>
                                <span class="assignment-type"><?php echo htmlspecialchars($assignment['fyp_assignment_type']); ?></span>
                            </div>
                        </div>
                        
                        <div class="assignment-meta">
                            <div class="meta-item <?php echo $deadlineClass; ?>">
                                <i class="fa fa-calendar-alt"></i>
                                <span>Due: <?php echo date('M d, Y h:i A', strtotime($assignment['fyp_deadline'])); ?></span>
                            </div>
                            <?php if ($daysLeft >= 0 && $status != 'Graded'): ?>
                                <div class="meta-item">
                                    <i class="fa fa-clock"></i>
                                    <span><?php echo $daysLeft; ?> day<?php echo $daysLeft != 1 ? 's' : ''; ?> left</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="assignment-description">
                            <?php echo htmlspecialchars(substr($assignment['fyp_description'], 0, 120)); ?>...
                        </div>
                        
                        <div class="card-footer">
                            <span class="status-badge <?php echo $statusClass; ?>">
                                <?php 
                                    $icons = [
                                        'Not Turned In' => 'fa-exclamation-circle',
                                        'Viewed' => 'fa-eye',
                                        'Turned In' => 'fa-check-circle',
                                        'Late Turned In' => 'fa-exclamation-triangle',
                                        'Resubmitted' => 'fa-redo',
                                        'Graded' => 'fa-star',
                                        'Need Revision' => 'fa-edit'
                                    ];
                                    $icon = $icons[$status] ?? 'fa-circle';
                                ?>
                                <i class="fa <?php echo $icon; ?>"></i>
                                <?php echo $status; ?>
                            </span>
                            <a href="student_assignment_details.php?id=<?php echo $assignment['fyp_assignmentid']; ?>&auth_user_id=<?php echo $auth_user_id; ?>" class="btn-view" onclick="event.stopPropagation()">
                                View Details <i class="fa fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fa fa-inbox"></i>
                <p>No assignments available at the moment. <?php if(!$my_supervisor_id) echo "(No Supervisor Assigned)"; ?></p>
            </div>
        <?php endif; ?>

    </div>

    <script>
        const filterTabs = document.querySelectorAll('.filter-tab');
        const assignmentCards = document.querySelectorAll('.assignment-card');

        filterTabs.forEach(tab => {
            tab.addEventListener('click', function() {
                filterTabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                const filter = this.getAttribute('data-filter');
                
                assignmentCards.forEach(card => {
                    if (filter === 'all' || card.getAttribute('data-category') === filter) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
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