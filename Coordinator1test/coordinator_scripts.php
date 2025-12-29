<?php
/**
 * Coordinator Main Page - Part 6
 * All Modals and JavaScript Functions
 */
?>

    </div><!-- End content -->
</div><!-- End main-content -->

<!-- ==================== MODALS ==================== -->

<!-- Add Student Modal -->
<div class="modal-overlay" id="addStudentModal">
    <div class="modal" style="max-width:500px;">
        <div class="modal-header"><h3><i class="fas fa-user-plus" style="color:#34d399;"></i> Add Student Manually</h3><button class="modal-close" onclick="closeModal('addStudentModal')">&times;</button></div>
        <form method="POST"><div class="modal-body">
            <div class="form-group"><label>Email <span style="color:#f87171;">*</span></label><input type="email" name="new_email" class="form-control" placeholder="student@student.edu.my" required></div>
            <div class="form-group"><label>Full Name <span style="color:#f87171;">*</span></label><input type="text" name="new_studname" class="form-control" placeholder="Enter student's full name" required></div>
            <div class="form-group"><label>Full Student ID <span style="color:#f87171;">*</span></label><input type="text" name="new_studfullid" class="form-control" placeholder="e.g. TP055012" required></div>
        </div><div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('addStudentModal')">Cancel</button>
            <button type="submit" name="add_student_manual" class="btn btn-success"><i class="fas fa-user-plus"></i> Add Student</button>
        </div></form>
    </div>
</div>

<!-- Import Excel Modal -->
<div class="modal-overlay" id="importExcelModal">
    <div class="modal" style="max-width:600px;">
        <div class="modal-header"><h3><i class="fas fa-file-excel" style="color:#10b981;"></i> Import Students from Excel/CSV</h3><button class="modal-close" onclick="closeModal('importExcelModal')">&times;</button></div>
        <form method="POST" enctype="multipart/form-data"><div class="modal-body">
            <div style="background:rgba(59,130,246,0.1);padding:15px;border-radius:12px;margin-bottom:20px;">
                <h4 style="color:#60a5fa;margin-bottom:10px;">Required CSV/Excel Format</h4>
                <p style="color:#94a3b8;font-size:0.9rem;">EMAIL, STUDENT_ID, NAME, PROGRAMME, CONTACT</p>
            </div>
            <div class="form-group"><label><i class="fas fa-upload"></i> Select File <span style="color:#f87171;">*</span></label>
                <input type="file" name="excel_file" accept=".csv,.xls,.xlsx" class="form-control" required style="padding:15px;background:rgba(15,15,26,0.6);border:2px dashed rgba(139,92,246,0.3);">
            </div>
        </div><div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('importExcelModal')">Cancel</button>
            <button type="submit" name="import_excel" class="btn btn-success"><i class="fas fa-upload"></i> Import Students</button>
        </div></form>
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
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('bulkRejectModal')">Cancel</button>
            <button type="submit" name="bulk_reject" form="bulkForm" class="btn btn-danger"><i class="fas fa-times"></i> Reject Selected</button>
        </div>
    </div>
</div>

<!-- Reject Registration Modal -->
<div class="modal-overlay" id="rejectRegModal">
    <div class="modal" style="max-width:500px;">
        <div class="modal-header"><h3><i class="fas fa-user-times" style="color:#f87171;"></i> Reject Registration</h3><button class="modal-close" onclick="closeModal('rejectRegModal')">&times;</button></div>
        <form method="POST"><div class="modal-body">
            <input type="hidden" name="reg_id" id="reject_reg_id">
            <p>Reject registration for: <strong id="reject_reg_name"></strong></p>
            <div class="form-group"><label>Reason (optional)</label><textarea name="remarks" class="form-control" rows="3"></textarea></div>
        </div><div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('rejectRegModal')">Cancel</button>
            <button type="submit" name="reject_registration" class="btn btn-danger"><i class="fas fa-times"></i> Reject</button>
        </div></form>
    </div>
</div>

