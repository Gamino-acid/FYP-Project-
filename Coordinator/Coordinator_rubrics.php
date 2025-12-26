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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_set'])) {
        $set_name = $_POST['set_name'];
        $academic_year = $_POST['academic_year'];
        $description = $_POST['description'];
        
        // Insert logic here
        $message = "Assessment set created successfully!";
        $message_type = 'success';
    }
    
    if (isset($_POST['create_item'])) {
        $item_name = $_POST['item_name'];
        $set_id = $_POST['set_id'];
        $weightage = $_POST['weightage'];
        
        $message = "Assessment item created successfully!";
        $message_type = 'success';
    }
    
    if (isset($_POST['create_criteria'])) {
        $criteria_name = $_POST['criteria_name'];
        $item_id = $_POST['item_id'];
        $max_marks = $_POST['max_marks'];
        
        $message = "Marking criteria created successfully!";
        $message_type = 'success';
    }
    
    if (isset($_POST['save_defaults'])) {
        $message = "Default assessment criteria saved successfully!";
        $message_type = 'success';
    }
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
        
        .sidebar-mini { width: 250px; height: 100vh; background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%); position: fixed; left: 0; top: 0; border-right: 1px solid rgba(139, 92, 246, 0.1); padding: 20px 0; }
        .sidebar-mini h2 { color: #fff; font-size: 1.1rem; padding: 0 20px 20px; border-bottom: 1px solid rgba(139, 92, 246, 0.1); margin-bottom: 20px; }
        .sidebar-mini ul { list-style: none; }
        .sidebar-mini li a { display: flex; align-items: center; padding: 12px 20px; color: #94a3b8; text-decoration: none; transition: all 0.3s; }
        .sidebar-mini li a:hover, .sidebar-mini li a.active { background: rgba(139, 92, 246, 0.15); color: #fff; border-left: 3px solid #8b5cf6; }
        .sidebar-mini li a i { margin-right: 10px; width: 20px; }
        .back-link { margin-top: auto; padding: 20px; border-top: 1px solid rgba(139, 92, 246, 0.1); position: absolute; bottom: 0; width: 100%; }
        .back-link a { color: #f87171; text-decoration: none; display: flex; align-items: center; gap: 10px; }
        
        .main-area { margin-left: 250px; padding: 30px; flex: 1; }
        
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header h1 { color: #fff; font-size: 1.5rem; }
        
        .card { background: rgba(26, 26, 46, 0.6); border-radius: 16px; border: 1px solid rgba(139, 92, 246, 0.1); margin-bottom: 25px; }
        .card-header { padding: 20px 25px; border-bottom: 1px solid rgba(139, 92, 246, 0.1); display: flex; justify-content: space-between; align-items: center; }
        .card-header h3 { color: #fff; font-size: 1.1rem; }
        .card-body { padding: 25px; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; color: #a78bfa; font-size: 0.9rem; margin-bottom: 8px; font-weight: 500; }
        .form-control { width: 100%; padding: 12px 15px; background: rgba(15, 15, 26, 0.5); border: 1px solid rgba(139, 92, 246, 0.2); border-radius: 8px; color: #fff; font-size: 0.95rem; }
        .form-control:focus { outline: none; border-color: #8b5cf6; box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1); }
        textarea.form-control { min-height: 100px; resize: vertical; }
        
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
        
        .btn { padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; font-size: 0.9rem; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s; }
        .btn-primary { background: linear-gradient(135deg, #8b5cf6, #6366f1); color: white; }
        .btn-primary:hover { box-shadow: 0 5px 20px rgba(139, 92, 246, 0.4); transform: translateY(-2px); }
        .btn-success { background: linear-gradient(135deg, #10b981, #059669); color: white; }
        .btn-danger { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; }
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
        .badge-inactive { background: rgba(100, 116, 139, 0.2); color: #94a3b8; }
        
        .empty-state { text-align: center; padding: 50px; color: #64748b; }
        .empty-state i { font-size: 3rem; margin-bottom: 15px; opacity: 0.3; }
        
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 1000; align-items: center; justify-content: center; }
        .modal-overlay.active { display: flex; }
        .modal { background: #1a1a2e; border-radius: 16px; border: 1px solid rgba(139, 92, 246, 0.2); width: 100%; max-width: 600px; max-height: 90vh; overflow-y: auto; }
        .modal-header { padding: 20px 25px; border-bottom: 1px solid rgba(139, 92, 246, 0.1); display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { color: #fff; }
        .modal-close { background: none; border: none; color: #94a3b8; font-size: 1.5rem; cursor: pointer; }
        .modal-body { padding: 25px; }
        .modal-footer { padding: 20px 25px; border-top: 1px solid rgba(139, 92, 246, 0.1); display: flex; justify-content: flex-end; gap: 10px; }
        
        .criteria-card { background: rgba(139, 92, 246, 0.05); border: 1px solid rgba(139, 92, 246, 0.1); border-radius: 12px; padding: 20px; margin-bottom: 15px; }
        .criteria-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .criteria-title { color: #fff; font-weight: 600; }
        .criteria-weight { color: #a78bfa; font-size: 0.9rem; }
    </style>
</head>
<body>

<div class="container">
    
    <nav class="sidebar-mini">
        <h2><i class="fas fa-list-check"></i> Rubrics</h2>
        <ul>
            <li><a href="?action=sets" class="<?= $action === 'sets' ? 'active' : ''; ?>"><i class="fas fa-layer-group"></i> Assessment Sets</a></li>
            <li><a href="?action=items" class="<?= $action === 'items' ? 'active' : ''; ?>"><i class="fas fa-list-ol"></i> Assessment Items</a></li>
            <li><a href="?action=criteria" class="<?= $action === 'criteria' ? 'active' : ''; ?>"><i class="fas fa-tasks"></i> Marking Criteria</a></li>
            <li><a href="?action=defaults" class="<?= $action === 'defaults' ? 'active' : ''; ?>"><i class="fas fa-cog"></i> Default Criteria</a></li>
        </ul>
        <div class="back-link">
            <a href="Coordinator_mainpage.php?page=rubrics"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>
    </nav>
    
    <main class="main-area">
        
        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type; ?>">
                <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?= $message; ?>
            </div>
        <?php endif; ?>
        
        <!-- ASSESSMENT SETS -->
        <?php if ($action === 'sets'): ?>
            <div class="header">
                <h1>Assessment Sets</h1>
                <button class="btn btn-primary" onclick="openModal('createSetModal')"><i class="fas fa-plus"></i> Create New Set</button>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <p style="color:#94a3b8;margin-bottom:20px;">Create assessment sets for different academic years. Each set contains assessment items and criteria.</p>
                    
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Set Name</th>
                                <th>Academic Year</th>
                                <th>Items</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>FYP Assessment 2024</td>
                                <td>2024/2025</td>
                                <td>5 items</td>
                                <td><span class="badge badge-active">Active</span></td>
                                <td>
                                    <button class="btn btn-secondary btn-sm"><i class="fas fa-eye"></i></button>
                                    <button class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                            <tr>
                                <td>FYP Assessment 2023</td>
                                <td>2023/2024</td>
                                <td>5 items</td>
                                <td><span class="badge badge-inactive">Inactive</span></td>
                                <td>
                                    <button class="btn btn-secondary btn-sm"><i class="fas fa-eye"></i></button>
                                    <button class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        
        <!-- ASSESSMENT ITEMS -->
        <?php elseif ($action === 'items'): ?>
            <div class="header">
                <h1>Assessment Items</h1>
                <button class="btn btn-primary" onclick="openModal('createItemModal')"><i class="fas fa-plus"></i> Create New Item</button>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <p style="color:#94a3b8;margin-bottom:20px;">Manage assessment items within each set. Items define what aspects are being evaluated.</p>
                    
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Item Name</th>
                                <th>Set</th>
                                <th>Weightage</th>
                                <th>Criteria Count</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Project Proposal</td>
                                <td>FYP Assessment 2024</td>
                                <td>15%</td>
                                <td>4 criteria</td>
                                <td>
                                    <button class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                            <tr>
                                <td>Progress Report 1</td>
                                <td>FYP Assessment 2024</td>
                                <td>20%</td>
                                <td>5 criteria</td>
                                <td>
                                    <button class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                            <tr>
                                <td>Progress Report 2</td>
                                <td>FYP Assessment 2024</td>
                                <td>20%</td>
                                <td>5 criteria</td>
                                <td>
                                    <button class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                            <tr>
                                <td>Final Report</td>
                                <td>FYP Assessment 2024</td>
                                <td>30%</td>
                                <td>6 criteria</td>
                                <td>
                                    <button class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                            <tr>
                                <td>Presentation</td>
                                <td>FYP Assessment 2024</td>
                                <td>15%</td>
                                <td>4 criteria</td>
                                <td>
                                    <button class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        
        <!-- MARKING CRITERIA -->
        <?php elseif ($action === 'criteria'): ?>
            <div class="header">
                <h1>Marking Criteria</h1>
                <button class="btn btn-primary" onclick="openModal('createCriteriaModal')"><i class="fas fa-plus"></i> Create New Criteria</button>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <p style="color:#94a3b8;margin-bottom:20px;">Define marking criteria for each assessment item. These are the specific aspects that will be graded.</p>
                    
                    <div class="form-group">
                        <label>Filter by Assessment Item</label>
                        <select class="form-control" style="max-width:300px;">
                            <option value="">All Items</option>
                            <option value="1">Project Proposal</option>
                            <option value="2">Progress Report 1</option>
                            <option value="3">Progress Report 2</option>
                            <option value="4">Final Report</option>
                            <option value="5">Presentation</option>
                        </select>
                    </div>
                    
                    <div class="criteria-card">
                        <div class="criteria-header">
                            <span class="criteria-title">Project Proposal</span>
                            <span class="criteria-weight">Total: 15%</span>
                        </div>
                        <table class="data-table">
                            <thead>
                                <tr><th>Criteria</th><th>Max Marks</th><th>Weight</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                                <tr><td>Problem Statement</td><td>10</td><td>3%</td><td><button class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i></button></td></tr>
                                <tr><td>Objectives</td><td>10</td><td>4%</td><td><button class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i></button></td></tr>
                                <tr><td>Scope</td><td>10</td><td>4%</td><td><button class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i></button></td></tr>
                                <tr><td>Methodology</td><td>10</td><td>4%</td><td><button class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i></button></td></tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="criteria-card">
                        <div class="criteria-header">
                            <span class="criteria-title">Final Report</span>
                            <span class="criteria-weight">Total: 30%</span>
                        </div>
                        <table class="data-table">
                            <thead>
                                <tr><th>Criteria</th><th>Max Marks</th><th>Weight</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                                <tr><td>Introduction</td><td>10</td><td>5%</td><td><button class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i></button></td></tr>
                                <tr><td>Literature Review</td><td>10</td><td>5%</td><td><button class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i></button></td></tr>
                                <tr><td>Methodology</td><td>10</td><td>5%</td><td><button class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i></button></td></tr>
                                <tr><td>Implementation</td><td>10</td><td>5%</td><td><button class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i></button></td></tr>
                                <tr><td>Results & Discussion</td><td>10</td><td>5%</td><td><button class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i></button></td></tr>
                                <tr><td>Conclusion</td><td>10</td><td>5%</td><td><button class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i></button></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        
        <!-- DEFAULT CRITERIA -->
        <?php elseif ($action === 'defaults'): ?>
            <div class="header">
                <h1>Default Assessment Criteria</h1>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <p style="color:#94a3b8;margin-bottom:20px;">Set default assessment criteria that will be applied to new academic years.</p>
                    
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Default Proposal Weight (%)</label>
                                <input type="number" class="form-control" name="proposal_weight" value="15" min="0" max="100">
                            </div>
                            <div class="form-group">
                                <label>Default Progress Report Weight (%)</label>
                                <input type="number" class="form-control" name="progress_weight" value="40" min="0" max="100">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Default Final Report Weight (%)</label>
                                <input type="number" class="form-control" name="final_weight" value="30" min="0" max="100">
                            </div>
                            <div class="form-group">
                                <label>Default Presentation Weight (%)</label>
                                <input type="number" class="form-control" name="presentation_weight" value="15" min="0" max="100">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Passing Mark (%)</label>
                            <input type="number" class="form-control" name="passing_mark" value="50" min="0" max="100" style="max-width:200px;">
                        </div>
                        <button type="submit" name="save_defaults" class="btn btn-success"><i class="fas fa-save"></i> Save Default Criteria</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
        
    </main>
</div>

<!-- Create Set Modal -->
<div class="modal-overlay" id="createSetModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Create Assessment Set</h3>
            <button class="modal-close" onclick="closeModal('createSetModal')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <div class="form-group">
                    <label>Set Name</label>
                    <input type="text" class="form-control" name="set_name" placeholder="e.g. FYP Assessment 2024" required>
                </div>
                <div class="form-group">
                    <label>Academic Year</label>
                    <select class="form-control" name="academic_year" required>
                        <option value="2024">2024/2025</option>
                        <option value="2025">2025/2026</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea class="form-control" name="description" placeholder="Brief description of this assessment set..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createSetModal')">Cancel</button>
                <button type="submit" name="create_set" class="btn btn-primary">Create Set</button>
            </div>
        </form>
    </div>
</div>

<!-- Create Item Modal -->
<div class="modal-overlay" id="createItemModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Create Assessment Item</h3>
            <button class="modal-close" onclick="closeModal('createItemModal')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <div class="form-group">
                    <label>Item Name</label>
                    <input type="text" class="form-control" name="item_name" placeholder="e.g. Project Proposal" required>
                </div>
                <div class="form-group">
                    <label>Assessment Set</label>
                    <select class="form-control" name="set_id" required>
                        <option value="1">FYP Assessment 2024</option>
                        <option value="2">FYP Assessment 2023</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Weightage (%)</label>
                    <input type="number" class="form-control" name="weightage" placeholder="15" min="0" max="100" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createItemModal')">Cancel</button>
                <button type="submit" name="create_item" class="btn btn-primary">Create Item</button>
            </div>
        </form>
    </div>
</div>

<!-- Create Criteria Modal -->
<div class="modal-overlay" id="createCriteriaModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Create Marking Criteria</h3>
            <button class="modal-close" onclick="closeModal('createCriteriaModal')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <div class="form-group">
                    <label>Criteria Name</label>
                    <input type="text" class="form-control" name="criteria_name" placeholder="e.g. Problem Statement" required>
                </div>
                <div class="form-group">
                    <label>Assessment Item</label>
                    <select class="form-control" name="item_id" required>
                        <option value="1">Project Proposal</option>
                        <option value="2">Progress Report 1</option>
                        <option value="3">Progress Report 2</option>
                        <option value="4">Final Report</option>
                        <option value="5">Presentation</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Maximum Marks</label>
                    <input type="number" class="form-control" name="max_marks" placeholder="10" min="1" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea class="form-control" name="criteria_description" placeholder="What should be evaluated..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createCriteriaModal')">Cancel</button>
                <button type="submit" name="create_criteria" class="btn btn-primary">Create Criteria</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
document.querySelectorAll('.modal-overlay').forEach(m => m.addEventListener('click', function(e) { if (e.target === this) this.classList.remove('active'); }));
</script>

</body>
</html>