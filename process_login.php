<?php
// process_login.php - Handles login form submission
require_once 'config.php'; // Provides $pdo, redirect(), session_start()

// Ensure this script is only accessed via POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('auth_portal.php'); // Redirect if accessed directly
    exit();
}

// If user is already logged in, redirect them to dashboard (shouldn't happen if auth_portal.php is doing its job)
if (isset($_SESSION['user_id'])) {
    redirect('dashboard.php');
    exit();
}

// Store old data in session in case of failure to pre-fill form
$_SESSION['old_login_data']['username_or_email'] = $_POST['username_or_email'] ?? '';

$message = ''; // To store messages before redirecting back

$username_or_email = trim($_POST['username_or_email'] ?? '');
$password = $_POST['password'] ?? '';

// Basic validation
if (empty($username_or_email) || empty($password)) {
    $message = '<p style="color: red;">Please enter both username/email and password.</p>';
} else {
    // Database Operations
    try {
        $stmt = $pdo->prepare("SELECT u.*, r.role_name
                              FROM users u
                              JOIN roles r ON u.role_id = r.role_id
                              WHERE u.username = ? OR u.email = ?");
        $stmt->execute([$username_or_email, $username_or_email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Successful login! Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['role_name'] = $user['role_name'];

            // Update last login timestamp
            $stmt_update = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
            $stmt_update->execute([$user['user_id']]);

            // Clear old login data on success
            unset($_SESSION['old_login_data']);

            redirect('dashboard.php'); // Redirect to dashboard
            exit(); // Important to stop script execution
        } else {
            $message = '<p style="color: red;">Invalid username/email or password.</p>';
        }
    } catch (PDOException $e) {
        $message = '<p style="color: red;">A database error occurred during login. Please try again later.</p>';
        error_log("PMMS Process Login Error: " . $e->getMessage());
    }
}

// If login failed, store the message and redirect back to auth_portal.php
$_SESSION['login_message'] = $message;
redirect('auth_portal.php');
exit(); // Ensure script terminates
?>