<?php
// ====================================================
// Coordinator_data_io.php - 数据导入导出
// ====================================================
include("connect.php");
$auth_user_id = $_GET['auth_user_id'] ?? null;
$current_page = 'data_io'; 
if (!$auth_user_id) { header("location: login.php"); exit; }

// 模拟处理上传
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['import_students'])) {
        // 实际逻辑需要解析 Excel/CSV
        echo "<script>alert('Student list imported successfully! (Simulation)');</script>";
    }
}
// --- 菜单 (与 History 相同) ---
$menu_items = [
    'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home', 'link' => 'Coordinator_mainpage.php?page=dashboard'],
    'profile'   => ['name' => 'Profile', 'icon' => 'fa-user', 'link' => 'Coordinator_profile.php'], 
    'project_mgmt' => ['name' => 'Project Mgmt', 'icon' => 'fa-tasks', 'sub_items' => ['propose_project' => ['name' => 'Propose Project', 'icon' => 'fa-plus-circle', 'link' => 'Coordinator_purpose.php'], 'project_requests' => ['name' => 'Project Requests', 'icon' => 'fa-envelope-open-text', 'link' => 'Coordinator_projectreq.php'], 'allocation' => ['name' => 'Auto Allocation', 'icon' => 'fa-bullseye', 'link' => 'Coordinator_mainpage.php?page=allocation']]],
    'assessment' => ['name' => 'Assessment', 'icon' => 'fa-clipboard-check', 'sub_items' => ['propose_assignment' => ['name' => 'Create Assignment', 'icon' => 'fa-plus', 'link' => 'Coordinator_assignment_purpose.php'], 'grade_assignment' => ['name' => 'Grade Assignments', 'icon' => 'fa-check-square', 'link' => 'Coordinator_assignment_grade.php']]],
    'announcements' => ['name' => 'Announcements', 'icon' => 'fa-bullhorn', 'sub_items' => ['post_announcement' => ['name' => 'Post New', 'icon' => 'fa-pen', 'link' => 'Coordinator_announcement.php']]],
    'schedule' => ['name' => 'My Schedule', 'icon' => 'fa-calendar-alt', 'link' => 'Coordinator_meeting.php'], 
    'data_io' => ['name' => 'Data Management', 'icon' => 'fa-database', 'link' => 'Coordinator_mainpage.php?page=data_io'],
    'reports' => ['name' => 'System Reports', 'icon' => 'fa-chart-bar', 'link' => 'Coordinator_history.php'], 
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data I/O - Coordinator</title>
    <link rel="icon" type="image/png" href="image/user.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* 复用样式 */
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap');
        :root { --primary-color: #0056b3; --primary-hover: #004494; --secondary-color: #f4f4f9; --text-color: #333; --border-color: #e0e0e0; --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); --sidebar-width: 260px; }
        body { font-family: 'Poppins', sans-serif; margin: 0; background: #eef2f7; color: var(--text-color); min-height: 100vh; display: flex; flex-direction: column; }
        .topbar { display: flex; justify-content: space-between; align-items: center; padding: 15px 40px; background-color: #fff; position: sticky; top: 0; z-index:100; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .logo { font-size: 22px; font-weight: 600; color: var(--primary-color); display: flex; align-items: center; gap: 10px; }
        .topbar-right { display: flex; align-items: center; gap: 20px; }
        .logout-btn { color: #d93025; text-decoration: none; font-weight: 500; }
        .layout-container { display: flex; flex: 1; max-width: 1400px; margin: 0 auto; width: 100%; padding: 20px; gap: 20px; }
        .sidebar { width: var(--sidebar-width); background: #fff; border-radius: 12px; box-shadow: var(--card-shadow); padding: 20px 0; min-height: calc(100vh - 120px); }
        .menu-list { list-style: none; padding: 0; margin: 0; }
        .menu-link { display: flex; align-items: center; padding: 12px 25px; text-decoration: none; color: #555; transition: all 0.3s; border-left: 4px solid transparent; }
        .menu-link:hover, .menu-link.active { background-color: #e3effd; color: var(--primary-color); border-left-color: var(--primary-color); }
        .menu-icon { width: 24px; margin-right: 10px; text-align: center; }
        .submenu { list-style: none; padding: 0; margin: 0; background-color: #fafafa; display: block; }
        .submenu .menu-link { padding-left: 58px; font-size: 14px; padding-top: 10px; padding-bottom: 10px; }
        .main-content { flex: 1; display: flex; flex-direction: column; gap: 20px; }
        
        .io-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .io-card { background: #fff; padding: 30px; border-radius: 12px; box-shadow: var(--card-shadow); text-align: center; }
        .io-icon { font-size: 40px; color: var(--primary-color); margin-bottom: 15px; }
        .file-drop { border: 2px dashed #ddd; padding: 20px; border-radius: 8px; margin: 15px 0; background: #fafafa; }
        .btn-io { background: var(--primary-color); color: white; padding: 10px 25px; border: none; border-radius: 6px; cursor: pointer; width: 100%; }
        .btn-exp { background: #28a745; }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="logo"><img src="image/ladybug.png" alt="Logo" style="width: 32px;"> FYP System</div>
        <div class="topbar-right">
            <div>Coordinator Panel</div>
            <a href="login.php" class="logout-btn"><i class="fa fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>
    <div class="layout-container">
        <aside class="sidebar">
            <ul class="menu-list">
                <?php foreach ($menu_items as $key => $item): 
                    $isActive = ($key == 'data_io'); 
                    $linkUrl = (isset($item['link']) && $item['link'] !== "#") ? $item['link'] . (strpos($item['link'], '?') !== false ? '&' : '?') . "auth_user_id=" . urlencode($auth_user_id) : "#";
                ?>
                <li class="menu-item">
                    <a href="<?php echo $linkUrl; ?>" class="menu-link <?php echo $isActive ? 'active' : ''; ?>">
                        <span class="menu-icon"><i class="fa <?php echo $item['icon']; ?>"></i></span> <?php echo $item['name']; ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </aside>
        <main class="main-content">
            <h2 style="margin-top:0;">Data Import / Export</h2>
            <div class="io-grid">
                <div class="io-card">
                    <i class="fa fa-user-graduate io-icon"></i>
                    <h3>Import Students</h3>
                    <p>Upload Excel/CSV file to register new students in bulk.</p>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="file-drop">
                            <input type="file" name="student_file" accept=".csv, .xlsx">
                        </div>
                        <button type="submit" name="import_students" class="btn-io">Upload & Process</button>
                    </form>
                </div>
                
                <div class="io-card">
                    <i class="fa fa-chalkboard-teacher io-icon"></i>
                    <h3>Export Supervisors</h3>
                    <p>Download the current list of supervisors and their quotas.</p>
                    <div class="file-drop" style="border-color: transparent;">
                        <i class="fa fa-file-csv" style="font-size: 30px; color: #28a745;"></i>
                    </div>
                    <button class="btn-io btn-exp">Download CSV</button>
                </div>
            </div>
        </main>
    </div>
</body>
</html>