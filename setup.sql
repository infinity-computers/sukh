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
  bedrooms INT,
  bathrooms INT,
  area_sqft INT,
  status ENUM('active', 'inactive') DEFAULT 'active',
  is_featured BOOLEAN DEFAULT FALSE,
  primary_image_id INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE,
  INDEX idx_admin (admin_id),
  INDEX idx_status (status),
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
