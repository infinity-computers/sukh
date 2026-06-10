<?php
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/utils/PropertyHelpers.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$current_time = time();
$last_activity = $_SESSION['last_activity'] ?? $current_time;
$timeout = SESSION_TIMEOUT_MINUTES * 60;

if ($current_time - $last_activity > $timeout) {
    session_destroy();
    header('Location: index.php');
    exit;
}

$_SESSION['last_activity'] = $current_time;

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = sukhdham_normalize_property_form($_POST);

    if ($form['address'] === '') {
        $error = 'Address is required';
    } else {
        $admin_id = (int) $_SESSION['admin_id'];
        $title = generatePropertyTitle($form['property_type'], $form['category'], $form['bedrooms']);

        $stmt = $conn->prepare("\n            INSERT INTO properties\n            (\n                admin_id,\n                title,\n                description,\n                address,\n                price,\n                security_deposit,\n                maintenance_charges,\n                available_from,\n                furnishing_type,\n                bedrooms,\n                bathrooms,\n                balconies,\n                floor_number,\n                total_floors,\n                area_sqft,\n                carpet_area,\n                parking,\n                water_supply,\n                electricity_backup,\n                facing_direction,\n                property_type,\n                category,\n                property_status,\n                booking_enabled,\n                amenities,\n                tenant_preferred,\n                lease_duration,\n                available_immediately,\n                bills_included,\n                pets_allowed,\n                washroom_available,\n                pantry_available,\n                cabin_count,\n                parking_spaces,\n                status\n            )\n            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)\n        ");

        if (!$stmt) {
            die('Prepare failed: ' . $conn->error);
        }

        $bindValues = [
            $admin_id,
            $title,
            $form['description'],
            $form['address'],
            $form['price'],
            $form['security_deposit'],
            $form['maintenance_charges'],
            $form['available_from'],
            $form['furnishing_type'],
            $form['bedrooms'],
            $form['bathrooms'],
            $form['balconies'],
            $form['floor_number'],
            $form['total_floors'],
            $form['area_sqft'],
            $form['carpet_area'],
            $form['parking'],
            $form['water_supply'],
            $form['electricity_backup'],
            $form['facing_direction'],
            $form['property_type'],
            $form['category'],
            $form['property_status'],
            $form['booking_enabled'],
            $form['amenities'],
            $form['tenant_preferred'],
            $form['lease_duration'],
            $form['available_immediately'],
            $form['bills_included'],
            $form['pets_allowed'],
            $form['washroom_available'],
            $form['pantry_available'],
            $form['cabin_count'],
            $form['parking_spaces'],
            'active',
        ];

        $types = 'isssdddss' . 'iiiiii' . 'd' . 'ssss' . 'sss' . 'i' . 'ssssssss' . 'iii';
        sukhdham_bind_stmt_values($stmt, $types, $bindValues);

        if ($stmt->execute()) {
            $property_id = $conn->insert_id;

            if (!empty($_POST['image_data'])) {
                $image_data = json_decode($_POST['image_data'], true);

                if (is_array($image_data)) {
                    $primary_set = false;

                    foreach ($image_data as $index => $base64_image) {
                        $result = sukhdham_save_base64_property_image($base64_image, $property_id, 'Sukhdham');

                        if (!empty($result['success'])) {
                            $is_primary = (!$primary_set && $index === 0) ? 1 : 0;

                            $img_stmt = $conn->prepare("INSERT INTO property_images (property_id, image_url, is_primary) VALUES (?, ?, ?)");
                            if ($img_stmt) {
                                $img_stmt->bind_param('isi', $property_id, $result['url'], $is_primary);
                                $img_stmt->execute();
                                $img_stmt->close();
                                if ($is_primary) {
                                    $primary_set = true;
                                }
                            }
                        }
                    }
                }
            }

            header('Location: dashboard.php');
            exit;
        }

        $error = 'Failed to create property: ' . $stmt->error;
        $stmt->close();
    }
}

