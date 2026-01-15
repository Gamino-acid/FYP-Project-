<?php
// ====================================================
// Coordinator_allocation.php - 混合分配系统 (Supervisor + Coordinator)
// ====================================================
include("connect.php");

$auth_user_id = $_GET['auth_user_id'] ?? null;
$current_page = 'allocation'; 

if (!$auth_user_id) { header("location: login.php"); exit; }

// 1. 获取当前 Coordinator 信息
$user_name = "Coordinator"; 
$user_avatar = "image/user.png"; 

if (isset($conn)) {
    $stmt = $conn->prepare("SELECT fyp_name, fyp_profileimg FROM coordinator WHERE fyp_userid = ?");
    $stmt->bind_param("i", $auth_user_id); $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        if(!empty($row['fyp_name'])) $user_name = $row['fyp_name'];
        if(!empty($row['fyp_profileimg'])) $user_avatar = $row['fyp_profileimg'];
    }
    $stmt->close();
}

// 2. 获取所有可用 Moderator (Supervisor + Coordinator)
// 逻辑：Supervisor 必须是 fyp_ismoderator=1，Coordinator 默认都可以当 Moderator
$moderators = [];
$sql_mods = "SELECT fyp_staffid, fyp_name, 'Supervisor' as role FROM supervisor WHERE fyp_ismoderator = 1 AND fyp_staffid IS NOT NULL
             UNION
             SELECT fyp_staffid, fyp_name, 'Coordinator' as role FROM coordinator WHERE fyp_staffid IS NOT NULL";

$res_m = $conn->query($sql_mods);
if ($res_m) {
    while($m = $res_m->fetch_assoc()) {
        // 使用 Staff ID 作为键，防止重复
        $moderators[$m['fyp_staffid']] = $m;
    }
}

// ====================================================
// 3. 处理 POST 请求 (保存 / 自动分配)
// ====================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // --- A. 手动保存/更新 ---
    if (isset($_POST['save_allocation'])) {
        $proj_id = $_POST['project_id'];
        $tgt_type = $_POST['target_type'];
        $tgt_id = $_POST['target_id'];
        $mod_id = $_POST['moderator_id'];
        $venue = $_POST['venue'];
        $datetime = $_POST['presentation_date'];
        
        $sql = "INSERT INTO schedule_presentation (project_id, target_type, target_id, moderator_id, venue, presentation_date) 
                VALUES (?, ?, ?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE moderator_id = VALUES(moderator_id), venue = VALUES(venue), presentation_date = VALUES(presentation_date)";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("isssss", $proj_id, $tgt_type, $tgt_id, $mod_id, $venue, $datetime);
            $stmt->execute();
            echo "<script>alert('Schedule updated successfully!'); window.location.href='Coordinator_allocation.php?auth_user_id=$auth_user_id';</script>";
        }
    }

    // --- B. 自动分配算法 (混合模式) ---
    if (isset($_POST['auto_allocate'])) {
        if (count($moderators) > 0) {
            // 获取所有未分配的项目 (Supervisor 和 Coordinator 的项目都在内)
            $sql_pend = "SELECT p.fyp_projectid, p.fyp_projecttype, 
                                r.fyp_staffid as supervisor_id,
                                CASE 
                                    WHEN p.fyp_projecttype = 'Group' THEN (SELECT group_id FROM student_group WHERE leader_id = r.fyp_studid LIMIT 1)
                                    ELSE r.fyp_studid 
                                END as target_id
                         FROM fyp_registration r
                         JOIN project p ON r.fyp_projectid = p.fyp_projectid
                         LEFT JOIN schedule_presentation ps ON (
                             (p.fyp_projecttype = 'Group' AND ps.target_id = (SELECT group_id FROM student_group WHERE leader_id = r.fyp_studid LIMIT 1))
                             OR 
                             (p.fyp_projecttype = 'Individual' AND ps.target_id = r.fyp_studid)
                         )
                         WHERE (ps.moderator_id IS NULL OR ps.moderator_id = '') 
                         AND (r.fyp_archive_status = 'Active' OR r.fyp_archive_status IS NULL)
                         GROUP BY target_id";

            $res_p = $conn->query($sql_pend);
            $count_assigned = 0;
            
            // 将 Moderator 转换为索引数组以便轮询
            $mod_list = array_keys($moderators);
            $mod_count = count($mod_list);
            $mod_index = 0;

            while ($proj = $res_p->fetch_assoc()) {
                if (empty($proj['target_id'])) continue;

                $assigned_mod = null;
                $attempts = 0;

                // 轮询寻找合适的 Moderator (不是该项目的导师)
                while ($attempts < $mod_count) {
                    $candidate_id = $mod_list[$mod_index % $mod_count];
                    $mod_index++;
                    $attempts++;

                    if ($candidate_id != $proj['supervisor_id']) {
                        $assigned_mod = $candidate_id;
                        break;
                    }
                }

                if ($assigned_mod) {
                    $ins_sql = "INSERT INTO schedule_presentation (project_id, target_type, target_id, moderator_id) 
                                VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE moderator_id = VALUES(moderator_id)";
                    $stmt = $conn->prepare($ins_sql);
                    $stmt->bind_param("isss", $proj['fyp_projectid'], $proj['fyp_projecttype'], $proj['target_id'], $assigned_mod);
                    $stmt->execute();
                    $count_assigned++;
                }
            }
            echo "<script>alert('Auto allocation complete! Assigned $count_assigned projects.'); window.location.href='Coordinator_allocation.php?auth_user_id=$auth_user_id';</script>";
        } else {
            echo "<script>alert('Error: No active Moderators found.');</script>";
        }
    }
}

