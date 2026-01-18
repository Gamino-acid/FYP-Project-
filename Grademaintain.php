<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "fyp_management";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$current_user = "STAFF001";

$acd_options = "";
$sql_acd = "SELECT * FROM ACADEMIC_YEAR ORDER BY fyp_academicid DESC";
$result_acd = $conn->query($sql_acd);
if ($result_acd->num_rows > 0) {
    while($row_acd = $result_acd->fetch_assoc()) {
        $acd_options .= "<option value='" . $row_acd['fyp_academicid'] . "'>" . $row_acd['fyp_acdyear'] . " (" . $row_acd['fyp_intake'] . ")</option>";
    }
}

$criteria_options = "";
$sql_crit = "SELECT * FROM GRADE_CRITERIA ORDER BY fyp_id DESC";
$result_crit = $conn->query($sql_crit);
if ($result_crit->num_rows > 0) {
    while($row_crit = $result_crit->fetch_assoc()) {
        $criteria_options .= "<option value='" . $row_crit['fyp_id'] . "'>" . $row_crit['fyp_criterianame'] . "</option>";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (isset($_POST['add_grade'])) {
        $acd_id = $_POST['acd_id'];
        $crit_id = $_POST['crit_id'];
        $grade = $_POST['grade_letter'];
        $mark_from = $_POST['mark_from'];
        $mark_to = $_POST['mark_to'];
        
        $sql = "INSERT INTO GRADE_MAINTENANCE (fyp_acdemicid, fyp_gradecriteriaid, fyp_grade, fyp_frommark, fyp_tomark, fyp_createdby, fyp_createddate) 
                VALUES ('$acd_id', '$crit_id', '$grade', '$mark_from', '$mark_to', '$current_user', NOW())";
        
        if ($conn->query($sql) === TRUE) {
            echo "<p>New Grade Range added.</p>";
        } else {
            echo "<p>Error: " . $conn->error . "</p>";
        }
    }

    if (isset($_POST['update_grade'])) {
        $edit_id = $_POST['edit_id'];
        $edit_acd = $_POST['edit_acd_id'];
        $edit_crit = $_POST['edit_crit_id'];
        $edit_grade = $_POST['edit_grade'];
        $edit_from = $_POST['edit_from'];
        $edit_to = $_POST['edit_to'];
        
        $sql = "UPDATE GRADE_MAINTENANCE 
                SET fyp_acdemicid = '$edit_acd', 
                    fyp_gradecriteriaid = '$edit_crit', 
                    fyp_grade = '$edit_grade', 
                    fyp_frommark = '$edit_from', 
                    fyp_tomark = '$edit_to', 
                    fyp_editedby = '$current_user', 
                    fyp_editeddate = NOW() 
                WHERE fyp_id = '$edit_id'";
        
        if ($conn->query($sql) === TRUE) {
            echo "<p>Grade Range updated successfully.</p>";
        } else {
            echo "<p>Error: " . $conn->error . "</p>";
        }
    }

    if (isset($_POST['delete_grade'])) {
        $delete_id = $_POST['delete_id'];
        $sql = "DELETE FROM GRADE_MAINTENANCE WHERE fyp_id = '$delete_id'";
        
        if ($conn->query($sql) === TRUE) {
            echo "<p>Record deleted.</p>";
        } else {
            echo "<p>Error: " . $conn->error . "</p>";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Grade Maintenance Setup</title>
    <style>
        .modal {
            display: none; 
            position: fixed; 
            z-index: 1; 
            left: 0;
            top: 0;
            width: 100%; 
            height: 100%; 
            overflow: auto; 
            background-color: rgba(0,0,0,0.4); 
        }

        .modal-content {
            background-color: #fefefe;
            margin: 10% auto; 
            padding: 20px;
            border: 1px solid #888;
            width: 40%;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
    </style>
</head>
<body>

    <h1>Grade Maintenance (Score Ranges)</h1>
    
    <table border="1" cellpadding="10">
        <tr>
            <td><b>Current User:</b></td>
            <td><?php echo $current_user; ?></td>
        </tr>
    </table>
    
    <br><hr><br>

    <h3>Add New Grade Range</h3>
    <form method="POST" action="">
        <label>Academic Year:</label><br>
        <select name="acd_id" required>
            <option value="">-- Select Year --</option>
            <?php echo $acd_options; ?>
        </select>
        <br><br>

        <label>Grade Criteria (Category):</label><br>
        <select name="crit_id" required>
            <option value="">-- Select Criteria --</option>
            <?php echo $criteria_options; ?>
        </select>
        <br><br>
        
        <label>Grade Letter (e.g., A, B+):</label><br>
        <input type="text" name="grade_letter" required maxlength="5" style="width: 100px;"><br><br>

        <label>Mark Range (From - To):</label><br>
        <input type="number" name="mark_from" placeholder="Min" required style="width: 80px;"> - 
        <input type="number" name="mark_to" placeholder="Max" required style="width: 80px;">
        <br><br>
        
        <button type="submit" name="add_grade">Add Grade Range</button>
    </form>

    <br><hr><br>

    <h3>Existing Grade Ranges</h3>
    <table border="1" cellpadding="5" width="100%">
        <thead>
            <tr>
                <th align="left">ID</th>
                <th align="left">Year ID</th>
                <th align="left">Criteria ID</th>
                <th align="left">Grade</th>
                <th align="left">Marks (From-To)</th>
                <th align="left">Created By</th>
                <th align="left">Last Edited</th>
                <th align="left">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $sql = "SELECT * FROM GRADE_MAINTENANCE ORDER BY fyp_id DESC";
            $result = $conn->query($sql);

            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . $row['fyp_id'] . "</td>";
                    echo "<td>" . $row['fyp_acdemicid'] . "</td>";
                    echo "<td>" . $row['fyp_gradecriteriaid'] . "</td>";
                    echo "<td><b>" . $row['fyp_grade'] . "</b></td>";
                    echo "<td>" . $row['fyp_frommark'] . " - " . $row['fyp_tomark'] . "</td>";
                    echo "<td>" . $row['fyp_createdby'] . "</td>";
                    echo "<td>" . $row['fyp_editeddate'] . "</td>";
                    echo "<td>
                            <button type='button' onclick='openEditModal(\"" . $row['fyp_id'] . "\", \"" . $row['fyp_acdemicid'] . "\", \"" . $row['fyp_gradecriteriaid'] . "\", \"" . $row['fyp_grade'] . "\", \"" . $row['fyp_frommark'] . "\", \"" . $row['fyp_tomark'] . "\")'>Edit</button>
                            
                            <form method='POST' style='display:inline;' onsubmit='return confirm(\"Delete this range?\");'>
                                <input type='hidden' name='delete_id' value='" . $row['fyp_id'] . "'>
                                <button type='submit' name='delete_grade'>Delete</button>
                            </form>
                          </td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='8'>No records found.</td></tr>";
            }
            ?>
        </tbody>
    </table>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h3>Edit Grade Range</h3>
            
            <form method="POST" action="">
                <input type="hidden" id="edit_id" name="edit_id">
                
                <label>Academic Year:</label><br>
                <select id="edit_acd_id" name="edit_acd_id" required>
                    <?php echo $acd_options; ?>
                </select>
                <br><br>

                <label>Grade Criteria:</label><br>
                <select id="edit_crit_id" name="edit_crit_id" required>
                    <?php echo $criteria_options; ?>
                </select>
                <br><br>

                <label>Grade Letter:</label><br>
                <input type="text" id="edit_grade" name="edit_grade" required maxlength="5" style="width: 100px;">
                <br><br>

                <label>Mark Range (From - To):</label><br>
                <input type="number" id="edit_from" name="edit_from" required style="width: 80px;"> - 
                <input type="number" id="edit_to" name="edit_to" required style="width: 80px;">
                <br><br>
                
                <button type="submit" name="update_grade">Update Changes</button>
            </form>
        </div>
    </div>

    <script>
        var modal = document.getElementById("editModal");

        function openEditModal(id, acdId, critId, grade, fromMark, toMark) {
            document.getElementById("edit_id").value = id;
            document.getElementById("edit_acd_id").value = acdId;
            document.getElementById("edit_crit_id").value = critId;
            document.getElementById("edit_grade").value = grade;
            document.getElementById("edit_from").value = fromMark;
            document.getElementById("edit_to").value = toMark;
            
            modal.style.display = "block";
        }

        function closeEditModal() {
            modal.style.display = "none";
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>

</body>
</html>