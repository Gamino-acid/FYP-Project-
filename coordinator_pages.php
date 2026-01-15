<?php
/**
 * Coordinator Main Page - Part 4
 * Page Content: Dashboard, Registrations, Group Requests, Students, Supervisors, Pairing, Projects
 */

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

<?php // ==================== STUDENT REGISTRATIONS ====================
elseif ($current_page === 'registrations'): 
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
                <p style="color:#94a3b8;margin-bottom:15px;">Share this file with students so they can login to their accounts.</p>
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
            <?php if (empty($pending_only)): ?>
                <div class="empty-state"><i class="fas fa-inbox"></i><p>No pending registrations</p></div>
            <?php else: ?>
                <form method="POST" id="bulkForm">
                    <div class="bulk-actions" style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:15px;padding:15px;background:rgba(26,26,46,0.6);border-radius:12px;align-items:center;">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <input type="checkbox" id="selectAll" onclick="toggleSelectAll()" style="width:18px;height:18px;cursor:pointer;">
                            <label for="selectAll" style="color:#94a3b8;cursor:pointer;margin:0;">Select All</label>
                        </div>
                        <span style="color:#4a4a6a;">|</span>
                        <span id="selectedCount" style="color:#a78bfa;font-weight:600;">0 selected</span>
                        <div style="flex:1;"></div>
                        <button type="submit" name="bulk_approve" class="btn btn-success" onclick="return confirmBulkAction('approve')"><i class="fas fa-check-double"></i> Approve Selected</button>
                        <button type="button" class="btn btn-danger" onclick="openBulkRejectModal()"><i class="fas fa-times"></i> Reject Selected</button>
                    </div>
                    
                    <table class="data-table" id="pendingTable">
                        <thead>
                            <tr>
                                <th style="width:40px;text-align:center;"><i class="fas fa-check-square" style="color:#a78bfa;"></i></th>
                                <th>Email</th><th>Student ID</th><th>Name</th><th>Programme</th><th>Type</th><th>Submitted</th><th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_only as $index => $reg): ?>
                            <tr data-name="<?= htmlspecialchars(strtolower($reg['studname'])); ?>" data-email="<?= htmlspecialchars(strtolower($reg['email'])); ?>" data-id="<?= htmlspecialchars(strtolower($reg['studfullid'])); ?>" data-date="<?= strtotime($reg['created_at']); ?>">
                                <td style="text-align:center;"><input type="checkbox" name="selected_regs[]" value="<?= $reg['id']; ?>" class="reg-checkbox" onclick="updateSelectedCount()" style="width:18px;height:18px;cursor:pointer;"></td>
                                <td><?= htmlspecialchars($reg['email']); ?></td>
                                <td><strong><?= htmlspecialchars($reg['studfullid']); ?></strong></td>
                                <td><?= htmlspecialchars($reg['studname']); ?></td>
                                <td><?= htmlspecialchars($reg['fyp_progname'] ?? '-'); ?></td>
                                <td><span class="badge badge-<?= $reg['group_type'] === 'Individual' ? 'approved' : 'pending'; ?>"><?= htmlspecialchars($reg['group_type']); ?></span></td>
                                <td><?= date('M j, Y', strtotime($reg['created_at'])); ?></td>
                                <td>
                                    <button type="button" class="btn btn-success btn-sm" onclick="approveSingle(<?= $reg['id']; ?>)"><i class="fas fa-check"></i></button>
                                    <button type="button" class="btn btn-danger btn-sm" onclick="showRejectRegModal(<?= $reg['id']; ?>,'<?= htmlspecialchars($reg['studname'], ENT_QUOTES); ?>')"><i class="fas fa-times"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </form>
                <form method="POST" id="singleApproveForm" style="display:none;">
                    <input type="hidden" name="reg_id" id="singleApproveId">
                    <input type="hidden" name="approve_registration" value="1">
                </form>
            <?php endif; ?>
        </div>
    </div>

