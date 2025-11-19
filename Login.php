<?php
session_start();

$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "fyp_management";

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);


if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $student_id = $_POST['student_id'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM students WHERE student_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

      
        if (password_verify($password, $user['password'])) {
            $_SESSION['student_id'] = $user['student_id'];
            $_SESSION['fullname'] = $user['fullname'];
            header("Location: Studentdashboard.php");
            exit();
        } else {
            echo "<script>alert('Incorrect password!'); window.history.back();</script>";
        }
    } else {
        echo "<script>alert('Student ID not found!'); window.history.back();</script>";
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FYP Management - Login</title>

   
    <link rel="stylesheet" href="css/Login.css">
</head>
<body>

    <div class="login-wrapper">
        <form class="login-form" action="Login.php" method="post">
            <h2>Project Portal Login</h2>

            <div class="input-group">
                <label for="student_id">Student ID</label>
                <input type="text" id="student_id" name="student_id" placeholder="Enter your Student ID" required>
            </div>

            <div class="input-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
            </div>

            <button type="submit" class="login-button">Log In</button>

            <div class="form-links">
                <a href="Registration.php">Student Registration</a>
                <a href="forgot-password.php">Forgot Password?</a>
            </div>
        </form>
    </div>

</body>
</html>
