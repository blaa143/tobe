 <?php
// my_projects.php - Displays a list of projects for the logged-in user (Mentee or Mentor)
require_once 'config.php'; // Includes database connection ($pdo) and session_start()

// --- Access Control: Only Mentees or Mentors can access this page ---
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_name'], ['Mentee', 'Mentor'])) {
    redirect('dashboard.php'); // Redirect if not authorized
}

$user_id = $_SESSION['user_id'];
$role_name = $_SESSION['role_name'];
$message = ''; // To display messages
$projects = []; // Array to hold fetched projects

try {
    if ($role_name === 'Mentee') {
        // --- Fetch Projects for a Mentee ---
        $stmt_mentee = $pdo->prepare("SELECT mentee_id FROM mentees WHERE user_id = ?");
        $stmt_mentee->execute([$user_id]);
        $mentee_data = $stmt_mentee->fetch(PDO::FETCH_ASSOC);

        if (!$mentee_data) {
            $message = '<p style="color: red;">Your mentee profile is incomplete. Please visit your <a href="profile.php">profile page</a> and save your details first to see your projects.</p>';
        } else {
            $mentee_id = $mentee_data['mentee_id'];

            $stmt_projects = $pdo->prepare("
                SELECT
                    p.project_id,
                    p.project_name,
                    p.description,
                    p.status,
                    p.start_date,
                    p.end_date,
                    p.created_at,
                    GROUP_CONCAT(DISTINCT CONCAT(um.first_name, ' ', um.last_name) SEPARATOR ', ') AS assigned_mentors
                FROM
                    projects p
                LEFT JOIN
                    mentorship_assignments ma ON p.project_id = ma.project_id
                LEFT JOIN
                    users um ON ma.mentor_user_id = um.user_id AND (SELECT r.role_name FROM roles r WHERE r.role_id = um.role_id) = 'Mentor'
                WHERE
                    p.project_owner_mentee_id = ?
                GROUP BY
                    p.project_id
                ORDER BY
                    p.created_at DESC
            ");
            $stmt_projects->execute([$mentee_id]);
            $projects = $stmt_projects->fetchAll(PDO::FETCH_ASSOC);
        }

    } elseif ($role_name === 'Mentor') {
        // --- Fetch Projects for a Mentor ---
        $stmt_projects = $pdo->prepare("
            SELECT
                p.project_id,
                p.project_name,
                p.description,
                p.status,
                p.start_date,
                p.end_date,
                p.created_at,
                CONCAT(u_mentee.first_name, ' ', u_mentee.last_name, ' (', u_mentee.username, ')') AS mentee_name
            FROM
                projects p
            JOIN
                mentorship_assignments ma ON p.project_id = ma.project_id
            JOIN
                mentees m ON p.project_owner_mentee_id = m.mentee_id
            JOIN
                users u_mentee ON m.user_id = u_mentee.user_id
            WHERE
                ma.mentor_user_id = ?
            GROUP BY
                p.project_id -- Group to prevent duplicate rows if multiple mentors are assigned, though for a mentor's view we'd usually show unique projects they are involved with.
            ORDER BY
                p.created_at DESC
        ");
        $stmt_projects->execute([$user_id]);
        $projects = $stmt_projects->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    error_log("PMMS My Projects Error: " . $e->getMessage());
    $message = '<p style="color: red;">Error fetching your projects from the database. ' . $e->getMessage() . '</p>';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PMMS - My Projects</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f0f2f5; margin: 0; padding: 20px; }
        .container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); max-width: 1000px; margin: 20px auto; }
        h1 { color: #333; margin-bottom: 20px; }
        .message { margin-bottom: 20px; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; vertical-align: top; }
        th { background-color: #e9ecef; }
        .back-link { margin-bottom: 20px; }
        .back-link a { color: #007bff; text-decoration: none; }
        .back-link a:hover { text-decoration: underline; }
        .project-description {
            max-height: 80px;
            overflow-y: auto;
            font-size: 0.9em;
            color: #666;
        }
        .status-proposed { background-color: #fff3cd; color: #856404; } /* yellow */
        .status-approved { background-color: #d4edda; color: #155724; } /* green */
        .status-in-progress { background-color: #cce5ff; color: #004085; } /* blue */
        .status-completed { background-color: #e2e3e5; color: #383d41; } /* grey */
        .status-rejected { background-color: #f8d7da; color: #721c24; } /* red */
    </style>
</head>
<body>
    <div class="container">
        <div class="back-link">
            <a href="dashboard.php">&larr; Back to Dashboard</a>
        </div>
        <h1><?php echo ($role_name === 'Mentee') ? 'My Submitted Projects' : 'My Mentored Projects'; ?></h1>
        <?php echo $message; ?>

        <?php if (empty($projects)): ?>
            <?php if ($role_name === 'Mentee'): ?>
                <p>You haven't submitted any projects yet. <a href="submit_proposal.php">Submit a new project proposal</a>!</p>
            <?php elseif ($role_name === 'Mentor'): ?>
                <p>You are not currently assigned to any projects. Projects need to be assigned by an Administrator or Coordinator from the <a href="projects_overview.php">Projects Overview</a> page.</p>
            <?php endif; ?>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Project Title</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Proposed Dates</th>
                        <?php if ($role_name === 'Mentee'): ?>
                            <th>Assigned Mentor(s)</th>
                            <th>Submitted On</th>
                        <?php elseif ($role_name === 'Mentor'): ?>
                            <th>Mentee</th>
                            <th>Assigned On</th>
                        <?php endif; ?>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projects as $project): ?>
                        <tr> 
                            <td><a href="project_details.php?id=<?php echo $project['project_id']; ?>"><?php echo htmlspecialchars($project['project_name']); ?></a></td>
                            <td><div class="project-description"><?php echo htmlspecialchars($project['description']); ?></div></td>
                            <td class="status-<?php echo strtolower(str_replace(' ', '-', $project['status'])); ?>">
                                <?php echo htmlspecialchars($project['status']); ?>
                            </td>
                            <td>
                                Start: <?php echo htmlspecialchars($project['start_date']); ?><br>
                                End: <?php echo htmlspecialchars($project['end_date'] ?? 'N/A'); ?>
                            </td>
                            <?php if ($role_name === 'Mentee'): ?>
                                <td><?php echo htmlspecialchars($project['assigned_mentors'] ?? 'None'); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($project['created_at'])); ?></td>
                            <?php elseif ($role_name === 'Mentor'): ?>
                                <td><?php echo htmlspecialchars($project['mentee_name']); ?></td>
                                <td>N/A </td>
                            <?php endif; ?>
                            <td>
                                </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>