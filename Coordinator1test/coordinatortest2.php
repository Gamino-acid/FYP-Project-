<?php
// Part 2 - More Handlers (Registration, Projects, Excel Import)

// --- Approve Student Registration ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_registration'])) {
    $reg_id = $_POST['reg_id'];
    $stmt = $conn->prepare("SELECT * FROM pending_registration WHERE id = ? AND status = 'pending'");
    $stmt->bind_param("i", $reg_id);
    $stmt->execute();
    $reg = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($reg) {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        $generated_password = '';
        for ($i = 0; $i < 8; $i++) {
            $generated_password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $password_hash = password_hash($generated_password, PASSWORD_DEFAULT);
        
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO user (fyp_username, fyp_passwordhash, fyp_usertype, fyp_datecreated) VALUES (?, ?, 'student', NOW())");
            $stmt->bind_param("ss", $reg['email'], $password_hash);
            $stmt->execute();
            $new_user_id = $stmt->insert_id;
            $stmt->close();
            
            $stmt = $conn->prepare("INSERT INTO student (fyp_studid, fyp_studfullid, fyp_studname, fyp_email, fyp_userid) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssi", $reg['studid'], $reg['studfullid'], $reg['studname'], $reg['email'], $new_user_id);
            $stmt->execute();
            $stmt->close();
            
            $stmt = $conn->prepare("UPDATE pending_registration SET status = 'approved', processed_at = NOW(), processed_by = ? WHERE id = ?");
            $stmt->bind_param("ii", $auth_user_id, $reg_id);
            $stmt->execute();
            $stmt->close();
            
            $conn->commit();
            
            $_SESSION['last_approved_credentials'] = [
                'name' => $reg['studname'],
                'student_id' => $reg['studfullid'],
                'email' => $reg['email'],
                'password' => $generated_password
            ];
            
            $message = "<i class='fas fa-check-circle'></i> Registration approved for: <strong>" . htmlspecialchars($reg['studname']) . "</strong>
                       <button type='button' class='btn btn-success btn-sm' style='margin-left:15px;' onclick='openModal(\"credentialsModal\")'>
                       <i class='fas fa-eye'></i> View Credentials</button>";
            $message_type = 'success';
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// --- Reject Student Registration ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_registration'])) {
    $reg_id = $_POST['reg_id'];
    $remarks = $_POST['remarks'] ?? '';
    $stmt = $conn->prepare("UPDATE pending_registration SET status = 'rejected', processed_at = NOW(), processed_by = ?, remarks = ? WHERE id = ?");
    $stmt->bind_param("isi", $auth_user_id, $remarks, $reg_id);
    if ($stmt->execute()) {
        $message = "Registration rejected.";
        $message_type = 'success';
    }
    $stmt->close();
}

// --- Bulk Approve Registrations ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_approve']) && !empty($_POST['selected_regs'])) {
    $selected = $_POST['selected_regs'];
    $success_count = 0;
    $error_count = 0;
    $credentials_list = [];
    
    foreach ($selected as $reg_id) {
        $stmt = $conn->prepare("SELECT * FROM pending_registration WHERE id = ? AND status = 'pending'");
        $stmt->bind_param("i", $reg_id);
        $stmt->execute();
        $reg = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($reg) {
            $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
            $generated_password = '';
            for ($i = 0; $i < 8; $i++) {
                $generated_password .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $password_hash = password_hash($generated_password, PASSWORD_DEFAULT);
            
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("INSERT INTO user (fyp_username, fyp_passwordhash, fyp_usertype, fyp_datecreated) VALUES (?, ?, 'student', NOW())");
                $stmt->bind_param("ss", $reg['email'], $password_hash);
                $stmt->execute();
                $new_user_id = $stmt->insert_id;
                $stmt->close();
                
                $stmt = $conn->prepare("INSERT INTO student (fyp_studid, fyp_studfullid, fyp_studname, fyp_email, fyp_userid) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssi", $reg['studid'], $reg['studfullid'], $reg['studname'], $reg['email'], $new_user_id);
                $stmt->execute();
                $stmt->close();
                
                $stmt = $conn->prepare("UPDATE pending_registration SET status = 'approved', processed_at = NOW(), processed_by = ? WHERE id = ?");
                $stmt->bind_param("ii", $auth_user_id, $reg_id);
                $stmt->execute();
                $stmt->close();
                
                $conn->commit();
                $success_count++;
                $credentials_list[] = [
                    'name' => $reg['studname'],
                    'email' => $reg['email'],
                    'password' => $generated_password
                ];
            } catch (Exception $e) {
                $conn->rollback();
                $error_count++;
            }
        }
    }
    
    $_SESSION['bulk_approved_credentials'] = $credentials_list;
    
    $names = array_column($credentials_list, 'name');
    $names_display = count($names) <= 3 ? implode(', ', $names) : implode(', ', array_slice($names, 0, 3)) . ' +' . (count($names) - 3) . ' more';
    
    $message = "<i class='fas fa-check-circle'></i> Approved <strong>$success_count</strong> student(s): $names_display";
    if ($success_count > 0) {
        $message .= " <button type='button' class='btn btn-success btn-sm' style='margin-left:15px;' onclick='openModal(\"bulkCredentialsModal\")'>
                     <i class='fas fa-eye'></i> View Credentials</button>";
    }
    $message_type = $error_count > 0 ? 'warning' : 'success';
}

