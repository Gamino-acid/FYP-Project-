<?php
// ====================================================
// Coordinator_manage_users.php - 最终全功能优化版
// ====================================================
include("connect.php");

// 开启严格报错模式，方便调试
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// 1. 验证用户登录
$auth_user_id = $_GET['auth_user_id'] ?? null;
$tab = $_GET['tab'] ?? 'student'; 
if (!$auth_user_id) { header("location: login.php"); exit; }

// 2. 获取 Coordinator 信息
$user_name = "Coordinator"; $user_avatar = "image/user.png";
if (isset($conn)) {
    $stmt = $conn->prepare("SELECT * FROM coordinator WHERE fyp_userid = ?");
    $stmt->bind_param("i", $auth_user_id); $stmt->execute(); 
    $res=$stmt->get_result();
    if($row=$res->fetch_assoc()) { 
        if(!empty($row['fyp_name'])) $user_name=$row['fyp_name']; 
        if(!empty($row['fyp_profileimg'])) $user_avatar=$row['fyp_profileimg']; 
    }
}

// 辅助函数：生成随机密码
function generateRandomPassword($length = 8) {
    return substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, $length);
}

// ----------------------------------------------------
// 3. 准备下拉菜单数据 (Academic Year & Programme)
// ----------------------------------------------------
$academic_options = [];
$res_acd = $conn->query("SELECT * FROM academic_year ORDER BY fyp_acdyear DESC");
if($res_acd) while($r = $res_acd->fetch_assoc()) $academic_options[] = $r;

$programme_options = [];
$res_prog = $conn->query("SELECT * FROM programme ORDER BY fyp_progname ASC");
if($res_prog) while($r = $res_prog->fetch_assoc()) $programme_options[] = $r;


// ----------------------------------------------------
// A. 手动添加学生 (Student)
// ----------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_single_student'])) {
    try {
        $name = $_POST['full_name']; $matrix = $_POST['matrix_no'];
        $email = $_POST['email']; $contact = $_POST['contact'];
        $acd_id = $_POST['academic_id']; $prog_id = $_POST['prog_id'];
        
        // 逻辑：Username = Email
        $gen_username = $email; 
        $gen_password = generateRandomPassword(); 
        
        // 查重
        $chk = $conn->query("SELECT fyp_userid FROM `user` WHERE fyp_username = '$gen_username'");
        if ($chk->num_rows > 0) { echo "<script>alert('Error: Email already exists!'); history.back();</script>"; exit; }

        // 1. Insert User
        $conn->query("INSERT INTO `user` (fyp_username, fyp_passwordhash, fyp_usertype, fyp_datecreated) VALUES ('$gen_username', '$gen_password', 'student', NOW())");
        $uid = $conn->insert_id;

        // 2. Insert Student
        $stmt = $conn->prepare("INSERT INTO student (fyp_userid, fyp_studname, fyp_studid, fyp_email, fyp_contactno, fyp_group, fyp_academicid, fyp_progid) VALUES (?, ?, ?, ?, ?, 'Individual', ?, ?)");
        $stmt->bind_param("issssii", $uid, $name, $matrix, $email, $contact, $acd_id, $prog_id);
        $stmt->execute();

        echo "<script>alert('Student added successfully!\\nUsername: $gen_username\\nPassword: $gen_password'); window.location.href='?auth_user_id=$auth_user_id&tab=student';</script>";
    } catch (Exception $e) { echo "<script>alert('Error: " . addslashes($e->getMessage()) . "');</script>"; }
}

