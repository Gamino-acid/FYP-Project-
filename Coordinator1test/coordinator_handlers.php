<?php
/**
 * Coordinator Main Page - Part 2
 * Registration Handlers, Excel Import, Assessment Marks, Project CRUD
 */

// --- Save Student Assessment Marks ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_student_assessment'])) {
    $assess_student_id = $_POST['assess_student_id'];
    $initial_work = $_POST['initial_work'] ?? [];
    $final_work = $_POST['final_work'] ?? [];
    $moderator_mark = $_POST['moderator_mark'] ?? [];
    $scaled_mark = $_POST['scaled_mark'] ?? [];
    
    $success_count = 0;
    $error_count = 0;
    
    foreach ($initial_work as $criteria_id => $initial) {
        $initial = floatval($initial);
        $final = floatval($final_work[$criteria_id] ?? 0);
        $mod_mark = floatval($moderator_mark[$criteria_id] ?? 0);
        $scaled = floatval($scaled_mark[$criteria_id] ?? 0);
        
        $values = array_filter([$initial, $final, $mod_mark], function($v) { return $v > 0; });
        $avg_mark = count($values) > 0 ? array_sum($values) / count($values) : 0;
        
        $stmt = $conn->prepare("SELECT fyp_criteriamarkid FROM criteria_mark WHERE fyp_studid = ? AND fyp_criteriaid = ?");
        $stmt->bind_param("si", $assess_student_id, $criteria_id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($existing) {
            $stmt = $conn->prepare("UPDATE criteria_mark SET fyp_initialwork = ?, fyp_finalwork = ?, fyp_markbymoderator = ?, fyp_avgmark = ?, fyp_scaledmark = ? WHERE fyp_criteriamarkid = ?");
            $stmt->bind_param("dddddi", $initial, $final, $mod_mark, $avg_mark, $scaled, $existing['fyp_criteriamarkid']);
        } else {
            $stmt = $conn->prepare("INSERT INTO criteria_mark (fyp_studid, fyp_criteriaid, fyp_initialwork, fyp_finalwork, fyp_markbymoderator, fyp_avgmark, fyp_scaledmark) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("siddddd", $assess_student_id, $criteria_id, $initial, $final, $mod_mark, $avg_mark, $scaled);
        }
        
        if ($stmt->execute()) { $success_count++; } else { $error_count++; }
        $stmt->close();
    }
    
    $total_scaled = 0;
    $res = $conn->query("SELECT SUM(fyp_scaledmark) as total FROM criteria_mark WHERE fyp_studid = '$assess_student_id'");
    if ($res) { $row = $res->fetch_assoc(); $total_scaled = floatval($row['total'] ?? 0); }
    
    $stmt = $conn->prepare("SELECT fyp_studid FROM total_mark WHERE fyp_studid = ?");
    $stmt->bind_param("s", $assess_student_id);
    $stmt->execute();
    $existing_total = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($existing_total) {
        $stmt = $conn->prepare("UPDATE total_mark SET fyp_totalmark = ? WHERE fyp_studid = ?");
        $stmt->bind_param("ds", $total_scaled, $assess_student_id);
    } else {
        $res = $conn->query("SELECT fyp_projectid FROM pairing WHERE fyp_studid = '$assess_student_id'");
        $project_id = $res ? ($res->fetch_assoc()['fyp_projectid'] ?? null) : null;
        $stmt = $conn->prepare("INSERT INTO total_mark (fyp_studid, fyp_projectid, fyp_totalmark) VALUES (?, ?, ?)");
        $stmt->bind_param("sid", $assess_student_id, $project_id, $total_scaled);
    }
    $stmt->execute();
    $stmt->close();
    
    if ($success_count > 0) { $message = "Marks saved successfully! ($success_count criteria updated)"; $message_type = 'success'; }
    else { $message = "Error saving marks."; $message_type = 'error'; }
}

// --- Create Announcement ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_announcement'])) {
    $subject = $_POST['subject'];
    $description = $_POST['description'];
    $receiver = $_POST['receiver'];
    
    $stmt = $conn->prepare("INSERT INTO announcement (fyp_supervisorid, fyp_subject, fyp_description, fyp_receiver, fyp_datecreated) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssss", $user_name, $subject, $description, $receiver);
    if ($stmt->execute()) { $message = "Announcement created successfully!"; $message_type = 'success'; }
    $stmt->close();
}

