<?php
/**
 * ============================================================
 * COORDINATOR PAGES - COMPLETE FIXED CODE
 * ============================================================
 * File: coordinator_pages.php
 * Description: All page content for coordinator dashboard
 * ============================================================
 */

// ==================== DASHBOARD ====================
if ($current_page === 'dashboard'): ?>

<div class="welcome-box">
    <h2>Welcome back, <?= htmlspecialchars($user_name); ?>!</h2>
    <p>Manage FYP students, supervisors, pairings, assessments and generate reports.</p>
</div>

<div class="stats-grid">
    <a href="?page=students" class="stat-card">
        <div class="stat-icon purple"><i class="fas fa-user-graduate"></i></div>
        <div class="stat-info"><h4><?= $total_students; ?></h4><p>Total Students</p></div>
    </a>
    <a href="?page=supervisors" class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-user-tie"></i></div>
        <div class="stat-info"><h4><?= $total_supervisors; ?></h4><p>Total Supervisors</p></div>
    </a>
    <a href="?page=group_requests" class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-users"></i></div>
        <div class="stat-info"><h4><?= $pending_requests; ?></h4><p>Pending Group Requests</p></div>
    </a>
    <a href="?page=projects" class="stat-card">
        <div class="stat-icon green"><i class="fas fa-folder-open"></i></div>
        <div class="stat-info"><h4><?= $total_projects; ?></h4><p>Total Projects</p></div>
    </a>
    <a href="?page=pairing" class="stat-card">
        <div class="stat-icon red"><i class="fas fa-link"></i></div>
        <div class="stat-info"><h4><?= $total_pairings; ?></h4><p>Total Pairings</p></div>
    </a>
</div>

<?php if (!empty($recent_registered_students)): ?>
<div class="card">
    <div class="card-header"><h3><i class="fas fa-user-check" style="color:#34d399;"></i> Recently Registered Students</h3></div>
    <div class="card-body">
        <table class="data-table">
            <thead><tr><th>Student ID</th><th>Name</th><th>Email</th><th>Type</th><th>Date</th></tr></thead>
            <tbody>
                <?php foreach (array_slice($recent_registered_students, 0, 10) as $student): ?>
                <tr>
                    <td><?= htmlspecialchars($student['fyp_studfullid']); ?></td>
                    <td><?= htmlspecialchars($student['fyp_studname']); ?></td>
                    <td><?= htmlspecialchars($student['fyp_email']); ?></td>
                    <td><span class="badge <?= ($student['fyp_type'] ?? '') === 'Group' ? 'badge-approved' : 'badge-pending'; ?>"><?= $student['fyp_type'] ?? 'Individual'; ?></span></td>
                    <td><?= $student['registered_date'] ? date('M j, Y', strtotime($student['registered_date'])) : '-'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h3>Quick Actions</h3></div>
    <div class="card-body">
        <div class="quick-actions">
            <a href="?page=registrations" class="quick-action"><i class="fas fa-user-plus"></i><h4>Student Registrations</h4><p>Approve pending registrations</p></a>
            <a href="?page=group_requests" class="quick-action"><i class="fas fa-users"></i><h4>Group Requests</h4><p>Approve or reject requests</p></a>
            <a href="?page=pairing" class="quick-action"><i class="fas fa-link"></i><h4>Manage Pairings</h4><p>Assign students to supervisors</p></a>
            <a href="?page=marks" class="quick-action"><i class="fas fa-calculator"></i><h4>Assessment Marks</h4><p>View and manage marks</p></a>
            <a href="?page=reports" class="quick-action"><i class="fas fa-file-alt"></i><h4>Generate Reports</h4><p>Export forms and reports</p></a>
            <a href="?page=announcements" class="quick-action"><i class="fas fa-bullhorn"></i><h4>Announcements</h4><p>Post announcements</p></a>
        </div>
    </div>
</div>

<?php 
// ==================== STUDENT REGISTRATIONS ====================
elseif ($current_page === 'registrations'): 
    $pending_regs = [];
    $res = $conn->query("SELECT pr.*, p.fyp_progname FROM pending_registration pr LEFT JOIN programme p ON pr.programme_id = p.fyp_progid WHERE pr.status = 'pending' ORDER BY pr.created_at DESC");
    if ($res) { while ($row = $res->fetch_assoc()) { $pending_regs[] = $row; } }
    
    $processed_regs = [];
    $res = $conn->query("SELECT pr.*, p.fyp_progname FROM pending_registration pr LEFT JOIN programme p ON pr.programme_id = p.fyp_progid WHERE pr.status != 'pending' ORDER BY pr.processed_at DESC LIMIT 50");
    if ($res) { while ($row = $res->fetch_assoc()) { $processed_regs[] = $row; } }
    
    $recently_approved = [];
    $res = $conn->query("SELECT pr.*, p.fyp_progname FROM pending_registration pr LEFT JOIN programme p ON pr.programme_id = p.fyp_progid WHERE pr.status = 'approved' AND pr.processed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) ORDER BY pr.processed_at DESC LIMIT 20");
    if ($res) { while ($row = $res->fetch_assoc()) { $recently_approved[] = $row; } }
