<?php
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/utils/PropertyHelpers.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

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

    $stmt = $conn->prepare('SELECT id, property_type, category, property_status, booking_enabled FROM properties WHERE id = ? AND status = "active"');
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
            $stmt = $conn->prepare('INSERT INTO property_bookings (property_id, name, phone, email, visit_date, visit_time, message) VALUES (?, ?, ?, ?, ?, ?, ?)');
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

$stmt = $conn->prepare('SELECT * FROM properties WHERE id = ? AND status = "active"');
$stmt->bind_param('i', $property_id);
$stmt->execute();
$property = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$property) {
    header('Location: properties.php');
    exit;
}

$stmt = $conn->prepare('SELECT * FROM property_images WHERE property_id = ? ORDER BY is_primary DESC, id ASC');
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

$amenities = sukhdham_decode_property_amenities($property['amenities'] ?? '');
$can_book = !empty($property['booking_enabled']) && ($property['property_status'] ?? 'available') === 'available';

$contact_phone = '+91 9376739237';
$contact_whatsapp = '919376739237';
$contact_email = 'bharuch@sukhdham.in';
$contact_location = 'Zadeshwar, Bharuch, Gujarat';
$contact_phone_href = preg_replace('/\D+/', '', $contact_phone);

$priceLabel = !empty($property['price']) ? '₹' . number_format((float) $property['price'], 2) : 'Price on request';
$areaValue = !empty($property['carpet_area']) ? number_format((float) $property['carpet_area'], 0) . ' sq ft carpet' : (!empty($property['area_sqft']) ? number_format((float) $property['area_sqft'], 0) . ' sq ft' : '-');
$galleryImages = !empty($images) ? $images : [];
$mainImageSrc = !empty($primary_image['image_url']) ? ltrim($primary_image['image_url'], '/') : 'images/no-image.jpg';
$hasCommercialDetails = !empty($property['washroom_available']) || !empty($property['pantry_available']) || !empty($property['cabin_count']) || !empty($property['parking_spaces']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($property['title'] ?? 'Property Details'); ?> - Sukhdham Estate Agency</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .page-shell {
            padding: 2rem 0 4rem;
        }
        .detail-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.5fr) minmax(300px, 0.75fr);
            gap: 1.4rem;
            align-items: start;
        }
        .panel,
        .booking-panel,
        .contact-panel,
        .modal,
        .message-card {
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 24px;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.08);
        }
        .panel {
            padding: 1.1rem;
        }
        .panel + .panel {
            margin-top: 1.2rem;
        }
        .hero-card {
            position: relative;
            overflow: hidden;
        }
        .gallery-main {
            width: 100%;
            height: 520px;
            object-fit: cover;
            border-radius: 20px;
            background: #f8fafc;
        }
        .gallery-thumbs {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 0.6rem;
            margin-top: 0.85rem;
        }
        .gallery-thumb {
            width: 100%;
            aspect-ratio: 1.4 / 1;
            object-fit: cover;
            border-radius: 14px;
            border: 2px solid transparent;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .gallery-thumb:hover,
        .gallery-thumb.active {
            border-color: #c2410c;
            transform: translateY(-2px);
        }
        .summary-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 0.55rem;
            margin-bottom: 0.9rem;
        }
        .pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.4rem 0.75rem;
            border-radius: 999px;
            font-size: 0.84rem;
            font-weight: 700;
            background: #eff6ff;
            color: #1d4ed8;
        }
        .pill.warn {
            background: #fff7ed;
            color: #c2410c;
        }
        .price {
            font-size: clamp(1.9rem, 4vw, 2.7rem);
            font-weight: 800;
            color: #c2410c;
            margin: 0.2rem 0 0.8rem;
        }
        .headline {
            font-size: clamp(1.55rem, 4vw, 2.1rem);
            font-weight: 800;
            color: #0f172a;
            line-height: 1.2;
        }
        .subline {
            color: #64748b;
            margin-top: 0.5rem;
        }
        .summary-grid,
        .spec-grid,
        .amenity-grid,
        .contact-grid {
            display: grid;
            gap: 0.8rem;
        }
        .summary-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            margin-top: 1rem;
        }
        .summary-item,
        .spec-item,
        .amenity-item {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 0.8rem 0.9rem;
        }
        .summary-item strong,
        .spec-item strong {
            display: block;
            color: #0f172a;
            margin-bottom: 0.15rem;
        }
        .section-title {
            font-size: 1.05rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 0.8rem;
        }
        .description {
            color: #475569;
            line-height: 1.85;
        }
        .spec-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .amenity-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
        .amenity-item {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            font-size: 0.92rem;
            color: #334155;
        }
        .amenity-icon {
            width: 34px;
            height: 34px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #eff6ff;
            color: #1d4ed8;
            font-weight: 800;
            flex-shrink: 0;
        }
        .booking-panel {
            padding: 1.2rem;
            position: sticky;
            top: 1.2rem;
        }
        .booking-panel .btn,
        .contact-panel .btn {
            width: 100%;
            text-align: center;
        }
        .contact-actions {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.7rem;
            margin: 0.85rem 0;
        }
        .booking-note {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 0.95rem;
            color: #475569;
            font-size: 0.92rem;
            margin-bottom: 1rem;
        }
        .message-card {
            padding: 1rem 1.1rem;
            margin-bottom: 1rem;
        }
        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.68);
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
            max-width: 760px;
            padding: 1.2rem;
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
            cursor: pointer;
        }
        .modal-close:hover {
            background: #cbd5e1;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.9rem;
        }
        .modal input,
        .modal select,
        .contact-panel input,
        .contact-panel textarea {
            width: 100%;
            padding: 0.82rem 0.92rem;
            border-radius: 12px;
            border: 1px solid #dbe3ec;
            background: #fff;
            font-family: inherit;
        }
        .modal textarea,
        .contact-panel textarea {
            min-height: 118px;
            resize: vertical;
        }
        .sticky-stack {
            display: grid;
            gap: 1rem;
        }
        .contact-panel {
            padding: 1.2rem;
        }
        .contact-cta-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        .contact-cta-grid .btn {
            min-height: 52px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            border-radius: 16px;
            font-weight: 800;
            letter-spacing: 0.01em;
            box-shadow: 0 14px 28px rgba(15, 23, 42, 0.12);
        }
        .contact-cta-grid .call-btn {
            background: linear-gradient(135deg, #0f172a, #334155);
        }
        .contact-cta-grid .whatsapp-btn {
            background: linear-gradient(135deg, #16a34a, #0f9d58);
        }
        .contact-cta-grid .btn:hover {
            transform: translateY(-2px);
        }
        .contact-note {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 0.95rem;
            color: #475569;
            font-size: 0.92rem;
            line-height: 1.7;
            margin-top: 0.85rem;
        }
        .detail-section {
            margin-top: 1.2rem;
        }
        @media (max-width: 1024px) {
            .detail-grid {
                grid-template-columns: 1fr;
            }
            .booking-panel {
                position: static;
            }
        }
        @media (max-width: 700px) {
            .gallery-main {
                height: 300px;
            }
            .gallery-thumbs,
            .summary-grid,
            .spec-grid,
            .amenity-grid,
            .contact-cta-grid,
            .contact-actions,
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/components/navbar.php'; ?>

    <main>
        <div class="container page-shell">
            <a href="properties.php" class="back-link" style="display:inline-flex;align-items:center;gap:0.35rem;color:#475569;font-weight:700;margin-bottom:1rem;">← Back to Properties</a>

            <?php if ($booking_success): ?>
                <div class="message-card">
                    <h3 style="margin-bottom:0.25rem;color:#0f172a;">Booking Confirmed</h3>
                    <p style="color:#475569;margin:0;"><?php echo htmlspecialchars($booking_success); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($booking_error): ?>
                <div class="message-card" style="border-left:4px solid #dc2626;">
                    <h3 style="margin-bottom:0.25rem;color:#dc2626;">Booking Error</h3>
                    <p style="color:#475569;margin:0;"><?php echo htmlspecialchars($booking_error); ?></p>
                </div>
            <?php endif; ?>

            <div class="detail-grid">
                <div class="sticky-stack">
                    <section class="panel hero-card">
                        <img id="mainImage" src="<?php echo htmlspecialchars($mainImageSrc); ?>" alt="Property image" class="gallery-main">
                        <?php if (!empty($galleryImages)): ?>
                            <div class="gallery-thumbs">
                                <?php foreach ($galleryImages as $index => $img): ?>
                                    <?php $thumbSrc = ltrim($img['image_url'], '/'); ?>
                                    <img src="<?php echo htmlspecialchars($thumbSrc); ?>" alt="Property image <?php echo $index + 1; ?>" class="gallery-thumb <?php echo !empty($img['is_primary']) ? 'active' : ''; ?>" onclick="changeImage('<?php echo htmlspecialchars($thumbSrc, ENT_QUOTES); ?>', this)">
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="panel">
                        <div class="summary-bar">
                            <span class="pill"><?php echo htmlspecialchars(ucfirst($property['property_type'] ?? 'sell')); ?></span>
                            <span class="pill"><?php echo htmlspecialchars($property['category'] ?? 'Apartment'); ?></span>
                            <span class="pill"><?php echo htmlspecialchars($property['furnishing_type'] ?? 'Unfurnished'); ?></span>
                            <span class="pill warn"><?php echo htmlspecialchars(ucfirst($property['property_status'] ?? 'available')); ?></span>
                        </div>

                        <div class="headline"><?php echo htmlspecialchars($property['title'] ?? 'Property'); ?></div>
                        <div class="subline"><?php echo htmlspecialchars($property['address'] ?? ''); ?></div>
                        <div class="price"><?php echo htmlspecialchars($priceLabel); ?></div>

                        <div class="summary-grid">
                            <div class="summary-item"><strong>Bedrooms / Bathrooms</strong><?php echo (int) ($property['bedrooms'] ?? 0); ?> BHK / <?php echo (int) ($property['bathrooms'] ?? 0); ?> Bath</div>
                            <div class="summary-item"><strong>Area Size</strong><?php echo htmlspecialchars($areaValue); ?></div>
                            <div class="summary-item"><strong>Parking</strong><?php echo htmlspecialchars($property['parking'] ?? 'No'); ?></div>
                            <div class="summary-item"><strong>Availability</strong><?php echo htmlspecialchars(ucfirst($property['property_status'] ?? 'available')); ?></div>
                            <div class="summary-item"><strong>Available From</strong><?php echo !empty($property['available_from']) ? htmlspecialchars($property['available_from']) : '-'; ?></div>
                            <div class="summary-item"><strong>Furnishing</strong><?php echo htmlspecialchars($property['furnishing_type'] ?? 'Unfurnished'); ?></div>
                        </div>
                    </section>
                </div>

                <aside class="sticky-stack">
                    <section class="booking-panel">
                        <h3 class="section-title" style="margin-bottom:0.35rem;">Book Site Visit</h3>
                        <p style="color:#64748b;margin-bottom:0.9rem;">Schedule a professional visit with a date and time slot.</p>

                        <?php if ($can_book): ?>
                            <div class="booking-note">Bookings are open for this property. Choose a future date and preferred time slot.</div>
                            <button type="button" class="btn" onclick="openBookingModal()">Book Site Visit</button>
                        <?php else: ?>
                            <div class="booking-note">Booking is currently disabled for this property or it is not available.</div>
                        <?php endif; ?>
                    </section>

                    <section class="contact-panel">
                        <h3 class="section-title" style="margin-bottom:0.35rem;">Contact</h3>
                        <p style="color:#64748b;margin-bottom:0.9rem;">Reach out directly for availability, pricing, or quick inquiries.</p>

                        <div class="contact-cta-grid">
                            <a class="btn call-btn" href="tel:<?php echo htmlspecialchars($contact_phone_href); ?>">Call Sukhdham</a>
                            <a class="btn whatsapp-btn" href="https://wa.me/<?php echo htmlspecialchars($contact_whatsapp); ?>?text=<?php echo urlencode('Hi Sukhdham, I am interested in ' . ($property['title'] ?? 'this property') . ' on ' . $property['address']); ?>" target="_blank" rel="noopener">WhatsApp Sukhdham</a>
                        </div>

                        <form id="inquiryForm" class="detail-section">
                            <div style="display:grid;gap:0.75rem;">
                                <input type="text" id="inquiryName" placeholder="Your name">
                                <input type="tel" id="inquiryPhone" placeholder="Phone number">
                                <textarea id="inquiryMessage" placeholder="Tell us what you need"></textarea>
                                <button type="button" class="btn" onclick="sendInquiry()">Send Inquiry</button>
                            </div>
                        </form>

                        <div class="contact-note">
                            <div><strong style="color:#0f172a;">Office:</strong> <?php echo htmlspecialchars($contact_location); ?></div>
                            <div><strong style="color:#0f172a;">Email:</strong> <?php echo htmlspecialchars($contact_email); ?></div>
                            <div style="margin-top:0.35rem;">Use the buttons above to reach Sukhdham Estate directly.</div>
                        </div>
                    </section>
                </aside>
            </div>

            <section class="panel detail-section">
                <h3 class="section-title">Detailed Description</h3>
                <div class="description">
                    <?php echo !empty($property['description']) ? nl2br(htmlspecialchars($property['description'])) : 'No description has been added for this property yet.'; ?>
                </div>
            </section>

            <section class="panel detail-section">
                <h3 class="section-title">Property Specifications</h3>
                <div class="spec-grid">
                    <div class="spec-item"><strong>Total Bedrooms</strong><?php echo (int) ($property['bedrooms'] ?? 0); ?></div>
                    <div class="spec-item"><strong>Total Bathrooms</strong><?php echo (int) ($property['bathrooms'] ?? 0); ?></div>
                    <div class="spec-item"><strong>Balconies</strong><?php echo (int) ($property['balconies'] ?? 0); ?></div>
                    <div class="spec-item"><strong>Floor Number</strong><?php echo (int) ($property['floor_number'] ?? 0); ?></div>
                    <div class="spec-item"><strong>Total Floors</strong><?php echo (int) ($property['total_floors'] ?? 0); ?></div>
                    <div class="spec-item"><strong>Carpet Area</strong><?php echo !empty($property['carpet_area']) ? number_format((float) $property['carpet_area'], 0) . ' sq ft' : '-'; ?></div>
                    <div class="spec-item"><strong>Parking</strong><?php echo htmlspecialchars($property['parking'] ?? 'No'); ?></div>
                    <div class="spec-item"><strong>Water Supply</strong><?php echo htmlspecialchars($property['water_supply'] ?? '24 Hours'); ?></div>
                    <div class="spec-item"><strong>Electricity Backup</strong><?php echo htmlspecialchars($property['electricity_backup'] ?? 'No'); ?></div>
                    <div class="spec-item"><strong>Facing Direction</strong><?php echo htmlspecialchars($property['facing_direction'] ?? 'East'); ?></div>
                    <div class="spec-item"><strong>Tenant Preferred</strong><?php echo htmlspecialchars($property['tenant_preferred'] ?? 'Any'); ?></div>
                    <div class="spec-item"><strong>Lease Duration</strong><?php echo !empty($property['lease_duration']) ? htmlspecialchars($property['lease_duration']) : '-'; ?></div>
                    <div class="spec-item"><strong>Available Immediately</strong><?php echo htmlspecialchars($property['available_immediately'] ?? 'No'); ?></div>
                    <div class="spec-item"><strong>Bills Included</strong><?php echo htmlspecialchars($property['bills_included'] ?? 'No'); ?></div>
                    <div class="spec-item"><strong>Pets Allowed</strong><?php echo htmlspecialchars($property['pets_allowed'] ?? 'No'); ?></div>
                    <div class="spec-item"><strong>Washroom Available</strong><?php echo htmlspecialchars($property['washroom_available'] ?? 'No'); ?></div>
                    <div class="spec-item"><strong>Pantry Available</strong><?php echo htmlspecialchars($property['pantry_available'] ?? 'No'); ?></div>
                    <div class="spec-item"><strong>Cabin Count</strong><?php echo (int) ($property['cabin_count'] ?? 0); ?></div>
                    <div class="spec-item"><strong>Parking Spaces</strong><?php echo (int) ($property['parking_spaces'] ?? 0); ?></div>
                </div>
            </section>

            <section class="panel detail-section">
                <h3 class="section-title">Amenities</h3>
                <?php if (!empty($amenities)): ?>
                    <div class="amenity-grid">
                        <?php foreach ($amenities as $amenity): ?>
                            <div class="amenity-item">
                                <span class="amenity-icon"><?php echo htmlspecialchars(sukhdham_property_amenity_icon($amenity)); ?></span>
                                <span><?php echo htmlspecialchars($amenity); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color:#64748b;margin:0;">No amenities were added for this property yet.</p>
                <?php endif; ?>
            </section>

        </div>
    </main>

    <?php if ($can_book): ?>
        <div class="modal-backdrop" id="bookingModal" aria-hidden="true">
            <div class="modal">
                <div class="modal-header">
                    <div>
                        <h3 style="margin-bottom:0.25rem;color:#0f172a;font-size:1.2rem;font-weight:800;">Book Site Visit</h3>
                        <p style="margin:0;color:#64748b;">Share your details and select a preferred slot.</p>
                    </div>
                    <button type="button" class="modal-close" onclick="closeBookingModal()">×</button>
                </div>

                <form method="POST" action="" class="detail-section" style="margin-top:0;">
                    <input type="hidden" name="action" value="book_visit">
                    <input type="hidden" name="property_id" value="<?php echo (int) $property_id; ?>">

                    <div class="form-grid">
                        <div>
                            <label style="display:block;font-weight:700;margin-bottom:0.35rem;color:#334155;">Full Name</label>
                            <input type="text" name="name" required>
                        </div>
                        <div>
                            <label style="display:block;font-weight:700;margin-bottom:0.35rem;color:#334155;">Phone Number</label>
                            <input type="tel" name="phone" required>
                        </div>
                    </div>

                    <div class="form-grid" style="margin-top:0.9rem;">
                        <div>
                            <label style="display:block;font-weight:700;margin-bottom:0.35rem;color:#334155;">Email</label>
                            <input type="email" name="email" required>
                        </div>
                        <div>
                            <label style="display:block;font-weight:700;margin-bottom:0.35rem;color:#334155;">Preferred Visit Date</label>
                            <input type="date" name="visit_date" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>

                    <div style="margin-top:0.9rem;">
                        <label style="display:block;font-weight:700;margin-bottom:0.35rem;color:#334155;">Preferred Time Slot</label>
                        <select name="visit_time" required>
                            <option value="">Select a time slot</option>
                            <option value="09:00:00">09:00 AM - 10:00 AM</option>
                            <option value="10:00:00">10:00 AM - 11:00 AM</option>
                            <option value="11:00:00">11:00 AM - 12:00 PM</option>
                            <option value="13:00:00">01:00 PM - 02:00 PM</option>
                            <option value="15:00:00">03:00 PM - 04:00 PM</option>
                            <option value="17:00:00">05:00 PM - 06:00 PM</option>
                        </select>
                    </div>

                    <div style="margin-top:0.9rem;">
                        <label style="display:block;font-weight:700;margin-bottom:0.35rem;color:#334155;">Message (optional)</label>
                        <textarea name="message" placeholder="Any specific requirements or preferences?"></textarea>
                    </div>

                    <button type="submit" class="btn" style="margin-top:1rem;">Submit Booking Request</button>
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

        function sendInquiry() {
            const name = document.getElementById('inquiryName').value.trim();
            const phone = document.getElementById('inquiryPhone').value.trim();
            const message = document.getElementById('inquiryMessage').value.trim();
            const subject = encodeURIComponent('Property inquiry: <?php echo addslashes($property['title'] ?? 'Property'); ?>');
            const body = encodeURIComponent(`Name: ${name || '-'}%0APhone: ${phone || '-'}%0AProperty: <?php echo addslashes($property['title'] ?? 'Property'); ?>%0AAddress: <?php echo addslashes($property['address'] ?? ''); ?>%0A%0AMessage: ${message || '-'}`);
            window.open(`https://wa.me/<?php echo htmlspecialchars($contact_whatsapp); ?>?text=${body}`, '_blank', 'noopener');
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