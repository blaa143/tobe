<?php
// send_mentor_request.php - Processes a mentee's request to a mentor
require_once 'config.php';

// --- Access Control: Only mentees can send requests ---
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] !== 'Mentee') {
    $_SESSION['message'] = '<p style="color: red;">You are not authorized to send mentorship requests.</p>';
    redirect('find_mentor.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
    $mentor_user_id = isset($_POST['mentor_user_id']) ? (int)$_POST['mentor_user_id'] : 0;
    $mentee_user_id = $_SESSION['user_id'];

    if ($project_id <= 0 || $mentor_user_id <= 0) {
        $_SESSION['message'] = '<p style="color: red;">Invalid project or mentor specified.</p>';
        redirect('find_mentor.php');
    }

    try {
        // Find the mentee_id for the current user
        $stmt_get_mentee_id = $pdo->prepare("SELECT mentee_id FROM mentees WHERE user_id = ?");
        $stmt_get_mentee_id->execute([$mentee_user_id]);
        $mentee_id = $stmt_get_mentee_id->fetchColumn();

        // Check if the current user is the owner of the selected project
        $stmt_check_project_owner = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE project_id = ? AND project_owner_mentee_id = ?");
        $stmt_check_project_owner->execute([$project_id, $mentee_id]);
        if ($stmt_check_project_owner->fetchColumn() == 0) {
            $_SESSION['message'] = '<p style="color: red;">You are not the owner of this project.</p>';
            redirect('find_mentor.php');
        }

        // Check if a request already exists to prevent duplicates (handled by UNIQUE KEY, but good practice)
        $stmt_check_request = $pdo->prepare("SELECT COUNT(*) FROM mentorship_requests WHERE project_id = ? AND mentor_user_id = ?");
        $stmt_check_request->execute([$project_id, $mentor_user_id]);
        if ($stmt_check_request->fetchColumn() > 0) {
            $_SESSION['message'] = '<p style="color: yellow;">A request for this mentor on this project has already been sent.</p>';
            redirect('find_mentor.php');
        }

        // Insert the new request into the database
        $stmt_insert_request = $pdo->prepare("INSERT INTO mentorship_requests (project_id, mentor_user_id) VALUES (?, ?)");
        $stmt_insert_request->execute([$project_id, $mentor_user_id]);

        $_SESSION['message'] = '<p style="color: green;">Mentorship request sent successfully!</p>';
        redirect('find_mentor.php');

    } catch (PDOException $e) {
        error_log("PMMS Mentorship Request Error: " . $e->getMessage());
        $_SESSION['message'] = '<p style="color: red;">An error occurred while sending the request: ' . $e->getMessage() . '</p>';
        redirect('find_mentor.php');
    }
} else {
    $_SESSION['message'] = '<p style="color: red;">Invalid request method.</p>';
    redirect('find_mentor.php');
}
?>