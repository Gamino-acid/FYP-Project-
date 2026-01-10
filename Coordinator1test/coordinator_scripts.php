<?php
/**
 * Coordinator Scripts - All Modals and JavaScript Functions
 * FIXED VERSION - Tab switching fixed
 */
?>

    </div><!-- End content -->
</div><!-- End main-content -->

<!-- ==================== MODALS ==================== -->

<!-- Add Student Modal -->
<div class="modal-overlay" id="addStudentModal">
    <div class="modal" style="max-width:500px;">
        <div class="modal-header">
            <h3><i class="fas fa-user-plus" style="color:#34d399;"></i> Add Student Manually</h3>
            <button class="modal-close" onclick="closeModal('addStudentModal')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <div class="form-group"><label>Email <span style="color:#f87171;">*</span></label><input type="email" name="new_email" class="form-control" placeholder="student@student.edu.my" required></div>
                <div class="form-group"><label>Full Name <span style="color:#f87171;">*</span></label><input type="text" name="new_studname" class="form-control" placeholder="Enter student's full name" required></div>
                <div class="form-group"><label>Full Student ID <span style="color:#f87171;">*</span></label><input type="text" name="new_studfullid" class="form-control" placeholder="e.g. TP055012" required></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addStudentModal')">Cancel</button>
                <button type="submit" name="add_student_manual" class="btn btn-success"><i class="fas fa-user-plus"></i> Add Student</button>
            </div>
        </form>
    </div>
</div>

<!-- Import Excel Modal -->
<div class="modal-overlay" id="importExcelModal">
    <div class="modal" style="max-width:600px;">
        <div class="modal-header"><h3><i class="fas fa-file-excel" style="color:#10b981;"></i> Import Students from Excel/CSV</h3><button class="modal-close" onclick="closeModal('importExcelModal')">&times;</button></div>
        <form method="POST" enctype="multipart/form-data">
            <div class="modal-body">
                <div style="background:rgba(59,130,246,0.1);padding:15px;border-radius:12px;margin-bottom:20px;"><h4 style="color:#60a5fa;margin-bottom:10px;">Required CSV/Excel Format</h4><p style="color:#94a3b8;font-size:0.9rem;">EMAIL, STUDENT_ID, NAME, PROGRAMME, CONTACT</p></div>
                <div class="form-group"><label><i class="fas fa-upload"></i> Select File <span style="color:#f87171;">*</span></label><input type="file" name="excel_file" accept=".csv,.xls,.xlsx" class="form-control" required style="padding:15px;background:rgba(15,15,26,0.6);border:2px dashed rgba(139,92,246,0.3);"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('importExcelModal')">Cancel</button><button type="submit" name="import_excel" class="btn btn-success"><i class="fas fa-upload"></i> Import Students</button></div>
        </form>
    </div>
</div>

<!-- Bulk Reject Modal -->
<div class="modal-overlay" id="bulkRejectModal">
    <div class="modal" style="max-width:500px;">
        <div class="modal-header"><h3><i class="fas fa-times-circle" style="color:#f87171;"></i> Bulk Reject</h3><button class="modal-close" onclick="closeModal('bulkRejectModal')">&times;</button></div>
        <div class="modal-body">
            <p style="margin-bottom:15px;">Reject <strong id="bulkRejectCount">0</strong> selected registration(s)?</p>
            <div class="form-group"><label>Reason for Rejection (optional)</label><textarea name="bulk_remarks" form="bulkForm" class="form-control" rows="3" placeholder="Enter reason..."></textarea></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('bulkRejectModal')">Cancel</button><button type="submit" name="bulk_reject" form="bulkForm" class="btn btn-danger"><i class="fas fa-times"></i> Reject Selected</button></div>
    </div>
</div>

<!-- Reject Registration Modal -->
<div class="modal-overlay" id="rejectRegModal">
    <div class="modal" style="max-width:500px;">
        <div class="modal-header"><h3><i class="fas fa-user-times" style="color:#f87171;"></i> Reject Registration</h3><button class="modal-close" onclick="closeModal('rejectRegModal')">&times;</button></div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="reg_id" id="reject_reg_id">
                <p>Reject registration for: <strong id="reject_reg_name"></strong></p>
                <div class="form-group"><label>Reason (optional)</label><textarea name="remarks" class="form-control" rows="3"></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('rejectRegModal')">Cancel</button><button type="submit" name="reject_registration" class="btn btn-danger"><i class="fas fa-times"></i> Reject</button></div>
        </form>
    </div>
</div>

<!-- Edit Student Modal -->
<div class="modal-overlay" id="editStudentModal">
    <div class="modal">
        <div class="modal-header"><h3><i class="fas fa-user-edit"></i> Edit Student</h3><button class="modal-close" onclick="closeModal('editStudentModal')">&times;</button></div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="studid" id="edit_studid">
                <div class="form-group"><label>Student Name</label><input type="text" name="studname" id="edit_studname" class="form-control" required></div>
                <div class="form-group"><label>Email</label><input type="email" name="email" id="edit_email" class="form-control"></div>
                <div class="form-group"><label>Contact Number</label><input type="text" name="contactno" id="edit_contactno" class="form-control"></div>
                <div class="form-row">
                    <div class="form-group"><label>Programme</label><select name="progid" id="edit_progid" class="form-control"><?php if(isset($programmes)): foreach ($programmes as $p): ?><option value="<?= $p['fyp_progid']; ?>"><?= htmlspecialchars($p['fyp_progname']); ?></option><?php endforeach; endif; ?></select></div>
                    <div class="form-group"><label>Group Type</label><select name="group_type" id="edit_group_type" class="form-control"><option value="Individual">Individual</option><option value="Group">Group</option></select></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('editStudentModal')">Cancel</button><button type="submit" name="edit_student" class="btn btn-primary"><i class="fas fa-save"></i> Save</button></div>
        </form>
    </div>
