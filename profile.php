 <?php
// profile.php - User profile page to view and edit personal information
require_once 'config.php';

// --- Access Control: Only logged-in users can access this page ---
if (!isset($_SESSION['user_id'])) {
    redirect('auth_portal.php');
}

$message = '';
$user_id = $_SESSION['user_id'];
$role_name = $_SESSION['role_name'];

// --- Handle form submission for profile updates ---
 // --- Handle form submission for profile updates ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);

    if (empty($first_name) || empty($last_name)) {
        $_SESSION['message'] = '<p style="color: red;">First name and last name cannot be empty.</p>';
        redirect('profile.php');
    } else {
        try {
            // Update users table (This part was already working)
            $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ? WHERE user_id = ?");
            $stmt->execute([$first_name, $last_name, $user_id]);

            // Handle Mentee and Mentor data
            if ($role_name === 'Mentee') {
                $learning_goals = !empty(trim($_POST['learning_goals'])) ? trim($_POST['learning_goals']) : null;
                
                // Check if a mentee record already exists
                $stmt_check = $pdo->prepare("SELECT mentee_id FROM mentees WHERE user_id = ?");
                $stmt_check->execute([$user_id]);
                $mentee_id = $stmt_check->fetchColumn();

                if ($mentee_id) {
                    // Record exists, so UPDATE
                    $stmt_mentee_update = $pdo->prepare("UPDATE mentees SET learning_goals = ? WHERE mentee_id = ?");
                    $stmt_mentee_update->execute([$learning_goals, $mentee_id]);
                } else {
                    // Record does not exist, so INSERT
                    $stmt_mentee_insert = $pdo->prepare("INSERT INTO mentees (user_id, learning_goals) VALUES (?, ?)");
                    $stmt_mentee_insert->execute([$user_id, $learning_goals]);
                }
            }
            if ($role_name === 'Mentor') {
                $expertise = !empty(trim($_POST['expertise'])) ? trim($_POST['expertise']) : null;
                
                // Check if a mentor record already exists
                $stmt_check = $pdo->prepare("SELECT mentor_id FROM mentors WHERE user_id = ?");
                $stmt_check->execute([$user_id]);
                $mentor_id = $stmt_check->fetchColumn();

                if ($mentor_id) {
                    // Record exists, so UPDATE
                    $stmt_mentor_update = $pdo->prepare("UPDATE mentors SET expertise = ? WHERE mentor_id = ?");
                    $stmt_mentor_update->execute([$expertise, $mentor_id]);
                } else {
                    // Record does not exist, so INSERT
                    $stmt_mentor_insert = $pdo->prepare("INSERT INTO mentors (user_id, expertise) VALUES (?, ?)");
                    $stmt_mentor_insert->execute([$user_id, $expertise]);
                }
            }

            // Update session variables
            $_SESSION['first_name'] = $first_name;
            $_SESSION['last_name'] = $last_name;

            $_SESSION['message'] = '<p style="color: green;">Profile updated successfully!</p>';
        } catch (PDOException $e) {
            error_log("PMMS Profile Update Error: " . $e->getMessage());
            $_SESSION['message'] = '<p style="color: red;">Error updating profile: ' . $e->getMessage() . '</p>';
        }
        redirect('profile.php');
    }
}

// --- Display messages from session (if they exist from a redirect) ---
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// --- Fetch user data for display (this will always run on page load) ---
$user_data = [];
$mentee_data = [];
$mentor_data = [];
try {
    $stmt_user = $pdo->prepare("SELECT user_id, username, email, first_name, last_name, created_at FROM users WHERE user_id = ?");
    $stmt_user->execute([$user_id]);
    $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

    // Fetch role-specific data
    if ($role_name === 'Mentee') {
        $stmt_mentee = $pdo->prepare("SELECT learning_goals FROM mentees WHERE user_id = ?");
        $stmt_mentee->execute([$user_id]);
        $mentee_data = $stmt_mentee->fetch(PDO::FETCH_ASSOC);
    }
    if ($role_name === 'Mentor') {
        $stmt_mentor = $pdo->prepare("SELECT expertise FROM mentors WHERE user_id = ?");
        $stmt_mentor->execute([$user_id]);
        $mentor_data = $stmt_mentor->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("PMMS Profile Fetch Error: " . $e->getMessage());
    $message = '<p style="color: red;">Error fetching profile data.</p>';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PMMS - My Profile</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f0f2f5; margin: 0; padding: 20px; }
        .container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); max-width: 800px; margin: 20px auto; }
        h1 { color: #333; margin-bottom: 20px; text-align: center; }
        .message { text-align: center; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input[type="text"], .form-group input[type="email"], .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .form-group textarea { min-height: 100px; }
        .btn-submit {
            padding: 10px 15px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .btn-submit:hover { background-color: #0056b3; }
        .profile-details p { margin: 5px 0; }
        .profile-details p strong { margin-right: 10px; }
        .back-link { margin-bottom: 20px; }
        .back-link a { color: #007bff; text-decoration: none; }
        .back-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <div class="back-link">
            <a href="dashboard.php">&larr; Back to Dashboard</a>
        </div>
        <h1>My Profile</h1>
        <?php echo $message; ?>

        <?php if ($user_data): ?>
        <form action="profile.php" method="POST">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user_data['username']); ?>" disabled>
            </div>
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_data['email']); ?>" disabled>
            </div>
            <div class="form-group">
                <label for="first_name">First Name:</label>
                <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user_data['first_name']); ?>" required>
            </div>
            <div class="form-group">
                <label for="last_name">Last Name:</label>
                <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user_data['last_name']); ?>" required>
            </div>

            <?php if ($role_name === 'Mentee'): ?>
                <div class="form-group">
                    <label for="learning_goals">My Learning Goals:</label>
                    <textarea id="learning_goals" name="learning_goals" placeholder="e.g., Learn Python, improve public speaking, get career advice"><?php echo htmlspecialchars($mentee_data['learning_goals'] ?? ''); ?></textarea>
                </div>
            <?php endif; ?>

            <?php if ($role_name === 'Mentor'): ?>
                <div class="form-group">
                    <label for="expertise">My Expertise:</label>
                    <textarea id="expertise" name="expertise" placeholder="e.g., Software Development, UI/UX Design, Project Management"><?php echo htmlspecialchars($mentor_data['expertise'] ?? ''); ?></textarea>
                </div>
            <?php endif; ?>

            <button type="submit" name="update_profile" class="btn-submit">Update Profile</button>
        </form>
        <?php else: ?>
            <p>Could not load user profile. Please try again.</p>
        <?php endif; ?>
    </div>
</body>
</html>