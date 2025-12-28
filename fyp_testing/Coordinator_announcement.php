<?php
/**
 * COORDINATOR ANNOUNCEMENTS
 * Coordinator_announcement.php
 */
include("includes/header.php");

// Handle POST - Create Announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_announcement'])) {
    $title = $_POST['title'];
    $content = $_POST['content'];
    $receiver = $_POST['receiver'];
    
    $stmt = $conn->prepare("INSERT INTO announcement (fyp_title, fyp_content, fyp_receiver, fyp_datecreated) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("sss", $title, $content, $receiver);
    if ($stmt->execute()) {
        $message = "Announcement created successfully!";
        $message_type = 'success';
    } else {
        $message = "Error creating announcement.";
        $message_type = 'error';
    }
    $stmt->close();
}

// Handle POST - Delete Announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_announcement'])) {
    $ann_id = $_POST['announcement_id'];
    $stmt = $conn->prepare("DELETE FROM announcement WHERE fyp_announcementid = ?");
    $stmt->bind_param("i", $ann_id);
    if ($stmt->execute()) {
        $message = "Announcement deleted successfully!";
        $message_type = 'success';
    }
    $stmt->close();
}

// Get filter parameters
$ann_sort = $_GET['sort'] ?? 'newest';
$ann_filter_receiver = $_GET['receiver'] ?? '';

// Build query with sorting
$ann_order = $ann_sort === 'oldest' ? 'ASC' : 'DESC';
$ann_where = '';
if (!empty($ann_filter_receiver)) {
    $ann_where = " WHERE fyp_receiver LIKE '%" . $conn->real_escape_string($ann_filter_receiver) . "%'";
}

$announcements = [];
$res = $conn->query("SELECT * FROM announcement $ann_where ORDER BY fyp_datecreated $ann_order LIMIT 50");
if ($res) { 
    while ($row = $res->fetch_assoc()) { 
        $announcements[] = $row; 
    }
}
?>

<?php if ($message): ?>
<div class="alert alert-<?= $message_type; ?>">
    <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i> 
    <?= $message; ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-bullhorn" style="color:#a78bfa;"></i> Announcements</h3>
        <div style="display:flex;gap:10px;">
            <button class="btn btn-primary" onclick="openModal('createAnnModal')">
                <i class="fas fa-plus"></i> Create Announcement
            </button>
        </div>
    </div>
    <div class="card-body">
        <!-- Filter Row -->
        <div class="filter-row" style="padding:15px;background:rgba(139,92,246,0.1);border-radius:12px;margin-bottom:20px;">
            <form method="GET" style="display:flex;gap:15px;flex-wrap:wrap;align-items:flex-end;">
                <div>
                    <label style="color:#a78bfa;font-size:0.85rem;display:block;margin-bottom:5px;">Sort By</label>
                    <select name="sort" class="form-control" style="width:auto;">
                        <option value="newest" <?= $ann_sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="oldest" <?= $ann_sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                    </select>
                </div>
                <div>
                    <label style="color:#a78bfa;font-size:0.85rem;display:block;margin-bottom:5px;">Receiver</label>
                    <select name="receiver" class="form-control" style="width:auto;">
                        <option value="">All</option>
                        <option value="student" <?= $ann_filter_receiver === 'student' ? 'selected' : ''; ?>>Students</option>
                        <option value="supervisor" <?= $ann_filter_receiver === 'supervisor' ? 'selected' : ''; ?>>Supervisors</option>
                        <option value="all" <?= $ann_filter_receiver === 'all' ? 'selected' : ''; ?>>Everyone</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Apply</button>
                <a href="Coordinator_announcement.php" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Clear</a>
            </form>
        </div>

        <?php if (empty($announcements)): ?>
            <div class="empty-state"><i class="fas fa-bullhorn"></i><p>No announcements found</p></div>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Content</th>
                            <th>Receiver</th>
                            <th>Date Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($announcements as $ann): ?>
                        <tr>
                            <td><?= $ann['fyp_announcementid']; ?></td>
                            <td><strong><?= htmlspecialchars($ann['fyp_title']); ?></strong></td>
                            <td><?= htmlspecialchars(substr($ann['fyp_content'], 0, 80)); ?><?= strlen($ann['fyp_content']) > 80 ? '...' : ''; ?></td>
                            <td><span class="badge badge-open"><?= ucfirst($ann['fyp_receiver']); ?></span></td>
                            <td><?= date('M j, Y g:i A', strtotime($ann['fyp_datecreated'])); ?></td>
                            <td>
                                <button class="btn btn-info btn-sm" onclick="viewAnnouncement('<?= htmlspecialchars($ann['fyp_title'], ENT_QUOTES); ?>', '<?= htmlspecialchars($ann['fyp_content'], ENT_QUOTES); ?>', '<?= $ann['fyp_receiver']; ?>', '<?= $ann['fyp_datecreated']; ?>')"><i class="fas fa-eye"></i></button>
                                <button class="btn btn-danger btn-sm" onclick="deleteAnnouncement(<?= $ann['fyp_announcementid']; ?>, '<?= htmlspecialchars($ann['fyp_title'], ENT_QUOTES); ?>')"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create Announcement Modal -->