// --- Bulk Reject Registrations ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_reject']) && !empty($_POST['selected_regs'])) {
    $selected = $_POST['selected_regs'];
    $remarks = $_POST['bulk_remarks'] ?? 'Rejected by coordinator';
    $success_count = 0;
    
    foreach ($selected as $reg_id) {
        $stmt = $conn->prepare("UPDATE pending_registration SET status = 'rejected', processed_at = NOW(), processed_by = ?, remarks = ? WHERE id = ? AND status = 'pending'");
        $stmt->bind_param("isi", $auth_user_id, $remarks, $reg_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $success_count++;
        }
        $stmt->close();
    }
    $message = "Bulk reject completed: $success_count rejected.";
    $message_type = 'success';
}

// --- Manually Add Student ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student_manual'])) {
    $email = trim($_POST['new_email']);
    $studfullid = trim($_POST['new_studfullid']);
    $studname = trim($_POST['new_studname']);
    
    $studid = preg_replace('/[^A-Za-z0-9]/', '', $studfullid);
    if (strlen($studid) > 10) {
        $studid = substr($studid, 0, 10);
    }
    
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    $generated_password = '';
    for ($i = 0; $i < 8; $i++) {
        $generated_password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    
    if (empty($email) || empty($studfullid) || empty($studname)) {
        $message = "Please fill in all required fields.";
        $message_type = 'error';
    } else {
        $stmt = $conn->prepare("SELECT fyp_userid FROM user WHERE fyp_username = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $message = "A user with this email already exists.";
            $message_type = 'error';
        } else {
            $stmt2 = $conn->prepare("SELECT fyp_studid FROM student WHERE fyp_studfullid = ?");
            $stmt2->bind_param("s", $studfullid);
            $stmt2->execute();
            $result2 = $stmt2->get_result();
            
            if ($result2->num_rows > 0) {
                $message = "A student with this ID already exists.";
                $message_type = 'error';
            } else {
                $conn->begin_transaction();
                try {
                    $password_hash = password_hash($generated_password, PASSWORD_DEFAULT);
                    $stmt3 = $conn->prepare("INSERT INTO user (fyp_username, fyp_passwordhash, fyp_usertype, fyp_datecreated) VALUES (?, ?, 'student', NOW())");
                    $stmt3->bind_param("ss", $email, $password_hash);
                    $stmt3->execute();
                    $new_user_id = $stmt3->insert_id;
                    $stmt3->close();
                    
                    $stmt4 = $conn->prepare("INSERT INTO student (fyp_studid, fyp_studfullid, fyp_studname, fyp_email, fyp_userid) VALUES (?, ?, ?, ?, ?)");
                    $stmt4->bind_param("ssssi", $studid, $studfullid, $studname, $email, $new_user_id);
                    $stmt4->execute();
                    $stmt4->close();
                    
                    $stmt5 = $conn->prepare("INSERT INTO pending_registration (email, studfullid, studid, studname, status, created_at, processed_at, processed_by) VALUES (?, ?, ?, ?, 'approved', NOW(), NOW(), ?)");
                    $stmt5->bind_param("ssssi", $email, $studfullid, $studid, $studname, $auth_user_id);
                    $stmt5->execute();
                    $stmt5->close();
                    
                    $conn->commit();
                    
                    $_SESSION['last_approved_credentials'] = [
                        'name' => $studname,
                        'student_id' => $studfullid,
                        'email' => $email,
                        'password' => $generated_password
                    ];
                    
                    $message = "<i class='fas fa-check-circle'></i> Student <strong>" . htmlspecialchars($studname) . "</strong> added successfully!
                               <button type='button' class='btn btn-success btn-sm' style='margin-left:15px;' onclick='openModal(\"credentialsModal\")'>
                               <i class='fas fa-eye'></i> View Credentials</button>";
                    $message_type = 'success';
                } catch (Exception $e) {
                    $conn->rollback();
                    $message = "Error adding student: " . $e->getMessage();
                    $message_type = 'error';
                }
            }
            $stmt2->close();
        }
        $stmt->close();
    }
}

