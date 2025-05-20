<?php
/**
 * Email Helper Class
 * A wrapper around PHPMailer for easy email sending with better error handling
 */

namespace FarmFresh;

// Try to include Composer autoloader
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
} else {
    // Fallback to direct includes if Composer is not available
    $phpmailerBase = __DIR__ . '/../phpmailer/';
    
    if (file_exists($phpmailerBase . 'src/PHPMailer.php')) {
        require_once $phpmailerBase . 'src/PHPMailer.php';
        require_once $phpmailerBase . 'src/SMTP.php';
        require_once $phpmailerBase . 'src/Exception.php';
    } else {
        // If PHPMailer isn't installed, log the error
        error_log("PHPMailer not found. Please install it via Composer or manually.");
    }
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

class Mailer {
    private $mail;
    private $errors = [];
    private $isConfigured = false;
    private $debug = false;
    
    /**
     * Initialize the mailer with configuration
     */
    public function __construct($debug = false) {
        $this->debug = $debug;
        $this->mail = new PHPMailer(true);
        
        try {
            // Server settings
            $this->mail->isSMTP();
            $this->mail->Host = 'smtp.gmail.com'; // Change to your SMTP host
            $this->mail->SMTPAuth = true;
            $this->mail->Username = 'your-smtp-username@example.com'; // SMTP username
            $this->mail->Password = 'your-smtp-password'; // SMTP password
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mail->Port = 587;
            
            // Set debug level
            if ($this->debug) {
                $this->mail->SMTPDebug = SMTP::DEBUG_SERVER;
            }
            
            // Default settings
            $this->mail->isHTML(true);
            $this->mail->CharSet = 'UTF-8';
            
            $this->isConfigured = true;
        } catch (Exception $e) {
            $this->errors[] = "Mailer configuration failed: " . $e->getMessage();
            error_log("FarmFresh Mailer configuration error: " . $e->getMessage());
        }
    }
    
    /**
     * Send a contact form email
     * 
     * @param string $name Sender's name
     * @param string $email Sender's email
     * @param string $subject Email subject
     * @param string $message Email message
     * @param string $to Recipient email
     * @return bool Success status
     */
    public function sendContactEmail($name, $email, $subject, $message, $to = 'quinonesjames650@gmail.com') {
        if (!$this->isConfigured) {
            $this->errors[] = "Mailer not properly configured";
            return false;
        }
        
        if (empty($name) || empty($email) || empty($message)) {
            $this->errors[] = "Required fields missing";
            return false;
        }
        
        try {
            // Reset recipients
            $this->mail->clearAddresses();
            $this->mail->clearReplyTos();
            
            // Set sender and recipient
            $this->mail->setFrom($email, $name);
            $this->mail->addAddress($to);
            $this->mail->addReplyTo($email, $name);
            
            // Set email content
            $this->mail->Subject = !empty($subject) ? $subject : 'New Contact Form Message';
            
            // Create email body with template
            $emailBody = $this->getContactEmailTemplate($name, $email, $subject, $message);
            
            $this->mail->Body = $emailBody;
            $this->mail->AltBody = strip_tags(str_replace(['<br>', '</p>'], ["\n", "\n\n"], $emailBody));
            
            // Send the email
            $success = $this->mail->send();
            
            // Log success
            if ($success) {
                error_log("Contact email sent successfully from {$email} to {$to}");
            }
            
            return $success;
        } catch (Exception $e) {
            $this->errors[] = "Message could not be sent. Mailer Error: " . $e->getMessage();
            error_log("FarmFresh Mailer error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get the HTML template for contact emails
     */
    private function getContactEmailTemplate($name, $email, $subject, $message) {
        $timestamp = date('F j, Y, g:i a');
        
        return "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; border: 1px solid #e1e1e1; border-radius: 5px; overflow: hidden; }
                .header { background-color: #3b7a57; color: white; padding: 20px; text-align: center; }
                .header h2 { margin: 0; font-size: 22px; }
                .content { padding: 20px; background-color: #fff; }
                .field { margin-bottom: 15px; }
                .field-label { font-weight: bold; display: inline-block; min-width: 100px; }
                .message-content { background-color: #f9f9f9; padding: 15px; border-radius: 4px; margin-top: 5px; white-space: pre-line; }
                .footer { font-size: 12px; color: #666; padding: 15px 20px; background-color: #f5f5f5; border-top: 1px solid #e1e1e1; }
                .timestamp { font-style: italic; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>New Contact Form Message</h2>
                </div>
                <div class='content'>
                    <div class='field'>
                        <span class='field-label'>From:</span> {$name}
                    </div>
                    <div class='field'>
                        <span class='field-label'>Email:</span> {$email}
                    </div>
                    <div class='field'>
                        <span class='field-label'>Subject:</span> {$subject}
                    </div>
                    <div class='field'>
                        <span class='field-label'>Message:</span>
                        <div class='message-content'>" . nl2br(htmlspecialchars($message)) . "</div>
                    </div>
                </div>
                <div class='footer'>
                    <p>This email was sent from your website contact form.</p>
                    <p class='timestamp'>Timestamp: {$timestamp}</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Get any errors that occurred
     * 
     * @return array Array of error messages
     */
    public function getErrors() {
        return $this->errors;
    }
} 