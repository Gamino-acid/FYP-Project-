<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "fyp_management";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$sql_create = "CREATE TABLE IF NOT EXISTS STUDENT (
    fyp_studid VARCHAR(12) PRIMARY KEY,
    fyp_studfullid VARCHAR(10),
    fyp_studname VARCHAR(56),
    fyp_academicid INT,
    fyp_progid INT,
    fyp_tutgroup INT,
    fyp_email VARCHAR(56),
    fyp_contactno VARCHAR(12),
    fyp_profileimg VARCHAR(500)
)";
$conn->query($sql_create);

$current_student_id = "S12345";
$check_student = $conn->query("SELECT * FROM STUDENT WHERE fyp_studid = '$current_student_id'");
if ($check_student->num_rows == 0) {
    $conn->query("INSERT INTO STUDENT (fyp_studid, fyp_studname) VALUES ('$current_student_id', 'New Student')");
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $stud_name = $_POST['stud_name'];
    $full_id = $_POST['stud_fullid'];
    $academic_id = !empty($_POST['academic_id']) ? $_POST['academic_id'] : "NULL";
    $prog_id = !empty($_POST['prog_id']) ? $_POST['prog_id'] : "NULL";
    $tut_group = !empty($_POST['tut_group']) ? $_POST['tut_group'] : "NULL";
    $email = $_POST['email'];
    $contact = $_POST['contact'];
    
    $sql_update = "UPDATE STUDENT SET 
                   fyp_studname = '$stud_name',
                   fyp_studfullid = '$full_id',
                   fyp_academicid = $academic_id,
                   fyp_progid = $prog_id,
                   fyp_tutgroup = $tut_group,
                   fyp_email = '$email',
                   fyp_contactno = '$contact'
                   WHERE fyp_studid = '$current_student_id'";
    $conn->query($sql_update);

    if (!empty($_FILES["profile_img"]["name"])) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }
        $target_file = $target_dir . time() . "_" . basename($_FILES["profile_img"]["name"]);
        if (move_uploaded_file($_FILES["profile_img"]["tmp_name"], $target_file)) {
            $conn->query("UPDATE STUDENT SET fyp_profileimg = '$target_file' WHERE fyp_studid = '$current_student_id'");
        }
    }
    echo "<meta http-equiv='refresh' content='0'>";
}

$result = $conn->query("SELECT * FROM STUDENT WHERE fyp_studid = '$current_student_id'");
$stud_data = $result->fetch_assoc();

$result_acd = $conn->query("SELECT * FROM ACADEMIC_YEAR");
$result_prog = $conn->query("SELECT * FROM PROGRAMME");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Student Profile</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f4f4; padding: 20px; }
        
        .container {
            display: flex;
            max-width: 900px;
            margin: 0 auto;
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .left-column {
            width: 30%;
            display: flex;
            flex-direction: column;
            align-items: center;
            border-right: 1px solid #eee;
            padding-right: 30px;
        }

        .profile-img-box {
            width: 160px;
            height: 160px;
            border-radius: 50%;
            background-color: #e0e0e0;
            overflow: hidden;
            margin-bottom: 15px;
            border: 4px solid #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }

        .profile-img-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .right-column {
            width: 70%;
            padding-left: 30px;
        }

        h1 { text-align: center; color: #333; margin-bottom: 30px; }
        h3 { color: #555; border-bottom: 2px solid #007bff; padding-bottom: 5px; margin-bottom: 20px; font-size: 1.1em; }

        label { display: block; margin-top: 15px; font-weight: 600; color: #444; font-size: 0.9em; }
        
        input[type="text"], input[type="email"], input[type="number"], select {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 0.95em;
        }

        .row { display: flex; gap: 20px; }
        .col { flex: 1; }

        button {
            margin-top: 25px;
            padding: 12px 25px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            transition: background 0.3s;
        }
        button:hover { background-color: #0056b3; }
        
        .student-id-display { font-size: 1.2em; font-weight: bold; color: #333; margin-top: 10px; }
    </style>
</head>
<body>

    <h1>Student Profile</h1>

    <form method="POST" enctype="multipart/form-data">
        <div class="container">
            
            <div class="left-column">
                <div class="profile-img-box">
                    <?php 
                    $img_path = $stud_data['fyp_profileimg'];
                    if (!empty($img_path) && file_exists($img_path)) {
                        echo "<img src='$img_path'>";
                    } else {
                        echo "<div style='width:100%;height:100%;background:#ddd;display:flex;align-items:center;justify-content:center;color:#777;'>No Image</div>";
                    }
                    ?>
                </div>
                
                <label>Change Photo:</label>
                <input type="file" name="profile_img" accept="image/*" style="font-size: 0.8em;">
                
                <div class="student-id-display"><?php echo $stud_data['fyp_studid']; ?></div>
            </div>

            <div class="right-column">
                <h3>Personal & Academic Details</h3>
                
                <label>Full Name:</label>
                <input type="text" name="stud_name" value="<?php echo $stud_data['fyp_studname']; ?>" required>

                <label>Student Full ID (Internal):</label>
                <input type="text" name="stud_fullid" value="<?php echo $stud_data['fyp_studfullid']; ?>">

                <div class="row">
                    <div class="col">
                        <label>Academic Year:</label>
                        <select name="academic_id">
                            <option value="">-- Select --</option>
                            <?php 
                            if ($result_acd) {
                                while($row = $result_acd->fetch_assoc()) {
                                    $selected = ($row['fyp_academicid'] == $stud_data['fyp_academicid']) ? "selected" : "";
                                    echo "<option value='" . $row['fyp_academicid'] . "' $selected>" . $row['fyp_acdyear'] . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col">
                        <label>Programme:</label>
                        <select name="prog_id">
                            <option value="">-- Select --</option>
                            <?php 
                            if ($result_prog) {
                                while($row = $result_prog->fetch_assoc()) {
                                    $selected = ($row['fyp_progid'] == $stud_data['fyp_progid']) ? "selected" : "";
                                    echo "<option value='" . $row['fyp_progid'] . "' $selected>" . $row['fyp_progname'] . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <label>Tutorial Group:</label>
                <input type="number" name="tut_group" value="<?php echo $stud_data['fyp_tutgroup']; ?>">

                <label>Email Address:</label>
                <input type="email" name="email" value="<?php echo $stud_data['fyp_email']; ?>">

                <label>Contact Number:</label>
                <input type="text" name="contact" value="<?php echo $stud_data['fyp_contactno']; ?>">

                <button type="submit" name="update_profile">Save Changes</button>
            </div>

        </div>
    </form>

</body>
</html>