</div>

<!-- Delete Student Modal -->
<div class="modal-overlay" id="deleteStudentModal">
    <div class="modal" style="max-width:450px;">
        <div class="modal-header"><h3><i class="fas fa-exclamation-triangle" style="color:#f87171;"></i> Confirm Delete</h3><button class="modal-close" onclick="closeModal('deleteStudentModal')">&times;</button></div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="studid" id="delete_studid">
                <p style="text-align:center;">Delete student: <strong id="delete_studname"></strong>?</p>
                <p style="text-align:center;color:#f87171;font-size:0.85rem;">This cannot be undone!</p>
            </div>
            <div class="modal-footer" style="justify-content:center;"><button type="button" class="btn btn-secondary" onclick="closeModal('deleteStudentModal')">Cancel</button><button type="submit" name="delete_student" class="btn btn-danger"><i class="fas fa-trash"></i> Delete</button></div>
        </form>
    </div>
</div>

<!-- Create Pairing Modal -->
<div class="modal-overlay" id="createPairingModal">
    <div class="modal">
        <div class="modal-header"><h3><i class="fas fa-link" style="color:#a78bfa;"></i> Create New Pairing</h3><button class="modal-close" onclick="closeModal('createPairingModal')">&times;</button></div>
        <form method="POST">
            <div class="modal-body">
                <div class="form-group"><label>Supervisor <span style="color:#f87171;">*</span></label><select name="supervisor_id" class="form-control" required><option value="">-- Select Supervisor --</option><?php if (isset($sup_list)): foreach ($sup_list as $s): ?><option value="<?= $s['fyp_supervisorid']; ?>"><?= htmlspecialchars($s['fyp_name']); ?></option><?php endforeach; endif; ?></select></div>
                <div class="form-group"><label>Project <span style="color:#f87171;">*</span></label><select name="project_id" class="form-control" required><option value="">-- Select Project --</option><?php if (isset($proj_list)): foreach ($proj_list as $p): ?><option value="<?= $p['fyp_projectid']; ?>"><?= htmlspecialchars($p['fyp_projecttitle']); ?></option><?php endforeach; endif; ?></select></div>
                <div class="form-group"><label>Moderator</label><select name="moderator_id" class="form-control"><option value="">-- Select Moderator (Optional) --</option><?php if (isset($sup_list)): foreach ($sup_list as $s): ?><option value="<?= $s['fyp_supervisorid']; ?>"><?= htmlspecialchars($s['fyp_name']); ?></option><?php endforeach; endif; ?></select></div>
                <div class="form-row">
                    <div class="form-group"><label>Academic Year <span style="color:#f87171;">*</span></label><select name="academic_id" class="form-control" required><?php if(isset($academic_years)): foreach ($academic_years as $ay): ?><option value="<?= $ay['fyp_academicid']; ?>"><?= $ay['fyp_acdyear'] . ' ' . $ay['fyp_intake']; ?></option><?php endforeach; endif; ?></select></div>
                    <div class="form-group"><label>Type <span style="color:#f87171;">*</span></label><select name="pairing_type" class="form-control" required><option value="Individual">Individual</option><option value="Group">Group</option></select></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('createPairingModal')">Cancel</button><button type="submit" name="create_pairing" class="btn btn-primary"><i class="fas fa-link"></i> Create Pairing</button></div>
        </form>
    </div>
</div>

<!-- Delete Pairing Modal -->
<div class="modal-overlay" id="deletePairingModal">
    <div class="modal" style="max-width:450px;">
        <div class="modal-header"><h3><i class="fas fa-unlink" style="color:#f87171;"></i> Delete Pairing</h3><button class="modal-close" onclick="closeModal('deletePairingModal')">&times;</button></div>
        <form method="POST">
            <input type="hidden" name="pairing_id" id="delete_pairing_id">
            <div class="modal-body" style="text-align:center;">
                <i class="fas fa-exclamation-triangle" style="font-size:3rem;color:#f87171;margin-bottom:15px;"></i>
                <p>Delete this pairing?</p>
                <p><strong id="delete_pairing_info" style="color:#a78bfa;"></strong></p>
            </div>
            <div class="modal-footer" style="justify-content:center;"><button type="button" class="btn btn-secondary" onclick="closeModal('deletePairingModal')">Cancel</button><button type="submit" name="delete_pairing" class="btn btn-danger"><i class="fas fa-trash"></i> Delete</button></div>
        </form>
    </div>
</div>

