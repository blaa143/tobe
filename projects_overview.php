 <?php
// projects_overview.php - Displays a table of all submitted projects for admin/coordinator management
require_once 'config.php';

// --- Access Control: Only Admins and Coordinators can see this page ---
if (!isset($_SESSION['user_id']) || ($_SESSION['role_name'] !== 'Administrator' && $_SESSION['role_name'] !== 'Coordinator')) {
    redirect('dashboard.php');
}

$message = '';
$projects = [];

// --- Handle Project Status Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $project_id = (int)$_POST['project_id'];
    $new_status = trim($_POST['new_status'] ?? '');

    $allowed_statuses = ['Proposed', 'Approved', 'Rejected', 'In Progress', 'Completed'];
    if (!in_array($new_status, $allowed_statuses)) {
        $message = '<p style="color: red;">Invalid project status provided.</p>';
    } else {
        try {
            $stmt_update = $pdo->prepare("UPDATE projects SET status = ? WHERE project_id = ?");
            $stmt_update->execute([$new_status, $project_id]);
            $message = '<p style="color: green;">Project status updated successfully!</p>';
        } catch (PDOException $e) {
            $message = '<p style="color: red;">Error updating project status: ' . $e->getMessage() . '</p>';
        }
    }
}

// --- Handle Project Deletion (if coming from a form, not link) ---
// Note: The delete link is handled by a separate page for better practice.

// Fetch all projects and their associated details for the table
try {
    $stmt = $pdo->prepare("
        SELECT
            p.project_id,
            p.project_name,
            p.status,
            p.created_at,
            CONCAT(u_mentee.first_name, ' ', u_mentee.last_name) AS mentee_name,
            GROUP_CONCAT(DISTINCT CONCAT(u_mentor.first_name, ' ', u_mentor.last_name) SEPARATOR ', ') AS assigned_mentor_names
        FROM
            projects p
        JOIN
            mentees m ON p.project_owner_mentee_id = m.mentee_id
        JOIN
            users u_mentee ON m.user_id = u_mentee.user_id
        LEFT JOIN
            mentorship_assignments ma ON p.project_id = ma.project_id
        LEFT JOIN
            users u_mentor ON ma.mentor_user_id = u_mentor.user_id AND (SELECT r.role_name FROM roles r WHERE r.role_id = u_mentor.role_id) = 'Mentor'
        GROUP BY
            p.project_id
        ORDER BY
            p.created_at DESC
    ");
    $stmt->execute();
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $message = '<p style="color: red;">Error fetching projects: ' . $e->getMessage() . '</p>';
}

// Check for and display messages from session
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
    <title>PMMS - Project Overview</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f0f2f5; margin: 0; padding: 20px; }
        .container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); max-width: 1200px; margin: 20px auto; }
        h1 { color: #333; margin-bottom: 20px; text-align: center; }
        .message { text-align: center; margin-bottom: 20px; }
        .project-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .project-table th, .project-table td { border: 1px solid #ddd; padding: 12px; text-align: left; vertical-align: top; }
        .project-table th { background-color: #e9ecef; }
        .project-table tr:nth-child(even) { background-color: #f9f9f9; }
        .status-form { display: flex; align-items: center; }
        .status-select { padding: 5px; border-radius: 4px; border: 1px solid #ddd; margin-right: 5px; }
        .update-btn {
            padding: 5px 10px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .update-btn:hover { background-color: #0056b3; }
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 5px;
            font-weight: bold;
            color: white;
            text-transform: capitalize;
        }
        .status-proposed { background-color: #ffc107; } /* yellow */
        .status-approved { background-color: #28a745; } /* green */
        .status-in-progress { background-color: #007bff; } /* blue */
        .status-completed { background-color: #6c757d; } /* grey */
        .status-rejected { background-color: #dc3545; } /* red */
        .action-links a { margin-right: 10px; text-decoration: none; color: #007bff; }
        .action-links a.delete-link { color: #dc3545; }
        .action-links a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Projects Overview</h1>
        <?php echo $message; ?>

        <?php if (empty($projects)): ?>
            <p style="text-align: center;">No projects have been submitted yet.</p>
        <?php else: ?>
            <table class="project-table">
                <thead>
                    <tr>
                        <th>Project Name</th>
                        <th>Mentee</th>
                        <th>Status</th>
                        <th>Assigned Mentor(s)</th>
                        <th>Submitted On</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projects as $project): ?>
                    <tr>
                        <td>
                            <a href="project_details.php?id=<?php echo htmlspecialchars($project['project_id']); ?>">
                                <?php echo htmlspecialchars($project['project_name']); ?>
                            </a>
                        </td>
                        <td><?php echo htmlspecialchars($project['mentee_name']); ?></td>
                        <td>
                            <form action="projects_overview.php" method="POST" class="status-form">
                                <input type="hidden" name="project_id" value="<?php echo htmlspecialchars($project['project_id']); ?>">
                                <select name="new_status" class="status-select">
                                    <option value="Proposed" <?php echo ($project['status'] === 'Proposed') ? 'selected' : ''; ?>>Proposed</option>
                                    <option value="Approved" <?php echo ($project['status'] === 'Approved') ? 'selected' : ''; ?>>Approved</option>
                                    <option value="Rejected" <?php echo ($project['status'] === 'Rejected') ? 'selected' : ''; ?>>Rejected</option>
                                    <option value="In Progress" <?php echo ($project['status'] === 'In Progress') ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="Completed" <?php echo ($project['status'] === 'Completed') ? 'selected' : ''; ?>>Completed</option>
                                </select>
                                <button type="submit" name="update_status" class="update-btn">Update</button>
                            </form>
                        </td>
                        <td><?php echo htmlspecialchars($project['assigned_mentor_names'] ?? 'None'); ?></td>
                        <td><?php echo date('Y-m-d', strtotime($project['created_at'])); ?></td>
                        <td class="action-links">
                            <a href="assign_mentor.php?project_id=<?php echo htmlspecialchars($project['project_id']); ?>">Assign Mentor</a>
                            <a href="project_details.php?id=<?php echo htmlspecialchars($project['project_id']); ?>">View</a>
                            <a href="delete_project.php?id=<?php echo htmlspecialchars($project['project_id']); ?>" class="delete-link" onclick="return confirm('Are you sure you want to delete this project?');">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>