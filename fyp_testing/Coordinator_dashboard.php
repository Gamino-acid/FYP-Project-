<?php
/**
 * COORDINATOR DASHBOARD
 * Coordinator_dashboard.php
 */
include("includes/header.php");
?>

<div class="welcome-box">
    <h2>Welcome back, <?= htmlspecialchars($user_name); ?>!</h2>
    <p>Manage FYP students, supervisors, pairings, assessments and generate reports.</p>
</div>

<div class="stats-grid">
    <a href="Coordinator_students.php" class="stat-card"><div class="stat-icon purple"><i class="fas fa-user-graduate"></i></div><div class="stat-info"><h4><?= $total_students; ?></h4><p>Total Students</p></div></a>
    <a href="Coordinator_supervisors.php" class="stat-card"><div class="stat-icon blue"><i class="fas fa-user-tie"></i></div><div class="stat-info"><h4><?= $total_supervisors; ?></h4><p>Total Supervisors</p></div></a>
    <a href="Coordinator_grouprequest.php" class="stat-card"><div class="stat-icon orange"><i class="fas fa-user-plus"></i></div><div class="stat-info"><h4><?= $pending_requests; ?></h4><p>Pending Group Requests</p></div></a>
    <a href="Coordinator_project.php" class="stat-card"><div class="stat-icon green"><i class="fas fa-folder-open"></i></div><div class="stat-info"><h4><?= $total_projects; ?></h4><p>Total Projects</p></div></a>
    <a href="Coordinator_pairing.php" class="stat-card"><div class="stat-icon red"><i class="fas fa-link"></i></div><div class="stat-info"><h4><?= $total_pairings; ?></h4><p>Total Pairings</p></div></a>
</div>

<?php if ($pending_requests > 0): ?>
<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> You have <strong><?= $pending_requests; ?></strong> pending group request(s). <a href="Coordinator_grouprequest.php" style="color:#fb923c;margin-left:10px;">Review Now →</a></div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h3>Quick Actions</h3></div>
    <div class="card-body">
        <div class="quick-actions">
            <a href="Coordinator_grouprequest.php" class="quick-action"><i class="fas fa-user-plus"></i><h4>Group Requests</h4><p>Approve or reject requests</p></a>
            <a href="Coordinator_pairing.php" class="quick-action"><i class="fas fa-link"></i><h4>Manage Pairings</h4><p>Assign students to supervisors</p></a>
            <a href="Coordinator_rubrics.php" class="quick-action"><i class="fas fa-list-check"></i><h4>Assessment Rubrics</h4><p>Create and manage rubrics</p></a>
            <a href="Coordinator_marks.php" class="quick-action"><i class="fas fa-calculator"></i><h4>View Marks</h4><p>Assessment mark allocation</p></a>
            <a href="Coordinator_report.php" class="quick-action"><i class="fas fa-file-alt"></i><h4>Generate Reports</h4><p>Export forms and reports</p></a>
            <a href="Coordinator_announcement.php" class="quick-action"><i class="fas fa-bullhorn"></i><h4>Announcements</h4><p>Post announcements</p></a>
        </div>
    </div>
</div>

<?php include("includes/footer.php"); ?>