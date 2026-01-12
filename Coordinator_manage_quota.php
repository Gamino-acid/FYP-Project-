<?php
// ====================================================
// Coordinator_manage_quota.php - Simplified Quota Management
// ====================================================
include("connect.php");
session_start();

$auth_user_id = $_GET['auth_user_id'] ?? $_SESSION['user_id'] ?? null;
if (!$auth_user_id) { header("location: login.php"); exit; }

// ====================================================
// 2. 处理表单提交 (POST)
// ====================================================

// A. 批量分配 (Bulk Apply) - 针对勾选的用户
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['bulk_apply'])) {
    $limit = intval($_POST['quota_limit']);
    $staff_ids = $_POST['selected_staff'] ?? [];

    if (empty($staff_ids)) {
        echo "<script>alert('Please select at least one staff member.');</script>";
    } else {
        $count = 0;
        
        // 预处理 SQL: 检查是否存在，存在则更新，不存在则插入
        $check = $conn->prepare("SELECT fyp_quotaid FROM quota WHERE fyp_staffid = ?");
        $insert = $conn->prepare("INSERT INTO quota (fyp_staffid, fyp_numofstudent, fyp_datecreated) VALUES (?, ?, NOW())");
        $update = $conn->prepare("UPDATE quota SET fyp_numofstudent = ? WHERE fyp_staffid = ?");

        foreach ($staff_ids as $sid) {
            // 1. 检查
            $check->bind_param("s", $sid);
            $check->execute();
            $res = $check->get_result();

            if ($res->num_rows > 0) {
                // 2. 存在 -> 更新
                $update->bind_param("is", $limit, $sid);
                $update->execute();
            } else {
                // 3. 不存在 -> 插入
                $insert->bind_param("si", $sid, $limit);
                $insert->execute();
            }
            $count++;
        }
        
        $check->close();
        $insert->close();
        $update->close();

        echo "<script>alert('Successfully assigned quota ($limit) to $count staff members.'); window.location.href='Coordinator_manage_quota.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
    }
}

// B. 单独修改 (Individual Manage) - 针对 Modal 提交
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_single'])) {
    $target_staff = $_POST['manage_staff_id'];
    $new_quota = intval($_POST['manage_quota_val']);

    // 检查是否已有记录
    $check_sql = "SELECT fyp_quotaid FROM quota WHERE fyp_staffid = '$target_staff'";
    $check_res = $conn->query($check_sql);
    
    if ($check_res->num_rows > 0) {
        // 更新
        $conn->query("UPDATE quota SET fyp_numofstudent = $new_quota WHERE fyp_staffid = '$target_staff'");
    } else {
        // 插入 (相当于单独给某人开通)
        $conn->query("INSERT INTO quota (fyp_staffid, fyp_numofstudent, fyp_datecreated) VALUES ('$target_staff', $new_quota, NOW())");
    }
    
    echo "<script>alert('Quota updated for Staff ID: $target_staff'); window.location.href='Coordinator_manage_quota.php?auth_user_id=" . urlencode($auth_user_id) . "';</script>";
}

// ====================================================
// 3. 获取列表 (Supervisor + Coordinator)
// ====================================================
// 直接 LEFT JOIN quota 表 (不再需要 Academic Year 过滤)
$staff_list = [];

$sql = "SELECT u.staffid, u.name, u.role, u.email, q.fyp_numofstudent 
        FROM (
            SELECT fyp_staffid as staffid, fyp_name as name, fyp_email as email, 'Supervisor' as role FROM supervisor
            UNION
            SELECT fyp_staffid as staffid, fyp_name as name, fyp_email as email, 'Coordinator' as role FROM coordinator
        ) u
        LEFT JOIN quota q ON u.staffid = q.fyp_staffid
        ORDER BY u.name ASC";

