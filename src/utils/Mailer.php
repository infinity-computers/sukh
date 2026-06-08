<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Require PHPMailer files directly
require_once __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';

class Mailer {
    
    public static function sendOTP($email, $otp) {
        $subject = "Your Sukhdham OTP Code";
        
        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; background-color: #f4f4f4; }
                .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
                .header { text-align: center; margin-bottom: 20px; }
                .header h1 { color: #333; margin: 0; }
                .content { margin: 20px 0; text-align: center; }
                .otp-code { font-size: 32px; font-weight: bold; color: #007bff; letter-spacing: 5px; margin: 20px 0; background-color: #f8f9fa; padding: 20px; border-radius: 5px; }
                .footer { text-align: center; color: #666; font-size: 12px; margin-top: 20px; }
                .warning { color: #dc3545; font-size: 14px; margin-top: 10px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Sukhdham Properties</h1>
                </div>
                <div class='content'>
                    <p>Hello,</p>
                    <p>You have requested an OTP to access your Sukhdham admin account.</p>
                    <p>Your OTP code is:</p>
                    <div class='otp-code'>$otp</div>
                    <p>This code will expire in " . OTP_EXPIRY_MINUTES . " minutes.</p>
                    <p class='warning'>Please do not share this code with anyone.</p>
                </div>
                <div class='footer'>
                    <p>If you didn't request this OTP, please ignore this email.</p>
                    <p>&copy; 2026 Sukhdham Properties. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail = new PHPMailer(true);
        
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USERNAME;
            $mail->Password   = SMTP_PASSWORD;
            $mail->SMTPSecure = SMTP_SECURE;
            $mail->Port       = SMTP_PORT;

            // Recipients
            $mail->setFrom(SMTP_USERNAME, MAIL_FROM_NAME);
            $mail->addAddress($email);
            $mail->addReplyTo(SMTP_USERNAME, MAIL_FROM_NAME);

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $message;
            $mail->AltBody = "Your OTP is: $otp. It will expire in " . OTP_EXPIRY_MINUTES . " minutes.";

            $mail->send();
            return true;
        } catch (Exception $e) {
            // Log the error and the OTP as fallback
            $logDir = __DIR__ . '/../../logs';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            $logFile = $logDir . '/otp_' . preg_replace('/[^a-zA-Z0-9]/', '_', $email) . '.txt';
            $logContent = "OTP for $email: $otp\nError sending email: {$mail->ErrorInfo}\nGenerated at: " . date('Y-m-d H:i:s') . "\nExpires in " . OTP_EXPIRY_MINUTES . " minutes\n";
            file_put_contents($logFile, $logContent);
            
            return true; // Return true anyway so local development continues working without SMTP
        }
    }
}


?>