<?php
/**
 * COORDINATOR REPORTS - Report Generation System
 * Matches Figures 124-127 exactly
 * Diploma IT FYP - Project 1 only (No Project 2)
 */
session_start();
include("../db_connect.php");

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'coordinator') {
    header("Location: ../Login.php");
    exit;
}

// =====================================================================
// DOWNLOAD FORM 3 (Word Document) - Single Student
// =====================================================================
if (isset($_GET['download_form3']) && isset($_GET['student_id'])) {
    $student_id = $conn->real_escape_string($_GET['student_id']);
    
    // Get student data
    $sql = "SELECT s.fyp_studid, s.fyp_studfullid, s.fyp_studname, prog.fyp_progname, pr.fyp_projecttitle,
                   sup.fyp_name as supervisor_name,
                   (SELECT fyp_name FROM supervisor WHERE fyp_supervisorid = pa.fyp_moderatorid) as moderator_name
            FROM pairing pa
            LEFT JOIN student s ON pa.fyp_studid = s.fyp_studid
            LEFT JOIN programme prog ON s.fyp_progid = prog.fyp_progid
            LEFT JOIN project pr ON pa.fyp_projectid = pr.fyp_projectid
            LEFT JOIN supervisor sup ON pa.fyp_supervisorid = sup.fyp_supervisorid
            WHERE s.fyp_studid = '$student_id' OR s.fyp_studfullid = '$student_id'
            LIMIT 1";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $student = $result->fetch_assoc();
        
        // Create JSON data for the form
        $formData = json_encode([
            'student_name' => $student['fyp_studname'] ?? '',
            'student_id' => $student['fyp_studfullid'] ?? $student['fyp_studid'],
            'programme' => $student['fyp_progname'] ?? 'RSD2',
            'supervisor' => $student['supervisor_name'] ?? '',
            'moderator' => $student['moderator_name'] ?? '',
            'project' => $student['fyp_projecttitle'] ?? '',
            'project_type' => 'application',
            'project_category' => 'Original Idea'
        ]);
        
        $outputFile = '/tmp/Form3_' . preg_replace('/[^a-zA-Z0-9]/', '_', $student['fyp_studname']) . '.docx';
        
        // Generate Word document using Node.js
        $cmd = "cd " . __DIR__ . " && node generate_form3.js '" . addslashes($formData) . "' '$outputFile' 2>&1";
        exec($cmd, $output, $return_var);
        
        if (file_exists($outputFile)) {
            $filename = 'Form3_' . preg_replace('/[^a-zA-Z0-9]/', '_', $student['fyp_studname']) . '.docx';
            header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($outputFile));
            readfile($outputFile);
            unlink($outputFile);
            exit;
        } else {
            // Fallback - generate HTML version
            header('Content-Type: text/html');
            echo generateForm3HTML($student);
            exit;
        }
    }
    
    header("Location: Coordinator_report.php?report=moderation&error=student_not_found");
    exit;
}

