<?php
// ====================================================
// Coordinator_manage_quota.php - UI Updated (Layout Set)
// ====================================================
include("connect.php");

// 1. 基础验证
$auth_user_id = $_GET['auth_user_id'] ?? null;
// 手动设置当前页面高亮 (假设该页面属于 Dashboard 或 Management)
$current_page = 'manage_quota'; 

if (!$auth_user_id) { 
    echo "<script>window.location.href='login.php';</script>";
    exit; 
}

// 获取当前 Coordinator 信息用于顶部栏显示
$user_name = 'Coordinator';
$user_avatar = 'image/user.png';
if (isset($conn)) {
    $sql_user = "SELECT fyp_name, fyp_profileimg FROM coordinator WHERE fyp_userid = ?";
    if ($stmt = $conn->prepare($sql_user)) { 
        $stmt->bind_param("i", $auth_user_id); 
        $stmt->execute(); 
        $res=$stmt->get_result(); 
        if($row=$res->fetch_assoc()) {
            if(!empty($row['fyp_name'])) $user_name=$row['fyp_name'];
            if(!empty($row['fyp_profileimg'])) $user_avatar=$row['fyp_profileimg'];
        }
        $stmt->close(); 
    }
}

// ====================================================
// 2. 逻辑处理 (POST) - 保持原有功能不变
// ====================================================

// A. 批量分配 (Bulk Apply)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['bulk_apply'])) {
    $limit = intval($_POST['quota_limit']);
    $staff_ids = $_POST['selected_staff'] ?? [];

    if (empty($staff_ids)) {
        echo "<script>alert('Please select at least one staff member.');</script>";
    } else {
        $count = 0;
        $check = $conn->prepare("SELECT fyp_quotaid FROM quota WHERE fyp_staffid = ?");
        $insert = $conn->prepare("INSERT INTO quota (fyp_staffid, fyp_numofstudent, fyp_datecreated) VALUES (?, ?, NOW())");
        $update = $conn->prepare("UPDATE quota SET fyp_numofstudent = ? WHERE fyp_staffid = ?");

        foreach ($staff_ids as $sid) {
            $check->bind_param("s", $sid);
            $check->execute();
            $res = $check->get_result();
            if ($res->num_rows > 0) {
                $update->bind_param("is", $limit, $sid);
                $update->execute();
            } else {
                $insert->bind_param("si", $sid, $limit);
                $insert->execute();
            }
            $count++;
        }
        $check->close(); $insert->close(); $update->close();
        echo "<script>alert('Successfully assigned quota ($limit) to $count staff members.'); window.location.href='Coordinator_manage_quota.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
    }
}

// B. 单独修改 (Individual Manage)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_single'])) {
    $target_staff = $_POST['manage_staff_id'];
    $new_quota = intval($_POST['manage_quota_val']);
    $check_sql = "SELECT fyp_quotaid FROM quota WHERE fyp_staffid = '$target_staff'";
    $check_res = $conn->query($check_sql);
    if ($check_res->num_rows > 0) {
        $conn->query("UPDATE quota SET fyp_numofstudent = $new_quota WHERE fyp_staffid = '$target_staff'");
    } else {
        $conn->query("INSERT INTO quota (fyp_staffid, fyp_numofstudent, fyp_datecreated) VALUES ('$target_staff', $new_quota, NOW())");
    }
    echo "<script>alert('Quota updated successfully.'); window.location.href='Coordinator_manage_quota.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
}

// ====================================================
// 3. 获取列表
// ====================================================
$staff_list = [];
$sql = "SELECT u.staffid, u.name, u.role, u.email, q.fyp_numofstudent 
        FROM (
            SELECT fyp_staffid as staffid, fyp_name as name, fyp_email as email, 'Supervisor' as role FROM supervisor
            UNION
            SELECT fyp_staffid as staffid, fyp_name as name, fyp_email as email, 'Coordinator' as role FROM coordinator
        ) u
        LEFT JOIN quota q ON u.staffid = q.fyp_staffid
        ORDER BY u.name ASC";
$res = $conn->query($sql);
if ($res) { while($row = $res->fetch_assoc()) { $staff_list[] = $row; } }

