<?php
session_start();

require_once __DIR__ . '/config/database.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

$property_id = intval($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM properties WHERE id = ? AND status = 'active'");
$stmt->bind_param('i', $property_id);
$stmt->execute();
$property = $stmt->get_result()->fetch_assoc();

if (!$property) {
    header('Location: properties.php');
    exit;
}

$stmt = $conn->prepare("SELECT * FROM property_images WHERE property_id = ?");
$stmt->bind_param('i', $property_id);
$stmt->execute();
$images = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($property['title']); ?> - Sukhdham Estate Agency</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .property-detail { padding: 2rem 0; }
        .back-link { display: inline-block; margin-bottom: 1.5rem; color: #333; font-weight: 500; }
        .back-link:hover { color: #e63946; }
        .gallery-main { width: 100%; height: 400px; object-fit: cover; border-radius: 8px; margin-bottom: 1rem; }
        .gallery-thumbs { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .gallery-thumb { width: 100px; height: 70px; object-fit: cover; border-radius: 4px; cursor: pointer; border: 2px solid transparent; transition: all 0.3s; }
        .gallery-thumb:hover, .gallery-thumb.active { border-color: #e63946; }
        .property-info-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-top: 2rem; }
        .property-info h1 { font-size: 2rem; margin-bottom: 0.5rem; }
        .property-info .location { color: #666; font-size: 1.1rem; margin-bottom: 1rem; }
        .property-info .price { font-size: 2rem; font-weight: 700; color: #e63946; margin-bottom: 1.5rem; }
        .property-features { display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
        .feature-item { background: #f8f8f8; padding: 1rem 1.5rem; border-radius: 8px; text-align: center; min-width: 100px; }
        .feature-item .icon { font-size: 1.5rem; display: block; margin-bottom: 0.5rem; }
        .property-desc { margin-top: 1.5rem; }
        .property-desc h3 { font-size: 1.3rem; margin-bottom: 1rem; }
        .property-desc p { color: #555; line-height: 1.6; }
        .contact-card { background: #f8f8f8; padding: 1.5rem; border-radius: 8px; height: fit-content; }
        .contact-card h3 { font-size: 1.2rem; margin-bottom: 1rem; }
        .contact-card p { margin-bottom: 0.5rem; color: #555; }
        @media (max-width: 768px) { .property-info-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <?php include __DIR__ . '/components/navbar.php'; ?>
    
    <main>
        <div class="container property-detail">
            <a href="properties.php" class="back-link">← Back to Properties</a>
            
            <!-- Image Gallery -->
            <?php if (!empty($images)): ?>
                <?php $primary_image = null; ?>
                <?php foreach ($images as $img): ?>
                    <?php if ($img['is_primary']): ?>
                        <?php $primary_image = $img; ?>
                        <?php break; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if (!$primary_image): ?>
                    <?php $primary_image = $images[0]; ?>
                <?php endif; ?>
                
                <img id="mainImage" src="<?php echo htmlspecialchars(ltrim($primary_image['image_url'], '/')); ?>" 
                     alt="<?php echo htmlspecialchars($property['title']); ?>" 
                     class="gallery-main">
                
                <div class="gallery-thumbs">
                    <?php foreach ($images as $index => $img): ?>
                        <img src="<?php echo htmlspecialchars(ltrim($img['image_url'], '/')); ?>" 
                             alt="Property image <?php echo $index + 1; ?>" 
                             class="gallery-thumb <?php echo $img['is_primary'] ? 'active' : ''; ?>"
                             onclick="changeImage('<?php echo htmlspecialchars(ltrim($img['image_url'], '/')); ?>', this)">
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <img src="images/no-image.jpg" alt="No Image" class="gallery-main">
            <?php endif; ?>
            
            <!-- Property Details -->
            <div class="property-info-grid">
                <div class="property-info">
                    <h1><?php echo htmlspecialchars($property['title']); ?></h1>
                    <p class="location">📍 <?php echo htmlspecialchars($property['address']); ?></p>
                    
                    <?php if ($property['price']): ?>
                        <p class="price">₹<?php echo number_format($property['price'], 2); ?></p>
                    <?php endif; ?>
                    
                    <div class="property-features">
                        <?php if ($property['bedrooms']): ?>
                            <div class="feature-item">
                                <span class="icon">🛏️</span>
                                <strong><?php echo $property['bedrooms']; ?></strong><br>
                                <span>Bedrooms</span>
                            </div>
                        <?php endif; ?>
                        <?php if ($property['bathrooms']): ?>
                            <div class="feature-item">
                                <span class="icon">🚿</span>
                                <strong><?php echo $property['bathrooms']; ?></strong><br>
                                <span>Bathrooms</span>
                            </div>
                        <?php endif; ?>
                        <?php if ($property['area_sqft']): ?>
                            <div class="feature-item">
                                <span class="icon">📐</span>
                                <strong><?php echo number_format($property['area_sqft']); ?></strong><br>
                                <span>Sq.ft</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($property['description']): ?>
                        <div class="property-desc">
                            <h3>Description</h3>
                            <p><?php echo htmlspecialchars($property['description']); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="contact-card">
                    <h3>Contact Us</h3>
                    <p>Interested in this property? Get in touch with us.</p>
                    <p>📧 bharuch@sukhdham.in</p>
                    <p>📞 +91 9376739237</p>
                    <a href="https://wa.me/919376739237" class="btn" style="margin-top: 1rem; display: inline-block;">WhatsApp Us</a>
                </div>
            </div>
        </div>
    </main>
    
    <script>
        function changeImage(src, thumb) {
            document.getElementById('mainImage').src = src;
            document.querySelectorAll('.gallery-thumb').forEach(t => t.classList.remove('active'));
            thumb.classList.add('active');
        }
    </script>
    
    <?php include __DIR__ . '/components/footer.php'; ?>
    <script src="script.js"></script>
</body>
</html>

<?php $conn->close(); ?>