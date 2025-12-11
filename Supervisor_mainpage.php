<?php
// ====================================================
// Supervisor_mainpage.php - 导师主页
// ====================================================

include("connect.php");

// 1. 验证用户登录 (接收 Login 传来的 auth_user_id)
$auth_user_id = $_GET['auth_user_id'] ?? null;
$current_page = $_GET['page'] ?? 'dashboard';

// 安全检查：如果没有ID，踢回登录页
if (!$auth_user_id) {
    header("location: login.php");
    exit;
}

// ====================================================
// 2. 数据查询 (核心：获取导师资料)
// ====================================================

$supervisor_data = [];
$user_name = "Supervisor"; // 默认名字
$user_avatar = "image/user.png"; // 默认头像

if (isset($conn)) {
    // 逻辑：通过 USER 表的 ID (fyp_userid) 去找 SUPERVISOR 表里的对应记录
    // 注意：Supervisor 表里有一个 fyp_userid 字段作为外键
    $sql_sv = "SELECT * FROM SUPERVISOR WHERE fyp_userid = ? LIMIT 1";
    
    if ($stmt = $conn->prepare($sql_sv)) {
        $stmt->bind_param("i", $auth_user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($res->num_rows > 0) {
            $supervisor_data = $res->fetch_assoc();
            
            // 1. 获取名字
            if (!empty($supervisor_data['fyp_name'])) {
                $user_name = $supervisor_data['fyp_name'];
            }
            
            // 2. 获取头像 (检查是否有 Base64 图片数据)
            if (!empty($supervisor_data['fyp_profileimg'])) {
                $user_avatar = $supervisor_data['fyp_profileimg'];
            }
        }
        $stmt->close();
    }
}

// ====================================================
// 3. 定义菜单 (Supervisor 专属菜单)
// ====================================================
$menu_items = [
    'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home'],
    'profile'   => ['name' => 'My Profile', 'icon' => 'fa-user'],
    'students'  => ['name' => 'My Students', 'icon' => 'fa-users'],
    'schedule'  => ['name' => 'My Schedule', 'icon' => 'fa-calendar-alt'],
    'requests'  => ['name' => 'Requests', 'icon' => 'fa-envelope-open-text'],
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supervisor Dashboard</title>
    
    <link rel="icon" type="image/png" href="<?php echo $user_avatar; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap');
        :root { --primary-color: #0056b3; --primary-hover: #004494; --secondary-color: #f4f4f9; --text-color: #333; --border-color: #e0e0e0; --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); --gradient-start: #eef2f7; --gradient-end: #ffffff; --sidebar-width: 260px; --student-accent: #007bff; }
        body { font-family: 'Poppins', sans-serif; margin: 0; background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end)); color: var(--text-color); min-height: 100vh; display: flex; flex-direction: column; }
        
        /* Topbar & Sidebar */
        .topbar { display: flex; justify-content: space-between; align-items: center; padding: 15px 40px; background-color: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.05); z-index: 100; position: sticky; top: 0; }
        .logo { font-size: 22px; font-weight: 600; color: var(--primary-color); display: flex; align-items: center; gap: 10px; }
        .topbar-right { display: flex; align-items: center; gap: 20px; }
        .user-name-display { font-weight: 600; font-size: 14px; display: block; }
        .user-role-badge { font-size: 11px; background-color: #e3effd; color: var(--primary-color); padding: 2px 8px; border-radius: 12px; font-weight: 500; }
        .user-avatar-circle { width: 40px; height: 40px; border-radius: 50%; overflow: hidden; border: 2px solid #e3effd; margin-left: 10px; }
        .user-avatar-circle img { width: 100%; height: 100%; object-fit: cover; }
        .logout-btn { color: #d93025; text-decoration: none; font-size: 14px; font-weight: 500; padding: 8px 15px; border-radius: 6px; transition: background 0.2s; }
        .logout-btn:hover { background-color: #fff0f0; }
        
        /* Layout */
        .layout-container { display: flex; flex: 1; max-width: 1400px; margin: 0 auto; width: 100%; padding: 20px; box-sizing: border-box; gap: 20px; }
        .sidebar { width: var(--sidebar-width); background: #fff; border-radius: 12px; box-shadow: var(--card-shadow); padding: 20px 0; flex-shrink: 0; min-height: calc(100vh - 120px); }
        .menu-list { list-style: none; padding: 0; margin: 0; }
        .menu-item { margin-bottom: 5px; }
        .menu-link { display: flex; align-items: center; padding: 12px 25px; text-decoration: none; color: #555; font-weight: 500; font-size: 15px; transition: all 0.3s; border-left: 4px solid transparent; }
        .menu-link:hover { background-color: var(--secondary-color); color: var(--primary-color); }
        .menu-link.active { background-color: #e3effd; color: var(--primary-color); border-left-color: var(--primary-color); }
        .menu-icon { width: 24px; margin-right: 10px; text-align: center; }
        
        /* Main Content */
        .main-content { flex: 1; display: flex; flex-direction: column; gap: 20px; }
        .welcome-card { background: #fff; padding: 30px; border-radius: 12px; box-shadow: var(--card-shadow); border-left: 5px solid var(--primary-color); }
        .page-title { font-size: 24px; margin: 0 0 10px 0; color: var(--text-color); }
        
        /* Cards */
        .info-cards-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; }
        .info-card { background: #fff; padding: 25px; border-radius: 12px; box-shadow: var(--card-shadow); display: flex; flex-direction: column; transition: transform 0.2s; border-left: 4px solid var(--student-accent); }
        .info-card:hover { transform: translateY(-3px); }
        .info-card h3 { margin: 0 0 15px 0; color: var(--student-accent); font-size: 16px; font-weight: 600; }
        
        /* Helpers */
        .section-header { font-size: 18px; font-weight: 600; margin-bottom: 15px; color: #333; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .empty-state { text-align: center; padding: 40px; color: #999; }
        
        @media (max-width: 900px) { .layout-container { flex-direction: column; } .sidebar { width: 100%; min-height: auto; } }
    </style>
</head>
<body>

    <header class="topbar">
        <div class="logo"><img src="image/ladybug.png" alt="Logo" style="width: 32px; margin-right: 10px;"> FYP System</div>
        <div class="topbar-right">
            <div class="user-profile-summary">
                <span class="user-name-display"><?php echo htmlspecialchars($user_name); ?></span>
                <span class="user-role-badge">Lecturer</span>
            </div>
            
            <div class="user-avatar-circle">
                <img src="<?php echo $user_avatar; ?>" alt="User Avatar">
            </div>
            
            <a href="login.php" class="logout-btn"><i class="fa fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>

    <div class="layout-container">
        
        <aside class="sidebar">
            <ul class="menu-list">
                <?php foreach ($menu_items as $key => $item): ?>
                    <?php 
                        $isActive = ($key == $current_page);
                        // 生成链接，带上 auth_user_id，确保 ID 不丢失
                        $linkUrl = "?page=" . $key . "&auth_user_id=" . urlencode($auth_user_id);
                    ?>
                    <li class="menu-item">
                        <a href="<?php echo $linkUrl; ?>" class="menu-link <?php echo $isActive ? 'active' : ''; ?>">
                            <span class="menu-icon"><i class="fa <?php echo $item['icon']; ?>"></i></span>
                            <?php echo $item['name']; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </aside>

        <main class="main-content">
            
            <div class="welcome-card">
                <?php if ($current_page == 'dashboard'): ?>
                    <h1 class="page-title">Hello, <?php echo htmlspecialchars($user_name); ?>! 👋</h1>
                    <p style="color: #666; margin: 0;">Welcome to your Supervisor Dashboard.</p>
                <?php else: ?>
                    <h1 class="page-title"><?php echo ucfirst(str_replace('_', ' ', $current_page)); ?></h1>
                    <p style="color: #666; margin: 0;">Manage your supervision tasks here.</p>
                <?php endif; ?>
            </div>

            <?php if ($current_page == 'dashboard'): ?>
                <div class="info-cards-grid">
                    <div class="info-card">
                        <h3><i class="fa fa-users"></i> Total Students</h3>
                        <p>0 Students</p> </div>
                    <div class="info-card" style="border-left-color: #d93025;">
                        <h3><i class="fa fa-clock"></i> Pending Requests</h3>
                        <p>0 Requests</p> </div>
                    <div class="info-card" style="border-left-color: #28a745;">
                        <h3><i class="fa fa-clipboard-check"></i> Project Quota</h3>
                        <p>0 / 5 Groups</p> </div>
                </div>

                <h3 class="section-header" style="margin-top: 40px;">Recent Activities</h3>
                <div class="empty-state">
                    <i class="fa fa-chart-line" style="font-size: 48px; margin-bottom: 20px; opacity: 0.3;"></i>
                    <p>No recent activities found.</p>
                </div>

            <?php elseif ($current_page == 'profile'): ?>
                <div style="background: #fff; padding: 40px; border-radius: 12px; text-align: center;">
                    <i class="fa fa-user-edit" style="font-size: 48px; color: #ddd; margin-bottom: 15px;"></i>
                    <p><strong>Profile Module</strong></p>
                    <p style="color: #777;">This section will allow you to edit your contact info, room number, and upload a photo.</p>
                    <p style="color: #999; font-size: 12px;">(Waiting for data structure confirmation)</p>
                </div>

            <?php elseif ($current_page == 'students'): ?>
                <div style="background: #fff; padding: 40px; border-radius: 12px; text-align: center;">
                    <i class="fa fa-users" style="font-size: 48px; color: #ddd; margin-bottom: 15px;"></i>
                    <p><strong>My Students Module</strong></p>
                    <p style="color: #777;">List of students under your supervision will appear here.</p>
                </div>

            <?php elseif ($current_page == 'schedule'): ?>
                <div style="background: #fff; padding: 40px; border-radius: 12px; text-align: center;">
                    <i class="fa fa-calendar-alt" style="font-size: 48px; color: #ddd; margin-bottom: 15px;"></i>
                    <p><strong>Schedule Module</strong></p>
                    <p style="color: #777;">Manage your available appointment slots here.</p>
                </div>

            <?php elseif ($current_page == 'requests'): ?>
                <div style="background: #fff; padding: 40px; border-radius: 12px; text-align: center;">
                    <i class="fa fa-envelope-open-text" style="font-size: 48px; color: #ddd; margin-bottom: 15px;"></i>
                    <p><strong>Requests Module</strong></p>
                    <p style="color: #777;">Approve or reject student appointment requests here.</p>
                </div>

            <?php else: ?>
                <div style="background: #fff; padding: 30px; border-radius: 12px; text-align: center;">
                    <i class="fa fa-wrench" style="font-size: 32px; color: #ddd; margin-bottom: 15px;"></i>
                    <p><strong>Under Construction</strong></p>
                </div>
            <?php endif; ?>

        </main>
    </div>

</body>
</html>