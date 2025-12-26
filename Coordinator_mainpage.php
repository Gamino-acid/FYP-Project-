<?php
// ----------------------------------------------------
// 1. 数据库连接与用户身份验证
// ----------------------------------------------------

// 引入数据库连接脚本。假设 dataconnection.php 中定义了 $conn 变量。
// 注意：之前我们在 login.php 里统一用了 $conn，这里也保持一致。
include("connect.php"); 

// 获取并验证从 login.php 传来的用户ID
$auth_user_id = $_GET['auth_user_id'] ?? null; 

// 如果没有有效的用户ID，则强制返回登录页（安全措施）
if (!$auth_user_id) {
    header("location: login.php");
    exit;
}

// ----------------------------------------------------
// 2. 查询用户数据 (Fetch User Data)
// ----------------------------------------------------

$sql_user = "SELECT fyp_username, fyp_usertype FROM USER WHERE fyp_userid = ? LIMIT 1";

$user_data = null;
$user_role = '';
$user_name = '';

// 使用 $conn 而不是 $connect，保持与 login.php 修复版一致
if (isset($conn) && $stmt_user = $conn->prepare($sql_user)) {
    $stmt_user->bind_param("s", $auth_user_id); // 假设 ID 是字符串或整数，这里用 s 通用，如果是纯数字用 i
    if ($stmt_user->execute()) {
        $result_user = $stmt_user->get_result();
        if ($result_user->num_rows == 1) {
            $user_data = $result_user->fetch_assoc();
            $user_role = $user_data['fyp_usertype']; // 'coordinator', 'student', 'lecturer'
            $user_name = $user_data['fyp_username']; // 默认使用用户名

            // 进一步查询，以获取真实姓名
            $sql_name = "";
            if ($user_role == 'student') {
                $sql_name = "SELECT fyp_studname FROM STUDENT WHERE fyp_userid = ?";
            } elseif ($user_role == 'lecturer' || $user_role == 'coordinator') {
                $sql_name = "SELECT fyp_name FROM SUPERVISOR WHERE fyp_userid = ?";
            }
            
            if (!empty($sql_name) && $stmt_name = $conn->prepare($sql_name)) {
                $stmt_name->bind_param("s", $auth_user_id);
                if ($stmt_name->execute()) {
                    $result_name = $stmt_name->get_result();
                    if ($result_name->num_rows == 1) {
                        $name_data = $result_name->fetch_assoc();
                        // 覆盖 $user_name 为真实姓名
                        $real_name = ($user_role == 'student') ? $name_data['fyp_studname'] : $name_data['fyp_name'];
                        if(!empty($real_name)) {
                            $user_name = $real_name;
                        }
                    }
                }
                $stmt_name->close();
            }

        } else {
            // 用户ID无效，强制登出
            header("location: login.php");
            exit;
        }
    }
    $stmt_user->close();
} else {
    // 数据库查询失败处理
    die("系统错误：数据库连接失败或查询错误。");
}

// ----------------------------------------------------
// 3. UI 框架 - 根据查询到的角色定义菜单项
// ----------------------------------------------------

$current_page = $_GET['page'] ?? 'dashboard'; // 获取当前页面，默认为 dashboard

// 根据角色设置用户头衔和菜单
$menu_items = [];
$user_title = ucfirst($user_role); // 默认标题

