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
            email VARCHAR(255) DEFAULT NULL,
            visit_date DATE NOT NULL,
            visit_time TIME NOT NULL,
            message TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
            INDEX idx_property_id (property_id),
            INDEX idx_visit_date (visit_date),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        if (columnExists($conn, 'property_bookings', 'email')) {
            $conn->query("ALTER TABLE property_bookings MODIFY email VARCHAR(255) DEFAULT NULL");
        }

        if (!columnExists($conn, 'properties', 'security_deposit')) {
            $conn->query("ALTER TABLE properties ADD COLUMN security_deposit DECIMAL(12,2) NULL AFTER price");
        }

        if (!columnExists($conn, 'properties', 'maintenance_charges')) {
            $conn->query("ALTER TABLE properties ADD COLUMN maintenance_charges DECIMAL(12,2) NULL AFTER security_deposit");
        }

        if (!columnExists($conn, 'properties', 'available_from')) {
            $conn->query("ALTER TABLE properties ADD COLUMN available_from DATE NULL AFTER maintenance_charges");
        }

        if (!columnExists($conn, 'properties', 'furnishing_type')) {
            $conn->query("ALTER TABLE properties ADD COLUMN furnishing_type ENUM('Furnished', 'Semi Furnished', 'Unfurnished') NOT NULL DEFAULT 'Unfurnished' AFTER available_from");
        }

        if (!columnExists($conn, 'properties', 'balconies')) {
            $conn->query("ALTER TABLE properties ADD COLUMN balconies INT NOT NULL DEFAULT 0 AFTER bathrooms");
        }

        if (!columnExists($conn, 'properties', 'floor_number')) {
            $conn->query("ALTER TABLE properties ADD COLUMN floor_number INT NOT NULL DEFAULT 0 AFTER balconies");
        }

        if (!columnExists($conn, 'properties', 'total_floors')) {
            $conn->query("ALTER TABLE properties ADD COLUMN total_floors INT NOT NULL DEFAULT 0 AFTER floor_number");
        }

        if (!columnExists($conn, 'properties', 'carpet_area')) {
            $conn->query("ALTER TABLE properties ADD COLUMN carpet_area DECIMAL(12,2) NULL AFTER area_sqft");
        }

        if (!columnExists($conn, 'properties', 'parking')) {
            $conn->query("ALTER TABLE properties ADD COLUMN parking ENUM('Yes', 'No') NOT NULL DEFAULT 'No' AFTER carpet_area");
        }

        if (!columnExists($conn, 'properties', 'water_supply')) {
            $conn->query("ALTER TABLE properties ADD COLUMN water_supply ENUM('24 Hours', 'Limited') NOT NULL DEFAULT '24 Hours' AFTER parking");
        }

        if (!columnExists($conn, 'properties', 'electricity_backup')) {
            $conn->query("ALTER TABLE properties ADD COLUMN electricity_backup ENUM('Yes', 'No') NOT NULL DEFAULT 'No' AFTER water_supply");
        }

        if (!columnExists($conn, 'properties', 'facing_direction')) {
            $conn->query("ALTER TABLE properties ADD COLUMN facing_direction ENUM('East', 'West', 'North', 'South') NOT NULL DEFAULT 'East' AFTER electricity_backup");
        }

        if (!columnExists($conn, 'properties', 'amenities')) {
            $conn->query("ALTER TABLE properties ADD COLUMN amenities LONGTEXT NULL AFTER facing_direction");
        }

        if (!columnExists($conn, 'properties', 'tenant_preferred')) {
            $conn->query("ALTER TABLE properties ADD COLUMN tenant_preferred ENUM('Family', 'Bachelor', 'Students', 'Any') NOT NULL DEFAULT 'Any' AFTER amenities");
        }

        if (!columnExists($conn, 'properties', 'lease_duration')) {
            $conn->query("ALTER TABLE properties ADD COLUMN lease_duration VARCHAR(100) NULL AFTER tenant_preferred");
        }

        if (!columnExists($conn, 'properties', 'available_immediately')) {
            $conn->query("ALTER TABLE properties ADD COLUMN available_immediately ENUM('Yes', 'No') NOT NULL DEFAULT 'No' AFTER lease_duration");
        }

        if (!columnExists($conn, 'properties', 'bills_included')) {
            $conn->query("ALTER TABLE properties ADD COLUMN bills_included ENUM('Yes', 'No') NOT NULL DEFAULT 'No' AFTER available_immediately");
        }

        if (!columnExists($conn, 'properties', 'pets_allowed')) {
            $conn->query("ALTER TABLE properties ADD COLUMN pets_allowed ENUM('Yes', 'No') NOT NULL DEFAULT 'No' AFTER bills_included");
        }

        if (!columnExists($conn, 'properties', 'washroom_available')) {
            $conn->query("ALTER TABLE properties ADD COLUMN washroom_available ENUM('Yes', 'No') NOT NULL DEFAULT 'No' AFTER pets_allowed");
        }

        if (!columnExists($conn, 'properties', 'pantry_available')) {
            $conn->query("ALTER TABLE properties ADD COLUMN pantry_available ENUM('Yes', 'No') NOT NULL DEFAULT 'No' AFTER washroom_available");
        }

        if (!columnExists($conn, 'properties', 'cabin_count')) {
            $conn->query("ALTER TABLE properties ADD COLUMN cabin_count INT NOT NULL DEFAULT 0 AFTER pantry_available");
        }

        if (!columnExists($conn, 'properties', 'parking_spaces')) {
            $conn->query("ALTER TABLE properties ADD COLUMN parking_spaces INT NOT NULL DEFAULT 0 AFTER cabin_count");
        }
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

    $conn->query("INSERT IGNORE INTO admins (email) VALUES ('bharuch@sukhdham.in')");

    $conn->query("UPDATE properties SET status = 'active' WHERE status IS NULL OR status = ''");
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