// ----------------------------------------------------
// B. 批量导入学生 (Student CSV)
// ----------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['import_students'])) {
    if (is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
        $file = fopen($_FILES['csv_file']['tmp_name'], "r"); fgetcsv($file); // Skip Header
        $count = 0;
        while (($data = fgetcsv($file, 1000, ",")) !== FALSE) {
            $i_name = $data[0]??''; $i_matrix = $data[1]??''; $i_email = $data[2]??''; $i_contact = $data[3]??''; $i_prog = $data[4]??1; $i_acd = $data[5]??1;
            if ($i_name && $i_email) {
                $chk = $conn->query("SELECT fyp_userid FROM `user` WHERE fyp_username = '$i_email'");
                if ($chk->num_rows == 0) {
                    $pass = generateRandomPassword();
                    $conn->query("INSERT INTO `user` (fyp_username, fyp_passwordhash, fyp_usertype, fyp_datecreated) VALUES ('$i_email', '$pass', 'student', NOW())");
                    $uid = $conn->insert_id;
                    $i_name = $conn->real_escape_string($i_name);
                    $conn->query("INSERT INTO student (fyp_userid, fyp_studname, fyp_studid, fyp_email, fyp_contactno, fyp_group, fyp_academicid, fyp_progid) VALUES ('$uid', '$i_name', '$i_matrix', '$i_email', '$i_contact', 'Individual', '$i_acd', '$i_prog')");
                    $count++;
                }
            }
        }
        echo "<script>alert('Imported $count students!'); window.location.href='?auth_user_id=$auth_user_id&tab=student';</script>";
    }
}

// ----------------------------------------------------
// C. 手动添加 Supervisor (优化：下拉菜单 + 全字段)
// ----------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_supervisor'])) {
    try {
        // 基本字段
        $uname = $_POST['username']; 
        $pass = $_POST['password'];
        $name = $_POST['full_name']; 
        $sid = $_POST['staff_id']; // 工号
        $email = $_POST['email']; 
        $contact = $_POST['contact'];
        
        // 详细字段
        $room = $_POST['room_no'];
        $prog = $_POST['programme']; // 下拉菜单选中的值 (例如 "Software Engineering")
        $spec = $_POST['specialization'];
        $interest = $_POST['area_of_interest'];
        $ismod = $_POST['is_moderator']; // 0 或 1

        $chk = $conn->query("SELECT fyp_userid FROM `user` WHERE fyp_username = '$uname'");
        if ($chk->num_rows > 0) { echo "<script>alert('Username exists!');</script>"; } 
        else {
            // 1. Insert User
            $stmt = $conn->prepare("INSERT INTO `user` (fyp_username, fyp_passwordhash, fyp_usertype, fyp_datecreated) VALUES (?, ?, 'lecturer', NOW())");
            $stmt->bind_param("ss", $uname, $pass); $stmt->execute();
            $nid = $conn->insert_id; 

            // 2. Insert Supervisor (注意：ID 自动生成，工号存入 fyp_staffid)
            $sql_sup = "INSERT INTO supervisor (fyp_userid, fyp_name, fyp_staffid, fyp_email, fyp_contactno, fyp_roomno, fyp_programme, fyp_specialization, fyp_areaofinterest, fyp_ismoderator) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt2 = $conn->prepare($sql_sup);
            // i=int, s=string. 共10个参数
            $stmt2->bind_param("issssssssi", $nid, $name, $sid, $email, $contact, $room, $prog, $spec, $interest, $ismod);
            $stmt2->execute();
            
            $new_sup_id = $conn->insert_id; 

            // 3. Insert Quota
            $conn->query("INSERT INTO quota (fyp_supervisorid, fyp_numofstudent) VALUES ('$new_sup_id', 3)");
            
            echo "<script>alert('Supervisor added successfully!'); window.location.href='?auth_user_id=$auth_user_id&tab=supervisor';</script>";
        }
    } catch (Exception $e) { echo "<script>alert('Error: " . addslashes($e->getMessage()) . "');</script>"; }
}

