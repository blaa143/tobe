 <?php
// process_registration.php - Handles form submission and user registration with robust validation.
require_once 'config.php';

// Ensure this script is only accessed via POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('register.php');
    exit();
}

// Initialize an array to store validation errors
$errors = [];

// Sanitize and retrieve form data
$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$role_name = $_POST['role'] ?? '';

// Retain old form data in session for re-population if validation fails
$_SESSION['old_registration_data'] = [
    'first_name' => $first_name,
    'last_name' => $last_name,
    'username' => $username,
    'email' => $email,
    'role' => $role_name
];

// --- Server-Side Validation ---

// Check for empty fields
if (empty($first_name) || empty($last_name) || empty($username) || empty($email) || empty($password) || empty($confirm_password) || empty($role_name)) {
    $errors[] = 'All fields are required.';
}

// Validate password length and complexity
// At least 8 characters, with a letter, a number, and a special character
if (!preg_match('/^(?=.*[a-zA-Z])(?=.*\d)(?=.*\W).{8,}$/', $password)) {
    $errors[] = 'Password must be at least 8 characters long and contain a combination of letters, numbers, and special characters.';
}

// Check if passwords match
if ($password !== $confirm_password) {
    $errors[] = 'Passwords do not match.';
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'The email address is not in a valid format.';
}

// Check for unique username and email
try {
    // Check for unique username
    $stmt_username = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
    $stmt_username->execute([$username]);
    if ($stmt_username->fetch()) {
        $errors[] = 'This username is already taken. Please choose a different one.';
    }

    // Check for unique email
    $stmt_email = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt_email->execute([$email]);
    if ($stmt_email->fetch()) {
        $errors[] = 'This email address is already registered.';
    }
} catch (PDOException $e) {
    error_log("PMMS Registration Validation Error: " . $e->getMessage());
    $errors[] = 'A database error occurred during validation. Please try again later.';
}

// If there are any errors, redirect back to the registration page with messages
if (!empty($errors)) {
    $_SESSION['registration_message'] = '<div class="message" style="color: red;">' . implode('<br>', array_map('htmlspecialchars', $errors)) . '</div>';
    redirect('register.php');
    exit();
}

// --- If Validation Passes, Process Registration ---

try {
    // Get the role_id from the role_name
    $stmt_role = $pdo->prepare("SELECT role_id FROM roles WHERE role_name = ?");
    $stmt_role->execute([$role_name]);
    $role = $stmt_role->fetch();

    if (!$role) {
        $errors[] = 'Invalid role selected.';
        $_SESSION['registration_message'] = '<div class="message" style="color: red;">' . implode('<br>', array_map('htmlspecialchars', $errors)) . '</div>';
        redirect('register.php');
        exit();
    }

    $role_id = $role['role_id'];

    // Hash the password securely
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // Prepare and execute the INSERT statement
    $stmt = $pdo->prepare("
        INSERT INTO users (first_name, last_name, username, email, password_hash, role_id)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $first_name,
        $last_name,
        $username,
        $email,
        $password_hash,
        $role_id
    ]);

    // Clear old data on success
    unset($_SESSION['old_registration_data']);

    // Set a success message and redirect to the login page
    $_SESSION['registration_message'] = '<div class="message" style="color: green;">Registration successful! You can now log in.</div>';
    redirect('auth_portal.php');

} catch (PDOException $e) {
    error_log("PMMS Registration DB Error: " . $e->getMessage());
    $_SESSION['registration_message'] = '<div class="message" style="color: red;">A database error occurred during registration. Please try again later.</div>';
    redirect('register.php');
}
?>
