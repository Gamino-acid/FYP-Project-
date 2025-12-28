<?php
/**
 * COORDINATOR ASSESSMENT MARKS
 * Coordinator_marks.php
 */
include("includes/header.php");

$marks_action = $_GET['action'] ?? 'list';
$view_student_id = $_GET['view'] ?? '';

// Get filter values
$filter_year = $_GET['year'] ?? '';
$filter_intake = $_GET['intake'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_search = $_GET['search'] ?? '';

// Build query for students with marks
$where_conditions = ["1=1"];
if (!empty($filter_year)) {
    $where_conditions[] = "(a.fyp_acdyear = '" . $conn->real_escape_string($filter_year) . "')";
}
if (!empty($filter_intake)) {
    $where_conditions[] = "a.fyp_intake = '" . $conn->real_escape_string($filter_intake) . "'";
}
if (!empty($filter_status)) {
    if ($filter_status === 'marked') {
        $where_conditions[] = "tm.fyp_totalmark IS NOT NULL AND tm.fyp_totalmark > 0";
    } elseif ($filter_status === 'pending') {
        $where_conditions[] = "(tm.fyp_totalmark IS NULL OR tm.fyp_totalmark = 0)";
    }
}
if (!empty($filter_search)) {
    $search_escaped = $conn->real_escape_string($filter_search);
    $where_conditions[] = "(s.fyp_studid LIKE '%$search_escaped%' OR s.fyp_studname LIKE '%$search_escaped%' OR p.fyp_projecttitle LIKE '%$search_escaped%')";
}
$where_sql = implode(" AND ", $where_conditions);

// Get all students with their marks
$students_marks = [];
$res = $conn->query("SELECT s.fyp_studid, s.fyp_studname, s.fyp_studfullid, s.fyp_email,
                            p.fyp_projecttitle, p.fyp_projectid,
                            sup.fyp_name as supervisor_name,
                            a.fyp_acdyear, a.fyp_intake,
                            tm.fyp_totalmark, tm.fyp_grade
                     FROM student s
                     LEFT JOIN pairing pa ON s.fyp_studid = pa.fyp_studid
                     LEFT JOIN project p ON pa.fyp_projectid = p.fyp_projectid
                     LEFT JOIN supervisor sup ON pa.fyp_supervisorid = sup.fyp_supervisorid
                     LEFT JOIN academic_year a ON s.fyp_academicid = a.fyp_academicid
                     LEFT JOIN total_mark tm ON s.fyp_studid = tm.fyp_studid
                     WHERE $where_sql
                     ORDER BY s.fyp_studname");
if ($res) { while ($row = $res->fetch_assoc()) { $students_marks[] = $row; } }

// Get unique years for filter
$years = [];
$res = $conn->query("SELECT DISTINCT fyp_acdyear FROM academic_year ORDER BY fyp_acdyear DESC");
if ($res) { while ($row = $res->fetch_assoc()) { $years[] = $row['fyp_acdyear']; } }

// Get unique intakes for filter
$intakes = [];
$res = $conn->query("SELECT DISTINCT fyp_intake FROM academic_year ORDER BY fyp_intake");
if ($res) { while ($row = $res->fetch_assoc()) { $intakes[] = $row['fyp_intake']; } }
?>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-calculator"></i> Assessment Marks</h3>
    </div>
    <div class="card-body">
        <!-- Filters -->
        <div class="filter-row" style="padding:15px;background:rgba(139,92,246,0.1);border-radius:12px;margin-bottom:20px;">
            <form method="GET" style="display:flex;flex-wrap:wrap;gap:15px;width:100%;align-items:flex-end;">
                <div style="flex:1;min-width:150px;">
                    <label style="color:#a78bfa;font-size:0.85rem;display:block;margin-bottom:5px;">Academic Year</label>
                    <select name="year" class="form-control">
                        <option value="">All Years</option>
                        <?php foreach ($years as $y): ?>
                        <option value="<?= $y; ?>" <?= $filter_year === $y ? 'selected' : ''; ?>><?= $y; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="flex:1;min-width:150px;">
                    <label style="color:#a78bfa;font-size:0.85rem;display:block;margin-bottom:5px;">Intake</label>
                    <select name="intake" class="form-control">
                        <option value="">All Intakes</option>
                        <?php foreach ($intakes as $i): ?>
                        <option value="<?= $i; ?>" <?= $filter_intake === $i ? 'selected' : ''; ?>><?= $i; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="flex:1;min-width:150px;">
                    <label style="color:#a78bfa;font-size:0.85rem;display:block;margin-bottom:5px;">Status</label>
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="marked" <?= $filter_status === 'marked' ? 'selected' : ''; ?>>Marked</option>
                        <option value="pending" <?= $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    </select>
                </div>
                <div style="flex:2;min-width:200px;">
                    <label style="color:#a78bfa;font-size:0.85rem;display:block;margin-bottom:5px;">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Search student or project..." value="<?= htmlspecialchars($filter_search); ?>">
                </div>
                <div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
                    <a href="Coordinator_marks.php" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
                </div>
            </form>
        </div>

        <?php if (empty($students_marks)): ?>
            <div class="empty-state"><i class="fas fa-calculator"></i><p>No students found matching the criteria</p></div>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Project</th>
                            <th>Supervisor</th>
                            <th>Academic Year</th>
                            <th>Total Mark</th>
                            <th>Grade</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students_marks as $sm): ?>
                        <tr>
                            <td><?= htmlspecialchars($sm['fyp_studfullid']); ?></td>
                            <td><?= htmlspecialchars($sm['fyp_studname']); ?></td>
                            <td><?= htmlspecialchars($sm['fyp_projecttitle'] ?? '-'); ?></td>
                            <td><?= htmlspecialchars($sm['supervisor_name'] ?? '-'); ?></td>
                            <td><?= htmlspecialchars(($sm['fyp_acdyear'] ?? '') . ' ' . ($sm['fyp_intake'] ?? '')); ?></td>
                            <td>
                                <?php if ($sm['fyp_totalmark']): ?>
                                    <strong style="color:#34d399;"><?= number_format($sm['fyp_totalmark'], 2); ?>%</strong>
                                <?php else: ?>
                                    <span style="color:#64748b;">Not marked</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($sm['fyp_grade']): ?>
                                    <span class="badge badge-approved"><?= $sm['fyp_grade']; ?></span>
                                <?php else: ?>
                                    <span class="badge badge-pending">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-info btn-sm" onclick="viewStudentMarks('<?= $sm['fyp_studid']; ?>', '<?= htmlspecialchars($sm['fyp_studname'], ENT_QUOTES); ?>')"><i class="fas fa-eye"></i> View</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div style="margin-top:20px;padding:15px;background:rgba(139,92,246,0.1);border-radius:12px;">
                <strong>Total Students:</strong> <?= count($students_marks); ?> |
                <strong>Marked:</strong> <?= count(array_filter($students_marks, function($s) { return $s['fyp_totalmark'] > 0; })); ?> |
                <strong>Pending:</strong> <?= count(array_filter($students_marks, function($s) { return !$s['fyp_totalmark']; })); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- View Student Marks Modal -->
<div class="modal-overlay" id="viewMarksModal">
    <div class="modal" style="max-width:700px;">
        <div class="modal-header">
            <h3><i class="fas fa-chart-bar" style="color:#a78bfa;"></i> Student Marks Detail</h3>
            <button class="modal-close" onclick="closeModal('viewMarksModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div id="marksContent">
                <p style="text-align:center;color:#64748b;">Loading...</p>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('viewMarksModal')">Close</button>
        </div>
    </div>
</div>

<script>
function viewStudentMarks(studId, studName) {
    document.getElementById('marksContent').innerHTML = `
        <h4 style="color:#fff;margin-bottom:20px;">${studName}</h4>
        <p style="color:#94a3b8;">Student ID: ${studId}</p>
        <div style="margin-top:20px;padding:20px;background:rgba(139,92,246,0.1);border-radius:12px;">
            <p style="color:#a78bfa;">Detailed marks breakdown will be loaded here.</p>
            <p style="color:#64748b;font-size:0.85rem;margin-top:10px;">This feature requires additional database queries for individual item marks.</p>
        </div>
    `;
    openModal('viewMarksModal');
}
</script>

<?php include("includes/footer.php"); ?>