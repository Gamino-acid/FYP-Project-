<?php
// ====================================================
// Supervisor_view_result.php - 查看学生最终等级
// ====================================================
include("connect.php");

// 1. 验证登录
$auth_user_id = $_GET['auth_user_id'] ?? null;
$current_page = 'view_result'; // 菜单高亮 ID

if (!$auth_user_id) { header("location: login.php"); exit; }

// 2. 获取导师信息
$user_name = "Supervisor"; 
$user_avatar = "image/user.png"; 
$sv_id = 0;

if (isset($conn)) {
    // 获取 USER 表名字
    $stmt = $conn->prepare("SELECT fyp_username FROM `USER` WHERE fyp_userid = ?");
    $stmt->bind_param("i", $auth_user_id); $stmt->execute();
    $res = $stmt->get_result(); if ($row = $res->fetch_assoc()) $user_name = $row['fyp_username'];
    $stmt->close();

    // 获取 Supervisor ID
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

// ====================================================
// 3. 等级转换函数 (Grading Scheme)
// ====================================================
function getGradeFromScore($score) {
    // 你可以根据学校的实际标准调整这里
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

// ====================================================
// 4. 获取学生列表并计算成绩
// ====================================================
$student_results = [];

if ($sv_id) {
    // 获取该导师名下所有活跃学生 (包括 Individual 和 Group Leader/Members)
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
        $group_name = $stud['group_name']; // 如果是 Leader
        
        // 如果是 Member，找 Group Name
        if (empty($group_name) && $stud['fyp_group'] == 'Group') {
             $g_res = $conn->query("SELECT sg.group_name FROM group_request gr JOIN student_group sg ON gr.group_id = sg.group_id WHERE gr.invitee_id = '$stud_id' AND gr.request_status='Accepted'");
             if ($gr = $g_res->fetch_assoc()) $group_name = $gr['group_name'];
        }

        // --- 核心计算逻辑 ---
        
        // 1. 找出该学生需要做的所有作业 (Target: ALL, Individual, Group)
        $target_sql_filter = "";
        if ($stud['fyp_group'] == 'Group') {
             // 查找该学生所属的组ID
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
            $m_mark = isset($m['fyp_mod_marks']) ? floatval($m['fyp_mod_marks']) : null; // Moderator Mark
            
            $total_weightage += $w;

            // 计算该作业的得分
            $assignment_final_mark = 0;
            
            if ($m_mark !== null) {
                // 如果 Moderator 打分了：(Sup + Mod) / 2
                $raw_avg = ($s_mark + $m_mark) / 2;
            } else {
                // Moderator 还没打分，或者没有分配 Moderator
                // 此时你可以选择：
                // A. 暂时只用 Supervisor 分数 (如下)
                $raw_avg = $s_mark; 
                // B. 或者标记为分数不全
                // $missing_marks = true;
            }

            // 加权计算: (原始分 / 100) * 权重
            $weighted_score = ($raw_avg / 100) * $w;
            $total_score += $weighted_score;
        }

        // 最终数据打包
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
            'final_grade' => $final_grade, // 只显示 A+, A...
            'status_class' => $status_class,
            'status_text' => $status_text
        ];
    }
}

