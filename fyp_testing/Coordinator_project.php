<?php
/**
 * COORDINATOR MANAGE PROJECTS
 * Coordinator_project.php
 */
include("includes/header.php");

// Handle POST - Add Project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_project'])) {
    $title = $_POST['project_title'];
    $type = $_POST['project_type'];
    $status = $_POST['project_status'];
    $category = $_POST['project_category'] ?? '';
    $description = $_POST['project_description'] ?? '';
    $supervisor_id = $_POST['supervisor_id'] ?: null;
    $max_students = $_POST['max_students'] ?? 1;
    
    $stmt = $conn->prepare("INSERT INTO project (fyp_projecttitle, fyp_projecttype, fyp_projectstatus, fyp_projectcat, fyp_projectdesc, fyp_supervisorid, fyp_maxstudent, fyp_datecreated) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("sssssii", $title, $type, $status, $category, $description, $supervisor_id, $max_students);
    if ($stmt->execute()) {
        $message = "Project added successfully!";
        $message_type = 'success';
    } else {
        $message = "Error adding project.";
        $message_type = 'error';
    }
    $stmt->close();
}

// Handle POST - Update Project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_project'])) {
    $project_id = $_POST['project_id'];
    $title = $_POST['project_title'];
    $type = $_POST['project_type'];
    $status = $_POST['project_status'];
    $category = $_POST['project_category'] ?? '';
    $description = $_POST['project_description'] ?? '';
    $supervisor_id = $_POST['supervisor_id'] ?: null;
    $max_students = $_POST['max_students'] ?? 1;
    
    $stmt = $conn->prepare("UPDATE project SET fyp_projecttitle = ?, fyp_projecttype = ?, fyp_projectstatus = ?, fyp_projectcat = ?, fyp_projectdesc = ?, fyp_supervisorid = ?, fyp_maxstudent = ? WHERE fyp_projectid = ?");
    $stmt->bind_param("sssssiii", $title, $type, $status, $category, $description, $supervisor_id, $max_students, $project_id);
    if ($stmt->execute()) {
        $message = "Project updated successfully!";
        $message_type = 'success';
    } else {
        $message = "Error updating project.";
        $message_type = 'error';
    }
    $stmt->close();
}

// Handle POST - Delete Project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_project'])) {
    $project_id = $_POST['project_id'];
    $stmt = $conn->prepare("DELETE FROM project WHERE fyp_projectid = ?");
    $stmt->bind_param("i", $project_id);
    if ($stmt->execute()) {
        $message = "Project deleted successfully!";
        $message_type = 'success';
    } else {
        $message = "Error deleting project.";
        $message_type = 'error';
    }
    $stmt->close();
}

