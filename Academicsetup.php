<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "fyp_management";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (isset($_POST['add_academic'])) {
        $year = $_POST['acd_year'];
        $intake = $_POST['acd_intake'];
        
        $sql = "INSERT INTO ACADEMIC_YEAR (fyp_acdyear, fyp_intake, fyp_datecreated) VALUES ('$year', '$intake', NOW())";
        
        if ($conn->query($sql) === TRUE) {
            echo "<p>New academic year added successfully.</p>";
        } else {
            echo "<p>Error: " . $conn->error . "</p>";
        }
    }

    if (isset($_POST['delete_academic'])) {
        $id_to_delete = $_POST['delete_id'];
        $sql = "DELETE FROM ACADEMIC_YEAR WHERE fyp_academicid = '$id_to_delete'";
        
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
    <title>Academic Year Setup</title>
</head>
<body>

    <h1>Academic Setup</h1>
    <a href="programme_setup.php">Go to Programme Setup ></a>
    <br><hr><br>

    <h3>Add New Academic Year</h3>
    <form method="POST" action="">
        <label>Academic Year (e.g., 2024/2025):</label><br>
        <input type="text" name="acd_year" required><br><br>
        
        <label>Intake (e.g., June, September):</label><br>
        <input type="text" name="acd_intake" required><br><br>
        
        <button type="submit" name="add_academic">Add Record</button>
    </form>

    <br><hr><br>

    <h3>Existing Academic Years</h3>
    <table border="1" cellpadding="5" width="80%">
        <thead>
            <tr>
                <th align="left">ID</th>
                <th align="left">Year</th>
                <th align="left">Intake</th>
                <th align="left">Date Created</th>
                <th align="left">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $sql = "SELECT * FROM ACADEMIC_YEAR ORDER BY fyp_academicid DESC";
            $result = $conn->query($sql);

            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . $row['fyp_academicid'] . "</td>";
                    echo "<td>" . $row['fyp_acdyear'] . "</td>";
                    echo "<td>" . $row['fyp_intake'] . "</td>";
                    echo "<td>" . $row['fyp_datecreated'] . "</td>";
                    echo "<td>
                            <form method='POST' onsubmit='return confirm(\"Are you sure?\");'>
                                <input type='hidden' name='delete_id' value='" . $row['fyp_academicid'] . "'>
                                <button type='submit' name='delete_academic'>Delete</button>
                            </form>
                          </td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='5'>No records found.</td></tr>";
            }
            ?>
        </tbody>
    </table>

</body>
</html>