// 菜单定义 (记得添加 View Result 入口)
$menu_items = [
    'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home', 'link' => 'Supervisor_mainpage.php?page=dashboard'],
    'grading' => [
        'name' => 'Assessment',
        'icon' => 'fa-marker',
        'sub_items' => [
            'grade_assignment' => ['name' => 'Grade Assignments', 'icon' => 'fa-check-square', 'link' => 'Supervisor_assignment_grade.php'],
            'view_result' => ['name' => 'Final Results', 'icon' => 'fa-poll', 'link' => 'Supervisor_view_result.php'], // 新入口
            'grade_mod' => ['name' => 'Moderator Grading', 'icon' => 'fa-gavel', 'link' => 'Moderator_assignment_grade.php'],
        ]
    ],
    // ... 其他菜单保持不变 ...
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Final Results</title>
    <link rel="icon" type="image/png" href="<?php echo $user_avatar; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap');
        :root { --primary-color: #0056b3; --primary-hover: #004494; --secondary-color: #f4f4f9; --text-color: #333; --border-color: #e0e0e0; --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); --gradient-start: #eef2f7; --gradient-end: #ffffff; --sidebar-width: 260px; }
        body { font-family: 'Poppins', sans-serif; margin: 0; background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end)); color: var(--text-color); min-height: 100vh; display: flex; flex-direction: column; }
        
        /* Layout & Sidebar (Copied from previous) */
        .topbar { display: flex; justify-content: space-between; align-items: center; padding: 15px 40px; background-color: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.05); position: sticky; top: 0; z-index:100; }
        .logo { font-size: 22px; font-weight: 600; color: var(--primary-color); display: flex; align-items: center; gap: 10px; }
        .topbar-right { display: flex; align-items: center; gap: 20px; }
        .user-name-display { font-weight: 600; font-size: 14px; display: block; }
        .user-role-badge { font-size: 11px; background-color: #e3effd; color: var(--primary-color); padding: 2px 8px; border-radius: 12px; font-weight: 500; }
        .user-avatar-circle { width: 40px; height: 40px; border-radius: 50%; overflow: hidden; border: 2px solid #e3effd; margin-left: 10px; }
        .user-avatar-circle img { width: 100%; height: 100%; object-fit: cover; }
        .logout-btn { color: #d93025; text-decoration: none; font-size: 14px; font-weight: 500; padding: 8px 15px; border-radius: 6px; }

        .layout-container { display: flex; flex: 1; max-width: 1400px; margin: 0 auto; width: 100%; padding: 20px; box-sizing: border-box; gap: 20px; }
        .sidebar { width: var(--sidebar-width); background: #fff; border-radius: 12px; box-shadow: var(--card-shadow); padding: 20px 0; flex-shrink: 0; min-height: calc(100vh - 120px); }
        .menu-list { list-style: none; padding: 0; margin: 0; }
        .menu-item { margin-bottom: 5px; }
        .menu-link { display: flex; align-items: center; padding: 12px 25px; text-decoration: none; color: #555; font-weight: 500; font-size: 15px; transition: all 0.3s; border-left: 4px solid transparent; }
        .menu-link:hover { background-color: var(--secondary-color); color: var(--primary-color); }
        .menu-link.active { background-color: #e3effd; color: var(--primary-color); border-left-color: var(--primary-color); }
        .menu-icon { width: 24px; margin-right: 10px; text-align: center; }
        .submenu { list-style: none; padding: 0; margin: 0; background-color: #fafafa; display: block; }
        .submenu .menu-link { padding-left: 58px; font-size: 14px; padding-top: 10px; padding-bottom: 10px; }
        .main-content { flex: 1; display: flex; flex-direction: column; gap: 20px; }

        /* Result Table Styles */
        .card { background: #fff; padding: 25px; border-radius: 12px; box-shadow: var(--card-shadow); }
        .result-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .result-table th { text-align: left; padding: 12px 15px; color: #666; font-size: 13px; border-bottom: 2px solid #eee; background: #f9f9f9; }
        .result-table td { padding: 15px; border-bottom: 1px solid #f0f0f0; color: #333; font-size: 14px; vertical-align: middle; }
        
        .grade-badge { 
            display: inline-block; width: 40px; height: 40px; line-height: 40px; 
            text-align: center; border-radius: 50%; font-weight: 700; font-size: 14px;
        }
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
    </style>
</head>
<body>
    <header class="topbar">
        <div class="logo"><img src="image/ladybug.png" alt="Logo" style="width: 32px; margin-right: 10px;"> FYP Grading</div>
        <div class="topbar-right">
            <span class="user-role-badge">Supervisor</span>
            <span style="font-weight:600; margin: 0 10px; font-size:14px;"><?php echo htmlspecialchars($user_name); ?></span>
            <a href="login.php" class="logout-btn"><i class="fa fa-sign-out-alt"></i></a>
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
                        $linkUrl = isset($item['link']) ? $item['link'] . "&auth_user_id=" . urlencode($auth_user_id) : "#";
                    ?>
                    <li class="menu-item <?php echo $hasActiveChild ? 'has-active-child' : ''; ?>">
                        <a href="<?php echo $linkUrl; ?>" class="menu-link <?php echo $isActive ? 'active' : ''; ?>">
                            <span class="menu-icon"><i class="fa <?php echo $item['icon']; ?>"></i></span>
                            <?php echo $item['name']; ?>
                        </a>
                        <?php if (isset($item['sub_items'])): ?>
                            <ul class="submenu">
                                <?php foreach ($item['sub_items'] as $sub_key => $sub_item): 
                                    $subLinkUrl = isset($sub_item['link']) ? $sub_item['link'] . "&auth_user_id=" . urlencode($auth_user_id) : "#";
                                ?>
                                    <li><a href="<?php echo $subLinkUrl; ?>" class="menu-link <?php echo ($sub_key == $current_page) ? 'active' : ''; ?>">
                                        <span class="menu-icon"><i class="fa <?php echo $sub_item['icon']; ?>"></i></span> <?php echo $sub_item['name']; ?>
                                    </a></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </aside>

        <main class="main-content">
            <div class="card">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                    <h2 style="margin:0; font-size:20px; color:var(--primary-color);">Final Results Overview</h2>
                    <div style="font-size:13px; color:#666;">
                        <i class="fa fa-info-circle"></i> Grades are calculated only when Total Weightage is 100%.
                    </div>
                </div>

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
                        <?php if (count($student_results) > 0): ?>
                            <?php foreach ($student_results as $res): 
                                // 生成 CSS 类名 (例如 grade-A-plus)
                                $gradeClass = 'grade-' . str_replace(['+', '-'], ['-plus', '-minus'], $res['final_grade']);
                                if ($res['final_grade'] == 'N/A') $gradeClass = 'grade-NA';
                            ?>
                                <tr>
                                    <td>
                                        <div style="font-weight:600;"><?php echo htmlspecialchars($res['name']); ?></div>
                                        <div style="font-size:12px; color:#888;"><?php echo $res['id']; ?></div>
                                    </td>
                                    <td>
                                        <?php if (!empty($res['group'])): ?>
                                            <span style="color:var(--primary-color); font-weight:500;"><i class="fa fa-users"></i> <?php echo htmlspecialchars($res['group']); ?></span>
                                        <?php else: ?>
                                            <span style="color:#777;">Individual</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-size:12px; font-weight:600; color:#555;"><?php echo $res['total_weight']; ?>% Collected</div>
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
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align:center; padding:30px; color:#999;">No students found under your supervision.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>