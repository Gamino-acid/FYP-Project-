<?php
// Part 5 - All Modals and JavaScript

// Get data needed for modals
$sup_list = [];
$res = $conn->query("SELECT fyp_supervisorid, fyp_name FROM supervisor ORDER BY fyp_name");
if ($res) { while ($row = $res->fetch_assoc()) { $sup_list[] = $row; } }

$proj_list = [];
$res = $conn->query("SELECT fyp_projectid, fyp_projecttitle FROM project ORDER BY fyp_projecttitle");
if ($res) { while ($row = $res->fetch_assoc()) { $proj_list[] = $row; } }

$students_for_marks = [];
$res = $conn->query("SELECT fyp_studid, fyp_studfullid, fyp_studname FROM student ORDER BY fyp_studname");
if ($res) { while ($row = $res->fetch_assoc()) { $students_for_marks[] = $row; } }
?>

<!-- ==================== MODALS ==================== -->

<!-- Reject Registration Modal -->
<div class="modal-overlay" id="rejectModal">
    <div class="modal">
        <div class="modal-header"><h3>Reject Registration</h3><button class="modal-close" onclick="closeModal('rejectModal')">&times;</button></div>
        <form method="POST"><div class="modal-body">
            <input type="hidden" name="reg_id" id="reject_reg_id">
            <p style="margin-bottom:15px;">Reject <strong id="reject_name"></strong>'s registration?</p>
            <div class="form-group"><label>Reason (optional)</label><textarea name="remarks" class="form-control" rows="3" placeholder="Enter reason for rejection..."></textarea></div>
        </div><div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('rejectModal')">Cancel</button>
            <button type="submit" name="reject_registration" class="btn btn-danger">Reject</button>
        </div></form>
    </div>
</div>

<!-- Bulk Reject Modal -->
<div class="modal-overlay" id="bulkRejectModal">
    <div class="modal">
        <div class="modal-header"><h3>Bulk Reject Registrations</h3><button class="modal-close" onclick="closeModal('bulkRejectModal')">&times;</button></div>
        <form method="POST" id="bulkRejectForm"><div class="modal-body">
            <p style="margin-bottom:15px;">Reject <strong id="bulk_reject_count">0</strong> selected registration(s)?</p>
            <div class="form-group"><label>Reason (optional)</label><textarea name="bulk_remarks" class="form-control" rows="3" placeholder="Enter reason for rejection..."></textarea></div>
            <div id="bulk_reject_ids"></div>
        </div><div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('bulkRejectModal')">Cancel</button>
            <button type="submit" name="bulk_reject" class="btn btn-danger">Reject Selected</button>
        </div></form>
    </div>
</div>

<!-- Credentials Modal -->
<div class="modal-overlay" id="credentialsModal">
    <div class="modal">
        <div class="modal-header"><h3>Download Credentials</h3><button class="modal-close" onclick="closeModal('credentialsModal')">&times;</button></div>
        <div class="modal-body">
            <p style="margin-bottom:15px;">First approve the selected registrations, then you can download their credentials.</p>
            <p style="color:#94a3b8;">After approving students, a download link will appear in the Import Results section.</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('credentialsModal')">Close</button>
        </div>
    </div>
</div>

