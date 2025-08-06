 <?php
// admin_users.php - Allows Administrators and Coordinators to manage system users.
require_once 'config.php';

// --- Access Control: Only Administrators and Coordinators can access this page ---
if (!isset($_SESSION['user_id']) || ($_SESSION['role_name'] !== 'Administrator' && $_SESSION['role_name'] !== 'Coordinator')) {
    redirect('dashboard.php');
}

$message = '';
$users = [];
$roles = [];

// --- Fetch all roles for the role dropdowns ---
try {
    $stmt_roles = $pdo->query("SELECT role_id, role_name FROM roles ORDER BY role_name");
    $roles = $stmt_roles->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("PMMS Admin Users Error: Failed to fetch roles for dropdown: " . $e->getMessage());
    $message = '<p style="color: red;">Error loading roles for user management.</p>';
}

// --- Handle User Deletion ---
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $user_to_delete_id = (int)$_GET['id'];

    if ($user_to_delete_id === $_SESSION['user_id']) {
        $message = '<p style="color: red;">You cannot delete your own account!</p>';
    } else {
        try {
            $pdo->beginTransaction();

            // CRITICAL CHECK: Prevent deleting a mentee who owns a project.
            $stmt_check_mentee_projects = $pdo->prepare("
                SELECT COUNT(*)
                FROM projects
                WHERE project_owner_mentee_id = (SELECT mentee_id FROM mentees WHERE user_id = ?)
            ");
            $stmt_check_mentee_projects->execute([$user_to_delete_id]);
            if ($stmt_check_mentee_projects->fetchColumn() > 0) {
                $_SESSION['admin_message'] = '<p style="color: red;">Cannot delete this mentee as they own projects. Please reassign the projects first.</p>';
                redirect('admin_users.php');
                exit;
            }

            // CRITICAL CHECK: Prevent deleting a mentor who is assigned to a project.
            $stmt_check_mentor_assignments = $pdo->prepare("
                SELECT COUNT(*)
                FROM mentorship_assignments
                WHERE mentor_user_id = ?
            ");
            $stmt_check_mentor_assignments->execute([$user_to_delete_id]);
            if ($stmt_check_mentor_assignments->fetchColumn() > 0) {
                $_SESSION['admin_message'] = '<p style="color: red;">Cannot delete this mentor as they are assigned to projects. Please reassign the projects first.</p>';
                redirect('admin_users.php');
                exit;
            }

            // If all checks pass, proceed with deletion.
            $stmt_del_mentor = $pdo->prepare("DELETE FROM mentors WHERE user_id = ?");
            $stmt_del_mentor->execute([$user_to_delete_id]);

            $stmt_del_mentee = $pdo->prepare("DELETE FROM mentees WHERE user_id = ?");
            $stmt_del_mentee->execute([$user_to_delete_id]);

            $stmt_delete_user = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt_delete_user->execute([$user_to_delete_id]);

            $pdo->commit();
            $_SESSION['admin_message'] = '<p style="color: green;">User deleted successfully!</p>';
            redirect('admin_users.php');
            exit();

        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = '<p style="color: red;">Error deleting user: ' . $e->getMessage() . '</p>';
            error_log("PMMS Admin Users Error (deletion): " . $e->getMessage());
        }
    }
}

// --- Handle User Role Update (via AJAX or form submission) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user_role'])) {
    $target_user_id = (int)$_POST['user_id'];
    $new_role_id = (int)$_POST['role_id'];

    if ($target_user_id === $_SESSION['user_id'] && $new_role_id !== $_SESSION['role_id']) {
        $message = '<p style="color: red;">You cannot change your own role directly from here.</p>';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt_update_role = $pdo->prepare("UPDATE users SET role_id = ? WHERE user_id = ?");
            $stmt_update_role->execute([$new_role_id, $target_user_id]);

            $stmt_get_new_role_name = $pdo->prepare("SELECT role_name FROM roles WHERE role_id = ?");
            $stmt_get_new_role_name->execute([$new_role_id]);
            $new_role_name = $stmt_get_new_role_name->fetchColumn();

            $stmt_del_mentor_profile = $pdo->prepare("DELETE FROM mentors WHERE user_id = ?");
            $stmt_del_mentor_profile->execute([$target_user_id]);
            $stmt_del_mentee_profile = $pdo->prepare("DELETE FROM mentees WHERE user_id = ?");
            $stmt_del_mentee_profile->execute([$target_user_id]);

            if ($new_role_name === 'Mentor') {
                $stmt_insert_mentor = $pdo->prepare("INSERT INTO mentors (user_id) VALUES (?)");
                $stmt_insert_mentor->execute([$target_user_id]);
            } elseif ($new_role_name === 'Mentee') {
                $stmt_insert_mentee = $pdo->prepare("INSERT INTO mentees (user_id) VALUES (?)");
                $stmt_insert_mentee->execute([$target_user_id]);
            }

            $pdo->commit();
            $_SESSION['admin_message'] = '<p style="color: green;">User role updated successfully!</p>';
            redirect('admin_users.php');
            exit();

        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = '<p style="color: red;">Error updating user role: ' . $e->getMessage() . '</p>';
            error_log("PMMS Admin Users Error (role update): " . $e->getMessage());
        }
    }
}