$projects = [];
$res = $conn->query("SELECT p.*, s.fyp_name as supervisor_name, s.fyp_email as supervisor_email,
                            (SELECT COUNT(*) FROM pairing WHERE fyp_projectid = p.fyp_projectid) as student_count
                     FROM project p 
                     LEFT JOIN supervisor s ON p.fyp_supervisorid = s.fyp_supervisorid 
                     ORDER BY p.fyp_datecreated DESC");
if ($res) { while ($row = $res->fetch_assoc()) { $projects[] = $row; } }

$supervisors_list = [];
$res = $conn->query("SELECT fyp_supervisorid, fyp_name, fyp_email FROM supervisor ORDER BY fyp_name");
if ($res) { while ($row = $res->fetch_assoc()) { $supervisors_list[] = $row; } }

$available_count = count(array_filter($projects, function($p) { return ($p['fyp_projectstatus'] ?? '') === 'Available'; }));
$unavailable_count = count(array_filter($projects, function($p) { return ($p['fyp_projectstatus'] ?? '') === 'Unavailable'; }));
?>

<?php if ($message): ?>
<div class="alert alert-<?= $message_type; ?>">
    <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i> 
    <?= $message; ?>
</div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="stats-grid" style="margin-bottom:25px;">
    <div class="stat-card" style="cursor:default;">
        <div class="stat-icon purple"><i class="fas fa-folder-open"></i></div>
        <div class="stat-info"><h4><?= count($projects); ?></h4><p>Total Projects</p></div>
    </div>
    <div class="stat-card" style="cursor:default;">
        <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
        <div class="stat-info"><h4><?= $available_count; ?></h4><p>Available</p></div>
    </div>
    <div class="stat-card" style="cursor:default;">
        <div class="stat-icon orange"><i class="fas fa-times-circle"></i></div>
        <div class="stat-info"><h4><?= $unavailable_count; ?></h4><p>Unavailable</p></div>
    </div>
</div>

<!-- Main Projects Card -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-project-diagram"></i> Project Allocation</h3>
        <button class="btn btn-primary" onclick="openModal('addProjectModal')">
            <i class="fas fa-plus"></i> Add New Project
        </button>
    </div>
    <div class="card-body">
        <!-- Filter Controls -->
        <div class="filter-controls" style="display:flex;flex-wrap:wrap;gap:15px;margin-bottom:20px;padding:15px;background:rgba(139,92,246,0.1);border-radius:12px;">
            <div style="flex:1;min-width:200px;">
                <label style="color:#a78bfa;font-size:0.85rem;display:block;margin-bottom:5px;"><i class="fas fa-search"></i> Search</label>
                <input type="text" id="projectSearch" class="form-control" placeholder="Search by title, supervisor..." onkeyup="filterProjectTable()">
            </div>
            <div style="min-width:150px;">
                <label style="color:#a78bfa;font-size:0.85rem;display:block;margin-bottom:5px;"><i class="fas fa-filter"></i> Status</label>
                <select id="filterProjectStatus" class="form-control" onchange="filterProjectTable()">
                    <option value="all">All Status</option>
                    <option value="Available">Available</option>
                    <option value="Unavailable">Unavailable</option>
                </select>
            </div>
            <div style="min-width:150px;">
                <label style="color:#a78bfa;font-size:0.85rem;display:block;margin-bottom:5px;"><i class="fas fa-tags"></i> Type</label>
                <select id="filterProjectType" class="form-control" onchange="filterProjectTable()">
                    <option value="all">All Types</option>
                    <option value="Research">Research</option>
                    <option value="Application">Application</option>
                    <option value="Package">Package</option>
                </select>
            </div>
        </div>
        
        <?php if (empty($projects)): ?>
            <div class="empty-state"><i class="fas fa-folder-open"></i><p>No projects found. Click "Add New Project" to create one.</p></div>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="data-table" id="projectTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Project Title</th>
                            <th>Type</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Supervisor</th>
                            <th>Students</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projects as $p): ?>
                        <tr data-status="<?= $p['fyp_projectstatus'] ?? ''; ?>" data-type="<?= $p['fyp_projecttype'] ?? ''; ?>">
                            <td><?= $p['fyp_projectid']; ?></td>
                            <td>
                                <strong><?= htmlspecialchars($p['fyp_projecttitle']); ?></strong>
                                <?php if (!empty($p['fyp_projectdesc'])): ?>
                                    <br><small style="color:#64748b;"><?= htmlspecialchars(substr($p['fyp_projectdesc'], 0, 60)); ?>...</small>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-open"><?= $p['fyp_projecttype'] ?? '-'; ?></span></td>
                            <td><?= htmlspecialchars($p['fyp_projectcat'] ?? '-'); ?></td>
                            <td>
                                <span class="badge badge-<?= ($p['fyp_projectstatus'] ?? '') === 'Available' ? 'approved' : 'pending'; ?>">
                                    <?= $p['fyp_projectstatus'] ?? '-'; ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($p['supervisor_name'] ?? '-'); ?></td>
                            <td><?= $p['student_count']; ?> / <?= $p['fyp_maxstudent'] ?? 1; ?></td>
                            <td>
                                <button class="btn btn-info btn-sm" onclick="viewProject(<?= $p['fyp_projectid']; ?>, '<?= htmlspecialchars($p['fyp_projecttitle'], ENT_QUOTES); ?>', '<?= htmlspecialchars($p['fyp_projectdesc'] ?? '', ENT_QUOTES); ?>', '<?= $p['fyp_projecttype'] ?? ''; ?>', '<?= $p['fyp_projectstatus'] ?? ''; ?>', '<?= htmlspecialchars($p['supervisor_name'] ?? '', ENT_QUOTES); ?>')"><i class="fas fa-eye"></i></button>
                                <button class="btn btn-primary btn-sm" onclick="editProject(<?= $p['fyp_projectid']; ?>, '<?= htmlspecialchars($p['fyp_projecttitle'], ENT_QUOTES); ?>', '<?= $p['fyp_projecttype'] ?? ''; ?>', '<?= $p['fyp_projectstatus'] ?? ''; ?>', '<?= htmlspecialchars($p['fyp_projectcat'] ?? '', ENT_QUOTES); ?>', '<?= htmlspecialchars($p['fyp_projectdesc'] ?? '', ENT_QUOTES); ?>', '<?= $p['fyp_supervisorid'] ?? ''; ?>', <?= $p['fyp_maxstudent'] ?? 1; ?>)"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-danger btn-sm" onclick="deleteProject(<?= $p['fyp_projectid']; ?>, '<?= htmlspecialchars($p['fyp_projecttitle'], ENT_QUOTES); ?>')"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Project Modal -->
