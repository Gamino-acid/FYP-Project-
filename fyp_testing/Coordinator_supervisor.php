<?php
/**
 * COORDINATOR MANAGE SUPERVISORS
 * Coordinator_supervisors.php
 */
include("includes/header.php");

// Get all supervisors
$supervisors = [];
$res = $conn->query("SELECT s.*, u.fyp_username, u.fyp_usertype FROM supervisor s LEFT JOIN user u ON s.fyp_userid = u.fyp_userid ORDER BY s.fyp_name");
if ($res) { while ($row = $res->fetch_assoc()) { $supervisors[] = $row; } }
?>

<div class="card">
    <div class="card-header">
        <h3>All Supervisors (<?= count($supervisors); ?>)</h3>
    </div>
    <div class="card-body">
        <?php if (empty($supervisors)): ?>
            <div class="empty-state"><i class="fas fa-user-tie"></i><p>No supervisors found</p></div>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Room</th>
                            <th>Programme</th>
                            <th>Email</th>
                            <th>Contact</th>
                            <th>Specialization</th>
                            <th>Moderator</th>
                        </tr>
                    </thead>
                    <tbody>
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
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include("includes/footer.php"); ?>