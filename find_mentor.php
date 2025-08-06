 <?php
// find_mentor.php - Allows mentees to search and browse for available mentors
require_once 'config.php';

// --- Access Control: Only logged-in users can view this page ---
if (!isset($_SESSION['user_id'])) {
    redirect('auth_portal.php');
}

$message = '';
$mentors = [];
$search_term = '';
$mentee_projects = []; // Added to store the mentee's projects

// --- Fetch projects for the current mentee, if they are a mentee ---
if ($_SESSION['role_name'] === 'Mentee') {
    try {
        $stmt_projects = $pdo->prepare("
            SELECT p.project_id, p.project_name
            FROM projects p
            JOIN mentees m ON p.project_owner_mentee_id = m.mentee_id
            WHERE m.user_id = ?
        ");
        $stmt_projects->execute([$_SESSION['user_id']]);
        $mentee_projects = $stmt_projects->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("PMMS Find Mentor Projects Error: " . $e->getMessage());
        // Do not fail the page, but log the error
    }
}

try {
    // Check for a search term in the URL
    if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
        $search_term = trim($_GET['search']);
        $search_param = '%' . $search_term . '%';
        
        $stmt = $pdo->prepare("
            SELECT u.user_id, u.first_name, u.last_name, u.email, u.created_at, m.expertise
            FROM users u
            JOIN roles r ON u.role_id = r.role_id
            LEFT JOIN mentors m ON u.user_id = m.user_id
            WHERE r.role_name = 'Mentor' AND m.expertise LIKE ?
            ORDER BY u.first_name, u.last_name
        ");
        $stmt->execute([$search_param]);
    } else {
        // Fetch all mentors if no search term is provided
        $stmt = $pdo->prepare("
            SELECT u.user_id, u.first_name, u.last_name, u.email, u.created_at, m.expertise
            FROM users u
            JOIN roles r ON u.role_id = r.role_id
            LEFT JOIN mentors m ON u.user_id = m.user_id
            WHERE r.role_name = 'Mentor'
            ORDER BY u.first_name, u.last_name
        ");
        $stmt->execute();
    }

    $mentors = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("PMMS Find Mentor Error: " . $e->getMessage());
    $message = '<p style="color: red;">Error fetching mentor list.</p>';
}

// Check for and display messages from the session
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
    <title>PMMS - Find a Mentor</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f0f2f5; margin: 0; padding: 20px; }
        .container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); max-width: 900px; margin: 20px auto; }
        h1 { color: #333; margin-bottom: 20px; text-align: center; }
        .message { text-align: center; margin-bottom: 20px; }
        .back-link { margin-bottom: 20px; }
        .back-link a { color: #007bff; text-decoration: none; }
        .back-link a:hover { text-decoration: underline; }
        .search-bar { margin-bottom: 20px; text-align: center; }
        .search-bar input[type="text"] {
            width: 70%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 16px;
        }
        .search-bar button {
            padding: 10px 15px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-left: 5px;
        }
        .search-bar button:hover { background-color: #0056b3; }
        .mentor-list { display: flex; flex-wrap: wrap; gap: 20px; justify-content: center; }
        .mentor-card {
            background-color: #f9f9f9;
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 20px;
            width: 350px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            transition: transform 0.2s;
        }
        .mentor-card:hover { transform: translateY(-5px); }
        .mentor-card h3 { margin-top: 0; color: #007bff; }
        .mentor-card p { margin: 5px 0; font-size: 0.9em; color: #555; }
        .mentor-card strong { color: #333; }
        .expertise-text {
            white-space: pre-wrap;
            word-wrap: break-word;
            margin-top: 10px;
            font-size: 0.95em;
            color: #666;
        }
        .request-form {
            margin-top: 15px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .request-form select, .request-form button {
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .request-form button {
            background-color: #28a745;
            color: white;
            font-weight: bold;
            cursor: pointer;
            border: none;
        }
        .request-form button:hover {
            background-color: #218838;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="back-link">
            <a href="dashboard.php">&larr; Back to Dashboard</a>
        </div>
        <h1>Find a Mentor</h1>
        <?php echo $message; ?>

        <div class="search-bar">
            <form action="find_mentor.php" method="GET">
                <input type="text" name="search" placeholder="Search by expertise (e.g., Python, UI/UX)" value="<?php echo htmlspecialchars($search_term); ?>">
                <button type="submit">Search</button>
            </form>
        </div>

        <div class="mentor-list">
            <?php if (empty($mentors)): ?>
                <p>No mentors found matching your criteria.</p>
            <?php else: ?>
                <?php foreach ($mentors as $mentor): ?>
                    <div class="mentor-card">
                        <h3><?php echo htmlspecialchars($mentor['first_name'] . ' ' . $mentor['last_name']); ?></h3>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($mentor['email']); ?></p>
                        <p><strong>Expertise:</strong></p>
                        <p class="expertise-text"><?php echo nl2br(htmlspecialchars($mentor['expertise'] ?? 'Not specified')); ?></p>
                        <?php if ($_SESSION['role_name'] === 'Mentee'): ?>
                            <form action="send_mentor_request.php" method="POST" class="request-form">
                                <input type="hidden" name="mentor_user_id" value="<?php echo htmlspecialchars($mentor['user_id']); ?>">
                                <select name="project_id" required>
                                    <option value="">Select your project</option>
                                    <?php foreach ($mentee_projects as $project): ?>
                                        <option value="<?php echo htmlspecialchars($project['project_id']); ?>">
                                            <?php echo htmlspecialchars($project['project_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit">Send Request</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>