<!-- Credentials Modal -->
<?php $last_cred = $_SESSION['last_approved_credentials'] ?? null; ?>
<div class="modal-overlay" id="credentialsModal">
    <div class="modal" style="max-width:500px;">
        <div class="modal-header"><h3><i class="fas fa-key" style="color:#34d399;"></i> Login Credentials</h3><button class="modal-close" onclick="closeModal('credentialsModal')">&times;</button></div>
        <div class="modal-body">
            <?php if ($last_cred): ?>
            <div style="text-align:center;margin-bottom:20px;"><h4><?= htmlspecialchars($last_cred['name']); ?></h4><p style="color:#94a3b8;"><?= htmlspecialchars($last_cred['student_id'] ?? ''); ?></p></div>
            <div style="background:rgba(16,185,129,0.1);padding:20px;border-radius:12px;">
                <div style="margin-bottom:15px;"><label style="color:#94a3b8;">Email (Username)</label><div style="background:rgba(0,0,0,0.3);padding:12px;border-radius:8px;color:#e2e8f0;"><?= htmlspecialchars($last_cred['email']); ?></div></div>
                <div><label style="color:#94a3b8;">Temporary Password</label><div style="background:rgba(251,191,36,0.2);padding:12px;border-radius:8px;color:#fbbf24;font-family:monospace;"><?= htmlspecialchars($last_cred['password']); ?></div></div>
            </div>
            <?php else: ?><div class="empty-state"><i class="fas fa-key"></i><p>No credentials available</p></div><?php endif; ?>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('credentialsModal')">Close</button></div>
    </div>
</div>

<!-- Bulk Credentials Modal -->
<?php $bulk_creds = $_SESSION['bulk_approved_credentials'] ?? []; ?>
<div class="modal-overlay" id="bulkCredentialsModal">
    <div class="modal" style="max-width:700px;">
        <div class="modal-header"><h3><i class="fas fa-users" style="color:#34d399;"></i> Approved Students Credentials (<?= count($bulk_creds); ?>)</h3><button class="modal-close" onclick="closeModal('bulkCredentialsModal')">&times;</button></div>
        <div class="modal-body" style="max-height:60vh;overflow-y:auto;">
            <?php if (!empty($bulk_creds)): ?>
            <table class="data-table"><thead><tr><th>Name</th><th>Email</th><th>Password</th></tr></thead><tbody>
                <?php foreach ($bulk_creds as $cred): ?>
                <tr><td><?= htmlspecialchars($cred['name']); ?></td><td><?= htmlspecialchars($cred['email']); ?></td><td><code style="background:rgba(251,191,36,0.2);padding:4px 10px;border-radius:4px;color:#fbbf24;"><?= htmlspecialchars($cred['password']); ?></code></td></tr>
                <?php endforeach; ?>
            </tbody></table>
            <?php endif; ?>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('bulkCredentialsModal')">Close</button></div>
    </div>
</div>

<!-- Edit Student Modal -->
<div class="modal-overlay" id="editStudentModal">
    <div class="modal">
        <div class="modal-header"><h3><i class="fas fa-user-edit"></i> Edit Student</h3><button class="modal-close" onclick="closeModal('editStudentModal')">&times;</button></div>
        <form method="POST"><div class="modal-body">
            <input type="hidden" name="studid" id="edit_studid">
            <div class="form-group"><label>Student Name</label><input type="text" name="studname" id="edit_studname" class="form-control" required></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" id="edit_email" class="form-control"></div>
            <div class="form-group"><label>Contact Number</label><input type="text" name="contactno" id="edit_contactno" class="form-control"></div>
            <div class="form-row">
                <div class="form-group"><label>Programme</label><select name="progid" id="edit_progid" class="form-control"><?php foreach ($programmes as $p): ?><option value="<?= $p['fyp_progid']; ?>"><?= htmlspecialchars($p['fyp_progname']); ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Group Type</label><select name="group_type" id="edit_group_type" class="form-control"><option value="Individual">Individual</option><option value="Group">Group</option></select></div>
            </div>
        </div><div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('editStudentModal')">Cancel</button>
            <button type="submit" name="edit_student" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
        </div></form>
    </div>
</div>

<!-- Delete Student Modal -->
<div class="modal-overlay" id="deleteStudentModal">
    <div class="modal" style="max-width:450px;">
        <div class="modal-header"><h3><i class="fas fa-exclamation-triangle" style="color:#f87171;"></i> Confirm Delete</h3><button class="modal-close" onclick="closeModal('deleteStudentModal')">&times;</button></div>
        <form method="POST"><div class="modal-body">
            <input type="hidden" name="studid" id="delete_studid">
            <p style="text-align:center;">Delete student: <strong id="delete_studname"></strong>?</p>
            <p style="text-align:center;color:#f87171;font-size:0.85rem;">This cannot be undone!</p>
        </div><div class="modal-footer" style="justify-content:center;">
            <button type="button" class="btn btn-secondary" onclick="closeModal('deleteStudentModal')">Cancel</button>
            <button type="submit" name="delete_student" class="btn btn-danger"><i class="fas fa-trash"></i> Delete</button>
        </div></form>
    </div>