<!-- Add Project Modal -->
<div class="modal-overlay" id="addProjectModal">
    <div class="modal" style="max-width:700px;">
        <div class="modal-header"><h3><i class="fas fa-plus-circle" style="color:#34d399;"></i> Add New Project</h3><button class="modal-close" onclick="closeModal('addProjectModal')">&times;</button></div>
        <form method="POST">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group" style="flex:2;"><label>Project Title <span style="color:#f87171;">*</span></label><input type="text" name="project_title" class="form-control" placeholder="Enter project title" required></div>
                    <div class="form-group" style="flex:1;"><label>Type</label><select name="project_type" class="form-control"><option value="Application" selected>Application</option><option value="Research">Research</option></select></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Category</label><input type="text" name="project_category" class="form-control" placeholder="e.g. Web, Mobile, AI"></div>
                    <div class="form-group"><label>Max Students</label><input type="number" name="max_students" class="form-control" value="2" min="1" max="10"></div>
                </div>
                <div class="form-group"><label>Description</label><textarea name="project_description" class="form-control" rows="3" placeholder="Brief description..."></textarea></div>
                <div class="form-group"><label>Supervisor</label><select name="supervisor_id" class="form-control"><option value="">-- Select Supervisor --</option><?php if (isset($supervisors_list)): foreach ($supervisors_list as $sv): ?><option value="<?= $sv['fyp_supervisorid']; ?>"><?= htmlspecialchars($sv['fyp_name']); ?></option><?php endforeach; endif; ?></select></div>
                <div style="background:rgba(16,185,129,0.1);padding:15px;border-radius:12px;border:1px solid rgba(16,185,129,0.3);"><p style="color:#34d399;margin:0;"><i class="fas fa-info-circle"></i> New projects are automatically set to <strong>Available</strong> status.</p></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('addProjectModal')">Cancel</button><button type="submit" name="add_project" class="btn btn-success"><i class="fas fa-plus"></i> Add Project</button></div>
        </form>
    </div>
</div>

<!-- Edit Project Modal -->
<div class="modal-overlay" id="editProjectModal">
    <div class="modal" style="max-width:700px;">
        <div class="modal-header"><h3><i class="fas fa-edit" style="color:#60a5fa;"></i> Edit Project</h3><button class="modal-close" onclick="closeModal('editProjectModal')">&times;</button></div>
        <form method="POST">
            <input type="hidden" name="project_id" id="edit_project_id">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group" style="flex:2;"><label>Project Title <span style="color:#f87171;">*</span></label><input type="text" name="project_title" id="edit_project_title" class="form-control" required></div>
                    <div class="form-group" style="flex:1;"><label>Type</label><select name="project_type" id="edit_project_type" class="form-control"><option value="Application">Application</option><option value="Research">Research</option></select></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Status</label><select name="project_status" id="edit_project_status" class="form-control"><option value="Available">Available</option><option value="Unavailable">Unavailable</option></select></div>
                    <div class="form-group"><label>Category</label><input type="text" name="project_category" id="edit_project_category" class="form-control"></div>
                </div>
                <div class="form-group"><label>Description</label><textarea name="project_description" id="edit_project_description" class="form-control" rows="3"></textarea></div>
                <div class="form-row">
                    <div class="form-group"><label>Supervisor</label><select name="supervisor_id" id="edit_supervisor_id" class="form-control"><option value="">-- Select --</option><?php if (isset($supervisors_list)): foreach ($supervisors_list as $sv): ?><option value="<?= $sv['fyp_supervisorid']; ?>"><?= htmlspecialchars($sv['fyp_name']); ?></option><?php endforeach; endif; ?></select></div>
                    <div class="form-group"><label>Max Students</label><input type="number" name="max_students" id="edit_max_students" class="form-control" min="1" max="10"></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('editProjectModal')">Cancel</button><button type="submit" name="update_project" class="btn btn-primary"><i class="fas fa-save"></i> Update Project</button></div>
        </form>
    </div>
</div>

<!-- Delete Project Modal -->
<div class="modal-overlay" id="deleteProjectModal">
    <div class="modal" style="max-width:450px;">
        <div class="modal-header"><h3><i class="fas fa-trash-alt" style="color:#f87171;"></i> Delete Project</h3><button class="modal-close" onclick="closeModal('deleteProjectModal')">&times;</button></div>
        <form method="POST">
            <input type="hidden" name="project_id" id="delete_project_id">
            <div class="modal-body" style="text-align:center;">
                <i class="fas fa-exclamation-triangle" style="font-size:3rem;color:#f87171;margin-bottom:15px;"></i>
                <p>Are you sure you want to delete this project?</p>
                <p><strong id="delete_project_title"></strong></p>
                <p style="color:#f87171;font-size:0.85rem;">This action cannot be undone!</p>
            </div>
            <div class="modal-footer" style="justify-content:center;"><button type="button" class="btn btn-secondary" onclick="closeModal('deleteProjectModal')">Cancel</button><button type="submit" name="delete_project" class="btn btn-danger"><i class="fas fa-trash"></i> Delete</button></div>
        </form>
    </div>
</div>

<!-- Create Announcement Modal -->
<div class="modal-overlay" id="createAnnouncementModal">
    <div class="modal">
        <div class="modal-header"><h3><i class="fas fa-bullhorn" style="color:#fbbf24;"></i> Create Announcement</h3><button class="modal-close" onclick="closeModal('createAnnouncementModal')">&times;</button></div>
        <form method="POST">
            <div class="modal-body">
                <div class="form-group"><label>Subject <span style="color:#f87171;">*</span></label><input type="text" name="subject" class="form-control" required></div>
                <div class="form-group"><label>Description <span style="color:#f87171;">*</span></label><textarea name="description" class="form-control" rows="4" required></textarea></div>
                <div class="form-group"><label>Send To <span style="color:#f87171;">*</span></label><select name="receiver" class="form-control" required><option value="All Students">All Students</option><option value="All Supervisors">All Supervisors</option><option value="Everyone">Everyone</option></select></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('createAnnouncementModal')">Cancel</button><button type="submit" name="create_announcement" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Post Announcement</button></div>
        </form>
    </div>