$res = $conn->query($sql);
if ($res) {
    while($row = $res->fetch_assoc()) {
        $staff_list[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Quota</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap');
        body { font-family: 'Poppins', sans-serif; background-color: #f4f7fc; padding: 20px; color: #333; }
        .container { max-width: 1100px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        
        /* Header */
        .header-area { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #eee; }
        h2 { margin: 0; font-size: 24px; color: #333; }

        /* Bulk Action Bar (黄色区域) */
        .bulk-bar { background: #fff8e1; padding: 20px; border-radius: 10px; margin-bottom: 25px; border: 1px solid #ffecb3; display: flex; align-items: flex-end; gap: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .form-input { padding: 10px 15px; border: 1px solid #ccc; border-radius: 6px; width: 200px; font-size: 14px; }
        .btn-bulk { background: #fbc02d; color: #333; border: none; padding: 11px 25px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 14px; transition: background 0.2s; }
        .btn-bulk:hover { background: #f9a825; }

        /* Table */
        .q-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .q-table th { background: #0056b3; color: white; padding: 15px; text-align: left; font-weight: 500; border-radius: 6px 6px 0 0; }
        .q-table td { padding: 15px; border-bottom: 1px solid #eee; vertical-align: middle; }
        .q-table tr:hover { background-color: #f9fbfd; }
        
        /* Role Badges */
        .role-badge { font-size: 11px; padding: 4px 10px; border-radius: 12px; font-weight: 600; letter-spacing: 0.5px; }
        .role-Supervisor { background: #e3effd; color: #0056b3; }
        .role-Coordinator { background: #e8f5e9; color: #2e7d32; }
        
        /* Manage Button (绿色) */
        .btn-manage { background: #28a745; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; font-size: 13px; font-weight: 500; display: inline-flex; align-items: center; gap: 6px; transition: background 0.2s; }
        .btn-manage:hover { background: #218838; }

        /* Modal */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999; justify-content: center; align-items: center; animation: fadeIn 0.2s; }
        .modal-box { background: white; padding: 30px; border-radius: 12px; width: 380px; text-align: center; box-shadow: 0 15px 40px rgba(0,0,0,0.2); transform: scale(0.9); animation: popIn 0.2s forwards; }
        .close-modal { float: right; cursor: pointer; font-size: 24px; color: #ccc; transition: color 0.2s; }
        .close-modal:hover { color: #333; }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes popIn { to { transform: scale(1); } }
    </style>
    <script>
        function toggleAll(source) {
            checkboxes = document.getElementsByName('selected_staff[]');
            for(var i=0, n=checkboxes.length;i<n;i++) checkboxes[i].checked = source.checked;
        }

        function openManageModal(staffId, staffName, currentQuota) {
            document.getElementById('modal_staff_id').value = staffId;
            document.getElementById('display_staff_name').innerText = staffName;
            document.getElementById('manage_quota_val').value = currentQuota;
            document.getElementById('manageModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('manageModal').style.display = 'none';
        }

        // 点击遮罩层关闭 Modal
        window.onclick = function(event) {
            if (event.target == document.getElementById('manageModal')) {
                closeModal();
            }
        }
    </script>
</head>
<body>

<div class="container">
    <div class="header-area">
        <h2><i class="fa fa-users-cog"></i> Manage Supervisor Quota</h2>
        <a href="coordinator_dashboard.php?auth_user_id=<?php echo $auth_user_id; ?>" style="color:#666; text-decoration:none; font-weight:500;"><i class="fa fa-arrow-left"></i> Back to Dashboard</a>
    </div>

    <form method="POST">
        
        <div class="bulk-bar">
            <div style="flex:1;">
                <label style="font-size:13px; font-weight:700; color:#b08500; display:block; margin-bottom:8px;">
                    <i class="fa fa-layer-group"></i> Bulk Assign (First Time Setup)
                </label>
                <input type="number" name="quota_limit" class="form-input" placeholder="Enter Quota (e.g. 5)" min="1">
                <div style="font-size:12px; color:#888; margin-top:5px;">Check boxes below to apply.</div>
            </div>
            <button type="submit" name="bulk_apply" class="btn-bulk">
                <i class="fa fa-check-circle"></i> Apply to Selected
            </button>
        </div>

        <table class="q-table">
            <thead>
                <tr>
                    <th style="width: 40px; text-align:center;"><input type="checkbox" onclick="toggleAll(this)" style="cursor:pointer; width:16px; height:16px;"></th>
                    <th>Staff Name & ID</th>
                    <th>Role</th>
                    <th>Current Quota</th>
                    <th style="text-align:right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($staff_list) > 0): ?>
                    <?php foreach ($staff_list as $s): ?>
                        <tr>
                            <td style="text-align:center;">
                                <input type="checkbox" name="selected_staff[]" value="<?php echo htmlspecialchars($s['staffid']); ?>" style="cursor:pointer; width:16px; height:16px;">
                            </td>
                            <td>
                                <strong style="color:#333; font-size:15px;"><?php echo htmlspecialchars($s['name']); ?></strong><br>
                                <small style="color:#999; font-family:monospace; font-size:13px;"><?php echo htmlspecialchars($s['staffid']); ?></small>
                            </td>
                            <td>
                                <span class="role-badge role-<?php echo $s['role']; ?>"><?php echo $s['role']; ?></span>
                            </td>
                            <td>
                                <?php if ($s['fyp_numofstudent'] !== null && $s['fyp_numofstudent'] > 0): ?>
                                    <span style="font-weight:bold; font-size:16px; color:#0056b3;">
                                        <?php echo $s['fyp_numofstudent']; ?>
                                    </span> 
                                    <span style="font-size:12px; color:#777;">Groups/Students</span>
                                <?php else: ?>
                                    <span style="color:#ccc; font-style:italic;">Not Set (0)</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right;">
                                <button type="button" class="btn-manage" 
                                        onclick="openManageModal('<?php echo $s['staffid']; ?>', '<?php echo addslashes($s['name']); ?>', '<?php echo $s['fyp_numofstudent'] ?? 0; ?>')">
                                    <i class="fa fa-sliders-h"></i> Manage
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" align="center" style="padding:40px; color:#999;">No staff found in the system.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </form>

</div>

<div id="manageModal" class="modal-overlay">
    <div class="modal-box">
        <span class="close-modal" onclick="closeModal()">&times;</span>
        <div style="margin-bottom:20px;">
            <i class="fa fa-user-edit" style="font-size:40px; color:#28a745;"></i>
        </div>
        <h3 style="margin-top:0; color:#333; margin-bottom:5px;">Manage Quota</h3>
        <p style="color:#666; margin-top:0;">Adjusting limit for: <br><strong id="display_staff_name" style="font-size:16px; color:#0056b3;"></strong></p>
        
        <form method="POST">
            <input type="hidden" name="manage_staff_id" id="modal_staff_id">
            
            <div style="margin:25px 0;">
                <label style="display:block; margin-bottom:8px; font-weight:600; color:#555;">New Quota Limit</label>
                <input type="number" name="manage_quota_val" id="manage_quota_val" class="form-input" style="width:120px; text-align:center; font-size:20px; font-weight:bold; padding:10px;" min="0" required>
            </div>
            
            <button type="submit" name="update_single" class="btn-manage" style="width:100%; justify-content:center; padding:12px; font-size:15px; border-radius:8px;">
                Confirm Update
            </button>
        </form>
    </div>
</div>

</body>
</html>