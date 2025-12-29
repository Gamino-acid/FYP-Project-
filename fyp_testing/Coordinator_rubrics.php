<?php
/**
 * COORDINATOR RUBRICS ASSESSMENT
 * Coordinator_rubrics.php
 */
include("includes/header.php");

// Handle POST - Add Set
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_set'])) {
    $setname = $_POST['setname'];
    $academic_id = $_POST['academic_id'];
    $stmt = $conn->prepare("INSERT INTO `set` (fyp_setname, fyp_academicid) VALUES (?, ?)");
    $stmt->bind_param("si", $setname, $academic_id);
    if ($stmt->execute()) {
        $message = "Assessment set added successfully!";
        $message_type = 'success';
    }
    $stmt->close();
}

// Handle POST - Add Item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    $itemname = $_POST['itemname'];
    $itemdesc = $_POST['itemdesc'] ?? '';
    $stmt = $conn->prepare("INSERT INTO item (fyp_itemname, fyp_itemdesc) VALUES (?, ?)");
    $stmt->bind_param("ss", $itemname, $itemdesc);
    if ($stmt->execute()) {
        $message = "Item added successfully!";
        $message_type = 'success';
    }
    $stmt->close();
}

// Handle POST - Add Marking Criteria
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_marking_criteria'])) {
    $criterianame = $_POST['criterianame'];
    $percent = $_POST['percentallocation'];
    $stmt = $conn->prepare("INSERT INTO marking_criteria (fyp_criterianame, fyp_percentallocation) VALUES (?, ?)");
    $stmt->bind_param("sd", $criterianame, $percent);
    if ($stmt->execute()) {
        $message = "Marking criteria added successfully!";
        $message_type = 'success';
    }
    $stmt->close();
}

$rubrics_action = $_GET['action'] ?? 'sets';

// Get all sets with academic year info
$sets = [];
$res = $conn->query("SELECT s.*, a.fyp_acdyear, a.fyp_intake FROM `set` s LEFT JOIN academic_year a ON s.fyp_academicid = a.fyp_academicid ORDER BY s.fyp_setid DESC");
if ($res) { while ($row = $res->fetch_assoc()) { $sets[] = $row; } }

// Get all items
$items = [];
$res = $conn->query("SELECT * FROM item ORDER BY fyp_itemid");
if ($res) { while ($row = $res->fetch_assoc()) { $items[] = $row; } }

// Get all assessment criteria
$criteria = [];
$res = $conn->query("SELECT * FROM assessment_criteria ORDER BY fyp_min ASC");
if ($res) { while ($row = $res->fetch_assoc()) { $criteria[] = $row; } }

// Get all marking criteria
$marking_criteria = [];
$res = $conn->query("SELECT * FROM marking_criteria ORDER BY fyp_criteriaid");
if ($res) { while ($row = $res->fetch_assoc()) { $marking_criteria[] = $row; } }
?>

<?php if ($message): ?>
<div class="alert alert-<?= $message_type; ?>">
    <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i> 
    <?= $message; ?>
</div>
<?php endif; ?>

<!-- Rubrics Sub-Navigation -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-body" style="padding:15px;">
        <div class="tab-nav">
            <a href="?action=sets" class="tab-btn <?= $rubrics_action === 'sets' ? 'active' : ''; ?>"><i class="fas fa-layer-group"></i> Sets</a>
            <a href="?action=items" class="tab-btn <?= $rubrics_action === 'items' ? 'active' : ''; ?>"><i class="fas fa-list"></i> Items</a>
            <a href="?action=criteria" class="tab-btn <?= $rubrics_action === 'criteria' ? 'active' : ''; ?>"><i class="fas fa-star"></i> Grade Criteria</a>
            <a href="?action=marking" class="tab-btn <?= $rubrics_action === 'marking' ? 'active' : ''; ?>"><i class="fas fa-percent"></i> Marking Criteria</a>
        </div>
    </div>
</div>

<?php if ($rubrics_action === 'sets'): ?>
<!-- Assessment Sets -->
<div class="card">
    <div class="card-header">
        <h3>Assessment Sets</h3>
        <button class="btn btn-primary" onclick="openModal('addSetModal')"><i class="fas fa-plus"></i> Add Set</button>
    </div>
    <div class="card-body">
        <?php if (empty($sets)): ?>
            <div class="empty-state"><i class="fas fa-layer-group"></i><p>No assessment sets found</p></div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Set Name</th>
                        <th>Academic Year</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sets as $s): ?>
                    <tr>
                        <td><?= $s['fyp_setid']; ?></td>
                        <td><?= htmlspecialchars($s['fyp_setname']); ?></td>
                        <td><?= htmlspecialchars(($s['fyp_acdyear'] ?? '') . ' ' . ($s['fyp_intake'] ?? '')); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($rubrics_action === 'items'): ?>
