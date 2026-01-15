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

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (isset($_POST['add_criteria'])) {
        $acd_id = $_POST['acd_id'];
        $criteria_name = $_POST['criteria_name'];
        
        $sql = "INSERT INTO GRADE_CRITERIA (fyp_acdemicid, fyp_criterianame, fyp_createdby, fyp_createddate) 
                VALUES ('$acd_id', '$criteria_name', '$current_user', NOW())";
        
        if ($conn->query($sql) === TRUE) {
            echo "<p>New Grade Criteria added.</p>";
        } else {
            echo "<p>Error: " . $conn->error . "</p>";
        }
    }

    if (isset($_POST['update_criteria'])) {
        $edit_id = $_POST['edit_id'];
        $edit_acd_id = $_POST['edit_acd_id'];
        $edit_name = $_POST['edit_criteria_name'];
        
        $sql = "UPDATE GRADE_CRITERIA 
                SET fyp_acdemicid = '$edit_acd_id', 
                    fyp_criterianame = '$edit_name', 
                    fyp_editedby = '$current_user', 
                    fyp_editeddate = NOW() 
                WHERE fyp_id = '$edit_id'";
        
        if ($conn->query($sql) === TRUE) {
            echo "<p>Criteria updated successfully.</p>";
        } else {
            echo "<p>Error: " . $conn->error . "</p>";
        }
    }

    if (isset($_POST['delete_criteria'])) {
        $delete_id = $_POST['delete_id'];
        $sql = "DELETE FROM GRADE_CRITERIA WHERE fyp_id = '$delete_id'";
        
        if ($conn->query($sql) === TRUE) {
            echo "<p>Criteria deleted.</p>";
        } else {
            echo "<p>Error: " . $conn->error . "</p>";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Grade Criteria Setup</title>
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
            margin: 15% auto; 
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

    <h1>Grade Criteria Setup</h1>
    
    <table border="1" cellpadding="10">
        <tr>
            <td><b>Current User:</b></td>
            <td><?php echo $current_user; ?></td>
        </tr>
    </table>
    
    <br><hr><br>

    <h3>Add New Criteria</h3>
    <form method="POST" action="">
        <label>Academic Year:</label><br>
        <select name="acd_id" required>
            <option value="">-- Select Year --</option>
            <?php echo $acd_options; ?>
        </select>
        <br><br>
        
        <label>Criteria Name (e.g., Proposal, Final Report, Presentation):</label><br>
        <input type="text" name="criteria_name" required style="width: 300px;"><br><br>
        
        <button type="submit" name="add_criteria">Add Criteria</button>
    </form>

    <br><hr><br>

    <h3>Existing Grade Criteria</h3>
    <table border="1" cellpadding="5" width="100%">
        <thead>
            <tr>
                <th align="left">ID</th>
                <th align="left">Academic Year ID</th>
                <th align="left">Criteria Name</th>
                <th align="left">Created By / Date</th>
                <th align="left">Edited By / Date</th>
                <th align="left">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $sql = "SELECT * FROM GRADE_CRITERIA ORDER BY fyp_id DESC";
            $result = $conn->query($sql);

            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . $row['fyp_id'] . "</td>";
                    echo "<td>" . $row['fyp_acdemicid'] . "</td>";
                    echo "<td>" . $row['fyp_criterianame'] . "</td>";
                    echo "<td>" . $row['fyp_createdby'] . "<br><small>" . $row['fyp_createddate'] . "</small></td>";
                    echo "<td>" . $row['fyp_editedby'] . "<br><small>" . $row['fyp_editeddate'] . "</small></td>";
                    echo "<td>
                            <button type='button' onclick='openEditModal(\"" . $row['fyp_id'] . "\", \"" . $row['fyp_acdemicid'] . "\", \"" . $row['fyp_criterianame'] . "\")'>Edit (Popup)</button>
                            
                            <form method='POST' style='display:inline;' onsubmit='return confirm(\"Delete this criteria?\");'>
                                <input type='hidden' name='delete_id' value='" . $row['fyp_id'] . "'>
                                <button type='submit' name='delete_criteria'>Delete</button>
                            </form>
                          </td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='6'>No criteria found.</td></tr>";
            }
            ?>
        </tbody>
    </table>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h3>Edit Grade Criteria</h3>
            
            <form method="POST" action="">
                <input type="hidden" id="edit_id" name="edit_id">
                
                <label>Academic Year ID:</label><br>
                <select id="edit_acd_id" name="edit_acd_id" required>
                    <?php echo $acd_options; ?>
                </select>
                <br><br>
                
                <label>Criteria Name:</label><br>
                <input type="text" id="edit_criteria_name" name="edit_criteria_name" style="width: 100%;" required>
                <br><br>
                
                <button type="submit" name="update_criteria">Update Changes</button>
            </form>
        </div>
    </div>

    <script>
        var modal = document.getElementById("editModal");

        function openEditModal(id, acdId, name) {
            document.getElementById("edit_id").value = id;
            document.getElementById("edit_acd_id").value = acdId;
            document.getElementById("edit_criteria_name").value = name;
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