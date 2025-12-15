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

$sql = "SELECT * FROM total_mark WHERE fyp_studid = '$current_student_id'";
$result = $conn->query($sql);
$marks = ($result && $result->num_rows > 0) ? $result->fetch_assoc() : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Results - FYP Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/Academic_mark.css">
</head>
<body>
    <div class="st-container" id="stContainer">
        <nav class="st-menu">
            <div class="sidebar-profile">
                <img src="https://ui-avatars.com/api/?name=John+Doe&background=42a5f5&color=fff&size=90" alt="Profile">
                <div class="student-name">John Doe</div>
                <div class="student-id">S12345</div>
            </div>
            <ul class="sidebar-nav">
                <li><a href="#"><i class="fas fa-home icon-left"></i> Dashboard</a></li>
                <li><a href="#"><i class="fas fa-project-diagram icon-left"></i> My Project</a></li>
                <li><a href="#" class="active"><i class="fas fa-chart-bar icon-left"></i> Results</a></li>
                <li><a href="#"><i class="fas fa-file-alt icon-left"></i> Documents</a></li>
                <li><a href="#"><i class="fas fa-calendar icon-left"></i> Schedule</a></li>
                <li><a href="#"><i class="fas fa-comments icon-left"></i> Messages</a></li>
                <li><a href="#"><i class="fas fa-bell icon-left"></i> Notifications</a></li>
                <li><a href="#"><i class="fas fa-cog icon-left"></i> Settings</a></li>
            </ul>
            <div class="sidebar-footer">
                <a href="#"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </nav>

        <div class="st-pusher">
            <header class="header">
                <div class="header-left">
                    <div class="menu-trigger" id="menuToggle">
                        <i class="fas fa-bars"></i>
                    </div>
                    <div class="logo">FYP System</div>
                    <nav class="header-nav">
                        <div class="dropdown">
                            <a class="dropdown-toggle">Announcements</a>
                        </div>
                        <div class="dropdown">
                            <a class="dropdown-toggle">Projects</a>
                        </div>
                        <div class="dropdown">
                            <a class="dropdown-toggle">Student</a>
                        </div>
                    </nav>
                </div>
                <div class="header-right">
                    <i class="fas fa-bell" style="font-size: 1.1rem; cursor: pointer;"></i>
                    <div class="user-info">
                        <i class="fas fa-user" style="font-size: 1rem;"></i>
                        <span>Bill</span>
                        <span>Ali</span>
                        <i class="fas fa-chevron-down" style="font-size: 0.8rem;"></i>
                    </div>
                </div>
            </header>

            <div class="deadline-banner">
                <div class="deadline-info">
                    <div class="deadline-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="deadline-text">
                        Final Report Submission Deadline: <strong>25 May 2025</strong>
                    </div>
                </div>
                <div class="deadline-action">
                    <button class="btn">DETAILS</button>
                </div>
            </div>
            <div class="main-scroll">
                <div class="card">
                    <div class="card-header">
                        <h3>Academic Results</h3>
                        <p>Final Year Project Assessment</p>
                    </div>
                    
                    <div class="card-body">
                        <?php if ($marks): ?>
                            <div class="grade-summary">
                                <div class="grade-box">
                                    <h4>Total Score</h4>
                                    <div class="big-score"><?php echo number_format($marks['fyp_totalmark'], 2); ?></div>
                                    <small style="color:#999">/ 100.00</small>
                                </div>
                                <div class="grade-box">
                                    <h4>Grade</h4>
                                    <div class="grade-letter">
                                        <?php echo !empty($marks['grade']) ? $marks['grade'] : "A"; ?>
                                    </div>
                                    <span class="pass-badge">PASSED</span>
                                </div>
                            </div>

                            <div class="assessment-breakdown">
                                <h4>Assessment Breakdown</h4>
                                
                                <div class="mark-row">
                                    <div>
                                        <div class="mark-title">Supervisor Assessment</div>
                                        <div class="mark-subtitle">Progress & Report Evaluation</div>
                                    </div>
                                    <div class="mark-value">
                                        <?php echo number_format($marks['fyp_totalfinalsupervisor'], 2); ?>
                                    </div>
                                </div>

                                <div class="mark-row">
                                    <div>
                                        <div class="mark-title">Moderator Assessment</div>
                                        <div class="mark-subtitle">Presentation Evaluation</div>
                                    </div>
                                    <div class="mark-value">
                                        <?php echo number_format($marks['fyp_totalfinalmoderator'], 2); ?>
                                    </div>
                                </div>

                                <div class="mark-row" style="border-top:2px solid #ddd; margin-top:10px; padding-top:15px;">
                                    <div class="mark-title" style="font-size:1.1rem;">Final Calculated Mark</div>
                                    <div class="mark-value" style="color:#2196f3; font-size:1.3rem;">
                                        <?php echo number_format($marks['fyp_totalmark'], 2); ?>
                                    </div>
                                </div>
                            </div>

                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-clipboard-list"></i>
                                <h3>No results available yet.</h3>
                                <p>Your assessment results have not been finalized.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const menuToggle = document.getElementById('menuToggle');
        const stContainer = document.getElementById('stContainer');

        menuToggle.addEventListener('click', function() {
            stContainer.classList.toggle('st-menu-open');
        });

        document.querySelector('.st-pusher').addEventListener('click', function(e) {
            if (stContainer.classList.contains('st-menu-open') && 
                e.target.classList.contains('st-pusher')) {
                stContainer.classList.remove('st-menu-open');
            }
        });

        const pusher = document.querySelector('.st-pusher');
        pusher.addEventListener('click', function(e) {
            if (stContainer.classList.contains('st-menu-open') && 
                e.target === pusher) {
                stContainer.classList.remove('st-menu-open');
            }
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>