if ($user_role == 'coordinator') {
    $user_title = 'Coordinator';
    $menu_items = [
        'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home'],
        'profile' => ['name' => 'Profile', 'icon' => 'fa-user'],
        'supervision' => [
            'name' => 'Supervision', 
            'icon' => 'fa-chalkboard-user',
            'sub_items' => [
                'data_io' => ['name' => 'Data I/O', 'icon' => 'fa-exchange-alt'], 
            ]
        ],
        'appointments' => [
            'name' => 'Appointments', 
            'icon' => 'fa-calendar-check',
            'sub_items' => [
                'manage_schedule' => ['name' => 'Manage Schedule', 'icon' => 'fa-plus-circle'], 
            ]
        ],
        'project_mgmt' => [
            'name' => 'Project Mgmt', 
            'icon' => 'fa-tasks',
            'sub_items' => [
                'group_setup' => ['name' => 'Group Setup', 'icon' => 'fa-users'], 
                'proposals' => ['name' => 'Proposals', 'icon' => 'fa-lightbulb'], 
                'allocation' => ['name' => 'Allocation', 'icon' => 'fa-bullseye'], 
                'workload' => ['name' => 'Workload', 'icon' => 'fa-briefcase'], 
                'assessment' => ['name' => 'Assessment', 'icon' => 'fa-check-square'], 
            ]
        ],
        'announcements' => [
            'name' => 'Announcements', 
            'icon' => 'fa-bullhorn',
            'sub_items' => [
                'post_announcement' => ['name' => 'Post New', 'icon' => 'fa-pen'], 
            ]
        ],
        'reports' => ['name' => 'Reports', 'icon' => 'fa-chart-bar'], 
    ];
} elseif ($user_role == 'student') {
    $user_title = 'Student';
    $menu_items = [
        'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home'],
        'profile' => ['name' => 'Profile', 'icon' => 'fa-user'],
        'appointments' => [
            'name' => 'Appointments', 
            'icon' => 'fa-calendar',
            'sub_items' => [
                'book_session' => ['name' => 'Book Session', 'icon' => 'fa-plus'], 
            ]
        ],
        'project_mgmt' => [
            'name' => 'Project Mgmt', 
            'icon' => 'fa-project-diagram',
            'sub_items' => [
                'group_setup' => ['name' => 'Group Setup', 'icon' => 'fa-users'], 
                'proposals' => ['name' => 'Proposals', 'icon' => 'fa-lightbulb'], 
                'assessment' => ['name' => 'My Grades', 'icon' => 'fa-star'], 
            ]
        ],
        'announcements' => ['name' => 'Announcements', 'icon' => 'fa-bullhorn'],
    ];
} elseif ($user_role == 'lecturer') {
    $user_title = 'Lecturer';
    $menu_items = [
        'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home'],
        'profile' => ['name' => 'Profile', 'icon' => 'fa-user'],
        'supervision' => [
            'name' => 'Supervision', 
            'icon' => 'fa-user-graduate',
            'sub_items' => [
                'my_students' => ['name' => 'My Students', 'icon' => 'fa-users'],
                'assessment' => ['name' => 'Assessment', 'icon' => 'fa-check-double'], 
                'doc_review' => ['name' => 'Doc Review', 'icon' => 'fa-file-alt'],
            ]
        ],
        'appointments' => [
            'name' => 'Appointments', 
            'icon' => 'fa-calendar-alt',
            'sub_items' => [
                'manage_schedule' => ['name' => 'Manage Schedule', 'icon' => 'fa-clock'], 
            ]
        ],
        'announcements' => [
            'name' => 'Announcements', 
            'icon' => 'fa-bullhorn',
            'sub_items' => [
                'post_announcement' => ['name' => 'Post New', 'icon' => 'fa-pen-fancy'], 
            ]
        ],
    ];
}