</div>

<!-- Create Pairing Modal -->
<div class="modal-overlay" id="createPairingModal">
    <div class="modal">
        <div class="modal-header"><h3>Create New Pairing</h3><button class="modal-close" onclick="closeModal('createPairingModal')">&times;</button></div>
        <form method="POST"><div class="modal-body">
            <div class="form-group"><label>Supervisor</label><select name="supervisor_id" class="form-control" required><option value="">-- Select --</option><?php if (isset($sup_list)): foreach ($sup_list as $s): ?><option value="<?= $s['fyp_supervisorid']; ?>"><?= htmlspecialchars($s['fyp_name']); ?></option><?php endforeach; endif; ?></select></div>
            <div class="form-group"><label>Project</label><select name="project_id" class="form-control" required><option value="">-- Select --</option><?php if (isset($proj_list)): foreach ($proj_list as $p): ?><option value="<?= $p['fyp_projectid']; ?>"><?= htmlspecialchars($p['fyp_projecttitle']); ?></option><?php endforeach; endif; ?></select></div>
            <div class="form-group"><label>Moderator</label><select name="moderator_id" class="form-control"><option value="">-- Select --</option><?php if (isset($sup_list)): foreach ($sup_list as $s): ?><option value="<?= $s['fyp_supervisorid']; ?>"><?= htmlspecialchars($s['fyp_name']); ?></option><?php endforeach; endif; ?></select></div>
            <div class="form-row">
                <div class="form-group"><label>Academic Year</label><select name="academic_id" class="form-control" required><?php foreach ($academic_years as $ay): ?><option value="<?= $ay['fyp_academicid']; ?>"><?= $ay['fyp_acdyear'] . ' ' . $ay['fyp_intake']; ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Type</label><select name="pairing_type" class="form-control" required><option value="Individual">Individual</option><option value="Group">Group</option></select></div>
            </div>
        </div><div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('createPairingModal')">Cancel</button>
            <button type="submit" name="create_pairing" class="btn btn-primary">Create Pairing</button>
        </div></form>
    </div>
</div>

<!-- Add Project Modal -->
<div class="modal-overlay" id="addProjectModal">
    <div class="modal" style="max-width:700px;">
        <div class="modal-header"><h3><i class="fas fa-plus-circle" style="color:#34d399;"></i> Add New Project</h3><button class="modal-close" onclick="closeModal('addProjectModal')">&times;</button></div>
        <form method="POST"><div class="modal-body">
            <div class="form-row"><div class="form-group"><label>Project Title <span style="color:#f87171;">*</span></label><input type="text" name="project_title" class="form-control" required></div>
            <div class="form-group"><label>Type</label><select name="project_type" class="form-control"><option value="Research">Research</option><option value="Application" selected>Application</option><option value="Package">Package</option></select></div></div>
            <div class="form-row"><div class="form-group"><label>Status</label><select name="project_status" class="form-control"><option value="Available" selected>Available</option><option value="Unavailable">Unavailable</option></select></div>
            <div class="form-group"><label>Category</label><input type="text" name="project_category" class="form-control" placeholder="e.g. Web, Mobile, AI"></div></div>
            <div class="form-group"><label>Description</label><textarea name="project_description" class="form-control" rows="3"></textarea></div>
            <div class="form-row"><div class="form-group"><label>Supervisor</label><select name="supervisor_id" class="form-control"><option value="">-- Select --</option><?php if (isset($supervisors_list)): foreach ($supervisors_list as $sv): ?><option value="<?= $sv['fyp_supervisorid']; ?>"><?= htmlspecialchars($sv['fyp_name']); ?></option><?php endforeach; endif; ?></select></div>
            <div class="form-group"><label>Max Students</label><input type="number" name="max_students" class="form-control" value="2" min="1" max="10"></div></div>
        </div><div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('addProjectModal')">Cancel</button>
            <button type="submit" name="add_project" class="btn btn-success"><i class="fas fa-plus"></i> Add Project</button>
        </div></form>
    </div>
</div>