<!-- Import Excel Modal -->
<div class="modal-overlay" id="importExcelModal">
    <div class="modal">
        <div class="modal-header"><h3>Import Students from Excel</h3><button class="modal-close" onclick="closeModal('importExcelModal')">&times;</button></div>
        <form method="POST" enctype="multipart/form-data"><div class="modal-body">
            <div class="alert alert-warning" style="margin-bottom:20px;">
                <i class="fas fa-info-circle"></i> Excel file must have columns: <strong>Student ID, Full ID, Name, Email, Contact, Group Type</strong>
            </div>
            <div class="form-group">
                <label>Excel File (.xlsx, .xls, .csv)</label>
                <input type="file" name="excel_file" class="form-control" accept=".xlsx,.xls,.csv" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Academic Year</label>
                    <select name="import_academic_id" class="form-control" required>
                        <?php foreach ($academic_years as $ay): ?>
                        <option value="<?= $ay['fyp_academicid']; ?>"><?= $ay['fyp_acdyear'] . ' ' . $ay['fyp_intake']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Programme</label>
                    <select name="import_programme_id" class="form-control" required>
                        <?php foreach ($programmes as $p): ?>
                        <option value="<?= $p['fyp_progid']; ?>"><?= htmlspecialchars($p['fyp_progname']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div><div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('importExcelModal')">Cancel</button>
            <button type="submit" name="import_excel" class="btn btn-success"><i class="fas fa-upload"></i> Import</button>
        </div></form>
    </div>
</div>

<!-- Add Student Modal -->
<div class="modal-overlay" id="addStudentModal">
    <div class="modal">
        <div class="modal-header"><h3>Add Student Manually</h3><button class="modal-close" onclick="closeModal('addStudentModal')">&times;</button></div>
        <form method="POST"><div class="modal-body">
            <div class="form-row">
                <div class="form-group"><label>Student ID</label><input type="text" name="studid" class="form-control" placeholder="e.g. 12345" required></div>
                <div class="form-group"><label>Full ID</label><input type="text" name="studfullid" class="form-control" placeholder="e.g. CB12345" required></div>
            </div>
            <div class="form-group"><label>Full Name</label><input type="text" name="studname" class="form-control" placeholder="Student's full name" required></div>
            <div class="form-row">
                <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" placeholder="student@email.com" required></div>
                <div class="form-group"><label>Contact</label><input type="text" name="contact" class="form-control" placeholder="Phone number"></div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Group Type</label>
                    <select name="group_type" class="form-control">
                        <option value="Individual">Individual</option>
                        <option value="Group">Group</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Programme</label>
                    <select name="programme_id" class="form-control" required>
                        <?php foreach ($programmes as $p): ?>
                        <option value="<?= $p['fyp_progid']; ?>"><?= htmlspecialchars($p['fyp_progname']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Academic Year</label>
                <select name="academic_year_id" class="form-control" required>
                    <?php foreach ($academic_years as $ay): ?>
                    <option value="<?= $ay['fyp_academicid']; ?>"><?= $ay['fyp_acdyear'] . ' ' . $ay['fyp_intake']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div><div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('addStudentModal')">Cancel</button>
            <button type="submit" name="add_student_manual" class="btn btn-primary"><i class="fas fa-user-plus"></i> Add Student</button>
        </div></form>
    </div>
</div>

<!-- Edit Student Modal -->
<div class="modal-overlay" id="editStudentModal">
    <div class="modal">
        <div class="modal-header"><h3>Edit Student</h3><button class="modal-close" onclick="closeModal('editStudentModal')">&times;</button></div>
        <form method="POST"><div class="modal-body">
            <input type="hidden" name="edit_studid" id="edit_studid">
            <div class="form-group"><label>Student Name</label><input type="text" name="edit_studname" id="edit_studname" class="form-control" required></div>
            <div class="form-group"><label>Email</label><input type="email" name="edit_email" id="edit_email" class="form-control" required></div>
        </div><div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('editStudentModal')">Cancel</button>
            <button type="submit" name="update_student" class="btn btn-primary">Update Student</button>
        </div></form>
    </div>
</div>

<!-- Delete Student Modal -->
<div class="modal-overlay" id="deleteStudentModal">
    <div class="modal">
        <div class="modal-header"><h3>Delete Student</h3><button class="modal-close" onclick="closeModal('deleteStudentModal')">&times;</button></div>
        <form method="POST"><div class="modal-body">
            <input type="hidden" name="delete_studid" id="delete_studid">
            <p>Are you sure you want to delete <strong id="delete_studname"></strong>?</p>
            <p style="color:#f87171;margin-top:10px;"><i class="fas fa-exclamation-triangle"></i> This action cannot be undone.</p>
        </div><div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('deleteStudentModal')">Cancel</button>
            <button type="submit" name="delete_student" class="btn btn-danger">Delete Student</button>
        </div></form>
    </div>
</div>

<!-- Create Pairing Modal -->
<div class="modal-overlay" id="createPairingModal">
    <div class="modal">
        <div class="modal-header"><h3>Create New Pairing</h3><button class="modal-close" onclick="closeModal('createPairingModal')">&times;</button></div>
        <form method="POST"><div class="modal-body">
            <div class="form-group"><label>Supervisor</label>
                <select name="supervisor_id" class="form-control" required>
                    <option value="">-- Select Supervisor --</option>
                    <?php foreach ($sup_list as $s): ?><option value="<?= $s['fyp_supervisorid']; ?>"><?= htmlspecialchars($s['fyp_name']); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Project</label>
                <select name="project_id" class="form-control" required>
                    <option value="">-- Select Project --</option>
                    <?php foreach ($proj_list as $p): ?><option value="<?= $p['fyp_projectid']; ?>"><?= htmlspecialchars($p['fyp_projecttitle']); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Moderator</label>
                <select name="moderator_id" class="form-control">
                    <option value="">-- Select Moderator --</option>
                    <?php foreach ($sup_list as $s): ?><option value="<?= $s['fyp_supervisorid']; ?>"><?= htmlspecialchars($s['fyp_name']); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Academic Year</label>
                    <select name="academic_id" class="form-control" required>
                        <?php foreach ($academic_years as $ay): ?><option value="<?= $ay['fyp_academicid']; ?>"><?= $ay['fyp_acdyear'] . ' ' . $ay['fyp_intake']; ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Type</label>
                    <select name="pairing_type" class="form-control" required>
                        <option value="Individual">Individual</option>
                        <option value="Group">Group</option>
                    </select>
                </div>
            </div>
        </div><div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('createPairingModal')">Cancel</button>
            <button type="submit" name="create_pairing" class="btn btn-primary">Create Pairing</button>
        </div></form>
    </div>
</div>

<!-- Add Project Modal -->
<div class="modal-overlay" id="addProjectModal">
    <div class="modal">
        <div class="modal-header"><h3>Add New Project</h3><button class="modal-close" onclick="closeModal('addProjectModal')">&times;</button></div>
        <form method="POST"><div class="modal-body">
            <div class="form-group"><label>Project Title</label><input type="text" name="project_title" class="form-control" placeholder="Enter project title" required></div>
            <div class="form-group"><label>Description</label><textarea name="project_description" class="form-control" rows="3" placeholder="Project description (optional)"></textarea></div>
            <div class="form-row">
                <div class="form-group"><label>Supervisor</label>
                    <select name="project_supervisor_id" class="form-control">
                        <option value="">-- Select Supervisor --</option>
                        <?php foreach ($sup_list as $s): ?><option value="<?= $s['fyp_supervisorid']; ?>"><?= htmlspecialchars($s['fyp_name']); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Status</label>
                    <select name="project_status" class="form-control">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                        <option value="Completed">Completed</option>
                    </select>
                </div>
            </div>
            <div class="form-group"><label>Academic Year</label>
                <select name="project_academic_id" class="form-control" required>
                    <?php foreach ($academic_years as $ay): ?><option value="<?= $ay['fyp_academicid']; ?>"><?= $ay['fyp_acdyear'] . ' ' . $ay['fyp_intake']; ?></option><?php endforeach; ?>
                </select>
            </div>
        </div><div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('addProjectModal')">Cancel</button>
            <button type="submit" name="add_project" class="btn btn-primary">Add Project</button>
        </div></form>
    </div>
</div>

<!-- Edit Project Modal -->
<div class="modal-overlay" id="editProjectModal">
    <div class="modal">
        <div class="modal-header"><h3>Edit Project</h3><button class="modal-close" onclick="closeModal('editProjectModal')">&times;</button></div>
        <form method="POST"><div class="modal-body">
            <input type="hidden" name="edit_project_id" id="edit_project_id">
            <div class="form-group"><label>Project Title</label><input type="text" name="edit_project_title" id="edit_project_title" class="form-control" required></div>
        </div><div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('editProjectModal')">Cancel</button>
            <button type="submit" name="update_project" class="btn btn-primary">Update Project</button>
        </div></form>
    </div>
</div>

<!-- Delete Project Modal -->
<div class="modal-overlay" id="deleteProjectModal">
    <div class="modal">
        <div class="modal-header"><h3>Delete Project</h3><button class="modal-close" onclick="closeModal('deleteProjectModal')">&times;</button></div>
        <form method="POST"><div class="modal-body">
            <input type="hidden" name="delete_project_id" id="delete_project_id">
            <p>Are you sure you want to delete <strong id="delete_project_title"></strong>?</p>
            <p style="color:#f87171;margin-top:10px;"><i class="fas fa-exclamation-triangle"></i> This action cannot be undone.</p>
        </div><div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('deleteProjectModal')">Cancel</button>
            <button type="submit" name="delete_project" class="btn btn-danger">Delete Project</button>
        </div></form>
    </div>
</div>

<!-- Create Assessment Set Modal -->
<div class="modal-overlay" id="createSetModal">
    <div class="modal">
        <div class="modal-header"><h3>Create Assessment Set</h3><button class="modal-close" onclick="closeModal('createSetModal')">&times;</button></div>
        <form method="POST"><div class="modal-body">
            <div class="form-group"><label>Set Name</label><input type="text" name="set_name" class="form-control" placeholder="e.g. FYP Assessment 2024" required></div>
            <div class="form-group"><label>Academic Year</label>
                <select name="academic_id" class="form-control" required>
                    <?php foreach ($academic_years as $ay): ?><option value="<?= $ay['fyp_academicid']; ?>"><?= $ay['fyp_acdyear'] . ' ' . $ay['fyp_intake']; ?></option><?php endforeach; ?>
                </select>
            </div>
        </div><div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('createSetModal')">Cancel</button>
            <button type="submit" name="create_set" class="btn btn-primary">Create Set</button>
        </div></form>
    </div>
</div>

<!-- Add Mark Modal -->
<div class="modal-overlay" id="addMarkModal">
    <div class="modal">
        <div class="modal-header"><h3>Add Assessment Mark</h3><button class="modal-close" onclick="closeModal('addMarkModal')">&times;</button></div>
        <form method="POST"><div class="modal-body">
            <div class="form-group"><label>Student</label>
                <select name="mark_studid" class="form-control" required>
                    <option value="">-- Select Student --</option>
                    <?php foreach ($students_for_marks as $s): ?><option value="<?= $s['fyp_studid']; ?>"><?= htmlspecialchars($s['fyp_studfullid'] . ' - ' . $s['fyp_studname']); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Supervisor Mark (%)</label><input type="number" name="supervisor_mark" class="form-control" min="0" max="100" step="0.01" placeholder="0-100" required></div>
                <div class="form-group"><label>Moderator Mark (%)</label><input type="number" name="moderator_mark" class="form-control" min="0" max="100" step="0.01" placeholder="0-100" required></div>
            </div>
        </div><div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('addMarkModal')">Cancel</button>
            <button type="submit" name="add_mark" class="btn btn-primary">Add Mark</button>
        </div></form>
    </div>
</div>

<!-- Edit Mark Modal -->
<div class="modal-overlay" id="editMarkModal">
    <div class="modal">
        <div class="modal-header"><h3>Edit Assessment Mark</h3><button class="modal-close" onclick="closeModal('editMarkModal')">&times;</button></div>
        <form method="POST"><div class="modal-body">
            <input type="hidden" name="edit_mark_studid" id="edit_mark_studid">
            <div class="form-group"><label>Student: <strong id="edit_mark_student_name"></strong></label></div>
            <div class="form-row">
                <div class="form-group"><label>Supervisor Mark (%)</label><input type="number" name="edit_supervisor_mark" id="edit_supervisor_mark" class="form-control" min="0" max="100" step="0.01" required></div>
                <div class="form-group"><label>Moderator Mark (%)</label><input type="number" name="edit_moderator_mark" id="edit_moderator_mark" class="form-control" min="0" max="100" step="0.01" required></div>
            </div>
        </div><div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('editMarkModal')">Cancel</button>
            <button type="submit" name="update_mark" class="btn btn-primary">Update Mark</button>
        </div></form>
    </div>
</div>

<!-- Create Announcement Modal -->
<div class="modal-overlay" id="createAnnouncementModal">
    <div class="modal">
        <div class="modal-header"><h3>Create Announcement</h3><button class="modal-close" onclick="closeModal('createAnnouncementModal')">&times;</button></div>
        <form method="POST"><div class="modal-body">
            <div class="form-group"><label>Subject</label><input type="text" name="subject" class="form-control" placeholder="Announcement subject" required></div>
            <div class="form-group"><label>Description</label><textarea name="description" class="form-control" rows="4" placeholder="Announcement details..." required></textarea></div>
            <div class="form-group"><label>Send To</label>
                <select name="receiver" class="form-control" required>
                    <option value="All Students">All Students</option>
                    <option value="All Supervisors">All Supervisors</option>
                    <option value="Everyone">Everyone</option>
                </select>
            </div>
        </div><div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('createAnnouncementModal')">Cancel</button>
            <button type="submit" name="create_announcement" class="btn btn-primary">Post Announcement</button>
        </div></form>
    </div>
</div>

<!-- ==================== JAVASCRIPT ==================== -->
<script>
// Modal Functions
function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }

// Close modal on overlay click
document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', function(e) { 
        if (e.target === this) this.classList.remove('active'); 
    });
});

