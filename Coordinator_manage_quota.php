<?php
// ====================================================
// Coordinator_manage_quota.php - Student UI Style
// ====================================================
include("connect.php");

// 1. Basic Verification
$auth_user_id = $_GET['auth_user_id'] ?? null;
// Menu Active State Helper
$current_page = 'manage_quota';

if (!$auth_user_id) { 
    echo "<script>window.location.href='login.php';</script>";
    exit; 
}

// Get Coordinator Info
$user_name = "Coordinator";
$user_avatar = "image/user.png"; 

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

// Initialize SweetAlert variable
$swal_alert = null;

// ====================================================
// 2. Logic (POST)
// ====================================================

// A. Bulk Apply
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['bulk_apply'])) {
    $limit = intval($_POST['quota_limit']);
    $staff_ids = $_POST['selected_staff'] ?? [];

    if (empty($staff_ids)) {
         $swal_alert = [
             'title' => 'Oops...',
             'text' => 'Please select at least one staff member.',
             'icon' => 'warning'
         ];
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
        
        $swal_alert = [
             'title' => 'Success!',
             'text' => "Successfully assigned quota ($limit) to $count staff members.",
             'icon' => 'success'
         ];
    }
}

// B. Individual Update
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
    
    $swal_alert = [
         'title' => 'Updated!',
         'text' => "Quota updated successfully.",
         'icon' => 'success'
     ];
}

// ====================================================
// 3. Get Staff List
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

