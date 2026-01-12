<?php
/**
 * Coordinator Main Page - Part 5
 * Page Content: Moderation, Rubrics, Marks, Reports, Announcements, Settings
 */

// ==================== STUDENT MODERATION ====================
if ($current_page === 'moderation'): 
    $moderations = [];
    $res = $conn->query("SELECT sm.*, s.fyp_studname, s.fyp_studfullid, mc.fyp_criterianame, mc.fyp_criteriadesc 
                         FROM student_moderation sm 
                         LEFT JOIN student s ON sm.fyp_studid = s.fyp_studid 
                         LEFT JOIN moderation_criteria mc ON sm.fyp_mdcriteriaid = mc.fyp_mdcriteriaid 
                         ORDER BY sm.fyp_studid");
    if ($res) { while ($row = $res->fetch_assoc()) { $moderations[] = $row; } }
?>
    <div class="card"><div class="card-header"><h3>Student Moderation Records</h3></div><div class="card-body">
        <?php if (empty($moderations)): ?><div class="empty-state"><i class="fas fa-clipboard-check"></i><p>No moderation records found</p></div>
        <?php else: ?>
            <table class="data-table"><thead><tr><th>Student ID</th><th>Student Name</th><th>Criteria</th><th>Description</th><th>Comply</th></tr></thead><tbody>
                <?php foreach ($moderations as $m): ?>
                <tr>
                    <td><?= htmlspecialchars($m['fyp_studfullid'] ?? $m['fyp_studid']); ?></td>
                    <td><?= htmlspecialchars($m['fyp_studname'] ?? '-'); ?></td>
                    <td><?= htmlspecialchars($m['fyp_criterianame'] ?? '-'); ?></td>
                    <td><?= htmlspecialchars($m['fyp_criteriadesc'] ?? '-'); ?></td>
                    <td><?= $m['fyp_comply'] ? '<span class="badge badge-approved">Yes</span>' : '<span class="badge badge-pending">No</span>'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody></table>
        <?php endif; ?>
    </div></div>

<?php // ==================== RUBRICS ASSESSMENT ====================
elseif ($current_page === 'rubrics'): 
    $rubrics_action = $_GET['action'] ?? 'sets';
    
    $sets = [];
    $res = $conn->query("SELECT s.*, a.fyp_acdyear, a.fyp_intake FROM `set` s LEFT JOIN academic_year a ON s.fyp_academicid = a.fyp_academicid ORDER BY s.fyp_setid DESC");
    if ($res) { while ($row = $res->fetch_assoc()) { $sets[] = $row; } }
    
    $items = [];
    $res = $conn->query("SELECT * FROM item ORDER BY fyp_itemid");
    if ($res) { while ($row = $res->fetch_assoc()) { $items[] = $row; } }
    
    $criteria = [];
    $res = $conn->query("SELECT * FROM assessment_criteria ORDER BY fyp_min ASC");
    if ($res) { while ($row = $res->fetch_assoc()) { $criteria[] = $row; } }
    
    $marking_criteria = [];
    $res = $conn->query("SELECT * FROM marking_criteria ORDER BY fyp_criteriaid");
    if ($res) { while ($row = $res->fetch_assoc()) { $marking_criteria[] = $row; } }
    
    $item_marking_links = [];
    $res = $conn->query("SELECT imc.*, i.fyp_itemname, mc.fyp_criterianame, mc.fyp_percentallocation 
                         FROM item_marking_criteria imc 
                         LEFT JOIN item i ON imc.fyp_itemid = i.fyp_itemid 
                         LEFT JOIN marking_criteria mc ON imc.fyp_criteriaid = mc.fyp_criteriaid 
                         ORDER BY imc.fyp_itemid, imc.fyp_criteriaid");
    if ($res) { while ($row = $res->fetch_assoc()) { $item_marking_links[] = $row; } }
?>
    <div class="card" style="margin-bottom:20px;">
        <div class="card-body" style="padding:15px;">
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                <a href="?page=rubrics&action=sets" class="btn <?= $rubrics_action === 'sets' ? 'btn-primary' : 'btn-secondary'; ?>"><i class="fas fa-layer-group"></i> Assessment Sets</a>
                <a href="?page=rubrics&action=items" class="btn <?= $rubrics_action === 'items' ? 'btn-primary' : 'btn-secondary'; ?>"><i class="fas fa-list-ol"></i> Assessment Items</a>
                <a href="?page=rubrics&action=criteria" class="btn <?= $rubrics_action === 'criteria' ? 'btn-primary' : 'btn-secondary'; ?>"><i class="fas fa-star"></i> Assessment Criteria</a>
                <a href="?page=rubrics&action=marking" class="btn <?= $rubrics_action === 'marking' ? 'btn-primary' : 'btn-secondary'; ?>"><i class="fas fa-percent"></i> Marking Criteria</a>
                <a href="?page=rubrics&action=link" class="btn <?= $rubrics_action === 'link' ? 'btn-warning' : 'btn-secondary'; ?>"><i class="fas fa-link"></i> Link Items & Criteria</a>
            </div>
        </div>
    </div>
    
    <?php if ($rubrics_action === 'sets'): ?>
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-layer-group" style="color:#a78bfa;"></i> Assessment Sets</h3><button class="btn btn-primary" onclick="openModal('createSetModal')"><i class="fas fa-plus"></i> Create Set</button></div>
        <div class="card-body">
            <?php if (empty($sets)): ?>
                <div class="empty-state"><i class="fas fa-layer-group"></i><p>No assessment sets found</p></div>
            <?php else: ?>
                <table class="data-table"><thead><tr><th>ID</th><th>Set Name</th><th>Academic Year</th><th>Intake</th><th>Actions</th></tr></thead><tbody>
                    <?php foreach ($sets as $s): ?>
                    <tr>
                        <td><?= $s['fyp_setid']; ?></td>
                        <td><strong style="color:#a78bfa;"><i class="fas fa-graduation-cap"></i> FYP <?= htmlspecialchars($s['fyp_acdyear'] ?? ''); ?> - <?= htmlspecialchars($s['fyp_intake'] ?? ''); ?></strong></td>
                        <td><?= htmlspecialchars($s['fyp_acdyear'] ?? '-'); ?></td>
                        <td><span class="badge badge-approved"><?= htmlspecialchars($s['fyp_intake'] ?? '-'); ?></span></td>
                        <td>
                            <button class="btn btn-info btn-sm" onclick="editSet(<?= $s['fyp_setid']; ?>, <?= $s['fyp_academicid'] ?? 0; ?>)"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-danger btn-sm" onclick="deleteSet(<?= $s['fyp_setid']; ?>)"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody></table>
            <?php endif; ?>
        </div>
    </div>
    
    <?php elseif ($rubrics_action === 'items'): ?>
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-list-ol" style="color:#fb923c;"></i> Assessment Items</h3><button class="btn btn-primary" onclick="openModal('createItemModal')"><i class="fas fa-plus"></i> Add New Item</button></div>
        <div class="card-body">
            <?php if (empty($items)): ?>
                <div class="empty-state"><i class="fas fa-list-ol"></i><p>No assessment items found</p></div>
            <?php else: ?>
                <table class="data-table"><thead><tr><th>ID</th><th>Item Name</th><th>Doc?</th><th>Mark %</th><th>Start Date</th><th>Deadline</th><th>Moderation</th><th>Actions</th></tr></thead><tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= $item['fyp_itemid']; ?></td>
                        <td><strong><?= htmlspecialchars($item['fyp_itemname']); ?></strong></td>
                        <td><?php if ($item['fyp_isdocument'] ?? 1): ?><span style="color:#34d399;"><i class="fas fa-check-circle"></i></span><?php else: ?><span style="color:#f87171;"><i class="fas fa-times-circle"></i></span><?php endif; ?></td>
                        <td><strong><?= number_format($item['fyp_originalmarkallocation'] ?? 0, 1); ?>%</strong></td>
                        <td><?= $item['fyp_startdate'] ? date('d/m/Y', strtotime($item['fyp_startdate'])) : '-'; ?></td>
                        <td><?= $item['fyp_finaldeadline'] ? date('d/m/Y', strtotime($item['fyp_finaldeadline'])) : '-'; ?></td>
                        <td><?php if ($item['fyp_ismoderation'] ?? 0): ?><span style="color:#34d399;"><i class="fas fa-check-circle"></i></span><?php else: ?><span style="color:#f87171;"><i class="fas fa-times-circle"></i></span><?php endif; ?></td>
                        <td>
                            <button class="btn btn-info btn-sm" onclick="editItem(<?= htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8'); ?>)"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-danger btn-sm" onclick="deleteItem(<?= $item['fyp_itemid']; ?>)"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody></table>
            <?php endif; ?>
        </div>
    </div>
    
    <?php elseif ($rubrics_action === 'criteria'): ?>
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-star" style="color:#fbbf24;"></i> Assessment Criteria</h3><button class="btn btn-primary" onclick="openModal('createCriteriaModal')"><i class="fas fa-plus"></i> Add Criteria</button></div>
        <div class="card-body">
            <?php if (empty($criteria)): ?>
                <div class="empty-state"><i class="fas fa-star"></i><p>No assessment criteria found</p></div>
            <?php else: ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(220px, 1fr));gap:20px;">
                    <?php foreach ($criteria as $c): ?>
                    <div style="background:rgba(251,191,36,0.1);padding:20px;border-radius:12px;border:1px solid rgba(251,191,36,0.2);">
                        <h4 style="color:#fbbf24;margin:0 0 10px 0;"><?= htmlspecialchars($c['fyp_assessmentcriterianame']); ?></h4>
                        <div style="background:rgba(0,0,0,0.2);padding:10px;border-radius:8px;margin-bottom:15px;">
                            <span style="color:#e2e8f0;font-size:1.2rem;font-weight:600;"><?= $c['fyp_min']; ?> - <?= $c['fyp_max']; ?></span><span style="color:#94a3b8;font-size:0.85rem;"> marks</span>
                        </div>
                        <p style="color:#94a3b8;font-size:0.85rem;margin-bottom:15px;"><?= htmlspecialchars($c['fyp_description'] ?? 'No description'); ?></p>
                        <div style="display:flex;gap:8px;">
                            <button class="btn btn-info btn-sm" onclick="editCriteria(<?= $c['fyp_assessmentcriteriaid']; ?>, '<?= htmlspecialchars($c['fyp_assessmentcriterianame'], ENT_QUOTES); ?>', <?= $c['fyp_min']; ?>, <?= $c['fyp_max']; ?>, '<?= htmlspecialchars($c['fyp_description'] ?? '', ENT_QUOTES); ?>')"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-danger btn-sm" onclick="deleteCriteria(<?= $c['fyp_assessmentcriteriaid']; ?>)"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php elseif ($rubrics_action === 'marking'): ?>
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-percent" style="color:#60a5fa;"></i> Marking Criteria</h3><button class="btn btn-primary" onclick="openModal('createMarkingModal')"><i class="fas fa-plus"></i> Add Marking Criteria</button></div>
        <div class="card-body">
            <?php if (empty($marking_criteria)): ?>
                <div class="empty-state"><i class="fas fa-percent"></i><p>No marking criteria found</p></div>
            <?php else: ?>
                <table class="data-table"><thead><tr><th>ID</th><th>Criteria Name</th><th>% Allocation</th><th>Actions</th></tr></thead><tbody>
                    <?php foreach ($marking_criteria as $mc): ?>
                    <tr>
                        <td><?= $mc['fyp_criteriaid']; ?></td>
                        <td><strong><?= htmlspecialchars($mc['fyp_criterianame']); ?></strong></td>
                        <td><span class="badge badge-approved"><?= number_format($mc['fyp_percentallocation'], 1); ?>%</span></td>
                        <td>
                            <button class="btn btn-info btn-sm" onclick="editMarking(<?= $mc['fyp_criteriaid']; ?>, '<?= htmlspecialchars($mc['fyp_criterianame'], ENT_QUOTES); ?>', <?= $mc['fyp_percentallocation']; ?>)"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-danger btn-sm" onclick="deleteMarking(<?= $mc['fyp_criteriaid']; ?>)"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody></table>
            <?php endif; ?>
        </div>
    </div>
    
    <?php elseif ($rubrics_action === 'link'): ?>
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-link" style="color:#fbbf24;"></i> Link Items & Marking Criteria</h3><button class="btn btn-primary" onclick="openModal('linkItemCriteriaModal')"><i class="fas fa-plus"></i> Create Link</button></div>
        <div class="card-body">
            <?php if (empty($items)): ?>
                <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> No assessment items found. Please <a href="?page=rubrics&action=items" style="color:#fbbf24;">create items first</a>.</div>
            <?php elseif (empty($marking_criteria)): ?>
                <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> No marking criteria found. Please <a href="?page=rubrics&action=marking" style="color:#fbbf24;">create marking criteria first</a>.</div>
            <?php else: 
                $links_by_item = [];
                foreach ($items as $item) { $links_by_item[$item['fyp_itemid']] = ['item' => $item, 'criteria' => []]; }
                foreach ($item_marking_links as $link) { if (isset($links_by_item[$link['fyp_itemid']])) { $links_by_item[$link['fyp_itemid']]['criteria'][] = $link; } }
            ?>
                <div style="display:grid;gap:20px;">
                    <?php foreach ($links_by_item as $item_id => $data): ?>
                    <div style="background:rgba(139,92,246,0.05);border:1px solid rgba(139,92,246,0.1);border-radius:12px;overflow:hidden;">
                        <div style="background:rgba(139,92,246,0.1);padding:15px 20px;display:flex;justify-content:space-between;align-items:center;">
                            <h4 style="color:#a78bfa;margin:0;"><i class="fas fa-file-alt"></i> <?= htmlspecialchars($data['item']['fyp_itemname']); ?> <span style="font-weight:normal;color:#94a3b8;font-size:0.85rem;">(<?= number_format($data['item']['fyp_originalmarkallocation'] ?? 0, 1); ?>%)</span></h4>
                            <span class="badge" style="background:rgba(59,130,246,0.2);color:#60a5fa;"><?= count($data['criteria']); ?> criteria linked</span>
                        </div>
                        <div style="padding:15px 20px;">
                            <?php if (empty($data['criteria'])): ?>
                            <p style="color:#fb923c;margin:0;"><i class="fas fa-exclamation-circle"></i> No criteria linked yet</p>
                            <?php else: ?>
                            <table class="data-table" style="margin:0;"><thead><tr><th>Criteria Name</th><th>Percent Allocation</th><th style="width:80px;">Action</th></tr></thead><tbody>
                                <?php foreach ($data['criteria'] as $c): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($c['fyp_criterianame']); ?></strong></td>
                                    <td><span style="color:#a78bfa;"><?= number_format($c['fyp_percentallocation'], 1); ?>%</span></td>
                                    <td>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this link?');">
                                            <input type="hidden" name="unlink_itemid" value="<?= $c['fyp_itemid']; ?>">
                                            <input type="hidden" name="unlink_criteriaid" value="<?= $c['fyp_criteriaid']; ?>">
                                            <button type="submit" name="unlink_item_criteria" class="btn btn-danger btn-sm"><i class="fas fa-unlink"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody></table>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

<?php // ==================== ASSESSMENT MARKS ====================
elseif ($current_page === 'marks'): 
    $marks_action = $_GET['action'] ?? 'list';
    $filter_search = $_GET['search'] ?? '';
    
    $students_marks = [];
    $res = $conn->query("SELECT s.fyp_studid, s.fyp_studname, s.fyp_studfullid, s.fyp_email, p.fyp_projectid, p.fyp_projecttitle, pa.fyp_pairingid, sup.fyp_name as supervisor_name, tm.fyp_totalmark, tm.fyp_totalfinalsupervisor, tm.fyp_totalfinalmoderator
                         FROM student s
                         LEFT JOIN pairing pa ON s.fyp_studid = pa.fyp_studid
                         LEFT JOIN project p ON pa.fyp_projectid = p.fyp_projectid
                         LEFT JOIN supervisor sup ON pa.fyp_supervisorid = sup.fyp_supervisorid
                         LEFT JOIN total_mark tm ON s.fyp_studid = tm.fyp_studid
                         ORDER BY s.fyp_studname");
    if ($res) { while ($row = $res->fetch_assoc()) { $students_marks[] = $row; } }
    
    $students_json = json_encode(array_map(function($s) {
        return ['id' => $s['fyp_studid'], 'name' => $s['fyp_studname'], 'project' => $s['fyp_projecttitle'] ?? '', 'total_mark' => $s['fyp_totalmark'] ?? null];
    }, $students_marks));
?>
    <div class="card" style="margin-bottom:20px;background:linear-gradient(135deg, rgba(139,92,246,0.15), rgba(59,130,246,0.1));">
        <div class="card-body" style="padding:25px;">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px;">
                <h2 style="margin:0;color:#e2e8f0;"><i class="fas fa-clipboard-check" style="color:#8b5cf6;margin-right:10px;"></i>Students Marks</h2>
                <div style="display:flex;gap:10px;">
                    <a href="?page=rubrics" class="btn btn-secondary btn-sm"><i class="fas fa-list-check"></i> Rubrics</a>
                    <a href="Coordinator_report.php" class="btn btn-success btn-sm"><i class="fas fa-file-export"></i> Reports</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-list-alt" style="color:#60a5fa;"></i> Students Marks</h3></div>
        <div class="card-body">
            <div style="margin-bottom:20px;">
                <input type="text" id="marksSearchInput" class="form-control" placeholder="Search by Student ID or Name..." onkeyup="liveSearchStudents(this.value)" value="<?= htmlspecialchars($filter_search); ?>">
            </div>
            
            <?php if (empty($students_marks)): ?>
            <div class="empty-state"><i class="fas fa-list-alt"></i><p>No students found</p></div>
            <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="data-table" id="marksTable">
                    <thead><tr><th>Student ID</th><th>Student Name</th><th>Project Title</th><th>Total Mark</th><th>Supervisor Mark</th><th>Moderator Mark</th><th>Actions</th></tr></thead>
                    <tbody id="marksTableBody">
                        <?php foreach ($students_marks as $sm): ?>
                        <tr class="student-row" data-id="<?= htmlspecialchars(strtolower($sm['fyp_studid'])); ?>" data-name="<?= htmlspecialchars(strtolower($sm['fyp_studname'])); ?>">
                            <td><strong style="color:#60a5fa;"><?= htmlspecialchars($sm['fyp_studid']); ?></strong></td>
                            <td><?= htmlspecialchars($sm['fyp_studname']); ?></td>
                            <td style="max-width:250px;"><?= htmlspecialchars($sm['fyp_projecttitle'] ?? '-'); ?></td>
                            <td><?php if ($sm['fyp_totalmark']): ?><span style="color:<?= $sm['fyp_totalmark'] >= 50 ? '#34d399' : '#f87171'; ?>;font-weight:600;"><?= number_format($sm['fyp_totalmark'], 2); ?></span><?php else: ?><span style="color:#94a3b8;">-</span><?php endif; ?></td>
                            <td><?= $sm['fyp_totalfinalsupervisor'] ? number_format($sm['fyp_totalfinalsupervisor'], 2) : '-'; ?></td>
                            <td><?= $sm['fyp_totalfinalmoderator'] ? number_format($sm['fyp_totalfinalmoderator'], 2) : '-'; ?></td>
                            <td>
                                <button class="btn btn-success btn-sm" onclick="openAddMarkModal('<?= $sm['fyp_studid']; ?>', '<?= htmlspecialchars($sm['fyp_studname'], ENT_QUOTES); ?>')"><i class="fas fa-edit"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <script>var allStudentsMarks = <?= $students_json; ?>;</script>

<?php // ==================== REPORTS ====================
elseif ($current_page === 'reports'): ?>
    <div class="card"><div class="card-header"><h3>Reports Generation</h3><a href="Coordinator_report.php" class="btn btn-primary"><i class="fas fa-file-export"></i> Open Export Center</a></div><div class="card-body">
        <p style="color:#94a3b8;margin-bottom:20px;">Generate and export various FYP reports. Click on any report to download.</p>
        <div class="quick-actions">
            <a href="Coordinator_report.php?export=excel&report=students" class="quick-action" style="border-left:4px solid #10B981;"><i class="fas fa-user-graduate" style="color:#10B981;"></i><h4>Student List</h4><p>Download Excel</p></a>
            <a href="Coordinator_report.php?export=excel&report=marks" class="quick-action" style="border-left:4px solid #10B981;"><i class="fas fa-chart-line" style="color:#10B981;"></i><h4>Assessment Marks</h4><p>Download Excel</p></a>
            <a href="Coordinator_report.php?export=excel&report=workload" class="quick-action" style="border-left:4px solid #10B981;"><i class="fas fa-briefcase" style="color:#10B981;"></i><h4>Supervisor Workload</h4><p>Download Excel</p></a>
            <a href="Coordinator_report.php?export=excel&report=pairing" class="quick-action" style="border-left:4px solid #10B981;"><i class="fas fa-link" style="color:#10B981;"></i><h4>Pairing List</h4><p>Download Excel</p></a>
        </div>
    </div></div>

<?php // ==================== ANNOUNCEMENTS ====================
elseif ($current_page === 'announcements'): 
    $announcements = [];
    $res = $conn->query("SELECT * FROM announcement ORDER BY fyp_datecreated DESC LIMIT 50");
    if ($res) { while ($row = $res->fetch_assoc()) { $announcements[] = $row; } }
    
    $ann_pk_column = 'fyp_announcementid';
    if (!empty($announcements)) {
        $first = $announcements[0];
        if (isset($first['fyp_announcementid'])) $ann_pk_column = 'fyp_announcementid';
        elseif (isset($first['announcementid'])) $ann_pk_column = 'announcementid';
        elseif (isset($first['id'])) $ann_pk_column = 'id';
    }
?>
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-bullhorn" style="color:#a78bfa;"></i> Announcements</h3><button class="btn btn-primary" onclick="openModal('createAnnouncementModal')"><i class="fas fa-plus"></i> New Announcement</button></div>
        <div class="card-body">
            <?php if (empty($announcements)): ?>
            <div class="empty-state"><i class="fas fa-bullhorn"></i><p>No announcements yet</p></div>
            <?php else: ?>
            <table class="data-table"><thead><tr><th>Subject</th><th>Description</th><th>From</th><th>Receiver</th><th>Date</th><th>Actions</th></tr></thead><tbody>
                <?php foreach ($announcements as $a): 
                    $ann_id = $a[$ann_pk_column] ?? 0;
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($a['fyp_subject']); ?></strong></td>
                    <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($a['fyp_description']); ?></td>
                    <td><?= htmlspecialchars($a['fyp_supervisorid']); ?></td>
                    <td><span class="badge" style="background:rgba(139,92,246,0.2);color:#a78bfa;"><?= htmlspecialchars($a['fyp_receiver']); ?></span></td>
                    <td style="white-space:nowrap;"><?= date('M j, Y H:i', strtotime($a['fyp_datecreated'])); ?></td>
                    <td><button class="btn btn-danger btn-sm" onclick="confirmUnsend(<?= $ann_id; ?>, '<?= htmlspecialchars($a['fyp_subject'], ENT_QUOTES); ?>')"><i class="fas fa-trash"></i></button></td>
                </tr>
                <?php endforeach; ?>
            </tbody></table>
            <?php endif; ?>
        </div>
    </div>

<?php // ==================== SETTINGS ====================
elseif ($current_page === 'settings'): 
    $maintenance = [];
    $res = $conn->query("SELECT * FROM fyp_maintenance ORDER BY fyp_category, fyp_subject");
    if ($res) { while ($row = $res->fetch_assoc()) { $maintenance[] = $row; } }
?>
    <div class="card"><div class="card-header"><h3>System Settings</h3></div><div class="card-body">
        <div class="quick-actions">
            <div class="quick-action"><i class="fas fa-calendar-alt"></i><h4>Academic Years</h4><p><?= count($academic_years); ?> years</p></div>
            <div class="quick-action"><i class="fas fa-graduation-cap"></i><h4>Programmes</h4><p><?= count($programmes); ?> programmes</p></div>
            <div class="quick-action"><i class="fas fa-sliders-h"></i><h4>Maintenance</h4><p><?= count($maintenance); ?> settings</p></div>
        </div>
    </div></div>
    
    <div class="card"><div class="card-header"><h3>Academic Years</h3></div><div class="card-body">
        <table class="data-table"><thead><tr><th>ID</th><th>Year</th><th>Intake</th><th>Created</th></tr></thead><tbody>
            <?php foreach ($academic_years as $ay): ?>
            <tr><td><?= $ay['fyp_academicid']; ?></td><td><?= htmlspecialchars($ay['fyp_acdyear']); ?></td><td><?= htmlspecialchars($ay['fyp_intake']); ?></td><td><?= $ay['fyp_datecreated'] ? date('M j, Y', strtotime($ay['fyp_datecreated'])) : '-'; ?></td></tr>
            <?php endforeach; ?>
        </tbody></table>
    </div></div>
    
    <div class="card"><div class="card-header"><h3>Programmes</h3></div><div class="card-body">
        <table class="data-table"><thead><tr><th>ID</th><th>Code</th><th>Full Name</th></tr></thead><tbody>
            <?php foreach ($programmes as $p): ?>
            <tr><td><?= $p['fyp_progid']; ?></td><td><?= htmlspecialchars($p['fyp_progname']); ?></td><td><?= htmlspecialchars($p['fyp_prognamefull']); ?></td></tr>
            <?php endforeach; ?>
        </tbody></table>
    </div></div>

<?php endif; ?>

<?php
// Include Part 6
include("Coordinator_scripts.php");