<!-- Edit Project Modal -->
<div class="modal-overlay" id="editProjectModal">
    <div class="modal" style="max-width:700px;">
        <div class="modal-header"><h3><i class="fas fa-edit" style="color:#60a5fa;"></i> Edit Project</h3><button class="modal-close" onclick="closeModal('editProjectModal')">&times;</button></div>
        <form method="POST"><input type="hidden" name="project_id" id="edit_project_id"><div class="modal-body">
            <div class="form-row"><div class="form-group"><label>Project Title</label><input type="text" name="project_title" id="edit_project_title" class="form-control" required></div>
            <div class="form-group"><label>Type</label><select name="project_type" id="edit_project_type" class="form-control"><option value="Research">Research</option><option value="Application">Application</option><option value="Package">Package</option></select></div></div>
            <div class="form-row"><div class="form-group"><label>Status</label><select name="project_status" id="edit_project_status" class="form-control"><option value="Available">Available</option><option value="Unavailable">Unavailable</option></select></div>
            <div class="form-group"><label>Category</label><input type="text" name="project_category" id="edit_project_category" class="form-control"></div></div>
            <div class="form-group"><label>Description</label><textarea name="project_description" id="edit_project_description" class="form-control" rows="3"></textarea></div>
            <div class="form-row"><div class="form-group"><label>Supervisor</label><select name="supervisor_id" id="edit_supervisor_id" class="form-control"><option value="">-- Select --</option><?php if (isset($supervisors_list)): foreach ($supervisors_list as $sv): ?><option value="<?= $sv['fyp_supervisorid']; ?>"><?= htmlspecialchars($sv['fyp_name']); ?></option><?php endforeach; endif; ?></select></div>
            <div class="form-group"><label>Max Students</label><input type="number" name="max_students" id="edit_max_students" class="form-control" min="1" max="10"></div></div>
        </div><div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('editProjectModal')">Cancel</button>
            <button type="submit" name="update_project" class="btn btn-primary"><i class="fas fa-save"></i> Update</button>
        </div></form>
    </div>
</div>

<!-- View Project Modal -->
<div class="modal-overlay" id="viewProjectModal">
    <div class="modal" style="max-width:600px;">
        <div class="modal-header"><h3><i class="fas fa-folder-open" style="color:#a78bfa;"></i> Project Details</h3><button class="modal-close" onclick="closeModal('viewProjectModal')">&times;</button></div>
        <div class="modal-body"><div id="viewProjectContent"></div></div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('viewProjectModal')">Close</button></div>
    </div>
</div>

<!-- Delete Project Modal -->
<div class="modal-overlay" id="deleteProjectModal">
    <div class="modal" style="max-width:450px;">
        <div class="modal-header"><h3><i class="fas fa-trash-alt" style="color:#f87171;"></i> Delete Project</h3><button class="modal-close" onclick="closeModal('deleteProjectModal')">&times;</button></div>
        <form method="POST"><input type="hidden" name="project_id" id="delete_project_id"><div class="modal-body" style="text-align:center;">
            <i class="fas fa-exclamation-triangle" style="font-size:3rem;color:#f87171;margin-bottom:15px;"></i>
            <p>Delete project: <strong id="delete_project_title"></strong>?</p>
        </div><div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('deleteProjectModal')">Cancel</button>
            <button type="submit" name="delete_project" class="btn btn-danger"><i class="fas fa-trash"></i> Delete</button>
        </div></form>
    </div>
</div>

<!-- Rubrics Modals -->
<div class="modal-overlay" id="createSetModal">
    <div class="modal" style="max-width:500px;">
        <div class="modal-header"><h3><i class="fas fa-layer-group" style="color:#a78bfa;"></i> <span id="setModalTitle">Create Assessment Set</span></h3><button class="modal-close" onclick="closeModal('createSetModal')">&times;</button></div>
        <form method="POST"><input type="hidden" name="set_id" id="edit_set_id" value=""><input type="hidden" name="project_phase" value="1"><div class="modal-body">
            <div class="form-group"><label>Academic Year <span style="color:#f87171;">*</span></label><select name="academic_id" id="set_academic_id" class="form-control" required><option value="">-- Select --</option><?php foreach ($academic_years as $ay): ?><option value="<?= $ay['fyp_academicid']; ?>"><?= htmlspecialchars($ay['fyp_acdyear'] . ' - ' . $ay['fyp_intake']); ?></option><?php endforeach; ?></select></div>
        </div><div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('createSetModal')">Cancel</button>
            <button type="submit" name="save_set" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
        </div></form>
    </div>
</div>