// --- Import Excel Students ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_excel'])) {
    if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['excel_file']['tmp_name'];
        $filename = $_FILES['excel_file']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (!in_array($ext, ['csv', 'xls', 'xlsx'])) {
            $message = "Invalid file type. Please upload CSV, XLS, or XLSX file.";
            $message_type = 'error';
        } else {
            $import_count = 0;
            $skip_count = 0;
            $error_count = 0;
            $errors = [];
            $imported_credentials = [];
            $data_rows = [];
            
            if ($ext === 'csv') {
                if (($handle = fopen($file, "r")) !== FALSE) {
                    $header = fgetcsv($handle);
                    while (($data = fgetcsv($handle)) !== FALSE) {
                        if (count($data) >= 3 && !empty(trim($data[0]))) {
                            $data_rows[] = $data;
                        }
                    }
                    fclose($handle);
                }
            } else if ($ext === 'xlsx') {
                $zip = new ZipArchive();
                if ($zip->open($file) === TRUE) {
                    $shared_strings = [];
                    $xml_strings = $zip->getFromName('xl/sharedStrings.xml');
                    if ($xml_strings) {
                        $xml_strings = preg_replace('/xmlns[^=]*="[^"]*"/i', '', $xml_strings);
                        $xml = @simplexml_load_string($xml_strings);
                        if ($xml) {
                            foreach ($xml->si as $si) {
                                if (isset($si->t)) {
                                    $shared_strings[] = (string)$si->t;
                                } elseif (isset($si->r)) {
                                    $text = '';
                                    foreach ($si->r as $r) {
                                        $text .= (string)$r->t;
                                    }
                                    $shared_strings[] = $text;
                                } else {
                                    $shared_strings[] = '';
                                }
                            }
                        }
                    }
                    
                    $xml_sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
                    if ($xml_sheet) {
                        $xml_sheet = preg_replace('/xmlns[^=]*="[^"]*"/i', '', $xml_sheet);
                        $xml = @simplexml_load_string($xml_sheet);
                        if ($xml && isset($xml->sheetData)) {
                            $row_index = 0;
                            foreach ($xml->sheetData->row as $row) {
                                $row_index++;
                                if ($row_index == 1) continue;
                                
                                $row_data = ['', '', '', '', ''];
                                
                                foreach ($row->c as $cell) {
                                    $cell_ref = (string)$cell['r'];
                                    $col_letter = preg_replace('/[0-9]/', '', $cell_ref);
                                    $col_index = ord(strtoupper($col_letter)) - ord('A');
                                    
                                    $type = (string)$cell['t'];
                                    $value = '';
                                    
                                    if (isset($cell->v)) {
                                        if ($type === 's') {
                                            $idx = (int)$cell->v;
                                            $value = isset($shared_strings[$idx]) ? $shared_strings[$idx] : '';
                                        } else {
                                            $value = (string)$cell->v;
                                        }
                                    }
                                    
                                    if ($col_index >= 0 && $col_index < 5) {
                                        $row_data[$col_index] = $value;
                                    }
                                }
                                
                                if (!empty(trim($row_data[0]))) {
                                    $data_rows[] = $row_data;
                                }
                            }
                        }
                    }
                    $zip->close();
                }
            } else {
                $message = "XLS format is not supported. Please save as XLSX or CSV.";
                $message_type = 'warning';
            }
            
            if (!empty($data_rows)) {
                $row_num = 1;
                foreach ($data_rows as $data) {
                    $row_num++;
                    
                    $email = trim($data[0] ?? '');
                    $studfullid = trim($data[1] ?? '');
                    $studname = trim($data[2] ?? '');
                    $programme = trim($data[3] ?? '');
                    $contact = trim($data[4] ?? '');
                    
                    $contact = ltrim($contact, "'");
                    
                    if (empty($email) || empty($studfullid) || empty($studname)) {
                        $skip_count++;
                        continue;
                    }
                    
                    $studid = preg_replace('/[^A-Za-z0-9]/', '', $studfullid);
                    if (strlen($studid) > 10) {
                        $studid = substr($studid, 0, 10);
                    }
                    
                    $stmt = $conn->prepare("SELECT fyp_userid FROM user WHERE fyp_username = ?");
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    if ($stmt->get_result()->num_rows > 0) {
                        $skip_count++;
                        $errors[] = "Row $row_num: Email '$email' already exists";
                        $stmt->close();
                        continue;
                    }
                    $stmt->close();
                    
                    $stmt = $conn->prepare("SELECT fyp_studid FROM student WHERE fyp_studfullid = ?");
                    $stmt->bind_param("s", $studfullid);
                    $stmt->execute();
                    if ($stmt->get_result()->num_rows > 0) {
                        $skip_count++;
                        $errors[] = "Row $row_num: Student ID '$studfullid' already exists";
                        $stmt->close();
                        continue;
                    }
                    $stmt->close();
                    
                    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
                    $generated_password = '';
                    for ($i = 0; $i < 8; $i++) {
                        $generated_password .= $chars[random_int(0, strlen($chars) - 1)];
                    }
                    $password_hash = password_hash($generated_password, PASSWORD_DEFAULT);
                    
                    $prog_id = null;
                    if (!empty($programme)) {
                        $stmt = $conn->prepare("SELECT fyp_progid FROM programme WHERE fyp_progname = ? OR fyp_prognamefull LIKE ?");
                        $prog_like = "%$programme%";
                        $stmt->bind_param("ss", $programme, $prog_like);
                        $stmt->execute();
                        $prog_result = $stmt->get_result()->fetch_assoc();
                        $prog_id = $prog_result['fyp_progid'] ?? null;
                        $stmt->close();
                    }
                    
                    $conn->begin_transaction();
                    try {
                        $stmt = $conn->prepare("INSERT INTO user (fyp_username, fyp_passwordhash, fyp_usertype, fyp_datecreated) VALUES (?, ?, 'student', NOW())");
                        $stmt->bind_param("ss", $email, $password_hash);
                        $stmt->execute();
                        $new_user_id = $stmt->insert_id;
                        $stmt->close();
                        
                        $stmt = $conn->prepare("INSERT INTO student (fyp_studid, fyp_studfullid, fyp_studname, fyp_email, fyp_contactno, fyp_progid, fyp_userid) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("sssssii", $studid, $studfullid, $studname, $email, $contact, $prog_id, $new_user_id);
                        $stmt->execute();
                        $stmt->close();
                        
                        $stmt = $conn->prepare("INSERT INTO pending_registration (email, studfullid, studid, studname, programme_id, status, created_at, processed_at, processed_by) VALUES (?, ?, ?, ?, ?, 'approved', NOW(), NOW(), ?)");
                        $stmt->bind_param("ssssii", $email, $studfullid, $studid, $studname, $prog_id, $auth_user_id);
                        $stmt->execute();
                        $stmt->close();
                        
                        $conn->commit();
                        $import_count++;
                        
                        $imported_credentials[] = [
                            'name' => $studname,
                            'student_id' => $studfullid,
                            'email' => $email,
                            'password' => $generated_password
                        ];
                    } catch (Exception $e) {
                        $conn->rollback();
                        $error_count++;
                        $errors[] = "Row $row_num: " . $e->getMessage();
                    }
                }
                
                $_SESSION['imported_credentials'] = $imported_credentials;
                $message_type = ($error_count > 0) ? 'warning' : 'success';
            } elseif (empty($message)) {
                $message = "No valid data found in the file. Please check the format.";
                $message_type = 'error';
            }
        }
    } else {
        $message = "Please select a file to upload.";
        $message_type = 'error';
    }
    
    if (isset($import_count)) {
        $_SESSION['import_results'] = [
            'import_count' => $import_count,
            'skip_count' => $skip_count,
            'error_count' => $error_count,
            'errors' => $errors
        ];
    }
}

