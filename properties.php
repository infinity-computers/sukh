<?php
session_start();

require_once __DIR__ . '/config/database.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

$min_price = $_GET['min_price'] ?? '';
$max_price = $_GET['max_price'] ?? '';
$location = $_GET['location'] ?? '';
$bedrooms = $_GET['bedrooms'] ?? '';
$sort = $_GET['sort'] ?? 'latest';

$where = "WHERE status = 'active'";
$params = [];
$types = "";

if ($min_price !== '') {
    $where .= " AND price >= ?";
    $params[] = floatval($min_price);
    $types .= "d";
}
if ($max_price !== '') {
    $where .= " AND price <= ?";
    $params[] = floatval($max_price);
    $types .= "d";
}
if ($location !== '') {
    $where .= " AND address LIKE ?";
    $params[] = "%" . $conn->real_escape_string($location) . "%";
    $types .= "s";
}
if ($bedrooms !== '') {
    $where .= " AND bedrooms = ?";
    $params[] = intval($bedrooms);
    $types .= "i";
}

switch ($sort) {
    case 'price_asc':
        $order_by = "ORDER BY price ASC";
        break;
    case 'price_desc':
        $order_by = "ORDER BY price DESC";
        break;
    case 'oldest':
        $order_by = "ORDER BY created_at ASC";
        break;
    default:
        $order_by = "ORDER BY created_at DESC";
}

$count_sql = "SELECT COUNT(*) as count FROM properties $where";
$stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_properties = $stmt->get_result()->fetch_assoc()['count'];
$total_pages = ceil($total_properties / $limit);

$sql = "SELECT * FROM properties $where $order_by LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$properties = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

