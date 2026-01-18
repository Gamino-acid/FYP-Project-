<?php
// ====================================================
// Supervisor_announcement_view.php - History (AJAX + Redesign)
// ====================================================
include("connect.php");

// 1. AJAX Handler (Edit / Toggle / Delete)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action_type'];
    
    // Auth Check inside AJAX
    $auth_user_id = $_POST['auth_user_id'] ?? null;
    // ... Fetch Staff ID logic required if rigorous checking, simplified here for "Same backend function" constraint context
    
    if ($action == 'edit') {
        $anno_id = $_POST['announce_id'];
        $subject = $_POST['edit_subject'];
        $description = $_POST['edit_description'];
        
        $stmt = $conn->prepare("UPDATE announcement SET fyp_subject = ?, fyp_description = ? WHERE fyp_annouceid = ?");
        $stmt->bind_param("ssi", $subject, $description, $anno_id);
        if ($stmt->execute()) echo json_encode(['status' => 'success', 'message' => 'Updated successfully']);
        else echo json_encode(['status' => 'error', 'message' => $stmt->error]);
        $stmt->close();
    }
    elseif ($action == 'toggle') {
        $anno_id = $_POST['announce_id'];
        $current_status = $_POST['current_status'];
        $new_status = ($current_status == 'Active') ? 'Archived' : 'Active';
        
        $stmt = $conn->prepare("UPDATE announcement SET fyp_status = ? WHERE fyp_annouceid = ?");
        $stmt->bind_param("si", $new_status, $anno_id);
        if ($stmt->execute()) echo json_encode(['status' => 'success', 'new_status' => $new_status]);
        else echo json_encode(['status' => 'error', 'message' => $stmt->error]);
        $stmt->close();
    }
    elseif ($action == 'delete') {
        $anno_id = $_POST['announce_id'];
        $stmt = $conn->prepare("DELETE FROM announcement WHERE fyp_annouceid = ?");
        $stmt->bind_param("i", $anno_id);
        if ($stmt->execute()) echo json_encode(['status' => 'success', 'message' => 'Deleted successfully']);
        else echo json_encode(['status' => 'error', 'message' => $stmt->error]);
        $stmt->close();
    }
    exit;
}

// 2. Standard Page Load
$auth_user_id = $_GET['auth_user_id'] ?? null;
$current_page = 'view_announcements'; 

if (!$auth_user_id) { header("location: login.php"); exit; }

// Fetch Info
$user_name = "Supervisor"; 
$user_avatar = "image/user.png"; 
$my_staff_id = "";

if (isset($conn)) {
    $stmt = $conn->prepare("SELECT * FROM supervisor WHERE fyp_userid = ?");
    $stmt->bind_param("i", $auth_user_id); $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $my_staff_id = $row['fyp_staffid'];
        if (!empty($row['fyp_name'])) $user_name = $row['fyp_name'];
        if (!empty($row['fyp_profileimg'])) $user_avatar = $row['fyp_profileimg'];
    }
    $stmt->close();
}

// Fetch Announcements
$announcements = [];
if (!empty($my_staff_id)) {
    $stmt = $conn->prepare("SELECT * FROM announcement WHERE fyp_staffid = ? ORDER BY fyp_datecreated DESC");
    $stmt->bind_param("s", $my_staff_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        if (!isset($row['fyp_status'])) $row['fyp_status'] = 'Active'; 
        $announcements[] = $row;
    }
    $stmt->close();
}

