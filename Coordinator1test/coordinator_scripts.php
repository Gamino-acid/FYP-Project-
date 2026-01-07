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
                <div class="form-group"><label>Programme</label><select name="progid" id="edit_progid" class="form-control"><?php if(isset($programmes)): foreach ($programmes as $p): ?><option value="<?= $p['fyp_progid']; ?>"><?= htmlspecialchars($p['fyp_progname']); ?></option><?php endforeach; endif; ?></select></div>
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
                <div class="form-group"><label>Academic Year</label><select name="academic_id" class="form-control" required><?php if(isset($academic_years)): foreach ($academic_years as $ay): ?><option value="<?= $ay['fyp_academicid']; ?>"><?= $ay['fyp_acdyear'] . ' ' . $ay['fyp_intake']; ?></option><?php endforeach; endif; ?></select></div>
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
            <div class="form-row"><div class="form-group" style="flex:2;"><label>Project Title <span style="color:#f87171;">*</span></label><input type="text" name="project_title" class="form-control" placeholder="Enter project title" required></div>
            <div class="form-group" style="flex:1;"><label>Type</label><select name="project_type" class="form-control"><option value="Application" selected>Application</option><option value="Research">Research</option><option value="Package">Package</option></select></div></div>
            <div class="form-row"><div class="form-group"><label>Category</label><input type="text" name="project_category" class="form-control" placeholder="e.g. Web, Mobile, AI, Networking"></div>
            <div class="form-group"><label>Max Students</label><input type="number" name="max_students" class="form-control" value="2" min="1" max="10"></div></div>
            <div class="form-group"><label>Description</label><textarea name="project_description" class="form-control" rows="3" placeholder="Brief description of the project..."></textarea></div>
            <div class="form-group"><label>Supervisor</label><select name="supervisor_id" class="form-control"><option value="">-- Select Supervisor --</option><?php if (isset($supervisors_list)): foreach ($supervisors_list as $sv): ?><option value="<?= $sv['fyp_supervisorid']; ?>"><?= htmlspecialchars($sv['fyp_name']); ?></option><?php endforeach; endif; ?></select></div>
            <div style="background:rgba(16,185,129,0.1);padding:15px;border-radius:12px;border:1px solid rgba(16,185,129,0.3);">
                <p style="color:#34d399;margin:0;"><i class="fas fa-info-circle"></i> New projects are automatically set to <strong>Available</strong> status.</p>
            </div>
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
            <div class="form-group"><label>Academic Year <span style="color:#f87171;">*</span></label><select name="academic_id" id="set_academic_id" class="form-control" required><option value="">-- Select --</option><?php if(isset($academic_years)): foreach ($academic_years as $ay): ?><option value="<?= $ay['fyp_academicid']; ?>"><?= htmlspecialchars($ay['fyp_acdyear'] . ' - ' . $ay['fyp_intake']); ?></option><?php endforeach; endif; ?></select></div>
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

<!-- View Supervisor History Modal -->
<div class="modal-overlay" id="viewSupervisorHistoryModal">
    <div class="modal" style="max-width:700px;">
        <div class="modal-header">
            <h3><i class="fas fa-history" style="color:#a78bfa;"></i> <span id="supervisorHistoryTitle">Supervisor History</span></h3>
            <button class="modal-close" onclick="closeModal('viewSupervisorHistoryModal')">&times;</button>
        </div>
        <div class="modal-body" style="max-height:70vh;overflow-y:auto;">
            <div id="supervisorHistoryContent"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('viewSupervisorHistoryModal')">Close</button>
        </div>
    </div>
</div>