// ----------------------------------------------------
// D. 批量导入 Supervisor (优化：9列完整数据)
// ----------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['import_supervisors'])) {
    if (is_uploaded_file($_FILES['csv_file_sup']['tmp_name'])) {
        $file = fopen($_FILES['csv_file_sup']['tmp_name'], "r"); fgetcsv($file); // Skip Header
        $count = 0;
        while (($data = fgetcsv($file, 1000, ",")) !== FALSE) {
            // CSV: Name, StaffID, Email, Contact, Room, Prog, Spec, Interest, IsMod
            $i_name = $data[0]??''; $i_staffid = $data[1]??''; $i_email = $data[2]??''; $i_contact = $data[3]??'';
            $i_room = $data[4]??''; $i_prog = $data[5]??''; $i_spec = $data[6]??''; $i_interest = $data[7]??''; $i_mod = $data[8]??0;

            if ($i_name && $i_staffid && $i_email) {
                $check = $conn->query("SELECT fyp_userid FROM `user` WHERE fyp_username = '$i_email'");
                if ($check->num_rows == 0) {
                    $pass = generateRandomPassword();
                    // 1. User
                    $conn->query("INSERT INTO `user` (fyp_username, fyp_passwordhash, fyp_usertype, fyp_datecreated) VALUES ('$i_email', '$pass', 'lecturer', NOW())");
                    $uid = $conn->insert_id;
                    
                    // Escape Strings
                    $i_name = $conn->real_escape_string($i_name);
                    $i_room = $conn->real_escape_string($i_room);
                    $i_prog = $conn->real_escape_string($i_prog);
                    $i_spec = $conn->real_escape_string($i_spec);
                    $i_interest = $conn->real_escape_string($i_interest);
                    $i_mod = (int)$i_mod;

                    // 2. Supervisor
                    $sql_imp = "INSERT INTO supervisor (fyp_userid, fyp_name, fyp_staffid, fyp_email, fyp_contactno, fyp_roomno, fyp_programme, fyp_specialization, fyp_areaofinterest, fyp_ismoderator) 
                                VALUES ('$uid', '$i_name', '$i_staffid', '$i_email', '$i_contact', '$i_room', '$i_prog', '$i_spec', '$i_interest', '$i_mod')";
                    $conn->query($sql_imp);
                    $sup_id = $conn->insert_id;

                    // 3. Quota
                    $conn->query("INSERT INTO quota (fyp_supervisorid, fyp_numofstudent) VALUES ('$sup_id', 3)");
                    $count++;
                }
            }
        }
        echo "<script>alert('Imported $count supervisors!'); window.location.href='?auth_user_id=$auth_user_id&tab=supervisor';</script>";
    }
}

// ----------------------------------------------------
// 获取列表数据
// ----------------------------------------------------
$data_list = [];
if ($tab == 'student') {
    $res = $conn->query("SELECT s.*, u.fyp_username, p.fyp_progname, a.fyp_acdyear, a.fyp_intake FROM student s JOIN `user` u ON s.fyp_userid = u.fyp_userid LEFT JOIN programme p ON s.fyp_progid = p.fyp_progid LEFT JOIN academic_year a ON s.fyp_academicid = a.fyp_academicid ORDER BY s.fyp_studname ASC");
} else {
    // 获取 Supervisor, 显示 fyp_staffid
    $res = $conn->query("SELECT s.*, u.fyp_username FROM supervisor s JOIN `user` u ON s.fyp_userid = u.fyp_userid ORDER BY s.fyp_name ASC");
}
if($res) while($r=$res->fetch_assoc()) $data_list[]=$r;