</div>

<!-- Unsend Announcement Modal -->
<div class="modal-overlay" id="unsendAnnouncementModal">
    <div class="modal" style="max-width:450px;">
        <div class="modal-header"><h3><i class="fas fa-trash-alt" style="color:#f87171;"></i> Unsend Announcement</h3><button class="modal-close" onclick="closeModal('unsendAnnouncementModal')">&times;</button></div>
        <form method="POST">
            <input type="hidden" name="announcement_id" id="unsendAnnId">
            <div class="modal-body" style="text-align:center;">
                <i class="fas fa-exclamation-triangle" style="font-size:3rem;color:#f87171;margin-bottom:15px;"></i>
                <p>Delete announcement: <strong id="unsendAnnSubject"></strong>?</p>
            </div>
            <div class="modal-footer" style="justify-content:center;"><button type="button" class="btn btn-secondary" onclick="closeModal('unsendAnnouncementModal')">Cancel</button><button type="submit" name="delete_announcement" class="btn btn-danger"><i class="fas fa-trash"></i> Unsend</button></div>
        </form>
    </div>
</div>

<!-- View Supervisor History Modal -->
<div class="modal-overlay" id="viewSupervisorHistoryModal">
    <div class="modal" style="max-width:700px;">
        <div class="modal-header"><h3><i class="fas fa-history" style="color:#a78bfa;"></i> <span id="supervisorHistoryTitle">Supervisor History</span></h3><button class="modal-close" onclick="closeModal('viewSupervisorHistoryModal')">&times;</button></div>
        <div class="modal-body" style="max-height:70vh;overflow-y:auto;"><div id="supervisorHistoryContent"></div></div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('viewSupervisorHistoryModal')">Close</button></div>
    </div>
</div>

<!-- ==================== JAVASCRIPT ==================== -->
<script>
// ==================== CORE MODAL FUNCTIONS ====================
function openModal(id) {
    var modal = document.getElementById(id);
    if (modal) modal.classList.add('active');
}

function closeModal(id) {
    var modal = document.getElementById(id);
    if (modal) modal.classList.remove('active');
}

function escapeHtml(text) {
    if (!text) return "";
    var div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
}

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('active');
    }
});

// ==================== REGISTRATION TAB FUNCTIONS - FIXED ====================
function showRegTab(tab, btn) {
    console.log('showRegTab called with tab:', tab); // Debug
    
    // Get all tab content divs
    var pendingTab = document.getElementById('pendingTab');
    var approvedTab = document.getElementById('approvedTab');
    var processedTab = document.getElementById('processedTab');
    
    // Hide all tabs first
    if (pendingTab) {
        pendingTab.style.display = 'none';
        console.log('Hiding pendingTab');
    }
    if (approvedTab) {
        approvedTab.style.display = 'none';
        console.log('Hiding approvedTab');
    }
    if (processedTab) {
        processedTab.style.display = 'none';
        console.log('Hiding processedTab');
    }
    
    // Remove active class from all tab buttons
    var buttons = document.querySelectorAll('.tab-btn');
    buttons.forEach(function(b) { 
        b.classList.remove('active'); 
    });
    
    // Show the selected tab and set active button
    if (tab === 'pending') {
        if (pendingTab) {
            pendingTab.style.display = 'block';
            console.log('Showing pendingTab');
        }
    } else if (tab === 'approved') {
        if (approvedTab) {
            approvedTab.style.display = 'block';
            console.log('Showing approvedTab');
        }
    } else if (tab === 'processed') {
        if (processedTab) {
            processedTab.style.display = 'block';
            console.log('Showing processedTab');
        }
    }
    
    // Add active class to clicked button
    if (btn) {
        btn.classList.add('active');
    }
    
    return false; // Prevent any default behavior
}

function toggleSelectAll() {
    var selectAll = document.getElementById("selectAll");
    var checkboxes = document.querySelectorAll(".reg-checkbox");
    var isChecked = selectAll ? selectAll.checked : false;
    checkboxes.forEach(function(cb) {
        var row = cb.closest("tr");
        if (row && row.style.display !== "none") cb.checked = isChecked;
    });
    updateSelectedCount();
}

function updateSelectedCount() {
    var count = document.querySelectorAll(".reg-checkbox:checked").length;
    var countEl = document.getElementById("selectedCount");
    if (countEl) countEl.textContent = count + " selected";
}

function openBulkRejectModal() {
    var count = document.querySelectorAll('.reg-checkbox:checked').length;
    if (count === 0) { alert('Please select at least one registration.'); return; }
    var countEl = document.getElementById('bulkRejectCount');
    if (countEl) countEl.textContent = count;
    openModal('bulkRejectModal');
}

function showRejectRegModal(id, name) {
    var regIdEl = document.getElementById('reject_reg_id');
    var regNameEl = document.getElementById('reject_reg_name');
    if (regIdEl) regIdEl.value = id;
    if (regNameEl) regNameEl.textContent = name;
    openModal('rejectRegModal');
}

