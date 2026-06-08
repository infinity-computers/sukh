<?php

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sukhdham_db');
define('DB_PORT', 3306);

// OTP Configuration
define('OTP_LENGTH', 6);
define('OTP_EXPIRY_MINUTES', 10);

// Session Configuration
define('SESSION_TIMEOUT_MINUTES', 15);

// Email Configuration
define('NOREPLY_EMAIL', 'noreply@sukhdham.in');
define('MAIL_FROM_NAME', 'Sukhdham Properties');

// Upload Configuration
define('UPLOAD_DIR', __DIR__ . '/../public/uploads/properties/');
define('UPLOAD_URL', '/public/uploads/properties/');
define('MAX_IMAGE_SIZE', 5 * 1024 * 1024); // 5MB
define('MAX_IMAGES_PER_PROPERTY', 10);
define('MIN_IMAGE_WIDTH', 800);
define('MIN_IMAGE_HEIGHT', 600);
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'webp']);

// Create uploads directory if it doesn't exist
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// Database connection
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

?>
