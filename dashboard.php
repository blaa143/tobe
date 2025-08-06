 <?php
// dashboard.php - User dashboard with role-based features and summaries
require_once 'config.php'; // Includes database connection ($pdo) and session_start()

// --- Access Control: Only logged-in users can access this page ---
if (!isset($_SESSION['user_id'])) {
    redirect('auth_portal.php'); // Redirect to login if not logged in
}

$user_id = $_SESSION['user_id'];
$role_name = $_SESSION['role_name'];
$username = $_SESSION['username'];
$first_name = $_SESSION['first_name'];

$dashboard_message = ''; // For general dashboard messages

// Check for and display messages from other pages (e.g., project_details.php redirect)
if (isset($_SESSION['project_details_message'])) {
    $dashboard_message = '<div class="message">' . $_SESSION['project_details_message'] . '</div>';
    unset($_SESSION['project_details_message']);
}

// --- Dashboard Summary Data Fetching ---
$summary_data = [];
try {
    if ($role_name === 'Mentee') {
        // Fetch mentee_id first
        $stmt_mentee = $pdo->prepare("SELECT mentee_id FROM mentees WHERE user_id = ?");
        $stmt_mentee->execute([$user_id]);
        $mentee_data = $stmt_mentee->fetch(PDO::FETCH_ASSOC);

        if ($mentee_data) {
            $mentee_id = $mentee_data['mentee_id'];

            // Total projects submitted
            $stmt_total_projects = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE project_owner_mentee_id = ?");
            $stmt_total_projects->execute([$mentee_id]);
            $summary_data['total_projects'] = $stmt_total_projects->fetchColumn();

            // Projects by status
            $stmt_project_status = $pdo->prepare("SELECT status, COUNT(*) AS count FROM projects WHERE project_owner_mentee_id = ? GROUP BY status");
            $stmt_project_status->execute([$mentee_id]);
            $summary_data['project_status_counts'] = $stmt_project_status->fetchAll(PDO::FETCH_KEY_PAIR);

            // Personal task counts (tasks assigned to this mentee)
            $stmt_tasks = $pdo->prepare("SELECT status, COUNT(*) AS count FROM tasks WHERE assigned_to_user_id = ? GROUP BY status");
            $stmt_tasks->execute([$user_id]);
            $summary_data['personal_task_counts'] = $stmt_tasks->fetchAll(PDO::FETCH_KEY_PAIR);
        } else {
            $dashboard_message = '<div class="message" style="color: blue;">Your mentee profile is not fully set up. Please visit your <a href="profile.php">profile page</a> and save your details.</div>';
        }

    } elseif ($role_name === 'Mentor') {
        // Total projects assigned
        $stmt_assigned_projects = $pdo->prepare("SELECT COUNT(DISTINCT project_id) FROM mentorship_assignments WHERE mentor_user_id = ?");
        $stmt_assigned_projects->execute([$user_id]);
        $summary_data['total_assigned_projects'] = $stmt_assigned_projects->fetchColumn();

        // Tasks across assigned projects
        $stmt_mentor_tasks = $pdo->prepare("
            SELECT t.status, COUNT(t.task_id) AS count
            FROM tasks t
            JOIN mentorship_assignments ma ON t.project_id = ma.project_id
            WHERE ma.mentor_user_id = ?
            GROUP BY t.status
        ");
        $stmt_mentor_tasks->execute([$user_id]);
        $summary_data['assigned_project_task_counts'] = $stmt_mentor_tasks->fetchAll(PDO::FETCH_KEY_PAIR);

        // List of unique mentees assigned to
        $stmt_assigned_mentees = $pdo->prepare("
            SELECT DISTINCT
                u.first_name, u.last_name, u.username
            FROM users u
            JOIN mentees m ON u.user_id = m.user_id
            JOIN projects p ON m.mentee_id = p.project_owner_mentee_id
            JOIN mentorship_assignments ma ON p.project_id = ma.project_id
            WHERE ma.mentor_user_id = ?
            ORDER BY u.first_name, u.last_name
        ");
        $stmt_assigned_mentees->execute([$user_id]);
        $summary_data['assigned_mentees'] = $stmt_assigned_mentees->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($role_name === 'Administrator' || $role_name === 'Coordinator') {
        // Total users
        $stmt_total_users = $pdo->query("SELECT COUNT(*) FROM users");
        $summary_data['total_users'] = $stmt_total_users->fetchColumn();

        // Total projects
        $stmt_total_projects_admin = $pdo->query("SELECT COUNT(*) FROM projects");
        $summary_data['total_projects_admin'] = $stmt_total_projects_admin->fetchColumn();

        // Projects by status (overall)
        $stmt_project_status_admin = $pdo->query("SELECT status, COUNT(*) AS count FROM projects GROUP BY status");
        $summary_data['project_status_counts_admin'] = $stmt_project_status_admin->fetchAll(PDO::FETCH_KEY_PAIR);

        // Recently submitted projects (e.g., last 5)
        $stmt_recent_projects = $pdo->query("
            SELECT p.project_name, p.status, p.created_at, u.first_name, u.last_name, u.username
            FROM projects p
            JOIN mentees m ON p.project_owner_mentee_id = m.mentee_id
            JOIN users u ON m.user_id = u.user_id
            ORDER BY p.created_at DESC LIMIT 5
        ");
        $summary_data['recent_projects'] = $stmt_recent_projects->fetchAll(PDO::FETCH_ASSOC);

        // Recently added users (e.g., last 5)
        $stmt_recent_users = $pdo->query("
            SELECT u.first_name, u.last_name, u.username, r.role_name, u.created_at
            FROM users u JOIN roles r ON u.role_id = r.role_id
            ORDER BY u.created_at DESC LIMIT 5
        ");
        $summary_data['recent_users'] = $stmt_recent_users->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("PMMS Dashboard Summary Error: " . $e->getMessage());
    $dashboard_message = '<div class="message" style="color: red;">Error fetching dashboard summary: ' . $e->getMessage() . '</div>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PMMS - Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f0f2f5; margin: 0; padding: 0; }
        .header { background-color: #333; color: #fff; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { margin: 0; font-size: 24px; }
        .header .user-info { font-size: 16px; }
        .header .user-info span { font-weight: bold; }
        .nav-menu { list-style: none; margin: 0; padding: 0; background-color: #444; overflow: hidden; }
        .nav-menu li { float: left; }
        .nav-menu li a { display: block; color: white; text-align: center; padding: 14px 16px; text-decoration: none; }
        .nav-menu li a:hover { background-color: #555; }
        .nav-menu .logout { float: right; }
        .container { padding: 20px; max-width: 1200px; margin: 20px auto; background-color: #fff; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .welcome-message { font-size: 24px; margin-bottom: 20px; color: #333; }
        .message { padding: 10px; border-radius: 5px; margin-bottom: 20px; text-align: center; }
        .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        .summary-card {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            border: 1px solid #eee;
        }
        .summary-card h3 {
            margin-top: 0;
            color: #555;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .summary-card p {
            font-size: 1.1em;
            margin: 5px 0;
            color: #333;
        }
        .summary-card ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .summary-card li {
            padding: 5px 0;
            border-bottom: 1px dashed #eee;
        }
        .summary-card li:last-child {
            border-bottom: none;
        }
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: bold;
            color: white;
            text-transform: capitalize;
            margin-left: 5px;
        }
        /* Project Status Colors */
        .status-proposed { background-color: #ffc107; color: #333; }
        .status-approved { background-color: #28a745; }
        .status-in-progress { background-color: #007bff; }
        .status-completed { background-color: #6c757d; }
        .status-rejected { background-color: #dc3545; }

        /* Task Status Colors (ensure these match project_details.php) */
        .task-status-Pending { background-color: #ffc107; color: #333;}
        .task-status-In-Progress { background-color: #007bff; }
        .task-status-Completed { background-color: #28a745; }
        .task-status-On-Hold { background-color: #6c757d; }
    </style>
</head>
<body>
    <div class="header">
        <h1>PMMS Dashboard</h1>
        <div class="user-info">
            Welcome, <span><?php echo htmlspecialchars($first_name); ?></span>! (Role: <span><?php echo htmlspecialchars($role_name); ?></span>)
        </div>
    </div>
    <ul class="nav-menu">
        <li><a href="dashboard.php">Dashboard</a></li>
        <li><a href="profile.php">My Profile</a></li>
        <?php if ($role_name === 'Administrator' || $role_name === 'Coordinator') : ?>
            <li><a href="admin_users.php">Manage Users</a></li>
            <li><a href="projects_overview.php">Projects Overview</a></li>
            <li><a href="admin_log_viewer.php">View Security Logs</a></li>
        <?php endif; ?>
        <?php if ($role_name === 'Mentor') : ?>
            <li><a href="my_mentees.php">My Mentees</a></li>
            <li><a href="my_projects.php">Mentored Projects</a></li>
        <?php endif; ?>
        <?php if ($role_name === 'Mentee') : ?>
            <li><a href="my_projects.php">My Projects</a></li>
            <li><a href="submit_proposal.php">Submit New Project Proposal</a></li>
            <li><a href="find_mentor.php">Find a Mentor</a></li>
        <?php endif; ?>
        <li class="logout"><a href="logout.php">Logout</a></li>
    </ul>

    <div class="container">
        <h2 class="welcome-message">Hello, <?php echo htmlspecialchars($first_name); ?>!</h2>
        <?php echo $dashboard_message; // Display any general dashboard messages ?>

        <div class="dashboard-grid">
            <?php if ($role_name === 'Mentee'): ?>
                <div class="summary-card">
                    <h3>My Projects Summary</h3>
                    <p>Total Projects Submitted: <strong><?php echo htmlspecialchars($summary_data['total_projects'] ?? 0); ?></strong></p>
                    <ul>
                        <?php foreach(['Proposed', 'Approved', 'In Progress', 'Completed', 'Rejected'] as $status): ?>
                            <li><?php echo $status; ?>:
                                <strong><?php echo htmlspecialchars($summary_data['project_status_counts'][$status] ?? 0); ?></strong>
                                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $status)); ?>"></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="summary-card">
                    <h3>My Tasks Summary</h3>
                    <p>Tasks Assigned To Me:</p>
                    <ul>
                        <?php foreach(['Pending', 'In Progress', 'Completed', 'On Hold'] as $status): ?>
                            <li><?php echo $status; ?>:
                                <strong><?php echo htmlspecialchars($summary_data['personal_task_counts'][$status] ?? 0); ?></strong>
                                <span class="status-badge task-status-<?php echo str_replace(' ', '-', $status); ?>"></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php elseif ($role_name === 'Mentor'): ?>
                <div class="summary-card">
                    <h3>My Mentored Projects</h3>
                    <p>Total Projects Assigned: <strong><?php echo htmlspecialchars($summary_data['total_assigned_projects'] ?? 0); ?></strong></p>
                    <p>Tasks in Assigned Projects:</p>
                    <ul>
                        <?php foreach(['Pending', 'In Progress', 'Completed', 'On Hold'] as $status): ?>
                            <li><?php echo $status; ?>:
                                <strong><?php echo htmlspecialchars($summary_data['assigned_project_task_counts'][$status] ?? 0); ?></strong>
                                <span class="status-badge task-status-<?php echo str_replace(' ', '-', $status); ?>"></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="summary-card">
                    <h3>My Mentees</h3>
                    <?php if (!empty($summary_data['assigned_mentees'])): ?>
                        <ul>
                            <?php foreach ($summary_data['assigned_mentees'] as $mentee): ?>
                                <li><?php echo htmlspecialchars($mentee['first_name'] . ' ' . $mentee['last_name'] . ' (' . $mentee['username'] . ')'); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>No mentees currently assigned to your projects.</p>
                    <?php endif; ?>
                </div>
                <?php elseif ($role_name === 'Administrator' || $role_name === 'Coordinator'): ?>
                <div class="summary-card">
                    <h3>System Overview</h3>
                    <p>Total Users: <strong><?php echo htmlspecialchars($summary_data['total_users'] ?? 0); ?></strong></p>
                    <p>Total Projects: <strong><?php echo htmlspecialchars($summary_data['total_projects_admin'] ?? 0); ?></strong></p>
                    <p>Projects by Status:</p>
                    <ul>
                        <?php foreach(['Proposed', 'Approved', 'In Progress', 'Completed', 'Rejected'] as $status): ?>
                            <li><?php echo $status; ?>:
                                <strong><?php echo htmlspecialchars($summary_data['project_status_counts_admin'][$status] ?? 0); ?></strong>
                                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $status)); ?>"></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="summary-card">
                    <h3>Recent Projects</h3>
                    <?php if (!empty($summary_data['recent_projects'])): ?>
                        <ul>
                            <?php foreach ($summary_data['recent_projects'] as $project): ?>
                                <li>
                                    <strong><?php echo htmlspecialchars($project['project_name']); ?></strong> (by <?php echo htmlspecialchars($project['first_name'] . ' ' . $project['last_name']); ?>)
                                    <br><small>Status: <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $project['status'])); ?>"><?php echo htmlspecialchars($project['status']); ?></span> - <?php echo date('Y-m-d', strtotime($project['created_at'])); ?></small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>No recent projects.</p>
                    <?php endif; ?>
                </div>
                <div class="summary-card">
                    <h3>Recent Users</h3>
                    <?php if (!empty($summary_data['recent_users'])): ?>
                        <ul>
                            <?php foreach ($summary_data['recent_users'] as $user): ?>
                                <li>
                                    <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong> (<?php echo htmlspecialchars($user['role_name']); ?>)
                                    <br><small>Joined: <?php echo date('Y-m-d', strtotime($user['created_at'])); ?></small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>No recent user registrations.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