<!-- Add Project Modal -->
<div class="modal-overlay" id="addProjectModal">
    <div class="modal" style="max-width:700px;">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle" style="color:#34d399;"></i> Add New Project</h3>
            <button class="modal-close" onclick="closeModal('addProjectModal')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group" style="flex:2;">
                        <label>Project Title <span style="color:#f87171;">*</span></label>
                        <input type="text" name="project_title" class="form-control" placeholder="Enter project title" required>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Type</label>
                        <select name="project_type" class="form-control">
                            <option value="Application" selected>Application</option>
                            <option value="Research">Research</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Category</label>
                        <input type="text" name="project_category" class="form-control" placeholder="e.g. Web, Mobile, AI">
                    </div>
                    <div class="form-group">
                        <label>Max Students</label>
                        <input type="number" name="max_students" class="form-control" value="2" min="1" max="10">
                    </div>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="project_description" class="form-control" rows="3" placeholder="Brief description..."></textarea>
                </div>
                <div class="form-group">
                    <label>Supervisor</label>
                    <select name="supervisor_id" class="form-control">
                        <option value="">-- Select Supervisor --</option>
                        <?php 
                        $sup_res = $conn->query("SELECT fyp_supervisorid, fyp_name, fyp_email FROM supervisor ORDER BY fyp_name");
                        if ($sup_res) { while ($sv = $sup_res->fetch_assoc()): ?>
                            <option value="<?= $sv['fyp_supervisorid']; ?>"><?= htmlspecialchars($sv['fyp_name']); ?></option>
                        <?php endwhile; } ?>
                    </select>
                </div>
                <div style="background:rgba(16,185,129,0.1);padding:15px;border-radius:12px;border:1px solid rgba(16,185,129,0.3);">
                    <p style="color:#34d399;margin:0;"><i class="fas fa-info-circle"></i> New projects are automatically set to <strong>Available</strong> status.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addProjectModal')">Cancel</button>
                <button type="submit" name="add_project" class="btn btn-success"><i class="fas fa-plus"></i> Add Project</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Project Modal -->
<div class="modal-overlay" id="editProjectModal">
    <div class="modal" style="max-width:700px;">
        <div class="modal-header">
            <h3><i class="fas fa-edit" style="color:#60a5fa;"></i> Edit Project</h3>
            <button class="modal-close" onclick="closeModal('editProjectModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="project_id" id="edit_project_id">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group" style="flex:2;">
                        <label>Project Title <span style="color:#f87171;">*</span></label>
                        <input type="text" name="project_title" id="edit_project_title" class="form-control" required>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Type</label>
                        <select name="project_type" id="edit_project_type" class="form-control">
                            <option value="Application">Application</option>
                            <option value="Research">Research</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="project_status" id="edit_project_status" class="form-control">
                            <option value="Available">Available</option>
                            <option value="Unavailable">Unavailable</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <input type="text" name="project_category" id="edit_project_category" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="project_description" id="edit_project_description" class="form-control" rows="3"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Supervisor</label>
                        <select name="supervisor_id" id="edit_supervisor_id" class="form-control">
                            <option value="">-- Select --</option>
                            <?php 
                            $sup_res2 = $conn->query("SELECT fyp_supervisorid, fyp_name FROM supervisor ORDER BY fyp_name");
                            if ($sup_res2) { while ($sv = $sup_res2->fetch_assoc()): ?>
                                <option value="<?= $sv['fyp_supervisorid']; ?>"><?= htmlspecialchars($sv['fyp_name']); ?></option>
                            <?php endwhile; } ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Max Students</label>
                        <input type="number" name="max_students" id="edit_max_students" class="form-control" min="1" max="10">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editProjectModal')">Cancel</button>
                <button type="submit" name="update_project" class="btn btn-primary"><i class="fas fa-save"></i> Update Project</button>
            </div>
        </form>
    </div>
</div>

<!-- View Project Modal -->
<div class="modal-overlay" id="viewProjectModal">
    <div class="modal" style="max-width:600px;">
        <div class="modal-header">
            <h3><i class="fas fa-folder-open" style="color:#a78bfa;"></i> Project Details</h3>
            <button class="modal-close" onclick="closeModal('viewProjectModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div id="viewProjectContent"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('viewProjectModal')">Close</button>
        </div>
    </div>
</div>

<!-- Delete Project Modal -->
<div class="modal-overlay" id="deleteProjectModal">
    <div class="modal" style="max-width:450px;">
        <div class="modal-header">
            <h3><i class="fas fa-trash-alt" style="color:#f87171;"></i> Delete Project</h3>
            <button class="modal-close" onclick="closeModal('deleteProjectModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="project_id" id="delete_project_id">
            <div class="modal-body" style="text-align:center;">
                <i class="fas fa-exclamation-triangle" style="font-size:3rem;color:#f87171;margin-bottom:15px;"></i>
                <p>Are you sure you want to delete this project?</p>
                <p><strong id="delete_project_title"></strong></p>
                <p style="color:#f87171;font-size:0.85rem;">This action cannot be undone!</p>
            </div>
            <div class="modal-footer" style="justify-content:center;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('deleteProjectModal')">Cancel</button>
                <button type="submit" name="delete_project" class="btn btn-danger"><i class="fas fa-trash"></i> Delete</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== JAVASCRIPT ==================== -->
