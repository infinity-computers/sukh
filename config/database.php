<?php

// Database configuration
// Automatically detect environment (local vs hosted)
$is_localhost = false;
if (isset($_SERVER['HTTP_HOST'])) {
    $host = strtolower($_SERVER['HTTP_HOST']);
    if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
        $is_localhost = true;
    }
}

if ($is_localhost) {
    // Local Development Configuration (XAMPP)
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'sukhdham_db');
    define('DB_PORT', 3306);
} else {
    // Hosted Production Configuration (Hostinger)
    define('DB_HOST', 'localhost');
    define('DB_USER', 'u211084505_sukhdham_user');
    define('DB_PASS', 'Infinity@54231');
    define('DB_NAME', 'u211084505_sukhdham_db');
    define('DB_PORT', 3306);
}

// OTP Configuration
define('OTP_LENGTH', 6);
define('OTP_EXPIRY_MINUTES', 10);

// Session Configuration
define('SESSION_TIMEOUT_MINUTES', 15);

// Email Configuration
define('NOREPLY_EMAIL', 'noreply@sukhdham.in');
define('MAIL_FROM_NAME', 'Sukhdham Properties');

// SMTP Configuration (for Hostinger or other real email servers)
define('SMTP_HOST', 'smtp.hostinger.com'); // e.g. smtp.hostinger.com
define('SMTP_USERNAME', 'your_email@domain.com'); // Your real email address
define('SMTP_PASSWORD', 'your_email_password'); // Your email password
define('SMTP_PORT', 465); // Usually 465 for SSL or 587 for TLS
define('SMTP_SECURE', 'ssl'); // 'ssl' or 'tls'

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

    ensureSchema($conn);
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

function columnExists($conn, $table, $column)
{
    $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : ['count' => 0];
    $stmt->close();

    return !empty($row['count']);
}

function tableExists($conn, $table)
{
    $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : ['count' => 0];
    $stmt->close();

    return !empty($row['count']);
}

function ensureSchema($conn)
{
    if (tableExists($conn, 'properties')) {
        $conn->query("CREATE TABLE IF NOT EXISTS property_bookings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            property_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            phone VARCHAR(30) NOT NULL,
            email VARCHAR(255) NOT NULL,
            visit_date DATE NOT NULL,
            visit_time TIME NOT NULL,
            message TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
            INDEX idx_property_id (property_id),
            INDEX idx_visit_date (visit_date),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    if (!columnExists($conn, 'properties', 'property_type')) {
        $conn->query("ALTER TABLE properties ADD COLUMN property_type ENUM('sell', 'rent') NOT NULL DEFAULT 'sell' AFTER admin_id");
    }

    if (!columnExists($conn, 'properties', 'category')) {
        $conn->query("ALTER TABLE properties ADD COLUMN category VARCHAR(50) NOT NULL DEFAULT 'Apartment' AFTER property_type");
    }

    if (!columnExists($conn, 'properties', 'property_status')) {
        $conn->query("ALTER TABLE properties ADD COLUMN property_status ENUM('available', 'sold', 'rented') NOT NULL DEFAULT 'available' AFTER category");
    }

    if (!columnExists($conn, 'properties', 'booking_enabled')) {
        $conn->query("ALTER TABLE properties ADD COLUMN booking_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER property_status");
    }
}

function generatePropertyTitle($propertyType, $category, $bedrooms = 0)
{
    $typeLabel = strtoupper(substr((string) $propertyType, 0, 1)) . strtolower(substr((string) $propertyType, 1));
    $categoryLabel = trim((string) $category);
    $parts = [$categoryLabel, $typeLabel];

    if (!empty($bedrooms)) {
        $parts[] = $bedrooms . ' BHK';
    }

    return implode(' ', $parts);
}

?>
