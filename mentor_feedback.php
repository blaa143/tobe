<?php
// mentor_feedback.php - Placeholder page for Mentors to provide feedback
require_once 'config.php';

// --- Access Control: Only Mentors can access this page ---
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] !== 'Mentor') {
    redirect('dashboard.php'); // Redirect if not authorized
}

$username = htmlspecialchars($_SESSION['username'] ?? 'User');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PMMS - Provide Feedback</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f0f2f5; margin: 0; padding: 20px; }
        .container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); max-width: 900px; margin: 20px auto; text-align: center; }
        h1 { color: #333; margin-bottom: 20px; }
        p { color: #666; margin-bottom: 15px; }
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
        <h1>Provide Feedback</h1>
        <p>This page will allow mentors to provide structured feedback and ratings for their mentees and projects.</p>
        <p>Check back soon for updates!</p>
    </div>
</body>
</html>