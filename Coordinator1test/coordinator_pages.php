<?php
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
    <div class="card-header"><h3><i class="fas fa-user-check" style="color:#34d399;"></i> Recently Registered Students (Last 30 Days)</h3></div>
    <div class="card-body">
        <table class="data-table">
            <thead><tr><th>Student ID</th><th>Name</th><th>Email</th><th>Type</th><th>Date Registered</th></tr></thead>
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

<?php // ==================== STUDENT REGISTRATIONS ====================
elseif ($current_page === 'registrations'): 
    $pending_regs = [];
    $res = $conn->query("SELECT * FROM pending_registration WHERE status = 'pending' ORDER BY created_at DESC");
    if ($res) { while ($row = $res->fetch_assoc()) { $pending_regs[] = $row; } }
    
    $processed_regs = [];
    $res = $conn->query("SELECT * FROM pending_registration WHERE status != 'pending' ORDER BY processed_at DESC LIMIT 50");
    if ($res) { while ($row = $res->fetch_assoc()) { $processed_regs[] = $row; } }
?>
    <div class="tab-nav">
        <button class="tab-btn active" onclick="showRegTab('pending')">Pending (<?= count($pending_regs); ?>)</button>
        <button class="tab-btn" onclick="showRegTab('processed')">Processed</button>
    </div>
    
    <div id="pendingTab">
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
                        <table class="data-table">
                            <thead><tr><th><input type="checkbox" onclick="toggleAllCheckboxes(this)"></th><th>Email</th><th>Student ID</th><th>Name</th><th>Type</th><th>Date</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($pending_regs as $reg): ?>
                                <tr>
                                    <td><input type="checkbox" name="selected_ids[]" value="<?= $reg['id']; ?>"></td>
                                    <td><?= htmlspecialchars($reg['email']); ?></td>
                                    <td><?= htmlspecialchars($reg['studfullid']); ?></td>
                                    <td><?= htmlspecialchars($reg['studname']); ?></td>
                                    <td><span class="badge <?= ($reg['fyp_type'] ?? '') === 'Group' ? 'badge-approved' : 'badge-pending'; ?>"><?= $reg['fyp_type'] ?? 'Individual'; ?></span></td>
                                    <td><?= date('M j, Y', strtotime($reg['created_at'])); ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;"><input type="hidden" name="reg_id" value="<?= $reg['id']; ?>"><button type="submit" name="approve_registration" class="btn btn-success btn-sm"><i class="fas fa-check"></i></button></form>
                                        <button class="btn btn-danger btn-sm" onclick="openRejectModal(<?= $reg['id']; ?>,'<?= htmlspecialchars($reg['studname'], ENT_QUOTES); ?>')"><i class="fas fa-times"></i></button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div style="margin-top:15px;display:flex;gap:10px;">
                            <button type="submit" name="bulk_approve" class="btn btn-success"><i class="fas fa-check-double"></i> Approve Selected</button>
                            <button type="button" class="btn btn-danger" onclick="openBulkRejectModal()"><i class="fas fa-times"></i> Reject Selected</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div id="processedTab" style="display:none;">
        <div class="card">
            <div class="card-header"><h3><i class="fas fa-history"></i> Recently Processed</h3></div>
            <div class="card-body">
                <?php if (empty($processed_regs)): ?>
                    <div class="empty-state"><i class="fas fa-inbox"></i><p>No processed registrations yet</p></div>
                <?php else: ?>
                    <table class="data-table">
                        <thead><tr><th>Email</th><th>Name</th><th>Status</th><th>Processed</th></tr></thead>
                        <tbody>
                            <?php foreach ($processed_regs as $reg): ?>
                            <tr>
                                <td><?= htmlspecialchars($reg['email']); ?></td>
                                <td><?= htmlspecialchars($reg['studname']); ?></td>
                                <td><span class="badge badge-<?= $reg['status'] === 'approved' ? 'approved' : 'rejected'; ?>"><?= ucfirst($reg['status']); ?></span></td>
                                <td><?= $reg['processed_at'] ? date('M j, Y H:i', strtotime($reg['processed_at'])) : '-'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

