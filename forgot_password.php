<?php
// forgot_password.php - Handles the "forgot password" process.
require_once 'config.php';

$message = '';
$is_success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $message = '<p style="color: red;">Please enter your email address.</p>';
    } else {
        try {
            // Check if email exists in the database
            $stmt = $pdo->prepare("SELECT user_id, first_name FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // In a real application, you would generate a secure token,
                // save it to the database, and send an email to the user with
                // a link containing the token.
                // For this example, we'll just show a success message.

                // Example of a real-world token generation:
                // $token = bin2hex(random_bytes(32));
                // $expires = date("U") + 1800; // Token expires in 30 minutes
                // $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires) VALUES (?, ?, ?)");
                // $stmt->execute([$user['user_id'], $token, $expires]);
                //
                // Send an email with a link like:
                // <a href="http://localhost/pmms/reset_password.php?token=' . $token . '">Reset Password</a>

                $message = '<p style="color: green;">If your email address is in our database, you will receive a password reset link shortly.</p>';
                $is_success = true;
            } else {
                // To prevent email enumeration, we provide the same message
                // whether the email exists or not.
                $message = '<p style="color: green;">If your email address is in our database, you will receive a password reset link shortly.</p>';
                $is_success = true;
            }
        } catch (PDOException $e) {
            error_log("PMMS Forgot Password Error: " . $e->getMessage());
            $message = '<p style="color: red;">An error occurred. Please try again.</p>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PMMS - Forgot Password</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }
    </style>
</head>
<body class="bg-gradient-to-br from-[#3e227a] to-[#ff33c2] flex items-center justify-center min-h-screen p-4">
    <div class="bg-white bg-opacity-10 backdrop-blur-lg p-8 md:p-12 rounded-xl shadow-2xl w-full max-w-md mx-auto transform transition duration-500 hover:scale-105">
        <h2 class="text-3xl font-bold text-center text-white mb-6">Forgot Your Password?</h2>

        <?php if (!empty($message)) echo '<div class="text-center mb-4">' . $message . '</div>'; ?>

        <p class="text-gray-300 text-center mb-6">
            Enter the email address associated with your account and we'll send you a link to reset your password.
        </p>

        <form action="forgot_password.php" method="POST" class="space-y-6">
            <div>
                <label for="email" class="block text-sm font-medium text-gray-200">Email Address</label>
                <input type="email" id="email" name="email" required
                       class="mt-1 block w-full p-3 bg-white bg-opacity-20 border border-white border-opacity-30 rounded-lg text-white placeholder-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 transition duration-300">
            </div>
            <button type="submit" name="request_reset"
                    class="w-full py-3 px-4 bg-blue-600 text-white font-semibold rounded-lg shadow-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-300 ease-in-out transform hover:scale-105">
                Send Reset Link
            </button>
        </form>

        <div class="mt-8 text-center text-gray-300">
            <a href="auth_portal.php" class="font-medium text-blue-400 hover:text-blue-200 transition duration-300 ease-in-out">
                Back to Login
            </a>
        </div>
    </div>
</body>
</html>
