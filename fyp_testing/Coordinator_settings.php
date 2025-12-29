<?php
/**
 * COORDINATOR SETTINGS
 * Coordinator_settings.php
 */
include("includes/header.php");

// Get maintenance settings
$maintenance = [];
$res = $conn->query("SELECT * FROM fyp_maintenance ORDER BY fyp_category, fyp_subject");
if ($res) { while ($row = $res->fetch_assoc()) { $maintenance[] = $row; } }
?>

<div class="card">
    <div class="card-header"><h3>System Settings</h3></div>
    <div class="card-body">
        <div class="quick-actions">
            <div class="quick-action"><i class="fas fa-calendar-alt"></i><h4>Academic Years</h4><p><?= count($academic_years); ?> years</p></div>
            <div class="quick-action"><i class="fas fa-graduation-cap"></i><h4>Programmes</h4><p><?= count($programmes); ?> programmes</p></div>
            <div class="quick-action"><i class="fas fa-sliders-h"></i><h4>Maintenance</h4><p><?= count($maintenance); ?> settings</p></div>
            <div class="quick-action"><i class="fas fa-database"></i><h4>Backup Data</h4><p>Export database</p></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Academic Years</h3></div>
    <div class="card-body">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Year</th>
                    <th>Intake</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($academic_years as $ay): ?>
                <tr>
                    <td><?= $ay['fyp_academicid']; ?></td>
                    <td><?= htmlspecialchars($ay['fyp_acdyear']); ?></td>
                    <td><?= htmlspecialchars($ay['fyp_intake']); ?></td>
                    <td><?= $ay['fyp_datecreated'] ? date('M j, Y', strtotime($ay['fyp_datecreated'])) : '-'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Programmes</h3></div>
    <div class="card-body">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Code</th>
                    <th>Full Name</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($programmes as $p): ?>
                <tr>
                    <td><?= $p['fyp_progid']; ?></td>
                    <td><?= htmlspecialchars($p['fyp_progname']); ?></td>
                    <td><?= htmlspecialchars($p['fyp_prognamefull']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include("includes/footer.php"); ?>