// --- Delete/Unsend Announcement ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_announcement'])) {
    $ann_id = intval($_POST['announcement_id']);
    $deleted = false;
    $column_names = ['fyp_announcementid', 'announcementid', 'id'];
    
    foreach ($column_names as $col) {
        $stmt = $conn->prepare("DELETE FROM announcement WHERE $col = ?");
        if ($stmt) {
            $stmt->bind_param("i", $ann_id);
            if ($stmt->execute() && $stmt->affected_rows > 0) { $deleted = true; $stmt->close(); break; }
            $stmt->close();
        }
    }
    
    if ($deleted) { $message = "Announcement unsent/deleted successfully!"; $message_type = 'success'; }
    else { $message = "Error deleting announcement."; $message_type = 'error'; }
}

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
        for ($i = 0; $i < 8; $i++) { $generated_password .= $chars[random_int(0, strlen($chars) - 1)]; }
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
                'name' => $reg['studname'], 'student_id' => $reg['studfullid'],
                'email' => $reg['email'], 'password' => $generated_password
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
    if ($stmt->execute()) { $message = "Registration rejected."; $message_type = 'success'; }
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
            for ($i = 0; $i < 8; $i++) { $generated_password .= $chars[random_int(0, strlen($chars) - 1)]; }
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
                $credentials_list[] = ['name' => $reg['studname'], 'email' => $reg['email'], 'password' => $generated_password];
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
        $message .= " <button type='button' class='btn btn-success btn-sm' style='margin-left:15px;' onclick='openModal(\"bulkCredentialsModal\")'><i class='fas fa-eye'></i> View Credentials</button>";
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
        if ($stmt->execute() && $stmt->affected_rows > 0) { $success_count++; }
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
    if (strlen($studid) > 10) { $studid = substr($studid, 0, 10); }
    
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    $generated_password = '';
    for ($i = 0; $i < 8; $i++) { $generated_password .= $chars[random_int(0, strlen($chars) - 1)]; }
    
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
                        'name' => $studname, 'student_id' => $studfullid, 'email' => $email, 'password' => $generated_password
                    ];
                    
                    $message = "<i class='fas fa-check-circle'></i> Student <strong>" . htmlspecialchars($studname) . "</strong> added successfully!
                               <button type='button' class='btn btn-success btn-sm' style='margin-left:15px;' onclick='openModal(\"credentialsModal\")'><i class='fas fa-eye'></i> View Credentials</button>";
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
                        if (count($data) >= 3 && !empty(trim($data[0]))) { $data_rows[] = $data; }
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
                                if (isset($si->t)) { $shared_strings[] = (string)$si->t; }
                                elseif (isset($si->r)) {
                                    $text = '';
                                    foreach ($si->r as $r) { $text .= (string)$r->t; }
                                    $shared_strings[] = $text;
                                } else { $shared_strings[] = ''; }
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
                                        } else { $value = (string)$cell->v; }
                                    }
                                    
                                    if ($col_index >= 0 && $col_index < 5) { $row_data[$col_index] = $value; }
                                }
                                
                                if (!empty(trim($row_data[0]))) { $data_rows[] = $row_data; }
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
                    
                    if (empty($email) || empty($studfullid) || empty($studname)) { $skip_count++; continue; }
                    
                    $studid = preg_replace('/[^A-Za-z0-9]/', '', $studfullid);
                    if (strlen($studid) > 10) { $studid = substr($studid, 0, 10); }
                    
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
                    for ($i = 0; $i < 8; $i++) { $generated_password .= $chars[random_int(0, strlen($chars) - 1)]; }
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
                        
                        $imported_credentials[] = ['name' => $studname, 'student_id' => $studfullid, 'email' => $email, 'password' => $generated_password];
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
            'import_count' => $import_count, 'skip_count' => $skip_count,
            'error_count' => $error_count, 'errors' => $errors
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
    echo '<p style="color:red;"><strong>IMPORTANT:</strong> Share these credentials with students. They should change their password after first login.</p>';
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

