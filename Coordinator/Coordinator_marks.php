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

// Get students and supervisors
$students = [];
$supervisors = [];

$res = $conn->query("SELECT * FROM student ORDER BY fyp_studname");
if ($res) { while ($row = $res->fetch_assoc()) { $students[] = $row; } }

$res = $conn->query("SELECT * FROM user WHERE fyp_usertype = 'lecturer' ORDER BY fyp_username");
if ($res) { while ($row = $res->fetch_assoc()) { $supervisors[] = $row; } }

// Handle mark updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_mark'])) {
    $message = "Mark updated successfully!";
    $message_type = 'success';
}
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
        
        .sidebar-mini { width: 250px; height: 100vh; background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%); position: fixed; left: 0; top: 0; border-right: 1px solid rgba(139, 92, 246, 0.1); padding: 20px 0; }
        .sidebar-mini h2 { color: #fff; font-size: 1.1rem; padding: 0 20px 20px; border-bottom: 1px solid rgba(139, 92, 246, 0.1); margin-bottom: 20px; }
        .sidebar-mini ul { list-style: none; }
        .sidebar-mini li a { display: flex; align-items: center; padding: 12px 20px; color: #94a3b8; text-decoration: none; transition: all 0.3s; }
        .sidebar-mini li a:hover, .sidebar-mini li a.active { background: rgba(139, 92, 246, 0.15); color: #fff; border-left: 3px solid #8b5cf6; }
        .sidebar-mini li a i { margin-right: 10px; width: 20px; }
        .back-link { padding: 20px; border-top: 1px solid rgba(139, 92, 246, 0.1); position: absolute; bottom: 0; width: 100%; }
        .back-link a { color: #f87171; text-decoration: none; display: flex; align-items: center; gap: 10px; }
        
        .main-area { margin-left: 250px; padding: 30px; flex: 1; }
        
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header h1 { color: #fff; font-size: 1.5rem; }
        
        .card { background: rgba(26, 26, 46, 0.6); border-radius: 16px; border: 1px solid rgba(139, 92, 246, 0.1); margin-bottom: 25px; }
        .card-header { padding: 20px 25px; border-bottom: 1px solid rgba(139, 92, 246, 0.1); display: flex; justify-content: space-between; align-items: center; }
        .card-header h3 { color: #fff; font-size: 1.1rem; }
        .card-body { padding: 25px; }
        
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-box { background: rgba(139, 92, 246, 0.1); border: 1px solid rgba(139, 92, 246, 0.2); border-radius: 12px; padding: 20px; text-align: center; }
        .stat-box h4 { font-size: 2rem; color: #fff; margin-bottom: 5px; }
        .stat-box p { color: #94a3b8; font-size: 0.85rem; }
        .stat-box.green { background: rgba(16, 185, 129, 0.1); border-color: rgba(16, 185, 129, 0.2); }
        .stat-box.green h4 { color: #34d399; }
        .stat-box.orange { background: rgba(249, 115, 22, 0.1); border-color: rgba(249, 115, 22, 0.2); }
        .stat-box.orange h4 { color: #fb923c; }
        .stat-box.red { background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.2); }
        .stat-box.red h4 { color: #f87171; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; color: #a78bfa; font-size: 0.9rem; margin-bottom: 8px; }
        .form-control { width: 100%; padding: 12px 15px; background: rgba(15, 15, 26, 0.5); border: 1px solid rgba(139, 92, 246, 0.2); border-radius: 8px; color: #fff; font-size: 0.95rem; }
        .form-control:focus { outline: none; border-color: #8b5cf6; }
        
        .filter-row { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
        .filter-row .form-group { margin-bottom: 0; flex: 1; min-width: 200px; }
        
        .btn { padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; font-size: 0.9rem; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s; }
        .btn-primary { background: linear-gradient(135deg, #8b5cf6, #6366f1); color: white; }
        .btn-success { background: linear-gradient(135deg, #10b981, #059669); color: white; }
        .btn-secondary { background: rgba(100, 116, 139, 0.2); color: #94a3b8; }
        .btn-sm { padding: 8px 16px; font-size: 0.85rem; }
        
        .alert { padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); color: #34d399; }
        
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid rgba(139, 92, 246, 0.1); }
        .data-table th { background: rgba(139, 92, 246, 0.1); color: #a78bfa; font-weight: 600; font-size: 0.8rem; text-transform: uppercase; }
        .data-table tr:hover { background: rgba(139, 92, 246, 0.05); }
        
        .mark-input { width: 60px; padding: 8px; background: rgba(15, 15, 26, 0.5); border: 1px solid rgba(139, 92, 246, 0.2); border-radius: 4px; color: #fff; text-align: center; }
        .mark-input:focus { outline: none; border-color: #8b5cf6; }
        
        .grade-badge { padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; }
        .grade-A { background: rgba(16, 185, 129, 0.2); color: #34d399; }
        .grade-B { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
        .grade-C { background: rgba(249, 115, 22, 0.2); color: #fb923c; }
        .grade-D { background: rgba(239, 68, 68, 0.2); color: #f87171; }
        .grade-F { background: rgba(239, 68, 68, 0.3); color: #fca5a5; }
        
        .student-card { background: rgba(139, 92, 246, 0.05); border: 1px solid rgba(139, 92, 246, 0.1); border-radius: 12px; padding: 20px; margin-bottom: 20px; }
        .student-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid rgba(139, 92, 246, 0.1); }
        .student-info h4 { color: #fff; margin-bottom: 5px; }
        .student-info p { color: #94a3b8; font-size: 0.85rem; }
        .student-total { text-align: right; }
        .student-total h3 { font-size: 2rem; color: #a78bfa; }
        .student-total p { color: #64748b; font-size: 0.8rem; }
        
        .marks-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; }
        .mark-box { background: rgba(15, 15, 26, 0.3); border-radius: 8px; padding: 15px; text-align: center; }
        .mark-box label { display: block; color: #94a3b8; font-size: 0.75rem; margin-bottom: 8px; text-transform: uppercase; }
        .mark-box .mark { font-size: 1.5rem; color: #fff; font-weight: 600; }
        .mark-box .max { color: #64748b; font-size: 0.8rem; }
    </style>
</head>
<body>

<div class="container">
    
    <nav class="sidebar-mini">
        <h2><i class="fas fa-calculator"></i> Marks</h2>
        <ul>
            <li><a href="?view=overview" class="<?= $view === 'overview' ? 'active' : ''; ?>"><i class="fas fa-chart-bar"></i> Batch Overview</a></li>
            <li><a href="?view=supervisor" class="<?= $view === 'supervisor' ? 'active' : ''; ?>"><i class="fas fa-user-check"></i> Supervisor Marks</a></li>
            <li><a href="?view=moderator" class="<?= $view === 'moderator' ? 'active' : ''; ?>"><i class="fas fa-user-edit"></i> Moderator Marks</a></li>
            <li><a href="?view=student" class="<?= $view === 'student' ? 'active' : ''; ?>"><i class="fas fa-user-graduate"></i> Student View</a></li>
            <li><a href="?view=final" class="<?= $view === 'final' ? 'active' : ''; ?>"><i class="fas fa-trophy"></i> Final Marks</a></li>
        </ul>
        <div class="back-link">
            <a href="Coordinator_mainpage.php?page=marks"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>
    </nav>
    
    <main class="main-area">
        
        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type; ?>">
                <i class="fas fa-check-circle"></i> <?= $message; ?>
            </div>
        <?php endif; ?>
        
        <!-- BATCH OVERVIEW -->
        <?php if ($view === 'overview'): ?>
            <div class="header">
                <h1>Batch Overview</h1>
                <button class="btn btn-success"><i class="fas fa-file-excel"></i> Export to Excel</button>
            </div>
            
            <div class="stats-row">
                <div class="stat-box">
                    <h4><?= count($students); ?></h4>
                    <p>Total Students</p>
                </div>
                <div class="stat-box green">
                    <h4>0</h4>
                    <p>Passed</p>
                </div>
                <div class="stat-box red">
                    <h4>0</h4>
                    <p>Failed</p>
                </div>
                <div class="stat-box orange">
                    <h4>0</h4>
                    <p>Pending Assessment</p>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3>All Students Marks</h3>
                </div>
                <div class="card-body">
                    <div class="filter-row">
                        <div class="form-group">
                            <label>Filter by Programme</label>
                            <select class="form-control">
                                <option value="">All Programmes</option>
                                <option value="CS">Computer Science</option>
                                <option value="IT">Information Technology</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Filter by Supervisor</label>
                            <select class="form-control">
                                <option value="">All Supervisors</option>
                                <?php foreach ($supervisors as $sup): ?>
                                <option value="<?= $sup['fyp_userid']; ?>"><?= htmlspecialchars($sup['fyp_username']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button class="btn btn-primary"><i class="fas fa-filter"></i> Apply</button>
                        </div>
                    </div>
                    
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Proposal</th>
                                <th>Progress 1</th>
                                <th>Progress 2</th>
                                <th>Final</th>
                                <th>Presentation</th>
                                <th>Total</th>
                                <th>Grade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($students)): ?>
                            <tr><td colspan="9" style="text-align:center;color:#64748b;padding:40px;">No students found</td></tr>
                            <?php else: ?>
                            <?php foreach ($students as $s): ?>
                            <tr>
                                <td><?= htmlspecialchars($s['fyp_studfullid']); ?></td>
                                <td><?= htmlspecialchars($s['fyp_studname']); ?></td>
                                <td>-</td>
                                <td>-</td>
                                <td>-</td>
                                <td>-</td>
                                <td>-</td>
                                <td><strong>-</strong></td>
                                <td><span class="grade-badge">-</span></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        
        <!-- SUPERVISOR MARKS -->
        <?php elseif ($view === 'supervisor'): ?>
            <div class="header">
                <h1>Supervisor Assessment Marks</h1>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3>Mark Entry by Supervisor</h3>
                </div>
                <div class="card-body">
                    <p style="color:#94a3b8;margin-bottom:20px;">View and manage marks entered by supervisors for their assigned students.</p>
                    
                    <div class="filter-row">
                        <div class="form-group">
                            <label>Select Supervisor</label>
                            <select class="form-control">
                                <option value="">-- Select Supervisor --</option>
                                <?php foreach ($supervisors as $sup): ?>
                                <option value="<?= $sup['fyp_userid']; ?>"><?= htmlspecialchars($sup['fyp_username']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Assessment Type</label>
                            <select class="form-control">
                                <option value="">All Types</option>
                                <option value="proposal">Proposal</option>
                                <option value="progress1">Progress Report 1</option>
                                <option value="progress2">Progress Report 2</option>
                                <option value="final">Final Report</option>
                                <option value="presentation">Presentation</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                        </div>
                    </div>
                    
                    <p style="color:#64748b;text-align:center;padding:40px;">Select a supervisor to view their mark entries.</p>
                </div>
            </div>
        
        <!-- MODERATOR MARKS -->
        <?php elseif ($view === 'moderator'): ?>
            <div class="header">
                <h1>Moderator Assessment Marks</h1>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3>Mark Entry by Moderator</h3>
                </div>
                <div class="card-body">
                    <p style="color:#94a3b8;margin-bottom:20px;">View and manage marks entered by moderators for student assessments.</p>
                    
                    <div class="filter-row">
                        <div class="form-group">
                            <label>Select Moderator</label>
                            <select class="form-control">
                                <option value="">-- Select Moderator --</option>
                                <?php foreach ($supervisors as $sup): ?>
                                <option value="<?= $sup['fyp_userid']; ?>"><?= htmlspecialchars($sup['fyp_username']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                        </div>
                    </div>
                    
                    <p style="color:#64748b;text-align:center;padding:40px;">Select a moderator to view their mark entries.</p>
                </div>
            </div>
        
        <!-- STUDENT VIEW -->
        <?php elseif ($view === 'student'): ?>
            <div class="header">
                <h1>Student Overall Marks</h1>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <div class="filter-row">
                        <div class="form-group">
                            <label>Search Student</label>
                            <input type="text" class="form-control" placeholder="Enter student ID or name...">
                        </div>
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sample Student Card -->
            <div class="student-card">
                <div class="student-header">
                    <div class="student-info">
                        <h4>Sample Student</h4>
                        <p>TP055001 | Computer Science | Supervisor: Dr. Sample</p>
                    </div>
                    <div class="student-total">
                        <h3>75.5</h3>
                        <p>Total Mark</p>
                    </div>
                </div>
                <div class="marks-grid">
                    <div class="mark-box">
                        <label>Proposal</label>
                        <div class="mark">12</div>
                        <div class="max">/ 15</div>
                    </div>
                    <div class="mark-box">
                        <label>Progress 1</label>
                        <div class="mark">16</div>
                        <div class="max">/ 20</div>
                    </div>
                    <div class="mark-box">
                        <label>Progress 2</label>
                        <div class="mark">17</div>
                        <div class="max">/ 20</div>
                    </div>
                    <div class="mark-box">
                        <label>Final Report</label>
                        <div class="mark">23</div>
                        <div class="max">/ 30</div>
                    </div>
                    <div class="mark-box">
                        <label>Presentation</label>
                        <div class="mark">7.5</div>
                        <div class="max">/ 15</div>
                    </div>
                </div>
            </div>
        
        <!-- FINAL MARKS -->
        <?php elseif ($view === 'final'): ?>
            <div class="header">
                <h1>Final Marks Calculation</h1>
                <button class="btn btn-success"><i class="fas fa-calculator"></i> Calculate All</button>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3>Final Coursework Marks</h3>
                </div>
                <div class="card-body">
                    <p style="color:#94a3b8;margin-bottom:20px;">Calculate and finalize marks for all students. This combines supervisor and moderator assessments.</p>
                    
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Supervisor Mark</th>
                                <th>Moderator Mark</th>
                                <th>Final Mark</th>
                                <th>Grade</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($students)): ?>
                            <tr><td colspan="8" style="text-align:center;color:#64748b;padding:40px;">No students found</td></tr>
                            <?php else: ?>
                            <?php foreach ($students as $s): ?>
                            <tr>
                                <td><?= htmlspecialchars($s['fyp_studfullid']); ?></td>
                                <td><?= htmlspecialchars($s['fyp_studname']); ?></td>
                                <td>-</td>
                                <td>-</td>
                                <td><strong>-</strong></td>
                                <td><span class="grade-badge">-</span></td>
                                <td><span style="color:#fb923c;">Pending</span></td>
                                <td><button class="btn btn-secondary btn-sm"><i class="fas fa-eye"></i> View</button></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
        
    </main>
</div>

</body>
</html>