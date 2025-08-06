 <?php
// register.php - Displays the registration form
require_once 'config.php'; // For $pdo and session_start() for dynamic roles

// Check for messages from process_registration.php (after a form submission attempt)
$message = $_SESSION['registration_message'] ?? '';
unset($_SESSION['registration_message']); // Clear message after displaying

// Retain form data if validation failed in process_registration.php
$old_username = $_SESSION['old_registration_data']['username'] ?? '';
$old_email = $_SESSION['old_registration_data']['email'] ?? '';
$old_first_name = $_SESSION['old_registration_data']['first_name'] ?? '';
$old_last_name = $_SESSION['old_registration_data']['last_name'] ?? '';
$old_role_name = $_SESSION['old_registration_data']['role'] ?? 'Mentee';
unset($_SESSION['old_registration_data']); // Clear old data

// --- IMPORTANT CHANGE: Removed the redirect logic entirely from here. ---
// This page should always display the registration form.
// Redirection after successful registration happens in process_registration.php.
// If a user is logged in and wants to register another user, they should be able to.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PMMS - Register</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .register-container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); width: 400px; }
        h2 { text-align: center; color: #333; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; color: #555; }
        input[type="text"],
        input[type="email"],
        input[type="password"],
        select {
            width: calc(100% - 20px);
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            width: 100%;
            padding: 10px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
        }
        button:hover {
            background-color: #0056b3;
        }
        .message { text-align: center; margin-top: 15px; }
        .login-link { text-align: center; margin-top: 20px; }
        .login-link a { color: #007bff; text-decoration: none; }
        .login-link a:hover { text-decoration: underline; }
        .validation-message { font-size: 0.8rem; margin-top: 5px; }
        .valid { color: green; }
        .invalid { color: red; }
    </style>
</head>
<body>
    <div class="register-container">
        <h2>Register for PMMS</h2>
        <?php echo $message; // Display messages here ?>
        <form action="process_registration.php" method="POST">
            <div class="form-group">
                <label for="first_name">First Name:</label>
                <input type="text" id="first_name" name="first_name" required value="<?php echo htmlspecialchars($old_first_name); ?>">
            </div>
            <div class="form-group">
                <label for="last_name">Last Name:</label>
                <input type="text" id="last_name" name="last_name" required value="<?php echo htmlspecialchars($old_last_name); ?>">
            </div>
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required value="<?php echo htmlspecialchars($old_username); ?>">
            </div>
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($old_email); ?>">
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required oninput="validatePassword()">
                <div id="password-validation" class="validation-message"></div>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required oninput="validatePassword()">
                <div id="confirm-password-validation" class="validation-message"></div>
            </div>
            <div class="form-group">
                <label for="role">Register as:</label>
                <select id="role" name="role">
                    <?php
                    try {
                        $stmt_roles_dd = $pdo->query("SELECT role_name FROM roles ORDER BY role_name");
                        $available_roles = $stmt_roles_dd->fetchAll(PDO::FETCH_COLUMN);

                        foreach ($available_roles as $role_option) {
                            $selected = ($role_option === $old_role_name) ? 'selected' : '';
                            echo '<option value="' . htmlspecialchars($role_option) . '" ' . $selected . '>' . htmlspecialchars($role_option) . '</option>';
                        }
                    } catch (PDOException $e) {
                        echo '<option value="Mentee">Mentee (Error fetching roles)</option>';
                        error_log("PMMS Register Error: Failed to fetch roles for dropdown: " . $e->getMessage());
                    }
                    ?>
                </select>
            </div>
            <button type="submit">Register</button>
        </form>
        <div class="login-link">
            Already have an account? <a href="auth_portal.php">Login here</a>.
        </div>
    </div>

    <script>
        function validatePassword() {
            const password = document.getElementById('password').value;
            const confirm_password = document.getElementById('confirm_password').value;
            const passwordValidationDiv = document.getElementById('password-validation');
            const confirmPasswordValidationDiv = document.getElementById('confirm-password-validation');

            // Regex for password complexity: at least 8 chars, 1 letter, 1 number, 1 special character.
            const passwordRegex = /^(?=.*[a-zA-Z])(?=.*\d)(?=.*\W).{8,}$/;
            const isPasswordValid = passwordRegex.test(password);

            // Display password validation message
            if (password === '') {
                passwordValidationDiv.textContent = '';
                passwordValidationDiv.className = 'validation-message';
            } else if (isPasswordValid) {
                passwordValidationDiv.textContent = 'Password meets requirements.';
                passwordValidationDiv.className = 'validation-message valid';
            } else {
                passwordValidationDiv.textContent = 'Password must be at least 8 characters long and contain a letter, number, and special character.';
                passwordValidationDiv.className = 'validation-message invalid';
            }

            // Display confirm password validation message
            if (confirm_password === '') {
                confirmPasswordValidationDiv.textContent = '';
                confirmPasswordValidationDiv.className = 'validation-message';
            } else if (password === confirm_password) {
                confirmPasswordValidationDiv.textContent = 'Passwords match.';
                confirmPasswordValidationDiv.className = 'validation-message valid';
            } else {
                confirmPasswordValidationDiv.textContent = 'Passwords do not match.';
                confirmPasswordValidationDiv.className = 'validation-message invalid';
            }
        }
    </script>
</body>
</html>
