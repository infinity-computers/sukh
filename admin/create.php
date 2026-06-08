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

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $address = trim($_POST['address'] ?? '');

    $price = !empty($_POST['price'])
        ? floatval($_POST['price'])
        : null;

    $bedrooms = intval($_POST['bedrooms'] ?? 0);
    $bathrooms = intval($_POST['bathrooms'] ?? 0);
    $area_sqft = intval($_POST['area_sqft'] ?? 0);

    if (empty($title)) {
        $error = 'Title is required';
    } elseif (empty($address)) {
        $error = 'Address is required';
    } else {

        $admin_id = $_SESSION['admin_id'];

        $stmt = $conn->prepare("
            INSERT INTO properties
            (
                admin_id,
                title,
                description,
                address,
                price,
                bedrooms,
                bathrooms,
                area_sqft,
                status
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");

        if (!$stmt) {
            die("Prepare failed: " . $conn->error);
        }

        // FIXED TYPES
        $stmt->bind_param(
            'isssdiii',
            $admin_id,
            $title,
            $description,
            $address,
            $price,
            $bedrooms,
            $bathrooms,
            $area_sqft
        );

        if ($stmt->execute()) {

            $property_id = $conn->insert_id;

            // HANDLE IMAGES
            if (!empty($_POST['image_data'])) {

                $image_data = json_decode($_POST['image_data'], true);

                if ($image_data && is_array($image_data)) {

                    $primary_set = false;

                    foreach ($image_data as $index => $base64_image) {

                        $result = saveBase64Image($base64_image, $property_id);

                        if ($result['success']) {

                            $is_primary = (!$primary_set && $index === 0) ? 1 : 0;

                            $img_stmt = $conn->prepare("
                                INSERT INTO property_images
                                (
                                    property_id,
                                    image_url,
                                    is_primary
                                )
                                VALUES (?, ?, ?)
                            ");

                            if ($img_stmt) {

                                $img_stmt->bind_param(
                                    'isi',
                                    $property_id,
                                    $result['url'],
                                    $is_primary
                                );

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

        } else {
            $error = 'Failed to create property: ' . $stmt->error;
        }

        $stmt->close();
    }
}

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
            'error' => 'Invalid base64 image'
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Property - Sukhdham</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <?php include __DIR__ . '/components/navbar.php'; ?>
    
    <div class="max-w-3xl mx-auto px-4 py-6 sm:py-8">
        <div class="flex flex-col gap-3 sm:flex-row sm:justify-between sm:items-center mb-6 sm:mb-8">
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-800 break-words">Add New Property</h1>
            <a href="dashboard.php" class="text-blue-600 hover:text-blue-800 text-sm sm:text-base">← Back to Dashboard</a>
        </div>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data" class="bg-white rounded-lg shadow p-6">
            <div class="mb-6">
                <label class="block text-gray-700 font-medium mb-2">Title *</label>
                <input type="text" name="title" required 
                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                       placeholder="Property title">
            </div>
            
            <div class="mb-6">
                <label class="block text-gray-700 font-medium mb-2">Description</label>
                <textarea name="description" rows="4" 
                          class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                          placeholder="Property description"></textarea>
            </div>
            
            <div class="mb-6">
                <label class="block text-gray-700 font-medium mb-2">Address *</label>
                <input type="text" name="address" required 
                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                       placeholder="Full address">
            </div>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-gray-700 font-medium mb-2">Price (₹)</label>
                    <input type="number" name="price" step="0.01" min="0"
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Enter price">
                </div>
                <div>
                    <label class="block text-gray-700 font-medium mb-2">Area (Sq.ft)</label>
                    <input type="number" name="area_sqft" min="0"
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Area in sq ft">
                </div>
            </div>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-gray-700 font-medium mb-2">Bedrooms</label>
                    <input type="number" name="bedrooms" min="0"
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Number of bedrooms">
                </div>
                <div>
                    <label class="block text-gray-700 font-medium mb-2">Bathrooms</label>
                    <input type="number" name="bathrooms" min="0"
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Number of bathrooms">
                </div>
            </div>
            
            <div class="mb-6">
                <label class="block text-gray-700 font-medium mb-2">Images (<span id="imageCount">0</span>/10)</label>
                <button type="button" id="addImageBtn" onclick="document.getElementById('imageInput').click()" 
                        class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition mb-4 w-full sm:w-auto">
                    + Add Image
                </button>
                <input type="file" id="imageInput" accept="image/*" style="display: none;" onchange="handleImageSelect(this)">
                <p class="text-gray-500 text-sm mb-2">Minimum 800x600px, Max 5MB per image</p>
                <div id="imagePreview" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-4"></div>
                <input type="hidden" name="image_data" id="imageData">
            </div>
            
            <div class="flex flex-col sm:flex-row gap-3 sm:gap-4">
                <button type="submit" 
                        class="bg-blue-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-blue-700 transition w-full sm:w-auto">
                    Create Property
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
        const MAX_IMAGES = 10;
        
        function updateImageCount() {
            document.getElementById('imageCount').textContent = uploadedImages.length;
            document.getElementById('addImageBtn').disabled = uploadedImages.length >= MAX_IMAGES;
            if (uploadedImages.length >= MAX_IMAGES) {
                document.getElementById('addImageBtn').textContent = 'Max images reached';
            } else {
                document.getElementById('addImageBtn').textContent = '+ Add Image';
            }
        }
        
        function validateImage(file) {
            const MAX_SIZE = 5 * 1024 * 1024; // 5MB
            const MIN_WIDTH = 800;
            const MIN_HEIGHT = 600;
            
            if (file.size > MAX_SIZE) {
                alert('File size exceeds 5MB limit');
                return false;
            }
            
            const img = new Image();
            const objectUrl = URL.createObjectURL(file);
            
            let isValid = false;
            img.onload = function() {
                if (img.width < MIN_WIDTH || img.height < MIN_HEIGHT) {
                    alert('Image must be at least 800x600 pixels');
                } else {
                    isValid = true;
                }
                URL.revokeObjectURL(objectUrl);
            };
            
            return new Promise((resolve) => {
                img.onload = function() {
                    if (img.width < MIN_WIDTH || img.height < MIN_HEIGHT) {
                        alert('Image must be at least 800x600 pixels');
                        resolve(false);
                    } else {
                        resolve(true);
                    }
                    URL.revokeObjectURL(objectUrl);
                };
                img.src = objectUrl;
            });
        }
        
        async function handleImageSelect(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                
                if (uploadedImages.length >= MAX_IMAGES) {
                    alert('Maximum 10 images allowed');
                    input.value = '';
                    return;
                }
                
                const isValid = await validateImage(file);
                if (!isValid) {
                    input.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    uploadedImages.push({
                        file: file,
                        dataUrl: e.target.result,
                        isPrimary: uploadedImages.length === 0
                    });
                    renderPreview();
                    updateImageCount();
                    input.value = '';
                };
                reader.readAsDataURL(file);
            }
        }
        
        function setPrimary(index) {
            uploadedImages.forEach((img, i) => {
                img.isPrimary = (i === index);
            });
            renderPreview();
        }
        
        function removeImage(index) {
            uploadedImages.splice(index, 1);
            if (uploadedImages.length > 0 && !uploadedImages.some(img => img.isPrimary)) {
                uploadedImages[0].isPrimary = true;
            }
            renderPreview();
            updateImageCount();
        }
        
        function renderPreview() {
            const preview = document.getElementById('imagePreview');
            preview.innerHTML = '';
            
            uploadedImages.forEach((img, index) => {
                const div = document.createElement('div');
                div.className = 'relative border rounded-lg p-2 overflow-hidden';
                div.innerHTML = `
                    <img src="${img.dataUrl}" class="w-full h-24 object-cover rounded mb-2">
                    <div class="flex flex-col gap-2 sm:flex-row sm:justify-between sm:items-center">
                        <label class="flex items-center text-xs cursor-pointer">
                            <input type="radio" name="primary_image" ${img.isPrimary ? 'checked' : ''} 
                                   onchange="setPrimary(${index})" class="mr-1">
                            Primary
                        </label>
                        <button type="button" onclick="removeImage(${index})" 
                                class="text-red-500 hover:text-red-700 text-sm text-left sm:text-right">✕ Remove</button>
                    </div>
                `;
                preview.appendChild(div);
            });
            
            document.getElementById('imageData').value = JSON.stringify(uploadedImages.map(img => img.dataUrl));
        }
        
        document.querySelector('form').onsubmit = function(e) {
            if (uploadedImages.length > 0) {
                document.getElementById('imageData').value = JSON.stringify(uploadedImages.map(img => img.dataUrl));
            }
        };
    </script>
</body>
</html>

<?php $conn->close(); ?>
