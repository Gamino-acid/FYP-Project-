<?php
/**
 * REGISTRATION HANDLERS
 * includes/registration_handlers.php
 * Handles all POST actions for student registrations
 */

// Handle POST - Approve Single Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_single'])) {
    $reg_id = $_POST['registration_id'];
    
    $stmt = $conn->prepare("SELECT * FROM pending_registration WHERE id = ?");
    $stmt->bind_param("i", $reg_id);
    $stmt->execute();
    $reg = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($reg) {
        // Generate credentials
        $username = strtolower(str_replace(' ', '', $reg['student_id']));
        $password = bin2hex(random_bytes(4));
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user
        $stmt = $conn->prepare("INSERT INTO user (fyp_username, fyp_password, fyp_usertype) VALUES (?, ?, 'student')");
        $stmt->bind_param("ss", $username, $hashed_password);
        $stmt->execute();
        $user_id = $conn->insert_id;
        $stmt->close();
        
        // Insert student
        $stmt = $conn->prepare("INSERT INTO student (fyp_studfullid, fyp_studname, fyp_email, fyp_contactno, fyp_progid, fyp_academicid, fyp_userid) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssiis", $reg['student_id'], $reg['full_name'], $reg['email'], $reg['phone'], $reg['programme_id'], $reg['academic_year_id'], $user_id);
        $stmt->execute();
        $stmt->close();
        
        // Update status
        $stmt = $conn->prepare("UPDATE pending_registration SET status = 'approved' WHERE id = ?");
        $stmt->bind_param("i", $reg_id);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['imported_credentials'][] = [
            'student_id' => $reg['student_id'],
            'name' => $reg['full_name'],
            'username' => $username,
            'password' => $password
        ];
        
        $message = "Registration approved! Username: $username, Password: $password";
        $message_type = 'success';
    }
}

// Handle POST - Reject Single Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_single'])) {
    $reg_id = $_POST['registration_id'];
    $stmt = $conn->prepare("UPDATE pending_registration SET status = 'rejected' WHERE id = ?");
    $stmt->bind_param("i", $reg_id);
    if ($stmt->execute()) {
        $message = "Registration rejected.";
        $message_type = 'success';
    }
    $stmt->close();
}

// Handle POST - Approve All Pending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_all_pending'])) {
    $pending = [];
    $res = $conn->query("SELECT * FROM pending_registration WHERE status = 'pending'");
    if ($res) { while ($row = $res->fetch_assoc()) { $pending[] = $row; } }
    
    $approved_count = 0;
    $credentials = [];
    
    foreach ($pending as $reg) {
        $username = strtolower(str_replace(' ', '', $reg['student_id']));
        $password = bin2hex(random_bytes(4));
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user
        $stmt = $conn->prepare("INSERT INTO user (fyp_username, fyp_password, fyp_usertype) VALUES (?, ?, 'student')");
        $stmt->bind_param("ss", $username, $hashed_password);
        if ($stmt->execute()) {
            $user_id = $conn->insert_id;
            $stmt->close();
            
            // Insert student
            $stmt = $conn->prepare("INSERT INTO student (fyp_studfullid, fyp_studname, fyp_email, fyp_contactno, fyp_progid, fyp_academicid, fyp_userid) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssiis", $reg['student_id'], $reg['full_name'], $reg['email'], $reg['phone'], $reg['programme_id'], $reg['academic_year_id'], $user_id);
            $stmt->execute();
            $stmt->close();
            
            // Update status
            $stmt = $conn->prepare("UPDATE pending_registration SET status = 'approved' WHERE id = ?");
            $stmt->bind_param("i", $reg['id']);
            $stmt->execute();
            $stmt->close();
            
            $credentials[] = [
                'student_id' => $reg['student_id'],
                'name' => $reg['full_name'],
                'username' => $username,
                'password' => $password
            ];
            $approved_count++;
        }
    }
    
    $_SESSION['imported_credentials'] = $credentials;
    $message = "Successfully approved $approved_count registration(s)!";
    $message_type = 'success';
}

// Handle POST - Import Excel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_excel'])) {
    if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['excel_file']['tmp_name'];
        $academic_id = $_POST['academic_year_id'];
        $programme_id = $_POST['programme_id'];
        
        // Read CSV file
        $handle = fopen($file, 'r');
        $header = fgetcsv($handle); // Skip header row
        
        $imported = 0;
        $errors = [];
        $credentials = [];
        
        while (($data = fgetcsv($handle)) !== FALSE) {
            if (count($data) >= 3) {
                $student_id = trim($data[0]);
                $full_name = trim($data[1]);
                $email = trim($data[2]);
                $phone = isset($data[3]) ? trim($data[3]) : '';
                
                // Check if already exists
                $stmt = $conn->prepare("SELECT id FROM pending_registration WHERE student_id = ?");
                $stmt->bind_param("s", $student_id);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    $errors[] = "Student $student_id already exists";
                    $stmt->close();
                    continue;
                }
                $stmt->close();
                
                // Generate credentials
                $username = strtolower(str_replace(' ', '', $student_id));
                $password = bin2hex(random_bytes(4));
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert user
                $stmt = $conn->prepare("INSERT INTO user (fyp_username, fyp_password, fyp_usertype) VALUES (?, ?, 'student')");
                $stmt->bind_param("ss", $username, $hashed_password);
                $stmt->execute();
                $user_id = $conn->insert_id;
                $stmt->close();
                
                // Insert student
                $stmt = $conn->prepare("INSERT INTO student (fyp_studfullid, fyp_studname, fyp_email, fyp_contactno, fyp_progid, fyp_academicid, fyp_userid) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssiis", $student_id, $full_name, $email, $phone, $programme_id, $academic_id, $user_id);
                $stmt->execute();
                $stmt->close();
                
                $credentials[] = [
                    'student_id' => $student_id,
                    'name' => $full_name,
                    'username' => $username,
                    'password' => $password
                ];
                $imported++;
            }
        }
        fclose($handle);
        
        $_SESSION['import_results'] = [
            'imported' => $imported,
            'errors' => $errors
        ];
        $_SESSION['imported_credentials'] = $credentials;
        
        $message = "Imported $imported student(s) successfully!";
        $message_type = 'success';
    } else {
        $message = "Error uploading file.";
        $message_type = 'error';
    }
}

// Handle POST - Clear Credentials Display
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_credentials'])) {
    unset($_SESSION['imported_credentials']);
    unset($_SESSION['import_results']);
    header("Location: Coordinator_register.php");
    exit;
}
?>