<!-- Assessment Items -->
<div class="card">
    <div class="card-header">
        <h3>Assessment Items</h3>
        <button class="btn btn-primary" onclick="openModal('addItemModal')"><i class="fas fa-plus"></i> Add Item</button>
    </div>
    <div class="card-body">
        <?php if (empty($items)): ?>
            <div class="empty-state"><i class="fas fa-list"></i><p>No items found</p></div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Item Name</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $i): ?>
                    <tr>
                        <td><?= $i['fyp_itemid']; ?></td>
                        <td><?= htmlspecialchars($i['fyp_itemname']); ?></td>
                        <td><?= htmlspecialchars($i['fyp_itemdesc'] ?? '-'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($rubrics_action === 'criteria'): ?>
<!-- Grade Criteria -->
<div class="card">
    <div class="card-header">
        <h3>Grade Criteria</h3>
    </div>
    <div class="card-body">
        <?php if (empty($criteria)): ?>
            <div class="empty-state"><i class="fas fa-star"></i><p>No grade criteria found</p></div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Grade</th>
                        <th>Min %</th>
                        <th>Max %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($criteria as $c): ?>
                    <tr>
                        <td><?= $c['fyp_assessmentid']; ?></td>
                        <td><span class="badge badge-approved"><?= htmlspecialchars($c['fyp_grade']); ?></span></td>
                        <td><?= $c['fyp_min']; ?>%</td>
                        <td><?= $c['fyp_max']; ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($rubrics_action === 'marking'): ?>
<!-- Marking Criteria -->
<div class="card">
    <div class="card-header">
        <h3>Marking Criteria</h3>
        <button class="btn btn-primary" onclick="openModal('addMarkingModal')"><i class="fas fa-plus"></i> Add Criteria</button>
    </div>
    <div class="card-body">
        <?php if (empty($marking_criteria)): ?>
            <div class="empty-state"><i class="fas fa-percent"></i><p>No marking criteria found</p></div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Criteria Name</th>
                        <th>Percentage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($marking_criteria as $mc): ?>
                    <tr>
                        <td><?= $mc['fyp_criteriaid']; ?></td>
                        <td><?= htmlspecialchars($mc['fyp_criterianame']); ?></td>
                        <td><?= $mc['fyp_percentallocation']; ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Add Set Modal -->
<div class="modal-overlay" id="addSetModal">
    <div class="modal">
        <div class="modal-header"><h3>Add Assessment Set</h3><button class="modal-close" onclick="closeModal('addSetModal')">&times;</button></div>
        <form method="POST">
            <div class="modal-body">
                <div class="form-group">
                    <label>Set Name</label>
                    <input type="text" name="setname" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Academic Year</label>
                    <select name="academic_id" class="form-control" required>
                        <?php foreach ($academic_years as $ay): ?>
                        <option value="<?= $ay['fyp_academicid']; ?>"><?= $ay['fyp_acdyear'] . ' ' . $ay['fyp_intake']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addSetModal')">Cancel</button>
                <button type="submit" name="add_set" class="btn btn-primary">Add Set</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Item Modal -->
<div class="modal-overlay" id="addItemModal">
    <div class="modal">
        <div class="modal-header"><h3>Add Assessment Item</h3><button class="modal-close" onclick="closeModal('addItemModal')">&times;</button></div>
        <form method="POST">
            <div class="modal-body">
                <div class="form-group">
                    <label>Item Name</label>
                    <input type="text" name="itemname" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="itemdesc" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addItemModal')">Cancel</button>
                <button type="submit" name="add_item" class="btn btn-primary">Add Item</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Marking Criteria Modal -->
<div class="modal-overlay" id="addMarkingModal">
    <div class="modal">
        <div class="modal-header"><h3>Add Marking Criteria</h3><button class="modal-close" onclick="closeModal('addMarkingModal')">&times;</button></div>
        <form method="POST">
            <div class="modal-body">
                <div class="form-group">
                    <label>Criteria Name</label>
                    <input type="text" name="criterianame" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Percentage Allocation</label>
                    <input type="number" name="percentallocation" class="form-control" min="0" max="100" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addMarkingModal')">Cancel</button>
                <button type="submit" name="add_marking_criteria" class="btn btn-primary">Add Criteria</button>
            </div>
        </form>
    </div>
</div>

<?php include("includes/footer.php"); ?>