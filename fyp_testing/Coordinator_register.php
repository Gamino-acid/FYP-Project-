<?php
/**
 * COORDINATOR STUDENT REGISTRATIONS
 * Coordinator_register.php
 */
include("includes/header.php");

// Handle POST - Approve Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_registration'])) {
    $reg_id = $_POST['registration_id'];
    
    // Get registration data
    $stmt = $conn->prepare("SELECT * FROM pending_registration WHERE id = ?");
    $stmt->bind_param("i", $reg_id);
    $stmt->execute();
    $reg = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($reg) {
        // Generate username and password
        $username = strtolower(str_replace(' ', '', $reg['student_id']));
        $password = bin2hex(random_bytes(4)); // 8 character random password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert into user table
        $stmt = $conn->prepare("INSERT INTO user (fyp_username, fyp_password, fyp_usertype) VALUES (?, ?, 'student')");
        $stmt->bind_param("ss", $username, $hashed_password);
        $stmt->execute();
        $user_id = $conn->insert_id;
        $stmt->close();
        
        // Insert into student table
        $stmt = $conn->prepare("INSERT INTO student (fyp_studfullid, fyp_studname, fyp_email, fyp_contactno, fyp_progid, fyp_academicid, fyp_userid) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssiis", $reg['student_id'], $reg['full_name'], $reg['email'], $reg['phone'], $reg['programme_id'], $reg['academic_year_id'], $user_id);
        $stmt->execute();
        $stmt->close();
        
        // Update registration status
        $stmt = $conn->prepare("UPDATE pending_registration SET status = 'approved' WHERE id = ?");
        $stmt->bind_param("i", $reg_id);
        $stmt->execute();
        $stmt->close();
        
        $message = "Registration approved! Username: $username, Password: $password";
        $message_type = 'success';
    }
}

// Handle POST - Reject Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_registration'])) {
    $reg_id = $_POST['registration_id'];
    $stmt = $conn->prepare("UPDATE pending_registration SET status = 'rejected' WHERE id = ?");
    $stmt->bind_param("i", $reg_id);
    if ($stmt->execute()) {
        $message = "Registration rejected.";
        $message_type = 'success';
    }
    $stmt->close();
}

$pending_regs = [];
$res = $conn->query("SELECT pr.*, p.fyp_progname, ay.fyp_acdyear, ay.fyp_intake 
                     FROM pending_registration pr 
                     LEFT JOIN programme p ON pr.programme_id = p.fyp_progid 
                     LEFT JOIN academic_year ay ON pr.academic_year_id = ay.fyp_academicid 
                     ORDER BY pr.status = 'pending' DESC, pr.created_at DESC");
if ($res) { while ($row = $res->fetch_assoc()) { $pending_regs[] = $row; } }

$pending_only = array_filter($pending_regs, function($r) { return $r['status'] === 'pending'; });
?>

<?php if ($message): ?>
<div class="alert alert-<?= $message_type; ?>">
    <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i> 
    <?= $message; ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3>Student Registrations</h3>
        <span class="badge badge-pending"><?= count($pending_only); ?> Pending</span>
    </div>
    <div class="card-body">
        <?php if (empty($pending_regs)): ?>
            <div class="empty-state"><i class="fas fa-user-plus"></i><p>No registration requests found</p></div>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Programme</th>
                            <th>Academic Year</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_regs as $reg): ?>
                        <tr>
                            <td><?= $reg['id']; ?></td>
                            <td><?= htmlspecialchars($reg['student_id']); ?></td>
                            <td><?= htmlspecialchars($reg['full_name']); ?></td>
                            <td><?= htmlspecialchars($reg['email']); ?></td>
                            <td><?= htmlspecialchars($reg['phone'] ?? '-'); ?></td>
                            <td><?= htmlspecialchars($reg['fyp_progname'] ?? '-'); ?></td>
                            <td><?= htmlspecialchars(($reg['fyp_acdyear'] ?? '') . ' ' . ($reg['fyp_intake'] ?? '')); ?></td>
                            <td>
                                <span class="badge badge-<?= $reg['status'] === 'approved' ? 'approved' : ($reg['status'] === 'pending' ? 'pending' : 'rejected'); ?>">
                                    <?= ucfirst($reg['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($reg['status'] === 'pending'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="registration_id" value="<?= $reg['id']; ?>">
                                    <button type="submit" name="approve_registration" class="btn btn-success btn-sm"><i class="fas fa-check"></i></button>
                                    <button type="submit" name="reject_registration" class="btn btn-danger btn-sm"><i class="fas fa-times"></i></button>
                                </form>
                                <?php else: ?>
                                <span style="color:#64748b;">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include("includes/footer.php"); ?>