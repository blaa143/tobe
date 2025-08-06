<?php
// process_mentor_request.php - Handles a mentor's decision on a request
require_once 'config.php';

// --- Access Control: Only Mentors can process requests ---
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] !== 'Mentor') {
    $_SESSION['message'] = '<p style="color: red;">You are not authorized to process mentorship requests.</p>';
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $mentor_user_id = $_SESSION['user_id'];

    if ($request_id <= 0 || ($action !== 'accept' && $action !== 'reject')) {
        $_SESSION['message'] = '<p style="color: red;">Invalid request or action.</p>';
        redirect('mentor_requests.php');
    }

    try {
        $pdo->beginTransaction();

        // Verify the request exists and belongs to the current mentor
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM mentorship_requests WHERE request_id = ? AND mentor_user_id = ? AND request_status = 'Pending'");
        $stmt_check->execute([$request_id, $mentor_user_id]);
        if ($stmt_check->fetchColumn() == 0) {
            $_SESSION['message'] = '<p style="color: red;">Invalid or already processed request.</p>';
            $pdo->rollBack();
            redirect('mentor_requests.php');
        }

        if ($action === 'accept') {
            // Get project_id from the request
            $stmt_get_project = $pdo->prepare("SELECT project_id FROM mentorship_requests WHERE request_id = ?");
            $stmt_get_project->execute([$request_id]);
            $project_id = $stmt_get_project->fetchColumn();

            // Insert a new record into mentorship_assignments
            $stmt_assign = $pdo->prepare("INSERT INTO mentorship_assignments (project_id, mentor_user_id) VALUES (?, ?)");
            $stmt_assign->execute([$project_id, $mentor_user_id]);

            // Update the request status
            $stmt_update_request = $pdo->prepare("UPDATE mentorship_requests SET request_status = 'Accepted' WHERE request_id = ?");
            $stmt_update_request->execute([$request_id]);
            
            $_SESSION['message'] = '<p style="color: green;">Request accepted successfully! Mentorship assignment created.</p>';
        } else { // action is 'reject'
            // Simply update the request status
            $stmt_update_request = $pdo->prepare("UPDATE mentorship_requests SET request_status = 'Rejected' WHERE request_id = ?");
            $stmt_update_request->execute([$request_id]);
            
            $_SESSION['message'] = '<p style="color: orange;">Request rejected successfully.</p>';
        }

        $pdo->commit();
        redirect('mentor_requests.php');

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("PMMS Process Mentor Request Error: " . $e->getMessage());
        $_SESSION['message'] = '<p style="color: red;">An error occurred while processing the request: ' . $e->getMessage() . '</p>';
        redirect('mentor_requests.php');
    }
} else {
    $_SESSION['message'] = '<p style="color: red;">Invalid request method.</p>';
    redirect('mentor_requests.php');
}
?>