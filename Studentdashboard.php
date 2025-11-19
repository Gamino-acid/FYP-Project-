<?php
include 'db_connect.php';

$student_id = 1;

$sql = "SELECT project_title, project_phase, supervisor FROM projects WHERE student_id = '$student_id'";
$result = $conn->query($sql);

if (!$result) {
    die("SQL Error: " . $conn->error);
}

if ($result->num_rows > 0) {
    $project = $result->fetch_assoc();
} else {
    $project = [
        'project_title' => 'No Project Assigned',
        'project_phase' => 'N/A',
        'supervisor' => 'N/A'
    ];
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="css/Studentdashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>

    <div class="dashboard-container">
        
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>FYP Portal</h2>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li class="active"><a href="#"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="#"><i class="fas fa-tasks"></i> My Project</a></li>
                    <li><a href="#"><i class="fas fa-users"></i> Team Members</a></li>
                    <li><a href="#"><i class="fas fa-file-alt"></i> Submissions</a></li>
                    <li><a href="#"><i class="fas fa-comments"></i> Messages</a></li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            
            <header class="header">
                <div class="header-title">
                    <h1>Welcome, Student</h1>
                </div>
                <div class="header-user">
                    <span>Student ID: <?php echo $student_id; ?></span>
                    <a href="Login.php" class="logout-btn">Logout</a>
                </div>
            </header>

            <section class="content">
                <div class="content-grid">
                
                    <div class="widget">
                        <h3>Project Status</h3>
                        <p><strong>Title:</strong> <?php echo $project['project_title']; ?></p>
                        <p><strong>Phase:</strong> <?php echo $project['project_phase']; ?></p>
                        <p><strong>Supervisor:</strong> <?php echo $project['supervisor']; ?></p>
                        <div class="status-bar">
                            <div class="status-progress" style="width: 25%;">25%</div>
                        </div>
                    </div>

                    <div class="widget">
                        <h3>Upcoming Deadlines</h3>
                        <ul>
                            <li><strong>Literature Review:</strong> 15th December 2024</li>
                            <li><strong>Initial Prototype:</strong> 20th January 2025</li>
                            <li><strong>Mid-term Report:</strong> 15th February 2025</li>
                        </ul>
                    </div>

                    <div class="widget">
                        <h3>Recent Feedback</h3>
                        <p>"Your proposal looks promising. Please add more details to section 3.2 regarding the dataset..."</p>
                        <a href="#">View All Feedback</a>
                    </div>

                </div>
            </section>
        </main>
    </div>

</body>
</html>
