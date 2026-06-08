<?php

class OTPGenerator {
    
    public static function generate($length = 6) {
        $otp = '';
        for ($i = 0; $i < $length; $i++) {
            $otp .= rand(0, 9);
        }
        return $otp;
    }
    
    public static function save($conn, $email, $otp) {
        $expires_at = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MINUTES . ' minutes'));
        
        $conn->query("DELETE FROM otp_verification WHERE email = '" . $conn->real_escape_string($email) . "'");
        
        $sql = "INSERT INTO otp_verification (email, otp, expires_at) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sss', $email, $otp, $expires_at);
        
        return $stmt->execute();
    }
    
    public static function verify($conn, $email, $otp) {
        $sql = "SELECT * FROM otp_verification WHERE email = ? AND otp = ? AND expires_at > NOW()";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $email, $otp);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->num_rows > 0;
    }
    
    public static function deleteOTP($conn, $email) {
        $sql = "DELETE FROM otp_verification WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $email);
        
        return $stmt->execute();
    }
}

?>