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

    if (isset($_POST['add_workload'])) {
        $acd_id = $_POST['acd_id'];
        $formula = $_POST['formula'];
        $semester = $_POST['semester'];
        
        $is_fulltime = 1; 
        
        $sql = "INSERT INTO WORKLOAD_FORMULA (fyp_academicid, fyp_formula, fyp_isfulltime, fyp_semester, fyp_createdby, fyp_createddate) 
                VALUES ('$acd_id', '$formula', '$is_fulltime', '$semester', '$current_user', NOW())";
        
        if ($conn->query($sql) === TRUE) {
            echo "<p>New Workload Formula added.</p>";
        } else {
            echo "<p>Error: " . $conn->error . "</p>";
        }
    }

    if (isset($_POST['update_workload'])) {
        $edit_id = $_POST['edit_id'];
        $edit_acd_id = $_POST['edit_acd_id'];
        $edit_formula = $_POST['edit_formula'];
        $edit_semester = $_POST['edit_semester'];
        
        $edit_fulltime = 1;
        
        $sql = "UPDATE WORKLOAD_FORMULA 
                SET fyp_academicid = '$edit_acd_id', 
                    fyp_formula = '$edit_formula', 
                    fyp_isfulltime = '$edit_fulltime', 
                    fyp_semester = '$edit_semester',
                    fyp_editedby = '$current_user', 
                    fyp_editeddate = NOW() 
                WHERE fyp_id = '$edit_id'";
        
        if ($conn->query($sql) === TRUE) {
            echo "<p>Workload updated successfully.</p>";
        } else {
            echo "<p>Error: " . $conn->error . "</p>";
        }
    }

    if (isset($_POST['delete_workload'])) {
        $delete_id = $_POST['delete_id'];
        $sql = "DELETE FROM WORKLOAD_FORMULA WHERE fyp_id = '$delete_id'";
        
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
    <title>Workload Formula Setup</title>
    <style>
        .modal {
            display: none; 
            position: fixed; 
            z-index: 100; 
            left: 0;
            top: 0;
            width: 100%; 
            height: 100%; 
            background-color: rgba(0,0,0,0.5); 
        }

        .modal-content {
            background-color: #fff;
            margin: 10% auto; 
            padding: 0;
            border: 1px solid #ccc;
            width: 450px; 
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            font-family: Arial, sans-serif;
        }

        .modal-header {
            padding: 15px;
            background-color: #f1f1f1;
            border-bottom: 1px solid #ddd;
            font-weight: bold;
            font-size: 18px;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            padding: 10px 15px;
            background-color: #f1f1f1;
            border-top: 1px solid #ddd;
            text-align: right;
        }

        button {
            padding: 8px 12px;
            cursor: pointer;
        }

        input[type="text"], select {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            margin-bottom: 15px;
            box-sizing: border-box;
        }
    </style>
</head>
<body>

    <h1>Workload Formula Setup</h1>
    
    <table border="1" cellpadding="10">
        <tr>
            <td><b>Current User:</b></td>
            <td><?php echo $current_user; ?></td>
        </tr>
    </table>
    
    <br><hr><br>

    <h3>Add New Workload Formula</h3>
    <form method="POST" action="">
        <label>Academic Year:</label><br>
        <select name="acd_id" required>
            <option value="">-- Select Year --</option>
            <?php echo $acd_options; ?>
        </select>
        
        <label>Semester (e.g., Short Sem, Long Sem):</label><br>
        <input type="text" name="semester" required maxlength="32">

        <label>Formula (e.g., Count * 0.5):</label><br>
        <input type="text" name="formula" required maxlength="100">
        
        <button type="submit" name="add_workload">Add Formula</button>
    </form>

    <br><hr><br>

    <h3>Existing Workload Formulas</h3>
    <table border="1" cellpadding="5" width="100%">
        <thead>
            <tr>
                <th align="left">ID</th>
                <th align="left">Year ID</th>
                <th align="left">Semester</th>
                <th align="left">Formula</th>
                <th align="left">Last Edited</th>
                <th align="left">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $sql = "SELECT * FROM WORKLOAD_FORMULA ORDER BY fyp_id DESC";
            $result = $conn->query($sql);

            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . $row['fyp_id'] . "</td>";
                    echo "<td>" . $row['fyp_academicid'] . "</td>";
                    echo "<td>" . $row['fyp_semester'] . "</td>";
                    echo "<td><b>" . $row['fyp_formula'] . "</b></td>";
                    echo "<td>" . $row['fyp_editeddate'] . "</td>";
                    echo "<td>
                            <button type='button' onclick='openEditModal(\"" . $row['fyp_id'] . "\", \"" . $row['fyp_academicid'] . "\", \"" . $row['fyp_formula'] . "\", \"" . $row['fyp_semester'] . "\")'>Edit</button>
                            
                            <form method='POST' style='display:inline;' onsubmit='return confirm(\"Delete this record?\");'>
                                <input type='hidden' name='delete_id' value='" . $row['fyp_id'] . "'>
                                <button type='submit' name='delete_workload'>Delete</button>
                            </form>
                          </td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='6'>No records found.</td></tr>";
            }
            ?>
        </tbody>
    </table>

    <div id="editModal" class="modal">
        <form method="POST" action="">
            <div class="modal-content">
                <div class="modal-header">
                    Edit Workload Formula
                </div>
                
                <div class="modal-body">
                    <input type="hidden" id="edit_id" name="edit_id">
                    
                    <label>Academic Year:</label>
                    <select id="edit_acd_id" name="edit_acd_id" required>
                        <?php echo $acd_options; ?>
                    </select>

                    <label>Semester:</label>
                    <input type="text" id="edit_semester" name="edit_semester" required>

                    <label>Formula:</label>
                    <input type="text" id="edit_formula" name="edit_formula" required>
                </div>
                
                <div class="modal-footer">
                    <button type="button" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" name="update_workload">Save Changes</button>
                </div>
            </div>
        </form>
    </div>

    <script>
        var modal = document.getElementById("editModal");

        function openEditModal(id, acdId, formula, semester) {
            document.getElementById("edit_id").value = id;
            document.getElementById("edit_acd_id").value = acdId;
            document.getElementById("edit_formula").value = formula;
            document.getElementById("edit_semester").value = semester;
            
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