$selectedPropertyType = 'sell';
$selectedCategory = 'Apartment';
$selectedStatus = 'available';
$selectedFurnishing = 'Unfurnished';
$selectedTenant = 'Any';
$selectedWater = '24 Hours';
$selectedDirection = 'East';
$selectedAmenities = [];

function selected_attr($current, $value)
{
    return $current === $value ? 'selected' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Property - Sukhdham</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <?php include __DIR__ . '/components/navbar.php'; ?>

    <div class="max-w-5xl mx-auto px-4 py-6 sm:py-8">
        <div class="flex flex-col gap-3 sm:flex-row sm:justify-between sm:items-center mb-6 sm:mb-8">
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-800 break-words">Add New Property</h1>
            <a href="dashboard.php" class="text-blue-600 hover:text-blue-800 text-sm sm:text-base">← Back to Dashboard</a>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="bg-white rounded-2xl shadow-xl p-5 sm:p-8 space-y-8">
            <section>
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-800">Basic Property Details</h2>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Property Type *</label>
                        <select name="property_type" id="propertyType" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="sell" <?php echo selected_attr($selectedPropertyType, 'sell'); ?>>Sell</option>
                            <option value="rent" <?php echo selected_attr($selectedPropertyType, 'rent'); ?>>Rent</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Property Category *</label>
                        <select name="category" id="propertyCategory" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <?php foreach (sukhdham_property_category_options() as $item): ?>
                                <option value="<?php echo htmlspecialchars($item); ?>" <?php echo selected_attr($selectedCategory, $item); ?>><?php echo htmlspecialchars($item); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Price (₹)</label>
                        <input type="number" name="price" step="0.01" min="0" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter price">
                    </div>
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Security Deposit (₹)</label>
                        <input type="number" name="security_deposit" step="0.01" min="0" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Security deposit">
                    </div>
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Maintenance Charges (₹)</label>
                        <input type="number" name="maintenance_charges" step="0.01" min="0" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Maintenance charges">
                    </div>
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Available From</label>
                        <input type="date" name="available_from" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Furnishing Type</label>
                        <select name="furnishing_type" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <?php foreach (sukhdham_furnishing_options() as $item): ?>
                                <option value="<?php echo htmlspecialchars($item); ?>" <?php echo selected_attr($selectedFurnishing, $item); ?>><?php echo htmlspecialchars($item); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Property Status *</label>
                        <select name="property_status" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <?php foreach (['available' => 'Available', 'rented' => 'Rented', 'sold' => 'Sold'] as $value => $label): ?>
                                <option value="<?php echo htmlspecialchars($value); ?>" <?php echo selected_attr($selectedStatus, $value); ?>><?php echo htmlspecialchars($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-3">
                    <input type="checkbox" name="booking_enabled" id="booking_enabled" checked class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                    <label for="booking_enabled" class="text-gray-700 font-medium">Booking Available</label>
                </div>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Property Specifications</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div><label class="block text-gray-700 font-medium mb-2">Total Bedrooms</label><input type="number" name="bedrooms" min="0" class="w-full px-4 py-2 border rounded-lg"></div>
                    <div><label class="block text-gray-700 font-medium mb-2">Total Bathrooms</label><input type="number" name="bathrooms" min="0" class="w-full px-4 py-2 border rounded-lg"></div>
                    <div><label class="block text-gray-700 font-medium mb-2">Balconies</label><input type="number" name="balconies" min="0" class="w-full px-4 py-2 border rounded-lg"></div>
                    <div><label class="block text-gray-700 font-medium mb-2">Floor Number</label><input type="number" name="floor_number" min="0" class="w-full px-4 py-2 border rounded-lg"></div>
                    <div><label class="block text-gray-700 font-medium mb-2">Total Floors</label><input type="number" name="total_floors" min="0" class="w-full px-4 py-2 border rounded-lg"></div>
                    <div><label class="block text-gray-700 font-medium mb-2">Area (Sq.ft)</label><input type="number" name="area_sqft" min="0" class="w-full px-4 py-2 border rounded-lg"></div>
                    <div><label class="block text-gray-700 font-medium mb-2">Carpet Area (Sq.ft)</label><input type="number" name="carpet_area" step="0.01" min="0" class="w-full px-4 py-2 border rounded-lg"></div>
                    <div><label class="block text-gray-700 font-medium mb-2">Parking</label><select name="parking" class="w-full px-4 py-2 border rounded-lg"><option>Yes</option><option selected>No</option></select></div>
                    <div><label class="block text-gray-700 font-medium mb-2">Water Supply</label><select name="water_supply" class="w-full px-4 py-2 border rounded-lg"><?php foreach (sukhdham_water_supply_options() as $item): ?><option value="<?php echo htmlspecialchars($item); ?>" <?php echo selected_attr($selectedWater, $item); ?>><?php echo htmlspecialchars($item); ?></option><?php endforeach; ?></select></div>
                    <div><label class="block text-gray-700 font-medium mb-2">Electricity Backup</label><select name="electricity_backup" class="w-full px-4 py-2 border rounded-lg"><option>Yes</option><option selected>No</option></select></div>
                    <div><label class="block text-gray-700 font-medium mb-2">Facing Direction</label><select name="facing_direction" class="w-full px-4 py-2 border rounded-lg"><?php foreach (sukhdham_facing_options() as $item): ?><option value="<?php echo htmlspecialchars($item); ?>" <?php echo selected_attr($selectedDirection, $item); ?>><?php echo htmlspecialchars($item); ?></option><?php endforeach; ?></select></div>
                </div>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Amenities</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-3">
                    <?php foreach (sukhdham_amenity_options() as $amenity): ?>
                        <label class="flex items-center gap-3 border rounded-xl px-3 py-2 bg-gray-50 hover:bg-blue-50 cursor-pointer">
                            <input type="checkbox" name="amenities[]" value="<?php echo htmlspecialchars($amenity); ?>" class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                            <span class="text-sm text-gray-700"><?php echo htmlspecialchars($amenity); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </section>

            <section id="rentFields" class="space-y-4">
                <h2 class="text-lg font-semibold text-gray-800">Rent-Specific Details</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Tenant Preferred</label>
                        <select name="tenant_preferred" class="w-full px-4 py-2 border rounded-lg">
                            <?php foreach (sukhdham_tenant_preferred_options() as $item): ?>
                                <option value="<?php echo htmlspecialchars($item); ?>" <?php echo selected_attr($selectedTenant, $item); ?>><?php echo htmlspecialchars($item); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Lease Duration</label>
                        <input type="text" name="lease_duration" class="w-full px-4 py-2 border rounded-lg" placeholder="e.g. 11 months">
                    </div>
                    <div><label class="block text-gray-700 font-medium mb-2">Available Immediately</label><select name="available_immediately" class="w-full px-4 py-2 border rounded-lg"><option>Yes</option><option selected>No</option></select></div>
                    <div><label class="block text-gray-700 font-medium mb-2">Bills Included</label><select name="bills_included" class="w-full px-4 py-2 border rounded-lg"><option>Yes</option><option selected>No</option></select></div>
                    <div><label class="block text-gray-700 font-medium mb-2">Pets Allowed</label><select name="pets_allowed" class="w-full px-4 py-2 border rounded-lg"><option>Yes</option><option selected>No</option></select></div>
                </div>
            </section>

            <section id="commercialFields" class="space-y-4">
                <h2 class="text-lg font-semibold text-gray-800">Commercial Property Details</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div><label class="block text-gray-700 font-medium mb-2">Washroom Available</label><select name="washroom_available" class="w-full px-4 py-2 border rounded-lg"><option>Yes</option><option selected>No</option></select></div>
                    <div><label class="block text-gray-700 font-medium mb-2">Pantry Available</label><select name="pantry_available" class="w-full px-4 py-2 border rounded-lg"><option>Yes</option><option selected>No</option></select></div>
                    <div><label class="block text-gray-700 font-medium mb-2">Cabin Count</label><input type="number" name="cabin_count" min="0" class="w-full px-4 py-2 border rounded-lg"></div>
                    <div><label class="block text-gray-700 font-medium mb-2">Parking Spaces</label><input type="number" name="parking_spaces" min="0" class="w-full px-4 py-2 border rounded-lg"></div>
                </div>
            </section>

            <section>
                <label class="block text-gray-700 font-medium mb-2">Description</label>
                <textarea name="description" rows="6" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Property description"></textarea>
            </section>

            <section>
                <label class="block text-gray-700 font-medium mb-2">Address *</label>
                <input type="text" name="address" required class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Full address">
            </section>

            <section>
                <label class="block text-gray-700 font-medium mb-2">Images (<span id="imageCount">0</span>/10)</label>
                <button type="button" id="addImageBtn" onclick="document.getElementById('imageInput').click()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition mb-4 w-full sm:w-auto">+ Add Image</button>
                <input type="file" id="imageInput" accept="image/*" style="display:none" onchange="handleImageSelect(this)">
                <p class="text-gray-500 text-sm mb-2">Max 5MB per image</p>
                <div id="imagePreview" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-4"></div>
                <input type="hidden" name="image_data" id="imageData">
            </section>

            <div class="flex flex-col sm:flex-row gap-3 sm:gap-4">
                <button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-blue-700 transition w-full sm:w-auto">Create Property</button>
                <a href="dashboard.php" class="bg-gray-300 text-gray-700 px-8 py-3 rounded-lg font-semibold hover:bg-gray-400 text-center w-full sm:w-auto">Cancel</a>
            </div>
        </form>
    </div>

    <script>
        let uploadedImages = [];
        const MAX_IMAGES = 10;

        function updateImageCount() {
            const count = document.getElementById('imageCount');
            const addBtn = document.getElementById('addImageBtn');
            if (count) count.textContent = uploadedImages.length;
            if (addBtn) {
                addBtn.disabled = uploadedImages.length >= MAX_IMAGES;
                addBtn.textContent = uploadedImages.length >= MAX_IMAGES ? 'Max images reached' : '+ Add Image';
            }
        }

        function handleImageSelect(input) {
            if (!input.files || !input.files[0]) return;
            if (uploadedImages.length >= MAX_IMAGES) {
                alert('Maximum image limit reached');
                return;
            }

            const file = input.files[0];
            const MAX_SIZE = 5 * 1024 * 1024;
            if (file.size > MAX_SIZE) {
                alert('File size exceeds 5MB limit');
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                uploadedImages.push({ dataUrl: e.target.result });
                renderPreview();
                updateImageCount();
            };
            reader.readAsDataURL(file);
        }

        function removeNewImage(index) {
            uploadedImages.splice(index, 1);
            renderPreview();
            updateImageCount();
        }

        function renderPreview() {
            const preview = document.getElementById('imagePreview');
            if (!preview) return;
            preview.innerHTML = '';
            uploadedImages.forEach((img, index) => {
                const div = document.createElement('div');
                div.className = 'relative border rounded-lg p-2 overflow-hidden';
                div.innerHTML = `
                    <img src="${img.dataUrl}" class="w-full h-24 object-cover rounded mb-2">
                    <button type="button" onclick="removeNewImage(${index})" class="text-red-500 hover:text-red-700 text-sm w-full text-left">✕ Remove</button>
                `;
                preview.appendChild(div);
            });
            const imageData = document.getElementById('imageData');
            if (imageData) imageData.value = JSON.stringify(uploadedImages.map(img => img.dataUrl));
        }

        function updateConditionalSections() {
            // Keep all sections visible so every field is available on the form.
        }

        const propertyTypeEl = document.getElementById('propertyType');
        const propertyCategoryEl = document.getElementById('propertyCategory');
        if (propertyTypeEl) propertyTypeEl.addEventListener('change', updateConditionalSections);
        if (propertyCategoryEl) propertyCategoryEl.addEventListener('change', updateConditionalSections);
        updateConditionalSections();
        updateImageCount();
    </script>
</body>
</html>

<?php $conn->close(); ?>