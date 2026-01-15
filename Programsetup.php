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

    if (isset($_POST['add_programme'])) {
        $prog_name = $_POST['prog_name'];
        $prog_full = $_POST['prog_full'];
        
        $sql = "INSERT INTO PROGRAMME (fyp_progname, fyp_prognamefull, fyp_datecreated) VALUES ('$prog_name', '$prog_full', NOW())";
        
        if ($conn->query($sql) === TRUE) {
            echo "<p>New programme added successfully.</p>";
        } else {
            echo "<p>Error: " . $conn->error . "</p>";
        }
    }

    if (isset($_POST['delete_programme'])) {
        $id_to_delete = $_POST['delete_id'];
        $sql = "DELETE FROM PROGRAMME WHERE fyp_progid = '$id_to_delete'";
        
        if ($conn->query($sql) === TRUE) {
            echo "<p>Programme deleted successfully.</p>";
        } else {
            echo "<p>Error: " . $conn->error . "</p>";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Programme Setup</title>
</head>
<body>

    <h1>Programme Setup</h1>
    <a href="academic_setup.php">< Go back to Academic Setup</a>
    <br><hr><br>

    <h3>Add New Programme</h3>
    <form method="POST" action="">
        <label>Programme Code (e.g., RIT, RSD):</label><br>
        <input type="text" name="prog_name" required><br><br>
        
        <label>Full Programme Name:</label><br>
        <input type="text" name="prog_full" style="width: 300px;" required><br><br>
        
        <button type="submit" name="add_programme">Add Programme</button>
    </form>

    <br><hr><br>

    <h3>Existing Programmes</h3>
    <table border="1" cellpadding="5" width="80%">
        <thead>
            <tr>
                <th align="left">ID</th>
                <th align="left">Code</th>
                <th align="left">Full Name</th>
                <th align="left">Date Created</th>
                <th align="left">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $sql = "SELECT * FROM PROGRAMME ORDER BY fyp_progid DESC";
            $result = $conn->query($sql);

            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . $row['fyp_progid'] . "</td>";
                    echo "<td>" . $row['fyp_progname'] . "</td>";
                    echo "<td>" . $row['fyp_prognamefull'] . "</td>";
                    echo "<td>" . $row['fyp_datecreated'] . "</td>";
                    echo "<td>
                            <form method='POST' onsubmit='return confirm(\"Are you sure?\");'>
                                <input type='hidden' name='delete_id' value='" . $row['fyp_progid'] . "'>
                                <button type='submit' name='delete_programme'>Delete</button>
                            </form>
                          </td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='5'>No programmes found.</td></tr>";
            }
            ?>
        </tbody>
    </table>

</body>
</html>