<?php // ==================== GROUP REQUESTS ====================
elseif ($current_page === 'group_requests'): 
    $all_requests = [];
    $res = $conn->query("SELECT gr.*, sg.group_name, s1.fyp_studname as inviter_name, s1.fyp_studfullid as inviter_fullid, s2.fyp_studname as invitee_name, s2.fyp_studfullid as invitee_fullid
                         FROM group_request gr
                         LEFT JOIN student_group sg ON gr.group_id = sg.group_id
                         LEFT JOIN student s1 ON gr.inviter_id = s1.fyp_studid
                         LEFT JOIN student s2 ON gr.invitee_id = s2.fyp_studid
                         ORDER BY gr.request_status = 'Pending' DESC, gr.request_id DESC");
    if ($res) { while ($row = $res->fetch_assoc()) { $all_requests[] = $row; } }
    $pending_only = array_filter($all_requests, function($r) { return $r['request_status'] === 'Pending'; });
?>
    <div class="card"><div class="card-header"><h3>All Group Requests</h3><span class="badge badge-pending"><?= count($pending_only); ?> Pending</span></div><div class="card-body">
        <?php if (empty($all_requests)): ?>
            <div class="empty-state"><i class="fas fa-inbox"></i><p>No group requests found</p></div>
        <?php else: ?>
            <table class="data-table"><thead><tr><th>ID</th><th>Group</th><th>Inviter</th><th>Invitee</th><th>Current Status</th><th>Change Status</th></tr></thead><tbody>
                <?php foreach ($all_requests as $req): ?>
                <tr>
                    <td><?= $req['request_id']; ?></td>
                    <td><strong><?= htmlspecialchars($req['group_name'] ?? 'Group #' . $req['group_id']); ?></strong></td>
                    <td><?= htmlspecialchars(($req['inviter_fullid'] ?? $req['inviter_id']) . ' - ' . ($req['inviter_name'] ?? '')); ?></td>
                    <td><?= htmlspecialchars(($req['invitee_fullid'] ?? $req['invitee_id']) . ' - ' . ($req['invitee_name'] ?? '')); ?></td>
                    <td><span class="badge badge-<?= $req['request_status'] === 'Accepted' ? 'approved' : ($req['request_status'] === 'Pending' ? 'pending' : 'rejected'); ?>"><?= $req['request_status']; ?></span></td>
                    <td>
                        <form method="POST" style="display:flex;gap:8px;align-items:center;">
                            <input type="hidden" name="request_id" value="<?= $req['request_id']; ?>">
                            <select name="new_status" class="form-control" style="width:auto;padding:6px 10px;">
                                <option value="Pending" <?= $req['request_status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="Accepted" <?= $req['request_status'] === 'Accepted' ? 'selected' : ''; ?>>Accepted</option>
                                <option value="Rejected" <?= $req['request_status'] === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                            <button type="submit" name="update_request_status" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Update</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody></table>
        <?php endif; ?>
    </div></div>

<?php // ==================== MANAGE STUDENTS ====================
elseif ($current_page === 'students'): 
    $students = [];
    $res = $conn->query("SELECT s.*, p.fyp_progname, p.fyp_progid, a.fyp_acdyear, a.fyp_intake, u.fyp_username, pr.fyp_projecttitle, pr.fyp_projectid, pa.fyp_pairingid, pa_pr.fyp_projecttitle as paired_project
                         FROM student s 
                         LEFT JOIN programme p ON s.fyp_progid = p.fyp_progid 
                         LEFT JOIN academic_year a ON s.fyp_academicid = a.fyp_academicid 
                         LEFT JOIN user u ON s.fyp_userid = u.fyp_userid 
                         LEFT JOIN fyp_registration fr ON s.fyp_studid = fr.fyp_studid
                         LEFT JOIN project pr ON fr.fyp_projectid = pr.fyp_projectid
                         LEFT JOIN pairing pa ON s.fyp_studid = pa.fyp_studid
                         LEFT JOIN project pa_pr ON pa.fyp_projectid = pa_pr.fyp_projectid
                         ORDER BY s.fyp_studname");
    if ($res) { while ($row = $res->fetch_assoc()) { $students[] = $row; } }
?>
    <div class="card"><div class="card-header"><h3>All Students (<?= count($students); ?>)</h3></div><div class="card-body">
        <div class="search-box"><input type="text" class="form-control" placeholder="Search students..." id="studentSearch" onkeyup="filterTable('studentSearch','studentTable')"></div>
        <?php if (empty($students)): ?><div class="empty-state"><i class="fas fa-user-graduate"></i><p>No students found</p></div>
        <?php else: ?>
            <div style="overflow-x:auto;"><table class="data-table" id="studentTable"><thead><tr><th>Student ID</th><th>Name</th><th>Programme</th><th>Project</th><th>Group Type</th><th>Email</th><th>Contact</th><th>Actions</th></tr></thead><tbody>
                <?php foreach ($students as $s): 
                    $project_title = $s['fyp_projecttitle'] ?? $s['paired_project'] ?? null;
                ?>
                <tr>
                    <td><?= htmlspecialchars($s['fyp_studfullid']); ?></td>
                    <td><?= htmlspecialchars($s['fyp_studname']); ?></td>
                    <td><?= htmlspecialchars($s['fyp_progname'] ?? '-'); ?></td>
                    <td><?php if ($project_title): ?><?= htmlspecialchars($project_title); ?><?php else: ?><span style="color:#64748b;">No Project</span><?php endif; ?></td>
                    <td><?= $s['fyp_group'] ? htmlspecialchars($s['fyp_group']) : '<span style="color:#64748b;">Individual</span>'; ?></td>
                    <td><?= htmlspecialchars($s['fyp_email'] ?? '-'); ?></td>
                    <td><?= htmlspecialchars($s['fyp_contactno'] ?? '-'); ?></td>
                    <td>
                        <button class="btn btn-primary btn-sm" onclick="openEditStudentModal('<?= $s['fyp_studid']; ?>','<?= htmlspecialchars($s['fyp_studname'], ENT_QUOTES); ?>','<?= htmlspecialchars($s['fyp_email'] ?? '', ENT_QUOTES); ?>','<?= htmlspecialchars($s['fyp_contactno'] ?? '', ENT_QUOTES); ?>','<?= $s['fyp_progid'] ?? 1; ?>','<?= htmlspecialchars($s['fyp_group'] ?? '', ENT_QUOTES); ?>')"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-danger btn-sm" onclick="confirmDeleteStudent('<?= $s['fyp_studid']; ?>','<?= htmlspecialchars($s['fyp_studname'], ENT_QUOTES); ?>')"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </div></div>

<?php // ==================== MANAGE SUPERVISORS ====================
elseif ($current_page === 'supervisors'): 
    $supervisors = [];
    $res = $conn->query("SELECT s.*, u.fyp_username, u.fyp_usertype FROM supervisor s LEFT JOIN user u ON s.fyp_userid = u.fyp_userid ORDER BY s.fyp_name");
    if ($res) { while ($row = $res->fetch_assoc()) { $supervisors[] = $row; } }
?>
    <div class="card"><div class="card-header"><h3>All Supervisors (<?= count($supervisors); ?>)</h3></div><div class="card-body">
        <?php if (empty($supervisors)): ?><div class="empty-state"><i class="fas fa-user-tie"></i><p>No supervisors found</p></div>
        <?php else: ?>
            <div style="overflow-x:auto;"><table class="data-table"><thead><tr><th>ID</th><th>Name</th><th>Room</th><th>Programme</th><th>Email</th><th>Contact</th><th>Specialization</th><th>Moderator</th></tr></thead><tbody>
                <?php foreach ($supervisors as $sup): ?>
                <tr>
                    <td><?= $sup['fyp_supervisorid']; ?></td>
                    <td><?= htmlspecialchars($sup['fyp_name']); ?></td>
                    <td><?= htmlspecialchars($sup['fyp_roomno'] ?? '-'); ?></td>
                    <td><?= htmlspecialchars($sup['fyp_programme'] ?? '-'); ?></td>
                    <td><?= htmlspecialchars($sup['fyp_email'] ?? '-'); ?></td>
                    <td><?= htmlspecialchars($sup['fyp_contactno'] ?? '-'); ?></td>
                    <td><?= htmlspecialchars($sup['fyp_specialization'] ?? '-'); ?></td>
                    <td><?= $sup['fyp_ismoderator'] ? '<span class="badge badge-approved">Yes</span>' : '<span class="badge badge-pending">No</span>'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </div></div>

<?php // ==================== STUDENT-SUPERVISOR PAIRING ====================
elseif ($current_page === 'pairing'): 
    $pairings = [];
    $res = $conn->query("SELECT p.*, pr.fyp_projecttitle, s.fyp_name as supervisor_name, 
                         (SELECT fyp_name FROM supervisor WHERE fyp_supervisorid = p.fyp_moderatorid) as moderator_name, a.fyp_acdyear, a.fyp_intake
                         FROM pairing p 
                         LEFT JOIN project pr ON p.fyp_projectid = pr.fyp_projectid 
                         LEFT JOIN supervisor s ON p.fyp_supervisorid = s.fyp_supervisorid 
                         LEFT JOIN academic_year a ON p.fyp_academicid = a.fyp_academicid 
                         ORDER BY p.fyp_datecreated DESC");
    if ($res) { while ($row = $res->fetch_assoc()) { $pairings[] = $row; } }
    
    $sup_list = [];
    $res = $conn->query("SELECT fyp_supervisorid, fyp_name FROM supervisor ORDER BY fyp_name");
    if ($res) { while ($row = $res->fetch_assoc()) { $sup_list[] = $row; } }
    
    $proj_list = [];
    $res = $conn->query("SELECT fyp_projectid, fyp_projecttitle FROM project ORDER BY fyp_projecttitle");
    if ($res) { while ($row = $res->fetch_assoc()) { $proj_list[] = $row; } }
?>
    <div class="card"><div class="card-header"><h3>Student-Supervisor Pairings (<?= count($pairings); ?>)</h3><button class="btn btn-primary" onclick="openModal('createPairingModal')"><i class="fas fa-plus"></i> Create Pairing</button></div><div class="card-body">
        <?php if (empty($pairings)): ?><div class="empty-state"><i class="fas fa-link"></i><p>No pairings found</p></div>
        <?php else: ?>
            <div style="overflow-x:auto;"><table class="data-table"><thead><tr><th>ID</th><th>Project</th><th>Supervisor</th><th>Moderator</th><th>Type</th><th>Academic Year</th><th>Created</th></tr></thead><tbody>
                <?php foreach ($pairings as $p): ?>
                <tr>
                    <td><?= $p['fyp_pairingid']; ?></td>
                    <td><?= htmlspecialchars($p['fyp_projecttitle'] ?? '-'); ?></td>
                    <td><?= htmlspecialchars($p['supervisor_name'] ?? '-'); ?></td>
                    <td><?= htmlspecialchars($p['moderator_name'] ?? '-'); ?></td>
                    <td><?= htmlspecialchars($p['fyp_type'] ?? '-'); ?></td>
                    <td><?= htmlspecialchars(($p['fyp_acdyear'] ?? '') . ' ' . ($p['fyp_intake'] ?? '')); ?></td>
                    <td><?= $p['fyp_datecreated'] ? date('M j, Y', strtotime($p['fyp_datecreated'])) : '-'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </div></div>

<?php // ==================== MANAGE PROJECTS ====================
elseif ($current_page === 'projects'): 
    $projects = [];
    $res = $conn->query("SELECT p.*, s.fyp_name as supervisor_name, s.fyp_email as supervisor_email,
                                (SELECT COUNT(*) FROM pairing WHERE fyp_projectid = p.fyp_projectid) as student_count
                         FROM project p 
                         LEFT JOIN supervisor s ON p.fyp_supervisorid = s.fyp_supervisorid 
                         ORDER BY p.fyp_datecreated DESC");
    if ($res) { while ($row = $res->fetch_assoc()) { $projects[] = $row; } }
    
    $supervisors_list = [];
    $res = $conn->query("SELECT fyp_supervisorid, fyp_name, fyp_email FROM supervisor ORDER BY fyp_name");
    if ($res) { while ($row = $res->fetch_assoc()) { $supervisors_list[] = $row; } }
    
    $available_count = count(array_filter($projects, function($p) { return ($p['fyp_projectstatus'] ?? '') === 'Available'; }));
    $unavailable_count = count(array_filter($projects, function($p) { return ($p['fyp_projectstatus'] ?? '') === 'Unavailable'; }));
?>
    <div class="stats-grid" style="margin-bottom:25px;">
        <div class="stat-card" style="cursor:default;"><div class="stat-icon purple"><i class="fas fa-folder-open"></i></div><div class="stat-info"><h4><?= count($projects); ?></h4><p>Total Projects</p></div></div>
        <div class="stat-card" style="cursor:default;"><div class="stat-icon green"><i class="fas fa-check-circle"></i></div><div class="stat-info"><h4><?= $available_count; ?></h4><p>Available</p></div></div>
        <div class="stat-card" style="cursor:default;"><div class="stat-icon orange"><i class="fas fa-times-circle"></i></div><div class="stat-info"><h4><?= $unavailable_count; ?></h4><p>Unavailable</p></div></div>
    </div>
    
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-project-diagram"></i> Project Allocation</h3><button class="btn btn-primary" onclick="openModal('addProjectModal')"><i class="fas fa-plus"></i> Add New Project</button></div>
        <div class="card-body">
            <div class="filter-controls" style="display:flex;flex-wrap:wrap;gap:15px;margin-bottom:20px;padding:15px;background:rgba(139,92,246,0.1);border-radius:12px;">
                <div style="flex:1;min-width:200px;">
                    <label style="color:#a78bfa;font-size:0.85rem;display:block;margin-bottom:5px;"><i class="fas fa-search"></i> Search</label>
                    <input type="text" id="projectSearch" class="form-control" placeholder="Search by title, supervisor..." onkeyup="filterProjectTable()">
                </div>
                <div style="min-width:150px;">
                    <label style="color:#a78bfa;font-size:0.85rem;display:block;margin-bottom:5px;"><i class="fas fa-filter"></i> Status</label>
                    <select id="filterProjectStatus" class="form-control" onchange="filterProjectTable()">
                        <option value="all">All Status</option><option value="Available">Available</option><option value="Unavailable">Unavailable</option>
                    </select>
                </div>
                <div style="min-width:150px;">
                    <label style="color:#a78bfa;font-size:0.85rem;display:block;margin-bottom:5px;"><i class="fas fa-tags"></i> Type</label>
                    <select id="filterProjectType" class="form-control" onchange="filterProjectTable()">
                        <option value="all">All Types</option><option value="Research">Research</option><option value="Application">Application</option><option value="Package">Package</option>
                    </select>
                </div>
            </div>
            
            <?php if (empty($projects)): ?>
                <div class="empty-state"><i class="fas fa-folder-open"></i><p>No projects found. Click "Add New Project" to create one.</p></div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="data-table" id="projectTable">
                        <thead><tr><th style="width:50px;">ID</th><th>Project Title</th><th>Type</th><th>Category</th><th>Status</th><th>Supervisor</th><th>Students</th><th>Created</th><th style="width:120px;">Actions</th></tr></thead>
                        <tbody>
                            <?php foreach ($projects as $p): ?>
                            <tr data-title="<?= htmlspecialchars(strtolower($p['fyp_projecttitle'])); ?>" data-status="<?= $p['fyp_projectstatus'] ?? ''; ?>" data-type="<?= $p['fyp_projecttype'] ?? ''; ?>" data-date="<?= strtotime($p['fyp_datecreated'] ?? 'now'); ?>" data-supervisor="<?= htmlspecialchars(strtolower($p['supervisor_name'] ?? '')); ?>">
                                <td><?= $p['fyp_projectid']; ?></td>
                                <td><strong><?= htmlspecialchars($p['fyp_projecttitle']); ?></strong><?php if (!empty($p['fyp_projectdesc'])): ?><br><small style="color:#64748b;"><?= htmlspecialchars(substr($p['fyp_projectdesc'], 0, 80)); ?><?= strlen($p['fyp_projectdesc']) > 80 ? '...' : ''; ?></small><?php endif; ?></td>
                                <td><span class="badge badge-<?= strtolower($p['fyp_projecttype'] ?? 'default'); ?>"><?= $p['fyp_projecttype'] ?? '-'; ?></span></td>
                                <td><?= htmlspecialchars($p['fyp_projectcat'] ?? '-'); ?></td>
                                <td><span class="badge badge-<?= ($p['fyp_projectstatus'] ?? '') === 'Available' ? 'approved' : 'pending'; ?>"><?= $p['fyp_projectstatus'] ?? '-'; ?></span></td>
                                <td><?= htmlspecialchars($p['supervisor_name'] ?? '-'); ?></td>
                                <td style="text-align:center;"><span class="badge" style="background:rgba(139,92,246,0.2);color:#a78bfa;"><?= $p['student_count']; ?>/<?= $p['fyp_maxstudent'] ?? 2; ?></span></td>
                                <td><?= $p['fyp_datecreated'] ? date('M j, Y', strtotime($p['fyp_datecreated'])) : '-'; ?></td>
                                <td>
                                    <button class="btn btn-primary btn-sm" onclick="openEditProjectModal(<?= htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8'); ?>)" title="Edit"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-info btn-sm" onclick="viewProjectDetails(<?= htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8'); ?>)" title="View"><i class="fas fa-eye"></i></button>
                                    <button class="btn btn-danger btn-sm" onclick="confirmDeleteProject(<?= $p['fyp_projectid']; ?>, '<?= htmlspecialchars($p['fyp_projecttitle'], ENT_QUOTES); ?>')" title="Delete"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php endif; ?>

<?php
// Include Part 5
include("Coordinator_modules.php");