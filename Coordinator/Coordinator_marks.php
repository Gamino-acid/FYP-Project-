<?php
session_start();
include("../db_connect.php");

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'coordinator') {
    header("Location: ../Login.php");
    exit;
}

$user_name = $_SESSION['username'];
$view = $_GET['view'] ?? 'overview';
$message = '';
$message_type = '';

// Get filter values
$filter_search = $_GET['search'] ?? '';

// Get academic years
$academic_years = [];
$res = $conn->query("SELECT * FROM academic_year ORDER BY fyp_academicid DESC");
if ($res) { while ($row = $res->fetch_assoc()) { $academic_years[] = $row; } }

// Get students with marks
$students = [];
$where = "1=1";
if (!empty($filter_search)) {
    $search = $conn->real_escape_string($filter_search);
    $where .= " AND (s.fyp_studid LIKE '%$search%' OR s.fyp_studname LIKE '%$search%')";
}

$res = $conn->query("SELECT s.*, 
                            p.fyp_projecttitle,
                            sup.fyp_name as supervisor_name,
                            mod_sup.fyp_name as moderator_name,
                            tm.fyp_totalmark, tm.fyp_totalfinalsupervisor, tm.fyp_totalfinalmoderator
                     FROM student s
                     LEFT JOIN pairing pa ON s.fyp_studid = pa.fyp_studid
                     LEFT JOIN project p ON pa.fyp_projectid = p.fyp_projectid
                     LEFT JOIN supervisor sup ON pa.fyp_supervisorid = sup.fyp_supervisorid
                     LEFT JOIN supervisor mod_sup ON pa.fyp_moderatorid = mod_sup.fyp_supervisorid
                     LEFT JOIN total_mark tm ON s.fyp_studid = tm.fyp_studid
                     WHERE $where
                     ORDER BY s.fyp_studname");
if ($res) { while ($row = $res->fetch_assoc()) { $students[] = $row; } }

// Get items
$items = [];
$res = $conn->query("SELECT * FROM item ORDER BY fyp_itemid");
if ($res) { while ($row = $res->fetch_assoc()) { $items[] = $row; } }

// Get marking criteria with item links
$item_criteria = [];
$res = $conn->query("SELECT imc.*, mc.fyp_criterianame, mc.fyp_percentallocation, i.fyp_itemname, i.fyp_originalmarkallocation
                     FROM item_marking_criteria imc
                     LEFT JOIN marking_criteria mc ON imc.fyp_criteriaid = mc.fyp_criteriaid
                     LEFT JOIN item i ON imc.fyp_itemid = i.fyp_itemid
                     ORDER BY imc.fyp_itemid, mc.fyp_criteriaid");
if ($res) { while ($row = $res->fetch_assoc()) { $item_criteria[] = $row; } }

// Handle mark submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_marks'])) {
    $student_id = $_POST['student_id'];
    $initial_work = $_POST['initial_work'] ?? [];
    $final_work = $_POST['final_work'] ?? [];
    $moderator_mark = $_POST['moderator_mark'] ?? [];
    
    $success_count = 0;
    
    foreach ($initial_work as $criteria_id => $initial) {
        $initial = floatval($initial);
        $final = floatval($final_work[$criteria_id] ?? 0);
        $mod_mark = floatval($moderator_mark[$criteria_id] ?? 0);
        
        // Calculate average
        $values = array_filter([$initial, $final, $mod_mark], function($v) { return $v > 0; });
        $avg_mark = count($values) > 0 ? array_sum($values) / count($values) : 0;
        
        // Get percent allocation for this criteria
        $percent = 0;
        foreach ($item_criteria as $ic) {
            if ($ic['fyp_criteriaid'] == $criteria_id) {
                $percent = $ic['fyp_percentallocation'];
                break;
            }
        }
        $scaled_mark = ($avg_mark * $percent) / 100;
        
        // Check if exists
        $stmt = $conn->prepare("SELECT fyp_criteriamarkid FROM criteria_mark WHERE fyp_studid = ? AND fyp_criteriaid = ?");
        $stmt->bind_param("si", $student_id, $criteria_id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($existing) {
            $stmt = $conn->prepare("UPDATE criteria_mark SET fyp_initialwork = ?, fyp_finalwork = ?, fyp_markbymoderator = ?, fyp_avgmark = ?, fyp_scaledmark = ? WHERE fyp_criteriamarkid = ?");
            $stmt->bind_param("dddddi", $initial, $final, $mod_mark, $avg_mark, $scaled_mark, $existing['fyp_criteriamarkid']);
        } else {
            $stmt = $conn->prepare("INSERT INTO criteria_mark (fyp_studid, fyp_criteriaid, fyp_initialwork, fyp_finalwork, fyp_markbymoderator, fyp_avgmark, fyp_scaledmark) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("siddddd", $student_id, $criteria_id, $initial, $final, $mod_mark, $avg_mark, $scaled_mark);
        }
        
        if ($stmt->execute()) $success_count++;
        $stmt->close();
    }
    
    // Calculate total mark
    $total = 0;
    $res = $conn->query("SELECT SUM(fyp_scaledmark) as total FROM criteria_mark WHERE fyp_studid = '$student_id'");
    if ($res) {
        $row = $res->fetch_assoc();
        $total = floatval($row['total'] ?? 0);
    }
    
    // Update total_mark table
    $check = $conn->query("SELECT fyp_studid FROM total_mark WHERE fyp_studid = '$student_id'");
    if ($check && $check->num_rows > 0) {
        $conn->query("UPDATE total_mark SET fyp_totalmark = $total WHERE fyp_studid = '$student_id'");
    } else {
        $project_res = $conn->query("SELECT fyp_projectid FROM pairing WHERE fyp_studid = '$student_id'");
        $project_id = $project_res ? ($project_res->fetch_assoc()['fyp_projectid'] ?? null) : null;
        $project_id_sql = $project_id ? $project_id : 'NULL';
        $conn->query("INSERT INTO total_mark (fyp_studid, fyp_projectid, fyp_totalmark) VALUES ('$student_id', $project_id_sql, $total)");
    }
    
    $message = "Marks saved successfully! Total: " . number_format($total, 2);
    $message_type = 'success';
}

// Get selected student for marking
$selected_student = null;
$student_marks = [];
if (!empty($_GET['student'])) {
    $sid = $conn->real_escape_string($_GET['student']);
    $res = $conn->query("SELECT s.*, p.fyp_projecttitle, sup.fyp_name as supervisor_name, mod_sup.fyp_name as moderator_name,
                                tm.fyp_totalmark, tm.fyp_totalfinalsupervisor, tm.fyp_totalfinalmoderator
                         FROM student s
                         LEFT JOIN pairing pa ON s.fyp_studid = pa.fyp_studid
                         LEFT JOIN project p ON pa.fyp_projectid = p.fyp_projectid
                         LEFT JOIN supervisor sup ON pa.fyp_supervisorid = sup.fyp_supervisorid
                         LEFT JOIN supervisor mod_sup ON pa.fyp_moderatorid = mod_sup.fyp_supervisorid
                         LEFT JOIN total_mark tm ON s.fyp_studid = tm.fyp_studid
                         WHERE s.fyp_studid = '$sid'");
    if ($res) $selected_student = $res->fetch_assoc();
    
    // Get existing marks
    $res = $conn->query("SELECT * FROM criteria_mark WHERE fyp_studid = '$sid'");
    if ($res) { while ($row = $res->fetch_assoc()) { $student_marks[$row['fyp_criteriaid']] = $row; } }
}

// Calculate statistics
$total_students = count($students);
$marked_students = 0;
$total_marks_sum = 0;
foreach ($students as $s) {
    if (!empty($s['fyp_totalmark'])) {
        $marked_students++;
        $total_marks_sum += $s['fyp_totalmark'];
    }
}
$avg_mark = $marked_students > 0 ? $total_marks_sum / $marked_students : 0;
$pending_students = $total_students - $marked_students;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Assessment Marks - FYP Portal</title>
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
        .back-link a { color: #f87171; text-decoration: none; display: flex; align-items: center; gap: 10px; }
        
        .main-area { margin-left: 260px; padding: 30px; flex: 1; }
        
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 15px; }
        .header h1 { color: #fff; font-size: 1.6rem; display: flex; align-items: center; gap: 12px; }
        .header h1 i { color: #8b5cf6; }
        
        .card { background: rgba(26, 26, 46, 0.6); border-radius: 16px; border: 1px solid rgba(139, 92, 246, 0.1); margin-bottom: 25px; }
        .card-header { padding: 20px 25px; border-bottom: 1px solid rgba(139, 92, 246, 0.1); display: flex; justify-content: space-between; align-items: center; }
        .card-header h3 { color: #fff; font-size: 1.1rem; display: flex; align-items: center; gap: 10px; }
        .card-header h3 i { color: #a78bfa; }
        .card-body { padding: 25px; }
        
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-box { background: rgba(139, 92, 246, 0.1); border: 1px solid rgba(139, 92, 246, 0.2); border-radius: 12px; padding: 20px; text-align: center; }
        .stat-box h4 { font-size: 2rem; color: #8b5cf6; margin-bottom: 5px; }
        .stat-box p { color: #94a3b8; font-size: 0.85rem; }
        .stat-box.green { background: rgba(16, 185, 129, 0.1); border-color: rgba(16, 185, 129, 0.2); }
        .stat-box.green h4 { color: #34d399; }
        .stat-box.orange { background: rgba(249, 115, 22, 0.1); border-color: rgba(249, 115, 22, 0.2); }
        .stat-box.orange h4 { color: #fb923c; }
        .stat-box.blue { background: rgba(59, 130, 246, 0.1); border-color: rgba(59, 130, 246, 0.2); }
        .stat-box.blue h4 { color: #60a5fa; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; color: #a78bfa; font-size: 0.9rem; margin-bottom: 8px; }
        .form-control { width: 100%; padding: 12px 15px; background: rgba(15, 15, 26, 0.5); border: 1px solid rgba(139, 92, 246, 0.2); border-radius: 8px; color: #fff; font-size: 0.95rem; }
        .form-control:focus { outline: none; border-color: #8b5cf6; }
        
        .filter-row { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; align-items: flex-end; }
        .filter-row .form-group { margin-bottom: 0; flex: 1; min-width: 200px; }
        
        .btn { padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; font-size: 0.9rem; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s; text-decoration: none; }
        .btn-primary { background: linear-gradient(135deg, #8b5cf6, #6366f1); color: white; }
        .btn-success { background: linear-gradient(135deg, #10b981, #059669); color: white; }
        .btn-secondary { background: rgba(100, 116, 139, 0.2); color: #94a3b8; }
        .btn-info { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; }
        .btn-sm { padding: 8px 16px; font-size: 0.85rem; }
        
        .alert { padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); color: #34d399; }
        .alert-error { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #f87171; }
        
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid rgba(139, 92, 246, 0.1); }
        .data-table th { background: rgba(139, 92, 246, 0.1); color: #a78bfa; font-weight: 600; font-size: 0.8rem; text-transform: uppercase; }
        .data-table tr:hover { background: rgba(139, 92, 246, 0.05); }
        
        .mark-input { width: 70px; padding: 8px 10px; background: rgba(15, 15, 26, 0.5); border: 1px solid rgba(139, 92, 246, 0.2); border-radius: 6px; color: #fff; text-align: center; font-size: 0.95rem; }
        .mark-input:focus { outline: none; border-color: #8b5cf6; background: rgba(139, 92, 246, 0.1); }
        .mark-input.readonly { background: rgba(15, 15, 26, 0.3); color: #60a5fa; }
        
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .badge-success { background: rgba(16, 185, 129, 0.2); color: #34d399; }
        .badge-warning { background: rgba(249, 115, 22, 0.2); color: #fb923c; }
        .badge-purple { background: rgba(139, 92, 246, 0.2); color: #a78bfa; }
        
        .student-card { background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(59, 130, 246, 0.05)); border: 1px solid rgba(139, 92, 246, 0.2); border-radius: 16px; padding: 25px; margin-bottom: 25px; }
        .student-header { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 20px; }
        .student-info h4 { color: #fff; font-size: 1.3rem; margin-bottom: 8px; }
        .student-info p { color: #94a3b8; font-size: 0.9rem; margin-bottom: 5px; }
        .student-info p i { width: 20px; color: #a78bfa; }
        .student-total { text-align: right; }
        .student-total h3 { font-size: 2.5rem; color: #8b5cf6; line-height: 1; }
        .student-total p { color: #64748b; font-size: 0.85rem; margin-top: 5px; }
        
        .item-section { background: rgba(139, 92, 246, 0.05); border: 1px solid rgba(139, 92, 246, 0.1); border-radius: 12px; margin-bottom: 20px; overflow: hidden; }
        .item-header { background: rgba(139, 92, 246, 0.1); padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        .item-header h4 { color: #a78bfa; font-size: 1rem; display: flex; align-items: center; gap: 10px; }
        .item-body { padding: 15px; }
        
        .search-box { position: relative; }
        .search-box input { padding-left: 45px; }
        .search-box i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #64748b; }
    </style>
</head>
<body>

<div class="container">
    
    <nav class="sidebar-mini">
        <h2><i class="fas fa-calculator"></i> Assessment Marks</h2>
        <ul>
            <li><a href="?view=overview" class="<?= $view === 'overview' ? 'active' : ''; ?>"><i class="fas fa-chart-bar"></i> Overview</a></li>
            <li><a href="?view=students" class="<?= $view === 'students' ? 'active' : ''; ?>"><i class="fas fa-user-graduate"></i> All Students</a></li>
            <li><a href="?view=mark" class="<?= $view === 'mark' ? 'active' : ''; ?>"><i class="fas fa-edit"></i> Mark Student</a></li>
            <li><a href="?view=reports" class="<?= $view === 'reports' ? 'active' : ''; ?>"><i class="fas fa-file-excel"></i> Export Reports</a></li>
            <li style="border-top:1px solid rgba(139,92,246,0.2);margin-top:15px;padding-top:15px;">
                <a href="Coordinator_rubrics.php" style="color:#fbbf24;"><i class="fas fa-list-check"></i> Configure Rubrics</a>
            </li>
        </ul>
        <div class="back-link">
            <a href="Coordinator_mainpage.php?page=marks"><i class="fas fa-arrow-left"></i> Back to Main Marks</a>
        </div>
    </nav>
    
    <main class="main-area">
        
        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type; ?>">
                <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?= htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <!-- OVERVIEW -->
        <?php if ($view === 'overview'): ?>
            <div class="header">
                <h1><i class="fas fa-chart-bar"></i> Marks Overview</h1>
                <a href="?view=reports" class="btn btn-success"><i class="fas fa-file-excel"></i> Export to Excel</a>
            </div>
            
            <div class="stats-row">
                <div class="stat-box">
                    <h4><?= $total_students; ?></h4>
                    <p>Total Students</p>
                </div>
                <div class="stat-box green">
                    <h4><?= $marked_students; ?></h4>
                    <p>Marked</p>
                </div>
                <div class="stat-box orange">
                    <h4><?= $pending_students; ?></h4>
                    <p>Pending</p>
                </div>
                <div class="stat-box blue">
                    <h4><?= number_format($avg_mark, 1); ?></h4>
                    <p>Average Mark</p>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list"></i> Quick Student List</h3>
                    <a href="?view=students" class="btn btn-primary btn-sm"><i class="fas fa-eye"></i> View All</a>
                </div>
                <div class="card-body">
                    <div style="overflow-x:auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Project</th>
                                    <th>Total Mark</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $show_students = array_slice($students, 0, 10);
                                foreach ($show_students as $s): 
                                ?>
                                <tr>
                                    <td><strong style="color:#60a5fa;"><?= htmlspecialchars($s['fyp_studid']); ?></strong></td>
                                    <td><?= htmlspecialchars($s['fyp_studname']); ?></td>
                                    <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($s['fyp_projecttitle'] ?? '-'); ?></td>
                                    <td>
                                        <?php if ($s['fyp_totalmark']): ?>
                                        <strong style="color:<?= $s['fyp_totalmark'] >= 50 ? '#34d399' : '#f87171'; ?>;"><?= number_format($s['fyp_totalmark'], 2); ?></strong>
                                        <?php else: ?>
                                        <span style="color:#64748b;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($s['fyp_totalmark']): ?>
                                        <span class="badge badge-success">Marked</span>
                                        <?php else: ?>
                                        <span class="badge badge-warning">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="?view=mark&student=<?= urlencode($s['fyp_studid']); ?>" class="btn btn-info btn-sm"><i class="fas fa-edit"></i></a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        
        <!-- ALL STUDENTS -->
        <?php elseif ($view === 'students'): ?>
            <div class="header">
                <h1><i class="fas fa-user-graduate"></i> All Students</h1>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="filter-row">
                        <input type="hidden" name="view" value="students">
                        <div class="form-group search-box">
                            <label>Search Student</label>
                            <i class="fas fa-search"></i>
                            <input type="text" class="form-control" name="search" placeholder="ID or Name..." value="<?= htmlspecialchars($filter_search); ?>">
                        </div>
                        <div class="form-group" style="flex:0;">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                        </div>
                        <?php if ($filter_search): ?>
                        <div class="form-group" style="flex:0;">
                            <label>&nbsp;</label>
                            <a href="?view=students" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
                        </div>
                        <?php endif; ?>
                    </form>
                    
                    <div style="overflow-x:auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Project</th>
                                    <th>Supervisor</th>
                                    <th>Total Mark</th>
                                    <th>Sup Mark</th>
                                    <th>Mod Mark</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($students)): ?>
                                <tr><td colspan="8" style="text-align:center;padding:40px;color:#64748b;">No students found</td></tr>
                                <?php else: ?>
                                <?php foreach ($students as $s): ?>
                                <tr>
                                    <td><strong style="color:#60a5fa;"><?= htmlspecialchars($s['fyp_studid']); ?></strong></td>
                                    <td><?= htmlspecialchars($s['fyp_studname']); ?></td>
                                    <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($s['fyp_projecttitle'] ?? '-'); ?></td>
                                    <td><?= htmlspecialchars($s['supervisor_name'] ?? '-'); ?></td>
                                    <td>
                                        <?php if ($s['fyp_totalmark']): ?>
                                        <strong style="color:<?= $s['fyp_totalmark'] >= 50 ? '#34d399' : '#f87171'; ?>;"><?= number_format($s['fyp_totalmark'], 2); ?></strong>
                                        <?php else: ?>
                                        <span style="color:#64748b;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $s['fyp_totalfinalsupervisor'] ? number_format($s['fyp_totalfinalsupervisor'], 2) : '-'; ?></td>
                                    <td><?= $s['fyp_totalfinalmoderator'] ? number_format($s['fyp_totalfinalmoderator'], 2) : '-'; ?></td>
                                    <td>
                                        <a href="?view=mark&student=<?= urlencode($s['fyp_studid']); ?>" class="btn btn-success btn-sm"><i class="fas fa-edit"></i> Mark</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div style="margin-top:15px;color:#64748b;font-size:0.9rem;">
                        Showing <?= count($students); ?> student(s)
                    </div>
                </div>
            </div>
        
        <!-- MARK STUDENT -->
        <?php elseif ($view === 'mark'): ?>
            <div class="header">
                <h1><i class="fas fa-edit"></i> Mark Student</h1>
                <a href="?view=students" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to List</a>
            </div>
            
            <?php if (!$selected_student): ?>
            <!-- Student Selection -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-user-graduate"></i> Select Student to Mark</h3>
                </div>
                <div class="card-body">
                    <form method="GET" class="filter-row">
                        <input type="hidden" name="view" value="mark">
                        <div class="form-group search-box" style="flex:2;">
                            <label>Search Student</label>
                            <i class="fas fa-search"></i>
                            <input type="text" class="form-control" name="search" placeholder="Enter student ID or name..." value="<?= htmlspecialchars($filter_search); ?>">
                        </div>
                        <div class="form-group" style="flex:0;">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                        </div>
                    </form>
                    
                    <?php if ($filter_search): ?>
                    <div style="overflow-x:auto;margin-top:20px;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Project</th>
                                    <th>Current Mark</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $s): ?>
                                <tr>
                                    <td><strong style="color:#60a5fa;"><?= htmlspecialchars($s['fyp_studid']); ?></strong></td>
                                    <td><?= htmlspecialchars($s['fyp_studname']); ?></td>
                                    <td><?= htmlspecialchars($s['fyp_projecttitle'] ?? '-'); ?></td>
                                    <td><?= $s['fyp_totalmark'] ? number_format($s['fyp_totalmark'], 2) : '-'; ?></td>
                                    <td>
                                        <a href="?view=mark&student=<?= urlencode($s['fyp_studid']); ?>" class="btn btn-success btn-sm"><i class="fas fa-edit"></i> Mark</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p style="color:#64748b;text-align:center;padding:30px;">Enter a student ID or name to search</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php else: ?>
            <!-- Student Marking Form -->
            <div class="student-card">
                <div class="student-header">
                    <div class="student-info">
                        <h4><?= htmlspecialchars($selected_student['fyp_studname']); ?></h4>
                        <p><i class="fas fa-id-card"></i> <?= htmlspecialchars($selected_student['fyp_studid']); ?></p>
                        <p><i class="fas fa-project-diagram"></i> <?= htmlspecialchars($selected_student['fyp_projecttitle'] ?? 'No project assigned'); ?></p>
                        <p><i class="fas fa-user-tie"></i> Supervisor: <?= htmlspecialchars($selected_student['supervisor_name'] ?? 'Not assigned'); ?></p>
                    </div>
                    <div class="student-total">
                        <h3><?= $selected_student['fyp_totalmark'] ? number_format($selected_student['fyp_totalmark'], 2) : '0.00'; ?></h3>
                        <p>Current Total</p>
                    </div>
                </div>
            </div>
            
            <form method="POST">
                <input type="hidden" name="save_marks" value="1">
                <input type="hidden" name="student_id" value="<?= htmlspecialchars($selected_student['fyp_studid']); ?>">
                
                <?php
                // Group criteria by item
                $items_with_criteria = [];
                foreach ($items as $item) {
                    $items_with_criteria[$item['fyp_itemid']] = [
                        'item' => $item,
                        'criteria' => []
                    ];
                }
                foreach ($item_criteria as $ic) {
                    if (isset($items_with_criteria[$ic['fyp_itemid']])) {
                        $items_with_criteria[$ic['fyp_itemid']]['criteria'][] = $ic;
                    }
                }
                
                $has_criteria = false;
                foreach ($items_with_criteria as $data) {
                    if (!empty($data['criteria'])) {
                        $has_criteria = true;
                        break;
                    }
                }
                ?>
                
                <?php if (!$has_criteria): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    No marking criteria configured. Please set up rubrics first.
                    <a href="Coordinator_rubrics.php?action=link" class="btn btn-primary btn-sm" style="margin-left:15px;">Configure Rubrics</a>
                </div>
                <?php else: ?>
                
                <?php foreach ($items_with_criteria as $item_id => $data): 
                    if (empty($data['criteria'])) continue;
                ?>
                <div class="item-section">
                    <div class="item-header">
                        <h4><i class="fas fa-file-alt"></i> <?= htmlspecialchars($data['item']['fyp_itemname']); ?></h4>
                        <span class="badge badge-purple"><?= number_format($data['item']['fyp_originalmarkallocation'] ?? 0, 1); ?>%</span>
                    </div>
                    <div class="item-body">
                        <table class="data-table" style="margin:0;">
                            <thead>
                                <tr>
                                    <th>Criteria</th>
                                    <th>%</th>
                                    <th>Initial</th>
                                    <th>Final</th>
                                    <th>Moderator</th>
                                    <th>Average</th>
                                    <th>Scaled</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data['criteria'] as $c): 
                                    $existing = $student_marks[$c['fyp_criteriaid']] ?? null;
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($c['fyp_criterianame']); ?></strong></td>
                                    <td><span class="badge badge-purple"><?= number_format($c['fyp_percentallocation'], 1); ?>%</span></td>
                                    <td>
                                        <input type="number" name="initial_work[<?= $c['fyp_criteriaid']; ?>]" 
                                               class="mark-input" min="0" max="100" step="0.01"
                                               value="<?= $existing['fyp_initialwork'] ?? ''; ?>"
                                               onchange="calcMarks(<?= $c['fyp_criteriaid']; ?>, <?= $c['fyp_percentallocation']; ?>)">
                                    </td>
                                    <td>
                                        <input type="number" name="final_work[<?= $c['fyp_criteriaid']; ?>]" 
                                               class="mark-input" min="0" max="100" step="0.01"
                                               value="<?= $existing['fyp_finalwork'] ?? ''; ?>"
                                               onchange="calcMarks(<?= $c['fyp_criteriaid']; ?>, <?= $c['fyp_percentallocation']; ?>)">
                                    </td>
                                    <td>
                                        <input type="number" name="moderator_mark[<?= $c['fyp_criteriaid']; ?>]" 
                                               class="mark-input" min="0" max="100" step="0.01"
                                               value="<?= $existing['fyp_markbymoderator'] ?? ''; ?>"
                                               onchange="calcMarks(<?= $c['fyp_criteriaid']; ?>, <?= $c['fyp_percentallocation']; ?>)">
                                    </td>
                                    <td>
                                        <input type="text" id="avg_<?= $c['fyp_criteriaid']; ?>" class="mark-input readonly" readonly
                                               value="<?= isset($existing['fyp_avgmark']) ? number_format($existing['fyp_avgmark'], 2) : ''; ?>">
                                    </td>
                                    <td>
                                        <input type="text" id="scaled_<?= $c['fyp_criteriaid']; ?>" class="mark-input readonly scaled-mark" readonly
                                               value="<?= isset($existing['fyp_scaledmark']) ? number_format($existing['fyp_scaledmark'], 2) : ''; ?>">
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <!-- Submit Section -->
                <div style="background:rgba(139,92,246,0.1);padding:25px;border-radius:12px;margin-top:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:20px;">
                    <div>
                        <label style="color:#a78bfa;font-weight:600;display:block;margin-bottom:8px;">Final Total Mark:</label>
                        <input type="text" id="finalTotal" class="form-control" style="width:150px;font-size:1.5rem;font-weight:700;color:#fbbf24;background:rgba(15,15,26,0.6);text-align:center;" readonly value="0.00">
                    </div>
                    <div style="display:flex;gap:15px;">
                        <a href="?view=students" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                        <button type="submit" class="btn btn-success" style="min-width:180px;"><i class="fas fa-save"></i> Save Marks</button>
                    </div>
                </div>
                <?php endif; ?>
            </form>
            <?php endif; ?>
        
        <!-- REPORTS -->
        <?php elseif ($view === 'reports'): ?>
            <div class="header">
                <h1><i class="fas fa-file-excel"></i> Export Reports</h1>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-download"></i> Available Reports</h3>
                </div>
                <div class="card-body">
                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:20px;">
                        <div style="background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.2);border-radius:12px;padding:25px;">
                            <h4 style="color:#34d399;margin-bottom:10px;"><i class="fas fa-table"></i> All Students Marks</h4>
                            <p style="color:#94a3b8;font-size:0.9rem;margin-bottom:20px;">Export complete marks data for all students.</p>
                            <button class="btn btn-success" onclick="exportToCSV()"><i class="fas fa-download"></i> Export CSV</button>
                        </div>
                        
                        <div style="background:rgba(59,130,246,0.1);border:1px solid rgba(59,130,246,0.2);border-radius:12px;padding:25px;">
                            <h4 style="color:#60a5fa;margin-bottom:10px;"><i class="fas fa-chart-pie"></i> Summary Report</h4>
                            <p style="color:#94a3b8;font-size:0.9rem;margin-bottom:20px;">Export summary statistics including pass/fail rates.</p>
                            <button class="btn btn-info"><i class="fas fa-download"></i> Export Summary</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
    </main>
</div>

<script>
// Calculate average and scaled marks
function calcMarks(criteriaId, percent) {
    var initial = parseFloat(document.querySelector('input[name="initial_work[' + criteriaId + ']"]').value) || 0;
    var final = parseFloat(document.querySelector('input[name="final_work[' + criteriaId + ']"]').value) || 0;
    var moderator = parseFloat(document.querySelector('input[name="moderator_mark[' + criteriaId + ']"]').value) || 0;
    
    var values = [initial, final, moderator].filter(function(v) { return v > 0; });
    var avg = values.length > 0 ? values.reduce(function(a, b) { return a + b; }, 0) / values.length : 0;
    var scaled = (avg * percent) / 100;
    
    document.getElementById('avg_' + criteriaId).value = avg.toFixed(2);
    document.getElementById('scaled_' + criteriaId).value = scaled.toFixed(2);
    
    updateTotal();
}

function updateTotal() {
    var total = 0;
    document.querySelectorAll('.scaled-mark').forEach(function(input) {
        total += parseFloat(input.value) || 0;
    });
    var finalInput = document.getElementById('finalTotal');
    if (finalInput) finalInput.value = total.toFixed(2);
}

function exportToCSV() {
    var students = <?= json_encode($students); ?>;
    var csv = 'Student ID,Name,Project,Supervisor,Total Mark,Supervisor Mark,Moderator Mark\n';
    
    students.forEach(function(s) {
        csv += '"' + (s.fyp_studid || '') + '",';
        csv += '"' + (s.fyp_studname || '') + '",';
        csv += '"' + (s.fyp_projecttitle || '') + '",';
        csv += '"' + (s.supervisor_name || '') + '",';
        csv += (s.fyp_totalmark || '') + ',';
        csv += (s.fyp_totalfinalsupervisor || '') + ',';
        csv += (s.fyp_totalfinalmoderator || '') + '\n';
    });
    
    var blob = new Blob([csv], { type: 'text/csv' });
    var url = window.URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = 'student_marks_' + new Date().toISOString().slice(0,10) + '.csv';
    a.click();
}

document.addEventListener('DOMContentLoaded', function() { updateTotal(); });
</script>

</body>
</html>