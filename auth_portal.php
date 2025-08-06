 <?php
// auth_portal.php - Handles both login and logout actions, with security logging and IP blocking.
require_once 'config.php';
require_once 'ip_blocker.php'; // New utility for IP blocking

// Get the user's IP address
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

// Check if the current IP is blocked before any login logic
if (is_ip_blocked($ip_address)) {
    // If the IP is blocked, log it and display a message to the user.
    log_event("Attempted access from a blocked IP address: {$ip_address}.", 'blocked');
    $message = '<p style="color: red;">Your IP address has been temporarily blocked due to too many failed login attempts.</p>';
    $_SESSION['auth_message'] = $message;
    redirect('auth_portal.php');
}

// Check for logout request first
if (isset($_GET['logout'])) {
    if (isset($_SESSION['username'])) {
        log_event("User '{$_SESSION['username']}' logged out.", 'info');
    }
    session_destroy();
    redirect('auth_portal.php');
}

// Check for a login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_input = trim($_POST['login_input'] ?? '');
    $password = $_POST['password'] ?? '';
    $message = '';

    if (empty($login_input) || empty($password)) {
        $message = '<p style="color: red;">Please enter both username/email and password.</p>';
        // --- This is where the code logs failed attempts due to empty fields ---
        log_event("Failed login attempt due to empty credentials from IP: " . $ip_address, 'failure');
        // Register the failed attempt for IP blocking
        register_failed_attempt($ip_address);
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT u.user_id, u.first_name, u.last_name, u.password_hash, r.role_name, u.username
                FROM users u
                JOIN roles r ON u.role_id = r.role_id
                WHERE u.email = ? OR u.username = ?
            ");
            $stmt->execute([$login_input, $login_input]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Login successful, set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['role_name'] = $user['role_name'];
                $_SESSION['username'] = $user['username'];

                // Reset failed attempts for this IP on success
                reset_failed_attempts($ip_address);

                // Update last login timestamp in the database
                $stmt_update = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                $stmt_update->execute([$user['user_id']]);

                log_event("User '{$user['username']}' logged in successfully.", 'success');
                if ($user['role_name'] === 'Admin') {
                    log_event("ADMIN LOGIN: User '{$user['username']}' logged in successfully.", 'admin-success');
                }

                unset($_SESSION['old_login_data']);
                redirect('dashboard.php');
            } else {
                $message = '<p style="color: red;">Invalid username/email or password.</p>';
                // --- This is where the code logs failed attempts due to incorrect credentials ---
                log_event("Failed login attempt for username/email: '{$login_input}' from IP: {$ip_address}.", 'failure');
                // Register the failed attempt for IP blocking
                register_failed_attempt($ip_address);
            }
        } catch (PDOException $e) {
            error_log("PMMS Login Error: " . $e->getMessage());
            $message = '<p style="color: red;">A database error occurred during login. Please try again later.</p>';
            log_event("Database error during login for username/email '{$login_input}'. Error: " . $e->getMessage(), 'error');
            register_failed_attempt($ip_address);
        }
    }
    $_SESSION['auth_message'] = $message;
    redirect('auth_portal.php');
}

$message = $_SESSION['auth_message'] ?? $_SESSION['registration_message'] ?? '';
unset($_SESSION['auth_message']);
unset($_SESSION['registration_message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PMMS - Login or Register</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }
    </style>
</head>
<body class="bg-gradient-to-br from-[#3e227a] to-[#ff33c2] flex items-center justify-center min-h-screen p-4">
    <div class="bg-white bg-opacity-10 backdrop-blur-lg p-8 md:p-12 rounded-xl shadow-2xl w-full max-w-md mx-auto transform transition duration-500 hover:scale-105">
        <h2 class="text-3xl font-bold text-center text-white mb-6">Login to PMMS</h2>
        <?php 
        if (!empty($message)) echo '<div class="text-center mb-4 text-red-400 font-medium">' . htmlspecialchars($message) . '</div>'; 
        ?>
        <form action="auth_portal.php" method="POST" class="space-y-6">
            <div>
                <label for="login_input" class="block text-sm font-medium text-gray-200">Username or Email Address</label>
                <input type="text" id="login_input" name="login_input" required
                       class="mt-1 block w-full p-3 bg-white bg-opacity-20 border border-white border-opacity-30 rounded-lg text-white placeholder-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 transition duration-300">
            </div>
            <div>
                <div class="flex justify-between items-center">
                    <label for="password" class="block text-sm font-medium text-gray-200">Password</label>
                    <a href="forgot_password.php" class="text-sm text-blue-400 hover:text-blue-200 transition duration-300 ease-in-out">
                        Forgot Password?
                    </a>
                </div>
                <input type="password" id="password" name="password" required
                       class="mt-1 block w-full p-3 bg-white bg-opacity-20 border border-white border-opacity-30 rounded-lg text-white placeholder-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 transition duration-300">
            </div>
            <button type="submit" name="login"
                    class="w-full py-3 px-4 bg-blue-600 text-white font-semibold rounded-lg shadow-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-300 ease-in-out transform hover:scale-105">
                Login
            </button>
        </form>
        <div class="mt-8 text-center text-gray-300">
            Don't have an account?
            <a href="register.php" class="font-medium text-blue-400 hover:text-blue-200 transition duration-300 ease-in-out">
                Register here
            </a>
        </div>
    </div>
</body>
</html>