// ----------------------------------------------------
// 4. HTML 输出 (集成 login.php 样式)
// ----------------------------------------------------
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo htmlspecialchars($user_title); ?></title>
    <link rel="icon" type="image/png" sizes="42x42" href="image/ladybug.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap');
        :root {
            /* 沿用 login.php 的蓝色主题 */
            --primary-color: #0056b3;
            --primary-hover: #004494;
            --secondary-color: #f4f4f9;
            --text-color: #333;
            --border-color: #e0e0e0;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            --gradient-start: #eef2f7;
            --gradient-end: #ffffff;
            --sidebar-width: 260px;
        }

        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: var(--text-color);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ---------------- Topbar (与 Login 一致) ---------------- */
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 40px;
            background-color: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            z-index: 100;
            position: sticky;
            top: 0;
        }
        .logo {
            font-size: 22px;
            font-weight: 600;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .topbar-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .user-profile-summary {
            text-align: right;
            line-height: 1.2;
        }
        .user-name-display {
            font-weight: 600;
            font-size: 14px;
            display: block;
        }
        .user-role-badge {
            font-size: 11px;
            background-color: var(--secondary-color);
            color: var(--primary-color);
            padding: 2px 8px;
            border-radius: 12px;
            font-weight: 500;
        }
        .flag-icon {
            width: 24px;
            height: auto;
        }
        .logout-btn {
            color: #d93025;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            padding: 8px 15px;
            border-radius: 6px;
            transition: background 0.2s;
        }
        .logout-btn:hover {
            background-color: #fff0f0;
        }

        /* ---------------- Layout: Sidebar + Main ---------------- */
        .layout-container {
            display: flex;
            flex: 1;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
            padding: 20px;
            box-sizing: border-box;
            gap: 20px;
        }

        /* ---------------- Sidebar ---------------- */
        .sidebar {
            width: var(--sidebar-width);
            background: #fff;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            padding: 20px 0;
            flex-shrink: 0;
            height: fit-content;
            min-height: calc(100vh - 120px);
        }
        
        .menu-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .menu-item {
            margin-bottom: 5px;
        }

        .menu-link {
            display: flex;
            align-items: center;
            padding: 12px 25px;
            text-decoration: none;
            color: #555;
            font-weight: 500;
            font-size: 15px;
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }

        .menu-link:hover {
            background-color: var(--secondary-color);
            color: var(--primary-color);
        }

        .menu-link.active {
            background-color: #e3effd;
            color: var(--primary-color);
            border-left-color: var(--primary-color);
        }

        .menu-icon {
            width: 24px;
            margin-right: 10px;
            text-align: center;
        }

        /* Submenu styling */
        .submenu {
            list-style: none;
            padding: 0;
            margin: 0;
            background-color: #fafafa;
            display: none; /* JS needed for toggle, or just show if active */
        }
        /* Show submenu if parent or child is active */
        .menu-item.has-active-child .submenu,
        .menu-item:hover .submenu { 
            display: block; 
        }

        .submenu .menu-link {
            padding-left: 58px;
            font-size: 14px;
            padding-top: 10px;
            padding-bottom: 10px;
        }

        /* ---------------- Main Content ---------------- */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .welcome-card {
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            border-left: 5px solid var(--primary-color);
        }

        .page-title {
            font-size: 24px;
            margin: 0 0 10px 0;
            color: var(--text-color);
        }
        
        .breadcrumb {
            color: #888;
            font-size: 14px;
            margin-bottom: 20px;
        }

        /* Dashboard Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .stat-card {
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            display: flex;
            flex-direction: column;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
        }
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .stat-icon {
            width: 45px;
            height: 45px;
            background-color: var(--secondary-color);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
            font-size: 20px;
        }
        .stat-value {
            font-size: 28px;
            font-weight: 600;
            color: var(--text-color);
            margin: 5px 0;
        }
        .stat-label {
            color: #777;
            font-size: 14px;
        }

        /* Content Area */
        .content-card {
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            min-height: 400px;
        }
        
        /* Responsive */
        @media (max-width: 900px) {
            .layout-container {
                flex-direction: column;
            }
            .sidebar {
                width: 100%;
                min-height: auto;
            }
        }
    </style>
</head>
<body>

    <!-- 顶部导航栏 (与 Login 保持一致) -->
    <header class="topbar">
        <div class="logo">
            <i class="fa fa-graduation-cap"></i>
            FYP Management
        </div>
        <div class="topbar-right">
            <div class="user-profile-summary">
                <span class="user-name-display"><?php echo htmlspecialchars($user_name); ?></span>
                <span class="user-role-badge"><?php echo htmlspecialchars($user_title); ?></span>
            </div>
            <img src="image/Malaysia.png" alt="MY" class="flag-icon" onerror="this.style.display='none'">
            <a href="login.php" class="logout-btn">
                <i class="fa fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </header>

    <div class="layout-container">
        
        <!-- 左侧边栏菜单 -->
        <aside class="sidebar">
            <ul class="menu-list">
                <?php foreach ($menu_items as $key => $item): ?>
                    <?php 
                        // 检查是否有子菜单被选中，用于展开菜单
                        $isActive = ($key == $current_page);
                        $hasActiveChild = false;
                        if (isset($item['sub_items'])) {
                            foreach ($item['sub_items'] as $sub_key => $sub) {
                                if ($sub_key == $current_page) {
                                    $hasActiveChild = true;
                                    break;
                                }
                            }
                        }
                    ?>
                    
                    <li class="menu-item <?php echo $hasActiveChild ? 'has-active-child' : ''; ?>">
                        <a href="?page=<?php echo $key; ?>&auth_user_id=<?php echo urlencode($auth_user_id); ?>" 
                           class="menu-link <?php echo $isActive ? 'active' : ''; ?>">
                            <span class="menu-icon"><i class="fa <?php echo $item['icon']; ?>"></i></span>
                            <?php echo $item['name']; ?>
                        </a>

                        <?php if (isset($item['sub_items'])): ?>
                            <ul class="submenu">
                                <?php foreach ($item['sub_items'] as $sub_key => $sub_item): ?>
                                    <li>
                                        <a href="?page=<?php echo $sub_key; ?>&auth_user_id=<?php echo urlencode($auth_user_id); ?>" 
                                           class="menu-link <?php echo ($sub_key == $current_page) ? 'active' : ''; ?>">
                                            <span class="menu-icon"><i class="fa <?php echo $sub_item['icon']; ?>"></i></span>
                                            <?php echo $sub_item['name']; ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </aside>

        <!-- 右侧主内容区 -->
        <main class="main-content">
            
            <!-- 欢迎卡片 -->
            <div class="welcome-card">
                <h1 class="page-title">Welcome back, <?php echo htmlspecialchars($user_name); ?>!</h1>
                <p style="color: #666; margin: 0;">Here is what's happening with your Final Year Projects today.</p>
            </div>

            <!-- 仪表盘统计卡片 (仅在 Dashboard 页面显示) -->
            <?php if ($current_page == 'dashboard'): ?>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-label">Total Projects</span>
                            <div class="stat-icon"><i class="fa fa-folder-open"></i></div>
                        </div>
                        <div class="stat-value">12</div>
                        <span class="stat-label" style="color: green;"><i class="fa fa-arrow-up"></i> Active</span>
                    </div>
                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-label">Pending Reviews</span>
                            <div class="stat-icon"><i class="fa fa-clock"></i></div>
                        </div>
                        <div class="stat-value">3</div>
                        <span class="stat-label" style="color: orange;">Action Required</span>
                    </div>
                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-label">Upcoming Deadlines</span>
                            <div class="stat-icon"><i class="fa fa-calendar-alt"></i></div>
                        </div>
                        <div class="stat-value">2</div>
                        <span class="stat-label">Next: Dec 31</span>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 功能内容显示区 -->
            <div class="content-card">
                <h2 style="margin-top: 0; color: var(--primary-color); border-bottom: 1px solid #eee; padding-bottom: 15px; font-size: 20px;">
                    <?php 
                        // 动态显示当前页面标题
                        $active_name = '';
                        foreach ($menu_items as $key => $item) {
                            if ($key == $current_page) {
                                $active_name = $item['name'];
                                break;
                            }
                            if (isset($item['sub_items'])) {
                                foreach ($item['sub_items'] as $sub_key => $sub_item) {
                                    if ($sub_key == $current_page) {
                                        $active_name = $sub_item['name'];
                                        break 2;
                                    }
                                }
                            }
                        }
                        echo htmlspecialchars($active_name) ?: 'Dashboard'; 
                    ?>
                </h2>
                
                <div style="padding: 20px 0; color: #555;">
                    <p>Current Page ID: <strong><?php echo htmlspecialchars($current_page); ?></strong></p>
                    <p>User Auth ID: <strong><?php echo htmlspecialchars($auth_user_id); ?></strong> (Authenticated)</p>
                    <br>
                    <div style="background: #f8f9fa; border: 1px dashed #ccc; padding: 20px; text-align: center; border-radius: 8px;">
                        <i class="fa fa-tools" style="font-size: 30px; color: #ccc; margin-bottom: 10px;"></i>
                        <p>Content for <strong><?php echo htmlspecialchars($active_name); ?></strong> will be displayed here.</p>
                        <p style="font-size: 12px; color: #999;">Start building your tables and forms in this section.</p>
                    </div>
                </div>
            </div>

        </main>
    </div>

</body>
</html>