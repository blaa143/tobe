 <?php
// delete_user.php - Handles user deletion with critical security checks
require_once 'config.php';

// --- Access Control: Only logged-in Administrators can access this page ---
// Note: Changed from your version to only allow Administrators for security.
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] !== 'Administrator') {
    $_SESSION['message'] = '<p style="color: red;">You are not authorized to delete users.</p>';
    redirect('dashboard.php');
}

$user_id_to_delete = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$current_user_id = $_SESSION['user_id'];

if ($user_id_to_delete <= 0) {
    $_SESSION['message'] = '<p style="color: red;">Invalid user ID specified.</p>';
    redirect('admin_users.php');
}

try {
    // --- CRITICAL SECURITY CHECKS ---
    // 1. Is the user trying to delete their own account?
    if ($user_id_to_delete === $current_user_id) {
        $_SESSION['message'] = '<p style="color: red;">You cannot delete your own account.</p>';
        redirect('admin_users.php');
    }

    // 2. Is the user to be deleted an Administrator?
    $stmt_check_role = $pdo->prepare("SELECT r.role_name FROM users u JOIN roles r ON u.role_id = r.role_id WHERE u.user_id = ?");
    $stmt_check_role->execute([$user_id_to_delete]);
    $deleted_user_role = $stmt_check_role->fetchColumn();

    if ($deleted_user_role === 'Administrator') {
        // 3. Is the user to be deleted the ONLY Administrator?
        $stmt_count_admins = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role_id = (SELECT role_id FROM roles WHERE role_name = 'Administrator')");
        $stmt_count_admins->execute();
        $admin_count = $stmt_count_admins->fetchColumn();

        if ($admin_count <= 1) {
            $_SESSION['message'] = '<p style="color: red;">Cannot delete the last Administrator account.</p>';
            redirect('admin_users.php');
        }
    }

    // 4. Check if the user is a Mentee assigned to any projects (prevents orphaned data)
    $stmt_check_mentee_projects = $pdo->prepare("
        SELECT COUNT(*)
        FROM projects
        WHERE project_owner_mentee_id = (SELECT mentee_id FROM mentees WHERE user_id = ?)
    ");
    $stmt_check_mentee_projects->execute([$user_id_to_delete]);
    if ($stmt_check_mentee_projects->fetchColumn() > 0) {
        $_SESSION['message'] = '<p style="color: red;">Cannot delete this mentee account as they own projects.</p>';
        redirect('admin_users.php');
    }

    // 5. Check if the user is a Mentor assigned to any projects (prevents orphaned data)
    $stmt_check_mentor_assignments = $pdo->prepare("SELECT COUNT(*) FROM mentorship_assignments WHERE mentor_user_id = ?");
    $stmt_check_mentor_assignments->execute([$user_id_to_delete]);
    if ($stmt_check_mentor_assignments->fetchColumn() > 0) {
        $_SESSION['message'] = '<p style="color: red;">Cannot delete this mentor account as they are assigned to projects.</p>';
        redirect('admin_users.php');
    }

    // --- Deletion Logic (if all checks pass) ---
    // Note: We'll rely on our ON DELETE CASCADE foreign keys for safety,
    // but the checks above are what prevent data integrity issues.
    $stmt_delete = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt_delete->execute([$user_id_to_delete]);

    $_SESSION['message'] = '<p style="color: green;">User deleted successfully.</p>';
    redirect('admin_users.php');

} catch (PDOException $e) {
    error_log("PMMS Delete User Error: " . $e->getMessage());
    $_SESSION['message'] = '<p style="color: red;">Error deleting user: ' . $e->getMessage() . '</p>';
    redirect('admin_users.php');
}