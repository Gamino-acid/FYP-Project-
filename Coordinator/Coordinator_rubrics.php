<?php
session_start();
include("../db_connect.php");

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'coordinator') {
    header("Location: ../Login.php");
    exit;
}

$user_name = $_SESSION['username'];
$action = $_GET['action'] ?? 'sets';
$message = '';
$message_type = '';

// Get academic years
$academic_years = [];
$res = $conn->query("SELECT * FROM academic_year ORDER BY fyp_academicid DESC");
if ($res) { while ($row = $res->fetch_assoc()) { $academic_years[] = $row; } }

// Get sets
$sets = [];
$res = $conn->query("SELECT s.*, a.fyp_acdyear, a.fyp_intake 
                     FROM `set` s 
                     LEFT JOIN academic_year a ON s.fyp_academicid = a.fyp_academicid 
                     ORDER BY s.fyp_setid DESC");
if ($res) { while ($row = $res->fetch_assoc()) { $sets[] = $row; } }

// Get items
$items = [];
$res = $conn->query("SELECT * FROM item ORDER BY fyp_itemid");
if ($res) { while ($row = $res->fetch_assoc()) { $items[] = $row; } }

// Get assessment criteria (marking criteria linked to items)
$assessment_criteria = [];
$res = $conn->query("SELECT * FROM assessment_criteria ORDER BY fyp_assessmentcriteriaid");
if ($res) { while ($row = $res->fetch_assoc()) { $assessment_criteria[] = $row; } }

// Get marking criteria
$marking_criteria = [];
$res = $conn->query("SELECT * FROM marking_criteria ORDER BY fyp_criteriaid");
if ($res) { while ($row = $res->fetch_assoc()) { $marking_criteria[] = $row; } }

// Get item-marking criteria links
$item_marking_links = [];
$res = $conn->query("SELECT imc.*, mc.fyp_criterianame, mc.fyp_percentallocation, i.fyp_itemname 
                     FROM item_marking_criteria imc
                     LEFT JOIN marking_criteria mc ON imc.fyp_criteriaid = mc.fyp_criteriaid
                     LEFT JOIN item i ON imc.fyp_itemid = i.fyp_itemid
                     ORDER BY imc.fyp_itemid, mc.fyp_criteriaid");
if ($res) { while ($row = $res->fetch_assoc()) { $item_marking_links[] = $row; } }

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Create Set
    if (isset($_POST['create_set'])) {
        $set_name = $_POST['set_name'];
        $academic_id = intval($_POST['academic_year']);
        $project_phase = 1; // Single project for Diploma IT
        
        $stmt = $conn->prepare("INSERT INTO `set` (fyp_setname, fyp_academicid, fyp_projectphase) VALUES (?, ?, ?)");
        $stmt->bind_param("sii", $set_name, $academic_id, $project_phase);
        if ($stmt->execute()) {
            $message = "Assessment set '$set_name' created successfully!";
            $message_type = 'success';
        } else {
            $message = "Error creating set: " . $conn->error;
            $message_type = 'error';
        }
        $stmt->close();
        header("Location: ?action=sets&msg=" . urlencode($message) . "&type=" . $message_type);
        exit;
    }
    
    // Create Item
    if (isset($_POST['create_item'])) {
        $item_name = $_POST['item_name'];
        $mark_allocation = floatval($_POST['mark_allocation']);
        $is_document = isset($_POST['is_document']) ? 1 : 0;
        $is_moderation = isset($_POST['is_moderation']) ? 1 : 0;
        
        $stmt = $conn->prepare("INSERT INTO item (fyp_itemname, fyp_originalmarkallocation, fyp_isdocument, fyp_ismoderation) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sdii", $item_name, $mark_allocation, $is_document, $is_moderation);
        if ($stmt->execute()) {
            $message = "Assessment item '$item_name' created successfully!";
            $message_type = 'success';
        } else {
            $message = "Error creating item: " . $conn->error;
            $message_type = 'error';
        }
        $stmt->close();
        header("Location: ?action=items&msg=" . urlencode($message) . "&type=" . $message_type);
        exit;
    }
    
    // Create Marking Criteria
    if (isset($_POST['create_criteria'])) {
        $criteria_name = $_POST['criteria_name'];
        $percent_allocation = floatval($_POST['percent_allocation']);
        
        $stmt = $conn->prepare("INSERT INTO marking_criteria (fyp_criterianame, fyp_percentallocation) VALUES (?, ?)");
        $stmt->bind_param("sd", $criteria_name, $percent_allocation);
        if ($stmt->execute()) {
            $message = "Marking criteria '$criteria_name' created successfully!";
            $message_type = 'success';
        } else {
            $message = "Error creating criteria: " . $conn->error;
            $message_type = 'error';
        }
        $stmt->close();
        header("Location: ?action=criteria&msg=" . urlencode($message) . "&type=" . $message_type);
        exit;
    }
    
    // Link Item to Marking Criteria
    if (isset($_POST['link_item_criteria'])) {
        $item_id = intval($_POST['item_id']);
        $criteria_id = intval($_POST['criteria_id']);
        
        // Check if link exists
        $check = $conn->query("SELECT * FROM item_marking_criteria WHERE fyp_itemid = $item_id AND fyp_criteriaid = $criteria_id");
        if ($check && $check->num_rows > 0) {
            $message = "This criteria is already linked to this item.";
            $message_type = 'error';
        } else {
            $stmt = $conn->prepare("INSERT INTO item_marking_criteria (fyp_itemid, fyp_criteriaid) VALUES (?, ?)");
            $stmt->bind_param("ii", $item_id, $criteria_id);
            if ($stmt->execute()) {
                $message = "Criteria linked to item successfully!";
                $message_type = 'success';
            } else {
                $message = "Error linking criteria: " . $conn->error;
                $message_type = 'error';
            }
            $stmt->close();
        }
        header("Location: ?action=link&msg=" . urlencode($message) . "&type=" . $message_type);
        exit;
    }
    
    // Delete Set
    if (isset($_POST['delete_set'])) {
        $set_id = intval($_POST['set_id']);
        $stmt = $conn->prepare("DELETE FROM `set` WHERE fyp_setid = ?");
        $stmt->bind_param("i", $set_id);
        if ($stmt->execute()) {
            $message = "Set deleted successfully!";
            $message_type = 'success';
        }
        $stmt->close();
        header("Location: ?action=sets&msg=" . urlencode($message) . "&type=" . $message_type);
        exit;
    }
    
    // Delete Item
    if (isset($_POST['delete_item'])) {
        $item_id = intval($_POST['item_id']);
        // First delete links
        $conn->query("DELETE FROM item_marking_criteria WHERE fyp_itemid = $item_id");
        $stmt = $conn->prepare("DELETE FROM item WHERE fyp_itemid = ?");
        $stmt->bind_param("i", $item_id);
        if ($stmt->execute()) {
            $message = "Item deleted successfully!";
            $message_type = 'success';
        }
        $stmt->close();
        header("Location: ?action=items&msg=" . urlencode($message) . "&type=" . $message_type);
        exit;
    }
    
    // Delete Criteria
    if (isset($_POST['delete_criteria'])) {
        $criteria_id = intval($_POST['criteria_id']);
        // First delete links
        $conn->query("DELETE FROM item_marking_criteria WHERE fyp_criteriaid = $criteria_id");
        $stmt = $conn->prepare("DELETE FROM marking_criteria WHERE fyp_criteriaid = ?");
        $stmt->bind_param("i", $criteria_id);
        if ($stmt->execute()) {
            $message = "Criteria deleted successfully!";
            $message_type = 'success';
        }
        $stmt->close();
        header("Location: ?action=criteria&msg=" . urlencode($message) . "&type=" . $message_type);
        exit;
    }
    
    // Unlink Item-Criteria
    if (isset($_POST['unlink_item_criteria'])) {
        $link_id = intval($_POST['link_id']);
        $stmt = $conn->prepare("DELETE FROM item_marking_criteria WHERE fyp_itemmarkid = ?");
        $stmt->bind_param("i", $link_id);
        if ($stmt->execute()) {
            $message = "Criteria unlinked successfully!";
            $message_type = 'success';
        }
        $stmt->close();
        header("Location: ?action=link&msg=" . urlencode($message) . "&type=" . $message_type);
        exit;
    }
}

// Get message from URL
if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
    $message_type = $_GET['type'] ?? 'success';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Rubrics Assessment - FYP Portal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #0f0f1a; color: #e2e8f0; min-height: 100vh; }
        
        .container { display: flex; }
        
        .sidebar-mini { width: 260px; height: 100vh; background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%); position: fixed; left: 0; top: 0; border-right: 1px solid rgba(139, 92, 246, 0.1); padding: 20px 0; }
        .sidebar-mini h2 { color: #fff; font-size: 1.1rem; padding: 0 20px 20px; border-bottom: 1px solid rgba(139, 92, 246, 0.1); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .sidebar-mini h2 i { color: #8b5cf6; }
        .sidebar-mini ul { list-style: none; }
        .sidebar-mini li a { display: flex; align-items: center; padding: 14px 20px; color: #94a3b8; text-decoration: none; transition: all 0.3s; font-size: 0.95rem; }
        .sidebar-mini li a:hover, .sidebar-mini li a.active { background: rgba(139, 92, 246, 0.15); color: #fff; border-left: 3px solid #8b5cf6; }
        .sidebar-mini li a i { margin-right: 12px; width: 20px; }
        .back-link { padding: 20px; border-top: 1px solid rgba(139, 92, 246, 0.1); position: absolute; bottom: 0; width: 100%; }
        .back-link a { color: #f87171; text-decoration: none; display: flex; align-items: center; gap: 10px; transition: color 0.3s; }
        .back-link a:hover { color: #fca5a5; }
        
        .main-area { margin-left: 260px; padding: 30px; flex: 1; min-height: 100vh; }
        
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 15px; }
        .header h1 { color: #fff; font-size: 1.6rem; display: flex; align-items: center; gap: 12px; }
        .header h1 i { color: #8b5cf6; }
        
        .card { background: rgba(26, 26, 46, 0.6); border-radius: 16px; border: 1px solid rgba(139, 92, 246, 0.1); margin-bottom: 25px; }
        .card-header { padding: 20px 25px; border-bottom: 1px solid rgba(139, 92, 246, 0.1); display: flex; justify-content: space-between; align-items: center; }
        .card-header h3 { color: #fff; font-size: 1.1rem; display: flex; align-items: center; gap: 10px; }
        .card-header h3 i { color: #a78bfa; }
        .card-body { padding: 25px; }
        
        .info-box { background: rgba(139, 92, 246, 0.1); border: 1px solid rgba(139, 92, 246, 0.2); border-radius: 12px; padding: 20px; margin-bottom: 25px; }
        .info-box h4 { color: #a78bfa; margin-bottom: 10px; font-size: 1rem; }
        .info-box p { color: #94a3b8; font-size: 0.9rem; line-height: 1.6; }
        .info-box ul { margin-left: 20px; margin-top: 10px; }
        .info-box li { color: #94a3b8; margin-bottom: 5px; font-size: 0.9rem; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; color: #a78bfa; font-size: 0.9rem; margin-bottom: 8px; font-weight: 500; }
        .form-control { width: 100%; padding: 12px 15px; background: rgba(15, 15, 26, 0.5); border: 1px solid rgba(139, 92, 246, 0.2); border-radius: 8px; color: #fff; font-size: 0.95rem; }
        .form-control:focus { outline: none; border-color: #8b5cf6; box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1); }
        textarea.form-control { min-height: 100px; resize: vertical; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
        
        .btn { padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; font-size: 0.9rem; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s; text-decoration: none; }
        .btn-primary { background: linear-gradient(135deg, #8b5cf6, #6366f1); color: white; }
        .btn-primary:hover { box-shadow: 0 5px 20px rgba(139, 92, 246, 0.4); transform: translateY(-2px); }
        .btn-success { background: linear-gradient(135deg, #10b981, #059669); color: white; }
        .btn-success:hover { box-shadow: 0 5px 20px rgba(16, 185, 129, 0.4); }
        .btn-danger { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; }
        .btn-warning { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
        .btn-secondary { background: rgba(100, 116, 139, 0.2); color: #94a3b8; }
        .btn-sm { padding: 8px 16px; font-size: 0.85rem; }
        
        .alert { padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); color: #34d399; }
        .alert-error { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #f87171; }
        
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 15px; text-align: left; border-bottom: 1px solid rgba(139, 92, 246, 0.1); }
        .data-table th { background: rgba(139, 92, 246, 0.1); color: #a78bfa; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; }
        .data-table tr:hover { background: rgba(139, 92, 246, 0.05); }
        
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .badge-active { background: rgba(16, 185, 129, 0.2); color: #34d399; }
        .badge-purple { background: rgba(139, 92, 246, 0.2); color: #a78bfa; }
        .badge-blue { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
        
        .empty-state { text-align: center; padding: 50px; color: #64748b; }
        .empty-state i { font-size: 3rem; margin-bottom: 15px; opacity: 0.3; display: block; }
        
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 1000; align-items: center; justify-content: center; }
        .modal-overlay.active { display: flex; }
        .modal { background: #1a1a2e; border-radius: 16px; border: 1px solid rgba(139, 92, 246, 0.2); width: 100%; max-width: 550px; max-height: 90vh; overflow-y: auto; }
        .modal-header { padding: 20px 25px; border-bottom: 1px solid rgba(139, 92, 246, 0.1); display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { color: #fff; display: flex; align-items: center; gap: 10px; }
        .modal-header h3 i { color: #8b5cf6; }
        .modal-close { background: none; border: none; color: #94a3b8; font-size: 1.5rem; cursor: pointer; }
        .modal-body { padding: 25px; }
        .modal-footer { padding: 20px 25px; border-top: 1px solid rgba(139, 92, 246, 0.1); display: flex; justify-content: flex-end; gap: 10px; }
        
        .stat-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: rgba(139, 92, 246, 0.1); border: 1px solid rgba(139, 92, 246, 0.2); border-radius: 12px; padding: 20px; text-align: center; }
        .stat-card h4 { font-size: 2rem; color: #8b5cf6; margin-bottom: 5px; }
        .stat-card p { color: #94a3b8; font-size: 0.85rem; }
        .stat-card.green { background: rgba(16, 185, 129, 0.1); border-color: rgba(16, 185, 129, 0.2); }
        .stat-card.green h4 { color: #34d399; }
        .stat-card.blue { background: rgba(59, 130, 246, 0.1); border-color: rgba(59, 130, 246, 0.2); }
        .stat-card.blue h4 { color: #60a5fa; }
        .stat-card.orange { background: rgba(249, 115, 22, 0.1); border-color: rgba(249, 115, 22, 0.2); }
        .stat-card.orange h4 { color: #fb923c; }
        
        .criteria-group { background: rgba(139, 92, 246, 0.05); border: 1px solid rgba(139, 92, 246, 0.1); border-radius: 12px; margin-bottom: 20px; overflow: hidden; }
        .criteria-group-header { background: rgba(139, 92, 246, 0.1); padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        .criteria-group-header h4 { color: #a78bfa; font-size: 1rem; }
        .criteria-group-body { padding: 15px 20px; }
    </style>
</head>
<body>

<div class="container">
    
    <nav class="sidebar-mini">
        <h2><i class="fas fa-list-check"></i> Rubrics Assessment</h2>
        <ul>
            <li><a href="?action=sets" class="<?= $action === 'sets' ? 'active' : ''; ?>"><i class="fas fa-layer-group"></i> Assessment Sets</a></li>
            <li><a href="?action=items" class="<?= $action === 'items' ? 'active' : ''; ?>"><i class="fas fa-list-ol"></i> Assessment Items</a></li>
            <li><a href="?action=criteria" class="<?= $action === 'criteria' ? 'active' : ''; ?>"><i class="fas fa-tasks"></i> Marking Criteria</a></li>
            <li><a href="?action=link" class="<?= $action === 'link' ? 'active' : ''; ?>"><i class="fas fa-link"></i> Link Items & Criteria</a></li>
            <li style="border-top:1px solid rgba(139,92,246,0.2);margin-top:15px;padding-top:15px;">
                <a href="Coordinator_marks.php" style="color:#34d399;"><i class="fas fa-calculator"></i> Go to Marks</a>
            </li>
        </ul>
        <div class="back-link">
            <a href="Coordinator_mainpage.php?page=rubrics"><i class="fas fa-arrow-left"></i> Back to Main Rubrics</a>
        </div>
    </nav>
    
    <main class="main-area">
        
        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type; ?>">
                <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?= htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <!-- ASSESSMENT SETS -->
        <?php if ($action === 'sets'): ?>
            <div class="header">
                <h1><i class="fas fa-layer-group"></i> Assessment Sets</h1>
                <button class="btn btn-primary" onclick="openModal('createSetModal')"><i class="fas fa-plus"></i> Create New Set</button>
            </div>
            
            <div class="info-box">
                <h4><i class="fas fa-info-circle"></i> What are Assessment Sets?</h4>
                <p>Assessment Sets group assessment items for a specific academic year. Each FYP batch should have one assessment set that defines how students will be evaluated.</p>
            </div>
            
            <div class="stat-cards">
                <div class="stat-card">
                    <h4><?= count($sets); ?></h4>
                    <p>Total Sets</p>
                </div>
                <div class="stat-card green">
                    <h4><?= count($items); ?></h4>
                    <p>Total Items</p>
                </div>
                <div class="stat-card blue">
                    <h4><?= count($marking_criteria); ?></h4>
                    <p>Marking Criteria</p>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list"></i> All Assessment Sets</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($sets)): ?>
                    <div class="empty-state">
                        <i class="fas fa-layer-group"></i>
                        <p>No assessment sets created yet</p>
                        <button class="btn btn-primary" style="margin-top:15px;" onclick="openModal('createSetModal')"><i class="fas fa-plus"></i> Create First Set</button>
                    </div>
                    <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Set Name</th>
                                <th>Academic Year</th>
                                <th>Intake</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sets as $set): ?>
                            <tr>
                                <td><?= $set['fyp_setid']; ?></td>
                                <td><strong style="color:#e2e8f0;"><?= htmlspecialchars($set['fyp_setname']); ?></strong></td>
                                <td><span class="badge badge-purple"><?= htmlspecialchars($set['fyp_acdyear'] ?? '-'); ?></span></td>
                                <td><span class="badge badge-blue"><?= htmlspecialchars($set['fyp_intake'] ?? '-'); ?></span></td>
                                <td>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this set?');">
                                        <input type="hidden" name="set_id" value="<?= $set['fyp_setid']; ?>">
                                        <button type="submit" name="delete_set" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        
        <!-- ASSESSMENT ITEMS -->
        <?php elseif ($action === 'items'): ?>
            <div class="header">
                <h1><i class="fas fa-list-ol"></i> Assessment Items</h1>
                <button class="btn btn-primary" onclick="openModal('createItemModal')"><i class="fas fa-plus"></i> Create New Item</button>
            </div>
            
            <div class="info-box">
                <h4><i class="fas fa-info-circle"></i> What are Assessment Items?</h4>
                <p>Assessment Items are the major components students are evaluated on, such as:</p>
                <ul>
                    <li><strong>Proposal</strong> - Initial project proposal document</li>
                    <li><strong>Chapter 1-5</strong> - Individual report chapters</li>
                    <li><strong>Final Report</strong> - Complete dissertation</li>
                    <li><strong>Presentation</strong> - Viva/demo presentation</li>
                </ul>
            </div>
            
            <?php
            $total_allocation = 0;
            foreach ($items as $item) {
                $total_allocation += floatval($item['fyp_originalmarkallocation'] ?? 0);
            }
            ?>
            
            <div class="stat-cards">
                <div class="stat-card">
                    <h4><?= count($items); ?></h4>
                    <p>Total Items</p>
                </div>
                <div class="stat-card <?= $total_allocation == 100 ? 'green' : 'orange'; ?>">
                    <h4><?= number_format($total_allocation, 1); ?>%</h4>
                    <p>Total Allocation <?= $total_allocation != 100 ? '(Should be 100%)' : ''; ?></p>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list"></i> All Assessment Items</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($items)): ?>
                    <div class="empty-state">
                        <i class="fas fa-list-ol"></i>
                        <p>No assessment items created yet</p>
                        <button class="btn btn-primary" style="margin-top:15px;" onclick="openModal('createItemModal')"><i class="fas fa-plus"></i> Create First Item</button>
                    </div>
                    <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Item Name</th>
                                <th>Mark Allocation</th>
                                <th>Document?</th>
                                <th>Moderation?</th>
                                <th>Linked Criteria</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): 
                                $linked_count = 0;
                                foreach ($item_marking_links as $link) {
                                    if ($link['fyp_itemid'] == $item['fyp_itemid']) $linked_count++;
                                }
                            ?>
                            <tr>
                                <td><?= $item['fyp_itemid']; ?></td>
                                <td><strong style="color:#e2e8f0;"><?= htmlspecialchars($item['fyp_itemname']); ?></strong></td>
                                <td><span class="badge badge-purple"><?= number_format($item['fyp_originalmarkallocation'] ?? 0, 1); ?>%</span></td>
                                <td><?= ($item['fyp_isdocument'] ?? 1) ? '<span style="color:#34d399;"><i class="fas fa-check"></i></span>' : '<span style="color:#f87171;"><i class="fas fa-times"></i></span>'; ?></td>
                                <td><?= ($item['fyp_ismoderation'] ?? 0) ? '<span style="color:#34d399;"><i class="fas fa-check"></i></span>' : '<span style="color:#f87171;"><i class="fas fa-times"></i></span>'; ?></td>
                                <td><span class="badge badge-blue"><?= $linked_count; ?> criteria</span></td>
                                <td>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this item? This will also remove all linked criteria.');">
                                        <input type="hidden" name="item_id" value="<?= $item['fyp_itemid']; ?>">
                                        <button type="submit" name="delete_item" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        
        <!-- MARKING CRITERIA -->
        <?php elseif ($action === 'criteria'): ?>
            <div class="header">
                <h1><i class="fas fa-tasks"></i> Marking Criteria</h1>
                <button class="btn btn-primary" onclick="openModal('createCriteriaModal')"><i class="fas fa-plus"></i> Create New Criteria</button>
            </div>
            
            <div class="info-box">
                <h4><i class="fas fa-info-circle"></i> What are Marking Criteria?</h4>
                <p>Marking Criteria are the specific aspects evaluated within each assessment item. Examples:</p>
                <ul>
                    <li><strong>Language</strong> - Grammar, spelling, clarity</li>
                    <li><strong>Content</strong> - Depth, accuracy, relevance</li>
                    <li><strong>Presentation</strong> - Format, structure, visuals</li>
                    <li><strong>Technical</strong> - Code quality, implementation</li>
                </ul>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list"></i> All Marking Criteria</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($marking_criteria)): ?>
                    <div class="empty-state">
                        <i class="fas fa-tasks"></i>
                        <p>No marking criteria created yet</p>
                        <button class="btn btn-primary" style="margin-top:15px;" onclick="openModal('createCriteriaModal')"><i class="fas fa-plus"></i> Create First Criteria</button>
                    </div>
                    <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Criteria Name</th>
                                <th>Percent Allocation</th>
                                <th>Used In Items</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($marking_criteria as $mc): 
                                $used_count = 0;
                                $used_items = [];
                                foreach ($item_marking_links as $link) {
                                    if ($link['fyp_criteriaid'] == $mc['fyp_criteriaid']) {
                                        $used_count++;
                                        $used_items[] = $link['fyp_itemname'];
                                    }
                                }
                            ?>
                            <tr>
                                <td><?= $mc['fyp_criteriaid']; ?></td>
                                <td><strong style="color:#e2e8f0;"><?= htmlspecialchars($mc['fyp_criterianame']); ?></strong></td>
                                <td><span class="badge badge-purple"><?= number_format($mc['fyp_percentallocation'] ?? 0, 1); ?>%</span></td>
                                <td>
                                    <?php if ($used_count > 0): ?>
                                    <span title="<?= htmlspecialchars(implode(', ', $used_items)); ?>" style="color:#60a5fa;"><?= $used_count; ?> item(s)</span>
                                    <?php else: ?>
                                    <span style="color:#94a3b8;">Not linked</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this criteria?');">
                                        <input type="hidden" name="criteria_id" value="<?= $mc['fyp_criteriaid']; ?>">
                                        <button type="submit" name="delete_criteria" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        
        <!-- LINK ITEMS & CRITERIA -->
        <?php elseif ($action === 'link'): ?>
            <div class="header">
                <h1><i class="fas fa-link"></i> Link Items & Criteria</h1>
                <button class="btn btn-primary" onclick="openModal('linkModal')"><i class="fas fa-plus"></i> Create New Link</button>
            </div>
            
            <div class="info-box">
                <h4><i class="fas fa-info-circle"></i> How Linking Works</h4>
                <p>Link marking criteria to assessment items to define what aspects are evaluated for each item. For example, link "Language" and "Content" criteria to "Chapter 1" item.</p>
            </div>
            
            <?php
            // Group links by item
            $links_by_item = [];
            foreach ($items as $item) {
                $links_by_item[$item['fyp_itemid']] = [
                    'item' => $item,
                    'criteria' => []
                ];
            }
            foreach ($item_marking_links as $link) {
                if (isset($links_by_item[$link['fyp_itemid']])) {
                    $links_by_item[$link['fyp_itemid']]['criteria'][] = $link;
                }
            }
            ?>
            
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-sitemap"></i> Current Links</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($items)): ?>
                    <div class="empty-state">
                        <i class="fas fa-link"></i>
                        <p>Create assessment items first before linking criteria</p>
                        <a href="?action=items" class="btn btn-primary" style="margin-top:15px;"><i class="fas fa-list-ol"></i> Go to Items</a>
                    </div>
                    <?php else: ?>
                    
                    <?php foreach ($links_by_item as $item_id => $data): ?>
                    <div class="criteria-group">
                        <div class="criteria-group-header">
                            <h4><i class="fas fa-file-alt"></i> <?= htmlspecialchars($data['item']['fyp_itemname']); ?> <span style="font-weight:normal;color:#94a3b8;font-size:0.85rem;">(<?= number_format($data['item']['fyp_originalmarkallocation'] ?? 0, 1); ?>%)</span></h4>
                            <span class="badge badge-blue"><?= count($data['criteria']); ?> criteria linked</span>
                        </div>
                        <div class="criteria-group-body">
                            <?php if (empty($data['criteria'])): ?>
                            <p style="color:#64748b;font-size:0.9rem;"><i class="fas fa-info-circle"></i> No criteria linked to this item yet</p>
                            <?php else: ?>
                            <table class="data-table" style="margin:0;">
                                <thead>
                                    <tr>
                                        <th>Criteria Name</th>
                                        <th>Percent Allocation</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data['criteria'] as $c): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($c['fyp_criterianame']); ?></td>
                                        <td><span class="badge badge-purple"><?= number_format($c['fyp_percentallocation'] ?? 0, 1); ?>%</span></td>
                                        <td>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Unlink this criteria?');">
                                                <input type="hidden" name="link_id" value="<?= $c['fyp_itemmarkid']; ?>">
                                                <button type="submit" name="unlink_item_criteria" class="btn btn-danger btn-sm"><i class="fas fa-unlink"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
    </main>
</div>

<!-- Create Set Modal -->
<div class="modal-overlay" id="createSetModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-layer-group"></i> Create Assessment Set</h3>
            <button class="modal-close" onclick="closeModal('createSetModal')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <div class="form-group">
                    <label>Set Name <span style="color:#f87171;">*</span></label>
                    <input type="text" class="form-control" name="set_name" placeholder="e.g. FYP 2024 - JUN" required>
                </div>
                <div class="form-group">
                    <label>Academic Year <span style="color:#f87171;">*</span></label>
                    <select class="form-control" name="academic_year" required>
                        <option value="">-- Select Academic Year --</option>
                        <?php foreach ($academic_years as $ay): ?>
                        <option value="<?= $ay['fyp_academicid']; ?>">FYP <?= htmlspecialchars($ay['fyp_acdyear']); ?> - <?= htmlspecialchars($ay['fyp_intake']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createSetModal')">Cancel</button>
                <button type="submit" name="create_set" class="btn btn-primary"><i class="fas fa-save"></i> Create Set</button>
            </div>
        </form>
    </div>
</div>

<!-- Create Item Modal -->
<div class="modal-overlay" id="createItemModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-list-ol"></i> Create Assessment Item</h3>
            <button class="modal-close" onclick="closeModal('createItemModal')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <div class="form-group">
                    <label>Item Name <span style="color:#f87171;">*</span></label>
                    <input type="text" class="form-control" name="item_name" placeholder="e.g. Proposal, Chapter 1, Final Report" required>
                </div>
                <div class="form-group">
                    <label>Mark Allocation (%) <span style="color:#f87171;">*</span></label>
                    <input type="number" class="form-control" name="mark_allocation" placeholder="e.g. 20" min="0" max="100" step="0.01" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Document Required?</label>
                        <div style="padding-top:10px;">
                            <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;color:#e2e8f0;">
                                <input type="checkbox" name="is_document" checked style="width:18px;height:18px;"> Yes, student must submit document
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Moderation Required?</label>
                        <div style="padding-top:10px;">
                            <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;color:#e2e8f0;">
                                <input type="checkbox" name="is_moderation" style="width:18px;height:18px;"> Yes, requires moderator marks
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createItemModal')">Cancel</button>
                <button type="submit" name="create_item" class="btn btn-primary"><i class="fas fa-save"></i> Create Item</button>
            </div>
        </form>
    </div>
</div>

<!-- Create Criteria Modal -->
<div class="modal-overlay" id="createCriteriaModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-tasks"></i> Create Marking Criteria</h3>
            <button class="modal-close" onclick="closeModal('createCriteriaModal')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <div class="form-group">
                    <label>Criteria Name <span style="color:#f87171;">*</span></label>
                    <input type="text" class="form-control" name="criteria_name" placeholder="e.g. Language, Content, Technical" required>
                </div>
                <div class="form-group">
                    <label>Percent Allocation (%) <span style="color:#f87171;">*</span></label>
                    <input type="number" class="form-control" name="percent_allocation" placeholder="e.g. 30" min="0" max="100" step="0.01" required>
                    <small style="color:#94a3b8;margin-top:5px;display:block;">This is the percentage of the item's mark this criteria represents.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createCriteriaModal')">Cancel</button>
                <button type="submit" name="create_criteria" class="btn btn-primary"><i class="fas fa-save"></i> Create Criteria</button>
            </div>
        </form>
    </div>
</div>

<!-- Link Item-Criteria Modal -->
<div class="modal-overlay" id="linkModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-link"></i> Link Criteria to Item</h3>
            <button class="modal-close" onclick="closeModal('linkModal')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <div class="form-group">
                    <label>Select Assessment Item <span style="color:#f87171;">*</span></label>
                    <select class="form-control" name="item_id" required>
                        <option value="">-- Select Item --</option>
                        <?php foreach ($items as $item): ?>
                        <option value="<?= $item['fyp_itemid']; ?>"><?= htmlspecialchars($item['fyp_itemname']); ?> (<?= number_format($item['fyp_originalmarkallocation'] ?? 0, 1); ?>%)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Select Marking Criteria <span style="color:#f87171;">*</span></label>
                    <select class="form-control" name="criteria_id" required>
                        <option value="">-- Select Criteria --</option>
                        <?php foreach ($marking_criteria as $mc): ?>
                        <option value="<?= $mc['fyp_criteriaid']; ?>"><?= htmlspecialchars($mc['fyp_criterianame']); ?> (<?= number_format($mc['fyp_percentallocation'] ?? 0, 1); ?>%)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('linkModal')">Cancel</button>
                <button type="submit" name="link_item_criteria" class="btn btn-primary"><i class="fas fa-link"></i> Create Link</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
document.querySelectorAll('.modal-overlay').forEach(function(m) { 
    m.addEventListener('click', function(e) { 
        if (e.target === this) this.classList.remove('active'); 
    }); 
});
</script>

</body>
</html>