<?php // ==================== GROUP REQUESTS ====================
elseif ($current_page === 'group_requests'): 
    $group_requests = [];
    $res = $conn->query("SELECT gr.*, s.fyp_studname as student_name, s.fyp_studfullid, s.fyp_email
                         FROM group_request gr 
                         LEFT JOIN student s ON gr.fyp_studid = s.fyp_studid 
                         ORDER BY gr.request_date DESC");
    if ($res) { while ($row = $res->fetch_assoc()) { $group_requests[] = $row; } }
?>
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-users"></i> Group Requests</h3></div>
        <div class="card-body">
            <div class="filter-row" style="margin-bottom:20px;">
                <div class="form-group" style="min-width:200px;">
                    <input type="text" class="form-control" placeholder="Search..." id="groupRequestSearch" onkeyup="filterTable('groupRequestSearch','groupRequestTable')">
                </div>
                <div class="form-group">
                    <select class="form-control" id="groupRequestStatus" onchange="filterGroupRequests()">
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
                <table class="data-table" id="groupRequestTable">
                    <thead><tr><th>Request ID</th><th>Student</th><th>Student ID</th><th>Group Name</th><th>Members</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($group_requests as $gr): ?>
                        <tr data-status="<?= $gr['request_status']; ?>">
                            <td><?= $gr['request_id']; ?></td>
                            <td><?= htmlspecialchars($gr['student_name'] ?? '-'); ?></td>
                            <td><?= htmlspecialchars($gr['fyp_studfullid'] ?? '-'); ?></td>
                            <td><?= htmlspecialchars($gr['group_name'] ?? '-'); ?></td>
                            <td><?= $gr['member_count'] ?? '-'; ?></td>
                            <td>
                                <span class="badge badge-<?= $gr['request_status'] === 'Accepted' ? 'approved' : ($gr['request_status'] === 'Rejected' ? 'rejected' : 'pending'); ?>">
                                    <?= $gr['request_status']; ?>
                                </span>
                            </td>
                            <td><?= $gr['request_date'] ? date('M j, Y', strtotime($gr['request_date'])) : '-'; ?></td>
                            <td>
                                <?php if ($gr['request_status'] === 'Pending'): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="request_id" value="<?= $gr['request_id']; ?>">
                                        <input type="hidden" name="new_status" value="Accepted">
                                        <button type="submit" name="update_request_status" class="btn btn-success btn-sm"><i class="fas fa-check"></i></button>
                                    </form>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="request_id" value="<?= $gr['request_id']; ?>">
                                        <input type="hidden" name="new_status" value="Rejected">
                                        <button type="submit" name="update_request_status" class="btn btn-danger btn-sm"><i class="fas fa-times"></i></button>
                                    </form>
                                <?php else: ?>
                                    <span style="color:#64748b;">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

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
    
    $programme_list = [];
    $prog_res = $conn->query("SELECT DISTINCT fyp_progid, fyp_progname FROM programme ORDER BY fyp_progname");
    if ($prog_res) { while ($row = $prog_res->fetch_assoc()) { $programme_list[] = $row; } }
?>
    <div class="card">
        <div class="card-header">
            <h3>All Students (<span id="studentCount"><?= count($students); ?></span>)</h3>
            <button class="btn btn-secondary btn-sm" onclick="resetStudentFilters()"><i class="fas fa-redo"></i> Reset Filters</button>
        </div>
        <div class="card-body">
            <div class="filter-controls" style="display:flex;flex-wrap:wrap;gap:15px;margin-bottom:20px;padding:20px;background:rgba(139,92,246,0.1);border-radius:12px;">
                <div style="flex:2;min-width:200px;">
                    <label style="color:#a78bfa;font-size:0.85rem;display:block;margin-bottom:5px;"><i class="fas fa-search"></i> Search</label>
                    <input type="text" class="form-control" placeholder="Search by ID or Name..." id="studentSearch" onkeyup="filterAndSortStudents()">
                </div>
                <div style="flex:1;min-width:150px;">
                    <label style="color:#a78bfa;font-size:0.85rem;display:block;margin-bottom:5px;"><i class="fas fa-sort"></i> Sort By</label>
                    <select id="studentSort" class="form-control" onchange="filterAndSortStudents()">
                        <option value="name_asc">Name (A-Z)</option>
                        <option value="name_desc">Name (Z-A)</option>
                        <option value="id_asc">Student ID (A-Z)</option>
                        <option value="id_desc">Student ID (Z-A)</option>
                    </select>
                </div>
                <div style="flex:1;min-width:150px;">
                    <label style="color:#a78bfa;font-size:0.85rem;display:block;margin-bottom:5px;"><i class="fas fa-graduation-cap"></i> Programme</label>
                    <select id="studentProgramme" class="form-control" onchange="filterAndSortStudents()">
                        <option value="all">All Programmes</option>
                        <?php foreach ($programme_list as $prog): ?>
                            <option value="<?= htmlspecialchars($prog['fyp_progname']); ?>"><?= htmlspecialchars($prog['fyp_progname']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="flex:1;min-width:150px;">
                    <label style="color:#a78bfa;font-size:0.85rem;display:block;margin-bottom:5px;"><i class="fas fa-users"></i> Group Type</label>
                    <select id="studentGroupType" class="form-control" onchange="filterAndSortStudents()">
                        <option value="all">All Types</option>
                        <option value="Individual">Individual</option>
                        <option value="Group">Group</option>
                    </select>
                </div>
            </div>
            <?php if (empty($students)): ?>
                <div class="empty-state"><i class="fas fa-user-graduate"></i><p>No students found</p></div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="data-table" id="studentTable">
                        <thead><tr><th>Student ID</th><th>Name</th><th>Programme</th><th>Project</th><th>Group Type</th><th>Email</th><th>Contact</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach ($students as $s): 
                                $project_title = $s['fyp_projecttitle'] ?? $s['paired_project'] ?? null;
                            ?>
                            <tr data-name="<?= htmlspecialchars(strtolower($s['fyp_studname'])); ?>" 
                                data-id="<?= htmlspecialchars(strtolower($s['fyp_studfullid'])); ?>"
                                data-programme="<?= htmlspecialchars(strtolower($s['fyp_progname'] ?? '')); ?>"
                                data-group="<?= $s['fyp_group'] ?? 'Individual'; ?>">
                                <td><?= htmlspecialchars($s['fyp_studfullid']); ?></td>
                                <td><?= htmlspecialchars($s['fyp_studname']); ?></td>
                                <td><?= htmlspecialchars($s['fyp_progname'] ?? '-'); ?></td>
                                <td><?= $project_title ? htmlspecialchars($project_title) : '<span style="color:#64748b;">No Project</span>'; ?></td>
                                <td><?= $s['fyp_group'] ? htmlspecialchars($s['fyp_group']) : '<span style="color:#64748b;">Individual</span>'; ?></td>
                                <td><?= htmlspecialchars($s['fyp_email'] ?? '-'); ?></td>
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

<?php // ==================== MANAGE SUPERVISORS ====================
elseif ($current_page === 'supervisors'): 
    $supervisors = [];
    $res = $conn->query("SELECT s.*, u.fyp_username, u.fyp_usertype FROM supervisor s LEFT JOIN user u ON s.fyp_userid = u.fyp_userid ORDER BY s.fyp_name");
    if ($res) { while ($row = $res->fetch_assoc()) { $supervisors[] = $row; } }
    
    $sup_programme_list = [];
    $prog_res = $conn->query("SELECT DISTINCT fyp_programme FROM supervisor WHERE fyp_programme IS NOT NULL AND fyp_programme != '' ORDER BY fyp_programme");
    if ($prog_res) { while ($row = $prog_res->fetch_assoc()) { $sup_programme_list[] = $row['fyp_programme']; } }
    
    $specialization_list = [];
    $spec_res = $conn->query("SELECT DISTINCT fyp_specialization FROM supervisor WHERE fyp_specialization IS NOT NULL AND fyp_specialization != '' ORDER BY fyp_specialization");
    if ($spec_res) { while ($row = $spec_res->fetch_assoc()) { $specialization_list[] = $row['fyp_specialization']; } }
    
    $supervisor_students = [];
    $ss_res = $conn->query("SELECT p.fyp_supervisorid, s.fyp_studid, s.fyp_studfullid, s.fyp_studname, 
                                   pr.fyp_projecttitle, ay.fyp_acdyear, ay.fyp_intake, ay.fyp_academicid
                            FROM pairing p
                            LEFT JOIN student s ON p.fyp_studid = s.fyp_studid
                            LEFT JOIN project pr ON p.fyp_projectid = pr.fyp_projectid
                            LEFT JOIN academic_year ay ON p.fyp_academicid = ay.fyp_academicid
                            ORDER BY ay.fyp_acdyear DESC, ay.fyp_intake DESC, s.fyp_studname");
    if ($ss_res) { 
        while ($row = $ss_res->fetch_assoc()) { 
            $sup_id = $row['fyp_supervisorid'];
            if (!isset($supervisor_students[$sup_id])) { $supervisor_students[$sup_id] = []; }
            $supervisor_students[$sup_id][] = $row;
        } 
    }
?>
    <div class="card">
        <div class="card-header">
            <h3>All Supervisors (<span id="supervisorCount"><?= count($supervisors); ?></span>)</h3>
            <button class="btn btn-secondary btn-sm" onclick="resetSupervisorFilters()"><i class="fas fa-redo"></i> Reset Filters</button>
        </div>
        <div class="card-body">
            <div class="filter-controls" style="display:flex;flex-wrap:wrap;gap:15px;margin-bottom:20px;padding:20px;background:rgba(139,92,246,0.1);border-radius:12px;">
                <div style="flex:2;min-width:200px;">
                    <label style="color:#a78bfa;font-size:0.85rem;display:block;margin-bottom:5px;"><i class="fas fa-search"></i> Search</label>
                    <input type="text" class="form-control" placeholder="Search by Name or Email..." id="supervisorSearch" onkeyup="filterAndSortSupervisors()">
                </div>
                <div style="flex:1;min-width:150px;">
                    <label style="color:#a78bfa;font-size:0.85rem;display:block;margin-bottom:5px;"><i class="fas fa-sort"></i> Sort By</label>
                    <select id="supervisorSort" class="form-control" onchange="filterAndSortSupervisors()">
                        <option value="name_asc">Name (A-Z)</option>
                        <option value="name_desc">Name (Z-A)</option>
                        <option value="id_asc">ID (Low-High)</option>
                        <option value="id_desc">ID (High-Low)</option>
                    </select>
                </div>
                <div style="flex:1;min-width:150px;">
                    <label style="color:#a78bfa;font-size:0.85rem;display:block;margin-bottom:5px;"><i class="fas fa-graduation-cap"></i> Programme</label>
                    <select id="supervisorProgramme" class="form-control" onchange="filterAndSortSupervisors()">
                        <option value="all">All Programmes</option>
                        <?php foreach ($sup_programme_list as $prog): ?>
                            <option value="<?= htmlspecialchars($prog); ?>"><?= htmlspecialchars($prog); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="flex:1;min-width:150px;">
                    <label style="color:#a78bfa;font-size:0.85rem;display:block;margin-bottom:5px;"><i class="fas fa-flask"></i> Specialization</label>
                    <select id="supervisorSpecialization" class="form-control" onchange="filterAndSortSupervisors()">
                        <option value="all">All Specializations</option>
                        <?php foreach ($specialization_list as $spec): ?>
                            <option value="<?= htmlspecialchars($spec); ?>"><?= htmlspecialchars($spec); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="flex:1;min-width:120px;">
                    <label style="color:#a78bfa;font-size:0.85rem;display:block;margin-bottom:5px;"><i class="fas fa-user-check"></i> Moderator</label>
                    <select id="supervisorModerator" class="form-control" onchange="filterAndSortSupervisors()">
                        <option value="all">All</option>
                        <option value="yes">Yes</option>
                        <option value="no">No</option>
                    </select>
                </div>
            </div>
            <?php if (empty($supervisors)): ?>
                <div class="empty-state"><i class="fas fa-user-tie"></i><p>No supervisors found</p></div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="data-table" id="supervisorTable">
                        <thead><tr><th>ID</th><th>Name</th><th>Room</th><th>Programme</th><th>Email</th><th>Contact</th><th>Specialization</th><th>Moderator</th><th>Actions</th></tr></thead>
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
                                <td><?= htmlspecialchars($sup['fyp_contactno'] ?? '-'); ?></td>
                                <td><?= htmlspecialchars($sup['fyp_specialization'] ?? '-'); ?></td>
                                <td><?= $sup['fyp_ismoderator'] ? '<span class="badge badge-approved">Yes</span>' : '<span class="badge badge-rejected">No</span>'; ?></td>
                                <td>
                                    <button class="btn btn-info btn-sm" onclick="viewSupervisorStudents(<?= $sup['fyp_supervisorid']; ?>, '<?= htmlspecialchars($sup['fyp_name'], ENT_QUOTES); ?>')" title="View Students">
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
    <script>var supervisorStudentsData = <?= json_encode($supervisor_students); ?>;</script>

<?php // ==================== STUDENT-SUPERVISOR PAIRING ====================
elseif ($current_page === 'pairing'): 
    $pairings = [];
    $res = $conn->query("SELECT p.*, s.fyp_studname, s.fyp_studfullid, s.fyp_email as student_email, 
                                sup.fyp_name as supervisor_name, sup.fyp_email as supervisor_email,
                                pr.fyp_projecttitle, pr.fyp_projecttype, pr.fyp_projectstatus,
                                ay.fyp_acdyear, ay.fyp_intake
                         FROM pairing p 
                         LEFT JOIN student s ON p.fyp_studid = s.fyp_studid 
                         LEFT JOIN supervisor sup ON p.fyp_supervisorid = sup.fyp_supervisorid 
                         LEFT JOIN project pr ON p.fyp_projectid = pr.fyp_projectid
                         LEFT JOIN academic_year ay ON p.fyp_academicid = ay.fyp_academicid
                         ORDER BY p.fyp_pairingid DESC");
    if ($res) { while ($row = $res->fetch_assoc()) { $pairings[] = $row; } }
    
    $pairing_supervisors = [];
    $res = $conn->query("SELECT DISTINCT sup.fyp_supervisorid, sup.fyp_name FROM pairing p JOIN supervisor sup ON p.fyp_supervisorid = sup.fyp_supervisorid ORDER BY sup.fyp_name");
    if ($res) { while ($row = $res->fetch_assoc()) { $pairing_supervisors[] = $row; } }
    
    $pairing_intakes = [];
    $res = $conn->query("SELECT DISTINCT ay.fyp_academicid, ay.fyp_acdyear, ay.fyp_intake FROM pairing p JOIN academic_year ay ON p.fyp_academicid = ay.fyp_academicid ORDER BY ay.fyp_acdyear DESC, ay.fyp_intake DESC");
    if ($res) { while ($row = $res->fetch_assoc()) { $pairing_intakes[] = $row; } }
?>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-link"></i> Student-Supervisor Pairings (<span id="pairingCount"><?= count($pairings); ?></span>)</h3>
            <div style="display:flex;gap:10px;">
                <button class="btn btn-secondary btn-sm" onclick="resetPairingFilters()"><i class="fas fa-redo"></i> Reset</button>
                <button class="btn btn-primary" onclick="openModal('addPairingModal')"><i class="fas fa-plus"></i> Add Pairing</button>
            </div>
        </div>
        <div class="card-body">
            <div class="filter-controls" style="display:flex;flex-wrap:wrap;gap:15px;margin-bottom:20px;padding:20px;background:rgba(139,92,246,0.1);border-radius:12px;">
                <div style="flex:2;min-width:200px;">
                    <label style="color:#a78bfa;font-size:0.85rem;display:block;margin-bottom:5px;"><i class="fas fa-search"></i> Search</label>
                    <input type="text" class="form-control" placeholder="Search student or project..." id="pairingSearch" onkeyup="filterPairings()">
                </div>
                <div style="flex:1;min-width:150px;">
                    <label style="color:#a78bfa;font-size:0.85rem;display:block;margin-bottom:5px;"><i class="fas fa-user-tie"></i> Supervisor</label>
                    <select id="pairingSupervisor" class="form-control" onchange="filterPairings()">
                        <option value="all">All Supervisors</option>
                        <?php foreach ($pairing_supervisors as $sup): ?>
                            <option value="<?= $sup['fyp_supervisorid']; ?>"><?= htmlspecialchars($sup['fyp_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="flex:1;min-width:150px;">
                    <label style="color:#a78bfa;font-size:0.85rem;display:block;margin-bottom:5px;"><i class="fas fa-calendar"></i> Intake</label>
                    <select id="pairingIntake" class="form-control" onchange="filterPairings()">
                        <option value="all">All Intakes</option>
                        <?php foreach ($pairing_intakes as $intake): ?>
                            <option value="<?= $intake['fyp_academicid']; ?>"><?= $intake['fyp_acdyear']; ?> - <?= $intake['fyp_intake']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="flex:1;min-width:120px;">
                    <label style="color:#a78bfa;font-size:0.85rem;display:block;margin-bottom:5px;"><i class="fas fa-th-list"></i> View</label>
                    <select id="pairingView" class="form-control" onchange="togglePairingView()">
                        <option value="table">Table View</option>
                        <option value="card">Card View</option>
                    </select>
                </div>
            </div>
            
            <div id="pairingTableView">
                <?php if (empty($pairings)): ?>
                    <div class="empty-state"><i class="fas fa-link"></i><p>No pairings found</p></div>
                <?php else: ?>
                    <div style="overflow-x:auto;">
                        <table class="data-table" id="pairingTable">
                            <thead><tr><th>ID</th><th>Student</th><th>Student ID</th><th>Supervisor</th><th>Project</th><th>Intake</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($pairings as $p): ?>
                                <tr data-supervisor="<?= $p['fyp_supervisorid']; ?>" data-intake="<?= $p['fyp_academicid'] ?? ''; ?>">
                                    <td><?= $p['fyp_pairingid']; ?></td>
                                    <td><?= htmlspecialchars($p['fyp_studname'] ?? '-'); ?></td>
                                    <td><?= htmlspecialchars($p['fyp_studfullid'] ?? '-'); ?></td>
                                    <td><?= htmlspecialchars($p['supervisor_name'] ?? '-'); ?></td>
                                    <td><?= htmlspecialchars($p['fyp_projecttitle'] ?? '-'); ?></td>
                                    <td><?= ($p['fyp_acdyear'] ?? '') . ' - ' . ($p['fyp_intake'] ?? ''); ?></td>
                                    <td>
                                        <button class="btn btn-info btn-sm" onclick="viewPairing(<?= $p['fyp_pairingid']; ?>)"><i class="fas fa-eye"></i></button>
                                        <button class="btn btn-danger btn-sm" onclick="confirmDeletePairing(<?= $p['fyp_pairingid']; ?>)"><i class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <div id="pairingCardView" style="display:none;">
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px;">
                    <?php foreach ($pairings as $p): ?>
                    <div class="card" style="padding:20px;" data-supervisor="<?= $p['fyp_supervisorid']; ?>" data-intake="<?= $p['fyp_academicid'] ?? ''; ?>">
                        <h4 style="color:#a78bfa;margin-bottom:15px;"><?= htmlspecialchars($p['fyp_studname'] ?? '-'); ?></h4>
                        <p><strong>ID:</strong> <?= htmlspecialchars($p['fyp_studfullid'] ?? '-'); ?></p>
                        <p><strong>Supervisor:</strong> <?= htmlspecialchars($p['supervisor_name'] ?? '-'); ?></p>
                        <p><strong>Project:</strong> <?= htmlspecialchars($p['fyp_projecttitle'] ?? '-'); ?></p>
                        <p><strong>Intake:</strong> <?= ($p['fyp_acdyear'] ?? '') . ' - ' . ($p['fyp_intake'] ?? ''); ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

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

    $categories = [];
    $res = $conn->query("SELECT DISTINCT fyp_projectcat FROM project WHERE fyp_projectcat IS NOT NULL AND fyp_projectcat != '' ORDER BY fyp_projectcat");
    if ($res) { while ($row = $res->fetch_assoc()) { $categories[] = $row['fyp_projectcat']; } }

    $supervisor_filter = [];
    $res = $conn->query("SELECT DISTINCT s.fyp_supervisorid, s.fyp_name FROM project p JOIN supervisor s ON p.fyp_supervisorid = s.fyp_supervisorid ORDER BY s.fyp_name");
    if ($res) { while ($row = $res->fetch_assoc()) { $supervisor_filter[] = $row; } }

    $total_projects_count = count($projects);
    $available_count = count(array_filter($projects, function($p) { return ($p['fyp_projectstatus'] ?? '') === 'Available'; }));
    $unavailable_count = count(array_filter($projects, function($p) { return ($p['fyp_projectstatus'] ?? '') === 'Unavailable'; }));
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
        <div class="stat-info"><h4><?= $total_projects_count; ?></h4><p>Total Projects</p></div>
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
        <h3><i class="fas fa-project-diagram"></i> Project Allocation (<span id="projectCount"><?= $total_projects_count; ?></span>)</h3>
        <div style="display:flex;gap:10px;">
            <button class="btn btn-secondary btn-sm" onclick="resetProjectFilters()"><i class="fas fa-redo"></i> Reset</button>
            <button class="btn btn-primary" onclick="openModal('addProjectModal')"><i class="fas fa-plus"></i> Add New Project</button>
        </div>
    </div>
    <div class="card-body">
        <div style="display:flex;flex-wrap:wrap;gap:15px;margin-bottom:20px;padding:20px;background:rgba(139,92,246,0.1);border-radius:12px;">
            <div style="flex:2;min-width:200px;">
                <label style="color:#a78bfa;font-size:0.85rem;display:block;margin-bottom:5px;"><i class="fas fa-search"></i> Search</label>
                <input type="text" id="projectSearch" class="form-control" placeholder="Search by title, supervisor..." onkeyup="filterProjects()">
            </div>
            <div style="flex:1;min-width:140px;">
                <label style="color:#a78bfa;font-size:0.85rem;display:block;margin-bottom:5px;"><i class="fas fa-filter"></i> Status</label>
                <select id="filterProjectStatus" class="form-control" onchange="filterProjects()">
                    <option value="all">All Status</option>
                    <option value="Available">Available</option>
                    <option value="Unavailable">Unavailable</option>
                </select>
            </div>
            <div style="flex:1;min-width:140px;">
                <label style="color:#a78bfa;font-size:0.85rem;display:block;margin-bottom:5px;"><i class="fas fa-tags"></i> Type</label>
                <select id="filterProjectType" class="form-control" onchange="filterProjects()">
                    <option value="all">All Types</option>
                    <option value="Research">Research</option>
                    <option value="Application">Application</option>
                </select>
            </div>
            <div style="flex:1;min-width:140px;">
                <label style="color:#a78bfa;font-size:0.85rem;display:block;margin-bottom:5px;"><i class="fas fa-layer-group"></i> Category</label>
                <select id="filterProjectCategory" class="form-control" onchange="filterProjects()">
                    <option value="all">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars(strtolower($cat)); ?>"><?= htmlspecialchars($cat); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex:1;min-width:150px;">
                <label style="color:#a78bfa;font-size:0.85rem;display:block;margin-bottom:5px;"><i class="fas fa-user-tie"></i> Supervisor</label>
                <select id="filterProjectSupervisor" class="form-control" onchange="filterProjects()">
                    <option value="all">All Supervisors</option>
                    <?php foreach ($supervisor_filter as $sup): ?>
                        <option value="<?= htmlspecialchars(strtolower($sup['fyp_name'])); ?>"><?= htmlspecialchars($sup['fyp_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <?php if (empty($projects)): ?>
            <div class="empty-state"><i class="fas fa-folder-open"></i><p>No projects found</p></div>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="data-table" id="projectTable">
                    <thead><tr><th>ID</th><th>Project Title</th><th>Type</th><th>Category</th><th>Status</th><th>Supervisor</th><th>Students</th><th>Created</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($projects as $p): ?>
                        <tr data-title="<?= htmlspecialchars(strtolower($p['fyp_projecttitle'])); ?>" 
                            data-status="<?= $p['fyp_projectstatus'] ?? ''; ?>" 
                            data-type="<?= $p['fyp_projecttype'] ?? ''; ?>"
                            data-category="<?= htmlspecialchars(strtolower($p['fyp_projectcat'] ?? '')); ?>"
                            data-supervisor="<?= htmlspecialchars(strtolower($p['supervisor_name'] ?? '')); ?>">
                            <td><?= $p['fyp_projectid']; ?></td>
                            <td>
                                <strong><?= htmlspecialchars($p['fyp_projecttitle']); ?></strong>
                                <?php if (!empty($p['fyp_projectdesc'])): ?>
                                    <br><small style="color:#64748b;"><?= htmlspecialchars(substr($p['fyp_projectdesc'], 0, 60)); ?><?= strlen($p['fyp_projectdesc']) > 60 ? '...' : ''; ?></small>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge" style="background:rgba(139,92,246,0.2);color:#a78bfa;"><?= $p['fyp_projecttype'] ?? '-'; ?></span></td>
                            <td><?= htmlspecialchars($p['fyp_projectcat'] ?? '-'); ?></td>
                            <td>
                                <form method="POST" style="display:inline;" id="toggleForm-<?= $p['fyp_projectid']; ?>">
                                    <input type="hidden" name="project_id" value="<?= $p['fyp_projectid']; ?>">
                                    <input type="hidden" name="new_status" id="newStatus-<?= $p['fyp_projectid']; ?>" value="">
                                    <input type="hidden" name="toggle_project_status" value="1">
                                    <label class="toggle-switch">
                                        <input type="checkbox" <?= ($p['fyp_projectstatus'] ?? '') === 'Available' ? 'checked' : ''; ?> 
                                               onchange="toggleProjectStatus(<?= $p['fyp_projectid']; ?>, this.checked)">
                                        <span class="toggle-slider"></span>
                                        <span class="toggle-label on">Open</span>
                                        <span class="toggle-label off">Closed</span>
                                    </label>
                                </form>
                            </td>
                            <td><?= htmlspecialchars($p['supervisor_name'] ?? '-'); ?></td>
                            <td style="text-align:center;"><span class="badge" style="background:rgba(139,92,246,0.2);color:#a78bfa;"><?= $p['student_count']; ?>/<?= $p['fyp_maxstudent'] ?? 2; ?></span></td>
                            <td><?= $p['fyp_datecreated'] ? date('M j, Y', strtotime($p['fyp_datecreated'])) : '-'; ?></td>
                            <td>
                                <button class="btn btn-primary btn-sm" onclick="openEditProjectModal(<?= htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8'); ?>)"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-info btn-sm" onclick="viewProjectDetails(<?= htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8'); ?>)"><i class="fas fa-eye"></i></button>
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

<?php // ==================== OTHER PAGES ====================
elseif ($current_page === 'rubrics'): ?>
<div class="card"><div class="card-header"><h3><i class="fas fa-list-check"></i> Rubrics Assessment</h3></div><div class="card-body"><p>Rubrics management coming soon...</p></div></div>

<?php elseif ($current_page === 'marks'): ?>
<div class="card"><div class="card-header"><h3><i class="fas fa-calculator"></i> Assessment Marks</h3></div><div class="card-body"><p>Assessment marks management coming soon...</p></div></div>

<?php elseif ($current_page === 'reports'): ?>
<div class="card"><div class="card-header"><h3><i class="fas fa-file-alt"></i> Reports</h3></div><div class="card-body"><p>Reports generation coming soon...</p></div></div>

<?php elseif ($current_page === 'announcements'): 
    $announcements = [];
    $res = $conn->query("SELECT * FROM announcement ORDER BY fyp_datecreated DESC");
    if ($res) { while ($row = $res->fetch_assoc()) { $announcements[] = $row; } }
?>
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-bullhorn"></i> Announcements</h3>
        <button class="btn btn-primary" onclick="openModal('addAnnouncementModal')"><i class="fas fa-plus"></i> New Announcement</button>
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
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="announcement_id" value="<?= $ann['fyp_announcementid'] ?? $ann['announcementid'] ?? $ann['id']; ?>">
                                <button type="submit" name="delete_announcement" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($current_page === 'settings'): ?>
<div class="card"><div class="card-header"><h3><i class="fas fa-cog"></i> Settings</h3></div><div class="card-body"><p>System settings coming soon...</p></div></div>

<?php endif; ?>

    </div><!-- End content -->
</div><!-- End main-content -->

<?php include("coordinator_scripts.php"); ?>
</body>
</html>