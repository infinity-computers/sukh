<?php
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/utils/PropertyHelpers.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

$property_type = $_GET['property_type'] ?? '';
$category = $_GET['category'] ?? '';
$furnishing_type = $_GET['furnishing_type'] ?? '';
$bedrooms = $_GET['bedrooms'] ?? '';
$min_price = $_GET['min_price'] ?? '';
$max_price = $_GET['max_price'] ?? '';
$available_only = !empty($_GET['available_only']);
$amenities_filter = $_GET['amenities'] ?? [];
$sort = $_GET['sort'] ?? 'latest';

if (!is_array($amenities_filter)) {
    $amenities_filter = [];
}

$whereParts = ["status = 'active'"];
$params = [];
$types = '';

if (in_array($property_type, sukhdham_property_type_options(), true)) {
    $whereParts[] = 'property_type = ?';
    $params[] = $property_type;
    $types .= 's';
}

if ($category !== '' && in_array($category, sukhdham_property_category_options(), true)) {
    $whereParts[] = 'category = ?';
    $params[] = $category;
    $types .= 's';
}

if ($furnishing_type !== '' && in_array($furnishing_type, sukhdham_furnishing_options(), true)) {
    $whereParts[] = 'furnishing_type = ?';
    $params[] = $furnishing_type;
    $types .= 's';
}

if ($bedrooms !== '' && is_numeric($bedrooms)) {
    $whereParts[] = 'bedrooms >= ?';
    $params[] = (int) $bedrooms;
    $types .= 'i';
}

if ($min_price !== '' && is_numeric($min_price)) {
    $whereParts[] = 'price >= ?';
    $params[] = (float) $min_price;
    $types .= 'd';
}

if ($max_price !== '' && is_numeric($max_price)) {
    $whereParts[] = 'price <= ?';
    $params[] = (float) $max_price;
    $types .= 'd';
}

if ($available_only) {
    $whereParts[] = "property_status = 'available'";
}

$amenityClauses = [];
foreach ($amenities_filter as $amenity) {
    if (in_array($amenity, sukhdham_amenity_options(), true)) {
        $amenityClauses[] = 'amenities LIKE ?';
        $params[] = '%' . $amenity . '%';
        $types .= 's';
    }
}

if (!empty($amenityClauses)) {
    $whereParts[] = '(' . implode(' OR ', $amenityClauses) . ')';
}

$where = 'WHERE ' . implode(' AND ', $whereParts);

switch ($sort) {
    case 'price_asc':
        $order_by = 'ORDER BY price IS NULL, price ASC, created_at DESC';
        break;
    case 'price_desc':
        $order_by = 'ORDER BY price IS NULL, price DESC, created_at DESC';
        break;
    case 'oldest':
        $order_by = 'ORDER BY created_at ASC';
        break;
    default:
        $order_by = 'ORDER BY created_at DESC';
}

$count_sql = "SELECT COUNT(*) AS count FROM properties $where";
$stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    sukhdham_bind_stmt_values($stmt, $types, $params);
}
$stmt->execute();
$total_properties = (int) ($stmt->get_result()->fetch_assoc()['count'] ?? 0);
$stmt->close();

$total_pages = max(1, (int) ceil($total_properties / $limit));

$sql = "SELECT * FROM properties $where $order_by LIMIT ? OFFSET ?";
$queryParams = $params;
$queryParams[] = $limit;
$queryParams[] = $offset;
$queryTypes = $types . 'ii';

$stmt = $conn->prepare($sql);
sukhdham_bind_stmt_values($stmt, $queryTypes, $queryParams);
$stmt->execute();
$properties = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

