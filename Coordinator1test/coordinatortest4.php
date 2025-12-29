<?php
// Part 4 - All Page HTML Content

// ==================== DASHBOARD ====================
if ($current_page === 'dashboard'): ?>
    <div class="welcome-box">
        <h2>Welcome back, <?= htmlspecialchars($user_name); ?>!</h2>
        <p>Manage FYP students, supervisors, pairings, assessments and generate reports.</p>
    </div>
    
    <div class="stats-grid">
        <a href="?page=students" class="stat-card"><div class="stat-icon purple"><i class="fas fa-user-graduate"></i></div><div class="stat-info"><h4><?= $total_students; ?></h4><p>Total Students</p></div></a>
        <a href="?page=supervisors" class="stat-card"><div class="stat-icon blue"><i class="fas fa-user-tie"></i></div><div class="stat-info"><h4><?= $total_supervisors; ?></h4><p>Total Supervisors</p></div></a>
        <a href="?page=group_requests" class="stat-card"><div class="stat-icon orange"><i class="fas fa-user-plus"></i></div><div class="stat-info"><h4><?= $pending_requests; ?></h4><p>Pending Group Requests</p></div></a>
        <a href="?page=projects" class="stat-card"><div class="stat-icon green"><i class="fas fa-folder-open"></i></div><div class="stat-info"><h4><?= $total_projects; ?></h4><p>Total Projects</p></div></a>
        <a href="?page=pairing" class="stat-card"><div class="stat-icon red"><i class="fas fa-link"></i></div><div class="stat-info"><h4><?= $total_pairings; ?></h4><p>Total Pairings</p></div></a>
    </div>
    
    <?php if ($pending_requests > 0): ?>
    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> You have <strong><?= $pending_requests; ?></strong> pending group request(s). <a href="?page=group_requests" style="color:#fb923c;margin-left:10px;">Review Now →</a></div>
    <?php endif; ?>
    
    <div class="card"><div class="card-header"><h3>Quick Actions</h3></div><div class="card-body"><div class="quick-actions">
        <a href="?page=group_requests" class="quick-action"><i class="fas fa-user-plus"></i><h4>Group Requests</h4><p>Approve or reject requests</p></a>
        <a href="?page=pairing" class="quick-action"><i class="fas fa-link"></i><h4>Manage Pairings</h4><p>Assign students to supervisors</p></a>
        <a href="?page=rubrics" class="quick-action"><i class="fas fa-list-check"></i><h4>Assessment Rubrics</h4><p>Create and manage rubrics</p></a>
        <a href="?page=marks" class="quick-action"><i class="fas fa-calculator"></i><h4>View Marks</h4><p>Assessment mark allocation</p></a>
        <a href="?page=reports" class="quick-action"><i class="fas fa-file-alt"></i><h4>Generate Reports</h4><p>Export forms and reports</p></a>
        <a href="?page=announcements" class="quick-action"><i class="fas fa-bullhorn"></i><h4>Announcements</h4><p>Post announcements</p></a>
    </div></div></div>

