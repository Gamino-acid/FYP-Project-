<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - FYP Management System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: #333;
        }

        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .navbar-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar h1 {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .navbar-right {
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .nav-links {
            display: flex;
            gap: 1.5rem;
            list-style: none;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            transition: opacity 0.3s;
        }

        .nav-links a:hover {
            opacity: 0.8;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .profile-header {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            font-weight: 600;
            position: relative;
        }

        .avatar-upload {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 36px;
            height: 36px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        .avatar-upload i {
            color: #667eea;
            font-size: 0.9rem;
        }

        .profile-info h2 {
            color: #333;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        .profile-role {
            color: #667eea;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }

        .profile-meta {
            display: flex;
            gap: 2rem;
            color: #666;
            font-size: 0.9rem;
        }

        .profile-meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 1.5rem;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }

        .card-header h3 {
            color: #333;
            font-size: 1.2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #555;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.95rem;
            font-family: inherit;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            font-size: 0.95rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-outline {
            background: white;
            border: 1px solid #667eea;
            color: #667eea;
        }

        .btn-outline:hover {
            background: #667eea;
            color: white;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e0e0e0;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 1rem;
            border-bottom: 1px solid #f0f0f0;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            color: #666;
            font-weight: 500;
        }

        .info-value {
            color: #333;
        }

        .stat-box {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 1rem;
        }

        .stat-box h4 {
            font-size: 2rem;
            color: #667eea;
            margin-bottom: 0.5rem;
        }

        .stat-box p {
            color: #666;
            font-size: 0.9rem;
        }

        .research-interests {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .interest-tag {
            background: #e7f0ff;
            color: #667eea;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
        }

        .full-width {
            grid-column: 1 / -1;
        }

        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .navbar-content {
                flex-direction: column;
                gap: 1rem;
            }

            .profile-header {
                flex-direction: column;
                text-align: center;
            }

            .profile-meta {
                flex-direction: column;
                gap: 0.5rem;
            }
        }
    </style>
</head>
<body>

    <nav class="navbar">
    <div class="navbar-content">
        <h1><i class="fas fa-graduation-cap"></i> FYP Management System</h1>
        <div class="navbar-right">
            <ul class="nav-links">
                <li><a href="dashboard.html"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="my_students.html"><i class="fas fa-users"></i> My Students</a></li>
                <li><a href="submissions.html"><i class="fas fa-file-alt"></i> Submissions</a></li>
                <li><a href="meetings.html"><i class="fas fa-calendar"></i> Meetings</a></li>
            </ul>
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
                <span>Dr. Adudu Banana</span>
                <a href="profile.html" style="color: white; margin-left: 1rem;"><i class="fas fa-cog"></i></a>
            </div>
        </div>
    </div>
   </nav>

    <div class="container">
        <div class="profile-header">
            <div class="profile-avatar">
                AB
                <div class="avatar-upload">
                    <i class="fas fa-camera"></i>
                </div>
            </div>
            <div class="profile-info">
                <h2>Dr. Adudu Banana</h2>
                <div class="profile-role">Senior Lecturer - Computer Science</div>
                <div class="profile-meta">
                    <div class="profile-meta-item">
                        <i class="fas fa-envelope"></i>
                        <span>Adudu.Banana@Staff.mmu.edu.my</span>
                    </div>
                    <div class="profile-meta-item">
                        <i class="fas fa-phone"></i>
                        <span>+60 13-7196948</span>
                    </div>
                    <div class="profile-meta-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>Office: FIST, MBTI 0100</span>
                    </div>
                </div>
            </div>
        </div>



        <div class="content-grid">
            <div class="card full-width">
                <div class="card-header">
                    <h3><i class="fas fa-user-edit"></i> Personal Information</h3>
                    <button class="btn btn-outline" id="editBtn">
                        <i class="fas fa-edit"></i> Edit Profile
                    </button>
                </div>

                <form id="profileForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name</label>
                            <input type="text" value="Adudu" disabled>
                        </div>
                        <div class="form-group">
                            <label>Last Name</label>
                            <input type="text" value="Banana" disabled>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Title</label>
                            <select disabled>
                                <option>Dr.</option>
                                <option>Prof.</option>
                                <option>Assoc. Prof.</option>
                                <option>Mr.</option>
                                <option>Ms.</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Staff ID</label>
                            <input type="text" value="S2024001" disabled>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" value="Adudu.Banana@Staff.mmu.edu.my" disabled>
                        </div>
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="tel" value="+60 13-7196948 " disabled>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Office Location</label>
                        <input type="text" value="FIST, MBTI 0100" disabled>
                    </div>

                    <div class="form-group">
                        <label>Department</label>
                        <input type="text" value="Faculty of Information Technology" disabled>
                    </div>

                    <div class="form-group">
                        <label>Specialization</label>
                        <input type="text" value="Artificial Intelligence, Machine Learning, Data Science" disabled>
                    </div>

                    <div class="form-group">
                        <label>Office Hours</label>
                        <textarea disabled>Monday: 2:00 PM - 4:00 PM
Wednesday: 10:00 AM - 12:00 PM
Friday: 3:00 PM - 5:00 PM</textarea>
                    </div>

                    <div class="form-group">
                        <label>Bio / About Me</label>
                        <textarea disabled>Mr.Banana is a Senior Lecturer in the Faculty of Information Science with over 10 years of experience in teaching and research at MMU. </textarea>
                    </div>

                    <div class="form-actions" style="display: none;" id="formActions">
                        <button type="button" class="btn btn-secondary" id="cancelBtn">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-line"></i> Supervision Statistics</h3>
                </div>

                <div class="stat-box">
                    <h4>3</h4>
                    <p>Current Students</p>
                </div>

                <div class="stat-box">
                    <h4>21</h4>
                    <p>Total Students Supervised</p>
                </div>

                <div class="stat-box">
                    <h4>1</h4>
                    <p>Active Projects</p>
                </div>

                <div class="stat-box">
                    <h4>6</h4>
                    <p>Years of Supervision</p>
                </div>
            </div>

            <div class="card full-width">
                <div class="card-header">
                    <h3><i class="fas fa-lock"></i> Change Password</h3>
                </div>

                <form>
                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" placeholder="Enter current password">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" placeholder="Enter new password">
                        </div>
                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <input type="password" placeholder="Confirm new password">
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Update Password</button>
                    </div>
                </form>
            </div>

            <div class="card full-width">
                <div class="card-header">
                    <h3><i class="fas fa-bell"></i> Notification Preferences</h3>
                </div>

                <div class="info-item">
                    <div class="info-label">
                        <i class="fas fa-envelope"></i> Email Notifications
                    </div>
                    <label style="cursor: pointer;">
                        <input type="checkbox" checked> Enabled
                    </label>
                </div>

                <div class="info-item">
                    <div class="info-label">
                        <i class="fas fa-file-alt"></i> New Submission Alerts
                    </div>
                    <label style="cursor: pointer;">
                        <input type="checkbox" checked> Enabled
                    </label>
                </div>

                <div class="info-item">
                    <div class="info-label">
                        <i class="fas fa-calendar"></i> Meeting Reminders
                    </div>
                    <label style="cursor: pointer;">
                        <input type="checkbox" checked> Enabled
                    </label>
                </div>

                <div class="info-item">
                    <div class="info-label">
                        <i class="fas fa-comment"></i> Message Notifications
                    </div>
                    <label style="cursor: pointer;">
                        <input type="checkbox" checked> Enabled
                    </label>
                </div>

                <div class="form-actions">
                    <button class="btn btn-primary">Save Preferences</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const editBtn = document.getElementById('editBtn');
        const cancelBtn = document.getElementById('cancelBtn');
        const formActions = document.getElementById('formActions');
        const form = document.getElementById('profileForm');
        const inputs = form.querySelectorAll('input, textarea, select');

        editBtn.addEventListener('click', function() {
            inputs.forEach(input => {
                if (input.type !== 'email' && input.value !== 'S2024001') {
                    input.disabled = false;
                }
            });
            formActions.style.display = 'flex';
            editBtn.style.display = 'none';
        });

        cancelBtn.addEventListener('click', function() {
            inputs.forEach(input => input.disabled = true);
            formActions.style.display = 'none';
            editBtn.style.display = 'inline-block';
        });

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            inputs.forEach(input => input.disabled = true);
            formActions.style.display = 'none';
            editBtn.style.display = 'inline-block';
            
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-success';
            alertDiv.innerHTML = '<i class="fas fa-check-circle"></i> Profile updated successfully!';
            form.insertBefore(alertDiv, form.firstChild);
            
            setTimeout(() => alertDiv.remove(), 3000);
        });
    </script>
</body>
</html>
