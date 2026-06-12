-- Create admins table
CREATE TABLE IF NOT EXISTS admins (
  id INT PRIMARY KEY AUTO_INCREMENT,
  email VARCHAR(255) UNIQUE NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create properties table
CREATE TABLE IF NOT EXISTS properties (
  id INT PRIMARY KEY AUTO_INCREMENT,
  admin_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  description LONGTEXT,
  address VARCHAR(255) NOT NULL,
  price DECIMAL(12, 2),
  security_deposit DECIMAL(12, 2),
  maintenance_charges DECIMAL(12, 2),
  available_from DATE,
  furnishing_type ENUM('Furnished', 'Semi Furnished', 'Unfurnished') NOT NULL DEFAULT 'Unfurnished',
  bedrooms INT,
  bathrooms INT,
  balconies INT DEFAULT 0,
  floor_number INT DEFAULT 0,
  total_floors INT DEFAULT 0,
  area_sqft INT,
  carpet_area DECIMAL(12, 2),
  parking ENUM('Yes', 'No') NOT NULL DEFAULT 'No',
  water_supply ENUM('24 Hours', 'Limited') NOT NULL DEFAULT '24 Hours',
  electricity_backup ENUM('Yes', 'No') NOT NULL DEFAULT 'No',
  facing_direction ENUM('East', 'West', 'North', 'South') NOT NULL DEFAULT 'East',
  property_type ENUM('sell', 'rent') NOT NULL DEFAULT 'sell',
  category VARCHAR(50) NOT NULL DEFAULT 'Apartment',
  property_status ENUM('available', 'sold', 'rented') NOT NULL DEFAULT 'available',
  booking_enabled TINYINT(1) NOT NULL DEFAULT 1,
  amenities LONGTEXT,
  tenant_preferred ENUM('Family', 'Bachelor', 'Students', 'Any') NOT NULL DEFAULT 'Any',
  lease_duration VARCHAR(100),
  available_immediately ENUM('Yes', 'No') NOT NULL DEFAULT 'No',
  bills_included ENUM('Yes', 'No') NOT NULL DEFAULT 'No',
  pets_allowed ENUM('Yes', 'No') NOT NULL DEFAULT 'No',
  washroom_available ENUM('Yes', 'No') NOT NULL DEFAULT 'No',
  pantry_available ENUM('Yes', 'No') NOT NULL DEFAULT 'No',
  cabin_count INT DEFAULT 0,
  parking_spaces INT DEFAULT 0,
  status ENUM('active', 'inactive') DEFAULT 'active',
  is_featured BOOLEAN DEFAULT FALSE,
  primary_image_id INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE,
  INDEX idx_admin (admin_id),
  INDEX idx_status (status),
  INDEX idx_property_type (property_type),
  INDEX idx_category (category),
  INDEX idx_property_status (property_status),
  INDEX idx_booking_enabled (booking_enabled),
  INDEX idx_featured (is_featured)
);

-- Create property_images table
CREATE TABLE IF NOT EXISTS property_images (
  id INT PRIMARY KEY AUTO_INCREMENT,
  property_id INT NOT NULL,
  image_url VARCHAR(255) NOT NULL,
  is_primary BOOLEAN DEFAULT FALSE,
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
  INDEX idx_property (property_id)
);

-- Create property_bookings table
CREATE TABLE IF NOT EXISTS property_bookings (
  id INT PRIMARY KEY AUTO_INCREMENT,
  property_id INT NOT NULL,
  name VARCHAR(255) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  email VARCHAR(255) DEFAULT NULL,
  visit_date DATE NOT NULL,
  visit_time TIME NOT NULL,
  message TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
  INDEX idx_booking_property (property_id),
  INDEX idx_booking_date (visit_date),
  INDEX idx_booking_created_at (created_at)
);

-- Create otp_verification table
CREATE TABLE IF NOT EXISTS otp_verification (
  id INT PRIMARY KEY AUTO_INCREMENT,
  email VARCHAR(255) NOT NULL,
  otp VARCHAR(6) NOT NULL,
  expires_at TIMESTAMP,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_email (email)
);

-- Insert default admin
INSERT IGNORE INTO admins (email) VALUES ('rathorjatin70@gmail.com');
INSERT IGNORE INTO admins (email) VALUES ('pacifier2204@gmail.com');
INSERT IGNORE INTO admins (email) VALUES ('bharuch@sukhdham.in');
