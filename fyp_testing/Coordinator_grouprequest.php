<?php
/**
 * COORDINATOR GROUP REQUESTS
 * Coordinator_grouprequest.php
 */
include("includes/header.php");

// Handle POST - Update request status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_request_status'])) {
    $request_id = $_POST['request_id'];
    $new_status = $_POST['new_status'];
    $stmt = $conn->prepare("UPDATE group_request SET request_status = ? WHERE request_id = ?");
    $stmt->bind_param("si", $new_status, $request_id);
    if ($stmt->execute()) {
        $message = "Group request status updated to: $new_status";
        $message_type = 'success';
    } else {
        $message = "Error updating request status.";
        $message_type = 'error';
    }
    $stmt->close();
}

// Get all group requests with student details
$all_requests = [];
$res = $conn->query("SELECT gr.*, 
                            sg.group_name,
                            s1.fyp_studname as inviter_name, s1.fyp_studfullid as inviter_fullid,
                            s2.fyp_studname as invitee_name, s2.fyp_studfullid as invitee_fullid
                     FROM group_request gr
                     LEFT JOIN student_group sg ON gr.group_id = sg.group_id
                     LEFT JOIN student s1 ON gr.inviter_id = s1.fyp_studid
                     LEFT JOIN student s2 ON gr.invitee_id = s2.fyp_studid
                     ORDER BY gr.request_status = 'Pending' DESC, gr.request_id DESC");
if ($res) { while ($row = $res->fetch_assoc()) { $all_requests[] = $row; } }

$pending_only = array_filter($all_requests, function($r) { return $r['request_status'] === 'Pending'; });
?>

<?php if ($message): ?>
<div class="alert alert-<?= $message_type; ?>">
    <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i> 
    <?= $message; ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3>All Group Requests</h3>
        <span class="badge badge-pending"><?= count($pending_only); ?> Pending</span>
    </div>
    <div class="card-body">
        <?php if (empty($all_requests)): ?>
            <div class="empty-state"><i class="fas fa-inbox"></i><p>No group requests found</p></div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Group</th>
                        <th>Inviter</th>
                        <th>Invitee</th>
                        <th>Current Status</th>
                        <th>Change Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_requests as $req): ?>
                    <tr>
                        <td><?= $req['request_id']; ?></td>
                        <td><strong><?= htmlspecialchars($req['group_name'] ?? 'Group #' . $req['group_id']); ?></strong></td>
                        <td><?= htmlspecialchars(($req['inviter_fullid'] ?? $req['inviter_id']) . ' - ' . ($req['inviter_name'] ?? '')); ?></td>
                        <td><?= htmlspecialchars(($req['invitee_fullid'] ?? $req['invitee_id']) . ' - ' . ($req['invitee_name'] ?? '')); ?></td>
                        <td><span class="badge badge-<?= $req['request_status'] === 'Accepted' ? 'approved' : ($req['request_status'] === 'Pending' ? 'pending' : 'rejected'); ?>"><?= $req['request_status']; ?></span></td>
                        <td>
                            <form method="POST" style="display:flex;gap:8px;align-items:center;">
                                <input type="hidden" name="request_id" value="<?= $req['request_id']; ?>">
                                <select name="new_status" class="form-control" style="width:auto;padding:6px 10px;">
                                    <option value="Pending" <?= $req['request_status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="Accepted" <?= $req['request_status'] === 'Accepted' ? 'selected' : ''; ?>>Accepted</option>
                                    <option value="Rejected" <?= $req['request_status'] === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                                <button type="submit" name="update_request_status" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Update</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php include("includes/footer.php"); ?>