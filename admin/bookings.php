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
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$count_stmt = $conn->prepare("SELECT COUNT(*) AS count FROM property_bookings pb INNER JOIN properties p ON p.id = pb.property_id WHERE p.admin_id = ?");
$count_stmt->bind_param('i', $admin_id);
$count_stmt->execute();
$total_bookings = (int) ($count_stmt->get_result()->fetch_assoc()['count'] ?? 0);
$count_stmt->close();

$total_pages = max(1, (int) ceil($total_bookings / $limit));

$stmt = $conn->prepare("SELECT pb.*, p.property_type, p.category, p.property_status, p.price FROM property_bookings pb INNER JOIN properties p ON p.id = pb.property_id WHERE p.admin_id = ? ORDER BY pb.created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param('iii', $admin_id, $limit, $offset);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Property Bookings - Sukhdham</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <?php include __DIR__ . '/components/navbar.php'; ?>

    <div class="max-w-7xl mx-auto px-4 py-6 sm:py-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:justify-between sm:items-center mb-6 sm:mb-8">
            <div class="min-w-0">
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-800 break-words">Property Bookings</h1>
                <p class="text-sm sm:text-base text-gray-600">All site visit requests submitted by users</p>
            </div>
            <a href="dashboard.php" class="inline-flex justify-center bg-gray-200 text-gray-800 px-5 sm:px-6 py-2 rounded-lg font-semibold hover:bg-gray-300 w-full sm:w-auto">← Back to Dashboard</a>
        </div>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <?php if (!empty($bookings)): ?>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[980px]">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Customer</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Contact</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Property</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Visit Slot</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Message</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Booked At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($booking['name']); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($booking['email']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-700"><?php echo htmlspecialchars($booking['phone']); ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-700">
                                        <div class="font-medium"><?php echo htmlspecialchars(ucfirst($booking['property_type']) . ' / ' . $booking['category']); ?></div>
                                        <div class="text-xs text-gray-500">Status: <?php echo htmlspecialchars(ucfirst($booking['property_status'])); ?></div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-700">
                                        <div><?php echo htmlspecialchars($booking['visit_date']); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($booking['visit_time']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-700 max-w-[300px]">
                                        <?php echo htmlspecialchars($booking['message'] ?: '-'); ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-700"><?php echo htmlspecialchars($booking['created_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="px-4 sm:px-6 py-4 flex flex-col gap-3 sm:flex-row sm:justify-between sm:items-center">
                    <p class="text-sm text-gray-600">Page <?php echo $page; ?> of <?php echo $total_pages; ?></p>
                    <div class="flex flex-wrap gap-2">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>" class="px-3 py-1 bg-gray-200 text-gray-800 rounded hover:bg-gray-300">Previous</a>
                        <?php endif; ?>
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>" class="px-3 py-1 bg-gray-200 text-gray-800 rounded hover:bg-gray-300">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="p-8 sm:p-12 text-center">
                    <p class="text-gray-600 text-base sm:text-lg">No bookings have been submitted yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php $conn->close(); ?>