?>
    <div class="tab-nav">
        <button class="tab-btn active" onclick="showRegTab('pending', this)">Pending (<?= count($pending_regs); ?>)</button>
        <button class="tab-btn" onclick="showRegTab('approved', this)">Recently Approved (<?= count($recently_approved); ?>)</button>
        <button class="tab-btn" onclick="showRegTab('processed', this)">All Processed</button>
    </div>
    
    <!-- Pending Tab -->
    <div id="pendingTab" class="tab-content">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-user-clock"></i> Pending Registrations</h3>
                <div style="display:flex;gap:10px;">
                    <button class="btn btn-success btn-sm" onclick="openModal('addStudentModal')"><i class="fas fa-user-plus"></i> Add Student</button>
                    <button class="btn btn-primary btn-sm" onclick="openModal('importExcelModal')"><i class="fas fa-file-excel"></i> Import Excel</button>
                    <a href="download_template.php" class="btn btn-secondary btn-sm"><i class="fas fa-download"></i> Template</a>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($pending_regs)): ?>
                    <div class="empty-state"><i class="fas fa-check-circle"></i><p>No pending registrations</p></div>
                <?php else: ?>
                    <form id="bulkForm" method="POST">
                        <div style="margin-bottom:15px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                            <label style="display:flex;align-items:center;gap:8px;color:#94a3b8;cursor:pointer;">
                                <input type="checkbox" id="selectAll" onclick="toggleSelectAll()"> Select All
                            </label>
                            <span style="color:#64748b;" id="selectedCount">0 selected</span>
                            <div style="margin-left:auto;display:flex;gap:10px;">
                                <button type="submit" name="bulk_approve" class="btn btn-success btn-sm"><i class="fas fa-check-double"></i> Approve Selected</button>
                                <button type="button" class="btn btn-danger btn-sm" onclick="openBulkRejectModal()"><i class="fas fa-times"></i> Reject Selected</button>
                            </div>
                        </div>
                        <div style="overflow-x:auto;">
                            <table class="data-table">
                                <thead><tr><th><input type="checkbox" onclick="toggleSelectAll()"></th><th>Email</th><th>Student ID</th><th>Name</th><th>Programme</th><th>Type</th><th>Date</th><th>Actions</th></tr></thead>
                                <tbody>
                                    <?php foreach ($pending_regs as $reg): ?>
                                    <tr>
                                        <td><input type="checkbox" name="selected_ids[]" value="<?= $reg['id']; ?>" class="reg-checkbox" onchange="updateSelectedCount()"></td>
                                        <td><?= htmlspecialchars($reg['email']); ?></td>
                                        <td><strong><?= htmlspecialchars($reg['studfullid']); ?></strong></td>
                                        <td><?= htmlspecialchars($reg['studname']); ?></td>
                                        <td><?= !empty($reg['fyp_progname']) ? '<span class="badge badge-open">'.htmlspecialchars($reg['fyp_progname']).'</span>' : '<span style="color:#64748b;">-</span>'; ?></td>
                                        <td><span class="badge <?= ($reg['fyp_type'] ?? '') === 'Group' ? 'badge-approved' : 'badge-pending'; ?>"><?= $reg['fyp_type'] ?? 'Individual'; ?></span></td>
                                        <td><?= date('M j, Y', strtotime($reg['created_at'])); ?></td>
                                        <td>
                                            <form method="POST" style="display:inline;"><input type="hidden" name="reg_id" value="<?= $reg['id']; ?>"><button type="submit" name="approve_registration" class="btn btn-success btn-sm" title="Approve"><i class="fas fa-check"></i></button></form>
                                            <button class="btn btn-danger btn-sm" onclick="showRejectRegModal(<?= $reg['id']; ?>,'<?= htmlspecialchars($reg['studname'], ENT_QUOTES); ?>')" title="Reject"><i class="fas fa-times"></i></button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Recently Approved Tab -->
    <div id="approvedTab" class="tab-content" style="display:none;">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-check-circle" style="color:#34d399;"></i> Recently Approved Students</h3>
                <span class="badge badge-approved"><?= count($recently_approved); ?> Approved</span>
            </div>
            <div class="card-body">
                <?php if (empty($recently_approved)): ?>
                    <div class="empty-state"><i class="fas fa-inbox"></i><p>No recently approved students</p></div>
                <?php else: ?>
                    <div class="alert alert-info" style="background:rgba(59,130,246,0.1);border:1px solid rgba(59,130,246,0.3);border-radius:8px;padding:15px;margin-bottom:20px;">
                        <i class="fas fa-info-circle" style="color:#3b82f6;"></i> Students can now complete registration by setting their password.
                    </div>
                    <div style="overflow-x:auto;">
                        <table class="data-table">
                            <thead><tr><th>Student ID</th><th>Full Name</th><th>Email</th><th>Programme</th><th>Approved Date</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php foreach ($recently_approved as $student): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($student['studfullid']); ?></strong></td>
                                    <td><?= htmlspecialchars($student['studname']); ?></td>
                                    <td><?= htmlspecialchars($student['email']); ?></td>
                                    <td><?= !empty($student['fyp_progname']) ? '<span class="badge badge-open">'.htmlspecialchars($student['fyp_progname']).'</span>' : '-'; ?></td>
                                    <td><?= date('M d, Y g:i A', strtotime($student['processed_at'])); ?></td>
                                    <td><span class="badge badge-pending" style="background:rgba(245,158,11,0.2);color:#f59e0b;">Pending Setup</span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Processed Tab -->
    <div id="processedTab" class="tab-content" style="display:none;">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> All Processed Registrations</h3>
                <select id="filterStatus" class="form-control" style="width:150px;" onchange="filterRegistrations()">
                    <option value="all">All Status</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
            <div class="card-body">
                <?php if (empty($processed_regs)): ?>
                    <div class="empty-state"><i class="fas fa-inbox"></i><p>No processed registrations yet</p></div>
                <?php else: ?>
                    <div style="overflow-x:auto;">
                        <table class="data-table" id="processedTable">
                            <thead><tr><th>Email</th><th>Student ID</th><th>Name</th><th>Programme</th><th>Status</th><th>Processed</th></tr></thead>
                            <tbody>
                                <?php foreach ($processed_regs as $reg): ?>
                                <tr data-status="<?= $reg['status']; ?>">
                                    <td><?= htmlspecialchars($reg['email']); ?></td>
                                    <td><strong><?= htmlspecialchars($reg['studfullid']); ?></strong></td>
                                    <td><?= htmlspecialchars($reg['studname']); ?></td>
                                    <td><?= !empty($reg['fyp_progname']) ? '<span class="badge badge-open">'.htmlspecialchars($reg['fyp_progname']).'</span>' : '-'; ?></td>
                                    <td><span class="badge badge-<?= $reg['status'] === 'approved' ? 'approved' : 'rejected'; ?>"><?= ucfirst($reg['status']); ?></span></td>
                                    <td><?= $reg['processed_at'] ? date('M j, Y H:i', strtotime($reg['processed_at'])) : '-'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

