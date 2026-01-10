<?php
if (file_exists('connect.php')) include 'connect.php';
elseif (file_exists('../connect.php')) include '../connect.php';
else die("<h3>Error: connect.php not found.</h3>");

$sql = "SELECT fyp_studid, fyp_studname, fyp_studfullid FROM student ORDER BY fyp_studname ASC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accept Student</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #eaeff2; display: flex; justify-content: center; padding-top: 50px; }
        .card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); width: 450px; }
        h2 { color: #2c3e50; text-align: center; }
        label { font-weight: bold; color: #555; display: block; margin-top: 15px; }
        select, input { width: 100%; padding: 12px; margin-top: 5px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; }
        .btn { background-color: #28a745; color: white; padding: 12px; border: none; width: 100%; margin-top: 25px; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: bold; }
        .btn:hover { background-color: #218838; }
        .note { font-size: 13px; color: #666; background: #e9ecef; padding: 10px; margin-top: 20px; border-radius: 4px; }
    </style>
</head>
<body>

<div class="card">
    <h2>🎓 Accept Student</h2>
    
    <form action="send_registration.php" method="POST">
        
        <label>Select Student to Accept:</label>
        <select name="student_id" required>
            <option value="">-- Choose Student --</option>
            <?php
            if ($result && $result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    echo "<option value='" . $row['fyp_studid'] . "'>" . $row['fyp_studname'] . " (" . $row['fyp_studfullid'] . ")</option>";
                }
            }
            ?>
        </select>

        <label>Receiver Email (Student's Outlook):</label>
        <!-- Type your student email here -->
        <input type="email" name="test_email" value="chin.foog.sin@student.mmu.edu.my" required>
        
        <div class="note">
            ℹ️ Email will be sent <b>FROM</b> fyp.notificationsystem@gmail.com <b>TO</b> the address above.
        </div>

        <button type="submit" name="send_btn" class="btn">Accept & Notify</button>
    </form>
</div>

</body>
</html>