<?php
/**
 * COORDINATOR REPORTS - Excel, CSV, PDF Export
 * 
 * HOW IT WORKS:
 * 1. Excel: Uses HTML table with Excel-specific headers - browser downloads .xls file
 * 2. CSV: Uses PHP fputcsv() function - browser downloads .csv file  
 * 3. PDF: Uses HTML template - user prints to PDF from browser
 * 
 * FILES NEEDED:
 * - This file (coordinator_reports.php) in Coordinator/ folder
 * - db_connect.php in parent folder
 */

session_start();
include("../db_connect.php");

// Check if user is logged in as coordinator
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'coordinator') {
    header("Location: ../Login.php");
    exit;
}

// Get data for dropdowns
$academic_years = [];
$res = $conn->query("SELECT * FROM academic_year ORDER BY fyp_academicid DESC");
if ($res) { while ($row = $res->fetch_assoc()) { $academic_years[] = $row; } }

$supervisors = [];
$res = $conn->query("SELECT fyp_supervisorid, fyp_name FROM supervisor ORDER BY fyp_name");
if ($res) { while ($row = $res->fetch_assoc()) { $supervisors[] = $row; } }

// =====================================================================
// EXCEL EXPORT - When user clicks "Export Excel" button
// =====================================================================
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    
    $report = $_GET['report'] ?? 'students';
    $filename = 'FYP_' . ucfirst($report) . '_Report_' . date('Y-m-d') . '.xls';
    
    // IMPORTANT: These headers tell the browser to download as Excel file
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");
    
    // Start Excel-compatible HTML
    echo '<!DOCTYPE html>';
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head>';
    echo '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
    echo '<style>';
    echo 'table { border-collapse: collapse; width: 100%; }';
    echo 'th, td { border: 1px solid #000000; padding: 8px; text-align: left; }';
    echo 'th { background-color: #8B5CF6; color: #FFFFFF; font-weight: bold; }';
    echo '.title { background-color: #8B5CF6; color: #FFFFFF; font-size: 18px; font-weight: bold; }';
    echo '.subtitle { background-color: #E9D5FF; color: #000000; }';
    echo '.number { text-align: center; }';
    echo '.fail { background-color: #FEE2E2; color: #DC2626; }';
    echo '.pass { background-color: #D1FAE5; color: #059669; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    echo '<table>';
    
    // ===== STUDENT LIST REPORT =====
    if ($report === 'students') {
        echo '<tr><td colspan="8" class="title">STUDENT LIST REPORT</td></tr>';
        echo '<tr><td colspan="8" class="subtitle">Generated: ' . date('Y-m-d H:i:s') . '</td></tr>';
        echo '<tr><td colspan="8"></td></tr>';
        echo '<tr>';
        echo '<th class="number">No.</th>';
        echo '<th>Student ID</th>';
        echo '<th>Full ID</th>';
        echo '<th>Name</th>';
        echo '<th>Programme</th>';
        echo '<th>Email</th>';
        echo '<th>Contact</th>';
        echo '<th>Group Type</th>';
        echo '</tr>';
        
        $sql = "SELECT s.*, p.fyp_progname 
                FROM student s 
                LEFT JOIN programme p ON s.fyp_progid = p.fyp_progid 
                ORDER BY s.fyp_studname";
        $result = $conn->query($sql);
        $no = 1;
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo '<tr>';
                echo '<td class="number">' . $no++ . '</td>';
                echo '<td>' . htmlspecialchars($row['fyp_studid'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['fyp_studfullid'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['fyp_studname'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['fyp_progname'] ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars($row['fyp_email'] ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars($row['fyp_contactno'] ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars($row['fyp_group'] ?? '-') . '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="8">No students found</td></tr>';
        }
    }
    
    // ===== ASSESSMENT MARKS REPORT =====
    elseif ($report === 'marks') {
        echo '<tr><td colspan="8" class="title">ASSESSMENT MARKS REPORT</td></tr>';
        echo '<tr><td colspan="8" class="subtitle">Generated: ' . date('Y-m-d H:i:s') . '</td></tr>';
        echo '<tr><td colspan="8"></td></tr>';
        echo '<tr>';
        echo '<th class="number">No.</th>';
        echo '<th>Student ID</th>';
        echo '<th>Name</th>';
        echo '<th>Project</th>';
        echo '<th class="number">Supervisor Mark</th>';
        echo '<th class="number">Moderator Mark</th>';
        echo '<th class="number">Total Mark</th>';
        echo '<th class="number">Grade</th>';
        echo '</tr>';
        
        $sql = "SELECT tm.*, s.fyp_studname, s.fyp_studfullid, p.fyp_projecttitle 
                FROM total_mark tm 
                LEFT JOIN student s ON tm.fyp_studid = s.fyp_studid 
                LEFT JOIN project p ON tm.fyp_projectid = p.fyp_projectid 
                ORDER BY tm.fyp_totalmark DESC";
        $result = $conn->query($sql);
        $no = 1;
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $mark = floatval($row['fyp_totalmark']);
                $grade = $mark >= 90 ? 'A+' : ($mark >= 80 ? 'A' : ($mark >= 75 ? 'B+' : ($mark >= 70 ? 'B' : ($mark >= 65 ? 'C+' : ($mark >= 60 ? 'C' : ($mark >= 50 ? 'D' : 'F'))))));
                $class = $mark >= 50 ? 'pass' : 'fail';
                
                echo '<tr>';
                echo '<td class="number">' . $no++ . '</td>';
                echo '<td>' . htmlspecialchars($row['fyp_studfullid'] ?? $row['fyp_studid']) . '</td>';
                echo '<td>' . htmlspecialchars($row['fyp_studname'] ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars($row['fyp_projecttitle'] ?? '-') . '</td>';
                echo '<td class="number">' . number_format($row['fyp_totalfinalsupervisor'], 2) . '</td>';
                echo '<td class="number">' . number_format($row['fyp_totalfinalmoderator'], 2) . '</td>';
                echo '<td class="number ' . $class . '">' . number_format($row['fyp_totalmark'], 2) . '</td>';
                echo '<td class="number ' . $class . '">' . $grade . '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="8">No marks found</td></tr>';
        }
    }
    
    // ===== SUPERVISOR WORKLOAD REPORT =====
    elseif ($report === 'workload') {
        echo '<tr><td colspan="6" class="title">SUPERVISOR WORKLOAD REPORT</td></tr>';
        echo '<tr><td colspan="6" class="subtitle">Generated: ' . date('Y-m-d H:i:s') . '</td></tr>';
        echo '<tr><td colspan="6"></td></tr>';
        echo '<tr>';
        echo '<th class="number">No.</th>';
        echo '<th>Supervisor Name</th>';
        echo '<th>Email</th>';
        echo '<th>Programme</th>';
        echo '<th class="number">No. of Students</th>';
        echo '<th class="number">No. of Projects</th>';
        echo '</tr>';
        
        $sql = "SELECT s.fyp_supervisorid, s.fyp_name, s.fyp_email, s.fyp_programme,
                COUNT(DISTINCT fr.fyp_studid) as student_count,
                COUNT(DISTINCT p.fyp_projectid) as project_count
                FROM supervisor s
                LEFT JOIN fyp_registration fr ON s.fyp_supervisorid = fr.fyp_supervisorid
                LEFT JOIN project p ON s.fyp_supervisorid = p.fyp_supervisorid
                GROUP BY s.fyp_supervisorid
                ORDER BY student_count DESC";
        $result = $conn->query($sql);
        $no = 1;
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo '<tr>';
                echo '<td class="number">' . $no++ . '</td>';
                echo '<td>' . htmlspecialchars($row['fyp_name']) . '</td>';
                echo '<td>' . htmlspecialchars($row['fyp_email'] ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars($row['fyp_programme'] ?? '-') . '</td>';
                echo '<td class="number"><strong>' . $row['student_count'] . '</strong></td>';
                echo '<td class="number">' . $row['project_count'] . '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="6">No supervisors found</td></tr>';
        }
    }
    
    // ===== PAIRING LIST REPORT =====
    elseif ($report === 'pairing') {
        echo '<tr><td colspan="6" class="title">STUDENT-SUPERVISOR PAIRING REPORT</td></tr>';
        echo '<tr><td colspan="6" class="subtitle">Generated: ' . date('Y-m-d H:i:s') . '</td></tr>';
        echo '<tr><td colspan="6"></td></tr>';
        echo '<tr>';
        echo '<th class="number">No.</th>';
        echo '<th>Project Title</th>';
        echo '<th>Supervisor</th>';
        echo '<th>Moderator</th>';
        echo '<th>Type</th>';
        echo '<th>Academic Year</th>';
        echo '</tr>';
        
        $sql = "SELECT p.*, pr.fyp_projecttitle, s.fyp_name as supervisor_name,
                (SELECT fyp_name FROM supervisor WHERE fyp_supervisorid = p.fyp_moderatorid) as moderator_name,
                a.fyp_acdyear, a.fyp_intake
                FROM pairing p
                LEFT JOIN project pr ON p.fyp_projectid = pr.fyp_projectid
                LEFT JOIN supervisor s ON p.fyp_supervisorid = s.fyp_supervisorid
                LEFT JOIN academic_year a ON p.fyp_academicid = a.fyp_academicid
                ORDER BY s.fyp_name";
        $result = $conn->query($sql);
        $no = 1;
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo '<tr>';
                echo '<td class="number">' . $no++ . '</td>';
                echo '<td>' . htmlspecialchars($row['fyp_projecttitle'] ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars($row['supervisor_name'] ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars($row['moderator_name'] ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars($row['fyp_type'] ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars(($row['fyp_acdyear'] ?? '') . ' ' . ($row['fyp_intake'] ?? '')) . '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="6">No pairings found</td></tr>';
        }
    }
    
    // ===== FAILED STUDENTS REPORT =====
    elseif ($report === 'failed') {
        $threshold = intval($_GET['threshold'] ?? 50);
        
        echo '<tr><td colspan="7" class="title">FAILED STUDENTS REPORT (Below ' . $threshold . '%)</td></tr>';
        echo '<tr><td colspan="7" class="subtitle">Generated: ' . date('Y-m-d H:i:s') . '</td></tr>';
        echo '<tr><td colspan="7"></td></tr>';
        echo '<tr>';
        echo '<th class="number">No.</th>';
        echo '<th>Student ID</th>';
        echo '<th>Name</th>';
        echo '<th>Programme</th>';
        echo '<th>Project</th>';
        echo '<th class="number">Total Mark</th>';
        echo '<th class="number">Grade</th>';
        echo '</tr>';
        
        $sql = "SELECT tm.*, s.fyp_studname, s.fyp_studfullid, p.fyp_projecttitle, prog.fyp_progname
                FROM total_mark tm
                LEFT JOIN student s ON tm.fyp_studid = s.fyp_studid
                LEFT JOIN project p ON tm.fyp_projectid = p.fyp_projectid
                LEFT JOIN programme prog ON s.fyp_progid = prog.fyp_progid
                WHERE tm.fyp_totalmark < $threshold
                ORDER BY tm.fyp_totalmark ASC";
        $result = $conn->query($sql);
        $no = 1;
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo '<tr>';
                echo '<td class="number">' . $no++ . '</td>';
                echo '<td>' . htmlspecialchars($row['fyp_studfullid'] ?? $row['fyp_studid']) . '</td>';
                echo '<td>' . htmlspecialchars($row['fyp_studname'] ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars($row['fyp_progname'] ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars($row['fyp_projecttitle'] ?? '-') . '</td>';
                echo '<td class="number fail">' . number_format($row['fyp_totalmark'], 2) . '</td>';
                echo '<td class="number fail">F</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="7">No failed students found</td></tr>';
        }
    }
    
    echo '</table>';
    echo '</body>';
    echo '</html>';
    exit; // Stop here - don't show the rest of the page
}

// =====================================================================
// CSV EXPORT - When user clicks "Export CSV" button
// =====================================================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    
    $report = $_GET['report'] ?? 'students';
    $filename = 'FYP_' . ucfirst($report) . '_Report_' . date('Y-m-d') . '.csv';
    
    // IMPORTANT: These headers tell the browser to download as CSV file
    header("Content-Type: text/csv; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Add BOM for Excel to recognize UTF-8 encoding
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    if ($report === 'students') {
        // Header row
        fputcsv($output, ['No', 'Student ID', 'Full ID', 'Name', 'Programme', 'Email', 'Contact', 'Group Type']);
        
        $sql = "SELECT s.*, p.fyp_progname FROM student s LEFT JOIN programme p ON s.fyp_progid = p.fyp_progid ORDER BY s.fyp_studname";
        $result = $conn->query($sql);
        $no = 1;
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                fputcsv($output, [
                    $no++,
                    $row['fyp_studid'] ?? '',
                    $row['fyp_studfullid'] ?? '',
                    $row['fyp_studname'] ?? '',
                    $row['fyp_progname'] ?? '-',
                    $row['fyp_email'] ?? '-',
                    $row['fyp_contactno'] ?? '-',
                    $row['fyp_group'] ?? '-'
                ]);
            }
        }
    }
    elseif ($report === 'marks') {
        fputcsv($output, ['No', 'Student ID', 'Name', 'Project', 'Supervisor Mark', 'Moderator Mark', 'Total Mark', 'Grade']);
        
        $sql = "SELECT tm.*, s.fyp_studname, s.fyp_studfullid, p.fyp_projecttitle FROM total_mark tm LEFT JOIN student s ON tm.fyp_studid = s.fyp_studid LEFT JOIN project p ON tm.fyp_projectid = p.fyp_projectid ORDER BY tm.fyp_totalmark DESC";
        $result = $conn->query($sql);
        $no = 1;
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $mark = floatval($row['fyp_totalmark']);
                $grade = $mark >= 90 ? 'A+' : ($mark >= 80 ? 'A' : ($mark >= 75 ? 'B+' : ($mark >= 70 ? 'B' : ($mark >= 65 ? 'C+' : ($mark >= 60 ? 'C' : ($mark >= 50 ? 'D' : 'F'))))));
                
                fputcsv($output, [
                    $no++,
                    $row['fyp_studfullid'] ?? $row['fyp_studid'],
                    $row['fyp_studname'] ?? '-',
                    $row['fyp_projecttitle'] ?? '-',
                    number_format($row['fyp_totalfinalsupervisor'], 2),
                    number_format($row['fyp_totalfinalmoderator'], 2),
                    number_format($row['fyp_totalmark'], 2),
                    $grade
                ]);
            }
        }
    }
    
    fclose($output);
    exit;
}

// =====================================================================
// PDF EXPORT - Creates printable HTML page
// =====================================================================
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    
    $report = $_GET['report'] ?? 'students';
    
    // Don't set download headers - let user print to PDF
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>FYP Report - <?= ucfirst($report); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 11px; padding: 20px; background: #fff; color: #000; }
        
        .print-instruction {
            background: #FEF3C7;
            border: 2px solid #F59E0B;
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 20px;
            text-align: center;
        }
        .print-instruction h3 { color: #B45309; margin-bottom: 8px; }
        .print-instruction p { color: #92400E; }
        .print-instruction code { background: #FDE68A; padding: 2px 8px; border-radius: 4px; font-weight: bold; }
        .print-btn {
            background: #8B5CF6;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            margin-top: 10px;
        }
        .print-btn:hover { background: #7C3AED; }
        
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #8B5CF6; padding-bottom: 15px; }
        .header h1 { color: #8B5CF6; font-size: 24px; margin-bottom: 5px; }
        .header p { color: #666; font-size: 12px; }
        
        h2 { color: #333; font-size: 18px; margin-bottom: 15px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #8B5CF6; color: white; font-weight: bold; }
        tr:nth-child(even) { background: #F9FAFB; }
        tr:hover { background: #F3F4F6; }
        
        .number { text-align: center; }
        .pass { color: #059669; font-weight: bold; }
        .fail { color: #DC2626; font-weight: bold; }
        
        .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #999; border-top: 1px solid #ddd; padding-top: 15px; }
        
        @media print {
            .print-instruction { display: none; }
            body { padding: 0; }
            .header { border-bottom: 2px solid #000; }
            .header h1 { color: #000; }
            th { background: #ddd !important; color: #000 !important; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>

<div class="print-instruction">
    <h3>📄 How to Save as PDF</h3>
    <p>Press <code>Ctrl + P</code> (Windows) or <code>Cmd + P</code> (Mac) → Select <code>Save as PDF</code> as printer → Click <code>Save</code></p>
    <button class="print-btn" onclick="window.print()">🖨️ Print / Save as PDF</button>
</div>

<div class="header">
    <h1>FYP Management System</h1>
    <p>Report Generated: <?= date('Y-m-d H:i:s'); ?></p>
</div>

<?php
    if ($report === 'students') {
        echo '<h2>Student List Report</h2>';
        echo '<table>';
        echo '<tr><th class="number">No.</th><th>Student ID</th><th>Name</th><th>Programme</th><th>Email</th><th>Contact</th></tr>';
        
        $sql = "SELECT s.*, p.fyp_progname FROM student s LEFT JOIN programme p ON s.fyp_progid = p.fyp_progid ORDER BY s.fyp_studname";
        $result = $conn->query($sql);
        $no = 1;
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo '<tr>';
                echo '<td class="number">' . $no++ . '</td>';
                echo '<td>' . htmlspecialchars($row['fyp_studfullid'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['fyp_studname'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['fyp_progname'] ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars($row['fyp_email'] ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars($row['fyp_contactno'] ?? '-') . '</td>';
                echo '</tr>';
            }
        }
        echo '</table>';
    }
    elseif ($report === 'marks') {
        echo '<h2>Assessment Marks Report</h2>';
        echo '<table>';
        echo '<tr><th class="number">No.</th><th>Student ID</th><th>Name</th><th>Project</th><th class="number">Supervisor</th><th class="number">Moderator</th><th class="number">Total</th><th class="number">Grade</th></tr>';
        
        $sql = "SELECT tm.*, s.fyp_studname, s.fyp_studfullid, p.fyp_projecttitle FROM total_mark tm LEFT JOIN student s ON tm.fyp_studid = s.fyp_studid LEFT JOIN project p ON tm.fyp_projectid = p.fyp_projectid ORDER BY tm.fyp_totalmark DESC";
        $result = $conn->query($sql);
        $no = 1;
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $mark = floatval($row['fyp_totalmark']);
                $grade = $mark >= 90 ? 'A+' : ($mark >= 80 ? 'A' : ($mark >= 75 ? 'B+' : ($mark >= 70 ? 'B' : ($mark >= 65 ? 'C+' : ($mark >= 60 ? 'C' : ($mark >= 50 ? 'D' : 'F'))))));
                $class = $mark >= 50 ? 'pass' : 'fail';
                
                echo '<tr>';
                echo '<td class="number">' . $no++ . '</td>';
                echo '<td>' . htmlspecialchars($row['fyp_studfullid'] ?? $row['fyp_studid']) . '</td>';
                echo '<td>' . htmlspecialchars($row['fyp_studname'] ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars($row['fyp_projecttitle'] ?? '-') . '</td>';
                echo '<td class="number">' . number_format($row['fyp_totalfinalsupervisor'], 2) . '</td>';
                echo '<td class="number">' . number_format($row['fyp_totalfinalmoderator'], 2) . '</td>';
                echo '<td class="number ' . $class . '">' . number_format($row['fyp_totalmark'], 2) . '</td>';
                echo '<td class="number ' . $class . '">' . $grade . '</td>';
                echo '</tr>';
            }
        }
        echo '</table>';
    }
?>

<div class="footer">
    <p>© FYP Management System - This report was automatically generated</p>
    <p>Printed on: <?= date('Y-m-d H:i:s'); ?></p>
</div>

</body>
</html>
    <?php
    exit;
}

// =====================================================================
// MAIN PAGE - Report Generation Interface
// =====================================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports - FYP Portal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background: linear-gradient(135deg, #0f0f1a 0%, #1a1a2e 100%); 
            color: #e2e8f0; 
            min-height: 100vh; 
            padding: 30px; 
        }
        
        .container { max-width: 1400px; margin: 0 auto; }
        
        /* Header */
        .header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 30px; 
            flex-wrap: wrap; 
            gap: 15px; 
        }
        .header h1 { color: #fff; font-size: 1.8rem; }
        .header h1 i { color: #8b5cf6; }
        .back-btn { 
            background: rgba(139, 92, 246, 0.2); 
            color: #a78bfa; 
            padding: 12px 24px; 
            border-radius: 10px; 
            text-decoration: none; 
            display: inline-flex; 
            align-items: center; 
            gap: 8px; 
            transition: all 0.3s;
        }
        .back-btn:hover { background: rgba(139, 92, 246, 0.3); }
        
        /* Info Box */
        .info-box { 
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(139, 92, 246, 0.1)); 
            border: 1px solid rgba(59, 130, 246, 0.3); 
            border-radius: 16px; 
            padding: 20px 25px; 
            margin-bottom: 30px; 
        }
        .info-box h3 { color: #60a5fa; margin-bottom: 12px; font-size: 1.1rem; }
        .info-box ul { list-style: none; }
        .info-box li { 
            color: #94a3b8; 
            font-size: 0.95rem; 
            padding: 8px 0; 
            border-bottom: 1px solid rgba(139, 92, 246, 0.1);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .info-box li:last-child { border-bottom: none; }
        .info-box .icon-excel { color: #10B981; }
        .info-box .icon-csv { color: #F59E0B; }
        .info-box .icon-pdf { color: #EF4444; }
        
        /* Report Grid */
        .report-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); 
            gap: 25px; 
        }
        
        /* Report Card */
        .report-card { 
            background: rgba(26, 26, 46, 0.8); 
            border-radius: 20px; 
            border: 1px solid rgba(139, 92, 246, 0.15); 
            overflow: hidden;
            transition: all 0.3s;
        }
        .report-card:hover { 
            border-color: rgba(139, 92, 246, 0.4); 
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        }
        
        .report-header { 
            padding: 25px; 
            border-bottom: 1px solid rgba(139, 92, 246, 0.1); 
            display: flex; 
            align-items: center; 
            gap: 18px; 
        }
        .report-icon { 
            width: 60px; 
            height: 60px; 
            border-radius: 15px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 1.5rem; 
        }
        .report-icon.purple { background: rgba(139, 92, 246, 0.2); color: #a78bfa; }
        .report-icon.green { background: rgba(16, 185, 129, 0.2); color: #34d399; }
        .report-icon.blue { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
        .report-icon.orange { background: rgba(249, 115, 22, 0.2); color: #fb923c; }
        .report-icon.red { background: rgba(239, 68, 68, 0.2); color: #f87171; }
        
        .report-title h3 { color: #fff; font-size: 1.15rem; margin-bottom: 5px; }
        .report-title p { color: #64748b; font-size: 0.85rem; }
        
        .report-body { padding: 25px; }
        .report-body p { color: #94a3b8; font-size: 0.9rem; margin-bottom: 20px; line-height: 1.6; }
        
        /* Export Buttons */
        .export-label { 
            color: #64748b; 
            font-size: 0.8rem; 
            margin-bottom: 12px; 
            display: flex; 
            align-items: center; 
            gap: 8px; 
        }
        .export-buttons { display: flex; flex-wrap: wrap; gap: 10px; }
        
        .btn { 
            padding: 12px 20px; 
            border: none; 
            border-radius: 10px; 
            cursor: pointer; 
            font-size: 0.9rem; 
            font-weight: 600; 
            display: inline-flex; 
            align-items: center; 
            gap: 8px; 
            text-decoration: none; 
            transition: all 0.3s;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(0,0,0,0.3); }
        
        .btn-excel { background: linear-gradient(135deg, #10B981, #059669); color: white; }
        .btn-csv { background: linear-gradient(135deg, #F59E0B, #D97706); color: white; }
        .btn-pdf { background: linear-gradient(135deg, #EF4444, #DC2626); color: white; }
        
        /* Form elements */
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; color: #a78bfa; font-size: 0.85rem; margin-bottom: 8px; }
        .form-control { 
            width: 100%; 
            padding: 12px; 
            background: rgba(15, 15, 26, 0.6); 
            border: 1px solid rgba(139, 92, 246, 0.2); 
            border-radius: 10px; 
            color: #fff; 
            font-size: 0.95rem;
        }
        .form-control:focus { outline: none; border-color: #8b5cf6; }
        
        /* Footer */
        .footer { 
            text-align: center; 
            margin-top: 40px; 
            padding-top: 20px; 
            border-top: 1px solid rgba(139, 92, 246, 0.1); 
            color: #64748b; 
            font-size: 0.85rem; 
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1><i class="fas fa-file-export"></i> Export Reports</h1>
        <a href="Coordinator_mainpage.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>
    
    <div class="info-box">
        <h3><i class="fas fa-info-circle"></i> How to Download Reports</h3>
        <ul>
            <li>
                <i class="fas fa-file-excel icon-excel"></i>
                <span><strong>Excel (.xls)</strong> - Click the green button. File downloads automatically and opens in Microsoft Excel.</span>
            </li>
            <li>
                <i class="fas fa-file-csv icon-csv"></i>
                <span><strong>CSV (.csv)</strong> - Click the yellow button. Opens in Excel, Google Sheets, or any spreadsheet app.</span>
            </li>
            <li>
                <i class="fas fa-file-pdf icon-pdf"></i>
                <span><strong>PDF</strong> - Click the red button. A page opens - press <kbd>Ctrl+P</kbd> and select "Save as PDF".</span>
            </li>
        </ul>
    </div>
    
    <div class="report-grid">
        <!-- Student List Report -->
        <div class="report-card">
            <div class="report-header">
                <div class="report-icon purple"><i class="fas fa-user-graduate"></i></div>
                <div class="report-title">
                    <h3>Student List</h3>
                    <p>All registered students</p>
                </div>
            </div>
            <div class="report-body">
                <p>Export complete list of all students with their ID, name, programme, email, and contact information.</p>
                <div class="export-label"><i class="fas fa-download"></i> Download Options:</div>
                <div class="export-buttons">
                    <a href="?export=excel&report=students" class="btn btn-excel">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                    <a href="?export=csv&report=students" class="btn btn-csv">
                        <i class="fas fa-file-csv"></i> CSV
                    </a>
                    <a href="?export=pdf&report=students" target="_blank" class="btn btn-pdf">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Assessment Marks Report -->
        <div class="report-card">
            <div class="report-header">
                <div class="report-icon green"><i class="fas fa-chart-line"></i></div>
                <div class="report-title">
                    <h3>Assessment Marks</h3>
                    <p>Student grades and marks</p>
                </div>
            </div>
            <div class="report-body">
                <p>Export student marks including supervisor marks, moderator marks, total marks, and final grades.</p>
                <div class="export-label"><i class="fas fa-download"></i> Download Options:</div>
                <div class="export-buttons">
                    <a href="?export=excel&report=marks" class="btn btn-excel">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                    <a href="?export=csv&report=marks" class="btn btn-csv">
                        <i class="fas fa-file-csv"></i> CSV
                    </a>
                    <a href="?export=pdf&report=marks" target="_blank" class="btn btn-pdf">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Supervisor Workload Report -->
        <div class="report-card">
            <div class="report-header">
                <div class="report-icon orange"><i class="fas fa-briefcase"></i></div>
                <div class="report-title">
                    <h3>Supervisor Workload</h3>
                    <p>Students per supervisor</p>
                </div>
            </div>
            <div class="report-body">
                <p>Export supervisor workload showing number of students and projects assigned to each supervisor.</p>
                <div class="export-label"><i class="fas fa-download"></i> Download Options:</div>
                <div class="export-buttons">
                    <a href="?export=excel&report=workload" class="btn btn-excel">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Pairing List Report -->
        <div class="report-card">
            <div class="report-header">
                <div class="report-icon blue"><i class="fas fa-link"></i></div>
                <div class="report-title">
                    <h3>Pairing List</h3>
                    <p>Student-Supervisor pairs</p>
                </div>
            </div>
            <div class="report-body">
                <p>Export list of all student-supervisor pairings with project titles and moderator assignments.</p>
                <div class="export-label"><i class="fas fa-download"></i> Download Options:</div>
                <div class="export-buttons">
                    <a href="?export=excel&report=pairing" class="btn btn-excel">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Failed Students Report -->
        <div class="report-card">
            <div class="report-header">
                <div class="report-icon red"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="report-title">
                    <h3>Failed Students</h3>
                    <p>Below passing threshold</p>
                </div>
            </div>
            <div class="report-body">
                <div class="form-group">
                    <label>Pass Threshold (%)</label>
                    <input type="number" id="threshold" class="form-control" value="50" min="0" max="100">
                </div>
                <div class="export-label"><i class="fas fa-download"></i> Download Options:</div>
                <div class="export-buttons">
                    <a href="#" onclick="exportFailed('excel')" class="btn btn-excel">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="footer">
        <p>FYP Management System - Report Generation Module</p>
    </div>
</div>

<script>
// Function to export failed students with threshold
function exportFailed(type) {
    var threshold = document.getElementById('threshold').value || 50;
    window.location.href = '?export=' + type + '&report=failed&threshold=' + threshold;
}
</script>

</body>
</html>