<?php
// delete_project.php - Safely deletes a project and related data
require_once 'config.php';

// --- Access Control: Only Admins and Coordinators can delete projects ---
if (!isset($_SESSION['user_id']) || ($_SESSION['role_name'] !== 'Administrator' && $_SESSION['role_name'] !== 'Coordinator')) {
    $_SESSION['message'] = '<p style="color: red;">You are not authorized to delete projects.</p>';
    redirect('dashboard.php');
}

$project_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($project_id <= 0) {
    $_SESSION['message'] = '<p style="color: red;">Invalid project ID.</p>';
    redirect('projects_overview.php');
}

try {
    $pdo->beginTransaction();

    // 1. Delete all associated mentorship assignments for this project
    $stmt_delete_assignments = $pdo->prepare("DELETE FROM mentorship_assignments WHERE project_id = ?");
    $stmt_delete_assignments->execute([$project_id]);
    
    // 2. Delete all associated tasks for this project
    $stmt_delete_tasks = $pdo->prepare("DELETE FROM tasks WHERE project_id = ?");
    $stmt_delete_tasks->execute([$project_id]);

    // 3. Finally, delete the project itself
    $stmt_delete_project = $pdo->prepare("DELETE FROM projects WHERE project_id = ?");
    $stmt_delete_project->execute([$project_id]);

    $pdo->commit();

    $_SESSION['message'] = '<p style="color: green;">Project deleted successfully!</p>';
    redirect('projects_overview.php');

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("PMMS Project Deletion Error: " . $e->getMessage());
    $_SESSION['message'] = '<p style="color: red;">Error deleting project: ' . $e->getMessage() . '</p>';
    redirect('projects_overview.php');
}
?>