<?php elseif ($current_page === 'registrations'): 
    $pending_regs = [];
    $res = $conn->query("SELECT pr.*, p.fyp_progname, ay.fyp_acdyear, ay.fyp_intake 
                         FROM pending_registration pr 
                         LEFT JOIN programme p ON pr.programme_id = p.fyp_progid 
                         LEFT JOIN academic_year ay ON pr.academic_year_id = ay.fyp_academicid 
                         ORDER BY pr.status = 'pending' DESC, pr.created_at DESC");
    if ($res) { while ($row = $res->fetch_assoc()) { $pending_regs[] = $row; } }
    
    $pending_only = array_filter($pending_regs, function($r) { return $r['status'] === 'pending'; });
    $pending_only = array_values($pending_only);
    
    $import_results = $_SESSION['import_results'] ?? null;
    $imported_credentials = $_SESSION['imported_credentials'] ?? [];
    if (isset($_SESSION['import_results'])) { unset($_SESSION['import_results']); }
?>
    
    <?php if ($import_results): ?>
    <div class="card" style="margin-bottom:25px;border:2px solid rgba(16,185,129,0.3);">
        <div class="card-header" style="background:rgba(16,185,129,0.1);">
            <h3 style="color:#34d399;"><i class="fas fa-check-circle"></i> Import Results</h3>
            <button class="btn btn-secondary btn-sm" onclick="this.closest('.card').remove()"><i class="fas fa-times"></i> Dismiss</button>
        </div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(150px, 1fr));gap:20px;margin-bottom:20px;">
                <div style="text-align:center;padding:20px;background:rgba(16,185,129,0.1);border-radius:12px;">
                    <div style="font-size:2rem;font-weight:700;color:#34d399;"><?= $import_results['import_count']; ?></div>
                    <div style="color:#94a3b8;font-size:0.9rem;">Successfully Imported</div>
                </div>
                <div style="text-align:center;padding:20px;background:rgba(249,115,22,0.1);border-radius:12px;">
                    <div style="font-size:2rem;font-weight:700;color:#fb923c;"><?= $import_results['skip_count']; ?></div>
                    <div style="color:#94a3b8;font-size:0.9rem;">Skipped (Duplicates)</div>
                </div>
                <div style="text-align:center;padding:20px;background:rgba(239,68,68,0.1);border-radius:12px;">
                    <div style="font-size:2rem;font-weight:700;color:#f87171;"><?= $import_results['error_count']; ?></div>
                    <div style="color:#94a3b8;font-size:0.9rem;">Errors</div>
                </div>
            </div>
            
            <?php if ($import_results['import_count'] > 0 && !empty($imported_credentials)): ?>
            <div style="background:rgba(16,185,129,0.1);padding:20px;border-radius:12px;border:1px solid rgba(16,185,129,0.3);">
                <h4 style="color:#34d399;margin-bottom:10px;"><i class="fas fa-download"></i> Download Credentials Report</h4>
                <p style="color:#94a3b8;margin-bottom:15px;">Share this file with students so they can login.</p>
                <a href="?page=registrations&download_credentials=1" class="btn btn-success"><i class="fas fa-file-excel"></i> Download Credentials (Excel)</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h3>Pending Student Registrations</h3>
            <div style="display:flex;gap:10px;align-items:center;">
                <button type="button" class="btn btn-info" onclick="openModal('importExcelModal')"><i class="fas fa-file-excel"></i> Import Excel</button>
                <button type="button" class="btn btn-primary" onclick="openModal('addStudentModal')"><i class="fas fa-user-plus"></i> Add Student Manually</button>
                <span class="badge badge-pending"><?= count($pending_only); ?> Pending</span>
            </div>
        </div>
        <div class="card-body">
            <?php if (count($pending_only) > 0): ?>
            <form method="POST" id="bulkApproveForm">
                <div style="margin-bottom:15px;display:flex;gap:10px;align-items:center;">
                    <label><input type="checkbox" id="selectAllPending" onchange="toggleSelectAll(this, 'pending_ids[]')"> Select All Pending</label>
                    <button type="submit" name="bulk_approve" class="btn btn-success btn-sm"><i class="fas fa-check"></i> Approve Selected</button>
                    <button type="button" class="btn btn-danger btn-sm" onclick="openBulkRejectModal()"><i class="fas fa-times"></i> Reject Selected</button>
                    <button type="button" class="btn btn-info btn-sm" onclick="openCredentialsModal()"><i class="fas fa-download"></i> Download Credentials</button>
                </div>
            <?php endif; ?>
            
            <div style="overflow-x:auto;">
                <table class="data-table" id="registrationsTable">
                    <thead><tr>
                        <th width="40">#</th>
                        <th>Student ID</th>
                        <th>Full ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Programme</th>
                        <th>Group</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($pending_regs as $reg): ?>
                        <tr>
                            <td><?php if ($reg['status'] === 'pending'): ?><input type="checkbox" name="pending_ids[]" value="<?= $reg['id']; ?>"><?php endif; ?></td>
                            <td><?= htmlspecialchars($reg['studid']); ?></td>
                            <td><?= htmlspecialchars($reg['studfullid']); ?></td>
                            <td><strong><?= htmlspecialchars($reg['studname']); ?></strong></td>
                            <td><?= htmlspecialchars($reg['email']); ?></td>
                            <td><?= htmlspecialchars($reg['fyp_progname'] ?? '-'); ?></td>
                            <td><?= htmlspecialchars($reg['group_type'] ?? '-'); ?></td>
                            <td><span class="badge badge-<?= $reg['status'] === 'approved' ? 'approved' : ($reg['status'] === 'pending' ? 'pending' : 'rejected'); ?>"><?= ucfirst($reg['status']); ?></span></td>
                            <td>
                                <?php if ($reg['status'] === 'pending'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="reg_id" value="<?= $reg['id']; ?>">
                                    <button type="submit" name="approve_registration" class="btn btn-success btn-sm"><i class="fas fa-check"></i></button>
                                </form>
                                <button class="btn btn-danger btn-sm" onclick="showRejectModal(<?= $reg['id']; ?>, '<?= htmlspecialchars($reg['studname']); ?>')"><i class="fas fa-times"></i></button>
                                <?php else: ?>
                                <span style="color:#64748b;font-size:0.85rem;"><?= $reg['processed_at'] ? date('M j, Y', strtotime($reg['processed_at'])) : '-'; ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (count($pending_only) > 0): ?></form><?php endif; ?>
        </div>
    </div>

<?php elseif ($current_page === 'group_requests'): 
    $all_requests = [];
    $res = $conn->query("SELECT gr.*, sg.group_name, 
        s1.fyp_studname as inviter_name, s1.fyp_studfullid as inviter_fullid, 
        s2.fyp_studname as invitee_name, s2.fyp_studfullid as invitee_fullid 
        FROM group_request gr 
        LEFT JOIN student_group sg ON gr.group_id = sg.group_id 
        LEFT JOIN student s1 ON gr.inviter_id = s1.fyp_studid 
        LEFT JOIN student s2 ON gr.invitee_id = s2.fyp_studid 
        ORDER BY gr.request_status = 'Pending' DESC, gr.request_id DESC");
    if ($res) { while ($row = $res->fetch_assoc()) { $all_requests[] = $row; } }
    $pending_only_req = array_filter($all_requests, function($r) { return $r['request_status'] === 'Pending'; });
?>
    <div class="card">
        <div class="card-header">
            <h3>All Group Requests</h3>
            <span class="badge badge-pending"><?= count($pending_only_req); ?> Pending</span>
        </div>
        <div class="card-body">
            <?php if (empty($all_requests)): ?>
                <div class="empty-state"><i class="fas fa-inbox"></i><p>No group requests found</p></div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="data-table">
                        <thead><tr><th>ID</th><th>Group</th><th>Inviter</th><th>Invitee</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php foreach ($all_requests as $req): ?>
                            <tr>
                                <td><?= $req['request_id']; ?></td>
                                <td><strong><?= htmlspecialchars($req['group_name'] ?? 'Group #' . $req['group_id']); ?></strong></td>
                                <td><?= htmlspecialchars(($req['inviter_fullid'] ?? '') . ' - ' . ($req['inviter_name'] ?? '')); ?></td>
                                <td><?= htmlspecialchars(($req['invitee_fullid'] ?? '') . ' - ' . ($req['invitee_name'] ?? '')); ?></td>
                                <td><span class="badge badge-<?= $req['request_status'] === 'Accepted' ? 'approved' : ($req['request_status'] === 'Pending' ? 'pending' : 'rejected'); ?>"><?= $req['request_status']; ?></span></td>
                                <td>
                                    <form method="POST" style="display:flex;gap:8px;align-items:center;">
                                        <input type="hidden" name="request_id" value="<?= $req['request_id']; ?>">
                                        <select name="new_status" class="form-control" style="width:auto;padding:6px 10px;">
                                            <option value="Pending" <?= $req['request_status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="Accepted" <?= $req['request_status'] === 'Accepted' ? 'selected' : ''; ?>>Accepted</option>
                                            <option value="Rejected" <?= $req['request_status'] === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                                        </select>
                                        <button type="submit" name="update_request_status" class="btn btn-primary btn-sm"><i class="fas fa-save"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($current_page === 'students'): 
    $students = [];
    $res = $conn->query("SELECT s.*, p.fyp_progname, ay.fyp_acdyear, ay.fyp_intake 
                         FROM student s 
                         LEFT JOIN programme p ON s.fyp_progid = p.fyp_progid 
                         LEFT JOIN academic_year ay ON s.fyp_academicid = ay.fyp_academicid 
                         ORDER BY s.fyp_studid DESC");
    if ($res) { while ($row = $res->fetch_assoc()) { $students[] = $row; } }
?>
    <div class="card">
        <div class="card-header">
            <h3>All Students</h3>
            <div style="display:flex;gap:10px;align-items:center;">
                <input type="text" id="studentSearch" class="form-control" placeholder="Search students..." onkeyup="filterTable('studentSearch', 'studentsTable')" style="width:250px;">
                <span class="badge badge-approved"><?= count($students); ?> Total</span>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($students)): ?>
                <div class="empty-state"><i class="fas fa-user-graduate"></i><p>No students found</p></div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="data-table" id="studentsTable">
                        <thead><tr><th>ID</th><th>Full ID</th><th>Name</th><th>Email</th><th>Programme</th><th>Group Type</th><th>Academic Year</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php foreach ($students as $s): ?>
                            <tr>
                                <td><?= htmlspecialchars($s['fyp_studid']); ?></td>
                                <td><?= htmlspecialchars($s['fyp_studfullid']); ?></td>
                                <td><strong><?= htmlspecialchars($s['fyp_studname']); ?></strong></td>
                                <td><?= htmlspecialchars($s['fyp_email']); ?></td>
                                <td><?= htmlspecialchars($s['fyp_progname'] ?? '-'); ?></td>
                                <td><?= htmlspecialchars($s['fyp_group'] ?? '-'); ?></td>
                                <td><?= htmlspecialchars(($s['fyp_acdyear'] ?? '') . ' ' . ($s['fyp_intake'] ?? '')); ?></td>
                                <td>
                                    <button class="btn btn-info btn-sm" onclick="showEditStudentModal('<?= $s['fyp_studid']; ?>', '<?= htmlspecialchars($s['fyp_studname']); ?>', '<?= htmlspecialchars($s['fyp_email']); ?>')"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-danger btn-sm" onclick="showDeleteStudentModal('<?= $s['fyp_studid']; ?>', '<?= htmlspecialchars($s['fyp_studname']); ?>')"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($current_page === 'supervisors'): 
    $supervisors = [];
    $res = $conn->query("SELECT * FROM supervisor ORDER BY fyp_name");
    if ($res) { while ($row = $res->fetch_assoc()) { $supervisors[] = $row; } }
?>
    <div class="card">
        <div class="card-header">
            <h3>All Supervisors</h3>
            <div style="display:flex;gap:10px;align-items:center;">
                <input type="text" id="supervisorSearch" class="form-control" placeholder="Search supervisors..." onkeyup="filterTable('supervisorSearch', 'supervisorsTable')" style="width:250px;">
                <span class="badge badge-approved"><?= count($supervisors); ?> Total</span>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($supervisors)): ?>
                <div class="empty-state"><i class="fas fa-user-tie"></i><p>No supervisors found</p></div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="data-table" id="supervisorsTable">
                        <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Department</th><th>Specialization</th><th>Created</th></tr></thead>
                        <tbody>
                        <?php foreach ($supervisors as $sup): ?>
                            <tr>
                                <td><?= htmlspecialchars($sup['fyp_supervisorid']); ?></td>
                                <td><strong><?= htmlspecialchars($sup['fyp_name']); ?></strong></td>
                                <td><?= htmlspecialchars($sup['fyp_email'] ?? '-'); ?></td>
                                <td><?= htmlspecialchars($sup['fyp_department'] ?? '-'); ?></td>
                                <td><?= htmlspecialchars($sup['fyp_specialization'] ?? '-'); ?></td>
                                <td><?= $sup['fyp_datecreated'] ? date('M j, Y', strtotime($sup['fyp_datecreated'])) : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($current_page === 'pairing'): 
    $pairings = [];
    $res = $conn->query("SELECT p.*, 
        sup.fyp_name as supervisor_name, 
        mod_sup.fyp_name as moderator_name,
        proj.fyp_projecttitle,
        s.fyp_studname, s.fyp_studfullid,
        ay.fyp_acdyear, ay.fyp_intake
        FROM pairing p
        LEFT JOIN supervisor sup ON p.fyp_supervisorid = sup.fyp_supervisorid
        LEFT JOIN supervisor mod_sup ON p.fyp_moderatorid = mod_sup.fyp_supervisorid
        LEFT JOIN project proj ON p.fyp_projectid = proj.fyp_projectid
        LEFT JOIN student s ON p.fyp_studid = s.fyp_studid
        LEFT JOIN academic_year ay ON p.fyp_academicid = ay.fyp_academicid
        ORDER BY p.fyp_pairingid DESC");
    if ($res) { while ($row = $res->fetch_assoc()) { $pairings[] = $row; } }
    
    $sup_list = [];
    $res = $conn->query("SELECT fyp_supervisorid, fyp_name FROM supervisor ORDER BY fyp_name");
    if ($res) { while ($row = $res->fetch_assoc()) { $sup_list[] = $row; } }
    
    $proj_list = [];
    $res = $conn->query("SELECT fyp_projectid, fyp_projecttitle FROM project ORDER BY fyp_projecttitle");
    if ($res) { while ($row = $res->fetch_assoc()) { $proj_list[] = $row; } }
?>
    <div class="card">
        <div class="card-header">
            <h3>All Pairings</h3>
            <div style="display:flex;gap:10px;align-items:center;">
                <button type="button" class="btn btn-primary" onclick="openModal('createPairingModal')"><i class="fas fa-plus"></i> New Pairing</button>
                <span class="badge badge-approved"><?= count($pairings); ?> Total</span>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($pairings)): ?>
                <div class="empty-state"><i class="fas fa-link"></i><p>No pairings found</p></div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="data-table" id="pairingsTable">
                        <thead><tr><th>ID</th><th>Student</th><th>Project</th><th>Supervisor</th><th>Moderator</th><th>Type</th><th>Academic Year</th></tr></thead>
                        <tbody>
                        <?php foreach ($pairings as $pair): ?>
                            <tr>
                                <td><?= $pair['fyp_pairingid']; ?></td>
                                <td><?= htmlspecialchars(($pair['fyp_studfullid'] ?? '') . ' - ' . ($pair['fyp_studname'] ?? '-')); ?></td>
                                <td><strong><?= htmlspecialchars($pair['fyp_projecttitle'] ?? '-'); ?></strong></td>
                                <td><?= htmlspecialchars($pair['supervisor_name'] ?? '-'); ?></td>
                                <td><?= htmlspecialchars($pair['moderator_name'] ?? '-'); ?></td>
                                <td><span class="badge badge-<?= ($pair['fyp_type'] ?? '') === 'Group' ? 'pending' : 'approved'; ?>"><?= $pair['fyp_type'] ?? '-'; ?></span></td>
                                <td><?= htmlspecialchars(($pair['fyp_acdyear'] ?? '') . ' ' . ($pair['fyp_intake'] ?? '')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($current_page === 'projects'): 
    $projects = [];
    $res = $conn->query("SELECT p.*, sup.fyp_name as supervisor_name, ay.fyp_acdyear, ay.fyp_intake 
                         FROM project p 
                         LEFT JOIN supervisor sup ON p.fyp_supervisorid = sup.fyp_supervisorid 
                         LEFT JOIN academic_year ay ON p.fyp_academicid = ay.fyp_academicid 
                         ORDER BY p.fyp_projectid DESC");
    if ($res) { while ($row = $res->fetch_assoc()) { $projects[] = $row; } }
    
    $sup_list = [];
    $res = $conn->query("SELECT fyp_supervisorid, fyp_name FROM supervisor ORDER BY fyp_name");
    if ($res) { while ($row = $res->fetch_assoc()) { $sup_list[] = $row; } }
?>
    <div class="card">
        <div class="card-header">
            <h3>All Projects</h3>
            <div style="display:flex;gap:10px;align-items:center;">
                <button type="button" class="btn btn-primary" onclick="openModal('addProjectModal')"><i class="fas fa-plus"></i> Add Project</button>
                <input type="text" id="projectSearch" class="form-control" placeholder="Search projects..." onkeyup="filterTable('projectSearch', 'projectsTable')" style="width:250px;">
                <span class="badge badge-approved"><?= count($projects); ?> Total</span>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($projects)): ?>
                <div class="empty-state"><i class="fas fa-folder-open"></i><p>No projects found</p></div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="data-table" id="projectsTable">
                        <thead><tr><th>ID</th><th>Title</th><th>Supervisor</th><th>Status</th><th>Academic Year</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php foreach ($projects as $proj): ?>
                            <tr>
                                <td><?= $proj['fyp_projectid']; ?></td>
                                <td><strong><?= htmlspecialchars($proj['fyp_projecttitle']); ?></strong></td>
                                <td><?= htmlspecialchars($proj['supervisor_name'] ?? '-'); ?></td>
                                <td><span class="badge badge-<?= ($proj['fyp_projectstatus'] ?? '') === 'Active' ? 'approved' : 'pending'; ?>"><?= $proj['fyp_projectstatus'] ?? '-'; ?></span></td>
                                <td><?= htmlspecialchars(($proj['fyp_acdyear'] ?? '') . ' ' . ($proj['fyp_intake'] ?? '')); ?></td>
                                <td>
                                    <button class="btn btn-info btn-sm" onclick="showEditProjectModal(<?= $proj['fyp_projectid']; ?>, '<?= htmlspecialchars(addslashes($proj['fyp_projecttitle'])); ?>')"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-danger btn-sm" onclick="showDeleteProjectModal(<?= $proj['fyp_projectid']; ?>, '<?= htmlspecialchars(addslashes($proj['fyp_projecttitle'])); ?>')"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($current_page === 'moderation'): 
    $moderation_records = [];
    $res = $conn->query("SELECT m.*, s.fyp_studname, s.fyp_studfullid, proj.fyp_projecttitle, sup.fyp_name as supervisor_name, mod_sup.fyp_name as moderator_name
        FROM moderation m
        LEFT JOIN student s ON m.fyp_studid = s.fyp_studid
        LEFT JOIN project proj ON m.fyp_projectid = proj.fyp_projectid
        LEFT JOIN supervisor sup ON m.fyp_supervisorid = sup.fyp_supervisorid
        LEFT JOIN supervisor mod_sup ON m.fyp_moderatorid = mod_sup.fyp_supervisorid
        ORDER BY m.fyp_moderationid DESC");
    if ($res) { while ($row = $res->fetch_assoc()) { $moderation_records[] = $row; } }
?>
    <div class="card">
        <div class="card-header">
            <h3>Moderation Records</h3>
            <span class="badge badge-approved"><?= count($moderation_records); ?> Total</span>
        </div>
        <div class="card-body">
            <?php if (empty($moderation_records)): ?>
                <div class="empty-state"><i class="fas fa-balance-scale"></i><p>No moderation records found</p></div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="data-table">
                        <thead><tr><th>ID</th><th>Student</th><th>Project</th><th>Supervisor</th><th>Moderator</th><th>Status</th><th>Date</th></tr></thead>
                        <tbody>
                        <?php foreach ($moderation_records as $mod): ?>
                            <tr>
                                <td><?= $mod['fyp_moderationid']; ?></td>
                                <td><?= htmlspecialchars(($mod['fyp_studfullid'] ?? '') . ' - ' . ($mod['fyp_studname'] ?? '')); ?></td>
                                <td><strong><?= htmlspecialchars($mod['fyp_projecttitle'] ?? '-'); ?></strong></td>
                                <td><?= htmlspecialchars($mod['supervisor_name'] ?? '-'); ?></td>
                                <td><?= htmlspecialchars($mod['moderator_name'] ?? '-'); ?></td>
                                <td><span class="badge badge-<?= ($mod['fyp_status'] ?? '') === 'Completed' ? 'approved' : 'pending'; ?>"><?= $mod['fyp_status'] ?? 'Pending'; ?></span></td>
                                <td><?= $mod['fyp_datecreated'] ? date('M j, Y', strtotime($mod['fyp_datecreated'])) : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($current_page === 'rubrics'): 
    $assessment_sets = [];
    $res = $conn->query("SELECT s.*, ay.fyp_acdyear, ay.fyp_intake, (SELECT COUNT(*) FROM item WHERE fyp_setid = s.fyp_setid) as item_count 
                         FROM `set` s 
                         LEFT JOIN academic_year ay ON s.fyp_academicid = ay.fyp_academicid 
                         ORDER BY s.fyp_setid DESC");
    if ($res) { while ($row = $res->fetch_assoc()) { $assessment_sets[] = $row; } }
?>
    <div class="card">
        <div class="card-header">
            <h3>Assessment Sets (Rubrics)</h3>
            <button type="button" class="btn btn-primary" onclick="openModal('createSetModal')"><i class="fas fa-plus"></i> Create Set</button>
        </div>
        <div class="card-body">
            <?php if (empty($assessment_sets)): ?>
                <div class="empty-state"><i class="fas fa-list-check"></i><p>No assessment sets found</p></div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="data-table">
                        <thead><tr><th>ID</th><th>Set Name</th><th>Academic Year</th><th>Items</th><th>Created</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php foreach ($assessment_sets as $set): ?>
                            <tr>
                                <td><?= $set['fyp_setid']; ?></td>
                                <td><strong><?= htmlspecialchars($set['fyp_setname']); ?></strong></td>
                                <td><?= htmlspecialchars(($set['fyp_acdyear'] ?? '') . ' ' . ($set['fyp_intake'] ?? '')); ?></td>
                                <td><span class="badge badge-approved"><?= $set['item_count']; ?> items</span></td>
                                <td><?= $set['fyp_datecreated'] ? date('M j, Y', strtotime($set['fyp_datecreated'])) : '-'; ?></td>
                                <td>
                                    <a href="Coordinator_rubrics.php?set_id=<?= $set['fyp_setid']; ?>" class="btn btn-info btn-sm"><i class="fas fa-cog"></i> Manage</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($current_page === 'marks'): 
    $marks = [];
    $res = $conn->query("SELECT tm.*, s.fyp_studname, s.fyp_studfullid, proj.fyp_projecttitle, ay.fyp_acdyear, ay.fyp_intake
        FROM total_mark tm
        LEFT JOIN student s ON tm.fyp_studid = s.fyp_studid
        LEFT JOIN project proj ON tm.fyp_projectid = proj.fyp_projectid
        LEFT JOIN academic_year ay ON tm.fyp_academicid = ay.fyp_academicid
        ORDER BY tm.fyp_studid DESC");
    if ($res) { while ($row = $res->fetch_assoc()) { $marks[] = $row; } }
    
    $students_for_marks = [];
    $res = $conn->query("SELECT fyp_studid, fyp_studfullid, fyp_studname FROM student ORDER BY fyp_studname");
    if ($res) { while ($row = $res->fetch_assoc()) { $students_for_marks[] = $row; } }
?>
    <div class="card">
        <div class="card-header">
            <h3>Assessment Marks</h3>
            <div style="display:flex;gap:10px;align-items:center;">
                <button type="button" class="btn btn-primary" onclick="openModal('addMarkModal')"><i class="fas fa-plus"></i> Add Mark</button>
                <a href="Coordinator_marks.php" class="btn btn-info"><i class="fas fa-table"></i> Detailed View</a>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($marks)): ?>
                <div class="empty-state"><i class="fas fa-calculator"></i><p>No assessment marks found</p></div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="data-table" id="marksTable">
                        <thead><tr><th>Student ID</th><th>Student Name</th><th>Project</th><th>Supervisor Mark</th><th>Moderator Mark</th><th>Total</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php foreach ($marks as $m): ?>
                            <tr>
                                <td><?= htmlspecialchars($m['fyp_studfullid'] ?? $m['fyp_studid']); ?></td>
                                <td><strong><?= htmlspecialchars($m['fyp_studname'] ?? '-'); ?></strong></td>
                                <td><?= htmlspecialchars($m['fyp_projecttitle'] ?? '-'); ?></td>
                                <td><?= number_format($m['fyp_totalfinalsupervisor'] ?? 0, 2); ?>%</td>
                                <td><?= number_format($m['fyp_totalfinalmoderator'] ?? 0, 2); ?>%</td>
                                <td><strong style="color:#10b981;"><?= number_format($m['fyp_totalmark'] ?? 0, 2); ?>%</strong></td>
                                <td>
                                    <button class="btn btn-info btn-sm" onclick="showEditMarkModal('<?= $m['fyp_studid']; ?>', '<?= htmlspecialchars($m['fyp_studname'] ?? ''); ?>', <?= $m['fyp_totalfinalsupervisor'] ?? 0; ?>, <?= $m['fyp_totalfinalmoderator'] ?? 0; ?>)"><i class="fas fa-edit"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($current_page === 'reports'): ?>
    <div class="card">
        <div class="card-header"><h3>Report Generation</h3></div>
        <div class="card-body">
            <div class="quick-actions">
                <a href="Coordinator_report.php" class="quick-action"><i class="fas fa-file-pdf"></i><h4>Generate Reports</h4><p>Create assessment forms, mark sheets, and export data</p></a>
                <a href="Coordinator_report.php?type=form1" class="quick-action"><i class="fas fa-file-alt"></i><h4>Form 1</h4><p>Supervisor Assessment Form</p></a>
                <a href="Coordinator_report.php?type=form2" class="quick-action"><i class="fas fa-file-alt"></i><h4>Form 2</h4><p>Moderator Assessment Form</p></a>
                <a href="Coordinator_report.php?type=form3" class="quick-action"><i class="fas fa-file-word"></i><h4>Form 3</h4><p>Moderation Form (Word)</p></a>
                <a href="Coordinator_report.php?type=marks" class="quick-action"><i class="fas fa-table"></i><h4>Mark Sheet</h4><p>Export marks to Excel</p></a>
                <a href="Coordinator_report.php?type=workload" class="quick-action"><i class="fas fa-chart-bar"></i><h4>Workload Report</h4><p>Supervisor workload analysis</p></a>
            </div>
        </div>
    </div>

<?php elseif ($current_page === 'announcements'): 
    $announcements = [];
    $res = $conn->query("SELECT * FROM announcement ORDER BY fyp_datecreated DESC");
    if ($res) { while ($row = $res->fetch_assoc()) { $announcements[] = $row; } }
?>
    <div class="card">
        <div class="card-header">
            <h3>Announcements</h3>
            <button type="button" class="btn btn-primary" onclick="openModal('createAnnouncementModal')"><i class="fas fa-plus"></i> New Announcement</button>
        </div>
        <div class="card-body">
            <?php if (empty($announcements)): ?>
                <div class="empty-state"><i class="fas fa-bullhorn"></i><p>No announcements found</p></div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="data-table">
                        <thead><tr><th>ID</th><th>Subject</th><th>Description</th><th>Receiver</th><th>Posted By</th><th>Date</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php foreach ($announcements as $ann): ?>
                            <tr>
                                <td><?= $ann['fyp_announcementid']; ?></td>
                                <td><strong><?= htmlspecialchars($ann['fyp_subject']); ?></strong></td>
                                <td><?= htmlspecialchars(substr($ann['fyp_description'], 0, 100)) . (strlen($ann['fyp_description']) > 100 ? '...' : ''); ?></td>
                                <td><span class="badge badge-approved"><?= htmlspecialchars($ann['fyp_receiver']); ?></span></td>
                                <td><?= htmlspecialchars($ann['fyp_supervisorid']); ?></td>
                                <td><?= $ann['fyp_datecreated'] ? date('M j, Y H:i', strtotime($ann['fyp_datecreated'])) : '-'; ?></td>
                                <td>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this announcement?');">
                                        <input type="hidden" name="announcement_id" value="<?= $ann['fyp_announcementid']; ?>">
                                        <button type="submit" name="delete_announcement" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($current_page === 'settings'): 
    $maintenance = [];
    $res = $conn->query("SELECT * FROM fyp_maintenance ORDER BY fyp_category, fyp_subject");
    if ($res) { while ($row = $res->fetch_assoc()) { $maintenance[] = $row; } }
?>
    <div class="card"><div class="card-header"><h3>System Settings</h3></div><div class="card-body">
        <div class="quick-actions">
            <div class="quick-action"><i class="fas fa-calendar-alt"></i><h4>Academic Years</h4><p><?= count($academic_years); ?> years</p></div>
            <div class="quick-action"><i class="fas fa-graduation-cap"></i><h4>Programmes</h4><p><?= count($programmes); ?> programmes</p></div>
            <div class="quick-action"><i class="fas fa-sliders-h"></i><h4>Maintenance</h4><p><?= count($maintenance); ?> settings</p></div>
            <div class="quick-action"><i class="fas fa-database"></i><h4>Backup Data</h4><p>Export database</p></div>
        </div>
    </div></div>
    
    <div class="card"><div class="card-header"><h3>Academic Years</h3></div><div class="card-body">
        <table class="data-table"><thead><tr><th>ID</th><th>Year</th><th>Intake</th><th>Created</th></tr></thead><tbody>
            <?php foreach ($academic_years as $ay): ?>
            <tr><td><?= $ay['fyp_academicid']; ?></td><td><?= htmlspecialchars($ay['fyp_acdyear']); ?></td><td><?= htmlspecialchars($ay['fyp_intake']); ?></td><td><?= $ay['fyp_datecreated'] ? date('M j, Y', strtotime($ay['fyp_datecreated'])) : '-'; ?></td></tr>
            <?php endforeach; ?>
        </tbody></table>
    </div></div>
    
    <div class="card"><div class="card-header"><h3>Programmes</h3></div><div class="card-body">
        <table class="data-table"><thead><tr><th>ID</th><th>Code</th><th>Full Name</th></tr></thead><tbody>
            <?php foreach ($programmes as $p): ?>
            <tr><td><?= $p['fyp_progid']; ?></td><td><?= htmlspecialchars($p['fyp_progname']); ?></td><td><?= htmlspecialchars($p['fyp_prognamefull']); ?></td></tr>
            <?php endforeach; ?>
        </tbody></table>
    </div></div>

<?php endif; ?>

    </div>
</div>

<?php
// Include Part 5 - Modals and JavaScript
include("Coordinator_mainpage_part5.php");