<?php 
// ==================== GROUP REQUESTS ====================
elseif ($current_page === 'group_requests'): 
    $group_requests = [];
    $res = $conn->query("SELECT gr.*, 
                                s1.fyp_studname as inviter_name, s1.fyp_studfullid as inviter_fullid,
                                s2.fyp_studname as invitee_name, s2.fyp_studfullid as invitee_fullid
                         FROM group_request gr 
                         LEFT JOIN student s1 ON gr.inviter_id = s1.fyp_studid 
                         LEFT JOIN student s2 ON gr.invitee_id = s2.fyp_studid
                         ORDER BY gr.request_id DESC");
    if ($res) { while ($row = $res->fetch_assoc()) { $group_requests[] = $row; } }
    
    $total_gr = count($group_requests);
    $pending_gr = count(array_filter($group_requests, function($r) { return ($r['request_status'] ?? '') === 'Pending'; }));
    $accepted_gr = count(array_filter($group_requests, function($r) { return ($r['request_status'] ?? '') === 'Accepted'; }));
    $rejected_gr = count(array_filter($group_requests, function($r) { return ($r['request_status'] ?? '') === 'Rejected'; }));
?>

<div class="stats-grid" style="margin-bottom:20px;">
    <div class="stat-card" style="cursor:pointer;" onclick="filterGroupByStatus('all')">
        <div class="stat-icon purple"><i class="fas fa-users"></i></div>
        <div class="stat-info"><h4><?= $total_gr; ?></h4><p>Total</p></div>
    </div>
    <div class="stat-card" style="cursor:pointer;" onclick="filterGroupByStatus('Pending')">
        <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
        <div class="stat-info"><h4><?= $pending_gr; ?></h4><p>Pending</p></div>
    </div>
    <div class="stat-card" style="cursor:pointer;" onclick="filterGroupByStatus('Accepted')">
        <div class="stat-icon green"><i class="fas fa-check"></i></div>
        <div class="stat-info"><h4><?= $accepted_gr; ?></h4><p>Accepted</p></div>
    </div>
    <div class="stat-card" style="cursor:pointer;" onclick="filterGroupByStatus('Rejected')">
        <div class="stat-icon red"><i class="fas fa-times"></i></div>
        <div class="stat-info"><h4><?= $rejected_gr; ?></h4><p>Rejected</p></div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-users"></i> Group Requests</h3>
        <button class="btn btn-secondary btn-sm" onclick="resetGroupFilters()"><i class="fas fa-redo"></i> Reset</button>
    </div>
    <div class="card-body">
        <div style="display:flex;gap:15px;margin-bottom:20px;padding:15px;background:rgba(139,92,246,0.1);border-radius:12px;flex-wrap:wrap;">
            <div style="flex:2;min-width:200px;">
                <input type="text" class="form-control" placeholder="Search..." id="groupSearch" onkeyup="filterGroupRequests()">
            </div>
            <div style="flex:1;min-width:150px;">
                <select class="form-control" id="groupStatus" onchange="filterGroupRequests()">
                    <option value="all">All Status</option>
                    <option value="Pending">Pending</option>
                    <option value="Accepted">Accepted</option>
                    <option value="Rejected">Rejected</option>
                </select>
            </div>
        </div>
        
        <?php if (empty($group_requests)): ?>
            <div class="empty-state"><i class="fas fa-users"></i><p>No group requests found</p></div>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="data-table" id="groupRequestTable">
                    <thead><tr><th>ID</th><th>Group ID</th><th>Inviter</th><th>Invitee</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($group_requests as $gr): 
                            $status = $gr['request_status'] ?? 'Pending';
                        ?>
                        <tr data-status="<?= $status; ?>" data-search="<?= strtolower(($gr['inviter_name'] ?? ''). ' ' .($gr['invitee_name'] ?? '')); ?>">
                            <td><?= $gr['request_id']; ?></td>
                            <td><span class="badge" style="background:rgba(139,92,246,0.2);color:#a78bfa;">Group <?= $gr['group_id']; ?></span></td>
                            <td><strong><?= htmlspecialchars($gr['inviter_name'] ?? '-'); ?></strong><br><small style="color:#64748b;"><?= htmlspecialchars($gr['inviter_fullid'] ?? ''); ?></small></td>
                            <td><strong><?= htmlspecialchars($gr['invitee_name'] ?? '-'); ?></strong><br><small style="color:#64748b;"><?= htmlspecialchars($gr['invitee_fullid'] ?? ''); ?></small></td>
                            <td><span class="badge badge-<?= $status === 'Accepted' ? 'approved' : ($status === 'Rejected' ? 'rejected' : 'pending'); ?>"><?= $status; ?></span></td>
                            <td>
                                <?php if ($status === 'Pending'): ?>
                                    <form method="POST" style="display:inline;"><input type="hidden" name="request_id" value="<?= $gr['request_id']; ?>"><input type="hidden" name="new_status" value="Accepted"><button type="submit" name="update_request_status" class="btn btn-success btn-sm"><i class="fas fa-check"></i></button></form>
                                    <form method="POST" style="display:inline;"><input type="hidden" name="request_id" value="<?= $gr['request_id']; ?>"><input type="hidden" name="new_status" value="Rejected"><button type="submit" name="update_request_status" class="btn btn-danger btn-sm"><i class="fas fa-times"></i></button></form>
                                <?php else: ?>
                                    <span style="color:#64748b;">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php 
// ==================== MANAGE STUDENTS ====================
elseif ($current_page === 'students'): 
    $students = [];
    $res = $conn->query("SELECT s.*, p.fyp_progname, p.fyp_progid FROM student s LEFT JOIN programme p ON s.fyp_progid = p.fyp_progid ORDER BY s.fyp_studname");
    if ($res) { while ($row = $res->fetch_assoc()) { $students[] = $row; } }
    
    $programmes = [];
    $prog_res = $conn->query("SELECT DISTINCT fyp_progid, fyp_progname FROM programme ORDER BY fyp_progname");
    if ($prog_res) { while ($row = $prog_res->fetch_assoc()) { $programmes[] = $row; } }
?>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-user-graduate"></i> All Students (<span id="studentCount"><?= count($students); ?></span>)</h3>
            <button class="btn btn-secondary btn-sm" onclick="resetStudentFilters()"><i class="fas fa-redo"></i> Reset</button>
        </div>
        <div class="card-body">
            <div class="filter-controls" style="display:flex;flex-wrap:wrap;gap:15px;margin-bottom:20px;padding:20px;background:rgba(139,92,246,0.1);border-radius:12px;">
                <div style="flex:2;min-width:200px;">
                    <label style="color:#a78bfa;font-size:0.85rem;display:block;margin-bottom:5px;"><i class="fas fa-search"></i> Search</label>
                    <input type="text" class="form-control" placeholder="Search by ID or Name..." id="studentSearch" onkeyup="filterStudents()">
                </div>
                <div style="flex:1;min-width:150px;">
                    <label style="color:#a78bfa;font-size:0.85rem;display:block;margin-bottom:5px;"><i class="fas fa-graduation-cap"></i> Programme</label>
                    <select id="studentProgramme" class="form-control" onchange="filterStudents()">
                        <option value="all">All Programmes</option>
                        <?php foreach ($programmes as $prog): ?>
                            <option value="<?= htmlspecialchars($prog['fyp_progname']); ?>"><?= htmlspecialchars($prog['fyp_progname']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="flex:1;min-width:150px;">
                    <label style="color:#a78bfa;font-size:0.85rem;display:block;margin-bottom:5px;"><i class="fas fa-users"></i> Type</label>
                    <select id="studentGroupType" class="form-control" onchange="filterStudents()">
                        <option value="all">All Types</option>
                        <option value="Individual">Individual</option>
                        <option value="Group">Group</option>
                    </select>
                </div>
                <div style="flex:1;min-width:150px;">
                    <label style="color:#a78bfa;font-size:0.85rem;display:block;margin-bottom:5px;"><i class="fas fa-sort"></i> Sort By</label>
                    <select id="studentSort" class="form-control" onchange="filterStudents()">
                        <option value="name_asc">Name (A-Z)</option>
                        <option value="name_desc">Name (Z-A)</option>
                        <option value="id_asc">ID (A-Z)</option>
                        <option value="id_desc">ID (Z-A)</option>
                    </select>
                </div>
            </div>
            <?php if (empty($students)): ?>
                <div class="empty-state"><i class="fas fa-user-graduate"></i><p>No students found</p></div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="data-table" id="studentTable">
                        <thead><tr><th>Student ID</th><th>Name</th><th>Programme</th><th>Email</th><th>Type</th><th>Contact</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach ($students as $s): ?>
                            <tr data-id="<?= htmlspecialchars(strtolower($s['fyp_studfullid'])); ?>"
                                data-name="<?= htmlspecialchars(strtolower($s['fyp_studname'])); ?>"
                                data-programme="<?= htmlspecialchars($s['fyp_progname'] ?? ''); ?>"
                                data-type="<?= htmlspecialchars($s['fyp_group'] ?? 'Individual'); ?>">
                                <td><strong><?= htmlspecialchars($s['fyp_studfullid']); ?></strong></td>
                                <td><?= htmlspecialchars($s['fyp_studname']); ?></td>
                                <td><?= htmlspecialchars($s['fyp_progname'] ?? '-'); ?></td>
                                <td><?= htmlspecialchars($s['fyp_email'] ?? '-'); ?></td>
                                <td><span class="badge <?= ($s['fyp_group'] ?? '') === 'Group' ? 'badge-approved' : 'badge-pending'; ?>"><?= $s['fyp_group'] ?? 'Individual'; ?></span></td>
                                <td><?= htmlspecialchars($s['fyp_contactno'] ?? '-'); ?></td>
                                <td>
                                    <button class="btn btn-primary btn-sm" onclick="openEditStudentModal('<?= $s['fyp_studid']; ?>','<?= htmlspecialchars($s['fyp_studname'], ENT_QUOTES); ?>','<?= htmlspecialchars($s['fyp_email'] ?? '', ENT_QUOTES); ?>','<?= htmlspecialchars($s['fyp_contactno'] ?? '', ENT_QUOTES); ?>','<?= $s['fyp_progid'] ?? 1; ?>','<?= htmlspecialchars($s['fyp_group'] ?? '', ENT_QUOTES); ?>')"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-danger btn-sm" onclick="confirmDeleteStudent('<?= $s['fyp_studid']; ?>','<?= htmlspecialchars($s['fyp_studname'], ENT_QUOTES); ?>')"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php 
// ==================== MANAGE SUPERVISORS ====================
elseif ($current_page === 'supervisors'): 
    $supervisors = [];
    $res = $conn->query("SELECT * FROM supervisor ORDER BY fyp_name");
    if ($res) { while ($row = $res->fetch_assoc()) { $supervisors[] = $row; } }
    
    // Get supervision history with semester grouping
    $supervisor_history = [];
    $history_res = $conn->query("SELECT p.fyp_supervisorid, ay.fyp_acdyear, ay.fyp_intake, COUNT(*) as student_count
                                 FROM pairing p
                                 LEFT JOIN academic_year ay ON p.fyp_academicid = ay.fyp_academicid
                                 GROUP BY p.fyp_supervisorid, ay.fyp_academicid
                                 ORDER BY ay.fyp_acdyear DESC, ay.fyp_intake DESC");
    if ($history_res) { 
        while ($row = $history_res->fetch_assoc()) { 
            $sup_id = $row['fyp_supervisorid'];
            if (!isset($supervisor_history[$sup_id])) { $supervisor_history[$sup_id] = []; }
            $supervisor_history[$sup_id][] = $row;
        } 
    }
    
    // Get supervision students for modal
    $supervisor_students = [];
    $ss_res = $conn->query("SELECT p.fyp_supervisorid, s.fyp_studid, s.fyp_studfullid, s.fyp_studname, pr.fyp_projecttitle, ay.fyp_acdyear, ay.fyp_intake
                            FROM pairing p
                            LEFT JOIN student s ON p.fyp_studid = s.fyp_studid
                            LEFT JOIN project pr ON p.fyp_projectid = pr.fyp_projectid
                            LEFT JOIN academic_year ay ON p.fyp_academicid = ay.fyp_academicid
                            ORDER BY s.fyp_studname");
    if ($ss_res) { 
        while ($row = $ss_res->fetch_assoc()) { 
            $sup_id = $row['fyp_supervisorid'];
            if (!isset($supervisor_students[$sup_id])) { $supervisor_students[$sup_id] = []; }
            $supervisor_students[$sup_id][] = $row;
        } 
    }
    
    // Get unique values for filters
    $sup_programmes = [];
    $sup_specializations = [];
    foreach ($supervisors as $sup) {
        if (!empty($sup['fyp_programme']) && !in_array($sup['fyp_programme'], $sup_programmes)) {
            $sup_programmes[] = $sup['fyp_programme'];
        }
        if (!empty($sup['fyp_specialization']) && !in_array($sup['fyp_specialization'], $sup_specializations)) {
            $sup_specializations[] = $sup['fyp_specialization'];
        }
    }
    sort($sup_programmes);
    sort($sup_specializations);
?>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-user-tie"></i> All Supervisors (<span id="supervisorCount"><?= count($supervisors); ?></span>)</h3>
            <button class="btn btn-secondary btn-sm" onclick="resetSupervisorFilters()"><i class="fas fa-redo"></i> Reset</button>
        </div>
        <div class="card-body">
            <div class="filter-controls" style="display:flex;flex-wrap:wrap;gap:15px;margin-bottom:20px;padding:20px;background:rgba(139,92,246,0.1);border-radius:12px;">
                <div style="flex:2;min-width:200px;">
                    <label style="color:#a78bfa;font-size:0.85rem;display:block;margin-bottom:5px;"><i class="fas fa-search"></i> Search</label>
                    <input type="text" class="form-control" placeholder="Search..." id="supervisorSearch" onkeyup="filterSupervisors()">
                </div>
                <div style="flex:1;min-width:150px;">
                    <label style="color:#a78bfa;font-size:0.85rem;display:block;margin-bottom:5px;"><i class="fas fa-graduation-cap"></i> Programme</label>
                    <select id="supervisorProgramme" class="form-control" onchange="filterSupervisors()">
                        <option value="all">All Programmes</option>
                        <?php foreach ($sup_programmes as $prog): ?>
                            <option value="<?= htmlspecialchars(strtolower($prog)); ?>"><?= htmlspecialchars($prog); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="flex:1;min-width:150px;">
                    <label style="color:#a78bfa;font-size:0.85rem;display:block;margin-bottom:5px;"><i class="fas fa-flask"></i> Specialization</label>
                    <select id="supervisorSpecialization" class="form-control" onchange="filterSupervisors()">
                        <option value="all">All</option>
                        <?php foreach ($sup_specializations as $spec): ?>
                            <option value="<?= htmlspecialchars(strtolower($spec)); ?>"><?= htmlspecialchars($spec); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="flex:1;min-width:120px;">
                    <label style="color:#a78bfa;font-size:0.85rem;display:block;margin-bottom:5px;"><i class="fas fa-user-check"></i> Moderator</label>
                    <select id="supervisorModerator" class="form-control" onchange="filterSupervisors()">
                        <option value="all">All</option>
                        <option value="yes">Yes</option>
                        <option value="no">No</option>
                    </select>
                </div>
                <div style="flex:1;min-width:150px;">
                    <label style="color:#a78bfa;font-size:0.85rem;display:block;margin-bottom:5px;"><i class="fas fa-sort"></i> Sort By</label>
                    <select id="supervisorSort" class="form-control" onchange="filterSupervisors()">
                        <option value="name_asc">Name (A-Z)</option>
                        <option value="name_desc">Name (Z-A)</option>
                        <option value="id_asc">ID (Asc)</option>
                        <option value="id_desc">ID (Desc)</option>
                    </select>
                </div>
            </div>
            <?php if (empty($supervisors)): ?>
                <div class="empty-state"><i class="fas fa-user-tie"></i><p>No supervisors found</p></div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="data-table" id="supervisorTable">
                        <thead><tr><th>ID</th><th>Name</th><th>Room</th><th>Programme</th><th>Email</th><th>Specialization</th><th>Moderator</th><th>Students</th></tr></thead>
                        <tbody>
                            <?php foreach ($supervisors as $sup): 
                                $student_count = isset($supervisor_students[$sup['fyp_supervisorid']]) ? count($supervisor_students[$sup['fyp_supervisorid']]) : 0;
                            ?>
                            <tr data-id="<?= $sup['fyp_supervisorid']; ?>"
                                data-name="<?= htmlspecialchars(strtolower($sup['fyp_name'])); ?>" 
                                data-email="<?= htmlspecialchars(strtolower($sup['fyp_email'] ?? '')); ?>"
                                data-programme="<?= htmlspecialchars(strtolower($sup['fyp_programme'] ?? '')); ?>"
                                data-specialization="<?= htmlspecialchars(strtolower($sup['fyp_specialization'] ?? '')); ?>"
                                data-moderator="<?= $sup['fyp_ismoderator'] ? 'yes' : 'no'; ?>">
                                <td><?= $sup['fyp_supervisorid']; ?></td>
                                <td><strong><?= htmlspecialchars($sup['fyp_name']); ?></strong></td>
                                <td><?= htmlspecialchars($sup['fyp_roomno'] ?? '-'); ?></td>
                                <td><?= htmlspecialchars($sup['fyp_programme'] ?? '-'); ?></td>
                                <td><?= htmlspecialchars($sup['fyp_email'] ?? '-'); ?></td>
                                <td><?= htmlspecialchars($sup['fyp_specialization'] ?? '-'); ?></td>
                                <td><?= $sup['fyp_ismoderator'] ? '<span class="badge badge-approved">Yes</span>' : '<span class="badge badge-rejected">No</span>'; ?></td>
                                <td>
                                    <button class="btn btn-info btn-sm" onclick="viewSupervisorHistory(<?= $sup['fyp_supervisorid']; ?>, '<?= htmlspecialchars($sup['fyp_name'], ENT_QUOTES); ?>')">
                                        <i class="fas fa-users"></i>
                                        <?php if ($student_count > 0): ?>
                                            <span style="background:#a78bfa;color:#fff;padding:2px 6px;border-radius:10px;font-size:0.7rem;margin-left:3px;"><?= $student_count; ?></span>
                                        <?php endif; ?>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
    var supervisorHistoryData = <?= json_encode($supervisor_history); ?>;
    var supervisorStudentsData = <?= json_encode($supervisor_students); ?>;
    </script>

<?php 
// ==================== STUDENT-SUPERVISOR PAIRING ====================
elseif ($current_page === 'pairing'): 
    
    // Fetch all pairings with detailed information
    $pairings = [];
    $res = $conn->query("SELECT p.*, 
                                s.fyp_studname, s.fyp_studfullid, s.fyp_email as student_email,
                                sup.fyp_name as supervisor_name, sup.fyp_email as supervisor_email,
                                pr.fyp_projecttitle, pr.fyp_projecttype,
                                ay.fyp_acdyear, ay.fyp_intake, ay.fyp_academicid
                         FROM pairing p 
                         LEFT JOIN student s ON p.fyp_studid = s.fyp_studid 
                         LEFT JOIN supervisor sup ON p.fyp_supervisorid = sup.fyp_supervisorid 
                         LEFT JOIN project pr ON p.fyp_projectid = pr.fyp_projectid
                         LEFT JOIN academic_year ay ON p.fyp_academicid = ay.fyp_academicid
                         ORDER BY p.fyp_pairingid DESC");
    if ($res) { while ($row = $res->fetch_assoc()) { $pairings[] = $row; } }

    // Fetch all semesters for filter
    $semesters = [];
    $sem_res = $conn->query("SELECT fyp_academicid, fyp_acdyear, fyp_intake FROM academic_year ORDER BY fyp_acdyear DESC, fyp_intake DESC");
    if ($sem_res) { while ($row = $sem_res->fetch_assoc()) { $semesters[] = $row; } }

    // Fetch all supervisors for filter
    $supervisors_filter = [];
    $sup_res = $conn->query("SELECT fyp_supervisorid, fyp_name FROM supervisor ORDER BY fyp_name");
    if ($sup_res) { while ($row = $sup_res->fetch_assoc()) { $supervisors_filter[] = $row; } }

    // For create pairing modal
    $sup_list = $supervisors_filter;
    $proj_list = [];
    $proj_res = $conn->query("SELECT fyp_projectid, fyp_projecttitle FROM project ORDER BY fyp_projecttitle");
    if ($proj_res) { while ($row = $proj_res->fetch_assoc()) { $proj_list[] = $row; } }
    $academic_years = $semesters;

    // Group pairings by supervisor for card view
    $pairings_by_supervisor = [];
    foreach ($pairings as $p) {
        $sup_id = $p['fyp_supervisorid'];
        if (!$sup_id) continue;
        if (!isset($pairings_by_supervisor[$sup_id])) {
            $pairings_by_supervisor[$sup_id] = [
                'supervisor_name' => $p['supervisor_name'],
                'supervisor_email' => $p['supervisor_email'],
                'students' => []
            ];
        }
        $pairings_by_supervisor[$sup_id]['students'][] = $p;
    }

    // Calculate stats
    $total_pairings_count = count($pairings);
    $unique_students = count(array_unique(array_filter(array_column($pairings, 'fyp_studid'))));
    $unique_supervisors = count($pairings_by_supervisor);
    $total_semesters = count($semesters);
?>

<!-- Pairing Page Styles -->
<style>
.view-toggle { display: flex; gap: 5px; background: rgba(139,92,246,0.1); padding: 5px; border-radius: 10px; }
.view-toggle .btn { padding: 8px 16px; border-radius: 8px; transition: all 0.3s; }

.pairing-filter-section { background: linear-gradient(135deg, rgba(139,92,246,0.1), rgba(99,102,241,0.1)); padding: 20px; border-radius: 16px; margin-bottom: 20px; border: 1px solid rgba(139,92,246,0.2); }
.pairing-filter-row { display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; }
.pairing-filter-group { flex: 1; min-width: 150px; }
.pairing-filter-group.search-group { flex: 2; min-width: 200px; }
.pairing-filter-group label { display: block; color: #a78bfa; font-size: 0.8rem; margin-bottom: 6px; font-weight: 600; text-transform: uppercase; }

.pairing-results-info { display: flex; justify-content: space-between; align-items: center; padding: 12px 20px; background: rgba(139,92,246,0.1); border-radius: 12px; margin-bottom: 20px; color: #a78bfa; font-weight: 600; }

.supervisor-card { background: linear-gradient(145deg, rgba(30,30,50,0.9), rgba(20,20,40,0.9)); border: 1px solid rgba(139,92,246,0.2); border-radius: 16px; margin-bottom: 20px; overflow: hidden; }
.supervisor-card:hover { border-color: rgba(139,92,246,0.5); }
.supervisor-card-header { background: linear-gradient(135deg, rgba(139,92,246,0.2), rgba(99,102,241,0.2)); padding: 20px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; }
.supervisor-card-header:hover { background: linear-gradient(135deg, rgba(139,92,246,0.3), rgba(99,102,241,0.3)); }
.supervisor-info { display: flex; align-items: center; gap: 15px; }
.supervisor-avatar { width: 50px; height: 50px; background: linear-gradient(135deg, #8b5cf6, #6366f1); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; color: #fff; font-weight: 700; }
.supervisor-details h4 { color: #e2e8f0; margin: 0 0 4px 0; }
.supervisor-details p { color: #64748b; margin: 0; font-size: 0.85rem; }
.student-count-badge { background: linear-gradient(135deg, #10b981, #059669); color: #fff; padding: 8px 16px; border-radius: 20px; font-weight: 600; }
.supervisor-card-body { padding: 0; display: none; }
.supervisor-card-body.show { display: block; }

.semester-tabs { display: flex; gap: 10px; padding: 15px 20px; background: rgba(0,0,0,0.2); overflow-x: auto; }
.semester-tab { padding: 8px 16px; background: rgba(139,92,246,0.1); border: 1px solid rgba(139,92,246,0.2); border-radius: 20px; color: #a78bfa; font-size: 0.85rem; cursor: pointer; white-space: nowrap; }
.semester-tab:hover, .semester-tab.active { background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; border-color: transparent; }

.students-list { padding: 15px; }
.chevron-icon { color: #a78bfa; transition: transform 0.3s; }
.chevron-icon.rotated { transform: rotate(180deg); }
</style>

<!-- Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon purple"><i class="fas fa-link"></i></div>
        <div class="stat-info"><h4><?= $total_pairings_count; ?></h4><p>Total Pairings</p></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-user-graduate"></i></div>
        <div class="stat-info"><h4><?= $unique_students; ?></h4><p>Students Paired</p></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-user-tie"></i></div>
        <div class="stat-info"><h4><?= $unique_supervisors; ?></h4><p>Active Supervisors</p></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-calendar-alt"></i></div>
        <div class="stat-info"><h4><?= $total_semesters; ?></h4><p>Semesters</p></div>
    </div>
</div>

<!-- Main Card -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-link"></i> Student-Supervisor Pairings</h3>
        <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
            <div class="view-toggle">
                <button type="button" class="btn btn-primary btn-sm" id="btnTableView" onclick="switchPairingView('table')">
                    <i class="fas fa-table"></i> Table
                </button>
                <button type="button" class="btn btn-secondary btn-sm" id="btnSupervisorView" onclick="switchPairingView('supervisor')">
                    <i class="fas fa-th-large"></i> By Supervisor
                </button>
            </div>
            <button type="button" class="btn btn-primary" onclick="openModal('createPairingModal')">
                <i class="fas fa-plus"></i> Add Pairing
            </button>
        </div>
    </div>
    <div class="card-body">
        
        <!-- Filters Section -->
        <div class="pairing-filter-section">
            <div class="pairing-filter-row">
                <div class="pairing-filter-group search-group">
                    <label><i class="fas fa-search"></i> Search</label>
                    <input type="text" id="pairingSearch" class="form-control" placeholder="Search student, supervisor, or project..." onkeyup="filterPairings()">
                </div>
                <div class="pairing-filter-group">
                    <label><i class="fas fa-calendar"></i> Semester</label>
                    <select id="pairingSemester" class="form-control" onchange="filterPairings()">
                        <option value="all">All Semesters</option>
                        <?php foreach ($semesters as $sem): ?>
                            <option value="<?= $sem['fyp_academicid']; ?>"><?= htmlspecialchars($sem['fyp_acdyear'] . ' - ' . $sem['fyp_intake']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="pairing-filter-group">
                    <label><i class="fas fa-user-tie"></i> Supervisor</label>
                    <select id="pairingSupervisor" class="form-control" onchange="filterPairings()">
                        <option value="all">All Supervisors</option>
                        <?php foreach ($supervisors_filter as $sup): ?>
                            <option value="<?= $sup['fyp_supervisorid']; ?>"><?= htmlspecialchars($sup['fyp_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="pairing-filter-group">
                    <label><i class="fas fa-sort"></i> Sort By</label>
                    <select id="pairingSort" class="form-control" onchange="filterPairings()">
                        <option value="newest">Newest First</option>
                        <option value="oldest">Oldest First</option>
                        <option value="student_az">Student (A-Z)</option>
                        <option value="student_za">Student (Z-A)</option>
                        <option value="supervisor_az">Supervisor (A-Z)</option>
                        <option value="supervisor_za">Supervisor (Z-A)</option>
                    </select>
                </div>
                <div class="pairing-filter-group" style="flex: 0; min-width: auto;">
                    <label>&nbsp;</label>
                    <button type="button" class="btn btn-secondary" onclick="resetPairingFilters()">
                        <i class="fas fa-redo"></i> Reset
                    </button>
                </div>
            </div>
        </div>

        <!-- Results Info -->
        <div class="pairing-results-info">
            <span><i class="fas fa-list"></i> Showing <span id="pairingCount"><?= $total_pairings_count; ?></span> of <?= $total_pairings_count; ?> pairings</span>
        </div>

        <!-- Table View -->
        <div id="pairingTableView">
            <?php if (empty($pairings)): ?>
                <div class="empty-state"><i class="fas fa-link"></i><p>No pairings found</p></div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="data-table" id="pairingTable">
                        <thead>
                            <tr><th>ID</th><th>Student</th><th>Student ID</th><th>Supervisor</th><th>Project</th><th>Semester</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pairings as $index => $p): 
                                $semester_label = ($p['fyp_acdyear'] ?? '') . ' - ' . ($p['fyp_intake'] ?? '');
                                $search_text = strtolower(($p['fyp_studname'] ?? '') . ' ' . ($p['fyp_studfullid'] ?? '') . ' ' . ($p['supervisor_name'] ?? '') . ' ' . ($p['fyp_projecttitle'] ?? '') . ' ' . $semester_label);
                            ?>
                            <tr data-index="<?= $index; ?>"
                                data-search="<?= htmlspecialchars($search_text); ?>"
                                data-semester="<?= $p['fyp_academicid'] ?? ''; ?>"
                                data-supervisor="<?= $p['fyp_supervisorid'] ?? ''; ?>"
                                data-student="<?= htmlspecialchars(strtolower($p['fyp_studname'] ?? '')); ?>"
                                data-supervisor-name="<?= htmlspecialchars(strtolower($p['supervisor_name'] ?? '')); ?>">
                                <td><?= $p['fyp_pairingid']; ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($p['fyp_studname'] ?? '-'); ?></strong>
                                    <?php if (!empty($p['student_email'])): ?>
                                        <br><small style="color:#64748b;"><?= htmlspecialchars($p['student_email']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge" style="background:rgba(139,92,246,0.2);color:#a78bfa;"><?= htmlspecialchars($p['fyp_studfullid'] ?? '-'); ?></span></td>
                                <td>
                                    <strong><?= htmlspecialchars($p['supervisor_name'] ?? '-'); ?></strong>
                                    <?php if (!empty($p['supervisor_email'])): ?>
                                        <br><small style="color:#64748b;"><?= htmlspecialchars($p['supervisor_email']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= !empty($p['fyp_projecttitle']) ? htmlspecialchars($p['fyp_projecttitle']) : '<span style="color:#64748b;">No project</span>'; ?></td>
                                <td><span class="badge" style="background:rgba(59,130,246,0.2);color:#60a5fa;"><?= htmlspecialchars($semester_label); ?></span></td>
                                <td>
                                    <button type="button" class="btn btn-danger btn-sm" onclick="confirmDeletePairing(<?= $p['fyp_pairingid']; ?>, '<?= htmlspecialchars(addslashes(($p['fyp_studname'] ?? '') . ' → ' . ($p['supervisor_name'] ?? ''))); ?>')"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Supervisor View -->
        <div id="pairingSupervisorView" style="display: none;">
            <?php if (empty($pairings_by_supervisor)): ?>
                <div class="empty-state"><i class="fas fa-user-tie"></i><p>No supervisors with pairings found</p></div>
            <?php else: ?>
                <div id="supervisorCards">
                <?php foreach ($pairings_by_supervisor as $sup_id => $sup_data): 
                    $name_parts = explode(' ', $sup_data['supervisor_name'] ?? '');
                    $initials = '';
                    foreach ($name_parts as $part) {
                        if (!empty($part)) $initials .= strtoupper(substr($part, 0, 1));
                        if (strlen($initials) >= 2) break;
                    }
                    if (empty($initials)) $initials = '?';
                    
                    $sup_semesters = [];
                    foreach ($sup_data['students'] as $student) {
                        $sem_id = $student['fyp_academicid'] ?? '';
                        $sem_label = ($student['fyp_acdyear'] ?? '') . ' - ' . ($student['fyp_intake'] ?? '');
                        if (!empty($sem_id) && !isset($sup_semesters[$sem_id])) {
                            $sup_semesters[$sem_id] = $sem_label;
                        }
                    }
                ?>
                <div class="supervisor-card" data-supervisor="<?= $sup_id; ?>">
                    <div class="supervisor-card-header" onclick="toggleSupervisorStudents(<?= $sup_id; ?>)">
                        <div class="supervisor-info">
                            <div class="supervisor-avatar"><?= $initials; ?></div>
                            <div class="supervisor-details">
                                <h4><?= htmlspecialchars($sup_data['supervisor_name'] ?? 'Unknown'); ?></h4>
                                <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($sup_data['supervisor_email'] ?? ''); ?></p>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <div class="student-count-badge">
                                <i class="fas fa-users"></i> <?= count($sup_data['students']); ?> Student<?= count($sup_data['students']) > 1 ? 's' : ''; ?>
                            </div>
                            <i class="fas fa-chevron-down chevron-icon" id="chevron-<?= $sup_id; ?>"></i>
                        </div>
                    </div>
                    <div class="supervisor-card-body" id="students-<?= $sup_id; ?>">
                        <div class="semester-tabs">
                            <div class="semester-tab active" onclick="filterSupervisorCardStudents(<?= $sup_id; ?>, 'all'); setActiveTab(this);">All</div>
                            <?php foreach ($sup_semesters as $sem_id => $sem_label): ?>
                                <div class="semester-tab" onclick="filterSupervisorCardStudents(<?= $sup_id; ?>, '<?= $sem_id; ?>'); setActiveTab(this);"><?= htmlspecialchars($sem_label); ?></div>
                            <?php endforeach; ?>
                        </div>
                        <div class="students-list">
                            <table class="data-table" id="sup-table-<?= $sup_id; ?>" style="margin:0;">
                                <thead><tr><th>Student ID</th><th>Name</th><th>Project</th><th>Semester</th></tr></thead>
                                <tbody>
                                <?php foreach ($sup_data['students'] as $student): ?>
                                    <tr data-semester="<?= $student['fyp_academicid'] ?? ''; ?>">
                                        <td><span class="badge" style="background:rgba(139,92,246,0.2);color:#a78bfa;"><?= htmlspecialchars($student['fyp_studfullid'] ?? ''); ?></span></td>
                                        <td><strong style="color:#e2e8f0;"><?= htmlspecialchars($student['fyp_studname'] ?? '-'); ?></strong></td>
                                        <td style="color:#94a3b8;"><?= htmlspecialchars($student['fyp_projecttitle'] ?? 'No project'); ?></td>
                                        <td><span class="badge" style="background:rgba(59,130,246,0.2);color:#60a5fa;"><?= htmlspecialchars(($student['fyp_acdyear'] ?? '') . ' ' . ($student['fyp_intake'] ?? '')); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php 
// ==================== MANAGE PROJECTS ====================
elseif ($current_page === 'projects'): 
    $projects = [];
    $res = $conn->query("SELECT p.*, s.fyp_name as supervisor_name,
                                (SELECT COUNT(*) FROM pairing WHERE fyp_projectid = p.fyp_projectid) as student_count
                         FROM project p 
                         LEFT JOIN supervisor s ON p.fyp_supervisorid = s.fyp_supervisorid 
                         ORDER BY p.fyp_projectid DESC");
    if ($res) { while ($row = $res->fetch_assoc()) { $projects[] = $row; } }

    $supervisors_list = [];
    $res = $conn->query("SELECT fyp_supervisorid, fyp_name FROM supervisor ORDER BY fyp_name");
    if ($res) { while ($row = $res->fetch_assoc()) { $supervisors_list[] = $row; } }

    $total_projects_count = count($projects);
    $available_count = count(array_filter($projects, function($p) { 
        return ($p['fyp_projectstatus'] ?? '') === 'Available' || ($p['fyp_projectstatus'] ?? '') === 'Open'; 
    }));
    $unavailable_count = $total_projects_count - $available_count;
?>

<style>
.toggle-switch { position: relative; display: inline-block; width: 80px; height: 32px; }
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(135deg, #ef4444, #dc2626); transition: 0.4s; border-radius: 32px; }
.toggle-slider:before { position: absolute; content: ""; height: 24px; width: 24px; left: 4px; bottom: 4px; background-color: white; transition: 0.4s; border-radius: 50%; }
.toggle-switch input:checked + .toggle-slider { background: linear-gradient(135deg, #10b981, #059669); }
.toggle-switch input:checked + .toggle-slider:before { transform: translateX(48px); }
.toggle-label { font-size: 0.7rem; font-weight: 600; position: absolute; top: 50%; transform: translateY(-50%); color: white; pointer-events: none; }
.toggle-label.on { left: 8px; display: none; }
.toggle-label.off { right: 6px; }
.toggle-switch input:checked ~ .toggle-label.on { display: block; }
.toggle-switch input:checked ~ .toggle-label.off { display: none; }
</style>

<div class="stats-grid">
    <div class="stat-card" onclick="quickFilterProject('all')" style="cursor:pointer;">
        <div class="stat-icon purple"><i class="fas fa-folder-open"></i></div>
        <div class="stat-info"><h4><?= $total_projects_count; ?></h4><p>Total</p></div>
    </div>
    <div class="stat-card" onclick="quickFilterProject('Available')" style="cursor:pointer;">
        <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
        <div class="stat-info"><h4><?= $available_count; ?></h4><p>Available</p></div>
    </div>
    <div class="stat-card" onclick="quickFilterProject('Unavailable')" style="cursor:pointer;">
        <div class="stat-icon orange"><i class="fas fa-times-circle"></i></div>
        <div class="stat-info"><h4><?= $unavailable_count; ?></h4><p>Unavailable</p></div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-project-diagram"></i> Projects (<span id="projectCount"><?= $total_projects_count; ?></span>)</h3>
        <div style="display:flex;gap:10px;">
            <button class="btn btn-secondary btn-sm" onclick="resetProjectFilters()"><i class="fas fa-redo"></i> Reset</button>
            <button class="btn btn-primary" onclick="openModal('addProjectModal')"><i class="fas fa-plus"></i> Add Project</button>
        </div>
    </div>
    <div class="card-body">
        <div style="display:flex;flex-wrap:wrap;gap:15px;margin-bottom:20px;padding:15px;background:rgba(139,92,246,0.1);border-radius:12px;">
            <div style="flex:2;min-width:200px;">
                <input type="text" id="projectSearch" class="form-control" placeholder="Search..." onkeyup="filterProjects()">
            </div>
            <div style="flex:1;min-width:150px;">
                <select id="filterProjectStatus" class="form-control" onchange="filterProjects()">
                    <option value="all">All Status</option>
                    <option value="Available">Available</option>
                    <option value="Unavailable">Unavailable</option>
                </select>
            </div>
            <div style="flex:1;min-width:150px;">
                <select id="filterProjectType" class="form-control" onchange="filterProjects()">
                    <option value="all">All Types</option>
                    <option value="Application">Application</option>
                    <option value="Research">Research</option>
                </select>
            </div>
        </div>

        <?php if (empty($projects)): ?>
            <div class="empty-state"><i class="fas fa-folder-open"></i><p>No projects found</p></div>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="data-table" id="projectTable">
                    <thead><tr><th>ID</th><th>Title</th><th>Type</th><th>Status</th><th>Supervisor</th><th>Students</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($projects as $p): 
                            $currentStatus = $p['fyp_projectstatus'] ?? 'Available';
                            $isAvailable = ($currentStatus === 'Available' || $currentStatus === 'Open');
                        ?>
                        <tr data-title="<?= htmlspecialchars(strtolower($p['fyp_projecttitle'])); ?>" 
                            data-status="<?= $isAvailable ? 'Available' : 'Unavailable'; ?>"
                            data-type="<?= htmlspecialchars($p['fyp_projecttype'] ?? ''); ?>"
                            data-supervisor="<?= htmlspecialchars(strtolower($p['supervisor_name'] ?? '')); ?>">
                            <td><?= $p['fyp_projectid']; ?></td>
                            <td>
                                <strong><?= htmlspecialchars($p['fyp_projecttitle']); ?></strong>
                                <?php if (!empty($p['fyp_description'])): ?>
                                    <br><small style="color:#64748b;"><?= htmlspecialchars(substr($p['fyp_description'], 0, 50)); ?>...</small>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge" style="background:rgba(139,92,246,0.2);color:#a78bfa;"><?= $p['fyp_projecttype'] ?? '-'; ?></span></td>
                            <td>
                                <form method="POST" style="display:inline;" id="toggleForm-<?= $p['fyp_projectid']; ?>">
                                    <input type="hidden" name="toggle_project_status" value="1">
                                    <input type="hidden" name="project_id" value="<?= $p['fyp_projectid']; ?>">
                                    <input type="hidden" name="new_status" id="newStatus-<?= $p['fyp_projectid']; ?>" value="<?= $isAvailable ? 'Unavailable' : 'Available'; ?>">
                                    <label class="toggle-switch">
                                        <input type="checkbox" <?= $isAvailable ? 'checked' : ''; ?> onchange="toggleProjectStatus(<?= $p['fyp_projectid']; ?>, this.checked)">
                                        <span class="toggle-slider"></span>
                                        <span class="toggle-label on">Open</span>
                                        <span class="toggle-label off">Closed</span>
                                    </label>
                                </form>
                            </td>
                            <td><?= htmlspecialchars($p['supervisor_name'] ?? '-'); ?></td>
                            <td style="text-align:center;">
                                <span class="badge" style="background:rgba(139,92,246,0.2);color:#a78bfa;"><?= $p['student_count']; ?>/2</span>
                            </td>
                            <td>
                                <button class="btn btn-primary btn-sm" onclick='openEditProjectModal(<?= json_encode($p); ?>)'><i class="fas fa-edit"></i></button>
                                <button class="btn btn-danger btn-sm" onclick="confirmDeleteProject(<?= $p['fyp_projectid']; ?>, '<?= htmlspecialchars($p['fyp_projecttitle'], ENT_QUOTES); ?>')"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php 
// ==================== RUBRICS ASSESSMENT ====================
elseif ($current_page === 'rubrics'): ?>
<div class="card"><div class="card-header"><h3><i class="fas fa-list-check"></i> Rubrics Assessment</h3></div><div class="card-body"><p>Coming soon...</p></div></div>

<?php 
// ==================== ASSESSMENT MARKS ====================
elseif ($current_page === 'marks'): ?>
<div class="card"><div class="card-header"><h3><i class="fas fa-calculator"></i> Assessment Marks</h3></div><div class="card-body"><p>Coming soon...</p></div></div>

<?php 
// ==================== REPORTS ====================
elseif ($current_page === 'reports'): ?>
<div class="card"><div class="card-header"><h3><i class="fas fa-file-alt"></i> Reports</h3></div><div class="card-body"><p>Coming soon...</p></div></div>

<?php 
// ==================== ANNOUNCEMENTS ====================
elseif ($current_page === 'announcements'): 
    $announcements = [];
    $res = $conn->query("SELECT * FROM announcement ORDER BY fyp_datecreated DESC");
    if ($res) { while ($row = $res->fetch_assoc()) { $announcements[] = $row; } }
?>
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-bullhorn"></i> Announcements</h3>
        <button class="btn btn-primary" onclick="openModal('createAnnouncementModal')"><i class="fas fa-plus"></i> New</button>
    </div>
    <div class="card-body">
        <?php if (empty($announcements)): ?>
            <div class="empty-state"><i class="fas fa-bullhorn"></i><p>No announcements yet</p></div>
        <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Subject</th><th>Receiver</th><th>Date</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($announcements as $ann): ?>
                    <tr>
                        <td><?= htmlspecialchars($ann['fyp_subject']); ?></td>
                        <td><?= htmlspecialchars($ann['fyp_receiver']); ?></td>
                        <td><?= date('M j, Y', strtotime($ann['fyp_datecreated'])); ?></td>
                        <td><button class="btn btn-danger btn-sm" onclick="confirmUnsend(<?= $ann['fyp_annouceid']; ?>, '<?= htmlspecialchars($ann['fyp_subject'], ENT_QUOTES); ?>')"><i class="fas fa-trash"></i></button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php 
// ==================== SETTINGS ====================
elseif ($current_page === 'settings'): ?>
<div class="card"><div class="card-header"><h3><i class="fas fa-cog"></i> Settings</h3></div><div class="card-body"><p>Coming soon...</p></div></div>

<?php endif; ?>