<script>
// Store original student data for sorting/filtering
var originalStudentRows = [];
var studentTableInitialized = false;

function initStudentTable() {
    if (studentTableInitialized) return;
    var table = document.getElementById('studentTable');
    if (!table) return;
    
    var tbody = table.querySelector('tbody');
    if (!tbody) return;
    
    var rows = tbody.querySelectorAll('tr');
    rows.forEach(function(row, index) {
        originalStudentRows.push({
            element: row.cloneNode(true),
            id: row.querySelector('td:first-child')?.textContent?.trim() || '',
            name: row.querySelector('td:nth-child(2)')?.textContent?.trim() || '',
            programme: row.querySelector('td:nth-child(3)')?.textContent?.trim() || '',
            groupType: row.querySelector('td:nth-child(5)')?.textContent?.trim() || '',
            index: index
        });
    });
    studentTableInitialized = true;
}

function filterAndSortStudents() {
    var table = document.getElementById('studentTable');
    if (!table) return;
    
    initStudentTable();
    
    var searchValue = (document.getElementById('studentSearch')?.value || '').toLowerCase();
    var sortValue = document.getElementById('studentSort')?.value || 'name_asc';
    var programmeValue = document.getElementById('studentProgramme')?.value || 'all';
    var groupTypeValue = document.getElementById('studentGroupType')?.value || 'all';
    
    // Filter
    var filteredRows = originalStudentRows.filter(function(row) {
        var matchesSearch = !searchValue || 
            row.id.toLowerCase().includes(searchValue) || 
            row.name.toLowerCase().includes(searchValue);
        var matchesProgramme = programmeValue === 'all' || 
            row.programme.toLowerCase() === programmeValue.toLowerCase() ||
            row.programme.toLowerCase().includes(programmeValue.toLowerCase());
        var matchesGroupType = groupTypeValue === 'all' || 
            row.groupType.toLowerCase().includes(groupTypeValue.toLowerCase());
        
        return matchesSearch && matchesProgramme && matchesGroupType;
    });
    
    // Sort
    filteredRows.sort(function(a, b) {
        switch(sortValue) {
            case 'name_asc':
                return a.name.localeCompare(b.name);
            case 'name_desc':
                return b.name.localeCompare(a.name);
            case 'id_asc':
                return a.id.localeCompare(b.id);
            case 'id_desc':
                return b.id.localeCompare(a.id);
            case 'oldest':
                return a.index - b.index;
            case 'newest':
                return b.index - a.index;
            default:
                return 0;
        }
    });
    
    // Rebuild table
    var tbody = table.querySelector('tbody');
    tbody.innerHTML = '';
    
    if (filteredRows.length === 0) {
        var emptyRow = document.createElement('tr');
        emptyRow.innerHTML = '<td colspan="8" style="text-align:center;padding:40px;color:#64748b;"><i class="fas fa-search" style="font-size:2rem;margin-bottom:10px;display:block;opacity:0.3;"></i>No students found matching your criteria</td>';
        tbody.appendChild(emptyRow);
    } else {
        filteredRows.forEach(function(row) {
            tbody.appendChild(row.element.cloneNode(true));
        });
    }
    
    // Update count
    var countEl = document.getElementById('studentCount');
    if (countEl) {
        countEl.textContent = filteredRows.length + ' of ' + originalStudentRows.length + ' students';
    }
}

function resetStudentFilters() {
    document.getElementById('studentSearch').value = '';
    document.getElementById('studentSort').value = 'name_asc';
    document.getElementById('studentProgramme').value = 'all';
    document.getElementById('studentGroupType').value = 'all';
    filterAndSortStudents();
}

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

// Table filter (legacy)
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

// ==================== PROJECT FUNCTIONS ====================
function toggleProjectStatus(projectId, isChecked) {
    var newStatus = isChecked ? 'Available' : 'Unavailable';
    document.getElementById('newStatus-' + projectId).value = newStatus;
    document.getElementById('toggleForm-' + projectId).submit();
}

function quickFilterProject(status) {
    var statusFilter = document.getElementById('filterProjectStatus');
    if (statusFilter) {
        statusFilter.value = status === 'all' ? 'all' : status;
        filterProjects();
    }
}

