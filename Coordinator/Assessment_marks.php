<?php
/**
 * Assessment Marks Management
 * FYP Management System for Diploma IT
 * 
 * Features:
 * - View all students marks
 * - Mark individual student assessment
 * - Filter by Academic Year, Intake, Student ID, Name
 * - Select all / individual students
 * - View overall marks breakdown
 * - Export marks
 */

session_start();
include("../db_connect.php");

// Authentication check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header("Location: ../Login.php");
    exit;
}

$user_role = $_SESSION['user_role'];
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['username'] ?? 'User';

// Only coordinator and supervisor can access
if (!in_array($user_role, ['coordinator', 'supervisor'])) {
    header("Location: ../Login.php");
    exit;
}

$img_src = "https://ui-avatars.com/api/?name=" . urlencode($user_name) . "&background=7C3AED&color=fff";

// Current action/tab
$action = $_GET['action'] ?? 'list';
$message = '';
$message_type = '';

// ===================== HANDLERS =====================

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
        
        // Calculate average
        $values = array_filter([$initial, $final, $mod_mark], function($v) { return $v > 0; });
        $avg_mark = count($values) > 0 ? array_sum($values) / count($values) : 0;
        
        // Check if mark exists
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
        
        if ($stmt->execute()) {
            $success_count++;
        } else {
            $error_count++;
        }
        $stmt->close();
    }
    
    // Calculate total mark
    $total_scaled = 0;
    $res = $conn->query("SELECT SUM(fyp_scaledmark) as total FROM criteria_mark WHERE fyp_studid = '$assess_student_id'");
    if ($res) {
        $row = $res->fetch_assoc();
        $total_scaled = floatval($row['total'] ?? 0);
    }
    
    // Get supervisor and moderator totals
    $total_supervisor = 0;
    $total_moderator = 0;
    $res = $conn->query("SELECT SUM(fyp_finalwork) as sup_total, SUM(fyp_markbymoderator) as mod_total FROM criteria_mark WHERE fyp_studid = '$assess_student_id'");
    if ($res) {
        $row = $res->fetch_assoc();
        $total_supervisor = floatval($row['sup_total'] ?? 0);
        $total_moderator = floatval($row['mod_total'] ?? 0);
    }
    
    // Update total_mark table
    $stmt = $conn->prepare("SELECT fyp_studid FROM total_mark WHERE fyp_studid = ?");
    $stmt->bind_param("s", $assess_student_id);
    $stmt->execute();
    $existing_total = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Get project_id and set_id
    $project_id = null;
    $set_id = null;
    $res = $conn->query("SELECT pa.fyp_projectid, s.fyp_setid FROM pairing pa 
                         LEFT JOIN `set` s ON pa.fyp_academicid = s.fyp_academicid 
                         WHERE pa.fyp_studid = '$assess_student_id' LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) {
        $project_id = $row['fyp_projectid'];
        $set_id = $row['fyp_setid'];
    }
    
    if ($existing_total) {
        $stmt = $conn->prepare("UPDATE total_mark SET fyp_totalmark = ?, fyp_totalfinalsupervisor = ?, fyp_totalfinalmoderator = ? WHERE fyp_studid = ?");
        $stmt->bind_param("ddds", $total_scaled, $total_supervisor, $total_moderator, $assess_student_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO total_mark (fyp_studid, fyp_projectid, fyp_setid, fyp_totalmark, fyp_totalfinalsupervisor, fyp_totalfinalmoderator) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("siiddd", $assess_student_id, $project_id, $set_id, $total_scaled, $total_supervisor, $total_moderator);
    }
    $stmt->execute();
    $stmt->close();
    
    if ($success_count > 0) {
        $message = "Marks saved successfully! ($success_count criteria updated)";
        $message_type = 'success';
    } else {
        $message = "Error saving marks.";
        $message_type = 'error';
    }
}

// --- Delete Student Marks ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_student_marks'])) {
    $delete_student_id = $_POST['delete_student_id'];
    
    // Delete criteria marks
    $stmt = $conn->prepare("DELETE FROM criteria_mark WHERE fyp_studid = ?");
    $stmt->bind_param("s", $delete_student_id);
    $stmt->execute();
    $stmt->close();
    
    // Delete total mark
    $stmt = $conn->prepare("DELETE FROM total_mark WHERE fyp_studid = ?");
    $stmt->bind_param("s", $delete_student_id);
    $stmt->execute();
    $stmt->close();
    
    $message = "Student marks deleted successfully!";
    $message_type = 'success';
}

// --- Bulk Update Marks ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_update_marks'])) {
    $selected_students = $_POST['selected_students'] ?? [];
    $bulk_action = $_POST['bulk_action'] ?? '';
    
    if (empty($selected_students)) {
        $message = "No students selected!";
        $message_type = 'error';
    } else {
        $updated = 0;
        foreach ($selected_students as $student_id) {
            if ($bulk_action === 'recalculate') {
                // Recalculate total marks
                $total_scaled = 0;
                $res = $conn->query("SELECT SUM(fyp_scaledmark) as total FROM criteria_mark WHERE fyp_studid = '$student_id'");
                if ($res && $row = $res->fetch_assoc()) {
                    $total_scaled = floatval($row['total'] ?? 0);
                }
                
                $stmt = $conn->prepare("UPDATE total_mark SET fyp_totalmark = ? WHERE fyp_studid = ?");
                $stmt->bind_param("ds", $total_scaled, $student_id);
                if ($stmt->execute()) $updated++;
                $stmt->close();
            }
        }
        $message = "Updated $updated student(s)!";
        $message_type = 'success';
    }
}

// ===================== GET DATA =====================

// Get academic years
$academic_years = [];
$res = $conn->query("SELECT * FROM academic_year ORDER BY fyp_academicid DESC");
if ($res) { while ($row = $res->fetch_assoc()) { $academic_years[] = $row; } }

// Get assessment items
$items = [];
$res = $conn->query("SELECT * FROM item ORDER BY fyp_itemid");
if ($res) { while ($row = $res->fetch_assoc()) { $items[] = $row; } }

// Get assessment criteria (Poor, Pass, Credit, etc.)
$criteria = [];
$res = $conn->query("SELECT * FROM assessment_criteria ORDER BY fyp_min ASC");
if ($res) { while ($row = $res->fetch_assoc()) { $criteria[] = $row; } }

// Get marking criteria
$marking_criteria = [];
$res = $conn->query("SELECT * FROM marking_criteria ORDER BY fyp_criteriaid");
if ($res) { while ($row = $res->fetch_assoc()) { $marking_criteria[] = $row; } }