<div class="modal-overlay" id="createItemModal">
    <div class="modal" style="max-width:600px;">
        <div class="modal-header"><h3><i class="fas fa-list-ol" style="color:#fb923c;"></i> <span id="itemModalTitle">Add Assessment Item</span></h3><button class="modal-close" onclick="closeModal('createItemModal')">&times;</button></div>
        <form method="POST"><input type="hidden" name="item_id" id="edit_item_id" value=""><div class="modal-body">
            <div class="form-row"><div class="form-group"><label>Item Name <span style="color:#f87171;">*</span></label><input type="text" name="item_name" id="item_name" class="form-control" required></div>
            <div class="form-group"><label>Mark %</label><input type="number" name="item_mark" id="item_mark" class="form-control" min="0" max="100" step="0.001"></div></div>
            <div class="form-row"><div class="form-group"><label>Document Required?</label><div style="display:flex;gap:20px;padding:10px 0;"><label><input type="radio" name="item_doc" id="item_doc_yes" value="1" checked> Yes</label><label><input type="radio" name="item_doc" id="item_doc_no" value="0"> No</label></div></div>
            <div class="form-group"><label>Moderation?</label><div style="display:flex;gap:20px;padding:10px 0;"><label><input type="radio" name="item_moderate" id="item_mod_yes" value="1"> Yes</label><label><input type="radio" name="item_moderate" id="item_mod_no" value="0" checked> No</label></div></div></div>
            <div class="form-row"><div class="form-group"><label>Start Date</label><input type="datetime-local" name="item_start" id="item_start" class="form-control"></div>
            <div class="form-group"><label>Deadline</label><input type="datetime-local" name="item_deadline" id="item_deadline" class="form-control"></div></div>
        </div><div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('createItemModal')">Cancel</button>
            <button type="submit" name="save_item" class="btn btn-success"><i class="fas fa-save"></i> Save</button>
        </div></form>
    </div>
</div>

<div class="modal-overlay" id="createCriteriaModal">
    <div class="modal" style="max-width:500px;">
        <div class="modal-header"><h3><i class="fas fa-star" style="color:#fbbf24;"></i> <span id="criteriaModalTitle">Add Assessment Criteria</span></h3><button class="modal-close" onclick="closeModal('createCriteriaModal')">&times;</button></div>
        <form method="POST"><input type="hidden" name="criteria_id" id="edit_criteria_id" value=""><div class="modal-body">
            <div class="form-group"><label>Criteria Name <span style="color:#f87171;">*</span></label><input type="text" name="crit_name" id="crit_name" class="form-control" required></div>
            <div class="form-row"><div class="form-group"><label>Min Mark</label><input type="number" name="crit_min" id="crit_min" class="form-control" value="0" min="0" max="100"></div>
            <div class="form-group"><label>Max Mark</label><input type="number" name="crit_max" id="crit_max" class="form-control" value="100" min="0" max="100"></div></div>
            <div class="form-group"><label>Description</label><textarea name="crit_desc" id="crit_desc" class="form-control" rows="3"></textarea></div>
        </div><div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('createCriteriaModal')">Cancel</button>
            <button type="submit" name="save_criteria" class="btn btn-success"><i class="fas fa-save"></i> Save</button>
        </div></form>
    </div>
</div>

<div class="modal-overlay" id="createMarkingModal">
    <div class="modal" style="max-width:500px;">
        <div class="modal-header"><h3><i class="fas fa-percent" style="color:#60a5fa;"></i> <span id="markingModalTitle">Add Marking Criteria</span></h3><button class="modal-close" onclick="closeModal('createMarkingModal')">&times;</button></div>
        <form method="POST"><input type="hidden" name="marking_id" id="edit_marking_id" value=""><div class="modal-body">
            <div class="form-group"><label>Criteria Name <span style="color:#f87171;">*</span></label><input type="text" name="marking_name" id="marking_name" class="form-control" required></div>
            <div class="form-group"><label>Percentage Allocation</label><input type="number" name="marking_percent" id="marking_percent" class="form-control" min="0" max="100" step="0.001"></div>
        </div><div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('createMarkingModal')">Cancel</button>
            <button type="submit" name="save_marking" class="btn btn-success"><i class="fas fa-save"></i> Save</button>
        </div></form>
    </div>
</div>

