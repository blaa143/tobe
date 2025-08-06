 <?php
// assign_mentor.php - Allows admins/coordinators to assign mentors to a project
require_once 'config.php';

// --- Access Control: Only Admins and Coordinators can see this page ---
if (!isset($_SESSION['user_id']) || ($_SESSION['role_name'] !== 'Administrator' && $_SESSION['role_name'] !== 'Coordinator')) {
    redirect('dashboard.php');
}

$message = '';
$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
$project_details = null;
$mentors = [];
$assigned_mentors = [];

if ($project_id <= 0) {
    $_SESSION['message'] = '<p style="color: red;">Invalid project ID.</p>';
    redirect('projects_overview.php'); // Corrected redirect
}

// Handle form submission for mentor assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_mentors'])) {
    $mentor_ids = $_POST['mentor_ids'] ?? [];

    try {
        $pdo->beginTransaction();

        // 1. Delete all current assignments for this project to start fresh
        $stmt_delete = $pdo->prepare("DELETE FROM mentorship_assignments WHERE project_id = ?");
        $stmt_delete->execute([$project_id]);

        // 2. Insert new assignments
        if (!empty($mentor_ids)) {
            $sql = "INSERT INTO mentorship_assignments (project_id, mentor_user_id) VALUES (?, ?)";
            $stmt_insert = $pdo->prepare($sql);
            foreach ($mentor_ids as $mentor_id) {
                $stmt_insert->execute([$project_id, $mentor_id]);
            }
        }
        
        $pdo->commit();
        $_SESSION['message'] = '<p style="color: green;">Mentors assigned to project successfully!</p>';
        redirect('projects_overview.php'); // Corrected redirect

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("PMMS Mentor Assignment Error: " . $e->getMessage());
        $message = '<p style="color: red;">Error assigning mentors: ' . $e->getMessage() . '</p>';
    }
}

// Fetch project details for display
try {
    $stmt_project = $pdo->prepare("
        SELECT p.project_name, p.description, CONCAT(u.first_name, ' ', u.last_name) as mentee_name
        FROM projects p
        JOIN mentees m ON p.project_owner_mentee_id = m.mentee_id
        JOIN users u ON m.user_id = u.user_id
        WHERE p.project_id = ?
    ");
    $stmt_project->execute([$project_id]);
    $project_details = $stmt_project->fetch(PDO::FETCH_ASSOC);

    if (!$project_details) {
        $_SESSION['message'] = '<p style="color: red;">Project not found.</p>';
        redirect('projects_overview.php'); // Corrected redirect
    }

    // Fetch all available mentors
    $stmt_mentors = $pdo->prepare("
        SELECT u.user_id, CONCAT(u.first_name, ' ', u.last_name) AS full_name
        FROM users u
        JOIN roles r ON u.role_id = r.role_id
        WHERE r.role_name = 'Mentor'
        ORDER BY full_name
    ");
    $stmt_mentors->execute();
    $mentors = $stmt_mentors->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch currently assigned mentors for this project
    $stmt_assigned = $pdo->prepare("SELECT mentor_user_id FROM mentorship_assignments WHERE project_id = ?");
    $stmt_assigned->execute([$project_id]);
    $assigned_mentors = $stmt_assigned->fetchAll(PDO::FETCH_COLUMN, 0);

} catch (PDOException $e) {
    error_log("PMMS Mentor Assignment Fetch Error: " . $e->getMessage());
    $message = '<p style="color: red;">Error fetching data: ' . $e->getMessage() . '</p>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PMMS - Assign Mentors</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f0f2f5; margin: 0; padding: 20px; }
        .container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); max-width: 800px; margin: 20px auto; }
        h1 { color: #333; margin-bottom: 20px; text-align: center; }
        .project-info { background-color: #e9ecef; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .project-info p { margin: 5px 0; }
        .project-info strong { color: #555; }
        .message { text-align: center; margin-bottom: 20px; }
        .back-link { margin-bottom: 20px; }
        .back-link a { color: #007bff; text-decoration: none; }
        .back-link a:hover { text-decoration: underline; }
        .mentor-list { margin-bottom: 20px; }
        .mentor-list label { display: block; margin-bottom: 8px; cursor: pointer; }
        .mentor-list input[type="checkbox"] { margin-right: 10px; }
        .submit-btn {
            display: block;
            width: 100%;
            padding: 10px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .submit-btn:hover { background-color: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <div class="back-link">
            <a href="projects_overview.php">&larr; Back to Project Overview</a> </div>
        <h1>Assign Mentors to Project</h1>
        <?php echo $message; ?>

        <?php if ($project_details): ?>
            <div class="project-info">
                <h3>Project: <?php echo htmlspecialchars($project_details['project_name']); ?></h3>
                <p><strong>Mentee:</strong> <?php echo htmlspecialchars($project_details['mentee_name']); ?></p>
                <p><strong>Description:</strong> <?php echo nl2br(htmlspecialchars($project_details['description'])); ?></p>
            </div>
            
            <form action="assign_mentor.php?project_id=<?php echo $project_id; ?>" method="POST">
                <div class="mentor-list">
                    <h3>Available Mentors</h3>
                    <?php if (empty($mentors)): ?>
                        <p>No mentors found in the system.</p>
                    <?php else: ?>
                        <?php foreach ($mentors as $mentor): ?>
                            <label>
                                <input type="checkbox" name="mentor_ids[]" value="<?php echo htmlspecialchars($mentor['user_id']); ?>"
                                    <?php echo in_array($mentor['user_id'], $assigned_mentors) ? 'checked' : ''; ?>>
                                <?php echo htmlspecialchars($mentor['full_name']); ?>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <button type="submit" name="assign_mentors" class="submit-btn">Update Mentor Assignments</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>