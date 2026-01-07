<?php
$menu_items = [
    'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home'],
    'registrations' => ['name' => 'Student Registrations', 'icon' => 'fa-user-plus', 'badge' => $pending_registrations],
    'group_requests' => ['name' => 'Group Requests', 'icon' => 'fa-users', 'badge' => $pending_requests],
    'students' => ['name' => 'Manage Students', 'icon' => 'fa-user-graduate'],
    'supervisors' => ['name' => 'Manage Supervisors', 'icon' => 'fa-user-tie'],
    'pairing' => ['name' => 'Student-Supervisor Pairing', 'icon' => 'fa-link'],
    'projects' => ['name' => 'Manage Projects', 'icon' => 'fa-folder-open'],
    'rubrics' => ['name' => 'Rubrics Assessment', 'icon' => 'fa-list-check'],
    'marks' => ['name' => 'Assessment Marks', 'icon' => 'fa-calculator'],
    'reports' => ['name' => 'Reports', 'icon' => 'fa-file-alt'],
    'announcements' => ['name' => 'Announcements', 'icon' => 'fa-bullhorn'],
    'settings' => ['name' => 'Settings', 'icon' => 'fa-cog'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Coordinator Dashboard - FYP Portal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #0f0f1a; color: #e2e8f0; min-height: 100vh; }
        
        .sidebar { width: 280px; height: 100vh; background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%); position: fixed; left: 0; top: 0; border-right: 1px solid rgba(139, 92, 246, 0.1); overflow-y: auto; z-index: 1000; }
        .sidebar-header { padding: 25px; text-align: center; border-bottom: 1px solid rgba(139, 92, 246, 0.1); background: rgba(139, 92, 246, 0.05); }
        .sidebar-header img { width: 80px; height: 80px; border-radius: 50%; margin-bottom: 12px; border: 3px solid #8b5cf6; }
        .sidebar-header h3 { font-size: 1rem; margin-bottom: 5px; color: #fff; }
        .sidebar-header p { font-size: 0.75rem; color: #a78bfa; text-transform: uppercase; letter-spacing: 1px; }
        
        .sidebar-nav { list-style: none; padding: 15px 0; }
        .sidebar-nav li a { display: flex; align-items: center; padding: 14px 25px; color: #94a3b8; text-decoration: none; transition: all 0.3s; border-left: 3px solid transparent; font-size: 0.9rem; }
        .sidebar-nav li a:hover, .sidebar-nav li a.active { background: rgba(139, 92, 246, 0.15); color: #fff; border-left-color: #8b5cf6; }
        .sidebar-nav li a i { margin-right: 12px; width: 20px; }
        .nav-badge { margin-left: auto; background: #ef4444; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.7rem; }
        
        .sidebar-footer { padding: 20px 25px; border-top: 1px solid rgba(139, 92, 246, 0.1); }
        .sidebar-footer a { color: #f87171; text-decoration: none; display: flex; align-items: center; }
        .sidebar-footer a i { margin-right: 10px; }
        
        .main-content { margin-left: 280px; min-height: 100vh; }
        .header { background: rgba(26, 26, 46, 0.8); backdrop-filter: blur(10px); padding: 20px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(139, 92, 246, 0.1); position: sticky; top: 0; z-index: 100; }
        .header h1 { font-size: 1.5rem; color: #fff; }
        .header-time { color: #94a3b8; font-size: 0.85rem; }
        
        .content { padding: 30px; }
        
        .card { background: rgba(26, 26, 46, 0.6); border-radius: 16px; border: 1px solid rgba(139, 92, 246, 0.1); margin-bottom: 25px; }
        .card-header { padding: 20px 25px; border-bottom: 1px solid rgba(139, 92, 246, 0.1); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .card-header h3 { font-size: 1.1rem; color: #fff; }
        .card-body { padding: 25px; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: linear-gradient(135deg, rgba(139, 92, 246, 0.1) 0%, rgba(59, 130, 246, 0.1) 100%); padding: 25px; border-radius: 16px; border: 1px solid rgba(139, 92, 246, 0.2); display: flex; align-items: center; transition: transform 0.3s; cursor: pointer; text-decoration: none; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-icon { width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-right: 20px; font-size: 1.5rem; }
        .stat-icon.purple { background: rgba(139, 92, 246, 0.2); color: #a78bfa; }
        .stat-icon.blue { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
        .stat-icon.green { background: rgba(16, 185, 129, 0.2); color: #34d399; }
        .stat-icon.orange { background: rgba(249, 115, 22, 0.2); color: #fb923c; }
        .stat-icon.red { background: rgba(239, 68, 68, 0.2); color: #f87171; }
        .stat-info h4 { font-size: 2rem; color: #fff; }
        .stat-info p { color: #94a3b8; font-size: 0.85rem; margin-top: 5px; }
        
        .welcome-box { background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 50%, #3b82f6 100%); color: white; padding: 35px; border-radius: 20px; margin-bottom: 30px; position: relative; overflow: hidden; }
        .welcome-box::before { content: ''; position: absolute; top: -50%; right: -20%; width: 400px; height: 400px; background: rgba(255,255,255,0.1); border-radius: 50%; }
        .welcome-box h2 { font-size: 1.8rem; margin-bottom: 10px; position: relative; }
        .welcome-box p { opacity: 0.9; position: relative; }
        
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 15px; text-align: left; border-bottom: 1px solid rgba(139, 92, 246, 0.1); }
        .data-table th { background: rgba(139, 92, 246, 0.1); color: #a78bfa; font-weight: 600; font-size: 0.8rem; text-transform: uppercase; }
        .data-table tr:hover { background: rgba(139, 92, 246, 0.05); }
        .data-table td { font-size: 0.9rem; }
        
        .btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-size: 0.85rem; font-weight: 600; transition: all 0.3s; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
        .btn-primary { background: linear-gradient(135deg, #8b5cf6, #6366f1); color: white; }
        .btn-primary:hover { box-shadow: 0 5px 20px rgba(139, 92, 246, 0.4); transform: translateY(-2px); }
        .btn-success { background: linear-gradient(135deg, #10b981, #059669); color: white; }
        .btn-danger { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; }
        .btn-secondary { background: rgba(100, 116, 139, 0.2); color: #94a3b8; border: 1px solid rgba(100, 116, 139, 0.3); }
        .btn-info { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; }
        .btn-warning { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
        .btn-sm { padding: 6px 12px; font-size: 0.8rem; }
        
        .alert { padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); color: #34d399; }
        .alert-error { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #f87171; }
        .alert-warning { background: rgba(249, 115, 22, 0.1); border: 1px solid rgba(249, 115, 22, 0.3); color: #fb923c; }
        .alert-info { background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); color: #60a5fa; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; color: #a78bfa; font-size: 0.9rem; margin-bottom: 8px; font-weight: 500; }
        .form-control { width: 100%; padding: 12px 15px; background: rgba(15, 15, 26, 0.5); border: 1px solid rgba(139, 92, 246, 0.2); border-radius: 8px; color: #fff; font-size: 0.95rem; }
        .form-control:focus { outline: none; border-color: #8b5cf6; box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1); }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
        
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .badge-pending { background: rgba(249, 115, 22, 0.2); color: #fb923c; }
        .badge-approved { background: rgba(16, 185, 129, 0.2); color: #34d399; }
        .badge-rejected { background: rgba(239, 68, 68, 0.2); color: #f87171; }
        .badge-open { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
        .badge-taken { background: rgba(139, 92, 246, 0.2); color: #a78bfa; }
        .badge-research { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
        .badge-application { background: rgba(16, 185, 129, 0.2); color: #34d399; }
        .badge-package { background: rgba(249, 115, 22, 0.2); color: #fb923c; }
        
        .empty-state { text-align: center; padding: 50px 20px; color: #64748b; }
        .empty-state i { font-size: 4rem; margin-bottom: 20px; opacity: 0.3; }
        
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 2000; align-items: center; justify-content: center; }
        .modal-overlay.active { display: flex; }
        .modal { background: #1a1a2e; border-radius: 16px; border: 1px solid rgba(139, 92, 246, 0.2); width: 100%; max-width: 600px; max-height: 90vh; overflow-y: auto; }
        .modal-header { padding: 20px 25px; border-bottom: 1px solid rgba(139, 92, 246, 0.1); display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { color: #fff; }
        .modal-close { background: none; border: none; color: #94a3b8; font-size: 1.5rem; cursor: pointer; }
        .modal-body { padding: 25px; }
        .modal-footer { padding: 20px 25px; border-top: 1px solid rgba(139, 92, 246, 0.1); display: flex; justify-content: flex-end; gap: 10px; }
        
        .quick-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; }
        .quick-action { background: rgba(139, 92, 246, 0.1); border: 1px solid rgba(139, 92, 246, 0.2); border-radius: 12px; padding: 20px; text-align: center; cursor: pointer; transition: all 0.3s; text-decoration: none; color: inherit; display: block; }
        .quick-action:hover { background: rgba(139, 92, 246, 0.2); transform: translateY(-3px); }
        .quick-action i { font-size: 2rem; color: #a78bfa; margin-bottom: 10px; display: block; }
        .quick-action h4 { color: #fff; font-size: 0.95rem; margin-bottom: 5px; }
        .quick-action p { color: #64748b; font-size: 0.8rem; }
        
        .search-box { display: flex; gap: 10px; margin-bottom: 20px; }
        .filter-row { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
        .filter-row .form-group { margin-bottom: 0; min-width: 150px; }
        
        .tab-nav { display: flex; gap: 5px; margin-bottom: 20px; border-bottom: 1px solid rgba(139, 92, 246, 0.1); padding-bottom: 10px; }
        .tab-btn { padding: 10px 20px; background: transparent; border: none; color: #94a3b8; cursor: pointer; border-radius: 8px 8px 0 0; transition: all 0.3s; }
        .tab-btn.active { background: rgba(139, 92, 246, 0.2); color: #fff; }
        
        @media (max-width: 768px) {
            .sidebar { width: 100%; height: auto; position: relative; }
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body>

<nav class="sidebar">
    <div class="sidebar-header">
        <img src="<?= $img_src; ?>" alt="Profile">
        <h3><?= htmlspecialchars($user_name); ?></h3>
        <p>Coordinator</p>
    </div>
    <ul class="sidebar-nav">
        <?php foreach ($menu_items as $key => $item): ?>
            <li><a href="?page=<?= $key; ?>" class="<?= ($key === $current_page) ? 'active' : ''; ?>">
                <i class="fas <?= $item['icon']; ?>"></i><?= $item['name']; ?>
                <?php if (isset($item['badge']) && $item['badge'] > 0): ?><span class="nav-badge"><?= $item['badge']; ?></span><?php endif; ?>
            </a></li>
        <?php endforeach; ?>
    </ul>
    <div class="sidebar-footer"><a href="../Login.php?logout=1"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
</nav>

<div class="main-content">
    <header class="header">
        <h1><?= $menu_items[$current_page]['name'] ?? 'Dashboard'; ?></h1>
        <span class="header-time"><?= date('l, F j, Y'); ?></span>
    </header>
    
    <div class="content">
        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type; ?>"><i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i> <?= $message; ?></div>
        <?php endif; ?>

<?php include("Coordinator_pages.php");