function filterRegistrations() {
    var statusEl = document.getElementById('filterStatus');
    var status = statusEl ? statusEl.value : 'all';
    var rows = document.querySelectorAll('#processedTable tbody tr');
    rows.forEach(function(row) {
        var rowStatus = row.getAttribute('data-status');
        row.style.display = (status === 'all' || rowStatus === status) ? '' : 'none';
    });
}

// ==================== GROUP REQUESTS FUNCTIONS ====================
function filterGroupRequests() {
    var searchEl = document.getElementById('groupSearch');
    var statusEl = document.getElementById('groupStatus');
    var searchValue = searchEl ? searchEl.value.toLowerCase() : '';
    var statusValue = statusEl ? statusEl.value : 'all';
    var rows = document.querySelectorAll('#groupRequestTable tbody tr');
    
    rows.forEach(function(row) {
        var search = row.getAttribute('data-search') || '';
        var status = row.getAttribute('data-status') || '';
        var matchesSearch = !searchValue || search.indexOf(searchValue) !== -1;
        var matchesStatus = statusValue === 'all' || status === statusValue;
        row.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
    });
}

function filterGroupByStatus(status) {
    var statusEl = document.getElementById('groupStatus');
    if (statusEl) statusEl.value = status;
    filterGroupRequests();
}

function resetGroupFilters() {
    var searchEl = document.getElementById('groupSearch');
    var statusEl = document.getElementById('groupStatus');
    if (searchEl) searchEl.value = '';
    if (statusEl) statusEl.value = 'all';
    filterGroupRequests();
}

