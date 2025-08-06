 <?php
// admin_log_viewer.php - Displays failed login attempts and allows for IP blocking.
require_once 'config.php';

// --- Access Control: Only Administrators and Coordinators can access this page ---
if (!isset($_SESSION['user_id']) || ($_SESSION['role_name'] !== 'Administrator' && $_SESSION['role_name'] !== 'Coordinator')) {
    redirect('dashboard.php');
}

$message = '';
$failed_logins = [];
$blocked_ips = [];

// --- Handle IP Blocking via Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['block_ip'])) {
    $ip_to_block = filter_input(INPUT_POST, 'ip_to_block', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    if ($ip_to_block) {
        try {
            $stmt = $pdo->prepare("INSERT INTO ip_blacklist (ip_address, blocked_by_user_id, reason) VALUES (?, ?, ?)");
            $stmt->execute([$ip_to_block, $_SESSION['user_id'], 'Manually blocked from admin panel']);
            $message = '<p style="color: green;">IP address ' . htmlspecialchars($ip_to_block) . ' has been successfully blocked.</p>';
        } catch (PDOException $e) {
            error_log("PMMS Admin IP Blocker Error: " . $e->getMessage());
            $message = '<p style="color: red;">Error blocking IP: ' . $e->getMessage() . '</p>';
        }
    } else {
        $message = '<p style="color: red;">Invalid IP address submitted.</p>';
    }
}


// --- Fetch Failed Login Attempts ---
try {
    $stmt_logs = $pdo->query("SELECT * FROM failed_logins ORDER BY created_at DESC LIMIT 50");
    $failed_logins = $stmt_logs->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("PMMS Admin Log Viewer Error: Failed to fetch logs: " . $e->getMessage());
    $message .= '<p style="color: red;">Error loading failed login attempts.</p>';
}

// --- Fetch Currently Blocked IPs ---
try {
    $stmt_blocked = $pdo->query("SELECT * FROM ip_blacklist ORDER BY blocked_at DESC");
    $blocked_ips = $stmt_blocked->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("PMMS Admin Log Viewer Error: Failed to fetch blocked IPs: " . $e->getMessage());
    $message .= '<p style="color: red;">Error loading blocked IP list.</p>';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PMMS - Security Logs & IP Blocker</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f0f2f5; margin: 0; padding: 20px; }
        .container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); max-width: 1200px; margin: 20px auto; }
        h1, h2 { color: #333; margin-bottom: 20px; border-bottom: 1px solid #ddd; padding-bottom: 10px; }
        .back-link { margin-bottom: 20px; }
        .back-link a { color: #007bff; text-decoration: none; }
        .back-link a:hover { text-decoration: underline; }
        .message { margin-bottom: 20px; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; vertical-align: top; }
        th { background-color: #e9ecef; }
        .action-button {
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            border: 1px solid #dc3545;
            background-color: #dc3545;
            color: #fff;
            text-decoration: none;
            font-size: 0.9em;
        }
        .action-button:hover { opacity: 0.8; }
        .block-form { display: flex; gap: 10px; margin-bottom: 20px; }
        .block-form input[type="text"] { flex-grow: 1; padding: 8px; border-radius: 4px; border: 1px solid #ccc; }
        .block-form button { padding: 8px 15px; border-radius: 4px; border: none; background-color: #007bff; color: #fff; cursor: pointer; }
        .block-form button:hover { background-color: #0056b3; }
        .log-section { margin-top: 30px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="back-link">
            <a href="dashboard.php">&larr; Back to Dashboard</a>
        </div>
        <h1>Security Logs & IP Blocker</h1>
        <?php echo $message; ?>

        <div class="log-section">
            <h2>Failed Login Attempts (Last 50)</h2>
            <?php if (empty($failed_logins)): ?>
                <p>No failed login attempts recorded.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Attempted Username</th>
                            <th>IP Address</th>
                            <th>Timestamp</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($failed_logins as $log): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($log['username']); ?></td>
                                <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                <td><?php echo htmlspecialchars($log['created_at']); ?></td>
                                <td>
                                    <form action="admin_log_viewer.php" method="POST" style="display:inline;">
                                        <input type="hidden" name="ip_to_block" value="<?php echo htmlspecialchars($log['ip_address']); ?>">
                                        <button type="submit" name="block_ip" class="action-button">Block IP</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="log-section">
            <h2>Manually Blocked IPs</h2>
            <p>Use the form below to manually block an IP address.</p>
            <form action="admin_log_viewer.php" method="POST" class="block-form">
                <input type="text" name="ip_to_block" placeholder="Enter IP address to block" required>
                <button type="submit" name="block_ip">Block IP</button>
            </form>
            <?php if (empty($blocked_ips)): ?>
                <p>No IP addresses are currently blocked.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>IP Address</th>
                            <th>Blocked By</th>
                            <th>Blocked At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($blocked_ips as $blocked): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($blocked['ip_address']); ?></td>
                                <td><?php echo htmlspecialchars($blocked['blocked_by_user_id']); ?></td>
                                <td><?php echo htmlspecialchars($blocked['blocked_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