// 4. 菜单定义 (与 Profile 页面保持一致)
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
        ]
    ],
    'schedule' => ['name' => 'My Schedule', 'icon' => 'fa-calendar-alt', 'link' => 'Coordinator_meeting.php'], 
    'data_io' => ['name' => 'Data Management', 'icon' => 'fa-database', 'link' => 'Coordinator_data_io.php'],
    'reports' => ['name' => 'System Reports', 'icon' => 'fa-chart-bar', 'link' => 'Coordinator_history.php'],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Quota - Coordinator</title>
    <link rel="icon" type="image/png" href="<?php echo $user_avatar; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap');
        :root { --primary-color: #0056b3; --primary-hover: #004494; --secondary-color: #f4f4f9; --text-color: #333; --border-color: #e0e0e0; --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); --gradient-start: #eef2f7; --gradient-end: #ffffff; --sidebar-width: 260px; }
        body { font-family: 'Poppins', sans-serif; margin: 0; background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end)); color: var(--text-color); min-height: 100vh; display: flex; flex-direction: column; }
        
        /* Layout & Sidebar Styles (Consistent Set) */
        .topbar { display: flex; justify-content: space-between; align-items: center; padding: 15px 40px; background-color: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.05); z-index: 100; position: sticky; top: 0; }
        .logo { font-size: 22px; font-weight: 600; color: var(--primary-color); display: flex; align-items: center; gap: 10px; }
        .topbar-right { display: flex; align-items: center; gap: 20px; }
        .user-name-display { font-weight: 600; font-size: 14px; display: block; }
        .user-role-badge { font-size: 11px; background-color: #e3effd; color: var(--primary-color); padding: 2px 8px; border-radius: 12px; font-weight: 500; }
        .user-avatar-circle { width: 40px; height: 40px; border-radius: 50%; overflow: hidden; border: 2px solid #e3effd; margin-left: 10px; }
        .user-avatar-circle img { width: 100%; height: 100%; object-fit: cover; }
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
        .submenu { list-style: none; padding: 0; margin: 0; background-color: #fafafa; display: block; }
        .submenu .menu-link { padding-left: 58px; font-size: 14px; padding-top: 10px; padding-bottom: 10px; }

        .main-content { flex: 1; display: flex; flex-direction: column; gap: 20px; }
        .welcome-card { background: #fff; padding: 30px; border-radius: 12px; box-shadow: var(--card-shadow); border-left: 5px solid var(--primary-color); }
        .page-title { font-size: 24px; margin: 0 0 10px 0; color: var(--text-color); }

        /* --- QUOTA SPECIFIC STYLES --- */
        
        /* Card Container for Content */
        .content-card { background: #fff; padding: 25px; border-radius: 12px; box-shadow: var(--card-shadow); }

        /* Bulk Action Bar (New Style) */
        .bulk-bar { 
            background: #fff; 
            padding: 20px; 
            border-radius: 12px; 
            display: flex; 
            align-items: flex-end; 
            gap: 20px; 
            box-shadow: var(--card-shadow);
            border-left: 5px solid #ffc107; /* Yellow accent for bulk */
        }
        .form-control { padding: 10px 15px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; width: 100%; box-sizing: border-box; }
        
        .btn-bulk { background: #ffc107; color: #333; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 14px; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-bulk:hover { background: #e0a800; transform: translateY(-1px); }

        /* Table Styling */
        .q-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .q-table th { background: #f8f9fa; color: #555; padding: 15px; text-align: left; font-weight: 600; border-bottom: 2px solid #eee; }
        .q-table td { padding: 15px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; color: #444; }
        .q-table tr:hover { background-color: #f9fbfd; }
        
        .role-badge { font-size: 11px; padding: 4px 10px; border-radius: 12px; font-weight: 600; display: inline-block; }
        .role-Supervisor { background: #e3effd; color: #0056b3; }
        .role-Coordinator { background: #e8f5e9; color: #2e7d32; }

        .btn-manage { background: #fff; border: 1px solid var(--primary-color); color: var(--primary-color); padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 500; transition: all 0.2s; }
        .btn-manage:hover { background: var(--primary-color); color: #fff; }

        /* Modal Styles */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; opacity: 0; transition: opacity 0.3s ease; }
        .modal-overlay.show { display: flex; opacity: 1; }
        
        .modal-box { background: #fff; width: 90%; max-width: 400px; padding: 30px; border-radius: 12px; box-shadow: 0 15px 40px rgba(0,0,0,0.2); transform: scale(0.9); transition: transform 0.3s ease; border-top: 5px solid var(--primary-color); text-align: center; }
        .modal-overlay.show .modal-box { transform: scale(1); }
        
        .close-modal { position: absolute; right: 20px; top: 15px; font-size: 24px; cursor: pointer; color: #999; }
        .close-modal:hover { color: #333; }

        .btn-save { background: var(--primary-color); color: white; border: none; padding: 12px 25px; border-radius: 6px; cursor: pointer; width: 100%; font-size: 15px; font-weight: 500; margin-top: 15px; transition: background 0.2s; }
        .btn-save:hover { background: var(--primary-hover); }

        @media (max-width: 900px) { .layout-container { flex-direction: column; } .sidebar { width: 100%; min-height: auto; } .bulk-bar { flex-direction: column; align-items: stretch; } }
    </style>
    
    <script>
        function toggleAll(source) {
            checkboxes = document.getElementsByName('selected_staff[]');
            for(var i=0, n=checkboxes.length;i<n;i++) checkboxes[i].checked = source.checked;
        }

        function openManageModal(staffId, staffName, currentQuota) {
            document.getElementById('modal_staff_id').value = staffId;
            document.getElementById('display_staff_name').innerText = staffName;
            document.getElementById('manage_quota_val').value = currentQuota;
            
            const modal = document.getElementById('manageModal');
            modal.classList.add('show');
        }

        function closeModal() {
            const modal = document.getElementById('manageModal');
            modal.classList.remove('show');
        }

        window.onclick = function(event) {
            const modal = document.getElementById('manageModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</head>
<body>
    <header class="topbar">
        <div class="logo"><img src="image/ladybug.png" alt="Logo" style="width: 32px; margin-right: 10px;"> FYP System</div>
        <div class="topbar-right">
            <div class="user-profile-summary">
                <span class="user-name-display"><?php echo htmlspecialchars($user_name); ?></span>
                <span class="user-role-badge">Coordinator</span>
            </div>
            <div class="user-avatar-circle"><img src="<?php echo $user_avatar; ?>" alt="User Avatar"></div>
            <a href="login.php" class="logout-btn"><i class="fa fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>

    <div class="layout-container">
        <aside class="sidebar">
            <ul class="menu-list">
                <?php foreach ($menu_items as $key => $item): ?>
                    <?php 
                        $isActive = ($key == $current_page);
                        $linkUrl = isset($item['link']) ? $item['link'] : "#";
                        if (strpos($linkUrl, '.php') !== false) {
                             $separator = (strpos($linkUrl, '?') !== false) ? '&' : '?';
                             $linkUrl .= $separator . "auth_user_id=" . urlencode($auth_user_id);
                        }
                    ?>
                    <li class="menu-item <?php echo $isActive ? 'has-active-child' : ''; ?>">
                        <a href="<?php echo $linkUrl; ?>" class="menu-link <?php echo $isActive ? 'active' : ''; ?>">
                            <span class="menu-icon"><i class="fa <?php echo $item['icon']; ?>"></i></span>
                            <?php echo $item['name']; ?>
                        </a>
                        <?php if (isset($item['sub_items'])): ?>
                            <ul class="submenu">
                                <?php foreach ($item['sub_items'] as $sub_key => $sub_item): 
                                    $subLinkUrl = isset($sub_item['link']) ? $sub_item['link'] : "#";
                                    if (strpos($subLinkUrl, '.php') !== false) {
                                        $separator = (strpos($subLinkUrl, '?') !== false) ? '&' : '?';
                                        $subLinkUrl .= $separator . "auth_user_id=" . urlencode($auth_user_id);
                                    }
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
            <div class="welcome-card">
                <h1 class="page-title">Manage Quota</h1>
                <p style="color: #666; margin: 0;">Set student supervision limits for supervisors.</p>
            </div>

            <form method="POST">
                <div class="bulk-bar">
                    <div style="flex:1;">
                        <label style="font-size:14px; font-weight:600; color:#b08500; display:block; margin-bottom:8px;">
                            <i class="fa fa-layer-group"></i> Bulk Assign (Initial Setup)
                        </label>
                        <input type="number" name="quota_limit" class="form-control" placeholder="Enter Quota Limit (e.g. 5)" min="1" style="max-width: 250px;">
                        <div style="font-size:12px; color:#888; margin-top:5px;">Select staff from the list below to apply.</div>
                    </div>
                    <button type="submit" name="bulk_apply" class="btn-bulk">
                        <i class="fa fa-check-circle"></i> Apply to Selected
                    </button>
                </div>

                <div class="content-card">
                    <table class="q-table">
                        <thead>
                            <tr>
                                <th style="width: 40px; text-align:center;"><input type="checkbox" onclick="toggleAll(this)" style="cursor:pointer; width:16px; height:16px;"></th>
                                <th>Staff Details</th>
                                <th>Role</th>
                                <th>Current Limit</th>
                                <th style="text-align:right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($staff_list) > 0): ?>
                                <?php foreach ($staff_list as $s): ?>
                                    <tr>
                                        <td style="text-align:center;">
                                            <input type="checkbox" name="selected_staff[]" value="<?php echo htmlspecialchars($s['staffid']); ?>" style="cursor:pointer; width:16px; height:16px;">
                                        </td>
                                        <td>
                                            <div style="font-weight:600; color:#333;"><?php echo htmlspecialchars($s['name']); ?></div>
                                            <div style="font-size:12px; color:#888;"><?php echo htmlspecialchars($s['staffid']); ?></div>
                                        </td>
                                        <td>
                                            <span class="role-badge role-<?php echo $s['role']; ?>"><?php echo $s['role']; ?></span>
                                        </td>
                                        <td>
                                            <?php if ($s['fyp_numofstudent'] !== null && $s['fyp_numofstudent'] > 0): ?>
                                                <span style="font-weight:600; color:#0056b3;">
                                                    <?php echo $s['fyp_numofstudent']; ?>
                                                </span> 
                                                <span style="font-size:12px; color:#999;">Students</span>
                                            <?php else: ?>
                                                <span style="color:#ccc; font-style:italic;">Not Set</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align:right;">
                                            <button type="button" class="btn-manage" 
                                                    onclick="openManageModal('<?php echo $s['staffid']; ?>', '<?php echo addslashes($s['name']); ?>', '<?php echo $s['fyp_numofstudent'] ?? 0; ?>')">
                                                <i class="fa fa-sliders-h"></i> Adjust
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" align="center" style="padding:40px; color:#999;">No staff found in the system.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </main>
    </div>

    <div id="manageModal" class="modal-overlay">
        <div class="modal-box">
            <span class="close-modal" onclick="closeModal()">&times;</span>
            <div style="margin-bottom:15px;">
                <div style="width:60px; height:60px; background:#e3effd; border-radius:50%; display:inline-flex; align-items:center; justify-content:center;">
                    <i class="fa fa-user-edit" style="font-size:24px; color:var(--primary-color);"></i>
                </div>
            </div>
            <h3 style="margin:0 0 5px 0; color:#333;">Edit Quota</h3>
            <p style="color:#666; font-size:14px; margin:0;">Target: <strong id="display_staff_name" style="color:var(--primary-color);"></strong></p>
            
            <form method="POST">
                <input type="hidden" name="manage_staff_id" id="modal_staff_id">
                
                <div style="margin:25px 0; text-align:left;">
                    <label style="display:block; margin-bottom:8px; font-weight:500; color:#555; font-size:13px;">New Quota Limit</label>
                    <input type="number" name="manage_quota_val" id="manage_quota_val" class="form-control" style="text-align:center; font-size:18px; font-weight:600; letter-spacing:1px;" min="0" required>
                </div>
                
                <button type="submit" name="update_single" class="btn-save">
                    <i class="fa fa-check"></i> Confirm Update
                </button>
            </form>
        </div>
    </div>
</body>
</html>