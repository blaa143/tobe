 <?php
// process_proposal.php - Handles submission of new project proposals.
require_once 'config.php'; // Provides $pdo, redirect(), session_start()

// Ensure this script is only accessed via POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('submit_proposal.php'); // Redirect if accessed directly
    exit();
}

// --- Access Control: Only Mentees can submit proposals ---
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] !== 'Mentee') {
    $_SESSION['proposal_message'] = '<p style="color: red;">Access Denied: Only mentees can submit project proposals.</p>';
    redirect('dashboard.php'); // Redirect to dashboard if not authorized
    exit();
}

// Store old data in session in case of failure to pre-fill form
$_SESSION['old_proposal_data'] = $_POST;

$message = ''; // To store messages before redirecting back

$user_id = $_SESSION['user_id']; // The ID of the logged-in mentee

// Sanitize and trim inputs
$project_name = trim($_POST['project_name'] ?? '');
$description = trim($_POST['description'] ?? '');
$start_date = trim($_POST['start_date'] ?? '');
$end_date = trim($_POST['end_date'] ?? ''); // Optional, can be empty

// --- Basic Validation ---
if (empty($project_name) || empty($description) || empty($start_date)) {
    $message = '<p style="color: red;">Error: Project Title, Description, and Proposed Start Date are required.</p>';
} elseif (!strtotime($start_date)) {
    $message = '<p style="color: red;">Error: Invalid Proposed Start Date format.</p>';
} elseif (!empty($end_date) && !strtotime($end_date)) {
    $message = '<p style="color: red;">Error: Invalid Proposed End Date format.</p>';
} elseif (!empty($end_date) && ($start_date > $end_date)) {
    $message = '<p style="color: red;">Error: Proposed End Date cannot be before Proposed Start Date.</p>';
} else {
    // --- Database Operations ---
    try {
        // First, get the mentee_id from the mentees table using the user_id
        // This is crucial because projects table links to mentee_id, not user_id directly
        $stmt_mentee_id = $pdo->prepare("SELECT mentee_id FROM mentees WHERE user_id = ?");
        $stmt_mentee_id->execute([$user_id]);
        $mentee_data = $stmt_mentee_id->fetch();

        if (!$mentee_data) {
            // This means the logged-in user is a Mentee role but doesn't have an entry in the 'mentees' table.
            // This can happen if they registered as a Mentee but never visited/saved their profile page.
            $message = '<p style="color: red;">Error: Your mentee profile is incomplete. Please visit your <a href="profile.php">profile page</a> and save your details first.</p>';
            error_log("PMMS Process Proposal Error: Mentee profile missing for user_id: " . $user_id);
        } else {
            $mentee_id = $mentee_data['mentee_id'];

            // Insert new project proposal into the 'projects' table
            // Status defaults to 'Proposed' as per table definition
            $stmt_insert_project = $pdo->prepare("INSERT INTO projects (project_owner_mentee_id, project_name, description, start_date, end_date) VALUES (?, ?, ?, ?, ?)");
            $stmt_insert_project->execute([$mentee_id, $project_name, $description, $start_date, empty($end_date) ? null : $end_date]);

            // Set success message and clear old form data
            $_SESSION['proposal_message'] = '<p style="color: green;">Project proposal "' . htmlspecialchars($project_name) . '" submitted successfully! It is now awaiting review.</p>';
            unset($_SESSION['old_proposal_data']); // Clear old data on success
            redirect('submit_proposal.php'); // Redirect back to the form page to show success
            exit(); // Stop script execution
        }

    } catch (PDOException $e) {
        $message = '<p style="color: red;">Database error submitting proposal: ' . $e->getMessage() . '</p>';
        error_log("PMMS Process Proposal Error: " . $e->getMessage());
    }
}

// If there was an error, store the message and redirect back to submit_proposal.php
$_SESSION['proposal_message'] = $message;
redirect('submit_proposal.php');
exit(); // Ensure script terminates
?>