// --- Add Assessment Mark ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_mark'])) {
    $studid = $_POST['mark_studid'];
    $sup_mark = floatval($_POST['supervisor_mark']);
    $mod_mark = floatval($_POST['moderator_mark']);
    $total_mark = ($sup_mark + $mod_mark) / 2;
    
    $project_id = !empty($_POST['mark_projectid']) ? intval($_POST['mark_projectid']) : null;
    
    if (!$project_id) {
        $stmt = $conn->prepare("SELECT fyp_projectid FROM pairing WHERE fyp_studid = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $studid);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $project_id = $result['fyp_projectid'] ?? null;
            $stmt->close();
        }
    }
    
    if (!$project_id) {
        $stmt = $conn->prepare("SELECT fyp_projectid FROM student WHERE fyp_studid = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $studid);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $project_id = $result['fyp_projectid'] ?? null;
            $stmt->close();
        }
    }
    
    if (!$project_id) {
        $result = $conn->query("SELECT fyp_projectid FROM project LIMIT 1");
        if ($result && $row = $result->fetch_assoc()) { $project_id = $row['fyp_projectid']; }
        else { $project_id = 1; }
    }
    
    $set_id = 1;
    $academic_id = 1;
    $result = $conn->query("SELECT fyp_setid FROM `set` ORDER BY fyp_setid DESC LIMIT 1");
    if ($result && $row = $result->fetch_assoc()) { $set_id = $row['fyp_setid']; }
    $result = $conn->query("SELECT fyp_academicid FROM academic_year ORDER BY fyp_academicid DESC LIMIT 1");
    if ($result && $row = $result->fetch_assoc()) { $academic_id = $row['fyp_academicid']; }
    
    $stmt = $conn->prepare("INSERT INTO total_mark (fyp_studid, fyp_projectid, fyp_totalfinalsupervisor, fyp_totalfinalmoderator, fyp_totalmark, fyp_setid, fyp_projectphase, fyp_academicid) VALUES (?, ?, ?, ?, ?, ?, 1, ?)");
    
    if ($stmt) {
        $stmt->bind_param("sidddii", $studid, $project_id, $sup_mark, $mod_mark, $total_mark, $set_id, $academic_id);
        if ($stmt->execute()) {
            $message = "Assessment mark added successfully! Total: " . number_format($total_mark, 2) . "%";
            $message_type = 'success';
        } else {
            $message = "Error adding mark: " . $conn->error;
            $message_type = 'error';
        }
        $stmt->close();
    } else {
        $message = "Database error: " . $conn->error;
        $message_type = 'error';
    }
}

// --- Update Assessment Mark ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_mark'])) {
    $studid = $_POST['edit_mark_studid'];
    $sup_mark = floatval($_POST['edit_supervisor_mark']);
    $mod_mark = floatval($_POST['edit_moderator_mark']);
    $total_mark = ($sup_mark + $mod_mark) / 2;
    
    $stmt = $conn->prepare("UPDATE total_mark SET fyp_totalfinalsupervisor = ?, fyp_totalfinalmoderator = ?, fyp_totalmark = ? WHERE fyp_studid = ?");
    
    if ($stmt) {
        $stmt->bind_param("ddds", $sup_mark, $mod_mark, $total_mark, $studid);
        if ($stmt->execute()) {
            $message = "Assessment mark updated successfully! New Total: " . number_format($total_mark, 2) . "%";
            $message_type = 'success';
        } else {
            $message = "Error updating mark: " . $conn->error;
            $message_type = 'error';
        }
        $stmt->close();
    } else {
        $message = "Database error: " . $conn->error;
        $message_type = 'error';
    }
}