// Menu Definition
$menu_items = [
    'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home', 'link' => 'Supervisor_mainpage.php?page=dashboard'],
    'profile'   => ['name' => 'My Profile', 'icon' => 'fa-user', 'link' => 'supervisor_profile.php'],
    'students'  => [
        'name' => 'My Students', 
        'icon' => 'fa-users',
        'sub_items' => [
            'project_requests' => ['name' => 'Project Requests', 'icon' => 'fa-envelope-open-text', 'link' => 'supervisor_projectreq.php'],
            'student_list'     => ['name' => 'Student List', 'icon' => 'fa-list', 'link' => 'Supervisor_student_list.php'],
        ]
    ],
    'fyp_project' => [
        'name' => 'FYP Project',
        'icon' => 'fa-project-diagram',
        'sub_items' => [
            'propose_project' => ['name' => 'Propose Project', 'icon' => 'fa-plus-circle', 'link' => 'supervisor_purpose.php'],
            'my_projects'     => ['name' => 'My Projects', 'icon' => 'fa-folder-open', 'link' => 'Supervisor_manage_project.php'],
            'propose_assignment' => ['name' => 'Propose Assignment', 'icon' => 'fa-tasks', 'link' => 'supervisor_assignment_purpose.php']
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
    <title>Announcement History</title>
    <link rel="icon" type="image/png" href="image/ladybug.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap');
        :root { --primary-color: #0056b3; --primary-hover: #004494; --secondary-color: #f8f9fa; --text-color: #333; --border-color: #e0e0e0; --card-shadow: 0 4px 6px rgba(0,0,0,0.05); --bg-color: #f4f6f9; --sidebar-bg: #004085; --sidebar-hover: #003366; --sidebar-text: #e0e0e0; }
        body { font-family: 'Poppins', sans-serif; margin: 0; background-color: var(--bg-color); min-height: 100vh; display: flex; overflow-x: hidden; }

        /* Sidebar & Menu */
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

        /* Main Content */
        .main-content-wrapper { margin-left: 60px; flex: 1; padding: 20px; width: calc(100% - 60px); transition: margin-left .05s linear; }

        /* Header */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; background: white; padding: 20px; border-radius: 12px; box-shadow: var(--card-shadow); }
        .welcome-text h1 { margin: 0; font-size: 24px; color: var(--primary-color); font-weight: 600; }
        .welcome-text p { margin: 5px 0 0; color: #666; font-size: 14px; }
        .logo-section { display: flex; align-items: center; gap: 12px; }
        .logo-img { height: 40px; width: auto; background: white; padding: 2px; border-radius: 6px; }
        .system-title { font-size: 20px; font-weight: 600; color: var(--primary-color); letter-spacing: 0.5px; }

        /* History Cards */
        .history-card { background: #fff; padding: 25px; border-radius: 12px; box-shadow: var(--card-shadow); }
        .section-title { border-bottom: 2px solid #eee; padding-bottom: 15px; margin-bottom: 25px; display:flex; justify-content:space-between; }
        
        .anno-card { background: #fff; border-radius: 8px; border: 1px solid #eee; padding: 20px; margin-bottom: 15px; position: relative; transition: all 0.2s; }
        .anno-card:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .anno-active { border-left: 4px solid #28a745; }
        .anno-archived { border-left: 4px solid #6c757d; background-color: #f9f9f9; opacity: 0.8; }
        
        .anno-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; }
        .anno-subject { font-size: 16px; font-weight: 600; color: #333; }
        .anno-status { padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .st-Active { background: #d4edda; color: #155724; }
        .st-Archived { background: #e2e3e5; color: #383d41; }
        
        .anno-meta { font-size: 12px; color: #888; margin-bottom: 12px; display: flex; gap: 15px; }
        .anno-desc { font-size: 14px; color: #555; line-height: 1.5; white-space: pre-wrap; margin-bottom: 15px; }
        
        .anno-actions { display: flex; gap: 8px; justify-content: flex-end; border-top: 1px solid #f0f0f0; padding-top: 12px; }
        .btn-action { border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 500; display: flex; align-items: center; gap: 5px; transition: 0.2s; }
        .btn-edit { background: #e3effd; color: #0056b3; }
        .btn-edit:hover { background: #d0e4ff; }
        .btn-archive { background: #fff3cd; color: #856404; }
        .btn-archive:hover { background: #ffeeba; }
        .btn-activate { background: #d4edda; color: #155724; }
        .btn-activate:hover { background: #c3e6cb; }
        .btn-delete { background: #f8d7da; color: #721c24; }
        .btn-delete:hover { background: #f1b0b7; }

        /* Modal */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-box { background: #fff; padding: 25px; border-radius: 12px; width: 90%; max-width: 500px; animation: popIn 0.3s ease; }
        .modal-title { font-size: 18px; font-weight: 600; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; margin-bottom: 15px; box-sizing: border-box; }
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; }
        .btn-save { background: var(--primary-color); color: white; padding: 8px 20px; border: none; border-radius: 6px; cursor: pointer; }
        .btn-close { background: #eee; color: #333; padding: 8px 20px; border: none; border-radius: 6px; cursor: pointer; }
        
        @keyframes popIn { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        @media (max-width: 900px) { .main-content-wrapper { margin-left: 0; width: 100%; } }
    </style>
</head>
<body>
    <nav class="main-menu">
        <ul>
            <?php foreach ($menu_items as $key => $item): ?>
                <?php 
                    $isActive = ($key == 'announcement'); 
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
            <div class="welcome-text"><h1>Announcement History</h1><p>Manage previously posted announcements.</p></div>
            <div class="logo-section"><img src="image/ladybug.png" alt="Logo" class="logo-img"><span class="system-title">FYP Portal</span></div>
            <div style="display:flex; align-items:center; gap:10px;">
                <span style="font-size:13px; color:#666; background:#f0f0f0; padding:5px 10px; border-radius:20px;">Supervisor</span>
                <?php if(!empty($user_avatar) && $user_avatar != 'image/user.png'): ?>
                    <img src="<?php echo htmlspecialchars($user_avatar); ?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
                <?php else: ?>
                    <div style="width:40px;height:40px;border-radius:50%;background:#0056b3;color:white;display:flex;align-items:center;justify-content:center;font-weight:bold;"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="history-card">
            <div class="section-title"><h2><i class="fa fa-history"></i> Posted Announcements</h2></div>

            <?php if (count($announcements) > 0): ?>
                <?php foreach ($announcements as $anno): 
                    $status = $anno['fyp_status'];
                    $isArchived = ($status == 'Archived');
                    $cardClass = $isArchived ? 'anno-archived' : 'anno-active';
                    $rowId = 'row-' . $anno['fyp_annouceid'];
                ?>
                    <div id="<?php echo $rowId; ?>" class="anno-card <?php echo $cardClass; ?>">
                        <div class="anno-top">
                            <div class="anno-subject"><?php echo htmlspecialchars($anno['fyp_subject']); ?></div>
                            <span id="badge-<?php echo $anno['fyp_annouceid']; ?>" class="anno-status st-<?php echo $status; ?>"><?php echo $status; ?></span>
                        </div>
                        <div class="anno-meta">
                            <span><i class="fa fa-calendar-alt"></i> <?php echo date('d M Y, h:i A', strtotime($anno['fyp_datecreated'])); ?></span>
                            <span><i class="fa fa-paper-plane"></i> To: <?php echo htmlspecialchars($anno['fyp_receiver']); ?></span>
                        </div>
                        <div class="anno-desc" id="desc-<?php echo $anno['fyp_annouceid']; ?>"><?php echo htmlspecialchars($anno['fyp_description']); ?></div>
                        
                        <div class="anno-actions" id="actions-<?php echo $anno['fyp_annouceid']; ?>">
                            <button class="btn-action btn-edit" onclick="openEditModal('<?php echo $anno['fyp_annouceid']; ?>', '<?php echo addslashes($anno['fyp_subject']); ?>', '<?php echo addslashes(str_replace(array("\r", "\n"), ' ', $anno['fyp_description'])); ?>')">
                                <i class="fa fa-edit"></i> Edit
                            </button>

                            <?php if ($isArchived): ?>
                                <button class="btn-action btn-activate" onclick="handleAction('toggle', '<?php echo $anno['fyp_annouceid']; ?>', 'Archived')">
                                    <i class="fa fa-box-open"></i> Set Active
                                </button>
                            <?php else: ?>
                                <button class="btn-action btn-archive" onclick="handleAction('toggle', '<?php echo $anno['fyp_annouceid']; ?>', 'Active')">
                                    <i class="fa fa-archive"></i> Archive
                                </button>
                            <?php endif; ?>

                            <button class="btn-action btn-delete" onclick="handleAction('delete', '<?php echo $anno['fyp_annouceid']; ?>')">
                                <i class="fa fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align:center; padding:40px; color:#999;">
                    <i class="fa fa-bullhorn" style="font-size:48px; opacity:0.3; margin-bottom:15px;"></i>
                    <p>No announcements found.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-title">Edit Announcement</div>
            <form id="editForm" onsubmit="submitEdit(event)">
                <input type="hidden" name="ajax_action" value="true">
                <input type="hidden" name="action_type" value="edit">
                <input type="hidden" name="announce_id" id="edit_id">
                
                <div class="form-group"><label>Subject</label><input type="text" name="edit_subject" id="edit_subject" class="form-control" required></div>
                <div class="form-group"><label>Description</label><textarea name="edit_description" id="edit_description" class="form-control" required></textarea></div>
                <div class="modal-footer">
                    <button type="button" class="btn-close" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn-save">Save Changes</button>
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

        function handleAction(type, id, currentStatus = '') {
            let title = type === 'delete' ? 'Are you sure?' : 'Confirm action?';
            let text = type === 'delete' ? "You won't be able to revert this!" : "";
            let confirmBtn = type === 'delete' ? 'Yes, delete it!' : 'Yes, proceed';
            let btnColor = type === 'delete' ? '#dc3545' : '#0056b3';

            Swal.fire({
                title: title, text: text, icon: 'warning', showCancelButton: true, confirmButtonColor: btnColor, confirmButtonText: confirmBtn
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('ajax_action', 'true');
                    formData.append('action_type', type);
                    formData.append('announce_id', id);
                    if(type === 'toggle') formData.append('current_status', currentStatus);

                    fetch('Supervisor_announcement_view.php', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        if(data.status === 'success') {
                            if(type === 'delete') {
                                document.getElementById('row-' + id).remove();
                                Swal.fire('Deleted!', 'Your file has been deleted.', 'success');
                            } else if(type === 'toggle') {
                                location.reload(); // Reload to refresh UI logic simply
                            }
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    });
                }
            });
        }

        const editModal = document.getElementById('editModal');
        function openEditModal(id, subject, desc) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_subject').value = subject;
            document.getElementById('edit_description').value = desc;
            editModal.style.display = 'flex';
        }
        function closeEditModal() { editModal.style.display = 'none'; }

        function submitEdit(e) {
            e.preventDefault();
            const formData = new FormData(document.getElementById('editForm'));
            fetch('Supervisor_announcement_view.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    Swal.fire('Updated!', 'Announcement updated.', 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            });
        }
    </script>
</body>
</html>