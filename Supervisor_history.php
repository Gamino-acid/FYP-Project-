<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "fyp_management";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$current_user = "STAFF001";

$sql_list = "SELECT DISTINCT fyp_supervisorid FROM SUPERVISOR_TYPE_HISTORY ORDER BY fyp_supervisorid ASC";
$result_list = $conn->query($sql_list);

$history_data = [];
$sql_history = "SELECT h.*, a.fyp_acdyear, a.fyp_intake 
                FROM SUPERVISOR_TYPE_HISTORY h
                LEFT JOIN ACADEMIC_YEAR a ON h.fyp_academicid = a.fyp_academicid
                ORDER BY h.fyp_createddate DESC";
$result_history = $conn->query($sql_history);

if ($result_history->num_rows > 0) {
    while($row = $result_history->fetch_assoc()) {
        $history_data[] = $row;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Supervisor Type History</title>
    <style>
        .modal {
            display: none; 
            position: fixed; 
            z-index: 100; 
            left: 0;
            top: 0;
            width: 100%; 
            height: 100%; 
            background-color: rgba(0,0,0,0.5); 
        }

        .modal-content {
            background-color: #fff;
            margin: 5% auto; 
            padding: 0;
            border: 1px solid #ccc;
            width: 80%; 
            max-width: 800px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            font-family: Arial, sans-serif;
            max-height: 80vh; 
            overflow-y: auto;
        }

        .modal-header {
            padding: 15px;
            background-color: #f1f1f1;
            border-bottom: 1px solid #ddd;
            font-weight: bold;
            font-size: 18px;
            position: sticky;
            top: 0;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            padding: 10px 15px;
            background-color: #f1f1f1;
            border-top: 1px solid #ddd;
            text-align: right;
            position: sticky;
            bottom: 0;
        }

        button {
            padding: 8px 12px;
            cursor: pointer;
        }

        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>

    <h1>Supervisor Type History Logs</h1>
    <p>This page displays the audit trail for supervisor status changes.</p>
    
    <br><hr><br>

    <h3>Supervisor List</h3>
    <table border="1" cellpadding="5">
        <thead>
            <tr>
                <th>Supervisor ID</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($result_list->num_rows > 0) {
                while($row = $result_list->fetch_assoc()) {
                    $sup_id = $row['fyp_supervisorid'];
                    echo "<tr>";
                    echo "<td><b>" . $sup_id . "</b></td>";
                    echo "<td>
                            <button onclick='openHistoryModal(\"$sup_id\")'>View Full History</button>
                          </td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='2'>No history records found.</td></tr>";
            }
            ?>
        </tbody>
    </table>

    <div id="historyModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                History for Supervisor: <span id="modalSupID"></span>
            </div>
            
            <div class="modal-body">
                <table id="historyTable" border="1" cellpadding="5">
                    <thead>
                        <tr>
                            <th>Academic Year</th>
                            <th>Status</th>
                            <th>Created By</th>
                            <th>Date Created</th>
                            <th>Edited By</th>
                            <th>Date Edited</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
            
            <div class="modal-footer">
                <button type="button" onclick="closeModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        var allHistory = <?php echo json_encode($history_data); ?>;
        var modal = document.getElementById("historyModal");
        var modalTitle = document.getElementById("modalSupID");
        var tableBody = document.querySelector("#historyTable tbody");

        function openHistoryModal(supervisorID) {
            modalTitle.innerText = supervisorID;

            tableBody.innerHTML = "";

            var foundRecords = false;

            for (var i = 0; i < allHistory.length; i++) {
                var record = allHistory[i];

                if (record.fyp_supervisorid === supervisorID) {
                    foundRecords = true;
                    var row = tableBody.insertRow();
                    
                    var statusText = (record.fyp_isfulltime == 1) ? "Full Time" : "Part Time";
                    var yearText = record.fyp_acdyear ? (record.fyp_acdyear + " (" + record.fyp_intake + ")") : "Unknown ID: " + record.fyp_academicid;

                    row.insertCell(0).innerText = yearText;
                    row.insertCell(1).innerText = statusText;
                    row.insertCell(2).innerText = record.fyp_createdby;
                    row.insertCell(3).innerText = record.fyp_createddate;
                    row.insertCell(4).innerText = record.fyp_editedby ? record.fyp_editedby : "-";
                    row.insertCell(5).innerText = record.fyp_editeddate ? record.fyp_editeddate : "-";
                }
            }

            if (!foundRecords) {
                var row = tableBody.insertRow();
                var cell = row.insertCell(0);
                cell.colSpan = 6;
                cell.innerText = "No detailed records found.";
                cell.style.textAlign = "center";
            }

            modal.style.display = "block";
        }

        function closeModal() {
            modal.style.display = "none";
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>

</body>
</html>