// ==================== STUDENT FUNCTIONS ====================
function filterStudents() {
    var searchEl = document.getElementById('studentSearch');
    var programmeEl = document.getElementById('studentProgramme');
    var typeEl = document.getElementById('studentGroupType');
    var sortEl = document.getElementById('studentSort');
    
    var searchValue = searchEl ? searchEl.value.toLowerCase() : '';
    var programmeValue = programmeEl ? programmeEl.value : 'all';
    var typeValue = typeEl ? typeEl.value : 'all';
    var sortValue = sortEl ? sortEl.value : 'name_asc';
    
    var table = document.getElementById('studentTable');
    if (!table) return;
    var tbody = table.querySelector('tbody');
    if (!tbody) return;
    
    var rows = Array.from(tbody.querySelectorAll('tr'));
    var visibleCount = 0;
    
    rows.forEach(function(row) {
        var id = row.getAttribute('data-id') || '';
        var name = row.getAttribute('data-name') || '';
        var programme = row.getAttribute('data-programme') || '';
        var type = row.getAttribute('data-type') || '';
        
        var matchesSearch = !searchValue || id.indexOf(searchValue) !== -1 || name.indexOf(searchValue) !== -1;
        var matchesProgramme = programmeValue === 'all' || programme === programmeValue;
        var matchesType = typeValue === 'all' || type === typeValue;
        
        if (matchesSearch && matchesProgramme && matchesType) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    var visibleRows = rows.filter(function(row) { return row.style.display !== 'none'; });
    visibleRows.sort(function(a, b) {
        var aName = a.getAttribute('data-name') || '';
        var bName = b.getAttribute('data-name') || '';
        var aId = a.getAttribute('data-id') || '';
        var bId = b.getAttribute('data-id') || '';
        switch(sortValue) {
            case 'name_asc': return aName.localeCompare(bName);
            case 'name_desc': return bName.localeCompare(aName);
            case 'id_asc': return aId.localeCompare(bId);
            case 'id_desc': return bId.localeCompare(aId);
            default: return 0;
        }
    });
    visibleRows.forEach(function(row) { tbody.appendChild(row); });
    
    var countEl = document.getElementById('studentCount');
    if (countEl) countEl.textContent = visibleCount;
}

function resetStudentFilters() {
    var searchEl = document.getElementById('studentSearch');
    var programmeEl = document.getElementById('studentProgramme');
    var typeEl = document.getElementById('studentGroupType');
    var sortEl = document.getElementById('studentSort');
    if (searchEl) searchEl.value = '';
    if (programmeEl) programmeEl.value = 'all';
    if (typeEl) typeEl.value = 'all';
    if (sortEl) sortEl.value = 'name_asc';
    filterStudents();
}

function openEditStudentModal(studid, studname, email, contactno, progid, group_type) {
    document.getElementById('edit_studid').value = studid;
    document.getElementById('edit_studname').value = studname;
    document.getElementById('edit_email').value = email || '';
    document.getElementById('edit_contactno').value = contactno || '';
    document.getElementById('edit_progid').value = progid;
    document.getElementById('edit_group_type').value = group_type || 'Individual';
    openModal('editStudentModal');
}

function confirmDeleteStudent(studid, studname) {
    document.getElementById('delete_studid').value = studid;
    document.getElementById('delete_studname').textContent = studname;
    openModal('deleteStudentModal');
}

// ==================== SUPERVISOR FUNCTIONS ====================
function filterSupervisors() {
    var searchEl = document.getElementById('supervisorSearch');
    var programmeEl = document.getElementById('supervisorProgramme');
    var specializationEl = document.getElementById('supervisorSpecialization');
    var moderatorEl = document.getElementById('supervisorModerator');
    var sortEl = document.getElementById('supervisorSort');
    
    var searchValue = searchEl ? searchEl.value.toLowerCase() : '';
    var programmeValue = programmeEl ? programmeEl.value.toLowerCase() : 'all';
    var specializationValue = specializationEl ? specializationEl.value.toLowerCase() : 'all';
    var moderatorValue = moderatorEl ? moderatorEl.value : 'all';
    var sortValue = sortEl ? sortEl.value : 'name_asc';
    
    var table = document.getElementById('supervisorTable');
    if (!table) return;
    var tbody = table.querySelector('tbody');
    if (!tbody) return;
    
    var rows = Array.from(tbody.querySelectorAll('tr'));
    var visibleCount = 0;
    
    rows.forEach(function(row) {
        var name = row.getAttribute('data-name') || '';
        var email = row.getAttribute('data-email') || '';
        var programme = row.getAttribute('data-programme') || '';
        var specialization = row.getAttribute('data-specialization') || '';
        var moderator = row.getAttribute('data-moderator') || '';
        
        var matchesSearch = !searchValue || name.indexOf(searchValue) !== -1 || email.indexOf(searchValue) !== -1;
        var matchesProgramme = programmeValue === 'all' || programme === programmeValue;
        var matchesSpecialization = specializationValue === 'all' || specialization === specializationValue;
        var matchesModerator = moderatorValue === 'all' || moderator === moderatorValue;
        
        if (matchesSearch && matchesProgramme && matchesSpecialization && matchesModerator) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    var visibleRows = rows.filter(function(row) { return row.style.display !== 'none'; });
    visibleRows.sort(function(a, b) {
        var aName = a.getAttribute('data-name') || '';
        var bName = b.getAttribute('data-name') || '';
        var aId = parseInt(a.getAttribute('data-id')) || 0;
        var bId = parseInt(b.getAttribute('data-id')) || 0;
        switch(sortValue) {
            case 'name_asc': return aName.localeCompare(bName);
            case 'name_desc': return bName.localeCompare(aName);
            case 'id_asc': return aId - bId;
            case 'id_desc': return bId - aId;
            default: return 0;
        }
    });
    visibleRows.forEach(function(row) { tbody.appendChild(row); });
    
    var countEl = document.getElementById('supervisorCount');
    if (countEl) countEl.textContent = visibleCount;
}

function resetSupervisorFilters() {
    var searchEl = document.getElementById('supervisorSearch');
    var programmeEl = document.getElementById('supervisorProgramme');
    var specializationEl = document.getElementById('supervisorSpecialization');
    var moderatorEl = document.getElementById('supervisorModerator');
    var sortEl = document.getElementById('supervisorSort');
    if (searchEl) searchEl.value = '';
    if (programmeEl) programmeEl.value = 'all';
    if (specializationEl) specializationEl.value = 'all';
    if (moderatorEl) moderatorEl.value = 'all';
    if (sortEl) sortEl.value = 'name_asc';
    filterSupervisors();
}

function viewSupervisorHistory(supervisorId, supervisorName) {
    var titleEl = document.getElementById('supervisorHistoryTitle');
    var contentEl = document.getElementById('supervisorHistoryContent');
    
    if (titleEl) titleEl.textContent = supervisorName + ' - Supervision History';
    
    var history = (typeof supervisorHistoryData !== 'undefined' && supervisorHistoryData[supervisorId]) ? supervisorHistoryData[supervisorId] : [];
    var content = '';
    
    if (!history || history.length === 0) {
        content = '<div class="empty-state" style="padding:40px;text-align:center;"><i class="fas fa-history" style="font-size:3rem;color:#4a4a6a;margin-bottom:15px;display:block;"></i><p style="color:#64748b;">No supervision history found for this supervisor.</p></div>';
    } else {
        var totalStudents = 0;
        history.forEach(function(h) { totalStudents += parseInt(h.student_count) || 0; });
        
        content = '<div style="display:flex;flex-direction:column;gap:15px;">';
        content += '<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(150px, 1fr));gap:15px;margin-bottom:10px;">';
        content += '<div style="background:rgba(16,185,129,0.1);padding:20px;border-radius:12px;text-align:center;"><div style="font-size:2rem;font-weight:700;color:#34d399;">' + totalStudents + '</div><div style="color:#94a3b8;font-size:0.85rem;">Total Students</div></div>';
        content += '<div style="background:rgba(139,92,246,0.1);padding:20px;border-radius:12px;text-align:center;"><div style="font-size:2rem;font-weight:700;color:#a78bfa;">' + history.length + '</div><div style="color:#94a3b8;font-size:0.85rem;">Semesters Active</div></div>';
        content += '</div>';
        content += '<div style="background:rgba(139,92,246,0.1);border-radius:12px;padding:20px;"><h4 style="color:#a78bfa;margin:0 0 15px 0;"><i class="fas fa-calendar-check" style="margin-right:10px;"></i>Semester History</h4><div style="display:flex;flex-direction:column;gap:10px;">';
        
        history.forEach(function(h, index) {
            var semester = (h.fyp_acdyear || 'Unknown') + ' ' + (h.fyp_intake || '');
            var isLatest = index === 0;
            var borderColor = isLatest ? '#34d399' : '#a78bfa';
            content += '<div style="display:flex;align-items:center;gap:15px;padding:12px;background:rgba(0,0,0,0.2);border-radius:8px;border-left:3px solid ' + borderColor + ';">';
            content += '<div style="flex:1;"><div style="color:#e2e8f0;font-weight:600;">' + escapeHtml(semester);
            if (isLatest) content += ' <span style="background:#34d399;color:#000;padding:2px 8px;border-radius:10px;font-size:0.7rem;margin-left:8px;">Current</span>';
            content += '</div></div><div style="background:rgba(139,92,246,0.3);padding:8px 15px;border-radius:20px;"><span style="color:#a78bfa;font-weight:600;">' + h.student_count + '</span><span style="color:#94a3b8;font-size:0.85rem;margin-left:5px;">student(s)</span></div></div>';
        });
        content += '</div></div></div>';
    }
    
    if (contentEl) contentEl.innerHTML = content;
    openModal('viewSupervisorHistoryModal');
}

// ==================== PAIRING FUNCTIONS ====================
function filterPairings() {
    var searchEl = document.getElementById('pairingSearch');
    var semesterEl = document.getElementById('pairingSemester');
    var supervisorEl = document.getElementById('pairingSupervisor');
    var sortEl = document.getElementById('pairingSort');
    
    var searchValue = searchEl ? searchEl.value.toLowerCase() : '';
    var semesterValue = semesterEl ? semesterEl.value : 'all';
    var supervisorValue = supervisorEl ? supervisorEl.value : 'all';
    var sortValue = sortEl ? sortEl.value : 'newest';
    
    var table = document.getElementById('pairingTable');
    if (!table) return;
    var tbody = table.querySelector('tbody');
    if (!tbody) return;
    
    var rows = Array.from(tbody.querySelectorAll('tr'));
    var visibleCount = 0;
    
    rows.forEach(function(row) {
        var search = row.getAttribute('data-search') || '';
        var semester = row.getAttribute('data-semester') || '';
        var supervisor = row.getAttribute('data-supervisor') || '';
        
        var matchesSearch = !searchValue || search.indexOf(searchValue) !== -1;
        var matchesSemester = semesterValue === 'all' || semester === semesterValue;
        var matchesSupervisor = supervisorValue === 'all' || supervisor === supervisorValue;
        
        if (matchesSearch && matchesSemester && matchesSupervisor) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    var visibleRows = rows.filter(function(row) { return row.style.display !== 'none'; });
    visibleRows.sort(function(a, b) {
        var aIndex = parseInt(a.getAttribute('data-index')) || 0;
        var bIndex = parseInt(b.getAttribute('data-index')) || 0;
        var aStudent = a.getAttribute('data-student') || '';
        var bStudent = b.getAttribute('data-student') || '';
        var aSupervisor = a.getAttribute('data-supervisor-name') || '';
        var bSupervisor = b.getAttribute('data-supervisor-name') || '';
        switch(sortValue) {
            case 'newest': return aIndex - bIndex;
            case 'oldest': return bIndex - aIndex;
            case 'student_az': return aStudent.localeCompare(bStudent);
            case 'student_za': return bStudent.localeCompare(aStudent);
            case 'supervisor_az': return aSupervisor.localeCompare(bSupervisor);
            case 'supervisor_za': return bSupervisor.localeCompare(aSupervisor);
            default: return 0;
        }
    });
    visibleRows.forEach(function(row) { tbody.appendChild(row); });
    
    var countEl = document.getElementById('pairingCount');
    if (countEl) countEl.textContent = visibleCount;
    
    var cards = document.querySelectorAll('.supervisor-card');
    cards.forEach(function(card) {
        var cardSupervisor = card.getAttribute('data-supervisor');
        card.style.display = (supervisorValue === 'all' || cardSupervisor === supervisorValue) ? '' : 'none';
    });
}

function resetPairingFilters() {
    var searchEl = document.getElementById('pairingSearch');
    var semesterEl = document.getElementById('pairingSemester');
    var supervisorEl = document.getElementById('pairingSupervisor');
    var sortEl = document.getElementById('pairingSort');
    if (searchEl) searchEl.value = '';
    if (semesterEl) semesterEl.value = 'all';
    if (supervisorEl) supervisorEl.value = 'all';
    if (sortEl) sortEl.value = 'newest';
    filterPairings();
}

function switchPairingView(view) {
    var tableView = document.getElementById('pairingTableView');
    var supervisorView = document.getElementById('pairingSupervisorView');
    var btnTable = document.getElementById('btnTableView');
    var btnSupervisor = document.getElementById('btnSupervisorView');
    
    if (view === 'table') {
        if (tableView) tableView.style.display = 'block';
        if (supervisorView) supervisorView.style.display = 'none';
        if (btnTable) btnTable.className = 'btn btn-primary btn-sm';
        if (btnSupervisor) btnSupervisor.className = 'btn btn-secondary btn-sm';
    } else {
        if (tableView) tableView.style.display = 'none';
        if (supervisorView) supervisorView.style.display = 'block';
        if (btnTable) btnTable.className = 'btn btn-secondary btn-sm';
        if (btnSupervisor) btnSupervisor.className = 'btn btn-primary btn-sm';
    }
}

function toggleSupervisorStudents(supId) {
    var content = document.getElementById('students-' + supId);
    var chevron = document.getElementById('chevron-' + supId);
    if (content && content.classList.contains('show')) {
        content.classList.remove('show');
        if (chevron) chevron.classList.remove('rotated');
    } else if (content) {
        content.classList.add('show');
        if (chevron) chevron.classList.add('rotated');
    }
}

function filterSupervisorCardStudents(supId, semesterId) {
    var table = document.getElementById('sup-table-' + supId);
    if (!table) return;
    var rows = table.querySelectorAll('tbody tr');
    rows.forEach(function(row) {
        var rowSemester = row.getAttribute('data-semester');
        row.style.display = (semesterId === 'all' || rowSemester === semesterId) ? '' : 'none';
    });
}

function setActiveTab(clickedTab) {
    var tabs = clickedTab.parentElement.querySelectorAll('.semester-tab');
    tabs.forEach(function(tab) { tab.classList.remove('active'); });
    clickedTab.classList.add('active');
}

function confirmDeletePairing(pairingId, info) {
    var idEl = document.getElementById('delete_pairing_id');
    var infoEl = document.getElementById('delete_pairing_info');
    if (idEl) idEl.value = pairingId;
    if (infoEl) infoEl.textContent = info;
    openModal('deletePairingModal');
}

// ==================== PROJECT FUNCTIONS ====================
function filterProjects() {
    var searchEl = document.getElementById('projectSearch');
    var statusEl = document.getElementById('filterProjectStatus');
    var typeEl = document.getElementById('filterProjectType');
    
    var searchValue = searchEl ? searchEl.value.toLowerCase() : '';
    var statusValue = statusEl ? statusEl.value : 'all';
    var typeValue = typeEl ? typeEl.value : 'all';
    
    var rows = document.querySelectorAll('#projectTable tbody tr');
    var visibleCount = 0;
    
    rows.forEach(function(row) {
        var title = row.getAttribute('data-title') || '';
        var status = row.getAttribute('data-status') || '';
        var type = row.getAttribute('data-type') || '';
        var supervisor = row.getAttribute('data-supervisor') || '';
        
        var matchesSearch = !searchValue || title.indexOf(searchValue) !== -1 || supervisor.indexOf(searchValue) !== -1;
        var matchesStatus = statusValue === 'all' || status === statusValue;
        var matchesType = typeValue === 'all' || type === typeValue;
        
        if (matchesSearch && matchesStatus && matchesType) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    var countEl = document.getElementById('projectCount');
    if (countEl) countEl.textContent = visibleCount;
}

function resetProjectFilters() {
    var searchEl = document.getElementById('projectSearch');
    var statusEl = document.getElementById('filterProjectStatus');
    var typeEl = document.getElementById('filterProjectType');
    if (searchEl) searchEl.value = '';
    if (statusEl) statusEl.value = 'all';
    if (typeEl) typeEl.value = 'all';
    filterProjects();
}

function quickFilterProject(status) {
    var statusEl = document.getElementById('filterProjectStatus');
    if (statusEl) statusEl.value = status === 'all' ? 'all' : status;
    filterProjects();
}

function toggleProjectStatus(projectId, isChecked) {
    var newStatus = isChecked ? 'Available' : 'Unavailable';
    var statusInput = document.getElementById('newStatus-' + projectId);
    var form = document.getElementById('toggleForm-' + projectId);
    if (statusInput) statusInput.value = newStatus;
    if (form) form.submit();
}

function openEditProjectModal(project) {
    document.getElementById('edit_project_id').value = project.fyp_projectid;
    document.getElementById('edit_project_title').value = project.fyp_projecttitle || '';
    document.getElementById('edit_project_type').value = project.fyp_projecttype || 'Application';
    document.getElementById('edit_project_status').value = project.fyp_projectstatus || 'Available';
    document.getElementById('edit_project_category').value = project.fyp_projectcat || '';
    document.getElementById('edit_project_description').value = project.fyp_description || project.fyp_projectdesc || '';
    document.getElementById('edit_supervisor_id').value = project.fyp_supervisorid || '';
    document.getElementById('edit_max_students').value = project.fyp_maxstudent || 2;
    openModal('editProjectModal');
}

function confirmDeleteProject(projectId, projectTitle) {
    document.getElementById('delete_project_id').value = projectId;
    document.getElementById('delete_project_title').textContent = projectTitle;
    openModal('deleteProjectModal');
}

// ==================== ANNOUNCEMENT FUNCTIONS ====================
function confirmUnsend(id, subject) {
    document.getElementById('unsendAnnId').value = id;
    document.getElementById('unsendAnnSubject').textContent = '"' + subject + '"';
    openModal('unsendAnnouncementModal');
}

// ==================== INITIALIZATION ====================
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded - initializing filters');
    
    // Check if tab elements exist for registration page
    var pendingTab = document.getElementById('pendingTab');
    var approvedTab = document.getElementById('approvedTab');
    var processedTab = document.getElementById('processedTab');
    
    if (pendingTab || approvedTab || processedTab) {
        console.log('Registration tabs found');
        // Make sure pending tab is shown by default
        if (pendingTab) pendingTab.style.display = 'block';
        if (approvedTab) approvedTab.style.display = 'none';
        if (processedTab) processedTab.style.display = 'none';
    }
    
    // Initialize other filters
    if (document.getElementById('studentSearch')) filterStudents();
    if (document.getElementById('supervisorSearch')) filterSupervisors();
    if (document.getElementById('pairingSearch')) filterPairings();
    if (document.getElementById('projectSearch')) filterProjects();
    if (document.getElementById('groupSearch')) filterGroupRequests();
});
</script>