<div class="modal-overlay" id="linkItemCriteriaModal">
    <div class="modal" style="max-width:500px;">
        <div class="modal-header"><h3><i class="fas fa-link" style="color:#fbbf24;"></i> Link Item to Criteria</h3><button class="modal-close" onclick="closeModal('linkItemCriteriaModal')">&times;</button></div>
        <form method="POST"><div class="modal-body">
            <div class="form-group"><label>Assessment Item <span style="color:#f87171;">*</span></label><select name="link_itemid" class="form-control" required><option value="">-- Select --</option><?php if (isset($items)): foreach ($items as $item): ?><option value="<?= $item['fyp_itemid']; ?>"><?= htmlspecialchars($item['fyp_itemname']); ?></option><?php endforeach; endif; ?></select></div>
            <div class="form-group"><label>Marking Criteria <span style="color:#f87171;">*</span></label><select name="link_criteriaid" class="form-control" required><option value="">-- Select --</option><?php if (isset($marking_criteria)): foreach ($marking_criteria as $mc): ?><option value="<?= $mc['fyp_criteriaid']; ?>"><?= htmlspecialchars($mc['fyp_criterianame']); ?> (<?= number_format($mc['fyp_percentallocation'], 1); ?>%)</option><?php endforeach; endif; ?></select></div>
        </div><div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('linkItemCriteriaModal')">Cancel</button>
            <button type="submit" name="link_item_criteria" class="btn btn-success"><i class="fas fa-link"></i> Link</button>
        </div></form>
    </div>
</div>

<!-- Announcement Modals -->
<div class="modal-overlay" id="createAnnouncementModal">
    <div class="modal">
        <div class="modal-header"><h3>Create Announcement</h3><button class="modal-close" onclick="closeModal('createAnnouncementModal')">&times;</button></div>
        <form method="POST"><div class="modal-body">
            <div class="form-group"><label>Subject</label><input type="text" name="subject" class="form-control" required></div>
            <div class="form-group"><label>Description</label><textarea name="description" class="form-control" rows="4" required></textarea></div>
            <div class="form-group"><label>Send To</label><select name="receiver" class="form-control" required><option value="All Students">All Students</option><option value="All Supervisors">All Supervisors</option><option value="Everyone">Everyone</option></select></div>
        </div><div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('createAnnouncementModal')">Cancel</button>
            <button type="submit" name="create_announcement" class="btn btn-primary">Post Announcement</button>
        </div></form>
    </div>
</div>

<div class="modal-overlay" id="unsendAnnouncementModal">
    <div class="modal" style="max-width:450px;">
        <div class="modal-header"><h3><i class="fas fa-trash-alt" style="color:#f87171;"></i> Unsend Announcement</h3><button class="modal-close" onclick="closeModal('unsendAnnouncementModal')">&times;</button></div>
        <form method="POST"><input type="hidden" name="announcement_id" id="unsendAnnId"><div class="modal-body" style="text-align:center;">
            <i class="fas fa-exclamation-triangle" style="font-size:3rem;color:#f87171;margin-bottom:15px;"></i>
            <p>Delete announcement: <strong id="unsendAnnSubject"></strong>?</p>
        </div><div class="modal-footer" style="justify-content:center;">
            <button type="button" class="btn btn-secondary" onclick="closeModal('unsendAnnouncementModal')">Cancel</button>
            <button type="submit" name="delete_announcement" class="btn btn-danger"><i class="fas fa-trash"></i> Unsend</button>
        </div></form>
    </div>
</div>

<!-- Add Mark Modal -->
<div class="modal-overlay" id="addMarkModal">
    <div class="modal" style="max-width:500px;">
        <div class="modal-header"><h3><i class="fas fa-edit" style="color:#34d399;"></i> Add/Edit Mark</h3><button class="modal-close" onclick="closeModal('addMarkModal')">&times;</button></div>
        <form method="POST"><div class="modal-body">
            <input type="hidden" name="mark_studid" id="mark_studid">
            <div class="form-group"><label>Student</label><input type="text" id="mark_student_display" class="form-control" readonly></div>
            <div class="form-row">
                <div class="form-group"><label>Supervisor Mark</label><input type="number" name="supervisor_mark" id="supervisor_mark" class="form-control" min="0" max="100" step="0.01" required></div>
                <div class="form-group"><label>Moderator Mark</label><input type="number" name="moderator_mark" id="moderator_mark" class="form-control" min="0" max="100" step="0.01" required></div>
            </div>
        </div><div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('addMarkModal')">Cancel</button>
            <button type="submit" name="add_mark" class="btn btn-success"><i class="fas fa-save"></i> Save Mark</button>
        </div></form>
    </div>
</div>

<!-- ==================== JAVASCRIPT ==================== -->
<script>
function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }

// Registration functions
function toggleSelectAll() {
    var selectAll = document.getElementById("selectAll");
    var checkboxes = document.querySelectorAll(".reg-checkbox");
    checkboxes.forEach(function(cb) { if (cb.closest("tr").style.display !== "none") { cb.checked = selectAll.checked; } });
    updateSelectedCount();
}

function updateSelectedCount() {
    var count = document.querySelectorAll(".reg-checkbox:checked").length;
    document.getElementById("selectedCount").textContent = count + " selected";
}