// 菜单定义
$menu_items = [
    'dashboard' => ['name' => 'Dashboard', 'icon' => 'fa-home', 'link' => 'Coordinator_mainpage.php?page=dashboard'],
    'profile'   => ['name' => 'My Profile', 'icon' => 'fa-user', 'link' => 'Coordinator_profile.php'],
    'management' => [
        'name' => 'User Management', 'icon' => 'fa-users-cog',
        'sub_items' => [
            'manage_students' => ['name' => 'Student List', 'icon' => 'fa-user-graduate', 'link' => '?tab=student'],
            'manage_supervisors' => ['name' => 'Supervisor List', 'icon' => 'fa-chalkboard-teacher', 'link' => '?tab=supervisor'],
        ]
    ],
    'project_mgmt' => [
        'name' => 'Project Management',
        'icon' => 'fa-project-diagram',
        'sub_items' => [
            'project_list' => ['name' => 'All Projects & Groups', 'icon' => 'fa-list-alt', 'link' => 'Coordinator_project_list.php'],
            'pairing_list' => ['name' => 'Pairing List', 'icon' => 'fa-link', 'link' => 'Coordinator_mainpage.php?page=pairing_list'],
        ]
    ],
    'announcement' => [
        'name' => 'Announcement',
        'icon' => 'fa-bullhorn',
        'sub_items' => [
            'post_announcement' => ['name' => 'Post Announcement', 'icon' => 'fa-pen-square', 'link' => 'Coordinator_announcement.php'], 
            'view_announcements' => ['name' => 'View History', 'icon' => 'fa-history', 'link' => 'Coordinator_mainpage.php?page=view_announcements'],
        ]
    ],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage <?php echo ucfirst($tab); ?>s</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap');
        body { font-family: 'Poppins', sans-serif; margin: 0; background: #f4f7fc; display: flex; flex-direction: column; min-height: 100vh; }
        .topbar { background: #fff; padding: 15px 40px; display: flex; justify-content: space-between; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .layout { display: flex; flex: 1; padding: 20px; gap: 20px; max-width: 1400px; margin: 0 auto; width: 100%; box-sizing: border-box;}
        .sidebar { width: 250px; background: #fff; padding: 20px 0; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); height: fit-content; }
        .main { flex: 1; display: flex; flex-direction: column; gap: 20px; }
        .card { background: #fff; padding: 25px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .btn { background: #0056b3; color: #fff; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; }
        .btn:hover { background: #004494; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
        input, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; }
        a { text-decoration: none; color: inherit; }
        .menu-link { display: block; padding: 10px 20px; color: #555; }
        .menu-link:hover, .menu-link.active { background: #e3effd; color: #0056b3; }
        .submenu .menu-link { padding-left: 40px; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class="topbar">
        <div style="font-weight:600; font-size:20px; color:#0056b3;">FYP System</div>
        <div><?php echo $user_name; ?> <a href="login.php" style="color:red; margin-left:15px;">Logout</a></div>
    </div>

    <div class="layout">
        <div class="sidebar">
            <?php foreach($menu_items as $k=>$item): ?>
                <div class="menu-item">
                    <div class="menu-link" style="font-weight:600;"><i class="fa <?php echo $item['icon']; ?>"></i> <?php echo $item['name']; ?></div>
                    <?php if(isset($item['sub_items'])): ?>
                        <?php foreach($item['sub_items'] as $sk=>$sitem): ?>
                            <a href="<?php echo $sitem['link']."&auth_user_id=$auth_user_id"; ?>" class="menu-link <?php echo ($sk==$current_page)?'active':''; ?>">
                                <?php echo $sitem['name']; ?>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="main">
            <div class="card">
                <h2>Manage <?php echo ucfirst($tab); ?>s</h2>
                <div style="margin-bottom:20px;">
                    <a href="?auth_user_id=<?php echo $auth_user_id; ?>&tab=student" class="btn" style="background:<?php echo $tab=='student'?'#0056b3':'#ccc';?>">Students</a>
                    <a href="?auth_user_id=<?php echo $auth_user_id; ?>&tab=supervisor" class="btn" style="background:<?php echo $tab=='supervisor'?'#0056b3':'#ccc';?>">Supervisors</a>
                </div>

                <div style="background:#e3effd; padding:15px; border-radius:5px; margin-bottom:20px; border:1px dashed #0056b3;">
                    <h4>Batch Import (CSV)</h4>
                    <form method="POST" enctype="multipart/form-data" style="display:flex; gap:10px;">
                        <?php if($tab=='student'): ?>
                            <input type="file" name="csv_file" accept=".csv" required>
                            <button name="import_students" class="btn">Import Students</button>
                        <?php else: ?>
                            <input type="file" name="csv_file_sup" accept=".csv" required>
                            <button name="import_supervisors" class="btn">Import Supervisors</button>
                        <?php endif; ?>
                    </form>
                    <small style="color:#666;">
                        <?php if($tab=='student'): ?>
                            Format: Name, Matrix, Email, Contact, ProgID, AcdID
                        <?php else: ?>
                            Format: Name, StaffID, Email, Contact, Room, Programme, Spec, Interest, IsMod(0/1)
                        <?php endif; ?>
                    </small>
                </div>

                <form method="POST">
                    <h4 style="border-bottom:1px solid #eee; padding-bottom:10px;">Add New <?php echo ucfirst($tab); ?></h4>
                    <div class="form-grid">
                        <?php if($tab=='student'): ?>
                            <input type="text" name="full_name" placeholder="Full Name" required>
                            <input type="text" name="matrix_no" placeholder="Matrix No" required>
                            <input type="email" name="email" placeholder="Email" required>
                            <input type="text" name="contact" placeholder="Contact">
                            <select name="academic_id" required><option value="">Select Academic Year</option><?php foreach($academic_options as $a) echo "<option value='{$a['fyp_academicid']}'>{$a['fyp_acdyear']}</option>"; ?></select>
                            <select name="prog_id" required><option value="">Select Programme</option><?php foreach($programme_options as $p) echo "<option value='{$p['fyp_progid']}'>{$p['fyp_progname']}</option>"; ?></select>
                            <button name="add_single_student" class="btn" style="grid-column: span 2;">Add Student</button>
                        <?php else: ?>
                            <input type="text" name="username" placeholder="Username (Recommend Email)" required>
                            <input type="text" name="password" placeholder="Password" required>
                            <input type="text" name="full_name" placeholder="Full Name" required>
                            <input type="text" name="staff_id" placeholder="Staff ID (e.g. SF001)" required>
                            <input type="email" name="email" placeholder="Email">
                            <input type="text" name="contact" placeholder="Contact">
                            <input type="text" name="room_no" placeholder="Room No (e.g. R001)">

                            <select name="programme" required>
                                <option value="">Select Programme</option>
                                <?php foreach($programme_options as $p): ?>
                                    <option value="<?php echo htmlspecialchars($p['fyp_progname']); ?>"><?php echo htmlspecialchars($p['fyp_progname']); ?></option>
                                <?php endforeach; ?>
                            </select>

                            <input type="text" name="specialization" placeholder="Specialization (e.g. AI)">
                            <input type="text" name="area_of_interest" placeholder="Area of Interest">
                            
                            <select name="is_moderator">
                                <option value="0">Is Moderator: No (0)</option>
                                <option value="1">Is Moderator: Yes (1)</option>
                            </select>

                            <button name="add_supervisor" class="btn" style="grid-column: span 2;">Add Supervisor</button>
                        <?php endif; ?>
                    </div>
                </form>

                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>ID (<?php echo $tab=='student'?'Matrix':'Staff'; ?>)</th>
                            <th>Username</th>
                            <?php if($tab=='student') echo "<th>Info</th>"; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($data_list)>0): ?>
                            <?php foreach($data_list as $row): ?>
                            <tr>
                                <td><?php echo $row[$tab=='student'?'fyp_studname':'fyp_name']; ?></td>
                                <td><?php echo $row[$tab=='student'?'fyp_studid':'fyp_staffid']; ?></td>
                                <td><?php echo $row['fyp_username']; ?></td>
                                <?php if($tab=='student'): ?>
                                    <td><?php echo $row['fyp_progname'] . " (" . $row['fyp_acdyear'] . ")"; ?></td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" style="text-align:center;">No data found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>