// ===================== PROJECT CRUD HANDLERS =====================

// --- Add New Project ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_project'])) {
    $title = trim($_POST['project_title']);
    $description = trim($_POST['project_description'] ?? '');
    $type = $_POST['project_type'];
    $status = $_POST['project_status'];
    $category = $_POST['project_category'] ?? '';
    $supervisor_id = !empty($_POST['supervisor_id']) ? $_POST['supervisor_id'] : null;
    $max_students = intval($_POST['max_students'] ?? 2);
    
    $stmt = $conn->prepare("SELECT fyp_projectid FROM project WHERE fyp_projecttitle = ?");
    if (!$stmt) {
        $message = "Database error: " . $conn->error;
        $message_type = 'error';
    } else {
        $stmt->bind_param("s", $title);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $message = "A project with this title already exists!";
            $message_type = 'error';
        } else {
            $stmt->close();
            
            $stmt = $conn->prepare("INSERT INTO project (fyp_projecttitle, fyp_projectdesc, fyp_projectcat, fyp_projecttype, fyp_projectstatus, fyp_supervisorid, fyp_maxstudent, fyp_datecreated) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            
            if ($stmt) {
                $stmt->bind_param("ssssssi", $title, $description, $category, $type, $status, $supervisor_id, $max_students);
                if ($stmt->execute()) {
                    $message = "Project <strong>" . htmlspecialchars($title) . "</strong> added successfully!";
                    $message_type = 'success';
                } else {
                    $message = "Error adding project: " . $conn->error;
                    $message_type = 'error';
                }
                $stmt->close();
            }
        }
    }
}

// --- Update Project ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_project'])) {
    $project_id = $_POST['project_id'];
    $title = trim($_POST['project_title']);
    $description = trim($_POST['project_description'] ?? '');
    $type = $_POST['project_type'];
    $status = $_POST['project_status'];
    $category = $_POST['project_category'] ?? '';
    $supervisor_id = !empty($_POST['supervisor_id']) ? $_POST['supervisor_id'] : null;
    $max_students = intval($_POST['max_students'] ?? 2);
    
    $stmt = $conn->prepare("SELECT fyp_projectid FROM project WHERE fyp_projecttitle = ? AND fyp_projectid != ?");
    if (!$stmt) {
        $message = "Database error: " . $conn->error;
        $message_type = 'error';
    } else {
        $stmt->bind_param("si", $title, $project_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $message = "Another project with this title already exists!";
            $message_type = 'error';
        } else {
            $stmt->close();
            
            $stmt = $conn->prepare("UPDATE project SET fyp_projecttitle = ?, fyp_projectdesc = ?, fyp_projectcat = ?, fyp_projecttype = ?, fyp_projectstatus = ?, fyp_supervisorid = ?, fyp_maxstudent = ? WHERE fyp_projectid = ?");
            
            if ($stmt) {
                $stmt->bind_param("ssssssii", $title, $description, $category, $type, $status, $supervisor_id, $max_students, $project_id);
                if ($stmt->execute()) {
                    $message = "Project updated successfully!";
                    $message_type = 'success';
                } else {
                    $message = "Error updating project: " . $conn->error;
                    $message_type = 'error';
                }
                $stmt->close();
            }
        }
    }
}

// --- Delete Project ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_project'])) {
    $project_id = $_POST['project_id'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM pairing WHERE fyp_projectid = ?");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result['cnt'] > 0) {
        $message = "Cannot delete this project! It is assigned to " . $result['cnt'] . " student(s).";
        $message_type = 'error';
    } else {
        $stmt->close();
        $stmt = $conn->prepare("DELETE FROM project WHERE fyp_projectid = ?");
        $stmt->bind_param("i", $project_id);
        if ($stmt->execute()) {
            $message = "Project deleted successfully!";
            $message_type = 'success';
        } else {
            $message = "Error deleting project.";
            $message_type = 'error';
        }
    }
    $stmt->close();
}

// Include Part 3
include("Coordinator_layout.php");