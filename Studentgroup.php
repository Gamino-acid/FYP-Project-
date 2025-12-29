<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "fyp_management";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$current_student_id = "S12345"; 
$current_pairing_id = 0;
$current_scope = "";

$sql_user = "SELECT * FROM STUDENT_GROUP WHERE fyp_studid = '$current_student_id'";
$result_user = $conn->query($sql_user);

if ($result_user && $result_user->num_rows > 0) {
    $row = $result_user->fetch_assoc();
    $current_pairing_id = $row['fyp_pairingid'];
    $current_scope = $row['fyp_individualscope'];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    if (isset($_POST['save_scope'])) {
        $scope_input = $_POST['scope_input'];
        $sql = "UPDATE STUDENT_GROUP SET fyp_individualscope = '$scope_input' WHERE fyp_studid = '$current_student_id'";
        if ($conn->query($sql) === TRUE) {
            $current_scope = $scope_input;
            echo "<p>Scope saved successfully.</p>";
        } else {
            echo "<p>Error: " . $conn->error . "</p>";
        }
    }

    if (isset($_POST['send_invite'])) {
        $invite_id = $_POST['invite_id'];
        $sql = "INSERT INTO STUDENT_GROUP (fyp_studid, fyp_pairingid, fyp_request, fyp_acceptance, fyp_datecreated) VALUES ('$invite_id', '$current_pairing_id', 1, 0, NOW())";
        if ($conn->query($sql) === TRUE) {
            echo "<p>Invitation sent to $invite_id.</p>";
        } else {
            echo "<p>Error: " . $conn->error . "</p>";
        }
    }

    if (isset($_POST['accept_member'])) {
        $target_id = $_POST['target_id'];
        $sql = "UPDATE STUDENT_GROUP SET fyp_acceptance = 1 WHERE fyp_studid = '$target_id' AND fyp_pairingid = '$current_pairing_id'";
        if ($conn->query($sql) === TRUE) {
            echo "<p>Member accepted successfully.</p>";
        } else {
            echo "<p>Error: " . $conn->error . "</p>";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>FYP Management System</title>
</head>
<body>

    <h1>FYP Student Dashboard</h1>
    
    <table border="1" cellpadding="10" width="50%">
        <tr>
            <td><b>Logged in as:</b></td>
            <td><?php echo $current_student_id; ?></td>
        </tr>
        <tr>
            <td><b>Group Pairing ID:</b></td>
            <td><?php echo $current_pairing_id; ?></td>
        </tr>
    </table>

    <br><hr><br>

    <h3>1. My Individual Scope</h3>
    <form method="POST" action="">
        <label>Describe your specific project contribution:</label><br><br>
        <textarea name="scope_input" rows="5" cols="60"><?php echo $current_scope; ?></textarea>
        <br><br>
        <button type="submit" name="save_scope">Save Scope</button>
    </form>

    <br><hr><br>

    <h3>2. Manage Group Members</h3>
    
    <form method="POST" action="">
        <label>Invite new member (Student ID): </label>
        <input type="text" name="invite_id" required>
        <button type="submit" name="send_invite">Send Invite</button>
    </form>
    
    <br>

    <p><b>Current Group List:</b></p>
    <table border="1" cellpadding="5" width="80%">
        <thead>
            <tr>
                <th align="left">Student ID</th>
                <th align="left">Status</th>
                <th align="left">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if($current_pairing_id > 0) {
                $sql_members = "SELECT * FROM STUDENT_GROUP WHERE fyp_pairingid = '$current_pairing_id'";
                $result_members = $conn->query($sql_members);

                if ($result_members && $result_members->num_rows > 0) {
                    while($row = $result_members->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>" . $row['fyp_studid'] . "</td>";
                        
                        if ($row['fyp_acceptance'] == 1) {
                            echo "<td>Joined</td>";
                        } else {
                            echo "<td>Pending Request</td>";
                        }

                        echo "<td>";
                        if ($row['fyp_acceptance'] == 0 && $row['fyp_studid'] != $current_student_id) {
                            echo '<form method="POST" action="">
                                    <input type="hidden" name="target_id" value="' . $row['fyp_studid'] . '">
                                    <button type="submit" name="accept_member">Accept</button>
                                  </form>';
                        } else {
                            echo "-";
                        }
                        echo "</td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='3'>No members found.</td></tr>";
                }
            } else {
                echo "<tr><td colspan='3'>You are not in a group yet.</td></tr>";
            }
            ?>
        </tbody>
    </table>

</body>
</html>