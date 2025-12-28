<?php
/**
 * COORDINATOR MANAGE STUDENTS
 * Coordinator_students.php
 */
include("includes/header.php");

// Handle POST - Edit Student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_student'])) {
    $studid = $_POST['studid'];
    $studname = $_POST['studname'];
    $email = $_POST['email'];
    $contact = $_POST['contactno'];
    $progid = $_POST['progid'];
    $group_type = $_POST['group_type'];
    
    $stmt = $conn->prepare("UPDATE student SET fyp_studname = ?, fyp_email = ?, fyp_contactno = ?, fyp_progid = ?, fyp_group = ? WHERE fyp_studid = ?");
    $stmt->bind_param("sssiss", $studname, $email, $contact, $progid, $group_type, $studid);
    if ($stmt->execute()) {
        $message = "Student information updated successfully!";
        $message_type = 'success';
    }
    $stmt->close();
}

// Handle POST - Delete Student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_student'])) {
    $studid = $_POST['studid'];
    $stmt = $conn->prepare("DELETE FROM student WHERE fyp_studid = ?");
    $stmt->bind_param("s", $studid);
    if ($stmt->execute()) {
        $message = "Student deleted successfully!";
        $message_type = 'success';
    }
    $stmt->close();
}

$students = [];
$res = $conn->query("SELECT s.*, p.fyp_progname, p.fyp_progid, a.fyp_acdyear, a.fyp_intake, u.fyp_username,
                            pr.fyp_projecttitle, pr.fyp_projectid,
                            pa.fyp_pairingid, pa_pr.fyp_projecttitle as paired_project
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

<?php if ($message): ?>
<div class="alert alert-<?= $message_type; ?>">
    <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i> 
    <?= $message; ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h3>All Students (<?= count($students); ?>)</h3></div>
    <div class="card-body">
        <div class="search-box"><input type="text" class="form-control" placeholder="Search students..." id="studentSearch" onkeyup="filterTable('studentSearch','studentTable')"></div>
        <?php if (empty($students)): ?>
            <div class="empty-state"><i class="fas fa-user-graduate"></i><p>No students found</p></div>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="data-table" id="studentTable">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Programme</th>
                            <th>Project</th>
                            <th>Group Type</th>
                            <th>Email</th>
                            <th>Contact</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
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
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Student Modal -->
<div class="modal-overlay" id="editStudentModal">
    <div class="modal">
        <div class="modal-header"><h3><i class="fas fa-user-edit"></i> Edit Student Information</h3><button class="modal-close" onclick="closeModal('editStudentModal')">&times;</button></div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="studid" id="edit_studid">
                <div class="form-group">
                    <label>Student Name</label>
                    <input type="text" name="studname" id="edit_studname" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="edit_email" class="form-control">
                </div>
                <div class="form-group">
                    <label>Contact Number</label>
                    <input type="text" name="contactno" id="edit_contactno" class="form-control">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Programme</label>
                        <select name="progid" id="edit_progid" class="form-control">
                            <?php foreach ($programmes as $p): ?>
                            <option value="<?= $p['fyp_progid']; ?>"><?= htmlspecialchars($p['fyp_progname'] . ' - ' . $p['fyp_prognamefull']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Group Type</label>
                        <select name="group_type" id="edit_group_type" class="form-control">
                            <option value="Individual">Individual</option>
                            <option value="Group">Group</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editStudentModal')">Cancel</button>
                <button type="submit" name="edit_student" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
            </div>
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
                <p style="text-align:center;margin-bottom:15px;">Are you sure you want to delete student:</p>
                <p style="text-align:center;font-size:1.1rem;color:#fff;"><strong id="delete_studname"></strong></p>
                <p style="text-align:center;color:#f87171;font-size:0.85rem;margin-top:15px;"><i class="fas fa-warning"></i> This action cannot be undone!</p>
            </div>
            <div class="modal-footer" style="justify-content:center;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('deleteStudentModal')">Cancel</button>
                <button type="submit" name="delete_student" class="btn btn-danger"><i class="fas fa-trash"></i> Delete Student</button>
            </div>
        </form>
    </div>
</div>

<script>
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
</script>

<?php include("includes/footer.php"); ?>