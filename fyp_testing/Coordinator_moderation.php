<?php
/**
 * COORDINATOR STUDENT MODERATION
 * Coordinator_moderation.php
 */
include("includes/header.php");

// Get all moderation records
$moderations = [];
$res = $conn->query("SELECT sm.*, s.fyp_studname, s.fyp_studfullid, mc.fyp_criterianame, mc.fyp_criteriadesc 
                     FROM student_moderation sm 
                     LEFT JOIN student s ON sm.fyp_studid = s.fyp_studid 
                     LEFT JOIN moderation_criteria mc ON sm.fyp_mdcriteriaid = mc.fyp_mdcriteriaid 
                     ORDER BY sm.fyp_studid");
if ($res) { while ($row = $res->fetch_assoc()) { $moderations[] = $row; } }
?>

<div class="card">
    <div class="card-header">
        <h3>Student Moderation Records</h3>
    </div>
    <div class="card-body">
        <?php if (empty($moderations)): ?>
            <div class="empty-state"><i class="fas fa-clipboard-check"></i><p>No moderation records found</p></div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Student Name</th>
                        <th>Criteria</th>
                        <th>Description</th>
                        <th>Comply</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($moderations as $m): ?>
                    <tr>
                        <td><?= htmlspecialchars($m['fyp_studfullid'] ?? $m['fyp_studid']); ?></td>
                        <td><?= htmlspecialchars($m['fyp_studname'] ?? '-'); ?></td>
                        <td><?= htmlspecialchars($m['fyp_criterianame'] ?? '-'); ?></td>
                        <td><?= htmlspecialchars($m['fyp_criteriadesc'] ?? '-'); ?></td>
                        <td><?= $m['fyp_comply'] ? '<span class="badge badge-approved">Yes</span>' : '<span class="badge badge-pending">No</span>'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php include("includes/footer.php"); ?>