// Get item-marking criteria links
$item_marking_links = [];
$res = $conn->query("SELECT imc.*, i.fyp_itemname, mc.fyp_criterianame, mc.fyp_percentallocation 
                     FROM item_marking_criteria imc 
                     LEFT JOIN item i ON imc.fyp_itemid = i.fyp_itemid 
                     LEFT JOIN marking_criteria mc ON imc.fyp_criteriaid = mc.fyp_criteriaid 
                     ORDER BY imc.fyp_itemid, imc.fyp_criteriaid");
if ($res) { while ($row = $res->fetch_assoc()) { $item_marking_links[] = $row; } }

// Get filter values
$filter_year = $_GET['year'] ?? '';
$filter_intake = $_GET['intake'] ?? '';
$filter_student_id = $_GET['student_id'] ?? '';
$filter_student_name = $_GET['student_name'] ?? '';
$filter_project = $_GET['project'] ?? '';

// Build query for students with marks
$where_conditions = ["pa.fyp_pairingid IS NOT NULL"];
if (!empty($filter_year)) {
    $where_conditions[] = "a.fyp_acdyear = '" . $conn->real_escape_string($filter_year) . "'";
}
if (!empty($filter_intake)) {
    $where_conditions[] = "a.fyp_intake = '" . $conn->real_escape_string($filter_intake) . "'";
}
if (!empty($filter_student_id)) {
    $where_conditions[] = "s.fyp_studid LIKE '%" . $conn->real_escape_string($filter_student_id) . "%'";
}
if (!empty($filter_student_name)) {
    $where_conditions[] = "s.fyp_studname LIKE '%" . $conn->real_escape_string($filter_student_name) . "%'";
}
if (!empty($filter_project)) {
    $where_conditions[] = "p.fyp_projecttitle LIKE '%" . $conn->real_escape_string($filter_project) . "%'";
}

$where_sql = implode(" AND ", $where_conditions);

// Get all students with their marks
$students_marks = [];
$res = $conn->query("SELECT s.fyp_studid, s.fyp_studname, s.fyp_studfullid, s.fyp_email,
                            p.fyp_projectid, p.fyp_projecttitle,
                            pa.fyp_pairingid, pa.fyp_supervisorid, pa.fyp_moderatorid,
                            sup.fyp_supervisorname as supervisor_name,
                            mod_sup.fyp_supervisorname as moderator_name,
                            tm.fyp_totalmark, tm.fyp_totalfinalsupervisor, tm.fyp_totalfinalmoderator,
                            a.fyp_acdyear, a.fyp_intake, a.fyp_academicid
                     FROM student s
                     LEFT JOIN pairing pa ON s.fyp_studid = pa.fyp_studid
                     LEFT JOIN project p ON pa.fyp_projectid = p.fyp_projectid
                     LEFT JOIN supervisor sup ON pa.fyp_supervisorid = sup.fyp_supervisorid
                     LEFT JOIN supervisor mod_sup ON pa.fyp_moderatorid = mod_sup.fyp_supervisorid
                     LEFT JOIN total_mark tm ON s.fyp_studid = tm.fyp_studid
                     LEFT JOIN academic_year a ON pa.fyp_academicid = a.fyp_academicid
                     WHERE $where_sql
                     ORDER BY s.fyp_studname");
if ($res) { while ($row = $res->fetch_assoc()) { $students_marks[] = $row; } }

// Get selected student for marking
$selected_student_id = $_GET['mark_student'] ?? '';
$selected_student = null;
$student_criteria_marks = [];

if (!empty($selected_student_id)) {
    // Get student details
    $stmt = $conn->prepare("SELECT s.*, p.fyp_projecttitle, p.fyp_projectid, pa.fyp_pairingid,
                                   sup.fyp_supervisorname as supervisor_name,
                                   mod_sup.fyp_supervisorname as moderator_name
                           FROM student s
                           LEFT JOIN pairing pa ON s.fyp_studid = pa.fyp_studid
                           LEFT JOIN project p ON pa.fyp_projectid = p.fyp_projectid
                           LEFT JOIN supervisor sup ON pa.fyp_supervisorid = sup.fyp_supervisorid
                           LEFT JOIN supervisor mod_sup ON pa.fyp_moderatorid = mod_sup.fyp_supervisorid
                           WHERE s.fyp_studid = ?");
    $stmt->bind_param("s", $selected_student_id);
    $stmt->execute();
    $selected_student = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Get existing marks for this student
    $res = $conn->query("SELECT cm.*, mc.fyp_criterianame, mc.fyp_percentallocation
                        FROM criteria_mark cm
                        LEFT JOIN marking_criteria mc ON cm.fyp_criteriaid = mc.fyp_criteriaid
                        WHERE cm.fyp_studid = '$selected_student_id'");
    if ($res) { while ($row = $res->fetch_assoc()) { $student_criteria_marks[$row['fyp_criteriaid']] = $row; } }
}

// Get student for overall view
$view_student_id = $_GET['view_student'] ?? '';
$view_student = null;
$overall_marks_data = [];

if (!empty($view_student_id)) {
    // Get student info
    $stmt = $conn->prepare("SELECT s.*, p.fyp_projecttitle, pa.fyp_pairingid,
                                   sup.fyp_supervisorname as supervisor_name,
                                   mod_sup.fyp_supervisorname as moderator_name,
                                   tm.fyp_totalmark, tm.fyp_totalfinalsupervisor, tm.fyp_totalfinalmoderator
                           FROM student s
                           LEFT JOIN pairing pa ON s.fyp_studid = pa.fyp_studid
                           LEFT JOIN project p ON pa.fyp_projectid = p.fyp_projectid
                           LEFT JOIN supervisor sup ON pa.fyp_supervisorid = sup.fyp_supervisorid
                           LEFT JOIN supervisor mod_sup ON pa.fyp_moderatorid = mod_sup.fyp_supervisorid
                           LEFT JOIN total_mark tm ON s.fyp_studid = tm.fyp_studid
                           WHERE s.fyp_studid = ?");
    $stmt->bind_param("s", $view_student_id);
    $stmt->execute();
    $view_student = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Get all criteria marks grouped by item
    $res = $conn->query("SELECT cm.*, mc.fyp_criterianame, mc.fyp_percentallocation, 
                                i.fyp_itemname, i.fyp_itemid, i.fyp_originalmarkallocation
                        FROM criteria_mark cm
                        LEFT JOIN marking_criteria mc ON cm.fyp_criteriaid = mc.fyp_criteriaid
                        LEFT JOIN item_marking_criteria imc ON cm.fyp_criteriaid = imc.fyp_criteriaid
                        LEFT JOIN item i ON imc.fyp_itemid = i.fyp_itemid
                        WHERE cm.fyp_studid = '$view_student_id'
                        ORDER BY i.fyp_itemid, mc.fyp_criteriaid");
    if ($res) { 
        while ($row = $res->fetch_assoc()) { 
            $item_name = $row['fyp_itemname'] ?? 'Unknown';
            if (!isset($overall_marks_data[$item_name])) {
                $overall_marks_data[$item_name] = [
                    'allocation' => $row['fyp_originalmarkallocation'],
                    'criteria' => []
                ];
            }
            $overall_marks_data[$item_name]['criteria'][] = $row;
        } 
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Assessment Marks - FYP Portal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #0f0f1a; color: #e2e8f0; min-height: 100vh; }
        
        /* Sidebar */
        .sidebar { width: 280px; height: 100vh; background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%); position: fixed; left: 0; top: 0; border-right: 1px solid rgba(139, 92, 246, 0.1); overflow-y: auto; z-index: 1000; }
        .sidebar-header { padding: 25px; text-align: center; border-bottom: 1px solid rgba(139, 92, 246, 0.1); background: rgba(139, 92, 246, 0.05); }
        .sidebar-header img { width: 80px; height: 80px; border-radius: 50%; margin-bottom: 12px; border: 3px solid #8b5cf6; }
        .sidebar-header h3 { font-size: 1rem; margin-bottom: 5px; color: #fff; }
        .sidebar-header p { font-size: 0.75rem; color: #a78bfa; text-transform: uppercase; letter-spacing: 1px; }
        
        .nav-menu { padding: 15px 0; }
        .nav-item { display: flex; align-items: center; padding: 14px 25px; color: #94a3b8; text-decoration: none; transition: all 0.3s; border-left: 3px solid transparent; }
        .nav-item:hover, .nav-item.active { background: rgba(139, 92, 246, 0.1); color: #a78bfa; border-left-color: #8b5cf6; }
        .nav-item i { width: 24px; margin-right: 12px; font-size: 1.1rem; }
        .nav-item.logout { color: #f87171; margin-top: 20px; border-top: 1px solid rgba(139, 92, 246, 0.1); padding-top: 20px; }
        .nav-item.logout:hover { background: rgba(248, 113, 113, 0.1); border-left-color: #f87171; }
        
        /* Main Content */
        .main-content { margin-left: 280px; padding: 30px; min-height: 100vh; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 15px; }
        .page-header h1 { font-size: 1.8rem; color: #fff; display: flex; align-items: center; gap: 12px; }
        .page-header h1 i { color: #a78bfa; }
        .header-date { color: #64748b; font-size: 0.9rem; }
        
        /* Cards */
        .card { background: linear-gradient(145deg, #1e1e32 0%, #1a1a2e 100%); border-radius: 16px; border: 1px solid rgba(139, 92, 246, 0.1); margin-bottom: 25px; overflow: hidden; }
        .card-header { padding: 20px 25px; border-bottom: 1px solid rgba(139, 92, 246, 0.1); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; background: rgba(139, 92, 246, 0.03); }
        .card-header h3 { font-size: 1.1rem; color: #e2e8f0; display: flex; align-items: center; gap: 10px; }
        .card-body { padding: 25px; }
        
        /* Buttons */
        .btn { padding: 10px 20px; border-radius: 8px; border: none; cursor: pointer; font-weight: 600; font-size: 0.9rem; transition: all 0.3s; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
        .btn-primary { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(139, 92, 246, 0.4); }
        .btn-success { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; }
        .btn-success:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(16, 185, 129, 0.4); }
        .btn-info { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white; }
        .btn-info:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(59, 130, 246, 0.4); }
        .btn-warning { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; }
        .btn-warning:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(245, 158, 11, 0.4); }
        .btn-danger { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; }
        .btn-danger:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(239, 68, 68, 0.4); }
        .btn-secondary { background: rgba(100, 116, 139, 0.2); color: #94a3b8; border: 1px solid rgba(100, 116, 139, 0.3); }
        .btn-secondary:hover { background: rgba(100, 116, 139, 0.3); color: #e2e8f0; }
        .btn-sm { padding: 6px 12px; font-size: 0.8rem; }
        
        /* Form Elements */
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #e2e8f0; font-weight: 500; font-size: 0.9rem; }
        .form-control { width: 100%; padding: 12px 15px; border-radius: 8px; border: 1px solid rgba(139, 92, 246, 0.2); background: rgba(15, 15, 26, 0.6); color: #e2e8f0; font-size: 0.95rem; transition: all 0.3s; }
        .form-control:focus { outline: none; border-color: #8b5cf6; box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1); }
        .form-control::placeholder { color: #64748b; }
        select.form-control { cursor: pointer; }
        
        /* Table */
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 14px 16px; text-align: left; border-bottom: 1px solid rgba(139, 92, 246, 0.1); }
        .data-table th { background: rgba(139, 92, 246, 0.1); color: #a78bfa; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; position: sticky; top: 0; }
        .data-table tr:hover { background: rgba(139, 92, 246, 0.05); }
        .data-table td { font-size: 0.9rem; vertical-align: middle; }
        
        /* Badges */
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .badge-success { background: rgba(16, 185, 129, 0.2); color: #34d399; }
        .badge-warning { background: rgba(245, 158, 11, 0.2); color: #fbbf24; }
        .badge-danger { background: rgba(239, 68, 68, 0.2); color: #f87171; }
        .badge-info { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
        .badge-purple { background: rgba(139, 92, 246, 0.2); color: #a78bfa; }
        
        /* Sub Navigation */
        .sub-nav { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 25px; }
        .sub-nav .btn { position: relative; }
        .sub-nav .btn.active { box-shadow: 0 0 15px rgba(139, 92, 246, 0.5); }
        
        /* Filter Section */
        .filter-section { background: rgba(59, 130, 246, 0.1); padding: 20px; border-radius: 12px; margin-bottom: 25px; border: 1px solid rgba(59, 130, 246, 0.2); }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; }
        .filter-actions { margin-top: 15px; display: flex; gap: 10px; flex-wrap: wrap; }
        
        /* Student Info Card */
        .student-info-card { background: rgba(52, 211, 153, 0.1); padding: 20px; border-radius: 12px; margin-bottom: 25px; border: 1px solid rgba(52, 211, 153, 0.2); }
        .student-info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .student-info-item small { color: #64748b; display: block; margin-bottom: 5px; }
        .student-info-item p { color: #e2e8f0; font-weight: 600; margin: 0; }
        
        /* Assessment Table */
        .assessment-table { overflow-x: auto; }
        .assessment-table table { min-width: 1000px; }
        .assessment-table input[type="number"] { width: 70px; padding: 8px; text-align: center; }
        .assessment-table input[readonly] { background: #1e1e32; }
        
        /* Item Header */
        .item-header { background: rgba(139, 92, 246, 0.1); padding: 15px 20px; border-radius: 8px 8px 0 0; margin-top: 20px; }
        .item-header h4 { color: #a78bfa; margin: 0; display: flex; align-items: center; gap: 10px; }
        
        /* Totals Row */
        .totals-row { background: rgba(139, 92, 246, 0.15); }
        .totals-row td { font-weight: 700; }
        
        /* Final Subtotal */
        .final-subtotal { background: rgba(139, 92, 246, 0.1); padding: 20px; border-radius: 12px; margin-top: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px; }
        .final-subtotal label { color: #a78bfa; font-weight: 600; margin-right: 15px; }
        .final-subtotal input { width: 120px; font-size: 1.2rem; font-weight: 700; color: #34d399; background: #1e1e32; text-align: center; }
        
        /* Empty State */
        .empty-state { text-align: center; padding: 60px 20px; color: #64748b; }
        .empty-state i { font-size: 4rem; margin-bottom: 20px; opacity: 0.5; }
        .empty-state p { font-size: 1.1rem; margin-bottom: 20px; }
        
        /* Alert Messages */
        .alert { padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; }
        .alert-success { background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); color: #34d399; }
        .alert-error { background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: #f87171; }
        .alert-warning { background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.3); color: #fbbf24; }
        
        /* Modal */
        .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); display: none; justify-content: center; align-items: center; z-index: 2000; padding: 20px; }
        .modal-overlay.active { display: flex; }
        .modal { background: linear-gradient(145deg, #1e1e32 0%, #1a1a2e 100%); border-radius: 16px; width: 100%; max-width: 500px; border: 1px solid rgba(139, 92, 246, 0.2); }
        .modal-header { padding: 20px; border-bottom: 1px solid rgba(139, 92, 246, 0.1); display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { color: #e2e8f0; font-size: 1.1rem; }
        .modal-close { background: none; border: none; color: #64748b; font-size: 1.5rem; cursor: pointer; }
        .modal-body { padding: 20px; }
        .modal-footer { padding: 20px; border-top: 1px solid rgba(139, 92, 246, 0.1); display: flex; justify-content: flex-end; gap: 10px; }
        
        /* Success Modal */
        .success-modal { text-align: center; }
        .success-icon { width: 80px; height: 80px; border-radius: 50%; background: rgba(52, 211, 153, 0.2); display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; }
        .success-icon i { font-size: 40px; color: #34d399; }
        .success-modal h2 { color: #34d399; margin-bottom: 15px; }
        .success-modal p { color: #94a3b8; margin-bottom: 25px; }
        
        /* Checkbox */
        .checkbox-wrapper { display: flex; align-items: center; gap: 8px; }
        .checkbox-wrapper input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; }
        
        /* Stats Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .stat-card { background: rgba(139, 92, 246, 0.1); padding: 20px; border-radius: 12px; border: 1px solid rgba(139, 92, 246, 0.2); }
        .stat-card h4 { color: #64748b; font-size: 0.85rem; margin-bottom: 10px; }
        .stat-card .value { font-size: 2rem; font-weight: 700; color: #a78bfa; }
        .stat-card .sub-value { color: #94a3b8; font-size: 0.85rem; margin-top: 5px; }
        
        /* Print Styles */
        @media print {
            .sidebar, .sub-nav, .filter-section, .btn, .no-print { display: none !important; }
            .main-content { margin-left: 0; padding: 20px; }
            .card { border: 1px solid #ccc; }
            body { background: white; color: black; }
            .data-table th, .data-table td { border: 1px solid #ccc; }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar { width: 100%; height: auto; position: relative; }
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <img src="<?= $img_src; ?>" alt="Profile">
        <h3><?= htmlspecialchars($user_name); ?></h3>
        <p><?= strtoupper($user_role); ?></p>
    </div>
    <nav class="nav-menu">
        <a href="<?= $user_role === 'coordinator' ? 'Coordinator_mainpage.php' : 'Supervisor_mainpage.php'; ?>" class="nav-item">
            <i class="fas fa-home"></i> Dashboard
        </a>
        <a href="assessment_marks.php" class="nav-item active">
            <i class="fas fa-calculator"></i> Assessment Marks
        </a>
        <a href="<?= $user_role === 'coordinator' ? 'Coordinator_mainpage.php?page=rubrics' : '#'; ?>" class="nav-item">
            <i class="fas fa-list-check"></i> Rubrics Settings
        </a>
        <a href="<?= $user_role === 'coordinator' ? 'Coordinator_mainpage.php?page=students' : 'Supervisor_mainpage.php?page=students'; ?>" class="nav-item">
            <i class="fas fa-user-graduate"></i> Students
        </a>
        <a href="../Login.php?logout=1" class="nav-item logout">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>
</div>

<!-- Main Content -->
<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <h1><i class="fas fa-calculator"></i> Assessment Marks</h1>
        <span class="header-date"><?= date('l, F j, Y'); ?></span>
    </div>
    
    <!-- Alert Messages -->
    <?php if (!empty($message)): ?>
    <div class="alert alert-<?= $message_type === 'success' ? 'success' : ($message_type === 'error' ? 'error' : 'warning'); ?>">
        <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : ($message_type === 'error' ? 'exclamation-circle' : 'exclamation-triangle'); ?>"></i>
        <?= $message; ?>
    </div>
    <?php endif; ?>
    
    <!-- Sub Navigation -->
    <div class="sub-nav">
        <a href="assessment_marks.php?action=list" class="btn <?= $action === 'list' ? 'btn-primary active' : 'btn-secondary'; ?>">
            <i class="fas fa-list-alt"></i> All Students Marks
        </a>
        <a href="assessment_marks.php?action=mark" class="btn <?= $action === 'mark' ? 'btn-success active' : 'btn-secondary'; ?>">
            <i class="fas fa-edit"></i> Mark Student
        </a>
        <a href="assessment_marks.php?action=overall" class="btn <?= $action === 'overall' ? 'btn-warning active' : 'btn-secondary'; ?>">
            <i class="fas fa-chart-bar"></i> Overall View
        </a>
    </div>
    
    <?php if ($action === 'list'): ?>
    <!-- ==================== ALL STUDENTS MARKS ==================== -->
    
    <!-- Statistics -->
    <?php
    $total_students = count($students_marks);
    $students_with_marks = 0;
    $avg_mark = 0;
    $total_marks_sum = 0;
    foreach ($students_marks as $sm) {
        if (!empty($sm['fyp_totalmark'])) {
            $students_with_marks++;
            $total_marks_sum += $sm['fyp_totalmark'];
        }
    }
    if ($students_with_marks > 0) {
        $avg_mark = $total_marks_sum / $students_with_marks;
    }
    ?>
    <div class="stats-grid">
        <div class="stat-card">
            <h4><i class="fas fa-users"></i> Total Students</h4>
            <div class="value"><?= $total_students; ?></div>
            <div class="sub-value">With project assignments</div>
        </div>
        <div class="stat-card">
            <h4><i class="fas fa-check-circle"></i> Marked Students</h4>
            <div class="value"><?= $students_with_marks; ?></div>
            <div class="sub-value"><?= $total_students > 0 ? round(($students_with_marks / $total_students) * 100) : 0; ?>% completed</div>
        </div>
        <div class="stat-card">
            <h4><i class="fas fa-chart-line"></i> Average Mark</h4>
            <div class="value" style="color:<?= $avg_mark >= 50 ? '#34d399' : '#f87171'; ?>;"><?= number_format($avg_mark, 1); ?>%</div>
            <div class="sub-value">Class average</div>
        </div>
        <div class="stat-card">
            <h4><i class="fas fa-clock"></i> Pending</h4>
            <div class="value" style="color:#fbbf24;"><?= $total_students - $students_with_marks; ?></div>
            <div class="sub-value">Students to mark</div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list-alt" style="color:#60a5fa;"></i> Students Marks List</h3>
            <div style="display:flex;gap:10px;">
                <button class="btn btn-info btn-sm" onclick="exportMarks()"><i class="fas fa-download"></i> Export</button>
                <button class="btn btn-secondary btn-sm" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
            </div>
        </div>
        <div class="card-body">
            <!-- Filter Section -->
            <div class="filter-section no-print">
                <form method="GET" action="assessment_marks.php">
                    <input type="hidden" name="action" value="list">
                    <div class="filter-grid">
                        <div class="form-group" style="margin:0;">
                            <label>Academic Year</label>
                            <select name="year" class="form-control">
                                <option value="">-- All --</option>
                                <?php 
                                $years_shown = [];
                                foreach ($academic_years as $ay): 
                                    if (!in_array($ay['fyp_acdyear'], $years_shown)):
                                        $years_shown[] = $ay['fyp_acdyear'];
                                ?>
                                <option value="<?= htmlspecialchars($ay['fyp_acdyear']); ?>" <?= $filter_year === $ay['fyp_acdyear'] ? 'selected' : ''; ?>><?= htmlspecialchars($ay['fyp_acdyear']); ?></option>
                                <?php endif; endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label>Intake</label>
                            <select name="intake" class="form-control">
                                <option value="">-- All --</option>
                                <option value="MAR" <?= $filter_intake === 'MAR' ? 'selected' : ''; ?>>MAR</option>
                                <option value="JUN" <?= $filter_intake === 'JUN' ? 'selected' : ''; ?>>JUN</option>
                                <option value="SEP" <?= $filter_intake === 'SEP' ? 'selected' : ''; ?>>SEP</option>
                                <option value="DEC" <?= $filter_intake === 'DEC' ? 'selected' : ''; ?>>DEC</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label>Student ID</label>
                            <input type="text" name="student_id" class="form-control" placeholder="e.g. TP055011" value="<?= htmlspecialchars($filter_student_id); ?>">
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label>Student Name</label>
                            <input type="text" name="student_name" class="form-control" placeholder="e.g. John" value="<?= htmlspecialchars($filter_student_name); ?>">
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label>Project Title</label>
                            <input type="text" name="project" class="form-control" placeholder="e.g. FYP System" value="<?= htmlspecialchars($filter_project); ?>">
                        </div>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-info"><i class="fas fa-filter"></i> Filter</button>
                        <a href="assessment_marks.php?action=list" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
                    </div>
                </form>
            </div>
            
            <?php if (empty($students_marks)): ?>
            <div class="empty-state">
                <i class="fas fa-list-alt"></i>
                <p>No students found matching the criteria</p>
                <a href="assessment_marks.php?action=list" class="btn btn-secondary">Clear Filters</a>
            </div>
            <?php else: ?>
            
            <!-- Bulk Actions -->
            <form method="POST" id="bulkForm">
                <div style="margin-bottom:15px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;" class="no-print">
                    <div class="checkbox-wrapper">
                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                        <label for="selectAll" style="color:#94a3b8;">Select All</label>
                    </div>
                    <select name="bulk_action" class="form-control" style="width:200px;">
                        <option value="">-- Bulk Action --</option>
                        <option value="recalculate">Recalculate Totals</option>
                    </select>
                    <button type="submit" name="bulk_update_marks" class="btn btn-primary btn-sm"><i class="fas fa-cog"></i> Apply</button>
                </div>
                
                <div style="overflow-x:auto;">
                    <table class="data-table" id="marksTable">
                        <thead>
                            <tr>
                                <th class="no-print" style="width:40px;"><input type="checkbox" id="headerSelectAll" onchange="toggleSelectAll(this)"></th>
                                <th>Student ID</th>
                                <th>Student Name</th>
                                <th>Project Title</th>
                                <th>Supervisor</th>
                                <th>Total Mark</th>
                                <th>Supervisor Mark</th>
                                <th>Moderator Mark</th>
                                <th class="no-print">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students_marks as $sm): ?>
                            <tr>
                                <td class="no-print">
                                    <input type="checkbox" name="selected_students[]" value="<?= htmlspecialchars($sm['fyp_studid']); ?>" class="student-checkbox">
                                </td>
                                <td><strong><?= htmlspecialchars($sm['fyp_studid']); ?></strong></td>
                                <td><?= htmlspecialchars($sm['fyp_studname']); ?></td>
                                <td style="max-width:200px;"><?= htmlspecialchars($sm['fyp_projecttitle'] ?? '-'); ?></td>
                                <td><?= htmlspecialchars($sm['supervisor_name'] ?? '-'); ?></td>
                                <td>
                                    <?php if ($sm['fyp_totalmark']): ?>
                                    <span class="badge <?= $sm['fyp_totalmark'] >= 50 ? 'badge-success' : 'badge-danger'; ?>" style="font-size:1rem;">
                                        <?= number_format($sm['fyp_totalmark'], 2); ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="badge badge-warning">Not Marked</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $sm['fyp_totalfinalsupervisor'] ? number_format($sm['fyp_totalfinalsupervisor'], 2) : '-'; ?></td>
                                <td><?= $sm['fyp_totalfinalmoderator'] ? number_format($sm['fyp_totalfinalmoderator'], 2) : '-'; ?></td>
                                <td class="no-print">
                                    <a href="assessment_marks.php?action=mark&mark_student=<?= urlencode($sm['fyp_studid']); ?>" class="btn btn-success btn-sm" title="Mark Student">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="assessment_marks.php?action=overall&view_student=<?= urlencode($sm['fyp_studid']); ?>" class="btn btn-info btn-sm" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <button type="button" class="btn btn-danger btn-sm" onclick="confirmDeleteMarks('<?= htmlspecialchars($sm['fyp_studid']); ?>', '<?= htmlspecialchars($sm['fyp_studname']); ?>')" title="Delete Marks">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
    
    <?php elseif ($action === 'mark'): ?>
    <!-- ==================== MARK STUDENT ==================== -->
    
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-edit" style="color:#34d399;"></i> Mark Student Assessment</h3>
            <a href="assessment_marks.php?action=list" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back to List</a>
        </div>
        <div class="card-body">
            <!-- Student Selection -->
            <div class="form-group" style="max-width:600px;margin-bottom:25px;">
                <label>Select Student to Mark</label>
                <select class="form-control" id="selectStudentForMarking" onchange="loadStudentForMarking(this.value)">
                    <option value="">-- Select Student --</option>
                    <?php foreach ($students_marks as $stud): ?>
                    <option value="<?= htmlspecialchars($stud['fyp_studid']); ?>" <?= $selected_student_id === $stud['fyp_studid'] ? 'selected' : ''; ?>>
                        <?= htmlspecialchars($stud['fyp_studid'] . ' - ' . $stud['fyp_studname']); ?>
                        <?= $stud['fyp_projecttitle'] ? ' (' . htmlspecialchars(substr($stud['fyp_projecttitle'], 0, 40)) . ')' : ''; ?>
                        <?= $stud['fyp_totalmark'] ? ' [' . number_format($stud['fyp_totalmark'], 1) . '%]' : ' [Not Marked]'; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <?php if ($selected_student): ?>
            <!-- Student Info Card -->
            <div class="student-info-card">
                <div class="student-info-grid">
                    <div class="student-info-item">
                        <small>Student ID</small>
                        <p style="color:#34d399;"><?= htmlspecialchars($selected_student['fyp_studid']); ?></p>
                    </div>
                    <div class="student-info-item">
                        <small>Student Name</small>
                        <p><?= htmlspecialchars($selected_student['fyp_studname']); ?></p>
                    </div>
                    <div class="student-info-item">
                        <small>Project Title</small>
                        <p style="color:#a78bfa;"><?= htmlspecialchars($selected_student['fyp_projecttitle'] ?? 'Not assigned'); ?></p>
                    </div>
                    <div class="student-info-item">
                        <small>Supervisor</small>
                        <p><?= htmlspecialchars($selected_student['supervisor_name'] ?? 'Not assigned'); ?></p>
                    </div>
                    <div class="student-info-item">
                        <small>Moderator</small>
                        <p><?= htmlspecialchars($selected_student['moderator_name'] ?? 'Not assigned'); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Assessment Form -->
            <form method="POST" id="assessmentForm">
                <input type="hidden" name="save_student_assessment" value="1">
                <input type="hidden" name="assess_student_id" value="<?= htmlspecialchars($selected_student['fyp_studid']); ?>">
                
                <?php 
                // Group items with their marking criteria
                $items_with_criteria = [];
                foreach ($items as $item) {
                    $item_id = $item['fyp_itemid'];
                    $items_with_criteria[$item_id] = [
                        'item' => $item,
                        'criteria' => []
                    ];
                    foreach ($item_marking_links as $link) {
                        if ($link['fyp_itemid'] == $item_id) {
                            $items_with_criteria[$item_id]['criteria'][] = $link;
                        }
                    }
                }
                
                $has_criteria = false;
                foreach ($items_with_criteria as $item_data) {
                    if (!empty($item_data['criteria'])) {
                        $has_criteria = true;
                        break;
                    }
                }
                ?>
                
                <?php if (!$has_criteria): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    No marking criteria linked to assessment items. Please configure rubrics first.
                    <a href="<?= $user_role === 'coordinator' ? 'Coordinator_mainpage.php?page=rubrics&action=marking' : '#'; ?>" class="btn btn-warning btn-sm" style="margin-left:15px;">Configure Rubrics</a>
                </div>
                <?php else: ?>
                
                <?php foreach ($items_with_criteria as $item_id => $item_data): 
                    if (empty($item_data['criteria'])) continue;
                ?>
                <div class="item-header">
                    <h4>
                        <i class="fas fa-file-alt"></i> <?= htmlspecialchars($item_data['item']['fyp_itemname']); ?>
                        <span style="font-size:0.85rem;color:#94a3b8;margin-left:10px;">
                            (Total: <?= number_format($item_data['item']['fyp_originalmarkallocation'], 1); ?>%)
                        </span>
                    </h4>
                </div>
                <div class="assessment-table">
                    <table class="data-table" style="margin-bottom:0;">
                        <thead>
                            <tr>
                                <th>Criteria Name</th>
                                <th>% Allocation</th>
                                <?php foreach ($criteria as $c): ?>
                                <th style="text-align:center;font-size:0.75rem;">
                                    <?= htmlspecialchars($c['fyp_assessmentcriterianame']); ?><br>
                                    <small>(<?= $c['fyp_min']; ?>-<?= $c['fyp_max']; ?>)</small>
                                </th>
                                <?php endforeach; ?>
                                <th>Initial Work</th>
                                <th>Final Work</th>
                                <th>Moderator Mark</th>
                                <th>Average Mark</th>
                                <th>Scaled Mark</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($item_data['criteria'] as $mc): 
                                $existing_mark = $student_criteria_marks[$mc['fyp_criteriaid']] ?? null;
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($mc['fyp_criterianame']); ?></strong></td>
                                <td><span class="badge badge-purple"><?= number_format($mc['fyp_percentallocation'], 1); ?>%</span></td>
                                <?php foreach ($criteria as $c): ?>
                                <td style="text-align:center;">
                                    <input type="radio" name="criteria_level[<?= $mc['fyp_criteriaid']; ?>]" value="<?= $c['fyp_assessmentcriteriaid']; ?>" style="width:18px;height:18px;cursor:pointer;">
                                </td>
                                <?php endforeach; ?>
                                <td>
                                    <input type="number" name="initial_work[<?= $mc['fyp_criteriaid']; ?>]" 
                                           class="form-control" style="width:80px;" min="0" max="100" step="0.01"
                                           value="<?= $existing_mark['fyp_initialwork'] ?? ''; ?>"
                                           onchange="calculateAverage(<?= $mc['fyp_criteriaid']; ?>, <?= $mc['fyp_percentallocation']; ?>)">
                                </td>
                                <td>
                                    <input type="number" name="final_work[<?= $mc['fyp_criteriaid']; ?>]" 
                                           class="form-control" style="width:80px;" min="0" max="100" step="0.01"
                                           value="<?= $existing_mark['fyp_finalwork'] ?? ''; ?>"
                                           onchange="calculateAverage(<?= $mc['fyp_criteriaid']; ?>, <?= $mc['fyp_percentallocation']; ?>)">
                                </td>
                                <td>
                                    <input type="number" name="moderator_mark[<?= $mc['fyp_criteriaid']; ?>]" 
                                           class="form-control" style="width:80px;" min="0" max="100" step="0.01"
                                           value="<?= $existing_mark['fyp_markbymoderator'] ?? ''; ?>"
                                           onchange="calculateAverage(<?= $mc['fyp_criteriaid']; ?>, <?= $mc['fyp_percentallocation']; ?>)">
                                </td>
                                <td>
                                    <input type="text" id="avg_<?= $mc['fyp_criteriaid']; ?>" 
                                           class="form-control" style="width:80px;color:#60a5fa;" readonly
                                           value="<?= isset($existing_mark['fyp_avgmark']) ? number_format($existing_mark['fyp_avgmark'], 2) : ''; ?>">
                                </td>
                                <td>
                                    <input type="text" id="scaled_<?= $mc['fyp_criteriaid']; ?>" name="scaled_mark[<?= $mc['fyp_criteriaid']; ?>]"
                                           class="form-control" style="width:80px;color:#34d399;font-weight:600;" readonly
                                           value="<?= isset($existing_mark['fyp_scaledmark']) ? number_format($existing_mark['fyp_scaledmark'], 2) : ''; ?>">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endforeach; ?>
                
                <!-- Final Subtotal -->
                <div class="final-subtotal">
                    <div>
                        <label>Final Subtotal:</label>
                        <input type="text" id="finalSubtotal" class="form-control" readonly value="0.00">
                    </div>
                    <div style="display:flex;gap:10px;">
                        <button type="button" class="btn btn-secondary" onclick="resetForm()"><i class="fas fa-undo"></i> Reset</button>
                        <button type="submit" class="btn btn-success" style="min-width:150px;">
                            <i class="fas fa-save"></i> Save Marks
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </form>
            
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-user-graduate"></i>
                <p>Select a student from the dropdown above to start marking</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php elseif ($action === 'overall'): ?>
    <!-- ==================== OVERALL VIEW ==================== -->
    
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-bar" style="color:#fbbf24;"></i> Overall Marks View</h3>
            <a href="assessment_marks.php?action=list" class="btn btn-secondary btn-sm no-print"><i class="fas fa-arrow-left"></i> Back to List</a>
        </div>
        <div class="card-body">
            <?php if (!$view_student_id): ?>
            <!-- Student Selection -->
            <div class="form-group" style="max-width:600px;margin-bottom:25px;">
                <label>Select Student to View Overall Marks</label>
                <select class="form-control" onchange="if(this.value) window.location='assessment_marks.php?action=overall&view_student='+this.value">
                    <option value="">-- Select Student --</option>
                    <?php foreach ($students_marks as $stud): ?>
                    <option value="<?= htmlspecialchars($stud['fyp_studid']); ?>">
                        <?= htmlspecialchars($stud['fyp_studid'] . ' - ' . $stud['fyp_studname']); ?>
                        <?= $stud['fyp_totalmark'] ? ' [' . number_format($stud['fyp_totalmark'], 1) . '%]' : ''; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="empty-state">
                <i class="fas fa-chart-bar"></i>
                <p>Select a student to view their overall marks breakdown</p>
            </div>
            
            <?php elseif ($view_student): ?>
            <!-- Student Info Header -->
            <div style="background:rgba(251,191,36,0.1);padding:20px;border-radius:12px;margin-bottom:25px;border:1px solid rgba(251,191,36,0.2);">
                <div class="student-info-grid">
                    <div class="student-info-item">
                        <small>Student ID</small>
                        <p style="color:#fbbf24;font-size:1.1rem;"><?= htmlspecialchars($view_student['fyp_studid']); ?></p>
                    </div>
                    <div class="student-info-item">
                        <small>Student Name</small>
                        <p><?= htmlspecialchars($view_student['fyp_studname']); ?></p>
                    </div>
                    <div class="student-info-item">
                        <small>Project Title</small>
                        <p style="color:#a78bfa;"><?= htmlspecialchars($view_student['fyp_projecttitle'] ?? 'Not assigned'); ?></p>
                    </div>
                    <div class="student-info-item">
                        <small>Total Mark</small>
                        <p style="color:<?= ($view_student['fyp_totalmark'] ?? 0) >= 50 ? '#34d399' : '#f87171'; ?>;font-size:1.3rem;font-weight:700;">
                            <?= $view_student['fyp_totalmark'] ? number_format($view_student['fyp_totalmark'], 2) . '%' : 'Not Marked'; ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <?php if (empty($overall_marks_data)): ?>
            <div class="empty-state">
                <i class="fas fa-clipboard-list"></i>
                <p>No marks recorded for this student yet</p>
                <a href="assessment_marks.php?action=mark&mark_student=<?= urlencode($view_student_id); ?>" class="btn btn-success">
                    <i class="fas fa-edit"></i> Mark This Student
                </a>
            </div>
            <?php else: ?>
            
            <!-- Overall Marks Table -->
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Criteria</th>
                            <th>% Allocation</th>
                            <?php foreach ($criteria as $c): ?>
                            <th style="text-align:center;font-size:0.7rem;"><?= htmlspecialchars($c['fyp_assessmentcriterianame']); ?><br>(<?= $c['fyp_min']; ?>-<?= $c['fyp_max']; ?>)</th>
                            <?php endforeach; ?>
                            <th>Initial</th>
                            <th>Final</th>
                            <th>Moderator</th>
                            <th>Average</th>
                            <th>Scaled</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $grand_total = 0;
                        foreach ($overall_marks_data as $item_name => $item_data): 
                            $first_row = true;
                            $criteria_count = count($item_data['criteria']);
                            foreach ($item_data['criteria'] as $mark):
                                $grand_total += floatval($mark['fyp_scaledmark'] ?? 0);
                        ?>
                        <tr>
                            <?php if ($first_row): ?>
                            <td rowspan="<?= $criteria_count; ?>" style="background:rgba(139,92,246,0.1);font-weight:600;color:#a78bfa;vertical-align:top;padding-top:15px;">
                                <?= htmlspecialchars($item_name); ?><br>
                                <small style="color:#94a3b8;">(<?= number_format($item_data['allocation'] ?? 0, 0); ?>%)</small>
                            </td>
                            <?php $first_row = false; endif; ?>
                            <td><?= htmlspecialchars($mark['fyp_criterianame']); ?></td>
                            <td><span class="badge badge-purple"><?= number_format($mark['fyp_percentallocation'], 1); ?>%</span></td>
                            <?php foreach ($criteria as $c): ?>
                            <td style="text-align:center;color:#64748b;">-</td>
                            <?php endforeach; ?>
                            <td><?= $mark['fyp_initialwork'] ? number_format($mark['fyp_initialwork'], 2) : '-'; ?></td>
                            <td><?= $mark['fyp_finalwork'] ? number_format($mark['fyp_finalwork'], 2) : '-'; ?></td>
                            <td><?= $mark['fyp_markbymoderator'] ? number_format($mark['fyp_markbymoderator'], 2) : '-'; ?></td>
                            <td style="color:#60a5fa;font-weight:600;"><?= $mark['fyp_avgmark'] ? number_format($mark['fyp_avgmark'], 2) : '-'; ?></td>
                            <td style="color:#34d399;font-weight:700;"><?= $mark['fyp_scaledmark'] ? number_format($mark['fyp_scaledmark'], 2) : '-'; ?></td>
                        </tr>
                        <?php endforeach; endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="totals-row">
                            <td colspan="<?= 3 + count($criteria); ?>" style="text-align:right;color:#a78bfa;">
                                <strong>Grand Total:</strong>
                            </td>
                            <td colspan="4" style="text-align:center;">
                                <strong style="color:#fbbf24;font-size:1.3rem;"><?= number_format($grand_total, 2); ?>%</strong>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <!-- Summary Cards -->
            <div class="stats-grid" style="margin-top:25px;">
                <div class="stat-card">
                    <h4>Final Supervisor Mark</h4>
                    <div class="value" style="color:#60a5fa;"><?= $view_student['fyp_totalfinalsupervisor'] ? number_format($view_student['fyp_totalfinalsupervisor'], 2) : '-'; ?></div>
                </div>
                <div class="stat-card">
                    <h4>Final Moderator Mark</h4>
                    <div class="value" style="color:#fb923c;"><?= $view_student['fyp_totalfinalmoderator'] ? number_format($view_student['fyp_totalfinalmoderator'], 2) : '-'; ?></div>
                </div>
                <div class="stat-card">
                    <h4>Final Total Mark</h4>
                    <div class="value" style="color:<?= ($view_student['fyp_totalmark'] ?? 0) >= 50 ? '#34d399' : '#f87171'; ?>;">
                        <?= $view_student['fyp_totalmark'] ? number_format($view_student['fyp_totalmark'], 2) . '%' : '-'; ?>
                    </div>
                </div>
                <div class="stat-card">
                    <h4>Grade</h4>
                    <?php
                    $total = $view_student['fyp_totalmark'] ?? 0;
                    $grade = '-';
                    $grade_color = '#94a3b8';
                    if ($total >= 80) { $grade = 'A'; $grade_color = '#34d399'; }
                    elseif ($total >= 70) { $grade = 'B'; $grade_color = '#60a5fa'; }
                    elseif ($total >= 60) { $grade = 'C'; $grade_color = '#fbbf24'; }
                    elseif ($total >= 50) { $grade = 'D'; $grade_color = '#fb923c'; }
                    elseif ($total > 0) { $grade = 'F'; $grade_color = '#f87171'; }
                    ?>
                    <div class="value" style="color:<?= $grade_color; ?>;"><?= $grade; ?></div>
                </div>
            </div>
            
            <?php endif; ?>
            
            <!-- Action Buttons -->
            <div style="margin-top:25px;display:flex;gap:10px;flex-wrap:wrap;" class="no-print">
                <a href="assessment_marks.php?action=list" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Students Marks
                </a>
                <a href="assessment_marks.php?action=mark&mark_student=<?= urlencode($view_student_id); ?>" class="btn btn-success">
                    <i class="fas fa-edit"></i> Edit Marks
                </a>
                <button class="btn btn-primary" onclick="window.print()">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
            
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-exclamation-triangle" style="color:#f87171;"></i>
                <p>Student not found</p>
                <a href="assessment_marks.php?action=list" class="btn btn-secondary">Back to Students Marks</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php endif; ?>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-exclamation-triangle" style="color:#f87171;"></i> Confirm Delete</h3>
            <button class="modal-close" onclick="closeModal('deleteModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="delete_student_id" id="delete_student_id">
            <div class="modal-body">
                <p>Are you sure you want to delete all marks for <strong id="delete_student_name"></strong>?</p>
                <p style="color:#f87171;margin-top:10px;"><i class="fas fa-warning"></i> This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('deleteModal')">Cancel</button>
                <button type="submit" name="delete_student_marks" class="btn btn-danger">
                    <i class="fas fa-trash"></i> Delete Marks
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Success Modal -->
<div class="modal-overlay" id="successModal">
    <div class="modal" style="max-width:400px;">
        <div class="modal-body success-modal" style="padding:40px;">
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            <h2>Success</h2>
            <p id="successMessage">Marks updated successfully.</p>
            <button class="btn btn-success" onclick="closeModal('successModal')" style="min-width:100px;">OK</button>
        </div>
    </div>
</div>

<script>
// Modal Functions
function openModal(id) {
    document.getElementById(id).classList.add('active');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

// Load student for marking
function loadStudentForMarking(studentId) {
    if (studentId) {
        window.location.href = 'assessment_marks.php?action=mark&mark_student=' + encodeURIComponent(studentId);
    }
}

// Calculate average and scaled mark
function calculateAverage(criteriaId, percentAllocation) {
    var initialInput = document.querySelector('input[name="initial_work[' + criteriaId + ']"]');
    var finalInput = document.querySelector('input[name="final_work[' + criteriaId + ']"]');
    var moderatorInput = document.querySelector('input[name="moderator_mark[' + criteriaId + ']"]');
    
    var initial = parseFloat(initialInput ? initialInput.value : 0) || 0;
    var final = parseFloat(finalInput ? finalInput.value : 0) || 0;
    var moderator = parseFloat(moderatorInput ? moderatorInput.value : 0) || 0;
    
    // Calculate average of non-zero values
    var values = [initial, final, moderator].filter(function(v) { return v > 0; });
    var avg = values.length > 0 ? values.reduce(function(a, b) { return a + b; }, 0) / values.length : 0;
    
    // Calculate scaled mark
    var scaled = (avg * percentAllocation) / 100;
    
    // Update display
    var avgEl = document.getElementById('avg_' + criteriaId);
    var scaledEl = document.getElementById('scaled_' + criteriaId);
    
    if (avgEl) avgEl.value = avg.toFixed(2);
    if (scaledEl) scaledEl.value = scaled.toFixed(2);
    
    // Recalculate total
    calculateFinalSubtotal();
}

// Calculate final subtotal
function calculateFinalSubtotal() {
    var scaledInputs = document.querySelectorAll('input[id^="scaled_"]');
    var total = 0;
    
    scaledInputs.forEach(function(input) {
        total += parseFloat(input.value) || 0;
    });
    
    var finalSubtotalEl = document.getElementById('finalSubtotal');
    if (finalSubtotalEl) {
        finalSubtotalEl.value = total.toFixed(2);
    }
}

// Reset form
function resetForm() {
    if (confirm('Are you sure you want to reset all values?')) {
        var form = document.getElementById('assessmentForm');
        if (form) {
            var inputs = form.querySelectorAll('input[type="number"]');
            inputs.forEach(function(input) {
                if (!input.readOnly) {
                    input.value = '';
                }
            });
            var readonlyInputs = form.querySelectorAll('input[readonly]');
            readonlyInputs.forEach(function(input) {
                input.value = '';
            });
            document.getElementById('finalSubtotal').value = '0.00';
        }
    }
}

// Toggle select all checkboxes
function toggleSelectAll(source) {
    var checkboxes = document.querySelectorAll('.student-checkbox');
    checkboxes.forEach(function(cb) {
        cb.checked = source.checked;
    });
}

// Confirm delete marks
function confirmDeleteMarks(studentId, studentName) {
    document.getElementById('delete_student_id').value = studentId;
    document.getElementById('delete_student_name').textContent = studentName;
    openModal('deleteModal');
}

// Export marks to CSV
function exportMarks() {
    var table = document.getElementById('marksTable');
    if (!table) return;
    
    var csv = [];
    var rows = table.querySelectorAll('tr');
    
    rows.forEach(function(row, index) {
        var cols = row.querySelectorAll('th, td');
        var rowData = [];
        cols.forEach(function(col, colIndex) {
            // Skip checkbox column and actions column
            if (colIndex === 0 || colIndex === cols.length - 1) return;
            var text = col.textContent.trim().replace(/"/g, '""');
            rowData.push('"' + text + '"');
        });
        if (rowData.length > 0) {
            csv.push(rowData.join(','));
        }
    });
    
    var csvContent = '\uFEFF' + csv.join('\n');
    var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    var link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'student_marks_' + new Date().toISOString().slice(0,10) + '.csv';
    link.click();
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    calculateFinalSubtotal();
    
    // Close modal when clicking outside
    document.querySelectorAll('.modal-overlay').forEach(function(modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal(this.id);
            }
        });
    });
});
</script>

</body>
</html>