// Reject Registration Modal
function showRejectModal(id, name) {
    document.getElementById('reject_reg_id').value = id;
    document.getElementById('reject_name').textContent = name;
    openModal('rejectModal');
}

// Bulk Reject Modal
function openBulkRejectModal() {
    const checkboxes = document.querySelectorAll('input[name="pending_ids[]"]:checked');
    if (checkboxes.length === 0) {
        alert('Please select at least one registration to reject.');
        return;
    }
    
    document.getElementById('bulk_reject_count').textContent = checkboxes.length;
    
    const idsContainer = document.getElementById('bulk_reject_ids');
    idsContainer.innerHTML = '';
    checkboxes.forEach(cb => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'reject_ids[]';
        input.value = cb.value;
        idsContainer.appendChild(input);
    });
    
    openModal('bulkRejectModal');
}

// Credentials Modal
function openCredentialsModal() {
    openModal('credentialsModal');
}

// Select All Toggle
function toggleSelectAll(checkbox, name) {
    const checkboxes = document.querySelectorAll('input[name="' + name + '"]');
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
}

// Edit Student Modal
function showEditStudentModal(id, name, email) {
    document.getElementById('edit_studid').value = id;
    document.getElementById('edit_studname').value = name;
    document.getElementById('edit_email').value = email;
    openModal('editStudentModal');
}