// Function to generate Form 3 as HTML (fallback)
function generateForm3HTML($student) {
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Form 3 - <?= htmlspecialchars($student['fyp_studname']); ?></title>
        <style>
            @page { size: landscape; margin: 0.5in; }
            body { font-family: Arial, sans-serif; font-size: 10pt; margin: 20px; }
            .header { display: flex; justify-content: space-between; margin-bottom: 20px; }
            .header-left h1 { font-size: 14pt; margin: 0; }
            .header-left p { margin: 5px 0; }
            .header-right { text-align: right; }
            .header-right h2 { font-size: 12pt; margin: 0; }
            .section-title { background: #d9d9d9; padding: 5px 10px; font-weight: bold; margin-top: 15px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
            th, td { border: 1px solid #000; padding: 6px 8px; text-align: left; }
            th { background: #e7e6e6; }
            .two-col { display: flex; gap: 20px; }
            .two-col > div { flex: 1; }
            .no-border { border: none !important; }
            .signature-section { margin-top: 20px; }
            .signature-section table { border: none; }
            .signature-section td { border: none; padding: 10px 0; }
            .no-print { background: #FEF3C7; padding: 15px; margin-bottom: 20px; text-align: center; border: 2px solid #F59E0B; }
            .no-print button { background: #10B981; color: white; padding: 10px 20px; border: none; cursor: pointer; margin: 5px; }
            @media print { .no-print { display: none; } }
        </style>
    </head>
    <body>
        <div class="no-print">
            <strong>Form 3: Project Proposal Moderation</strong><br>
            <button onclick="window.print()">🖨️ Print</button>
            <button onclick="window.close()">✕ Close</button>
        </div>
        
        <div class="header">
            <div class="header-left">
                <h1>EXAMPLE UNIVERSITY</h1>
                <p>Faculty of Computing and Information Technology</p>
                <p><strong>BACS3403 Project 1</strong></p>
            </div>
            <div class="header-right">
                <h2>Form 3: Project Proposal</h2>
                <h2>Moderation</h2>
            </div>
        </div>
        
        <div class="two-col">
            <div>
                <div class="section-title">1. Project Details</div>
                <table>
                    <tr><td style="width:150px"><strong>Student Name</strong></td><td><?= htmlspecialchars($student['fyp_studname'] ?? ''); ?></td><td style="width:100px"><strong>Programme</strong></td><td style="width:80px"><?= htmlspecialchars($student['fyp_progname'] ?? 'RSD2'); ?></td></tr>
                    <tr><td><strong>Supervisor Name</strong></td><td colspan="3"><?= htmlspecialchars($student['supervisor_name'] ?? ''); ?></td></tr>
                    <tr><td><strong>Moderator Name</strong></td><td colspan="3"><?= htmlspecialchars($student['moderator_name'] ?? ''); ?></td></tr>
                    <tr><td><strong>Project Title/Scope</strong></td><td colspan="3"><?= htmlspecialchars($student['fyp_projecttitle'] ?? ''); ?></td></tr>
                    <tr><td><strong>Project Type</strong></td><td>application</td><td><strong>Project Category</strong></td><td>Original Idea</td></tr>
                </table>
                
                <div class="section-title">2. Project Scope Moderation [to be filled by Moderator] [Please tick (√) if comply]</div>
                <table>
                    <tr><th>Project Requirements</th><th style="width:60px">Comply</th></tr>
                    <tr><td><strong>Research Area and contribution.</strong> The project is within the area of specialization related to the programme of studies. The outcome of the project is able to contribute to the IT practices, target market, or knowledge.</td><td></td></tr>
                    <tr><td><strong>Information Technology content.</strong> The project is IT-related and has substantial amount of IT content.</td><td></td></tr>
                    <tr><td><strong>Technical Skill.</strong> The project requires the students to write substantial amounts of programming codes, or use of IT technical skills with the aid of tools.</td><td></td></tr>
                    <tr><td><strong>Methodology.</strong> The project allows the students to apply some kind of system development or research methodology.</td><td></td></tr>
                    <tr><td><strong>Practicality or Innovativeness.</strong> The project is an industrial project, or should practically represent a 'real-life' case of a company, or it is innovative and the idea is original.</td><td></td></tr>
                </table>
            </div>
            <div>
                <div class="section-title">3. Feedback</div>
                <table>
                    <tr><th>Comments and Changes Recommended (by moderator)</th><th>Actions Taken (by supervisor)</th></tr>
                    <tr><td style="height:150px"></td><td></td></tr>
                </table>
                
                <div class="section-title">4. Assessment</div>
                <table>
                    <tr><th>Item</th><th>Criteria</th><th>Mark Allocation</th><th>Mark</th></tr>
                    <tr><td rowspan="2">Proposal</td><td>Language</td><td style="text-align:center">10</td><td></td></tr>
                    <tr><td>Content</td><td style="text-align:center">20</td><td></td></tr>
                    <tr><td rowspan="2">Chapter 1</td><td>Language</td><td style="text-align:center">10</td><td></td></tr>
                    <tr><td>Content</td><td style="text-align:center">20</td><td></td></tr>
                    <tr><td rowspan="2">Chapter 2</td><td>Content</td><td style="text-align:center">20</td><td></td></tr>
                    <tr><td>Language</td><td style="text-align:center">10</td><td></td></tr>
                    <tr><td rowspan="2">Chapter 3</td><td>Language</td><td style="text-align:center">10</td><td></td></tr>
                    <tr><td>Content</td><td style="text-align:center">20</td><td></td></tr>
                    <tr><td rowspan="2">Chapter 4</td><td>Language</td><td style="text-align:center">10</td><td></td></tr>
                    <tr><td>Content</td><td style="text-align:center">20</td><td></td></tr>
                </table>
                
                <div class="signature-section">
                    <table>
                        <tr><td><strong>Moderated by:</strong></td><td><strong>Received by:</strong></td></tr>
                        <tr><td><br>Moderator's Signature: ________________</td><td><br>Supervisor's Signature: ________________</td></tr>
                        <tr><td>Moderation Date: ________________</td><td>Received Date: ________________</td></tr>
                    </table>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

// Get data for dropdowns
$academic_years = [];
$res = $conn->query("SELECT * FROM academic_year ORDER BY fyp_academicid DESC");
if ($res) { while ($row = $res->fetch_assoc()) { $academic_years[] = $row; } }

$supervisors = [];
$res = $conn->query("SELECT fyp_supervisorid, fyp_name FROM supervisor ORDER BY fyp_name");
if ($res) { while ($row = $res->fetch_assoc()) { $supervisors[] = $row; } }

$programmes = [];
$res = $conn->query("SELECT * FROM programme ORDER BY fyp_progname");
if ($res) { while ($row = $res->fetch_assoc()) { $programmes[] = $row; } }

// =====================================================================
// EXCEL EXPORT
// =====================================================================
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $report = $_GET['report'] ?? 'marks';
    $filter_year = $_GET['year'] ?? '';
    $filter_intake = $_GET['intake'] ?? '';
    $filter_supervisor = $_GET['supervisor'] ?? '';
    $filter_moderator = $_GET['moderator'] ?? '';
    $filter_programme = $_GET['programme'] ?? '';
    $filter_student_id = $_GET['student_id'] ?? '';
    $filter_student_name = $_GET['student_name'] ?? '';
    $filter_supervisors = isset($_GET['supervisors']) ? explode(',', $_GET['supervisors']) : [];
    
    $filename = 'FYP_' . ucfirst($report) . '_Report_' . date('Y-m-d') . '.xls';
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>';
    echo 'table{border-collapse:collapse;width:100%}';
    echo 'th,td{border:1px solid #000;padding:6px 8px;font-family:Arial;font-size:10pt}';
    echo 'th{background-color:#E7E6E6;font-weight:bold}';
    echo '.header{border:none;font-size:12pt;font-weight:bold;text-align:center}';
    echo '.subheader{border:none;font-size:10pt;text-align:center}';
    echo '.title{font-size:11pt;font-weight:bold;background:#D9E2F3}';
    echo '.supervisor-row{background-color:#F2F2F2;font-weight:bold}';
    echo '.number{text-align:center}';
    echo '.grade-a{color:#006100}.grade-b{color:#9C5700}.grade-f{color:#C00000}';
    echo '</style></head><body>';
    
    // University Header
    echo '<table>';
    echo '<tr><td colspan="8" class="header">EXAMPLE UNIVERSITY</td></tr>';
    echo '<tr><td colspan="8" class="subheader">Faculty of Computing and Information Technology</td></tr>';
    echo '<tr><td colspan="8" style="border:none;height:15px"></td></tr>';
    
    // Build WHERE clause
    $where = [];
    if (!empty($filter_year)) $where[] = "a.fyp_acdyear = '" . $conn->real_escape_string($filter_year) . "'";
    if (!empty($filter_intake)) $where[] = "a.fyp_intake = '" . $conn->real_escape_string($filter_intake) . "'";
    if (!empty($filter_supervisor)) $where[] = "pa.fyp_supervisorid = " . intval($filter_supervisor);
    if (!empty($filter_moderator)) $where[] = "pa.fyp_moderatorid = " . intval($filter_moderator);
    if (!empty($filter_programme)) $where[] = "prog.fyp_progid = " . intval($filter_programme);
    if (!empty($filter_student_id)) $where[] = "s.fyp_studfullid LIKE '%" . $conn->real_escape_string($filter_student_id) . "%'";
    if (!empty($filter_student_name)) $where[] = "s.fyp_studname LIKE '%" . $conn->real_escape_string($filter_student_name) . "%'";
    if (!empty($filter_supervisors) && $filter_supervisors[0] !== '') {
        $sup_ids = array_map('intval', $filter_supervisors);
        $where[] = "pa.fyp_supervisorid IN (" . implode(',', $sup_ids) . ")";
    }
    $where_sql = !empty($where) ? " WHERE " . implode(" AND ", $where) : "";
    
    // ===== WORKLOAD REPORT =====
    if ($report === 'workload') {
        echo '<tr><td colspan="6" class="title">SUPERVISORS\' WORKLOAD REPORT</td></tr>';
        echo '<tr><td colspan="6" style="border:none;height:10px"></td></tr>';
        echo '<tr><th>Supervisor Name</th><th>Student ID</th><th>Student Name</th><th>Project Title</th><th class="number">Total Student</th><th class="number">Total Workload</th></tr>';
        
        $sql = "SELECT sup.fyp_supervisorid, sup.fyp_name, COUNT(DISTINCT pa.fyp_studid) as total_students
                FROM supervisor sup
                LEFT JOIN pairing pa ON sup.fyp_supervisorid = pa.fyp_supervisorid
                GROUP BY sup.fyp_supervisorid
                HAVING total_students > 0
                ORDER BY sup.fyp_name";
        $result = $conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            while ($sup = $result->fetch_assoc()) {
                $first_row = true;
                $stud_sql = "SELECT s.fyp_studfullid, s.fyp_studname, pr.fyp_projecttitle
                             FROM pairing pa
                             LEFT JOIN student s ON pa.fyp_studid = s.fyp_studid
                             LEFT JOIN project pr ON pa.fyp_projectid = pr.fyp_projectid
                             WHERE pa.fyp_supervisorid = " . intval($sup['fyp_supervisorid']) . "
                             ORDER BY s.fyp_studname";
                $students = $conn->query($stud_sql);
                $student_count = $students ? $students->num_rows : 0;
                
                if ($students && $students->num_rows > 0) {
                    while ($stud = $students->fetch_assoc()) {
                        echo '<tr>';
                        if ($first_row) {
                            echo '<td rowspan="' . $student_count . '" style="vertical-align:top;font-weight:bold">' . htmlspecialchars($sup['fyp_name']) . '</td>';
                        }
                        echo '<td>' . htmlspecialchars($stud['fyp_studfullid'] ?? '') . '</td>';
                        echo '<td>' . htmlspecialchars($stud['fyp_studname'] ?? '') . '</td>';
                        echo '<td>' . htmlspecialchars($stud['fyp_projecttitle'] ?? '') . '</td>';
                        if ($first_row) {
                            echo '<td rowspan="' . $student_count . '" class="number" style="vertical-align:top">' . $sup['total_students'] . '</td>';
                            echo '<td rowspan="' . $student_count . '" class="number" style="vertical-align:top">' . number_format($sup['total_students'] * 2.50, 2) . '</td>';
                            $first_row = false;
                        }
                        echo '</tr>';
                    }
                }
            }
        }
    }
    
    // ===== ASSESSMENT MARKS =====
    elseif ($report === 'marks') {
        echo '<tr><td colspan="8" class="title">SUMMARY OF STUDENT ASSESSMENT MARKS (GRADES)</td></tr>';
        echo '<tr><td colspan="8" style="border:none;height:10px"></td></tr>';
        echo '<tr><th>Programme</th><th>Student ID</th><th>Student Name</th><th class="number">Project Phase</th>';
        echo '<th class="number">Total Final Supervisor</th><th class="number">Total Final Moderator</th>';
        echo '<th class="number">Total Mark</th><th class="number">Grade</th></tr>';
        
        $sql = "SELECT s.fyp_studid, s.fyp_studfullid, s.fyp_studname, prog.fyp_progname,
                       tm.fyp_totalfinalsupervisor, tm.fyp_totalfinalmoderator, tm.fyp_totalmark
                FROM student s
                LEFT JOIN programme prog ON s.fyp_progid = prog.fyp_progid
                LEFT JOIN pairing pa ON s.fyp_studid = pa.fyp_studid
                LEFT JOIN total_mark tm ON s.fyp_studid = tm.fyp_studid
                $where_sql
                ORDER BY prog.fyp_progname, s.fyp_studname";
        
        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $mark = floatval($row['fyp_totalmark'] ?? 0);
                if ($mark >= 80) { $grade = 'A'; $gc = 'grade-a'; }
                elseif ($mark >= 70) { $grade = 'B'; $gc = 'grade-b'; }
                elseif ($mark >= 60) { $grade = 'C'; $gc = ''; }
                elseif ($mark >= 50) { $grade = 'D'; $gc = ''; }
                else { $grade = 'F'; $gc = 'grade-f'; }
                
                echo '<tr>';
                echo '<td>' . htmlspecialchars($row['fyp_progname'] ?? 'RSD2') . '</td>';
                echo '<td>' . htmlspecialchars($row['fyp_studfullid'] ?? $row['fyp_studid']) . '</td>';
                echo '<td>' . htmlspecialchars($row['fyp_studname'] ?? '') . '</td>';
                echo '<td class="number">1</td>';
                echo '<td class="number">' . ($row['fyp_totalfinalsupervisor'] ? number_format($row['fyp_totalfinalsupervisor'], 2) : '') . '</td>';
                echo '<td class="number">' . ($row['fyp_totalfinalmoderator'] ? number_format($row['fyp_totalfinalmoderator'], 2) : '') . '</td>';
                echo '<td class="number ' . $gc . '">' . ($mark > 0 ? number_format($mark, 2) : '') . '</td>';
                echo '<td class="number ' . $gc . '">' . ($mark > 0 ? $grade : '') . '</td>';
                echo '</tr>';
            }
        }
    }
    
    // ===== PAIRING LIST (Figure 125) =====
    elseif ($report === 'pairing') {
        echo '<tr><td colspan="6" class="title">STUDENT - SUPERVISOR PAIRING LIST</td></tr>';
        echo '<tr><td colspan="6" style="border:none;height:10px"></td></tr>';
        echo '<tr><th>Supervisor</th><th>Moderator Name</th><th>Programme</th><th>Student ID</th><th>Student Name</th><th>Project Title</th></tr>';
        
        $sql = "SELECT s.fyp_studfullid, s.fyp_studname, prog.fyp_progname, pr.fyp_projecttitle,
                       sup.fyp_name as supervisor_name,
                       (SELECT fyp_name FROM supervisor WHERE fyp_supervisorid = pa.fyp_moderatorid) as moderator_name
                FROM pairing pa
                LEFT JOIN student s ON pa.fyp_studid = s.fyp_studid
                LEFT JOIN programme prog ON s.fyp_progid = prog.fyp_progid
                LEFT JOIN project pr ON pa.fyp_projectid = pr.fyp_projectid
                LEFT JOIN supervisor sup ON pa.fyp_supervisorid = sup.fyp_supervisorid
                LEFT JOIN academic_year a ON pa.fyp_academicid = a.fyp_academicid
                $where_sql
                ORDER BY sup.fyp_name, s.fyp_studname";
        $result = $conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($row['supervisor_name'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['moderator_name'] ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars($row['fyp_progname'] ?? 'RSD2') . '</td>';
                echo '<td>' . htmlspecialchars($row['fyp_studfullid'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['fyp_studname'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['fyp_projecttitle'] ?? '') . '</td>';
                echo '</tr>';
            }
        }
    }
    
    // ===== MODERATION FORM =====
    elseif ($report === 'moderation') {
        echo '<tr><td colspan="6" class="title">MODERATION FORM (FORM 3) LIST</td></tr>';
        echo '<tr><td colspan="6" style="border:none;height:10px"></td></tr>';
        echo '<tr><th>Moderator</th><th>Programme</th><th>Project Title</th><th>Student ID</th><th>Student Name</th><th>Download Form</th></tr>';
        
        $sql = "SELECT s.fyp_studfullid, s.fyp_studname, prog.fyp_progname, pr.fyp_projecttitle,
                       (SELECT fyp_name FROM supervisor WHERE fyp_supervisorid = pa.fyp_moderatorid) as moderator_name
                FROM pairing pa
                LEFT JOIN student s ON pa.fyp_studid = s.fyp_studid
                LEFT JOIN programme prog ON s.fyp_progid = prog.fyp_progid
                LEFT JOIN project pr ON pa.fyp_projectid = pr.fyp_projectid
                LEFT JOIN academic_year a ON pa.fyp_academicid = a.fyp_academicid
                $where_sql
                ORDER BY moderator_name, s.fyp_studname";
        $result = $conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($row['moderator_name'] ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars($row['fyp_progname'] ?? 'RSD2') . '</td>';
                echo '<td>' . htmlspecialchars($row['fyp_projecttitle'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['fyp_studfullid'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['fyp_studname'] ?? '') . '</td>';
                echo '<td class="number">Download Form</td>';
                echo '</tr>';
            }
        }
    }
    
    echo '</table></body></html>';
    exit;
}

// =====================================================================
// PDF EXPORT - Same format as Excel with Back button
// =====================================================================
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    $report = $_GET['report'] ?? 'marks';
    $filter_year = $_GET['year'] ?? '';
    $filter_intake = $_GET['intake'] ?? '';
    $filter_supervisor = $_GET['supervisor'] ?? '';
    $filter_moderator = $_GET['moderator'] ?? '';
    $filter_programme = $_GET['programme'] ?? '';
    $filter_student_id = $_GET['student_id'] ?? '';
    $filter_student_name = $_GET['student_name'] ?? '';
    $filter_supervisors = isset($_GET['supervisors']) ? explode(',', $_GET['supervisors']) : [];
    
    // Build WHERE clause
    $where = [];
    if (!empty($filter_year)) $where[] = "a.fyp_acdyear = '" . $conn->real_escape_string($filter_year) . "'";
    if (!empty($filter_intake)) $where[] = "a.fyp_intake = '" . $conn->real_escape_string($filter_intake) . "'";
    if (!empty($filter_supervisor)) $where[] = "pa.fyp_supervisorid = " . intval($filter_supervisor);
    if (!empty($filter_moderator)) $where[] = "pa.fyp_moderatorid = " . intval($filter_moderator);
    if (!empty($filter_programme)) $where[] = "prog.fyp_progid = " . intval($filter_programme);
    if (!empty($filter_student_id)) $where[] = "s.fyp_studfullid LIKE '%" . $conn->real_escape_string($filter_student_id) . "%'";
    if (!empty($filter_student_name)) $where[] = "s.fyp_studname LIKE '%" . $conn->real_escape_string($filter_student_name) . "%'";
    if (!empty($filter_supervisors) && $filter_supervisors[0] !== '') {
        $sup_ids = array_map('intval', $filter_supervisors);
        $where[] = "pa.fyp_supervisorid IN (" . implode(',', $sup_ids) . ")";
    }
    $where_sql = !empty($where) ? " WHERE " . implode(" AND ", $where) : "";
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>FYP Report - <?= ucfirst($report); ?></title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:Arial,sans-serif;font-size:10pt;padding:20px;background:#fff}
        .no-print{background:#f5f5f5;border:1px solid #ddd;padding:15px 20px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;border-radius:4px}
        .no-print .left{display:flex;align-items:center;gap:15px}
        .no-print a{color:#1976D2;text-decoration:none;display:flex;align-items:center;gap:5px;font-size:0.95rem}
        .no-print a:hover{text-decoration:underline}
        .no-print button{background:#10B981;color:#fff;border:none;padding:8px 20px;border-radius:4px;cursor:pointer;font-size:0.9rem}
        .university-header{text-align:center;margin-bottom:20px;padding-bottom:15px;border-bottom:2px solid #333}
        .university-header h1{font-size:16pt;margin-bottom:5px}
        .university-header p{font-size:11pt;color:#555}
        h2{font-size:12pt;margin:15px 0 10px;color:#333}
        table{width:100%;border-collapse:collapse;margin-top:10px}
        th,td{border:1px solid #000;padding:6px 8px}
        th{background:#E7E6E6;font-weight:bold;font-size:9pt}
        .number{text-align:center}
        .grade-a{color:#006100;font-weight:bold}
        .grade-b{color:#9C5700;font-weight:bold}
        .grade-f{color:#C00000;font-weight:bold}
        .supervisor-cell{font-weight:bold;vertical-align:top;background:#f9f9f9}
        @media print{.no-print{display:none}}
    </style>
</head>
<body>
<div class="no-print">
    <div class="left">
        <a href="Coordinator_report.php"><i class="fas fa-arrow-left"></i> ← Back to Report Generation</a>
    </div>
    <div>
        <button onclick="window.print()">🖨️ Print / Save as PDF</button>
    </div>
</div>

<div class="university-header">
    <h1>EXAMPLE UNIVERSITY</h1>
    <p>Faculty of Computing and Information Technology</p>
</div>

<?php
    // ===== WORKLOAD REPORT =====
    if ($report === 'workload') {
        echo '<h2>Supervisors\' Workload Report</h2>';
        echo '<table>';
        echo '<tr><th>Supervisor Name</th><th>Student ID</th><th>Student Name</th><th>Project Title</th><th class="number">Total Student</th><th class="number">Total Workload</th></tr>';
        
        $sql = "SELECT sup.fyp_supervisorid, sup.fyp_name, COUNT(DISTINCT pa.fyp_studid) as total_students
                FROM supervisor sup
                LEFT JOIN pairing pa ON sup.fyp_supervisorid = pa.fyp_supervisorid
                GROUP BY sup.fyp_supervisorid
                HAVING total_students > 0
                ORDER BY sup.fyp_name";
        $result = $conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            while ($sup = $result->fetch_assoc()) {
                $first_row = true;
                $stud_sql = "SELECT s.fyp_studfullid, s.fyp_studname, pr.fyp_projecttitle
                             FROM pairing pa
                             LEFT JOIN student s ON pa.fyp_studid = s.fyp_studid
                             LEFT JOIN project pr ON pa.fyp_projectid = pr.fyp_projectid
                             WHERE pa.fyp_supervisorid = " . intval($sup['fyp_supervisorid']) . "
                             ORDER BY s.fyp_studname";
                $students = $conn->query($stud_sql);
                $student_count = $students ? $students->num_rows : 0;
                
                if ($students && $students->num_rows > 0) {
                    while ($stud = $students->fetch_assoc()) {
                        echo '<tr>';
                        if ($first_row) {
                            echo '<td rowspan="' . $student_count . '" class="supervisor-cell">' . htmlspecialchars($sup['fyp_name']) . '</td>';
                        }
                        echo '<td>' . htmlspecialchars($stud['fyp_studfullid'] ?? '') . '</td>';
                        echo '<td>' . htmlspecialchars($stud['fyp_studname'] ?? '') . '</td>';
                        echo '<td>' . htmlspecialchars($stud['fyp_projecttitle'] ?? '') . '</td>';
                        if ($first_row) {
                            echo '<td rowspan="' . $student_count . '" class="number" style="vertical-align:top">' . $sup['total_students'] . '</td>';
                            echo '<td rowspan="' . $student_count . '" class="number" style="vertical-align:top">' . number_format($sup['total_students'] * 2.50, 2) . '</td>';
                            $first_row = false;
                        }
                        echo '</tr>';
                    }
                }
            }
        }
        echo '</table>';
    }
    
    // ===== ASSESSMENT MARKS =====
    elseif ($report === 'marks') {
        echo '<h2>Summary of Student Assessment Marks (Grades)</h2>';
        echo '<table>';
        echo '<tr><th>Programme</th><th>Student ID</th><th>Student Name</th><th class="number">Project Phase</th>';
        echo '<th class="number">Total Final Supervisor</th><th class="number">Total Final Moderator</th>';
        echo '<th class="number">Total Mark</th><th class="number">Grade</th></tr>';
        
        $sql = "SELECT s.fyp_studid, s.fyp_studfullid, s.fyp_studname, prog.fyp_progname,
                       tm.fyp_totalfinalsupervisor, tm.fyp_totalfinalmoderator, tm.fyp_totalmark
                FROM student s
                LEFT JOIN programme prog ON s.fyp_progid = prog.fyp_progid
                LEFT JOIN pairing pa ON s.fyp_studid = pa.fyp_studid
                LEFT JOIN total_mark tm ON s.fyp_studid = tm.fyp_studid
                $where_sql
                ORDER BY prog.fyp_progname, s.fyp_studname";
        
        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $mark = floatval($row['fyp_totalmark'] ?? 0);
                if ($mark >= 80) { $grade = 'A'; $gc = 'grade-a'; }
                elseif ($mark >= 70) { $grade = 'B'; $gc = 'grade-b'; }
                elseif ($mark >= 60) { $grade = 'C'; $gc = ''; }
                elseif ($mark >= 50) { $grade = 'D'; $gc = ''; }
                else { $grade = 'F'; $gc = 'grade-f'; }
                
                echo '<tr>';
                echo '<td>' . htmlspecialchars($row['fyp_progname'] ?? 'RSD2') . '</td>';
                echo '<td>' . htmlspecialchars($row['fyp_studfullid'] ?? $row['fyp_studid']) . '</td>';
                echo '<td>' . htmlspecialchars($row['fyp_studname'] ?? '') . '</td>';
                echo '<td class="number">1</td>';
                echo '<td class="number">' . ($row['fyp_totalfinalsupervisor'] ? number_format($row['fyp_totalfinalsupervisor'], 2) : '') . '</td>';
                echo '<td class="number">' . ($row['fyp_totalfinalmoderator'] ? number_format($row['fyp_totalfinalmoderator'], 2) : '') . '</td>';
                echo '<td class="number ' . $gc . '">' . ($mark > 0 ? number_format($mark, 2) : '') . '</td>';
                echo '<td class="number ' . $gc . '">' . ($mark > 0 ? $grade : '') . '</td>';
                echo '</tr>';
            }
        }
        echo '</table>';
    }
    
    // ===== PAIRING LIST =====
    elseif ($report === 'pairing') {
        echo '<h2>Student - Supervisor Pairing List</h2>';
        echo '<table>';
        echo '<tr><th>Supervisor</th><th>Moderator Name</th><th>Programme</th><th>Student ID</th><th>Student Name</th><th>Project Title</th></tr>';
        
        $sql = "SELECT s.fyp_studfullid, s.fyp_studname, prog.fyp_progname, pr.fyp_projecttitle,
                       sup.fyp_name as supervisor_name,
                       (SELECT fyp_name FROM supervisor WHERE fyp_supervisorid = pa.fyp_moderatorid) as moderator_name
                FROM pairing pa
                LEFT JOIN student s ON pa.fyp_studid = s.fyp_studid
                LEFT JOIN programme prog ON s.fyp_progid = prog.fyp_progid
                LEFT JOIN project pr ON pa.fyp_projectid = pr.fyp_projectid
                LEFT JOIN supervisor sup ON pa.fyp_supervisorid = sup.fyp_supervisorid
                LEFT JOIN academic_year a ON pa.fyp_academicid = a.fyp_academicid
                $where_sql
                ORDER BY sup.fyp_name, s.fyp_studname";
        $result = $conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($row['supervisor_name'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['moderator_name'] ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars($row['fyp_progname'] ?? 'RSD2') . '</td>';
                echo '<td>' . htmlspecialchars($row['fyp_studfullid'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['fyp_studname'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['fyp_projecttitle'] ?? '') . '</td>';
                echo '</tr>';
            }
        }
        echo '</table>';
    }
    
    // ===== MODERATION =====
    elseif ($report === 'moderation') {
        echo '<h2>Moderation Form (Form 3) List</h2>';
        echo '<table>';
        echo '<tr><th>Moderator</th><th>Programme</th><th>Project Title</th><th>Student ID</th><th>Student Name</th></tr>';
        
        $sql = "SELECT s.fyp_studfullid, s.fyp_studname, prog.fyp_progname, pr.fyp_projecttitle,
                       (SELECT fyp_name FROM supervisor WHERE fyp_supervisorid = pa.fyp_moderatorid) as moderator_name
                FROM pairing pa
                LEFT JOIN student s ON pa.fyp_studid = s.fyp_studid
                LEFT JOIN programme prog ON s.fyp_progid = prog.fyp_progid
                LEFT JOIN project pr ON pa.fyp_projectid = pr.fyp_projectid
                LEFT JOIN academic_year a ON pa.fyp_academicid = a.fyp_academicid
                $where_sql
                ORDER BY moderator_name, s.fyp_studname";
        $result = $conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($row['moderator_name'] ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars($row['fyp_progname'] ?? 'RSD2') . '</td>';
                echo '<td>' . htmlspecialchars($row['fyp_projecttitle'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['fyp_studfullid'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['fyp_studname'] ?? '') . '</td>';
                echo '</tr>';
            }
        }
        echo '</table>';
    }
?>
</body>
</html>
<?php
    exit;
}

// =====================================================================
// VIEW REPORT DATA (AJAX)
// =====================================================================
if (isset($_GET['action']) && $_GET['action'] === 'view') {
    header('Content-Type: application/json');
    $report = $_GET['report'] ?? 'marks';
    $filter_year = $_GET['year'] ?? '';
    $filter_intake = $_GET['intake'] ?? '';
    $filter_supervisor = $_GET['supervisor'] ?? '';
    $filter_moderator = $_GET['moderator'] ?? '';
    $filter_programme = $_GET['programme'] ?? '';
    $filter_student_id = $_GET['student_id'] ?? '';
    $filter_student_name = $_GET['student_name'] ?? '';
    $filter_supervisors = isset($_GET['supervisors']) ? explode(',', $_GET['supervisors']) : [];
    
    // Build WHERE clause
    $where = [];
    if (!empty($filter_year)) $where[] = "a.fyp_acdyear = '" . $conn->real_escape_string($filter_year) . "'";
    if (!empty($filter_intake)) $where[] = "a.fyp_intake = '" . $conn->real_escape_string($filter_intake) . "'";
    if (!empty($filter_supervisor)) $where[] = "pa.fyp_supervisorid = " . intval($filter_supervisor);
    if (!empty($filter_moderator)) $where[] = "pa.fyp_moderatorid = " . intval($filter_moderator);
    if (!empty($filter_programme)) $where[] = "prog.fyp_progid = " . intval($filter_programme);
    if (!empty($filter_student_id)) $where[] = "s.fyp_studfullid LIKE '%" . $conn->real_escape_string($filter_student_id) . "%'";
    if (!empty($filter_student_name)) $where[] = "s.fyp_studname LIKE '%" . $conn->real_escape_string($filter_student_name) . "%'";
    if (!empty($filter_supervisors) && $filter_supervisors[0] !== '') {
        $sup_ids = array_map('intval', $filter_supervisors);
        $where[] = "pa.fyp_supervisorid IN (" . implode(',', $sup_ids) . ")";
    }
    $where_sql = !empty($where) ? " WHERE " . implode(" AND ", $where) : "";
    
    $data = [];
    
    if ($report === 'workload') {
        $sql = "SELECT sup.fyp_supervisorid, sup.fyp_name, COUNT(DISTINCT pa.fyp_studid) as total_students
                FROM supervisor sup
                LEFT JOIN pairing pa ON sup.fyp_supervisorid = pa.fyp_supervisorid
                GROUP BY sup.fyp_supervisorid
                HAVING total_students > 0
                ORDER BY sup.fyp_name";
        $result = $conn->query($sql);
        
        if ($result) {
            while ($sup = $result->fetch_assoc()) {
                $stud_sql = "SELECT s.fyp_studfullid, s.fyp_studname, pr.fyp_projecttitle
                             FROM pairing pa
                             LEFT JOIN student s ON pa.fyp_studid = s.fyp_studid
                             LEFT JOIN project pr ON pa.fyp_projectid = pr.fyp_projectid
                             WHERE pa.fyp_supervisorid = " . intval($sup['fyp_supervisorid']) . "
                             ORDER BY s.fyp_studname";
                $students = $conn->query($stud_sql);
                
                $first = true;
                if ($students && $students->num_rows > 0) {
                    while ($stud = $students->fetch_assoc()) {
                        $data[] = [
                            'supervisor' => $first ? $sup['fyp_name'] : '',
                            'student_id' => $stud['fyp_studfullid'] ?? '',
                            'student_name' => $stud['fyp_studname'] ?? '',
                            'project' => $stud['fyp_projecttitle'] ?? '',
                            'total_students' => $first ? $sup['total_students'] : '',
                            'total_workload' => $first ? number_format($sup['total_students'] * 2.50, 2) : '',
                            'is_first' => $first
                        ];
                        $first = false;
                    }
                }
            }
        }
    }
    elseif ($report === 'marks') {
        $sql = "SELECT s.fyp_studid, s.fyp_studfullid, s.fyp_studname, prog.fyp_progname,
                       tm.fyp_totalfinalsupervisor, tm.fyp_totalfinalmoderator, tm.fyp_totalmark
                FROM student s
                LEFT JOIN programme prog ON s.fyp_progid = prog.fyp_progid
                LEFT JOIN pairing pa ON s.fyp_studid = pa.fyp_studid
                LEFT JOIN academic_year a ON pa.fyp_academicid = a.fyp_academicid
                LEFT JOIN total_mark tm ON s.fyp_studid = tm.fyp_studid
                $where_sql
                ORDER BY prog.fyp_progname, s.fyp_studname";
        $result = $conn->query($sql);
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $mark = floatval($row['fyp_totalmark'] ?? 0);
                if ($mark >= 80) $grade = 'A'; elseif ($mark >= 70) $grade = 'B';
                elseif ($mark >= 60) $grade = 'C'; elseif ($mark >= 50) $grade = 'D'; else $grade = 'F';
                
                $data[] = [
                    'programme' => $row['fyp_progname'] ?? 'RSD2',
                    'student_id' => $row['fyp_studfullid'] ?? $row['fyp_studid'],
                    'student_name' => $row['fyp_studname'] ?? '',
                    'phase' => '1',
                    'supervisor_mark' => $row['fyp_totalfinalsupervisor'] ? number_format($row['fyp_totalfinalsupervisor'], 2) : '',
                    'moderator_mark' => $row['fyp_totalfinalmoderator'] ? number_format($row['fyp_totalfinalmoderator'], 2) : '',
                    'total_mark' => $mark > 0 ? number_format($mark, 2) : '',
                    'grade' => $mark > 0 ? $grade : ''
                ];
            }
        }
    }
    elseif ($report === 'pairing') {
        $sql = "SELECT s.fyp_studfullid, s.fyp_studname, prog.fyp_progname, pr.fyp_projecttitle,
                       sup.fyp_name as supervisor_name,
                       (SELECT fyp_name FROM supervisor WHERE fyp_supervisorid = pa.fyp_moderatorid) as moderator_name
                FROM pairing pa
                LEFT JOIN student s ON pa.fyp_studid = s.fyp_studid
                LEFT JOIN programme prog ON s.fyp_progid = prog.fyp_progid
                LEFT JOIN project pr ON pa.fyp_projectid = pr.fyp_projectid
                LEFT JOIN supervisor sup ON pa.fyp_supervisorid = sup.fyp_supervisorid
                LEFT JOIN academic_year a ON pa.fyp_academicid = a.fyp_academicid
                $where_sql
                ORDER BY sup.fyp_name, s.fyp_studname";
        $result = $conn->query($sql);
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = [
                    'supervisor' => $row['supervisor_name'] ?? '-',
                    'moderator' => $row['moderator_name'] ?? '-',
                    'programme' => $row['fyp_progname'] ?? 'RSD2',
                    'student_id' => $row['fyp_studfullid'] ?? '',
                    'student_name' => $row['fyp_studname'] ?? ''
                ];
            }
        }
    }
    elseif ($report === 'moderation') {
        $sql = "SELECT s.fyp_studfullid, s.fyp_studname, prog.fyp_progname, pr.fyp_projecttitle,
                       (SELECT fyp_name FROM supervisor WHERE fyp_supervisorid = pa.fyp_moderatorid) as moderator_name
                FROM pairing pa
                LEFT JOIN student s ON pa.fyp_studid = s.fyp_studid
                LEFT JOIN programme prog ON s.fyp_progid = prog.fyp_progid
                LEFT JOIN project pr ON pa.fyp_projectid = pr.fyp_projectid
                LEFT JOIN academic_year a ON pa.fyp_academicid = a.fyp_academicid
                $where_sql
                ORDER BY moderator_name, s.fyp_studname";
        $result = $conn->query($sql);
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = [
                    'moderator' => $row['moderator_name'] ?? '-',
                    'programme' => $row['fyp_progname'] ?? 'RSD2',
                    'project' => $row['fyp_projecttitle'] ?? '',
                    'student_id' => $row['fyp_studfullid'] ?? '',
                    'student_name' => $row['fyp_studname'] ?? ''
                ];
            }
        }
    }
    
    echo json_encode(['success' => true, 'data' => $data, 'count' => count($data)]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Report Generation - FYP Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Segoe UI',Arial,sans-serif;background:#f5f5f5;color:#333;min-height:100vh}
        
        .header{background:#fff;padding:15px 30px;border-bottom:1px solid #e0e0e0}
        .header h1{font-size:1.4rem;color:#333}
        .breadcrumb{font-size:0.85rem;color:#666;margin-top:5px}
        .breadcrumb a{color:#1976D2;text-decoration:none}
        
        .container{max-width:1300px;margin:20px auto;padding:0 20px}
        
        .card{background:#fff;border-radius:4px;box-shadow:0 1px 3px rgba(0,0,0,0.1);margin-bottom:20px}
        .card-header{padding:15px 20px;border-bottom:1px solid #e0e0e0}
        .card-header h2{font-size:1rem;color:#333;font-weight:600}
        .card-body{padding:20px}
        
        .form-row{display:flex;flex-wrap:wrap;gap:15px;align-items:flex-end;margin-bottom:15px}
        .form-group{flex:1;min-width:150px}
        .form-group label{display:block;font-size:0.8rem;color:#666;margin-bottom:5px}
        .form-control{width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:4px;font-size:0.9rem}
        .form-control:focus{outline:none;border-color:#1976D2}
        
        .btn{padding:8px 16px;border:none;border-radius:4px;font-size:0.85rem;cursor:pointer;display:inline-flex;align-items:center;gap:5px}
        .btn-primary{background:#1976D2;color:#fff}
        .btn-success{background:#2E7D32;color:#fff}
        .btn-light{background:#f5f5f5;color:#333;border:1px solid #ddd}
        .btn:hover{opacity:0.9}
        
        .filter-toggle{color:#1976D2;cursor:pointer;font-size:0.85rem;display:inline-flex;align-items:center;gap:5px;padding:8px 0}
        .filter-section{display:none;padding:15px;background:#fafafa;border:1px solid #e0e0e0;border-radius:4px;margin-top:10px}
        .filter-section.show{display:block}
        
        .filter-label{font-size:0.8rem;color:#666;margin-bottom:8px;display:block}
        
        .supervisor-list{display:flex;flex-direction:column;gap:5px;max-height:180px;overflow-y:auto;padding:10px;border:1px solid #ddd;border-radius:4px;background:#fff}
        .supervisor-item{display:flex;align-items:center;gap:8px}
        .supervisor-item input{width:16px;height:16px}
        .supervisor-item label{font-size:0.85rem;cursor:pointer}
        
        table{width:100%;border-collapse:collapse;font-size:0.9rem}
        th{padding:12px 15px;text-align:left;color:#666;font-weight:500;font-size:0.75rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #e0e0e0}
        td{padding:12px 15px;text-align:left;border-bottom:1px solid #f0f0f0;color:#333}
        tr:hover{background:#fafafa}
        .text-center{text-align:center}
        
        .supervisor-cell{font-weight:600;background:#f9f9f9;vertical-align:top}
        .grade-a{color:#2E7D32;font-weight:600}
        .grade-b{color:#1976D2;font-weight:600}
        .grade-f{color:#C62828;font-weight:600}
        
        .btn-download{background:#1976D2;color:#fff;padding:4px 10px;border-radius:4px;text-decoration:none;font-size:0.8rem;display:inline-block}
        .btn-download:hover{background:#1565C0}
        
        .empty-state{text-align:center;padding:40px;color:#999}
        .empty-state i{font-size:2rem;margin-bottom:10px}
        
        .action-bar{display:flex;justify-content:flex-end;gap:10px;padding:15px 20px;border-top:1px solid #e0e0e0}
        
        /* New layout styles */
        .report-selector-row{display:flex;align-items:center;gap:15px;flex-wrap:wrap;padding-bottom:20px;border-bottom:1px solid #e0e0e0;margin-bottom:0}
        .report-selector-row .form-group{flex:1;min-width:300px;margin:0}
        .report-selector-row .form-group label{display:block;font-size:0.8rem;color:#666;margin-bottom:5px}
        .btn-row{display:flex;gap:10px;align-items:flex-end}
        .filter-toggle-btn{background:none;border:none;color:#1976D2;cursor:pointer;font-size:0.9rem;padding:8px 12px;display:flex;align-items:center;gap:5px}
        .filter-toggle-btn:hover{text-decoration:underline}
    </style>
</head>
<body>

<div class="header">
    <h1>Report Generation</h1>
    <div class="breadcrumb"><a href="Coordinator_mainpage.php">Home</a> &gt; <span style="color:#1976D2">Report Generation</span></div>
</div>

<div class="container">
    <div class="card">
        <div class="card-header"><h2>Generate Reports</h2></div>
        <div class="card-body">
            <!-- Report Selector Row -->
            <div class="report-selector-row">
                <div class="form-group">
                    <label>Select Report:</label>
                    <select id="reportType" class="form-control" onchange="onReportChange()">
                        <option value="pairing">Student-Supervisor Pairing List</option>
                        <option value="moderation">Moderation Form (Form 3)</option>
                        <option value="workload">Supervisors' Workload</option>
                        <option value="marks">Summary of Student Assessment Marks (Grades)</option>
                    </select>
                </div>
                <div class="btn-row">
                    <button class="filter-toggle-btn" onclick="toggleFilters()"><i class="fas fa-plus" id="filterIcon"></i> Filter Options</button>
                    <button class="btn btn-primary" onclick="viewReport()"><i class="fas fa-eye"></i> View Report</button>
                    <button class="btn btn-success" id="generateFileBtn" onclick="generateFile()"><i class="fas fa-file-excel"></i> Generate File</button>
                </div>
            </div>
            
            <!-- Filter Section -->
            <div id="filterSection" class="filter-section">
                <!-- Common Filters (Figure 125 style) -->
                <div id="commonFilters">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Academic Year</label>
                            <select id="filterYear" class="form-control">
                                <option value="">-- All --</option>
                                <?php foreach ($academic_years as $ay): ?>
                                <option value="<?= $ay['fyp_acdyear']; ?>"><?= $ay['fyp_acdyear']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Intake</label>
                            <select id="filterIntake" class="form-control">
                                <option value="">-- All --</option>
                                <?php foreach (array_unique(array_column($academic_years, 'fyp_intake')) as $i): ?>
                                <option value="<?= $i; ?>"><?= $i; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" id="supervisorFilterGroup">
                            <label>Supervisor</label>
                            <select id="filterSupervisor" class="form-control">
                                <option value="">-- All --</option>
                                <?php foreach ($supervisors as $sup): ?>
                                <option value="<?= $sup['fyp_supervisorid']; ?>"><?= htmlspecialchars($sup['fyp_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Programme</label>
                            <select id="filterProgramme" class="form-control">
                                <option value="">-- All --</option>
                                <?php foreach ($programmes as $p): ?>
                                <option value="<?= $p['fyp_progid']; ?>"><?= htmlspecialchars($p['fyp_progname']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Student ID</label>
                            <input type="text" id="filterStudentId" class="form-control" placeholder="e.g. TP055001">
                        </div>
                        <div class="form-group">
                            <label>Student Name</label>
                            <input type="text" id="filterStudentName" class="form-control" placeholder="e.g. Tan">
                        </div>
                        <div class="form-group" id="moderatorFilterGroup">
                            <label>Moderator</label>
                            <select id="filterModerator" class="form-control">
                                <option value="">-- All --</option>
                                <?php foreach ($supervisors as $sup): ?>
                                <option value="<?= $sup['fyp_supervisorid']; ?>"><?= htmlspecialchars($sup['fyp_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <button class="btn btn-light" onclick="applyFilter()"><i class="fas fa-search"></i> Filter</button>
                        </div>
                    </div>
                </div>
                
                <!-- Supervisor Checkboxes for Marks Report (Figure 127) -->
                <div id="marksFilters" style="display:none;margin-top:15px">
                    <label class="filter-label">Supervisor (select to include in report)</label>
                    <div class="supervisor-list" id="supervisorList">
                        <div class="supervisor-item">
                            <input type="checkbox" id="supAll" onchange="toggleAllSupervisors()" checked>
                            <label for="supAll"><strong>Select All Supervisors</strong></label>
                        </div>
                        <?php foreach ($supervisors as $sup): ?>
                        <div class="supervisor-item">
                            <input type="checkbox" class="sup-check" id="sup<?= $sup['fyp_supervisorid']; ?>" value="<?= $sup['fyp_supervisorid']; ?>" checked>
                            <label for="sup<?= $sup['fyp_supervisorid']; ?>"><?= htmlspecialchars($sup['fyp_name']); ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="margin-top:10px">
                        <button class="btn btn-success" onclick="exportSummaryMark()"><i class="fas fa-download"></i> Export Summary Mark</button>
                    </div>
                </div>
            </div>
            
            <!-- Results Table - Inside same card -->
            <div id="tableContainer" style="padding:20px 0;margin-top:20px">
                <div class="empty-state">
                    <i class="fas fa-table"></i>
                    <p>Click <strong>View Report</strong> to load data</p>
                </div>
            </div>
            
            <!-- Action Bar -->
            <div class="action-bar" id="actionBar" style="display:none;border-top:none;padding:15px 0">
                <a id="downloadExcel" href="#" class="btn btn-success"><i class="fas fa-file-excel"></i> Download Excel</a>
                <a id="downloadPdf" href="#" target="_blank" class="btn btn-light"><i class="fas fa-file-pdf"></i> PDF Preview</a>
            </div>
        </div>
    </div>
</div>

<script>
function toggleFilters() {
    var section = document.getElementById('filterSection');
    section.classList.toggle('show');
    document.getElementById('filterIcon').className = section.classList.contains('show') ? 'fas fa-minus' : 'fas fa-plus';
}

function onReportChange() {
    var report = document.getElementById('reportType').value;
    // Show/hide supervisor dropdown vs checkboxes
    document.getElementById('supervisorFilterGroup').style.display = (report === 'marks') ? 'none' : 'block';
    document.getElementById('marksFilters').style.display = (report === 'marks') ? 'block' : 'none';
    // Show moderator filter only for pairing and moderation
    document.getElementById('moderatorFilterGroup').style.display = (report === 'pairing' || report === 'moderation') ? 'block' : 'none';
    // Auto-refresh report when changing type
    viewReport();
}

function toggleAllSupervisors() {
    var all = document.getElementById('supAll').checked;
    document.querySelectorAll('.sup-check').forEach(function(c) { c.checked = all; });
}

function getSelectedSupervisors() {
    var selected = [];
    document.querySelectorAll('.sup-check:checked').forEach(function(c) { selected.push(c.value); });
    return selected.join(',');
}

function getFilterParams() {
    var params = new URLSearchParams();
    params.append('report', document.getElementById('reportType').value);
    
    var year = document.getElementById('filterYear').value;
    var intake = document.getElementById('filterIntake').value;
    var supervisor = document.getElementById('filterSupervisor').value;
    var moderator = document.getElementById('filterModerator').value;
    var programme = document.getElementById('filterProgramme').value;
    var studentId = document.getElementById('filterStudentId').value;
    var studentName = document.getElementById('filterStudentName').value;
    
    if (year) params.append('year', year);
    if (intake) params.append('intake', intake);
    if (supervisor) params.append('supervisor', supervisor);
    if (moderator) params.append('moderator', moderator);
    if (programme) params.append('programme', programme);
    if (studentId) params.append('student_id', studentId);
    if (studentName) params.append('student_name', studentName);
    
    // For marks report, use supervisor checkboxes
    if (document.getElementById('reportType').value === 'marks') {
        params.append('supervisors', getSelectedSupervisors());
    }
    
    return params;
}

function viewReport() {
    var container = document.getElementById('tableContainer');
    container.innerHTML = '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Loading...</p></div>';
    
    var params = getFilterParams();
    params.append('action', 'view');
    
    fetch('?' + params.toString())
        .then(function(r) { return r.json(); })
        .then(function(result) {
            if (result.success) {
                renderTable(result.data, document.getElementById('reportType').value);
                document.getElementById('actionBar').style.display = 'flex';
                updateDownloadLinks();
            }
        });
}

function applyFilter() {
    viewReport();
}

function renderTable(data, report) {
    var container = document.getElementById('tableContainer');
    if (!data || data.length === 0) {
        container.innerHTML = '<div class="empty-state"><i class="fas fa-inbox"></i><p>No records found</p></div>';
        return;
    }
    
    var html = '<table><thead><tr>';
    
    if (report === 'workload') {
        html += '<th>SUPERVISOR NAME</th><th>STUDENT ID</th><th>STUDENT NAME</th><th>PROJECT TITLE</th><th class="text-center">TOTAL STUDENT</th><th class="text-center">TOTAL WORKLOAD</th>';
    } else if (report === 'marks') {
        html += '<th>PROGRAMME</th><th>STUDENT ID</th><th>STUDENT NAME</th><th class="text-center">PROJECT PHASE</th><th class="text-center">TOTAL FINAL SUPERVISOR</th><th class="text-center">TOTAL FINAL MODERATOR</th><th class="text-center">TOTAL MARK</th><th class="text-center">GRADE</th>';
    } else if (report === 'pairing') {
        html += '<th>SUPERVISOR</th><th>MODERATOR NAME</th><th>PROGRAMME</th><th>STUDENT ID</th><th>STUDENT NAME</th>';
    } else if (report === 'moderation') {
        html += '<th>MODERATOR</th><th>PROGRAMME</th><th>PROJECT TITLE</th><th>STUDENT ID</th><th>STUDENT NAME</th><th class="text-center">DOWNLOAD FORM</th>';
    }
    
    html += '</tr></thead><tbody>';
    
    for (var i = 0; i < data.length; i++) {
        var r = data[i];
        html += '<tr>';
        
        if (report === 'workload') {
            html += '<td' + (r.is_first ? ' class="supervisor-cell"' : '') + '>' + (r.supervisor || '') + '</td>';
            html += '<td>' + (r.student_id || '') + '</td>';
            html += '<td>' + (r.student_name || '') + '</td>';
            html += '<td>' + (r.project || '') + '</td>';
            html += '<td class="text-center"' + (r.is_first ? ' style="font-weight:bold"' : '') + '>' + (r.total_students || '') + '</td>';
            html += '<td class="text-center"' + (r.is_first ? ' style="font-weight:bold"' : '') + '>' + (r.total_workload || '') + '</td>';
        } else if (report === 'marks') {
            var gc = r.grade === 'A' ? 'grade-a' : r.grade === 'B' ? 'grade-b' : r.grade === 'F' ? 'grade-f' : '';
            html += '<td>' + (r.programme || '') + '</td>';
            html += '<td>' + (r.student_id || '') + '</td>';
            html += '<td>' + (r.student_name || '') + '</td>';
            html += '<td class="text-center">1</td>';
            html += '<td class="text-center">' + (r.supervisor_mark || '') + '</td>';
            html += '<td class="text-center">' + (r.moderator_mark || '') + '</td>';
            html += '<td class="text-center ' + gc + '">' + (r.total_mark || '') + '</td>';
            html += '<td class="text-center ' + gc + '">' + (r.grade || '') + '</td>';
        } else if (report === 'pairing') {
            html += '<td>' + (r.supervisor || '') + '</td>';
            html += '<td>' + (r.moderator || '') + '</td>';
            html += '<td>' + (r.programme || '') + '</td>';
            html += '<td>' + (r.student_id || '') + '</td>';
            html += '<td>' + (r.student_name || '') + '</td>';
        } else if (report === 'moderation') {
            html += '<td>' + (r.moderator || '') + '</td>';
            html += '<td>' + (r.programme || '') + '</td>';
            html += '<td>' + (r.project || '') + '</td>';
            html += '<td>' + (r.student_id || '') + '</td>';
            html += '<td>' + (r.student_name || '') + '</td>';
            html += '<td class="text-center"><a href="?download_form3=1&student_id=' + encodeURIComponent(r.student_id || '') + '" class="btn-download" target="_blank">Download Form</a></td>';
        }
        
        html += '</tr>';
    }
    
    html += '</tbody></table>';
    container.innerHTML = html;
}

function updateDownloadLinks() {
    var params = getFilterParams();
    document.getElementById('downloadExcel').href = '?export=excel&' + params.toString();
    document.getElementById('downloadPdf').href = '?export=pdf&' + params.toString();
}

function generateFile() {
    var params = getFilterParams();
    window.location.href = '?export=excel&' + params.toString();
}

function exportSummaryMark() {
    generateFile();
}

function toggleSelectAllMod() {
    var all = document.getElementById('selectAllMod').checked;
    document.querySelectorAll('.mod-check').forEach(function(c) { c.checked = all; });
}

function downloadSelectedForms() {
    var selected = [];
    document.querySelectorAll('.mod-check:checked').forEach(function(c) { 
        selected.push(c.value); 
    });
    
    if (selected.length === 0) {
        alert('Please select at least one student to download forms.');
        return;
    }
    
    // For now, download individually (ZIP would require server-side ZIP creation)
    if (selected.length === 1) {
        window.open('?download_form3=1&student_id=' + encodeURIComponent(selected[0]), '_blank');
    } else {
        alert('Downloading ' + selected.length + ' forms. Each will open in a new tab.');
        selected.forEach(function(id, i) {
            setTimeout(function() {
                window.open('?download_form3=1&student_id=' + encodeURIComponent(id), '_blank');
            }, i * 500);
        });
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    onReportChange();
    viewReport();
});
</script>

</body>
</html>