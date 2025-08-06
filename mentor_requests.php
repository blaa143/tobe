<?php
// mentor_requests.php - Allows a mentor to view and manage incoming requests
require_once 'config.php';

// --- Access Control: Only Mentors can view this page ---
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] !== 'Mentor') {
    redirect('dashboard.php');
}

$message = '';
$requests = [];

try {
    // Fetch all pending requests for the current mentor
    $stmt = $pdo->prepare("
        SELECT 
            mr.request_id,
            p.project_name,
            p.description,
            mr.requested_at,
            CONCAT(u.first_name, ' ', u.last_name) AS mentee_name
        FROM mentorship_requests mr
        JOIN projects p ON mr.project_id = p.project_id
        JOIN mentees m ON p.project_owner_mentee_id = m.mentee_id
        JOIN users u ON m.user_id = u.user_id
        WHERE mr.mentor_user_id = ? AND mr.request_status = 'Pending'
        ORDER BY mr.requested_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("PMMS Mentor Requests Error: " . $e->getMessage());
    $message = '<p style="color: red;">Error fetching requests: ' . $e->getMessage() . '</p>';
}

// Display messages from the session
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
    <title>PMMS - Mentorship Requests</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f0f2f5; margin: 0; padding: 20px; }
        .container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); max-width: 900px; margin: 20px auto; }
        h1 { color: #333; margin-bottom: 20px; }
        .message { text-align: center; margin-bottom: 20px; }
        .back-link { margin-bottom: 20px; }
        .back-link a { color: #007bff; text-decoration: none; }
        .back-link a:hover { text-decoration: underline; }
        .request-list { display: flex; flex-direction: column; gap: 20px; }
        .request-card {
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .request-card h3 { margin-top: 0; color: #007bff; }
        .request-card p { margin: 5px 0; font-size: 0.9em; }
        .request-card p strong { color: #333; }
        .action-buttons {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }
        .action-buttons button {
            padding: 10px 15px;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        .action-buttons button.accept { background-color: #28a745; }
        .action-buttons button.reject { background-color: #dc3545; }
        .action-buttons button.accept:hover { background-color: #218838; }
        .action-buttons button.reject:hover { background-color: #c82333; }
        .no-requests { text-align: center; color: #777; }
    </style>
</head>
<body>
    <div class="container">
        <div class="back-link">
            <a href="dashboard.php">&larr; Back to Dashboard</a>
        </div>
        <h1>Pending Mentorship Requests</h1>
        <?php echo $message; ?>

        <div class="request-list">
            <?php if (empty($requests)): ?>
                <p class="no-requests">You have no new mentorship requests at this time.</p>
            <?php else: ?>
                <?php foreach ($requests as $request): ?>
                    <div class="request-card">
                        <h3>Project: <?php echo htmlspecialchars($request['project_name']); ?></h3>
                        <p><strong>Mentee:</strong> <?php echo htmlspecialchars($request['mentee_name']); ?></p>
                        <p><strong>Requested On:</strong> <?php echo date('F j, Y', strtotime($request['requested_at'])); ?></p>
                        <p><strong>Project Description:</strong> <?php echo nl2br(htmlspecialchars($request['description'])); ?></p>

                        <div class="action-buttons">
                            <form action="process_mentor_request.php" method="POST">
                                <input type="hidden" name="request_id" value="<?php echo htmlspecialchars($request['request_id']); ?>">
                                <button type="submit" name="action" value="accept" class="accept">Accept</button>
                            </form>
                            <form action="process_mentor_request.php" method="POST">
                                <input type="hidden" name="request_id" value="<?php echo htmlspecialchars($request['request_id']); ?>">
                                <button type="submit" name="action" value="reject" class="reject">Reject</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>