foreach ($properties as &$property) {
    $stmt = $conn->prepare('SELECT * FROM property_images WHERE property_id = ? ORDER BY is_primary DESC, id ASC');
    $stmt->bind_param('i', $property['id']);
    $stmt->execute();
    $property['images'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
unset($property);

$selectedAmenities = $amenities_filter;
$categories = sukhdham_property_category_options();
$queryBase = [
    'property_type' => $property_type,
    'category' => $category,
    'furnishing_type' => $furnishing_type,
    'bedrooms' => $bedrooms,
    'min_price' => $min_price,
    'max_price' => $max_price,
    'available_only' => $available_only ? '1' : '',
    'sort' => $sort,
    'amenities' => $selectedAmenities,
];

$pageQueryBase = $queryBase;
unset($pageQueryBase['page']);

function property_thumb($property)
{
    if (!empty($property['images'])) {
        foreach ($property['images'] as $img) {
            if (!empty($img['is_primary'])) {
                return ltrim($img['image_url'], '/');
            }
        }
        return ltrim($property['images'][0]['image_url'], '/');
    }

    return 'images/no-image.jpg';
}

function query_string_with_page(array $base, $page)
{
    $base['page'] = $page;
    return http_build_query($base);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Properties - Sukhdham Estate Agency</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .listing-hero {
            position: relative;
            padding: 4rem 0 2rem;
            background: linear-gradient(135deg, #fff7ed 0%, #ffffff 45%, #eff6ff 100%);
            overflow: hidden;
        }
        .listing-hero::before,
        .listing-hero::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            filter: blur(20px);
            opacity: 0.45;
        }
        .listing-hero::before {
            width: 220px;
            height: 220px;
            background: rgba(251, 146, 60, 0.18);
            top: -80px;
            right: -30px;
        }
        .listing-hero::after {
            width: 280px;
            height: 280px;
            background: rgba(59, 130, 246, 0.12);
            bottom: -140px;
            left: -80px;
        }
        .listing-shell {
            position: relative;
            z-index: 1;
        }
        .filter-card,
        .property-card-modern,
        .results-card,
        .empty-state {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(148, 163, 184, 0.22);
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.08);
            border-radius: 24px;
        }
        .filter-card {
            padding: 1.25rem;
        }
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
        }
        .filter-group label {
            display: block;
            font-weight: 700;
            color: #334155;
            margin-bottom: 0.45rem;
            font-size: 0.92rem;
        }
        .filter-group input,
        .filter-group select {
            width: 100%;
            border: 1px solid #d8e1ea;
            border-radius: 14px;
            padding: 0.8rem 0.9rem;
            background: #fff;
            font-size: 0.96rem;
        }
        .amenity-panel {
            margin-top: 1rem;
            border-top: 1px solid #e2e8f0;
            padding-top: 1rem;
        }
        .amenity-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.5rem 0.75rem;
            margin-top: 0.75rem;
        }
        .amenity-chip {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            border: 1px solid #d8e1ea;
            border-radius: 999px;
            padding: 0.45rem 0.7rem;
            background: #fff;
            font-size: 0.88rem;
            color: #334155;
        }
        .results-card {
            padding: 0.85rem 1rem;
            margin: 1rem 0 1.25rem;
            color: #475569;
        }
        .properties-grid-modern {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1.25rem;
            align-items: stretch;
        }
        .property-card-modern {
            overflow: hidden;
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        .property-card-modern:hover {
            transform: translateY(-4px);
            box-shadow: 0 30px 70px rgba(15, 23, 42, 0.12);
        }
        .property-media {
            position: relative;
            aspect-ratio: 16 / 10;
            overflow: hidden;
        }
        .property-media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .property-body {
            padding: 1rem 1rem 1.1rem;
            display: flex;
            flex-direction: column;
            flex: 1;
        }
        .badge-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
            margin-bottom: 0.8rem;
        }
        .pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            border-radius: 999px;
            padding: 0.35rem 0.7rem;
            font-size: 0.8rem;
            font-weight: 700;
            background: #eff6ff;
            color: #1d4ed8;
        }
        .price {
            font-size: 1.4rem;
            font-weight: 800;
            color: #c2410c;
            margin: 0.25rem 0 0.65rem;
        }
        .summary {
            color: #475569;
            font-size: 0.95rem;
            line-height: 1.65;
            min-height: 3.2rem;
            flex: 1;
        }
        .meta-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.6rem;
            margin: 0.9rem 0;
        }
        .meta-item {
            padding: 0.65rem 0.75rem;
            border-radius: 14px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            font-size: 0.86rem;
            color: #475569;
        }
        .meta-item strong {
            display: block;
            color: #0f172a;
            margin-bottom: 0.15rem;
        }
        .actions {
            display: flex;
            gap: 0.75rem;
            margin-top: auto;
        }
        .actions .btn {
            flex: 1;
            text-align: center;
        }
        .empty-state {
            padding: 3rem;
            text-align: center;
        }
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: 2rem;
        }
        .pagination .btn.active {
            background: #0f172a;
            color: #fff;
        }
        @media (max-width: 1100px) {
            .filter-grid,
            .amenity-grid,
            .properties-grid-modern {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (max-width: 640px) {
            .listing-hero {
                padding-top: 2.75rem;
            }
            .filter-grid,
            .amenity-grid,
            .properties-grid-modern,
            .meta-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/components/navbar.php'; ?>

    <main>
        <section class="listing-hero">
            <div class="container listing-shell">
                <div class="section-header fade-in">
                    <h2>Find the Right Property</h2>
                    <div class="line"></div>
                    <p style="max-width:760px;margin:0.85rem auto 0;color:#475569;">Search homes, rentals, and commercial spaces with richer filters for amenities, furnishing, price, and availability.</p>
                </div>

                <form method="GET" action="" class="filter-card fade-in">
                    <div class="filter-grid">
                        <div class="filter-group">
                            <label>Sell / Rent</label>
                            <select name="property_type">
                                <option value="">All</option>
                                <option value="sell" <?php echo $property_type === 'sell' ? 'selected' : ''; ?>>Sell</option>
                                <option value="rent" <?php echo $property_type === 'rent' ? 'selected' : ''; ?>>Rent</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Property Category</label>
                            <select name="category">
                                <option value="">All</option>
                                <?php foreach ($categories as $item): ?>
                                    <option value="<?php echo htmlspecialchars($item); ?>" <?php echo $category === $item ? 'selected' : ''; ?>><?php echo htmlspecialchars($item); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Furnishing Type</label>
                            <select name="furnishing_type">
                                <option value="">All</option>
                                <?php foreach (sukhdham_furnishing_options() as $item): ?>
                                    <option value="<?php echo htmlspecialchars($item); ?>" <?php echo $furnishing_type === $item ? 'selected' : ''; ?>><?php echo htmlspecialchars($item); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Bedrooms</label>
                            <select name="bedrooms">
                                <option value="">Any</option>
                                <?php for ($i = 1; $i <= 6; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo (string) $bedrooms === (string) $i ? 'selected' : ''; ?>><?php echo $i; ?>+</option>
                                <?php endfor; ?>
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
                        <div class="filter-group">
                            <label>Sort By</label>
                            <select name="sort">
                                <option value="latest" <?php echo $sort === 'latest' ? 'selected' : ''; ?>>Latest</option>
                                <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest</option>
                                <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Price: Low to High</option>
                                <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Price: High to Low</option>
                            </select>
                        </div>
                        <div class="filter-group" style="display:flex;align-items:end;">
                            <label style="display:flex;align-items:center;gap:0.6rem;font-weight:700;">
                                <input type="checkbox" name="available_only" value="1" <?php echo $available_only ? 'checked' : ''; ?> style="width:auto;">
                                Available Properties Only
                            </label>
                        </div>
                    </div>

                    <div class="amenity-panel">
                        <label style="display:block;font-weight:700;color:#334155;margin-bottom:0.35rem;">Amenities</label>
                        <div class="amenity-grid">
                            <?php foreach (sukhdham_amenity_options() as $amenity): ?>
                                <label class="amenity-chip">
                                    <input type="checkbox" name="amenities[]" value="<?php echo htmlspecialchars($amenity); ?>" <?php echo in_array($amenity, $selectedAmenities, true) ? 'checked' : ''; ?>>
                                    <span><?php echo htmlspecialchars($amenity); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="filter-buttons" style="margin-top:1.2rem;display:flex;gap:0.75rem;flex-wrap:wrap;">
                        <button type="submit" class="btn">Apply Filters</button>
                        <a href="properties.php" class="btn btn-outline">Clear</a>
                    </div>
                </form>
            </div>
        </section>

        <section class="section" style="padding-top:1rem;">
            <div class="container">
                <div class="results-card fade-in">Showing <?php echo count($properties); ?> of <?php echo $total_properties; ?> properties</div>

                <?php if (!empty($properties)): ?>
                    <div class="properties-grid-modern fade-in">
                        <?php foreach ($properties as $property): ?>
                            <?php $thumb = property_thumb($property); ?>
                            <?php $amenityList = sukhdham_decode_property_amenities($property['amenities'] ?? ''); ?>
                            <article class="property-card-modern">
                                <div class="property-media">
                                    <img src="<?php echo htmlspecialchars($thumb); ?>" alt="<?php echo htmlspecialchars($property['title'] ?? 'Property image'); ?>">
                                </div>
                                <div class="property-body">
                                    <div class="badge-row">
                                        <span class="pill"><?php echo htmlspecialchars(ucfirst($property['property_type'] ?? 'sell')); ?></span>
                                        <span class="pill"><?php echo htmlspecialchars($property['category'] ?? 'Apartment'); ?></span>
                                    </div>

                                    <h3 style="font-size:1.1rem;font-weight:800;color:#0f172a;line-height:1.35;"><?php echo htmlspecialchars($property['title'] ?? 'Property'); ?></h3>

                                    <p style="color:#64748b;font-size:0.92rem;line-height:1.55;margin-top:0.35rem;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;min-height:2.65rem;">
                                        <?php echo !empty($property['address']) ? htmlspecialchars($property['address']) : 'Address available on request'; ?>
                                    </p>

                                    <?php if ($property['price']): ?>
                                        <p class="price">₹<?php echo number_format($property['price'], 2); ?></p>
                                    <?php endif; ?>

                                    <div class="meta-grid">
                                        <div class="meta-item"><strong>Bedrooms / Baths</strong><?php echo (int) ($property['bedrooms'] ?? 0); ?> BHK / <?php echo (int) ($property['bathrooms'] ?? 0); ?> Bath</div>
                                        <div class="meta-item"><strong>Area</strong><?php echo !empty($property['carpet_area']) ? number_format((float) $property['carpet_area'], 0) . ' sq ft' : (!empty($property['area_sqft']) ? number_format((float) $property['area_sqft'], 0) . ' sq ft' : '-'); ?></div>
                                        <div class="meta-item"><strong>Furnishing</strong><?php echo htmlspecialchars($property['furnishing_type'] ?? 'Unfurnished'); ?></div>
                                    </div>

                                    <p class="summary"><?php echo !empty($property['description']) ? htmlspecialchars(substr(trim(strip_tags($property['description'])), 0, 120)) . (strlen(trim(strip_tags($property['description']))) > 120 ? '...' : '') : 'No description added yet.'; ?></p>

                                    <div class="actions">
                                        <a href="property.php?id=<?php echo $property['id']; ?>" class="btn">View Details</a>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state fade-in">
                        <h3 style="font-size:1.35rem;font-weight:800;color:#0f172a;margin-bottom:0.5rem;">No properties found</h3>
                        <p style="color:#64748b;margin-bottom:1rem;">Try adjusting the filters to see more matching listings.</p>
                        <a href="properties.php" class="btn">Clear Filters</a>
                    </div>
                <?php endif; ?>

                <?php if ($total_pages > 1): ?>
                    <div class="pagination fade-in">
                        <?php if ($page > 1): ?>
                            <a href="?<?php echo htmlspecialchars(query_string_with_page($pageQueryBase, $page - 1)); ?>" class="btn">Previous</a>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?<?php echo htmlspecialchars(query_string_with_page($pageQueryBase, $i)); ?>" class="btn <?php echo $page === $i ? 'active' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?<?php echo htmlspecialchars(query_string_with_page($pageQueryBase, $page + 1)); ?>" class="btn">Next</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <?php include __DIR__ . '/components/footer.php'; ?>
    <script src="script.js"></script>
</body>
</html>

<?php $conn->close(); ?>