// 4. Menu Definitions
$menu_items = [
    'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home', 'link' => 'Coordinator_mainpage.php?page=dashboard'],
    'profile'   => ['name' => 'My Profile', 'icon' => 'fa-user', 'link' => 'Coordinator_profile.php'], 
    'management' => ['name' => 'User Management', 'icon' => 'fa-users-cog', 'sub_items' => ['manage_students' => ['name' => 'Student List', 'icon' => 'fa-user-graduate', 'link' => 'Coordinator_manage_users.php?tab=student'], 'manage_supervisors' => ['name' => 'Supervisor List', 'icon' => 'fa-chalkboard-teacher', 'link' => 'Coordinator_manage_users.php?tab=supervisor'], 'manage_quota' => ['name' => 'Supervisor Quota', 'icon' => 'fa-chalkboard-teacher', 'link' => 'Coordinator_manage_quota.php']]],
    'project_mgmt' => ['name' => 'Project Mgmt', 'icon' => 'fa-tasks', 'sub_items' => ['propose_project' => ['name' => 'Propose Project', 'icon' => 'fa-plus-circle', 'link' => 'Coordinator_purpose.php'], 'project_requests' => ['name' => 'Project Requests', 'icon' => 'fa-envelope-open-text', 'link' => 'Coordinator_projectreq.php'], 'project_list' => ['name' => 'All Projects & Groups', 'icon' => 'fa-list-alt', 'link' => 'Coordinator_manage_project.php']]],
    'assessment' => ['name' => 'Assessment', 'icon' => 'fa-clipboard-check', 'sub_items' => ['propose_assignment' => ['name' => 'Create Assignment', 'icon' => 'fa-plus', 'link' => 'Coordinator_assignment_purpose.php'], 'grade_assignment' => ['name' => 'Grade Assignments', 'icon' => 'fa-check-square', 'link' => 'Coordinator_assignment_grade.php']]],
    'announcements' => ['name' => 'Announcements', 'icon' => 'fa-bullhorn', 'sub_items' => ['post_announcement' => ['name' => 'Post New', 'icon' => 'fa-pen', 'link' => 'Coordinator_announcement.php'], 'view_announcements' => ['name' => 'View History', 'icon' => 'fa-history', 'link' => 'Coordinator_announcement_view.php']]],
    'schedule' => ['name' => 'My Schedule', 'icon' => 'fa-calendar-alt', 'link' => 'Coordinator_meeting.php'], 
    'allocation' => ['name' => 'Moderator Allocation', 'icon' => 'fa-people-arrows', 'link' => 'Coordinator_allocation.php'],
    'reports' => ['name' => 'System Reports', 'icon' => 'fa-chart-bar', 'link' => 'Coordinator_history.php'],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Quota - Coordinator</title>
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

        /* Sidebar - Student Style */
        .main-menu { background: var(--sidebar-bg); border-right: 1px solid rgba(255,255,255,0.1); position: fixed; top: 0; bottom: 0; height: 100%; left: 0; width: 60px; overflow-y: auto; overflow-x: hidden; transition: width 0.3s ease; z-index: 1000; box-shadow: 2px 0 5px rgba(0,0,0,0.1); }
        .main-menu:hover, nav.main-menu.expanded { width: 260px; overflow: visible; }
        .main-menu ul { margin: 7px 0; padding: 0; list-style: none; }
        .main-menu li { position: relative; display: block; width: 260px; }
        .main-menu li > a { display: flex; align-items: center; padding: 15px 25px; color: var(--sidebar-text); text-decoration: none; font-size: 14px; transition: all 0.2s; border-left: 4px solid transparent; width: 100%; padding: 0; display: table; border-collapse: collapse; }
        
        .main-menu .nav-icon { position: relative; display: table-cell; width: 60px; height: 46px; text-align: center; vertical-align: middle; font-size: 18px; }
        .main-menu .nav-text { position: relative; display: table-cell; vertical-align: middle; width: 190px; padding-left: 10px; white-space: nowrap; }
        .main-menu li:hover > a, nav.main-menu li.active > a { color: #fff; background-color: var(--sidebar-hover); border-left: 4px solid #fff; }
        .main-menu > ul.logout { position: absolute; left: 0; bottom: 0; width: 100%; }

        /* Submenu */
        .dropdown-arrow { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); transition: transform 0.3s; font-size: 12px; }
        .menu-item.open .dropdown-arrow { transform: translateY(-50%) rotate(180deg); }
        .submenu { background-color: rgba(0,0,0,0.2); max-height: 0; overflow: hidden; transition: max-height 0.3s ease-out; margin: 0; padding: 0; list-style: none; }
        .menu-item.open .submenu { max-height: 500px; transition: max-height 0.5s ease-in; }
        .submenu li > a { padding-left: 0; }
        .submenu .nav-text { padding-left: 20px; font-size: 13px; }

        /* Main Content */
        .main-content-wrapper { margin-left: 60px; flex: 1; padding: 20px; width: calc(100% - 60px); transition: margin-left 0.3s ease; }
        
        /* Page Header */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; background: white; padding: 20px; border-radius: 12px; box-shadow: var(--card-shadow); }
        .welcome-text h1 { margin: 0; font-size: 24px; color: var(--primary-color); font-weight: 600; }
        .welcome-text p { margin: 5px 0 0; color: #666; font-size: 14px; }
        .logo-section { display: flex; align-items: center; gap: 12px; }
        .logo-img { height: 40px; width: auto; background: white; padding: 2px; border-radius: 6px; }
        .system-title { font-size: 20px; font-weight: 600; color: var(--primary-color); letter-spacing: 0.5px; }
        
        .user-section { display: flex; align-items: center; gap: 10px; }
        .user-badge { font-size: 13px; color: #666; background: #f0f0f0; padding: 5px 10px; border-radius: 20px; }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
        .user-avatar-placeholder { width: 40px; height: 40px; border-radius: 50%; background: #0056b3; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; }

        /* Content Cards */
        .content-card { background: #fff; padding: 25px; border-radius: 12px; box-shadow: var(--card-shadow); }

        /* Bulk Action Bar */
        .bulk-bar { background: #fff; padding: 20px; border-radius: 12px; display: flex; align-items: flex-end; gap: 20px; box-shadow: var(--card-shadow); border-left: 5px solid #ffc107; margin-bottom: 20px; }
        .form-control { padding: 10px 15px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; width: 100%; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        .btn-bulk { background: #ffc107; color: #333; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 14px; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-bulk:hover { background: #e0a800; transform: translateY(-1px); }

        /* Table */
        .q-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .q-table th { background: #f8f9fa; color: #555; padding: 15px; text-align: left; font-weight: 600; border-bottom: 2px solid #eee; }
        .q-table td { padding: 15px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; color: #444; font-size: 14px; }
        .q-table tr:hover { background-color: #f9fbfd; }
        
        .role-badge { font-size: 11px; padding: 4px 10px; border-radius: 12px; font-weight: 600; display: inline-block; }
        .role-Supervisor { background: #e3effd; color: #0056b3; }
        .role-Coordinator { background: #e8f5e9; color: #2e7d32; }

        .btn-manage { background: #fff; border: 1px solid var(--primary-color); color: var(--primary-color); padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 500; transition: all 0.2s; }
        .btn-manage:hover { background: var(--primary-color); color: #fff; }

        /* Modal */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; opacity: 0; transition: opacity 0.3s ease; }
        .modal-overlay.show { display: flex; opacity: 1; }
        .modal-box { background: #fff; width: 90%; max-width: 400px; padding: 30px; border-radius: 12px; box-shadow: 0 15px 40px rgba(0,0,0,0.2); transform: scale(0.9); transition: transform 0.3s ease; border-top: 5px solid var(--primary-color); text-align: center; position: relative; }
        .modal-overlay.show .modal-box { transform: scale(1); }
        .close-modal { position: absolute; right: 20px; top: 15px; font-size: 24px; cursor: pointer; color: #999; }
        .btn-save { background: var(--primary-color); color: white; border: none; padding: 12px 25px; border-radius: 6px; cursor: pointer; width: 100%; font-size: 15px; font-weight: 500; margin-top: 15px; transition: background 0.2s; }
        .btn-save:hover { background: var(--primary-hover); }

        @media (max-width: 900px) { .main-content-wrapper { margin-left: 0; width: 100%; } .bulk-bar { flex-direction: column; align-items: stretch; } }
    </style>
</head>
<body>

    <!-- Sidebar Navigation -->
    <nav class="main-menu">
        <ul>
            <?php foreach ($menu_items as $key => $item): ?>
                <?php 
                    $isActive = ($key == $current_page);
                    // Also check if any child is active
                    $childActive = false;
                    if (isset($item['sub_items'])) {
                        foreach ($item['sub_items'] as $sub_key => $sub) {
                             if($sub_key == $current_page) $childActive = true;
                        }
                    }
                    
                    $linkUrl = isset($item['link']) ? $item['link'] : "#";
                    if ($linkUrl !== "#" && strpos($linkUrl, '.php') !== false) {
                         $separator = (strpos($linkUrl, '?') !== false) ? '&' : '?';
                         $linkUrl .= $separator . "auth_user_id=" . urlencode($auth_user_id);
                    }
                    
                    $hasSubmenu = isset($item['sub_items']);
                ?>
                <li class="menu-item <?php echo ($isActive || $childActive) ? 'open active' : ''; ?>">
                    <a href="<?php echo $hasSubmenu ? 'javascript:void(0)' : $linkUrl; ?>" 
                       class="<?php echo $activeClass; ?>"
                       <?php if ($hasSubmenu): ?>onclick="toggleSubmenu(this)"<?php endif; ?>>
                        <i class="fa <?php echo $item['icon']; ?> nav-icon"></i>
                        <span class="nav-text"><?php echo $item['name']; ?></span>
                        <?php if ($hasSubmenu): ?>
                            <i class="fa fa-chevron-down dropdown-arrow"></i>
                        <?php endif; ?>
                    </a>
                    
                    <?php if ($hasSubmenu): ?>
                        <ul class="submenu">
                            <?php foreach ($item['sub_items'] as $sub_key => $sub_item): 
                                $subLinkUrl = isset($sub_item['link']) ? $sub_item['link'] : "#";
                                if ($subLinkUrl !== "#" && strpos($subLinkUrl, '.php') !== false) {
                                    $separator = (strpos($subLinkUrl, '?') !== false) ? '&' : '?';
                                    $subLinkUrl .= $separator . "auth_user_id=" . urlencode($auth_user_id);
                                }
                            ?>
                                <li>
                                    <a href="<?php echo $subLinkUrl; ?>" class="<?php echo ($sub_key == $current_page) ? 'active' : ''; ?>">
                                        <i class="fa <?php echo $sub_item['icon']; ?> nav-icon"></i>
                                        <span class="nav-text"><?php echo $sub_item['name']; ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
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
        <div class="page-header">
            <div class="welcome-text">
                <h1>Manage Quota</h1>
                <p>Set student supervision limits for supervisors.</p>
            </div>
            
            <div class="logo-section">
                <img src="image/ladybug.png?v=<?php echo time(); ?>" alt="Logo" class="logo-img">
                <span class="system-title">FYP Portal</span>
            </div>

            <div class="user-section">
                <span class="user-badge">Coordinator</span>
                <?php if(!empty($user_avatar) && $user_avatar !== 'image/user.png'): ?>
                    <img src="<?php echo htmlspecialchars($user_avatar); ?>" class="user-avatar" alt="Avatar">
                <?php else: ?>
                    <div class="user-avatar-placeholder">
                        <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                    </div>
                <?php endif; ?>
            </div>
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

    </div>

    <!-- Modal -->
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
                <input type="hidden" name="update_single" value="1">
                
                <div style="margin:25px 0; text-align:left;">
                    <label style="display:block; margin-bottom:8px; font-weight:500; color:#555; font-size:13px;">New Quota Limit</label>
                    <input type="number" name="manage_quota_val" id="manage_quota_val" class="form-control" style="text-align:center; font-size:18px; font-weight:600; letter-spacing:1px;" min="0" required>
                </div>
                
                <button type="submit" class="btn-save">
                    <i class="fa fa-check"></i> Confirm Update
                </button>
            </form>
        </div>
    </div>

    <script>
        function toggleSubmenu(element) {
            element.parentElement.classList.toggle('open');
        }

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

        // Show SweetAlert if set by PHP
        <?php if ($swal_alert): ?>
            Swal.fire({
                title: "<?php echo $swal_alert['title']; ?>",
                text: "<?php echo $swal_alert['text']; ?>",
                icon: "<?php echo $swal_alert['icon']; ?>",
                confirmButtonColor: '#0056b3'
            });
        <?php endif; ?>
    </script>
</body>
</html>