// Delete Student Modal
function showDeleteStudentModal(id, name) {
    document.getElementById('delete_studid').value = id;
    document.getElementById('delete_studname').textContent = name;
    openModal('deleteStudentModal');
}

// Edit Project Modal
function showEditProjectModal(id, title) {
    document.getElementById('edit_project_id').value = id;
    document.getElementById('edit_project_title').value = title;
    openModal('editProjectModal');
}

// Delete Project Modal
function showDeleteProjectModal(id, title) {
    document.getElementById('delete_project_id').value = id;
    document.getElementById('delete_project_title').textContent = title;
    openModal('deleteProjectModal');
}

// Edit Mark Modal
function showEditMarkModal(studid, name, supMark, modMark) {
    document.getElementById('edit_mark_studid').value = studid;
    document.getElementById('edit_mark_student_name').textContent = name;
    document.getElementById('edit_supervisor_mark').value = supMark;
    document.getElementById('edit_moderator_mark').value = modMark;
    openModal('editMarkModal');
}

// Table Filter Function
function filterTable(inputId, tableId) {
    const filter = document.getElementById(inputId).value.toLowerCase();
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const rows = table.getElementsByTagName('tr');
    for (let i = 1; i < rows.length; i++) {
        let found = false;
        const cells = rows[i].getElementsByTagName('td');
        for (let j = 0; j < cells.length; j++) {
            if (cells[j].textContent.toLowerCase().indexOf(filter) > -1) {
                found = true;
                break;
            }
        }
        rows[i].style.display = found ? '' : 'none';
    }
}

// Keyboard shortcut to close modals (Escape key)
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
    }
});
</script>

</body>
</html>