function approveSingle(regId) {
    if (confirm("Approve this registration?")) {
        document.getElementById("singleApproveId").value = regId;
        document.getElementById("singleApproveForm").submit();
    }
}

function confirmBulkAction(action) {
    var count = document.querySelectorAll(".reg-checkbox:checked").length;
    if (count === 0) { alert("Please select at least one registration."); return false; }
    return confirm("Are you sure you want to " + action + " " + count + " registration(s)?");
}

function openBulkRejectModal() {
    var count = document.querySelectorAll('.reg-checkbox:checked').length;
    if (count === 0) { alert('Please select at least one registration.'); return; }
    document.getElementById('bulkRejectCount').textContent = count;
    openModal('bulkRejectModal');
}

function showRejectRegModal(id, name) {
    document.getElementById('reject_reg_id').value = id;
    document.getElementById('reject_reg_name').textContent = name;
    openModal('rejectRegModal');
}

// Student functions
function openEditStudentModal(studid, studname, email, contactno, progid, group_type) {
    document.getElementById('edit_studid').value = studid;
    document.getElementById('edit_studname').value = studname;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_contactno').value = contactno;
    document.getElementById('edit_progid').value = progid;
    document.getElementById('edit_group_type').value = group_type || 'Individual';
    openModal('editStudentModal');
}

function confirmDeleteStudent(studid, studname) {
    document.getElementById('delete_studid').value = studid;
    document.getElementById('delete_studname').textContent = studname;
    openModal('deleteStudentModal');
}

// Project functions
function openEditProjectModal(project) {
    document.getElementById('edit_project_id').value = project.fyp_projectid;
    document.getElementById('edit_project_title').value = project.fyp_projecttitle || '';
    document.getElementById('edit_project_type').value = project.fyp_projecttype || 'Application';
    document.getElementById('edit_project_status').value = project.fyp_projectstatus || 'Available';
    document.getElementById('edit_project_category').value = project.fyp_projectcat || '';
    document.getElementById('edit_project_description').value = project.fyp_projectdesc || '';
    document.getElementById('edit_supervisor_id').value = project.fyp_supervisorid || '';
    document.getElementById('edit_max_students').value = project.fyp_maxstudent || 2;
    openModal('editProjectModal');
}

function viewProjectDetails(project) {
    var html = '<div style="padding:10px;"><h4 style="color:#a78bfa;margin-bottom:15px;">' + escapeHtml(project.fyp_projecttitle) + '</h4>' +
        '<div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:20px;">' +
        '<div style="background:rgba(139,92,246,0.1);padding:12px;border-radius:8px;"><small style="color:#64748b;">Type</small><p style="color:#e2e8f0;margin:5px 0 0;font-weight:600;">' + (project.fyp_projecttype || '-') + '</p></div>' +
        '<div style="background:rgba(139,92,246,0.1);padding:12px;border-radius:8px;"><small style="color:#64748b;">Status</small><p style="color:' + (project.fyp_projectstatus === 'Available' ? '#34d399' : '#fb923c') + ';margin:5px 0 0;font-weight:600;">' + (project.fyp_projectstatus || '-') + '</p></div></div>' +
        '<div style="margin-bottom:15px;"><small style="color:#64748b;">Description</small><p style="color:#e2e8f0;margin:5px 0 0;">' + (escapeHtml(project.fyp_projectdesc) || 'No description.') + '</p></div></div>';
    document.getElementById('viewProjectContent').innerHTML = html;
    openModal('viewProjectModal');
}

function confirmDeleteProject(projectId, projectTitle) {
    document.getElementById("delete_project_id").value = projectId;
    document.getElementById("delete_project_title").textContent = projectTitle;
    openModal("deleteProjectModal");
}

function filterProjectTable() {
    var search = (document.getElementById("projectSearch")?.value || "").toLowerCase();
    var statusFilter = document.getElementById("filterProjectStatus")?.value || "all";
    var typeFilter = document.getElementById("filterProjectType")?.value || "all";
    var rows = document.querySelectorAll("#projectTable tbody tr");
    
    rows.forEach(function(row) {
        var title = row.getAttribute("data-title") || "";
        var status = row.getAttribute("data-status") || "";
        var type = row.getAttribute("data-type") || "";
        var matchesSearch = title.includes(search);
        var matchesStatus = statusFilter === "all" || status === statusFilter;
        var matchesType = typeFilter === "all" || type === typeFilter;
        row.style.display = (matchesSearch && matchesStatus && matchesType) ? "" : "none";
    });
}

