 <?php
// submit_proposal.php - Form for mentees to submit new project proposals.
require_once 'config.php'; // Includes database connection ($pdo) and session_start()

// --- Access Control: Only Mentees can access this page ---
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] !== 'Mentee') {
    // Redirect to dashboard or an access denied page if not a mentee or not logged in
    redirect('dashboard.php');
}

// Check for messages from process_proposal.php (after form submission)
$message = $_SESSION['proposal_message'] ?? '';
unset($_SESSION['proposal_message']); // Clear message after displaying

// Retain form data if validation failed in process_proposal.php
$old_project_name = $_SESSION['old_proposal_data']['project_name'] ?? '';
$old_description = $_SESSION['old_proposal_data']['description'] ?? '';
$old_start_date = $_SESSION['old_proposal_data']['start_date'] ?? '';
$old_end_date = $_SESSION['old_proposal_data']['end_date'] ?? '';
unset($_SESSION['old_proposal_data']); // Clear old data

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PMMS - Submit Project Proposal</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f0f2f5; margin: 0; padding: 20px; }
        .container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); max-width: 700px; margin: 20px auto; }
        h1 { color: #333; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; color: #555; font-weight: bold; }
        input[type="text"],
        input[type="date"],
        textarea {
            width: calc(100% - 20px);
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            padding: 10px 20px;
            background-color: #28a745; /* Green for submit */
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            margin-top: 10px;
        }
        button:hover {
            background-color: #218838;
        }
        .message { margin-top: 15px; text-align: center; }
        .back-link { margin-bottom: 20px; text-align: left; }
        .back-link a { color: #007bff; text-decoration: none; }
        .back-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <div class="back-link">
            <a href="dashboard.php">&larr; Back to Dashboard</a>
        </div>
        <h1>Submit New Project Proposal</h1>
        <?php echo $message; // Display messages here ?>

        <form action="process_proposal.php" method="POST"> <!-- Form submits to new processor -->
            <div class="form-group">
                <label for="project_name">Project Title:</label>
                <input type="text" id="project_name" name="project_name" required value="<?php echo htmlspecialchars($old_project_name); ?>">
            </div>
            <div class="form-group">
                <label for="description">Project Description:</label>
                <textarea id="description" name="description" rows="8" required><?php echo htmlspecialchars($old_description); ?></textarea>
            </div>
            <div class="form-group">
                <label for="start_date">Proposed Start Date:</label>
                <input type="date" id="start_date" name="start_date" required value="<?php echo htmlspecialchars($old_start_date); ?>">
            </div>
            <div class="form-group">
                <label for="end_date">Proposed End Date:</label>
                <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($old_end_date); ?>">
                <small>Optional: Provide an estimated end date.</small>
            </div>
            <button type="submit">Submit Proposal</button>
        </form>
    </div>
</body>
</html>