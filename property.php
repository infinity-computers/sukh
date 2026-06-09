<?php
session_start();

require_once __DIR__ . '/config/database.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

$property_id = intval($_GET['id'] ?? 0);
if ($property_id <= 0) {
    header('Location: properties.php');
    exit;
}

$booking_success = '';
$booking_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'book_visit') {
    $form_property_id = intval($_POST['property_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $visit_date = trim($_POST['visit_date'] ?? '');
    $visit_time = trim($_POST['visit_time'] ?? '');
    $message = trim($_POST['message'] ?? '');

    $stmt = $conn->prepare("SELECT id, property_type, category, property_status, booking_enabled FROM properties WHERE id = ? AND status = 'active'");
    $stmt->bind_param('i', $form_property_id ?: $property_id);
    $stmt->execute();
    $booking_property = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$booking_property) {
        $booking_error = 'Property not found.';
    } elseif (empty($booking_property['booking_enabled']) || $booking_property['property_status'] !== 'available') {
        $booking_error = 'Booking is currently disabled for this property.';
    } elseif ($name === '' || $phone === '' || $email === '' || $visit_date === '' || $visit_time === '') {
        $booking_error = 'Please fill in all required booking fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $booking_error = 'Please enter a valid email address.';
    } else {
        $today = new DateTime('today');
        $selectedDate = DateTime::createFromFormat('Y-m-d', $visit_date);

        if (!$selectedDate || $selectedDate < $today) {
            $booking_error = 'Please choose a future visit date.';
        } else {
            $stmt = $conn->prepare("INSERT INTO property_bookings (property_id, name, phone, email, visit_date, visit_time, message) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('issssss', $property_id, $name, $phone, $email, $visit_date, $visit_time, $message);

            if ($stmt->execute()) {
                $booking_success = 'Your site visit request has been submitted. We will contact you soon to confirm the schedule.';
            } else {
                $booking_error = 'Unable to save your booking right now. Please try again.';
            }
            $stmt->close();
        }
    }
}

$stmt = $conn->prepare("SELECT * FROM properties WHERE id = ? AND status = 'active'");
$stmt->bind_param('i', $property_id);
$stmt->execute();
$property = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$property) {
    header('Location: properties.php');
    exit;
}

$stmt = $conn->prepare("SELECT * FROM property_images WHERE property_id = ? ORDER BY is_primary DESC, id ASC");
$stmt->bind_param('i', $property_id);
$stmt->execute();
$images = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$primary_image = null;
foreach ($images as $img) {
    if (!empty($img['is_primary'])) {
        $primary_image = $img;
        break;
    }
}
if (!$primary_image && !empty($images)) {
    $primary_image = $images[0];
}
$can_book = !empty($property['booking_enabled']) && ($property['property_status'] ?? 'available') === 'available';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Property Details - Sukhdham Estate Agency</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .property-detail {
            padding: 2rem 0 4rem;
        }
        .detail-shell {
            display: grid;
            grid-template-columns: 1.5fr 0.85fr;
            gap: 1.5rem;
            align-items: start;
        }
        .media-card,
        .info-card,
        .booking-panel,
        .message-card {
            background: #fff;
            border-radius: 22px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
        }
        .media-card {
            padding: 1rem;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            margin-bottom: 1rem;
            color: #475569;
            font-weight: 600;
        }
        .back-link:hover { color: #0f172a; }
        .gallery-main {
            width: 100%;
            height: 460px;
            object-fit: cover;
            border-radius: 18px;
            margin-bottom: 0.85rem;
            background: #f8fafc;
        }
        .gallery-thumbs {
            display: flex;
            gap: 0.6rem;
            flex-wrap: wrap;
        }
        .gallery-thumb {
            width: 104px;
            height: 74px;
            object-fit: cover;
            border-radius: 12px;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.25s ease;
        }
        .gallery-thumb:hover,
        .gallery-thumb.active {
            border-color: #c2410c;
            transform: translateY(-2px);
        }
        .info-card {
            padding: 1.5rem;
        }
        .badge-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        .pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: #eff6ff;
            color: #1d4ed8;
            padding: 0.4rem 0.8rem;
            border-radius: 999px;
            font-size: 0.86rem;
            font-weight: 700;
        }
        .pill.warn {
            background: #fff7ed;
            color: #c2410c;
        }
        .price {
            font-size: clamp(1.8rem, 4vw, 2.5rem);
            font-weight: 800;
            color: #c2410c;
            margin-bottom: 1rem;
        }
        .description {
            color: #475569;
            line-height: 1.8;
            font-size: 0.98rem;
        }
        .booking-panel {
            padding: 1.5rem;
            position: sticky;
            top: 1.5rem;
        }
        .booking-panel h3,
        .message-card h3 {
            margin-bottom: 0.5rem;
            color: #0f172a;
        }
        .booking-panel p,
        .message-card p {
            color: #64748b;
            margin-bottom: 1rem;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.85rem;
        }
        .booking-panel label {
            display: block;
            font-weight: 600;
            font-size: 0.92rem;
            color: #334155;
            margin-bottom: 0.35rem;
        }
        .booking-panel input,
        .booking-panel textarea {
            width: 100%;
            padding: 0.8rem 0.9rem;
            border-radius: 12px;
            border: 1px solid #dbe3ec;
            background: #fff;
            font-family: inherit;
        }
        .booking-panel textarea {
            min-height: 120px;
            resize: vertical;
        }
        .booking-panel input:focus,
        .booking-panel textarea:focus {
            outline: none;
            border-color: #c2410c;
            box-shadow: 0 0 0 4px rgba(194, 65, 12, 0.08);
        }
        .booking-note {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 1rem;
            margin-bottom: 1rem;
            color: #475569;
            font-size: 0.92rem;
        }
        .message-card {
            padding: 1rem 1.2rem;
            margin-bottom: 1rem;
        }
        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.66);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            z-index: 2000;
        }
        .modal-backdrop.open {
            display: flex;
        }
        .modal {
            width: 100%;
            max-width: 680px;
            background: #fff;
            border-radius: 22px;
            padding: 1.25rem;
            box-shadow: 0 24px 80px rgba(15, 23, 42, 0.35);
            max-height: calc(100vh - 2rem);
            overflow: auto;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            align-items: center;
            margin-bottom: 1rem;
        }
        .modal-close {
            border: 0;
            background: #e2e8f0;
            color: #0f172a;
            width: 40px;
            height: 40px;
            border-radius: 999px;
            font-size: 1.1rem;
            cursor: pointer;
        }
        .modal-close:hover { background: #cbd5e1; }
        .modal .btn { width: 100%; text-align: center; }
        .stack { display: grid; gap: 1rem; }
        @media (max-width: 900px) {
            .detail-shell { grid-template-columns: 1fr; }
            .booking-panel { position: static; }
        }
        @media (max-width: 640px) {
            .gallery-main { height: 280px; }
            .gallery-thumb { width: calc(50% - 0.3rem); }
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/components/navbar.php'; ?>

    <main>
        <div class="container property-detail">
            <a href="properties.php" class="back-link">← Back to Properties</a>

            <?php if ($booking_success): ?>
                <div class="message-card">
                    <h3>Booking Confirmed</h3>
                    <p><?php echo htmlspecialchars($booking_success); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($booking_error): ?>
                <div class="message-card" style="border-left: 4px solid #dc2626;">
                    <h3 style="color:#dc2626;">Booking Error</h3>
                    <p><?php echo htmlspecialchars($booking_error); ?></p>
                </div>
            <?php endif; ?>

            <div class="detail-shell">
                <div class="stack">
                    <section class="media-card">
                        <?php if (!empty($images)): ?>
                            <img id="mainImage" src="<?php echo htmlspecialchars(ltrim($primary_image['image_url'], '/')); ?>" alt="Property image" class="gallery-main">
                            <div class="gallery-thumbs">
                                <?php foreach ($images as $index => $img): ?>
                                    <img src="<?php echo htmlspecialchars(ltrim($img['image_url'], '/')); ?>" alt="Property image <?php echo $index + 1; ?>" class="gallery-thumb <?php echo !empty($img['is_primary']) ? 'active' : ''; ?>" onclick="changeImage('<?php echo htmlspecialchars(ltrim($img['image_url'], '/')); ?>', this)">
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <img src="images/no-image.jpg" alt="No Image" class="gallery-main">
                        <?php endif; ?>
                    </section>

                    <section class="info-card">
                        <div class="badge-row">
                            <span class="pill"><?php echo htmlspecialchars(ucfirst($property['property_type'] ?? 'sell')); ?></span>
                            <span class="pill"><?php echo htmlspecialchars($property['category'] ?? 'Apartment'); ?></span>
                            <span class="pill warn"><?php echo htmlspecialchars(ucfirst($property['property_status'] ?? 'available')); ?></span>
                        </div>

                        <?php if ($property['price']): ?>
                            <div class="price">₹<?php echo number_format($property['price'], 2); ?></div>
                        <?php endif; ?>

                        <?php if (!empty($property['description'])): ?>
                            <div class="description">
                                <?php echo nl2br(htmlspecialchars($property['description'])); ?>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>

                <aside class="booking-panel">
                    <h3>Book Site Visit</h3>
                    <p>Schedule a professional visit for this property in a few quick steps.</p>

                    <?php if ($can_book): ?>
                        <div class="booking-note">
                            Bookings are open for this property. Choose a future date and preferred time.
                        </div>
                        <button type="button" class="btn" onclick="openBookingModal()">Book Visit</button>
                    <?php else: ?>
                        <div class="booking-note">
                            Booking is currently disabled for this property or it is not available.
                        </div>
                    <?php endif; ?>
                </aside>
            </div>
        </div>
    </main>

    <?php if ($can_book): ?>
        <div class="modal-backdrop" id="bookingModal" aria-hidden="true">
            <div class="modal">
                <div class="modal-header">
                    <div>
                        <h3>Book Site Visit</h3>
                        <p style="margin-bottom:0;color:#64748b;">Share your details and we’ll confirm a slot.</p>
                    </div>
                    <button type="button" class="modal-close" onclick="closeBookingModal()">×</button>
                </div>

                <form method="POST" action="" class="stack">
                    <input type="hidden" name="action" value="book_visit">
                    <input type="hidden" name="property_id" value="<?php echo (int) $property_id; ?>">

                    <div class="form-grid">
                        <div>
                            <label>Full Name</label>
                            <input type="text" name="name" required>
                        </div>
                        <div>
                            <label>Phone Number</label>
                            <input type="tel" name="phone" required>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div>
                            <label>Email</label>
                            <input type="email" name="email" required>
                        </div>
                        <div>
                            <label>Preferred Visit Date</label>
                            <input type="date" name="visit_date" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>

                    <div>
                        <label>Preferred Time</label>
                        <input type="time" name="visit_time" required>
                    </div>

                    <div>
                        <label>Message (optional)</label>
                        <textarea name="message" placeholder="Any specific requirements or preferences?"></textarea>
                    </div>

                    <button type="submit" class="btn">Submit Booking Request</button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <script>
        function changeImage(src, thumb) {
            const mainImage = document.getElementById('mainImage');
            if (mainImage) {
                mainImage.src = src;
            }
            document.querySelectorAll('.gallery-thumb').forEach(t => t.classList.remove('active'));
            thumb.classList.add('active');
        }

        function openBookingModal() {
            const modal = document.getElementById('bookingModal');
            if (modal) {
                modal.classList.add('open');
                modal.setAttribute('aria-hidden', 'false');
            }
        }

        function closeBookingModal() {
            const modal = document.getElementById('bookingModal');
            if (modal) {
                modal.classList.remove('open');
                modal.setAttribute('aria-hidden', 'true');
            }
        }

        const modal = document.getElementById('bookingModal');
        if (modal) {
            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    closeBookingModal();
                }
            });
        }
    </script>

    <?php include __DIR__ . '/components/footer.php'; ?>
    <script src="script.js"></script>
</body>
</html>

<?php $conn->close(); ?>