// Rubrics functions
function editSet(id, academicId) {
    document.getElementById('setModalTitle').textContent = 'Edit Assessment Set';
    document.getElementById('edit_set_id').value = id;
    document.getElementById('set_academic_id').value = academicId || '';
    openModal('createSetModal');
}

function deleteSet(id) { if (confirm('Delete this assessment set?')) { var form = document.createElement('form'); form.method = 'POST'; form.innerHTML = '<input type="hidden" name="delete_set" value="1"><input type="hidden" name="set_id" value="' + id + '">'; document.body.appendChild(form); form.submit(); } }

function editItem(item) {
    document.getElementById("itemModalTitle").textContent = "Edit Assessment Item";
    document.getElementById("edit_item_id").value = item.fyp_itemid;
    document.getElementById("item_name").value = item.fyp_itemname || "";
    document.getElementById("item_mark").value = item.fyp_originalmarkallocation || 0;
    openModal("createItemModal");
}

function deleteItem(id) { if (confirm('Delete this item?')) { var form = document.createElement('form'); form.method = 'POST'; form.innerHTML = '<input type="hidden" name="delete_item" value="1"><input type="hidden" name="item_id" value="' + id + '">'; document.body.appendChild(form); form.submit(); } }

function editCriteria(id, name, min, max, desc) {
    document.getElementById('criteriaModalTitle').textContent = 'Edit Assessment Criteria';
    document.getElementById('edit_criteria_id').value = id;
    document.getElementById('crit_name').value = name;
    document.getElementById('crit_min').value = min;
    document.getElementById('crit_max').value = max;
    document.getElementById('crit_desc').value = desc;
    openModal('createCriteriaModal');
}

function deleteCriteria(id) { if (confirm('Delete this criteria?')) { var form = document.createElement('form'); form.method = 'POST'; form.innerHTML = '<input type="hidden" name="delete_criteria" value="1"><input type="hidden" name="criteria_id" value="' + id + '">'; document.body.appendChild(form); form.submit(); } }

function editMarking(id, name, percent) {
    document.getElementById('markingModalTitle').textContent = 'Edit Marking Criteria';
    document.getElementById('edit_marking_id').value = id;
    document.getElementById('marking_name').value = name;
    document.getElementById('marking_percent').value = percent;
    openModal('createMarkingModal');
}

function deleteMarking(id) { if (confirm('Delete this marking criteria?')) { var form = document.createElement('form'); form.method = 'POST'; form.innerHTML = '<input type="hidden" name="delete_marking" value="1"><input type="hidden" name="marking_id" value="' + id + '">'; document.body.appendChild(form); form.submit(); } }

// Announcement functions
function confirmUnsend(id, subject) {
    document.getElementById('unsendAnnId').value = id;
    document.getElementById('unsendAnnSubject').textContent = '"' + subject + '"';
    openModal('unsendAnnouncementModal');
}

// Marks functions
function openAddMarkModal(studid, studname) {
    document.getElementById('mark_studid').value = studid;
    document.getElementById('mark_student_display').value = studname;
    document.getElementById('supervisor_mark').value = '';
    document.getElementById('moderator_mark').value = '';
    openModal('addMarkModal');
}

function liveSearchStudents(query) {
    query = query.trim().toLowerCase();
    var rows = document.querySelectorAll('.student-row');
    rows.forEach(function(row) {
        var id = row.getAttribute('data-id') || '';
        var name = row.getAttribute('data-name') || '';
        if (!query || id.indexOf(query) !== -1 || name.indexOf(query) !== -1) { row.style.display = ''; }
        else { row.style.display = 'none'; }
    });
}

// Table filter
function filterTable(inputId, tableId) {
    var filter = document.getElementById(inputId).value.toLowerCase();
    var rows = document.getElementById(tableId).getElementsByTagName('tr');
    for (var i = 1; i < rows.length; i++) {
        var found = false;
        var cells = rows[i].getElementsByTagName('td');
        for (var j = 0; j < cells.length; j++) { if (cells[j].textContent.toLowerCase().indexOf(filter) > -1) { found = true; break; } }
        rows[i].style.display = found ? '' : 'none';
    }
}

function escapeHtml(text) { if (!text) return ""; var div = document.createElement("div"); div.textContent = text; return div.innerHTML; }

// Close modal on click outside
document.querySelectorAll(".modal-overlay").forEach(function(m) { m.addEventListener("click", function(e) { if (e.target === this) { this.classList.remove("active"); } }); });
</script>

</body>
</html>