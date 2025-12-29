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

    if (isset($_POST['add_mark'])) {
        $acd_id = $_POST['acd_id'];
        $mark = $_POST['academic_mark'];
        
        $sql = "INSERT INTO ACADEMIC_MARK (fyp_academicid, fyp_mark, fyp_createdby, fyp_createddate) 
                VALUES ('$acd_id', '$mark', '$current_user', NOW())";
        
        if ($conn->query($sql) === TRUE) {
            echo "<p>New Academic Mark added.</p>";
        } else {
            echo "<p>Error: " . $conn->error . "</p>";
        }
    }

    if (isset($_POST['update_mark'])) {
        $edit_id = $_POST['edit_id'];
        $edit_acd_id = $_POST['edit_acd_id'];
        $edit_mark = $_POST['edit_mark'];
        
        $sql = "UPDATE ACADEMIC_MARK 
                SET fyp_academicid = '$edit_acd_id', 
                    fyp_mark = '$edit_mark', 
                    fyp_editedby = '$current_user', 
                    fyp_editeddate = NOW() 
                WHERE fyp_id = '$edit_id'";
        
        if ($conn->query($sql) === TRUE) {
            echo "<p>Academic Mark updated successfully.</p>";
        } else {
            echo "<p>Error: " . $conn->error . "</p>";
        }
    }

    if (isset($_POST['delete_mark'])) {
        $delete_id = $_POST['delete_id'];
        $sql = "DELETE FROM ACADEMIC_MARK WHERE fyp_id = '$delete_id'";
        
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
    <title>Academic Mark Setup</title>
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
            margin: 15% auto; 
            padding: 0;
            border: 1px solid #ccc;
            width: 400px; 
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

        input[type="text"], input[type="number"], select {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            margin-bottom: 15px;
            box-sizing: border-box;
        }
    </style>
</head>
<body>

    <h1>Academic Mark Setup</h1>
    
    <table border="1" cellpadding="10">
        <tr>
            <td><b>Current User:</b></td>
            <td><?php echo $current_user; ?></td>
        </tr>
    </table>
    
    <br><hr><br>

    <h3>Add New Academic Mark</h3>
    <form method="POST" action="">
        <label>Academic Year:</label><br>
        <select name="acd_id" required style="width: 300px;">
            <option value="">-- Select Year --</option>
            <?php echo $acd_options; ?>
        </select>
        <br><br>
        
        <label>Mark Value:</label><br>
        <input type="number" step="0.01" name="academic_mark" required style="width: 150px;"><br><br>
        
        <button type="submit" name="add_mark">Add Mark</button>
    </form>

    <br><hr><br>

    <h3>Existing Academic Marks</h3>
    <table border="1" cellpadding="5" width="100%">
        <thead>
            <tr>
                <th align="left">ID</th>
                <th align="left">Academic Year ID</th>
                <th align="left">Mark</th>
                <th align="left">Created By</th>
                <th align="left">Last Edited</th>
                <th align="left">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $sql = "SELECT * FROM ACADEMIC_MARK ORDER BY fyp_id DESC";
            $result = $conn->query($sql);

            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . $row['fyp_id'] . "</td>";
                    echo "<td>" . $row['fyp_academicid'] . "</td>";
                    echo "<td><b>" . $row['fyp_mark'] . "</b></td>";
                    echo "<td>" . $row['fyp_createdby'] . "<br><small>" . $row['fyp_createddate'] . "</small></td>";
                    echo "<td>" . $row['fyp_editedby'] . "<br><small>" . $row['fyp_editeddate'] . "</small></td>";
                    echo "<td>
                            <button type='button' onclick='openEditModal(\"" . $row['fyp_id'] . "\", \"" . $row['fyp_academicid'] . "\", \"" . $row['fyp_mark'] . "\")'>Edit</button>
                            
                            <form method='POST' style='display:inline;' onsubmit='return confirm(\"Delete this record?\");'>
                                <input type='hidden' name='delete_id' value='" . $row['fyp_id'] . "'>
                                <button type='submit' name='delete_mark'>Delete</button>
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
                    Edit Academic Mark
                </div>
                
                <div class="modal-body">
                    <input type="hidden" id="edit_id" name="edit_id">
                    
                    <label>Academic Year:</label>
                    <select id="edit_acd_id" name="edit_acd_id" required>
                        <?php echo $acd_options; ?>
                    </select>
                    
                    <label>Mark Value:</label>
                    <input type="number" step="0.01" id="edit_mark" name="edit_mark" required>
                </div>
                
                <div class="modal-footer">
                    <button type="button" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" name="update_mark">Save Changes</button>
                </div>
            </div>
        </form>
    </div>

    <script>
        var modal = document.getElementById("editModal");

        function openEditModal(id, acdId, mark) {
            document.getElementById("edit_id").value = id;
            document.getElementById("edit_acd_id").value = acdId;
            document.getElementById("edit_mark").value = mark;
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