// --- Fetch all users for display ---
try {
    $stmt = $pdo->prepare("SELECT u.user_id, u.username, u.email, u.first_name, u.last_name, r.role_name, r.role_id
                          FROM users u
                          JOIN roles r ON u.role_id = r.role_id
                          ORDER BY u.user_id DESC");
    $stmt->execute();
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("PMMS Admin Users Error (fetching users): " . $e->getMessage());
    $message = '<p style="color: red;">Error fetching users from the database.</p>';
}

if (isset($_SESSION['admin_message'])) {
    $message = $_SESSION['admin_message'];
    unset($_SESSION['admin_message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PMMS - Manage Users</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f0f2f5; margin: 0; padding: 20px; }
        .container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); max-width: 1200px; margin: 20px auto; }
        h1 { color: #333; margin-bottom: 20px; }
        .message { margin-bottom: 20px; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; vertical-align: middle; }
        th { background-color: #e9ecef; }
        .action-links a, .action-links button {
            margin-right: 8px;
            text-decoration: none;
            color: #007bff;
            padding: 5px 10px;
            border-radius: 4px;
            background-color: #e7f5ff;
            border: 1px solid #007bff;
            cursor: pointer;
            font-size: 0.9em;
            display: inline-block;
        }
        .action-links a.delete, .action-links button.delete {
            color: #dc3545;
            background-color: #ffe7e7;
            border: 1px solid #dc3545;
        }
        .action-links a:hover, .action-links button:hover {
            opacity: 0.8;
        }
        .back-link { margin-bottom: 20px; }
        .back-link a { color: #007bff; text-decoration: none; }
        .back-link a:hover { text-decoration: underline; }
        select.role-select {
            padding: 5px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="back-link">
            <a href="dashboard.php">&larr; Back to Dashboard</a>
        </div>
        <h1>Manage System Users</h1>
        <?php echo $message; ?>

        <?php if (empty($users)): ?>
            <p>No users found in the system.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Full Name</th>
                        <th>Role</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user_row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user_row['user_id']); ?></td>
                            <td><?php echo htmlspecialchars($user_row['username']); ?></td>
                            <td><?php htmlspecialchars($user_row['email']); ?> </td>
                            <td><?php echo htmlspecialchars($user_row['first_name'] . ' ' . $user_row['last_name']); ?></td>
                            <td>
                                <form action="admin_users.php" method="POST" style="display:inline;">
                                    <input type="hidden" name="user_id" value="<?php echo $user_row['user_id']; ?>">
                                    <select name="role_id" class="role-select" onchange="this.form.submit()">
                                        <?php foreach ($roles as $role_option): ?>
                                            <option value="<?php echo htmlspecialchars($role_option['role_id']); ?>"
                                                <?php echo ($role_option['role_id'] == $user_row['role_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($role_option['role_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="update_user_role" value="1">
                                </form>
                            </td>
                            <td class="action-links">
                                <?php if ($user_row['user_id'] !== $_SESSION['user_id']): ?>
                                    <a href="admin_users.php?action=delete&id=<?php echo $user_row['user_id']; ?>" class="delete" onclick="return confirm('Are you sure you want to delete user <?php echo htmlspecialchars($user_row['username']); ?>? This cannot be undone!');">Delete</a>
                                <?php else: ?>
                                    <span style="color:rgb(25,22,125);">(Cannot delete self)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>