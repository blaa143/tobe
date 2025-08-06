 <?php
// my_mentees.php - Displays a list of projects and assigned mentees for the logged-in mentor.
require_once 'config.php';

// --- Access Control: Only Mentors can access this page ---
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] !== 'Mentor') {
    redirect('dashboard.php');
}

$message = '';
$assigned_projects = [];
$mentor_user_id = $_SESSION['user_id'];

try {
    // Fetch all projects assigned to the logged-in mentor
    $stmt = $pdo->prepare("
        SELECT 
            p.project_id,
            p.project_name,
            p.status,
            CONCAT(u.first_name, ' ', u.last_name) AS mentee_name,
            u.email AS mentee_email
        FROM mentorship_assignments ma
        JOIN projects p ON ma.project_id = p.project_id
        JOIN mentees m ON p.project_owner_mentee_id = m.mentee_id
        JOIN users u ON m.user_id = u.user_id
        WHERE ma.mentor_user_id = ?
        ORDER BY p.status, p.project_name
    ");
    $stmt->execute([$mentor_user_id]);
    $assigned_projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("PMMS My Mentees Error: " . $e->getMessage());
    $message = '<p style="color: red;">Error fetching your assigned mentees and projects.</p>';
}

// Display messages from the session if they exist
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PMMS - My Mentees</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f0f2f5; margin: 0; padding: 20px; }
        .container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); max-width: 900px; margin: 20px auto; }
        h1 { color: #333; margin-bottom: 20px; text-align: center; }
        .message { text-align: center; margin-bottom: 20px; }
        .back-link { margin-bottom: 20px; text-align: left; }
        .back-link a { color: #007bff; text-decoration: none; }
        .back-link a:hover { text-decoration: underline; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; vertical-align: middle; }
        th { background-color: #e9ecef; }
        .action-links a {
            color: #007bff;
            text-decoration: none;
            padding: 5px;
            border-radius: 4px;
        }
        .action-links a:hover {
            background-color: #e7f5ff;
        }
        .status-badge { display: inline-block; padding: 5px 10px; border-radius: 5px; font-weight: bold; color: white; text-transform: capitalize; }
        .status-proposed { background-color: #ffc107; }
        .status-approved { background-color: #28a745; }
        .status-in-progress { background-color: #007bff; }
        .status-completed { background-color: #6c757d; }
    </style>
</head>
<body>
    <div class="container">
        <div class="back-link">
            <a href="dashboard.php">&larr; Back to Dashboard</a>
        </div>
        <h1>My Mentees & Projects</h1>
        <?php echo $message; ?>

        <?php if (empty($assigned_projects)): ?>
            <p style="text-align: center;">You are not currently assigned to any projects or mentees.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Project Name</th>
                        <th>Mentee Name</th>
                        <th>Mentee Email</th>
                        <th>Project Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assigned_projects as $project): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($project['project_name']); ?></td>
                            <td><?php echo htmlspecialchars($project['mentee_name']); ?></td>
                            <td><a href="mailto:<?php echo htmlspecialchars($project['mentee_email']); ?>"><?php echo htmlspecialchars($project['mentee_email']); ?></a></td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $project['status'])); ?>">
                                    <?php echo htmlspecialchars($project['status']); ?>
                                </span>
                            </td>
                            <td class="action-links">
                                <a href="project_details.php?id=<?php echo htmlspecialchars($project['project_id']); ?>">View Project</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>