<div class="modal-overlay" id="addProjectModal">
    <div class="modal" style="max-width:700px;">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle" style="color:#34d399;"></i> Add New Project</h3>
            <button class="modal-close" onclick="closeModal('addProjectModal')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Project Title <span style="color:#f87171;">*</span></label>
                        <input type="text" name="project_title" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Project Type <span style="color:#f87171;">*</span></label>
                        <select name="project_type" class="form-control" required>
                            <option value="Research">Research</option>
                            <option value="Application">Application</option>
                            <option value="Package">Package</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Project Status <span style="color:#f87171;">*</span></label>
                        <select name="project_status" class="form-control" required>
                            <option value="Available">Available</option>
                            <option value="Unavailable">Unavailable</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <input type="text" name="project_category" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="project_description" class="form-control" rows="3"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Supervisor</label>
                        <select name="supervisor_id" class="form-control">
                            <option value="">-- Select Supervisor --</option>
                            <?php foreach ($supervisors_list as $sv): ?>
                            <option value="<?= $sv['fyp_supervisorid']; ?>"><?= htmlspecialchars($sv['fyp_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Max Students</label>
                        <input type="number" name="max_students" class="form-control" value="1" min="1" max="10">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addProjectModal')">Cancel</button>
                <button type="submit" name="add_project" class="btn btn-success"><i class="fas fa-plus"></i> Add Project</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Project Modal -->
<div class="modal-overlay" id="editProjectModal">
    <div class="modal" style="max-width:700px;">
        <div class="modal-header">
            <h3><i class="fas fa-edit" style="color:#60a5fa;"></i> Edit Project</h3>
            <button class="modal-close" onclick="closeModal('editProjectModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="project_id" id="edit_project_id">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Project Title <span style="color:#f87171;">*</span></label>
                        <input type="text" name="project_title" id="edit_project_title" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Project Type <span style="color:#f87171;">*</span></label>
                        <select name="project_type" id="edit_project_type" class="form-control" required>
                            <option value="Research">Research</option>
                            <option value="Application">Application</option>
                            <option value="Package">Package</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Project Status <span style="color:#f87171;">*</span></label>
                        <select name="project_status" id="edit_project_status" class="form-control" required>
                            <option value="Available">Available</option>
                            <option value="Unavailable">Unavailable</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <input type="text" name="project_category" id="edit_project_category" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="project_description" id="edit_project_description" class="form-control" rows="3"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Supervisor</label>
                        <select name="supervisor_id" id="edit_supervisor_id" class="form-control">
                            <option value="">-- Select Supervisor --</option>
                            <?php foreach ($supervisors_list as $sv): ?>
                            <option value="<?= $sv['fyp_supervisorid']; ?>"><?= htmlspecialchars($sv['fyp_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Max Students</label>
                        <input type="number" name="max_students" id="edit_max_students" class="form-control" min="1" max="10">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editProjectModal')">Cancel</button>
                <button type="submit" name="update_project" class="btn btn-primary"><i class="fas fa-save"></i> Update Project</button>
            </div>
        </form>
    </div>
</div>

<!-- View Project Modal -->
<div class="modal-overlay" id="viewProjectModal">
    <div class="modal" style="max-width:600px;">
        <div class="modal-header">
            <h3><i class="fas fa-folder-open" style="color:#a78bfa;"></i> Project Details</h3>
            <button class="modal-close" onclick="closeModal('viewProjectModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div id="viewProjectContent"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('viewProjectModal')">Close</button>
        </div>
    </div>
</div>

<!-- Delete Project Modal -->
<div class="modal-overlay" id="deleteProjectModal">
    <div class="modal" style="max-width:450px;">
        <div class="modal-header">
            <h3><i class="fas fa-trash-alt" style="color:#f87171;"></i> Delete Project</h3>
            <button class="modal-close" onclick="closeModal('deleteProjectModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="project_id" id="delete_project_id">
            <div class="modal-body">
                <div style="text-align:center;padding:20px;">
                    <i class="fas fa-exclamation-triangle" style="font-size:3rem;color:#f87171;margin-bottom:15px;"></i>
                    <p style="color:#e2e8f0;margin-bottom:10px;">Are you sure you want to delete this project?</p>
                    <p style="color:#f87171;font-weight:600;" id="delete_project_title"></p>
                    <p style="color:#94a3b8;font-size:0.85rem;margin-top:15px;">This action cannot be undone.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('deleteProjectModal')">Cancel</button>
                <button type="submit" name="delete_project" class="btn btn-danger"><i class="fas fa-trash"></i> Delete Project</button>
            </div>
        </form>
    </div>
</div>

<script>
function filterProjectTable() {
    const search = document.getElementById('projectSearch').value.toLowerCase();
    const status = document.getElementById('filterProjectStatus').value;
    const type = document.getElementById('filterProjectType').value;
    const rows = document.querySelectorAll('#projectTable tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const rowStatus = row.dataset.status;
        const rowType = row.dataset.type;
        
        let show = true;
        if (search && !text.includes(search)) show = false;
        if (status !== 'all' && rowStatus !== status) show = false;
        if (type !== 'all' && rowType !== type) show = false;
        
        row.style.display = show ? '' : 'none';
    });
}

function editProject(id, title, type, status, category, description, supervisorId, maxStudents) {
    document.getElementById('edit_project_id').value = id;
    document.getElementById('edit_project_title').value = title;
    document.getElementById('edit_project_type').value = type;
    document.getElementById('edit_project_status').value = status;
    document.getElementById('edit_project_category').value = category;
    document.getElementById('edit_project_description').value = description;
    document.getElementById('edit_supervisor_id').value = supervisorId;
    document.getElementById('edit_max_students').value = maxStudents;
    openModal('editProjectModal');
}

function viewProject(id, title, description, type, status, supervisor) {
    document.getElementById('viewProjectContent').innerHTML = `
        <h4 style="color:#fff;margin-bottom:15px;">${title}</h4>
        <p style="color:#94a3b8;margin-bottom:20px;">${description || 'No description available.'}</p>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
            <div><strong style="color:#a78bfa;">Type:</strong><br>${type}</div>
            <div><strong style="color:#a78bfa;">Status:</strong><br>${status}</div>
            <div><strong style="color:#a78bfa;">Supervisor:</strong><br>${supervisor || 'Not assigned'}</div>
            <div><strong style="color:#a78bfa;">Project ID:</strong><br>${id}</div>
        </div>
    `;
    openModal('viewProjectModal');
}

function deleteProject(id, title) {
    document.getElementById('delete_project_id').value = id;
    document.getElementById('delete_project_title').textContent = title;
    openModal('deleteProjectModal');
}
</script>

<?php include("includes/footer.php"); ?>