<div class="modal-overlay" id="createAnnModal">
    <div class="modal" style="max-width:600px;">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle" style="color:#34d399;"></i> Create Announcement</h3>
            <button class="modal-close" onclick="closeModal('createAnnModal')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <div class="form-group">
                    <label>Title <span style="color:#f87171;">*</span></label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Content <span style="color:#f87171;">*</span></label>
                    <textarea name="content" class="form-control" rows="5" required></textarea>
                </div>
                <div class="form-group">
                    <label>Send To <span style="color:#f87171;">*</span></label>
                    <select name="receiver" class="form-control" required>
                        <option value="all">Everyone</option>
                        <option value="student">Students Only</option>
                        <option value="supervisor">Supervisors Only</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createAnnModal')">Cancel</button>
                <button type="submit" name="create_announcement" class="btn btn-success"><i class="fas fa-paper-plane"></i> Publish</button>
            </div>
        </form>
    </div>
</div>

<!-- View Announcement Modal -->
<div class="modal-overlay" id="viewAnnModal">
    <div class="modal" style="max-width:600px;">
        <div class="modal-header">
            <h3><i class="fas fa-bullhorn" style="color:#a78bfa;"></i> View Announcement</h3>
            <button class="modal-close" onclick="closeModal('viewAnnModal')">&times;</button>
        </div>
        <div class="modal-body">
            <h4 id="view_ann_title" style="color:#fff;margin-bottom:15px;"></h4>
            <p id="view_ann_content" style="color:#e2e8f0;line-height:1.6;margin-bottom:20px;"></p>
            <div style="display:flex;gap:20px;color:#94a3b8;font-size:0.85rem;">
                <span><i class="fas fa-users"></i> <span id="view_ann_receiver"></span></span>
                <span><i class="fas fa-calendar"></i> <span id="view_ann_date"></span></span>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('viewAnnModal')">Close</button>
        </div>
    </div>
</div>

<!-- Delete Announcement Modal -->
<div class="modal-overlay" id="deleteAnnModal">
    <div class="modal" style="max-width:450px;">
        <div class="modal-header">
            <h3><i class="fas fa-trash-alt" style="color:#f87171;"></i> Delete Announcement</h3>
            <button class="modal-close" onclick="closeModal('deleteAnnModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="announcement_id" id="delete_ann_id">
            <div class="modal-body">
                <div style="text-align:center;padding:20px;">
                    <i class="fas fa-exclamation-triangle" style="font-size:3rem;color:#f87171;margin-bottom:15px;"></i>
                    <p style="color:#e2e8f0;margin-bottom:10px;">Are you sure you want to delete this announcement?</p>
                    <p style="color:#f87171;font-weight:600;" id="delete_ann_title"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('deleteAnnModal')">Cancel</button>
                <button type="submit" name="delete_announcement" class="btn btn-danger"><i class="fas fa-trash"></i> Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
function viewAnnouncement(title, content, receiver, date) {
    document.getElementById('view_ann_title').textContent = title;
    document.getElementById('view_ann_content').textContent = content;
    document.getElementById('view_ann_receiver').textContent = receiver.charAt(0).toUpperCase() + receiver.slice(1);
    document.getElementById('view_ann_date').textContent = new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
    openModal('viewAnnModal');
}

function deleteAnnouncement(id, title) {
    document.getElementById('delete_ann_id').value = id;
    document.getElementById('delete_ann_title').textContent = title;
    openModal('deleteAnnModal');
}
</script>

<?php include("includes/footer.php"); ?>