<?php

class Mailer {

    private static function sendHtmlMail($to, $subject, $message, $fallbackContent, $fallbackFile)
    {
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8\r\n";
        $headers .= "From: " . MAIL_FROM_NAME . " <" . NOREPLY_EMAIL . ">\r\n";

        $sent = @mail($to, $subject, $message, $headers);

        if (!$sent) {
            $logDir = __DIR__ . '/../../logs';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }

            file_put_contents($logDir . '/' . $fallbackFile, $fallbackContent . "\n\n" . $message . "\n");
        }

        return true;
    }
    
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
                    <p>&copy; " . date('Y') . " Sukhdham Properties. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";

        self::sendHtmlMail(
            $email,
            $subject,
            $message,
            "OTP for $email: $otp\nGenerated at: " . date('Y-m-d H:i:s') . "\nExpires in " . OTP_EXPIRY_MINUTES . " minutes",
            'otp_' . preg_replace('/[^a-zA-Z0-9]/', '_', $email) . '.txt'
        );

        return true;
    }

    public static function sendVisitRequestNotification(array $details)
    {
        $subject = 'New Site Visit Request - ' . ($details['property_title'] ?? 'Sukhdham Estate');
        $to = $details['to'] ?? 'bharuch@sukhdham.in';
        $submittedAt = date('Y-m-d H:i:s');

        $html = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; background-color: #f4f4f4; color: #1f2937; }
                .container { max-width: 640px; margin: 0 auto; background-color: #ffffff; padding: 24px; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
                .header { margin-bottom: 18px; }
                .title { margin: 0; color: #c2410c; }
                .section { margin-top: 18px; padding-top: 18px; border-top: 1px solid #e5e7eb; }
                .row { margin: 8px 0; }
                .label { font-weight: bold; color: #111827; }
                .note { background: #fff7ed; padding: 14px 16px; border-radius: 8px; margin-top: 16px; color: #9a3412; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2 class='title'>New Site Visit Request</h2>
                    <p>A visitor has submitted a site visit request from the Sukhdham website.</p>
                </div>

                <div class='section'>
                    <div class='row'><span class='label'>Name:</span> " . htmlspecialchars($details['name'] ?? '-') . "</div>
                    <div class='row'><span class='label'>Phone:</span> " . htmlspecialchars($details['phone'] ?? '-') . "</div>
                    <div class='row'><span class='label'>Requested Date:</span> " . htmlspecialchars($details['visit_date'] ?? '-') . "</div>
                    <div class='row'><span class='label'>Requested Time:</span> " . htmlspecialchars($details['visit_time'] ?? '-') . "</div>
                </div>

                <div class='section'>
                    <div class='row'><span class='label'>Property:</span> " . htmlspecialchars($details['property_title'] ?? '-') . "</div>
                    <div class='row'><span class='label'>Address:</span> " . htmlspecialchars($details['property_address'] ?? '-') . "</div>
                </div>

                <div class='section'>
                    <div class='row'><span class='label'>Message:</span></div>
                    <div class='row'>" . nl2br(htmlspecialchars($details['message'] ?? '-')) . "</div>
                </div>

                <div class='note'>Request submitted at " . $submittedAt . ". Please contact the customer directly for confirmation.</div>
            </div>
        </body>
        </html>
        ";

        self::sendHtmlMail(
            $to,
            $subject,
            $html,
            'Visit request notification generated at ' . $submittedAt,
            'visit_request_' . preg_replace('/[^a-zA-Z0-9]/', '_', (string) ($details['name'] ?? 'guest')) . '.txt'
        );

        return true;
    }

    public static function sendPropertyInquiry(array $details)
    {
        $subject = 'New Property Inquiry - ' . ($details['property_title'] ?? 'Sukhdham Estate');
        $to = $details['to'] ?? 'bharuch@sukhdham.in';
        $submittedAt = date('Y-m-d H:i:s');

        $html = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; background-color: #f4f4f4; color: #1f2937; }
                .container { max-width: 640px; margin: 0 auto; background-color: #ffffff; padding: 24px; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
                .header { margin-bottom: 18px; }
                .title { margin: 0; color: #c2410c; }
                .section { margin-top: 18px; padding-top: 18px; border-top: 1px solid #e5e7eb; }
                .row { margin: 8px 0; }
                .label { font-weight: bold; color: #111827; }
                .note { background: #fff7ed; padding: 14px 16px; border-radius: 8px; margin-top: 16px; color: #9a3412; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2 class='title'>New Property Inquiry</h2>
                    <p>A visitor has submitted a property inquiry from the Sukhdham website.</p>
                </div>

                <div class='section'>
                    <div class='row'><span class='label'>Name:</span> " . htmlspecialchars($details['name'] ?? '-') . "</div>
                    <div class='row'><span class='label'>Phone:</span> " . htmlspecialchars($details['phone'] ?? '-') . "</div>
                    <div class='row'><span class='label'>Email:</span> " . htmlspecialchars($details['email'] ?? 'Not provided') . "</div>
                </div>

                <div class='section'>
                    <div class='row'><span class='label'>Property:</span> " . htmlspecialchars($details['property_title'] ?? '-') . "</div>
                    <div class='row'><span class='label'>Address:</span> " . htmlspecialchars($details['property_address'] ?? '-') . "</div>
                </div>

                <div class='section'>
                    <div class='row'><span class='label'>Message:</span></div>
                    <div class='row'>" . nl2br(htmlspecialchars($details['message'] ?? '-')) . "</div>
                </div>

                <div class='note'>Inquiry submitted at " . $submittedAt . ". Please contact the customer directly.</div>
            </div>
        </body>
        </html>
        ";

        self::sendHtmlMail(
            $to,
            $subject,
            $html,
            'Property inquiry notification generated at ' . $submittedAt,
            'property_inquiry_' . preg_replace('/[^a-zA-Z0-9]/', '_', (string) ($details['name'] ?? 'guest')) . '.txt'
        );

        return true;
    }
}

?>