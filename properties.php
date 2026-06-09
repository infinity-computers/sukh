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

$property_type = $_GET['property_type'] ?? '';
$category = $_GET['category'] ?? '';
$min_price = $_GET['min_price'] ?? '';
$max_price = $_GET['max_price'] ?? '';
$available_only = !empty($_GET['available_only']);
$sort = $_GET['sort'] ?? 'latest';

$where = "WHERE status = 'active'";
$params = [];
$types = "";

if ($property_type !== '' && in_array($property_type, ['sell', 'rent'], true)) {
    $where .= " AND property_type = ?";
    $params[] = $property_type;
    $types .= "s";
}

if ($category !== '') {
    $where .= " AND category = ?";
    $params[] = $category;
    $types .= "s";
}

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

if ($available_only) {
    $where .= " AND property_status = 'available'";
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
$total_pages = max(1, (int) ceil($total_properties / $limit));

$sql = "SELECT * FROM properties $where $order_by LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$properties = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

foreach ($properties as &$property) {
    $stmt = $conn->prepare("SELECT * FROM property_images WHERE property_id = ? ORDER BY is_primary DESC, id ASC");
    $stmt->bind_param('i', $property['id']);
    $stmt->execute();
    $property['images'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
unset($property);

$categories = ['Apartment', 'House', 'Villa', 'Commercial', 'Plot', 'Other'];
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
        .filter-form {
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            padding: 1.5rem;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
            border: 1px solid #e5e7eb;
        }
        .filter-row {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .filter-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #334155;
            font-size: 0.95rem;
        }
        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 0.8rem 0.9rem;
            border: 1px solid #dbe3ec;
            border-radius: 12px;
            font-size: 0.98rem;
            background: #fff;
        }
        .filter-buttons {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        .btn-outline {
            background: transparent;
            border: 1px solid #cbd5e1;
            color: #0f172a;
        }
        .btn-outline:hover {
            background: #0f172a;
            color: #fff;
        }
        .results-count {
            color: #64748b;
            margin-bottom: 1.5rem;
        }
        .no-properties {
            text-align: center;
            padding: 3rem;
            background: #fff;
            border-radius: 18px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
        }
        .no-properties p {
            margin-bottom: 1rem;
            color: #64748b;
        }
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }
        .pagination .btn.active {
            background: #0f172a;
            color: #fff;
        }
        .property-card {
            position: relative;
            overflow: hidden;
            border-radius: 20px;
            background: #fff;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
        }
        .property-card img {
            height: 240px;
            object-fit: cover;
        }
        .property-card .info {
            padding: 1.2rem 1.25rem 1.35rem;
        }
        .property-card .price {
            font-size: 1.45rem;
            font-weight: 700;
            color: #c2410c;
            margin: 0.4rem 0;
        }
        .property-card .meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin: 0.75rem 0 1rem;
        }
        .pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: #eff6ff;
            color: #1d4ed8;
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .snippet {
            color: #475569;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-top: 0.4rem;
        }
        .actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }
        .actions .btn {
            flex: 1 1 160px;
            text-align: center;
        }
        @media (max-width: 768px) {
            .filter-row {
                grid-template-columns: 1fr;
            }
            .property-card img {
                height: 200px;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/components/navbar.php'; ?>

    <main>
        <section class="section">
            <div class="section-header fade-in">
                <h2>All Properties</h2>
                <div class="line"></div>
            </div>

            <div class="container fade-in" style="margin-bottom: 2rem;">
                <form method="GET" action="" class="filter-form">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label>Sell / Rent</label>
                            <select name="property_type">
                                <option value="">All</option>
                                <option value="sell" <?php echo $property_type === 'sell' ? 'selected' : ''; ?>>Sell</option>
                                <option value="rent" <?php echo $property_type === 'rent' ? 'selected' : ''; ?>>Rent</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Category</label>
                            <select name="category">
                                <option value="">All</option>
                                <?php foreach ($categories as $item): ?>
                                    <option value="<?php echo htmlspecialchars($item); ?>" <?php echo $category === $item ? 'selected' : ''; ?>><?php echo htmlspecialchars($item); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Min Price</label>
                            <input type="number" name="min_price" value="<?php echo htmlspecialchars($min_price); ?>" placeholder="Min Price">
                        </div>
                        <div class="filter-group">
                            <label>Max Price</label>
                            <input type="number" name="max_price" value="<?php echo htmlspecialchars($max_price); ?>" placeholder="Max Price">
                        </div>
                    </div>
                    <div class="filter-row" style="margin-bottom: 0;">
                        <div class="filter-group">
                            <label style="display:flex;align-items:center;gap:0.6rem;font-weight:600;">
                                <input type="checkbox" name="available_only" value="1" <?php echo $available_only ? 'checked' : ''; ?> style="width:auto;">
                                Available Properties Only
                            </label>
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

            <div class="container fade-in">
                <p class="results-count">Showing <?php echo count($properties); ?> of <?php echo $total_properties; ?> properties</p>
            </div>

            <?php if (!empty($properties)): ?>
                <div class="properties-grid container fade-in">
                    <?php foreach ($properties as $property): ?>
                        <div class="property-card">
                            <?php if (!empty($property['images'])): ?>
                                <?php $primary_image = null; ?>
                                <?php foreach ($property['images'] as $img): ?>
                                    <?php if (!empty($img['is_primary'])): ?>
                                        <?php $primary_image = $img; ?>
                                        <?php break; ?>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <?php if (!$primary_image): ?>
                                    <?php $primary_image = $property['images'][0]; ?>
                                <?php endif; ?>
                                <img src="<?php echo htmlspecialchars(ltrim($primary_image['image_url'], '/')); ?>" alt="Property image">
                            <?php else: ?>
                                <img src="images/no-image.jpg" alt="No Image">
                            <?php endif; ?>
                            <div class="info">
                                <div class="meta">
                                    <span class="pill"><?php echo htmlspecialchars(ucfirst($property['property_type'] ?? 'sell')); ?></span>
                                    <span class="pill"><?php echo htmlspecialchars($property['category'] ?? 'Apartment'); ?></span>
                                    <span class="pill"><?php echo htmlspecialchars(ucfirst($property['property_status'] ?? 'available')); ?></span>
                                </div>
                                <?php if ($property['price']): ?>
                                    <p class="price">₹<?php echo number_format($property['price'], 2); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($property['description'])): ?>
                                    <p class="snippet"><?php echo htmlspecialchars(substr(trim(strip_tags($property['description'])), 0, 140)); ?><?php echo strlen(trim(strip_tags($property['description']))) > 140 ? '...' : ''; ?></p>
                                <?php endif; ?>
                                <div class="actions">
                                    <a href="property.php?id=<?php echo $property['id']; ?>" class="btn">View Details</a>
                                </div>
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

            <?php if ($total_pages > 1): ?>
                <div class="pagination container fade-in">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&property_type=<?php echo urlencode($property_type); ?>&category=<?php echo urlencode($category); ?>&min_price=<?php echo urlencode($min_price); ?>&max_price=<?php echo urlencode($max_price); ?>&available_only=<?php echo $available_only ? '1' : ''; ?>&sort=<?php echo urlencode($sort); ?>" class="btn">Previous</a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&property_type=<?php echo urlencode($property_type); ?>&category=<?php echo urlencode($category); ?>&min_price=<?php echo urlencode($min_price); ?>&max_price=<?php echo urlencode($max_price); ?>&available_only=<?php echo $available_only ? '1' : ''; ?>&sort=<?php echo urlencode($sort); ?>" class="btn <?php echo $page == $i ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&property_type=<?php echo urlencode($property_type); ?>&category=<?php echo urlencode($category); ?>&min_price=<?php echo urlencode($min_price); ?>&max_price=<?php echo urlencode($max_price); ?>&available_only=<?php echo $available_only ? '1' : ''; ?>&sort=<?php echo urlencode($sort); ?>" class="btn">Next</a>
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