foreach ($properties as &$property) {
    $stmt = $conn->prepare("SELECT * FROM property_images WHERE property_id = ?");
    $stmt->bind_param('i', $property['id']);
    $stmt->execute();
    $property['images'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
unset($property);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Properties - Sukhdham Estate Agency</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .filter-form { background: #f8f8f8; padding: 1.5rem; border-radius: 8px; }
        .filter-row { display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: 1rem; }
        .filter-group { flex: 1; min-width: 150px; }
        .filter-group label { display: block; font-weight: 500; margin-bottom: 0.5rem; color: #333; }
        .filter-group input, .filter-group select { width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem; }
        .filter-buttons { display: flex; gap: 1rem; }
        .btn-outline { background: transparent; border: 2px solid #333; color: #333; }
        .btn-outline:hover { background: #333; color: #fff; }
        .results-count { color: #666; margin-bottom: 1.5rem; }
        .no-properties { text-align: center; padding: 3rem; background: #f8f8f8; border-radius: 8px; }
        .no-properties p { margin-bottom: 1rem; color: #666; }
        .pagination { display: flex; justify-content: center; gap: 0.5rem; margin-top: 2rem; flex-wrap: wrap; }
        .pagination .btn.active { background: #333; color: #fff; }
        .property-card .price { font-size: 1.5rem; font-weight: 700; color: #e63946; margin: 0.5rem 0; }
        @media (max-width: 768px) { .filter-group { min-width: 100%; } }
    </style>
</head>
<body>
    <?php include __DIR__ . '/components/navbar.php'; ?>
    
    <main>
        <!-- Properties Section -->
        <section class="section">
            <div class="section-header fade-in">
                <h2>All Properties</h2>
                <div class="line"></div>
            </div>
            
            <!-- Filters -->
            <div class="container fade-in" style="margin-bottom: 2rem;">
                <form method="GET" action="" class="filter-form">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label>Min Price</label>
                            <input type="number" name="min_price" value="<?php echo htmlspecialchars($min_price); ?>" placeholder="Min Price">
                        </div>
                        <div class="filter-group">
                            <label>Max Price</label>
                            <input type="number" name="max_price" value="<?php echo htmlspecialchars($max_price); ?>" placeholder="Max Price">
                        </div>
                        <div class="filter-group">
                            <label>Location</label>
                            <input type="text" name="location" value="<?php echo htmlspecialchars($location); ?>" placeholder="Search location">
                        </div>
                        <div class="filter-group">
                            <label>Bedrooms</label>
                            <select name="bedrooms">
                                <option value="">Any</option>
                                <option value="1" <?php echo $bedrooms == '1' ? 'selected' : ''; ?>>1</option>
                                <option value="2" <?php echo $bedrooms == '2' ? 'selected' : ''; ?>>2</option>
                                <option value="3" <?php echo $bedrooms == '3' ? 'selected' : ''; ?>>3</option>
                                <option value="4" <?php echo $bedrooms == '4' ? 'selected' : ''; ?>>4+</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Sort By</label>
                            <select name="sort">
                                <option value="latest" <?php echo $sort == 'latest' ? 'selected' : ''; ?>>Latest</option>
                                <option value="oldest" <?php echo $sort == 'oldest' ? 'selected' : ''; ?>>Oldest</option>
                                <option value="price_asc" <?php echo $sort == 'price_asc' ? 'selected' : ''; ?>>Price: Low to High</option>
                                <option value="price_desc" <?php echo $sort == 'price_desc' ? 'selected' : ''; ?>>Price: High to Low</option>
                            </select>
                        </div>
                    </div>
                    <div class="filter-buttons">
                        <button type="submit" class="btn">Apply Filters</button>
                        <a href="properties.php" class="btn btn-outline">Clear</a>
                    </div>
                </form>
            </div>
            
            <!-- Results Count -->
            <div class="container fade-in">
                <p class="results-count">Showing <?php echo count($properties); ?> of <?php echo $total_properties; ?> properties</p>
            </div>
            
            <!-- Properties Grid -->
            <?php if (!empty($properties)): ?>
                <div class="properties-grid container fade-in">
                    <?php foreach ($properties as $property): ?>
                        <div class="property-card">
                            <?php if (!empty($property['images'])): ?>
                                <?php $primary_image = null; ?>
                                <?php foreach ($property['images'] as $img): ?>
                                    <?php if ($img['is_primary']): ?>
                                        <?php $primary_image = $img; ?>
                                        <?php break; ?>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <?php if (!$primary_image): ?>
                                    <?php $primary_image = $property['images'][0]; ?>
                                <?php endif; ?>
                                <img src="<?php echo htmlspecialchars(ltrim($primary_image['image_url'], '/')); ?>" alt="<?php echo htmlspecialchars($property['title']); ?>">
                            <?php else: ?>
                                <img src="images/no-image.jpg" alt="No Image">
                            <?php endif; ?>
                            <div class="info">
                                <h3><?php echo htmlspecialchars($property['title']); ?></h3>
                                <p class="location">📍 <?php echo htmlspecialchars($property['address']); ?></p>
                                <?php if ($property['price']): ?>
                                    <p class="price">₹<?php echo number_format($property['price'], 2); ?></p>
                                <?php endif; ?>
                                <p class="details">
                                    <?php if ($property['bedrooms']): ?><?php echo $property['bedrooms']; ?> BHK <?php endif; ?>
                                    <?php if ($property['area_sqft']): ?>| <?php echo number_format($property['area_sqft']); ?> sq.ft <?php endif; ?>
                                </p>
                                <a href="property.php?id=<?php echo $property['id']; ?>" class="btn">View Details</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="container fade-in">
                    <div class="no-properties">
                        <p>No properties found matching your criteria.</p>
                        <a href="properties.php" class="btn">Clear Filters</a>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination container fade-in">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&min_price=<?php echo urlencode($min_price); ?>&max_price=<?php echo urlencode($max_price); ?>&location=<?php echo urlencode($location); ?>&bedrooms=<?php echo urlencode($bedrooms); ?>&sort=<?php echo urlencode($sort); ?>" class="btn">Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&min_price=<?php echo urlencode($min_price); ?>&max_price=<?php echo urlencode($max_price); ?>&location=<?php echo urlencode($location); ?>&bedrooms=<?php echo urlencode($bedrooms); ?>&sort=<?php echo urlencode($sort); ?>" class="btn <?php echo $page == $i ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&min_price=<?php echo urlencode($min_price); ?>&max_price=<?php echo urlencode($max_price); ?>&location=<?php echo urlencode($location); ?>&bedrooms=<?php echo urlencode($bedrooms); ?>&sort=<?php echo urlencode($sort); ?>" class="btn">Next</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
    
    <?php include __DIR__ . '/components/footer.php'; ?>
    <script src="script.js"></script>
</body>
</html>

<?php $conn->close(); ?>
