<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

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

// Handle POST requests (AJAX actions)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $property_id = intval($_POST['id'] ?? 0);
    
    if ($action === 'delete') {
        $stmt = $conn->prepare("SELECT * FROM properties WHERE id = ? AND admin_id = ?");
        $stmt->bind_param('ii', $property_id, $admin_id);
        $stmt->execute();
        $property = $stmt->get_result()->fetch_assoc();
        
        if (!$property) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }
        
        $stmt = $conn->prepare("SELECT * FROM property_images WHERE property_id = ?");
        $stmt->bind_param('i', $property_id);
        $stmt->execute();
        $images = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        foreach ($images as $image) {
            $stmt = $conn->prepare("DELETE FROM property_images WHERE id = ?");
            $stmt->bind_param('i', $image['id']);
            $stmt->execute();
        }
        
        $stmt = $conn->prepare("DELETE FROM properties WHERE id = ?");
        $stmt->bind_param('i', $property_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Property deleted']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete']);
        }
        exit;
    }
    
    if ($action === 'toggle_featured') {
        $stmt = $conn->prepare("SELECT * FROM properties WHERE id = ? AND admin_id = ?");
        $stmt->bind_param('ii', $property_id, $admin_id);
        $stmt->execute();
        $property = $stmt->get_result()->fetch_assoc();
        
        if (!$property) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }
        
        $stmt = $conn->prepare("UPDATE properties SET is_featured = NOT is_featured WHERE id = ?");
        $stmt->bind_param('i', $property_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Featured status updated']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update']);
        }
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

// GET request - fetch data and display page
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM properties WHERE admin_id = ?");
$stmt->bind_param('i', $admin_id);
$stmt->execute();
$total_properties = $stmt->get_result()->fetch_assoc()['count'];
$total_pages = ceil($total_properties / $limit);

$stmt = $conn->prepare("SELECT * FROM properties WHERE admin_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param('iii', $admin_id, $limit, $offset);
$stmt->execute();
$properties = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

foreach ($properties as &$property) {
    $stmt = $conn->prepare("SELECT * FROM property_images WHERE property_id = ?");
    $stmt->bind_param('i', $property['id']);
    $stmt->execute();
    $property['images'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
unset($property);

$remaining_time = SESSION_TIMEOUT_MINUTES * 60;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sukhdham</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <?php include __DIR__ . '/components/navbar.php'; ?>
    
    <div class="max-w-7xl mx-auto px-4 py-6 sm:py-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:justify-between sm:items-center mb-6 sm:mb-8">
            <div class="min-w-0">
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-800 break-words">Dashboard</h1>
                <p class="text-sm sm:text-base text-gray-600">Manage your properties</p>
            </div>
            <a href="create.php" 
               class="inline-flex justify-center bg-blue-600 text-white px-5 sm:px-6 py-2 rounded-lg font-semibold hover:bg-blue-700 w-full sm:w-auto">
                + Add Property
            </a>
        </div>
        
        <!-- Stats -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6 mb-6 sm:mb-8">
            <div class="bg-white rounded-lg shadow p-4 sm:p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Total Properties</p>
                        <p class="text-2xl sm:text-3xl font-bold text-gray-800 break-words"><?php echo $total_properties; ?></p>
                    </div>
                    <div class="text-3xl sm:text-4xl text-blue-600">📊</div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 sm:p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Featured Properties</p>
                        <p class="text-2xl sm:text-3xl font-bold text-gray-800 break-words">
                            <?php echo count(array_filter($properties, function($p) { return $p['is_featured']; })); ?>
                        </p>
                    </div>
                    <div class="text-3xl sm:text-4xl text-yellow-600">⭐</div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 sm:p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Session Expires In</p>
                        <p class="text-2xl sm:text-3xl font-bold text-gray-800" id="sessionTime">15:00</p>
                    </div>
                    <div class="text-3xl sm:text-4xl text-red-600">⏱️</div>
                </div>
            </div>
        </div>
        
        <!-- Properties List -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <?php if (!empty($properties)): ?>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px]">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Title</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Address</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Price</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Images</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Featured</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($properties as $property): ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?php echo htmlspecialchars($property['title']); ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600">
                                        <?php echo htmlspecialchars($property['address']); ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?php echo $property['price'] ? '₹' . number_format($property['price'], 2) : '-'; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600">
                                        <?php echo count($property['images']); ?>/10
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <button onclick="toggleFeatured(<?php echo $property['id']; ?>)" 
                                                class="<?php echo $property['is_featured'] ? 'text-yellow-600' : 'text-gray-400'; ?> hover:text-yellow-600 text-xl">
                                            <?php echo $property['is_featured'] ? '★' : '☆'; ?>
                                        </button>
                                    </td>
                                    <td class="px-6 py-4 text-sm space-x-2">
                                        <a href="edit.php?id=<?php echo $property['id']; ?>" 
                                           class="text-blue-600 hover:text-blue-800">Edit</a>
                                        <button onclick="deleteProperty(<?php echo $property['id']; ?>)" 
                                                class="text-red-600 hover:text-red-800">Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="px-4 sm:px-6 py-4 flex flex-col gap-3 sm:flex-row sm:justify-between sm:items-center">
                    <p class="text-sm text-gray-600">
                        Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>" 
                               class="px-3 py-1 bg-gray-200 text-gray-800 rounded hover:bg-gray-300">Previous</a>
                        <?php endif; ?>
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>" 
                               class="px-3 py-1 bg-gray-200 text-gray-800 rounded hover:bg-gray-300">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="p-8 sm:p-12 text-center">
                    <p class="text-gray-600 text-base sm:text-lg mb-4">You haven't added any properties yet.</p>
                    <a href="create.php" 
                       class="text-blue-600 hover:text-blue-800 font-semibold">Add your first property →</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        let sessionTime = <?php echo $remaining_time; ?>;
        const sessionDisplay = document.getElementById('sessionTime');
        
        setInterval(() => {
            sessionTime--;
            if (sessionTime <= 0) {
                window.location.href = 'logout.php';
            }
            const minutes = Math.floor(sessionTime / 60);
            const seconds = sessionTime % 60;
            sessionDisplay.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
        }, 1000);
        
        function deleteProperty(id) {
            if (!confirm('Are you sure you want to delete this property?')) return;
            
            fetch('dashboard.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'action=delete&id=' + id
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message);
                }
            });
        }
        
        function toggleFeatured(id) {
            fetch('dashboard.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'action=toggle_featured&id=' + id
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message);
                }
            });
        }
    </script>
</body>
</html>

<?php $conn->close(); ?>
