<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "fyp_management";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$current_admin_id = "STAFF001";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (isset($_POST['add_maintenance'])) {
        $subject = $_POST['maintain_subject'];
        $category = $_POST['maintain_category'];
        $value = $_POST['maintain_value'];
        
        $sql = "INSERT INTO FYP_MAINTENANCE (fyp_subject, fyp_category, fyp_value, fyp_createdby, fyp_datecreated) VALUES ('$subject', '$category', '$value', '$current_admin_id', NOW())";
        
        if ($conn->query($sql) === TRUE) {
            echo "<p>New maintenance setting added successfully.</p>";
        } else {
            echo "<p>Error: " . $conn->error . "</p>";
        }
    }

    if (isset($_POST['delete_maintenance'])) {
        $id_to_delete = $_POST['delete_id'];
        $sql = "DELETE FROM FYP_MAINTENANCE WHERE fyp_maintainid = '$id_to_delete'";
        
        if ($conn->query($sql) === TRUE) {
            echo "<p>Record deleted successfully.</p>";
        } else {
            echo "<p>Error: " . $conn->error . "</p>";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>System Maintenance Setup</title>
</head>
<body>

    <h1>System Maintenance Setup</h1>
    
    <table border="1" cellpadding="10">
        <tr>
            <td><b>Current Admin:</b></td>
            <td><?php echo $current_admin_id; ?></td>
        </tr>
    </table>
    
    <br><hr><br>

    <h3>Add New Setting</h3>
    <form method="POST" action="">
        <label>Subject (e.g., Grade, Role):</label><br>
        <input type="text" name="maintain_subject" required style="width: 300px;"><br><br>
        
        <label>Category (e.g., Final Year Project):</label><br>
        <input type="text" name="maintain_category" required style="width: 300px;"><br><br>
        
        <label>Value (e.g., A, Pass, Active):</label><br>
        <input type="text" name="maintain_value" required maxlength="24" style="width: 300px;"><br><br>
        
        <button type="submit" name="add_maintenance">Add Setting</button>
    </form>

    <br><hr><br>

    <h3>Existing Maintenance Records</h3>
    <table border="1" cellpadding="5" width="100%">
        <thead>
            <tr>
                <th align="left">ID</th>
                <th align="left">Subject</th>
                <th align="left">Category</th>
                <th align="left">Value</th>
                <th align="left">Created By</th>
                <th align="left">Date Created</th>
                <th align="left">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $sql = "SELECT * FROM FYP_MAINTENANCE ORDER BY fyp_maintainid DESC";
            $result = $conn->query($sql);

            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . $row['fyp_maintainid'] . "</td>";
                    echo "<td>" . $row['fyp_subject'] . "</td>";
                    echo "<td>" . $row['fyp_category'] . "</td>";
                    echo "<td>" . $row['fyp_value'] . "</td>";
                    echo "<td>" . $row['fyp_createdby'] . "</td>";
                    echo "<td>" . $row['fyp_datecreated'] . "</td>";
                    echo "<td>
                            <form method='POST' onsubmit='return confirm(\"Are you sure?\");'>
                                <input type='hidden' name='delete_id' value='" . $row['fyp_maintainid'] . "'>
                                <button type='submit' name='delete_maintenance'>Delete</button>
                            </form>
                          </td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='7'>No records found.</td></tr>";
            }
            ?>
        </tbody>
    </table>

</body>
</html>