<?php
session_start();

require_once __DIR__ . '/../config/database.php';

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

$admin_id = $_SESSION['admin_id'];
$property_id = intval($_GET['id'] ?? 0);

if ($property_id <= 0) {
    header('Location: dashboard.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| VERIFY PROPERTY OWNERSHIP
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT *
    FROM properties
    WHERE id = ? AND admin_id = ?
");

$stmt->bind_param('ii', $property_id, $admin_id);
$stmt->execute();

$property = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$property) {
    header('Location: dashboard.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| AJAX ACTIONS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    header('Content-Type: application/json');

    /*
    |--------------------------------------------------------------------------
    | SET PRIMARY IMAGE
    |--------------------------------------------------------------------------
    */

    if ($_POST['action'] === 'set_primary') {

        $image_id = intval($_POST['image_id'] ?? 0);

        $stmt = $conn->prepare("
            SELECT *
            FROM property_images
            WHERE id = ? AND property_id = ?
        ");

        $stmt->bind_param('ii', $image_id, $property_id);
        $stmt->execute();

        $image = $stmt->get_result()->fetch_assoc();

        $stmt->close();

        if (!$image) {
            echo json_encode([
                'success' => false,
                'message' => 'Image not found'
            ]);
            exit;
        }

        // RESET ALL PRIMARY FLAGS

        $stmt = $conn->prepare("
            UPDATE property_images
            SET is_primary = 0
            WHERE property_id = ?
        ");

        $stmt->bind_param('i', $property_id);
        $stmt->execute();
        $stmt->close();

        // SET NEW PRIMARY

        $stmt = $conn->prepare("
            UPDATE property_images
            SET is_primary = 1
            WHERE id = ?
        ");

        $stmt->bind_param('i', $image_id);

        if ($stmt->execute()) {

            echo json_encode([
                'success' => true,
                'message' => 'Primary image updated'
            ]);

        } else {

            echo json_encode([
                'success' => false,
                'message' => 'Failed to update primary image'
            ]);
        }

        $stmt->close();
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE IMAGE
    |--------------------------------------------------------------------------
    */

    if ($_POST['action'] === 'delete_image') {

        $image_id = intval($_POST['image_id'] ?? 0);

        $stmt = $conn->prepare("
            SELECT *
            FROM property_images
            WHERE id = ? AND property_id = ?
        ");

        $stmt->bind_param('ii', $image_id, $property_id);
        $stmt->execute();

        $image = $stmt->get_result()->fetch_assoc();

        $stmt->close();

        if (!$image) {
            echo json_encode([
                'success' => false,
                'message' => 'Image not found'
            ]);
            exit;
        }

        // DELETE FILE

        $filepath = str_replace(
            UPLOAD_URL,
            UPLOAD_DIR,
            $image['image_url']
        );

        if (file_exists($filepath)) {
            unlink($filepath);
        }

        // DELETE DB RECORD

        $stmt = $conn->prepare("
            DELETE FROM property_images
            WHERE id = ?
        ");

        $stmt->bind_param('i', $image_id);

        if ($stmt->execute()) {

            echo json_encode([
                'success' => true,
                'message' => 'Image deleted'
            ]);

        } else {

            echo json_encode([
                'success' => false,
                'message' => 'Failed to delete image'
            ]);
        }

        $stmt->close();
        exit;
    }

    echo json_encode([
        'success' => false,
        'message' => 'Invalid action'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| LOAD PROPERTY IMAGES
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT *
    FROM property_images
    WHERE property_id = ?
    ORDER BY is_primary DESC, id ASC
");

$stmt->bind_param('i', $property_id);
$stmt->execute();

$images = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt->close();

$primary_image_id = 0;
foreach ($images as $img) {
    if (!empty($img['is_primary'])) {
        $primary_image_id = (int) $img['id'];
        break;
    }
}

if ($primary_image_id === 0 && !empty($images)) {
    $primary_image_id = (int) $images[0]['id'];
}

/*
|--------------------------------------------------------------------------
| FORM SUBMIT
|--------------------------------------------------------------------------
*/

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $description = trim($_POST['description'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $property_type = in_array(($_POST['property_type'] ?? 'sell'), ['sell', 'rent'], true) ? $_POST['property_type'] : 'sell';
    $category = trim($_POST['category'] ?? 'Apartment');
    $property_status = in_array(($_POST['property_status'] ?? 'available'), ['available', 'sold', 'rented'], true) ? $_POST['property_status'] : 'available';
    $booking_enabled = !empty($_POST['booking_enabled']) ? 1 : 0;

    $price = !empty($_POST['price'])
        ? floatval($_POST['price'])
        : null;

    $bedrooms = intval($_POST['bedrooms'] ?? 0);
    $bathrooms = intval($_POST['bathrooms'] ?? 0);
    $area_sqft = intval($_POST['area_sqft'] ?? 0);
    $title = generatePropertyTitle($property_type, $category, $bedrooms);

    if (empty($address)) {

        $error = 'Address is required';

    } else {

        $stmt = $conn->prepare("
            UPDATE properties
            SET
                title = ?,
                description = ?,
                address = ?,
                price = ?,
                bedrooms = ?,
                bathrooms = ?,
                area_sqft = ?,
                property_type = ?,
                category = ?,
                property_status = ?,
                booking_enabled = ?
            WHERE id = ?
        ");

        // FIXED TYPES
        $stmt->bind_param(
            'sssdiiisssii',
            $title,
            $description,
            $address,
            $price,
            $bedrooms,
            $bathrooms,
            $area_sqft,
            $property_type,
            $category,
            $property_status,
            $booking_enabled,
            $property_id
        );

        $deleted_image_ids = [];
        if (!empty($_POST['deleted_image_ids'])) {
            $deleted_image_ids = array_filter(array_map('intval', explode(',', $_POST['deleted_image_ids'])));
        }

        $selected_primary_image_id = intval($_POST['primary_image_id'] ?? 0);

        if ($stmt->execute()) {

            foreach ($deleted_image_ids as $delete_id) {
                $stmt = $conn->prepare("\n                    SELECT *\n                    FROM property_images\n                    WHERE id = ? AND property_id = ?\n                ");
                $stmt->bind_param('ii', $delete_id, $property_id);
                $stmt->execute();
                $image_to_delete = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($image_to_delete) {
                    $filepath = str_replace(UPLOAD_URL, UPLOAD_DIR, $image_to_delete['image_url']);
                    if (file_exists($filepath)) {
                        unlink($filepath);
                    }

                    $stmt = $conn->prepare("DELETE FROM property_images WHERE id = ? AND property_id = ?");
                    $stmt->bind_param('ii', $delete_id, $property_id);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            /*
            |--------------------------------------------------------------------------
            | HANDLE NEW IMAGES
            |--------------------------------------------------------------------------
            */

            if (!empty($_POST['image_data'])) {

                $current_image_count = count($images);

                $image_data = json_decode(
                    $_POST['image_data'],
                    true
                );

                if (
                    $image_data &&
                    is_array($image_data)
                ) {

                    foreach ($image_data as $base64_image) {

                        if (
                            $current_image_count >=
                            MAX_IMAGES_PER_PROPERTY
                        ) {
                            break;
                        }

                        $result = saveBase64Image(
                            $base64_image,
                            $property_id
                        );

                        if ($result['success']) {

                            $is_primary =
                                ($current_image_count === 0)
                                ? 1
                                : 0;

                            $img_stmt = $conn->prepare("
                                INSERT INTO property_images
                                (
                                    property_id,
                                    image_url,
                                    is_primary
                                )
                                VALUES (?, ?, ?)
                            ");

                            $img_stmt->bind_param(
                                'isi',
                                $property_id,
                                $result['url'],
                                $is_primary
                            );

                            $img_stmt->execute();
                            $img_stmt->close();

                            $current_image_count++;
                        }
                    }
                }
            }

            $remaining_primary_id = $selected_primary_image_id;
            if ($remaining_primary_id > 0 && in_array($remaining_primary_id, $deleted_image_ids, true)) {
                $remaining_primary_id = 0;
            }

            if ($remaining_primary_id > 0) {
                $stmt = $conn->prepare("UPDATE property_images SET is_primary = 0 WHERE property_id = ?");
                $stmt->bind_param('i', $property_id);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("UPDATE property_images SET is_primary = 1 WHERE id = ? AND property_id = ?");
                $stmt->bind_param('ii', $remaining_primary_id, $property_id);
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $conn->prepare("SELECT id FROM property_images WHERE property_id = ? ORDER BY id ASC LIMIT 1");
                $stmt->bind_param('i', $property_id);
                $stmt->execute();
                $first_image = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($first_image) {
                    $stmt = $conn->prepare("UPDATE property_images SET is_primary = 0 WHERE property_id = ?");
                    $stmt->bind_param('i', $property_id);
                    $stmt->execute();
                    $stmt->close();

                    $stmt = $conn->prepare("UPDATE property_images SET is_primary = 1 WHERE id = ? AND property_id = ?");
                    $stmt->bind_param('ii', $first_image['id'], $property_id);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            header('Location: dashboard.php');
            exit;

        } else {

            $error = 'Failed to update property';
        }

        $stmt->close();
    }
}

/*
|--------------------------------------------------------------------------
| IMAGE SAVE HELPER
|--------------------------------------------------------------------------
*/

function saveBase64Image($base64_data, $property_id)
{
    $timestamp = time();
    $random = rand(1000, 9999);

    $filename = "property_{$property_id}_{$timestamp}_{$random}.jpg";

    $filepath = UPLOAD_DIR . $filename;

    $data = explode(',', $base64_data);

    if (count($data) < 2) {
        return [
            'success' => false,
            'error' => 'Invalid image data'
        ];
    }

    $image_content = base64_decode($data[1]);

    if ($image_content === false) {
        return [
            'success' => false,
            'error' => 'Base64 decode failed'
        ];
    }

    if (file_put_contents($filepath, $image_content) === false) {
        return [
            'success' => false,
            'error' => 'Failed to save image'
        ];
    }

    return [
        'success' => true,
        'url' => UPLOAD_URL . $filename
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Edit Property - Sukhdham</title>

    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100">

<?php include __DIR__ . '/components/navbar.php'; ?>

<div class="max-w-3xl mx-auto px-4 py-6 sm:py-8">

    <div class="flex flex-col gap-3 sm:flex-row sm:justify-between sm:items-center mb-6 sm:mb-8">

        <h1 class="text-2xl sm:text-3xl font-bold text-gray-800 break-words">
            Edit Property
        </h1>

        <a href="dashboard.php"
           class="text-blue-600 hover:text-blue-800 text-sm sm:text-base">

            ← Back to Dashboard

        </a>

    </div>

    <?php if ($error): ?>

        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">

            <?php echo htmlspecialchars($error); ?>

        </div>

    <?php endif; ?>

    <form method="POST"
          enctype="multipart/form-data"
          class="bg-white rounded-lg shadow p-6">

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">

            <div>

                <label class="block text-gray-700 font-medium mb-2">
                    Property Type *
                </label>

                <select name="property_type"
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">

                    <option value="sell" <?php echo (($property['property_type'] ?? 'sell') === 'sell') ? 'selected' : ''; ?>>Sell</option>
                    <option value="rent" <?php echo (($property['property_type'] ?? 'sell') === 'rent') ? 'selected' : ''; ?>>Rent</option>

                </select>

            </div>

            <div>

                <label class="block text-gray-700 font-medium mb-2">
                    Property Category *
                </label>

                <select name="category"
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">

                    <?php foreach (['Apartment', 'House', 'Villa', 'Commercial', 'Plot', 'Other'] as $item): ?>
                        <option value="<?php echo htmlspecialchars($item); ?>" <?php echo (($property['category'] ?? 'Apartment') === $item) ? 'selected' : ''; ?>><?php echo htmlspecialchars($item); ?></option>
                    <?php endforeach; ?>

                </select>

            </div>

        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">

            <div>

                <label class="block text-gray-700 font-medium mb-2">
                    Property Status *
                </label>

                <select name="property_status"
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">

                    <option value="available" <?php echo (($property['property_status'] ?? 'available') === 'available') ? 'selected' : ''; ?>>Available</option>
                    <option value="sold" <?php echo (($property['property_status'] ?? 'available') === 'sold') ? 'selected' : ''; ?>>Sold</option>
                    <option value="rented" <?php echo (($property['property_status'] ?? 'available') === 'rented') ? 'selected' : ''; ?>>Rented</option>

                </select>

            </div>

            <div class="flex items-center gap-3 mt-7">
                <input type="checkbox"
                       name="booking_enabled"
                       id="booking_enabled"
                       <?php echo !empty($property['booking_enabled']) ? 'checked' : ''; ?>
                       class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                <label for="booking_enabled" class="text-gray-700 font-medium">Booking Available</label>
            </div>

        </div>

        <div class="mb-6">

            <label class="block text-gray-700 font-medium mb-2">
                Description
            </label>

            <textarea name="description"
                      rows="6"
                      class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($property['description'] ?? ''); ?></textarea>

        </div>

        <div class="mb-6">

            <label class="block text-gray-700 font-medium mb-2">
                Address *
            </label>

            <input type="text"
                   name="address"
                   required
                   value="<?php echo htmlspecialchars($property['address']); ?>"
                   class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">

        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">

            <div>

                <label class="block text-gray-700 font-medium mb-2">
                    Price (₹)
                </label>

                <input type="number"
                       name="price"
                       step="0.01"
                       min="0"
                       value="<?php echo htmlspecialchars($property['price'] ?? ''); ?>"
                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">

            </div>

            <div>

                <label class="block text-gray-700 font-medium mb-2">
                    Area (Sq.ft)
                </label>

                <input type="number"
                       name="area_sqft"
                       min="0"
                       value="<?php echo htmlspecialchars($property['area_sqft'] ?? ''); ?>"
                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">

            </div>

        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">

            <div>

                <label class="block text-gray-700 font-medium mb-2">
                    Bedrooms
                </label>

                <input type="number"
                       name="bedrooms"
                       min="0"
                       value="<?php echo htmlspecialchars($property['bedrooms'] ?? ''); ?>"
                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">

            </div>

            <div>

                <label class="block text-gray-700 font-medium mb-2">
                    Bathrooms
                </label>

                <input type="number"
                       name="bathrooms"
                       min="0"
                       value="<?php echo htmlspecialchars($property['bathrooms'] ?? ''); ?>"
                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">

            </div>

        </div>

        <div class="mb-6">

            <label class="block text-gray-700 font-medium mb-2">

                Current Images
                (<?php echo count($images); ?>/<?php echo MAX_IMAGES_PER_PROPERTY; ?>)

            </label>

            <?php if (!empty($images)): ?>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

                    <?php foreach ($images as $img): ?>

                        <div class="relative border rounded-lg p-2 overflow-hidden <?php echo !empty($img['is_primary']) ? 'border-blue-600 ring-2 ring-blue-200' : ''; ?>" data-current-image-card data-image-id="<?php echo (int) $img['id']; ?>">

                            <img src="../<?php echo htmlspecialchars(ltrim($img['image_url'], '/')); ?>"
                                 class="w-full h-28 sm:h-32 object-cover rounded">

                            <?php if ($img['is_primary']): ?>

                                <span class="absolute top-1 left-1 bg-blue-600 text-white text-xs px-2 py-1 rounded">
                                    Primary
                                </span>

                            <?php endif; ?>

                            <div class="flex flex-col sm:flex-row gap-2 mt-2">

                                <button type="button"
                                        onclick="setPrimary(<?php echo $img['id']; ?>)"
                                        class="text-xs bg-gray-200 px-2 py-1 rounded hover:bg-gray-300 w-full sm:w-auto">

                                    Set Primary

                                </button>

                                <button type="button"
                                        onclick="deleteImage(<?php echo $img['id']; ?>)"
                                        class="text-xs bg-red-100 text-red-600 px-2 py-1 rounded hover:bg-red-200 w-full sm:w-auto">

                                    Delete

                                </button>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <p class="text-gray-500">
                    No images uploaded yet.
                </p>

            <?php endif; ?>

        </div>

        <?php if (count($images) < MAX_IMAGES_PER_PROPERTY): ?>

            <div class="mb-6">

                <label class="block text-gray-700 font-medium mb-2">

                    Add More Images
                    (<span id="newImageCount">0</span>)

                </label>

                <button type="button"
                        id="addImageBtn"
                        onclick="document.getElementById('imageInput').click()"
                        class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition mb-4 w-full sm:w-auto">

                    + Add Image

                </button>

                <input type="file"
                       id="imageInput"
                       accept="image/*"
                       style="display:none"
                       onchange="handleImageSelect(this)">

                <div id="imagePreview"
                     class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-4"></div>

                <input type="hidden"
                       name="image_data"
                       id="imageData">

                <input type="hidden"
                       name="primary_image_id"
                       id="primaryImageId"
                       value="<?php echo (int) $primary_image_id; ?>">

                <input type="hidden"
                       name="deleted_image_ids"
                       id="deletedImageIds"
                       value="">

            </div>

        <?php endif; ?>

        <div class="flex flex-col sm:flex-row gap-3 sm:gap-4">

            <button type="submit"
                    class="bg-blue-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-blue-700 transition w-full sm:w-auto">

                Update Property

            </button>

            <a href="dashboard.php"
               class="bg-gray-300 text-gray-700 px-8 py-3 rounded-lg font-semibold hover:bg-gray-400 text-center w-full sm:w-auto">

                Cancel

            </a>

        </div>

    </form>

</div>

<script>
let uploadedImages = [];

const maxNewImages =
    <?php echo MAX_IMAGES_PER_PROPERTY - count($images); ?>;

let primaryImageId = <?php echo (int) $primary_image_id; ?>;
let deletedImageIds = [];

function syncHiddenInputs() {
    const primaryInput = document.getElementById('primaryImageId');
    const deletedInput = document.getElementById('deletedImageIds');

    if (primaryInput) {
        primaryInput.value = primaryImageId || '';
    }

    if (deletedInput) {
        deletedInput.value = deletedImageIds.join(',');
    }
}

function updateCurrentImageUI() {
    document.querySelectorAll('[data-current-image-card]').forEach(card => {
        const imageId = parseInt(card.dataset.imageId, 10);
        const badge = card.querySelector('[data-primary-badge]');
        const isDeleted = deletedImageIds.includes(imageId);

        card.classList.toggle('hidden', isDeleted);
        card.classList.toggle('border-blue-600', imageId === primaryImageId && !isDeleted);
        card.classList.toggle('ring-2', imageId === primaryImageId && !isDeleted);
        card.classList.toggle('ring-blue-200', imageId === primaryImageId && !isDeleted);

        if (badge) {
            badge.classList.toggle('hidden', !(imageId === primaryImageId && !isDeleted));
        }
    });
}

function setPrimary(imageId) {

    if (deletedImageIds.includes(imageId)) {
        return;
    }

    primaryImageId = imageId;
    syncHiddenInputs();
    updateCurrentImageUI();
}

function deleteImage(imageId) {

    if (!confirm('Delete this image?')) {
        return;
    }

    if (!deletedImageIds.includes(imageId)) {
        deletedImageIds.push(imageId);
    }

    if (primaryImageId === imageId) {
        const remaining = Array.from(document.querySelectorAll('[data-current-image-card]'))
            .map(card => parseInt(card.dataset.imageId, 10))
            .filter(id => !deletedImageIds.includes(id) && id !== imageId);

        primaryImageId = remaining.length ? remaining[0] : 0;
    }

    syncHiddenInputs();
    updateCurrentImageUI();
}

function updateImageCount() {

    const counter =
        document.getElementById('newImageCount');

    if (counter) {
        counter.textContent = uploadedImages.length;
    }
}

function handleImageSelect(input) {

    if (input.files && input.files[0]) {

        const file = input.files[0];

        if (uploadedImages.length >= maxNewImages) {

            alert('Maximum image limit reached');
            return;
        }

        const reader = new FileReader();

        reader.onload = function(e) {

            uploadedImages.push({
                dataUrl: e.target.result
            });

            renderPreview();
            updateImageCount();
        };

        reader.readAsDataURL(file);
    }
}

function removeNewImage(index) {

    uploadedImages.splice(index, 1);

    renderPreview();
    updateImageCount();
}

function renderPreview() {

    const preview =
        document.getElementById('imagePreview');

    if (!preview) return;

    preview.innerHTML = '';

    uploadedImages.forEach((img, index) => {

        const div = document.createElement('div');

        div.className =
            'relative border rounded-lg p-2 overflow-hidden';

        div.innerHTML = `
            <img
                src="${img.dataUrl}"
                class="w-full h-24 object-cover rounded mb-2"
            >

            <button
                type="button"
                onclick="removeNewImage(${index})"
                class="text-red-500 hover:text-red-700 text-sm w-full text-left"
            >
                ✕ Remove
            </button>
        `;

        preview.appendChild(div);
    });

    document.getElementById('imageData').value =
        JSON.stringify(
            uploadedImages.map(img => img.dataUrl)
        );
}

syncHiddenInputs();
updateCurrentImageUI();
</script>

</body>
</html>

<?php $conn->close(); ?>
