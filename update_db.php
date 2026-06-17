<?php
require_once __DIR__ . '/config/database.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Add food_preference
$conn->query("ALTER TABLE properties ADD COLUMN food_preference ENUM('Veg', 'Non Veg', 'Both') NOT NULL DEFAULT 'Both' AFTER facing_direction");
if ($conn->error) {
    echo "Error adding food_preference: " . $conn->error . "\n";
} else {
    echo "Added food_preference column.\n";
}

// Add admin
$conn->query("INSERT IGNORE INTO admins (email) VALUES ('sukhdham.in@gmail.com')");
if ($conn->error) {
    echo "Error adding admin: " . $conn->error . "\n";
} else {
    echo "Added admin.\n";
}

echo "Done.\n";
