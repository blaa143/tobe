 <?php
// logger.php - A simple utility for logging security events.

/**
 * Logs a message to a security log file.
 *
 * @param string $message The message to be logged.
 * @param string $type The type of log event (e.g., 'info', 'success', 'failure').
 * @return void
 */
function log_event($message, $type = 'info') {
    $log_directory = __DIR__ . '/logs';
    $log_file = $log_directory . '/security.log';

    // Create the logs directory if it doesn't exist.
    if (!is_dir($log_directory)) {
        if (!mkdir($log_directory, 0775, true)) {
            // Echo an error if directory creation fails
            error_log("Failed to create log directory: " . $log_directory);
            return;
        }
    }

    $timestamp = date('Y-m-d H:i:s');
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $log_message = sprintf("[%s] [%s] [%s] %s\n", $timestamp, strtoupper($type), $ip_address, $message);

    try {
        if (file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX) === false) {
            error_log("Failed to write to log file: " . $log_file);
        }
    } catch (Exception $e) {
        // This will catch any exceptions thrown during file writing.
        error_log("Error writing to log file: " . $e->getMessage());
    }
}
?>