// --- Download Imported Credentials ---
if (isset($_GET['download_credentials']) && !empty($_SESSION['imported_credentials'])) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="student_credentials_' . date('Y-m-d_His') . '.xls"');
    header('Cache-Control: max-age=0');
    
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>';
    echo '<h2>FYP System - Student Login Credentials</h2>';
    echo '<p>Generated: ' . date('F j, Y g:i A') . '</p>';
    echo '<p style="color:red;"><strong>IMPORTANT:</strong> Share these credentials with students.</p>';
    echo '<table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;">';
    echo '<tr style="background:#8B5CF6;color:white;"><th>No.</th><th>Student Name</th><th>Student ID</th><th>Email (Username)</th><th>Temporary Password</th></tr>';
    
    $no = 1;
    foreach ($_SESSION['imported_credentials'] as $cred) {
        echo '<tr>';
        echo '<td>' . $no++ . '</td>';
        echo '<td>' . htmlspecialchars($cred['name']) . '</td>';
        echo '<td>' . htmlspecialchars($cred['student_id']) . '</td>';
        echo '<td>' . htmlspecialchars($cred['email']) . '</td>';
        echo '<td style="font-family:monospace;background:#fffde7;">' . htmlspecialchars($cred['password']) . '</td>';
        echo '</tr>';
    }
    
    echo '</table></body></html>';
    unset($_SESSION['imported_credentials']);
    exit;
}

// Include Part 3
include("Coordinator_mainpage_part3.php");