<?php
// ip_blocker.php - A utility to manage IP blocking based on failed login attempts.

// Define constants
define('BLOCKED_IPS_FILE', __DIR__ . '/logs/blocked_ips.json');
define('FAILED_ATTEMPTS_FILE', __DIR__ . '/logs/failed_attempts.json');

/**
 * Loads the data from a specified JSON file.
 *
 * @param string $file_path The path to the JSON file.
 * @return array The decoded JSON data, or an empty array if the file doesn't exist or is invalid.
 */
function load_json_file($file_path) {
    if (!file_exists($file_path) || filesize($file_path) == 0) {
        return [];
    }
    $data = file_get_contents($file_path);
    return json_decode($data, true) ?? [];
}

/**
 * Saves data to a JSON file.
 *
 * @param string $file_path The path to the JSON file.
 * @param array $data The data to be encoded and saved.
 * @return void
 */
function save_json_file($file_path, $data) {
    // Ensure the logs directory exists
    $log_directory = __DIR__ . '/logs';
    if (!is_dir($log_directory)) {
        mkdir($log_directory, 0775, true);
    }
    $json_data = json_encode($data, JSON_PRETTY_PRINT);
    file_put_contents($file_path, $json_data, LOCK_EX);
}

/**
 * Registers a failed login attempt for an IP.
 * The system no longer automatically blocks the IP.
 *
 * @param string $ip_address The IP address of the user.
 * @return void
 */
function register_failed_attempt($ip_address) {
    $failed_attempts = load_json_file(FAILED_ATTEMPTS_FILE);
    $failed_attempts[$ip_address] = ($failed_attempts[$ip_address] ?? 0) + 1;
    save_json_file(FAILED_ATTEMPTS_FILE, $failed_attempts);
}

/**
 * Adds an IP address to the blocked list, for manual use by an administrator.
 *
 * @param string $ip_address The IP address to block.
 * @return void
 */
function manual_block_ip($ip_address) {
    $blocked_ips = load_json_file(BLOCKED_IPS_FILE);
    if (!in_array($ip_address, $blocked_ips)) {
        $blocked_ips[] = $ip_address;
        save_json_file(BLOCKED_IPS_FILE, $blocked_ips);
        log_event("IP address {$ip_address} has been manually blocked by an administrator.", 'block');
    }
    // Also reset the failed attempts count for the newly blocked IP
    reset_failed_attempts($ip_address);
}

/**
 * Checks if an IP address is currently blocked.
 *
 * @param string $ip_address The IP address to check.
 * @return bool True if the IP is blocked, false otherwise.
 */
function is_ip_blocked($ip_address) {
    $blocked_ips = load_json_file(BLOCKED_IPS_FILE);
    return in_array($ip_address, $blocked_ips);
}

/**
 * Resets the failed attempt count for a given IP address.
 *
 * @param string $ip_address The IP address to unblock.
 * @return void
 */
function reset_failed_attempts($ip_address) {
    $failed_attempts = load_json_file(FAILED_ATTEMPTS_FILE);
    if (isset($failed_attempts[$ip_address])) {
        unset($failed_attempts[$ip_address]);
        save_json_file(FAILED_ATTEMPTS_FILE, $failed_attempts);
    }
}

/**
 * Manually unblocks an IP address.
 *
 * @param string $ip_address The IP address to unblock.
 * @return void
 */
function unblock_ip($ip_address) {
    $blocked_ips = load_json_file(BLOCKED_IPS_FILE);
    $key = array_search($ip_address, $blocked_ips);
    if ($key !== false) {
        unset($blocked_ips[$key]);
        save_json_file(BLOCKED_IPS_FILE, array_values($blocked_ips));
        log_event("IP address {$ip_address} has been manually unblocked by an administrator.", 'unblock');
    }
}

/**
 * Gets the current failed login attempts for all IPs.
 *
 * @return array A map of IP addresses to their failed attempt counts.
 */
function get_failed_attempts() {
    return load_json_file(FAILED_ATTEMPTS_FILE);
}

// We will also use a new `log_event` function which is defined in `logger.php`
// But we need to make sure we don't redefine it if it's already included.
if (!function_exists('log_event')) {
    function log_event($message, $type) {
        // This is a dummy function. In a real application, you'd include logger.php here.
        // For this example, we assume it's included via config.php.
        // The real log_event function will be used.
    }
}
?>