// ====================================================
// 4. 获取显示列表 (复合查询)
// ====================================================
$allocations = [];

// 关键 SQL：同时连接 Supervisor 和 Coordinator 表，用 COALESCE 获取导师名字
$sql_view = "SELECT 
                p.fyp_projecttitle, 
                p.fyp_projecttype,
                p.fyp_projectid,
                r.fyp_studid,
                s.fyp_studname,
                
                -- 【核心】复合获取导师名字
                COALESCE(sup.fyp_name, coor.fyp_name, r.fyp_staffid) as supervisor_name,
                r.fyp_staffid as supervisor_staff_id,
                
                sched.moderator_id,
                sched.venue,
                sched.presentation_date,
                
                -- 【核心】复合获取 Moderator 名字
                COALESCE(mod_sup.fyp_name, mod_coor.fyp_name, sched.moderator_id) as moderator_name
                
             FROM fyp_registration r
             JOIN project p ON r.fyp_projectid = p.fyp_projectid
             JOIN student s ON r.fyp_studid = s.fyp_studid
             
             -- 连接导师信息 (可能是 Supervisor 或 Coordinator)
             LEFT JOIN supervisor sup ON r.fyp_staffid = sup.fyp_staffid
             LEFT JOIN coordinator coor ON r.fyp_staffid = coor.fyp_staffid
             
             LEFT JOIN student_group sg ON (p.fyp_projecttype = 'Group' AND sg.leader_id = r.fyp_studid)
             
             LEFT JOIN schedule_presentation sched ON (
                (p.fyp_projecttype = 'Group' AND sched.target_id = sg.group_id) OR
                (p.fyp_projecttype = 'Individual' AND sched.target_id = r.fyp_studid)
             )
             
             -- 连接 Moderator 信息 (可能是 Supervisor 或 Coordinator)
             LEFT JOIN supervisor mod_sup ON sched.moderator_id = mod_sup.fyp_staffid
             LEFT JOIN coordinator mod_coor ON sched.moderator_id = mod_coor.fyp_staffid
             
             WHERE 
                (p.fyp_projecttype = 'Individual') 
                OR 
                (p.fyp_projecttype = 'Group' AND sg.group_id IS NOT NULL)
             ORDER BY p.fyp_projecttitle ASC";

$res = $conn->query($sql_view);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        // 构建显示逻辑
        if ($row['fyp_projecttype'] == 'Group') {
             $g_res = $conn->query("SELECT group_id, group_name FROM student_group WHERE leader_id = '{$row['fyp_studid']}'");
             if ($g_r = $g_res->fetch_assoc()) {
                 $row['target_id'] = $g_r['group_id'];
                 $row['display_name'] = "Group: " . $g_r['group_name'];
             }
        } else {
            $row['target_id'] = $row['fyp_studid'];
            $row['display_name'] = "Student: " . $row['fyp_studname'];
        }
        $allocations[] = $row;
    }
}

