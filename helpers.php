<?php
// helpers.php - Contains various helper functions for the PMMS, including email sending.

/**
 * Sends a basic email.
 * This is a simple implementation. For production, consider a robust library like PHPMailer
 * or a transactional email service (e.g., SendGrid, Mailgun).
 *
 * @param string $to The recipient's email address.
 * @param string $subject The subject line of the email.
 * @param string $message The body of the email.
 * @param string $headers The email headers.
 * @return bool True on success, false on failure.
 */
function send_email($to, $subject, $message) {
    // You may need to configure your server's mail settings for this to work.
    // If you are using a local XAMPP/WAMP server, you will need to configure sendmail.ini and php.ini.

    // Headers for a basic HTML email
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Project Management System <noreply@yourdomain.com>" . "\r\n";

    return mail($to, $subject, $message, $headers);
}