function filterProjects() {
    var searchValue = (document.getElementById('projectSearch')?.value || '').toLowerCase();
    var statusValue = document.getElementById('filterProjectStatus')?.value || 'all';
    var typeValue = document.getElementById('filterProjectType')?.value || 'all';
    var categoryValue = (document.getElementById('filterProjectCategory')?.value || 'all').toLowerCase();
    var supervisorValue = (document.getElementById('filterProjectSupervisor')?.value || 'all').toLowerCase();
    
    var rows = document.querySelectorAll('#projectTable tbody tr');
    var visibleCount = 0;
    
    rows.forEach(function(row) {
        var title = row.getAttribute('data-title') || '';
        var status = row.getAttribute('data-status') || '';
        var type = row.getAttribute('data-type') || '';
        var category = row.getAttribute('data-category') || '';
        var supervisor = row.getAttribute('data-supervisor') || '';
        
        var matchesSearch = !searchValue || title.includes(searchValue) || supervisor.includes(searchValue);
        var matchesStatus = statusValue === 'all' || status === statusValue;
        var matchesType = typeValue === 'all' || type === typeValue;
        var matchesCategory = categoryValue === 'all' || category === categoryValue;
        var matchesSupervisor = supervisorValue === 'all' || supervisor === supervisorValue;
        
        if (matchesSearch && matchesStatus && matchesType && matchesCategory && matchesSupervisor) {
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
    var search = document.getElementById('projectSearch');
    var status = document.getElementById('filterProjectStatus');
    var type = document.getElementById('filterProjectType');
    var category = document.getElementById('filterProjectCategory');
    var supervisor = document.getElementById('filterProjectSupervisor');
    
    if (search) search.value = '';
    if (status) status.value = 'all';
    if (type) type.value = 'all';
    if (category) category.value = 'all';
    if (supervisor) supervisor.value = 'all';
    filterProjects();
}

// Close modal on click outside
document.querySelectorAll(".modal-overlay").forEach(function(m) { m.addEventListener("click", function(e) { if (e.target === this) { this.classList.remove("active"); } }); });

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initStudentTable();
    initSupervisorTable();
});

// ==================== SUPERVISOR FILTERING ====================
var originalSupervisorRows = [];
var supervisorTableInitialized = false;

function initSupervisorTable() {
    if (supervisorTableInitialized) return;
    var table = document.getElementById('supervisorTable');
    if (!table) return;
    
    var tbody = table.querySelector('tbody');
    if (!tbody) return;
    
    var rows = tbody.querySelectorAll('tr');
    rows.forEach(function(row, index) {
        originalSupervisorRows.push({
            element: row.cloneNode(true),
            id: row.getAttribute('data-id') || '',
            name: row.getAttribute('data-name') || '',
            email: row.getAttribute('data-email') || '',
            programme: row.getAttribute('data-programme') || '',
            specialization: row.getAttribute('data-specialization') || '',
            moderator: row.getAttribute('data-moderator') || '',
            index: index
        });
    });
    supervisorTableInitialized = true;
}

function filterAndSortSupervisors() {
    var table = document.getElementById('supervisorTable');
    if (!table) return;
    
    initSupervisorTable();
    
    var searchValue = (document.getElementById('supervisorSearch')?.value || '').toLowerCase();
    var sortValue = document.getElementById('supervisorSort')?.value || 'name_asc';
    var programmeValue = (document.getElementById('supervisorProgramme')?.value || 'all').toLowerCase();
    var specializationValue = (document.getElementById('supervisorSpecialization')?.value || 'all').toLowerCase();
    var moderatorValue = document.getElementById('supervisorModerator')?.value || 'all';
    
    // Filter
    var filteredRows = originalSupervisorRows.filter(function(row) {
        var matchesSearch = !searchValue || 
            row.name.includes(searchValue) || 
            row.email.includes(searchValue);
        var matchesProgramme = programmeValue === 'all' || row.programme === programmeValue;
        var matchesSpecialization = specializationValue === 'all' || row.specialization === specializationValue;
        var matchesModerator = moderatorValue === 'all' || row.moderator === moderatorValue;
        
        return matchesSearch && matchesProgramme && matchesSpecialization && matchesModerator;
    });
    
    // Sort
    filteredRows.sort(function(a, b) {
        switch(sortValue) {
            case 'name_asc':
                return a.name.localeCompare(b.name);
            case 'name_desc':
                return b.name.localeCompare(a.name);
            case 'id_asc':
                return parseInt(a.id) - parseInt(b.id);
            case 'id_desc':
                return parseInt(b.id) - parseInt(a.id);
            default:
                return 0;
        }
    });
    
    // Rebuild table
    var tbody = table.querySelector('tbody');
    tbody.innerHTML = '';
    
    if (filteredRows.length === 0) {
        var emptyRow = document.createElement('tr');
        emptyRow.innerHTML = '<td colspan="9" style="text-align:center;padding:40px;color:#64748b;"><i class="fas fa-search" style="font-size:2rem;margin-bottom:10px;display:block;opacity:0.3;"></i>No supervisors found matching your criteria</td>';
        tbody.appendChild(emptyRow);
    } else {
        filteredRows.forEach(function(row) {
            tbody.appendChild(row.element.cloneNode(true));
        });
    }
    
    // Update count
    var countEl = document.getElementById('supervisorCount');
    if (countEl) {
        countEl.textContent = filteredRows.length;
    }
}

function resetSupervisorFilters() {
    document.getElementById('supervisorSearch').value = '';
    document.getElementById('supervisorSort').value = 'name_asc';
    document.getElementById('supervisorProgramme').value = 'all';
    document.getElementById('supervisorSpecialization').value = 'all';
    document.getElementById('supervisorModerator').value = 'all';
    filterAndSortSupervisors();
}

var currentSupervisorId = null;
var currentSupervisorName = '';

// ==================== SUPERVISOR HISTORY ====================
function viewSupervisorHistory(supervisorId, supervisorName) {
    document.getElementById('supervisorHistoryTitle').textContent = supervisorName + ' - Supervision History';
    
    var history = supervisorHistoryData[supervisorId] || [];
    var content = '';
    
    if (history.length === 0) {
        content = '<div class="empty-state" style="padding:40px;"><i class="fas fa-history" style="font-size:3rem;color:#4a4a6a;margin-bottom:15px;"></i><p style="color:#64748b;">No supervision history found for this supervisor.</p></div>';
    } else {
        // Calculate totals
        var totalStudents = 0;
        history.forEach(function(h) { totalStudents += parseInt(h.student_count); });
        
        content = '<div style="display:flex;flex-direction:column;gap:15px;">';
        
        // Summary Cards
        content += '<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(150px, 1fr));gap:15px;margin-bottom:10px;">';
        content += '<div style="background:rgba(16,185,129,0.1);padding:20px;border-radius:12px;text-align:center;">';
        content += '<div style="font-size:2rem;font-weight:700;color:#34d399;">' + totalStudents + '</div>';
        content += '<div style="color:#94a3b8;font-size:0.85rem;">Total Students</div></div>';
        content += '<div style="background:rgba(139,92,246,0.1);padding:20px;border-radius:12px;text-align:center;">';
        content += '<div style="font-size:2rem;font-weight:700;color:#a78bfa;">' + history.length + '</div>';
        content += '<div style="color:#94a3b8;font-size:0.85rem;">Semesters Active</div></div>';
        content += '</div>';
        
        // Timeline
        content += '<div style="background:rgba(139,92,246,0.1);border-radius:12px;padding:20px;">';
        content += '<h4 style="color:#a78bfa;margin:0 0 15px 0;"><i class="fas fa-calendar-check" style="margin-right:10px;"></i>Semester History</h4>';
        content += '<div style="display:flex;flex-direction:column;gap:10px;">';
        
        history.forEach(function(h, index) {
            var semester = (h.fyp_acdyear || 'Unknown') + ' ' + (h.fyp_intake || '');
            var isLatest = index === 0;
            
            content += '<div style="display:flex;align-items:center;gap:15px;padding:12px;background:rgba(0,0,0,0.2);border-radius:8px;border-left:3px solid ' + (isLatest ? '#34d399' : '#a78bfa') + ';">';
            content += '<div style="flex:1;">';
            content += '<div style="color:#e2e8f0;font-weight:600;">' + escapeHtml(semester);
            if (isLatest) {
                content += ' <span style="background:#34d399;color:#000;padding:2px 8px;border-radius:10px;font-size:0.7rem;margin-left:8px;">Current</span>';
            }
            content += '</div>';
            content += '</div>';
            content += '<div style="background:rgba(139,92,246,0.3);padding:8px 15px;border-radius:20px;">';
            content += '<span style="color:#a78bfa;font-weight:600;">' + h.student_count + '</span>';
            content += '<span style="color:#94a3b8;font-size:0.85rem;margin-left:5px;">student(s)</span>';
            content += '</div></div>';
        });
        
        content += '</div></div></div>';
    }
    
    document.getElementById('supervisorHistoryContent').innerHTML = content;
    openModal('viewSupervisorHistoryModal');
}

// ==================== PAIRING PAGE FUNCTIONS ====================
function switchPairingView(view) {
    var tableView = document.getElementById('pairingTableView');
    var supervisorView = document.getElementById('pairingSupervisorView');
    var btnTable = document.getElementById('btnTableView');
    var btnSupervisor = document.getElementById('btnSupervisorView');
    
    if (view === 'table') {
        tableView.style.display = 'block';
        supervisorView.style.display = 'none';
        btnTable.className = 'btn btn-primary btn-sm';
        btnSupervisor.className = 'btn btn-secondary btn-sm';
    } else {
        tableView.style.display = 'none';
        supervisorView.style.display = 'block';
        btnTable.className = 'btn btn-secondary btn-sm';
        btnSupervisor.className = 'btn btn-primary btn-sm';
    }
}

function toggleSupervisorStudents(supId) {
    var content = document.getElementById('students-' + supId);
    var chevron = document.getElementById('chevron-' + supId);
    
    if (content.style.display === 'none') {
        content.style.display = 'block';
        chevron.style.transform = 'rotate(180deg)';
    } else {
        content.style.display = 'none';
        chevron.style.transform = 'rotate(0deg)';
    }
}

function filterSupervisorCardStudents(supId, semesterId) {
    var table = document.getElementById('sup-table-' + supId);
    if (!table) return;
    
    var rows = table.querySelectorAll('tbody tr');
    rows.forEach(function(row) {
        var rowSemester = row.getAttribute('data-semester');
        if (semesterId === 'all' || rowSemester === semesterId) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function filterPairings() {
    var searchValue = (document.getElementById('pairingSearch')?.value || '').toLowerCase();
    var semesterValue = document.getElementById('pairingSemester')?.value || 'all';
    var supervisorValue = document.getElementById('pairingSupervisor')?.value || 'all';
    var typeValue = document.getElementById('pairingType')?.value || 'all';
    
    // Filter table view
    var rows = document.querySelectorAll('#pairingTable tbody tr');
    var visibleCount = 0;
    
    rows.forEach(function(row) {
        var search = row.getAttribute('data-search') || '';
        var semester = row.getAttribute('data-semester') || '';
        var supervisor = row.getAttribute('data-supervisor') || '';
        var type = row.getAttribute('data-type') || '';
        
        var matchesSearch = !searchValue || search.includes(searchValue);
        var matchesSemester = semesterValue === 'all' || semester === semesterValue;
        var matchesSupervisor = supervisorValue === 'all' || supervisor === supervisorValue;
        var matchesType = typeValue === 'all' || type === typeValue;
        
        if (matchesSearch && matchesSemester && matchesSupervisor && matchesType) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    // Filter supervisor view cards
    var supervisorCards = document.querySelectorAll('.supervisor-card');
    supervisorCards.forEach(function(card) {
        var cardSupervisor = card.getAttribute('data-supervisor');
        if (supervisorValue === 'all' || cardSupervisor === supervisorValue) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
    
    // Update count
    var countEl = document.getElementById('pairingCount');
    if (countEl) {
        countEl.textContent = visibleCount;
    }
}

function resetPairingFilters() {
    document.getElementById('pairingSearch').value = '';
    document.getElementById('pairingSemester').value = 'all';
    document.getElementById('pairingSupervisor').value = 'all';
    document.getElementById('pairingType').value = 'all';
    filterPairings();
}

// ==================== PROJECT PAGE FUNCTIONS ====================
function toggleProjectStatus(projectId, isChecked) {
    var newStatus = isChecked ? 'Available' : 'Unavailable';
    document.getElementById('newStatus-' + projectId).value = newStatus;
    document.getElementById('toggleForm-' + projectId).submit();
}

function quickFilterProject(status) {
    document.getElementById('filterProjectStatus').value = status === 'all' ? 'all' : status;
    filterProjects();
}

function filterProjects() {
    var searchValue = (document.getElementById('projectSearch')?.value || '').toLowerCase();
    var statusValue = document.getElementById('filterProjectStatus')?.value || 'all';
    var typeValue = document.getElementById('filterProjectType')?.value || 'all';
    var categoryValue = (document.getElementById('filterProjectCategory')?.value || 'all').toLowerCase();
    var supervisorValue = (document.getElementById('filterProjectSupervisor')?.value || 'all').toLowerCase();
    
    var rows = document.querySelectorAll('#projectTable tbody tr');
    var visibleCount = 0;
    
    rows.forEach(function(row) {
        var title = row.getAttribute('data-title') || '';
        var status = row.getAttribute('data-status') || '';
        var type = row.getAttribute('data-type') || '';
        var category = row.getAttribute('data-category') || '';
        var supervisor = row.getAttribute('data-supervisor') || '';
        
        var matchesSearch = !searchValue || title.includes(searchValue) || supervisor.includes(searchValue);
        var matchesStatus = statusValue === 'all' || status === statusValue;
        var matchesType = typeValue === 'all' || type === typeValue;
        var matchesCategory = categoryValue === 'all' || category === categoryValue;
        var matchesSupervisor = supervisorValue === 'all' || supervisor === supervisorValue;
        
        if (matchesSearch && matchesStatus && matchesType && matchesCategory && matchesSupervisor) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    document.getElementById('projectCount').textContent = visibleCount;
}

function resetProjectFilters() {
    document.getElementById('projectSearch').value = '';
    document.getElementById('filterProjectStatus').value = 'all';
    document.getElementById('filterProjectType').value = 'all';
    document.getElementById('filterProjectCategory').value = 'all';
    document.getElementById('filterProjectSupervisor').value = 'all';
    filterProjects();
}

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
    var statusColor = project.fyp_projectstatus === 'Available' ? '#34d399' : '#f87171';
    var html = '<div style="padding:10px;">' +
        '<h4 style="color:#a78bfa;margin-bottom:20px;">' + escapeHtml(project.fyp_projecttitle || '') + '</h4>' +
        '<div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:20px;">' +
        '<div style="background:rgba(139,92,246,0.1);padding:15px;border-radius:12px;"><small style="color:#64748b;">Type</small><p style="color:#e2e8f0;margin:5px 0 0;font-weight:600;">' + (project.fyp_projecttype || '-') + '</p></div>' +
        '<div style="background:rgba(139,92,246,0.1);padding:15px;border-radius:12px;"><small style="color:#64748b;">Status</small><p style="color:' + statusColor + ';margin:5px 0 0;font-weight:600;">' + (project.fyp_projectstatus || '-') + '</p></div>' +
        '<div style="background:rgba(139,92,246,0.1);padding:15px;border-radius:12px;"><small style="color:#64748b;">Category</small><p style="color:#e2e8f0;margin:5px 0 0;font-weight:600;">' + (project.fyp_projectcat || '-') + '</p></div>' +
        '<div style="background:rgba(139,92,246,0.1);padding:15px;border-radius:12px;"><small style="color:#64748b;">Supervisor</small><p style="color:#e2e8f0;margin:5px 0 0;font-weight:600;">' + (project.supervisor_name || '-') + '</p></div>' +
        '</div>' +
        '<div style="background:rgba(139,92,246,0.1);padding:15px;border-radius:12px;"><small style="color:#64748b;">Description</small><p style="color:#e2e8f0;margin:5px 0 0;">' + escapeHtml(project.fyp_projectdesc || 'No description.') + '</p></div></div>';
    document.getElementById('viewProjectContent').innerHTML = html;
    openModal('viewProjectModal');
}

function confirmDeleteProject(projectId, projectTitle) {
    document.getElementById('delete_project_id').value = projectId;
    document.getElementById('delete_project_title').textContent = projectTitle;
    openModal('deleteProjectModal');
}

function togglePairingView() {
    var view = document.getElementById('pairingView').value;
    if (view === 'table') {
        document.getElementById('pairingTableView').style.display = 'block';
        document.getElementById('pairingCardView').style.display = 'none';
    } else {
        document.getElementById('pairingTableView').style.display = 'none';
        document.getElementById('pairingCardView').style.display = 'block';
    }
}
</script>