// 菜单定义 (Standard Coordinator Menu)
$menu_items = [
    'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home', 'link' => 'Coordinator_mainpage.php?page=dashboard'],
    'profile'   => ['name' => 'My Profile', 'icon' => 'fa-user', 'link' => 'Coordinator_profile.php'], 
    
     'management' => [
        'name' => 'User Management',
        'icon' => 'fa-users-cog',
        'sub_items' => [
            // 两个链接都指向同一个管理页面，通过 tab 参数区分默认显示
            'manage_students' => ['name' => 'Student List', 'icon' => 'fa-user-graduate', 'link' => 'Coordinator_manage_users.php?tab=student'],
            'manage_supervisors' => ['name' => 'Supervisor List', 'icon' => 'fa-chalkboard-teacher', 'link' => 'Coordinator_manage_users.php?tab=supervisor'],
            'manage_quota' => ['name' => 'Supervisor Quota', 'icon' => 'fa-chalkboard-teacher', 'link' => 'Coordinator_manage_quota.php'],
        ]
    ],
    
    'project_mgmt' => [
        'name' => 'Project Mgmt', 
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
    'allocation' => ['name' => 'Moderator & Schedule Management', 'icon' => 'fa-database', 'link' => 'Coordinator_allocation.php'],
    'reports' => ['name' => 'System Reports', 'icon' => 'fa-chart-bar', 'link' => 'Coordinator_history.php'],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Presentation Allocation</title>
    <link rel="icon" type="image/png" href="<?php echo $user_avatar; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* CSS 样式保持 Coordinator 风格 */
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap');
        :root { --primary-color: #0056b3; --secondary-color: #f4f4f9; --text-color: #333; --border-color: #e0e0e0; }
        body { font-family: 'Poppins', sans-serif; margin: 0; background: #eef2f7; color: var(--text-color); display: flex; flex-direction: column; min-height: 100vh; }
        .topbar { background: #fff; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .layout-container { display: flex; max-width: 1400px; margin: 20px auto; width: 100%; gap: 20px; flex: 1; }
        .sidebar { width: 260px; background: #fff; border-radius: 12px; padding: 20px 0; min-height: 500px; }
        .main-content { flex: 1; display: flex; flex-direction: column; gap: 20px; }
        .menu-list { list-style: none; padding: 0; }
        .menu-link { display: flex; align-items: center; padding: 12px 25px; text-decoration: none; color: #555; font-weight: 500; }
        .menu-link:hover, .menu-link.active { background-color: #e3effd; color: var(--primary-color); }
        .menu-icon { margin-right: 10px; width: 20px; text-align: center; }
        .submenu { display: block; background: #fafafa; } 
        .submenu .menu-link { padding-left: 50px; font-size: 14px; }
        .card { background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
        .btn-auto { background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { text-align: left; padding: 12px; background: #f8f9fa; color: #555; font-size: 13px; font-weight: 600; border-bottom: 2px solid #eee; }
        td { padding: 12px; border-bottom: 1px solid #eee; font-size: 14px; vertical-align: middle; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; }
        .badge-gray { background: #e9ecef; color: #666; }
        .badge-blue { background: #cce5ff; color: #004085; }
        .badge-purple { background: #e0d4fc; color: #59359a; }
        .btn-edit { background: #007bff; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background: #fff; padding: 25px; border-radius: 12px; width: 500px; max-width: 90%; }
        .form-group { margin-bottom: 15px; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="logo" style="font-weight:600; color:#0056b3;">FYP System</div>
        <div class="user-info"><?php echo htmlspecialchars($user_name); ?></div>
    </header>

    <div class="layout-container">
        <aside class="sidebar">
            <ul class="menu-list">
                <?php foreach ($menu_items as $key => $item): ?>
                    <li class="menu-item">
                        <a href="<?php echo isset($item['link'])?$item['link']:'#'; ?>?auth_user_id=<?php echo $auth_user_id; ?>" class="menu-link <?php echo $key==$current_page?'active':''; ?>">
                            <i class="fa <?php echo $item['icon']; ?> menu-icon"></i> <?php echo $item['name']; ?>
                        </a>
                        <?php if(isset($item['sub_items'])): ?>
                        <ul class="submenu">
                            <?php foreach($item['sub_items'] as $sub): ?>
                            <li><a href="<?php echo $sub['link']; ?>?auth_user_id=<?php echo $auth_user_id; ?>" class="menu-link"><?php echo $sub['name']; ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </aside>

        <main class="main-content">
            <div class="card">
                <div class="page-header">
                    <div>
                        <h2 style="margin:0; color:#0056b3;"><i class="fa fa-bullseye"></i> Presentation Allocation</h2>
                        <p style="font-size:13px; color:#666; margin:5px 0 0;">Manage supervisors, coordinators, and assign moderators.</p>
                    </div>
                    <form method="POST" onsubmit="return confirm('Auto Allocate? This will assign moderators to all pending slots.');">
                        <button type="submit" name="auto_allocate" class="btn-auto">
                            <i class="fa fa-magic"></i> Auto Allocate
                        </button>
                    </form>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>Project / Target</th>
                            <th>Supervisor</th>
                            <th>Assigned Moderator</th>
                            <th>Venue & Time</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($allocations) > 0): ?>
                            <?php foreach($allocations as $row): 
                                $hasMod = !empty($row['moderator_name']);
                                $hasTime = !empty($row['presentation_date']);
                            ?>
                            <tr>
                                <td>
                                    <div style="font-weight:600; color:#333;"><?php echo htmlspecialchars($row['fyp_projecttitle']); ?></div>
                                    <div style="font-size:12px; color:#666; margin-top:2px;">
                                        <span class="badge badge-gray"><?php echo $row['fyp_projecttype']; ?></span> 
                                        <?php echo htmlspecialchars($row['display_name']); ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($row['supervisor_name']); ?></td>
                                <td>
                                    <?php if($hasMod): ?>
                                        <span class="badge badge-purple"><i class="fa fa-user-tie"></i> <?php echo htmlspecialchars($row['moderator_name']); ?></span>
                                    <?php else: ?>
                                        <span style="color:#999; font-size:12px; font-style:italic;">- Not Assigned -</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($hasTime): ?>
                                        <div style="font-size:13px;"><i class="fa fa-calendar"></i> <?php echo date('d M Y, h:i A', strtotime($row['presentation_date'])); ?></div>
                                        <div style="font-size:12px; color:#666; margin-top:3px;"><i class="fa fa-map-marker-alt"></i> <?php echo htmlspecialchars($row['venue']); ?></div>
                                    <?php else: ?>
                                        <span style="color:#999; font-size:12px;">- Not Scheduled -</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn-edit" onclick="openModal(
                                        '<?php echo $row['fyp_projectid']; ?>',
                                        '<?php echo $row['fyp_projecttype']; ?>',
                                        '<?php echo $row['target_id']; ?>',
                                        '<?php echo htmlspecialchars($row['display_name']); ?>',
                                        '<?php echo $row['moderator_id']; ?>',
                                        '<?php echo $row['venue']; ?>',
                                        '<?php echo $row['presentation_date'] ? date('Y-m-d\TH:i', strtotime($row['presentation_date'])) : ''; ?>',
                                        '<?php echo $row['supervisor_staff_id']; ?>'
                                    )">
                                        <i class="fa fa-edit"></i> Edit
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align:center; padding:30px; color:#999;">No active projects found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <div id="allocationModal" class="modal">
        <div class="modal-content">
            <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">Edit Schedule</h3>
            <p id="modalTargetName" style="font-weight:600; color:#0056b3; font-size:14px; margin-bottom:20px;"></p>
            
            <form method="POST">
                <input type="hidden" name="project_id" id="mod_project_id">
                <input type="hidden" name="target_type" id="mod_target_type">
                <input type="hidden" name="target_id" id="mod_target_id">
                
                <div class="form-group">
                    <label>Assign Moderator (Including Coordinators)</label>
                    <select name="moderator_id" id="mod_moderator_id" class="form-control" required>
                        <option value="">-- Select Moderator --</option>
                        <?php foreach($moderators as $id => $m): ?>
                            <option value="<?php echo $id; ?>">
                                <?php echo htmlspecialchars($m['fyp_name']); ?> (<?php echo $m['role']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color:#d93025; display:none;" id="conflictMsg"><i class="fa fa-exclamation-triangle"></i> Warning: This is the Supervisor!</small>
                </div>
                
                <div class="form-group">
                    <label>Date & Time</label>
                    <input type="datetime-local" name="presentation_date" id="mod_date" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Venue / Room</label>
                    <input type="text" name="venue" id="mod_venue" class="form-control" placeholder="e.g. Lab 3, Meeting Room A" required>
                </div>
                
                <div class="modal-footer">
                    <button type="submit" name="save_allocation" class="btn-auto" style="background:#0056b3;">Save Changes</button>
                    <button type="button" onclick="document.getElementById('allocationModal').style.display='none'" class="btn-auto" style="background:#6c757d;">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentSupervisorId = '';

        function openModal(pid, type, tid, name, modId, venue, date, svId) {
            document.getElementById('mod_project_id').value = pid;
            document.getElementById('mod_target_type').value = type;
            document.getElementById('mod_target_id').value = tid;
            document.getElementById('modalTargetName').innerText = name;
            
            document.getElementById('mod_moderator_id').value = modId;
            document.getElementById('mod_venue').value = venue;
            document.getElementById('mod_date').value = date;
            
            currentSupervisorId = svId; 
            checkConflict();
            
            document.getElementById('allocationModal').style.display = 'flex';
        }

        document.getElementById('mod_moderator_id').addEventListener('change', checkConflict);

        function checkConflict() {
            const selected = document.getElementById('mod_moderator_id').value;
            const warning = document.getElementById('conflictMsg');
            if (selected && selected === currentSupervisorId) {
                warning.style.display = 'block';
            } else {
                warning.style.display = 'none';
            }
        }
        
        window.onclick = function(e) {
            if (e.target == document.getElementById('allocationModal')) {
                document.getElementById('allocationModal').style.display = 'none';
            }
        }
    </script>
</body>
</html>